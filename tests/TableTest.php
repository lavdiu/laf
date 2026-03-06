<?php

use Laf\Database\Table;
use Laf\Database\PrimaryKey;
use Laf\Database\ForeignKey;
use Laf\Database\Field\Field;
use Laf\Database\Field\TypeInteger;
use Laf\Database\Field\TypeVarchar;
use PHPUnit\Framework\TestCase;

class TableTest extends TestCase
{
	private function makeTable(): Table
	{
		$table = new Table('users');

		$pk = new PrimaryKey();
		$idField = new Field('id');
		$idField->setType(new TypeInteger());
		$idField->setAutoIncrement(true);
		$pk->addField($idField);

		$table->setPrimaryKey($pk);
		$table->addField($idField);

		return $table;
	}

	private function addField(Table $table, string $name): Field
	{
		$field = new Field($name);
		$field->setType(new TypeVarchar());
		$table->addField($field);
		return $field;
	}

	/** @test */
	public function set_and_get_name()
	{
		$table = new Table('my_table');
		$this->assertSame('my_table', $table->getName());

		$table->setName('other_table');
		$this->assertSame('other_table', $table->getName());
	}

	/** @test */
	public function add_and_get_fields()
	{
		$table = $this->makeTable();
		$this->addField($table, 'name');
		$this->addField($table, 'email');

		$this->assertCount(3, $table->getFields()); // id + name + email
		$this->assertSame(3, $table->getFieldCount());
	}

	/** @test */
	public function get_field_by_name()
	{
		$table = $this->makeTable();
		$nameField = $this->addField($table, 'name');

		$this->assertSame($nameField, $table->getField('name'));
		$this->assertNull($table->getField('nonexistent'));
	}

	/** @test */
	public function has_field()
	{
		$table = $this->makeTable();
		$this->addField($table, 'name');

		$this->assertTrue($table->hasField('name'));
		$this->assertTrue($table->hasField('id'));
		$this->assertFalse($table->hasField('nonexistent'));
	}

	/** @test */
	public function primary_key()
	{
		$table = $this->makeTable();

		$this->assertNotNull($table->getPrimaryKey());
		$this->assertSame(1, $table->getPrimaryKeyCount());
		$this->assertTrue($table->isPrimaryKey('id'));
		$this->assertFalse($table->isPrimaryKey('name'));
	}

	/** @test */
	public function primary_key_first_field()
	{
		$table = $this->makeTable();
		$firstPk = $table->getPrimaryKey()->getFirstField();
		$this->assertSame('id', $firstPk->getName());
	}

	/** @test */
	public function primary_key_has_more_than_one_field()
	{
		$table = $this->makeTable();
		$this->assertFalse($table->getPrimaryKey()->hasMoreThanOneField());

		$extraField = new Field('tenant_id');
		$extraField->setType(new TypeInteger());
		$table->getPrimaryKey()->addField($extraField);
		$this->assertTrue($table->getPrimaryKey()->hasMoreThanOneField());
	}

	/** @test */
	public function foreign_key()
	{
		$table = $this->makeTable();
		$fkField = $this->addField($table, 'department_id');

		$fk = new ForeignKey('fk_department', $table, $fkField, 'departments');
		$table->addForeignKey($fk);

		$this->assertTrue($table->isForeignKey('department_id'));
		$this->assertFalse($table->isForeignKey('id'));
		$this->assertSame($fk, $table->getForeignKey('department_id'));
		$this->assertNull($table->getForeignKey('nonexistent'));
		$this->assertCount(1, $table->getForeignKeys());
	}

	/** @test */
	public function foreign_key_properties()
	{
		$table = $this->makeTable();
		$fkField = $this->addField($table, 'role_id');

		$fk = new ForeignKey();
		$fk->setKeyName('fk_role');
		$fk->setField($fkField);
		$fk->setReferencingTable('roles');
		$fk->setReferencingField('id');
		$table->addForeignKey($fk);

		$this->assertSame('fk_role', $fk->getKeyName());
		$this->assertSame('roles', $fk->getReferencingTable());
		$this->assertSame('id', $fk->getReferencingField());
		$this->assertSame($fkField, $fk->getField());
	}

	/** @test */
	public function display_field_explicit()
	{
		$table = $this->makeTable();
		$nameField = $this->addField($table, 'email');
		$table->setDisplayField($nameField);

		$this->assertSame($nameField, $table->getDisplayField());
	}

	/** @test */
	public function display_field_auto_detect_name()
	{
		$table = $this->makeTable();
		$this->addField($table, 'name');
		$this->addField($table, 'email');

		$display = $table->getDisplayField();
		$this->assertSame('name', $display->getName());
	}

	/** @test */
	public function display_field_auto_detect_label()
	{
		$table = $this->makeTable();
		$this->addField($table, 'label');
		$this->addField($table, 'email');

		$display = $table->getDisplayField();
		$this->assertSame('label', $display->getName());
	}

	/** @test */
	public function display_field_auto_detect_description()
	{
		$table = $this->makeTable();
		$this->addField($table, 'description');
		$this->addField($table, 'email');

		$display = $table->getDisplayField();
		$this->assertSame('description', $display->getName());
	}

	/** @test */
	public function display_field_fallback_to_second_field()
	{
		$table = $this->makeTable();
		$emailField = $this->addField($table, 'email');

		$display = $table->getDisplayField();
		$this->assertSame('email', $display->getName());
	}

	/** @test */
	public function unique_fields()
	{
		$table = $this->makeTable();
		$emailField = $this->addField($table, 'email');
		$emailField->setUnique(true);

		$this->assertFalse($table->hasUniqueFields());
		$table->addUniqueField($emailField);
		$this->assertTrue($table->hasUniqueFields());
		$this->assertCount(1, $table->getUniqueFields());
	}

	/** @test */
	public function to_string()
	{
		$table = new Table('products');
		$this->assertSame('products', (string)$table);
	}

	/** @test */
	public function name_as_classname()
	{
		$table = new Table('user_roles');
		$this->assertSame('UserRoles', $table->getNameAsClassname());
	}

	/** @test */
	public function field_table_reference_set_on_add()
	{
		$table = $this->makeTable();
		$field = $this->addField($table, 'name');

		$this->assertSame($table, $field->getTable());
		$this->assertSame('users', $field->getTableName());
	}
}
