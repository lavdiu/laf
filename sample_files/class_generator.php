<?php

use Laf\Generator\DatabaseGenerator;

require_once __DIR__ . '/config.php';

/**
 * Input your package name here
 * it will be used for Namespace root as well
 */
$packageName = "YourPackageName";

$config = [
    'namespace' => $packageName,
    'base_class_dir' => __DIR__ . "/../lib/{$packageName}/Base",
    'class_dir' => __DIR__ . '/../lib/' . $packageName
];


ob_start();

echo "<pre>Starting Class Generation";
$dbg = new DatabaseGenerator($config);
$dbg->generateEverything();

echo "\nAll tables processed\n\n";
