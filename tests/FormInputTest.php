<?php

use Laf\Database\Field\Field;
use Laf\Database\Field\TypeVarchar;
use Laf\Database\Field\TypeInteger;
use Laf\Database\Field\TypeText;
use Laf\Database\Field\TypeBool;
use Laf\Database\PrimaryKey;
use Laf\Database\Table;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\Input\Text;
use Laf\UI\Form\Input\TextArea;
use Laf\UI\Form\Input\Hidden;
use Laf\UI\Form\Input\Checkbox;
use Laf\UI\Form\InputType;
use PHPUnit\Framework\TestCase;

class FormInputTest extends TestCase
{
	private function makeFieldWithTable(string $name, $type, array $options = []): Field
	{
		$table = new Table('test_table');
		$pk = new PrimaryKey();
		$idField = new Field('id');
		$idField->setType(new TypeInteger());
		$idField->setAutoIncrement(true);
		$idField->setRequired(false);
		$idField->setUnique(false);
		$pk->addField($idField);
		$table->setPrimaryKey($pk);
		$table->addField($idField);

		$field = new Field($name);
		$field->setType($type);
		$field->setAutoIncrement($options['autoIncrement'] ?? false);
		$field->setRequired($options['required'] ?? false);
		$field->setUnique($options['unique'] ?? false);
		$field->setMaxLength($options['maxLength'] ?? 0);
		$field->setMinLength($options['minLength'] ?? 0);
		$field->setLabel($options['label'] ?? ucfirst($name));
		$field->setPlaceholder($options['placeholder'] ?? null);
		$field->setHint($options['hint'] ?? null);
		$table->addField($field);

		return $field;
	}

	// ── Text Input ──

	/** @test */
	public function text_input_view_mode()
	{
		$field = $this->makeFieldWithTable('username', new TypeVarchar(), [
			'label' => 'Username',
		]);
		$field->loadValueFromDb('john_doe');

		$input = new Text();
		$input->setField($field);
		$input->setDrawMode(DrawMode::VIEW);
		$html = $input->draw();

		$this->assertStringContainsString('john_doe', $html);
		$this->assertStringContainsString('Username', $html);
		$this->assertStringContainsString('form-control-plaintext', $html);
		$this->assertStringNotContainsString('<input', $html);
	}

	/** @test */
	public function text_input_update_mode()
	{
		$field = $this->makeFieldWithTable('email', new TypeVarchar(), [
			'label' => 'Email',
			'maxLength' => 100,
			'required' => true,
		]);
		$field->loadValueFromDb('test@example.com');

		$input = new Text();
		$input->setField($field);
		$input->setDrawMode(DrawMode::UPDATE);
		$html = $input->draw();

		$this->assertStringContainsString('<input', $html);
		$this->assertStringContainsString('form-control', $html);
		$this->assertStringContainsString('Email', $html);
		$this->assertStringContainsString('required', $html);
		$this->assertStringContainsString('test@example.com', $html);
	}

	/** @test */
	public function text_input_insert_mode()
	{
		$field = $this->makeFieldWithTable('name', new TypeVarchar(), [
			'label' => 'Name',
		]);

		$input = new Text();
		$input->setField($field);
		$input->setDrawMode(DrawMode::INSERT);
		$html = $input->draw();

		$this->assertStringContainsString('<input', $html);
		$this->assertStringContainsString('Name', $html);
	}

	/** @test */
	public function text_input_with_hint()
	{
		$field = $this->makeFieldWithTable('code', new TypeVarchar(), [
			'label' => 'Code',
			'hint' => 'Enter a unique code',
		]);

		$input = new Text();
		$input->setField($field);
		$input->setDrawMode(DrawMode::UPDATE);
		$html = $input->draw();

		$this->assertStringContainsString('Enter a unique code', $html);
		$this->assertStringContainsString('form-text text-muted', $html);
	}

	/** @test */
	public function text_input_copies_field_attributes()
	{
		$field = $this->makeFieldWithTable('age', new TypeVarchar(), [
			'label' => 'Age',
			'maxLength' => 3,
			'placeholder' => 'Enter age',
		]);

		$input = new Text();
		$input->setField($field);

		$this->assertSame('Age', $input->getLabel());
		$this->assertSame('Enter age', $input->getPlaceholder());
		// Field name is rot13'd for form security
		$this->assertSame(str_rot13('age'), $input->getName());
	}

	/** @test */
	public function text_input_default_draw_mode_is_view()
	{
		$field = $this->makeFieldWithTable('notes', new TypeVarchar());
		$field->loadValueFromDb('some notes');

		$input = new Text();
		$input->setField($field);
		$html = $input->draw();

		$this->assertStringContainsString('form-control-plaintext', $html);
	}

	// ── TextArea ──

	/** @test */
	public function textarea_update_mode()
	{
		$field = $this->makeFieldWithTable('description', new TypeText(), [
			'label' => 'Description',
		]);
		$field->loadValueFromDb('Long text here');

		$input = new TextArea();
		$input->setField($field);
		$input->setDrawMode(DrawMode::UPDATE);
		$html = $input->draw();

		$this->assertStringContainsString('<textarea', $html);
		$this->assertStringContainsString('Long text here', $html);
		$this->assertStringContainsString('form-control', $html);
	}

	/** @test */
	public function textarea_view_mode_uses_parent()
	{
		$field = $this->makeFieldWithTable('bio', new TypeText(), [
			'label' => 'Bio',
		]);
		$field->loadValueFromDb('My bio');

		$input = new TextArea();
		$input->setField($field);
		$input->setDrawMode(DrawMode::VIEW);
		$html = $input->draw();

		$this->assertStringContainsString('My bio', $html);
		$this->assertStringContainsString('form-control-plaintext', $html);
	}

	// ── Hidden ──

	/** @test */
	public function hidden_input_update_mode()
	{
		$field = $this->makeFieldWithTable('token', new TypeVarchar());
		$field->loadValueFromDb('abc123');

		$input = new Hidden();
		$input->setField($field);
		$input->setDrawMode(DrawMode::UPDATE);
		$html = $input->draw();

		$this->assertStringContainsString('<input', $html);
		$this->assertStringContainsString('d-none', $html);
		$this->assertStringContainsString('abc123', $html);
	}

	/** @test */
	public function hidden_input_view_mode()
	{
		$field = $this->makeFieldWithTable('secret', new TypeVarchar());
		$field->loadValueFromDb('hidden_val');

		$input = new Hidden();
		$input->setField($field);
		$input->setDrawMode(DrawMode::VIEW);
		$html = $input->draw();

		$this->assertStringContainsString('d-none', $html);
	}

	// ── FormElementTrait properties ──

	/** @test */
	public function form_element_disabled()
	{
		$input = new Text();
		$this->assertFalse($input->isDisabled());

		$input->setDisabled(true);
		$this->assertTrue($input->isDisabled());
	}

	/** @test */
	public function form_element_readonly()
	{
		$input = new Text();
		$this->assertFalse($input->isReadonly());

		$input->setReadonly(true);
		$this->assertTrue($input->isReadonly());
	}

	/** @test */
	public function form_element_hidden()
	{
		$input = new Text();
		$this->assertFalse($input->isHidden());

		$input->setHidden(true);
		$this->assertTrue($input->isHidden());
	}

	/** @test */
	public function form_element_excluded()
	{
		$input = new Text();
		$this->assertFalse($input->isExcluded());

		$input->setExcluded(true);
		$this->assertTrue($input->isExcluded());
	}

	/** @test */
	public function form_element_value()
	{
		$input = new Text();
		$input->setValue('hello');
		$this->assertSame('hello', $input->getValue());
	}

	/** @test */
	public function form_element_dimensions()
	{
		$input = new Text();
		$input->setHeight(100);
		$input->setWidth(200);
		$input->setCols(5);
		$input->setRows(10);

		$this->assertSame(100, $input->getHeight());
		$this->assertSame(200, $input->getWidth());
		$this->assertSame(5, $input->getCols());
		$this->assertSame(10, $input->getRows());
	}

	/** @test */
	public function form_element_constraints()
	{
		$input = new Text();
		$input->setMaxLength(100);
		$input->setMinLength(5);
		$input->setMax(999);
		$input->setMin(1);
		$input->setStep(2);
		$input->setPattern('[0-9]+');

		$this->assertSame(100, $input->getMaxLength());
		$this->assertSame(5, $input->getMinLength());
		$this->assertSame(999, $input->getMax());
		$this->assertSame(1, $input->getMin());
		$this->assertSame(2, $input->getStep());
		$this->assertSame('[0-9]+', $input->getPattern());
	}

	/** @test */
	public function form_element_type()
	{
		$input = new Text();
		$this->assertSame(InputType::Text, $input->getType());
	}

	/** @test */
	public function form_element_multiple()
	{
		$input = new Text();
		$this->assertFalse($input->isMultiple());

		$input->setMultiple(true);
		$this->assertTrue($input->isMultiple());
	}

	/** @test */
	public function form_element_size()
	{
		$input = new Text();
		$input->setSize(10);
		$this->assertSame(10, $input->getSize());
	}
}
