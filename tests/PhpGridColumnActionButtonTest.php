<?php

use Laf\UI\Grid\PhpGrid\Column;
use Laf\UI\Grid\PhpGrid\ActionButton;
use PHPUnit\Framework\TestCase;

class PhpGridColumnActionButtonTest extends TestCase
{
    // ── Column ──

    /** @test */
    public function column_constructor_sets_properties(): void
    {
        $col = new Column('user_name', 'User Name', true, true, '/user/{id}', '_blank');

        $this->assertEquals('user_name', $col->getFieldName());
        $this->assertEquals('User Name', $col->getLabel());
        $this->assertTrue($col->isVisible());
        $this->assertTrue($col->isExportable());
        $this->assertEquals('/user/{id}', $col->getHref());
        $this->assertEquals('_blank', $col->getTarget());
    }

    /** @test */
    public function column_constructor_trims_field_name(): void
    {
        $col = new Column('  name  ', 'Name');
        $this->assertEquals('name', $col->getFieldName());
    }

    /** @test */
    public function column_setters_are_fluent(): void
    {
        $col = new Column('id');
        $result = $col->setFieldName('new_id')
            ->setLabel('New ID')
            ->setFormat('integer')
            ->setHref('/item/{id}')
            ->setTarget('_self')
            ->setVisible(false)
            ->setExportable(false)
            ->setIndex(5)
            ->setInnerElementCssStyle('color:red')
            ->setInnerElementCssClass('badge')
            ->setOuterElementCssStyle('font-weight:bold')
            ->setOuterElementCssClass('col-md-6')
            ->setInnerElementAttributes('data-type="id"')
            ->setOuterElementAttributes('data-col="1"');

        $this->assertInstanceOf(Column::class, $result);
        $this->assertEquals('new_id', $col->getFieldName());
        $this->assertEquals('New ID', $col->getLabel());
        $this->assertEquals('integer', $col->getFormat());
        $this->assertEquals('/item/{id}', $col->getHref());
        $this->assertEquals('_self', $col->getTarget());
        $this->assertFalse($col->isVisible());
        $this->assertFalse($col->isExportable());
        $this->assertEquals(5, $col->getIndex());
        $this->assertEquals('color:red', $col->getInnerElementCssStyle());
        $this->assertEquals('badge', $col->getInnerElementCssClass());
        $this->assertEquals('font-weight:bold', $col->getOuterElementCssStyle());
        $this->assertEquals('col-md-6', $col->getOuterElementCssClass());
        $this->assertEquals('data-type="id"', $col->getInnerElementAttributes());
        $this->assertEquals('data-col="1"', $col->getOuterElementAttributes());
    }

    /** @test */
    public function column_js_callback(): void
    {
        $col = new Column('status');
        $this->assertNull($col->getJsCallback());

        $col->setJsCallback('MyApp.formatStatus');
        $this->assertEquals('MyApp.formatStatus', $col->getJsCallback());
    }

    /** @test */
    public function column_create_from_json_string(): void
    {
        $json = json_encode([
            'fieldName' => 'email',
            'label' => 'Email Address',
            'format' => 'text',
            'href' => 'mailto:{email}',
            'visible' => true,
            'exportable' => false,
        ]);

        $col = Column::createFromJsonString($json);

        $this->assertEquals('email', $col->getFieldName());
        $this->assertEquals('Email Address', $col->getLabel());
        $this->assertEquals('text', $col->getFormat());
        $this->assertEquals('mailto:{email}', $col->getHref());
    }

    /** @test */
    public function column_create_from_assoc_array(): void
    {
        $settings = [
            'fieldName' => 'age',
            'label' => 'Age',
            'format' => 'integer',
            'visible' => false,
            'innerElementCssClass' => 'text-right',
        ];

        $col = Column::createFromAssocArray($settings);

        $this->assertEquals('age', $col->getFieldName());
        $this->assertEquals('Age', $col->getLabel());
        $this->assertEquals('integer', $col->getFormat());
    }

    /** @test */
    public function column_create_from_array_ignores_unknown_properties(): void
    {
        $settings = [
            'fieldName' => 'test',
            'unknownProp' => 'should_be_ignored',
        ];

        $col = Column::createFromAssocArray($settings);
        $this->assertEquals('test', $col->getFieldName());
        // Should not throw, unknown prop is silently ignored
    }

    /** @test */
    public function column_set_property_value(): void
    {
        $col = new Column('id');
        $col->setPropertyValue('label', 'ID Number');
        $this->assertEquals('ID Number', $col->getLabel());
    }

    /** @test */
    public function column_set_property_value_ignores_unknown(): void
    {
        $col = new Column('id');
        $col->setPropertyValue('nonexistentProp', 'value');
        // Should not throw
        $this->assertEquals('id', $col->getFieldName());
    }

    /** @test */
    public function column_per_column_tuning_defaults(): void
    {
        $col = new Column('name');
        $this->assertNull($col->caseInsensitive);
        $this->assertNull($col->caseInsensitiveSort);
        $this->assertNull($col->searchOperator);
        $this->assertNull($col->wildcardMode);
    }

    /** @test */
    public function column_per_column_tuning_can_be_set(): void
    {
        $col = new Column('name');
        $col->caseInsensitive = true;
        $col->caseInsensitiveSort = true;
        $col->searchOperator = 'eq';
        $col->wildcardMode = 'startswith';

        $this->assertTrue($col->caseInsensitive);
        $this->assertTrue($col->caseInsensitiveSort);
        $this->assertEquals('eq', $col->searchOperator);
        $this->assertEquals('startswith', $col->wildcardMode);
    }

    // ── ActionButton ──

    /** @test */
    public function action_button_constructor(): void
    {
        $btn = new ActionButton('Edit', '/edit/{id}', 'fa fa-edit');

        $this->assertEquals('Edit', $btn->getLabel());
        $this->assertEquals('/edit/{id}', $btn->getHref());
        $this->assertEquals('fa fa-edit', $btn->getIcon());
    }

    /** @test */
    public function action_button_constructor_without_icon(): void
    {
        $btn = new ActionButton('Delete', '/delete/{id}');

        $this->assertEquals('Delete', $btn->getLabel());
        $this->assertEquals('/delete/{id}', $btn->getHref());
        // icon property is null when not provided
        $this->assertNull($btn->icon);
    }

    /** @test */
    public function action_button_setters_are_fluent(): void
    {
        $btn = new ActionButton('View', '/view');
        $result = $btn->setLabel('Details')
            ->setHref('/details/{id}')
            ->setIcon('fa fa-eye');

        $this->assertInstanceOf(ActionButton::class, $result);
        $this->assertEquals('Details', $btn->getLabel());
        $this->assertEquals('/details/{id}', $btn->getHref());
        $this->assertEquals('fa fa-eye', $btn->getIcon());
    }

    /** @test */
    public function action_button_attributes(): void
    {
        $btn = new ActionButton('Click', '/url');
        $this->assertEmpty($btn->getAttributes());

        $btn->addAttribute('data-confirm', 'Are you sure?');
        $btn->addAttribute('class', 'btn btn-danger');

        $attrs = $btn->getAttributes();
        $this->assertCount(2, $attrs);
        $this->assertEquals('Are you sure?', $attrs['data-confirm']);
        $this->assertEquals('btn btn-danger', $attrs['class']);
    }

    /** @test */
    public function action_button_set_attributes_replaces(): void
    {
        $btn = new ActionButton('Click', '/url');
        $btn->addAttribute('old', 'value');

        $btn->setAttributes(['new' => 'value2']);
        $this->assertCount(1, $btn->getAttributes());
        $this->assertArrayNotHasKey('old', $btn->getAttributes());
    }

    /** @test */
    public function action_button_js_callback(): void
    {
        $btn = new ActionButton('Check', '/check');
        $this->assertNull($btn->getJsCallback());

        $btn->setJsCallbackk('MyApp.buttons.check');
        $this->assertEquals('MyApp.buttons.check', $btn->getJsCallback());
    }
}
