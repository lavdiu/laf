<?php

declare(strict_types=1);

use Laf\UI\Component\Dropdown;
use Laf\UI\Component\Link;
use Laf\UI\Container\ContainerType;
use Laf\UI\Container\HtmlContainer;
use Laf\UI\Page\AdminPage;
use Laf\UI\Page\GenericPage;
use Laf\UI\Page\HeaderComponent;
use Laf\UI\Page\Html;
use Laf\UI\Page\Page;
use Laf\Util\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Laf\UI\Page: the document (Html), the card (GenericPage / AdminPage), the banner variant (Page)
 * and the head tags (HeaderComponent).
 */
class PageTest extends TestCase
{
	protected function tearDown(): void
	{
		Settings::set('locale.language', '');
	}

	// ── GenericPage ──

	/** @test */
	public function generic_page_draws_title_icon_links_components_and_footer_in_a_card()
	{
		$page = new GenericPage();
		$page->setTitle("<a href='?x'>Lista</a>");
		$page->setTitleIcon('far fa-list-alt');
		$page->setContainerType(ContainerType::TYPE_FLUID);
		$page->addComponent(new HtmlContainer('<p>body</p>'));
		$page->addLink(new Link('Add', '?action=new', 'fa fa-plus'));
		$page->addFooterComponent(new HtmlContainer('<small>foot</small>'));
		$html = $page->draw();

		$this->assertStringContainsString("<div class='p-2 container-fluid Laf-UI-Page-GenericPage'>", $html);
		$this->assertStringContainsString("<div class='card border-dark shadow'", $html);
		$this->assertStringContainsString("<div class='card-header bg-light border-dark'>", $html);
		$this->assertStringContainsString("<i class='far fa-list-alt'></i> <a href='?x'>Lista</a>", $html);
		$this->assertStringContainsString('?action=new', $html);
		$this->assertStringContainsString("<div class='card-body'><p>body</p></div>", $html);
		$this->assertStringContainsString("<div class='card-footer'><small>foot</small></div>", $html);
	}

	/** @test */
	public function generic_page_has_no_footer_element_without_footer_components()
	{
		$page = (new GenericPage())->setTitle('T');
		$this->assertStringNotContainsString('card-footer', $page->draw());
	}

	/** @test */
	public function generic_page_drawn_twice_gives_the_same_html()
	{
		$page = (new GenericPage())->setTitle('T');
		$page->addComponent(new HtmlContainer('<p>once</p>'));
		$page->addFooterComponent(new HtmlContainer('<b>f</b>'));
		$first = $page->draw();

		$this->assertSame($first, $page->draw());
		$this->assertSame(1, substr_count($first, '<p>once</p>'));
	}

	/** @test */
	public function enable_and_disable_are_fluent_and_a_disabled_page_draws_nothing()
	{
		$page = (new GenericPage())->setTitle('T')->addComponent(new HtmlContainer('x'));

		$this->assertSame($page, $page->disable());
		$this->assertFalse($page->isEnabled());
		$this->assertSame('', $page->draw());
		$this->assertSame($page, $page->enable());
		$this->assertStringContainsString('x', $page->draw());
	}

	/** @test */
	public function null_title_and_icon_are_accepted()
	{
		$page = new GenericPage();
		$page->setTitle(null);
		$page->setTitleIcon(null);

		$this->assertSame('', $page->getTitle());
		$this->assertSame('', $page->getTitleIcon());
		$this->assertStringNotContainsString('<i ', $page->draw());
	}

	/** @test */
	public function title_icon_is_attribute_escaped_and_title_is_raw_html()
	{
		$page = new GenericPage();
		$page->setTitle('<b>raw</b>');
		$page->setTitleIcon("fa'><script>");
		$html = $page->draw();

		$this->assertStringContainsString('<b>raw</b>', $html);
		$this->assertStringContainsString("<i class='fa'&gt;&lt;script&gt;'></i>", $html);
	}

	/** @test */
	public function header_and_footer_html_wrap_the_card()
	{
		$page = (new GenericPage())->setTitle('T')->setHeader('<nav>crumbs</nav>')->setFooter('<p>after</p>');
		$html = $page->draw();

		$this->assertStringStartsWith('<nav>crumbs</nav>', $html);
		$this->assertStringEndsWith('<p>after</p>', $html);
		$this->assertLessThan(strpos($html, '<div class=\'p-2'), strpos($html, '<nav>'));
	}

	/** @test */
	public function component_attributes_are_drawn_on_the_wrapper()
	{
		$page = (new GenericPage())->setTitle('T');
		$page->setAttribute('id', 'main-card')->setAttribute('data-x', 'a"b');
		$html = $page->draw();

		$this->assertStringContainsString("Laf-UI-Page-GenericPage' id=\"main-card\" data-x=\"a&quot;b\">", $html);
	}

	/** @test */
	public function card_header_can_be_hidden()
	{
		$page = (new GenericPage())->setTitle('Hidden title')->setShowCardHeader(false);
		$page->addLink(new Link('L', '?l'));
		$html = $page->draw();

		$this->assertStringNotContainsString('card-header', $html);
		$this->assertStringNotContainsString('Hidden title', $html);
		$this->assertStringContainsString('card-body', $html);
	}

	/** @test */
	public function card_and_header_classes_can_be_replaced()
	{
		$page = (new GenericPage())->setTitle('T')
			->setCardCssClasses(['card', 'border-0'])
			->addCardCssClass('shadow-sm')
			->addCardCssClass('shadow-sm')
			->setCardHeaderCssClasses(['card-header'])
			->addCardHeaderCssClass('bg-white');
		$html = $page->draw();

		$this->assertStringContainsString("<div class='card border-0 shadow-sm'", $html);
		$this->assertStringContainsString("<div class='card-header bg-white'>", $html);
		$this->assertStringNotContainsString('border-dark', $html);
	}

	/** @test */
	public function subtitle_is_drawn_after_the_title_when_set()
	{
		$page = (new GenericPage())->setTitle('Orders');
		$this->assertStringNotContainsString('<small', $page->draw());

		$page->setSubtitle('open only');
		$this->assertStringContainsString("Orders</span> <small class='text-muted fw-normal fs-6'>open only</small>", $page->draw());
	}

	/** @test */
	public function links_accept_dropdowns_and_keep_their_order()
	{
		$page = (new GenericPage())->setTitle('T');
		$dd = new Dropdown('Actions');
		$dd->addLink(new Link('Edit', '?edit'));
		$page->addLink(new Link('First', '?first'))->addLink($dd);
		$html = $page->draw();

		$this->assertTrue($page->hasLinks());
		$this->assertCount(2, $page->getLinks());
		$this->assertLessThan(strpos($html, 'dropdown-toggle'), strpos($html, '?first'));
		$this->assertStringContainsString('?edit', $html);
	}

	/** @test */
	public function admin_page_renders_like_generic_page_with_its_own_control_class()
	{
		$page = (new AdminPage())->setTitle('T')->addComponent(new HtmlContainer('<p>b</p>'));
		$html = $page->draw();

		$this->assertStringContainsString('Laf-UI-Page-AdminPage', $html);
		$this->assertStringContainsString("<div class='card-body'><p>b</p></div>", $html);
		$this->assertSame('Laf-UI-Page-AdminPage', $page->getComponentCssControlClass());
	}

	// ── Page (banner variant) ──

	/** @test */
	public function page_draws_a_banner_with_the_image_and_the_title_around_the_card()
	{
		$page = (new Page())->setTitle('Contact')->setBannerImage("img/ban'ner.jpg")->setHeader('<i>h</i>')->setFooter('<i>f</i>');
		$page->addComponent(new HtmlContainer('<p>b</p>'));
		$html = $page->draw();

		$this->assertStringContainsString('<section class="innerbanner" style="background-image:url(\'img/ban\'ner.jpg\');">', $html);
		$this->assertStringContainsString("<h1 class='innerbanner-heading'>Contact</h1>", $html);
		$this->assertStringContainsString("<section class='contact-us'>", $html);
		$this->assertStringContainsString("<div class='card-body'><p>b</p></div>", $html);
		$this->assertLessThan(strpos($html, '<i>h</i>'), strpos($html, 'contact-us'));
		$this->assertLessThan(strpos($html, '</section>', strpos($html, 'contact-us')), strpos($html, '<i>f</i>'));
		// the caller's header/footer are restored after drawing
		$this->assertSame('<i>h</i>', $page->getHeader());
		$this->assertSame('<i>f</i>', $page->getFooter());
		$this->assertSame($html, $page->draw());
	}

	/** @test */
	public function page_without_a_banner_image_draws_the_banner_without_a_style()
	{
		$html = (new Page())->setTitle('T')->draw();

		$this->assertStringContainsString('<section class="innerbanner">', $html);
		$this->assertStringNotContainsString('background-image', $html);
		$this->assertSame('', (new Page())->getBannerImage());
	}

	// ── HeaderComponent ──

	/** @test */
	public function header_component_draws_self_closing_content_and_bare_attributes()
	{
		$this->assertSame('<link rel="stylesheet" href="a.css" />', (new HeaderComponent('link', ['rel' => 'stylesheet', 'href' => 'a.css'], true))->draw());
		$this->assertSame('<script type="text/javascript">alert(1);</script>', (new HeaderComponent('script', ['type' => 'text/javascript']))->setContent('alert(1);')->draw());
		$this->assertSame('<script src="a.js" defer></script>', (new HeaderComponent('script', ['src' => 'a.js', 'defer' => null]))->draw());
		$this->assertSame('<script src="a.js" async></script>', (new HeaderComponent('script', ['src' => 'a.js', 'async' => true, 'defer' => false]))->draw());
	}

	/** @test */
	public function void_elements_never_get_a_closing_tag_and_values_are_escaped()
	{
		$this->assertSame('<meta name="robots" content="noindex" />', (new HeaderComponent('meta', ['name' => 'robots', 'content' => 'noindex']))->draw());
		$this->assertSame('<link rel="icon" href="i.png?a=1&amp;b=&quot;2&quot;" />', (new HeaderComponent('link', ['rel' => 'icon', 'href' => 'i.png?a=1&b="2"']))->draw());
	}

	/** @test */
	public function header_component_factories()
	{
		$this->assertSame('<script src="a.js" type="module"></script>', HeaderComponent::script('a.js', ['type' => 'module'])->draw());
		$this->assertSame('<script>x();</script>', HeaderComponent::inlineScript('x();')->draw());
		$this->assertSame('<link rel="stylesheet" href="a.css" media="print" />', HeaderComponent::stylesheet('a.css', ['media' => 'print'])->draw());
		$this->assertSame('<style>b{}</style>', HeaderComponent::inlineStyle('b{}')->draw());
		$this->assertSame('<meta name="robots" content="noindex,nofollow" />', HeaderComponent::meta('robots', 'noindex,nofollow')->draw());
		$this->assertSame('<link rel="canonical" href="https://x/" />', HeaderComponent::link('canonical', 'https://x/')->draw());
	}

	/** @test */
	public function header_component_rejects_an_invalid_tag_name()
	{
		$this->expectException(InvalidArgumentException::class);
		new HeaderComponent('script><b', []);
	}

	/** @test */
	public function header_component_attribute_setters()
	{
		$hc = (new HeaderComponent('base'))->addAttribute('href', '/app/')->addAttribute('target', '_blank')->removeAttribute('target');
		$this->assertSame(['href' => '/app/'], $hc->getAttributes());
		$this->assertSame('<base href="/app/" />', $hc->draw());
		$this->assertTrue($hc->isVoidElement());
	}

	// ── Html ──

	/** @test */
	public function html_draws_the_document_with_escaped_title_head_components_menu_and_components()
	{
		$doc = new Html();
		$doc->setPageTitle('A & B <C>')
			->addHeaderComponent(new HeaderComponent('script', ['src' => 'b.js']))
			->addHeaderComponent(new HeaderComponent('link', ['rel' => 'stylesheet', 'href' => 'm.css'], true))
			->setHtmlHeader('<!-- raw head -->')
			->setMenu('<nav>menu</nav>')
			->addComponent((new AdminPage())->setTitle('T')->addComponent(new HtmlContainer('<p>b</p>')))
			->addComponent(new HtmlContainer('<script>js</script>'));
		$html = $doc->draw();

		$this->assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n", $html);
		$this->assertStringContainsString("<meta charset='utf-8'>", $html);
		$this->assertStringContainsString("<meta name='viewport'", $html);
		$this->assertStringContainsString('<title>A &amp; B &lt;C&gt;</title>', $html);
		$this->assertStringContainsString("<script src=\"b.js\"></script>\n<link rel=\"stylesheet\" href=\"m.css\" />\n<!-- raw head -->\n</head>", $html);
		$this->assertStringContainsString("<body style=\"background-color:#EEEEEE\"><nav>menu</nav>", $html);
		$this->assertStringContainsString("<div class='card-body'><p>b</p></div>", $html);
		$this->assertStringEndsWith("<script>js</script>\n</body>\n</html>", $html);
	}

	/** @test */
	public function html_language_comes_from_the_setter_or_the_locale_setting()
	{
		$doc = new Html();
		$this->assertSame('en', $doc->getLanguage());

		Settings::set('locale.language', 'sq');
		$this->assertSame('sq', $doc->getLanguage());
		$this->assertStringContainsString('<html lang="sq">', $doc->draw());

		$doc->setLanguage('de-CH');
		$this->assertStringContainsString('<html lang="de-CH">', $doc->draw());
		$doc->setLanguage(null);
		$this->assertSame('sq', $doc->getLanguage());
	}

	/** @test */
	public function html_body_attributes_can_be_replaced_or_extended()
	{
		$doc = new Html();
		$doc->setBodyAttributes(['class' => 'dark'])->addBodyAttribute('data-page', 'x')->addBodyCssClass('fixed')->addBodyCssClass('fixed');
		$html = $doc->draw();

		$this->assertStringContainsString('<body class="dark fixed" data-page="x">', $html);
		$this->assertStringNotContainsString('#EEEEEE', $html);

		$this->assertStringContainsString('<body>', (new Html())->setBodyAttributes([])->draw());
	}

	/** @test */
	public function html_draw_without_closing_tags_stops_after_the_components()
	{
		$doc = new Html();
		$doc->addComponent(new HtmlContainer('<p>c</p>'))
			->addFooterComponent(new HtmlContainer('<footer>f</footer>'))
			->setFooterHtml('<!-- site footer -->')
			->addScript('end.js', [], true);

		$open = $doc->draw(true);
		$this->assertStringEndsWith('<p>c</p>', $open);
		$this->assertStringNotContainsString('</body>', $open);
		$this->assertStringNotContainsString('end.js', $open);

		$closed = $doc->draw();
		$this->assertStringEndsWith("<p>c</p><footer>f</footer><!-- site footer -->\n<script src=\"end.js\"></script>\n</body>\n</html>", $closed);
	}

	/** @test */
	public function html_convenience_adders_build_head_tags()
	{
		$doc = new Html();
		$doc->addScript('a.js', ['defer' => null])
			->addInlineScript('x();')
			->addStylesheet('a.css')
			->addInlineStyle('b{}')
			->addMeta('robots', 'noindex')
			->addLinkTag('canonical', 'https://x/')
			->setFavicon('icon.png')
			->addInlineScript('y();', true);
		$html = $doc->draw();
		$head = substr($html, 0, strpos($html, '</head>'));

		$this->assertStringContainsString('<script src="a.js" defer></script>', $head);
		$this->assertStringContainsString('<script>x();</script>', $head);
		$this->assertStringContainsString('<link rel="stylesheet" href="a.css" />', $head);
		$this->assertStringContainsString('<style>b{}</style>', $head);
		$this->assertStringContainsString('<meta name="robots" content="noindex" />', $head);
		$this->assertStringContainsString('<link rel="canonical" href="https://x/" />', $head);
		$this->assertStringContainsString('<link rel="icon" href="icon.png" />', $head);
		$this->assertStringContainsString('<link rel="shortcut icon" href="icon.png" />', $head);
		$this->assertStringContainsString('<link rel="apple-touch-icon" href="icon.png" />', $head);
		$this->assertStringNotContainsString('y();', $head);
		$this->assertStringContainsString("<script>y();</script>\n</body>", $html);
		$this->assertCount(1, $doc->getBodyEndComponents());
		$this->assertCount(9, $doc->getHeaderComponents());
	}

	/** @test */
	public function html_setters_are_fluent_and_replace_lists()
	{
		$doc = new Html();
		$hc = HeaderComponent::meta('a', 'b');
		$this->assertSame($doc, $doc->setHeaderComponents([$hc]));
		$this->assertSame([$hc], $doc->getHeaderComponents());
		$this->assertSame($doc, $doc->setComponents([new HtmlContainer('z')]));
		$this->assertCount(1, $doc->getComponents());
		$this->assertSame($doc, $doc->setPageTitle('t')->setMenu('m')->setHtmlHeader('h'));
		$this->assertSame('t', $doc->getPageTitle());
		$this->assertSame('m', $doc->getMenu());
		$this->assertSame('h', $doc->getHtmlHeader());
	}
}
