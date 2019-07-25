<?php

use Laf\Generator\DatabaseGenerator;

require_once __DIR__.'/../config/config.php';

$config = [
	'namespace' => 'Intrepicure',
	'base_class_dir' => __DIR__.'/../lib/Intrepicure/Base',
	'class_dir' => __DIR__.'/../lib/Intrepicure'
];
ob_start();
echo "<pre>Starting Class Generation";
$dbg = new DatabaseGenerator($config);
$dbg->generateEverything();
echo "\nAll tables processed\n\n";
