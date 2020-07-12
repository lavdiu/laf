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
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use PhpOffice\PhpSpreadsheet\Shared\Font;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Laf\UI\Grid\PhpGrid\Column;

class PhpGrid
{
    #from db
    protected $grid_name = "";
    protected $title = "";
    protected $params_list = [];
    protected $expected_param_count = 0;
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
    protected $settings = null;
    protected $rows_per_page = 10;

    #class settings
    protected $filters = [];
    protected $row_count = 0;
    protected $page_count = 0;
    protected $columnMetaData = [];

    protected $data = [];
    protected $errorMessage = "";
    protected $_json_value = null;


    /**
     *
     * INSERT INTO `grid` (`id`, `grid_name`, `title`, `params_list`, `expected_params_count`, `sql_query`, `settings`, `rows_per_page`, `created_by`, `created_on`, `updated_by`, `updated_on`, `column_list`) VALUES
     * (1, 'test', 'My First Grid', NULL, 0, 'SELECT  g.grupid as id , g.grupid as label , g.g_grandtotal as page_file , g.g_b as is_default , g.g_b as is_visible , FROM_UNIXTIME(g.g_dataregjistrimit, \'%Y-%m-%d\') as test FROM asm.grup g', '{\"actionButtons\":[{\"label\":\"Edit\",\"href\":\"?modulus=[modulus]&action=[action]&id={id}&edit=1\",\"icon\":null},{\"label\":\"Goog List\",\"href\":\"http://google.com\",\"icon\":\"fa fa-list\"}], \"allowExport\":true}', 10, NULL, NULL, NULL, NULL, '[{\"fieldName\":\"id\",\"label\":\"Id\",\"format\":\"text\",\"href\":\"?module=routing_table&action={label}&view={id}&\",\"innerElementCssStyle\":\"\",\"innerElementCssClass\":\"\",\"outerElementCssStyle\":\"\",\"outerElementCssClass\":\"\",\"visible\":true,\"exportable\":true,\"innerElementAttributes\":\"\",\"outerElementAttributes\":\"\"},{\"fieldName\":\"label\",\"label\":\"Label\",\"format\":\"text\",\"href\":\"\",\"innerElementCssStyle\":\"color:red;\",\"innerElementCssClass\":\"badge\",\"outerElementCssStyle\":\"\",\"outerElementCssClass\":\"\",\"visible\":true,\"exportable\":true,\"innerElementAttributes\":\"\",\"outerElementAttributes\":\"\"},{\"fieldName\":\"page_file\",\"label\":\"Page File\",\"format\":\"text\",\"href\":\"\",\"innerElementCssStyle\":\"\",\"innerElementCssClass\":\"\",\"outerElementCssStyle\":\"font-weight:bold;\",\"outerElementCssClass\":\"label label-danger\",\"visible\":true,\"exportable\":true,\"innerElementAttributes\":\"\",\"outerElementAttributes\":\"\"},{\"fieldName\":\"is_default\",\"label\":\"Is Default Page\",\"format\":\"text\",\"href\":\"\",\"innerElementCssStyle\":\"\",\"innerElementCssClass\":\"\",\"outerElementCssStyle\":\"font-weight:bold;\",\"outerElementCssClass\":\"label label-danger\",\"visible\":false,\"exportable\":true,\"innerElementAttributes\":\"\",\"outerElementAttributes\":\"\"},{\"fieldName\":\"is_visible\",\"label\":\"Is Visible Page\",\"format\":\"text\",\"href\":\"\",\"innerElementCssStyle\":\"\",\"innerElementCssClass\":\"\",\"outerElementCssStyle\":\"font-weight:bold;\",\"outerElementCssClass\":\"label label-danger\",\"visible\":true,\"exportable\":true,\"innerElementAttributes\":\"\",\"outerElementAttributes\":\"\"},{\"fieldName\":\"test\",\"label\":\"Date field\",\"format\":\"text\",\"href\":\"\",\"innerElementCssStyle\":\"\",\"innerElementCssClass\":\"\",\"outerElementCssStyle\":\"\",\"outerElementCssClass\":\"\",\"visible\":true,\"exportable\":true,\"innerElementAttributes\":\"\",\"outerElementAttributes\":\"\"}]');
    *
     */


    /**
     * PhpGrid constructor.
     * @param BaseObject|null $gridInstance
     * @param array $params_list
     * @param array $filters
     * @throws \Exception
     */

    public function __construct(?BaseObject $gridInstance = null, array $params_list = [], array $filters = [])
    {
        $this->setGridInstance($gridInstance);
        $this->filters = $filters;
        $this->loadColumnsFromConfiguration();
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


    public function addColumn(Column $column)
    {
        $column->setIndex($this->getColumnsCount());
        $this->column_list[$column->getFieldName()] = $column;
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
     * @param string $field_name
     * @return bool
     */
    public function hasColumn(string $field_name): bool
    {
        return array_key_exists($field_name, $this->column_list);
    }

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
    public function setRowCount(int $rowCount): PhpGrid
    {
        $this->row_count = $rowCount;
        $this->calculatePageCount();
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
     * Reads the columns field in the db record object
     * and sets up column settings in $columns property
     * @param BaseObject $dbObject
     * @throws \Exception
     */
    protected function loadColumnsFromDbObject(BaseObject $dbObject)
    {
        $json = $dbObject->getColumnListVal();
        if (!Util::isJson($json)) {
            throw new \Exception("Invalid JSON config for Grid: " . $dbObject->getGridNameVal());
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
        $filters = $this->filters;
        $page = 0;
        $sort = array_keys($this->getColumnsList())[0];
        $dir = 'ASC';
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
            $sort = $filters['sort'];
        }

        if (isset($filters['dir']) && in_array(strtolower($filters['dir']), ['asc', 'desc'])) {
            $dir = $filters['dir'];
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


        $sqlWhere .= " ORDER BY `$sort` $dir \n";

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
            {$sqlLimit}
            "
        );

        $this->setGeneratedSqlCountQuery("
            #grid counter: {$this->getGridName()}  
            SELECT 
                COUNT(*) as total_number_of_rows 
            FROM (
                {$this->getSqlQuery()}
            ) {$this->getGridName()} 
            {$sqlWhere}
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
                $this->columnMetaData[$tmp['name']] = $tmp['native_type'];
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
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
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
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function exportToExcelPhpExcel()
    {
        ob_clean();
        $_column = 'A';
        $_row = 1;
        $workbook = new Spreadsheet();
        $fileName = $this->getGridName() . date(' (Y-m-d H.i)');

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
        $w = WriterEntityFactory::createXLSXWriter();
        $fileName = $this->getGridName() . date(' (Y-m-d H.i)');
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
        $w->openToBrowser($fileName);
    }

    public function exportToCsv()
    {

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
        if (count($this->data) < 1 || $this->_json_value == "" || $reload_results) {
            $this->execute();
            $data = [
                'success' => $this->getErrorMessage() == "",
                'id' => 1,
                'name' => $this->getGridName(),
                'title' => $this->getTitle(),
                'columns' => $this->getColumnsList(),
                'columnCount' => $this->getColumnsCount(),
                'settings' => $this->getSettings(),
                'message' => $this->getErrorMessage(),
                'pageCount' => $this->getPageCount(),
                'rowsPerPage' => $this->getRowsPerPage(),
                'rowCount' => $this->getRowCount(),
                'user_query' => $sql = (isLive() ? null : $this->getSqlQuery()),
                'generated_query' => $sql = (isLive() ? null : $this->getSqlQuery()),
                'generated_counter_query' => $sql = (isLive() ? null : $this->getGeneratedSqlCountQuery()),
                'rows' => $this->data
            ];
            $this->_json_value = json_encode($data);

        }
        echo $this->_json_value;
        ob_end_flush();
        exit;
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
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * @param array $settings
     * @return PhpGrid
     */
    public function setSettings(array $settings): PhpGrid
    {
        $this->settings = $settings;
        return $this;
    }


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
	\$(document).ready(function () {
	     window.grid['{$gridName}'].initialize();   
	});
</script>
<div class='table-responsive' style='position:relative'>
	<table id='{$gridName}' data-component-type='Grid' class='table table-striped table-bordered table-hover table-sm table-responsive-md'  style='margin-bottom:0;'>
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
			<div id='{$gridName}_paginationInfoSection' class='m-0 py-2 small'></div>
			<div class='row m-0 py-2'>
				<span class='dropdown'>
				  <button title='Choose how many rows to show per page' class='btn btn-outline-primary btn-sm dropdown-toggle' type='button' id='{$gridName}_rowsPerPageSelector' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>10</button>
				  <span class='dropdown-menu text-right' aria-labelledby='{$gridName}_pagesPerRowSelector'>
				    <span class='dropdown-item'>Rows to display</span>
				        <div class='dropdown-divider'></div>
				    <a class='dropdown-item' href='javascript:;' onclick=\"window.grid['{$gridName}'].setRowsPerPage(10);\">10</a>
				    <a class='dropdown-item' href='javascript:;' onclick=\"window.grid['{$gridName}'].setRowsPerPage(50);\">50</a>
				    <a class='dropdown-item' href='javascript:;' onclick=\"window.grid['{$gridName}'].setRowsPerPage(100);\">100</a>
				    <a class='dropdown-item' href='javascript:;' onclick=\"window.grid['{$gridName}'].setRowsPerPage(1000);\">1000</a>
				    <!-- <a class='dropdown-item' href='javascript:;' onclick=\"window.grid['{$gridName}'].setRowsPerPage(0);\">All</a> -->
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
                return 'integer';
            case "integer":
            case "INTEGER":
            case "LONG":
            case "TINY":
                return 'float';
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


}
