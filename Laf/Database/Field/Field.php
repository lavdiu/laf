<?php

namespace Laf\Database\Field;

use Laf\Database\Db;
use Laf\Database\PrimaryKey;
use Laf\Exception;
use Laf\Database\Table;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\Util\Settings;
use Laf\Util\Util;

class Field
{
	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var Table
	 */
	private $table;

	/**
	 * @var string
	 */
	private $value;

	/**
	 * @var string
	 */
	private $oldValue;

	/**
	 * @var int
	 */
	private $loadedOnTs = null;

	/**
	 * @var int
	 */
	private $updatedOnTs = null;

	/**
	 * @var FieldType
	 */
	private $type;

	/**
	 * @var int
	 */
	private $maxLength = 0;

	/**
	 * @var int
	 */
	private $minLength = 0;

	/**
	 * @var int
	 */
	private $minValue = null;

	/**
	 * @var int
	 */
	private $maxValue = null;

	/**
	 * @var int
	 */
	private $incrementStep = 1;

	/**
	 * @var bool
	 */
	private $required = false;

	/**
	 * @var bool
	 */
	private $unique = false;

	/**
	 * @var bool
	 */
	private $autoIncrement = false;

	/**
	 * @var string
	 */
	private $label = null;

	/**
	 * @var string
	 */
	private $placeholder = null;

	/**
	 * @var string
	 */
	private $hint = null;

	/**
	 * @var array
	 */
	private $attributes = [];

	/**
	 * @var FormElementInterface
	 */
	protected $formElement = null;

	/**
	 * @var string
	 */
	private $invalidValueErrorMessage = null;

	/**
	 * @var array
	 */
	public $dbSelectionCriteria = [];

	/**
	 * Field constructor.
	 * @param string $name
	 * @param Table $table
	 * @param string $value
	 * @param string $oldValue
	 * @param int $loadedOnTs
	 * @param int $updatedOnTs
	 * @param FieldType $type
	 * @param int $maxLength
	 * @param int $minLength
	 * @param int $incrementStep
	 * @param bool $required
	 * @param bool $autoIncrement
	 * @param string $label
	 * @param string $placeholder
	 * @param string $hint
	 * @param array $attributes
	 * @param string $invalidValueErrorMessage
	 */
	public function __construct(string $name = null, Table $table = null, string $value = null, string $oldValue = null, int $loadedOnTs = null, int $updatedOnTs = null, FieldType $type = null, int $maxLength = null, int $minLength = null, int $incrementStep = null, bool $required = null, bool $unique = null, bool $autoIncrement = null, string $label = null, string $placeholder = null, string $hint = null, array $attributes = [], string $invalidValueErrorMessage = null)
	{
		$this->name = $name;
		$this->table = $table;
		$this->value = $value;
		$this->oldValue = $oldValue;
		$this->loadedOnTs = $loadedOnTs;
		$this->updatedOnTs = $updatedOnTs;
		$this->type = $type;
		$this->maxLength = $maxLength;
		$this->minLength = $minLength;
		$this->incrementStep = $incrementStep;
		$this->required = $required;
		$this->unique = $unique;
		$this->autoIncrement = $autoIncrement;
		$this->label = $label;
		$this->placeholder = $placeholder;
		$this->hint = $hint;
		$this->attributes = $attributes;
		$this->invalidValueErrorMessage = $invalidValueErrorMessage;
	}

	/**
	 * Get field name
	 * @return mixed
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getNameRot13()
	{
		return str_rot13($this->getName());
	}

	/**
	 * Set field name
	 * @param mixed $name
	 * @return Field
	 */
	public function setName($name): Field
	{
		$this->name = $name;
		return $this;
	}

	/**
	 * Set table ref
	 * @return Table
	 */
	public function getTable(): Table
	{
		return $this->table;
	}

	/**
	 * Get table ref
	 * @param Table $table
	 * @return Field
	 */
	public function setTable(Table $table): Field
	{
		$this->table = $table;
		return $this;
	}

	/**
	 * Get field value
	 * @return mixed
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * Get field value with nl2br
	 * @return mixed
	 */
	public function getValueNl2Br()
	{
		return nl2br($this->value);
	}

	/**
	 * Returns the value formatted
	 * for db insert
	 * @return string
	 */
	public function getValueForDbInsert()
	{
		return $this->getType()->formatForDb($this->getValue());
	}

	/**
	 * Set field value
	 * @param mixed $value
	 * @return Field
	 * @throws Exception\InvalidValueException
	 */
	public function setValue($value): Field
	{

        /**
         * for float/double/numeric types, try to parse the numbers with different formats, such as ##,###.## ##.###,## ###,## ###.##
         */
        if(get_class($this->getType()) == TypeFloat::class && str_contains($value, ',')){
            $value = Util::toFloat($value);
        }

		if ($this->type->isValid($value)) {
			$tmpVal = $this->sanitize($value);
			if ($this->value !== $tmpVal) {
				$this->value = $tmpVal;
				$this->setUpdatedOnTs();
			}
		} else {
			throw new Exception\InvalidValueException(sprintf('Invalid Value for type:%s and field: %s:%s ', get_class($this->getType()), $this->getTableName(), $this->getName()));
		}
		return $this;
	}

	/**
	 * Load value from DB, sets value and oldValue
	 * Bypasses all validation
	 * @param $value
	 * @return Field
	 */
	public function loadValueFromDb($value): Field
	{
		$this->value = $value;
		$this->oldValue = $value;
		$this->setLoadedOnTs(time());
		return $this;
	}

	/**
	 * Set a raw unfiltered value
	 * @param $value
	 * @return Field
	 */
	public function setValueRaw($value): Field
	{
		if ($this->value != $value) {
			$this->value = $value;
			$this->setUpdatedOnTs();
		}
		return $this;
	}

	/**
	 * Set HTML Value
	 * @param $value
	 * @return Field
	 */
	public function setValueHTML($value): Field
	{
		if ($this->value != $value) {
			$this->value = $value;
			#@TODO $this->value = $this->sanitizeHTML($value);
			$this->setUpdatedOnTs();
		}
		return $this;
	}

	/**
	 * Returns the updated time
	 * @return float
	 */
	public function getUpdatedOnTs()
	{
		return $this->updatedOnTs;
	}

	/**
	 * Sets the update time to current floating point microtime
	 * @return Field
	 */
	public function setUpdatedOnTs(): Field
	{
		$this->updatedOnTs = time();
		return $this;
	}

	/**
	 * @param $timestamp
	 * @return Field
	 */
	public function setLoadedOnTs($timestamp): Field
	{
		$this->loadedOnTs = $timestamp;
		return $this;
	}

	/**
	 * Set old value
	 * @return mixed
	 */
	public function getOldValue()
	{
		return $this->oldValue;
	}

	/**
	 * Get old value
	 * @param mixed $oldValue
	 * @return Field
	 */
	public function setOldValue($oldValue): Field
	{
		$this->oldValue = $oldValue;
		return $this;
	}

	/**
	 * Checks if field value has changed
	 * @return bool
	 */
	public function hasChanged()
	{
		return $this->getOldValue() != $this->getValue();
	}

	/**
	 * Get field type
	 * @return FieldType
	 */
	public function getType(): FieldType
	{
		return $this->type;
	}

	/**
	 * Set field type
	 * @param mixed $type
	 * @return Field
	 */
	public function setType(FieldType $type): Field
	{
		$this->type = $type;
		return $this;
	}

	/**
	 * Get field length
	 * @return int $length
	 */
	public function getLength(): ?int
	{
		return mb_strlen($this->getValue());
	}

	/**
	 * Is required?
	 * @return bool
	 */
	public function isRequired(): bool
	{
		return $this->required;
	}

	/**
	 * Set required
	 * @param bool $required
	 * @return Field
	 */
	public function setRequired(bool $required): Field
	{
		$this->required = $required;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function isUnique(): bool
	{
		return $this->unique;
	}

	/**
	 * @param bool $unique
	 * @return Field
	 */
	public function setUnique(bool $unique): Field
	{
		$this->unique = $unique;
		return $this;
	}


	/**
	 * Is autoincrement?
	 * @return bool
	 */
	public function isAutoIncrement(): bool
	{
		return $this->autoIncrement;
	}

	/**
	 * Set autoincrement
	 * @param bool $autoIncrement
	 * @return Field
	 */
	public function setAutoIncrement(bool $autoIncrement): Field
	{
		$this->autoIncrement = $autoIncrement;
		return $this;
	}

	/**
	 * Return PrimaryKey
	 * @return PrimaryKey
	 */
	public function getPrimaryKey(): PrimaryKey
	{
		return $this->getTable()->getPrimaryKey();
	}

	/**
	 * Is primary key
	 * @return bool
	 */
	public function isPrimaryKey(): bool
	{
		return $this->getTable()->getPrimaryKey()->hasField($this->getName());
	}

	/**
	 * Is a foreign key
	 * @return bool
	 */
	public function isForeignKey(): bool
	{
		return $this->getTable()->isForeignKey($this->getName());
	}

	/**
	 * Get label
	 * @return string
	 */
	public function getLabel()
	{
		return $this->label;
	}

	/**
	 * Set Label
	 * @param string $label
	 * @return Field
	 */
	public function setLabel($label): Field
	{
		$this->label = $label;
		return $this;
	}

	/**
	 * Get placeholder
	 * @return string
	 */
	public function getPlaceholder()
	{
		return $this->placeholder;
	}

	/**
	 * Get placeholder
	 * @param string $placeholder
	 * @return Field
	 */
	public function setPlaceholder($placeholder): Field
	{
		$this->placeholder = $placeholder;
		return $this;
	}

	/**
	 * Get hint
	 * @return string
	 */
	public function getHint()
	{
		return $this->hint;
	}

	/**
	 * Set hint
	 * @param string $hint
	 * @return Field
	 */
	public function setHint($hint): Field
	{
		$this->hint = $hint;
		return $this;
	}

	/**
	 * Set invalid message
	 * @return string
	 */
	public function getInvalidValueErrorMessage()
	{
		return $this->invalidValueErrorMessage;
	}

	/**
	 * Get invalid message
	 * @param string $invalidValueErrorMessage
	 * @return Field
	 */
	public function setInvalidValueErrorMessage($invalidValueErrorMessage): Field
	{
		$this->invalidValueErrorMessage = $invalidValueErrorMessage;
		return $this;
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return $this->getValue();
	}

	/**
	 * Will return the value of the foreign-key pointer, if exists
	 * Otherwise will return value
	 */
	public function getReferencedValue()
	{
		$settings = Settings::getInstance();
		if ($this->isForeignKey()) {
			$field = $this->getTable()->getField($this->getName());
			$foreignKey = $this->getTable()->getForeignKey($this->getName());

			$referencedTable = new  Table($foreignKey->getReferencingTable());
			$referencedObjectName = "\\" . $settings->getProperty('project.package_name') . "\\" . $referencedTable->getNameAsClassname();
			$referencedTableObject = new $referencedObjectName($this->getValue());
			$referencedValue = $referencedTableObject->getTable()->getDisplayField()->getValue();
			return $referencedValue;
		} else {
			return $this->getValue();
		}
	}

	/**
	 * Get table name
	 * @return string table name
	 */
	public function getTableName()
	{
		return $this->getTable()->getName();
	}

	/**
	 * Will return Library name that corresponds to table name
	 * @return string
	 */
	public function getClassName()
	{
		return Db::convertTableNameToClassName($this->getTableName());
	}

	/**
	 *
	 */
	public function isValidValue()
	{
		#@TODO
	}

	/**
	 * Cleanup value
	 * @param $value
	 * @return mixed
	 */
	public function sanitize($value)
	{
		return $this->getType()->getValueDbSanitized($value);
	}

	/**
	 * Get all HTML tag attributes
	 * @return array
	 */
	public function getAttributes(): array
	{
		return $this->attributes;
	}

	/**
	 * @param $key
	 * @return string
	 */
	public function getAttribute($key)
	{
		if (array_key_exists($key, $this->getAttributes()))
			return $this->getAttributes()[$key];
		else
			return null;
	}

	/**
	 * Set HTML tag attribute
	 * @param string $key
	 * @param string $value
	 * @return Field
	 */
	public function setAttribute($key, $value): Field
	{
		$this->attributes[$key] = $value;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getMaxLength(): ?int
	{
		return $this->maxLength;
	}

	/**
	 * @param int $maxLength
	 * @return Field
	 */
	public function setMaxLength(int $maxLength): Field
	{
		$this->maxLength = $maxLength;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getMinLength(): ?int
	{
		return $this->minLength;
	}

	/**
	 * @param int $minLength
	 * @return Field
	 */
	public function setMinLength(int $minLength): Field
	{
		$this->minLength = $minLength;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getIncrementStep(): ?int
	{
		return $this->incrementStep;
	}

	/**
	 * @param int $incrementStep
	 * @return Field
	 */
	public function setIncrementStep(int $incrementStep): Field
	{
		$this->incrementStep = $incrementStep;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getMinValue(): ?int
	{
		return $this->minValue;
	}

	/**
	 * @param int $minValue
	 * @return Field
	 */
	public function setMinValue(int $minValue)
	{
		$this->minValue = $minValue;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getMaxValue(): ?int
	{
		return $this->maxValue;
	}

	/**
	 * @param int $maxValue
	 * @return Field
	 */
	public function setMaxValue(int $maxValue)
	{
		$this->maxValue = $maxValue;
		return $this;
	}

	/**
	 * Set this field as display field
	 * @return Field
	 */
	public function setTableDisplayField()
	{
		$this->getTable()->setDisplayFieldName($this->getName());
		return $this;
	}

	/**
	 * @param FormElementInterface|null $formElementOverride
	 * @return FormElementInterface
	 */
	public function getFormElement(FormElementInterface $formElementOverride = null): ComponentInterface
	{
		if (is_object($formElementOverride) && $formElementOverride != $this->formElement) {
			$this->formElement = $formElementOverride;
			$this->formElement->setField($this);
		}

		if (is_object($this->formElement))
			return $this->formElement;

		if (is_object($formElementOverride)) {
			$this->formElement = $formElementOverride;
			$this->formElement->setField($this);
		} else {
			$this->formElement = $this->getType()->getFormElement($this);
		}
		return $this->formElement;
	}

	/**
	 * Form elements with it's value are cached
	 * This allows clearing cache so it can be reloaded
	 */
	public function clearFormElementCache()
	{
		$this->formElement = null;
	}

	/**
	 * Check if the field points to the documents table and should have a document
	 * @return bool
	 */
	public function isDocumentField()
	{
		if ($this->getTable()->getForeignKey($this->getName())) {
			return $this->getTable()->getForeignKey($this->getName())->getReferencingTable() == 'document';
		}
		return false;
	}

	/**
	 * Get the criteria to select rows from DB
	 * Used primarly for Select outputs and FK fields
	 * @return array
	 */
	public function getDbSelectionCriteria(): array
	{
		return $this->dbSelectionCriteria;
	}

	/**
	 * Add a criteria to select rows from DB
	 * Used primarly for Select outputs and FK fields
	 * @param array $dbSelectionCriteria
	 * @return Field
	 */
	public function setDbSelectionCriteria(array $dbSelectionCriteria): Field
	{
		$this->dbSelectionCriteria = $dbSelectionCriteria;
		return $this;
	}

	/**
	 * Add a criteria to select rows from DB
	 * Used primarly for Select outputs and FK fields
	 * @param $key
	 * @param $value
	 */
	public function addDbSelectionCriteria($key, $value)
	{
		$this->dbSelectionCriteria[$key] = $value;
	}

	/**
	 * Check if the field has selection criteria
	 * @return bool
	 */
	public function hasDbSelectionCriteria()
	{
		return sizeof($this->dbSelectionCriteria) > 0;
	}


}