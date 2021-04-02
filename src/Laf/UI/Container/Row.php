<?php


namespace Laf\UI\Container;


use Laf\UI\ComponentInterface;

class Row extends GenericContainer implements ComponentInterface
{
    public function draw(): ?string
    {
        $this->addCssClass(static::getComponentCssControlClass())
            ->addCssClass('row');

        $html = "\n\t<div 
			style='{$this->getCssStyleForHtml()}' 
			class='{$this->getCssClassesForHtml()} {$this->getContainerType()}'
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
