<?php

use Laf\UI\Component\Alert;
use Laf\UI\Component\Link;
use Laf\UI\Component\Dropdown;
use Laf\UI\Form\DrawMode;
use PHPUnit\Framework\TestCase;

class UIComponentTest extends TestCase
{
	// ── Alert ──

	/** @test */
	public function alert_draws_with_message()
	{
		$alert = new Alert('Title', 'Something happened', Alert::Type_Success);
		$html = $alert->draw();

		$this->assertStringContainsString('alert-success', $html);
		$this->assertStringContainsString('Something happened', $html);
		$this->assertStringContainsString('Title', $html);
		$this->assertStringContainsString('alert-heading', $html);
	}

	/** @test */
	public function alert_empty_message_returns_empty()
	{
		$alert = new Alert('Title', '');
		$this->assertSame('', $alert->draw());
	}

	/** @test */
	public function alert_without_title()
	{
		$alert = new Alert('', 'Message only', Alert::Type_Warning);
		$html = $alert->draw();

		$this->assertStringContainsString('alert-warning', $html);
		$this->assertStringContainsString('Message only', $html);
		$this->assertStringNotContainsString('alert-heading', $html);
	}

	/** @test */
	public function alert_with_container_wrap()
	{
		$alert = new Alert('', 'Wrapped', Alert::Type_Info);
		$html = $alert->draw(true);

		$this->assertStringContainsString('col-sm-12', $html);
		$this->assertStringContainsString('offset-md-4', $html);
	}

	/** @test */
	public function alert_type_constants()
	{
		$this->assertSame('primary', Alert::Type_Primary);
		$this->assertSame('secondary', Alert::Type_Secondary);
		$this->assertSame('success', Alert::Type_Success);
		$this->assertSame('danger', Alert::Type_Danger);
		$this->assertSame('warning', Alert::Type_Warning);
		$this->assertSame('info', Alert::Type_Info);
		$this->assertSame('light', Alert::Type_Light);
		$this->assertSame('dark', Alert::Type_Dark);
	}

	/** @test */
	public function alert_fluent_setters()
	{
		$alert = new Alert();
		$result = $alert->setTitle('T')->setMessage('M')->setType('danger');

		$this->assertInstanceOf(Alert::class, $result);
		$this->assertSame('T', $alert->getTitle());
		$this->assertSame('M', $alert->getMessage());
		$this->assertSame('danger', $alert->getType());
	}

	// ── Link ──

	/** @test */
	public function link_draws_anchor_tag()
	{
		$link = new Link('Click Me', '/path/to/page');
		$html = $link->draw();

		$this->assertStringContainsString('<a ', $html);
		$this->assertStringContainsString('Click Me', $html);
		$this->assertStringContainsString('/path/to/page', $html);
	}

	/** @test */
	public function link_with_icon()
	{
		$link = new Link('Edit', '/edit', 'fa fa-edit');
		$html = $link->draw();

		$this->assertStringContainsString("fa fa-edit", $html);
		$this->assertStringContainsString('<i ', $html);
		$this->assertStringContainsString('Edit', $html);
	}

	/** @test */
	public function link_properties()
	{
		$link = new Link('Text', '/url');
		$link->setTarget('_blank');
		$link->setRel('noopener');
		$link->setTitle('Tooltip');
		$link->setId('my-link');
		$link->setOnClick('alert(1)');

		$this->assertSame('/url', $link->getHref());
		$this->assertSame('_blank', $link->getTarget());
		$this->assertSame('noopener', $link->getRel());
		$this->assertSame('Tooltip', $link->getTitle());
		$this->assertSame('my-link', $link->getId());
		$this->assertSame('alert(1)', $link->getOnClick());
	}

	/** @test */
	public function link_confirmation_message()
	{
		$link = new Link('Delete', '/delete');
		$link->setConfirmationMessage('Are you sure?');

		$html = $link->draw();
		$this->assertStringContainsString("return confirm('Are you sure?')", $html);
	}

	/** @test */
	public function link_to_string()
	{
		$link = new Link('Test', '/test');
		$this->assertSame($link->draw(), (string)$link);
	}

	/** @test */
	public function link_css_classes()
	{
		$link = new Link('Test', '/test', '', [], ['btn', 'btn-primary']);
		$html = $link->draw();

		$this->assertStringContainsString('btn', $html);
		$this->assertStringContainsString('btn-primary', $html);
	}

	// ── Dropdown ──

	/** @test */
	public function dropdown_renders_with_links()
	{
		$dropdown = new Dropdown('Actions', 'btn-primary');
		$dropdown->addLink(new Link('Edit', '/edit'));
		$dropdown->addLink(new Link('Delete', '/delete'));
		$html = $dropdown->draw();

		$this->assertStringContainsString('dropdown-toggle', $html);
		$this->assertStringContainsString('dropdown-menu', $html);
		$this->assertStringContainsString('Actions', $html);
		$this->assertStringContainsString('Edit', $html);
		$this->assertStringContainsString('Delete', $html);
		$this->assertStringContainsString('dropdown-item', $html);
	}

	/** @test */
	public function dropdown_with_icon()
	{
		$dropdown = new Dropdown('Menu', '', 'fa fa-bars');
		$html = $dropdown->draw();

		$this->assertStringContainsString('fa fa-bars', $html);
	}

	/** @test */
	public function dropdown_right_align()
	{
		$dropdown = new Dropdown('Menu', '', '', true);
		$html = $dropdown->draw();

		$this->assertStringContainsString('dropdown-menu-right', $html);
	}

	/** @test */
	public function dropdown_left_align_default()
	{
		$dropdown = new Dropdown('Menu');
		$html = $dropdown->draw();

		$this->assertStringNotContainsString('dropdown-menu-right', $html);
	}

	/** @test */
	public function dropdown_properties()
	{
		$dropdown = new Dropdown();
		$dropdown->setText('Options');
		$dropdown->setIcon('fa fa-cog');

		$this->assertSame('Options', $dropdown->getText());
		$this->assertSame('fa fa-cog', $dropdown->getIcon());
	}

	/** @test */
	public function dropdown_set_and_get_links()
	{
		$dropdown = new Dropdown();
		$link1 = new Link('A', '/a');
		$link2 = new Link('B', '/b');

		$dropdown->addLink($link1);
		$dropdown->addLink($link2);
		$this->assertCount(2, $dropdown->getLinks());

		$dropdown->setLinks([$link1]);
		$this->assertCount(1, $dropdown->getLinks());
	}

	// ── ComponentTrait (tested via Alert) ──

	/** @test */
	public function component_css_class_management()
	{
		$alert = new Alert('', 'test', 'info');

		$alert->addCssClass('my-class');
		$this->assertTrue($alert->hasCssClass('my-class'));

		$alert->addCssClass('my-class'); // duplicate
		$this->assertCount(1, array_filter($alert->getCssClasses(), fn($c) => $c === 'my-class'));

		$alert->removeCssClass('my-class');
		$this->assertFalse($alert->hasCssClass('my-class'));
	}

	/** @test */
	public function component_css_style_management()
	{
		$alert = new Alert('', 'test');

		$alert->addCssStyleItem('color', 'red');
		$this->assertTrue($alert->hasCssStyleItem('color'));

		$alert->removeCssStyle('color');
		$this->assertFalse($alert->hasCssStyleItem('color'));
	}

	/** @test */
	public function component_attribute_management()
	{
		$alert = new Alert('', 'test');

		$alert->addAttribute('data-id', '5');
		$this->assertTrue($alert->hasAttribute('data-id'));
		$this->assertSame('5', $alert->getAttribute('data-id'));

		$alert->removeAttribute('data-id');
		$this->assertFalse($alert->hasAttribute('data-id'));
		$this->assertNull($alert->getAttribute('data-id'));
	}

	/** @test */
	public function component_wrapper_css_class()
	{
		$alert = new Alert('', 'test');

		$alert->addWrapperCssClass('wrapper-class');
		$this->assertTrue($alert->hasWrapperCssClass('wrapper-class'));

		$alert->removeWrapperCssClass('wrapper-class');
		$this->assertFalse($alert->hasWrapperCssClass('wrapper-class'));
	}

	/** @test */
	public function component_wrapper_attributes()
	{
		$alert = new Alert('', 'test');

		$alert->addWrapperAttribute('data-wrap', 'yes');
		$this->assertTrue($alert->hasWrapperAttribute('data-wrap'));
		$this->assertSame('yes', $alert->getWrapperAttribute('data-wrap'));

		$alert->removeWrapperAttribute('data-wrap');
		$this->assertFalse($alert->hasWrapperAttribute('data-wrap'));
	}

	/** @test */
	public function component_draw_mode()
	{
		$alert = new Alert('', 'test');

		$alert->setDrawMode(DrawMode::VIEW);
		$this->assertSame(DrawMode::VIEW, $alert->getDrawMode());

		$alert->setDrawMode(DrawMode::UPDATE);
		$this->assertSame(DrawMode::UPDATE, $alert->getDrawMode());
	}

	/** @test */
	public function component_add_child_components()
	{
		$parent = new Alert('', 'parent');
		$child = new Alert('', 'child');

		$parent->addComponent($child);
		$this->assertCount(1, $parent->getComponents());
		$this->assertSame($child, $parent->getComponents()[0]);
	}

	/** @test */
	public function component_wrapper_css_style()
	{
		$alert = new Alert('', 'test');

		$alert->addWrapperCssStyleItem('margin', '10px');
		$this->assertTrue($alert->hasWrapperCssStyleItem('margin'));

		$alert->removeWrapperCssStyle('margin');
		$this->assertFalse($alert->hasWrapperCssStyleItem('margin'));
	}
}
