<?php

declare(strict_types=1);

namespace Laf\UI\Form\Input;

use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;

/**
 * <input type="tel"> with the telephone keyboard on mobile (inputmode="tel"); no format is enforced.
 */
class Tel extends Text implements FormElementInterface
{
    public function drawUpdateMode(): string
    {
        $this->setType(InputType::Tel);
        $mode = $this->getInputMode();
        if ($mode === null || $mode === '') {
            $this->setInputMode('tel');
        }
        return parent::drawUpdateMode();
    }

    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }
}
