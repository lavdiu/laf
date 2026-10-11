<?php

namespace Laf\UI\Form\Input;

use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;
use Laf\Util\Locale;

class Date extends Text implements FormElementInterface, ComponentInterface
{
    /**
     * Re-render the canonical 'Y-m-d' value in the locale display format.
     * A value that does not parse is left untouched so the user sees what is stored.
     */
    private function applyDisplayFormat(): void
    {
        $value = $this->getValue();
        if ($value === null || trim($value) === '') {
            return;
        }
        $date = Locale::parseDate($value, [Locale::ISO_DATE, 'Y-m-d H:i:s', Locale::dateFormat()]);
        if ($date !== null) {
            $this->setValue($date->format(Locale::dateFormat()));
        }
    }

    public function drawUpdateMode()
    {
        $this->setType(InputType::Text);
        $this->addCssClass('date-picker');
        $this->applyDisplayFormat();
        return parent::drawUpdateMode();
    }

    public function drawViewMode()
    {
        $this->removeCssClass('date-picker');
        $this->applyDisplayFormat();
        $this->addCssClass('form-control-plaintext');
        $this->addCssClass('border border-secondary border-1');
        return parent::drawViewMode();
    }

    /**
     * Returns the CSS class unique to the UI component
     * @return string
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }
}
