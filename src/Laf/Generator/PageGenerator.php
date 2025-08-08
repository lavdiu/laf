<?php

namespace Laf\Generator;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Laf\Database\Table;
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
     * @var TableInspector
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
        $this->tableInspector = new TableInspector($this->getTable()->getName());
        if(Settings::get('database.engine') == 'postgres'){
            $this->tableInspector = new PostgresTableInspector($this->getTable()->getName());
        }
        $namespace = $this->getConfig()['namespace'];
        $className = $this->getTable()->getNameAsClassname();
        $tableName = $this->getTable()->getName();
        $instanceName = strtolower($className);
        $labels = $this->getLabels();

        $file = "<?php

use {$namespace}\\{$className};
use Laf\UI\Component\Dropdown;
use Laf\UI\Component\Link;
use Laf\UI\Container\ContainerType;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\Form;
use Laf\UI\Page\AdminPage;
use Laf\Util\UrlParser;
use Laf\UI\Container\HtmlContainer;
use Laf\UI\Grid\PhpGrid\PhpGrid;
use Laf\UI\Grid\PhpGrid\Column;
use Laf\UI\Grid\PhpGrid\ActionButton;
use {$namespace}\Factory;
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


if (\$form->isSubmitted()) {
	\$id = \$form->processForm();
	UrlParser::redirectToViewPage(\$id);
	exit;
}

switch (UrlParser::getAction()) {
	case 'update':
        \$page->setContainerType(ContainerType::TYPE_DEFAULT);
		\$form->setDrawMode(DrawMode::UPDATE);
		{$this->getAllFieldsCommentedOut($instanceName, true)}
		\$page->addComponent(\$form);

		\$page->addLink(new Link('{$labels['cancel']}', UrlParser::getViewLink(), 'fas fa-window-close', [], ['btn', 'btn-sm', 'btn-outline-success']));
		\$html->addComponent(\$page);
		echo \$html->draw();
		break;
	case 'new':
	    \$page->setContainerType(ContainerType::TYPE_DEFAULT);
	    {$this->getAllFieldsCommentedOut($instanceName, true)}
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
		}
		UrlParser::redirectToListPage();
		break;
	case 'view':
	    \$page->setContainerType(ContainerType::TYPE_DEFAULT);
		\$form->setDrawMode(DrawMode::VIEW);
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
		
		// Add audit trail section
        \$auditTrail = \${$instanceName}->getAuditTrail();
        if (!empty(\$auditTrail)) {
            \$auditHtml = '<div class=\"mt-4\"><h5>Audit Trail</h5><div class=\"table-responsive\"><table class=\"table table-sm\">';
            \$auditHtml .= '<thead><tr><th>Date</th><th>Action</th><th>User ID</th><th>Changes</th></tr></thead><tbody>';
            foreach (\$auditTrail as \$entry) {
                \$auditHtml .= '<tr><td>' . \$entry['created_on'] . '</td><td><span class=\"badge badge-' . 
                    ((\$entry['action'] == 'INSERT') ? 'success' : ((\$entry['action'] == 'UPDATE') ? 'warning' : 'danger')) . '\">' . 
                    \$entry['action'] . '</span></td><td>' . (\$entry['user_id'] ?? 'System') . '</td><td>' . \$entry['changes_count'] . ' field(s)</td></tr>';
            }
            \$auditHtml .= '</tbody></table></div></div>';
            \$page->addComponent(new HtmlContainer(\$auditHtml));
        }
		
		\$html->addComponent(\$page);\n";

        if ($this->getTableInspector()->hasReferencingTables()) {
            $file .= "
        \$tabContainer = new TabContainer();
        \$panel = new Div();
        \$panel->setContainerType(ContainerType::TYPE_FLUID);\n\n";


            foreach ($this->getTableInspector()->getReferencingTables() as $table) {
                $gridVarName = $table['TABLE_NAME'];
                $gridDraw = $this->buildGrid($gridVarName, $gridVarName, ['table_name' => $tableName, 'column_name' => $this->getTableInspector()->getPrimaryColumnName()]);

                $file .= "
        {$gridDraw}
        
        \$tabItem = new TabItem('" . Util::tableNameToClassName($gridVarName) . "');
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
        $file .= $this->buildGrid($this->getTable());
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
     * @return TableInspector
     */
    public function getTableInspector(): TableInspector
    {
        return $this->tableInspector;
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
        $ti = new TableInspector($tableName);

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

                $referencingTable = new TableInspector($c['FOREIGN_KEY']['referenced_table_name']);
                $displayCol = $referencingTable->getDisplayColumnName();

                $columns[$tableAlias . '_' . $columnName] = [$tableAlias, $columnName, $columnName . 'Id', false];
                $columns[$fkTableAlias . '_' . $displayCol] = [$fkTableAlias, $displayCol, $columnName, true];

                $joins[] = "LEFT JOIN `{$fkTableName}` `{$fkTableAlias}` ON `{$tableName}`.`{$columnName}` = `{$fkTableAlias}`.`{$fkTableCol}`";
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
            $sql .= "`" . $column[0] . '`.`' . $column[1] . '` AS ' . $alias;
            $iterator++;
        }
        $sql .= "\n\tFROM {$tableName} {$tableAlias}";
        $sql .= "\n\t" . implode("\n\t", $joins);
        $sql .= "\n\tWHERE 1=1 ";

        if (isset($filters['table_name']) && isset($filters['column_name']) && $filters['table_name'] != '' && $filters['column_name'] != '') {
            $sql .= " AND {$filters['table_name']}.{$filters['column_name']} = ' . ((int)UrlParser::getId()).'\n";
        }

        return [
            'sql' => "SELECT * FROM (\n{$sql}\n)l1 ",
            'columns' => $columns
        ];

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

        $tableName = $this->getTable()->getName();

        $file = "\n\t\t\${$grid_name} = new PhpGrid('{$table_name}_list');
        \${$grid_name}->setTitle('{$tableName} {$labels['list']}')
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
