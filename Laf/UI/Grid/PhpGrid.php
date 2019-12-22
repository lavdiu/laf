<?php


namespace Laf\UI\Grid;

use Box\Spout\Common\Entity\Style\Border;
use Box\Spout\Common\Entity\Style\Color;
use Box\Spout\Writer\Common\Creator\Style\BorderBuilder;
use Box\Spout\Writer\Common\Creator\Style\StyleBuilder;
use Laf\Database\BaseObject;
use Laf\Database\Db;
use Laf\Util\Util;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use PhpOffice\PhpSpreadsheet\Shared\Font;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PhpGrid
{
	/**
	 * @var BaseObject $gridInstance
	 */
	protected $gridInstance = null;
	protected $params_list = [];
	protected $filters = [];
	protected $columns = [];
	protected $rowCount = 0;
	protected $pageCount = 0;
	protected $rowsPerPage = 0;

	protected $data = [];
	protected $errorMessage = "";
	protected $_json_value = null;
	protected $_sql = "";
	protected $_sql_count = "";

	public function __construct(?BaseObject $gridInstance = null, array $params_list = [], array $filters = [])
	{
		$this->setGridInstance($gridInstance);
		$this->filters = $filters;
		$this->loadColumnsFromConfiguration();
		$this->setParamsList($params_list);
	}

	public function setFilters(array $filters = []): PhpGrid
	{
		$this->filters = $filters;
		return $this;
	}

	public function getFilters(): array
	{
		return $this->filters;
	}

	public function setParamsList(array $params_list = []): PhpGrid
	{
		$this->params_list = $params_list;
		return $this;
	}

	public function getParamsList(): array
	{
		return $this->params_list;
	}

	public function addParam(string $key, string $value): PhpGrid
	{
		$this->params_list[$key] = $value;
		return $this;
	}

	/**
	 * @return Grid
	 */
	public function getGridInstance(): ?BaseObject
	{
		return $this->gridInstance;
	}

	/**
	 * @param BaseObject|null $gridInstance
	 * @return PhpGrid
	 */
	public function setGridInstance(?BaseObject $gridInstance): PhpGrid
	{
		$this->gridInstance = $gridInstance;
		return $this;
	}


	public function addColumn(Column $column)
	{
		$column->setIndex($this->getColumnsCount());
		$this->columns[$column->getFieldName()] = $column;
	}

	/**
	 * @return Column[]
	 */
	public function getColumns(): array
	{
		return $this->columns;
	}

	/**
	 * @return int
	 */
	public function getColumnsCount(): int
	{
		return count($this->columns);
	}

	/**
	 * @param string $field_name
	 * @return bool
	 */
	public function hasColumn(string $field_name): bool
	{
		return array_key_exists($field_name, $this->columns);
	}

	public function getColumn(string $fieldName): ?Column
	{
		if (array_key_exists($fieldName, $this->columns)) {
			return $this->columns[$fieldName];
		} else {
			return null;
		}
	}

	/**
	 * @return int
	 */
	public function getRowCount(): int
	{
		return $this->rowCount;
	}

	/**
	 * @param int $rowCount
	 * @return PhpGrid
	 */
	public function setRowCount(int $rowCount): PhpGrid
	{
		$this->rowCount = $rowCount;
		$this->calculatePageCount();
		return $this;
	}

	private function calculatePageCount(): void
	{
		$rowsPerPage = $this->getRowsPerPage();
		if ($rowsPerPage == 0) {
			$this->setPageCount(1);
		} else {
			$rowCount = $this->getRowCount();
			if (!is_numeric($rowsPerPage) || $rowsPerPage == 0) {
				$rowsPerPage = 10;
			}

			$pages = $rowCount / $rowsPerPage;
			$pages = (int)$pages;
			$pages++;
			$this->setPageCount($pages);
		}
	}

	/**
	 * @return int
	 */
	public function getPageCount(): int
	{
		return $this->pageCount;
	}

	/**
	 * @param int $pageCount
	 * @return PhpGrid
	 */
	public function setPageCount(int $pageCount): PhpGrid
	{
		$this->pageCount = $pageCount;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getRowsPerPage(): int
	{
		return $this->rowsPerPage;
	}

	/**
	 * @param int $rowsPerPage
	 * @return PhpGrid
	 */
	public function setRowsPerPage(int $rowsPerPage): PhpGrid
	{
		$this->rowsPerPage = $rowsPerPage;
		return $this;
	}


	/**
	 * Reads the columns field in the db
	 * and sets up column settings in $columns property
	 * @throws \Exception
	 */
	protected function loadColumnsFromConfiguration()
	{
		$json = $this->getGridInstance()->getColumnListVal();
		if (!Util::isJson($json)) {
			throw new \Exception("Invalid JSON config for Grid: " . $this->getGridInstance()->getGridNameVal());
		}
		$jsonDecoded = json_decode($json, true);
		foreach ($jsonDecoded as $c) {
			$this->addColumn(Column::createFromAssocArray($c));
		}
	}

	/**
	 * @param bool $getAllRows
	 */
	protected function generateSql(bool $getAllRows = false): void
	{
		$filers = $this->filters;
		$page = 0;
		$sort = array_keys($this->getColumns())[0];
		$dir = 'ASC';
		$limit = $this->getGridInstance()->getRowsPerPageVal() ?? 10;
		if ($this->getRowsPerPage() == 0) {
			$this->setRowsPerPage($limit);
		}
		$sqlWhere = '';


		if (isset($filers['page']) && is_numeric($filers['page'])) {
			$page = $filers['page'];
			$page--;
		}

		if (isset($filers['start']) && is_numeric($filers['start'])) {
			$start = $filers['start'];
		}

		if (isset($filers['limit']) && is_numeric($filers['limit']) && $filers['limit'] >= 0) {
			$limit = $filers['limit'];
			$this->setRowsPerPage($limit);
		}

		if (isset($filers['sort']) && $this->hasColumn($filers['sort'])) {
			$sort = $filers['sort'];
		}

		if (isset($filers['dir']) && in_array(strtolower($filers['dir']), ['asc', 'desc'])) {
			$dir = $filers['dir'];
		}

		if (isset($filers['searchParams'])) {
			$sqlWhere .= " WHERE \n\t1=1 ";
			$searchParams = urldecode($filers['searchParams']);
			$searchParams = json_decode($searchParams);

			for ($i = 0; $i < count($searchParams); $i++) {
				$filterItem = $searchParams[$i];
				$operator = $this->getOperator($filterItem->operator);
				$value = $filterItem->value;
				$property = $filterItem->property;

				if (!$this->hasColumn($property))
					continue;

				$sqlWhere .= "\n\tAND `" . $property . "` " . $operator . " :" . $property;
				if ($operator == 'LIKE') {
					$this->addParam($property, $value . '%');
				} else {
					$this->addParam($property, $value);
				}
			}
		}

		$start = $page * $limit;


		$sqlWhere .= " ORDER BY `$sort` $dir \n";

		$sqlLimit = "";
		if ($limit > 0 && !$getAllRows)
			$sqlLimit .= " LIMIT {$start}, {$limit} \n";

		$this->_sql = "SELECT 
		" . join(',', array_keys($this->getColumns())) . " 
		FROM (\n {$this->getGridInstance()->getSqlQueryVal()} \n) {$this->getGridInstance()->getGridNameVal()} \n{$sqlWhere}\n{$sqlLimit}\n";

		$this->_sql_count = "SELECT COUNT(*) as total_number_of_rows 
		FROM (\n {$this->getGridInstance()->getSqlQueryVal()} \n) {$this->getGridInstance()->getGridNameVal()} \n{$sqlWhere}\n";

	}

	/**
	 * @param $operator
	 * @return string
	 */
	protected function getOperator($operator): string
	{
		switch ($operator) {
			case 'lt':
				return '<';
				break;
			case 'gt':
				return '>';
				break;
			case 'lteq':
				return '<=';
				break;
			case 'gteq':
				return '>=';
				break;
			case 'eq':
			case 'stricteq':
				return '=';
				break;
				break;
			case 'noteq':
			case 'notstricteq':
				return '!=';
				break;
			case 'like':
				return 'LIKE';
				break;
		}
	}

	/**
	 * @return bool
	 */
	protected function isValid()
	{
		$obj = $this->getGridInstance();
		if (!is_numeric($obj->getIdVal())) {
			$this->errorMessage = "Unable to find a Grid for id " . $obj->getIdVal();
			return false;
		}
		if (strlen($obj->getSqlQueryVal()) < 10) {
			$this->errorMessage = "Missing query information for Grid id " . $obj->getIdVal();
			return false;
		}
		if ($this->getColumnsCount() < 2) {
			$this->errorMessage = "Missing column information for Grid id " . $obj->getIdVal();
			return false;
		}
		if ($obj->getGridNameVal() == '') {
			$this->errorMessage = "Grid Information not found for name " . $obj->getGridNameVal();
			return false;
		}
		if (coalesce($obj->getExpectedParamsCountVal(), 0) > count($this->getParamsList())) {
			$this->errorMessage = "Missing paramenters for grid id " . $obj->getIdVal();
			return false;
		}
		return true;
	}

	/**
	 * Runs the query and generates JSON data
	 * Store JSON data in _json_value
	 * and return it
	 * @param bool $getAllRows
	 * @return bool
	 * @throws \Exception
	 */
	public function execute(bool $getAllRows = false): bool
	{
		$data = [];

		if (!$this->isValid()) {
			return false;
		}

		$this->generateSql($getAllRows);

		$db = Db::getInstance();
		try {
			$stmt = $db->prepare($this->_sql);
			foreach ($this->getParamsList() as $k => $v) {
				$stmt->bindValue(':' . $k, $v);
			}
			$stmt->execute();
			$this->data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
			if (!is_array($this->data)) {
				return false;
			}

			if (!$getAllRows) {
				$stmtCount = $db->prepare($this->_sql_count);
				foreach ($this->getParamsList() as $k => $v) {
					$stmtCount->bindValue(':' . $k, $v);
				}
				$stmtCount->execute();
				$this->setRowCount(($stmtCount->fetchObject())->total_number_of_rows);
			}


		} catch (\Exception $ex) {
			$this->errorMessage = 'An error has occurred while generatng grid data';
			return false;
		}
		return true;
	}

	public function bootstrap()
	{
		if (array_key_exists('export_grid_to_excel', $this->filters) && $this->filters['export_grid_to_excel'] == 1) {
			$this->exportToExcelPhpExcel();
		} else if (array_key_exists('export_grid_to_excel', $this->filters) && $this->filters['export_grid_to_excel'] == 2) {
			$this->exportToExcelSpout();
		} else if (array_key_exists('export_grid_to_csv', $this->filters)) {
			$this->exportToCsv();
		} else {
			$this->outputJsonWithHeaders();
		}
	}

	public
	function exportToExcelPhpExcel()
	{
		ob_clean();
		$_column = 'A';
		$_row = 1;
		$workbook = new Spreadsheet();
		$fileName = $this->getGridInstance()->getGridNameVal() . date(' (Y-m-d H.i)');

		$sheet = $workbook->getActiveSheet();

		/**
		 * set heading row
		 */
		$headingRow = [];
		foreach ($this->getColumns() as $column) {
			$headingRow[] = $column->getLabel();
		}
		$sheet->fromArray($headingRow, '', $_column . $_row);
		$sheet->getStyle('A1:' . $this->columnNumberToLetter(count($this->columns)) . '1')->applyFromArray([
			'font' => [
				'bold' => true,
				'color' => ['rgb' => 'ffffff']
			],
			'alignment' => ['horizontal' => 'center'],
			'fill' => [
				'fillType' => 'solid',
				'color' => ['rgb' => '000000']
			]

		]);
		$_row++;


		$this->execute(true);
		foreach ($this->data as $row) {
			$sheet->fromArray($row, '', $_column . $_row);
			$_row++;
		}


		/**
		 * Set auto width
		 */
		Font::setAutoSizeMethod(Font::AUTOSIZE_METHOD_APPROX);
		$cellIterator = $sheet->getRowIterator()->current()->getCellIterator();
		$cellIterator->setIterateOnlyExistingCells(true);
		foreach ($cellIterator as $cell) {
			$sheet->getColumnDimension($cell->getColumn())->setAutoSize(true);
		}

		$sheet->setSelectedCell('A1');
		$writer = new Xlsx($workbook);


		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $fileName . '"');
		header('Cache-Control: max-age=0');
		$writer->save('php://output');


		//log exec time
		//log peak memory
		exit;
	}

	public
	function exportToExcelSpout()
	{
		$w = WriterEntityFactory::createXLSXWriter();
		$fileName = $this->getGridInstance()->getGridNameVal() . date(' (Y-m-d H.i)');
		$w->openToBrowser($fileName);

		$headingRow = [];
		foreach ($this->getColumns() as $column) {
			$headingRow[] = $column->getLabel();
		}
		$headingRowStyle = ((new StyleBuilder())
			->setFontBold()
			->setFontColor(Color::WHITE)
			->setBackgroundColor(Color::BLACK))->build();

		$border = ((new BorderBuilder())
			->setBorderTop(Color::BLACK, Border::WIDTH_THIN)
			->setBorderRight(Color::BLACK, Border::WIDTH_THIN)
			->setBorderBottom(Color::BLACK, Border::WIDTH_THIN)
			->setBorderLeft(Color::BLACK, Border::WIDTH_THIN)
		)->build();

		$defaultStyle = ((new StyleBuilder())
			->setBorder($border)
		)->build();
		$w->setDefaultRowStyle($defaultStyle);


		$row = WriterEntityFactory::createRowFromArray($headingRow, $headingRowStyle);
		$w->addRow($row);

		$this->execute(true);
		foreach ($this->data as $row) {
			$row = WriterEntityFactory::createRowFromArray($row);
			$w->addRow($row);
		}

		$w->close();
		$w->openToBrowser();
	}

	public
	function exportToCsv()
	{

	}

	/**
	 * Output Json clearing the buffer and setting proper json headers
	 * If the resuls are not generated, it would re-run it
	 * @param bool $reload_results
	 * @throws \Exception
	 */
	public
	function outputJsonWithHeaders(bool $reload_results = false)
	{
		ob_clean();
		header('Content-Type: application/json');
		if (count($this->data) < 1 || $this->_json_value == "" || $reload_results) {
			$this->execute();
			$data = [
				'success' => $this->errorMessage == "" ? true : false,
				'id' => $this->getGridInstance()->getIdVal(),
				'name' => $this->getGridInstance()->getGridNameVal(),
				'title' => $this->getGridInstance()->getTitleVal(),
				'columns' => $this->columns,
				'columnCount' => $this->getColumnsCount(),
				'settings' => json_decode($this->getGridInstance()->getSettingsVal()),
				'message' => $this->errorMessage,
				'pageCount' => $this->getPageCount(),
				'rowsPerPage' => $this->getRowsPerPage(),
				'rowCount' => $this->getRowCount(),
				'query' => $sql = (isLive() ? null : $this->_sql),
				'queryCounter' => $sql = (isLive() ? null : $this->_sql_count),
				'rows' => $this->data
			];
			$this->_json_value = json_encode($data);

		}
		echo $this->_json_value;
		ob_end_flush();
		exit;
	}

	public
	function draw()
	{
		$gridName = $this->getGridInstance()->getGridNameVal();
		$header = "";
		foreach ($this->getColumns() as $column) {
			$header .= "<th>" . $column->getLabel() . '</th>';
		}

		$html =
			"
<script type='text/javascript'>
	grid['{$gridName}'] = new Grid('{$gridName}');
	\$(document).ready(function () {
	     window.grid['{$gridName}'].initialize();   
	});
</script>
<div class='table-responsive' style='position:relative'>
	<table id='{$gridName}' data-component-type='Grid' class='table table-striped table-bordered table-hover table-sm'  style='margin-bottom:0;'>
		<thead id='{$gridName}_thead' class='thead-light'>
			<tr>
				<th style='text-align: left'  id='{$gridName}_title'></th>
				<th style='text-align: right'  id='{$gridName}_buttons'></th>
			</tr>
			<tr></tr>
			<tr></tr>
		</thead>
		<tbody id='{$gridName}_tbody'>
		</tbody>
		<tfoot>
		</tfoot>
			
	</table>
	
	<div>
		<div class='d-flex justify-content-between'>
			<div id='{$gridName}_paginationInfoSection' class='m-0 py-2'></div>
			<div class='row m-0 py-2'>
				<span class='dropdown'>
				  <button title='Choose how many rows to show per page' class='btn btn-outline-primary btn-sm dropdown-toggle' type='button' id='{$gridName}_rowsPerPageSelector' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>10</button>
				  <span class='dropdown-menu text-right' aria-labelledby='{$gridName}_pagesPerRowSelector'>
				    <span class='dropdown-item'>Rows to display</span>
				        <div class='dropdown-divider'></div>
				    <a class='dropdown-item' href='javascript:;' onclick=\"window.grid['{$gridName}'].setRowsPerPage(10);\">10</a>
				    <a class='dropdown-item' href='javascript:;' onclick=\"window.grid['{$gridName}'].setRowsPerPage(50);\">50</a>
				    <a class='dropdown-item' href='javascript:;' onclick=\"window.grid['{$gridName}'].setRowsPerPage(100);\">100</a>
				    <a class='dropdown-item' href='javascript:;' onclick=\"window.grid['{$gridName}'].setRowsPerPage(0);\">All</a>
				  </span>
				</span>
				&nbsp;&nbsp;
				<nav aria-label='Navigation'>
					<ul class='Laf-SimpleTable-Pagination pagination pagination-sm'>
						<li class='page-item'><a id='{$gridName}_paginationFirstPage' href='javascript:;' class='page-link' title='First Page'><i class='fa fa-angle-double-left'></i></a></li>
						<li class='page-item'><a id='{$gridName}_paginationPrevPage' href='javascript:;' class='page-link' title='Previous Page'><i class='fa fa-angle-left'></i></a></li>
						<li class='page-item'><a id='{$gridName}_paginationCurrPage' href='javascript:;' class='page-link' title='Current Page'>1</a></li>
						<li class='page-item '><a  id='{$gridName}_paginationNextPage'href='javascript:;' class='page-link' title='Next Page'><i class='fa fa-angle-right'></i></a></li>
						<li class='page-item'><a id='{$gridName}_paginationLastPage'href='javascript:;' class='page-link' title='Last Page'><i class='fa fa-angle-double-right'></i></a></i>
					</ul>
				</nav>
			</div>
		</div>
	</div>
	<div id='{$gridName}_loader' style='background-color: lightgray; z-index:85; position:absolute; top:0px; left:0px; width:100%; height:100%; opacity:.5; text-align: center;padding:20px; display:none;'>
		<div class='fa-5x'><i class='fas fa-spinner fa-spin' style='color:#000000;'></i></div>
	</div>
</div>
";
		return $html;
	}

	function columnNumberToLetter($c)
	{
		$c = intval($c);
		if ($c <= 0) return '';

		$letter = '';

		while ($c != 0) {
			$p = ($c - 1) % 26;
			$c = intval(($c - $p) / 26);
			$letter = chr(65 + $p) . $letter;
		}

		return $letter;
	}


}
