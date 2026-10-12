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

/**
 * Class BaseDocument
 * @package LafShell\Base
 * Base Class for Table document
 * Basic definition of the fields and relationship with the Database Table document
 * This class will be auto-generated every time there is a schema change
 * Please do not add code here. Instead add your code at the main class one directory above
 */
class BaseDocument extends Database\BaseObject
{
	/**
	 * BaseDocument constructor: binds the table and loads the row when a key is given
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
		$this->setTable(new Table("document"));
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
			->setName("file_name_original")
			->setLabel("File Name Original")
			->setPlaceholder("File Name Original")
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
			->setPlaceholder("File Name")
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
			->setPlaceholder("File Extension")
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
			->setPlaceholder("Thumbnail Name")
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
			->setPlaceholder("File Size")
			->setRequired(false)
			->setAutoIncrement(false)
			->setUnique(false)
			->setType(new Database\Field\TypeInteger('int'));
		$this->getTable()->addField($field);
		$field = null;

		$field = (new Field())
			->setName("mime_type")
			->setLabel("Mime Type")
			->setPlaceholder("Mime Type")
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
			->setPlaceholder("Encrypt Key")
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
	 * Set File Name Original value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setFileNameOriginalVal($value = null)
	{
		$this->setFieldValue("file_name_original", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get File Name Original value
	 * @return mixed
	 */
	public function getFileNameOriginalVal()
	{
		return $this->getFieldValue("file_name_original");
	}

	/**
	 * Get File Name Original field reference
	 * @return Field
	 */
	public function getFileNameOriginalFld()
	{
		return $this->getField("file_name_original");
	}

	/**
	 * Get File Name Original form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getFileNameOriginalFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("file_name_original")->getFormElement($formElementOverride);
	}

	/**
	 * Set File Name value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setFileNameVal($value = null)
	{
		$this->setFieldValue("file_name", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get File Name value
	 * @return mixed
	 */
	public function getFileNameVal()
	{
		return $this->getFieldValue("file_name");
	}

	/**
	 * Get File Name field reference
	 * @return Field
	 */
	public function getFileNameFld()
	{
		return $this->getField("file_name");
	}

	/**
	 * Get File Name form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getFileNameFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("file_name")->getFormElement($formElementOverride);
	}

	/**
	 * Set File Extension value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setFileExtensionVal($value = null)
	{
		$this->setFieldValue("file_extension", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get File Extension value
	 * @return mixed
	 */
	public function getFileExtensionVal()
	{
		return $this->getFieldValue("file_extension");
	}

	/**
	 * Get File Extension field reference
	 * @return Field
	 */
	public function getFileExtensionFld()
	{
		return $this->getField("file_extension");
	}

	/**
	 * Get File Extension form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getFileExtensionFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("file_extension")->getFormElement($formElementOverride);
	}

	/**
	 * Set Thumbnail Name value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setThumbnailNameVal($value = null)
	{
		$this->setFieldValue("thumbnail_name", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Thumbnail Name value
	 * @return mixed
	 */
	public function getThumbnailNameVal()
	{
		return $this->getFieldValue("thumbnail_name");
	}

	/**
	 * Get Thumbnail Name field reference
	 * @return Field
	 */
	public function getThumbnailNameFld()
	{
		return $this->getField("thumbnail_name");
	}

	/**
	 * Get Thumbnail Name form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getThumbnailNameFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("thumbnail_name")->getFormElement($formElementOverride);
	}

	/**
	 * Set File Size value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setFileSizeVal($value = null)
	{
		$this->setFieldValue("file_size", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get File Size value
	 * @return mixed
	 */
	public function getFileSizeVal()
	{
		return $this->getFieldValue("file_size");
	}

	/**
	 * Get File Size field reference
	 * @return Field
	 */
	public function getFileSizeFld()
	{
		return $this->getField("file_size");
	}

	/**
	 * Get File Size form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getFileSizeFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("file_size")->getFormElement($formElementOverride);
	}

	/**
	 * Set Mime Type value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setMimeTypeVal($value = null)
	{
		$this->setFieldValue("mime_type", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Mime Type value
	 * @return mixed
	 */
	public function getMimeTypeVal()
	{
		return $this->getFieldValue("mime_type");
	}

	/**
	 * Get Mime Type field reference
	 * @return Field
	 */
	public function getMimeTypeFld()
	{
		return $this->getField("mime_type");
	}

	/**
	 * Get Mime Type form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getMimeTypeFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("mime_type")->getFormElement($formElementOverride);
	}

	/**
	 * Set Encrypt Key value
	 * @param mixed $value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function setEncryptKeyVal($value = null)
	{
		$this->setFieldValue("encrypt_key", $value);
		return static::returnLeafClass();
	}

	/**
	 * Get Encrypt Key value
	 * @return mixed
	 */
	public function getEncryptKeyVal()
	{
		return $this->getFieldValue("encrypt_key");
	}

	/**
	 * Get Encrypt Key field reference
	 * @return Field
	 */
	public function getEncryptKeyFld()
	{
		return $this->getField("encrypt_key");
	}

	/**
	 * Get Encrypt Key form element reference
	 * @param FormElementInterface|null $formElementOverride
	 * @return ComponentInterface
	 */
	public function getEncryptKeyFormElement(?FormElementInterface $formElementOverride = null) : ComponentInterface
	{
		return $this->getField("encrypt_key")->getFormElement($formElementOverride);
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
	 * @return Document[]
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
