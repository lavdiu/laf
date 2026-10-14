<?php

declare(strict_types=1);

namespace Laf\Generator;

use Laf\Database\Db;
use Laf\Util\Settings;

/**
 * The `laf-generate` command: DatabaseGenerator from the command line.
 *
 *   vendor/bin/laf-generate --bootstrap=data/config.php --lib=app/lib [--tables=a,b,inv_*] [--list] ...
 *
 * The bootstrap file is the application's own configuration (it must set Settings `database.*` and
 * `project.package_name`). run() takes an argv array and two streams and returns the exit code
 * (0 success, 1 warnings with --strict, 2 usage or configuration error), so it is testable without a process.
 */
final class Cli
{
    public const EXIT_OK = 0;
    public const EXIT_WARNINGS = 1;
    public const EXIT_ERROR = 2;

    public const DEFAULT_LIB = 'app/lib';
    public const DEFAULT_EXCLUDED = ['audit_log', 'sql_error', 'object_list', 'phinx_*'];

    private const FLAGS = ['classes', 'pages', 'install-pages', 'force', 'list', 'strict', 'quiet', 'help'];
    private const VALUES = ['bootstrap', 'lib', 'tables', 'exclude', 'labels', 'many-to-many'];
    private const SHORT = ['q' => 'quiet', 'h' => 'help', 'l' => 'list', 'f' => 'force'];

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    private bool $quiet = false;

    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    private function __construct($stdout, $stderr)
    {
        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }

    /**
     * Entry point of bin/laf-generate: `exit(Cli::main($argv))`
     * @param string[] $argv
     */
    public static function main(array $argv): int
    {
        return self::run($argv, STDOUT, STDERR);
    }

    /**
     * Runs the command with the given argv (argv[0] is the program name) and returns the exit code
     * @param string[] $argv
     * @param resource $stdout
     * @param resource $stderr
     */
    public static function run(array $argv, $stdout, $stderr): int
    {
        $cli = new self($stdout, $stderr);
        try {
            $options = self::parse(array_slice($argv, 1));
        } catch (\InvalidArgumentException $ex) {
            $cli->error($ex->getMessage() . ' (run with --help for the options)');
            return self::EXIT_ERROR;
        }
        if ($options['help']) {
            $cli->out(self::usage());
            return self::EXIT_OK;
        }
        $cli->quiet = $options['quiet'];
        return $cli->execute($options);
    }

    /**
     * Parses the arguments (without the program name) into an option map
     *
     * @param string[] $args
     * @return array{bootstrap: ?string, lib: string, tables: string[], exclude: string[], labels: ?string,
     *               classes: bool, pages: bool, 'install-pages': bool, force: bool, list: bool, strict: bool,
     *               quiet: bool, help: bool}
     * @throws \InvalidArgumentException for an unknown option, a missing value or an argument that is not an option
     */
    public static function parse(array $args): array
    {
        $options = [
            'bootstrap' => null,
            'lib' => self::DEFAULT_LIB,
            'tables' => [],
            'exclude' => self::DEFAULT_EXCLUDED,
            'labels' => null,
            'many-to-many' => null,
        ];
        foreach (self::FLAGS as $flag) {
            $options[$flag] = false;
        }

        $count = count($args);
        for ($i = 0; $i < $count; $i++) {
            $arg = (string)$args[$i];
            $value = null;
            if (strncmp($arg, '--', 2) === 0) {
                $name = substr($arg, 2);
                $equals = strpos($name, '=');
                if ($equals !== false) {
                    $value = substr($name, $equals + 1);
                    $name = substr($name, 0, $equals);
                }
            } elseif (strlen($arg) === 2 && $arg[0] === '-' && isset(self::SHORT[$arg[1]])) {
                $name = self::SHORT[$arg[1]];
            } else {
                throw new \InvalidArgumentException("Unexpected argument: {$arg}");
            }

            if (in_array($name, self::FLAGS, true)) {
                if ($value !== null) {
                    throw new \InvalidArgumentException("Option --{$name} takes no value");
                }
                $options[$name] = true;
                continue;
            }
            if (!in_array($name, self::VALUES, true)) {
                throw new \InvalidArgumentException("Unknown option: {$arg}");
            }
            if ($value === null) {
                if ($i + 1 >= $count) {
                    throw new \InvalidArgumentException("Option --{$name} needs a value");
                }
                $value = (string)$args[++$i];
            }
            if ($name === 'tables' || $name === 'exclude') {
                $options[$name] = self::splitList($value);
            } else {
                $options[$name] = $value;
            }
        }

        if ($options['classes'] && $options['pages']) {
            throw new \InvalidArgumentException('--classes and --pages exclude each other');
        }
        if ($options['lib'] === '') {
            throw new \InvalidArgumentException('--lib needs a directory');
        }
        if ($options['many-to-many'] !== null && !in_array($options['many-to-many'], ['marked', 'auto', 'off'], true)) {
            throw new \InvalidArgumentException('--many-to-many takes marked, auto or off');
        }
        return $options;
    }

    public static function usage(): string
    {
        $excluded = implode(', ', self::DEFAULT_EXCLUDED);
        return <<<TXT
Usage: laf-generate --bootstrap=FILE [--lib=DIR] [options]

Generates the Base and model classes and the CRUD pages of the connected database.

  --bootstrap=FILE   PHP file that configures Settings (database.*, project.package_name);
                     required unless the LAF_BOOTSTRAP environment variable names it
  --lib=DIR          folder that receives {Namespace}/, {Namespace}/Base/, generator_cache/pages/
                     and pages/ (default: app/lib)
  --tables=a,b,c     only these tables; patterns with * are allowed (default: every base table)
  --exclude=x,y      tables left out, patterns allowed (default: {$excluded}; --exclude= keeps all)
  --classes          only the Base and model classes
  --pages            only the pages (written to generator_cache/pages/)
  --install-pages    also copy the page of every table that has none in pages/ yet
  --force, -f        overwrite existing model classes (Base classes are always rewritten)
  --list, -l         dry run: print the tables and the files a run would write
  --labels=FILE      .php (returning an array) or .json file with page label translations
                     (default: Settings settings.label.translations)
  --many-to-many=M   linker tables that become many-to-many pickers: marked (default: only
                     tables commented {"linker": true}), auto (every table the rule finds) or off
                     (default: Settings generator.many_to_many, else marked)
  --strict           exit code 1 when the generator reported warnings
  --quiet, -q        print only warnings and the summary
  --help, -h         this text

Exit codes: 0 success, 1 warnings with --strict, 2 usage or configuration error.
TXT;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function execute(array $options): int
    {
        $bootstrap = $options['bootstrap'];
        if ($bootstrap === null || $bootstrap === '') {
            $env = getenv('LAF_BOOTSTRAP');
            $bootstrap = is_string($env) && $env !== '' ? $env : null;
        }
        if ($bootstrap === null) {
            return $this->fail('No bootstrap file: pass --bootstrap=FILE or set LAF_BOOTSTRAP');
        }
        if (!is_file($bootstrap)) {
            return $this->fail("Bootstrap file not found: {$bootstrap}");
        }
        try {
            (static function (string $file): void {
                require $file;
            })($bootstrap);
        } catch (\Throwable $ex) {
            return $this->fail("The bootstrap file failed: " . $ex->getMessage());
        }

        $namespace = Settings::has('project.package_name') ? trim((string)Settings::get('project.package_name')) : '';
        if ($namespace === '') {
            return $this->fail("Settings 'project.package_name' is not set by {$bootstrap}");
        }
        try {
            Db::getInstance();
        } catch (\Throwable $ex) {
            return $this->fail('Cannot connect to the database: ' . $ex->getMessage());
        }
        try {
            $labels = $this->labels($options['labels']);
        } catch (\RuntimeException $ex) {
            return $this->fail($ex->getMessage());
        }

        $generator = new DatabaseGenerator((string)$options['lib'], (bool)$options['force']);
        $generator->setTables($options['tables'])
            ->setExcludedTables($options['exclude'])
            ->setLabelTranslations($labels)
            ->setGenerateAutoloader(false)
            ->setInstallMissingPages((bool)$options['install-pages'])
            ->setManyToManyMode($options['many-to-many'])
            ->setOutput(function (string $line): void {
                $this->progress($line);
            });

        try {
            $tables = $generator->getTables();
        } catch (\Throwable $ex) {
            return $this->fail('Cannot list the tables: ' . $ex->getMessage());
        }
        if ($tables === []) {
            return $this->fail('No table matches the selection (--tables / --exclude)');
        }

        if ($options['list']) {
            return $this->listPlan($generator, $options);
        }

        try {
            if ($options['classes']) {
                $generator->processTables();                 // the generators create their own directories
            } elseif ($options['pages']) {
                $generator->processPages();
                if ($options['install-pages']) {
                    $generator->installMissingPages();
                }
            } else {
                $generator->generateEverything();
            }
        } catch (\Throwable $ex) {
            return $this->fail('Generation failed: ' . $ex->getMessage());
        }

        $classes = $pages = 0;
        foreach ($generator->getGeneratedFiles() as $file) {
            if (substr($file, -5) === '.page') {
                $pages++;
            } elseif (substr($file, -4) === '.php') {
                $classes++;
            }
        }
        $installed = count($generator->getInstalledPages());
        $pages -= $installed;
        $warnings = count($generator->getWarnings());
        $this->out(sprintf(
            'Done: %d table%s, %d class file%s, %d page%s, %d page%s installed, %d warning%s',
            count($tables), count($tables) === 1 ? '' : 's',
            $classes, $classes === 1 ? '' : 's',
            $pages, $pages === 1 ? '' : 's',
            $installed, $installed === 1 ? '' : 's',
            $warnings, $warnings === 1 ? '' : 's'
        ));
        return $warnings > 0 && $options['strict'] ? self::EXIT_WARNINGS : self::EXIT_OK;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function listPlan(DatabaseGenerator $generator, array $options): int
    {
        $rewrite = (bool)$options['force'];
        foreach ($generator->plan() as $entry) {
            $this->out($entry['table']);
            if (!$options['pages']) {
                $this->out('  write   ' . $entry['base_class']);
                $this->out(($entry['class_exists'] && !$rewrite ? '  keep    ' : '  write   ') . $entry['class']);
            }
            if (!$options['classes']) {
                $this->out('  write   ' . $entry['page']);
                if ($options['install-pages']) {
                    $this->out(($entry['live_page_exists'] ? '  keep    ' : '  install ') . $entry['live_page']);
                }
            }
        }
        return self::EXIT_OK;
    }

    /**
     * The page labels: the --labels file, else Settings settings.label.translations, else none
     * @return array<string, string>
     * @throws \RuntimeException when the file cannot be read
     */
    private function labels(?string $file): array
    {
        if ($file === null) {
            $labels = Settings::has('settings.label.translations') ? Settings::get('settings.label.translations') : [];
            return is_array($labels) ? $labels : [];
        }
        if (!is_file($file)) {
            throw new \RuntimeException("Labels file not found: {$file}");
        }
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($extension === 'json') {
            $labels = json_decode((string)file_get_contents($file), true);
        } elseif ($extension === 'php') {
            $labels = (static function (string $f) {
                return require $f;
            })($file);
        } else {
            throw new \RuntimeException("Labels file must be .php or .json: {$file}");
        }
        if (!is_array($labels)) {
            throw new \RuntimeException("Labels file does not hold an array: {$file}");
        }
        return $labels;
    }

    /**
     * A progress line from the generator; in quiet mode only warnings are shown
     */
    private function progress(string $line): void
    {
        $line = trim($line);
        if ($line === '') {
            return;
        }
        if ($this->quiet && strncmp($line, 'Warning:', 8) !== 0 && strncmp($line, 'Note:', 5) !== 0) {
            return;
        }
        $this->out($line);
    }

    private function fail(string $message): int
    {
        $this->error($message);
        return self::EXIT_ERROR;
    }

    private function out(string $line): void
    {
        fwrite($this->stdout, $line . PHP_EOL);
    }

    private function error(string $line): void
    {
        fwrite($this->stderr, 'laf-generate: ' . $line . PHP_EOL);
    }

    /**
     * @return string[]
     */
    private static function splitList(string $value): array
    {
        $items = [];
        foreach (explode(',', $value) as $item) {
            $item = trim($item);
            if ($item !== '') {
                $items[] = $item;
            }
        }
        return $items;
    }
}
