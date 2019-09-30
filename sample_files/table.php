<?php
require '../vendor/autoload.php';

echo "<html>
<body>

";

use Laf\UI\Table\Tr;
use Laf\UI\Table\Table;
use Laf\UI\Table\Td;
use Laf\UI\Table\Th;

$table = new Table('test_table');
$table->setParams(['border'=>1]);
$tr = new Tr();
$tr->addCell(new Td('1'))
	->addCell(new Td('2'))
	->addCell(new Th('3'));

$table->addTr($tr);



$tr = new Tr();
$tr->addCell(new Td('4'))
	->addCell(new Td('5'))
	->addCell(new Td('6'));

$table->addTr($tr);



$tr = new Tr();
$tr->addCell(new Th('C1'))
	->addCell(new Th('C2'))
	->addCell(new Th('C3'));

$table->setThead($tr);
$table->setTfoot($tr);
$table->setPrettyPrint(true);
echo $table->draw();