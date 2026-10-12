<?php

namespace Laf\UI\Container;

use Laf\UI\ComponentInterface;

/**
 * A <div> holding components: the GenericContainer under the name the pages use.
 */
class Div extends GenericContainer implements ComponentInterface
{
    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }
}
