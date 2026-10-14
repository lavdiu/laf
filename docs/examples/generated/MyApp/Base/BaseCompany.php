<?php

namespace MyApp\Base;

use Laf\Database;
use Laf\Database\Table;
use Laf\Database\Field\Field;
use Laf\Database\PrimaryKey;
use Laf\Database\ForeignKey;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\ComponentInterface;
use MyApp\Company;
use Laf\Exception\InvalidForeignKeyValue;

/**
 * Class BaseCompany
 * @package MyApp\Base
 * Base Class for Table company
 * Basic definition of the fields and relationship with the Database Table company
 * This class will be auto-generated every time there is a schema change
 * Please do not add code here. Instead add your code at the main class one directory above
 */
class BaseCompany extends Database\BaseObject
{
	/**
	 * BaseCompany constructor: binds the table and loads the row when a key is given
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
		$this->setTable(new Table("company"));
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
			->setName("name")
			->setLabel("Name")
			->setPlaceholder("Name")
			->setRequired(true)
			->setMaxLength(100)
			->setAutoIncrement(false)
			->setUnique(true)
			->setType(new Database\Field\TypeVarchar());
		$this->getTable()->addUniqueField($field);
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("vat_number")
			->setPlaceholder("Vat Number")
			->setRequired(false)
			->setMaxLength(20)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
		$field->setLabel('VAT number');
		$field->setHint('Leave empty for private persons');
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("email")
			->setPlaceholder("Email")
			->setRequired(false)
			->setMaxLength(150)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
		$field->setLabel('E-mail');
		$field->setFormOptions(['group' => 'Contact']);
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("phone")
			->setLabel("Phone")
			->setPlaceholder("Phone")
			->setRequired(false)
			->setMaxLength(30)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
		$field->setFormOptions(['group' => 'Contact']);
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("record_status_id")
			->setLabel("Record Status")
			->setPlaceholder("Record Status")
			->setRequired(true)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger('tinyint'));
		$field->setRequired(false);
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

		/**
		 * Generating Foreign keys
		 */
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
	 * Set Vat Number value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setVatNumberVal($value = null)
	{
		$this->setFieldValue("vat_number", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Vat Number value
	 * @return mixed
	 */
	public function getVatNumberVal()
	{
		return $this->getFieldValue("vat_number");
	}

	/**
	 * Get Vat Number field reference
	 * @return Field
	 */
	public function getVatNumberFld()
	{
		return $this->getField("vat_number");
	}

	/**
	 * Get Vat Number form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getVatNumberFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("vat_number")->getFormElement($formElementOverride);
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
	 * Set Phone value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setPhoneVal($value = null)
	{
		$this->setFieldValue("phone", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Phone value
	 * @return mixed
	 */
	public function getPhoneVal()
	{
		return $this->getFieldValue("phone");
	}

	/**
	 * Get Phone field reference
	 * @return Field
	 */
	public function getPhoneFld()
	{
		return $this->getField("phone");
	}

	/**
	 * Get Phone form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getPhoneFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("phone")->getFormElement($formElementOverride);
	}

	/**
	 * Set Record Status value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setRecordStatusIdVal($value = null)
	{
		$this->setFieldValue("record_status_id", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Record Status value
	 * @return mixed
	 */
	public function getRecordStatusIdVal()
	{
		return $this->getFieldValue("record_status_id");
	}

	/**
	 * Get Record Status field reference
	 * @return Field
	 */
	public function getRecordStatusIdFld()
	{
		return $this->getField("record_status_id");
	}

	/**
	 * Get Record Status form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getRecordStatusIdFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("record_status_id")->getFormElement($formElementOverride);
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
	 * @return Company[]
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
