# Grids

`PhpGrid` is LAF's interactive data grid: you give it a name, an SQL statement and the columns, and the page gets
a Bootstrap 5 table that loads its rows over AJAX and lets the user page, sort, search per column, total, hide
columns and export to Excel or CSV. The server half is `Laf\UI\Grid\PhpGrid\PhpGrid` (with `Column`,
`ActionButton`, `GridRequest` and `GridQuery`), the browser half is `sample_files/grid.js` (vanilla JavaScript, no
build step). Generated list pages use it for every table. This chapter covers the whole round trip, from the PHP
setup to the SQL it generates and the JSON it answers.

**On this page**

- [How a grid works](#how-a-grid-works)
- [A first grid: employees with their company](#a-first-grid-employees-with-their-company)
- [The SQL and parameters](#the-sql-and-parameters)
- [Columns](#columns)
- [Action buttons](#action-buttons)
- [Paging](#paging)
- [Sorting](#sorting)
- [Searching](#searching)
- [Column totals](#column-totals)
- [Exports](#exports)
- [Deferred loading and pages without inline scripts](#deferred-loading-and-pages-without-inline-scripts)
- [JavaScript callbacks](#javascript-callbacks)
- [Errors and debugging](#errors-and-debugging)
- [The JSON contract](#the-json-contract)
- [The grid.js client](#the-gridjs-client)
- [Running a grid from PHP (tests, APIs)](#running-a-grid-from-php-tests-apis)
- [The demo page](#the-demo-page)
- [Reference](#reference)
- [See also](#see-also)

## How a grid works

```text
1. GET /hr/employee/list           page builds the PhpGrid, draw() prints the empty table shell + script
2. grid.js (in the browser)        GET <same URL>&load_grid_by_name=employee_list&page=1&limit=10&sort=..&dir=..&searchParams=[..]
3. same page, same PhpGrid         isReadyToHandleRequests() is true -> bootstrap() answers JSON and exits
4. grid.js                         draws header, search row, rows, totals, pager from the JSON
```

The data request goes to **the page itself** (grid.js copies `window.location.href` and adds its parameters). So:

- The page must build the grid with the **same name** on every request, and call `bootstrap()` **before it prints
  anything**: whatever was echoed earlier would end up in front of the JSON.
- The page's own access checks (login, permissions) protect the data request too, since it is the same script.
- Several grids on one page are fine: each answers only requests carrying its own name.

The user's SQL is never parsed. `GridQuery` wraps it as a derived table (`SELECT cols FROM (your SQL) alias WHERE
... ORDER BY ... LIMIT ...`), so it may contain joins, `GROUP BY`, `ORDER BY` and `:named` parameters. Filter values
from the browser are always bound (`:f0`, `:f1`, ...), column names are checked against the columns you declared,
and unknown operators fall back to `LIKE`.

## A first grid: employees with their company

```php
use Laf\UI\Grid\PhpGrid\ActionButton;
use Laf\UI\Grid\PhpGrid\Column;
use Laf\UI\Grid\PhpGrid\PhpGrid;

$grid = new PhpGrid('employee_list');
$grid->setTitle('Employees')
    ->setSqlQuery("
        SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) AS full_name, e.email,
               c.name AS company, e.birth_date, e.salary, e.is_active
        FROM employee e
        LEFT JOIN company c ON c.id = e.company_id
    ")
    ->setRowsPerPage(2)
    ->setRowsPerPageOptions([2, 10, 50, 0])      // 0 = "All"
    ->setSortDetails('full_name', 'ASC')         // default sort until the user clicks a header
    ->setEnableWildCardSearch(true)              // LIKE searches match anywhere, not only at the start
    ->setColumnTotals(['salary']);

$grid->addColumn(new Column('id', 'ID', true, true, '?module=hr&submodule=employee&action=view&id={id}'));
$grid->addColumn(new Column('full_name', 'Name'));
$grid->addColumn(new Column('email', 'E-mail'));
$grid->addColumn((new Column('company', 'Company'))->setCaseInsensitive(true));
$grid->addColumn(new Column('birth_date', 'Born'));
$grid->addColumn(new Column('salary', 'Salary'));
$grid->addColumn((new Column('is_active', 'Active'))->setSearchOperator('eq'));

$grid->addActionButton(new ActionButton('View', '?module=hr&submodule=employee&action=view&id={id}', 'fa fa-eye'));
$grid->addActionButton(new ActionButton('Edit', '?module=hr&submodule=employee&action=update&id={id}', 'fa fa-edit'));

if ($grid->isReadyToHandleRequests()) {
    $grid->bootstrap();          // answers the AJAX request (JSON or export) and exits
}

// ... normal page output
echo $grid->draw();
```

The page needs Bootstrap 5 (CSS and the JS bundle, for the dropdowns), Font Awesome (icons) and `grid.js`. Copy
`sample_files/grid.js` into your application's assets and load it in the head; SheetJS is optional (client-side
Excel export, see [Exports](#exports)):

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>  <!-- optional -->
<script src="assets/grid.js"></script>
...
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
```

With the `Html` document class: `$html->addScript('assets/grid.js')` (see [11-pages-and-ui.md](11-pages-and-ui.md)).
Inside an admin page put the markup in a container: `$page->addComponent(new HtmlContainer($grid->draw()))`, which is
what the generated pages do.

### What the generated page does

The generator writes the same pattern for every table. From
[`examples/generated/pages/employee.page`](examples/generated/pages/employee.page) (shortened):

```php
$grid = new PhpGrid('employee_list');
$grid->setTitle('Employee List')
    ->setRowsPerPage(10)
    ->setSqlQuery('
SELECT * FROM (
	SELECT
		  `employee`.`id` AS `employee_id`
		, `employee`.`company_id` AS `employee_company_id`
		, `company`.`name` AS `company_name`
		, `employee`.`first_name` AS `employee_first_name`
		...
	FROM `employee`
	LEFT JOIN `company` `company` ON `employee`.`company_id` = `company`.`id`
	WHERE 1=1 
) l1 ');

$grid->addColumn(((new Column('employee_id', 'Id', true, true, sprintf('?module=%s&action=view&id={employee_id}', UrlParser::getModule())))->setInnerElementCssClass('btn btn-sm btn-outline-success'))->setOuterElementCssStyle('width:100px;'));
$grid->addColumn(new Column('employee_company_id', 'Company Id', false));
$grid->addColumn(new Column('company_name', 'Company', true));
...
$deleteLink = new ActionButton('Delete', $form->withCsrfToken(sprintf('?module=%s&action=delete&id={employee_id}', UrlParser::getModule())), 'fa fa-trash');
$deleteLink->addAttribute('onclick', 'return confirm("Are you sure you want to delete this?")');
$grid->addActionButton($deleteLink);

if ($grid->isReadyToHandleRequests()) {
    $grid->bootstrap();
}
$page->addComponent(new HtmlContainer($grid->draw()));
```

Every foreign key is joined to show the referenced row's display field, the key itself is a hidden column, and the
Delete button carries the form's CSRF token (see [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md)).

## The SQL and parameters

`setSqlQuery($sql)` takes any `SELECT` that produces the rows. The grid wraps it; for the first grid and a request
for page 1 the three generated statements are (`getGeneratedSqlQuery()`, `getGeneratedSqlCountQuery()`,
`getGeneratedSqlTotalsQuery()`, MariaDB):

```sql
-- grid query: employee_list
SELECT `id`, `full_name`, `email`, `company`, `birth_date`, `salary`, `is_active`
FROM (
SELECT e.id, CONCAT(e.first_name, ' ', e.last_name) AS full_name, e.email,
               c.name AS company, e.birth_date, e.salary, e.is_active
        FROM employee e
        LEFT JOIN company c ON c.id = e.company_id
) employee_list
ORDER BY `full_name` ASC
LIMIT 2 OFFSET 0

-- grid counter: employee_list
SELECT COUNT(*) AS total_number_of_rows
FROM (
...same...
) employee_list

-- grid totals: employee_list
SELECT SUM(`salary`) AS `salary`
FROM (
...same...
) employee_list
```

Rules that follow from the wrapping:

- Each `Column` field name must be a column **name or alias of the result set** (`full_name`, `company`), not a
  table-qualified expression. Give expressions an alias.
- Filters and sorting work on those result columns, so searching "Company" filters on the joined `c.name` alias
  without you writing any `WHERE`.
- Your statement must not end with `LIMIT` (the grid adds paging); a trailing `;` is removed.
- The grid name becomes the derived-table alias (characters outside `A-Za-z0-9_` become `_`, a leading digit gets a
  `grid_` prefix); spaces in the name become `_` in `setGridName()` already.
- Reserved words as column names work (`` `order` `` on MySQL, `"order"` on PostgreSQL); on PostgreSQL only
  all-lower-case names are quoted, so mixed-case aliases keep matching.

Parameters go into the SQL as `:name` and their values into the params list, either the constructor's second
argument or `setParamsList()` / `addParam()`. They are bound together with the filter values in all three
statements:

```php
$grid = new PhpGrid('company_staff', ['company_id' => $company->getIdVal()]);
$grid->setSqlQuery('SELECT id, first_name, last_name, salary FROM employee WHERE company_id = :company_id')
    ->addColumn(new Column('id', 'ID'))
    ->addColumn(new Column('first_name', 'First name'))
    ->addColumn(new Column('last_name', 'Last name'))
    ->addColumn(new Column('salary', 'Salary'));
```

With a request `sort=last_name&dir=desc&limit=5000`:

```sql
-- grid query: company_staff
SELECT `id`, `first_name`, `last_name`, `salary`
FROM (
SELECT id, first_name, last_name, salary FROM employee WHERE company_id = :company_id
) company_staff
ORDER BY `last_name` DESC
LIMIT 1000 OFFSET 0
```

(The requested 5000 rows were capped at `setMaxRowsPerPage()`, default 1000.)

> **Note:** never concatenate request values into the grid SQL. Use `:params` for values you add yourself; the
> user's searches are bound by the grid. The SQL itself is trusted developer code.

## Columns

```php
new Column(
    $fieldName,                    // result column name/alias
    $label,                        // header text (drawn as text by grid.js)
    $visible = true,               // false: not shown (still in the JSON rows, usable in links)
    $exportable = true,            // false: left out of exports
    $href = null,                  // link template, {column} and [query_param] placeholders
    $target = null,                // link target, e.g. '_blank'
    $innerElementCssStyle = null,  // style of the <a>/<span> inside the cell
    $innerElementCssClass = null,  // class of the <a>/<span>
    $outerElementCssStyle = null,  // style of the <td>
    $outerElementCssClass = null,  // class of the <td>
    $innerElementAttributes = null,// JSON object string of extra attributes for the <a>/<span>
    $outerElementAttributes = null // JSON object string of extra attributes for the <td>
);
```

The positional constructor is what generated pages and existing applications use; the fluent setters are nicer
for new code:

```php
$grid->addColumn((new Column('id', 'ID'))
    ->setHref('?module=hr&submodule=employee&action=view&id={id}')
    ->setInnerElementCssClass('btn btn-sm btn-outline-success')
    ->setOuterElementCssStyle('width:90px;'));
$grid->addColumn((new Column('notes', 'Notes'))->setExportable(false)->setSortable(false));
$grid->addColumn((new Column('company_id', 'Company id', false)));      // hidden, but {company_id} works in hrefs
$grid->addColumn((new Column('email', 'E-mail'))->setInnerElementAttributes(['data-copy' => '1']));
```

- **Formats.** When you do not call `setFormat()`, the grid reads the type the driver reports for each result
  column after the first query: `integer`, `float`, `boolean`, `date`, `time`, `datetime` or `string`
  (`Column::FORMAT_*`). grid.js right-aligns `integer` and `float` columns and exports them as numbers. MySQL
  `TINYINT(1)` reports as `integer`.
- **Order.** Columns appear in the order added; adding a column with an existing field name replaces it in place.
- **Links.** `{column}` is replaced by the row's value of any column in the result (hidden ones too), `[param]` by
  the current page's query-string parameter. Row values are inserted as they are (not URL-encoded).
- **Visibility** is a display choice, not a security boundary: hidden columns are in the JSON, and users can show
  them again with the column toggle menu. Leave sensitive data out of the SQL.

Search and sort tuning per column:

| Setter | Effect |
|---|---|
| `setSearchable(false)` | no search box, and filters on this column are ignored by the server |
| `setSortable(false)` | no sort button, and sort requests on it are ignored |
| `setSearchOperator('eq')` | always use this operator, whatever the client sends (e.g. `eq` for ids and flags, `gteq`, `in`, `between`) |
| `setWildcardMode(Column::WILDCARD_CONTAINS / WILDCARD_STARTS_WITH / WILDCARD_ENDS_WITH)` | where the `%` go for LIKE on this column; null = grid default |
| `setCaseInsensitive(true)` | `=` / `!=` compare `LOWER(CAST(col AS CHAR/TEXT))` with the lower-cased value |
| `setCaseInsensitiveSort(true)` | PostgreSQL: sort by `LOWER(CAST(col AS TEXT))` (MySQL collations already ignore case) |
| `setJsCallback('fnName')` | grid.js calls `fnName(value, row, column)` to render the cell, see [callbacks](#javascript-callbacks) |
| `setFormat(Column::FORMAT_FLOAT)` | override the detected format |

## Action buttons

Action buttons form the per-row dropdown menu at the end of each row.

```php
$grid->addActionButton(new ActionButton('View', '?module=hr&submodule=employee&action=view&id={id}', 'fa fa-eye'));

$delete = (new ActionButton('Delete', $form->withCsrfToken('?module=hr&submodule=employee&action=delete&id={id}'), 'fa fa-trash'))
    ->setCssClass('text-danger')
    ->addAttribute('data-name', '{full_name}')
    ->addAttribute('onclick', 'return confirm("Delete " + this.dataset.name + "?")');
$grid->addActionButton($delete);

$grid->addActionButton((new ActionButton('Approve', '?action=approve&id={id}', 'fa fa-check'))
    ->setJsCallback('approveButton'));          // decide per row in JavaScript
```

- The href uses the same `{column}` / `[param]` placeholders as column links.
- `addAttribute()` values get the placeholders replaced too, **except** event handler attributes (`on*`), which are
  set verbatim so row data never ends up inside script. Pass row values through a `data-*` attribute and read them
  from `this.dataset`, as above.
- The label is drawn as **HTML** by grid.js (it may contain markup); do not put user data in it.
- `setActionButtons([...])` replaces all buttons and also accepts arrays in their JSON form
  (`['label' => ..., 'href' => ..., 'icon' => ...]`).

## Paging

| Setting | Meaning |
|---|---|
| `setRowsPerPage(int)` | initial page size (default 10) |
| `setRowsPerPageOptions([10, 50, 100])` | entries of the rows-per-page menu; `0` adds "All" |
| `setMaxRowsPerPage(int)` | largest page size a client may ask for (default 1000); larger requests are capped |

The client sends `page` (1-based) and `limit`. `limit=0` means all rows on one page and is honoured regardless of
`setMaxRowsPerPage()` (that is how the client-side export fetches everything). grid.js remembers the chosen page
size per grid in `localStorage` (`laf.grid.{name}.rowsPerPage`), so it can differ from `setRowsPerPage()` on the
next visit.

> **Note:** `setRowsPerPage(0)` on the server does not show all rows: when the request carries no `limit`, a page
> size of 0 is replaced by 10 (`PhpGrid::generateSql()`). grid.js always sends a limit, so in practice the first
> page uses the client's value. Use `setRowsPerPageOptions([..., 0])` to offer "All".

## Sorting

`setSortDetails($field, $direction)` sets the initial sort. Without it the grid sorts by the **first column,
descending** (newest ids first for a typical list). A request's `sort` is used only when it names a sortable
column; `dir` must be `asc` or `desc` (any case), otherwise it is ignored. The JSON echoes `sort` and `dir` as the
grid resolved them (the direction exactly as sent, e.g. `desc`).

Clicking a header in grid.js sorts ascending, clicking again toggles the direction; changing the sort goes back to
page 1.

## Searching

grid.js draws a search box under every searchable column. The user types and presses Enter (or sets
`Grid.searchDebounceMs` to search while typing; Escape clears the box). The value may start with an operator
prefix:

| Typed | Operator sent | SQL |
|---|---|---|
| `ada` | `like` | `col LIKE 'ada%'` (or `'%ada%'` with `setEnableWildCardSearch(true)`) |
| `=Acme` | `eq` | `col = 'Acme'` |
| `!=Acme` | `noteq` | `col != 'Acme'` |
| `<5000`, `>5000` | `lt`, `gt` | `col < 5000`, `col > 5000` |
| `<=5000`, `>=5000` | `lteq`, `gteq` | `col <= 5000`, `col >= 5000` |

The server understands more operators than the search box offers (send them from your own code or force them per
column with `setSearchOperator()`):

| Operator | SQL | Value |
|---|---|---|
| `like`, `notlike` | `LIKE` / `NOT LIKE` (PostgreSQL: `CAST(col AS TEXT) ILIKE` / `NOT ILIKE`) | text; `%` added by the wildcard mode |
| `eq`, `stricteq` | `=` (value null: `IS NULL`) | scalar |
| `noteq`, `notstricteq` | `!=` (value null: `IS NOT NULL`) | scalar |
| `lt`, `gt`, `lteq`, `gteq` | `<`, `>`, `<=`, `>=` | scalar |
| `in` | `IN (:f0, :f1, ...)` | list, or a string split on `,` |
| `between` | `BETWEEN :f0 AND :f1` | two-item list, or `'low..high'` |
| `isnull`, `notnull` | `IS NULL`, `IS NOT NULL` | ignored |

Anything else becomes `like`. Filters on columns that do not exist or are not searchable are dropped, and so are
values of the wrong shape (an object where a value is expected). LIKE is case-insensitive on both engines
(MySQL through the collation, PostgreSQL through `ILIKE`); `=` follows the collation unless the column has
`setCaseInsensitive(true)`.

The request parameter `searchParams` is a JSON list of `{property, operator, value}`. For example:

```json
[
  {"property": "company",    "operator": "eq",      "value": "ACME CORP"},
  {"property": "salary",     "operator": "gteq",    "value": "5000"},
  {"property": "email",      "operator": "like",    "value": "a"},
  {"property": "nope",       "operator": "eq",      "value": "x"},
  {"property": "birth_date", "operator": "between", "value": "1980-01-01..1990-12-31"}
]
```

with `sort=salary&dir=desc&limit=10` on the first grid gives:

```sql
-- grid query: employee_list
SELECT `id`, `full_name`, `email`, `company`, `birth_date`, `salary`, `is_active`
FROM (
...
) employee_list
WHERE LOWER(CAST(`company` AS CHAR)) = :f0
	AND `salary` >= :f1
	AND `email` LIKE :f2
	AND `birth_date` BETWEEN :f3 AND :f4
ORDER BY `salary` DESC
LIMIT 10 OFFSET 0
```

```text
getGeneratedBindings(): [':f0' => 'acme corp', ':f1' => '5000', ':f2' => '%a%', ':f3' => '1980-01-01', ':f4' => '1990-12-31']
```

The unknown column `nope` was dropped; `company` is compared lower-cased because of `setCaseInsensitive(true)`;
`email` got `%a%` because wildcard search is on. Two rows matched (Alan Turing, then Ada Lovelace), and the salary
total over the filtered rows was `11500.50`.

`in` and `isnull` (`{"property":"id","operator":"in","value":"4,5,6"}`, `{"property":"company","operator":"isnull"}`):

```sql
WHERE `id` IN (:f0, :f1, :f2)
	AND `company` IS NULL
```

The same filters on PostgreSQL (built with `GridQuery` directly, `like` on a name and a case-insensitive `eq` plus
case-insensitive sort on company, page 2 of 25):

```sql
WHERE CAST("full_name" AS TEXT) ILIKE :f0
	AND LOWER(CAST("company" AS TEXT)) = :f1
ORDER BY LOWER(CAST("company" AS TEXT)) ASC
LIMIT 25 OFFSET 25
```

From JavaScript, filters are set on the instance: `Grid.get('employee_list').filters = {company: '=Acme Corp',
salary: '>=5000'}` (setting it reloads and returns to page 1).

## Column totals

`setColumnTotals(['salary'])` adds a third statement, `SELECT SUM(col) ...` over **all** rows matching the current
filters (not only the visible page). The values come back in `columnTotals` (as the driver returns them, e.g. the
string `"23550.50"` for a DECIMAL), and grid.js draws them in the table footer, formatted with
`Intl.NumberFormat` using the page's `<html lang>` and two decimals. A total on a column the grid does not have is
a configuration error (`execute()` returns false with "Unable to calculate the total of unknown column ...").

## Exports

The grid's toolbar has an export button with four entries:

| Entry | Where the file is made | Columns | Needs |
|---|---|---|---|
| Excel | in the browser (SheetJS `XLSX`) | visible + exportable, keyed by label | `window.XLSX`; without it the server export is used |
| CSV | in the browser (UTF-8 with BOM) | visible + exportable | nothing |
| Excel (server) | `PhpGrid::exportToXlsx()` (OpenSpout, bold header row, numeric cells typed) | exportable | `export_grid_to_excel=1` (any value >= 1) |
| CSV (server) | `PhpGrid::exportToCsv()` | exportable | `export_grid_to_csv` present |

Exports contain every row matching the current filters and sort (no paging). Server exports stream an attachment
named after the grid, e.g. `employee_list (2026-10-14 1855).csv`. The server CSV with the example data:

```text
ID,Name,E-mail,Company,Born,Salary,Active
4,"Ada Lovelace",ada@example.com,"Acme Corp",1985-12-10,5400.00,1
5,"Alan Turing",alan@example.com,"Acme Corp",1982-06-23,6100.50,1
6,"Grace Hopper",grace@example.com,"Globex & Sons",1979-12-09,7250.00,0
7,"Linus <b>Torvalds</b>",linus@example.com,"Globex & Sons",,4800.00,1
8,"Margaret Hamilton",margaret@example.com,,1986-08-17,,1
```

- `setCsvBom(true)` prefixes the server CSV with a UTF-8 byte order mark, so Excel reads accented characters
  correctly when it opens the file directly.
- Server exports include **hidden** columns that are exportable (the client exports only visible ones). Mark
  internal columns `setExportable(false)` if they should never leave the server.
- `setAllowExport(false)` hides the export button and makes the server exports answer **403** with
  `{"success":false,"message":"Export is not allowed for this grid","errorCode":"ERR_FORBIDDEN","rows":[]}`.

> **Note:** `setAllowExport(false)` only blocks the export endpoints. A client can still ask the JSON endpoint for
> `limit=0` (all rows), which is what the browser-side export does. If a user must not be able to download all
> rows, restrict the SQL (or the page) for that user rather than relying on the export switch.

## Deferred loading and pages without inline scripts

`draw()` prints a small inline `<script>` that registers the grid in `window.grid[name]` and starts it. Two
switches change that:

- `setDeferInitialize(true)`: the grid loads only when its container scrolls into view (`IntersectionObserver`),
  for grids in hidden tabs or far down a page. Generated pages use it for the related-records tabs.
- `setInlineScript(false)`: no `<script>` at all, for pages with a Content-Security-Policy that forbids inline
  scripts. grid.js finds every `table[data-component-type="Grid"]` on `DOMContentLoaded` (`Grid.autoInitAll()`)
  and reads the settings from the container's `data-*` attributes.

```php
$perCompany = new PhpGrid('employees_per_company');
$perCompany->setTitle('Employees per company')
    ->setSqlQuery('SELECT c.name AS company, COUNT(e.id) AS people, SUM(e.salary) AS payroll
                   FROM company c LEFT JOIN employee e ON e.company_id = c.id
                   GROUP BY c.name')
    ->setShowSearchBar(false)
    ->setDeferInitialize(true)
    ->setInlineScript(false)
    ->setAllowExport(false)
    ->setSortDetails('people', 'desc');
$perCompany->addColumn(new Column('company', 'Company'))
    ->addColumn(new Column('people', 'People'))
    ->addColumn(new Column('payroll', 'Payroll'));
```

The container `draw()` prints carries the settings either way:

```html
<div id="employee_list_container" class="laf-grid" data-grid-name="employee_list" data-rows-per-page="2"
     data-show-title-bar="true" data-show-search-bar="true" data-defer-initialize="true"
     data-rows-per-page-options="[2,10,50,0]" style="position:relative;">
	<div class="table-responsive" style="overflow:visible;">
		<table id="employee_list" data-component-type="Grid" class="table table-striped table-bordered table-hover table-sm mb-0">
			<thead id="employee_list_thead" class="table-light">
				<tr id="employee_list_title_row">
					<th class="text-start" id="employee_list_title"></th>
					<th class="text-end" id="employee_list_buttons"></th>
				</tr>
				<tr id="employee_list_header_row"></tr>
				<tr id="employee_list_search_row" class="d-print-none"></tr>
			</thead>
			<tbody id="employee_list_tbody"></tbody>
			<tfoot id="employee_list_tfoot"></tfoot>
		</table>
	</div>
	<!-- pagination info, rows-per-page dropdown, first/prev/current/next/last buttons, loading overlay -->
</div>
```

`setShowTitle(false)` hides the title bar (title, export and column buttons); `setShowSearchBar(false)` hides the
search row.

## JavaScript callbacks

Columns, action buttons and rows can name a JavaScript function (a global name or a dot path such as
`MyApp.grid.formatStatus`). The functions must exist before the grid loads its data, so define them in a script
that runs before `DOMContentLoaded`. Full reference: `sample_files/grid-callback-examples.md`.

```php
$grid->getColumn('is_active')->setJsCallback('activeBadge');
$grid->getColumn('email')->setJsCallback('emailLink');
$grid->setRowLevelJsCallback('highlightHighSalary');
$grid->addActionButton((new ActionButton('Edit', '?module=hr&submodule=employee&action=update&id={id}', 'fa fa-edit'))
    ->setJsCallback('editButton'));
```

```js
// column: fn(cellValue, row, columnDefinition)
//   null / undefined / true -> default rendering
//   string                  -> raw HTML for the cell
//   object                  -> overrides: value (text), innerHTML, href, target, innerElementCssStyle,
//                              innerElementCssClass, outerElementCssStyle, outerElementCssClass, hidden
function activeBadge(value) {
    return (value == 1 || value === true)
        ? '<span class="badge bg-success">active</span>'
        : '<span class="badge bg-secondary">inactive</span>';
}
function emailLink(value) {
    return value ? {href: 'mailto:' + value, value: value} : {value: '-'};   // value is drawn as text
}

// row: fn(tr, row), called before the cells are added
function highlightHighSalary(tr, row) {
    if (parseFloat(row.salary) > 7000) {
        tr.classList.add('table-warning');
    }
}

// action button: fn(row, buttonDefinition)
//   null / false -> hide for this row, true / undefined -> as defined,
//   object -> overrides on a copy: label, icon, href, cssClass, disabled, hidden
function editButton(row) {
    return (row.is_active == 1) ? true : {disabled: true, label: 'Edit (inactive)'};
}
```

> **Note:** a string returned from a column callback is inserted as HTML. When the text comes from the database,
> return the object form (`{value: ...}`), which grid.js sets as text, or escape it yourself.

## Errors and debugging

`execute()` returns `false` when something is wrong; `getErrorMessage()`, `getErrorCode()` and `getHttpStatus()`
say what, and `bootstrap()` sends the JSON with that status:

| Situation | `getErrorCode()` | HTTP | Message |
|---|---|---|---|
| no SQL (shorter than 10 characters) | `ERR_VALIDATION` | 400 | `Missing SQL Query` |
| no columns | `ERR_VALIDATION` | 400 | `Missing column information` |
| no grid name | `ERR_VALIDATION` | 400 | `Missing grid name` |
| total on an unknown column | `ERR_VALIDATION` | 400 | `Unable to calculate the total of unknown column ...` |
| SQL failed (driver exception) | `ERR_RUNTIME` | 500 | `An error has occurred while generating grid data.` |
| export with `setAllowExport(false)` | `ERR_FORBIDDEN` | 403 | `Export is not allowed for this grid` |
| success | `null` | 200 | `''` |

grid.js shows the message in a red banner above the table and "No records found" in the body. A failed server
export answers with the plain-text message instead of a half-written file.

```php
$grid->setSqlQuery('SELECT id, nope FROM employee');
var_dump($grid->execute());                 // bool(false)
echo $grid->getErrorMessage();              // An error has occurred while generating grid data.
echo $grid->getErrorCode();                 // ERR_RUNTIME
echo $grid->getHttpStatus();                // 500
```

`setDebug(true)` adds the exception text and stack trace to the message, and puts the SQL, the bindings and the
timings into the JSON (`user_query`, `generated_query`, `generated_counter_query`, `query`, `queryCount`,
`bindings`, `metrics`). In the browser, `Grid.debug = true` logs every request URL to the console.

> **Note:** debug output goes to whoever loads the page: SQL, bound values (your `:params` included) and stack
> traces with file paths. Turn it on only in development.

## The JSON contract

`toArray()` is the payload grid.js reads (and `toJson()` encodes it, replacing invalid UTF-8 instead of failing).
For the first grid, page 1 (two of the seven columns shown, the others have the same shape):

```json
{
    "success": true,
    "id": 1,
    "name": "employee_list",
    "title": "Employees",
    "columns": {
        "id": {
            "index": 0, "fieldName": "id", "label": "ID", "format": "integer",
            "href": "?module=hr&submodule=employee&action=view&id={id}", "target": null,
            "innerElementCssStyle": "", "innerElementCssClass": "", "outerElementCssStyle": "", "outerElementCssClass": "",
            "visible": true, "exportable": true, "innerElementAttributes": "", "outerElementAttributes": "",
            "caseInsensitive": null, "caseInsensitiveSort": null, "searchOperator": null, "wildcardMode": null,
            "jsCallback": null, "searchable": true, "sortable": true
        },
        "company": {
            "index": 3, "fieldName": "company", "label": "Company", "format": "string",
            "href": null, "target": null,
            "innerElementCssStyle": "", "innerElementCssClass": "", "outerElementCssStyle": "", "outerElementCssClass": "",
            "visible": true, "exportable": true, "innerElementAttributes": "", "outerElementAttributes": "",
            "caseInsensitive": true, "caseInsensitiveSort": null, "searchOperator": null, "wildcardMode": null,
            "jsCallback": null, "searchable": true, "sortable": true
        }
    },
    "columnCount": 7,
    "actionButtons": [
        {"label": "View", "href": "?module=hr&submodule=employee&action=view&id={id}", "icon": "fa fa-eye",
         "attributeList": [], "jsCallback": null, "cssClass": null},
        {"label": "Edit", "href": "?module=hr&submodule=employee&action=update&id={id}", "icon": "fa fa-edit",
         "attributeList": [], "jsCallback": null, "cssClass": null}
    ],
    "message": "",
    "sort": "full_name",
    "dir": "ASC",
    "errorCode": null,
    "pageCount": 3,
    "rowsPerPage": 2,
    "rowsPerPageOptions": [2, 10, 50, 0],
    "allowExport": true,
    "rowCount": 5,
    "user_query": null,
    "generated_query": null,
    "generated_counter_query": null,
    "query": null,
    "queryCount": null,
    "bindings": null,
    "metrics": null,
    "columnTotals": {"salary": "23550.50"},
    "rowLevelJsCallback": null,
    "rows": [
        {"id": 4, "full_name": "Ada Lovelace", "email": "ada@example.com", "company": "Acme Corp",
         "birth_date": "1985-12-10", "salary": "5400.00", "is_active": 1},
        {"id": 5, "full_name": "Alan Turing", "email": "alan@example.com", "company": "Acme Corp",
         "birth_date": "1982-06-23", "salary": "6100.50", "is_active": 1}
    ]
}
```

| Key | Meaning |
|---|---|
| `success` | `execute()` ran and succeeded |
| `name`, `title` | grid name, title (drawn as text) |
| `columns` | object keyed by field name, each a `Column::jsonSerialize()` |
| `columnCount` | number of columns |
| `actionButtons` | list of `ActionButton::jsonSerialize()` |
| `message`, `errorCode` | error message and code, `''` / `null` on success |
| `sort`, `dir` | sort column and direction in effect |
| `pageCount`, `rowsPerPage`, `rowCount` | paging: pages, page size in effect, rows matching the filters |
| `rowsPerPageOptions` | entries of the rows-per-page menu |
| `allowExport` | show the export button |
| `columnTotals` | `{column: sum}` or `[]` when no totals are configured |
| `rowLevelJsCallback` | row callback name or `null` |
| `rows` | the page of rows, keyed by result column (all columns of the SELECT list, hidden ones included) |
| `user_query` ... `metrics` | debug fields, `null` unless `setDebug(true)` |

`Column::createFromJsonString()` / `createFromAssocArray()` and `ActionButton::createFromAssocArray()` read that
JSON form back, and `loadConfigFromObject($record)` configures a whole grid from a row of a `grid` table (columns
`grid_name`, `title`, `sql_query`, `rows_per_page`, and `column_list`, `action_buttons`, `params_list` as JSON);
it throws `\Exception` when `column_list` is not JSON.

## The grid.js client

`sample_files/grid.js` is plain ES2020 in an IIFE, with no jQuery and no build step. It needs Bootstrap 5
(dropdowns) and Font Awesome; SheetJS is optional. It defines `window.Grid` and keeps every instance in
`window.grid[name]`.

| API | What it does |
|---|---|
| `new Grid(name)` | instance for the table `#name` (the inline script does this) |
| `Grid.get(name)` | the registered instance or `null` |
| `Grid.autoInit(name)` | start a registered grid (now, or when visible when `deferInitialize`) |
| `Grid.autoInitAll()` | register and start every grid table without an instance (runs on `DOMContentLoaded`) |
| `Grid.debug = true` | log requests to the console |
| `Grid.searchDebounceMs = 400` | search while typing after 400 ms (default `0` = on Enter) |
| `grid.refresh()` | reload the current page |
| `grid.filters = {field: 'text'}` | set the filters (prefixes `=`, `!=`, `<`, `>`, `<=`, `>=`), back to page 1, reload |
| `grid.clearFilters()` | empty the search boxes and reload |
| `grid.sort(field, 'asc' or 'desc')` | sort and reload from page 1 |
| `grid.setRowsPerPage(n)` | change the page size (0 = all), remembered in `localStorage` |
| `grid.currentPage = n` | go to page `n` (clamped to 1..pageCount) |
| `grid.buildUrl(allRows)` | the data URL for the current state |
| `grid.downloadExcelJs()`, `grid.downloadCsvJs()`, `grid.serverExportUrl('csv' or 'xlsx')` | exports |
| `grid.showError(message)`, `grid.clearError()` | the error banner |
| `grid.columns`, `grid.rows`, `grid.data`, `grid.rowCount`, `grid.pageCount` | state after the last load |

Example: a "Show inactive" button outside the grid.

```js
document.getElementById('show-inactive').addEventListener('click', function () {
    Grid.get('employee_list').filters = {is_active: '=0'};
});
```

Users can hide columns with the column menu in the toolbar; the choice is kept per grid in `localStorage`
(`laf.grid.{name}.hiddenColumns`) and "Reset" restores the server's settings.

The client escapes what it draws from data: cell values, the title, column labels and totals are set as text.
Raw HTML enters only through a column callback returning a string, the `innerHTML` key of a callback object and
action button labels.

## Running a grid from PHP (tests, APIs)

The third constructor argument is the request; without it the grid reads `$_GET`. That is how the integration test
`tests/PhpGridTest.php` drives a grid without a browser, and how you can serve grid data from your own endpoint or
check a grid's SQL in a unit of your app:

```php
$grid = new PhpGrid('employee_list', [], [
    'load_grid_by_name' => 'employee_list',
    'page' => '1',
    'limit' => '10',
    'sort' => 'salary',
    'dir' => 'desc',
    'searchParams' => json_encode([['property' => 'salary', 'operator' => 'gteq', 'value' => '5000']]),
]);
// ... setSqlQuery(), addColumn() as above

var_dump($grid->isReadyToHandleRequests());   // bool(true)
if ($grid->execute()) {
    $rows = $grid->getRows();                  // the page of rows
    $total = $grid->getRowCount();             // matching rows, all pages
    $sums = $grid->getColumnTotals();          // ['salary' => '...']
    $payload = $grid->toArray();               // the JSON payload as an array
    echo $grid->getGeneratedSqlQuery();
}
```

`bootstrap(false)`, `outputJsonWithHeaders(false, false)`, `exportToCsv(false)` and `exportToXlsx(false)` write
their response (headers when none were sent yet) and return instead of exiting. `GridRequest::fromArray($query)`
and `new GridQuery($sql, $columns, $alias, $engine)` are pure (no connection): use them to unit-test the parsing
and the SQL on either engine, as `tests/GridRequestTest.php` and `tests/GridQueryTest.php` do.

## The demo page

`sample_files/grid_demo.php` shows every feature on one page: a `grid_demo` table it creates and seeds (57 rows) in
the database named by the `LAF_DB_*` variables (default `laf_test`), a grid with links, CSS, totals, a reserved-word
column, callbacks of every kind, a Delete button with a `data-` attribute, a hidden column and debug on, and a
second grouped grid in a hidden tab with `setDeferInitialize(true)` and `setInlineScript(false)`. Open it through
the local web server, e.g. `http://localhost/laf/sample_files/grid_demo.php`; `?demo_break=1` runs a broken
statement to show the error path, `?js=old` loads an older `grid.js` from `var/grid_old.js` for compatibility checks.

## Reference

### `PhpGrid`

| Method | What it does | Notes |
|---|---|---|
| `__construct(string $gridName, array $params_list = [], array $filters = [])` | new grid | empty `$filters` = `$_GET` |
| `getGridName()`, `setGridName(string)` | name; spaces become `_` | also the table id and SQL alias |
| `getTitle()`, `setTitle(string)` | title in the title bar | text |
| `getSqlQuery()`, `setSqlQuery(?string)` | the row statement | wrapped, never parsed |
| `getParamsList()`, `setParamsList(array)`, `addParam(string $key, $value)` | `:name` values | bound in every statement |
| `addColumn(Column)`, `getColumnsList()`, `getColumn(string)`, `hasColumn(string)`, `getColumnsCount()`, `getFirstColumnName()`, `getExportableColumns()` | columns | same field name replaces |
| `addActionButton(ActionButton)`, `setActionButtons(array)`, `getActionButtons()` | row menu | arrays accepted in `setActionButtons()` |
| `setRowsPerPage(int)`, `getRowsPerPage()` | page size | see the note on 0 |
| `setRowsPerPageOptions(array)`, `getRowsPerPageOptions()` | menu entries | 0 = All |
| `setMaxRowsPerPage(int)`, `getMaxRowsPerPage()` | cap on the client's limit | default 1000; limit 0 still allowed |
| `setSortDetails(string $field, string $direction)`, `getSortDetails()` | initial sort | default: first column DESC |
| `setEnableWildCardSearch(bool)`, `isEnableWildCardSearch()` | LIKE contains instead of starts-with | per column: `setWildcardMode()` |
| `setColumnTotals(array)`, `getColumnTotals()` | SUM columns | values after `execute()` |
| `setAllowExport(bool = true)`, `getAllowExport()` | export button and endpoints | 403 when off |
| `setCsvBom(bool)`, `isCsvBom()` | BOM in the server CSV | |
| `setShowTitle(bool)`, `getShowTitle()`, `setShowSearchBar(bool)`, `getShowSearchBar()` | toolbar and search row | |
| `setDeferInitialize(bool)`, `isDeferInitialize()` | load when visible | |
| `setInlineScript(bool)`, `isInlineScript()` | inline `<script>` in `draw()` | false: `Grid.autoInitAll()` |
| `setRowLevelJsCallback(?string)`, `getRowLevelJsCallback()` | row callback | |
| `setDebug(bool)`, `getDebug()` | SQL, bindings, timings, exception in the JSON | development only |
| `setFilters(array)`, `getFilters()`, `getFiltersCount()`, `hasFilters()` | the request parameters | resets the parsed request |
| `getRequest(): GridRequest` | typed view of the request | |
| `isReadyToHandleRequests(): bool` | the request is this grid's data/export request | `load_grid_by_name` = name |
| `execute(bool $getAllRows = false): bool` | build and run the statements | false + error on failure |
| `isExecuted()`, `getRows()`, `getRowCount()`, `getPageCount()`, `setPageCount(int)` | results of the last run | |
| `getGeneratedSqlQuery()`, `getGeneratedSqlCountQuery()`, `getGeneratedSqlTotalsQuery()`, `getGeneratedBindings()` | the statements of the last run | totals `''` when none |
| `getErrorMessage()`, `setErrorMessage(string)`, `getErrorCode()`, `getHttpStatus()` | errors | codes `PhpGrid::ERR_VALIDATION`, `ERR_RUNTIME`, `ERR_FORBIDDEN` |
| `toArray()`, `toJson()` | the payload | |
| `bootstrap(bool $terminate = true)` | answer the request: XLSX, CSV or JSON | exits unless `false` |
| `outputJsonWithHeaders(bool $reload_results = false, bool $terminate = true)` | send the JSON with status and headers | runs `execute()` when needed |
| `exportToCsv(bool $terminate = true)`, `exportToXlsx(bool $terminate = true)` | stream an export | 403 / error text on refusal / failure |
| `exportToExcelPhpExcel()`, `exportToExcelSpout()` | deprecated aliases of `exportToXlsx()` | |
| `draw(): string` | table shell, pager, loader and (unless disabled) the script | |
| `loadConfigFromObject(BaseObject $record): void` | configure from a `grid` table row | throws on bad JSON |
| `columnNumberToLetter($c): string` | 1 -> `A`, 27 -> `AA` | helper |
| `getUseIlikeOnPg()`, `setUseIlikeOnPg(bool)` | deprecated, no effect | PostgreSQL LIKE is always ILIKE |

### `Column`

| Method | What it does |
|---|---|
| `__construct($fieldName, $label, $visible, $exportable, $href, $target, $innerElementCssStyle, $innerElementCssClass, $outerElementCssStyle, $outerElementCssClass, $innerElementAttributes, $outerElementAttributes)` | see [Columns](#columns) |
| `static createFromJsonString(string)`, `static createFromAssocArray(array)`, `setPropertyValue(string, $value)`, `jsonSerialize()` | JSON form round trip; unknown keys ignored, values cast to the property type |
| `getFieldName()`, `setFieldName()`, `getLabel()`, `setLabel()`, `getIndex()`, `setIndex()` | identity and order |
| `getFormat()`, `setFormat()`, `isNumeric()` | `Column::FORMAT_INTEGER`, `FORMAT_FLOAT`, `FORMAT_BOOLEAN`, `FORMAT_DATE`, `FORMAT_TIME`, `FORMAT_DATETIME`, `FORMAT_STRING` |
| `getHref()`, `setHref()`, `getTarget()`, `setTarget()` | link template and target (`''` = none) |
| `get/setInnerElementCssStyle()`, `get/setInnerElementCssClass()`, `get/setOuterElementCssStyle()`, `get/setOuterElementCssClass()` | CSS of the content and the cell |
| `get/setInnerElementAttributes()`, `get/setOuterElementAttributes()` | extra attributes: JSON object string or array |
| `isVisible()`, `setVisible()`, `isExportable()`, `setExportable()`, `isSearchable()`, `setSearchable()`, `isSortable()`, `setSortable()` | switches |
| `getSearchOperator()`, `setSearchOperator()`, `getWildcardMode()`, `setWildcardMode()` | search tuning; `Column::WILDCARD_CONTAINS`, `WILDCARD_STARTS_WITH`, `WILDCARD_ENDS_WITH` |
| `isCaseInsensitive()`, `setCaseInsensitive(?bool)`, `isCaseInsensitiveSort()`, `setCaseInsensitiveSort(?bool)` | case handling |
| `getJsCallback()`, `setJsCallback(?string)` | cell callback |

### `ActionButton`

| Method | What it does |
|---|---|
| `__construct(string $label, string $href, ?string $icon = null)` | label (HTML), href template, icon classes |
| `static createFromAssocArray(array)`, `jsonSerialize()` | JSON form (`label`, `href`, `icon`, `attributeList`, `jsCallback`, `cssClass`) |
| `getLabel()`, `setLabel()`, `getHref()`, `setHref()`, `getIcon()`, `setIcon()` | basics |
| `addAttribute(string, string)`, `setAttributes(array)`, `getAttributes()` | attributes of the link; placeholders replaced except in `on*` |
| `getCssClass()`, `setCssClass(?string)` | extra classes of the link |
| `getJsCallback()`, `setJsCallback(?string)` | per-row callback; `setJsCallbackk()` is a deprecated misspelled alias |

### `GridRequest` and `GridQuery`

| Member | What it does |
|---|---|
| `GridRequest::fromArray(array $query, int $maxRowsPerPage = 1000): GridRequest` | parse `page`, `limit`, `sort`, `dir`, `searchParams`, `load_grid_by_name`, `export_grid_to_excel`, `export_grid_to_csv` |
| `$page`, `$limit` (null = not sent, 0 = all), `$sort`, `$dir`, `$filters`, `$gridName`, `$export` | the parsed values |
| `dirSql(): ?string`, `isExport(): bool` | `ASC` / `DESC`; export requested |
| `GridRequest::OPERATORS`, `EXPORT_XLSX`, `EXPORT_CSV` | constants |
| `new GridQuery(string $sql, array $columns, string $alias, string $engine)` | statement builder for one grid, no connection |
| `build(array $filters, array $options = []): array` | `['data', 'count', 'totals', 'bindings']`; options `sortField`, `sortDir`, `page`, `rowsPerPage`, `allRows`, `wildcardContains`, `totals` |
| `GridQuery::operatorSql()`, `quoteColumn()`, `castToText()`, `sanitizeAlias()` | static helpers |

`Laf\UI\Grid\PhpGrid\Settings` is a small holder of action buttons kept for compatibility; the grid does not use
it. `Laf\UI\Grid\SimpleTable` is deprecated.

## See also

- [09-lists-and-tables.md](09-lists-and-tables.md): server-rendered tables without JavaScript
- [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md): generated list pages and related-record tabs
- [11-pages-and-ui.md](11-pages-and-ui.md): `Html`, `AdminPage`, `HtmlContainer`, tabs
- [13-database.md](13-database.md): `Db`, bindings, `Identifier` quoting
- [17-security.md](17-security.md): escaping, CSRF on delete links
- [18-testing-and-tooling.md](18-testing-and-tooling.md): the PhpGrid test suites
- `sample_files/grid-callback-examples.md`: every callback in detail
