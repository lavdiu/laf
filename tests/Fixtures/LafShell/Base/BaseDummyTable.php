<?php

namespace LafShell\Base;

use Laf\Database;
use Laf\Database\Table;
use Laf\Database\Field\Field;
use Laf\Database\PrimaryKey;
use Laf\Database\ForeignKey;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\ComponentInterface;
use LafShell\DummyTable;
use Laf\Exception\InvalidForeignKeyValue;

/**
 * Class BaseDummyTable
 * @package LafShell\Base
 * Base Class for Table dummy_table
 * Basic definition of the fields and relationship with the Database Table dummy_table
 * This class will be auto-generated every time there is a schema change
 * Please do not add code here. Instead add your code at the main class one directory above
 */
class BaseDummyTable extends Database\BaseObject
{
	/**
	 * BaseDummyTable constructor: binds the table and loads the row when a key is given
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
		$this->setTable(new Table("dummy_table"));
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
			->setName("varchar_field45")
			->setLabel("Varchar Field45")
			->setPlaceholder("Varchar Field45")
			->setRequired(false)
			->setMaxLength(45)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("text_field")
			->setLabel("Text Field")
			->setPlaceholder("Text Field")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeText());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("integer_field")
			->setLabel("Integer Field")
			->setPlaceholder("Integer Field")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger('int'));
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("decimal_field")
			->setLabel("Decimal Field")
			->setPlaceholder("Decimal Field")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeDecimal(8, 2));
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("date_field")
			->setLabel("Date Field")
			->setPlaceholder("Date Field")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeDate());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("datetime_field")
			->setLabel("Datetime Field")
			->setPlaceholder("Datetime Field")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeDateTime());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("time_field")
			->setLabel("Time Field")
			->setPlaceholder("Time Field")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeTime());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("float_field")
			->setLabel("Float Field")
			->setPlaceholder("Float Field")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeFloat());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("json_field")
			->setLabel("Json Field")
			->setPlaceholder("Json Field")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeJson());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("null_field")
			->setLabel("Null Field")
			->setPlaceholder("Null Field")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeText());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("empty_field")
			->setLabel("Empty Field")
			->setPlaceholder("Empty Field")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeText());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("unique_field")
			->setLabel("Unique Field")
			->setPlaceholder("Unique Field")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(true)
			->setType(new Database\Field\TypeText());
		$this->getTable()->addUniqueField($field);
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("bool_field")
			->setLabel("Bool Field")
			->setPlaceholder("Bool Field")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeBool());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("parent_id")
			->setLabel("Parent")
			->setPlaceholder("Parent")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger('int'));
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("deleted")
			->setLabel("Deleted")
			->setPlaceholder("Deleted")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger('int'));
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("required_field")
			->setLabel("Required Field")
			->setPlaceholder("Required Field")
			->setRequired(true)
			->setMaxLength(10)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
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
			->setName("status")
			->setLabel("Status")
			->setPlaceholder("Status")
			->setRequired(false)
			->setMaxLength(10)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
		$this->getTable()->addField($field);
		$field = null;

		$this->getTable()->setPrimaryKey($pk);

		/**
		 * Generating Foreign keys
		 */
		$this->getTable()->addForeignKey(
			(new ForeignKey())
				->setField($this->getTable()->getField("parent_id"))
				->setKeyName('dummy_table_parent_id_fk')
				->setReferencingTable("dummy_table")
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
	 * Set Varchar Field45 value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setVarcharField45Val($value = null)
	{
		$this->setFieldValue("varchar_field45", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Varchar Field45 value
	 * @return mixed
	 */
	public function getVarcharField45Val()
	{
		return $this->getFieldValue("varchar_field45");
	}

	/**
	 * Get Varchar Field45 field reference
	 * @return Field
	 */
	public function getVarcharField45Fld()
	{
		return $this->getField("varchar_field45");
	}

	/**
	 * Get Varchar Field45 form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getVarcharField45FormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("varchar_field45")->getFormElement($formElementOverride);
	}

	/**
	 * Set Text Field value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setTextFieldVal($value = null)
	{
		$this->setFieldValue("text_field", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Text Field value
	 * @return mixed
	 */
	public function getTextFieldVal()
	{
		return $this->getFieldValue("text_field");
	}

	/**
	 * Get Text Field field reference
	 * @return Field
	 */
	public function getTextFieldFld()
	{
		return $this->getField("text_field");
	}

	/**
	 * Get Text Field form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getTextFieldFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("text_field")->getFormElement($formElementOverride);
	}

	/**
	 * Set Integer Field value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setIntegerFieldVal($value = null)
	{
		$this->setFieldValue("integer_field", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Integer Field value
	 * @return mixed
	 */
	public function getIntegerFieldVal()
	{
		return $this->getFieldValue("integer_field");
	}

	/**
	 * Get Integer Field field reference
	 * @return Field
	 */
	public function getIntegerFieldFld()
	{
		return $this->getField("integer_field");
	}

	/**
	 * Get Integer Field form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getIntegerFieldFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("integer_field")->getFormElement($formElementOverride);
	}

	/**
	 * Set Decimal Field value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setDecimalFieldVal($value = null)
	{
		$this->setFieldValue("decimal_field", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Decimal Field value
	 * @return mixed
	 */
	public function getDecimalFieldVal()
	{
		return $this->getFieldValue("decimal_field");
	}

	/**
	 * Get Decimal Field field reference
	 * @return Field
	 */
	public function getDecimalFieldFld()
	{
		return $this->getField("decimal_field");
	}

	/**
	 * Get Decimal Field form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getDecimalFieldFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("decimal_field")->getFormElement($formElementOverride);
	}

	/**
	 * Set Date Field value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setDateFieldVal($value = null)
	{
		$this->setFieldValue("date_field", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Date Field value
	 * @return mixed
	 */
	public function getDateFieldVal()
	{
		return $this->getFieldValue("date_field");
	}

	/**
	 * Get Date Field field reference
	 * @return Field
	 */
	public function getDateFieldFld()
	{
		return $this->getField("date_field");
	}

	/**
	 * Get Date Field form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getDateFieldFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("date_field")->getFormElement($formElementOverride);
	}

	/**
	 * Set Datetime Field value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setDatetimeFieldVal($value = null)
	{
		$this->setFieldValue("datetime_field", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Datetime Field value
	 * @return mixed
	 */
	public function getDatetimeFieldVal()
	{
		return $this->getFieldValue("datetime_field");
	}

	/**
	 * Get Datetime Field field reference
	 * @return Field
	 */
	public function getDatetimeFieldFld()
	{
		return $this->getField("datetime_field");
	}

	/**
	 * Get Datetime Field form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getDatetimeFieldFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("datetime_field")->getFormElement($formElementOverride);
	}

	/**
	 * Set Time Field value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setTimeFieldVal($value = null)
	{
		$this->setFieldValue("time_field", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Time Field value
	 * @return mixed
	 */
	public function getTimeFieldVal()
	{
		return $this->getFieldValue("time_field");
	}

	/**
	 * Get Time Field field reference
	 * @return Field
	 */
	public function getTimeFieldFld()
	{
		return $this->getField("time_field");
	}

	/**
	 * Get Time Field form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getTimeFieldFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("time_field")->getFormElement($formElementOverride);
	}

	/**
	 * Set Float Field value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setFloatFieldVal($value = null)
	{
		$this->setFieldValue("float_field", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Float Field value
	 * @return mixed
	 */
	public function getFloatFieldVal()
	{
		return $this->getFieldValue("float_field");
	}

	/**
	 * Get Float Field field reference
	 * @return Field
	 */
	public function getFloatFieldFld()
	{
		return $this->getField("float_field");
	}

	/**
	 * Get Float Field form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getFloatFieldFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("float_field")->getFormElement($formElementOverride);
	}

	/**
	 * Set Json Field value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setJsonFieldVal($value = null)
	{
		$this->setFieldValue("json_field", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Json Field value
	 * @return mixed
	 */
	public function getJsonFieldVal()
	{
		return $this->getFieldValue("json_field");
	}

	/**
	 * Get Json Field field reference
	 * @return Field
	 */
	public function getJsonFieldFld()
	{
		return $this->getField("json_field");
	}

	/**
	 * Get Json Field form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getJsonFieldFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("json_field")->getFormElement($formElementOverride);
	}

	/**
	 * Set Null Field value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setNullFieldVal($value = null)
	{
		$this->setFieldValue("null_field", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Null Field value
	 * @return mixed
	 */
	public function getNullFieldVal()
	{
		return $this->getFieldValue("null_field");
	}

	/**
	 * Get Null Field field reference
	 * @return Field
	 */
	public function getNullFieldFld()
	{
		return $this->getField("null_field");
	}

	/**
	 * Get Null Field form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getNullFieldFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("null_field")->getFormElement($formElementOverride);
	}

	/**
	 * Set Empty Field value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setEmptyFieldVal($value = null)
	{
		$this->setFieldValue("empty_field", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Empty Field value
	 * @return mixed
	 */
	public function getEmptyFieldVal()
	{
		return $this->getFieldValue("empty_field");
	}

	/**
	 * Get Empty Field field reference
	 * @return Field
	 */
	public function getEmptyFieldFld()
	{
		return $this->getField("empty_field");
	}

	/**
	 * Get Empty Field form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getEmptyFieldFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("empty_field")->getFormElement($formElementOverride);
	}

	/**
	 * Set Unique Field value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setUniqueFieldVal($value = null)
	{
		$this->setFieldValue("unique_field", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Unique Field value
	 * @return mixed
	 */
	public function getUniqueFieldVal()
	{
		return $this->getFieldValue("unique_field");
	}

	/**
	 * Get Unique Field field reference
	 * @return Field
	 */
	public function getUniqueFieldFld()
	{
		return $this->getField("unique_field");
	}

	/**
	 * Get Unique Field form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getUniqueFieldFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("unique_field")->getFormElement($formElementOverride);
	}

	/**
	 * Set Bool Field value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setBoolFieldVal($value = null)
	{
		$this->setFieldValue("bool_field", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Bool Field value
	 * @return mixed
	 */
	public function getBoolFieldVal()
	{
		return $this->getFieldValue("bool_field");
	}

	/**
	 * Get Bool Field field reference
	 * @return Field
	 */
	public function getBoolFieldFld()
	{
		return $this->getField("bool_field");
	}

	/**
	 * Get Bool Field form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getBoolFieldFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("bool_field")->getFormElement($formElementOverride);
	}

	/**
	 * Set Parent value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setParentIdVal($value = null)
	{
		$this->setFieldValue("parent_id", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Parent value
	 * @return mixed
	 */
	public function getParentIdVal()
	{
		return $this->getFieldValue("parent_id");
	}

	/**
	 * Get Parent field reference
	 * @return Field
	 */
	public function getParentIdFld()
	{
		return $this->getField("parent_id");
	}

	/**
	 * Get Parent form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getParentIdFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("parent_id")->getFormElement($formElementOverride);
	}

	/**
	 * Get DummyTable Object (empty when the key is not set)
	 * @return \LafShell\DummyTable
	 */
	public function getParentIdObj()
	{
		$value = $this->getParentIdVal();
		if ($value !== null && $value !== '') {
			return new \LafShell\DummyTable($value);
		}
		return new \LafShell\DummyTable();
	}

	/**
	 * Set Deleted value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setDeletedVal($value = null)
	{
		$this->setFieldValue("deleted", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Deleted value
	 * @return mixed
	 */
	public function getDeletedVal()
	{
		return $this->getFieldValue("deleted");
	}

	/**
	 * Get Deleted field reference
	 * @return Field
	 */
	public function getDeletedFld()
	{
		return $this->getField("deleted");
	}

	/**
	 * Get Deleted form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getDeletedFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("deleted")->getFormElement($formElementOverride);
	}

	/**
	 * Set Required Field value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setRequiredFieldVal($value = null)
	{
		$this->setFieldValue("required_field", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Required Field value
	 * @return mixed
	 */
	public function getRequiredFieldVal()
	{
		return $this->getFieldValue("required_field");
	}

	/**
	 * Get Required Field field reference
	 * @return Field
	 */
	public function getRequiredFieldFld()
	{
		return $this->getField("required_field");
	}

	/**
	 * Get Required Field form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getRequiredFieldFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("required_field")->getFormElement($formElementOverride);
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
	 * Set Status value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setStatusVal($value = null)
	{
		$this->setFieldValue("status", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Status value
	 * @return mixed
	 */
	public function getStatusVal()
	{
		return $this->getFieldValue("status");
	}

	/**
	 * Get Status field reference
	 * @return Field
	 */
	public function getStatusFld()
	{
		return $this->getField("status");
	}

	/**
	 * Get Status form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getStatusFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("status")->getFormElement($formElementOverride);
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
	 * @return DummyTable[]
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
