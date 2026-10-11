<?php

namespace Laf\UI\Form\Input;

use Laf\Database\BaseObject;
use Laf\Database\Db;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\Util\Settings;


class Radio extends Text implements FormElementInterface, ComponentInterface
{

    public function drawViewMode()
    {
        $this->addCssClass('form-control-plaintext');
        $this->addCssClass('border border-secondary border-1');
        $this->setValue($this->getSelectedLabel());
        return $this->drawUpdateMode();
    }

    /**
     * Returns the label from the FB table
     * @return string
     * @throws \Exception
     */
    protected function getSelectedLabel()
    {
        $fk = $this->getField()->getTable()->getForeignKey($this->getField()->getName());
        $record = $fk->getReferencedObject($this->getField()->getValue());
        if ($record === null) {
            return $this->getValue();
        }
        $field = 'get' . \Laf\Util\Util::tableFieldNameToMethodName($record->getTable()->getDisplayField()->getName()) . 'Val';
        if (method_exists($record, $field))
            return $record->$field();
        else
            return $this->getValue();
    }

    public function drawUpdateMode()
    {
        $this->addCssClass(static::getComponentCssControlClass());

        $attributes = $this->getAttributes();

        if ($this->getHeight()) {
            $this->addCssStyleItem('height', $this->getHeight() . 'px');
            unset($attributes['height']);
        }
        if ($this->getWidth()) {
            $this->addCssStyleItem('width', $this->getWidth() . 'px');
            unset($attributes['width']);
        }

        $this->addCssClass('form-control');

        unset($attributes['placeholder']);
        unset($attributes['type']);
        unset($attributes['value']);

        $params = '';
        foreach ($attributes as $key => $value)
            $params .= "\n\t\t\t\t" . $key . '="' . \Laf\Util\Util::attr($value) . '" ';

        $html = "
        <div id='{$this->getId()}_container'  style='{$this->getWrapperCssStyleForHtml()}'  class='form-group row  mb-2 {$this->getWrapperCssClassesForHtml()}" . ($this->isHidden() || $this->hasCssClass('d-none') ? " d-none" : "") . "'>
            <div>";
        foreach ($this->getOptions() as $ok => $ov) {
            $okHtml = \Laf\Util\Util::e($ok);
            $ovHtml = \Laf\Util\Util::e($ov);
            $html .= "
            <div class='custom-control custom-radio'>
              <input {$params} 
              style='{$this->getCssStyleForHtml()}' 
			  class='{$this->getCssClassesForHtml()}'
              type='radio' id='{$this->getId()}_{$okHtml}' value='{$okHtml}' name='{$this->getName()}' class='custom-control-input'>
              <label class='custom-control-label' for='{$this->getId()}_{$okHtml}'>{$ovHtml}</label>
            </div>
            ";
        }
        $html .= "
                </div>" . ((mb_strlen($this->getHint()) > 0) ? "\n\t\t\t\t<small id='{$this->getId()}_hint' class='form-text text-muted'>{$this->getHint()}</small>" : "") . "
            </div>
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
        $fk = $this->getField()->getTable()->getForeignKey($this->getField()->getName());
        $fkTable = $fk->getReferencingTable();
        $meta = $fk->getReferencedTableMeta();
        if ($meta === null) {
            return [];
        }
        $field = $meta->getDisplayField()->getName();
        $keyField = $fk->resolveReferencedFieldName();

        $where = '';
        $params = [];
        if ($this->getField()->hasDbSelectionCriteria()) {
            $i = 0;
            foreach ($this->getField()->getDbSelectionCriteria() as $key => $value) {
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                    continue;
                }
                $placeholder = ':crit' . $i++;
                $where .= " AND {$key} = {$placeholder}";
                $params[$placeholder] = $value;
            }
        }

        $q = '\Laf\Database\Identifier';
        $sql = "SELECT " . $q::quote($keyField) . ", " . $q::quote($field) . " FROM " . $q::quote($fkTable) . " WHERE 1=1 {$where} ORDER BY " . $q::quote($field) . " ASC";
        $db = Db::getInstance();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $options = [];
        while ($res = $stmt->fetchObject()) {
            $options[$res->$keyField] = $res->$field;
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
