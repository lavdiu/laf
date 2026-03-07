<?php

namespace Laf\Generator;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Laf\Database\Table;
use Laf\Util\Settings;
use Laf\Util\UrlParser;
use Laf\Util\Util;

class PageGenerator
{

    /**
     * @var Table $table
     */
    private $table;

    /**
     * @var string
     */
    private $pageFile = null;

    /**
     * @var string[]
     */
    private $config = [];

    /**
     * @var array
     */
    private $labelTranslations = [];

    /**
     * @var bool
     */
    private $writeOnLiveDirectory = false;

    /**
     * @var TableInspectorInterface
     */
    private $tableInspector = null;

    /**
     * Table constructor.
     * @param Table $table
     * @param string[] $config
     * @param string $labelTranslations
     */
    public function __construct(Table $table, array $config, array $labelTranslations = [])
    {
        $this->setTable($table);
        $this->config = $config;
        $this->labelTranslations = $labelTranslations;
    }

    private function getLabels(): array
    {
        $labels['view'] = $this->labelTranslations['view'] ?? 'View';
        $labels['cancel'] = $this->labelTranslations['cancel'] ?? 'Cancel';
        $labels['options'] = $this->labelTranslations['options'] ?? 'Options';
        $labels['add-new'] = $this->labelTranslations['add-new'] ?? 'Add new';
        $labels['update'] = $this->labelTranslations['update'] ?? 'Update';
        $labels['delete'] = $this->labelTranslations['delete'] ?? 'Delete';
        $labels['list'] = $this->labelTranslations['list'] ?? 'List';
        $labels['delete-confirmation'] = $this->labelTranslations['delete-confirmation'] ?? 'Are you sure you want to delete this?';
        $labels['record-saved'] = $this->labelTranslations['record-saved'] ?? 'Record saved successfully';
        $labels['record-deleted'] = $this->labelTranslations['record-deleted'] ?? 'Record deleted successfully';
        $labels['record-not-found'] = $this->labelTranslations['record-not-found'] ?? 'Record not found';
        return $labels;
    }

    private function getAllFieldsCommentedOut(string $instanceName, bool $skip_row_metadata = false) : string
    {
        $html = "\n/**\n\t\$form->setComponents([])";

        foreach($this->getTableInspector()->getColumns() as $column){
            if($skip_row_metadata && in_array($column['COLUMN_NAME'], ['created_on', 'created_by', 'updated_on', 'updated_by'])){
                continue;
            }else {
                $html .= "\n\t\t->addComponent(\${$instanceName}->get" . Util::tableFieldNameToMethodName($column['COLUMN_NAME']) . "FormElement())";
            }
        }

        $html .= ";\n*/";

        return $html;
    }

    /**
     *
     */
    public function generatePageFile(): void
    {
        $this->tableInspector = self::createTableInspector($this->getTable()->getName());
        $this->getTableInspector()->inspect();


        $namespace = $this->getConfig()['namespace'];
        $className = $this->getTable()->getNameAsClassname();
        $tableName = $this->getTable()->getName();
        $instanceName = strtolower($className);
        $labels = $this->getLabels();

        $file = "<?php

use {$namespace}\\{$className};
use Laf\UI\Component\Alert;
use Laf\UI\Component\Dropdown;
use Laf\UI\Component\Link;
use Laf\UI\Container\ContainerType;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\Form;
use Laf\UI\Form\FormRowDisplayMode;
use Laf\UI\Page\AdminPage;
use Laf\Util\UrlParser;
use Laf\UI\Container\HtmlContainer;
use Laf\UI\Grid\PhpGrid\PhpGrid;
use Laf\UI\Grid\PhpGrid\Column;
use Laf\UI\Grid\PhpGrid\ActionButton;
use {$namespace}\\Factory;
use Laf\UI\Container\Div;
use Laf\UI\Container\TabContainer;
use Laf\UI\Container\TabItem;

\$id = UrlParser::getId();
\${$instanceName} = new {$className}(\$id);
\$form = \${$instanceName}->getForm();
{$this->getAllFieldsCommentedOut($instanceName)}
\$html = Factory::GeneralPage();
\$page = new AdminPage();

\$page->setTitle(\"<a href='\" . UrlParser::getListLink() . \"' class='text-black text-decoration-none'>" . ucfirst($className) . "</a>\");
\$page->setTitleIcon('far fa-list-alt');

// Flash message support
if (isset(\$_SESSION['flash_message'])) {
	\$page->addComponent(new Alert('', \$_SESSION['flash_message'], \$_SESSION['flash_type'] ?? Alert::Type_Success));
	unset(\$_SESSION['flash_message'], \$_SESSION['flash_type']);
}

if (\$form->isSubmitted()) {
{$this->buildRecordStatusDefault()}	\$id = \$form->processForm();
	\$_SESSION['flash_message'] = '{$labels['record-saved']}';
	\$_SESSION['flash_type'] = Alert::Type_Success;
	UrlParser::redirectToViewPage(\$id);
	exit;
}

switch (UrlParser::getAction()) {
	case 'update':
		if (!\${$instanceName}->recordExists()) {
			\$_SESSION['flash_message'] = '{$labels['record-not-found']}';
			\$_SESSION['flash_type'] = Alert::Type_Danger;
			UrlParser::redirectToListPage();
			exit;
		}
        \$page->setContainerType(ContainerType::TYPE_FLUID);
		\$form->setDrawMode(DrawMode::UPDATE);
{$this->buildEditFormLayout($instanceName)}
		\$page->addComponent(\$form);

		\$page->addLink(new Link('{$labels['cancel']}', UrlParser::getViewLink(), 'fas fa-window-close', [], ['btn', 'btn-sm', 'btn-outline-success']));
		\$html->addComponent(\$page);
		echo \$html->draw();
		break;
	case 'new':
	    \$page->setContainerType(ContainerType::TYPE_FLUID);
{$this->buildEditFormLayout($instanceName)}
		\$form->setDrawMode(DrawMode::INSERT);
		\$page->addComponent(\$form);
		\$page->addLink(new Link('{$labels['cancel']}', UrlParser::getListLink(), 'fas fa-window-close', [], ['btn', 'btn-sm', 'btn-outline-success']));
		\$html->addComponent(\$page);
		echo \$html->draw();
		break;
	case 'delete':
		if (\${$instanceName}->recordExists()) {
			if (\${$instanceName}->canSoftDelete()) {
				\${$instanceName}->softDelete();
			} else {
				\${$instanceName}->hardDelete();
			}
			\$_SESSION['flash_message'] = '{$labels['record-deleted']}';
			\$_SESSION['flash_type'] = Alert::Type_Success;
		} else {
			\$_SESSION['flash_message'] = '{$labels['record-not-found']}';
			\$_SESSION['flash_type'] = Alert::Type_Danger;
		}
		UrlParser::redirectToListPage();
		break;
	case 'view':
		if (!\${$instanceName}->recordExists()) {
			\$_SESSION['flash_message'] = '{$labels['record-not-found']}';
			\$_SESSION['flash_type'] = Alert::Type_Danger;
			UrlParser::redirectToListPage();
			exit;
		}
	    \$page->setContainerType(ContainerType::TYPE_FLUID);
		\$form->setDrawMode(DrawMode::VIEW);
{$this->buildViewFormLayout($instanceName)}
		\$page->addComponent(\$form);
		\$page->addLink(new Link('{$labels['list']}', UrlParser::getListLink(), 'far fa-list-alt', [], ['btn', 'btn-sm', 'btn-outline-success']));

		\$dd = new Dropdown('{$labels['options']}', '', 'fa fa-cogs', true);
		\$dd->addCssClass('btn-outline-success')
			->addCssClass('btn-sm');
		\$newLink = new Link('{$labels['update']}', UrlParser::getUpdateLink(), 'fa fa-edit', ['class' => 'btn btn-sm btn-outline-warning']);
		\$deleteLink = new Link('{$labels['delete']}', UrlParser::getDeleteLink(), 'fa fa-trash', ['class' => 'btn btn-sm btn-outline-danger']);
		\$deleteLink->setConfirmationMessage('{$labels['delete-confirmation']}');

		\$dd->addLink(\$newLink)
			->addLink(\$deleteLink);
		\$page->addLink(\$dd);
		
		
		\$html->addComponent(\$page);\n";

        if ($this->getTableInspector()->hasReferencingTables()) {
            $tabContainerId = $this->getTable()->getName() . '_related_tabs';
            $file .= "
        \$tabContainer = new TabContainer('{$tabContainerId}');\n\n";

            foreach ($this->getTableInspector()->getReferencingTables() as $table) {
                $table = array_change_key_case($table, CASE_UPPER);
                $refTableName = $table['TABLE_NAME'];
                $junction = self::detectJunctionTable($refTableName, $tableName);

                if ($junction !== null) {
                    // Many-to-many: show the "other" table, filtered via the junction
                    $gridVarName = $junction['other_table'];
                    $tabLabel = Util::tableNameToClassName($junction['other_table']);
                    $gridDraw = $this->buildManyToManyGrid(
                        $junction['other_table'],
                        $gridVarName,
                        $junction['junction_table'],
                        $junction['fk_to_current'],
                        $junction['fk_to_other'],
                        $tableName,
                        $this->getTableInspector()->getPrimaryColumnName()
                    );
                } else {
                    // One-to-many: show the referencing table directly
                    $gridVarName = $refTableName;
                    $tabLabel = Util::tableNameToClassName($refTableName);
                    $gridDraw = $this->buildGrid($gridVarName, $gridVarName, ['table_name' => $tableName, 'column_name' => $this->getTableInspector()->getPrimaryColumnName()]);
                }

                $file .= "
        {$gridDraw}
        \${$gridVarName}->setDeferInitialize(true);

        \$tabItem = new TabItem('{$tabLabel}');
        \$tabItem->addComponent(new HtmlContainer(\${$gridVarName}->draw()));
        \$tabContainer->addComponent(\$tabItem);\n";
            }
            $file .= "

        \$page2 = new AdminPage();
        \$page2->setTitle('Related information')
            ->addComponent(new HtmlContainer(\$tabContainer->draw()));
        \$html->addComponent(\$page2);";

        }

        $file .= "
		echo \$html->draw();
		break;
	case 'list':
	default:";
        $file .= $this->buildGrid($this->getTable()->getName());
        $file .= "
        \$page->addComponent(new HtmlContainer(\$grid->draw()));
        \$page->addLink(new Link('{$labels['add-new']}', UrlParser::getNewLink(), 'fa fa-plus-square', [], ['class' => 'btn btn-sm btn-outline-success']));
		\$page->setContainerType(ContainerType::TYPE_FLUID);
		\$html->addComponent(\$page);
		echo \$html->draw();
		break;
}
";
        $this->setPageFile($file);
    }

    /**
     * Return the path where the page file will be stored
     * @return string
     */
    #[Pure] public function getPageFilePath(): string
    {
        if ($this->isWriteOnLiveDirectory()) {
            return $this->getConfig()['live_page_dir'] . '/' . $this->getTable()->getName() . '.page';
        } else {
            return $this->getConfig()['page_dir'] . '/' . $this->getTable()->getName() . '.page';
        }
    }

    /**
     * Check if the page file already exists in the filesystem
     * @return bool
     */
    #[Pure] public function pageFileExists(): bool
    {
        return file_exists($this->getPageFilePath());
    }

    /**
     * Generates and saves class to file
     * @return PageGenerator
     */
    public function savePageToFile(): static
    {
        $this->generatePageFile();
        $file = $this->getPageFilePath();
        
        // Create directory if it doesn't exist
        $dir = dirname($file);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new \RuntimeException("Cannot create directory: {$dir}");
            }
        }
        
        // Check write permissions
        if (file_exists($file) && !is_writable($file)) {
            throw new \RuntimeException("File is not writable: {$file}");
        }
        
        if (file_put_contents($file, $this->getPageFile()) === false) {
            throw new \RuntimeException("Failed to write file: {$file}");
        }
        
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
     * @return TableInspectorInterface
     */
    public function getTableInspector(): TableInspectorInterface
    {
        return $this->tableInspector;
    }

    private static function createTableInspector(string $tableName): TableInspectorInterface
    {
        if (Settings::get('database.engine') == 'postgres') {
            return new PostgresTableInspector($tableName);
        }
        return new TableInspector($tableName);
    }

    /**
     * Checks if a table is a junction/pivot table for a many-to-many relationship.
     * A junction table has exactly 2 foreign keys and few additional non-FK,
     * non-PK, non-metadata columns.
     *
     * @param string $tableName The referencing table to check
     * @param string $currentTable The current/parent table name
     * @return array|null Null if not a junction table, otherwise:
     *   ['other_table' => string, 'junction_table' => string, 'fk_to_current' => string, 'fk_to_other' => string]
     */
    private static function detectJunctionTable(string $tableName, string $currentTable): ?array
    {
        $ti = self::createTableInspector($tableName);
        $ti->inspect();

        $foreignKeys = [];
        $metadataColumns = ['id', 'created_on', 'created_by', 'updated_on', 'updated_by', 'is_deleted'];

        foreach ($ti->getColumns() as $column) {
            if (array_key_exists('FOREIGN_KEY', $column)) {
                $foreignKeys[] = $column;
            }
        }

        if (count($foreignKeys) !== 2) {
            return null;
        }

        // Count non-FK, non-PK, non-metadata columns
        $extraColumns = 0;
        foreach ($ti->getColumns() as $column) {
            $colName = $column['COLUMN_NAME'];
            if (in_array($colName, $metadataColumns)) {
                continue;
            }
            if (array_key_exists('FOREIGN_KEY', $column)) {
                continue;
            }
            if (isset($column['COLUMN_KEY']) && $column['COLUMN_KEY'] === 'PRI') {
                continue;
            }
            $extraColumns++;
        }

        if ($extraColumns > 2) {
            return null;
        }

        // Identify which FK points to current table and which to the other
        $fkToCurrent = null;
        $fkToOther = null;
        foreach ($foreignKeys as $fk) {
            if ($fk['FOREIGN_KEY']['referenced_table_name'] === $currentTable) {
                $fkToCurrent = $fk;
            } else {
                $fkToOther = $fk;
            }
        }

        if ($fkToCurrent === null || $fkToOther === null) {
            return null;
        }

        return [
            'junction_table' => $tableName,
            'other_table' => $fkToOther['FOREIGN_KEY']['referenced_table_name'],
            'fk_to_current' => $fkToCurrent['COLUMN_NAME'],
            'fk_to_other' => $fkToOther['COLUMN_NAME'],
        ];
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
    public function getPageFile()
    {
        return $this->pageFile;
    }

    /**
     * @param null $pageFile
     */
    public function setPageFile($pageFile): void
    {
        $this->pageFile = $pageFile;
    }

    /**
     * @return bool
     */
    public function isWriteOnLiveDirectory(): bool
    {
        return $this->writeOnLiveDirectory;
    }

    /**
     * @param bool $writeOnLiveDirectory
     * @return PageGenerator
     */
    public function setWriteOnLiveDirectory(bool $writeOnLiveDirectory): PageGenerator
    {
        $this->writeOnLiveDirectory = $writeOnLiveDirectory;
        return $this;
    }

    /**
     * @return array|string
     */
    public function getLabelTranslations()
    {
        return $this->labelTranslations;
    }

    /**
     * @param array|string $labelTranslations
     * @return PageGenerator
     */
    public function setLabelTranslations($labelTranslations)
    {
        $this->labelTranslations = $labelTranslations;
        return $this;
    }

    /**
     * Returns the built sql to select the list
     * and a list of columns
     * @param string $tableName
     * @param array $filters
     * format: [
     *  sql = "sql statement"
     *  columns [
     *      alias => [
     *          0 => table name
     *          1 => column name
     *          2 => label
     *          3 => visible
     *      ]
     *  ]
     *
     * ]
     * @return array
     */
    #[ArrayShape(['sql' => "string", 'columns' => "array"])]
    private function getDbTableDetails(string $tableName, array $filters = []): array
    {
        $columns = [];
        $joins = [];
        $joinedTables = [$tableName];
        $ti = self::createTableInspector($tableName);
        $ti->inspect();

        foreach ($ti->getColumns() as $c) {
            $columnName = $c['COLUMN_NAME'];
            $tableAlias = $tableName;

            if (array_key_exists('FOREIGN_KEY', $c)) {
                $fkTableName = $c['FOREIGN_KEY']['referenced_table_name'];
                $fkTableCol = $c['FOREIGN_KEY']['referenced_column_name'];

                if (in_array($fkTableName, $joinedTables)) {
                    $fkTableAlias = $fkTableName . '_' . $columnName;
                } else {
                    $fkTableAlias = $fkTableName;
                }
                array_push($joinedTables, $fkTableName);

                $referencingTable = self::createTableInspector($c['FOREIGN_KEY']['referenced_table_name']);
                $referencingTable->inspect();
                $displayCol = $referencingTable->getDisplayColumnName();

                $columns[$tableAlias . '_' . $columnName] = [$tableAlias, $columnName, $columnName . 'Id', false];
                $columns[$fkTableAlias . '_' . $displayCol] = [$fkTableAlias, $displayCol, $columnName, true];

                $joins[] = "LEFT JOIN {$fkTableName} {$fkTableAlias} ON {$tableName}.{$columnName} = {$fkTableAlias}.{$fkTableCol}";
            } else {
                $columns[$tableAlias . '_' . $columnName] = [$tableAlias, $columnName, $columnName, true];
            }
        }

        $sql = "\tSELECT";
        $iterator = 1;
        foreach ($columns as $alias => $column) {
            if ($iterator == 1) {
                $sql .= "\n\t\t  ";
            } else {
                $sql .= "\n\t\t, ";
            }
            $sql .= "" . $column[0] . '.' . $column[1] . ' AS ' . $alias;
            $iterator++;
        }
        $sql .= "\n\tFROM {$tableName}";
        $sql .= "\n\t" . implode("\n\t", $joins);
        $sql .= "\n\tWHERE 1=1 ";

        if (isset($filters['table_name']) && isset($filters['column_name']) && $filters['table_name'] != '' && $filters['column_name'] != '') {
            $sql .= " AND {$filters['table_name']}.{$filters['column_name']} = ' . ((int)UrlParser::getId()).'\n";
        }

        return [
            'sql' => "SELECT * FROM (\n{$sql}\n) l1 ",
            'columns' => $columns
        ];

    }

    /**
     * Build a grid for a many-to-many relationship via a junction table.
     * Shows columns from the "other" table, filtered by the current record's ID through the junction.
     *
     * @param string $otherTable The target table to display (e.g., 'tags')
     * @param string $gridName Variable name for the grid
     * @param string $junctionTable The junction/pivot table (e.g., 'order_tags')
     * @param string $fkToCurrent Junction column pointing to current table (e.g., 'order_id')
     * @param string $fkToOther Junction column pointing to other table (e.g., 'tag_id')
     * @param string $currentTable The current/parent table (e.g., 'orders')
     * @param string $currentPk Primary key column of the current table (e.g., 'id')
     * @return string
     */
    public function buildManyToManyGrid(
        string $otherTable,
        string $gridName,
        string $junctionTable,
        string $fkToCurrent,
        string $fkToOther,
        string $currentTable,
        string $currentPk
    ): string {
        $otherDetails = $this->getDbTableDetails($otherTable);
        $labels = $this->getLabels();
        $tableLabel = Util::tableFieldNameToLabel($otherTable);

        // Wrap the other table's query and add a JOIN through the junction
        $innerSql = $otherDetails['sql'];
        $sql = "SELECT l1.* FROM (\n{$innerSql}\n) l1 "
            . "INNER JOIN {$junctionTable} jt ON l1.{$otherTable}_id = jt.{$fkToOther} "
            . "WHERE jt.{$fkToCurrent} = ' . ((int)UrlParser::getId()) . '";

        $file = "\n\t\t\${$gridName} = new PhpGrid('{$gridName}_list');
        \${$gridName}->setTitle('{$tableLabel} {$labels['list']}')
            ->setRowsPerPage(20)
            ->setSqlQuery('\n{$sql}');\n";

        foreach ($otherDetails['columns'] as $alias => $column) {
            if ($column[0] == $otherTable && $column[1] == 'id') {
                $file .= "\n\t\t\${$gridName}->addColumn(((new Column('{$alias}', '" . Util::tableFieldNameToLabel($column[2]) . "', true, true, sprintf('?module=%s&action=view&id={{$alias}}', UrlParser::getModule())))->setInnerElementCssClass('btn btn-sm btn-outline-success'))->setOuterElementCssStyle('width:100px;'));";
            } else {
                $file .= "\n\t\t\${$gridName}->addColumn(new Column('{$alias}', '" . Util::tableFieldNameToLabel($column[2]) . "', " . ($column[3] ? 'true' : 'false') . "));";
            }
        }

        $file .= "\n\n\t\t\${$gridName}->addActionButton(new ActionButton('{$labels['view']}', sprintf('?module=%s&action=view&id={" . $otherTable . "_id}', UrlParser::getModule()), 'fa fa-eye'));

        if (\${$gridName}->isReadyToHandleRequests()) {
            \${$gridName}->bootstrap();
        }\n";
        return $file;
    }

    /**
     * If the table has a record_status_id column, generate code to default it to 1 on insert.
     */
    private function buildRecordStatusDefault(): string
    {
        foreach ($this->getTableInspector()->getColumns() as $column) {
            if ($column['COLUMN_NAME'] === 'record_status_id') {
                return "\tif (UrlParser::getAction() == 'new') {\n\t\t\$form->setSubmittedFieldValue('record_status_id', 1);\n\t}\n";
            }
        }
        return '';
    }

    /**
     * Build the edit (insert/update) form layout with multi-column support.
     * Metadata fields are excluded entirely.
     */
    private function buildEditFormLayout(string $instanceName): string
    {
        $excludeFields = ['created_on', 'created_by', 'updated_on', 'updated_by'];
        $fields = [];

        foreach ($this->getTableInspector()->getColumns() as $column) {
            $colName = $column['COLUMN_NAME'];
            if (!in_array($colName, $excludeFields)) {
                $fields[] = Util::tableFieldNameToMethodName($colName);
            }
        }

        $fieldCount = count($fields);

        // Determine number of columns based on total field count in the table
        $totalColumns = count($this->getTableInspector()->getColumns());
        if ($totalColumns < 10) {
            $numCols = 1;
        } elseif ($totalColumns <= 20) {
            $numCols = 2;
        } else {
            $numCols = 3;
        }

        $file = "\t\t\$form->setComponents([]);\n";

        if ($numCols <= 1) {
            foreach ($fields as $method) {
                $file .= "\t\t\$form->addComponent(\${$instanceName}->get{$method}FormElement());\n";
            }
            return $file;
        }

        $file .= "\t\t\$row = new Div(['row']);\n";
        $chunks = array_chunk($fields, (int)ceil($fieldCount / $numCols));
        foreach ($chunks as $i => $chunk) {
            $colVar = 'col' . ($i + 1);
            $file .= "\t\t\${$colVar} = new Div(['col-lg']);\n";
            foreach ($chunk as $method) {
                $file .= "\t\t\${$colVar}->addComponent(\${$instanceName}->get{$method}FormElement());\n";
            }
            $file .= "\t\t\$row->addComponent(\${$colVar});\n";
        }
        $file .= "\t\t\$form->addComponent(\$row);\n";

        return $file;
    }

    /**
     * Build the view mode form layout with multi-column support and a metadata tab.
     * <10 fields: 1 column, <=20: 2 columns, >20: 3 columns.
     * Metadata fields (created_on, created_by, updated_on, updated_by) go in a separate "Details" tab.
     */
    private function buildViewFormLayout(string $instanceName): string
    {
        $metadataFieldNames = ['record_status_id', 'created_on', 'created_by', 'updated_on', 'updated_by'];
        $col1MetadataNames = ['created_by', 'created_on', 'record_status_id'];
        $col2MetadataNames = ['updated_by', 'updated_on'];
        $mainFields = [];
        $metadataFields = [];
        $metadataCol1 = [];
        $metadataCol2 = [];

        foreach ($this->getTableInspector()->getColumns() as $column) {
            $colName = $column['COLUMN_NAME'];
            $methodName = Util::tableFieldNameToMethodName($colName);
            if (in_array($colName, $metadataFieldNames)) {
                $metadataFields[] = $methodName;
                if (in_array($colName, $col1MetadataNames)) {
                    $metadataCol1[] = $methodName;
                } elseif (in_array($colName, $col2MetadataNames)) {
                    $metadataCol2[] = $methodName;
                }
            } else {
                $mainFields[] = $methodName;
            }
        }

        $mainCount = count($mainFields);
        $totalCount = $mainCount + count($metadataFields);
        $hasMetadata = count($metadataFields) > 0;

        // Determine number of columns based on total field count
        if ($totalCount < 10) {
            $numCols = 1;
        } elseif ($totalCount <= 20) {
            $numCols = 2;
        } else {
            $numCols = 3;
        }

        $file = "";

        if ($numCols === 1 && !$hasMetadata) {
            // Simple case: no columns, no tabs needed
            $file .= "\t\t\$form->setComponents([])";
            foreach ($mainFields as $method) {
                $file .= "\n\t\t\t->addComponent(\${$instanceName}->get{$method}FormElement())";
            }
            $file .= ";\n";
            return $file;
        }

        $file .= "\t\t\$form->setComponents([]);\n";

        if ($numCols > 1) {
            $file .= "\t\t\$form->setFormRowDisplayMode(FormRowDisplayMode::INLINE);\n";
        }

        // Build the main fields in columns
        if ($numCols > 1) {
            $file .= "\t\t\$row = new Div(['row']);\n";
            $chunks = array_chunk($mainFields, (int)ceil($mainCount / $numCols));
            foreach ($chunks as $i => $chunk) {
                $colVar = 'col' . ($i + 1);
                $file .= "\t\t\${$colVar} = new Div(['col-lg']);\n";
                foreach ($chunk as $method) {
                    $file .= "\t\t\${$colVar}->addComponent(\${$instanceName}->get{$method}FormElement());\n";
                }
                $file .= "\t\t\$row->addComponent(\${$colVar});\n";
            }
        }

        if ($hasMetadata) {
            // Wrap in tabs: General + Details
            $file .= "\t\t\$formTabContainer = new TabContainer('{$this->getTable()->getName()}_form_tabs');\n";
            $file .= "\t\t\$generalTab = new TabItem('General');\n";
            if ($numCols > 1) {
                $file .= "\t\t\$generalTab->addComponent(\$row);\n";
            } else {
                // Single column, but still has metadata to separate
                $file .= "\t\t\$generalDiv = new Div();\n";
                foreach ($mainFields as $method) {
                    $file .= "\t\t\$generalDiv->addComponent(\${$instanceName}->get{$method}FormElement());\n";
                }
                $file .= "\t\t\$generalTab->addComponent(\$generalDiv);\n";
            }
            $file .= "\t\t\$detailsTab = new TabItem('Details');\n";
            if (count($metadataCol1) > 0 && count($metadataCol2) > 0) {
                $file .= "\t\t\$metaRow = new Div(['row']);\n";
                $file .= "\t\t\$metaCol1 = new Div(['col-lg']);\n";
                foreach ($metadataCol1 as $method) {
                    $file .= "\t\t\$metaCol1->addComponent(\${$instanceName}->get{$method}FormElement());\n";
                }
                $file .= "\t\t\$metaCol2 = new Div(['col-lg']);\n";
                foreach ($metadataCol2 as $method) {
                    $file .= "\t\t\$metaCol2->addComponent(\${$instanceName}->get{$method}FormElement());\n";
                }
                $file .= "\t\t\$metaRow->addComponent(\$metaCol1);\n";
                $file .= "\t\t\$metaRow->addComponent(\$metaCol2);\n";
                $file .= "\t\t\$detailsTab->addComponent(\$metaRow);\n";
            } else {
                foreach ($metadataFields as $method) {
                    $file .= "\t\t\$detailsTab->addComponent(\${$instanceName}->get{$method}FormElement());\n";
                }
            }
            $file .= "\t\t\$formTabContainer->addComponent(\$generalTab);\n";
            $file .= "\t\t\$formTabContainer->addComponent(\$detailsTab);\n";
            $file .= "\t\t\$form->addComponent(\$formTabContainer);\n";
        } else {
            // No metadata, just add the row directly
            if ($numCols > 1) {
                $file .= "\t\t\$form->addComponent(\$row);\n";
            }
        }

        return $file;
    }

    /**
     * @param string $table_name
     * @param string $grid_name
     * @param array|null[] $filters
     * @return string
     */
    public function buildGrid(string $table_name, string $grid_name = 'grid', array $filters = []): string
    {
        $tableDetails = $this->getDbTableDetails($table_name, $filters);
        $labels = $this->getLabels();

        $tableName = $table_name;
        $tableLabel = Util::tableFieldNameToLabel($table_name);

        $file = "\n\t\t\${$grid_name} = new PhpGrid('{$table_name}_list');
        \${$grid_name}->setTitle('{$tableLabel} {$labels['list']}')
            ->setRowsPerPage(20)
            ->setSqlQuery('\n" . ($tableDetails['sql']) . "');\n";

        foreach ($tableDetails['columns'] as $alias => $column) {
            if ($column[0] == $tableName && $column[1] == 'id') {
                $file .= "\n\t\t\${$grid_name}->addColumn(((new Column('{$alias}', '" . Util::tableFieldNameToLabel($column[2]) . "', true, true, sprintf('?module=%s&action=view&id={{$alias}}', UrlParser::getModule())))->setInnerElementCssClass('btn btn-sm btn-outline-success'))->setOuterElementCssStyle('width:100px;'));";
            } else {
                $file .= "\n\t\t\${$grid_name}->addColumn(new Column('{$alias}', '" . Util::tableFieldNameToLabel($column[2]) . "', " . ($column[3] ? 'true' : 'false') . "));";
            }
        }

        $file .= "\n\n\t\t\${$grid_name}->addActionButton(new ActionButton('{$labels['view']}', sprintf('?module=%s&action=view&id={" . $tableName . "_id}', UrlParser::getModule()), 'fa fa-eye'));
        \${$grid_name}->addActionButton(new ActionButton('{$labels['update']}', sprintf('?module=%s&action=update&id={" . $tableName . "_id}', UrlParser::getModule()), 'fa fa-edit'));
        \$deleteLink = new ActionButton('{$labels['delete']}', sprintf('?module=%s&action=delete&id={" . $tableName . "_id}', UrlParser::getModule()), 'fa fa-trash');
        \$deleteLink->addAttribute('onclick', \"return confirm('{$labels['delete-confirmation']}')\");
        \${$grid_name}->addActionButton(\$deleteLink);

        if (\${$grid_name}->isReadyToHandleRequests()) {
            \${$grid_name}->bootstrap();
        }\n";
        return $file;
    }
}
