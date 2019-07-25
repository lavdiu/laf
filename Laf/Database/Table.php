<?php

namespace Laf\Database;

use Laf\Util\Util;
use Laf\Database\Field\Field;

/**
 * Class Table
 */
class Table
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string[]
     */
    private $fields = [];

    /**
     * @var PrimaryKey
     */
    private $primaryKey = null;

    /**
     * @var ForeignKey[]
     */
    private $foreignKey = [];

    /**
     * @var string
     */
    private $displayField = null;

    /**
     * Table constructor.
     * @param $name
     * @param array $fields
     * @param PrimaryKey $primaryKey
     * @param string[] $foreignKey
     * @param Field $displayField
     */
    public function __construct($name = null, array $fields = null, PrimaryKey $primaryKey = null, array $foreignKey = null, ?Field $displayField = null)
    {
        $this->name = $name;
        $this->fields = $fields;
        $this->primaryKey = $primaryKey;
        $this->foreignKey = $foreignKey;
        $this->displayField = $displayField;
    }

    /**
     * Set Table Name
     * @param mixed $name
     * @return Table
     */
    public function setName($name): Table
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get Table Name
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get Table name in Rot13
     * @return string
     */
    public function getNameRot13()
    {
        return ($this->getName());
    }

    public function getNameAsClassname()
    {
        return Util::tableNameToClassName($this->getName());
    }

    /**
     * Add Field to the table
     * @param Field $field
     * @return Table
     */
    public function addField(Field $field)
    {
        $this->setField($field);
        return $this;
    }

    /**
     * Set a specific field in the table
     * @param Field $field
     * @return Table
     */
    public function setField(Field $field): Table
    {
        $field->setTable($this);
        $this->fields[$field->getName()] = $field;
        return $this;
    }

    /**
     * Get table field by name
     * @param string $fieldName
     * @return Field
     */
    public function getField(?string $fieldName)
    {
        if (isset($this->fields[$fieldName])) {
            return $this->fields[$fieldName];
        } else {
            return null;
        }
    }

    /**
     * Check if field exists in the table
     * @param string $fieldName
     * @return bool
     */
    public function hasField(string $fieldName)
    {
        if (isset($this->fields[$fieldName])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get all table fields
     * @return Field[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Returns number of fields per table
     * @return int
     */
    public function getFieldCount()
    {
        return sizeof($this->fields);
    }

    /**
     * Set primary key
     * @param PrimaryKey $key
     * @return Table
     */
    public function setPrimaryKey(PrimaryKey $key): Table
    {
        $key->setTable($this);
        $this->primaryKey = $key;
        return $this;
    }

    /**
     * Get table Primary Key
     * @return PrimaryKey
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * Return number of primary keys per table
     * @return int
     */
    public function getPrimaryKeyCount(){
        return sizeof($this->getPrimaryKey()->getFields());
    }

    /**
     * Check by fieldname is a field is primary key
     * @param $fieldName
     * @return bool
     */
    public function isPrimaryKey($fieldName)
    {
        if ($this->getPrimaryKey()->hasField($fieldName)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Add Table Foreign Key
     * @param ForeignKey $key
     * @return Table
     */
    public function addForeignKey(ForeignKey $key)
    {
        $key->setTable($this);
        $this->foreignKey[$key->getField()->getName()] = $key;
        return $this;
    }

    /**
     * Get table foreign key
     * @param string $name
     * @return ForeignKey
     */
    public function getForeignKey(string $name)
    {
        if (isset($this->foreignKey[$name])) {
            return $this->foreignKey[$name];
        } else {
            return null;
        }
    }

    /**
     * Check if a field is a foreign key
     * @param string $name
     * @return bool
     */
    public function isForeignKey(string $name)
    {
        return isset($this->foreignKey[$name]);
    }

    /**
     * Get all foreign keys
     * @return array
     */
    public function getForeignKeys()
    {
        return $this->foreignKey;
    }

    /**
     * Set display field name
     * @param Field $displayField
     * @return Table
     */
    public function setDisplayField(?Field $displayField): Table
    {
        $this->displayField = $displayField;
        return $this;
    }

    /**
     * Returns the instance of displayField
     * @return Field
     */
    public function getDisplayField(): ?Field
    {
        if (is_object($this->displayField))
            return $this->displayField;
        else {
            $keys = array_keys($this->fields);
            if(array_key_exists(1, $keys))
                return $this->fields[$keys[1]];
            else if(array_key_exists(0, $keys))
                return $this->fields[$keys[0]];
        }
        return null;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getName();
    }


}