<?php


namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;
use Laf\UI\Form\DrawMode;
use Laf\UI\Traits\ComponentTrait;

class Div implements ComponentInterface
{
    use ComponentTrait;

    /**
     * Div constructor.
     * @param array $classes use array values as classes
     * @param array $style use key-> value pair for styles example ['border':'solid 1px red']
     */
    function __construct(array $classes = [], array $style = [])
    {
        $this->setCssClasses($classes);
        $this->setCssStyles($style);
    }


    /**
     * @return string
     */
    public function draw(): ?string
    {
        $this->addCssClass(static::getComponentCssControlClass());
        $this->addCssClass($this->getContainerType());
        $html = "\n\t<div
			style='{$this->getCssStyleForHtml()}' 
			class='{$this->getCssClassesForHtml()}'
		>\n";
        foreach ($this->getComponents() as $component) {
            if ($component->getDrawMode() == '')
                $component->setDrawMode($this->getDrawMode());

            $component->setFormRowDisplayMode($this->getFormRowDisplayMode());
            $html .= "\n\t\t" . $component->draw();
        }
        $html .= "\n\t</div>";
        return $html;
    }

    /**
     * Returns the CSS class unique to the UI component
     * @return string
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }

}
