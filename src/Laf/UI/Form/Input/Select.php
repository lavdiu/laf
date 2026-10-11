<?php

namespace Laf\UI\Form\Input;

use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\Database\Db;
use Laf\Util\Settings;
use Laf\Util\Util;

class Select extends Text implements FormElementInterface, ComponentInterface
{
    /**
     * @var array|null static option list (value => label); when set, the
     * foreign-key lookup is skipped. Used for ENUM columns and hand-built selects.
     */
    private ?array $options = null;

    /**
     * Provide the options directly instead of reading them from the FK table
     * @param array $options value => label
     * @return $this
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function hasStaticOptions(): bool
    {
        return $this->options !== null;
    }

    public function drawViewMode()
    {
        $this->addCssClass('form-control-plaintext');
        $this->addCssClass('border border-secondary');
        $this->setValue($this->getSelectedLabel());
        return parent::drawViewMode();
    }

    /**
     * Returns the label from the FK table, or from the static option list
     * @return string
     */
    protected function getSelectedLabel()
    {
        if ($this->options !== null) {
            $value = $this->getField()->getValue();
            return $this->options[(string)$value] ?? (string)$value;
        }
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
        unset($attributes['maxlength']);
        unset($attributes['minlength']);

        $params = '';
        foreach ($attributes as $key => $value)
            $params .= "\n\t\t\t\t" . $key . '="' . \Laf\Util\Util::attr($value) . '" ';

        $options = "<option value=''>&nbsp;</option>";

        foreach ($this->getOptions() as $ok => $ov) {
            $okHtml = Util::e($ok);
            $ovHtml = Util::e($ov);
            if ($ok == $this->getValue() && mb_strlen($ok??'') == mb_strlen($this->getValue()??'')) {
                $options .= "\n\t\t\t\t<option value='{$okHtml}' selected='selected'>{$ovHtml}</option>";
            } else {
                $options .= "\n\t\t\t\t<option value='{$okHtml}'>{$ovHtml}</option>";
            }
        }

        $html = "
        <div id='{$this->getId()}_container' style='{$this->getWrapperCssStyleForHtml()}' class='mb-3 {$this->getFormRowDisplayMode()} {$this->getWrapperCssClassesForHtml()}" . ($this->isHidden() || $this->hasCssClass('d-none') ? " d-none" : "") . "'>
            <label id='{$this->getId()}_label' for='{$this->getId()}' class='" . ($this->getFormRowDisplayMode() == 'row' ? "col-sm-2" : "") . " col-form-label'>{$this->getLabel()}" . ($this->isRequired() ? '*' : '') . " :</label>
            " . ($this->getFormRowDisplayMode() == 'row' ? "<div class='col-sm-10'>" : "") . "
            <select {$params}
                class='{$this->getCssClassesForHtml()}' 
                style='{$this->getCssStyleForHtml()}' 
                " . ((mb_strlen($this->getHint()??'') > 0) ? "aria-describedby='{$this->getId()}_hint'" : "") . "
                >
                {$options}
                </select>
                " . ((mb_strlen($this->getHint()??'') > 0) ? "\n\t\t\t\t<small id='{$this->getId()}_hint' class='form-text text-muted'>{$this->getHint()}</small>" : "") . "
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
        if ($this->options !== null) {
            return $this->options;
        }

        $fk = $this->getField()->getTable()->getForeignKey($this->getField()->getName());
        $fkTable = $fk->getReferencingTable();
        $meta = $fk->getReferencedTableMeta();
        if ($meta === null) {
            return [];
        }
        $field = $meta->getDisplayField()->getName();
        $pkFieldName = $fk->resolveReferencedFieldName();

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

        $isMysql = false;
        try {
            $isMysql = strtolower((string)Settings::get('database.engine')) === 'mysql';
        } catch (\Throwable $ex) {
        }
        $q = $isMysql ? '`' : '';
        $sql = "SELECT {$q}{$pkFieldName}{$q}, {$q}{$field}{$q} FROM {$q}{$fkTable}{$q} WHERE 1=1 {$where} ORDER BY {$q}{$field}{$q} ASC";

        $db = Db::getInstance();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
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
