<?php

namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;

/**
 * Raw HTML as a component: draw() returns the content as given (not escaped),
 * so pages can mix hand-written markup with components. It holds no children.
 */
class HtmlContainer extends GenericContainer implements ComponentInterface
{
    protected string $content = '';

    public function __construct(string $content = '')
    {
        parent::__construct();
        $this->content = $content;
    }

    public function draw(): ?string
    {
        return $this->getContent();
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Appends raw HTML to the content
     */
    public function addContent(string $content): static
    {
        $this->content .= $content;
        return $this;
    }

    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }

    /**
     * An HtmlContainer holds no components
     * @throws \LogicException always
     */
    public function addComponent(ComponentInterface $component): ComponentInterface
    {
        throw new \LogicException('HtmlContainer cannot hold other components; use setContent()/addContent() for raw HTML');
    }
}
