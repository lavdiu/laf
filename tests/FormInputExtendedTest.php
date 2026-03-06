<?php

use Laf\Database\Field\Field;
use Laf\Database\Field\TypeVarchar;
use Laf\Database\Field\TypeInteger;
use Laf\Database\Field\TypeBool;
use Laf\Database\PrimaryKey;
use Laf\Database\Table;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\Input\Checkbox;
use Laf\UI\Form\Input\Color;
use Laf\UI\Form\Input\Email;
use Laf\UI\Form\Input\Password;
use Laf\UI\Form\Input\Search;
use Laf\UI\Form\InputType;
use Laf\UI\Form\Control\Button;
use Laf\UI\Form\Control\SubmitButton;
use Laf\UI\Form\Control\ButtonType;
use PHPUnit\Framework\TestCase;

class FormInputExtendedTest extends TestCase
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

    // ── Email Input ──

    /** @test */
    public function email_input_update_mode_sets_type_email(): void
    {
        $field = $this->makeFieldWithTable('email', new TypeVarchar(), ['label' => 'Email']);
        $field->loadValueFromDb('user@example.com');

        $input = new Email();
        $input->setField($field);
        $input->setDrawMode(DrawMode::UPDATE);
        $html = $input->draw();

        $this->assertStringContainsString('<input', $html);
        $this->assertStringContainsString('email', $html);
        $this->assertStringContainsString('user@example.com', $html);
        $this->assertStringContainsString('Email', $html);
    }

    /** @test */
    public function email_input_view_mode_shows_value(): void
    {
        $field = $this->makeFieldWithTable('email', new TypeVarchar(), ['label' => 'Email']);
        $field->loadValueFromDb('test@test.com');

        $input = new Email();
        $input->setField($field);
        $input->setDrawMode(DrawMode::VIEW);
        $html = $input->draw();

        $this->assertStringContainsString('test@test.com', $html);
        $this->assertStringContainsString('form-control-plaintext', $html);
    }

    /** @test */
    public function email_component_css_class(): void
    {
        $input = new Email();
        $this->assertStringContainsString('Email', $input->getComponentCssControlClass());
    }

    // ── Password Input ──

    /** @test */
    public function password_input_update_mode_sets_type_password(): void
    {
        $field = $this->makeFieldWithTable('password', new TypeVarchar(), ['label' => 'Password']);

        $input = new Password();
        $input->setField($field);
        $input->setDrawMode(DrawMode::UPDATE);
        $html = $input->draw();

        $this->assertStringContainsString('<input', $html);
        $this->assertStringContainsString('password', $html);
    }

    /** @test */
    public function password_component_css_class(): void
    {
        $input = new Password();
        $this->assertStringContainsString('Password', $input->getComponentCssControlClass());
    }

    // ── Color Input ──

    /** @test */
    public function color_input_update_mode_sets_type_color(): void
    {
        $field = $this->makeFieldWithTable('color', new TypeVarchar(), ['label' => 'Color']);
        $field->loadValueFromDb('#ff0000');

        $input = new Color();
        $input->setField($field);
        $input->setDrawMode(DrawMode::UPDATE);
        $html = $input->draw();

        $this->assertStringContainsString('<input', $html);
        $this->assertStringContainsString('#ff0000', $html);
    }

    /** @test */
    public function color_component_css_class(): void
    {
        $input = new Color();
        $this->assertStringContainsString('Color', $input->getComponentCssControlClass());
    }

    // ── Search Input ──

    /** @test */
    public function search_input_update_mode_sets_type_search(): void
    {
        $field = $this->makeFieldWithTable('query', new TypeVarchar(), ['label' => 'Search']);
        $field->loadValueFromDb('test query');

        $input = new Search();
        $input->setField($field);
        $input->setDrawMode(DrawMode::UPDATE);
        $html = $input->draw();

        $this->assertStringContainsString('<input', $html);
        $this->assertStringContainsString('test query', $html);
    }

    /** @test */
    public function search_component_css_class(): void
    {
        $input = new Search();
        $this->assertStringContainsString('Search', $input->getComponentCssControlClass());
    }

    // ── Checkbox Input ──

    /** @test */
    public function checkbox_view_mode_shows_yes_for_truthy(): void
    {
        $field = $this->makeFieldWithTable('active', new TypeBool(), ['label' => 'Active']);
        $field->loadValueFromDb(1);

        $input = new Checkbox();
        $input->setField($field);
        $input->setDrawMode(DrawMode::VIEW);
        $html = $input->draw();

        $this->assertStringContainsString('Yes', $html);
    }

    /** @test */
    public function checkbox_view_mode_shows_no_for_zero(): void
    {
        $field = $this->makeFieldWithTable('active', new TypeBool(), ['label' => 'Active']);
        $field->loadValueFromDb(0);

        $input = new Checkbox();
        $input->setField($field);
        $input->setDrawMode(DrawMode::VIEW);
        $html = $input->draw();

        $this->assertStringContainsString('No', $html);
    }

    /** @test */
    public function checkbox_view_mode_shows_no_for_null(): void
    {
        $field = $this->makeFieldWithTable('active', new TypeBool(), ['label' => 'Active']);

        $input = new Checkbox();
        $input->setField($field);
        $input->setDrawMode(DrawMode::VIEW);
        $html = $input->draw();

        // Null is treated as falsy, so Checkbox renders "No"
        $this->assertStringContainsString('No', $html);
    }

    /** @test */
    public function checkbox_component_css_class(): void
    {
        $input = new Checkbox();
        $this->assertStringContainsString('Checkbox', $input->getComponentCssControlClass());
    }

    // ── Button ──

    /** @test */
    public function button_draws_button_element(): void
    {
        $btn = new Button();
        $btn->setValue('Click Me');
        $btn->setId('btn_test');
        $html = $btn->draw();

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('Click Me', $html);
    }

    /** @test */
    public function button_type_is_button_by_default(): void
    {
        $btn = new Button();
        $this->assertEquals(InputType::Button, $btn->getType());
    }

    /** @test */
    public function button_wrap_in_div_default_true(): void
    {
        $btn = new Button();
        $this->assertTrue($btn->getWrapInDiv());
    }

    /** @test */
    public function button_wrap_in_div_can_be_disabled(): void
    {
        $btn = new Button();
        $btn->setWrapInDiv(false);
        $this->assertFalse($btn->getWrapInDiv());

        $btn->setId('btn_nowrap');
        $btn->setValue('No Wrap');
        $html = $btn->draw();

        $this->assertStringContainsString('<button', $html);
        $this->assertStringNotContainsString('form-group', $html);
    }

    /** @test */
    public function button_set_field_throws_exception(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid call');

        $btn = new Button();
        $field = new Field('test');
        $btn->setField($field);
    }

    /** @test */
    public function button_get_field_throws_exception(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid call');

        $btn = new Button();
        $btn->getField();
    }

    /** @test */
    public function button_with_css_classes(): void
    {
        $btn = new Button();
        $btn->setId('btn_styled');
        $btn->setValue('Styled');
        $btn->addCssClass('btn');
        $btn->addCssClass('btn-primary');
        $html = $btn->draw();

        $this->assertStringContainsString('btn', $html);
        $this->assertStringContainsString('btn-primary', $html);
    }

    // ── SubmitButton ──

    /** @test */
    public function submit_button_type_is_submit(): void
    {
        $btn = new SubmitButton();
        $this->assertEquals(InputType::Submit, $btn->getType());
    }

    /** @test */
    public function submit_button_draws_button_element(): void
    {
        $btn = new SubmitButton();
        $btn->setId('btn_submit');
        $btn->setValue('Submit');
        $html = $btn->draw();

        $this->assertStringContainsString('<button', $html);
        $this->assertStringContainsString('Submit', $html);
    }

    /** @test */
    public function submit_button_set_field_throws_exception(): void
    {
        $this->expectException(\Exception::class);
        $btn = new SubmitButton();
        $btn->setField(new Field('test'));
    }

    /** @test */
    public function submit_button_get_field_throws_exception(): void
    {
        $this->expectException(\Exception::class);
        $btn = new SubmitButton();
        $btn->getField();
    }

    /** @test */
    public function submit_button_component_css_class(): void
    {
        $btn = new SubmitButton();
        $this->assertStringContainsString('SubmitButton', $btn->getComponentCssControlClass());
    }

    // ── ButtonType Constants ──

    /** @test */
    public function button_type_constants(): void
    {
        $this->assertEquals('button', ButtonType::Button);
        $this->assertEquals('reset', ButtonType::Reset);
        $this->assertEquals('submit', ButtonType::Submit);
    }
}
