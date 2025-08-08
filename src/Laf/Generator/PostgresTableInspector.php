<?php

namespace Laf\Generator;

use Laf\Database\Db;
use Laf\Exception\MissingConfigParamException;
use Laf\Util\Settings;

class PostgresTableInspector implements TableInspectorInterface
{
    /**
     * @var string
     */
    private $table = null;

    /**
     * @var array[]
     */
    private $columns = [];

    /**
     * @var null
     */
    private $primaryColumnName = null;

    /**
     * @var bool
     */
    private $hasForeignKeys = false;

    /**
     * @var array
     */
    private $referencingTables = [];


    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * @return bool
     */
    public function hasForeignKeys(): bool
    {
        return $this->hasForeignKeys;
    }

    /**
     * @return bool
     */
    public function hasReferencingTables(): bool
    {
        return count($this->referencingTables) > 0;
    }

    /**
     * @return array
     */
    public function getReferencingTables(): array
    {
        return $this->referencingTables;
    }


    /**
     * @return null
     */
    public function getPrimaryColumnName()
    {
        return $this->primaryColumnName;
    }

    /**
     * @param null $primaryColumnName
     * @return TableInspector
     */
    public function setPrimaryColumnName($primaryColumnName)
    {
        $this->primaryColumnName = $primaryColumnName;
        return $this;
    }

    /**
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @param string $table
     * @return TableInspector
     */
    public function setTable(string $table): TableInspectorInterface
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @return array[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @param array[] $columns
     * @return TableInspector
     */
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

    /**
     * @throws MissingConfigParamException
     */
    private function populateColumnsData()
    {
        $db = Db::getInstance();
        $settings = Settings::getInstance();
        $sql = "
        SELECT
            c.*,
            (
                SELECT 1
                FROM information_schema.table_constraints AS tc
                JOIN information_schema.key_column_usage AS kcu
                    ON tc.constraint_name = kcu.constraint_name AND tc.constraint_schema = kcu.constraint_schema
                WHERE
                    tc.constraint_type = 'PRIMARY KEY'
                    AND tc.table_name = c.table_name
                    AND tc.table_schema = c.table_schema
                    AND kcu.column_name = c.column_name
            ) AS is_primary
        FROM
            information_schema.columns AS c
        WHERE
            c.table_schema = 'public'
            AND c.table_name = '{$this->getTable()}'
        ORDER BY
            c.ordinal_position;
        ";

        $q = $db->query($sql);
        while ($col = $q->fetch(\PDO::FETCH_ASSOC)) {
            $this->columns[$col['column_name']] = $col;
            if ($col['is_primary'] == '1') {
                $this->setPrimaryColumnName($col['column_name']);
            }
        }
    }

    /**
     * @throws MissingConfigParamException
     */
    private function populateForeignKeyData()
    {
        $db = DB::getInstance();
        $settings = Settings::getInstance();

        $sql = "
        SELECT
            kcu.column_name,
            ccu.table_name AS referenced_table_name,
            ccu.column_name AS referenced_column_name,
            kcu.constraint_name
        FROM information_schema.table_constraints AS tc
        JOIN information_schema.key_column_usage AS kcu ON tc.constraint_name = kcu.constraint_name AND tc.table_schema = kcu.table_schema
        JOIN information_schema.constraint_column_usage AS ccu ON tc.constraint_name = ccu.constraint_name AND tc.constraint_schema = ccu.constraint_schema
        WHERE
            tc.constraint_type = 'FOREIGN KEY'
            AND tc.table_schema = 'public'
            AND tc.table_name = '{$this->getTable()}';
		";
        $res = $db->query($sql);
        while ($r = $res->fetchObject()) {

            $this->hasForeignKeys = true;

            $this->columns[$r->column_name]['FOREIGN_KEY'] = [
                'column_name' => $r->column_name,
                'constraint_name' => $r->constraint_name,
                'referenced_table_name' => $r->referenced_table_name,
                'referenced_column_name' => $r->referenced_column_name,
            ];
        }
    }

    /**
     * @return string
     */
    public function getDisplayColumnName(): string
    {
        if (array_key_exists('label', $this->getColumns())) {
            return 'label';
        }
        if (array_key_exists('name', $this->getColumns())) {
            return 'name';
        }

        $cols = $this->getColumns();
        $first = array_shift($cols);//discard
        $second = array_shift($cols);
        return $second['COLUMN_NAME'];
    }

    /**
     * @throws MissingConfigParamException
     */
    public function populateReferencingTables(): void
    {
        $db = DB::getInstance();
        $settings = Settings::getInstance();

        $sql = "
        
        SELECT
            DISTINCT tc.table_name
        FROM information_schema.table_constraints AS tc
        JOIN information_schema.constraint_column_usage AS ccu  ON tc.constraint_name = ccu.constraint_name AND tc.constraint_schema = ccu.constraint_schema
        WHERE
            tc.constraint_type = 'FOREIGN KEY'
            AND tc.table_schema = 'public' -- Or your specific schema
            AND ccu.table_name = '{$this->getTable()}'
            AND ccu.column_name = '{$this->primaryColumnName}';

		";
        $this->referencingTables = Db::getAllAssoc($sql);
    }

}
