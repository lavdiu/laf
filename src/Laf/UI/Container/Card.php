<?php

namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;

/**
 * A Bootstrap card (<div class="card">); add CardHeader, CardBody and CardFooter.
 */
class Card extends Div implements ComponentInterface
{
    public function draw(): ?string
    {
        $this->addCssClass('card');
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
