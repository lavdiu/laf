<?php
/**
 * Regenerates the LafShell test fixtures from the real ClassGenerator so they
 * cannot drift from generator output. Not part of the test suites.
 *
 * Usage (needs a database with the tables, which BaseObjectTest and DocumentIntegrationTest create):
 *   LAF_DB_ENGINE=mysql    LAF_DB_NAME=laf_test LAF_DB_USER=root     LAF_DB_PASS=         php tests/Fixtures/regenerate.php
 *   LAF_DB_ENGINE=postgres LAF_DB_NAME=laf_test LAF_DB_USER=postgres LAF_DB_PASS=postgres php tests/Fixtures/regenerate.php
 *
 * Output goes to tests/Fixtures/generated/<engine>/ so you can diff it against
 * tests/Fixtures/LafShell/ before copying anything over. The hand-maintained
 * fixtures add a few helpers (getParentIdObj, listAll*) that the generator also
 * emits, so a diff should be small.
 */
require_once __DIR__ . '/../bootstrap.php';

use Laf\Database\Db;
use Laf\Database\Table;
use Laf\Generator\ClassGenerator;
use Laf\Util\Settings;

$engine = Settings::get('database.engine');
$outDir = __DIR__ . '/generated/' . $engine;
$config = [
    'namespace' => 'LafShell',
    'base_class_dir' => $outDir . '/Base',
    'class_dir' => $outDir,
    'rewrite_class' => true,
];
@mkdir($config['base_class_dir'], 0777, true);

foreach (['dummy_table', 'code_table', 'link_table', 'document'] as $tableName) {
    if (!Db::getInstance()->tableExists($tableName)) {
        echo "skipped {$tableName}: table not found\n";
        continue;
    }
    $generator = new ClassGenerator(new Table($tableName), $config);
    $generator->saveBaseClassToFile()->saveClassToFile();
    echo "generated {$tableName} -> {$generator->getBaseClassFilePath()}\n";
}
echo "Diff against tests/Fixtures/LafShell/ and copy what you want to keep.\n";
