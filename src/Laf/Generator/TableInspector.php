<?php


namespace Laf\Generator;


use Laf\Database\Db;
use Laf\Exception\MissingConfigParamException;
use Laf\Util\Settings;

class TableInspector extends AbstractTableInspector
{
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
