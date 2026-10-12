<?php

namespace LafShell\Base;

use Laf\Database;
use Laf\Database\Table;
use Laf\Database\Field\Field;
use Laf\Database\PrimaryKey;
use Laf\Database\ForeignKey;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\ComponentInterface;
use LafShell\LinkTable;
use Laf\Exception\InvalidForeignKeyValue;

/**
 * Class BaseLinkTable
 * @package LafShell\Base
 * Base Class for Table link_table
 * Basic definition of the fields and relationship with the Database Table link_table
 * This class will be auto-generated every time there is a schema change
 * Please do not add code here. Instead add your code at the main class one directory above
 */
class BaseLinkTable extends Database\BaseObject
{
	/**
	 * BaseLinkTable constructor: binds the table and loads the row when a key is given
	 * @param int|string|array|null $id primary key value (name => value for a composite key)
	 */
	public function __construct($id = null)
	{
		parent::__construct($id);
		$this->buildClass();
		if ($id !== null && $id !== '') {
			self::select($id);
		}
	}

	/**
	 * Select the record by primary key
	 * @param int|string|array $id
	 * @return bool
	 * @throws \Exception
	 */
	public function select($id) : bool
	{
		$this->setRecordId($id);
		return parent::select($id);
	}

	/**
	 * Build the class properties and link it to the db table
	 */
	private function buildClass()
	{
		$this->setTable(new Table("link_table"));
		/**
		 * Generate field data
		 */
		$pk = new PrimaryKey();
		$field = (new Field())
			->setName("left_id")
			->setLabel("Left")
			->setPlaceholder("Left")
			->setRequired(true)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger('int'));
		$pk->addField($field);
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("code")
			->setLabel("Code")
			->setPlaceholder("Code")
			->setRequired(true)
			->setMaxLength(40)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
		$pk->addField($field);
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("note")
			->setLabel("Note")
			->setPlaceholder("Note")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeText());
		$this->getTable()->addField($field);
		$field = null;

		$this->getTable()->setPrimaryKey($pk);

		/**
		 * Generating Foreign keys
		 */
		$this->getTable()->addForeignKey(
			(new ForeignKey())
				->setField($this->getTable()->getField("left_id"))
				->setKeyName('link_table_left_fk')
				->setReferencingTable("dummy_table")
				->setReferencingField("id")
				->setOnDelete('CASCADE')
		);

		$this->getTable()->addForeignKey(
			(new ForeignKey())
				->setField($this->getTable()->getField("code"))
				->setKeyName('link_table_code_fk')
				->setReferencingTable("code_table")
				->setReferencingField("code")
		);

	}

	/**
	 * Set Left value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setLeftIdVal($value = null)
	{
		$this->setFieldValue("left_id", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Left value
	 * @return mixed
	 */
	public function getLeftIdVal()
	{
		return $this->getFieldValue("left_id");
	}

	/**
	 * Get Left field reference
	 * @return Field
	 */
	public function getLeftIdFld()
	{
		return $this->getField("left_id");
	}

	/**
	 * Get Left form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getLeftIdFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("left_id")->getFormElement($formElementOverride);
	}

	/**
	 * Get DummyTable Object (empty when the key is not set)
	 * @return \LafShell\DummyTable
	 */
	public function getLeftIdObj()
	{
		$value = $this->getLeftIdVal();
		if ($value !== null && $value !== '') {
			return new \LafShell\DummyTable($value);
		}
		return new \LafShell\DummyTable();
	}

	/**
	 * Set Code value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setCodeVal($value = null)
	{
		$this->setFieldValue("code", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Code value
	 * @return mixed
	 */
	public function getCodeVal()
	{
		return $this->getFieldValue("code");
	}

	/**
	 * Get Code field reference
	 * @return Field
	 */
	public function getCodeFld()
	{
		return $this->getField("code");
	}

	/**
	 * Get Code form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getCodeFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("code")->getFormElement($formElementOverride);
	}

	/**
	 * Get CodeTable Object (empty when the key is not set)
	 * @return \LafShell\CodeTable
	 */
	public function getCodeObj()
	{
		$value = $this->getCodeVal();
		if ($value !== null && $value !== '') {
			return new \LafShell\CodeTable($value);
		}
		return new \LafShell\CodeTable();
	}

	/**
	 * Set Note value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setNoteVal($value = null)
	{
		$this->setFieldValue("note", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Note value
	 * @return mixed
	 */
	public function getNoteVal()
	{
		return $this->getFieldValue("note");
	}

	/**
	 * Get Note field reference
	 * @return Field
	 */
	public function getNoteFld()
	{
		return $this->getField("note");
	}

	/**
	 * Get Note form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getNoteFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("note")->getFormElement($formElementOverride);
	}

	/**
	 * Get all rows as associative array
	 * @return string[]
	 * @throws \Exception
	 */
	public function listAllArray(): array
	{
		$query = static::query();
		foreach ($this->getTable()->getPrimaryKey()->getFieldNames() as $keyColumn) {
			$query->orderBy($keyColumn);
		}
		return $query->arrays();
	}

	/**
	 * Gets all rows and instantiates the object for all
	 * Then returns an array of all objects
	 * One SELECT, hydrated without a query per row; still every row of the table
	 * @return LinkTable[]
	 * @throws \Exception
	 */
	public function listAllObjects(): array
	{
		$query = static::query();
		foreach ($this->getTable()->getPrimaryKey()->getFieldNames() as $keyColumn) {
			$query->orderBy($keyColumn);
		}
		return $query->get();
	}

	/**
	* Return all fields as form elements
	* @return FormElementInterface[]
	*/
	public function getAllFieldsAsFormElements() : array{
		$tmp = [];
		foreach($this->getTable()->getFields() as $field){
			$tmp[] = $field->getFormElement();
		}
		return $tmp;
	}

}
