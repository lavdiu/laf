<?php

/**
 * Foreign-key autocomplete demo: the test fixture LinkTable's `code` column (a foreign key to code_table)
 * drawn as an Autocomplete. Open it through the web server
 * (http://localhost/laf/sample_files/autocomplete_demo.php). The database is the LAF test database
 * (LAF_DB_* variables, defaults below); code_table must exist (the integration suite creates it) and is
 * filled with demo rows when it has fewer than 50. Submitting shows what the form posted, nothing is stored.
 */

declare(strict_types=1);

use Laf\Database\Db;
use Laf\UI\Form\DrawMode;
use Laf\Util\Settings;
use Laf\Util\Util;
use LafShell\LinkTable;

require_once __DIR__ . '/../vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();

foreach ([
    'database.engine' => ['LAF_DB_ENGINE', 'mysql'],
    'database.hostname' => ['LAF_DB_HOST', '127.0.0.1'],
    'database.port' => ['LAF_DB_PORT', '3306'],
    'database.database_name' => ['LAF_DB_NAME', 'laf_test'],
    'database.username' => ['LAF_DB_USER', 'root'],
    'database.password' => ['LAF_DB_PASS', ''],
] as $key => [$variable, $default]) {
    $value = getenv($variable);
    Settings::set($key, $value === false ? $default : $value);
}
Settings::set('project.package_name', 'LafShell');
Settings::set('settings.use_pretty_url', false);

$db = Db::getInstance();
if (!$db->tableExists('code_table')) {
    exit('code_table is missing: run the integration suite (BaseObjectTest creates it) first.');
}
if ((int)$db->getOne('SELECT COUNT(*) FROM code_table') < 50) {
    $words = ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf', 'Hotel', 'India', 'Juliett', 'Kilo', 'Lima', 'Mike', 'Zürich', 'São Paulo', 'O\'Brien & Sons', '<b>Bold</b>'];
    $n = 0;
    foreach ($words as $a) {
        foreach (['Ltd', 'GmbH', 'Inc', '100%', 'Group', 'Partners'] as $b) {
            $code = sprintf('C%03d', ++$n);
            if (!$db->getOne('SELECT 1 FROM code_table WHERE code = :c', [':c' => $code])) {
                $db->insert('code_table', ['code' => $code, 'name' => "$a $b"]);
            }
        }
    }
}

$link = new LinkTable();
$link->getCodeFld()->setFormElementClass('Autocomplete')->setLabel('Company');
/** @var \Laf\UI\Form\Input\Autocomplete $company */
$company = $link->getCodeFld()->getFormElement();
$company->setSearchColumns(['code', 'name'])->setHint('Type part of the name or the code (C001…)');
if (isset($_GET['preset'])) {
    $link->getCodeFld()->setValueRaw('C002');
}

$form = $link->getForm(DrawMode::INSERT);
$form->handleLookupRequest();   // answers the autocomplete's searches and exits

$posted = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autocomplete demo</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 720px">
    <h1 class="h3">Foreign-key autocomplete</h1>
    <p class="text-muted">link_table.code &rarr; code_table, searched by name and code. <a href="?preset=1">With a stored value</a></p>
    <?php if ($posted !== null): ?>
        <div class="alert alert-info"><strong>Posted:</strong> <code id="posted"><?= Util::e((string)json_encode($posted, JSON_UNESCAPED_UNICODE)) ?></code></div>
    <?php endif; ?>
    <div class="card"><div class="card-body">
        <?= $form->draw() ?>
    </div></div>
    <p class="mt-3 small text-muted" id="events">Events: none yet</p>
</div>
<script>
    document.addEventListener('laf:autocomplete:select', function (e) {
        document.getElementById('events').textContent = 'Events: selected ' + JSON.stringify(e.detail);
    });
</script>
</body>
</html>
