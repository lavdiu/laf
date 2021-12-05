<?php

namespace Laf\UI\Form\Input;

use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\Database\Db;
use Laf\Util\Settings;

class Select extends Text implements FormElementInterface, ComponentInterface
{
    public function drawViewMode()
    {
        $this->addCssClass('form-control-plaintext');
        $this->addCssClass('border border-secondary');
        $this->setValue($this->getSelectedLabel());
        return parent::drawViewMode();
    }

    /**
     * Returns the label from the FB table
     * @return string
     */
    protected function getSelectedLabel()
    {
        return $this->getField()->getReferencedValue();
    }

    public function drawUpdateMode()
    {
        $this->addCssClass(static::getComponentCssControlClass());

        $attributes = [];
        foreach ($this->getAttributes() as $key => $value) {
            if ($value != '') {
                $attributes[$key] = $value;
            }
        }

        if ($this->getHeight()) {
            $this->addStyle('height', $this->getHeight() . 'px');
            unset($attributes['height']);
        }
        if ($this->getWidth()) {
            $this->addStyle('width', $this->getWidth() . 'px');
            unset($attributes['width']);
        }

        $this->addCssClass('form-control');

        unset($attributes['placeholder']);
        unset($attributes['type']);
        unset($attributes['value']);
        unset($attributes['maxlength']);
        unset($attributes['minlength']);

        $params = '';
        foreach ($attributes as $key => $value)
            $params .= "\n\t\t\t\t" . $key . '="' . $value . '" ';

        $options = "<option value=''>&nbsp;</option>";

        foreach ($this->getOptions() as $ok => $ov) {
            if ($ok == $this->getValue() && mb_strlen($ok) == mb_strlen($this->getValue())) {
                $options .= "\n\t\t\t\t<option value='{$ok}' selected='selected'>{$ov}</option>";
            } else {
                $options .= "\n\t\t\t\t<option value='{$ok}'>{$ov}</option>";
            }
        }

        $html = "
        <div id='{$this->getId()}_container' style='{$this->getWrapperCssStyleForHtml()}' class='mb-3 {$this->getFormRowDisplayMode()} {$this->getWrapperCssClassesForHtml()}" . ($this->isHidden() || $this->hasCssClass('d-none') ? " d-none" : "") . "'>
            <label id='{$this->getId()}_label' for='{$this->getId()}' class='" . ($this->getFormRowDisplayMode() == 'row' ? "col-sm-2" : "") . " col-form-label'>{$this->getLabel()}" . ($this->isRequired() ? '*' : '') . " :</label>
            " . ($this->getFormRowDisplayMode() == 'row' ? "<div class='col-sm-10'>" : "") . "
            <select {$params}
                class='{$this->getCssClassesForHtml()}' 
                style='{$this->getCssStyleForHtml()}' 
                " . ((mb_strlen($this->getHint()) > 0) ? "aria-describedby='{$this->getId()}_hint'" : "") . "
                >
                {$options}
                </select>
                " . ((mb_strlen($this->getHint()) > 0) ? "\n\t\t\t\t<small id='{$this->getId()}_hint' class='form-text text-muted'>{$this->getHint()}</small>" : "") . "
            " . ($this->getFormRowDisplayMode() == 'row' ? "</div>" : "") . "
        </div>";

        return $html;
    }

    /**
     * Returns all options from the FK table, to build select element options
     * @return array
     * @throws \Exception
     */
    protected function getOptions()
    {
        $fkTable = $this->getField()->getTable()->getForeignKey($this->getField()->getName())->getReferencingTable();
        $settings = Settings::getInstance();
        $fkClass = '\\' . $settings->getProperty('project.package_name') . '\\' . Db::convertTableNameToClassName($fkTable);
        $record = new $fkClass($this->getField()->getValue());
        $field = $record->getTable()->getDisplayField()->getName();
        $pkFieldName = $record->getTable()->getPrimaryKey()->getFirstField()->getName();

        #@TODO optimize and add values as prepared statement parameters
        $where = '';
        if ($this->getField()->hasDbSelectionCriteria()) {
            foreach ($this->getField()->getDbSelectionCriteria() as $key => $value) {
                $where .= " AND `{$key}`='{$value}'";
            }
        }

        $sql = "SELECT `{$pkFieldName}`, `{$field}` FROM `{$fkTable}` WHERE 1=1 {$where} ORDER BY `{$field}` ASC";
        $db = Db::getInstance();
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $options = [];
        while ($res = $stmt->fetchObject()) {
            $options[$res->$pkFieldName] = $res->$field;
        }
        return $options;
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
