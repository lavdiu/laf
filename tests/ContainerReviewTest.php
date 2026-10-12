<?php

declare(strict_types=1);

use Laf\UI\Container\Card;
use Laf\UI\Container\Column;
use Laf\UI\Container\ContainerType;
use Laf\UI\Container\Div;
use Laf\UI\Container\Fieldset;
use Laf\UI\Container\GenericContainer;
use Laf\UI\Container\HtmlContainer;
use Laf\UI\Container\Modal;
use Laf\UI\Container\Row;
use Laf\UI\Container\TabContainer;
use Laf\UI\Container\TabItem;
use Laf\UI\Form\Control\SubmitButton;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\Input\Text;
use PHPUnit\Framework\TestCase;

/**
 * Container review (2026-10-12): HtmlContainer refusal, Modal ids / escaping / options,
 * containers that only hand down their draw mode, attributes and tags on containers,
 * TabContainer ids and the active tab.
 */
class ContainerReviewTest extends TestCase
{
    /** @test */
    public function html_container_refuses_children_with_a_real_exception_and_appends_content(): void
    {
        $html = new HtmlContainer('<p>a</p>');
        $html->addContent('<p>b</p>');
        $this->assertSame('<p>a</p><p>b</p>', $html->draw());
        $this->assertSame('', (new HtmlContainer())->draw());

        $this->expectException(\LogicException::class);
        $html->addComponent(new Div());
    }

    /** @test */
    public function modal_title_id_matches_aria_labelledby_and_values_are_escaped(): void
    {
        $modal = (new Modal("x'y"))->setTitle("<b>T</b>")->setCancelLabel("<Close>")->setSize(Modal::SIZE_LARGE);
        $html = (string)$modal->draw();
        $this->assertStringContainsString("id='x&apos;y' tabindex='-1' role='dialog' aria-labelledby='x&apos;yLabel'", $html);
        $this->assertStringContainsString("<h5 class='modal-title' id='x&apos;yLabel'><b>T</b></h5>", $html);
        $this->assertStringNotContainsString('Label}', $html);
        $this->assertStringContainsString('>&lt;Close&gt;</button>', $html);
        $this->assertStringContainsString("class='modal-dialog modal-lg modal-dialog-scrollable'", $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertSame('', Modal::SIZE_NORMAL);
    }

    /** @test */
    public function modal_form_options_hidden_inputs_and_open_button_label(): void
    {
        $modal = (new Modal('m'))
            ->setIncludeForm(true)->setFormAction('/save?a=1&b=2')->setFormMethod('post')->setFormId('mf')->setFormHasFiles(true)
            ->addHiddenInput('_csrf_token_x', 'tok')
            ->setCentered(true)->setStaticBackdrop(true)->setScrollable(false)->setShowCancelButton(false)
            ->addFooterButton((new SubmitButton())->setValue('Go')->setWrapInDiv(false));
        $modal->addComponent((new Text())->setId('t')->setName('t'));
        $html = (string)$modal->draw();

        $this->assertStringContainsString("<form role='form' action='/save?a=1&amp;b=2' method='POST' id='mf' enctype='multipart/form-data'>", $html);
        $this->assertStringContainsString("<input type='hidden' name='_csrf_token_x' value='tok' />", $html);
        $this->assertStringContainsString("class='modal-dialog modal-dialog-centered'", $html);
        $this->assertStringContainsString("data-bs-backdrop='static'", $html);
        $this->assertStringNotContainsString('btn-outline-secondary', $html);
        $this->assertStringContainsString('>Go</button>', $html);
        $this->assertStringContainsString("id='t_container'", $html);
        $this->assertStringContainsString('</form>', $html);

        $button = $modal->getModalOpenButton('Open it', 'btn  btn-primary');
        $buttonHtml = $button->draw();
        $this->assertStringContainsString('>Open it</a>', $buttonHtml);
        $this->assertStringContainsString('data-bs-target="#m"', $buttonHtml);
        $this->assertSame(['btn', 'btn-primary'], array_values(array_diff($button->getCssClasses(), ['Laf-UI-Component-Link'])));
    }

    /** @test */
    public function row_and_column_hand_their_draw_mode_only_to_children_without_one(): void
    {
        $row = new Row();
        $column = new Column(['col-lg-6']);
        $own = (new Text())->setId('a')->setName('a')->setDrawMode(DrawMode::VIEW);
        $inherits = (new Text())->setId('b')->setName('b');
        $column->addComponent($own)->addComponent($inherits);
        $row->addComponent($column);
        $row->setDrawMode(DrawMode::UPDATE);
        $html = (string)$row->draw();

        $this->assertSame(DrawMode::VIEW, $own->getDrawMode());
        $this->assertSame(DrawMode::UPDATE, $inherits->getDrawMode());
        $this->assertStringContainsString("class='row Laf-UI-Container-Row'", $html);
        $this->assertStringContainsString("class='col-lg-6 col-sm Laf-UI-Container-Column'", $html);
        $this->assertStringContainsString("<div id='a' class='Laf-UI-Form-Input-Text form-control-plaintext", $html, 'the element with its own mode stays read-only');
        $this->assertStringContainsString('name="b"', $html);
    }

    /** @test */
    public function containers_render_their_attributes_tag_and_no_empty_class(): void
    {
        $div = (new Div(['x'], ['color' => 'red']))->setAttribute('id', 'box')->setAttribute('data-role', 'a"b')->setAttribute('class', 'ignored');
        $html = (string)$div->draw();
        $this->assertStringContainsString("<div style='color:red' class='x Laf-UI-Container-Div' id=\"box\" data-role=\"a&quot;b\">", $html);
        $this->assertStringNotContainsString('ignored', $html);
        $this->assertStringNotContainsString("Div '", $html, 'no empty class for an unset container type');

        $section = (new GenericContainer())->setTag('section')->setContainerType(ContainerType::TYPE_FLUID);
        $section->addComponent(new HtmlContainer('inner'));
        $html = (string)$section->draw();
        $this->assertStringContainsString("<section style='' class='Laf-UI-Container-GenericContainer container-fluid'>", $html);
        $this->assertStringContainsString('inner', $html);
        $this->assertStringContainsString('</section>', $html);

        $this->expectException(\InvalidArgumentException::class);
        $section->setTag('div onclick');
    }

    /** @test */
    public function card_and_fieldset_keep_their_classes_and_draw_children(): void
    {
        $card = new Card(['shadow']);
        $card->addComponent(new HtmlContainer('body'));
        $html = (string)$card->draw();
        $this->assertStringContainsString("class='shadow card Laf-UI-Container-Card'", $html);
        $this->assertStringContainsString('body', $html);

        $fieldset = (new Fieldset('<Legend>'))->setAttribute('id', 'fs');
        $fieldset->addComponent((new Text())->setId('f')->setName('f'));
        $fieldset->setDrawMode(DrawMode::UPDATE);
        $html = (string)$fieldset->draw();
        $this->assertStringContainsString("<fieldset style='' class='Laf-UI-Container-Fieldset' id=\"fs\">", $html);
        $this->assertStringContainsString('<legend>&lt;Legend&gt;</legend>', $html);
        $this->assertStringContainsString('name="f"', $html);
    }

    /** @test */
    public function tab_container_ids_and_active_tab(): void
    {
        $generated = new TabContainer(null);
        $this->assertMatchesRegularExpression('/^tabs-[0-9a-f-]{36}$/', $generated->getId());
        $this->assertStringContainsString('#tabs-', TabContainer::navigationScript($generated->getId(), 'next'));

        $tabs = (new TabContainer("t'1"))->setAttribute('data-x', '1');
        $first = new TabItem('First');
        $second = new TabItem('Second & more', '', true);
        $tabs->addComponent($first)->addComponent($second);
        $html = (string)$tabs->draw();
        $this->assertFalse($first->isActive(), 'an explicitly active tab wins over the first');
        $this->assertTrue($second->isActive());
        $this->assertStringContainsString("id='t&apos;1_tab_links'", $html);
        $this->assertStringContainsString('data-x="1"', $html);
        $this->assertStringContainsString('>Second &amp; more</a>', $html);
        $this->assertStringContainsString("href='#Secondmore-content'", $html);

        $tabs->setActiveTab('First');
        $tabs->draw();
        $this->assertTrue($first->isActive());
        $this->assertFalse($second->isActive());
        $tabs->setActiveTab(1);
        $this->assertTrue($second->isActive());
        $this->assertSame([$first, $second], $tabs->getTabs());
    }
}
