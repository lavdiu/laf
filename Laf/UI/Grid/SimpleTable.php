<?php

namespace Laf\UI\Grid;

use Laf\Database\Db;
use Laf\UI\Component\Dropdown;
use Laf\UI\Component\Link;
use Laf\UI\ComponentInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\Util\Settings;
use Laf\Util\Util;

class SimpleTable implements ComponentInterface
{

	use ComponentTrait;

	protected $sql = '';
	protected $tableUrl = '';
	protected $currentPage = 1;
	protected $totalPages = 0;
	protected $totalRowCount = 0;
	protected $columnCount = 0;
	protected $rowsPerPage = 0;
	protected $columns = [];
	protected $actionButtons = [];
	protected $tableCssClass = null;
	protected $trCssClass = null;
	protected $tdCssClass = null;
	protected $stmt = null;
	protected $showFooter = true;
	protected $sorting = [];
	protected $jsDynamic = false;
	protected $dataTableOptions = [];
	protected $title = "";
	protected $id = "";

	/**
	 * SimpleTable constructor.
	 * @param string $title
	 * @param string $sql
	 */
	public function __construct($title = "", $sql = "")
	{
		$this->setDataTableShowSearch(false)
			->setDataTableLengthChange(false);
		$this->setTitle($title);
		$this->setSql($sql);
	}

	/**
	 * Enables or disables the rows per page field
	 * @param bool $enabled
	 * @return SimpleTable
	 */
	public function setDataTableLengthChange(bool $enabled)
	{
		$this->addDataTableOption('data-length-change', $enabled);
		return $this;
	}

	/**
	 * Add a parameter for DataTable
	 * @param $key
	 * @param $value
	 * @return SimpleTable
	 */
	public function addDataTableOption($key, $value)
	{
		$this->dataTableOptions[$key] = $value;
		return $this;
	}

	/**
	 * Show or hide the search bar
	 * @param bool $enabled
	 * @return SimpleTable
	 */
	public function setDataTableShowSearch(bool $enabled)
	{
		$this->addDataTableOption('data-searching', $enabled);
		return $this;
	}

	/**
	 * @return int
	 */
	public function getTotalRowCount(): int
	{
		return $this->totalRowCount;
	}

	/**
	 * @param int $totalRowCount
	 * @return SimpleTable
	 */
	protected function setTotalRowCount(int $totalRowCount)
	{
		if ($this->getRowsPerPage() == 0) {
			$this->setTotalPages(0);
			return $this;
		}
		$this->totalRowCount = $totalRowCount;
		$totalPages = (int)$this->totalRowCount / $this->getRowsPerPage();
		if ($this->totalRowCount % $this->getRowsPerPage() != 0) {
			$totalPages++;
		}
		$this->setTotalPages($totalPages);
		return $this;
	}

	/**
	 * @return int
	 */
	public function getRowsPerPage(): int
	{
		if ($this->isJsDynamic())
			return 0;
		return (int)$this->rowsPerPage;
	}

	/**
	 * @param int $rowsPerPage
	 * @return SimpleTable
	 */
	public function setRowsPerPage(int $rowsPerPage)
	{
		$rowsPerPage = (int)$rowsPerPage;
		if (!is_numeric($rowsPerPage) || $rowsPerPage < 1)
			$rowsPerPage = 10;
		$this->rowsPerPage = $rowsPerPage;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function isJsDynamic(): bool
	{
		return $this->jsDynamic;
	}

	/**
	 * @param Link|Dropdown $button
	 * @return SimpleTable
	 */
	public function addActionButton($button)
	{
		$this->actionButtons[] = $button;
		return $this;
	}

	public function toExcel()
	{

	}

	/**
	 * @return SimpleTable
	 */
	public function showFooter()
	{
		$this->showFooter = true;
		return $this;
	}

	/**
	 * @return SimpleTable
	 */
	public function hideFooter()
	{
		$this->showFooter = false;
		return $this;
	}

	/**
	 * @param $columnName
	 * @param $sortOrder
	 * @return SimpleTable
	 */
	public function addSortColumn($columnName, $sortOrder)
	{
		$this->sorting[$columnName] = $sortOrder;
		return $this;
	}

	/**
	 * @return SimpleTable
	 */
	public function clearSortColumns()
	{
		$this->sorting = [];
		return $this;
	}

	/**
	 * @return  SimpleTable
	 */
	public function enableJsDynamic(): SimpleTable
	{
		$this->jsDynamic = true;
		return $this;
	}

	/**
	 * @return  SimpleTable
	 */
	public function disableJsDynamic(): SimpleTable
	{
		$this->jsDynamic = false;
		return $this;
	}

	/**
	 * Enables or disables state saving
	 * @param bool $enabled
	 * @return SimpleTable
	 */
	public function setDataTableSaveState(bool $enabled)
	{
		$this->addDataTableOption('data-state-save', $enabled);
		return $this;
	}

	/**
	 * Set rows per page
	 * @param int $length
	 * @return SimpleTable
	 */
	public function setDataTablePageLength($length)
	{
		$this->addDataTableOption('data-page-length', $length);
		return $this;
	}

	/**
	 * Enable or disable searching
	 * @param bool $enabled
	 * @return $this
	 */
	public function setDataTableOrdering(bool $enabled)
	{
		$this->addDataTableOption('data-ordering', $enabled);
		return $this;
	}

	public function __toString()
	{
		return $this->draw();
	}

	/**
	 * Returns the table generated in HTML format
	 * @alias toHTML()
	 * @return string
	 * @throws \Exception;
	 */
	public function draw(): string
	{
		return $this->toHTML();
	}

	/**
	 * Returns the table generated in HTML format
	 * @return string
	 * @throws \Exception
	 */
	public function toHTML(): string
	{
		if ($this->sql === '') {
			return '';
		}

		$this->calculateTotalRows();
		$db = Db::getInstance();

		$sql = "SELECT * FROM (" . $this->getSql() . ") SimpleTableSelect WHERE 1=1 ";
		$sql .= $this->getSqlOrderBySection();
		if ($this->getRowsPerPage() > 0)
			$sql .= " LIMIT " . $this->getRecordsetOffset() . ', ' . $this->getRowsPerPage();
		$this->stmt = $db->query($sql);
		if ($this->stmt === false) {
			throw new \Exception($db->getErrorMessage());
		}

		$rows = $this->stmt->fetchAll(\PDO::FETCH_ASSOC);
		#echo $this->stmt->getColumnMeta(0)['name'];
		if (!$this->hasColumns()) {
			for ($i = 0; $i < $this->stmt->columnCount(); $i++) {
				$columnsFromDb[$this->stmt->getColumnMeta($i)['name']] = Util::tableFieldNameToLabel($this->stmt->getColumnMeta($i)['name']);
			}
			#$columnsFromDb = array_map(array('Laf\Util\Util', 'tableFieldNameToLabel'), array_keys($rows[0]));

			/*if ($this->getColumnCount() == 0 && $this->hasActionButtons()) {
				$columnsFromDb[] = "&nbsp;";
			}*/
			$this->setColumns($columnsFromDb);
		}

		$html = "\n\n<!-- STARTOF SimpleTable -->\n";
		#$html .= "\n<div>";
		$html .= "\n<table 
                id='{$this->getId()}'
                " . ($this->isJsDynamic() ? $this->getDataTableOptionsForHtml() : '') . "
                class='" . ($this->isJsDynamic() ? 'DataTable ' : "") . " Laf-SimpleTable-Table-Class table table-striped table-hover table-responsive table-sm table-bordered {$this->getTableCssClass()}' >\n";
		$html .= ($this->getTitle() ? "\n<caption>{$this->getTitle()}</caption>" : "");
		$html .= $this->getHeaderRow();
		$html .= "\n\t<tbody class='Laf-SimpleTable-TBody-Class'>\n";

		foreach ($rows as $row) {
			$html .= "\t\t<tr class='Laf-SimpleTable-Tr-Class {$this->getTrCssClass()}'>\n";
			foreach ($row as $column => $cell) {
				if ($this->columnExists($column))
					$html .= "\t\t\t<td class='Laf-SimpleTable-Td-Class {$this->getTdCssClass()}'>{$cell}</td>\n";
			}

			if ($this->hasActionButtons()) {
				$html .= "\t\t\t<td class='Laf-SimpleTable-Td-Class Laf-SimpleTable-Td-Action-Col text-right {$this->getTdCssClass()}'>";
				$html .= "\t\t\t\t<div class='btn-group'>";
				foreach ($this->getActionButtons() as $btn) {
					$button = clone $btn;
					$parameters = '';
					$buttonOutput = $button->draw();
					preg_match_all("/{([a-zA-Z0-9_]*)}/", $buttonOutput, $parameters);
					foreach ($parameters[1] as $param) {
						if (!isset($row[$param]))
							$row[$param] = '';
						$buttonOutput = str_replace('{' . $param . '}', $row[$param], $buttonOutput);
					}
					$html .= $buttonOutput;
				}

				$html .= "</div>\n";
				$html .= "</td>\n";
			}
			$html .= "\t\t</tr>\n";
		}

		if ($this->showFooter) {
			$html .= "\t</tbody>\n";
			$html .= $this->getFooterRow();
			$html .= "</table>\n";
			#$html .= "</div>\n<!-- ENDOF SimpleTable {$this->getId()} -->\n\n";
			$html .= "\n<!-- ENDOF SimpleTable {$this->getId()} -->\n\n";
		}
		return $html;
	}

	/**
	 * Will run the sql query without the limit and offset, to count(*) all results
	 */
	protected function calculateTotalRows()
	{
		$db = Db::getInstance();
		$countStmt = $db->query("SELECT COUNT(*) AS totalRowCount FROM (" . $this->getSql() . ") SimpleTableSelectCounter ");
		$countResult = $countStmt->fetchObject();
		if ($countResult === false) {
			throw new \Exception($db->getErrorMessage());
		}
		$this->setTotalRowCount($countResult->totalRowCount);
	}

	/**
	 * @return string
	 */
	public function getSql(): string
	{
		return $this->sql;
	}

	/**
	 * @param string $sql
	 * @return SimpleTable
	 */
	public function setSql(string $sql)
	{
		$this->sql = $sql;
		return $this;
	}

	/**
	 * Of sort order is set
	 * Will return a sorting order in the following fashion:
	 * ORDER BY <column> <order>[] ...
	 * @return string
	 */
	protected function getSqlOrderBySection()
	{
		if (count($this->getSortColumns()) < 1) {
			return '';
		}

		$_sorting = [];
		foreach ($this->getSortColumns() as $_column => $_order) {
			if ($_column != '' && in_array(strtolower($_order), ['asc', 'desc'])) {
				$_sorting[] = $_column . ' ' . $_order;
			}
		}

		if (count($_sorting) > 0) {
			return " ORDER BY " . join(', ', $_sorting) . ' ';
		} else {
			return '';
		}
	}

	/**
	 * @return string[]
	 */
	public function getSortColumns()
	{
		return $this->sorting;
	}

	/**
	 * Returns the calculated offset depending on the page number
	 * @return int
	 */
	protected function getRecordsetOffset()
	{
		$offset = $this->getRowsPerPage() * $this->getCurrentPage() - $this->getRowsPerPage();
		return (int)$offset;
	}

	/**
	 * @return int
	 */
	public function getCurrentPage(): int
	{
		return $this->currentPage;
	}

	/**
	 * @param int $page
	 * @return SimpleTable
	 */
	public function setCurrentPage($page)
	{
		$page = (int)$page;
		if (!is_numeric($page) || $page < 1)
			$page = 1;
		$this->currentPage = $page;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function hasColumns()
	{
		return count($this->columns) > 0;
	}

	/**
	 * @return string
	 */
	public function getId(): string
	{
		return $this->id;
	}

	/**
	 * @param string $id
	 * @return SimpleTable
	 */
	public function setId(string $id): SimpleTable
	{
		$this->id = $id;
		return $this;
	}

	/**
	 * Only used to draw the table
	 * @return string
	 */
	protected function getDataTableOptionsForHtml()
	{
		$html = ' ';
		foreach ($this->getDataTableOptions() as $k => $v) {
			$html .= $k . '="' . $v . '" ';
		}
		return $html;
	}

	/**
	 * Get DataTable Options
	 * @return array
	 */
	public function getDataTableOptions()
	{
		return $this->dataTableOptions;
	}

	/**
	 * @return string
	 */
	public function getTableCssClass()
	{
		return $this->tableCssClass;
	}

	/**
	 * @param string $tableCssClass
	 * @return SimpleTable
	 */
	public function setTableCssClass($tableCssClass)
	{
		$this->tableCssClass = $tableCssClass;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTitle(): string
	{
		return $this->title;
	}

	/**
	 * @param string $title
	 * @return SimpleTable
	 */
	public function setTitle(string $title): SimpleTable
	{
		$this->title = $title;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getHeaderRow()
	{
		if ($this->hasActionButtons())
			$this->addColumn("Actions");

		$header = "\t<thead class='Laf-SimpleTable-THead-Class thead-dark'>
\t\t<tr class='Laf-SimpleTable-Tr-Class {$this->getTrCssClass()}'>
\t\t\t<th class='Laf-SimpleTable-Th-Class {$this->getTdCssClass()}'>" . join("</th>\n\t\t\t<th class='Laf-SimpleTable-Th-Class {$this->getTdCssClass()}'>", $this->getColumns()) . "</th>
\t\t</tr>
\t</thead>";
		return $header;
	}

	/**
	 * @return bool
	 */
	public function hasActionButtons()
	{
		return count($this->actionButtons) > 0;
	}

	/**
	 * @param string $column
	 * @return SimpleTable
	 */
	public function addColumn($column)
	{
		$this->columns[] = $column;
		return $this;
	}

	/**
	 * @return null
	 */
	public function getTrCssClass()
	{
		return $this->trCssClass;
	}

	/**
	 * @param string $trCssClass
	 * @return SimpleTable
	 */
	public function setTrCssClass($trCssClass)
	{
		$this->trCssClass = $trCssClass;
		return $this;
	}

	/**
	 * @return null
	 */
	public function getTdCssClass()
	{
		return $this->tdCssClass;
	}

	/**
	 * @param string $tdCssClass
	 * @return SimpleTable
	 */
	public function setTdCssClass($tdCssClass)
	{
		$this->tdCssClass = $tdCssClass;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getColumns(): array
	{
		return $this->columns;
	}

	/**
	 * @param array $columns
	 * @return SimpleTable
	 */
	public function setColumns(array $columns)
	{
		$this->columns = $columns;
		$this->setColumnCount(count($columns));
		return $this;
	}

	/**
	 * @param $columnName
	 * @return bool
	 */
	public function columnExists($columnName)
	{
		return array_key_exists($columnName, $this->columns);
	}

	/**
	 * @return Link[]
	 */
	public function getActionButtons(): array
	{
		return $this->actionButtons;
	}

	public function getFooterRow()
	{
		if ($this->getTotalPages() <= 1)
			return '';

		$colspan = $this->getColumnCount() + 1;
		$html = "\n\t<tfoot>";
		$html .= "\n\t\t<tr>\n\t\t\t<td colspan='{$colspan}'>";
		$html .= "\n\t\t\t\t<nav aria-label='Navigation'>";
		$html .= "\n\t\t\t\t<ul class='Laf-SimpleTable-Pagination pagination justify-content-end'>";
		$questionMark = strpos($this->getTableUrl(), '?') === false ? "?" : '';
		$html .= "\n\t\t\t\t<li class='page-item " . ($this->hasPreviousPage() ? '' : 'disabled') . "'><a href='{$this->getTableUrl()}{$questionMark}&page=" . ($this->getCurrentPage() - 1) . "' class='page-link'>Previous</a></li>";

		if ($this->getTotalPages() > 10) {
			for ($i = 1; $i <= $this->getTotalPages(); $i++) {
				if($this->getCurrentPage() <= 3 || $this->getCurrentPage() >= ($this->getTotalPages()) || $this->getCurrentPage() <= ($i+2) || $this->getCurrentPage() >= ($i-2))
				$active = $i == $this->getCurrentPage() ? " active " : "";
				$html .= "\n\t\t\t\t<li class='page-item{$active}'><a href='{$this->getTableUrl()}{$questionMark}&page={$i}' class='page-link'>{$i}</a></li>";
			}
		} else {
			for ($i = 1; $i <= $this->getTotalPages(); $i++) {
				$active = $i == $this->getCurrentPage() ? " active " : "";
				$html .= "\n\t\t\t\t<li class='page-item{$active}'><a href='{$this->getTableUrl()}{$questionMark}&page={$i}' class='page-link'>{$i}</a></li>";
			}
		}
		$html .= "\n\t\t\t\t<li class='page-item " . ($this->hasNextPage() ? '' : 'disabled') . "'><a href='{$this->getTableUrl()}{$questionMark}&page=" . ($this->getCurrentPage() + 1) . "' class='page-link'>Next</a></li>";
		$html .= "\n\t\t\t\t</ul>";
		$html .= "\n\t\t\t\t</nav>";
		$html .= "\n\t\t\t</td>\n\t\t</tr>";
		$html .= "\n\t</tfoot>\n";
		return $html;
	}

	/**
	 * @return int
	 */
	public function getTotalPages(): int
	{
		if ($this->isJsDynamic())
			return 0;
		return $this->totalPages;
	}

	/**
	 * @param int $totalPages
	 * @return SimpleTable
	 */
	protected function setTotalPages(int $totalPages)
	{
		$this->totalPages = $totalPages;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getColumnCount(): int
	{
		return $this->columnCount;
	}

	/**
	 * @param int $columnCount
	 * @return SimpleTable
	 */
	protected function setColumnCount(int $columnCount)
	{
		$this->columnCount = $columnCount;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTableUrl(): string
	{
		if ($this->tableUrl == '') {
			unset($_GET['page']);
			foreach ($_GET as $key => $value) {
				if ($key == 'uriRewrite') {
					$this->tableUrl = '/' . $_GET['uriRewrite'] . $this->tableUrl;
				} else {
					$this->tableUrl .= '?' . $key . '=' . $value;
				}
			}
		}
		return $this->tableUrl;
	}

	/**
	 * Set the current page url, so the paginator can set the &page= params
	 * @param string $tableUrl
	 */
	public function setTableUrl(string $tableUrl): void
	{
		$this->tableUrl = $tableUrl;
	}

	protected function hasPreviousPage()
	{
		return $this->getCurrentPage() > 1;
	}

	protected function hasNextPage()
	{
		return $this->getCurrentPage() < $this->getTotalPages();
	}

	/**
	 * Returns the CSS class unique to the UI component
	 * @return string
	 */
	public function getComponentCssControlClass(): string
	{
		return str_replace('\\', '-', static::class);
	}
}
