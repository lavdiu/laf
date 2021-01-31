<?php

namespace Laf\Generator;

use Laf\Database\Table;
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

    public function processClass()
    {
        $namespace = $this->getConfig()['namespace'];
        $className = $this->getTable()->getNameAsClassname();
        $tableName = $this->getTable()->getName();
        $instanceName = strtolower($className);

        $labels = [];
        $labels['view'] = $this->labelTranslations['view'] ?? 'View';
        $labels['cancel'] = $this->labelTranslations['cancel'] ?? 'Cancel';
        $labels['options'] = $this->labelTranslations['options'] ?? 'Options';
        $labels['add-new'] = $this->labelTranslations['add-new'] ?? 'Add new';
        $labels['update'] = $this->labelTranslations['update'] ?? 'Update';
        $labels['delete'] = $this->labelTranslations['delete'] ?? 'Delete';
        $labels['list'] = $this->labelTranslations['list'] ?? 'List';
        $labels['delete-confirmation'] = $this->labelTranslations['delete-confirmation'] ?? 'Are you sure you want to delete this?';

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

\$id = UrlParser::getId();
\${$instanceName} = new {$className}(\$id);
\$form = \${$instanceName}->getForm();

\$page = new AdminPage();
\$page->setTitle(\"<a href='\" . UrlParser::getListLink() . \"'>\" . ucfirst(\${$instanceName}->getTable()->getNameAsClassname()) . '</a>');
\$page->setTitleIcon('far fa-list-alt');


if (\$form->isSubmitted()) {
	\$id = \$form->processForm();
	UrlParser::redirectToViewPage(\$id);
	exit;
}

switch (UrlParser::getAction()) {
	case 'update':

		\$form->setDrawMode(DrawMode::UPDATE);
		\$page->addComponent(\$form);

		\$page->addLink(new Link('{$labels['cancel']}', UrlParser::getViewLink(), 'fas fa-window-close', [], ['btn', 'btn-sm', 'btn-outline-success']));
		break;
	case 'new':
		\$form->setDrawMode(DrawMode::INSERT);
		\$page->addComponent(\$form);
		\$page->addLink(new Link('{$labels['cancel']}', UrlParser::getListLink(), 'fas fa-window-close', [], ['btn', 'btn-sm', 'btn-outline-success']));
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
		break;
	case 'list':
	default:
		\$grid = new PhpGrid('{$tableName}_list');
        \$grid->setTitle('{$className} {$labels['list']}')
            ->setRowsPerPage(20)
            ->setSqlQuery('\n" . ($this->buildListSql()['sql']) . "');\n\n";


        foreach ($this->getTableInspector()->getColumns() as $column){
            if($column['COLUMN_KEY'] == 'PRI'){

            }
        }

            foreach ($this->buildListSql()['columns'] as $alias => $column) {
                if ($column[0] == $tableName && $column[1] == 'id') {
                    $file .= "\n\t\t\$grid->addColumn(new Column('{$alias}', '" . Util::tableFieldNameToLabel($column[2]) . "', true, true, sprintf('?module=%s&action=view&id={" . $tableName . "_id}', UrlParser::getModule())));";
                } else {
                    $file .= "\n\t\t\$grid->addColumn(new Column('{$alias}', '" . Util::tableFieldNameToLabel($column[2]) . "', " . ($column[3] ? 'true' : 'false') . "));";
                }
            }

        $file .= "\n\n\t\t\$grid->addActionButton(new ActionButton('{$labels['view']}', sprintf('?module=%s&action=view&id={" . $tableName . "_id}', UrlParser::getModule()), 'fa fa-eye'));
        \$grid->addActionButton(new ActionButton('{$labels['update']}', sprintf('?module=%s&action=update&id={" . $tableName . "_id}', UrlParser::getModule()), 'fa fa-edit'));
        \$deleteLink = new ActionButton('{$labels['delete']}', sprintf('?module=%s&action=delete&id={" . $tableName . "_id}', UrlParser::getModule()), 'fa fa-trash');
        \$deleteLink->addAttribute('onclick', \"return confirm('{$labels['delete-confirmation']}')\");
        \$grid->addActionButton(\$deleteLink);

        if (\$grid->isReadyToHandleRequests()) {
            \$grid->bootstrap();
        }
        
        \$page->addLink(new Link('{$labels['add-new']}', UrlParser::getNewLink(), 'fa fa-plus-square', [], ['class' => 'btn btn-sm btn-outline-success']));
        \$page->addComponent(new HtmlContainer(\$grid->draw()));
		\$page->setContainerType(ContainerType::TYPE_FLUID);
		break;
}

\$html = \\{$namespace}\\Factory::GeneralPage();
\$html->addComponent(\$page);
echo \$html->draw();

";
        $this->setPageFile($file);
    }

    /**
     * Return the path where the page file will be stored
     * @return string
     */
    public function getPageFilePath(): string
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
    public function pageFileExists(): bool
    {
        return file_exists($this->getPageFilePath());
    }

    /**
     * Generates and saves class to file
     * @return PageGenerator
     */
    public function savePageToFile()
    {
        $this->processClass();
        $file = $this->getPageFilePath();
        file_put_contents($file, $this->getpageFile());
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
        $this->tableInspector = new TableInspector($table->getName());
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
    private function buildListSql(): array
    {
        $className = '\\' . $this->getConfig()['namespace'] . '\\' . $this->getTable()->getNameAsClassname();
        $thisTable = (new $className)->getTable();
        $columns = [];
        $joins = [];

        foreach($this->getTableInspector()->getColumns() as $c){
            if(array_key_exists('FOREIGN_KEY', $c)){
                $columnName = $c['COLUMN_NAME'];
                $fkTableName = $c['FOREIGN_KEY']['referenced_table_name'];
                $fkTableCol = $c['FOREIGN_KEY']['referenced_column_name'];

                $referencingTable = new TableInspector($c['FOREIGN_KEY']['referenced_table_name']);
                $referencingTableColumns = $referencingTable->getColumns();
                $displayCol = $referencingTable->getDisplayColumnName();

                $columns[$c['TABLE_NAME'] . '_' . $c['COLUMN_NAME']] = [$c['TABLE_NAME'], $c['COLUMN_NAME'], $c['COLUMN_NAME'] . 'Id', false];
                $columns[$fkTableName . '_' . $displayCol] = [$fkTableName, $displayCol, $fkTableName, true];

                $joins[] = "LEFT JOIN `" . $fkTableName . "` ON `" . $thisTable->getName() . '`.`' . $columnName . '` = `' . $fkTableName . '`.`' . $fkTableCol . '`';
            }
        }

        foreach ($thisTable->getFields() as $field) {
            if ($field->isForeignKey()) {
                $fkClassName = '\\' . $this->getConfig()['namespace'] . '\\' . $thisTable->getForeignKey($field->getName())->getReferencingTable();
                $fkTable = (new $fkClassName)->getTable();

                $columns[$thisTable->getName() . '_' . $field->getName()] = [$thisTable->getName(), $field->getName(), $field->getName() . 'Id', false];
                $columns[$fkTable->getName() . '_' . $fkTable->getDisplayField()->getName()] = [$fkTable->getName(), $fkTable->getDisplayField()->getName(), $fkTable->getName(), true];

                $joins[] = "LEFT JOIN `" . $fkTable->getName() . "` ON `" . $thisTable->getName() . '`.`' . $field->getName() . '` = `' . $fkTable->getName() . '`.`' . $thisTable->getForeignKey($field->getName())->getReferencingField() . '`';
            } else {
                $columns[$thisTable->getName() . '_' . $field->getName()] = [$thisTable->getName(), $field->getName(), $field->getName(), true];
            }
        }

        $sql = "\tSELECT\n";
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
        $sql .= "\n\tFROM " . $thisTable->getName() . "\n";
        $sql .= join("\n\t\t", $joins);

        return [
            'sql' => "SELECT * FROM (\n{$sql}\n)l1 ",
            'columns' => $columns
        ];

    }


}