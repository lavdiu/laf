<?php

use Laf\Database\Field\Field;
use Laf\Database\Field\TypeVarchar;
use Laf\Database\Field\TypeInteger;
use Laf\Database\Field\TypeDate;
use Laf\Database\Field\TypeText;
use Laf\Database\Table;
use Laf\Database\PrimaryKey;
use Laf\Exception\InvalidValueException;
use PHPUnit\Framework\TestCase;

class FieldTest extends TestCase
{
	private function makeTable(): Table
	{
		$table = new Table('test_table');
		$pk = new PrimaryKey();
		$idField = new Field('id');
		$idField->setType(new TypeInteger());
		$idField->setAutoIncrement(true);
		$pk->addField($idField);
		$table->setPrimaryKey($pk);
		$table->addField($idField);
		return $table;
	}

	private function makeField(string $name, $type, ?Table $table = null): Field
	{
		$table = $table ?? $this->makeTable();
		$field = new Field($name);
		$field->setType($type);
		$table->addField($field);
		return $field;
	}

	/** @test */
	public function set_and_get_value()
	{
		$field = $this->makeField('name', new TypeVarchar());
		$field->setValue('John');
		$this->assertSame('John', $field->getValue());
	}

	/** @test */
	public function set_value_sanitizes_input()
	{
		$field = $this->makeField('name', new TypeVarchar());
		$field->setValue('<b>bold</b>');
		$this->assertSame('bold', $field->getValue());
	}

	/** @test */
	public function set_invalid_value_throws_exception()
	{
		$this->expectException(InvalidValueException::class);
		$field = $this->makeField('age', new TypeDate());
		$field->setValue('not-a-date');
	}

	/** @test */
	public function has_changed_after_value_set()
	{
		$field = $this->makeField('name', new TypeVarchar());
		$field->loadValueFromDb('original');
		$this->assertFalse($field->hasChanged());

		$field->setValue('modified');
		$this->assertTrue($field->hasChanged());
	}

	/** @test */
	public function has_changed_false_when_same_value()
	{
		$field = $this->makeField('name', new TypeVarchar());
		$field->loadValueFromDb('same');
		$field->setValue('same');
		$this->assertFalse($field->hasChanged());
	}

	/** @test */
	public function load_value_from_db_sets_old_value()
	{
		$field = $this->makeField('name', new TypeVarchar());
		$field->loadValueFromDb('db_value');
		$this->assertSame('db_value', $field->getValue());
		$this->assertSame('db_value', $field->getOldValue());
	}

	/** @test */
	public function set_value_raw_bypasses_sanitization()
	{
		$field = $this->makeField('html', new TypeVarchar());
		$field->setValueRaw('<b>bold</b>');
		$this->assertSame('<b>bold</b>', $field->getValue());
	}

	/** @test */
	public function field_properties()
	{
		$field = new Field('test_field');
		$field->setType(new TypeVarchar());
		$field->setRequired(true);
		$field->setUnique(true);
		$field->setAutoIncrement(false);
		$field->setMaxLength(100);
		$field->setMinLength(1);
		$field->setLabel('Test Field');
		$field->setPlaceholder('Enter value');
		$field->setHint('This is a hint');
		$field->setInvalidValueErrorMessage('Invalid!');

		$this->assertSame('test_field', $field->getName());
		$this->assertTrue($field->isRequired());
		$this->assertTrue($field->isUnique());
		$this->assertFalse($field->isAutoIncrement());
		$this->assertSame(100, $field->getMaxLength());
		$this->assertSame(1, $field->getMinLength());
		$this->assertSame('Test Field', $field->getLabel());
		$this->assertSame('Enter value', $field->getPlaceholder());
		$this->assertSame('This is a hint', $field->getHint());
		$this->assertSame('Invalid!', $field->getInvalidValueErrorMessage());
	}

	/** @test */
	public function fluent_interface()
	{
		$field = new Field();
		$result = $field->setName('test')
			->setType(new TypeVarchar())
			->setRequired(true)
			->setUnique(false)
			->setAutoIncrement(false)
			->setMaxLength(255)
			->setMinLength(0)
			->setLabel('Label')
			->setPlaceholder('Placeholder')
			->setHint('Hint');

		$this->assertInstanceOf(Field::class, $result);
	}

	/** @test */
	public function attributes()
	{
		$field = new Field('test');
		$field->setAttribute('class', 'form-control');
		$field->setAttribute('data-id', '5');

		$this->assertSame('form-control', $field->getAttribute('class'));
		$this->assertSame('5', $field->getAttribute('data-id'));
		$this->assertNull($field->getAttribute('nonexistent'));
		$this->assertCount(2, $field->getAttributes());
	}

	/** @test */
	public function name_rot13()
	{
		$field = new Field('name');
		$this->assertSame('anzr', $field->getNameRot13());
	}

	/** @test */
	public function get_value_nl2br()
	{
		$field = $this->makeField('notes', new TypeText());
		$field->setValueRaw("line1\nline2");
		$this->assertSame("line1<br />\nline2", $field->getValueNl2Br());
	}

	/** @test */
	public function is_primary_key()
	{
		$table = $this->makeTable();
		$idField = $table->getField('id');
		$this->assertTrue($idField->isPrimaryKey());

		$nameField = $this->makeField('name', new TypeVarchar(), $table);
		$this->assertFalse($nameField->isPrimaryKey());
	}

	/** @test */
	public function min_and_max_value()
	{
		$field = new Field('quantity');
		$field->setMinValue(0);
		$field->setMaxValue(999);
		$this->assertSame(0, $field->getMinValue());
		$this->assertSame(999, $field->getMaxValue());
	}

	/** @test */
	public function increment_step()
	{
		$field = new Field('step_field');
		$field->setIncrementStep(5);
		$this->assertSame(5, $field->getIncrementStep());
	}

	/** @test */
	public function allow_html_flag()
	{
		$field = $this->makeField('content', new TypeVarchar());
		$this->assertFalse($field->allowHtml());

		$field->setAllowHtml(true);
		$this->assertTrue($field->allowHtml());

		$field->setValue('<b>bold</b>');
		$this->assertSame('<b>bold</b>', $field->getValue());
	}

	/** @test */
	public function db_selection_criteria()
	{
		$field = new Field('status_id');
		$this->assertFalse($field->hasDbSelectionCriteria());

		$field->addDbSelectionCriteria('active', 1);
		$this->assertTrue($field->hasDbSelectionCriteria());
		$this->assertSame(['active' => 1], $field->getDbSelectionCriteria());

		$field->setDbSelectionCriteria(['type' => 'admin']);
		$this->assertSame(['type' => 'admin'], $field->getDbSelectionCriteria());
	}

	/** @test */
	public function get_value_for_db_insert()
	{
		$field = $this->makeField('birthday', new TypeDate());
		$field->setValueRaw('2024-01-15');
		$this->assertSame('2024-01-15', $field->getValueForDbInsert());
	}

	/** @test */
	public function clear_form_element_cache()
	{
		$table = $this->makeTable();
		$field = new Field('username');
		$field->setType(new TypeVarchar());
		$field->setAutoIncrement(false);
		$field->setRequired(false);
		$field->setUnique(false);
		$table->addField($field);

		$element1 = $field->getFormElement();
		$field->clearFormElementCache();
		$element2 = $field->getFormElement();
		$this->assertNotSame($element1, $element2);
	}
}
