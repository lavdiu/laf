<?php

declare(strict_types=1);

namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;
use Laf\Util\Util;

/**
 * <fieldset> with an optional <legend>; children are drawn like in a Div.
 */
class Fieldset extends Div implements ComponentInterface
{
    private string $legend;

    /**
     * @param string $legend caption, escaped at output
     * @param array $classes css classes
     * @param array $style property => value
     */
    public function __construct(string $legend = '', array $classes = [], array $style = [])
    {
        parent::__construct($classes, $style);
        $this->legend = $legend;
    }

    public function getLegend(): string
    {
        return $this->legend;
    }

    public function setLegend(string $legend): Fieldset
    {
        $this->legend = $legend;
        return $this;
    }

    public function draw(): ?string
    {
        $this->addCssClass(static::getComponentCssControlClass());
        $this->addCssClass($this->getContainerType());

        $html = "\n\t<fieldset style='" . Util::e($this->getCssStyleForHtml()) . "' class='" . Util::e($this->getCssClassesForHtml()) . "'>";
        if ($this->legend !== '') {
            $html .= "\n\t\t<legend>" . Util::e($this->legend) . "</legend>";
        }
        foreach ($this->getComponents() as $component) {
            if ((string)$component->getDrawMode() === '') {
                $component->setDrawMode($this->getDrawMode());
            }
            $component->setFormRowDisplayMode((string)$this->getFormRowDisplayMode());
            $html .= "\n\t\t" . $component->draw();
        }
        $html .= "\n\t</fieldset>";
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
