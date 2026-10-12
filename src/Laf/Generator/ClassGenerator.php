<?php

namespace Laf\Generator;

use Laf\Database\Field\FieldTypeFactory;
use Laf\Database\Table;
use Laf\Util\Util;

/**
 * Generates the two classes of a table: Base/Base{Class}.php (fields, keys,
 * foreign keys, accessors; rewritten on every run) and {Class}.php (the model
 * the developer owns; written once unless config 'rewrite_class' is set).
 *
 * Config: namespace, base_class_dir, class_dir, rewrite_class (no trailing slashes).
 * The column metadata comes from a TableInspectorInterface (TableInspectorFactory
 * by default); JSON column comments add Field setters and form options
 * (see ColumnComment).
 */
class ClassGenerator
{
    /** Column types whose CHARACTER_MAXIMUM_LENGTH becomes Field::setMaxLength() */
    private const CHARACTER_TYPES = ['varchar', 'char', 'character varying', 'character', 'bpchar', 'citext', 'enum', 'set', 'binary', 'varbinary'];

    private Table $table;

    /** @var array<string, mixed> */
    private array $config;

    private ?TableInspectorInterface $tableInspector;

    private bool $inspected = false;

    private ?string $baseClassFile = null;

    private ?string $classFile = null;

    /** @var array<string, array<string, string|null>> column => foreign key description */
    private array $foreignKeys = [];

    /** @var string[] */
    private array $warnings = [];

    /**
     * @param array<string, mixed> $config namespace, base_class_dir, class_dir, rewrite_class
     * @param TableInspectorInterface|null $tableInspector inspected lazily; null = TableInspectorFactory::create()
     */
    public function __construct(Table $table, array $config, ?TableInspectorInterface $tableInspector = null)
    {
        $this->table = $table;
        $this->config = $config;
        $this->tableInspector = $tableInspector;
    }

    /* ───────────── base class ───────────── */

    /**
     * Build the Base class source (getBaseClassFile())
     */
    public function processBaseClass(): static
    {
        $this->warnings = [];
        $this->populateForeignKeys();

        $file = $this->renderBaseClassHeader();
        $file .= $this->generateFields();
        $file .= $this->generateForeignKeys();
        $file .= "\n\t}\n";
        foreach ($this->getTableColumns() as $column) {
            $file .= $this->renderAccessors((string)$column['COLUMN_NAME']);
        }
        $file .= $this->renderListMethods();
        $file .= "\n}\n";

        $this->setBaseClassFile($file);
        return $this;
    }

    /**
     * Build and write the Base class
     * @throws \RuntimeException when the file cannot be written
     */
    public function saveBaseClassToFile(): static
    {
        $this->processBaseClass();
        self::writeFile($this->getBaseClassFilePath(), (string)$this->getBaseClassFile());
        return $this;
    }

    private function renderBaseClassHeader(): string
    {
        $template = <<<'PHP'
<?php

namespace {ns}\Base;

use Laf\Database;
use Laf\Database\Table;
use Laf\Database\Field\Field;
use Laf\Database\PrimaryKey;
use Laf\Database\ForeignKey;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\ComponentInterface;
use {ns}\{class};
use Laf\Exception\InvalidForeignKeyValue;

/**
 * Class Base{class}
 * @package {ns}\Base
 * Base Class for Table {table}
 * Basic definition of the fields and relationship with the Database Table {table}
 * This class will be auto-generated every time there is a schema change
 * Please do not add code here. Instead add your code at the main class one directory above
 */
class Base{class} extends Database\BaseObject
{
	/**
	 * Base{class} constructor: binds the table and loads the row when a key is given
	 * @param int|string|array|null $id primary key value (name => value for a composite key)
	 */
	public function __construct($id = null)
	{
		parent::__construct($id);
		$this->buildClass();
		if ($id !== null && $id !== '') {
			self::select($id);
		}
	}

	/**
	 * Select the record by primary key
	 * @param int|string|array $id
	 * @return bool
	 * @throws \Exception
	 */
	public function select($id) : bool
	{
		$this->setRecordId($id);
		return parent::select($id);
	}

	/**
	 * Build the class properties and link it to the db table
	 */
	private function buildClass()
	{
		$this->setTable(new Table({tableLiteral}));
PHP;
        return strtr($template, [
            '{ns}' => $this->getNamespace(),
            '{class}' => $this->getTable()->getNameAsClassname(),
            '{table}' => $this->getTable()->getName(),
            '{tableLiteral}' => self::stringLiteral($this->getTable()->getName()),
        ]);
    }

    /**
     * The field definitions of buildClass() (public for compatibility; processBaseClass() calls it)
     */
    public function generateFields(): string
    {
        $tmp = "\n\t\t/**\n\t\t * Generate field data\n\t\t */\n\t\t\$pk = new PrimaryKey();";

        $columnNames = array_map('strval', array_keys($this->getTableColumns()));
        $displayField = null;
        foreach ($this->getTableColumns() as $column) {
            $name = (string)$column['COLUMN_NAME'];
            $comment = isset($column['COLUMN_COMMENT']) ? (string)$column['COLUMN_COMMENT'] : null;
            $setters = ColumnComment::fieldSetters($comment, $this->warnings);
            $typeOverride = ColumnComment::typeOverride($comment);
            $formOptions = ColumnComment::formOptions($comment, $this->warnings);
            $commentDisplayField = ColumnComment::displayField($comment, $name, $columnNames);
            if ($commentDisplayField !== null) {
                if ($displayField !== null && $displayField !== $commentDisplayField) {
                    $this->warnings[] = sprintf('table %s: several columns name a display field; "%s" wins', $this->getTable()->getName(), $commentDisplayField);
                }
                $displayField = $commentDisplayField;
            }

            $tmp .= "\n\t\t\$field = (new Field())\n\t\t\t->setName(" . self::stringLiteral($name) . ")";
            if (!isset($setters['setLabel'])) {
                $tmp .= "\n\t\t\t->setLabel(" . self::stringLiteral(Util::tableFieldNameToLabel($name)) . ")";
            }
            if (!isset($setters['setPlaceholder'])) {
                $tmp .= "\n\t\t\t->setPlaceholder(" . self::stringLiteral(Util::tableFieldNameToLabel($name)) . ")";
            }
            $tmp .= "\n\t\t\t->setRequired(" . (($column['IS_NULLABLE'] ?? 'YES') == 'NO' ? 'true' : 'false') . ")";
            // only character columns carry a meaningful limit (text/json report their storage size on MySQL)
            $maxLength = $column['CHARACTER_MAXIMUM_LENGTH'] ?? null;
            $isCharacter = in_array(strtolower((string)($column['DATA_TYPE'] ?? '')), self::CHARACTER_TYPES, true);
            if ($isCharacter && is_numeric($maxLength) && (int)$maxLength > 0) {
                $tmp .= "\n\t\t\t->setMaxLength(" . (int)$maxLength . ")";
            }
            $tmp .= "\n\t\t\t->setAutoIncrement(" . (str_contains(strtolower((string)($column['EXTRA'] ?? '')), 'auto_increment') ? 'true' : 'false') . ")";
            $tmp .= "\n\t\t\t->setUnique(" . (($column['COLUMN_KEY'] ?? '') == 'UNI' ? 'true' : 'false') . ")";
            if ($typeOverride !== null) {
                // {"fields":{"setType":"uuid"}} overrides the schema type, e.g. a MySQL char(36) holding UUIDs
                $tmp .= "\n\t\t\t->setType(" . FieldTypeFactory::getClassLiteral($typeOverride) . ");";
            } else {
                $tmp .= "\n\t\t\t->setType(" . FieldTypeFactory::getClassLiteral((string)($column['DATA_TYPE'] ?? 'text'), $column) . ");";
            }

            foreach ($setters as $method => $arguments) {
                $tmp .= "\n\t\t\$field->{$method}({$arguments});";
            }
            if ($formOptions !== null && $formOptions !== []) {
                $tmp .= "\n\t\t\$field->setFormOptions(" . self::exportArray($formOptions) . ");";
            }

            if (($column['COLUMN_KEY'] ?? '') == 'PRI') {
                $tmp .= "\n\t\t\$pk->addField(\$field);";
            }
            if (($column['COLUMN_KEY'] ?? '') == 'UNI') {
                $tmp .= "\n\t\t\$this->getTable()->addUniqueField(\$field);";
            }
            $tmp .= "\n\t\t\$this->getTable()->addField(\$field);\n\t\t\$field = null;\n";
        }

        $tmp .= "\n\t\t\$this->getTable()->setPrimaryKey(\$pk);\n";
        if ($displayField !== null) {
            $tmp .= "\t\t\$this->getTable()->setDisplayField(\$this->getTable()->getField(" . self::stringLiteral($displayField) . "));\n";
        }
        return $tmp;
    }

    /**
     * The foreign key definitions of buildClass()
     */
    public function generateForeignKeys(): string
    {
        $tmp = "\n\t\t/**\n\t\t * Generating Foreign keys\n\t\t */";

        foreach ($this->foreignKeys as $fk) {
            $rules = '';
            // only emit rules that change behaviour, so the common case stays byte-identical to older generator output
            foreach (['delete_rule' => 'setOnDelete', 'update_rule' => 'setOnUpdate'] as $key => $setter) {
                $rule = strtoupper(trim((string)($fk[$key] ?? '')));
                if ($rule !== '' && !in_array($rule, ['NO ACTION', 'RESTRICT'], true)) {
                    $rules .= "\n\t\t\t\t->{$setter}('" . addslashes($rule) . "')";
                }
            }
            $tmp .= "\n\t\t\$this->getTable()->addForeignKey(
			(new ForeignKey())
				->setField(\$this->getTable()->getField(" . self::stringLiteral((string)$fk['column_name']) . "))
				->setKeyName('" . addslashes((string)$fk['constraint_name']) . "')
				->setReferencingTable(" . self::stringLiteral((string)$fk['referenced_table_name']) . ")
				->setReferencingField(" . self::stringLiteral((string)$fk['referenced_column_name']) . "){$rules}
		);\n";
        }
        return $tmp;
    }

    /**
     * setXVal / getXVal / getXFld / getXFormElement (+ getXObj for a foreign key) of one column
     */
    private function renderAccessors(string $columnName): string
    {
        $method = Util::tableFieldNameToMethodName($columnName);
        $label = Util::tableFieldNameToLabel($columnName);
        $literal = self::stringLiteral($columnName);

        $file = "
	/**
	 * Set {$label} value
	 * @param mixed \$value
	 * @return static
	 * @throws InvalidForeignKeyValue
	 */
	public function set{$method}Val(\$value = null)
	{
		\$this->setFieldValue({$literal}, \$value);
		return static::returnLeafClass();
	}

	/**
	 * Get {$label} value
	 * @return mixed
	 */
	public function get{$method}Val()
	{
		return \$this->getFieldValue({$literal});
	}

	/**
	 * Get {$label} field reference
	 * @return Field
	 */
	public function get{$method}Fld()
	{
		return \$this->getField({$literal});
	}

	/**
	 * Get {$label} form element reference
	 * @param FormElementInterface|null \$formElementOverride
	 * @return ComponentInterface
	 */
	public function get{$method}FormElement(?FormElementInterface \$formElementOverride = null) : ComponentInterface
	{
		return \$this->getField({$literal})->getFormElement(\$formElementOverride);
	}
";
        $fkTable = $this->getFkTable($columnName);
        if ($fkTable !== '') {
            $fkClass = '\\' . $this->getNamespace() . '\\' . Util::tableNameToClassName($fkTable);
            $file .= "
	/**
	 * Get " . Util::tableNameToClassName($fkTable) . " Object (empty when the key is not set)
	 * @return {$fkClass}
	 */
	public function get{$method}Obj()
	{
		\$value = \$this->get{$method}Val();
		if (\$value !== null && \$value !== '') {
			return new {$fkClass}(\$value);
		}
		return new {$fkClass}();
	}
";
        }
        return $file;
    }

    private function renderListMethods(): string
    {
        $template = <<<'PHP'

	/**
	 * Get all rows as associative array
	 * @return string[]
	 * @throws \Exception
	 */
	public function listAllArray(): array
	{
		$query = static::query();
		foreach ($this->getTable()->getPrimaryKey()->getFieldNames() as $keyColumn) {
			$query->orderBy($keyColumn);
		}
		return $query->arrays();
	}

	/**
	 * Gets all rows and instantiates the object for all
	 * Then returns an array of all objects
	 * One SELECT, hydrated without a query per row; still every row of the table
	 * @return {class}[]
	 * @throws \Exception
	 */
	public function listAllObjects(): array
	{
		$query = static::query();
		foreach ($this->getTable()->getPrimaryKey()->getFieldNames() as $keyColumn) {
			$query->orderBy($keyColumn);
		}
		return $query->get();
	}

	/**
	* Return all fields as form elements
	* @return FormElementInterface[]
	*/
	public function getAllFieldsAsFormElements() : array{
		$tmp = [];
		foreach($this->getTable()->getFields() as $field){
			$tmp[] = $field->getFormElement();
		}
		return $tmp;
	}

PHP;
        return strtr($template, ['{class}' => $this->getTable()->getNameAsClassname()]);
    }

    /* ───────────── main class ───────────── */

    /**
     * Build the model class source (getClassFile())
     */
    public function processClass(): static
    {
        $template = <<<'PHP'
<?php

namespace {ns};

use Laf\UI\Form\DrawMode;
use Laf\UI\Form\FormLayout;

/**
 * Class {class}
 * @package {ns}
 * Main Class for Table {table}
 * This class inherits functionality from Base{class}.
 * It is generated only once, please include all logic and code here
 */
class {class} extends Base\Base{class}
{
	/**
	 * {class} constructor.
	 * @param int|string|array|null $id
	 */
	public function __construct($id = null)
	{
		parent::__construct($id);
	}

	/**
	 * Find one row by using the first result
	 * @param array $keyValuePairs
	 * @return {class}|null
	 * @throws \Exception
	 */
	public static function findOne(array $keyValuePairs) : ?{class}
	{
		return parent::bOfindOne($keyValuePairs);
	}

	/**
	 * @param array $keyValuePairs
	 * @param array $orderBy field => ASC|DESC
	 * @return {class}[]
	 * @throws \Exception
	 */
	public static function find(array $keyValuePairs = [], array $orderBy = [], ?int $limit = null, int $offset = 0) : array
	{
		return parent::bOfind($keyValuePairs, $orderBy, $limit, $offset);
	}

	/**
	 * The layout of this model's forms (null = FormLayout::auto() from the table and the
	 * JSON column comments). Uncomment and adjust to control columns, sections, tabs and
	 * which fields are excluded, read-only or hidden in each draw mode:
	 *
	 * public function getFormLayout(): ?FormLayout
	 * {
	 *     return FormLayout::auto($this->getTable())
	 *         ->readOnly(['code'], [DrawMode::UPDATE])
	 *         ->fieldset('Address', ['street', 'city'], 2);
	 * }
	 */
}

PHP;
        $this->setClassFile(strtr($template, [
            '{ns}' => $this->getNamespace(),
            '{class}' => $this->getTable()->getNameAsClassname(),
            '{table}' => $this->getTable()->getName(),
        ]));
        return $this;
    }

    /**
     * Build and write the model class, unless it exists and 'rewrite_class' is off
     * @throws \RuntimeException when the file cannot be written
     */
    public function saveClassToFile(): static
    {
        $this->processClass();
        $file = $this->getClassFilePath();
        if (!file_exists($file) || !empty($this->getConfig()['rewrite_class'])) {
            self::writeFile($file, (string)$this->getClassFile());
        }
        return $this;
    }

    /* ───────────── paths ───────────── */

    /**
     * Returns the path where the class file will be stored
     */
    public function getClassFilePath(): string
    {
        return rtrim((string)($this->getConfig()['class_dir'] ?? ''), '/\\') . '/' . $this->getTable()->getNameAsClassname() . '.php';
    }

    public function classFileExists(): bool
    {
        return file_exists($this->getClassFilePath());
    }

    /**
     * Returns the path where the base class file will be stored
     */
    public function getBaseClassFilePath(): string
    {
        return rtrim((string)($this->getConfig()['base_class_dir'] ?? ''), '/\\') . '/Base' . $this->getTable()->getNameAsClassname() . '.php';
    }

    public function baseClassFileExists(): bool
    {
        return file_exists($this->getBaseClassFilePath());
    }

    /* ───────────── accessors ───────────── */

    public function getTable(): Table
    {
        return $this->table;
    }

    public function setTable(Table $table): void
    {
        $this->table = $table;
        $this->tableInspector = null;
        $this->inspected = false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getBaseClassFile(): ?string
    {
        return $this->baseClassFile;
    }

    public function setBaseClassFile(?string $baseClassFile): void
    {
        $this->baseClassFile = $baseClassFile;
    }

    public function getClassFile(): ?string
    {
        return $this->classFile;
    }

    public function setClassFile(?string $classFile): void
    {
        $this->classFile = $classFile;
    }

    /**
     * Column metadata of the table (inspects on first use)
     * @return array<string, array<string, mixed>>
     */
    public function getTableColumns(): array
    {
        return $this->inspector()->getColumns();
    }

    /**
     * The inspector, created for the engine when none was given (inspect() runs on first use)
     */
    public function getTableInspector(): TableInspectorInterface
    {
        if ($this->tableInspector === null) {
            $this->tableInspector = TableInspectorFactory::create($this->getTable()->getName());
            $this->inspected = false;
        }
        return $this->tableInspector;
    }

    public function setTableInspector(TableInspectorInterface $tableInspector): static
    {
        $this->tableInspector = $tableInspector;
        $this->inspected = false;
        return $this;
    }

    /**
     * Messages about comment settings or types the generator could not use
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Collect the foreign keys of the inspected columns
     */
    public function populateForeignKeys(): void
    {
        $this->foreignKeys = [];
        foreach ($this->getTableColumns() as $column) {
            if (!empty($column['FOREIGN_KEY']) && is_array($column['FOREIGN_KEY'])) {
                $fk = $column['FOREIGN_KEY'];
                $this->foreignKeys[(string)$fk['column_name']] = [
                    'column_name' => (string)$fk['column_name'],
                    'constraint_name' => (string)$fk['constraint_name'],
                    'referenced_table_name' => (string)$fk['referenced_table_name'],
                    'referenced_column_name' => (string)$fk['referenced_column_name'],
                    'delete_rule' => $fk['delete_rule'] ?? null,
                    'update_rule' => $fk['update_rule'] ?? null,
                ];
            }
        }
    }

    /**
     * The table a foreign key column points at, '' when the column is not a foreign key
     * @param string $column
     */
    public function getFkTable($column): string
    {
        return $this->foreignKeys[(string)$column]['referenced_table_name'] ?? '';
    }

    /* ───────────── internals ───────────── */

    private function inspector(): TableInspectorInterface
    {
        $inspector = $this->getTableInspector();
        $needsInspection = $inspector instanceof AbstractTableInspector ? !$inspector->isInspected() : !$this->inspected;
        if ($needsInspection) {
            $inspector->inspect();
            $this->inspected = true;
        }
        return $inspector;
    }

    private function getNamespace(): string
    {
        return trim((string)($this->getConfig()['namespace'] ?? ''), '\\');
    }

    /**
     * A double-quoted PHP string literal (the generated files always used double quotes)
     */
    private static function stringLiteral(string $value): string
    {
        return '"' . addcslashes($value, "\\\"\$\n\r\t") . '"';
    }

    /**
     * Short-array PHP source of a nested array (strings and scalars exported by var_export)
     * @param array<int|string, mixed> $value
     */
    public static function exportArray(array $value): string
    {
        $isList = $value === [] || array_keys($value) === range(0, count($value) - 1);
        $parts = [];
        foreach ($value as $key => $item) {
            $exported = is_array($item) ? self::exportArray($item) : var_export($item, true);
            $parts[] = $isList ? $exported : var_export($key, true) . ' => ' . $exported;
        }
        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * Write a file, creating its directory
     * @throws \RuntimeException
     */
    private static function writeFile(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory: {$dir}");
        }
        if (file_exists($path) && !is_writable($path)) {
            throw new \RuntimeException("File is not writable: {$path}");
        }
        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException("Failed to write file: {$path}");
        }
    }
}
