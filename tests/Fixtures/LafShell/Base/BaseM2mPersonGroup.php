<?php

namespace LafShell\Base;

use Laf\Database;
use Laf\Database\Table;
use Laf\Database\Field\Field;
use Laf\Database\PrimaryKey;
use Laf\Database\ForeignKey;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\ComponentInterface;
use LafShell\M2mPersonGroup;
use Laf\Exception\InvalidForeignKeyValue;

/**
 * Class BaseM2mPersonGroup
 * @package LafShell\Base
 * Base Class for Table m2m_person_group
 * Basic definition of the fields and relationship with the Database Table m2m_person_group
 * This class will be auto-generated every time there is a schema change
 * Please do not add code here. Instead add your code at the main class one directory above
 */
class BaseM2mPersonGroup extends Database\BaseObject
{
	/**
	 * BaseM2mPersonGroup constructor: binds the table and loads the row when a key is given
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
		$this->setTable(new Table("m2m_person_group"));
		/**
		 * Generate field data
		 */
		$pk = new PrimaryKey();
		$field = (new Field())
			->setName("id")
			->setLabel("Id")
			->setPlaceholder("Id")
			->setRequired(true)
			->setAutoIncrement(true)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger('int'));
		$pk->addField($field);
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("person_id")
			->setLabel("Person")
			->setPlaceholder("Person")
			->setRequired(true)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger('int'));
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("group_id")
			->setLabel("Group")
			->setPlaceholder("Group")
			->setRequired(true)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger('int'));
		$this->getTable()->addField($field);
		$field = null;

		$this->getTable()->setPrimaryKey($pk);

		/**
		 * Generating Foreign keys
		 */
		$this->getTable()->addForeignKey(
			(new ForeignKey())
				->setField($this->getTable()->getField("person_id"))
				->setKeyName('m2m_person_group_person_fk')
				->setReferencingTable("m2m_person")
				->setReferencingField("id")
				->setOnDelete('CASCADE')
		);

		$this->getTable()->addForeignKey(
			(new ForeignKey())
				->setField($this->getTable()->getField("group_id"))
				->setKeyName('m2m_person_group_group_fk')
				->setReferencingTable("m2m_group")
				->setReferencingField("id")
		);

	}

	/**
	 * Set Id value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setIdVal($value = null)
	{
		$this->setFieldValue("id", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Id value
	 * @return mixed
	 */
	public function getIdVal()
	{
		return $this->getFieldValue("id");
	}

	/**
	 * Get Id field reference
	 * @return Field
	 */
	public function getIdFld()
	{
		return $this->getField("id");
	}

	/**
	 * Get Id form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getIdFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("id")->getFormElement($formElementOverride);
	}

	/**
	 * Set Person value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setPersonIdVal($value = null)
	{
		$this->setFieldValue("person_id", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Person value
	 * @return mixed
	 */
	public function getPersonIdVal()
	{
		return $this->getFieldValue("person_id");
	}

	/**
	 * Get Person field reference
	 * @return Field
	 */
	public function getPersonIdFld()
	{
		return $this->getField("person_id");
	}

	/**
	 * Get Person form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getPersonIdFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("person_id")->getFormElement($formElementOverride);
	}

	/**
	 * Get M2mPerson Object (empty when the key is not set; loaded once per key value, $fresh reloads)
	 * @return \LafShell\M2mPerson
	 */
	public function getPersonIdObj(bool $fresh = false)
	{
		/** @var \LafShell\M2mPerson $object */
		$object = $this->relatedObject(\LafShell\M2mPerson::class, "person_id", $fresh);
		return $object;
	}

	/**
	 * Set Group value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setGroupIdVal($value = null)
	{
		$this->setFieldValue("group_id", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Group value
	 * @return mixed
	 */
	public function getGroupIdVal()
	{
		return $this->getFieldValue("group_id");
	}

	/**
	 * Get Group field reference
	 * @return Field
	 */
	public function getGroupIdFld()
	{
		return $this->getField("group_id");
	}

	/**
	 * Get Group form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getGroupIdFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("group_id")->getFormElement($formElementOverride);
	}

	/**
	 * Get M2mGroup Object (empty when the key is not set; loaded once per key value, $fresh reloads)
	 * @return \LafShell\M2mGroup
	 */
	public function getGroupIdObj(bool $fresh = false)
	{
		/** @var \LafShell\M2mGroup $object */
		$object = $this->relatedObject(\LafShell\M2mGroup::class, "group_id", $fresh);
		return $object;
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
	 * @return M2mPersonGroup[]
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
