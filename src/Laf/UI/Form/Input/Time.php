<?php

namespace Laf\UI\Form\Input;

use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;
use Laf\Util\Locale;

class Time extends Text implements FormElementInterface, ComponentInterface
{
    /**
     * Re-render the canonical 'H:i:s[.u]' value in the locale display format.
     * A value that does not parse is left untouched so the user sees what is stored.
     */
    private function applyDisplayFormat(): void
    {
        $value = $this->getValue();
        if ($value === null || trim($value) === '') {
            return;
        }
        $time = Locale::parseDate($value, [Locale::ISO_TIME, 'H:i:s.u', 'H:i', Locale::timeFormat()]);
        if ($time !== null) {
            $this->setValue($time->format(Locale::timeFormat()));
        }
    }

    public function drawViewMode()
    {
        $this->applyDisplayFormat();
        return parent::drawViewMode();
    }

    public function drawUpdateMode()
    {
        $this->setType(InputType::Text);
        $this->addCssClass('time-picker');
        $this->applyDisplayFormat();
        return parent::drawUpdateMode();
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
