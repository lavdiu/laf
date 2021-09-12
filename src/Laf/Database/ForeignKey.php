<?php

namespace Laf\Database;

use Laf\Database\Field\Field;

/**
 * Class ForeignKey
 */
class ForeignKey
{
    /**
     * @var mixed|null
     */
    private $keyName;

    /**
     * @var Table|null
     */
    private $table;

    /**
     * @var Field|null
     */
    private $field;

    /**
     * @var string|null
     */
    private $referencingTable;

    /**
     * @var
     */
    private $referencingField;

    /**
     * ForeignKey constructor.
     * @param null $keyName
     * @param Table|null $table
     * @param Field|null $field
     * @param string|null $referencingTable
     */
    public function __construct($keyName = null, Table $table = null, Field $field = null, string $referencingTable = null)
    {
        $this->keyName = $keyName;
        $this->table = $table;
        $this->field = $field;
        $this->referencingTable = $referencingTable;
    }

    /**
     * @return string
     */
    public function getKeyName()
    {
        return $this->keyName;
    }

    /**
     * @param string $keyName
     * @return ForeignKey
     */
    public function setKeyName($keyName)
    {
        $this->keyName = $keyName;
        return $this;
    }

    /**
     * @return Table
     */
    public function getTable(): Table
    {
        return $this->table;
    }

    /**
     * @param Table $table
     * @return ForeignKey
     */
    public function setTable(Table $table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @return Field
     */
    public function getField(): Field
    {
        return $this->field;
    }

    /**
     * @param Field $field
     * @return ForeignKey
     */
    public function setField(Field $field)
    {
        $this->field = $field;
        return $this;
    }

    /**
     * @return string
     */
    public function getReferencingTable()
    {
        return $this->referencingTable;
    }

    /**
     * @param string $referencingTable
     * @return ForeignKey
     */
    public function setReferencingTable(string $referencingTable)
    {
        $this->referencingTable = $referencingTable;
        return $this;
    }

    /**
     * @return string
     */
    public function getReferencingField()
    {
        return $this->referencingField;
    }

    /**
     * @param string $referencingField
     * @return ForeignKey
     */
    public function setReferencingField($referencingField): ForeignKey
    {
        $this->referencingField = $referencingField;
        return $this;
    }

    public function isValidValue($id)
    {
        $id = trim($id);
        if ($id == '' || is_null($id))
            return true;
        $db = Db::getInstance();
        $stmt = $db->query("SELECT 1 AS value_found FROM {$this->getReferencingTable()} WHERE {$this->getReferencingField()}=" . ((int)$id));
        $result = $stmt->fetchObject();
        if ($result->value_found == 1) {
            return true;
        } else {
            return false;
        }

    }

}