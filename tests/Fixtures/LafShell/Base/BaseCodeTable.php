<?php

namespace LafShell\Base;

use Laf\Database;
use Laf\Database\Table;
use Laf\Database\Field\Field;
use Laf\Database\PrimaryKey;
use Laf\Database\ForeignKey;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\ComponentInterface;
use LafShell\CodeTable;
use Laf\Exception\InvalidForeignKeyValue;

/**
 * Class BaseCodeTable
 * @package LafShell\Base
 * Base Class for Table code_table
 * Basic definition of the fields and relationship with the Database Table code_table
 * This class will be auto-generated every time there is a schema change
 * Please do not add code here. Instead add your code at the main class one directory above
 */
class BaseCodeTable extends Database\BaseObject
{
	/**
	 * BaseCodeTable constructor: binds the table and loads the row when a key is given
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
		$this->setTable(new Table("code_table"));
		/**
		 * Generate field data
		 */
		$pk = new PrimaryKey();
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
			->setName("name")
			->setLabel("Name")
			->setPlaceholder("Name")
			->setRequired(false)
			->setMaxLength(50)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
		$this->getTable()->addField($field);
		$field = null;

		$this->getTable()->setPrimaryKey($pk);

		/**
		 * Generating Foreign keys
		 */
		/**
		 * Many-to-many relations through linker tables
		 */
		$this->getTable()->addManyToMany("link_table", ['owner' => 'code', 'other' => 'left_id', 'table' => 'dummy_table', 'label' => 'Dummy Tables']);

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
	 * Set Name value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setNameVal($value = null)
	{
		$this->setFieldValue("name", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Name value
	 * @return mixed
	 */
	public function getNameVal()
	{
		return $this->getFieldValue("name");
	}

	/**
	 * Get Name field reference
	 * @return Field
	 */
	public function getNameFld()
	{
		return $this->getField("name");
	}

	/**
	 * Get Name form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getNameFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("name")->getFormElement($formElementOverride);
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
	 * @return CodeTable[]
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
