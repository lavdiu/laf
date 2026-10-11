<?php

namespace Laf\UI\Form\Input;

use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;
use Laf\Util\Locale;

class DateTime extends Text implements FormElementInterface, ComponentInterface
{
    /**
     * Re-render the canonical 'Y-m-d H:i:s[.u][P]' value in the locale display format.
     * A value that does not parse is left untouched so the user sees what is stored.
     */
    private function applyDisplayFormat(): void
    {
        $value = $this->getValue();
        if ($value === null || trim($value) === '') {
            return;
        }
        $date = Locale::parseDate($value, [
            Locale::ISO_DATETIME, 'Y-m-d H:i:s.u', 'Y-m-d H:i:sP', 'Y-m-d H:i:s.uP',
            'Y-m-d H:i', Locale::ISO_DATE, Locale::dateTimeFormat(),
        ]);
        if ($date !== null) {
            $this->setValue($date->format(Locale::dateTimeFormat()));
        }
    }

    public function drawUpdateMode()
    {
        $this->setType(InputType::Text);
        $this->addCssClass('date-time-picker');
        $this->applyDisplayFormat();
        return parent::drawUpdateMode();
    }

    public function drawViewMode()
    {
        $this->applyDisplayFormat();
        $this->removeCssClass('date-time-picker');
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
