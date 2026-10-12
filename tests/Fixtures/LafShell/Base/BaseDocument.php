<?php

namespace LafShell\Base;

use Laf\Database;
use Laf\Database\Table;
use Laf\Database\Field\Field;
use Laf\Database\PrimaryKey;
use Laf\Database\ForeignKey;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\ComponentInterface;
use LafShell\Document;
use Laf\Exception\InvalidForeignKeyValue;
use Laf\Util\Settings;

/**
 * Class BaseDocument
 * Hand-kept fixture in the shape of the generated Base classes: the document table that
 * Laf\Filesystem\Document stores uploads in (routerarena's column set).
 */
class BaseDocument extends Database\BaseObject
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
		$this->setTable(new Table('document'));

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
			->setName("file_name_original")
			->setLabel("File Name Original")
			->setPlaceHolder("File Name Original")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("file_name")
			->setLabel("File Name")
			->setPlaceHolder("File Name")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("file_extension")
			->setLabel("File Extension")
			->setPlaceHolder("File Extension")
			->setRequired(false)
			->setMaxLength(10)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("thumbnail_name")
			->setLabel("Thumbnail Name")
			->setPlaceHolder("Thumbnail Name")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("file_size")
			->setLabel("File Size")
			->setPlaceHolder("File Size")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("mime_type")
			->setLabel("Mime Type")
			->setPlaceHolder("Mime Type")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("encrypt_key")
			->setLabel("Encrypt Key")
			->setPlaceHolder("Encrypt Key")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeVarchar());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("created_on")
			->setLabel("Created On")
			->setPlaceHolder("Created On")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeDateTime());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("created_by")
			->setLabel("Created By")
			->setPlaceHolder("Created By")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("updated_on")
			->setLabel("Updated On")
			->setPlaceHolder("Updated On")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeDateTime());
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("updated_by")
			->setLabel("Updated By")
			->setPlaceHolder("Updated By")
			->setRequired(false)
			->setMaxLength(255)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger());
		$this->getTable()->addField($field);
		$field = null;

		$this->getTable()->setPrimaryKey($pk);
	}

	public function setIdVal($value = null) { $this->setFieldValue("id", $value); return static::returnLeafClass(); }
	public function getIdVal() { return $this->getFieldValue("id"); }
	public function setFileNameOriginalVal($value = null) { $this->setFieldValue("file_name_original", $value); return static::returnLeafClass(); }
	public function getFileNameOriginalVal() { return $this->getFieldValue("file_name_original"); }
	public function setFileNameVal($value = null) { $this->setFieldValue("file_name", $value); return static::returnLeafClass(); }
	public function getFileNameVal() { return $this->getFieldValue("file_name"); }
	public function setFileExtensionVal($value = null) { $this->setFieldValue("file_extension", $value); return static::returnLeafClass(); }
	public function getFileExtensionVal() { return $this->getFieldValue("file_extension"); }
	public function setThumbnailNameVal($value = null) { $this->setFieldValue("thumbnail_name", $value); return static::returnLeafClass(); }
	public function getThumbnailNameVal() { return $this->getFieldValue("thumbnail_name"); }
	public function setFileSizeVal($value = null) { $this->setFieldValue("file_size", $value); return static::returnLeafClass(); }
	public function getFileSizeVal() { return $this->getFieldValue("file_size"); }
	public function setMimeTypeVal($value = null) { $this->setFieldValue("mime_type", $value); return static::returnLeafClass(); }
	public function getMimeTypeVal() { return $this->getFieldValue("mime_type"); }
	public function setEncryptKeyVal($value = null) { $this->setFieldValue("encrypt_key", $value); return static::returnLeafClass(); }
	public function getEncryptKeyVal() { return $this->getFieldValue("encrypt_key"); }
	public function setCreatedOnVal($value = null) { $this->setFieldValue("created_on", $value); return static::returnLeafClass(); }
	public function getCreatedOnVal() { return $this->getFieldValue("created_on"); }
	public function setCreatedByVal($value = null) { $this->setFieldValue("created_by", $value); return static::returnLeafClass(); }
	public function getCreatedByVal() { return $this->getFieldValue("created_by"); }
	public function setUpdatedOnVal($value = null) { $this->setFieldValue("updated_on", $value); return static::returnLeafClass(); }
	public function getUpdatedOnVal() { return $this->getFieldValue("updated_on"); }
	public function setUpdatedByVal($value = null) { $this->setFieldValue("updated_by", $value); return static::returnLeafClass(); }
	public function getUpdatedByVal() { return $this->getFieldValue("updated_by"); }

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
}
