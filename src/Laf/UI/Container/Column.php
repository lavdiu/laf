<?php

namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;

/**
 * A Bootstrap column (<div class="col-sm">) inside a Row; pass width classes
 * such as 'col-lg-6' in the constructor.
 */
class Column extends GenericContainer implements ComponentInterface
{
    public function draw(): ?string
    {
        $this->addCssClass('col-sm');
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
