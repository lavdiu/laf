<?php

/**
 * Generating the classes and pages from PHP instead of the command (`vendor/bin/laf-generate
 * --bootstrap=app/bootstrap.php --lib=app/lib --install-pages` does the same with options). Keep a script like
 * this only when the generation has to run from inside the application, e.g. behind an admin page.
 */

declare(strict_types=1);

use Laf\Generator\DatabaseGenerator;
use Laf\Util\Settings;

require_once __DIR__ . '/bootstrap.php';

$generator = new DatabaseGenerator(__DIR__ . '/../app/lib');            // writes app/lib/{Package}/...
$generator
    ->setExcludedTables(['audit_log', 'sql_error', 'object_list', 'phinx_*'])
    ->setLabelTranslations(Settings::has('settings.label.translations') ? (array)Settings::get('settings.label.translations') : [])
    ->setGenerateAutoloader(false)                                       // Composer autoloads the package
    ->setInstallMissingPages(true)                                       // new tables get their page in pages/
    ->setOutput(static function (string $line): void {
        echo trim($line) !== '' ? trim($line) . (PHP_SAPI === 'cli' ? PHP_EOL : "<br>\n") : '';
    })
    ->generateEverything();

foreach ($generator->getWarnings() as $warning) {
    fwrite(PHP_SAPI === 'cli' ? STDERR : STDOUT, 'Warning: ' . $warning . PHP_EOL);
}
printf('%d files written%s', count($generator->getGeneratedFiles()), PHP_EOL);
exit($generator->getWarnings() === [] ? 0 : 1);
