<?php

namespace MyApp\Base;

use Laf\Database;
use Laf\Database\Table;
use Laf\Database\Field\Field;
use Laf\Database\PrimaryKey;
use Laf\Database\ForeignKey;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\ComponentInterface;
use MyApp\Employee;
use Laf\Exception\InvalidForeignKeyValue;

/**
 * Class BaseEmployee
 * @package MyApp\Base
 * Base Class for Table employee
 * Basic definition of the fields and relationship with the Database Table employee
 * This class will be auto-generated every time there is a schema change
 * Please do not add code here. Instead add your code at the main class one directory above
 */
class BaseEmployee extends Database\BaseObject
{
	/**
	 * BaseEmployee constructor: binds the table and loads the row when a key is given
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
		$this->setTable(new Table("employee"));
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
			->setName("company_id")
			->setPlaceholder("Company")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger('int'));
		$field->setLabel('Company');
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("first_name")
			->setLabel("First Name")
			->setPlaceholder("First Name")
			->setRequired(true)
			->setMaxLength(50)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("last_name")
			->setLabel("Last Name")
			->setPlaceholder("Last Name")
			->setRequired(true)
			->setMaxLength(50)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("email")
			->setPlaceholder("Email")
			->setRequired(true)
			->setMaxLength(150)
			->setAutoIncrement(false)
			->setUnique(true)
			->setType(new Database\Field\TypeVarchar());
		$field->setLabel('E-mail');
		$this->getTable()->addUniqueField($field);
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("birth_date")
			->setLabel("Birth Date")
			->setPlaceholder("Birth Date")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeDate());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("salary")
			->setLabel("Salary")
			->setPlaceholder("Salary")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeDecimal(10, 2));
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("is_active")
			->setPlaceholder("Is Active")
			->setRequired(true)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeBool());
		$field->setLabel('Active');
		$field->setRequired(false);
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("notes")
			->setLabel("Notes")
			->setPlaceholder("Notes")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeText());
		$field->setFormOptions(['tab' => 'Notes']);
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("created_on")
			->setLabel("Created On")
			->setPlaceholder("Created On")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeDateTime());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("created_by")
			->setLabel("Created By")
			->setPlaceholder("Created By")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger('int'));
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("updated_on")
			->setLabel("Updated On")
			->setPlaceholder("Updated On")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeDateTime());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("updated_by")
			->setLabel("Updated By")
			->setPlaceholder("Updated By")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger('int'));
		$this->getTable()->addField($field);
		$field = null;

		$this->getTable()->setPrimaryKey($pk);
		$this->getTable()->setDisplayField($this->getTable()->getField("email"));

		/**
		 * Generating Foreign keys
		 */
		$this->getTable()->addForeignKey(
			(new ForeignKey())
				->setField($this->getTable()->getField("company_id"))
				->setKeyName('employee_company_fk')
				->setReferencingTable("company")
				->setReferencingField("id")
		);

		/**
		 * Many-to-many relations through linker tables
		 */
		$this->getTable()->addManyToMany("employee_project", ['owner' => 'employee_id', 'other' => 'project_id', 'table' => 'project', 'label' => 'Projects']);

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
	 * Set Company value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setCompanyIdVal($value = null)
	{
		$this->setFieldValue("company_id", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Company value
	 * @return mixed
	 */
	public function getCompanyIdVal()
	{
		return $this->getFieldValue("company_id");
	}

	/**
	 * Get Company field reference
	 * @return Field
	 */
	public function getCompanyIdFld()
	{
		return $this->getField("company_id");
	}

	/**
	 * Get Company form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getCompanyIdFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("company_id")->getFormElement($formElementOverride);
	}

	/**
	 * Get Company Object (empty when the key is not set; loaded once per key value, $fresh reloads)
	 * @return \MyApp\Company
	 */
	public function getCompanyIdObj(bool $fresh = false)
	{
		/** @var \MyApp\Company $object */
		$object = $this->relatedObject(\MyApp\Company::class, "company_id", $fresh);
		return $object;
	}

	/**
	 * Set First Name value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setFirstNameVal($value = null)
	{
		$this->setFieldValue("first_name", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get First Name value
	 * @return mixed
	 */
	public function getFirstNameVal()
	{
		return $this->getFieldValue("first_name");
	}

	/**
	 * Get First Name field reference
	 * @return Field
	 */
	public function getFirstNameFld()
	{
		return $this->getField("first_name");
	}

	/**
	 * Get First Name form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getFirstNameFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("first_name")->getFormElement($formElementOverride);
	}

	/**
	 * Set Last Name value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setLastNameVal($value = null)
	{
		$this->setFieldValue("last_name", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Last Name value
	 * @return mixed
	 */
	public function getLastNameVal()
	{
		return $this->getFieldValue("last_name");
	}

	/**
	 * Get Last Name field reference
	 * @return Field
	 */
	public function getLastNameFld()
	{
		return $this->getField("last_name");
	}

	/**
	 * Get Last Name form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getLastNameFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("last_name")->getFormElement($formElementOverride);
	}

	/**
	 * Set Email value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setEmailVal($value = null)
	{
		$this->setFieldValue("email", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Email value
	 * @return mixed
	 */
	public function getEmailVal()
	{
		return $this->getFieldValue("email");
	}

	/**
	 * Get Email field reference
	 * @return Field
	 */
	public function getEmailFld()
	{
		return $this->getField("email");
	}

	/**
	 * Get Email form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getEmailFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("email")->getFormElement($formElementOverride);
	}

	/**
	 * Set Birth Date value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setBirthDateVal($value = null)
	{
		$this->setFieldValue("birth_date", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Birth Date value
	 * @return mixed
	 */
	public function getBirthDateVal()
	{
		return $this->getFieldValue("birth_date");
	}

	/**
	 * Get Birth Date field reference
	 * @return Field
	 */
	public function getBirthDateFld()
	{
		return $this->getField("birth_date");
	}

	/**
	 * Get Birth Date form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getBirthDateFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("birth_date")->getFormElement($formElementOverride);
	}

	/**
	 * Set Salary value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setSalaryVal($value = null)
	{
		$this->setFieldValue("salary", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Salary value
	 * @return mixed
	 */
	public function getSalaryVal()
	{
		return $this->getFieldValue("salary");
	}

	/**
	 * Get Salary field reference
	 * @return Field
	 */
	public function getSalaryFld()
	{
		return $this->getField("salary");
	}

	/**
	 * Get Salary form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getSalaryFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("salary")->getFormElement($formElementOverride);
	}

	/**
	 * Set Is Active value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setIsActiveVal($value = null)
	{
		$this->setFieldValue("is_active", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Is Active value
	 * @return mixed
	 */
	public function getIsActiveVal()
	{
		return $this->getFieldValue("is_active");
	}

	/**
	 * Get Is Active field reference
	 * @return Field
	 */
	public function getIsActiveFld()
	{
		return $this->getField("is_active");
	}

	/**
	 * Get Is Active form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getIsActiveFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("is_active")->getFormElement($formElementOverride);
	}

	/**
	 * Set Notes value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setNotesVal($value = null)
	{
		$this->setFieldValue("notes", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Notes value
	 * @return mixed
	 */
	public function getNotesVal()
	{
		return $this->getFieldValue("notes");
	}

	/**
	 * Get Notes field reference
	 * @return Field
	 */
	public function getNotesFld()
	{
		return $this->getField("notes");
	}

	/**
	 * Get Notes form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getNotesFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("notes")->getFormElement($formElementOverride);
	}

	/**
	 * Set Created On value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setCreatedOnVal($value = null)
	{
		$this->setFieldValue("created_on", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Created On value
	 * @return mixed
	 */
	public function getCreatedOnVal()
	{
		return $this->getFieldValue("created_on");
	}

	/**
	 * Get Created On field reference
	 * @return Field
	 */
	public function getCreatedOnFld()
	{
		return $this->getField("created_on");
	}

	/**
	 * Get Created On form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getCreatedOnFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("created_on")->getFormElement($formElementOverride);
	}

	/**
	 * Set Created By value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setCreatedByVal($value = null)
	{
		$this->setFieldValue("created_by", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Created By value
	 * @return mixed
	 */
	public function getCreatedByVal()
	{
		return $this->getFieldValue("created_by");
	}

	/**
	 * Get Created By field reference
	 * @return Field
	 */
	public function getCreatedByFld()
	{
		return $this->getField("created_by");
	}

	/**
	 * Get Created By form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getCreatedByFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("created_by")->getFormElement($formElementOverride);
	}

	/**
	 * Set Updated On value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setUpdatedOnVal($value = null)
	{
		$this->setFieldValue("updated_on", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Updated On value
	 * @return mixed
	 */
	public function getUpdatedOnVal()
	{
		return $this->getFieldValue("updated_on");
	}

	/**
	 * Get Updated On field reference
	 * @return Field
	 */
	public function getUpdatedOnFld()
	{
		return $this->getField("updated_on");
	}

	/**
	 * Get Updated On form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getUpdatedOnFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("updated_on")->getFormElement($formElementOverride);
	}

	/**
	 * Set Updated By value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setUpdatedByVal($value = null)
	{
		$this->setFieldValue("updated_by", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Updated By value
	 * @return mixed
	 */
	public function getUpdatedByVal()
	{
		return $this->getFieldValue("updated_by");
	}

	/**
	 * Get Updated By field reference
	 * @return Field
	 */
	public function getUpdatedByFld()
	{
		return $this->getField("updated_by");
	}

	/**
	 * Get Updated By form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getUpdatedByFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("updated_by")->getFormElement($formElementOverride);
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
	 * @return Employee[]
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
