<?php

namespace Laf\Generator;

use Laf\Database\Identifier;
use Laf\Database\Table;
use Laf\Util\Util;

/**
 * Generates the CRUD page (.page file) of a table: list grid, view with
 * related-record tabs, insert/update forms laid out by the model's FormLayout,
 * delete protected by the form's CSRF token.
 *
 * Config: namespace, page_dir, live_page_dir, optional page_factory (class with
 * the method that returns the page shell, default "{namespace}\Factory") and
 * page_factory_method (default "GeneralPage"). Labels: see LABEL_DEFAULTS.
 */
class PageGenerator
{
    /** Label keys the generated page uses and their English defaults */
    public const LABEL_DEFAULTS = [
        'view' => 'View',
        'cancel' => 'Cancel',
        'options' => 'Options',
        'add-new' => 'Add new',
        'update' => 'Update',
        'delete' => 'Delete',
        'list' => 'List',
        'delete-confirmation' => 'Are you sure you want to delete this?',
        'record-saved' => 'Record saved successfully',
        'record-deleted' => 'Record deleted successfully',
        'record-not-found' => 'Record not found',
        'record-not-saved' => 'The record was not saved, please correct the fields marked below',
        'record-not-deleted' => 'The record was not deleted: the link is not valid, open the record and try again',
        'related-information' => 'Related information',
    ];

    private const INTEGER_TYPES = ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint', 'serial', 'bigserial', 'smallserial', 'int2', 'int4', 'int8', 'year'];

    private Table $table;

    /** @var array<string, mixed> */
    private array $config;

    /** @var array<string, string> */
    private array $labelTranslations;

    private bool $writeOnLiveDirectory = false;

    private ?string $pageFile = null;

    /** @var array<string, TableInspectorInterface> inspectors by table name (the page's own table included) */
    private array $inspectors = [];

    private ?string $engine = null;

    /** @var string[] */
    private array $warnings = [];

    /**
     * @param array<string, mixed> $config
     * @param array<string, string> $labelTranslations label key => text (see LABEL_DEFAULTS)
     * @param TableInspectorInterface|null $tableInspector inspector of the page's table; null = TableInspectorFactory::create()
     */
    public function __construct(Table $table, array $config, array $labelTranslations = [], ?TableInspectorInterface $tableInspector = null)
    {
        $this->table = $table;
        $this->config = $config;
        $this->labelTranslations = $labelTranslations;
        if ($tableInspector !== null) {
            $this->addTableInspector($tableInspector);
        }
    }

    /* ───────────── generation ───────────── */

    /**
     * Build the page source (getPageFile())
     */
    public function generatePageFile(): void
    {
        $this->warnings = [];

        $namespace = $this->getNamespace();
        $className = $this->getTable()->getNameAsClassname();
        $tableName = $this->getTable()->getName();
        $instanceName = strtolower($className);
        $labels = $this->getLabels();
        $factoryClass = trim((string)($this->getConfig()['page_factory'] ?? ($namespace . '\\Factory')), '\\');
        $factoryShort = substr($factoryClass, strrpos('\\' . $factoryClass, '\\'));
        $factoryMethod = (string)($this->getConfig()['page_factory_method'] ?? 'GeneralPage');

        $inspector = $this->inspector($tableName);
        if ($inspector->getPrimaryColumnName() === null) {
            $this->warnings[] = sprintf('table %s has no primary key: the generated page cannot view, update or delete rows', $tableName);
        }

        $viewBody = $this->renderRelatedTabs($tableName, $labels);
        $listGrid = $this->buildGrid($tableName);

        $template = <<<'PHP'
<?php

use {ns}\{class};
use Laf\Exception\FormValidationException;
use Laf\UI\Component\Alert;
use Laf\UI\Component\Dropdown;
use Laf\UI\Component\Link;
use Laf\UI\Container\ContainerType;
use Laf\UI\Container\Div;
use Laf\UI\Container\HtmlContainer;
use Laf\UI\Container\TabContainer;
use Laf\UI\Container\TabItem;
use Laf\UI\Flash;
use Laf\UI\Form\DrawMode;
use Laf\UI\Form\Form;
use Laf\UI\Form\FormLayout;
use Laf\UI\Grid\PhpGrid\ActionButton;
use Laf\UI\Grid\PhpGrid\Column;
use Laf\UI\Grid\PhpGrid\PhpGrid;
use Laf\UI\Page\AdminPage;
use Laf\Util\UrlParser;
{dbImport}use {factoryClass};

$id = UrlParser::getId();
$action = UrlParser::getAction();
${instance} = new {class}($id);
$form = ${instance}->getForm($action === 'new' ? DrawMode::INSERT : ($action === 'update' ? DrawMode::UPDATE : DrawMode::VIEW));
{handLayout}
// Searches of autocomplete fields are answered here (JSON) and end the request
$form->handleLookupRequest();

$html = {factoryShort}::{factoryMethod}();
$page = new AdminPage();

$page->setTitle("<a href='" . UrlParser::getListLink() . "' class='text-black text-decoration-none'>{title}</a>");
$page->setTitleIcon('far fa-list-alt');

// Messages queued by the previous request (Flash::success() / Flash::error(), drained once)
foreach (Flash::drain() as $flashAlert) {
	$page->addComponent($flashAlert);
}

if ($form->isSubmitted()) {
{recordStatusDefault}	try {
		$id = $form->processForm();
		Flash::success({L:record-saved});
		UrlParser::redirectToViewPage($id);
		exit;
	} catch (FormValidationException $ex) {
		// the form is drawn again below with the submitted values and the messages
		$page->addComponent(new Alert('', {L:record-not-saved}, Alert::Type_Danger));
	}
}

switch ($action) {
	case 'update':
		if (!${instance}->recordExists()) {
			Flash::error({L:record-not-found});
			UrlParser::redirectToListPage();
			exit;
		}
		$page->setContainerType(ContainerType::TYPE_FLUID);
		$page->addComponent($form);
		$page->addLink(new Link({L:cancel}, UrlParser::getViewLink(), 'fas fa-window-close', [], ['btn', 'btn-sm', 'btn-outline-success']));
		$html->addComponent($page);
		echo $html->draw();
		break;
	case 'new':
		$page->setContainerType(ContainerType::TYPE_FLUID);
		$page->addComponent($form);
		$page->addLink(new Link({L:cancel}, UrlParser::getListLink(), 'fas fa-window-close', [], ['btn', 'btn-sm', 'btn-outline-success']));
		$html->addComponent($page);
		echo $html->draw();
		break;
	case 'delete':
		// the delete links carry the form's CSRF token: a crafted link cannot delete
		if (!$form->isValidCsrfToken($_GET[$form->getCsrfSessionKey()] ?? null)) {
			Flash::error({L:record-not-deleted});
			UrlParser::redirectToListPage();
			exit;
		}
		if (${instance}->recordExists()) {
			if (${instance}->canSoftDelete()) {
				${instance}->softDelete();
			} else {
				${instance}->hardDelete();
			}
			Flash::success({L:record-deleted});
		} else {
			Flash::error({L:record-not-found});
		}
		UrlParser::redirectToListPage();
		exit;
	case 'view':
		if (!${instance}->recordExists()) {
			Flash::error({L:record-not-found});
			UrlParser::redirectToListPage();
			exit;
		}
		$page->setContainerType(ContainerType::TYPE_FLUID);
		$page->addComponent($form);
		$page->addLink(new Link({L:list}, UrlParser::getListLink(), 'far fa-list-alt', [], ['btn', 'btn-sm', 'btn-outline-success']));

		$dd = new Dropdown({L:options}, '', 'fa fa-cogs', true);
		$dd->addCssClass('btn-outline-success')
			->addCssClass('btn-sm');
		$updateLink = new Link({L:update}, UrlParser::getUpdateLink(), 'fa fa-edit', ['class' => 'btn btn-sm btn-outline-warning']);
		$deleteLink = new Link({L:delete}, $form->withCsrfToken(UrlParser::getDeleteLink()), 'fa fa-trash', ['class' => 'btn btn-sm btn-outline-danger']);
		$deleteLink->setConfirmationMessage({L:delete-confirmation});

		$dd->addLink($updateLink)
			->addLink($deleteLink);
		$page->addLink($dd);

		$html->addComponent($page);
{viewBody}
		echo $html->draw();
		break;
	case 'list':
	default:
{listGrid}
		$page->addComponent(new HtmlContainer($grid->draw()));
		$page->addLink(new Link({L:add-new}, UrlParser::getNewLink(), 'fa fa-plus-square', [], ['btn', 'btn-sm', 'btn-outline-success']));
		$page->setContainerType(ContainerType::TYPE_FLUID);
		$html->addComponent($page);
		echo $html->draw();
		break;
}

PHP;
        $replacements = [
            '{ns}' => $namespace,
            '{class}' => $className,
            '{instance}' => $instanceName,
            '{title}' => ucfirst($className),
            '{factoryClass}' => $factoryClass,
            '{factoryShort}' => $factoryShort,
            '{factoryMethod}' => $factoryMethod,
            '{dbImport}' => str_contains($viewBody . $listGrid, 'Db::getInstance()') ? "use Laf\\Database\\Db;\n" : '',
            '{handLayout}' => $this->getAllFieldsCommentedOut($instanceName),
            '{recordStatusDefault}' => $this->buildRecordStatusDefault(),
            '{viewBody}' => $viewBody,
            '{listGrid}' => $listGrid,
        ];
        foreach ($labels as $key => $text) {
            $replacements['{L:' . $key . '}'] = self::lit($text);
        }
        $this->setPageFile(strtr($template, $replacements));
    }

    /**
     * A commented block showing how to replace the model's layout by hand
     */
    private function getAllFieldsCommentedOut(string $instanceName): string
    {
        $html = "/**\n * The form is laid out by {$this->getTable()->getNameAsClassname()}::getFormLayout() (FormLayout::auto() by default).\n"
            . " * To change it here instead, apply a layout:\n *\n"
            . " *\t\$form->applyLayout(\$form->getDrawMode(), FormLayout::auto(\${$instanceName}->getTable())\n"
            . " *\t\t->readOnly(['code'], [DrawMode::UPDATE])\n"
            . " *\t\t->fieldset('Address', ['street', 'city'], 2));\n *\n"
            . " * or build the components yourself (Div(['row']) / Div(['col-lg']) for columns):\n *\n"
            . " *\t\$form->setComponents([])";

        foreach ($this->inspector($this->getTable()->getName())->getColumns() as $column) {
            $name = (string)$column['COLUMN_NAME'];
            $html .= "\n *\t\t->addComponent(\${$instanceName}->get" . Util::tableFieldNameToMethodName($name) . "FormElement())";
        }
        $html .= ";\n */";
        return $html;
    }

    /**
     * If the table has a record_status_id column, generate code to default it to 1 on insert.
     */
    private function buildRecordStatusDefault(): string
    {
        if ($this->inspector($this->getTable()->getName())->getColumns()['record_status_id'] ?? false) {
            return "\tif (\$action == 'new') {\n\t\t\$form->setSubmittedFieldValue('record_status_id', 1);\n\t}\n";
        }
        return '';
    }

    /**
     * The "Related information" tab container of the view page: one grid per foreign key
     * that points at this table (a many-to-many junction shows the table behind it)
     * @param array<string, string> $labels
     */
    private function renderRelatedTabs(string $tableName, array $labels): string
    {
        $inspector = $this->inspector($tableName);
        if (!$inspector->hasReferencingTables()) {
            return '';
        }
        $primary = (string)$inspector->getPrimaryColumnName();
        $references = $inspector->getReferencingTables();
        $perTable = [];
        foreach ($references as $reference) {
            $perTable[$reference['table_name']] = ($perTable[$reference['table_name']] ?? 0) + 1;
        }

        $tabContainerId = $tableName . '_related_tabs';
        $file = "\n\t\t\$tabContainer = new TabContainer(" . self::lit($tabContainerId) . ");\n";
        $usedNames = [];
        foreach ($references as $reference) {
            $refTable = (string)$reference['table_name'];
            $fkColumn = (string)$reference['column_name'];
            $duplicated = ($perTable[$refTable] ?? 0) > 1;
            $junction = $this->detectJunctionTable($refTable, $tableName, $fkColumn);

            if ($junction !== null) {
                $baseName = $junction['other_table'];
                $tabLabel = Util::tableNameToClassName($junction['other_table']);
            } else {
                $baseName = $refTable;
                $tabLabel = Util::tableNameToClassName($refTable);
            }
            if ($duplicated) {
                $tabLabel .= ' (' . Util::tableFieldNameToLabel($fkColumn) . ')';
            }
            $gridVar = self::uniqueName($duplicated ? $baseName . '_' . $fkColumn : $baseName, $usedNames);
            $gridId = $gridVar . '_list';

            if ($junction !== null) {
                $gridDraw = $this->buildManyToManyGrid(
                    $junction['other_table'],
                    $gridVar,
                    $junction['junction_table'],
                    $junction['fk_to_current'],
                    $junction['fk_to_other'],
                    $tableName,
                    $primary,
                    $gridId
                );
            } else {
                // the rows belong to $refTable: its buttons go to that table's own page (view / update, delete stays there)
                $gridDraw = $this->buildGrid($refTable, $gridVar, ['table_name' => $refTable, 'column_name' => $fkColumn], $gridId, $this->moduleForTable($refTable));
            }

            $file .= $gridDraw;
            $file .= "\t\t\${$gridVar}->setDeferInitialize(true);\n\n";
            $file .= "\t\t\$tabItem = (new TabItem(" . self::lit($tabLabel) . "))->setIdPrefix(" . self::lit($tabContainerId) . ");\n";
            $file .= "\t\t\$tabItem->addComponent(new HtmlContainer(\${$gridVar}->draw()));\n";
            $file .= "\t\t\$tabContainer->addComponent(\$tabItem);\n";
        }
        $file .= "\n\t\t\$page2 = new AdminPage();\n"
            . "\t\t\$page2->setTitle(" . self::lit($labels['related-information']) . ")\n"
            . "\t\t\t->addComponent(new HtmlContainer(\$tabContainer->draw()));\n"
            . "\t\t\$html->addComponent(\$page2);\n";
        return $file;
    }

    /**
     * The many-to-many relation a referencing table gives the current table (JunctionDetector rule)
     *
     * @param string $tableName the referencing table to check
     * @param string $currentTable the current/parent table name
     * @param string|null $viaColumn the junction column that must point at the current table
     * @return array{junction_table: string, other_table: string, fk_to_current: string, fk_to_other: string}|null
     */
    private function detectJunctionTable(string $tableName, string $currentTable, ?string $viaColumn = null): ?array
    {
        return JunctionDetector::detect($this->inspector($tableName), $currentTable, $viaColumn);
    }

    /**
     * The list query of a table with its foreign keys joined for display, and the grid columns:
     * alias => [table alias, column, label base, visible]
     *
     * @param array<string, string> $filters table_name + column_name: restrict the rows to
     *                                       that column = the page's id
     * @return array{sql: string, columns: array<string, array{0: string, 1: string, 2: string, 3: bool}>, primary: string, primary_alias: string}
     */
    private function getDbTableDetails(string $tableName, array $filters = []): array
    {
        $columns = [];
        $joins = [];
        $joinedTables = [$tableName];
        $inspector = $this->inspector($tableName);
        $primary = $inspector->getPrimaryColumnName() ?? 'id';

        foreach ($inspector->getColumns() as $column) {
            $columnName = (string)$column['COLUMN_NAME'];
            if (self::columnIsForeignKey($column)) {
                $fkTableName = (string)$column['FOREIGN_KEY']['referenced_table_name'];
                $fkTableCol = (string)$column['FOREIGN_KEY']['referenced_column_name'];
                $fkTableAlias = in_array($fkTableName, $joinedTables, true) ? $fkTableName . '_' . $columnName : $fkTableName;
                $joinedTables[] = $fkTableName;
                $displayCol = $this->inspector($fkTableName)->getDisplayColumnName();

                $columns[$tableName . '_' . $columnName] = [$tableName, $columnName, $columnName, false];
                $columns[$fkTableAlias . '_' . $displayCol] = [$fkTableAlias, $displayCol, $columnName, true];
                $joins[] = 'LEFT JOIN ' . $this->q($fkTableName) . ' ' . $this->q($fkTableAlias)
                    . ' ON ' . $this->q($tableName) . '.' . $this->q($columnName) . ' = ' . $this->q($fkTableAlias) . '.' . $this->q($fkTableCol);
            } else {
                $columns[$tableName . '_' . $columnName] = [$tableName, $columnName, $columnName, true];
            }
        }

        $sql = "\tSELECT";
        $first = true;
        foreach ($columns as $alias => $column) {
            $sql .= $first ? "\n\t\t  " : "\n\t\t, ";
            $sql .= $this->q($column[0]) . '.' . $this->q($column[1]) . ' AS ' . $this->q((string)$alias);
            $first = false;
        }
        $sql .= "\n\tFROM " . $this->q($tableName);
        if ($joins !== []) {
            $sql .= "\n\t" . implode("\n\t", $joins);
        }
        $sql .= "\n\tWHERE 1=1 ";

        if (!empty($filters['table_name']) && !empty($filters['column_name'])) {
            $sql .= ' AND ' . $this->q((string)$filters['table_name']) . '.' . $this->q((string)$filters['column_name'])
                . ' = ' . $this->idExpression((string)$filters['table_name'], (string)$filters['column_name']) . "\n";
        }

        return [
            'sql' => "SELECT * FROM (\n{$sql}\n) l1 ",
            'columns' => $columns,
            'primary' => $primary,
            'primary_alias' => $tableName . '_' . $primary,
        ];
    }

    /**
     * Build a grid for a many-to-many relationship via a junction table.
     * Shows columns from the "other" table, filtered by the current record's id through the junction.
     *
     * @param string $otherTable the target table to display (e.g., 'tags')
     * @param string $gridName variable name for the grid
     * @param string $junctionTable the junction/pivot table (e.g., 'order_tags')
     * @param string $fkToCurrent junction column pointing to the current table (e.g., 'order_id')
     * @param string $fkToOther junction column pointing to the other table (e.g., 'tag_id')
     * @param string $currentTable the current/parent table (e.g., 'orders')
     * @param string $currentPk primary key column of the current table (e.g., 'id')
     * @param string|null $gridId PhpGrid name; default "{gridName}_list"
     */
    public function buildManyToManyGrid(
        string $otherTable,
        string $gridName,
        string $junctionTable,
        string $fkToCurrent,
        string $fkToOther,
        string $currentTable,
        string $currentPk,
        ?string $gridId = null
    ): string {
        $otherDetails = $this->getDbTableDetails($otherTable);
        $labels = $this->getLabels();
        $tableLabel = Util::tableFieldNameToLabel($otherTable);
        $gridId = $gridId ?? $gridName . '_list';

        $innerSql = $otherDetails['sql'];
        $sql = "SELECT l1.* FROM (\n{$innerSql}\n) l1 "
            . 'INNER JOIN ' . $this->q($junctionTable) . ' jt ON l1.' . $this->q($otherDetails['primary_alias']) . ' = jt.' . $this->q($fkToOther) . ' '
            . 'WHERE jt.' . $this->q($fkToCurrent) . ' = ' . $this->idExpression($currentTable, $currentPk);

        $file = "\n\t\t\${$gridName} = new PhpGrid(" . self::lit($gridId) . ");\n"
            . "\t\t\${$gridName}->setTitle(" . self::lit($tableLabel . ' ' . $labels['list']) . ")\n"
            . "\t\t\t->setRowsPerPage(10)\n"
            . "\t\t\t->setSqlQuery(" . self::sqlLiteral($sql) . ");\n";

        // the rows are $otherTable records: open them on that table's page
        $module = $this->moduleForTable($otherTable);
        $file .= $this->renderGridColumns($gridName, $otherDetails, $otherTable, $module);
        $file .= "\n\n\t\t\${$gridName}->addActionButton(new ActionButton(" . self::lit($labels['view']) . ", " . $this->linkExpression('view', $otherDetails['primary_alias'], $module) . ", 'fa fa-eye'));\n"
            . "\n\t\tif (\${$gridName}->isReadyToHandleRequests()) {\n\t\t\t\${$gridName}->bootstrap();\n\t\t}\n";
        return $file;
    }

    /**
     * The list grid of a table with view / update / delete buttons
     * @param array<string, string> $filters table_name + column_name restrict the rows to that column = the page's id
     * @param string|null $gridId PhpGrid name; default "{table}_list"
     * @param string|null $targetModule the module whose page shows the rows; null = the current page (its own list).
     *                                  Another module gets view / update buttons only: deleting is that page's job,
     *                                  with its own CSRF token
     */
    public function buildGrid(string $table_name, string $grid_name = 'grid', array $filters = [], ?string $gridId = null, ?string $targetModule = null): string
    {
        $tableDetails = $this->getDbTableDetails($table_name, $filters);
        $labels = $this->getLabels();
        $tableLabel = Util::tableFieldNameToLabel($table_name);
        $gridId = $gridId ?? $table_name . '_list';
        $primaryAlias = $tableDetails['primary_alias'];
        $confirm = 'return confirm(' . json_encode($labels['delete-confirmation'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) . ')';

        $file = "\n\t\t\${$grid_name} = new PhpGrid(" . self::lit($gridId) . ");\n"
            . "\t\t\${$grid_name}->setTitle(" . self::lit($tableLabel . ' ' . $labels['list']) . ")\n"
            . "\t\t\t->setRowsPerPage(10)\n"
            . "\t\t\t->setSqlQuery(" . self::sqlLiteral($tableDetails['sql']) . ");\n";

        $file .= $this->renderGridColumns($grid_name, $tableDetails, $table_name, $targetModule);

        $file .= "\n\n\t\t\${$grid_name}->addActionButton(new ActionButton(" . self::lit($labels['view']) . ", " . $this->linkExpression('view', $primaryAlias, $targetModule) . ", 'fa fa-eye'));\n"
            . "\t\t\${$grid_name}->addActionButton(new ActionButton(" . self::lit($labels['update']) . ", " . $this->linkExpression('update', $primaryAlias, $targetModule) . ", 'fa fa-edit'));\n";
        if ($targetModule === null) {
            $file .= "\t\t\$deleteLink = new ActionButton(" . self::lit($labels['delete']) . ", \$form->withCsrfToken(" . $this->linkExpression('delete', $primaryAlias, null) . "), 'fa fa-trash');\n"
                . "\t\t\$deleteLink->addAttribute('onclick', " . self::lit($confirm) . ");\n"
                . "\t\t\${$grid_name}->addActionButton(\$deleteLink);\n";
        }
        $file .= "\n\t\tif (\${$grid_name}->isReadyToHandleRequests()) {\n\t\t\t\${$grid_name}->bootstrap();\n\t\t}\n";
        return $file;
    }

    /**
     * The module whose page shows a table's records: config "module_for_table"
     * (table => module, or a callable taking the table name), else the table name,
     * which is what --install-pages names the page file after
     */
    public function moduleForTable(string $table): string
    {
        $map = $this->getConfig()['module_for_table'] ?? null;
        if (is_callable($map)) {
            return (string)$map($table);
        }
        if (is_array($map) && isset($map[$table])) {
            return (string)$map[$table];
        }
        return $table;
    }

    /**
     * PHP source of a grid link: the current module is read at runtime
     * (sprintf with UrlParser::getModule()), another module is a literal
     * @param string $idAlias the grid column holding the key, used as a {placeholder}
     */
    private function linkExpression(string $action, string $idAlias, ?string $module): string
    {
        if ($module === null) {
            return "sprintf('?module=%s&action={$action}&id={{$idAlias}}', UrlParser::getModule())";
        }
        return self::lit('?module=' . $module . '&action=' . $action . '&id={' . $idAlias . '}');
    }

    /**
     * The addColumn() lines of a grid: the primary key as a link to the view page, the rest as given
     * @param array{columns: array<string, array{0: string, 1: string, 2: string, 3: bool}>, primary: string} $details
     */
    private function renderGridColumns(string $gridName, array $details, string $tableName, ?string $targetModule = null): string
    {
        $file = '';
        foreach ($details['columns'] as $alias => $column) {
            $label = Util::tableFieldNameToLabel($column[2]);
            if ($column[0] === $tableName && $column[1] === $details['primary']) {
                $file .= "\n\t\t\${$gridName}->addColumn(((new Column(" . self::lit((string)$alias) . ", " . self::lit($label) . ", true, true, " . $this->linkExpression('view', (string)$alias, $targetModule) . "))->setInnerElementCssClass('btn btn-sm btn-outline-success'))->setOuterElementCssStyle('width:100px;'));";
            } else {
                if (!$column[3]) {
                    $label .= ' Id';
                }
                $file .= "\n\t\t\${$gridName}->addColumn(new Column(" . self::lit((string)$alias) . ", " . self::lit($label) . ", " . ($column[3] ? 'true' : 'false') . "));";
            }
        }
        return $file;
    }

    /**
     * PHP source, inside the single-quoted SQL string, of the page id as a literal of the
     * column's type: an int cast for integer keys, a quoted value otherwise
     */
    private function idExpression(string $tableName, string $columnName): string
    {
        $type = strtolower((string)($this->inspector($tableName)->getColumns()[$columnName]['DATA_TYPE'] ?? 'int'));
        if (in_array($type, self::INTEGER_TYPES, true)) {
            return "' . ((int)UrlParser::getId()) . '";
        }
        // the page then imports Laf\Database\Db (generatePageFile() looks for this call)
        return "' . Db::getInstance()->quote((string)UrlParser::getId()) . '";
    }

    /* ───────────── files ───────────── */

    /**
     * Return the path where the page file will be stored
     */
    public function getPageFilePath(): string
    {
        $dir = $this->isWriteOnLiveDirectory() ? ($this->getConfig()['live_page_dir'] ?? '') : ($this->getConfig()['page_dir'] ?? '');
        return rtrim((string)$dir, '/\\') . '/' . $this->getTable()->getName() . '.page';
    }

    /**
     * Check if the page file already exists in the filesystem
     */
    public function pageFileExists(): bool
    {
        return file_exists($this->getPageFilePath());
    }

    /**
     * Generates and saves the page to file
     * @throws \RuntimeException
     */
    public function savePageToFile(): static
    {
        $this->generatePageFile();
        $file = $this->getPageFilePath();

        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory: {$dir}");
        }
        if (file_exists($file) && !is_writable($file)) {
            throw new \RuntimeException("File is not writable: {$file}");
        }
        if (file_put_contents($file, (string)$this->getPageFile()) === false) {
            throw new \RuntimeException("Failed to write file: {$file}");
        }
        return $this;
    }

    /* ───────────── accessors ───────────── */

    public function getTable(): Table
    {
        return $this->table;
    }

    public function setTable(Table $table): void
    {
        $this->table = $table;
    }

    /**
     * The inspector of the page's table (created for the engine when none was given)
     */
    public function getTableInspector(): TableInspectorInterface
    {
        return $this->inspector($this->getTable()->getName());
    }

    /**
     * Use this inspector for its table (the page's own or a related one) instead of querying the database
     */
    public function addTableInspector(TableInspectorInterface $inspector): static
    {
        $this->inspectors[$inspector->getTable()] = $inspector;
        return $this;
    }

    /**
     * The engine the generated SQL is quoted for (mysql|postgres); null = the connection's / Settings
     */
    public function setEngine(?string $engine): static
    {
        $this->engine = $engine;
        return $this;
    }

    public function getEngine(): string
    {
        return $this->engine ?? Identifier::engine();
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

    public function getPageFile(): ?string
    {
        return $this->pageFile;
    }

    public function setPageFile(?string $pageFile): void
    {
        $this->pageFile = $pageFile;
    }

    public function isWriteOnLiveDirectory(): bool
    {
        return $this->writeOnLiveDirectory;
    }

    public function setWriteOnLiveDirectory(bool $writeOnLiveDirectory): PageGenerator
    {
        $this->writeOnLiveDirectory = $writeOnLiveDirectory;
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getLabelTranslations(): array
    {
        return $this->labelTranslations;
    }

    /**
     * @param array<string, string> $labelTranslations
     */
    public function setLabelTranslations(array $labelTranslations): PageGenerator
    {
        $this->labelTranslations = $labelTranslations;
        return $this;
    }

    /**
     * Messages about tables the page cannot fully handle (no primary key, ...)
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /* ───────────── internals ───────────── */

    /**
     * The labels with their defaults
     * @return array<string, string>
     */
    private function getLabels(): array
    {
        $labels = [];
        foreach (self::LABEL_DEFAULTS as $key => $default) {
            $labels[$key] = (string)($this->labelTranslations[$key] ?? $default);
        }
        return $labels;
    }

    private function getNamespace(): string
    {
        return trim((string)($this->getConfig()['namespace'] ?? ''), '\\');
    }

    /**
     * The inspected metadata of a table, cached per generator
     */
    private function inspector(string $tableName): TableInspectorInterface
    {
        if (!isset($this->inspectors[$tableName])) {
            $this->inspectors[$tableName] = TableInspectorFactory::create($tableName, $this->getEngine());
        }
        $inspector = $this->inspectors[$tableName];
        $needsInspection = $inspector instanceof AbstractTableInspector ? !$inspector->isInspected() : $inspector->getColumns() === [];
        if ($needsInspection) {
            $inspector->inspect();
        }
        return $inspector;
    }

    /**
     * @param array<string, mixed> $column
     */
    private static function columnIsForeignKey(array $column): bool
    {
        return !empty($column['FOREIGN_KEY']) && is_array($column['FOREIGN_KEY']);
    }

    /**
     * Quote an identifier for the generated SQL
     */
    private function q(string $identifier): string
    {
        return Identifier::quote($identifier, $this->getEngine());
    }

    /**
     * A single-quoted PHP string literal
     * @param mixed $value
     */
    private static function lit($value): string
    {
        return var_export((string)$value, true);
    }

    /**
     * The SQL as a single-quoted PHP literal; the id expression's quotes end and restart the string
     */
    private static function sqlLiteral(string $sql): string
    {
        return "'\n" . str_replace(['\\', "'"], ['\\\\', "\\'"], $sql) . "'";
    }

    /**
     * A PHP variable name not used before
     * @param string[] $used
     */
    private static function uniqueName(string $base, array &$used): string
    {
        $base = (string)preg_replace('/[^A-Za-z0-9_]/', '_', $base);
        if (preg_match('/^[0-9]/', $base)) {
            $base = 't_' . $base;
        }
        $name = $base;
        $n = 2;
        while (in_array($name, $used, true)) {
            $name = $base . '_' . $n++;
        }
        $used[] = $name;
        return $name;
    }
}
