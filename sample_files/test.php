<?php
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/config.php';

use Laf\Generator\TableInspector;

$ti = new TableInspector("person");
$ti->inspect();