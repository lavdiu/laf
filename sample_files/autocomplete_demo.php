<?php

/**
 * Autocomplete and many-to-many demo on the LAF test database (LAF_DB_* variables, defaults below).
 * Open it through the web server: http://localhost/laf/sample_files/autocomplete_demo.php
 *
 *   (default)        a person form: m2m_person ⇄ m2m_group through the linker table m2m_person_group
 *                    (shaped like asm's person ⇄ group through person_group). The generator found the linker
 *                    table, so the Groups picker is on the form by itself; saving stores the person and the links.
 *   &style=chips     the same picker as chips with search and '+ Create "…"' (a big table's picker)
 *   &style=dual      the same picker as two lists (available / selected) with a new-entry box
 *   ?demo=company    a single foreign key (link_table.code → code_table) as an Autocomplete: one value only;
 *                    creating stores a code_table row (a varchar key gets a generated UUID)
 *
 * The tables must exist (the integration suite creates them); demo rows are added when they are empty.
 */

declare(strict_types=1);

use Laf\Database\Db;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\FormLayout;
use Laf\Util\Settings;
use Laf\Util\Util;
use LafShell\LinkTable;
use LafShell\M2mPerson;

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
foreach (['code_table', 'link_table', 'm2m_person', 'm2m_group', 'm2m_person_group'] as $table) {
    if (!$db->tableExists($table)) {
        exit("{$table} is missing: run the integration suite (BaseObjectTest creates it) first.");
    }
}

$demo = ($_GET['demo'] ?? '') === 'company' ? 'company' : 'person';
$self = static fn(array $query): string => '?' . http_build_query($query);
$message = null;

$style = in_array($_GET['style'] ?? '', ['chips', 'dual'], true) ? $_GET['style'] : null;
if ($demo === 'company') {
    if ((int)$db->getOne('SELECT COUNT(*) FROM code_table') < 50) {
        $n = 0;
        foreach (['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf', 'Hotel', 'India', 'Zürich', 'O\'Brien & Sons', '<b>Bold</b>'] as $a) {
            foreach (['Ltd', 'GmbH', 'Inc', 'Group', 'Partners'] as $b) {
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
    $company->setSearchColumns(['code', 'name'])->setAllowCreate(true)->setHint('One company: a foreign key holds one key. Type a new name to create it (its code becomes a generated UUID).');
    $form = $link->getForm(DrawMode::INSERT, FormLayout::auto($link->getTable())->fields(['code', 'note']));
    $form->handleLookupRequest();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $message = 'Posted (nothing stored): ' . json_encode($_POST, JSON_UNESCAPED_UNICODE);
    }
} else {
    if ((int)$db->getOne('SELECT COUNT(*) FROM m2m_group') === 0) {
        foreach (['Admins', 'Accounting', 'Drivers', 'Mechanics', 'Sales', 'Warehouse', 'Management', 'Guests'] as $name) {
            $db->insert('m2m_group', ['name' => $name]);
        }
    }
    $personId = isset($_GET['person']) && ctype_digit((string)$_GET['person']) ? (int)$_GET['person'] : null;
    $person = new M2mPerson($personId);
    if ($personId !== null && !$person->isLoaded()) {
        $person = new M2mPerson();
        $personId = null;
    }
    $layout = FormLayout::auto($person->getTable());
    if ($style === 'chips') {
        $layout->element('m2m_person_group', 'Autocomplete');
    } elseif ($style === 'dual') {
        $layout->element('m2m_person_group', 'DualList');
    }
    $form = $person->getForm($personId === null ? DrawMode::INSERT : DrawMode::UPDATE, $layout);
    $groups = $form->getRelationElement('m2m_person_group');
    $groups->setLabel('Groups');
    if ($groups instanceof \Laf\UI\Form\Input\ManyToManyDualList) {
        $groups->setAllowCreate(true)->setHint('Move groups across with the buttons, a double click, Enter or Delete; add a new one under the list');
    } elseif ($groups instanceof \Laf\UI\Form\Input\ManyToManyAutocomplete) {
        $groups->setAllowCreate(true)->setMinChars(0)->setHint('Type to search, Backspace removes the last group, or create a new one');
    } elseif ($groups instanceof \Laf\UI\Form\Input\ManyToManyCheckboxes) {
        $groups->setInline(true);
    }
    $form->setCancelLink($self(['style' => $style]));
    $form->handleLookupRequest();

    if ($form->isSubmitted()) {
        try {
            $id = $form->processForm();
            header('Location: ' . $self(['person' => $id, 'style' => $style, 'saved' => 1]));
            exit;
        } catch (\Laf\Exception\FormValidationException $ex) {
            // the redrawn form shows the messages and keeps the chosen groups
        }
    }
    if (!empty($_GET['saved']) && $personId !== null && !$form->isSubmitted()) {
        $message = 'Saved. Rows in m2m_person_group for this person: '
            . json_encode($db->getAllAssoc('SELECT id, person_id, group_id FROM m2m_person_group WHERE person_id = :p ORDER BY id', [':p' => $personId]));
    }
    $people = $db->getAllAssoc('SELECT id, name FROM m2m_person ORDER BY id DESC LIMIT 10');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autocomplete and many-to-many demo</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 760px">
    <h1 class="h3">Autocomplete and many-to-many</h1>
    <ul class="nav nav-pills mb-3 flex-wrap">
        <li class="nav-item"><a class="nav-link<?= $demo === 'person' && $style === null ? ' active' : '' ?>" href="?">Person: groups as checkboxes</a></li>
        <li class="nav-item"><a class="nav-link<?= $demo === 'person' && $style === 'chips' ? ' active' : '' ?>" href="?style=chips">Person: groups as chips</a></li>
        <li class="nav-item"><a class="nav-link<?= $demo === 'person' && $style === 'dual' ? ' active' : '' ?>" href="?style=dual">Person: groups as two lists</a></li>
        <li class="nav-item"><a class="nav-link<?= $demo === 'company' ? ' active' : '' ?>" href="?demo=company">Single foreign key</a></li>
    </ul>
    <?php if ($message !== null): ?>
        <div class="alert alert-info small" id="message"><?= Util::e($message) ?></div>
    <?php endif; ?>
    <div class="card"><div class="card-body">
        <?= $form->draw() ?>
    </div></div>
    <?php if ($demo === 'person' && $people !== []): ?>
        <h2 class="h6 mt-4">Recent people</h2>
        <ul class="small">
            <?php foreach ($people as $row): ?>
                <li><a href="<?= Util::attr($self(['person' => $row['id'], 'style' => $style])) ?>"><?= Util::e((string)$row['name']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p class="mt-3 small text-muted" id="events">Events: none yet</p>
</div>
<script>
    ['laf:autocomplete:select', 'laf:autocomplete:add', 'laf:autocomplete:remove', 'laf:autocomplete:create'].forEach(function (name) {
        document.addEventListener(name, function (e) {
            document.getElementById('events').textContent = 'Events: ' + name.replace('laf:autocomplete:', '') + ' ' + JSON.stringify(e.detail);
        });
    });
</script>
</body>
</html>
