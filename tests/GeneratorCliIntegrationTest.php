<?php

declare(strict_types=1);

use Laf\Database\Db;
use Laf\Generator\Cli;
use Laf\Util\Settings;
use PHPUnit\Framework\TestCase;

/**
 * `laf-generate` (Laf\Generator\Cli) against the integration database: the dry run, a real run that writes
 * classes and installs the page of a new table, the keep/force rules on a second run, the labels file and
 * the exit code when no table matches. Uses the tables BaseObjectTest creates.
 */
class GeneratorCliIntegrationTest extends TestCase
{
    private static bool $dbAvailable = false;
    private string $lib = '';
    private string $bootstrap = '';

    public static function setUpBeforeClass(): void
    {
        try {
            Db::getInstance();
            self::$dbAvailable = Db::getInstance()->tableExists('dummy_table');
        } catch (\Exception $e) {
            self::$dbAvailable = false;
        }
    }

    protected function setUp(): void
    {
        if (!self::$dbAvailable) {
            $this->markTestSkipped('No database connection or no dummy_table (run BaseObjectTest first)');
        }
        $this->lib = sys_get_temp_dir() . '/laf_cli_' . bin2hex(random_bytes(4));
        mkdir($this->lib);
        // the bootstrap only renames the package: the database settings come from tests/bootstrap.php
        $this->bootstrap = $this->lib . '/bootstrap.php';
        file_put_contents($this->bootstrap, "<?php\n\\Laf\\Util\\Settings::set('project.package_name', 'LafCliGen');\n");
    }

    protected function tearDown(): void
    {
        Settings::set('project.package_name', 'LafShell');
        if ($this->lib !== '' && is_dir($this->lib)) {
            self::removeDir($this->lib);
        }
    }

    private static function removeDir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? self::removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * @param string[] $args
     * @return array{int, string, string}
     */
    private function runCli(array $args): array
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        $code = Cli::run(array_merge(['laf-generate', '--bootstrap=' . $this->bootstrap, '--lib=' . $this->lib], $args), $out, $err);
        rewind($out);
        rewind($err);
        return [$code, (string)stream_get_contents($out), (string)stream_get_contents($err)];
    }

    /** @test */
    public function the_dry_run_lists_the_files_without_writing_them(): void
    {
        [$code, $out, $err] = $this->runCli(['--list', '--tables=dummy_table,code_table', '--install-pages']);
        $this->assertSame(Cli::EXIT_OK, $code, $err);
        $this->assertStringContainsString("dummy_table" . PHP_EOL, $out);
        $this->assertStringContainsString('write   ' . $this->lib . '/LafCliGen/Base/BaseDummyTable.php', $out);
        $this->assertStringContainsString('write   ' . $this->lib . '/LafCliGen/CodeTable.php', $out);
        $this->assertStringContainsString('write   ' . $this->lib . '/generator_cache/pages/dummy_table.page', $out);
        $this->assertStringContainsString('install ' . $this->lib . '/pages/dummy_table.page', $out);
        $this->assertStringNotContainsString('link_table', $out);
        $this->assertDirectoryDoesNotExist($this->lib . '/LafCliGen');
    }

    /** @test */
    public function a_run_writes_the_classes_installs_the_new_page_and_keeps_the_application_files_after(): void
    {
        [$code, $out, $err] = $this->runCli(['--tables=dummy_table', '--install-pages', '--quiet']);
        $this->assertSame(Cli::EXIT_OK, $code, $err);
        $this->assertSame('', $err);
        $this->assertStringContainsString('Done: 1 table, 2 class files, 1 page, 1 page installed, 0 warnings', $out);
        $this->assertStringNotContainsString('Processed table', $out, 'quiet');

        $base = $this->lib . '/LafCliGen/Base/BaseDummyTable.php';
        $class = $this->lib . '/LafCliGen/DummyTable.php';
        $page = $this->lib . '/generator_cache/pages/dummy_table.page';
        $live = $this->lib . '/pages/dummy_table.page';
        foreach ([$base, $class, $page, $live] as $file) {
            $this->assertFileExists($file);
        }
        $this->assertStringContainsString('namespace LafCliGen\Base;', (string)file_get_contents($base));
        $this->assertSame(file_get_contents($page), file_get_contents($live));

        // the application edits its page and model; a second run keeps both and rewrites the Base class
        file_put_contents($live, "<?php // my page\n");
        file_put_contents($class, "<?php // my model\n");
        file_put_contents($base, "<?php // stale base\n");
        [$code, $out, $err] = $this->runCli(['--tables=dummy_table', '--install-pages']);
        $this->assertSame(Cli::EXIT_OK, $code, $err);
        $this->assertStringContainsString('Processed table: dummy_table', $out);
        $this->assertStringContainsString('0 pages installed', $out);
        $this->assertSame("<?php // my page\n", file_get_contents($live));
        $this->assertSame("<?php // my model\n", file_get_contents($class));
        $this->assertStringContainsString('namespace LafCliGen\Base;', (string)file_get_contents($base));

        // --force rewrites the model class, never the live page
        [$code, , $err] = $this->runCli(['--tables=dummy_table', '--install-pages', '--force', '-q']);
        $this->assertSame(Cli::EXIT_OK, $code, $err);
        $this->assertStringContainsString('class DummyTable extends', (string)file_get_contents($class));
        $this->assertSame("<?php // my page\n", file_get_contents($live));
    }

    /** @test */
    public function classes_only_pages_only_and_a_labels_file(): void
    {
        [$code, , $err] = $this->runCli(['--tables=code_table', '--classes']);
        $this->assertSame(Cli::EXIT_OK, $code, $err);
        $this->assertFileExists($this->lib . '/LafCliGen/Base/BaseCodeTable.php');
        $this->assertDirectoryDoesNotExist($this->lib . '/generator_cache');
        unlink($this->lib . '/LafCliGen/CodeTable.php');

        $labels = $this->lib . '/labels.json';
        file_put_contents($labels, json_encode(['add-new' => 'Regjistro']));
        [$code, $out, $err] = $this->runCli(['--tables=code_table', '--pages', '--labels=' . $labels]);
        $this->assertSame(Cli::EXIT_OK, $code, $err);
        $this->assertStringContainsString('Done: 1 table, 0 class files, 1 page, 0 pages installed', $out);
        $this->assertStringContainsString('Regjistro', (string)file_get_contents($this->lib . '/generator_cache/pages/code_table.page'));
        $this->assertFileDoesNotExist($this->lib . '/LafCliGen/CodeTable.php', '--pages writes no classes');

        [$code, , $err] = $this->runCli(['--tables=code_table', '--pages', '--labels=' . $this->lib . '/missing.json']);
        $this->assertSame(Cli::EXIT_ERROR, $code);
        $this->assertStringContainsString('Labels file not found', $err);
    }

    /** @test */
    public function no_matching_table_is_a_configuration_error(): void
    {
        [$code, , $err] = $this->runCli(['--tables=nope_*']);
        $this->assertSame(Cli::EXIT_ERROR, $code);
        $this->assertStringContainsString('No table matches', $err);

        [$code, $out, $err] = $this->runCli(['--list', '--exclude=', '--tables=dummy_*']);
        $this->assertSame(Cli::EXIT_OK, $code, $err);
        $this->assertStringContainsString("dummy_table" . PHP_EOL, $out);
    }
}
