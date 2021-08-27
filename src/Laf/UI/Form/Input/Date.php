<?php

namespace Laf\UI\Form\Input;


use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;
use Laf\UI\Grid\PhpGrid\Settings;

class Date extends Text implements FormElementInterface, ComponentInterface
{
    public function drawUpdateMode()
    {

        $this->setType(InputType::Date);
        $this->addCssClass('date-picker');
        if ($this->getValue() == '') {
            return parent::drawUpdateMode();
        }
        $date = \Datetime::createFromFormat('Y-m-d', $this->getValue());
        if ($date === false) {
            $date = new \DateTime();
        }
        $format = 'F d, Y';
        try {
            $format = \Laf\Util\Settings::get('locale.date.format');
        } catch (\Exception $ex) {

        }
        $this->setValue($date->format($format));
        return parent::drawUpdateMode();
    }

    public function drawViewMode()
    {
        $this->removeCssClass('date-picker');
        if ($this->getValue() == '') {
            return parent::drawViewMode();
        }
        $date = \Datetime::createFromFormat('Y-m-d', $this->getValue());
        if ($date === false) {
            $date = new \DateTime();
        }
        $format = 'F d, Y';
        try {
            $format = \Laf\Util\Settings::get('locale.date.format');
        } catch (\Exception $ex) {

        }
        $this->setValue($date->format($format));
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
