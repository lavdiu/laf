<?php

namespace Laf\Generator;

use Laf\Database\Field\FieldTypeFactory;
use Laf\Database\Table;
use Laf\Database\Field\Field;
use Laf\Database\Db;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\Util\Settings;
use Laf\Util\Util;

class TableGenerator
{

    /**
     * @var Table $table
     */
    private $table;

    private $baseClassFile = null;
    private $classFile = null;

    private $foreignKeys = [];

    /**
     * @var string[]
     *
     * example
     * [
     *  'namespace'         =>  'namespace;',
     *  'base_class_dir'    =>  '/path/to/write/files,
     *  'class_dir'         =>  '/path/to/class/dir',
     *  'rewrite_class'        =>    1
     * ]
     * no trailing slashes at the end
     */
    private $config = [];

    /**
     * Table constructor.
     * @param Table $table
     * @param string[] $config
     */
    public function __construct(Table $table, array $config)
    {
        $this->table = $table;
        $this->config = $config;
        $this->populateForeignKeys();
    }

    /**
     * @return TableGenerator
     */
    public function processBaseClass()
    {
        $file = "<?php

namespace {$this->config['namespace']}\\Base;

use Laf\Database;
use Laf\Database\Table;
use Laf\Database\Field\Field;
use Laf\Database\PrimaryKey;
use Laf\Database\ForeignKey;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\ComponentInterface;
use {$this->config['namespace']}\\".$this->getTable()->getNameAsClassname().";
use Laf\Exception\InvalidForeignKeyValue;

/**
 * Class Base{$this->getTable()->getNameAsClassname()}
 * @package {$this->config['namespace']}\Base
 * Base Class for Table {$this->getTable()->getName()}
 * Basic definition of the fields and relationship with the Database Table {$this->getTable()->getName()}
 * This class will be auto-generated every time there is a schema change
 * Please do not add code here. Instead add your code at the main class one directory above
 */
class Base{$this->getTable()->getNameAsClassname()} extends Database\BaseObject
{
	/**
	 * Instructors constructor.
	 * @param int \$id
	 */
	public function __construct(\$id = null)
	{
		parent::__construct(\$id);
		\$this->buildClass();
		\$this->setRecordId(\$id);
		if (is_numeric(\$id)) {
			self::select(\$id);
		}
	}

	/**
	 * Select the record by primary key
	 * @param int \$id
	 * @return bool
	 */
	public function select(\$id) : bool
	{
		\$this->setRecordId(\$id);
		return parent::select(\$id);
	}

	/**
	 * Build the class  properties and link it to the db table
	 *
	 */
	private function buildClass()
	{
		\$this->setTable(new Table('{$this->getTable()->getName()}'));";
        $file .= $this->generateFields();
        $file .= $this->generateForeignKeys();
        $file .= "
	}
";
        foreach ($this->getTableColumns() as $column) {
            $file .= "
	/**
	 * Set " . Util::tableFieldNameToLabel($column['COLUMN_NAME']) . " value
	 * @param mixed \$value
	 * @return {$this->getTable()->getNameAsClassname()}
	 * @throws InvalidForeignKeyValue
	 */
	public function set" . Util::tableFieldNameToMethodName($column['COLUMN_NAME']) . "Val(\$value = null)
	{
		\$this->setFieldValue(\"{$column['COLUMN_NAME']}\", \$value);
		return static::returnLeafClass();
	}

	/**
	 * Get " . Util::tableFieldNameToLabel($column['COLUMN_NAME']) . " value
	 * @return mixed
	 */
	public function get" . Util::tableFieldNameToMethodName($column['COLUMN_NAME']) . "Val()
	{
		return \$this->getFieldValue(\"{$column['COLUMN_NAME']}\");
	}

	/**
	 * Get " . Util::tableFieldNameToLabel($column['COLUMN_NAME']) . " field reference
	 * @return Field
	 */
	public function get" . Util::tableFieldNameToMethodName($column['COLUMN_NAME']) . "Fld()
	{
		return \$this->getField(\"{$column['COLUMN_NAME']}\");
	}

	/**
	 * Get " . Util::tableFieldNameToLabel($column['COLUMN_NAME']) . " form element reference
	 * @param FormElementInterface|null \$formElementOverride
	 * @return ComponentInterface
	 */
	public function get" . Util::tableFieldNameToMethodName($column['COLUMN_NAME']) . "FormElement(FormElementInterface \$formElementOverride = null) : ComponentInterface
	{
		return \$this->getField(\"{$column['COLUMN_NAME']}\")->getFormElement(\$formElementOverride);
	}
";
            if ($this->getFkTable($column['COLUMN_NAME']) != '') {
                $file .= "
	/**
	 * Get ".Util::tableNameToClassName($this->getFkTable($column['COLUMN_NAME']))." Object
	 * @return \\{$this->config['namespace']}\\".Util::tableNameToClassName($this->getFkTable($column['COLUMN_NAME']))."
	 */
	public function get" . Util::tableFieldNameToMethodName($column['COLUMN_NAME']) . "Obj()
	{
		if (is_numeric(\$this->get" . Util::tableFieldNameToMethodName($column['COLUMN_NAME']) . "Val())) {
			return new \\{$this->config['namespace']}\\" . Util::tableNameToClassName($this->getFkTable($column['COLUMN_NAME'])) . "(\$this->get" . Util::tableFieldNameToMethodName($column['COLUMN_NAME']) . "Val());
		} else {
			return new \\{$this->config['namespace']}\\" . Util::tableNameToClassName($this->getFkTable($column['COLUMN_NAME'])) . "();
		}
	}
";
            }
        }

        $file .= "
\t/**
\t * Get all rows as associative array
\t * @return string[]
\t */
\tpublic function listAllArray(): array
\t{
\t\t\$db = Database\Db::getInstance();
\t\t\$sql = \"SELECT * FROM {\$this->getTable()->getName()} ORDER BY {\$this->getTable()->getPrimaryKey()->getFirstField()->getName()} ASC\";
\t\t\$res = \$db->query(\$sql);
\t\treturn \$res->fetchAll(\PDO::FETCH_ASSOC);
\t}

\t/**
\t * Gets all rows and instantiates the object for all
\t * Then returns an array of all objects
\t * Please be careful, this can be bad for large tables
\t * @return {$this->getTable()->getNameAsClassname()}[]
\t */
\tpublic function listAllObjects(): array
\t{
\t\t\$db = Database\Db::getInstance();
\t\t\$primaryKeyField = \$this->getTable()->getPrimaryKey()->getFirstField()->getName();
\t\t\$sql = \"SELECT {\$this->getTable()->getPrimaryKey()->getFirstField()->getName()} FROM {\$this->getTable()->getName()} ORDER BY {\$primaryKeyField} ASC\";
\t\t\$res = \$db->query(\$sql);

\t\t\$objects = [];
\t\t\$allIds = \$res->fetchAll(\PDO::FETCH_ASSOC);
\t\tforeach (\$allIds as \$id) {
\t\t\t\$objects[] = new static(\$id[\$primaryKeyField]);
\t\t}
\t\treturn \$objects;
\t}

	/**
	* Return all fields as form elements
	* @return FormElementInterface[]
	*/
	public function getAllFieldsAsFormElements() : array{
		\$tmp = [];
		foreach(\$this->getTable()->getFields() as \$field){
			\$tmp[] = \$field->getFormElement();
		}
		return \$tmp;
	}
";


        $file .= "\n}\n";
        $this->setBaseClassFile($file);
        return $this;
    }


    /**
     * Generates and saves class to file
     * @return TableGenerator
     */
    public function saveBaseClassToFile()
    {
        $this->processBaseClass();
        $file = $this->getConfig()['base_class_dir'] . '/Base' . $this->getTable()->getNameAsClassname() . '.php';
        $ok = file_put_contents($file, $this->getBaseClassFile());
        return $this;
    }

    public function processClass()
    {
        $file = "<?php

namespace {$this->config['namespace']};

/**
 * Class {$this->getTable()->getNameAsClassname()}
 * @package {$this->config['namespace']}
 * Main Class for Table {$this->getTable()->getName()}
 * This class inherits functionality from Base{$this->getTable()->getNameAsClassname()}.
 * It is generated only once, please include all logic and code here
 */
class {$this->getTable()->getNameAsClassname()} extends Base\\Base{$this->getTable()->getNameAsClassname()}
{
	/**
	 * Instructors constructor.
	 * @param int \$id
	 */
	public function __construct(\$id = null)
	{
		parent::__construct(\$id);
	}

	/**
	 * Returns the lowest level class in the inheritance tree
	 * Used with late static binding to get the lowest level class
	 */
	protected function returnLeafClass()
	{
		return \$this;
	}
	
	/**
	 * Find one row by using the first result
	 * @param array \$keyValuePairs
	 * @return {$this->getTable()->getNameAsClassname()}
	 * @throws \Exception
	 */
	public function findOne(array \$keyValuePairs) : {$this->getTable()->getNameAsClassname()}
	{
		return parent::bOfindOne(\$keyValuePairs);
	}
	
	/**
	 * @param array \$keyValuePairs
	 * @return {$this->getTable()->getNameAsClassname()}[]
	 * @throws \Exception
	 */
	public function find(array \$keyValuePairs) : array
	{
		return parent::bOfind(\$keyValuePairs);
	}";
        $file .= "\n}\n";
        $this->setClassFile($file);
    }

    /**
     * Generates and saves class to file
     * @return TableGenerator
     */
    public function saveClassToFile()
    {
        $this->processClass();
        $file = $this->getConfig()['class_dir'] . '/' . $this->getTable()->getNameAsClassname() . '.php';
        if (!file_exists($file) || (isset($this->getConfig()['rewrite_class']) && $this->getConfig()['rewrite_class']))
            file_put_contents($file, $this->getClassFile());
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
     */
    public function setTable(Table $table): void
    {
        $this->table = $table;
    }

    /**
     * @return string[]
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param string[] $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * @return null
     */
    public function getBaseClassFile()
    {
        return $this->baseClassFile;
    }

    /**
     * @param null $baseClassFile
     */
    public function setBaseClassFile($baseClassFile): void
    {
        $this->baseClassFile = $baseClassFile;
    }

    /**
     * @return null
     */
    public function getClassFile()
    {
        return $this->classFile;
    }

    /**
     * @param null $classFile
     */
    public function setClassFile($classFile): void
    {
        $this->classFile = $classFile;
    }

    /**
     * Get column info for a table
     * @return array
     */
    private function getTableColumns()
    {
        $db = Db::getInstance();
        $sql = "
        SELECT *
        FROM information_schema.columns
        WHERE
            table_schema = '{$db->getDatabase()}'
            AND table_name='{$this->getTable()->getName()}'
        ORDER BY table_name, ordinal_position
        ";

        $q = $db->query($sql);
        return $q->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function populateForeignKeys()
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
			table_name = '{$this->table->getName()}'
				AND CONSTRAINT_SCHEMA='{$settings->getProperty('database.database_name')}'
				AND referenced_table_name IS NOT NULL
		";
        $res = $db->query($sql);
        while ($r = $res->fetchObject()) {
            $this->foreignKeys[$r->column_name] = [
                'column_name' => $r->column_name,
                'constraint_name' => $r->constraint_name,
                'referenced_table_name' => $r->referenced_table_name,
                'referenced_column_name' => $r->referenced_column_name,
            ];
        }
    }

    private function generateForeignKeys()
    {
        $tmp = "\n\t\t/**
		 * Generating Foreign keys
		 */";

        foreach ($this->foreignKeys as $fk) {
            $tmp .= "\n\t\t\$this->getTable()->addForeignKey(
			(new ForeignKey())
				->setField(\$this->getTable()->getField(\"{$fk['column_name']}\"))
				->setKeyName('{$fk['constraint_name']}')
				->setReferencingTable(\"{$fk['referenced_table_name']}\")
				->setReferencingField(\"{$fk['referenced_column_name']}\")
		);\n";
        }
        return $tmp;
    }

    private function generateFields()
    {
        $tmp = "\n\t\t/**
		 * Generate field data
		 */
		\$pk = new PrimaryKey();";

        foreach ($this->getTableColumns() as $column) {
            $additionalSettings = null;

            if (mb_strstr($column['COLUMN_COMMENT'], 'setLabel') === false) {
                $additionalSettings = "\n\t\t\t->setLabel(\"" . Util::tableFieldNameToLabel($column['COLUMN_NAME']) . "\")";
            }
            /*if (mb_strstr($column['COLUMN_COMMENT'], 'setHint') === false) {
                $additionalSettings .= "\n\t\t\t->setHint(\"" . Util::tableFieldNameToLabel($column['COLUMN_NAME']) . "\")";
            }*/
            if (mb_strstr($column['COLUMN_COMMENT'], 'setPlaceHolder') === false) {
                $additionalSettings .= "\n\t\t\t->setPlaceHolder(\"" . Util::tableFieldNameToLabel($column['COLUMN_NAME']) . "\")";
            }

            $tmp .= "\n\t\t\$field = (new Field())
			->setName(\"{$column['COLUMN_NAME']}\"){$additionalSettings}
			->setRequired(" . ($column['IS_NULLABLE'] == 'NO' ? 'true' : 'false') . ")
			->setMaxLength(" . (is_numeric($column['CHARACTER_MAXIMUM_LENGTH']) ? $column['CHARACTER_MAXIMUM_LENGTH'] : '0') . ")
			->setAutoIncrement(" . (strpos($column['EXTRA'], 'auto_increment') === false ? 'false' : 'true') . ")
			->setUnique(" . ($column['COLUMN_KEY'] == 'UNI' ? 'true':'false') . ")
			->setType(" . FieldTypeFactory::getClassLiteral($column['DATA_TYPE']) . ");";

            $maxLength = preg_replace("/[^0-9,.]/", "", $column['COLUMN_TYPE']);
            if (mb_strstr($maxLength, ','))
                $maxLength = array_sum(explode(',', $maxLength));

            if (is_int($maxLength)) {
                $tmp .= "\n\t\t\$field->setMaxLength({$maxLength});";
            }

            if (Util::isJSON($column['COLUMN_COMMENT'])) {
                $params = json_decode($column['COLUMN_COMMENT']);
                foreach ($params->fields as $method => $value) {
                    if (method_exists((new Field()), $method)) {
                        $tmp .= "\n\t\t\$field->$method(\"{$value}\");";
                    }else if($method == 'setDisplayField'){
                        $tmp .= "\n\t\t\$this->getTable()->setDisplayField(\$field);";
                    }

                }
            }

            if ($column['COLUMN_KEY'] == 'PRI') {
                $tmp .= "\n\t\t\$pk->addField(\$field);";
            }
	        if ($column['COLUMN_KEY'] == 'UNI') {
		        $tmp .= "\n\t\t\$this->getTable()->addUniqueField(\$field);";
	        }
            $tmp .= "\n\t\t\$this->getTable()->addField(\$field);
		\$field = null;\n";

        }

        $tmp .= "\n\t\t\$this->getTable()->setPrimaryKey(\$pk);\n";
        return $tmp;
    }

    /**
     * Given a column name, it will return the poiting table if it's a Foreign Key
     * @param $column
     * @return mixed|string
     */
    public function getFkTable($column)
    {
        if (array_key_exists($column, $this->foreignKeys)) {
            return $this->foreignKeys[$column]['referenced_table_name'];
        } else {
            return '';
        }
    }
}