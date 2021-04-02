<?php

namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;
use mysql_xdevapi\Exception;

class HtmlContainer extends GenericContainer implements ComponentInterface
{

    /**
     * @var string
     */
    protected $content = "";

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    public function draw(): ?string
    {
        return $this->getContent();
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param string $content
     * @return HtmlContainer
     */
    public function setContent(string $content): HtmlContainer
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Returns the CSS class unique to the UI component
     * @return string
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }

    /**
     * @param ComponentInterface $component
     * @return ComponentInterface
     */
    public function addComponent(ComponentInterface $component): ComponentInterface
    {
        throw new Exception("HtmlContainer cannot accept other components. Use addContent() to add raw HTML content");
    }
}
