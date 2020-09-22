<?php

namespace Laf\Generator;

use Laf\Database\Table;
use Laf\Database\Db;
use Laf\Exception\MissingConfigParamException;
use Laf\Util\Settings;

class DatabaseGenerator
{

	/**
	 * @var string[]
	 *
	 * example
	 * [
	 *  'namespace'         =>  'namespace;',
	 *  'base_class_dir'    =>  '/path/to/write/files,
	 *  'class_dir'         =>  '/path/to/class/dir',
	 *  'rewrite_class'        =>    1
	 * ]
	 * no trailing slashes at the end
	 */
	private $config = [];

	/**
	 * In case we want to override the labels and translate them;
	 * it looks for the word as the key
	 * ['add-new'=>'Add new', 'delete'=>'Delete', 'update'=>'Update', 'view'='View']
	 * @var array
	 */
	private $labelTranslations = [];

	/**
	 * DatabaseGenerator constructor.
	 * @param string $library_path Relative or absolute path to the folder you want to write the library and generated classes
	 * @param bool $force_rewrite_class_files Set to true if you want to force rewrite existing class files
	 * @throws MissingConfigParamException
	 */
	public function __construct($library_path, $force_rewrite_class_files = false)
	{
		$settings = Settings::getInstance();
		$ns = $settings->getProperty('project.package_name');
		$this->config = [
			'namespace' => $ns,
			'base_class_dir' => $library_path . '/' . $ns . '/' . 'Base',
			'class_dir' => $library_path . '/' . $ns,
			'page_dir' => $library_path . '/' . $ns . '/pages',
			'rewrite_class' => $force_rewrite_class_files
		];
	}

	/**
	 * Generate classes for tables
	 * @return DatabaseGenerator
	 */
	public function processTables()
	{
		echo "\nStarting to generate Classes";
		foreach ($this->getTables() as $table) {
			$tg = new TableGenerator(new Table($table['table_name']), $this->getConfig());
			$tg->saveBaseClassToFile()
				->saveClassToFile();
			echo "\nProcessed table: " . $table['table_name'];
			if (PHP_SAPI != 'cli')
				ob_flush();
		}
		return $this;
	}

	public function processPages()
	{
		echo "\nStarting to generate Pages";
		foreach ($this->getTables() as $table) {
			$tg = new PageGenerator(new Table($table['table_name']), $this->getConfig(), $this->labelTranslations);
			$tg->savePageToFile();
			echo "\nProcessed page: " . $table['table_name'];
			if (PHP_SAPI != 'cli')
				ob_flush();
		}
		return $this;
	}

	/**
	 * Generates autoloader.php
	 * @return DatabaseGenerator
	 */
	public function generateAutoLoader()
	{
		echo "\nGenerating autoload.php";
		$file = "<?php

spl_autoload_register('{$this->getConfig()['namespace']}Autoloader');

function {$this->getConfig()['namespace']}Autoloader(\$className)
{
	\$file = str_replace('\\\\', DIRECTORY_SEPARATOR, \$className);
	\$file = __DIR__ . '/../' . \$file . '.php';
	#echo \"trying to include Class: {\$className}; file: {\$file}\\n<br />\";
	if (file_exists(\$file) && is_readable(\$file)) {
		require_once \$file;
	}
}";
		file_put_contents($this->getConfig()['class_dir'] . '/autoload.php', $file);
		return $this;
	}

	/**
	 * Creates directory structure for all files
	 * @return DatabaseGenerator
	 */
	public function createDirectoryStructure()
	{
		if (!is_dir($this->getConfig()['class_dir'])) {
			echo "\nCreating directory structure";
			mkdir($this->getConfig()['class_dir'], 0777, true);
		}

		if (!is_dir($this->getConfig()['base_class_dir'])) {
			echo "\nCreating directory structure";
			mkdir($this->getConfig()['base_class_dir'], 0777, true);
		}

		if (!is_dir($this->getConfig()['page_dir'])) {
			echo "\nCreating directory structure";
			mkdir($this->getConfig()['page_dir'], 0777, true);
		}
		return $this;
	}

	/**
	 * Get tables from database
	 * @return array
	 */
	public function getTables()
	{
		$db = Db::getInstance();
		$sql = "
        SELECT table_name
		FROM information_schema.tables
		WHERE table_schema = '{$db->getDatabase()}'
		#AND table_name='person'
		ORDER BY table_name ASC;";

		$q = $db->query($sql);
		return $q->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * @return string[]
	 */
	public function getConfig(): array
	{
		return $this->config;
	}

	/**
	 * @param string[] $config
	 */
	public function setConfig(array $config): void
	{
		$this->config = $config;
	}

	/**
	 * Checks if config is valid
	 * @return bool
	 */
	public function checkConfig()
	{
		echo "\nChecking the environment";
		if (!isset($this->getConfig()['class_dir'])) {
			echo "Error: Invalid class dir";
			return false;
		}
		if (!isset($this->getConfig()['base_class_dir'])) {
			echo "Error: Invalid base class dir";
			return false;
		}
		if (!isset($this->getConfig()['namespace'])) {
			echo "Error: Invalid Namespace";
			return false;
		}
		return true;
	}

	/**
	 * Generates everything: autoload, base and class files
	 * @param array $labelTranslations
	 */
	public function generateEverything(array $labelTranslations = [])
	{
		$this->labelTranslations = $labelTranslations;
		if ($this->checkConfig()) {
			$this->createDirectoryStructure()
				->generateAutoLoader();
			$this->processTables($labelTranslations)
				->processPages($labelTranslations);
		}
	}
}