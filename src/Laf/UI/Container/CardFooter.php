<?php

namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;

/**
 * The footer of a Card (<div class="card-footer">).
 */
class CardFooter extends Div implements ComponentInterface
{
    public function draw(): ?string
    {
        $this->addCssClass('card-footer');
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
