<?php

use Laf\Util\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Settings::loadFromEnvironment() and loadEnvFile(): LAF_SECTION__KEY variables become dotted keys.
 */
class SettingsEnvironmentTest extends TestCase
{
    private string $envFile;

    protected function setUp(): void
    {
        $this->envFile = sys_get_temp_dir() . '/laf_settings_' . uniqid() . '.env';
        foreach (['database.hostname', 'database.port', 'settings.use_pretty_url', 'project.package_name', 'app.name', 'app.motto', 'app.path'] as $key) {
            Settings::remove($key);
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->envFile);
        $this->setUp();
    }

    /** @test */
    public function prefixed_variables_map_to_dotted_lower_case_keys()
    {
        $loaded = Settings::loadFromEnvironment('LAF_', [
            'LAF_DATABASE__HOSTNAME' => 'db.example',
            'LAF_DATABASE__PORT' => '5432',
            'LAF_SETTINGS__USE_PRETTY_URL' => 'false',
            'LAF_PROJECT__PACKAGE_NAME' => 'MyApp',
            'PATH' => '/usr/bin',
            'LAF_' => 'ignored',
            'LAF_APP__LIST' => ['not', 'scalar'],
        ]);

        $this->assertSame(4, $loaded);
        $this->assertSame('db.example', Settings::get('database.hostname'));
        $this->assertSame('5432', Settings::get('database.port'), 'values stay strings');
        $this->assertSame(5432, Settings::getInt('database.port'));
        $this->assertFalse(Settings::getBool('settings.use_pretty_url', true));
        $this->assertSame('MyApp', Settings::get('project.package_name'));
        $this->assertFalse(Settings::has('path'));
    }

    /** @test */
    public function env_file_lines_are_parsed_with_quotes_comments_and_export()
    {
        file_put_contents($this->envFile, implode("\n", [
            '# database',
            'LAF_DATABASE__HOSTNAME=localhost   # trailing comment',
            "export LAF_APP__NAME='Quoted name'",
            'LAF_APP__MOTTO="Line one\nwith \"quotes\""',
            'LAF_APP__PATH=/var/www/app',
            'not a pair',
            '',
            '9BAD=skipped',
        ]));

        $this->assertSame(4, Settings::loadEnvFile($this->envFile));
        $this->assertSame('localhost', Settings::get('database.hostname'));
        $this->assertSame('Quoted name', Settings::get('app.name'));
        $this->assertSame("Line one\nwith \"quotes\"", Settings::get('app.motto'));
        $this->assertSame('/var/www/app', Settings::get('app.path'));
        $this->assertFalse(Settings::loadEnvFile($this->envFile . '.missing'));
    }

    /** @test */
    public function the_real_environment_wins_over_the_file()
    {
        putenv('LAF_DATABASE__HOSTNAME=from-env');
        try {
            file_put_contents($this->envFile, "LAF_DATABASE__HOSTNAME=from-file\n");
            Settings::loadEnvFile($this->envFile);
            $this->assertSame('from-env', Settings::get('database.hostname'));
        } finally {
            putenv('LAF_DATABASE__HOSTNAME');
        }
    }
}
