<?php

declare(strict_types=1);

use Laf\UI\Container\Div;
use Laf\UI\Form\Control\Button;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\Input\Text;
use PHPUnit\Framework\TestCase;

/**
 * The rules of ComponentTrait and FormElementTrait (2026-10-12 review): empty means absent,
 * unique css classes, strict comparisons, empty style items left out.
 */
class UITraitsTest extends TestCase
{
    /** @test */
    public function draw_mode_is_never_null_and_empty_means_not_set(): void
    {
        $div = new Div();
        $this->assertSame('', $div->getDrawMode());
        $div->setDrawMode(DrawMode::UPDATE);
        $this->assertSame('update', $div->getDrawMode());
        $div->setDrawMode(null);
        $this->assertSame('', $div->getDrawMode());
    }

    /** @test */
    public function css_classes_are_unique_and_compared_strictly(): void
    {
        $div = new Div(['a']);
        $div->addCssClass('a')->addCssClasses('b', 'c', 'b');
        $this->assertSame(['a', 'b', 'c'], $div->getCssClasses());
        $this->assertTrue($div->hasCssClass('b'));
        $this->assertFalse($div->hasCssClass('0'));

        $div->removeCssClass('b');
        $this->assertSame(['a', 'c'], $div->getCssClasses());

        $div->addWrapperCssClass('w')->addWrapperCssClass('w');
        $this->assertSame(['w'], $div->getWrapperCssClasses());
        $div->removeWrapperCssClass('w');
        $this->assertSame([], $div->getWrapperCssClasses());
    }

    /** @test */
    public function an_empty_attribute_counts_as_absent_and_can_still_be_removed(): void
    {
        $text = new Text();
        $text->addAttribute('disabled', '');
        $this->assertFalse($text->hasAttribute('disabled'));
        $this->assertNull($text->getAttribute('disabled'));
        $this->assertArrayHasKey('disabled', $text->getAttributes());

        $text->removeAttribute('disabled');
        $this->assertArrayNotHasKey('disabled', $text->getAttributes());

        $text->addAttributes(['data-x' => '1', 'title' => null, 'size' => 3]);
        $this->assertSame('1', $text->getAttribute('data-x'));
        $this->assertNull($text->getAttribute('title'));
        $this->assertSame(3, $text->getSize());

        $text->addWrapperAttribute('label', '');
        $this->assertFalse($text->hasWrapperAttribute('label'));
        $text->removeWrapperAttribute('label');
        $this->assertArrayNotHasKey('label', $text->getWrapperAttributes());
    }

    /** @test */
    public function empty_style_items_are_left_out_of_the_rendered_style(): void
    {
        $div = new Div([], ['color' => 'red', 'margin' => null, 'padding' => '']);
        $this->assertSame('red', $div->getCssStyleItem('color'));
        $this->assertNull($div->getCssStyleItem('margin'));
        $this->assertFalse($div->hasCssStyleItem('padding'));

        $html = (string)$div->draw();
        $this->assertStringContainsString("color:red", $html);
        $this->assertStringNotContainsString('margin', $html);
        $this->assertStringNotContainsString('padding', $html);

        $div->removeCssStyle('color');
        $this->assertStringNotContainsString('color', (string)$div->draw());
    }

    /** @test */
    public function components_and_boolean_attributes(): void
    {
        $div = new Div();
        $this->assertFalse($div->hasComponents());
        $div->addComponent(new Div());
        $this->assertTrue($div->hasComponents());

        $text = new Text();
        $this->assertFalse($text->isDisabled());
        $text->setDisabled(true);
        $this->assertTrue($text->isDisabled());
        $text->setDisabled(false);
        $this->assertFalse($text->isDisabled());
        $this->assertFalse($text->isAutofocus());
        $text->setAutofocus(true);
        $this->assertTrue($text->isAutofocus());

        $this->assertSame('', $text->getValueForHtml());
        $text->setValue("a<b\nc");
        $this->assertSame("a&lt;b<br />\nc", $text->getValueForHtml());

        $button = (new Button())->setValue('x');
        $this->assertFalse($button->hasErrors());
        $button->addError('e');
        $this->assertSame(['e'], $button->getErrors());
        $button->clearErrors();
        $this->assertFalse($button->hasErrors());
    }
}
