<?php

namespace Laf\UI\Container;

/**
 * Bootstrap container widths, used with ComponentInterface::setContainerType():
 * the value is drawn as a css class of the container.
 */
class ContainerType
{
    /**
     * Displays it only in the center of the width of the screen
     */
    const TYPE_DEFAULT = 'container';

    /**
     * Stretches the full width of the parent container
     */
    const TYPE_FLUID = 'container-fluid';
}
