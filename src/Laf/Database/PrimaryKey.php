<?php

namespace Laf\Database;

use Laf\Database\Field\Field;

/**
 * Class PrimaryKey
 */
class PrimaryKey
{
    private $fields = [];
    private $table = null;

    /**
     * PrimaryKey constructor.
     * @param array $fields
     * @param null $table
     */
    public function __construct(array $fields = null, $table = null)
    {
        $this->fields = $fields;
        $this->table = $table;
    }

    /**
     * @param Field $field
     * @return PrimaryKey
     */
    public function addField(Field $field)
    {
        $this->fields[] = $field;
        return $this;
    }

    /**
     * @return Field[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return Field
     */
    public function getFirstField()
    {
        if (isset($this->fields[0])) {
            return $this->fields[0];
        } else {
            return null;
        }
    }

    /**
     * @return bool
     */
    public function hasMoreThanOneField()
    {
        return count($this->fields) > 1;
    }

    public function hasField(string $fieldName)
    {
        foreach ($this->getFields() as $f) {
            if ($f->getName() == $fieldName)
                return true;
        }
        return false;
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
     */
    public function setTable(Table $table): void
    {
        $this->table = $table;
    }
}