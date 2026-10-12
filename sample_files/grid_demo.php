<?php

/**
 * PhpGrid + grid.js demo page: every grid feature on one page against a demo table it creates itself.
 * Open it through the web server (http://localhost/laf/sample_files/grid_demo.php). The database is the
 * LAF test database (LAF_DB_* variables, defaults below); the demo table `grid_demo` is created and
 * seeded on first use.
 *
 *   ?demo_break=1   the first grid runs a broken statement (error path)
 *   ?js=old         loads var/grid_old.js (a previous grid.js) instead of grid.js to check compatibility
 */

declare(strict_types=1);

use Laf\Database\Db;
use Laf\UI\Grid\PhpGrid\ActionButton;
use Laf\UI\Grid\PhpGrid\Column;
use Laf\UI\Grid\PhpGrid\PhpGrid;
use Laf\Util\Settings;
use Laf\Util\Util;

require_once __DIR__ . '/../vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

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
Settings::set('project.package_name', 'GridDemo');
Settings::set('settings.use_pretty_url', false);

$db = Db::getInstance();
$isPostgres = $db->isPostgres();

// ─── demo table ───────────────────────────────────────────────────────
if (!$db->tableExists('grid_demo')) {
    $db->execute($isPostgres
        ? 'CREATE TABLE grid_demo (id SERIAL PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(100), city VARCHAR(100),
            "order" INTEGER, amount NUMERIC(10,2), active BOOLEAN DEFAULT TRUE, notes TEXT, created_on TIMESTAMP)'
        : 'CREATE TABLE grid_demo (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(100), city VARCHAR(100),
            `order` INT, amount DECIMAL(10,2), active TINYINT(1) DEFAULT 1, notes TEXT, created_on DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $first = ['Alice', 'Bob', 'Charlie', 'Diana', 'Eve', 'Frank', 'Grace', 'Hank', 'Ivy', 'Jack', 'Karen', 'Leo', 'Mia', 'Noah', 'Olga', 'Paul', 'Quinn', 'Rosa', 'Sam', 'Tina'];
    $cities = ['New York', 'Boston', 'Chicago', 'San Francisco', 'Zürich', 'São Paulo'];
    for ($i = 1; $i <= 57; $i++) {
        $name = $first[($i - 1) % count($first)] . ' ' . chr(65 + ($i % 26)) . '.';
        $db->insert('grid_demo', [
            'name' => $name,
            'email' => strtolower(str_replace([' ', '.'], ['', ''], $name)) . '@example.com',
            'city' => $cities[$i % count($cities)],
            'order' => ($i * 37) % 100,
            'amount' => round(fmod($i * 123.45, 1000) + $i / 100, 2),
            'active' => $i % 4 !== 0,
            'notes' => $i % 5 === 0 ? 'a & b <c> "quoted" ' . $i : null,
            'created_on' => date('Y-m-d H:i:s', strtotime('2026-01-01') + $i * 86400 * 3),
        ]);
    }
}

$quote = fn(string $name) => $isPostgres ? '"' . $name . '"' : '`' . $name . '`';
$broken = isset($_GET['demo_break']);

// ─── grid 1: everything ───────────────────────────────────────────────
$grid = new PhpGrid('demo people');
$grid->setTitle('Demo people <b>(title is text)</b>')
    ->setSqlQuery('SELECT id, name, email, city, ' . $quote('order') . ', amount, active, notes, created_on' . ($broken ? ', missing_column' : '') . ' FROM grid_demo')
    ->setRowsPerPage(10)
    ->setRowsPerPageOptions([5, 10, 25, 0])
    ->setEnableWildCardSearch(true)
    ->setColumnTotals(['amount'])
    ->setRowLevelJsCallback('demoRow')
    ->setDebug(true);
$grid->addColumn((new Column('id', 'ID', true, true, '?module=demo&action=view&id={id}'))->setInnerElementCssClass('btn btn-sm btn-outline-success')->setOuterElementCssStyle('width:90px;'));
$grid->addColumn(new Column('name', 'Name'));
$grid->addColumn((new Column('email', 'Email'))->setJsCallback('demoEmail'));
$grid->addColumn((new Column('city', 'City'))->setCaseInsensitive(true));
$grid->addColumn(new Column('order', 'Order (reserved word)'));
$grid->addColumn(new Column('amount', 'Amount'));
$grid->addColumn((new Column('active', 'Active'))->setJsCallback('demoActive')->setSearchOperator('eq'));
$grid->addColumn((new Column('notes', 'Notes'))->setExportable(false));
$grid->addColumn((new Column('created_on', 'Created', false)));
if ($broken) {
    $grid->addColumn(new Column('missing_column', 'Missing'));
}
$grid->addActionButton(new ActionButton('View', '?module=demo&action=view&id={id}', 'fa fa-eye'));
$grid->addActionButton((new ActionButton('Edit', '?module=demo&action=update&id={id}', 'fa fa-edit'))->setJsCallback('demoEditButton'));
$grid->addActionButton((new ActionButton('Delete', '?module=demo&action=delete&id={id}', 'fa fa-trash'))->addAttribute('onclick', 'return confirm("Delete record {id}?")'));
$grid->addActionButton((new ActionButton('Odd rows only', '?module=demo&action=odd&id={id}'))->setJsCallback('demoOddButton'));
if ($grid->isReadyToHandleRequests()) {
    $grid->bootstrap();
}

// ─── grid 2: deferred, in a hidden tab, no inline script ──────────────
$deferred = new PhpGrid('demo_cities');
$deferred->setTitle('Rows per city')
    ->setSqlQuery('SELECT city, COUNT(*) AS people, SUM(amount) AS total FROM grid_demo GROUP BY city ORDER BY city')
    ->setRowsPerPage(10)
    ->setShowSearchBar(false)
    ->setDeferInitialize(true)
    ->setInlineScript(false)
    ->setAllowExport(false)
    ->setSortDetails('people', 'desc');
$deferred->addColumn(new Column('city', 'City'));
$deferred->addColumn(new Column('people', 'People'));
$deferred->addColumn(new Column('total', 'Total amount'));
if ($deferred->isReadyToHandleRequests()) {
    $deferred->bootstrap();
}

$gridJs = (isset($_GET['js']) && $_GET['js'] === 'old') ? '../var/grid_old.js' : 'grid.js';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PhpGrid demo</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="<?= Util::attr($gridJs) ?>?v=<?= filemtime(__DIR__ . '/' . ($gridJs === 'grid.js' ? 'grid.js' : '../var/grid_old.js')) ?>"></script>
    <script>
        // row callback: highlight large amounts
        function demoRow(tr, row) {
            if (parseFloat(row.amount) > 900) {
                tr.classList.add('table-warning');
            }
        }
        // column callback returning an object (link + text)
        function demoEmail(value, row, column) {
            if (!value) return {value: '—'};
            return {href: 'mailto:' + value, value: value, innerElementCssClass: 'text-decoration-none'};
        }
        // column callback returning HTML
        function demoActive(value, row, column) {
            return (value == 1 || value === true)
                ? '<span class="badge bg-success">active</span>'
                : '<span class="badge bg-secondary">inactive</span>';
        }
        // action button callback: disabled when inactive
        function demoEditButton(row, button) {
            if (!(row.active == 1 || row.active === true)) {
                return {disabled: true, label: 'Edit (inactive)'};
            }
            return true;
        }
        // action button callback: hidden on even ids
        function demoOddButton(row, button) {
            return row.id % 2 === 1 ? {cssClass: 'text-primary'} : false;
        }
        window.demoEvents = [];
    </script>
</head>
<body class="bg-light">
<div class="container-fluid py-3">
    <h1 class="h4">PhpGrid demo <small class="text-muted">(<?= Util::e($gridJs) ?><?= $broken ? ', broken SQL' : '' ?>)</small></h1>
    <p class="small text-muted">
        <a href="?">normal</a> · <a href="?demo_break=1">broken SQL</a> · <a href="?js=old">old grid.js</a> ·
        engine: <?= Util::e(Settings::get('database.engine')) ?>
    </p>

    <div class="card mb-3">
        <div class="card-body p-2">
            <?= $grid->draw() ?>
        </div>
    </div>

    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-info" type="button" role="tab">Info</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-grid" type="button" role="tab">Deferred grid</button></li>
    </ul>
    <div class="tab-content border border-top-0 bg-white p-3">
        <div class="tab-pane fade show active" id="tab-info" role="tabpanel">
            The second grid is drawn with <code>setDeferInitialize(true)</code> and <code>setInlineScript(false)</code>:
            it is registered by <code>Grid.autoInitAll()</code> and loads when its tab is shown.
        </div>
        <div class="tab-pane fade" id="tab-grid" role="tabpanel">
            <?= $deferred->draw() ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
