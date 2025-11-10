<?php

namespace Laf\Generator;

abstract class AbstractTableInspector implements TableInspectorInterface
{
    /**
     * @var string
     */
    protected $table = null;

    /**
     * @var array[]
     */
    protected $columns = [];

    /**
     * @var string|null
     */
    protected $primaryColumnName = null;

    /**
     * @var bool
     */
    protected $hasForeignKeys = false;

    /**
     * @var array
     */
    protected $referencingTables = [];


    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function hasForeignKeys(): bool
    {
        return $this->hasForeignKeys;
    }

    public function hasReferencingTables(): bool
    {
        return count($this->referencingTables) > 0;
    }

    public function getReferencingTables(): array
    {
        return $this->referencingTables;
    }

    public function getPrimaryColumnName()
    {
        return $this->primaryColumnName;
    }

    public function setPrimaryColumnName($primaryColumnName): TableInspectorInterface
    {
        $this->primaryColumnName = $primaryColumnName;
        return $this;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function setTable(string $table): TableInspectorInterface
    {
        $this->table = $table;
        return $this;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function setColumns(array $columns): TableInspectorInterface
    {
        $this->columns = $columns;
        return $this;
    }

    public function inspect(): void
    {
        $this->populateColumnsData();
        $this->populateForeignKeyData();
        $this->populateReferencingTables();
    }

    public function getDisplayColumnName(): string
    {
        if (array_key_exists('label', $this->getColumns())) {
            return 'label';
        }
        if (array_key_exists('name', aarsort($this->getColumns()))) {
            return 'name';
        }

        $cols = $this->getColumns();
        $first = array_shift($cols);//discard PK
        if (count($cols) > 0) {
            $second = array_shift($cols);
            return $second['COLUMN_NAME'];
        }
        // Fallback to the primary key if no other columns exist
        return $this->getPrimaryColumnName();
    }

    /**
     * @inheritdoc
     */
    public function getJunctionTableInfo(): ?array
    {
        $foreignKeys = [];
        $primaryKeys = [];

        foreach ($this->getColumns() as $column) {
            if (isset($column['FOREIGN_KEY'])) {
                $foreignKeys[$column['COLUMN_NAME']] = $column['FOREIGN_KEY'];
            }
            if ($column['COLUMN_KEY'] === 'PRI') {
                $primaryKeys[] = $column['COLUMN_NAME'];
            }
        }

        // A junction table typically has 2 foreign keys which are also the composite primary key
        if (count($foreignKeys) === 2 && count($primaryKeys) === 2) {
            $fkNames = array_keys($foreignKeys);
            sort($fkNames);
            sort($primaryKeys);

            if ($fkNames === $primaryKeys) {
                return [
                    'is_junction' => true,
                    'foreign_keys' => $foreignKeys,
                ];
            }
        }

        return null;
    }
}