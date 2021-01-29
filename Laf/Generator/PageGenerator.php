<?php

namespace Laf\Generator;

use Laf\Database\Field\FieldTypeFactory;
use Laf\Database\Table;
use Laf\Database\Field\Field;
use Laf\Database\Db;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Page\Page;
use Laf\Util\Settings;
use Laf\Util\Util;

class PageGenerator
{

    /**
     * @var Table $table
     */
    private $table;

    private $pageFile = null;

    private $config = [];

    private $labelTranslations = [];

    /**
     * @var bool
     */
    private $writeOnLiveDirectory = false;

    /**
     * Table constructor.
     * @param Table $table
     * @param string[] $config
     * @param string $labelTranslations
     */
    public function __construct(Table $table, array $config, array $labelTranslations = [])
    {
        $this->table = $table;
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
            ->setSqlQuery('\n" . ($this->buildLlistSql()['sql']) . "');\n";

        foreach ($this->buildLlistSql()['columns'] as $alias => $column) {
            if ($column[1] == 'id') {
                $file .= "\t\t->addColumn(new Column('{$alias}', '" . Util::tableFieldNameToLabel($column[1]) . "', true, true, sprintf('?module=%s&action=view&id={id}', UrlParser::getModule())));";
            } else {

                $file .= "\t\t->addColumn(new Column('{$alias}', '" . Util::tableFieldNameToLabel($column[1]) . "'));";
            }
        }

        $file .= "\t\$grid->addActionButton(new ActionButton('{$labels['view']}', sprintf('?module=%s&action=view&id={id}', UrlParser::getModule()), 'fa fa-eye'));
        \$grid->addActionButton(new ActionButton('{$labels['update']}', sprintf('?module=%s&action=update&id={id}', UrlParser::getModule()), 'fa fa-edit'));
        \$deleteLink = new ActionButton('{$labels['delete']}', sprintf('?module=%s&action=delete&id={id}', UrlParser::getModule()), 'fa fa-trash');
        \$grid->addActionButton(\$deleteLink);

        if (\$grid->isReadyToHandleRequests()) {
            \$grid->bootstrap();
        }

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
     * format: [
     *  sql = "sql statement"
     *  columns [
     *      alias => [
     *          0 => table name
     *          1 => column name
     *      ]
     *  ]
     *
     * ]
     * @return array
     */
    private function buildLlistSql(): array
    {
        $className = '\\'.$this->getConfig()['namespace'].'\\'.$this->getTable()->getNameAsClassname();
        $thisTable = (new $className)->getTable();
        $columns = [];
        $joins = [];
        foreach ($thisTable->getFields() as $field) {
            if ($field->isForeignKey()) {
                $fkClassName = '\\'.$this->getConfig()['namespace'].'\\'.$thisTable->getForeignKey($field->getName())->getTable()->getName();
                $fkTable = new $fkClassName;

                $columns[$thisTable->getName() . '_' . $field->getName()] = [$thisTable->getName(), $field->getName(), true];
                $columns[$fkTable->getName() . '_' . $field->getName()] = [$fkTable->getName(), $field->getName(), false];

                $joins[] = "LEFT JOIN `" . $fkTable->getName() . "` ON `" . $thisTable->getName() . '`.`' . $field->getName() . '` = `' . $fkTable->getName() . '`.`' . $thisTable->getForeignKey($field->getName())->getReferencingField() . '`';
            } else {
                $columns[$thisTable->getName() . '_' . $field->getName()] = [$thisTable->getName(), $field->getName(), true];
            }
        }

        $sql = "SELECT\n";
        foreach ($columns as $alias => $column) {
            $sql .= "\t, `" . $column[0] . '`.`' . $column[1] . '` AS ' . $alias;
        }
        $sql .= "FROM " . $thisTable->getName() . "\n";
        $sql .= join("\n", $joins);

        return [
            'sql' => "SELECT * FROM ({$sql})l1 ",
            'columns' => $columns
        ];

    }


}