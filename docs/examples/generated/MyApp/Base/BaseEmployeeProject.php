<?php

namespace MyApp\Base;

use Laf\Database;
use Laf\Database\Table;
use Laf\Database\Field\Field;
use Laf\Database\PrimaryKey;
use Laf\Database\ForeignKey;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\ComponentInterface;
use MyApp\EmployeeProject;
use Laf\Exception\InvalidForeignKeyValue;

/**
 * Class BaseEmployeeProject
 * @package MyApp\Base
 * Base Class for Table employee_project
 * Basic definition of the fields and relationship with the Database Table employee_project
 * This class will be auto-generated every time there is a schema change
 * Please do not add code here. Instead add your code at the main class one directory above
 */
class BaseEmployeeProject extends Database\BaseObject
{
	/**
	 * BaseEmployeeProject constructor: binds the table and loads the row when a key is given
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
		$this->setTable(new Table("employee_project"));
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
			->setName("employee_id")
			->setPlaceholder("Employee")
			->setRequired(true)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger('int'));
		$field->setLabel('Team members');
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("project_id")
			->setPlaceholder("Project")
			->setRequired(true)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger('int'));
		$field->setLabel('Projects');
		$this->getTable()->addField($field);
		$field = null;

		$this->getTable()->setPrimaryKey($pk);

		/**
		 * Generating Foreign keys
		 */
		$this->getTable()->addForeignKey(
			(new ForeignKey())
				->setField($this->getTable()->getField("employee_id"))
				->setKeyName('employee_project_employee_fk')
				->setReferencingTable("employee")
				->setReferencingField("id")
				->setOnDelete('CASCADE')
		);

		$this->getTable()->addForeignKey(
			(new ForeignKey())
				->setField($this->getTable()->getField("project_id"))
				->setKeyName('employee_project_project_fk')
				->setReferencingTable("project")
				->setReferencingField("id")
				->setOnDelete('CASCADE')
		);

		/**
		 * Unique keys over several columns
		 */
		$this->getTable()->addUniqueKey("employee_project_unique", ['employee_id', 'project_id']);

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
	 * Set Employee value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setEmployeeIdVal($value = null)
	{
		$this->setFieldValue("employee_id", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Employee value
	 * @return mixed
	 */
	public function getEmployeeIdVal()
	{
		return $this->getFieldValue("employee_id");
	}

	/**
	 * Get Employee field reference
	 * @return Field
	 */
	public function getEmployeeIdFld()
	{
		return $this->getField("employee_id");
	}

	/**
	 * Get Employee form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getEmployeeIdFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("employee_id")->getFormElement($formElementOverride);
	}

	/**
	 * Get Employee Object (empty when the key is not set; loaded once per key value, $fresh reloads)
	 * @return \MyApp\Employee
	 */
	public function getEmployeeIdObj(bool $fresh = false)
	{
		/** @var \MyApp\Employee $object */
		$object = $this->relatedObject(\MyApp\Employee::class, "employee_id", $fresh);
		return $object;
	}

	/**
	 * Set Project value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setProjectIdVal($value = null)
	{
		$this->setFieldValue("project_id", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Project value
	 * @return mixed
	 */
	public function getProjectIdVal()
	{
		return $this->getFieldValue("project_id");
	}

	/**
	 * Get Project field reference
	 * @return Field
	 */
	public function getProjectIdFld()
	{
		return $this->getField("project_id");
	}

	/**
	 * Get Project form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getProjectIdFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("project_id")->getFormElement($formElementOverride);
	}

	/**
	 * Get Project Object (empty when the key is not set; loaded once per key value, $fresh reloads)
	 * @return \MyApp\Project
	 */
	public function getProjectIdObj(bool $fresh = false)
	{
		/** @var \MyApp\Project $object */
		$object = $this->relatedObject(\MyApp\Project::class, "project_id", $fresh);
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
	 * @return EmployeeProject[]
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
