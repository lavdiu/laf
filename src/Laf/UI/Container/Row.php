<?php

namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;

/**
 * A Bootstrap row (<div class="row">); put Column containers inside.
 * Like every container it only hands its draw mode to children that have none.
 */
class Row extends GenericContainer implements ComponentInterface
{
    public function draw(): ?string
    {
        $this->addCssClass('row');
        return parent::draw();
    }

    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }
}
