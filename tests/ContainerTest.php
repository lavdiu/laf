<?php

use Laf\UI\Component\Alert;
use Laf\UI\Container\Card;
use Laf\UI\Container\CardBody;
use Laf\UI\Container\CardFooter;
use Laf\UI\Container\CardHeader;
use Laf\UI\Container\Column;
use Laf\UI\Container\ContainerType;
use Laf\UI\Container\Div;
use Laf\UI\Container\GenericContainer;
use Laf\UI\Container\Row;
use Laf\UI\Container\TabContainer;
use Laf\UI\Container\TabContent;
use Laf\UI\Container\TabItem;
use Laf\UI\Form\DrawMode;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
	// ── Div ──

	/** @test */
	public function div_renders_with_classes_and_styles()
	{
		$div = new Div(['my-class'], ['color' => 'red']);
		$html = $div->draw();

		$this->assertStringContainsString('<div', $html);
		$this->assertStringContainsString('</div>', $html);
		$this->assertStringContainsString('my-class', $html);
		$this->assertStringContainsString('color:red', $html);
	}

	/** @test */
	public function div_renders_child_components()
	{
		$div = new Div();
		$alert = new Alert('', 'Child content', 'info');
		$div->addComponent($alert);
		$html = $div->draw();

		$this->assertStringContainsString('Child content', $html);
		$this->assertStringContainsString('alert-info', $html);
	}

	/** @test */
	public function div_propagates_draw_mode_to_children()
	{
		$div = new Div();
		$child = new Div();
		$div->addComponent($child);
		$div->setDrawMode(DrawMode::UPDATE);
		$div->draw();

		$this->assertSame(DrawMode::UPDATE, $child->getDrawMode());
	}

	// ── GenericContainer ──

	/** @test */
	public function generic_container_renders()
	{
		$container = new GenericContainer(['container']);
		$html = $container->draw();

		$this->assertStringContainsString('<div', $html);
		$this->assertStringContainsString('container', $html);
	}

	/** @test */
	public function generic_container_with_container_type()
	{
		$container = new GenericContainer();
		$container->setContainerType(ContainerType::TYPE_FLUID);
		$html = $container->draw();

		$this->assertStringContainsString('container-fluid', $html);
	}

	// ── Row ──

	/** @test */
	public function row_renders_with_row_class()
	{
		$row = new Row();
		$html = $row->draw();

		$this->assertStringContainsString('row', $html);
		$this->assertStringContainsString('<div', $html);
	}

	/** @test */
	public function row_with_children()
	{
		$row = new Row();
		$col = new Column();
		$alert = new Alert('', 'In column', 'primary');
		$col->addComponent($alert);
		$row->addComponent($col);
		$html = $row->draw();

		$this->assertStringContainsString('row', $html);
		$this->assertStringContainsString('col-sm', $html);
		$this->assertStringContainsString('In column', $html);
	}

	// ── Column ──

	/** @test */
	public function column_renders_with_col_class()
	{
		$col = new Column();
		$html = $col->draw();

		$this->assertStringContainsString('col-sm', $html);
	}

	/** @test */
	public function column_with_custom_class()
	{
		$col = new Column(['col-md-6']);
		$html = $col->draw();

		$this->assertStringContainsString('col-md-6', $html);
	}

	// ── Card ──

	/** @test */
	public function card_renders_with_card_class()
	{
		$card = new Card();
		$html = $card->draw();

		$this->assertStringContainsString('card', $html);
	}

	/** @test */
	public function card_with_header_body_footer()
	{
		$card = new Card();
		$header = new CardHeader();
		$header->addComponent(new Alert('', 'Header text', 'info'));
		$body = new CardBody();
		$body->addComponent(new Alert('', 'Body text', 'info'));
		$footer = new CardFooter();
		$footer->addComponent(new Alert('', 'Footer text', 'info'));

		$card->addComponent($header);
		$card->addComponent($body);
		$card->addComponent($footer);
		$html = $card->draw();

		$this->assertStringContainsString('card-header', $html);
		$this->assertStringContainsString('card-body', $html);
		$this->assertStringContainsString('card-footer', $html);
		$this->assertStringContainsString('Header text', $html);
		$this->assertStringContainsString('Body text', $html);
		$this->assertStringContainsString('Footer text', $html);
	}

	/** @test */
	public function card_header_renders_with_class()
	{
		$header = new CardHeader();
		$html = $header->draw();
		$this->assertStringContainsString('card-header', $html);
	}

	/** @test */
	public function card_body_renders_with_class()
	{
		$body = new CardBody();
		$html = $body->draw();
		$this->assertStringContainsString('card-body', $html);
	}

	/** @test */
	public function card_footer_renders_with_class()
	{
		$footer = new CardFooter();
		$html = $footer->draw();
		$this->assertStringContainsString('card-footer', $html);
	}

	// ── TabContainer ──

	/** @test */
	public function tab_container_renders_tabs()
	{
		$tabs = new TabContainer('my-tabs');
		$tab1 = new TabItem('General', 'fa fa-home');
		$tab1->addComponent(new Alert('', 'Tab 1 content', 'info'));
		$tab2 = new TabItem('Settings', 'fa fa-cog');
		$tab2->addComponent(new Alert('', 'Tab 2 content', 'info'));

		$tabs->addComponent($tab1);
		$tabs->addComponent($tab2);
		$html = $tabs->draw();

		$this->assertStringContainsString('nav-tabs', $html);
		$this->assertStringContainsString('tab-content', $html);
		$this->assertStringContainsString('General', $html);
		$this->assertStringContainsString('Settings', $html);
		$this->assertStringContainsString('Tab 1 content', $html);
		$this->assertStringContainsString('Tab 2 content', $html);
	}

	/** @test */
	public function tab_container_first_tab_active()
	{
		$tabs = new TabContainer('test-tabs');
		$tab1 = new TabItem('First');
		$tab2 = new TabItem('Second');
		$tabs->addComponent($tab1);
		$tabs->addComponent($tab2);
		$tabs->draw();

		$this->assertTrue($tab1->isActive());
		$this->assertFalse($tab2->isActive());
	}

	/** @test */
	public function tab_container_with_nav_buttons()
	{
		$tabs = new TabContainer('nav-tabs', true);
		$tab = new TabItem('Tab');
		$tabs->addComponent($tab);
		$html = $tabs->draw();

		$this->assertStringContainsString('Previous', $html);
		$this->assertStringContainsString('Next', $html);
	}

	/** @test */
	public function tab_container_without_nav_buttons()
	{
		$tabs = new TabContainer('no-nav');
		$tab = new TabItem('Tab');
		$tabs->addComponent($tab);
		$html = $tabs->draw();

		$this->assertStringNotContainsString('Previous', $html);
		$this->assertStringNotContainsString('Next', $html);
	}

	/** @test */
	public function tab_container_id()
	{
		$tabs = new TabContainer('custom-id');
		$this->assertSame('custom-id', $tabs->getId());

		$tabs->setId('new-id');
		$this->assertSame('new-id', $tabs->getId());
	}

	/** @test */
	public function tab_container_auto_generates_id()
	{
		$tabs = new TabContainer();
		$this->assertNotEmpty($tabs->getId());
	}

	// ── TabItem ──

	/** @test */
	public function tab_item_properties()
	{
		$tab = new TabItem('Details', 'fa fa-info', true);

		$this->assertSame('Details', $tab->getTitle());
		$this->assertSame('fa fa-info', $tab->getIcon());
		$this->assertTrue($tab->isActive());
	}

	/** @test */
	public function tab_item_title_no_spaces()
	{
		$tab = new TabItem('My Tab Title');
		$this->assertSame('MyTabTitle', $tab->getTitleNoSpaces());
	}

	/** @test */
	public function tab_item_id_from_title()
	{
		$tab = new TabItem('General');
		$this->assertSame('General-tab', $tab->getId());
	}

	/** @test */
	public function tab_item_draw_title()
	{
		$tab = new TabItem('Settings', 'fa fa-cog');
		$html = $tab->drawTitle();

		$this->assertStringContainsString('nav-link', $html);
		$this->assertStringContainsString('Settings', $html);
		$this->assertStringContainsString('fa fa-cog', $html);
	}

	/** @test */
	public function tab_item_draw_title_active()
	{
		$tab = new TabItem('Active Tab');
		$tab->setActive(true);
		$html = $tab->drawTitle();

		$this->assertStringContainsString('active', $html);
	}

	/** @test */
	public function tab_item_draw_content()
	{
		$tab = new TabItem('Content Tab');
		$tab->addComponent(new Alert('', 'Inside tab', 'success'));
		$html = $tab->draw();

		$this->assertStringContainsString('tab-pane', $html);
		$this->assertStringContainsString('Inside tab', $html);
	}

	// ── TabContent (deprecated) ──

	/** @test */
	public function tab_content_properties()
	{
		$tc = new TabContent('Title', 'Content', ['field1', 'field2']);

		$this->assertSame('Title', $tc->getTitle());
		$this->assertSame('Content', $tc->getContent());
		$this->assertSame(['field1', 'field2'], $tc->getFields());
		$this->assertSame('Title', $tc->getTitleNoSpaces());
	}

	/** @test */
	public function tab_content_fluent_setters()
	{
		$tc = new TabContent('T', 'C');
		$tc->setTitle('New Title')->setContent('New Content')->setFields(['f1']);
		$tc->addField('f2');

		$this->assertSame('New Title', $tc->getTitle());
		$this->assertSame('New Content', $tc->getContent());
		$this->assertSame(['f1', 'f2'], $tc->getFields());
	}

	// ── ContainerType ──

	/** @test */
	public function container_type_constants()
	{
		$this->assertSame('container', ContainerType::TYPE_DEFAULT);
		$this->assertSame('container-fluid', ContainerType::TYPE_FLUID);
	}

	// ── DrawMode constants ──

	/** @test */
	public function draw_mode_constants()
	{
		$this->assertSame('view', DrawMode::VIEW);
		$this->assertSame('update', DrawMode::UPDATE);
		$this->assertSame('insert', DrawMode::INSERT);
	}
}
