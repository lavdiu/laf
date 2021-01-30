<?php

namespace Laf\UI\Grid\PhpGrid;

use Box\Spout\Common\Entity\Style\Border;
use Box\Spout\Common\Entity\Style\Color;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Writer\Common\Creator\Style\BorderBuilder;
use Box\Spout\Writer\Common\Creator\Style\StyleBuilder;
use Box\Spout\Writer\Exception\WriterNotOpenedException;
use Laf\Database\BaseObject;
use Laf\Database\Db;
use Laf\Util\Util;
use Laf\UI\Grid\PhpGrid\Column;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use PhpOffice\PhpSpreadsheet\Shared\Font;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


/**
 * Class PhpGrid
 * @package Laf\UI\Grid\PhpGrid
 */
class PhpGrid
{
    /**
     * @var string
     */
    protected $grid_name = "";

    /**
     * @var string
     */
    protected $title = "";

    /**
     * @var array
     */
    protected $params_list = [];

    /**
     * @var array
     */
    protected $column_list = [];

    /**
     * Query set by the user
     * @var string
     */
    protected $sql_query = null;

    /**
     * query generated by appliying filters and search
     * @var string
     */
    protected $generated_sql_query = null;
    /**
     * query generated for counting
     * @var string
     */
    protected $generated_sql_count_query = null;

    /**
     * @var ActionButton[]
     */
    protected $actionButtons = [];

    /**
     * @var int
     */
    protected $rows_per_page = 10;

    /**
     * @var array
     */
    protected $filters = [];

    /**
     * @var array
     */
    protected $sortDetails = ['field' => null, 'dir' => 'ASC'];

    /**
     * @var int
     */
    protected $row_count = 0;
    /**
     * @var int
     */
    protected $page_count = 0;

    /**
     * results will be stored here
     * @var array
     */
    protected $data = [];

    /**
     * @var string
     */
    protected $errorMessage = "";

    /**
     * @var bool
     */
    protected $allowExport = true;

    /**
     * PhpGrid constructor.
     * @param string $gridName
     * @param array $params_list
     * @param array $filters
     * @throws \Exception
     */

    public function __construct(string $gridName, array $params_list = [], array $filters = [])
    {
        $this->setGridName($gridName);
        $this->setFilters($filters);
        if (!$this->hasFilters()) {
            $this->setFilters($_GET);
        }
        $this->setParamsList($params_list);
    }

    /**
     * @return string
     */
    public function getGridName(): string
    {
        return $this->grid_name;
    }

    /**
     * @param string $grid_name
     * @return PhpGrid
     */
    public function setGridName(string $grid_name): PhpGrid
    {
        $this->grid_name = str_replace(' ', '_', $grid_name);
        return $this;
    }

    /**
     * @return array
     */
    public function getSortDetails(): array
    {
        return $this->sortDetails;
    }

    /**
     * @param string $field
     * @param string $direction
     * @return $this
     */
    public function setSortDetails(string $field, string $direction): PhpGrid
    {
        $this->sortDetails = ['field' => $field, 'dir' => $direction];
        return $this;
    }


    /**
     * @return null
     */
    public function getSqlQuery()
    {
        return $this->sql_query;
    }

    /**
     * @param null $sql_query
     * @return PhpGrid
     */
    public function setSqlQuery($sql_query)
    {
        $this->sql_query = $sql_query;
        return $this;
    }

    /**
     * @return string
     */
    public function getGeneratedSqlQuery(): string
    {
        return $this->generated_sql_query;
    }

    /**
     * @param string $generated_sql_query
     * @return PhpGrid
     */
    private function setGeneratedSqlQuery(string $generated_sql_query): PhpGrid
    {
        $this->generated_sql_query = $generated_sql_query;
        return $this;
    }

    /**
     * @return string
     */
    public function getGeneratedSqlCountQuery(): string
    {
        return $this->generated_sql_count_query;
    }

    /**
     * @param string $generated_sql_count_query
     * @return PhpGrid
     */
    private function setGeneratedSqlCountQuery(string $generated_sql_count_query): PhpGrid
    {
        $this->generated_sql_count_query = $generated_sql_count_query;
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
     * @return PhpGrid
     */
    public function setTitle(string $title): PhpGrid
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @param array $filters
     * @return $this
     */
    public function setFilters(array $filters = []): PhpGrid
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * @return array
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @return int
     */
    public function getFiltersCount(): int
    {
        return count($this->getFilters());
    }

    /**
     * @return bool
     */
    public function hasFilters(): bool
    {
        return $this->getFiltersCount() > 0;
    }

    /**
     * @param array $params_list
     * @return $this
     */
    public function setParamsList(array $params_list = []): PhpGrid
    {
        $this->params_list = $params_list;
        return $this;
    }

    /**
     * @return array
     */
    public function getParamsList(): array
    {
        return $this->params_list;
    }

    /**
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function addParam(string $key, string $value): PhpGrid
    {
        $this->params_list[$key] = $value;
        return $this;
    }

    /**
     * @return ActionButton[]
     */
    public function getActionButtons(): array
    {
        return $this->actionButtons;
    }

    /**
     * @param ActionButton[] $actionButtons
     * @return PhpGrid
     */
    public function setActionButtons(array $actionButtons): PhpGrid
    {
        $this->actionButtons = $actionButtons;
        return $this;
    }

    /**
     * @param ActionButton $button
     * @return PhpGrid
     */
    public function addActionButton(ActionButton $button): PhpGrid
    {
        $this->actionButtons[] = $button;
        return $this;
    }


    /**
     * @param Column $column
     * @return $this
     */
    public function addColumn(Column $column): PhpGrid
    {
        $column->setIndex($this->getColumnsCount());
        $this->column_list[$column->getFieldName()] = $column;
        return $this;
    }

    /**
     * @return Column[]
     */
    public function getColumnsList(): array
    {
        return $this->column_list;
    }

    /**
     * @return int
     */
    public function getColumnsCount(): int
    {
        return count($this->column_list);
    }

    /**
     * @return string
     */
    public function getFirstColumnName(): string
    {
        $cols = array_keys($this->getColumnsList());
        return array_shift($cols);
    }

    /**
     * @param string $field_name
     * @return bool
     */
    public function hasColumn(string $field_name): bool
    {
        return array_key_exists($field_name, $this->column_list);
    }

    /**
     * @param string $fieldName
     * @return \Laf\UI\Grid\PhpGrid\Column|null
     */
    public function getColumn(string $fieldName): ?Column
    {
        if (array_key_exists($fieldName, $this->column_list)) {
            return $this->column_list[$fieldName];
        } else {
            return null;
        }
    }

    /**
     * @return int
     */
    public function getRowCount(): int
    {
        return $this->row_count;
    }

    /**
     * @param int $rowCount
     * @return PhpGrid
     */
    private function setRowCount(int $rowCount): PhpGrid
    {
        $this->row_count = $rowCount;
        $this->calculatePageCount();
        return $this;
    }

    /**
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    /**
     * @param string $errorMessage
     * @return PhpGrid
     */
    public function setErrorMessage(string $errorMessage): PhpGrid
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    /**
     * @return int
     */
    public function getPageCount(): int
    {
        return $this->page_count;
    }

    /**
     * @param int $pageCount
     * @return PhpGrid
     */
    public function setPageCount(int $pageCount): PhpGrid
    {
        $this->page_count = $pageCount;
        return $this;
    }

    /**
     * @param bool $allowExport
     * @return $this
     */
    public function setAllowExport(bool $allowExport = true): PhpGrid
    {
        $this->allowExport = $allowExport;
        return $this;
    }

    /**
     * @return bool
     */
    public function getAllowExport(): bool
    {
        return $this->allowExport;
    }

    /**
     * @return int
     */
    public function getRowsPerPage(): int
    {
        return $this->rows_per_page;
    }

    /**
     * @param int $rowsPerPage
     * @return PhpGrid
     */
    public function setRowsPerPage(int $rowsPerPage): PhpGrid
    {
        $this->rows_per_page = $rowsPerPage;
        return $this;
    }

    /**
     * Looks at the amount of rows returned and
     * rows per page to display
     * then calculates the amount of pages it needs to list
     * and stores it in pageCount
     */
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
     * Reads the columns field in the db record object
     * and sets up column settings in $columns property
     * @param BaseObject $dbObject
     * @throws \Exception
     */
    public function loadConfigFromObject(BaseObject $dbObject): void
    {
        $json = $dbObject->getColumnListVal();
        if (!Util::isJson($json)) {
            throw new \Exception("Invalid JSON config for Grid: " . $dbObject->getGridNameVal());
        }
        $jsonDecoded = json_decode($json, true);
        foreach ($jsonDecoded as $c) {
            $this->addColumn(Column::createFromAssocArray($c));
        }
        $this->setGridName($dbObject->getGridNameVal());
        $this->setActionButtons(json_decode($dbObject->getActionButtonsVal(), true));
        $this->setTitle($dbObject->getTitleVal());
        $this->setSqlQuery($dbObject->getSqlQueryVal());
        $this->setRowsPerPage($dbObject->getRowsPerPageVal());
        $this->setParamsList(Util::coalesce(json_decode($dbObject->getParamsListVal(), true), []));
    }


    /**
     * @param bool $getAllRows
     */
    protected function generateSql(bool $getAllRows = false): void
    {
        $filters = $this->filters;
        $page = 0;
        if ($this->getSortDetails()['field'] == '') {
            $this->setSortDetails($this->getFirstColumnName(), 'DESC');
        }

        if ($this->getRowsPerPage() == 0) {
            $this->setRowsPerPage(10);
        }
        $sqlWhere = '';


        if (isset($filters['page']) && is_numeric($filters['page'])) {
            $page = $filters['page'];
            $page--;
        }

        if (isset($filters['start']) && is_numeric($filters['start'])) {
            $start = $filters['start'];
        }

        if (isset($filters['limit']) && is_numeric($filters['limit']) && $filters['limit'] >= 0) {
            $this->setRowsPerPage(((int)$filters['limit']));
        }

        if (isset($filters['sort']) && $this->hasColumn($filters['sort'])) {
            $this->sortDetails['field'] = $filters['sort'];
        }

        if (isset($filters['dir']) && in_array(strtolower($filters['dir']), ['asc', 'desc'])) {
            $this->sortDetails['dir'] = $filters['dir'];
        }

        if (isset($filters['searchParams'])) {
            $sqlWhere .= " WHERE \n\t1=1 ";
            $searchParams = urldecode($filters['searchParams']);
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

        $start = $page * $this->getRowsPerPage();

        $sqlOrderBy = " ORDER BY `{$this->sortDetails['field']}` {$this->sortDetails['dir']} \n";

        $sqlLimit = "";
        if ($this->getRowsPerPage() > 0 && !$getAllRows) {
            $sqlLimit .= " LIMIT {$start}, {$this->getRowsPerPage()} \n";
        }

        $this->setGeneratedSqlQuery("
            #grid query: {$this->getGridName()}  
            SELECT 
            " . join(',', array_keys($this->getColumnsList())) . " 
            FROM (
                {$this->getSqlQuery()}
            ) {$this->getGridName()} 
            {$sqlWhere}
            {$sqlOrderBy}
            {$sqlLimit}
            "
        );

        $this->setGeneratedSqlCountQuery("
            #grid counter: {$this->getGridName()}  
            SELECT 
                COUNT(*) as total_number_of_rows 
            FROM (
                SELECT 
                " . $this->getFirstColumnName() . " 
                FROM (
                    {$this->getSqlQuery()}
                ) {$this->getGridName()} 
                {$sqlWhere}
            ) {$this->getGridName()}_count
        "
        );

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
        if (strlen($this->getSqlQuery()) < 10) {
            $this->errorMessage = "Missing SQL Query";
            return false;
        }
        if ($this->getColumnsCount() < 2) {
            $this->errorMessage = "Missing column information";
            return false;
        }
        if ($this->getGridName() == '') {
            $this->errorMessage = "Missing grid name";
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
            $stmt = $db->prepare($this->getGeneratedSqlQuery());
            foreach ($this->getParamsList() as $k => $v) {
                $stmt->bindValue(':' . $k, $v);
            }
            $stmt->execute();
            $this->data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $tmp = $stmt->getColumnMeta($i);
                $this->getColumn($tmp['name'])->setFormat($this->convertNativeDataTypeToString($tmp['native_type']));
                $this->setRowCount(count($this->data));
            }

            if (!is_array($this->data)) {
                return false;
            }

            if (!$getAllRows) {
                $stmtCount = $db->prepare($this->getGeneratedSqlCountQuery());
                foreach ($this->getParamsList() as $k => $v) {
                    $stmtCount->bindValue(':' . $k, $v);
                }
                $stmtCount->execute();
                $this->setRowCount(($stmtCount->fetchObject())->total_number_of_rows);
            }


        } catch (\Exception $ex) {
            $this->errorMessage = 'An error has occurred while generating grid data';
            return false;
        }
        return true;
    }

    /**
     * @throws IOException
     * @throws WriterNotOpenedException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
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

    /**
     * Export data to Excel using Spreadsheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws Exception
     */
    public function exportToExcelPhpExcel()
    {
        if (!$this->getAllowExport()) {
            return null;
        }
        ob_clean();
        $_column = 'A';
        $_row = 1;
        $workbook = new Spreadsheet();
        $fileName = $this->getGridName() . date(' (Y-m-d Hi)');

        $sheet = $workbook->getActiveSheet();

        /**
         * set heading row
         */
        $headingRow = [];
        foreach ($this->getColumnsList() as $column) {
            $headingRow[] = $column->getLabel();
        }
        $sheet->fromArray($headingRow, '', $_column . $_row);
        $sheet->getStyle('A1:' . $this->columnNumberToLetter($this->getColumnsCount()) . '1')->applyFromArray([
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

    /**
     * Export data to Excel via Spout
     * @throws IOException
     * @throws WriterNotOpenedException
     */
    public function exportToExcelSpout()
    {
        $fileName = $this->getGridName() . date(' (Y-m-d Hi)');

        ob_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        if (!$this->getAllowExport()) {
            return null;
        }

        $w = WriterEntityFactory::createXLSXWriter();
        $w->openToBrowser($fileName);

        $headingRow = [];
        foreach ($this->getColumnsList() as $column) {
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
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
    }

    public function exportToCsv()
    {
        if (!$this->getAllowExport()) {
            return null;
        }


    }

    /**
     * Output Json clearing the buffer and setting proper json headers
     * If the results are not generated, it would re-run it
     * @param bool $reload_results
     * @throws \Exception
     */
    public function outputJsonWithHeaders(bool $reload_results = false)
    {
        ob_clean();
        header('Content-Type: application/json');
        if (count($this->data) < 1 || $reload_results) {
            $this->execute();
            $data = [
                'success' => $this->getErrorMessage() == "",
                'id' => 1,
                'name' => $this->getGridName(),
                'title' => $this->getTitle(),
                'columns' => $this->getColumnsList(),
                'columnCount' => $this->getColumnsCount(),
                'actionButtons' => $this->getActionButtons(),
                'message' => $this->getErrorMessage(),
                'pageCount' => $this->getPageCount(),
                'rowsPerPage' => $this->getRowsPerPage(),
                'allowExport' => $this->getAllowExport(),
                'rowCount' => $this->getRowCount(),
                'user_query' => $sql = (isLive() ? null : $this->getSqlQuery()),
                'generated_query' => $sql = (isLive() ? null : $this->getGeneratedSqlQuery()),
                'generated_counter_query' => $sql = (isLive() ? null : $this->getGeneratedSqlCountQuery()),
                'rows' => $this->data
            ];
            echo json_encode($data);

        } else {
            echo [];
        }
        ob_end_flush();
        exit;
    }

    /**
     * @return string
     */
    public function draw(): string
    {
        $gridName = $this->getGridName();
        $header = "";
        foreach ($this->getColumnsList() as $column) {
            $header .= "<th>" . $column->getLabel() . '</th>';
        }

        $html =
            "
<script type='text/javascript'>
	grid['{$gridName}'] = new Grid('{$gridName}');
	window.grid['{$gridName}']._rowsPerPage = {$this->getRowsPerPage()};
	\$(document).ready(function () {
	     window.grid['{$gridName}'].initialize();
	});
</script>
<div class='table-responsive' style='position:relative'>
	<table id='{$gridName}' data-component-type='Grid' class='table table-striped table-bordered table-hover table-sm table-responsive-md'  style='margin-bottom:0;'>
		<thead id='{$gridName}_thead' class='thead-light'>
			<tr>
				<th style='text-align: left'  id='{$gridName}_title'></th>
				<th style='text-align: right' id='{$gridName}_buttons'></th>
			</tr>
			<tr></tr>
			<tr class='d-print-none'></tr>
		</thead>
		<tbody id='{$gridName}_tbody'>
		</tbody>
		<tfoot>
		</tfoot>
	</table>
	
	<div>
		<div class='d-flex justify-content-between'>
			<div id='{$gridName}_paginationInfoSection' class='m-0 py-2 small'></div>
			<div class='row m-0 py-2'>
				<span class='dropdown'>
				  <button title='Choose how many rows to show per page' class='btn btn-outline-secondary btn-sm dropdown-toggle' type='button' id='{$gridName}_rowsPerPageSelector' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>10</button>
				  <span class='dropdown-menu text-right' aria-labelledby='{$gridName}_pagesPerRowSelector'>
				    <a class='dropdown-item' href='javascript:;' onclick=\"window.grid['{$gridName}'].setRowsPerPage(10);\">10</a>
				    <a class='dropdown-item' href='javascript:;' onclick=\"window.grid['{$gridName}'].setRowsPerPage(50);\">50</a>
				    <a class='dropdown-item' href='javascript:;' onclick=\"window.grid['{$gridName}'].setRowsPerPage(100);\">100</a>
				    <!-- <a class='dropdown-item' href='javascript:;' onclick=\"window.grid['{$gridName}'].setRowsPerPage(1000);\">1000</a> -->
				    <!-- <a class='dropdown-item' href='javascript:;' onclick=\"window.grid['{$gridName}'].setRowsPerPage(0);\">All</a> -->
				  </span>
				</span>
				&nbsp;&nbsp;
				<nav aria-label='Navigation'>
					<ul class='Laf-SimpleTable-Pagination pagination pagination-sm'>
						<li class='page-item'><a id='{$gridName}_paginationFirstPage' href='javascript:;' class='page-link' title='First Page'><i class='fa fa-angle-double-left'></i></a></li>
						<li class='page-item'><a id='{$gridName}_paginationPrevPage' href='javascript:;' class='page-link' title='Previous Page'><i class='fa fa-angle-left'></i></a></li>
						<li class='page-item'><a id='{$gridName}_paginationCurrPage' href='javascript:;' class='page-link' title='Current Page'>1</a></li>
						<li class='page-item '><a  id='{$gridName}_paginationNextPage' href='javascript:;' class='page-link' title='Next Page'><i class='fa fa-angle-right'></i></a></li>
						<li class='page-item'><a id='{$gridName}_paginationLastPage' href='javascript:;' class='page-link' title='Last Page'><i class='fa fa-angle-double-right'></i></a></i>
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

    /**
     * To match excel format 1-A 2-B
     * Example: input = 1; output = A
     * @param $c
     * @return string
     */
    public function columnNumberToLetter($c)
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

    /**
     * Convert native mysql datatype to string
     * @param string $type
     * @return string
     */
    private function convertNativeDataTypeToString(string $type): string
    {
        switch ($this) {
            case "float":
            case "FLOAT":
            case "DOUBLE":
            case "NEWDECIMAL":
                return 'float';
            case "integer":
            case "INTEGER":
            case "LONG":
            case "TINY":
                return 'integer';
            case 'DATE':
                return 'date';
            case 'TIME':
                return 'time';
            case 'DATETIME':
                return 'datetime';
            case 'VAR_STRING':
            default:
                return "string";
        }
    }

    /**
     * Checks if the request has necessary params to handle json requests
     * @return bool
     */
    public function isReadyToHandleRequests()
    {
        return array_key_exists('load_grid_by_name', $this->getFilters());
    }
}
