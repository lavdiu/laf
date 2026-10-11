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
 * Base Class for Table code_table — a VARCHAR primary key that is not auto-increment.
 * Hand-maintained to match ClassGenerator output; see tests/Fixtures/regenerate.php.
 */
class BaseCodeTable extends Database\BaseObject
{
	public function __construct($id = null)
	{
		parent::__construct($id);
		$this->buildClass();
		$this->setRecordId($id);
		if ($id != '') {
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
		$this->setTable(new Table('code_table'));

		$pk = new PrimaryKey();
		$field = (new Field())
			->setName("code")
			->setLabel("Code")
			->setPlaceHolder("Code")
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
			->setPlaceHolder("Name")
			->setRequired(false)
			->setMaxLength(50)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
		$this->getTable()->addField($field);
		$field = null;

		$this->getTable()->setPrimaryKey($pk);
	}

	public function setCodeVal($value = null) { $this->setFieldValue("code", $value); return static::returnLeafClass(); }
	public function getCodeVal() { return $this->getFieldValue("code"); }
	public function setNameVal($value = null) { $this->setFieldValue("name", $value); return static::returnLeafClass(); }
	public function getNameVal() { return $this->getFieldValue("name"); }

	public function listAllArray(): array
	{
		$query = static::query();
		foreach ($this->getTable()->getPrimaryKey()->getFieldNames() as $keyColumn) {
			$query->orderBy($keyColumn);
		}
		return $query->arrays();
	}

	public function listAllObjects(): array
	{
		$query = static::query();
		foreach ($this->getTable()->getPrimaryKey()->getFieldNames() as $keyColumn) {
			$query->orderBy($keyColumn);
		}
		return $query->get();
	}

	public function getAllFieldsAsFormElements(): array
	{
		$tmp = [];
		foreach ($this->getTable()->getFields() as $field) {
			$tmp[] = $field->getFormElement();
		}
		return $tmp;
	}
}
