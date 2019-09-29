<?php
use Laf\UI\Table\Tr;
use Laf\UI\Table\Table;
use Laf\UI\Table\Td;
use Laf\UI\Table\Th;

$table = new Table();
$tr = new Tr();
$tr->addCell(new Td('a1'))
	->addCell(new Td('a2'))
	->addCell(new Th('a3'));

$table->addTr($tr);

echo $table->draw();