<?php

namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;

/**
 * The header of a Card (<div class="card-header">).
 */
class CardHeader extends Div implements ComponentInterface
{
    public function draw(): ?string
    {
        $this->addCssClass('card-header');
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
