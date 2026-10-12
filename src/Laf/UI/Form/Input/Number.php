<?php

declare(strict_types=1);

namespace Laf\UI\Form\Input;

use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;

/**
 * <input type="number"> for decimal and float columns, opt-in (the types create a
 * plain Text so locale-formatted numbers such as "1.234,56" are accepted).
 * The step comes from the field's type (TypeDecimal scale) or defaults to "any";
 * the browser only accepts "." as the decimal point.
 */
class Number extends Text implements FormElementInterface
{
    public function drawUpdateMode(): string
    {
        $this->setType(InputType::Number);
        $step = $this->getAttribute('step');
        if ($step === null || $step === '') {
            $this->setStep('any');
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
