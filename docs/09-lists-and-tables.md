# Lists and tables

Not every list needs the AJAX grid of [10-grids.md](10-grids.md). A dashboard widget, a printable report, an
e-mail body or a short "latest five employees" box is simpler as a server-rendered HTML table: read the rows with
`find()` / `query()`, put them in a `Laf\UI\Table\Table`, format numbers and dates with `Laf\Util\Locale`, link each
row to its view or edit page with `Laf\Util\UrlParser`, and page through long results with `Query::paginate()` and
`Laf\Database\Pagination`. Everything on this page renders on the server in one request, with no JavaScript.

**On this page**

- [When to use a table, when a grid](#when-to-use-a-table-when-a-grid)
- [A table from an array: `Table::fromArray()`](#a-table-from-an-array-tablefromarray)
- [Building rows and cells by hand](#building-rows-and-cells-by-hand)
- [Escaping: text vs raw HTML](#escaping-text-vs-raw-html)
- [A complete employee list page](#a-complete-employee-list-page)
- [Pagination with `Pagination`](#pagination-with-pagination)
- [Formatting values with `Locale`](#formatting-values-with-locale)
- [Linking rows with `UrlParser`](#linking-rows-with-urlparser)
- [Reference](#reference)
- [See also](#see-also)

## When to use a table, when a grid

| Need | Use |
|---|---|
| Users search, sort, page and export a big list interactively | `PhpGrid` ([10-grids.md](10-grids.md)) |
| A fixed, small or pre-filtered list (dashboard box, related records, report, PDF, e-mail) | `Table` (this page) |
| A JSON list for an API | `Query::paginate()` and `json_encode($page)` ([04-queries.md](04-queries.md)) |
| A list with server-side paging but no JavaScript | `Table` + `Pagination` (this page) |

A `Table` is a normal UI component (`Laf\UI\ComponentInterface`), so it goes into a `Card`, a `TabItem`, a `Div` or
an `AdminPage` with `addComponent()` like any other component (see [11-pages-and-ui.md](11-pages-and-ui.md)).

> **Note:** `Laf\UI\Grid\SimpleTable` is deprecated. Do not use it in new code; use `Laf\UI\Table\Table` for
> server-rendered tables and `PhpGrid` for interactive ones.

## A table from an array: `Table::fromArray()`

The quickest route: a list of rows (each a list or an associative array of scalar values) and an optional list of
header labels. Every value becomes an **escaped text** cell; the keys of associative rows are ignored, only the
order counts.

```php
use Laf\UI\Table\Table;

$table = Table::fromArray(
    [
        ['Linus <b>Torvalds</b>', 'linus@example.com', '4800.00'],
        ['Margaret Hamilton', 'margaret@example.com', null],
        ['Grace Hopper', 'grace@example.com', '7250.00'],
    ],
    ['Name', 'E-mail', 'Salary'],
    'employees'                     // table id; omitted = a generated "tbl_xxxxxxxx"
);

echo $table->draw();               // or: echo $table;  (__toString() calls draw())
```

Output (line breaks added):

```html
<table id='employees'>
<thead><tr id='employees_head_0'><th id='employees_head_0_0'>Name</th><th id='employees_head_0_1'>E-mail</th><th id='employees_head_0_2'>Salary</th></tr></thead>
<tbody>
<tr id='employees_0'><td id='employees_0_0'>Linus &lt;b&gt;Torvalds&lt;/b&gt;</td><td id='employees_0_1'>linus@example.com</td><td id='employees_0_2'>4800.00</td></tr>
<tr id='employees_1'><td id='employees_1_0'>Margaret Hamilton</td><td id='employees_1_1'>margaret@example.com</td><td id='employees_1_2'></td></tr>
<tr id='employees_2'><td id='employees_2_0'>Grace Hopper</td><td id='employees_2_1'>grace@example.com</td><td id='employees_2_2'>7250.00</td></tr>
</tbody></table>
```

Things to notice:

- The markup in the name was escaped: `fromArray()` and `addRow()` never produce HTML from data.
- `null` became an empty cell.
- Every row and cell has an id derived from the table id: body rows `{table}_{row}`, header rows
  `{table}_head_{n}`, footer rows `{table}_foot_{n}`, cells `{row id}_{column}`. That makes it easy to target a
  cell from JavaScript or a browser test.
- A row that is a plain scalar (`'single'`) becomes a one-cell row.

Add the Bootstrap 5 look with `bootstrap()`: `table table-striped table-hover table-sm`, wrapped in
`<div class='table-responsive'>` so wide tables scroll sideways on a phone instead of breaking the layout.

```php
$table->bootstrap()->setCaption('Staff & contractors');
echo $table;
```

```html
<div class='table-responsive'><table id='employees' class='table table-striped table-hover table-sm'><caption>Staff &amp; contractors</caption><thead>...</thead><tbody>...</tbody></table></div>
```

`bootstrap(false)` leaves out the stripes, `bootstrap(true, false)` the responsive wrapper. The caption is escaped
text.

## Building rows and cells by hand

When cells need classes, links, colspans or a second header row, build the parts yourself:

| Class | Draws | Notes |
|---|---|---|
| `Laf\UI\Table\Table` | `<table>` with `<caption>`, `<thead>`, `<tbody>`, `<tfoot>` | a `ComponentInterface` |
| `Laf\UI\Table\Tr` | `<tr>` | holds `Cell` objects |
| `Laf\UI\Table\Td` | `<td>` | extends `Cell` |
| `Laf\UI\Table\Th` | `<th>` | extends `Cell` |

A two-row header with a `rowspan` and a `colspan`, a right-aligned cell with a data attribute, and a footer:

```php
use Laf\UI\Table\Table;
use Laf\UI\Table\Th;
use Laf\UI\Table\Tr;

$t = new Table('salaries');
$t->addCssClass('table')->addAttribute('data-sortable', 'yes')->addCssStyleItem('width', '100%');

$t->addTheadRow((new Tr())
    ->addCell((new Th())->setText('Employee')->setRowSpan(2))
    ->addCell((new Th())->setText('Pay')->setColSpan(2)->alignCenter()));
$t->addTheadRow((new Tr())->addCells(['Monthly', 'Yearly'], true));   // true = <th> cells

$t->addRow(['Ada Lovelace', '5,400.00', '64,800.00']);
$t->getTbodyRows()[0]->getCell(1)->alignRight()->addParam('data-raw', "5400 'x'");

$t->getTfoot()->addCells(['Total', '5,400.00', '64,800.00']);
echo $t;
```

```html
<table id='salaries' class='table' style='width:100%' data-sortable='yes'>
<thead>
  <tr id='salaries_head_0'><th id='salaries_head_0_0' rowspan='2'>Employee</th><th id='salaries_head_0_1' colspan='2' class='text-center'>Pay</th></tr>
  <tr id='salaries_head_1'><th id='salaries_head_1_0'>Monthly</th><th id='salaries_head_1_1'>Yearly</th></tr>
</thead>
<tbody>
  <tr id='salaries_0'><td id='salaries_0_0'>Ada Lovelace</td><td id='salaries_0_1' class='text-end' data-raw='5400 &apos;x&apos;'>5,400.00</td><td id='salaries_0_2'>64,800.00</td></tr>
</tbody>
<tfoot><tr id='salaries_foot_0'><td id='salaries_foot_0_0'>Total</td><td id='salaries_foot_0_1'>5,400.00</td><td id='salaries_foot_0_2'>64,800.00</td></tr></tfoot>
</table>
```

Two attribute APIs exist side by side on `Table` and both are drawn: the original `addClass()` / `addStyle()` /
`addParam()` and the component ones from `ComponentTrait` (`addCssClass()`, `addCssStyleItem()`, `addAttribute()`).
`Tr` and `Cell` only have the original trio. The header has two entry points as well: `getThead()` is the first
header row (the historical single-row API, drawn only when it has cells), and `addTheadRow()` appends further rows
after it.

`setPrettyPrint(true)` on the table indents the markup and adds an `<!-- ENDOF Table {id}-->` comment, handy when
you read the source of a page.

## Escaping: text vs raw HTML

Cells hold HTML. Which methods escape is the most important thing to know about this class:

| Call | Content is |
|---|---|
| `Table::fromArray($rows, $headers)`, `Table::addRow($values)`, `Tr::addCells($values)` | escaped text |
| `Cell::setText($text)` (`Td`, `Th`) | escaped text (escaped when you call it) |
| `new Td($html)`, `new Th($html)`, `Cell::setData($html)` | **raw HTML** |
| `Table::setCaption($text)` | escaped text |
| class names, style properties and values, attribute names and values (all levels) | always escaped |

Raw HTML is there because cells often hold links, badges and buttons. Escape the data you put into such a cell
yourself with `Util::e()` (text) and `Util::attr()` (a double-quoted attribute value):

```php
use Laf\UI\Table\Td;
use Laf\Util\Util;

$cell = new Td('<a href="' . Util::attr($url) . '">' . Util::e($employee->getLastNameVal()) . '</a>');
```

```text
(new Td('<b>raw</b>'))->draw()            <td><b>raw</b></td>
(new Td())->setText('<b>text</b>')->draw()  <td>&lt;b&gt;text&lt;/b&gt;</td>
```

> **Note:** `getData()` returns the stored HTML, so after `setText('a & b')` it returns `a &amp; b`. `Util::e()`
> does not double-encode: a value that already contains an entity such as `&amp;` is left as it is. See
> [17-security.md](17-security.md) for the escaping rules of the whole framework.

## A complete employee list page

The page below lists employees with their company, birth date, salary and an Edit button, two per page, and the
range in the footer. It reads model objects through the [query builder](04-queries.md), follows the
`company_id` foreign key with the memoised `getCompanyIdObj()` (one query per company, not per row), formats with
`Locale` and links with `UrlParser`.

```php
use Laf\UI\Table\Table;
use Laf\UI\Table\Td;
use Laf\UI\Table\Th;
use Laf\UI\Table\Tr;
use Laf\Util\Locale;
use Laf\Util\UrlParser;
use Laf\Util\Util;
use MyApp\Employee;

// route: ?module=hr&submodule=employee&action=list&id=all&page=2
$page = Employee::query()
    ->orderBy('last_name')
    ->paginate($_GET['page'] ?? 1, 2);

$table = (new Table('employee_list'))->bootstrap();

$head = $table->getThead();
foreach (['Name', 'Company', 'Born', 'Salary', 'Active', ''] as $label) {
    $head->addCell((new Th())->setText($label));
}
$head->getCell(3)->alignRight();

foreach ($page as $employee) {
    /** @var Employee $employee */
    $name = $employee->getFirstNameVal() . ' ' . $employee->getLastNameVal();
    $viewLink = UrlParser::getViewLink($employee->getIdVal());
    $editLink = UrlParser::getUpdateLink($employee->getIdVal());

    $tr = new Tr();
    $tr->addCell(new Td('<a href="' . Util::attr($viewLink) . '">' . Util::e($name) . '</a>'));
    $tr->addCell((new Td())->setText($employee->getCompanyIdObj()->getNameVal() ?? '-'));
    $tr->addCell((new Td())->setText(Locale::formatDate($employee->getBirthDateVal(), '-')));
    $tr->addCell((new Td())->setText(Locale::formatNumber($employee->getSalaryVal(), 2, '-'))->alignRight());
    $tr->addCell((new Td())->setText($employee->getIsActiveVal() ? 'Yes' : 'No'));
    $tr->addCell(new Td('<a class="btn btn-sm btn-outline-primary" href="' . Util::attr($editLink) . '">Edit</a>'));
    if (!$employee->getIsActiveVal()) {
        $tr->addClass('text-muted');
    }
    $table->addTr($tr);
}

$table->getTfoot()->addCell(
    (new Td())->setText("Showing {$page->from}-{$page->to} of {$page->total}")->setColSpan(6)
);

echo $table->setPrettyPrint(true)->draw();
```

Output with the example data (five employees, page 2):

```html
<div class='table-responsive'><table id='employee_list' class='table table-striped table-hover table-sm'>
  <thead>
    <tr id='employee_list_head_0'>
      <th id='employee_list_head_0_0'>Name</th>
      <th id='employee_list_head_0_1'>Company</th>
      <th id='employee_list_head_0_2'>Born</th>
      <th id='employee_list_head_0_3' class='text-end'>Salary</th>
      <th id='employee_list_head_0_4'>Active</th>
      <th id='employee_list_head_0_5'></th></tr>
  </thead>
  <tbody>
    <tr id='employee_list_0' class='text-muted'>
      <td id='employee_list_0_0'><a href="?module=hr&amp;submodule=employee&amp;action=view&amp;id=6">Grace Hopper</a></td>
      <td id='employee_list_0_1'>Globex &amp; Sons</td>
      <td id='employee_list_0_2'>December 09, 1979</td>
      <td id='employee_list_0_3' class='text-end'>7,250.00</td>
      <td id='employee_list_0_4'>No</td>
      <td id='employee_list_0_5'><a class="btn btn-sm btn-outline-primary" href="?module=hr&amp;submodule=employee&amp;action=update&amp;id=6">Edit</a></td></tr>
    <tr id='employee_list_1'>
      <td id='employee_list_1_0'><a href="?module=hr&amp;submodule=employee&amp;action=view&amp;id=4">Ada Lovelace</a></td>
      <td id='employee_list_1_1'>Acme Corp</td>
      <td id='employee_list_1_2'>December 10, 1985</td>
      <td id='employee_list_1_3' class='text-end'>5,400.00</td>
      <td id='employee_list_1_4'>Yes</td>
      <td id='employee_list_1_5'><a class="btn btn-sm btn-outline-primary" href="?module=hr&amp;submodule=employee&amp;action=update&amp;id=4">Edit</a></td></tr>
  </tbody>
  <tfoot>
    <tr id='employee_list_foot_0'>
      <td id='employee_list_foot_0_0' colspan='6'>Showing 3-4 of 5</td></tr>
  </tfoot>
</table></div>
<!-- ENDOF Table employee_list-->
```

Why it is built this way:

- `paginate()` runs two statements (a `COUNT(*)` and the page with `LIMIT/OFFSET`); the rows are full `Employee`
  objects, so the generated getters and `getCompanyIdObj()` work.
- An employee without a company gets an *empty* `Company` from `getCompanyIdObj()`, whose `getNameVal()` is `null`;
  `?? '-'` turns that into a dash.
- The link cells are raw HTML (`new Td(...)`); the name inside is escaped with `Util::e()`, the URL with
  `Util::attr()`. Everything else uses `setText()`.
- For a list with joins, aggregates or columns from several tables, read the rows with `Db::getAllAssoc()` and a
  hand-written SQL instead (see [13-database.md](13-database.md)), or use a grid.

To show it inside the admin layout, add the table to the page card: `$page->addComponent($table)` (see
[11-pages-and-ui.md](11-pages-and-ui.md)).

## Pagination with `Pagination`

`Query::paginate($page = 1, $perPage = 25, $asArrays = false)` returns a `Laf\Database\Pagination`:

- `$page` is 1-based; anything below 1 or non-numeric means 1, so `$_GET['page'] ?? 1` can go in directly.
- The query's own `limit()` / `offset()` are ignored.
- A page past the end is **empty** (not the last page).
- `$asArrays = true` returns rows as associative arrays instead of objects.

| Member | Type | Meaning |
|---|---|---|
| `$items` | `array` | the objects (or arrays) on this page |
| `$total` | `int` | rows matching the query, all pages together |
| `$page` | `int` | current page, 1-based |
| `$perPage` | `int` | page size (at least 1) |
| `$lastPage` | `int` | number of the last page; 1 when there are no rows |
| `$from` / `$to` | `int` | 1-based position of the first / last item of this page in the whole result; 0 on an empty page |
| `hasMore()` | `bool` | there is a page after this one |
| `hasPrevious()` | `bool` | there is a page before this one |
| `isEmpty()` | `bool` | this page has no items |
| `count()` | `int` | items on this page (`Countable`, so `count($page)` works) |
| `getIterator()` | `ArrayIterator` | `foreach ($page as $item)` (`IteratorAggregate`) |
| `toArray()` / `jsonSerialize()` | `array` | `items` plus the counts; `json_encode($page)` works |

The page of the example above, as returned:

```json
{"total":5,"page":2,"perPage":2,"lastPage":3,"from":3,"to":4,"count":2,"hasMore":true,"hasPrevious":true}
```

Page 9 of the same query: `isEmpty()` is `true`, `from` is `0`, `lastPage` is still `3`.

`json_encode()` of a page, with `$asArrays = true` and one row per page:

```json
{"items":[{"id":4,"company_id":4,"first_name":"Ada","last_name":"Lovelace","email":"ada@example.com","birth_date":"1985-12-10","salary":"5400.00","is_active":1,"notes":null,"created_on":"2026-10-14 18:55:00","created_by":1,"updated_on":null,"updated_by":null}],"total":5,"page":1,"perPage":1,"lastPage":5,"from":1,"to":1}
```

A Bootstrap pager for it. The links keep the route and add `page=N`; `UrlParser::getListLink()` gives the list
URL of the current module in either URL style, so the separator is chosen at run time:

```php
use Laf\Database\Pagination;
use Laf\Util\UrlParser;
use Laf\Util\Util;

function pager(Pagination $page): string
{
    if ($page->lastPage <= 1) {
        return '';
    }
    $base = UrlParser::getListLink();
    $glue = str_contains($base, '?') ? '&' : '?';
    $item = function (int $number, string $label, bool $disabled = false, bool $active = false) use ($base, $glue): string {
        $class = 'page-item' . ($disabled ? ' disabled' : '') . ($active ? ' active' : '');
        $href = Util::attr($base . $glue . 'page=' . $number);
        return '<li class="' . $class . '"><a class="page-link" href="' . $href . '">' . Util::e($label) . '</a></li>';
    };
    $html = $item($page->page - 1, 'Previous', !$page->hasPrevious());
    for ($n = 1; $n <= $page->lastPage; $n++) {
        $html .= $item($n, (string)$n, false, $n === $page->page);
    }
    $html .= $item($page->page + 1, 'Next', !$page->hasMore());
    return '<nav aria-label="Pages"><ul class="pagination pagination-sm">' . $html . '</ul></nav>';
}

echo pager($page);
```

```html
<nav aria-label="Pages"><ul class="pagination pagination-sm">
<li class="page-item"><a class="page-link" href="?module=hr&amp;submodule=employee&amp;action=list&amp;id=all&amp;page=1">Previous</a></li>
<li class="page-item"><a class="page-link" href="?module=hr&amp;submodule=employee&amp;action=list&amp;id=all&amp;page=1">1</a></li>
<li class="page-item active"><a class="page-link" href="?module=hr&amp;submodule=employee&amp;action=list&amp;id=all&amp;page=2">2</a></li>
<li class="page-item"><a class="page-link" href="?module=hr&amp;submodule=employee&amp;action=list&amp;id=all&amp;page=3">3</a></li>
<li class="page-item"><a class="page-link" href="?module=hr&amp;submodule=employee&amp;action=list&amp;id=all&amp;page=3">Next</a></li>
</ul></nav>
```

With pretty URLs the base is `/hr/employee/list/all`, so the links become `/hr/employee/list/all?page=2`. For very
long results print a window of page numbers around `$page->page` instead of all of them.

> **Note:** `OFFSET` paging gets slower on deep pages and can skip or repeat rows when the data changes between
> requests. Always order by something stable (add the primary key as a last `orderBy()` when the main sort column
> is not unique). For batch jobs use `cursor()` or `chunk()` ([04-queries.md](04-queries.md)).

## Formatting values with `Locale`

The database returns ISO dates (`1979-12-09`) and decimal strings (`7250.00`). `Laf\Util\Locale` turns them into
display text using the `locale.*` settings, the same formats the form inputs use, so lists and forms agree:

| Method | Example input | Output with default settings |
|---|---|---|
| `Locale::formatNumber($value, $decimals = 2, $empty = '')` | `'7250.5'` | `7,250.50` |
| `Locale::formatNumber($value, null)` | `'12.50'` | `12.5` (keeps the digits the value has) |
| `Locale::formatDate($value, $empty = '')` | `'1979-12-09'` | `December 09, 1979` |
| `Locale::formatDateTime($value, $empty = '')` | `'2026-10-14 09:30:00'` | `October 14, 2026 09:30` |
| `Locale::formatTime($value, $empty = '')` | `'14:30:00'` | `2:30 PM` |

- Input may be an ISO string, a `DateTimeInterface`, a value already in the display format, an int/float, or a
  user-typed number; `null`, `''` and unparsable values give `$empty` (pass `'-'` for a visible placeholder).
- The output is plain text: put it in a cell with `setText()`.

With `locale.decimal_separator = ','`, `locale.thousands_separator = '.'` and `locale.date.format = 'd.m.Y'`:

```text
Locale::formatNumber('7250.5')       7.250,50
Locale::formatDate('1979-12-09')      09.12.1979
Locale::formatNumber('12.50', null)  12,5
```

The full list of keys, plus `parseDate()` / `parseNumber()` for typed input, is in
[16-settings-locale-utilities.md](16-settings-locale-utilities.md).

## Linking rows with `UrlParser`

LAF routes are `/module/submodule/action/id` (pretty URLs) or `?module=&submodule=&action=&id=` (with
`settings.use_pretty_url` off). The link builders produce URLs for the **current** module and submodule, so the
list page of `hr/employee` links to the view page of `hr/employee` without spelling either out:

| Builder | Pretty URL (`/hr/employee/list/all`) | Query-string URL |
|---|---|---|
| `UrlParser::getViewLink(6)` | `/hr/employee/view/6` | `?module=hr&submodule=employee&action=view&id=6` |
| `UrlParser::getUpdateLink(6)` | `/hr/employee/update/6` | `?module=hr&submodule=employee&action=update&id=6` |
| `UrlParser::getDeleteLink(7)` | `/hr/employee/delete/7` | `?module=hr&submodule=employee&action=delete&id=7` |
| `UrlParser::getListLink()` | `/hr/employee/list/all` | `?module=hr&submodule=employee&action=list&id=all` |
| `UrlParser::getNewLink()` (= `getInsertLink()`) | `/hr/employee/new` | `?module=hr&submodule=employee&action=new` |
| `UrlParser::getLink('export', null, false)` | `/hr/employee/export` | `?module=hr&submodule=employee&action=export` |

- The id may be an int, a numeric string or a UUID; anything else (or `null`) means "the id of the current
  route".
- The module and submodule are URL-encoded; the builders return the URL unescaped for HTML, so wrap it in
  `Util::attr()` when you put it in an attribute (the `&` become `&amp;`, as in the output above).
- To link to another table's page (the company of an employee), build the URL yourself, e.g.
  `'?module=hr&submodule=company&action=view&id=' . rawurlencode((string)$companyId)`; generated pages use the
  `module_for_table` setting for this (see [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md)).

> **Note:** a delete link is a state-changing GET. Generated pages add the form's CSRF token to it
> (`$form->withCsrfToken($url)`) and check it before deleting. If you put Delete buttons in your own tables, do the
> same, see [06-forms.md](06-forms.md) and [17-security.md](17-security.md).

## Reference

### `Laf\UI\Table\Table`

| Method | What it does | Notes |
|---|---|---|
| `__construct(?string $id = null)` | new empty table | null/empty id = generated `tbl_` + 8 hex chars |
| `static fromArray(array $rows, array $headers = [], ?string $id = null): Table` | table of escaped text cells | rows: lists or maps (order counts) or scalars |
| `addRow(array $values): static` | append a body row of escaped text cells | |
| `addTr(Tr $tr): static` | append a body row you built | sets the row index |
| `getTbodyRows(): array` / `setTbodyRows(array $rows): static` | body rows | setter re-indexes |
| `getThead(): Tr` / `setThead(Tr $row): static` | the first header row | drawn only when it has cells |
| `addTheadRow(Tr $row): static` / `setTheadRows(array $rows): static` | extra header rows after the first | setter keeps `getThead()` |
| `getTheadRows(): array` | every header row in draw order | |
| `getTfoot(): Tr` / `setTfoot(Tr $row): static` | the footer row | drawn only when it has cells |
| `getCaption(): string` / `setCaption(string $caption): static` | `<caption>` | escaped |
| `getId(): string` / `setId(?string $id): static` | table id, prefix of row and cell ids | |
| `bootstrap(bool $striped = true, bool $responsive = true): static` | `table [table-striped] table-hover table-sm` (+ responsive wrapper) | |
| `isResponsive(): bool` / `setResponsive(bool $responsive): static` | wrap in `<div class='table-responsive'>` | |
| `addClass()`, `setClasses()`, `getClasses()` | classes (original API) | drawn together with `addCssClass()` |
| `addStyle($property, $value)`, `setStyles()`, `getStyles()` | inline style (original API) | drawn together with `addCssStyleItem()` |
| `addParam($name, $value)`, `setParams()`, `getParams()` | attributes (original API) | `id`, `class`, `style` keys are skipped |
| `addCssClass()`, `addCssStyleItem()`, `addAttribute()`, ... | the `ComponentInterface` attribute API | see [11-pages-and-ui.md](11-pages-and-ui.md) |
| `isPrettyPrint(): bool` / `setPrettyPrint(bool $prettyPrint): static` | indented output + end comment | |
| `getComponentCssControlClass(): string` | `Laf-UI-Table-Table` | ComponentInterface |
| `draw(): string` / `__toString(): string` | the HTML | |

### `Laf\UI\Table\Tr`

| Method | What it does | Notes |
|---|---|---|
| `addCell(Cell $cell): static` | append a `Td`/`Th` | |
| `addCells(array $values, bool $header = false): static` | append escaped text cells (`Th` when `$header`) | `null` = empty cell |
| `getCell(int $index): ?Cell`, `getCells(): array`, `setCells(array $cells): static` | the cells | |
| `hasCells(): bool`, `getCellCount(): int` | | |
| `addClass()`, `setClasses()`, `getClasses()`, `addStyle()`, `setStyles()`, `getStyles()`, `addParam()`, `setParams()`, `getParams()` | attributes of the `<tr>` | escaped |
| `getRowIndex()`, `setRowIndex(int)` | position in its section | set by the table |
| `getSection()`, `setSection(string)` | `Tr::SECTION_BODY` / `SECTION_HEAD` / `SECTION_FOOT` | set by the table at draw |
| `getHtmlId(): ?string` | `{table}_3`, `{table}_head_0`, `{table}_foot_0` | null when drawn standalone |
| `getTable()`, `setTable(Table)` | owning table | set by the table at draw |
| `isPrettyPrint()`, `setPrettyPrint(bool)` | | |
| `draw(): string` | the `<tr>` | |

### `Laf\UI\Table\Cell` (`Td`, `Th`)

| Method | What it does | Notes |
|---|---|---|
| `__construct(string $data = '')` | cell with content | **raw HTML** |
| `setData(?string $data): static` / `getData(): ?string` | content | **raw HTML** |
| `setText(?string $text): static` | content | escaped |
| `getTagName(): string` | `td` or `th` | |
| `setColSpan(int)`, `getColSpan()`, `setRowSpan(int)`, `getRowSpan()` | spans | 0 = no attribute |
| `alignLeft()`, `alignCenter()`, `alignRight()` | `text-start` / `text-center` / `text-end` | right-align numbers |
| `addClass()`, `setClasses()`, `getClasses()`, `addStyle()`, `setStyles()`, `getStyles()`, `addParam()`, `setParams()`, `getParams()` | attributes of the cell | escaped |
| `getHtmlId(): ?string` | `{row id}_{column}` | null when drawn standalone |
| `getColumnIndex()`, `setColumnIndex()`, `getRowIndex()`, `setRowIndex()`, `getRowHtmlId()`, `setRowHtmlId()`, `getTable()`, `setTable()` | position bookkeeping | set by the row/table at draw |
| `isPrettyPrint()`, `setPrettyPrint(bool)` | | |
| `draw(): string` | the cell | |

## See also

- [04-queries.md](04-queries.md): `find()`, `query()`, `paginate()`, `cursor()`, `chunk()`
- [10-grids.md](10-grids.md): the interactive AJAX grid
- [11-pages-and-ui.md](11-pages-and-ui.md): pages, cards and the component attribute API
- [12-routing-and-generated-pages.md](12-routing-and-generated-pages.md): `UrlParser` in full
- [16-settings-locale-utilities.md](16-settings-locale-utilities.md): `Locale` settings and parsing, `Util::e()` / `Util::attr()`
- [17-security.md](17-security.md): escaping rules and CSRF on delete links
