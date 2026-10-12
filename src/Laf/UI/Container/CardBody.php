<?php

namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;

/**
 * The body of a Card (<div class="card-body">).
 */
class CardBody extends Div implements ComponentInterface
{
    public function draw(): ?string
    {
        $this->addCssClass('card-body');
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
