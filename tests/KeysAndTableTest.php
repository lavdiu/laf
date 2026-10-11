<?php

use Laf\Database\Field\Field;
use Laf\Database\Field\TypeDecimal;
use Laf\Database\Field\TypeInteger;
use Laf\Database\Field\TypeVarchar;
use Laf\Database\ForeignKey;
use Laf\Database\Identifier;
use Laf\Database\PrimaryKey;
use Laf\Database\Table;
use Laf\UI\Container\Div;
use Laf\UI\Table\Table as HtmlTable;
use Laf\UI\Table\Td;
use Laf\UI\Table\Th;
use Laf\UI\Table\Tr;
use Laf\Util\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests (no database) for Identifier, PrimaryKey, ForeignKey helpers and
 * the Laf\UI\Table component.
 */
class KeysAndTableTest extends TestCase
{
	private $savedEngine = null;

	protected function setUp(): void
	{
		$this->savedEngine = Settings::getInstance()->propertyExists('database.engine') ? Settings::get('database.engine') : null;
	}

	protected function tearDown(): void
	{
		Settings::set('database.engine', $this->savedEngine ?? 'mysql');
	}

	private function makeTable(): Table
	{
		$table = new Table('order_line');
		$pk = new PrimaryKey();
		$orderId = (new Field('order_id'))->setType(new TypeInteger());
		$line = (new Field('line_no'))->setType(new TypeInteger());
		$pk->addField($orderId)->addField($line);
		$table->addField($orderId)->addField($line);
		$table->addField((new Field('qty'))->setType(new TypeDecimal(10, 2)));
		$table->setPrimaryKey($pk);
		return $table;
	}

	// ── Identifier ──

	/** @test */
	public function identifier_quotes_per_engine_and_whitelists()
	{
		Settings::set('database.engine', 'mysql');
		$this->assertSame('`a_b`', Identifier::quote('a_b'));
		$this->assertSame('`abDROP`', Identifier::quote('a`b; DROP'), 'anything outside [A-Za-z0-9_] is stripped');
		Settings::set('database.engine', 'postgres');
		$this->assertSame('"a_b"', Identifier::quote('a_b'));
		$this->assertSame('"t"."c"', Identifier::quoteQualified('t', 'c'));
		$this->assertSame('`x`', Identifier::quote('x', Identifier::MYSQL));
		Settings::set('database.engine', 'pgsql');
		$this->assertTrue(Identifier::isPostgres());
		Settings::set('database.engine', '');
		$this->assertTrue(Identifier::isMysql(), 'unset/unknown engine defaults to mysql');
	}

	// ── PrimaryKey ──

	/** @test */
	public function primary_key_composite_helpers()
	{
		Settings::set('database.engine', 'mysql');
		$table = $this->makeTable();
		$pk = $table->getPrimaryKey();
		$this->assertTrue($pk->isComposite());
		$this->assertSame(['order_id', 'line_no'], $pk->getFieldNames());
		$this->assertFalse($pk->isAutoIncrement());
		$this->assertFalse($pk->hasValue());

		$pk->setValues(['order_id' => '7', 'line_no' => 2]);
		$this->assertTrue($pk->hasValue());
		$this->assertSame(['order_id' => 7, 'line_no' => 2], $pk->getValues());
		$this->assertSame('`order_id` = :pk_order_id AND `line_no` = :pk_line_no', $pk->getWhereClause());
		$this->assertSame([':pk_order_id' => [7, \PDO::PARAM_INT], ':pk_line_no' => [2, \PDO::PARAM_INT]], $pk->getBindings('pk_', false));

		Settings::set('database.engine', 'postgres');
		$this->assertSame('"order_id" = :k_order_id AND "line_no" = :k_line_no', $pk->getWhereClause('k_'));

		$pk->setValues([9, 3]);
		$this->assertSame(['order_id' => 9, 'line_no' => 3], $pk->getValues(), 'positional values follow key order');
	}

	/** @test */
	public function primary_key_old_values_address_the_loaded_row()
	{
		$table = $this->makeTable();
		$pk = $table->getPrimaryKey();
		$table->getField('order_id')->loadValueFromDb('7');
		$table->getField('line_no')->loadValueFromDb('2');
		$table->getField('line_no')->setValue(5);
		$this->assertSame(['order_id' => 7, 'line_no' => 2], $pk->getOldValues());
		$this->assertSame(2, $pk->getBindings('pk_', true)[':pk_line_no'][0]);
		$this->assertSame(5, $pk->getBindings('pk_', false)[':pk_line_no'][0]);
	}

	/** @test */
	public function primary_key_single_column_and_nullable_getters()
	{
		$pk = new PrimaryKey();
		$this->assertNull($pk->getFirstField());
		$this->assertNull($pk->getTable());
		$this->assertFalse($pk->hasValue());
		$this->assertSame([], $pk->getFieldNames());
		$id = (new Field('id'))->setType(new TypeInteger())->setAutoIncrement(true);
		$pk->addField($id);
		$this->assertTrue($pk->isAutoIncrement());
		$pk->setValues('42');
		$this->assertSame(42, $id->getValue());
		$legacy = new PrimaryKey([], 'ignored string table name');
		$this->assertNull($legacy->getTable(), 'the old string argument is ignored instead of breaking');
	}

	// ── ForeignKey ──

	/** @test */
	public function foreign_key_aliases_and_metadata()
	{
		$fk = new ForeignKey('fk_name', null, null, 'country', 'code');
		$this->assertSame('country', $fk->getReferencedTable());
		$this->assertSame('country', $fk->getReferencingTable());
		$this->assertSame('code', $fk->getReferencedField());
		$this->assertNull($fk->getTable());
		$this->assertNull($fk->getField());
		$fk->setReferencedTable('region')->setReferencedField('id')->setOnDelete('cascade')->setOnUpdate(null);
		$this->assertSame('region', $fk->getReferencingTable());
		$this->assertSame('CASCADE', $fk->getOnDelete());
		$this->assertTrue($fk->cascadesOnDelete());
		$this->assertNull($fk->getOnUpdate());
		$this->assertTrue($fk->isNullable(), 'no field means nothing says it is required');
		$this->assertSame('?.? -> region.id', (string)$fk);
	}

	/** @test */
	public function foreign_key_class_resolution_uses_package_name()
	{
		Settings::set('project.package_name', 'LafShell');
		$fk = (new ForeignKey())->setReferencingTable('dummy_table')->setReferencingField('id');
		$this->assertSame('\\LafShell\\DummyTable', $fk->getReferencedClassName());
		$this->assertTrue($fk->referencedClassExists());
		$this->assertSame('id', $fk->resolveReferencedFieldName());
		$this->assertInstanceOf(TypeInteger::class, $fk->getReferencedFieldType());
		$this->assertInstanceOf(Table::class, $fk->getReferencedTableMeta());

		$unknown = (new ForeignKey())->setReferencingTable('no_such_table');
		$this->assertFalse($unknown->referencedClassExists());
		$this->assertNull($unknown->getReferencedObject(1));
		$this->assertNull($unknown->getReferencedTableMeta());
		$this->assertSame('id', $unknown->resolveReferencedFieldName());
	}

	/** @test */
	public function foreign_key_is_valid_value_short_circuits_without_a_database()
	{
		$fk = (new ForeignKey())->setReferencingTable('x')->setReferencingField('id');
		$this->assertTrue($fk->isValidValue(null));
		$this->assertTrue($fk->isValidValue(''));
		$this->assertTrue($fk->isValidValue('   '));
		$this->assertTrue((new ForeignKey())->isValidValue(5), 'no referenced table configured: nothing to check');
		ForeignKey::clearValidationCache();
		ForeignKey::clearValidationCache('x');
		$this->addToAssertionCount(1);
	}

	// ── Laf\UI\Table ──

	/** @test */
	public function html_table_fixes_and_features()
	{
		$table = new HtmlTable();
		$this->assertMatchesRegularExpression('/^tbl_[0-9a-f]{8}$/', $table->getId(), 'a missing id is generated');

		$table = new HtmlTable('t1');
		$cell = (new Td('x'))->addStyle('color', 'red');
		$this->assertInstanceOf(Td::class, $cell, 'Cell::addStyle() used to return an undefined constant');
		$this->assertSame($table, $table->addTr((new Tr())->addCell($cell)), 'addTr() is fluent');
		$table->setCaption('Sales <Q1>');
		$html = $table->draw();
		$this->assertStringContainsString('<caption>Sales &lt;Q1&gt;</caption>', $html);
		$this->assertStringContainsString("<tr id='t1_0'>", $html);
		$this->assertStringContainsString("<td id='t1_0_0' style='color:red'>x</td>", $html);
	}

	/** @test */
	public function html_table_ids_follow_row_index_and_section()
	{
		$table = new HtmlTable('t');
		$table->getThead()->addCell(new Th('H'));
		$r0 = (new Tr())->addCell(new Td('a'));
		$r1 = (new Tr())->addCell(new Td('b'));
		$table->addTr($r0)->addTr($r1);
		$table->getTfoot()->addCell(new Td('f'));
		$html = $table->draw();
		$this->assertStringContainsString("<tr id='t_head_0'><th id='t_head_0_0'>H</th></tr>", $html);
		$this->assertStringContainsString("<tr id='t_0'><td id='t_0_0'>a</td></tr>", $html);
		$this->assertStringContainsString("<tr id='t_1'><td id='t_1_0'>b</td></tr>", $html, 'cell ids follow the row index assigned by addTr()');
		$this->assertStringContainsString("<tr id='t_foot_0'><td id='t_foot_0_0'>f</td></tr>", $html);
	}

	/** @test */
	public function html_table_escapes_attributes_and_text_cells_but_keeps_html_cells()
	{
		$table = new HtmlTable('t');
		$table->addParam('data-x', "a'b");
		$row = (new Tr())->addParam('data-y', '<y>');
		$row->addCell((new Td('<b>raw</b>'))->addClass('c"d'));
		$row->addCell((new Td(''))->setText('<i>text</i>'));
		$table->addTr($row);
		$html = $table->draw();
		$this->assertStringContainsString("data-x='a&apos;b'", $html);
		$this->assertStringContainsString("data-y='&lt;y&gt;'", $html);
		$this->assertStringContainsString("class='c&quot;d'", $html);
		$this->assertStringContainsString('<b>raw</b>', $html, 'constructor/setData content is raw HTML by design');
		$this->assertStringContainsString('&lt;i&gt;text&lt;/i&gt;', $html, 'setText() escapes');
	}

	/** @test */
	public function html_table_standalone_row_and_cell_draw_without_table()
	{
		$this->assertSame("<tr><td>a</td></tr>", (new Tr())->addCell(new Td('a'))->draw());
		$this->assertSame("<td colspan='2'>a</td>", (new Td('a'))->setColSpan(2)->draw());
	}

	/** @test */
	public function html_table_is_a_component_and_merges_both_attribute_apis()
	{
		$table = new HtmlTable('t');
		$table->addClass('legacy')->addCssClass('trait')->addAttribute('data-k', 'v')->addParam('data-p', 'q')->addCssStyleItem('width', '100%');
		$div = new Div();
		$div->addComponent($table);
		$html = $div->draw();
		$this->assertStringContainsString("id='t'", $html);
		$this->assertStringContainsString("class='legacy trait'", $html);
		$this->assertStringContainsString("data-k='v'", $html);
		$this->assertStringContainsString("data-p='q'", $html);
		$this->assertStringContainsString('width:100%', $html);
		$this->assertSame('Laf-UI-Table-Table', $table->getComponentCssControlClass());
		$this->assertSame($table->draw(), (string)$table);
	}

	/** @test */
	public function html_table_from_array_bootstrap_and_alignment()
	{
		$table = HtmlTable::fromArray([['Bolt <M8>', '12.50'], ['Nut', '0.30']], ['Item', 'Price'], 'prices')
			->bootstrap();
		$table->getTbodyRows()[0]->getCell(1)->alignRight();
		$html = $table->draw();
		$this->assertStringStartsWith("<div class='table-responsive'><table id='prices' class='table table-striped table-hover table-sm'>", $html);
		$this->assertStringContainsString("<th id='prices_head_0_0'>Item</th>", $html);
		$this->assertStringContainsString('Bolt &lt;M8&gt;', $html);
		$this->assertStringContainsString("<td id='prices_0_1' class='text-end'>12.50</td>", $html);
		$this->assertStringEndsWith('</table></div>', $html);

		$plain = new HtmlTable('p');
		$plain->addTheadRow((new Tr())->addCells(['A', 'B'], true))->addTheadRow((new Tr())->addCells(['a1', 'b1'], true));
		$this->assertSame(2, count($plain->getTheadRows()));
		$this->assertStringContainsString("<tr id='p_head_1'>", $plain->draw());
	}
}
