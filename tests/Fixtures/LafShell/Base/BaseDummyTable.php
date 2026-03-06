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
use Laf\Util\Settings;

class BaseDummyTable extends Database\BaseObject
{
	public function __construct($id = null)
	{
		parent::__construct($id);
		$this->buildClass();
		$this->setRecordId($id);
		if (is_numeric($id) && in_array($this->getTable()->getPrimaryKey()->getFirstField()->getType(), [Database\Field\FieldType::TYPE_BIG_INTEGER, Database\Field\FieldType::TYPE_INTEGER])) {
			self::select($id);
		} else if ($id != '') {
			self::select($id);
		}
	}

	public function select($id): bool
	{
		$this->setRecordId($id);
		return parent::select($id);
	}

	private function buildClass()
	{
		$this->setTable(new Table('dummy_table'));

		$pk = new PrimaryKey();
		$field = (new Field())
			->setName("id")
			->setLabel("Id")
			->setPlaceHolder("Id")
			->setRequired(true)
			->setMaxLength(255)
			->setAutoIncrement(true)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger());
		$pk->addField($field);
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("varchar_field45")
			->setLabel("Varchar Field45")
			->setPlaceHolder("Varchar Field45")
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
			->setPlaceHolder("Text Field")
			->setRequired(false)
			->setMaxLength(65535)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeText());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("integer_field")
			->setLabel("Integer Field")
			->setPlaceHolder("Integer Field")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("decimal_field")
			->setLabel("Decimal Field")
			->setPlaceHolder("Decimal Field")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeFloat());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("date_field")
			->setLabel("Date Field")
			->setPlaceHolder("Date Field")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeDate());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("datetime_field")
			->setLabel("Datetime Field")
			->setPlaceHolder("Datetime Field")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeDateTime());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("time_field")
			->setLabel("Time Field")
			->setPlaceHolder("Time Field")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeTime());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("float_field")
			->setLabel("Float Field")
			->setPlaceHolder("Float Field")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeFloat());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("json_field")
			->setLabel("Json Field")
			->setPlaceHolder("Json Field")
			->setRequired(false)
			->setMaxLength(4294967295)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeText());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("null_field")
			->setLabel("Null Field")
			->setPlaceHolder("Null Field")
			->setRequired(false)
			->setMaxLength(65535)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeText());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("empty_field")
			->setLabel("Empty Field")
			->setPlaceHolder("Empty Field")
			->setRequired(false)
			->setMaxLength(65535)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeText());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("unique_field")
			->setLabel("Unique Field")
			->setPlaceHolder("Unique Field")
			->setRequired(false)
			->setMaxLength(65535)
			->setAutoIncrement(false)
			->setUnique(true)
			->setType(new Database\Field\TypeText());
		$this->getTable()->addUniqueField($field);
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("bool_field")
			->setLabel("Bool Field")
			->setPlaceHolder("Bool Field")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeBool());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("parent_id")
			->setLabel("Parent")
			->setPlaceHolder("Parent")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("deleted")
			->setLabel("Deleted")
			->setPlaceHolder("Deleted")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger());
		$this->getTable()->addField($field);
		$field = null;

		$this->getTable()->setPrimaryKey($pk);

		$this->getTable()->addForeignKey(
			(new ForeignKey())
				->setField($this->getTable()->getField("parent_id"))
				->setKeyName('laf_test_dt_parent_id_fk')
				->setReferencingTable("dummy_table")
				->setReferencingField("id")
		);
	}

	public function setIdVal($value = null) { $this->setFieldValue("id", $value); return static::returnLeafClass(); }
	public function getIdVal() { return $this->getFieldValue("id"); }
	public function setVarcharField45Val($value = null) { $this->setFieldValue("varchar_field45", $value); return static::returnLeafClass(); }
	public function getVarcharField45Val() { return $this->getFieldValue("varchar_field45"); }
	public function setTextFieldVal($value = null) { $this->setFieldValue("text_field", $value); return static::returnLeafClass(); }
	public function getTextFieldVal() { return $this->getFieldValue("text_field"); }
	public function setIntegerFieldVal($value = null) { $this->setFieldValue("integer_field", $value); return static::returnLeafClass(); }
	public function getIntegerFieldVal() { return $this->getFieldValue("integer_field"); }
	public function setDecimalFieldVal($value = null) { $this->setFieldValue("decimal_field", $value); return static::returnLeafClass(); }
	public function getDecimalFieldVal() { return $this->getFieldValue("decimal_field"); }
	public function setDateFieldVal($value = null) { $this->setFieldValue("date_field", $value); return static::returnLeafClass(); }
	public function getDateFieldVal() { return $this->getFieldValue("date_field"); }
	public function setDatetimeFieldVal($value = null) { $this->setFieldValue("datetime_field", $value); return static::returnLeafClass(); }
	public function getDatetimeFieldVal() { return $this->getFieldValue("datetime_field"); }
	public function setTimeFieldVal($value = null) { $this->setFieldValue("time_field", $value); return static::returnLeafClass(); }
	public function getTimeFieldVal() { return $this->getFieldValue("time_field"); }
	public function setFloatFieldVal($value = null) { $this->setFieldValue("float_field", $value); return static::returnLeafClass(); }
	public function getFloatFieldVal() { return $this->getFieldValue("float_field"); }
	public function setJsonFieldVal($value = null) { $this->setFieldValue("json_field", $value); return static::returnLeafClass(); }
	public function getJsonFieldVal() { return $this->getFieldValue("json_field"); }
	public function setNullFieldVal($value = null) { $this->setFieldValue("null_field", $value); return static::returnLeafClass(); }
	public function getNullFieldVal() { return $this->getFieldValue("null_field"); }
	public function setEmptyFieldVal($value = null) { $this->setFieldValue("empty_field", $value); return static::returnLeafClass(); }
	public function getEmptyFieldVal() { return $this->getFieldValue("empty_field"); }
	public function setUniqueFieldVal($value = null) { $this->setFieldValue("unique_field", $value); return static::returnLeafClass(); }
	public function getUniqueFieldVal() { return $this->getFieldValue("unique_field"); }
	public function setBoolFieldVal($value = null) { $this->setFieldValue("bool_field", $value); return static::returnLeafClass(); }
	public function getBoolFieldVal() { return $this->getFieldValue("bool_field"); }
	public function setParentIdVal($value = null) { $this->setFieldValue("parent_id", $value); return static::returnLeafClass(); }
	public function getParentIdVal() { return $this->getFieldValue("parent_id"); }
	public function setDeletedVal($value = null) { $this->setFieldValue("deleted", $value); return static::returnLeafClass(); }
	public function getDeletedVal() { return $this->getFieldValue("deleted"); }
}
