<?php

namespace Laf\UI\Form\Input;

use Laf\Database\BaseObject;
use Laf\Database\Db;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;


class Radio extends Text implements FormElementInterface, ComponentInterface
{

	public function drawViewMode()
	{
		$this->addCssClass('form-control-plaintext');
		$this->setValue($this->getSelectedLabel());
		return $this->drawUpdateMode();
	}

	/**
	 * Returns the label from the FB table
	 * @return string
	 */
	protected function getSelectedLabel()
	{
		$fkTable = $this->getField()->getTable()->getForeignKey($this->getField()->getName())->getReferencingTable();
		#@Todo optimize this, remove dependency
		$fkClass = '\\Intrepicure\\' . Db::convertTableNameToClassName($fkTable);
		$record = new $fkClass($this->getField()->getValue());
		$field = 'get' . Db::convertTableNameToClassName($record->getTable()->getDisplayField()->getName()) . 'Val';
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
			$params .= "\n\t\t\t\t" . $key . '="' . $value . '" ';

		$html = "
        <div id='{$this->getId()}_container'  style='{$this->getWrapperCssStyleForHtml()}'  class='form-group row {$this->getWrapperCssClassesForHtml()}" . ($this->isHidden() || $this->hasCssClass('d-none') ? " d-none" : "") . "'>
            <div>";
		foreach ($this->getOptions() as $ok => $ov) {
			$html .= "
            <div class='custom-control custom-radio'>
              <input {$params} 
              style='{$this->getCssStyleForHtml()}' 
			  class='{$this->getCssClassesForHtml()}'
              type='radio' id='{$this->getId()}_{$ok}' value='{$ok}' name='{$this->getName()}' class='custom-control-input'>
              <label class='custom-control-label' for='{$this->getId()}_{$ok}'>{$ov}</label>
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
	 */
	protected function getOptions()
	{
		$fkTable = $this->getField()->getTable()->getForeignKey($this->getField()->getName())->getReferencingTable();
		#@Todo remove dependency
		$fkClass = '\\Intrepicure\\' . Db::convertTableNameToClassName($fkTable);
		/**
		 * @var $record BaseObject
		 */
		$record = new $fkClass($this->getField()->getValue());
		$field = $record->getTable()->getDisplayField()->getName();

		#@TODO optimize and add values as prepared statement parameters
		$where = '';
		if ($this->getField()->hasDbSelectionCriteria()) {
			foreach ($this->getField()->getDbSelectionCriteria() as $key => $value) {
				$where .= " AND {$key}='{$value}'";
			}
		}

		$sql = "SELECT id, {$field} FROM $fkTable WHERE 1=1 {$where} ORDER BY {$field} ASC";
		$db = Db::getInstance();
		$stmt = $db->prepare($sql);
		$stmt->execute();
		$options = [];
		while ($res = $stmt->fetchObject()) {
			$options[$res->id] = $res->$field;
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
