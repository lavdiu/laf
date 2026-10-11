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
 * Base Class for Table link_table — composite primary key (left_id, code) with an
 * integer FK to dummy_table.id and a VARCHAR FK to code_table.code.
 * Hand-maintained to match ClassGenerator output; see tests/Fixtures/regenerate.php.
 */
class BaseLinkTable extends Database\BaseObject
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
		$this->setTable(new Table('link_table'));

		$pk = new PrimaryKey();
		$field = (new Field())
			->setName("left_id")
			->setLabel("Left")
			->setPlaceHolder("Left")
			->setRequired(true)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger('int'));
		$pk->addField($field);
		$this->getTable()->addField($field);
		$field = null;

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
			->setName("note")
			->setLabel("Note")
			->setPlaceHolder("Note")
			->setRequired(false)
			->setMaxLength(65535)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeText());
		$this->getTable()->addField($field);
		$field = null;

		$this->getTable()->setPrimaryKey($pk);

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

	public function setLeftIdVal($value = null) { $this->setFieldValue("left_id", $value); return static::returnLeafClass(); }
	public function getLeftIdVal() { return $this->getFieldValue("left_id"); }
	public function getLeftIdObj()
	{
		if (is_numeric($this->getLeftIdVal())) {
			return new \LafShell\DummyTable($this->getLeftIdVal());
		} else {
			return new \LafShell\DummyTable();
		}
	}
	public function setCodeVal($value = null) { $this->setFieldValue("code", $value); return static::returnLeafClass(); }
	public function getCodeVal() { return $this->getFieldValue("code"); }
	public function getCodeObj()
	{
		return new \LafShell\CodeTable($this->getCodeVal());
	}
	public function setNoteVal($value = null) { $this->setFieldValue("note", $value); return static::returnLeafClass(); }
	public function getNoteVal() { return $this->getFieldValue("note"); }

	public function listAllArray(): array
	{
		$db = Database\Db::getInstance();
		$sql = "SELECT * FROM {$this->getTable()->getName()} ORDER BY left_id, code ASC";
		$res = $db->query($sql);
		return $res->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function listAllObjects(): array
	{
		$db = Database\Db::getInstance();
		$pk = $this->getTable()->getPrimaryKey();
		$keyColumns = join(', ', $pk->getFieldNames());
		$sql = "SELECT {$keyColumns} FROM {$this->getTable()->getName()} ORDER BY {$keyColumns} ASC";
		$res = $db->query($sql);

		$objects = [];
		foreach ($res->fetchAll(\PDO::FETCH_ASSOC) as $row) {
			$objects[] = new static($pk->isComposite() ? $row : reset($row));
		}
		return $objects;
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
