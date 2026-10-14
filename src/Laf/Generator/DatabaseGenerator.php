<?php

namespace Laf\Generator;

use Laf\Database\Db;
use Laf\Database\Table;
use Laf\Exception\MissingConfigParamException;
use Laf\Util\Settings;
use Psr\Log\LoggerInterface;

/**
 * Runs ClassGenerator and PageGenerator for the tables of the connected
 * database (base tables of the current database / schema, views left out).
 *
 *   $generator = new DatabaseGenerator(__DIR__ . '/../lib/');
 *   $generator->setExcludedTables(['audit_log', 'sql_error', 'phinx_*'])
 *       ->generateEverything($labels);
 *
 * Progress lines go to echo (web: flushed) or to the callable given to
 * setOutput(); getGeneratedFiles() lists what was written. setInstallMissingPages(true) also copies the
 * page of every table that has none in live_page_dir yet; plan() shows what a run would write.
 * `vendor/bin/laf-generate` (Laf\Generator\Cli) is the command-line front end.
 */
class DatabaseGenerator
{
    private ?LoggerInterface $logger = null;

    /**
     * namespace, base_class_dir, class_dir, page_dir, live_page_dir, rewrite_class (no trailing slashes)
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * Label overrides for the pages, e.g. ['add-new' => 'Regjistro'] (see PageGenerator::LABEL_DEFAULTS)
     * @var array<string, string>
     */
    private array $labelTranslations = [];

    /** @var string[] only these tables (patterns with *); empty = every table */
    private array $tables = [];

    /** @var string[] tables left out (patterns with *) */
    private array $excludedTables = [];

    /** @var callable|null */
    private $output = null;

    private bool $generateAutoloader = true;

    private bool $installMissingPages = false;

    /** @var string[] */
    private array $generatedFiles = [];

    /** @var string[] pages copied to live_page_dir by installMissingPages() */
    private array $installedPages = [];

    /** @var string[] warnings of the last run */
    private array $warnings = [];

    /** @var string[] notices of the last run (each once) */
    private array $notices = [];

    /**
     * @param string $library_path relative or absolute path of the folder that receives the namespace folder
     * @param bool $force_rewrite_class_files true overwrites existing model classes (Base classes are always rewritten)
     * @throws MissingConfigParamException when project.package_name is not set
     */
    public function __construct(string $library_path, bool $force_rewrite_class_files = false)
    {
        $ns = (string)Settings::getInstance()->getProperty('project.package_name');
        $library_path = rtrim($library_path, '/\\');
        $this->config = [
            'namespace' => $ns,
            'base_class_dir' => $library_path . '/' . $ns . '/Base',
            'class_dir' => $library_path . '/' . $ns,
            'page_dir' => $library_path . '/generator_cache/pages',
            'live_page_dir' => $library_path . '/pages',
            'rewrite_class' => $force_rewrite_class_files,
        ];
    }

    /* ───────────── settings ───────────── */

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function setLogger(?LoggerInterface $logger): DatabaseGenerator
    {
        $this->logger = $logger;
        if ($logger !== null) {
            $logger->debug('Loading Logger: ' . get_class($logger));
        }
        return $this;
    }

    /**
     * Generate only these tables (names or patterns with *); empty = every table
     * @param string[] $tables
     */
    public function setTables(array $tables): static
    {
        $this->tables = array_values(array_map('strval', $tables));
        return $this;
    }

    /**
     * Leave these tables out (names or patterns with *, e.g. 'audit_log', 'phinx_*')
     * @param string[] $tables
     */
    public function setExcludedTables(array $tables): static
    {
        $this->excludedTables = array_values(array_map('strval', $tables));
        return $this;
    }

    /**
     * @param array<string, string> $labelTranslations
     */
    public function setLabelTranslations(array $labelTranslations): static
    {
        $this->labelTranslations = $labelTranslations;
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getLabelTranslations(): array
    {
        return $this->labelTranslations;
    }

    /**
     * Where progress lines go: a callable taking the line, or null for echo
     */
    public function setOutput(?callable $output): static
    {
        $this->output = $output;
        return $this;
    }

    /**
     * Whether generateEverything() also writes {class_dir}/autoload.php (not needed with Composer)
     */
    public function setGenerateAutoloader(bool $generate): static
    {
        $this->generateAutoloader = $generate;
        return $this;
    }

    /**
     * Whether existing model classes are overwritten (Base classes always are); the constructor's second argument
     */
    public function setForceRewriteClasses(bool $force): static
    {
        $this->config['rewrite_class'] = $force;
        return $this;
    }

    public function isForceRewriteClasses(): bool
    {
        return !empty($this->config['rewrite_class']);
    }

    /**
     * Whether generateEverything() ends with installMissingPages()
     */
    public function setInstallMissingPages(bool $install): static
    {
        $this->installMissingPages = $install;
        return $this;
    }

    /**
     * The pages installMissingPages() copied to live_page_dir
     * @return string[]
     */
    public function getInstalledPages(): array
    {
        return $this->installedPages;
    }

    /**
     * The files written so far
     * @return string[]
     */
    public function getGeneratedFiles(): array
    {
        return $this->generatedFiles;
    }

    /**
     * Messages from the generators (skipped tables, unusable comment settings)
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Notices of the last run (each once): linker tables found but not marked {"linker": true}
     * @return string[]
     */
    public function getNotices(): array
    {
        return $this->notices;
    }

    /**
     * How linker tables become many-to-many relations: ClassGenerator::MANY_TO_MANY_MARKED (default: only
     * tables whose comment says {"linker": true}), MANY_TO_MANY_AUTO (every table the rule finds, unless
     * {"linker": false}) or MANY_TO_MANY_OFF; null = Settings generator.many_to_many
     * @throws \InvalidArgumentException for another value
     */
    public function setManyToManyMode(?string $mode): static
    {
        if ($mode === null) {
            unset($this->config['many_to_many']);
            return $this;
        }
        $mode = strtolower(trim($mode));
        if (!in_array($mode, [ClassGenerator::MANY_TO_MANY_MARKED, ClassGenerator::MANY_TO_MANY_AUTO, ClassGenerator::MANY_TO_MANY_OFF], true)) {
            throw new \InvalidArgumentException(sprintf('Many-to-many mode "%s": use marked, auto or off', $mode));
        }
        $this->config['many_to_many'] = $mode;
        return $this;
    }

    /* ───────────── generation ───────────── */

    /**
     * Generate the Base and model classes of every selected table
     */
    public function processTables(): static
    {
        $this->write("\nStarting to generate Classes");
        foreach ($this->getTables() as $table) {
            $generator = new ClassGenerator(new Table($table['table_name']), $this->getConfig());
            $generator->saveBaseClassToFile()
                ->saveClassToFile();
            $this->generatedFiles[] = $generator->getBaseClassFilePath();
            $this->generatedFiles[] = $generator->getClassFilePath();
            foreach ($generator->getWarnings() as $warning) {
                $this->warn($table['table_name'] . ': ' . $warning);
            }
            foreach ($generator->getNotices() as $notice) {
                if (!in_array($notice, $this->notices, true)) {
                    $this->notices[] = $notice;
                    $this->write("\nNote: " . $notice);
                }
            }
            $this->write("\nProcessed table: " . $table['table_name']);
        }
        return $this;
    }

    /**
     * Generate the page of every selected table into page_dir
     */
    public function processPages(): static
    {
        $this->write("\nStarting to generate Pages");
        foreach ($this->getTables() as $table) {
            $generator = new PageGenerator(new Table($table['table_name']), $this->getConfig(), $this->labelTranslations);
            $generator->savePageToFile();
            $this->generatedFiles[] = $generator->getPageFilePath();
            foreach ($generator->getWarnings() as $warning) {
                $this->warn($warning);
            }
            $this->write("\nProcessed page: " . $table['table_name']);
        }
        return $this;
    }

    /**
     * Copies the generated page of every selected table from page_dir to live_page_dir when no page is there
     * yet; a page that exists is never touched (the live pages are the application's own code)
     * @throws \RuntimeException when a page cannot be copied
     */
    public function installMissingPages(): static
    {
        $pageDir = rtrim((string)($this->getConfig()['page_dir'] ?? ''), '/\\');
        $liveDir = rtrim((string)($this->getConfig()['live_page_dir'] ?? ''), '/\\');
        if ($pageDir === '' || $liveDir === '') {
            return $this;
        }
        $this->write("\nInstalling the pages of new tables");
        foreach ($this->getTables() as $table) {
            $name = $table['table_name'] . '.page';
            $source = $pageDir . '/' . $name;
            $target = $liveDir . '/' . $name;
            if (!is_file($source)) {
                $this->warn($table['table_name'] . ': no generated page to install (' . $source . ')');
                continue;
            }
            if (file_exists($target)) {
                continue;
            }
            if (!is_dir($liveDir) && !mkdir($liveDir, 0755, true) && !is_dir($liveDir)) {
                throw new \RuntimeException("Cannot create directory: {$liveDir}");
            }
            if (!copy($source, $target)) {
                throw new \RuntimeException("Failed to write file: {$target}");
            }
            $this->installedPages[] = $target;
            $this->generatedFiles[] = $target;
            $this->write("\nInstalled page: " . $name);
        }
        return $this;
    }

    /**
     * What a run would write for every selected table, without writing anything: the Base class path, the
     * class path and whether it exists (kept unless rewrite_class), the page path, and the live page path
     * and whether it exists (installMissingPages() copies only missing ones)
     *
     * @return array<int, array{table: string, base_class: string, class: string, class_exists: bool,
     *                          page: string, live_page: string, live_page_exists: bool}>
     */
    public function plan(): array
    {
        $liveDir = rtrim((string)($this->getConfig()['live_page_dir'] ?? ''), '/\\');
        $plan = [];
        foreach ($this->getTables() as $table) {
            $tableObject = new Table($table['table_name']);
            $classGenerator = new ClassGenerator($tableObject, $this->getConfig());
            $pageGenerator = new PageGenerator($tableObject, $this->getConfig(), $this->labelTranslations);
            $livePage = $liveDir . '/' . $table['table_name'] . '.page';
            $plan[] = [
                'table' => $table['table_name'],
                'base_class' => $classGenerator->getBaseClassFilePath(),
                'class' => $classGenerator->getClassFilePath(),
                'class_exists' => $classGenerator->classFileExists(),
                'page' => $pageGenerator->getPageFilePath(),
                'live_page' => $livePage,
                'live_page_exists' => file_exists($livePage),
            ];
        }
        return $plan;
    }

    /**
     * Generates {class_dir}/autoload.php, a PSR-0 style autoloader for the generated namespace
     */
    public function generateAutoLoader(): static
    {
        $this->write("\nGenerating autoload.php");
        $namespace = (string)$this->getConfig()['namespace'];
        $template = <<<'PHP'
<?php

spl_autoload_register('{ns}Autoloader');

function {ns}Autoloader($className)
{
	$file = str_replace('\\', DIRECTORY_SEPARATOR, $className);
	$file = __DIR__ . '/../' . $file . '.php';
	if (file_exists($file) && is_readable($file)) {
		require_once $file;
	}
}

PHP;
        $file = rtrim((string)$this->getConfig()['class_dir'], '/\\') . '/autoload.php';
        if (file_put_contents($file, strtr($template, ['{ns}' => $namespace])) === false) {
            throw new \RuntimeException("Failed to write file: {$file}");
        }
        $this->generatedFiles[] = $file;
        return $this;
    }

    /**
     * Creates the class, base class and page directories
     */
    public function createDirectoryStructure(): static
    {
        $announced = false;
        foreach (['class_dir', 'base_class_dir', 'page_dir'] as $key) {
            $dir = (string)($this->getConfig()[$key] ?? '');
            if ($dir !== '' && !is_dir($dir)) {
                if (!$announced) {
                    $this->write("\nCreating directory structure");
                    $announced = true;
                }
                if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                    throw new \RuntimeException("Cannot create directory: {$dir}");
                }
            }
        }
        return $this;
    }

    /**
     * The selected base tables of the connected database, as [['table_name' => ...], ...]
     * @return array<int, array{table_name: string}>
     */
    public function getTables(): array
    {
        $tables = [];
        foreach (Db::getInstance()->getTableNames() as $name) {
            if ($this->tables !== [] && !self::matchesAny($name, $this->tables)) {
                continue;
            }
            if (self::matchesAny($name, $this->excludedTables)) {
                continue;
            }
            $tables[] = ['table_name' => $name];
        }
        return $tables;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * Checks that the config names the directories and the namespace
     */
    public function checkConfig(): bool
    {
        $this->write("\nChecking the environment");
        foreach (['class_dir' => 'class dir', 'base_class_dir' => 'base class dir', 'namespace' => 'Namespace'] as $key => $label) {
            if (empty($this->getConfig()[$key])) {
                $this->write("Error: Invalid {$label}");
                return false;
            }
        }
        return true;
    }

    /**
     * Generates everything: directories, autoloader (unless switched off), classes, pages and, with
     * setInstallMissingPages(true), the live pages of new tables
     * @param array<string, string> $labelTranslations page labels; keeps setLabelTranslations() when empty
     * @throws MissingConfigParamException when the config is incomplete
     */
    public function generateEverything(array $labelTranslations = []): void
    {
        if ($labelTranslations !== []) {
            $this->labelTranslations = $labelTranslations;
        }
        if (!$this->checkConfig()) {
            throw new MissingConfigParamException('DatabaseGenerator: the config needs class_dir, base_class_dir and namespace');
        }
        $this->createDirectoryStructure();
        if ($this->generateAutoloader) {
            $this->generateAutoLoader();
        }
        $this->processTables()
            ->processPages();
        if ($this->installMissingPages) {
            $this->installMissingPages();
        }
    }

    /* ───────────── internals ───────────── */

    private function write(string $line): void
    {
        if ($this->logger !== null) {
            $this->logger->info(trim($line));
        }
        if ($this->output !== null) {
            ($this->output)($line);
            return;
        }
        echo $line;
        if (PHP_SAPI !== 'cli' && ob_get_level() > 0) {
            ob_flush();
            flush();
        }
    }

    private function warn(string $message): void
    {
        $this->warnings[] = $message;
        if ($this->logger !== null) {
            $this->logger->warning($message);
        }
        $this->write("\nWarning: " . $message);
    }

    /**
     * @param string[] $patterns names or patterns with *
     */
    private static function matchesAny(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/i';
            if (preg_match($regex, $name)) {
                return true;
            }
        }
        return false;
    }
}
