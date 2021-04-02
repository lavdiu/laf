<?php


namespace Laf\Generator;


use Laf\Database\Db;
use Laf\Exception\MissingConfigParamException;
use Laf\Util\Settings;

class TableInspector
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
        $this->inspect();
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
    public function setTable(string $table): TableInspector
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
    public function setColumns(array $columns): TableInspector
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
        SELECT *
        FROM information_schema.columns
        WHERE
            table_schema = '{$settings->getProperty('database.database_name')}'
            AND table_name='{$this->getTable()}'
        ORDER BY table_name, ordinal_position
        ";

        $q = $db->query($sql);
        while ($col = $q->fetch(\PDO::FETCH_ASSOC)) {
            $this->columns[$col['COLUMN_NAME']] = $col;
            if ($col['COLUMN_KEY'] == 'PRI') {
                $this->setPrimaryColumnName($col['COLUMN_NAME']);
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
			column_name,
			referenced_table_name,
			referenced_column_name,
			constraint_name
		FROM
			information_schema.key_column_usage
		WHERE
			table_name = '{$this->getTable()}'
				AND CONSTRAINT_SCHEMA='{$settings->getProperty('database.database_name')}'
				AND referenced_table_name IS NOT NULL
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
            DISTINCT TABLE_NAME
        FROM
          information_schema.KEY_COLUMN_USAGE
        WHERE
          REFERENCED_TABLE_NAME = '{$this->getTable()}'
          AND REFERENCED_COLUMN_NAME = '{$this->primaryColumnName}'
          AND TABLE_SCHEMA = '{$settings->getProperty('database.database_name')}'
		";
        $this->referencingTables = Db::getAllAssoc($sql);
    }

}