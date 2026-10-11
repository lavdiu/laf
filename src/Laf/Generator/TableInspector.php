<?php


namespace Laf\Generator;


use Laf\Database\Db;
use Laf\Exception\MissingConfigParamException;
use Laf\Util\Settings;

class TableInspector implements TableInspectorInterface
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
     * @return TableInspectorInterface
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
     * @return TableInspectorInterface
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
     * @return TableInspectorInterface
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
        $this->detectMariaDbJsonColumns();
    }

    /**
     * MariaDB has no native JSON type: `JSON` is an alias for LONGTEXT plus a
     * CHECK (json_valid(col)) constraint, and information_schema reports the
     * column as longtext. Read the check constraints and re-label such columns
     * as DATA_TYPE 'json' so they get TypeJson. Harmless on MySQL 8 (native
     * json) and silently skipped on servers without CHECK_CONSTRAINTS.
     */
    private function detectMariaDbJsonColumns(): void
    {
        $settings = Settings::getInstance();
        $sql = "
        SELECT cc.CHECK_CLAUSE
        FROM information_schema.CHECK_CONSTRAINTS cc
        JOIN information_schema.TABLE_CONSTRAINTS tc
            ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
        WHERE tc.TABLE_SCHEMA = :schema
            AND tc.TABLE_NAME = :table
            AND cc.CHECK_CLAUSE LIKE '%json_valid(%'
        ";
        try {
            $stmt = Db::getInstance()->prepare($sql);
            $stmt->execute([':schema' => $settings->getProperty('database.database_name'), ':table' => $this->getTable()]);
            while (($clause = $stmt->fetchColumn()) !== false) {
                if (preg_match('/json_valid\s*\(\s*`?([A-Za-z0-9_]+)`?\s*\)/i', (string)$clause, $m) && isset($this->columns[$m[1]])) {
                    $this->columns[$m[1]]['DATA_TYPE'] = 'json';
                }
            }
        } catch (\Throwable $ex) {
            // no CHECK_CONSTRAINTS view (MySQL < 8.0.16, MariaDB < 10.2): nothing to detect
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
				kcu.referenced_table_name,
				kcu.referenced_column_name,
				kcu.constraint_name,
				rc.delete_rule,
				rc.update_rule
			FROM
				information_schema.key_column_usage kcu
				LEFT JOIN information_schema.referential_constraints rc
					ON rc.constraint_schema = kcu.constraint_schema AND rc.constraint_name = kcu.constraint_name
			WHERE
				kcu.table_name = '{$this->getTable()}'
				AND kcu.constraint_schema = '{$settings->getProperty('database.database_name')}'
				AND kcu.referenced_table_name IS NOT NULL
			";
        $res = $db->query($sql);
        while ($r = $res->fetchObject()) {

            $this->hasForeignKeys = true;

            $this->columns[$r->column_name]['FOREIGN_KEY'] = [
                'column_name' => $r->column_name,
                'constraint_name' => $r->constraint_name,
                'referenced_table_name' => $r->referenced_table_name,
                'referenced_column_name' => $r->referenced_column_name,
                'delete_rule' => $r->delete_rule ?? null,
                'update_rule' => $r->update_rule ?? null,
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
