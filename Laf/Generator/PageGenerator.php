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

	/**
	 * Table constructor.
	 * @param Table $table
	 * @param string[] $config
	 */
	public function __construct(Table $table, array $config)
	{
		$this->table = $table;
		$this->config = $config;
	}

	public function processClass()
	{
		$namespace = $this->getConfig()['namespace'];
		$className = $this->getTable()->getNameAsClassname();
		$tableName = $this->getTable()->getName();
		$instanceName = strtolower($className);
		$file = "<?php
<?php

use {$namespace}}\{$className};
use Laf\UI\Component\Dropdown;
use Laf\UI\Component\Link;
use Laf\UI\Container\ContainerType;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\Form;
use Laf\UI\Page\AdminPage;
use Laf\Util\UrlParser;

\$id = UrlParser::getId();
\{$instanceName} = new {$className}(\$id);
\$form = \{$instanceName}->getForm();

\$page = new AdminPage();
\$page->setTitle(\"<a href='\" . UrlParser::getListLink() . \"'>\" . ucfirst(\{$instanceName}->getTable()->getNameAsClassname()) . '</a>');
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

		\$page->addLink(new Link('Cancel', UrlParser::getViewLink(), 'fas fa-window-close', [], ['btn', 'btn-sm', 'btn-outline-success']));
		break;
	case 'new':
		\$form->setDrawMode(DrawMode::INSERT);
		\$page->addComponent(\$form);
		\$page->addLink(new Link('Cancel', UrlParser::getListLink(), 'fas fa-window-close', [], ['btn', 'btn-sm', 'btn-outline-success']));
		break;
	case 'delete':
		if (\{$instanceName}->recordExists()) {
			if (\{$instanceName}->canSoftDelete()) {
				\{$instanceName}->softDelete();
			} else {
				\{$instanceName}->hardDelete();
			}
		}
		UrlParser::redirectToListPage();
		break;
	case 'view':
		\$form->setDrawMode(DrawMode::VIEW);
		\$page->addComponent(\$form);
		\$page->addLink(new Link('List', UrlParser::getListLink(), 'far fa-list-alt', [], ['btn', 'btn-sm', 'btn-outline-success']));

		\$dd = new Dropdown('Options', '', 'fa fa-cogs');
		\$dd->addCssClass('btn-outline-success')
			->addCssClass('btn-sm');
		\$newLink = new Link('Update', UrlParser::getUpdateLink(), 'fa fa-edit', ['class' => 'btn btn-sm btn-outline-warning']);
		\$deleteLink = new Link('Delete', UrlParser::getDeleteLink(), 'fa fa-trash', ['class' => 'btn btn-sm btn-outline-danger']);
		\$deleteLink->setConfirmationMessage('Are you sure you want to delete this item?\\nThis action cannot be undone!');

		\$dd->addLink(\$newLink)
			->addLink(\$deleteLink);
		\$page->addLink(\$dd);
		break;
	case 'list':
	default:
		\$table = \{$instanceName}->getListAllSimpleTableObject();
		#\$table->setSql(\"SELECT {$this->getColumnsAsCSV()} FROM {$tableName}\");
		\$table->setRowsPerPage(2);
		\$page->addLink(new Link('Add New', UrlParser::getNewLink(), 'fa fa-plus-square', ['class' => 'btn btn-sm btn-outline-success']));

		\$page->addComponent(\$table);
		\$page->setContainerType(ContainerType::TYPE_DEFAULT);
		break;
}
echo \$page->draw();

";
		$this->setPageFile($file);
	}

	/**
	 * Generates and saves class to file
	 * @return PageGenerator
	 */
	public function savePageToFile()
	{
		$this->processClass();
		$file = $this->getConfig()['page_dir'] . '/' . $this->getTable()->getNameAsClassname() . '.page';
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
	 * Get column info for a table
	 * @return array
	 * @throws \Exception
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

	/**
	 * @return string
	 * @throws \Exception
	 */
	public function getColumnsAsCSV()
	{
		$cols = [];
		foreach ($this->getTableColumns() as $col) {
			$cols[] = $col['COLUMN_NAME'];
		}
		return join(', ', $cols);
	}


}