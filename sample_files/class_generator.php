<?php

use Laf\Generator\DatabaseGenerator;
use Laf\Util\Settings;

require_once __DIR__ . '/config.php';

$labels = Settings::getInstance()->propertyExists('settings.label.translations')
    ? (array)Settings::get('settings.label.translations')
    : [];

/**
 * The generator reads the namespace from Settings 'project.package_name' and
 * writes {library}/{Package}/Base/*.php, {library}/{Package}/*.php (once) and
 * {library}/generator_cache/pages/*.page
 */
$libraryPath = __DIR__ . '/../lib';

ob_start();

echo "<pre>Starting Class Generation";
$generator = new DatabaseGenerator($libraryPath);
$generator
    ->setExcludedTables(['audit_log', 'sql_error', 'object_list'])   // tables that need no model or page
    ->setGenerateAutoloader(false)                                   // Composer autoloads the package
    ->generateEverything($labels);

echo "\nAll tables processed\n\n";
foreach ($generator->getWarnings() as $warning) {
    echo "Warning: {$warning}\n";
}
