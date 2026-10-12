<?php

namespace Laf\UI\Grid\PhpGrid;

use Laf\Database\BaseObject;
use Laf\Database\Db;
use Laf\Database\Identifier;
use Laf\Util\Util;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Server side of the AJAX data grid (grid.js is the client). The developer gives it a name, an SQL
 * statement and the columns; draw() renders the table shell and bootstrap() answers the grid's own
 * requests (JSON page, CSV or XLSX export) built from the query string: page, limit, sort, dir,
 * searchParams (see GridRequest) and load_grid_by_name.
 *
 * Typical page:
 *   $grid = new PhpGrid('customer_list');
 *   $grid->setSqlQuery('SELECT id, name, city FROM customer')->setRowsPerPage(20)
 *        ->addColumn(new Column('id', 'ID', true, true, '?module=customer&action=view&id={id}'))
 *        ->addColumn(new Column('name', 'Name'))
 *        ->addActionButton(new ActionButton('Edit', '?module=customer&action=update&id={id}', 'fa fa-edit'));
 *   if ($grid->isReadyToHandleRequests()) { $grid->bootstrap(); }
 *   echo $grid->draw();
 */
class PhpGrid
{
    public const ERR_VALIDATION = 'ERR_VALIDATION';
    public const ERR_RUNTIME = 'ERR_RUNTIME';
    public const ERR_FORBIDDEN = 'ERR_FORBIDDEN';

    protected string $grid_name = '';
    protected string $title = '';
    /** @var array<string, mixed> developer parameters bound into the SQL (:name) */
    protected array $params_list = [];
    /** @var array<string, Column> keyed by field name, in display order */
    protected array $column_list = [];
    protected ?string $sql_query = null;
    protected string $generated_sql_query = '';
    protected string $generated_sql_count_query = '';
    protected string $generated_sql_totals_query = '';
    /** @var array<string, mixed> filter bindings of the last generated statements */
    protected array $generated_bindings = [];
    /** @var array<string, mixed> totals keyed by column name (0 until computed) */
    protected array $column_totals = [];
    /** @var ActionButton[] */
    protected array $actionButtons = [];
    protected int $rows_per_page = 10;
    /** @var array<string, mixed> the request parameters ($_GET copy by default) */
    protected array $filters = [];
    protected ?GridRequest $request = null;
    /** @var array{field: ?string, dir: string} */
    protected array $sortDetails = ['field' => null, 'dir' => 'ASC'];
    protected int $row_count = 0;
    protected int $page_count = 0;
    /** @var array<int, array<string, mixed>> the rows of the last execute() */
    protected array $data = [];
    protected bool $executed = false;
    protected string $errorMessage = '';
    protected ?string $errorCode = null;
    protected bool $allowExport = true;
    protected bool $debug = false;
    protected bool $enableWildCardSearch = false;
    protected bool $showSearchBar = true;
    protected bool $showTitle = true;
    /** @deprecated LIKE on PostgreSQL is always ILIKE now; kept so existing calls keep working */
    protected bool $useIlikeOnPg = false;
    protected float $execMs = 0.0;
    protected float $countMs = 0.0;
    protected ?string $rowLevelJsCallback = null;
    /** Skip the automatic load in draw(): grid.js loads the grid when it becomes visible (hidden tabs) */
    protected bool $deferInitialize = false;
    /** Upper bound for the limit a client may request */
    protected int $maxRowsPerPage = 1000;
    /** @var int[] entries of the rows-per-page menu; 0 = all rows */
    protected array $rowsPerPageOptions = [10, 50, 100];
    /** Emit the inline <script> that registers the grid (false: grid.js's Grid.autoInitAll() picks the table up) */
    protected bool $inlineScript = true;
    /** Prefix the CSV export with a UTF-8 byte order mark (Excel then reads accents correctly) */
    protected bool $csvBom = false;

    /**
     * @param string $gridName identifies the grid in the page; spaces become underscores
     * @param array<string, mixed> $params_list values for :placeholders in the SQL
     * @param array<string, mixed> $filters the request parameters; $_GET when empty
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

    // ─── Configuration ────────────────────────────────────────────────────

    public function getGridName(): string
    {
        return $this->grid_name;
    }

    public function setGridName(string $grid_name): static
    {
        $this->grid_name = str_replace(' ', '_', $grid_name);
        return $this;
    }

    /**
     * @return array{field: ?string, dir: string}
     */
    public function getSortDetails(): array
    {
        return $this->sortDetails;
    }

    /**
     * Default sort, used until the client sorts; the request's sort/dir override it.
     */
    public function setSortDetails(string $field, string $direction): static
    {
        $this->sortDetails = ['field' => $field, 'dir' => $direction];
        return $this;
    }

    public function getSqlQuery(): ?string
    {
        return $this->sql_query;
    }

    /**
     * The statement that produces the rows; it is wrapped as a derived table, so it may order, group
     * and use :placeholders from the params list, but must not end with LIMIT.
     */
    public function setSqlQuery(?string $sql_query): static
    {
        $this->sql_query = $sql_query;
        $this->executed = false;
        return $this;
    }

    public function getGeneratedSqlQuery(): string
    {
        return $this->generated_sql_query;
    }

    public function getGeneratedSqlCountQuery(): string
    {
        return $this->generated_sql_count_query;
    }

    public function getGeneratedSqlTotalsQuery(): string
    {
        return $this->generated_sql_totals_query;
    }

    /**
     * @return array<string, mixed> the filter bindings of the last generated statements (:f0, :f1, ...)
     */
    public function getGeneratedBindings(): array
    {
        return $this->generated_bindings;
    }

    /**
     * Columns to SUM over every matching row (not only the current page); the totals come back in the
     * JSON as columnTotals and grid.js draws them in the table footer.
     * @param string[] $columns
     */
    public function setColumnTotals(array $columns = []): static
    {
        $this->column_totals = array_fill_keys(array_map('strval', $columns), 0);
        return $this;
    }

    /**
     * @return array<string, mixed> totals keyed by column name, computed by execute()
     */
    public function getColumnTotals(): array
    {
        return $this->column_totals;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @param array<string, mixed> $filters the request parameters (page, limit, sort, dir, searchParams, ...)
     */
    public function setFilters(array $filters = []): static
    {
        $this->filters = $filters;
        $this->request = null;
        $this->executed = false;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getFiltersCount(): int
    {
        return count($this->filters);
    }

    public function hasFilters(): bool
    {
        return $this->filters !== [];
    }

    /**
     * The typed view of the request parameters
     */
    public function getRequest(): GridRequest
    {
        if ($this->request === null) {
            $this->request = GridRequest::fromArray($this->filters, $this->maxRowsPerPage);
        }
        return $this->request;
    }

    /**
     * @deprecated LIKE on PostgreSQL is always case-insensitive (ILIKE) now; this flag has no effect
     */
    public function getUseIlikeOnPg(): bool
    {
        return $this->useIlikeOnPg;
    }

    /**
     * @deprecated LIKE on PostgreSQL is always case-insensitive (ILIKE) now; this flag has no effect
     */
    public function setUseIlikeOnPg(bool $use): static
    {
        $this->useIlikeOnPg = $use;
        return $this;
    }

    /**
     * @param array<string, mixed> $params_list values for the :placeholders of the SQL
     */
    public function setParamsList(array $params_list = []): static
    {
        $this->params_list = $params_list;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParamsList(): array
    {
        return $this->params_list;
    }

    /**
     * @param mixed $value
     */
    public function addParam(string $key, $value): static
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
     * @param array<ActionButton|array<string, mixed>> $actionButtons buttons, or arrays in their JSON form
     */
    public function setActionButtons(array $actionButtons): static
    {
        $this->actionButtons = [];
        foreach ($actionButtons as $button) {
            if ($button instanceof ActionButton) {
                $this->actionButtons[] = $button;
            } elseif (is_array($button)) {
                $this->actionButtons[] = ActionButton::createFromAssocArray($button);
            }
        }
        return $this;
    }

    public function addActionButton(ActionButton $button): static
    {
        $this->actionButtons[] = $button;
        return $this;
    }

    /**
     * Add a column; a column with the same field name replaces the earlier one
     */
    public function addColumn(Column $column): static
    {
        if (!isset($this->column_list[$column->getFieldName()])) {
            $column->setIndex($this->getColumnsCount());
        } else {
            $column->setIndex($this->column_list[$column->getFieldName()]->getIndex());
        }
        $this->column_list[$column->getFieldName()] = $column;
        return $this;
    }

    /**
     * @return array<string, Column> keyed by field name
     */
    public function getColumnsList(): array
    {
        return $this->column_list;
    }

    public function getColumnsCount(): int
    {
        return count($this->column_list);
    }

    public function getFirstColumnName(): string
    {
        $names = array_keys($this->column_list);
        return $names === [] ? '' : (string)$names[0];
    }

    public function hasColumn(string $field_name): bool
    {
        return array_key_exists($field_name, $this->column_list);
    }

    public function getColumn(string $fieldName): ?Column
    {
        return $this->column_list[$fieldName] ?? null;
    }

    /**
     * Only the columns marked exportable
     * @return array<string, Column>
     */
    public function getExportableColumns(): array
    {
        return array_filter($this->column_list, fn(Column $col) => $col->isExportable());
    }

    public function getRowCount(): int
    {
        return $this->row_count;
    }

    private function setRowCount(int $rowCount): void
    {
        $this->row_count = max(0, $rowCount);
        $this->calculatePageCount();
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    /**
     * PhpGrid::ERR_VALIDATION, ERR_RUNTIME, ERR_FORBIDDEN or null when the last run succeeded
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getPageCount(): int
    {
        return $this->page_count;
    }

    public function setPageCount(int $pageCount): static
    {
        $this->page_count = max(1, $pageCount);
        return $this;
    }

    public function setAllowExport(bool $allowExport = true): static
    {
        $this->allowExport = $allowExport;
        return $this;
    }

    public function getAllowExport(): bool
    {
        return $this->allowExport;
    }

    public function getRowsPerPage(): int
    {
        return $this->rows_per_page;
    }

    /**
     * @param int $rowsPerPage 0 = every row on one page
     */
    public function setRowsPerPage(int $rowsPerPage): static
    {
        $this->rows_per_page = max(0, $rowsPerPage);
        return $this;
    }

    public function getMaxRowsPerPage(): int
    {
        return $this->maxRowsPerPage;
    }

    /**
     * The largest page size a client may request (default 1000); a request for 0 (all rows) is still honoured
     */
    public function setMaxRowsPerPage(int $maxRowsPerPage): static
    {
        $this->maxRowsPerPage = max(1, $maxRowsPerPage);
        $this->request = null;
        return $this;
    }

    /**
     * @return int[]
     */
    public function getRowsPerPageOptions(): array
    {
        return $this->rowsPerPageOptions;
    }

    /**
     * Entries of the rows-per-page menu (default 10, 50, 100); 0 adds "All"
     * @param int[] $options
     */
    public function setRowsPerPageOptions(array $options): static
    {
        $clean = [];
        foreach ($options as $option) {
            if (is_numeric($option) && (int)$option >= 0) {
                $clean[] = (int)$option;
            }
        }
        $this->rowsPerPageOptions = array_values(array_unique($clean));
        return $this;
    }

    public function isInlineScript(): bool
    {
        return $this->inlineScript;
    }

    /**
     * false: draw() emits no <script>; grid.js initialises the table from its data attributes on
     * DOMContentLoaded (Grid.autoInitAll()), which suits a Content-Security-Policy without inline scripts
     */
    public function setInlineScript(bool $inlineScript): static
    {
        $this->inlineScript = $inlineScript;
        return $this;
    }

    public function isCsvBom(): bool
    {
        return $this->csvBom;
    }

    public function setCsvBom(bool $csvBom): static
    {
        $this->csvBom = $csvBom;
        return $this;
    }

    public function getDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Debug adds the SQL, the bindings, timings and the exception text to the JSON
     */
    public function setDebug(bool $debug): static
    {
        $this->debug = $debug;
        return $this;
    }

    public function isEnableWildCardSearch(): bool
    {
        return $this->enableWildCardSearch;
    }

    /**
     * true: LIKE searches match anywhere in the value; false (default): only at the start
     */
    public function setEnableWildCardSearch(bool $enableWildCardSearch): static
    {
        $this->enableWildCardSearch = $enableWildCardSearch;
        return $this;
    }

    public function getShowSearchBar(): bool
    {
        return $this->showSearchBar;
    }

    public function setShowSearchBar(bool $showSearchBar): static
    {
        $this->showSearchBar = $showSearchBar;
        return $this;
    }

    public function getShowTitle(): bool
    {
        return $this->showTitle;
    }

    public function setShowTitle(bool $showTitle): static
    {
        $this->showTitle = $showTitle;
        return $this;
    }

    public function isDeferInitialize(): bool
    {
        return $this->deferInitialize;
    }

    public function setDeferInitialize(bool $deferInitialize): static
    {
        $this->deferInitialize = $deferInitialize;
        return $this;
    }

    public function getRowLevelJsCallback(): ?string
    {
        return $this->rowLevelJsCallback;
    }

    /**
     * @param string|null $value JS function called for every row before its cells are drawn: fn(tr, row)
     */
    public function setRowLevelJsCallback(?string $value): static
    {
        $this->rowLevelJsCallback = ($value === '') ? null : $value;
        return $this;
    }

    /**
     * Configure the grid from a record of a `grid` table (column_list, action_buttons and params_list as
     * JSON, grid_name, title, sql_query, rows_per_page).
     * @throws \Exception when the column list is not JSON
     */
    public function loadConfigFromObject(BaseObject $dbObject): void
    {
        $json = (string)$dbObject->getFieldValue('column_list');
        if (!Util::isJSON($json)) {
            throw new \Exception("Invalid JSON config for Grid: " . (string)$dbObject->getFieldValue('grid_name'));
        }
        foreach ((array)json_decode($json, true) as $c) {
            if (is_array($c)) {
                $this->addColumn(Column::createFromAssocArray($c));
            }
        }
        $this->setGridName((string)$dbObject->getFieldValue('grid_name'));
        $buttons = json_decode((string)$dbObject->getFieldValue('action_buttons'), true);
        $this->setActionButtons(is_array($buttons) ? $buttons : []);
        $this->setTitle((string)$dbObject->getFieldValue('title'));
        $this->setSqlQuery((string)$dbObject->getFieldValue('sql_query'));
        $this->setRowsPerPage((int)$dbObject->getFieldValue('rows_per_page'));
        $params = json_decode((string)$dbObject->getFieldValue('params_list'), true);
        $this->setParamsList(is_array($params) ? $params : []);
    }

    // ─── Running ──────────────────────────────────────────────────────────

    /**
     * Whether the current request is this grid's own data request (load_grid_by_name)
     */
    public function isReadyToHandleRequests(): bool
    {
        return $this->getRequest()->gridName !== null && $this->getRequest()->gridName === $this->getGridName();
    }

    protected function isValid(): bool
    {
        if (strlen((string)$this->getSqlQuery()) < 10) {
            $this->errorMessage = "Missing SQL Query";
            return false;
        }
        if ($this->getColumnsCount() < 1) {
            $this->errorMessage = "Missing column information";
            return false;
        }
        if ($this->getGridName() === '') {
            $this->errorMessage = "Missing grid name";
            return false;
        }
        foreach (array_keys($this->column_totals) as $name) {
            if (!$this->hasColumn((string)$name)) {
                $this->errorMessage = 'Unable to calculate the total of unknown column ' . $name;
                return false;
            }
        }
        return true;
    }

    /**
     * Resolve sort, page size and filters from the request and build the statements
     */
    protected function generateSql(bool $getAllRows = false): void
    {
        $request = $this->getRequest();

        if ($this->rows_per_page === 0 && $request->limit === null) {
            $this->rows_per_page = 10;
        }
        if ($request->limit !== null) {
            $this->setRowsPerPage($request->limit);
        }

        if (($this->sortDetails['field'] ?? '') === '') {
            $this->setSortDetails($this->getFirstColumnName(), 'DESC');
        }
        if ($request->sort !== null) {
            $sortColumn = $this->getColumn($request->sort);
            if ($sortColumn !== null && $sortColumn->isSortable()) {
                $this->sortDetails['field'] = $request->sort;
            }
        }
        if ($request->dir !== null) {
            $this->sortDetails['dir'] = $request->dir;
        }

        $query = new GridQuery((string)$this->getSqlQuery(), $this->column_list, $this->getGridName(), Identifier::engine());
        $built = $query->build($request->filters, [
            'sortField' => $this->sortDetails['field'],
            'sortDir' => strtoupper((string)$this->sortDetails['dir']),
            'page' => $request->page,
            'rowsPerPage' => $this->rows_per_page,
            'allRows' => $getAllRows,
            'wildcardContains' => $this->enableWildCardSearch,
            'totals' => array_keys($this->column_totals),
        ]);
        $this->generated_sql_query = $built['data'];
        $this->generated_sql_count_query = $built['count'];
        $this->generated_sql_totals_query = (string)$built['totals'];
        $this->generated_bindings = $built['bindings'];
    }

    /**
     * Every value bound into the statements: the developer's params plus the filter bindings
     * @return array<string, mixed>
     */
    protected function getBindings(): array
    {
        return array_merge($this->params_list, $this->generated_bindings);
    }

    /**
     * Run the statements and keep the rows in the grid. Returns false when the configuration is not
     * valid or a statement failed; getErrorMessage() / getErrorCode() say why.
     * @param bool $getAllRows ignore the page size (exports)
     */
    public function execute(bool $getAllRows = false): bool
    {
        $this->data = [];
        $this->errorMessage = '';
        $this->errorCode = null;
        $this->executed = true;
        foreach (array_keys($this->column_totals) as $name) {
            $this->column_totals[$name] = 0;
        }

        if (!$this->isValid()) {
            $this->errorCode = self::ERR_VALIDATION;
            return false;
        }

        try {
            $this->generateSql($getAllRows);
            $db = Db::getInstance();
            $bindings = $this->getBindings();

            $t0 = microtime(true);
            $stmt = $db->statement($this->generated_sql_query, $bindings);
            $this->data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->execMs = (microtime(true) - $t0) * 1000.0;
            $this->detectColumnFormats($stmt);

            if ($getAllRows) {
                $this->setRowCount(count($this->data));
            } else {
                $t1 = microtime(true);
                $count = $db->statement($this->generated_sql_count_query, $bindings)->fetchColumn();
                $this->setRowCount((int)$count);
                $this->countMs = (microtime(true) - $t1) * 1000.0;
            }

            if ($this->generated_sql_totals_query !== '') {
                $totals = $db->statement($this->generated_sql_totals_query, $bindings)->fetch(\PDO::FETCH_ASSOC);
                if (is_array($totals)) {
                    foreach (array_keys($this->column_totals) as $name) {
                        if (array_key_exists($name, $totals)) {
                            $this->column_totals[$name] = $totals[$name];
                        }
                    }
                }
            }
            return true;
        } catch (\Throwable $ex) {
            $this->errorMessage = 'An error has occurred while generating grid data.';
            if ($this->debug) {
                $this->errorMessage .= ' ' . $ex->getMessage() . ' - ' . $ex->getTraceAsString();
            }
            $this->errorCode = self::ERR_RUNTIME;
            return false;
        }
    }

    /**
     * Give every column without a format the type the driver reports for it
     */
    protected function detectColumnFormats(\PDOStatement $stmt): void
    {
        try {
            $count = $stmt->columnCount();
            for ($i = 0; $i < $count; $i++) {
                $meta = $stmt->getColumnMeta($i);
                if (!is_array($meta) || !isset($meta['name'])) {
                    continue;
                }
                $column = $this->getColumn((string)$meta['name']);
                if ($column !== null && $column->getFormat() === '') {
                    $column->setFormat($this->convertNativeDataTypeToString((string)($meta['native_type'] ?? '')));
                }
            }
        } catch (\Throwable $ex) {
            // a driver without column metadata: formats stay empty
        }
    }

    /**
     * The rows of the last execute()
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        return $this->data;
    }

    public function isExecuted(): bool
    {
        return $this->executed;
    }

    private function calculatePageCount(): void
    {
        $rowsPerPage = $this->getRowsPerPage();
        if ($rowsPerPage <= 0) {
            $this->setPageCount(1);
            return;
        }
        $this->setPageCount((int)ceil($this->getRowCount() / $rowsPerPage));
    }

    /**
     * The HTTP status the last run deserves: 200, 400 (configuration), 403 (export refused) or 500
     */
    public function getHttpStatus(): int
    {
        if ($this->errorCode === null && $this->errorMessage === '') {
            return 200;
        }
        return match ($this->errorCode) {
            self::ERR_VALIDATION => 400,
            self::ERR_FORBIDDEN => 403,
            default => 500,
        };
    }

    /**
     * The JSON payload grid.js reads (run execute() first; outputJsonWithHeaders() does)
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $success = $this->executed && $this->errorMessage === '';
        $debug = $this->getDebug();
        return [
            'success' => $success,
            'id' => 1,
            'name' => $this->getGridName(),
            'title' => $this->getTitle(),
            'columns' => $this->getColumnsList(),
            'columnCount' => $this->getColumnsCount(),
            'actionButtons' => array_values($this->getActionButtons()),
            'message' => $this->getErrorMessage(),
            'sort' => $this->getSortDetails()['field'],
            'dir' => $this->getSortDetails()['dir'],
            'errorCode' => $this->errorCode,
            'pageCount' => $this->getPageCount(),
            'rowsPerPage' => $this->getRowsPerPage(),
            'rowsPerPageOptions' => $this->getRowsPerPageOptions(),
            'allowExport' => $this->getAllowExport(),
            'rowCount' => $this->getRowCount(),
            // Debug fields (the first three are the historical names, the rest what grid.js reads)
            'user_query' => $debug ? $this->getSqlQuery() : null,
            'generated_query' => $debug ? $this->getGeneratedSqlQuery() : null,
            'generated_counter_query' => $debug ? $this->getGeneratedSqlCountQuery() : null,
            'query' => $debug ? $this->getSqlQuery() : null,
            'queryCount' => $debug ? $this->getGeneratedSqlCountQuery() : null,
            'bindings' => $debug ? $this->getBindings() : null,
            'metrics' => $debug ? ['execMs' => round($this->execMs, 2), 'countMs' => round($this->countMs, 2)] : null,
            'columnTotals' => $this->column_totals === [] ? [] : $this->column_totals,
            'rowLevelJsCallback' => $this->getRowLevelJsCallback(),
            'rows' => $this->data,
        ];
    }

    /**
     * toArray() as JSON; invalid UTF-8 in the rows is replaced instead of failing the whole response
     */
    public function toJson(): string
    {
        $json = json_encode($this->toArray(), JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return $json === false ? '{"success":false,"message":"The grid data could not be encoded as JSON","rows":[]}' : $json;
    }

    /**
     * Answer the grid's own request: XLSX or CSV export when asked for, else the JSON page. Ends the
     * script afterwards (the page that called it must not print anything else).
     * @param bool $terminate false: return instead of exiting (tests)
     */
    public function bootstrap(bool $terminate = true): void
    {
        $export = $this->getRequest()->export;
        if ($export === GridRequest::EXPORT_XLSX) {
            $this->exportToXlsx($terminate);
        } elseif ($export === GridRequest::EXPORT_CSV) {
            $this->exportToCsv($terminate);
        } else {
            $this->outputJsonWithHeaders(false, $terminate);
        }
    }

    /**
     * Send the JSON page with its headers and status code, then exit
     * @param bool $reload_results run the statements again even when execute() already ran
     * @param bool $terminate false: print the JSON and return (tests)
     */
    public function outputJsonWithHeaders(bool $reload_results = false, bool $terminate = true): void
    {
        if (!$this->executed || $reload_results) {
            $this->execute();
        }
        $this->respond($this->getHttpStatus(), ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store'], $this->toJson(), $terminate);
    }

    /**
     * Stream every matching row as a CSV attachment, then exit
     */
    public function exportToCsv(bool $terminate = true): void
    {
        if (!$this->prepareExport($terminate)) {
            return;
        }
        $fileName = $this->sanitizeExportFileName($this->getGridName()) . ' (' . date('Y-m-d Hi') . ').csv';
        $this->prepareOutput($terminate, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);

        $columns = $this->getExportableColumns();
        $out = fopen('php://output', 'wb');
        if ($out === false) {
            throw new \RuntimeException('Unable to open php://output');
        }
        if ($this->csvBom) {
            fwrite($out, "\xEF\xBB\xBF");
        }
        $labels = [];
        foreach ($columns as $column) {
            $labels[] = $column->getLabel();
        }
        fputcsv($out, $labels, ',', '"', '');
        foreach ($this->data as $row) {
            $values = [];
            foreach ($columns as $name => $column) {
                $value = $row[$name] ?? null;
                $values[] = is_bool($value) ? ($value ? '1' : '0') : $value;
            }
            fputcsv($out, $values, ',', '"', '');
        }
        fclose($out);
        if ($terminate) {
            $this->terminate();
        }
    }

    /**
     * Stream every matching row as an XLSX attachment (OpenSpout, bold header, numeric cells typed), then exit
     */
    public function exportToXlsx(bool $terminate = true): void
    {
        if (!$this->prepareExport($terminate)) {
            return;
        }
        $fileName = $this->sanitizeExportFileName($this->getGridName()) . ' (' . date('Y-m-d Hi') . ').xlsx';
        $this->prepareOutput($terminate, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);

        $columns = $this->getExportableColumns();
        $headingStyle = (new Style())->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor(Color::BLACK);
        $border = new Border(new BorderPart(Border::TOP), new BorderPart(Border::RIGHT), new BorderPart(Border::BOTTOM), new BorderPart(Border::LEFT));
        $options = new Options();
        $options->DEFAULT_ROW_STYLE = (new Style())->setBorder($border);

        $writer = new Writer($options);
        $writer->openToFile('php://output');   // the headers were sent above (openToBrowser() would send its own, unguarded)
        $labels = [];
        foreach ($columns as $column) {
            $labels[] = $column->getLabel();
        }
        $writer->addRow(Row::fromValues($labels, $headingStyle));
        foreach ($this->data as $row) {
            $values = [];
            foreach ($columns as $name => $column) {
                $values[] = $this->exportCellValue($row[$name] ?? null, $column);
            }
            $writer->addRow(Row::fromValues($values));
        }
        $writer->close();
        if ($terminate) {
            $this->terminate();
        }
    }

    /**
     * @deprecated use exportToXlsx(); the PhpSpreadsheet export was replaced by the OpenSpout one
     */
    public function exportToExcelPhpExcel(bool $terminate = true): void
    {
        $this->exportToXlsx($terminate);
    }

    /**
     * @deprecated use exportToXlsx()
     */
    public function exportToExcelSpout(bool $terminate = true): void
    {
        $this->exportToXlsx($terminate);
    }

    /**
     * Integer and float columns go into the sheet as numbers so Excel can sum them
     * @param mixed $value
     * @return mixed
     */
    protected function exportCellValue($value, Column $column)
    {
        if ($column->isNumeric() && is_string($value) && is_numeric($value)) {
            return $column->getFormat() === Column::FORMAT_INTEGER && (string)(int)$value === $value ? (int)$value : (float)$value;
        }
        return $value;
    }

    /**
     * Refuse (403) or run the statements (failure → 500/400 text) before an export starts streaming
     * @return bool true when the rows are ready to be written
     */
    protected function prepareExport(bool $terminate): bool
    {
        if (!$this->getAllowExport()) {
            $this->errorMessage = 'Export is not allowed for this grid';
            $this->errorCode = self::ERR_FORBIDDEN;
            $body = json_encode(['success' => false, 'message' => $this->errorMessage, 'errorCode' => $this->errorCode, 'rows' => []]);
            $this->respond(403, ['Content-Type' => 'application/json'], (string)$body, $terminate);
            return false;
        }
        if (!$this->execute(true)) {
            $this->respond($this->getHttpStatus(), ['Content-Type' => 'text/plain; charset=utf-8'], $this->errorMessage, $terminate);
            return false;
        }
        return true;
    }

    /**
     * Headers and buffer handling before a body is written to php://output
     * @param array<string, string> $headers
     */
    protected function prepareOutput(bool $terminate, int $status, array $headers): void
    {
        if ($terminate) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }
        if (!headers_sent()) {
            http_response_code($status);
            foreach ($headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
    }

    /**
     * Write one complete response body
     * @param array<string, string> $headers
     */
    protected function respond(int $status, array $headers, string $body, bool $terminate): void
    {
        $this->prepareOutput($terminate, $status, $headers);
        echo $body;
        if ($terminate) {
            $this->terminate();
        }
    }

    /**
     * End of a grid request; overridable (tests throw instead of exiting)
     */
    protected function terminate(): void
    {
        exit;
    }

    // ─── Markup ───────────────────────────────────────────────────────────

    /**
     * The table shell grid.js fills: the table, the pagination bar, the loading overlay and, unless
     * setInlineScript(false), the script that registers the grid in window.grid and starts it.
     */
    public function draw(): string
    {
        $gridName = $this->getGridName();
        $n = Util::attr($gridName);
        $jsName = json_encode($gridName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $attrName = Util::attr(json_encode($gridName, JSON_HEX_TAG | JSON_HEX_AMP));
        $rowsPerPage = $this->getRowsPerPage();
        $showTitle = $this->getShowTitle() ? 'true' : 'false';
        $showSearch = $this->getShowSearchBar() ? 'true' : 'false';
        $defer = $this->isDeferInitialize() ? 'true' : 'false';
        $options = Util::attr(json_encode($this->getRowsPerPageOptions()));

        $menu = '';
        foreach ($this->getRowsPerPageOptions() as $option) {
            $label = $option === 0 ? 'All' : (string)$option;
            $onclick = $this->inlineScript
                ? ' onclick="if (window.grid && window.grid[' . $attrName . ']) { window.grid[' . $attrName . '].setRowsPerPage(' . $option . '); } return false;"'
                : '';
            $menu .= "\t\t\t\t\t<li><a class=\"dropdown-item\" href=\"#\" data-rows-per-page=\"{$option}\"{$onclick}>{$label}</a></li>\n";
        }

        $script = '';
        if ($this->inlineScript) {
            $script = <<<HTML
<script>
(function () {
	var gridName = {$jsName};
	var registry = window.grid = window.grid || {};
	if (typeof Grid !== "function") { return; }
	var grid = registry[gridName] = new Grid(gridName);
	grid._rowsPerPage = {$rowsPerPage};
	grid.showTitleBar = {$showTitle};
	grid.showSearchBar = {$showSearch};
	grid.deferInitialize = {$defer};
	if (typeof Grid.autoInit === "function") {
		Grid.autoInit(gridName);
	} else if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", function () { grid.initialize(); });
	} else {
		grid.initialize();
	}
})();
</script>

HTML;
        }

        return <<<HTML
{$script}<div id="{$n}_container" class="laf-grid" data-grid-name="{$n}" data-rows-per-page="{$rowsPerPage}" data-show-title-bar="{$showTitle}" data-show-search-bar="{$showSearch}" data-defer-initialize="{$defer}" data-rows-per-page-options="{$options}" style="position:relative;">
	<div class="table-responsive" style="overflow:visible;">
		<table id="{$n}" data-component-type="Grid" class="table table-striped table-bordered table-hover table-sm mb-0">
			<thead id="{$n}_thead" class="table-light">
				<tr id="{$n}_title_row">
					<th class="text-start" id="{$n}_title"></th>
					<th class="text-end" id="{$n}_buttons"></th>
				</tr>
				<tr id="{$n}_header_row"></tr>
				<tr id="{$n}_search_row" class="d-print-none"></tr>
			</thead>
			<tbody id="{$n}_tbody"></tbody>
			<tfoot id="{$n}_tfoot"></tfoot>
		</table>
	</div>
	<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 d-print-none">
		<div id="{$n}_paginationInfoSection" class="small text-muted"></div>
		<div class="d-flex align-items-center gap-2">
			<div class="dropdown">
				<button type="button" id="{$n}_rowsPerPageSelector" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Rows per page">{$rowsPerPage}</button>
				<ul id="{$n}_rowsPerPageMenu" class="dropdown-menu dropdown-menu-end" aria-labelledby="{$n}_rowsPerPageSelector">
{$menu}				</ul>
			</div>
			<nav aria-label="Grid pages">
				<ul class="pagination pagination-sm mb-0">
					<li class="page-item"><button type="button" id="{$n}_paginationFirstPage" class="page-link" title="First page" aria-label="First page"><i class="fa fa-angle-double-left"></i></button></li>
					<li class="page-item"><button type="button" id="{$n}_paginationPrevPage" class="page-link" title="Previous page" aria-label="Previous page"><i class="fa fa-angle-left"></i></button></li>
					<li class="page-item"><span id="{$n}_paginationCurrPage" class="page-link" title="Current page" aria-current="page">1</span></li>
					<li class="page-item"><button type="button" id="{$n}_paginationNextPage" class="page-link" title="Next page" aria-label="Next page"><i class="fa fa-angle-right"></i></button></li>
					<li class="page-item"><button type="button" id="{$n}_paginationLastPage" class="page-link" title="Last page" aria-label="Last page"><i class="fa fa-angle-double-right"></i></button></li>
				</ul>
			</nav>
		</div>
	</div>
	<div id="{$n}_loader" class="laf-grid-loader" style="position:absolute;top:0;left:0;width:100%;height:100%;z-index:85;background-color:rgba(200,200,200,.5);text-align:center;padding:20px;display:none;" aria-hidden="true">
		<i class="fas fa-spinner fa-spin fa-3x"></i>
	</div>
</div>

HTML;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /**
     * Excel column letter for a 1-based number: 1 → A, 27 → AA
     * @param int|string $c
     */
    public function columnNumberToLetter($c): string
    {
        $c = (int)$c;
        if ($c <= 0) {
            return '';
        }
        $letter = '';
        while ($c !== 0) {
            $p = ($c - 1) % 26;
            $c = (int)(($c - $p) / 26);
            $letter = chr(65 + $p) . $letter;
        }
        return $letter;
    }

    /**
     * Column::FORMAT_* for a PDO native type name (MySQL and PostgreSQL)
     */
    protected function convertNativeDataTypeToString(string $type): string
    {
        return match (strtolower($type)) {
            'float', 'double', 'newdecimal', 'decimal', 'numeric', 'float4', 'float8', 'real', 'money' => Column::FORMAT_FLOAT,
            'int', 'integer', 'long', 'longlong', 'tiny', 'short', 'int24', 'year', 'bigint', 'smallint',
            'int2', 'int4', 'int8', 'serial', 'bigserial', 'oid' => Column::FORMAT_INTEGER,
            'bool', 'boolean' => Column::FORMAT_BOOLEAN,
            'date' => Column::FORMAT_DATE,
            'time', 'timetz' => Column::FORMAT_TIME,
            'datetime', 'timestamp', 'timestamptz' => Column::FORMAT_DATETIME,
            default => Column::FORMAT_STRING,
        };
    }

    /**
     * A file name safe for a Content-Disposition header
     */
    protected function sanitizeExportFileName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-. ]/', '_', $name) ?? 'grid';
    }
}
