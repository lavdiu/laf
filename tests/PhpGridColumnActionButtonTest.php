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
    public function column_constructor_defaults_are_null_safe(): void
    {
        $col = new Column('x');
        $this->assertSame('', $col->getLabel());
        $this->assertNull($col->getHref());
        $this->assertNull($col->getTarget());
        $this->assertSame('', $col->getInnerElementCssStyle());
        $this->assertSame('', $col->getInnerElementCssClass());
        $this->assertSame('', $col->getOuterElementCssStyle());
        $this->assertSame('', $col->getOuterElementCssClass());
        $this->assertSame('', $col->getInnerElementAttributes());
        $this->assertSame('', $col->getFormat());
        $this->assertTrue($col->isSearchable());
        $this->assertTrue($col->isSortable());
        $this->assertFalse($col->isNumeric());

        $empty = new Column();
        $this->assertSame('', $empty->getFieldName());
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
            ->setOuterElementAttributes('data-col="1"')
            ->setSearchable(false)
            ->setSortable(false)
            ->setSearchOperator('eq')
            ->setWildcardMode(Column::WILDCARD_STARTS_WITH)
            ->setCaseInsensitive(true)
            ->setCaseInsensitiveSort(false);

        $this->assertInstanceOf(Column::class, $result);
        $this->assertEquals('new_id', $col->getFieldName());
        $this->assertEquals('New ID', $col->getLabel());
        $this->assertEquals('integer', $col->getFormat());
        $this->assertTrue($col->isNumeric());
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
        $this->assertFalse($col->isSearchable());
        $this->assertFalse($col->isSortable());
        $this->assertSame('eq', $col->getSearchOperator());
        $this->assertSame('startswith', $col->getWildcardMode());
        $this->assertTrue($col->isCaseInsensitive());
        $this->assertFalse($col->isCaseInsensitiveSort());
    }

    /** @test */
    public function column_empty_string_setters_mean_unset(): void
    {
        $col = new Column('id');
        $col->setHref('')->setTarget('')->setJsCallback('')->setSearchOperator('')->setWildcardMode('');
        $this->assertNull($col->getHref());
        $this->assertNull($col->getTarget());
        $this->assertNull($col->getJsCallback());
        $this->assertNull($col->getSearchOperator());
        $this->assertNull($col->getWildcardMode());
    }

    /** @test */
    public function column_attributes_accept_arrays(): void
    {
        $col = new Column('id');
        $col->setInnerElementAttributes(['data-x' => '1'])->setOuterElementAttributes(['title' => 'T']);
        $this->assertSame('{"data-x":"1"}', $col->getInnerElementAttributes());
        $this->assertSame('{"title":"T"}', $col->getOuterElementAttributes());
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
    public function column_json_keys_and_shapes_are_the_grid_js_contract(): void
    {
        $col = new Column('name', 'Name');
        $json = json_decode(json_encode($col), true);

        $this->assertSame([
            'index', 'fieldName', 'label', 'format', 'href', 'target',
            'innerElementCssStyle', 'innerElementCssClass', 'outerElementCssStyle', 'outerElementCssClass',
            'visible', 'exportable', 'innerElementAttributes', 'outerElementAttributes',
            'caseInsensitive', 'caseInsensitiveSort', 'searchOperator', 'wildcardMode', 'jsCallback',
            'searchable', 'sortable',
        ], array_keys($json));

        // grid.js decides "is a link" with href != null and reads visible as a boolean
        $this->assertNull($json['href']);
        $this->assertNull($json['target']);
        $this->assertNull($json['jsCallback']);
        $this->assertNull($json['caseInsensitive']);
        $this->assertSame('', $json['innerElementCssClass']);
        $this->assertTrue($json['visible']);
        $this->assertTrue($json['exportable']);
        $this->assertSame(0, $json['index']);
    }

    /** @test */
    public function column_round_trips_through_its_own_json(): void
    {
        $col = (new Column('salary', 'Salary', false, false, '/x/{id}', '_blank', 'color:red', 'badge', 'w:1', 'c'))
            ->setIndex(3)
            ->setFormat('float')
            ->setJsCallback('fmt')
            ->setSearchOperator('gteq')
            ->setWildcardMode('endswith')
            ->setCaseInsensitive(true)
            ->setCaseInsensitiveSort(true)
            ->setSearchable(false)
            ->setSortable(false)
            ->setInnerElementAttributes('{"a":"b"}');

        $copy = Column::createFromAssocArray(json_decode(json_encode($col), true));
        $this->assertSame(json_encode($col), json_encode($copy));

        $copy2 = Column::createFromJsonString(json_encode($col));
        $this->assertSame(json_encode($col), json_encode($copy2));
        $this->assertFalse($copy2->isVisible());
        $this->assertSame(3, $copy2->getIndex());
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
        $this->assertFalse($col->isExportable());
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
        $this->assertFalse($col->isVisible());
    }

    /** @test */
    public function column_create_from_array_casts_loose_values(): void
    {
        $col = Column::createFromAssocArray([
            'fieldName' => ' id ',
            'label' => null,
            'visible' => '0',
            'exportable' => 'true',
            'caseInsensitive' => 1,
            'caseInsensitiveSort' => '',
            'index' => '7',
            'href' => null,
            'innerElementAttributes' => ['data-a' => 1],
        ]);
        $this->assertSame('id', $col->getFieldName());
        $this->assertSame('', $col->getLabel());
        $this->assertFalse($col->isVisible());
        $this->assertTrue($col->isExportable());
        $this->assertTrue($col->isCaseInsensitive());
        $this->assertNull($col->isCaseInsensitiveSort());
        $this->assertSame(7, $col->getIndex());
        $this->assertNull($col->getHref());
        $this->assertSame('{"data-a":1}', $col->getInnerElementAttributes());

        $noName = Column::createFromAssocArray(['label' => 'x']);
        $this->assertSame('', $noName->getFieldName());
        $bad = Column::createFromJsonString('not json');
        $this->assertSame('', $bad->getFieldName());
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
        $this->assertArrayNotHasKey('unknownProp', json_decode(json_encode($col), true));
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
        $this->assertNull($btn->getIcon());
    }

    /** @test */
    public function action_button_setters_are_fluent(): void
    {
        $btn = new ActionButton('View', '/view');
        $result = $btn->setLabel('Details')
            ->setHref('/details/{id}')
            ->setIcon('fa fa-eye')
            ->setCssClass('text-danger');

        $this->assertInstanceOf(ActionButton::class, $result);
        $this->assertEquals('Details', $btn->getLabel());
        $this->assertEquals('/details/{id}', $btn->getHref());
        $this->assertEquals('fa fa-eye', $btn->getIcon());
        $this->assertEquals('text-danger', $btn->getCssClass());
        $btn->setIcon('')->setCssClass('');
        $this->assertNull($btn->getIcon());
        $this->assertNull($btn->getCssClass());
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

        $btn->setJsCallback('MyApp.other');
        $this->assertEquals('MyApp.other', $btn->getJsCallback());
        $btn->setJsCallback(null);
        $this->assertNull($btn->getJsCallback());
    }

    /** @test */
    public function action_button_json_keeps_icon_key_when_null_and_attributes_as_object(): void
    {
        $btn = new ActionButton('Delete', '?action=delete&id={id}');
        $json = json_decode(json_encode($btn), true);
        $this->assertSame(['label', 'href', 'icon', 'attributeList', 'jsCallback', 'cssClass'], array_keys($json));
        $this->assertArrayHasKey('icon', $json);
        $this->assertNull($json['icon']);
        $this->assertSame('[]', json_encode($btn->getAttributes()));

        $btn->addAttribute('onclick', 'return confirm("x")');
        $this->assertSame('{"onclick":"return confirm(\"x\")"}', json_encode(json_decode(json_encode($btn), true)['attributeList']));
    }

    /** @test */
    public function action_button_from_assoc_array(): void
    {
        $btn = ActionButton::createFromAssocArray([
            'label' => 'Edit',
            'href' => '/e/{id}',
            'icon' => null,
            'attributeList' => ['target' => '_blank'],
            'jsCallback' => 'cb',
            'cssClass' => 'x',
            'unknown' => 1,
        ]);
        $this->assertSame('Edit', $btn->getLabel());
        $this->assertNull($btn->getIcon());
        $this->assertSame(['target' => '_blank'], $btn->getAttributes());
        $this->assertSame('cb', $btn->getJsCallback());
        $this->assertSame('x', $btn->getCssClass());

        $copy = ActionButton::createFromAssocArray(json_decode(json_encode($btn), true));
        $this->assertSame(json_encode($btn), json_encode($copy));
    }
}
