<?php

declare(strict_types=1);

use Laf\Generator\Cli;
use Laf\Util\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Laf\Generator\Cli without a database: argument parsing, usage, and the configuration errors that end a run
 * with exit code 2 before anything is generated. A full run is in GeneratorCliIntegrationTest.
 */
class GeneratorCliTest extends TestCase
{
    /** @var string[] */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        putenv('LAF_BOOTSTRAP');
        Settings::set('project.package_name', 'LafShell');   // tests/bootstrap.php value, a bootstrap file above may change it
    }

    private function tempFile(string $content): string
    {
        $path = sys_get_temp_dir() . '/laf_cli_' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($path, $content);
        $this->files[] = $path;
        return $path;
    }

    /**
     * @param string[] $args
     * @return array{int, string, string} exit code, stdout, stderr
     */
    private function runCli(array $args): array
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        $code = Cli::run(array_merge(['laf-generate'], $args), $out, $err);
        rewind($out);
        rewind($err);
        return [$code, (string)stream_get_contents($out), (string)stream_get_contents($err)];
    }

    /** @test */
    public function parse_reads_values_flags_lists_and_short_options(): void
    {
        $options = Cli::parse(['--bootstrap=data/config.php', '--lib', 'app/lib2', '--tables=a, b,inv_*', '--exclude=', '--install-pages', '-f', '-q']);
        $this->assertSame('data/config.php', $options['bootstrap']);
        $this->assertSame('app/lib2', $options['lib']);
        $this->assertSame(['a', 'b', 'inv_*'], $options['tables']);
        $this->assertSame([], $options['exclude'], '--exclude= keeps every table');
        $this->assertTrue($options['install-pages']);
        $this->assertTrue($options['force']);
        $this->assertTrue($options['quiet']);
        $this->assertFalse($options['list']);

        $defaults = Cli::parse([]);
        $this->assertNull($defaults['bootstrap']);
        $this->assertSame(Cli::DEFAULT_LIB, $defaults['lib']);
        $this->assertSame(Cli::DEFAULT_EXCLUDED, $defaults['exclude']);
        $this->assertSame([], $defaults['tables']);
    }

    /** @test */
    public function parse_rejects_unknown_options_missing_values_and_contradictions(): void
    {
        foreach ([
            [['--bogus'], 'Unknown option'],
            [['--tables'], 'needs a value'],
            [['--force=1'], 'takes no value'],
            [['stray'], 'Unexpected argument'],
            [['--classes', '--pages'], 'exclude each other'],
            [['--lib='], 'needs a directory'],
            [['-x'], 'Unexpected argument'],
        ] as [$args, $message]) {
            try {
                Cli::parse($args);
                $this->fail('expected an exception for ' . implode(' ', $args));
            } catch (\InvalidArgumentException $ex) {
                $this->assertStringContainsString($message, $ex->getMessage());
            }
        }
    }

    /** @test */
    public function help_prints_the_usage_and_a_bad_option_exits_with_2(): void
    {
        [$code, $out, $err] = $this->runCli(['--help']);
        $this->assertSame(Cli::EXIT_OK, $code);
        $this->assertStringContainsString('Usage: laf-generate --bootstrap=FILE', $out);
        $this->assertStringContainsString('--install-pages', $out);
        $this->assertSame('', $err);

        [$code, $out, $err] = $this->runCli(['--nope']);
        $this->assertSame(Cli::EXIT_ERROR, $code);
        $this->assertSame('', $out);
        $this->assertStringContainsString('Unknown option: --nope', $err);
    }

    /** @test */
    public function a_missing_or_failing_bootstrap_exits_with_2(): void
    {
        [$code, , $err] = $this->runCli([]);
        $this->assertSame(Cli::EXIT_ERROR, $code);
        $this->assertStringContainsString('No bootstrap file', $err);

        [$code, , $err] = $this->runCli(['--bootstrap=' . sys_get_temp_dir() . '/does-not-exist.php']);
        $this->assertSame(Cli::EXIT_ERROR, $code);
        $this->assertStringContainsString('Bootstrap file not found', $err);

        $failing = $this->tempFile("<?php\nthrow new RuntimeException('boom');\n");
        putenv('LAF_BOOTSTRAP=' . $failing);
        [$code, , $err] = $this->runCli([]);
        $this->assertSame(Cli::EXIT_ERROR, $code, 'LAF_BOOTSTRAP is read when --bootstrap is absent');
        $this->assertStringContainsString('bootstrap file failed: boom', $err);
    }

    /** @test */
    public function a_bootstrap_without_a_package_name_exits_with_2(): void
    {
        $bootstrap = $this->tempFile("<?php\n\\Laf\\Util\\Settings::set('project.package_name', '');\n");
        [$code, , $err] = $this->runCli(['--bootstrap=' . $bootstrap]);
        $this->assertSame(Cli::EXIT_ERROR, $code);
        $this->assertStringContainsString("'project.package_name' is not set", $err);
    }
}
