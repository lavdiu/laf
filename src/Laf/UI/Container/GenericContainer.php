<?php

namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\Util\Util;

/**
 * A block element (a <div> unless setTag() says otherwise) that draws its child
 * components in order. The base of every container.
 *
 * Children without a draw mode of their own inherit the container's; the form
 * row display mode is pushed down. The container type (ContainerType::*) is
 * drawn as a css class; the attribute bag (id, data-*, aria-*) is rendered on
 * the element, css classes and style escaped.
 */
class GenericContainer implements ComponentInterface
{
    use ComponentTrait;

    /** HTML tag of the element */
    protected string $tag = 'div';

    /**
     * @param string[] $classes css classes
     * @param array<string, string|null> $style property => value, e.g. ['border' => 'solid 1px red']
     */
    public function __construct(array $classes = [], array $style = [])
    {
        $this->setCssClasses($classes);
        $this->setCssStyles($style);
    }

    /**
     * The HTML tag to draw: div (default), section, span, nav, article, aside, header, footer, main, ul, li, ...
     * @throws \InvalidArgumentException on a name that is not a tag name
     */
    public function setTag(string $tag): static
    {
        $tag = strtolower(trim($tag));
        if (!preg_match('/^[a-z][a-z0-9-]*$/', $tag)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not an HTML tag name', $tag));
        }
        $this->tag = $tag;
        return $this;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function draw(): ?string
    {
        $this->addCssClass(static::getComponentCssControlClass());
        if ($this->getContainerType() !== '') {
            $this->addCssClass($this->getContainerType());
        }
        return $this->openTag() . $this->drawComponents() . $this->closeTag();
    }

    /**
     * The opening tag with style, classes and the attribute bag
     */
    protected function openTag(): string
    {
        return "\n\t<" . $this->tag . " style='" . Util::e($this->getCssStyleForHtml()) . "' class='" . Util::e($this->getCssClassesForHtml()) . "'"
            . $this->drawAttributes() . ">\n";
    }

    protected function closeTag(): string
    {
        return "\n\t</" . $this->tag . ">";
    }

    /**
     * The attribute bag as key="value" pairs; class and style are drawn separately, empty values skipped
     */
    protected function drawAttributes(): string
    {
        $html = '';
        foreach ($this->getAttributes() as $key => $value) {
            $key = (string)$key;
            if ($value === null || $value === '' || $key === 'class' || $key === 'style' || !preg_match('/^[A-Za-z_:][A-Za-z0-9_:.\-]*$/', $key)) {
                continue;
            }
            $html .= ' ' . $key . '="' . Util::attr($value) . '"';
        }
        return $html;
    }

    /**
     * The children, each with the container's draw mode when it has none and the
     * container's form row display mode
     */
    protected function drawComponents(): string
    {
        $html = '';
        foreach ($this->getComponents() as $component) {
            if ((string)$component->getDrawMode() === '') {
                $component->setDrawMode($this->getDrawMode());
            }
            $component->setFormRowDisplayMode((string)$this->getFormRowDisplayMode());
            $html .= "\n\t\t" . $component->draw();
        }
        return $html;
    }

    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }
}
