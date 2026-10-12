Review of the AJAX grid pair `src/Laf/UI/Grid/PhpGrid/PhpGrid.php` (+ `Column`, `ActionButton`) and `sample_files/grid.js` on `improvements1`, 2026-10-12: 14 server-side and 11 client-side defects, the compatibility contract the applications depend on, and what the rewrite changed (notes in `2026-10-12-grid-review-notes.md`).

# What the pair does

`PhpGrid` takes a name, an SQL statement and columns; `draw()` renders the table shell and an inline script that
creates `new Grid(name)`; `bootstrap()` answers the grid's own requests (`?load_grid_by_name=…&page=&limit=&sort=
&dir=&searchParams=[…]`) with JSON, CSV or XLSX. `grid.js` fetches the JSON and renders rows, sort buttons, search
inputs, pagination, exports, the column menu and the three callback kinds. asm (53 files) and routerarena (54 files)
build every list page on it; both apps ship a copy of grid.js in their webpack bundle (routerarena: byte-identical to
the LAF sample; asm: an older one without `Grid.autoInit`).

# Compatibility contract (verified in both applications)

- `new PhpGrid('name')`, fluent `setSqlQuery/setTitle/setRowsPerPage/addColumn/addActionButton/setAllowExport/
  setSortDetails/setEnableWildCardSearch/setShowSearchBar/setShowTitle/setDebug`, `getColumn()->setJsCallback()`,
  `isReadyToHandleRequests()`, `bootstrap()`, `draw()`; `Column` positional constructor (2–9 args), css setters;
  `ActionButton(label, href, icon)->addAttribute('onclick', …)`. Pages run under the apps' `ob_start()`.
- SQL always wrapped as a derived table; asm keeps top-level `ORDER BY` / `GROUP BY` inside it.
- Old grid.js reads the payload literally: `dir` echoed in lower case, `icon` key present even when null,
  `actionButtons.length`, `href != null`, `columnTotals.length` (`[]` when none), header/search rows as `thead tr[1]`/`tr[2]`.
- Both apps reset `window.grid = []` after loading grid.js.

# Server-side findings (PhpGrid.php 1,573 lines before)

1. **Fatal export**: `export_grid_to_excel=1` (first entry of the JS export menu) called PhpSpreadsheet, a package
   dropped from LAF on 2026-10-12; the imports and the `JetBrains\PhpStorm` attributes pointed at absent packages.
2. **Double URL decoding** of `searchParams` and `sort` (`$_GET` is already decoded): `+` became a space, `%25` a `%`.
3. **Unclamped page**: `page=0` → `OFFSET -10`, `page=1.5` → `OFFSET 7.5`; `start` read and discarded.
4. **Unvalidated filter items**: a non-object item warned, a `null` value threw a TypeError in `addParam(string)`.
5. **PostgreSQL case handling never ran**: `isTextualColumn()` ended in `|| … && false`, formats were learned from
   `getColumnMeta()` only after the query, so PG LIKE was case-sensitive; the type map missed `LONGLONG`, `INT24`,
   `int4`, `int8`, `numeric`, `bool`, `timestamptz`; a developer-set format was overwritten.
6. **Unquoted identifiers** in SELECT/WHERE/ORDER BY/SUM: a reserved-word alias (`order`, `desc`) broke the grid; the
   grid name went unsanitised into the subquery alias and SQL comments.
7. **Totals computed twice** per request, every exception swallowed; `?array` typed but used as an array.
8. **Errors hidden**: `execute()` returned `true` after a caught SQL error, so exports sent a 200 file with only the
   header row; `allowExport=false` returned `null` and the page kept rendering HTML after `bootstrap()`.
9. Dead code: `setRowCount()` once per column, unused `$data`/`$header`, unreachable `!is_array`, `break` after `return`;
   `getGeneratedSqlQuery(): string` returned null before a run.
10. **draw()**: mixed Bootstrap 4/5 markup, inline `onclick`s, `aria-labelledby` to a missing id, grid name unescaped
    in HTML and JS, `#[Pure]` on a method with side effects.
11. **Column**: untyped properties; `trim(null)` deprecation; `new Column('x')` left label/css `null` while getters were
    `: string` (TypeError in `draw()` and exports); `setPropertyValue(string $value)` turned `visible: false` into `""`
    and threw on `null`, so the class could not round-trip its own JSON.
12. **ActionButton**: `getIcon(): string` threw for the default `null`; the setter was `setJsCallbackk()`.
13. Docs described a `callback` property and `setCallback()` that did not exist, and a string result as "display
    text" while the code and the apps use it as HTML; the row callback was undocumented.
14. Tests never covered the JSON payload, exports, PG case handling, column tuning or `draw()` escaping; two
    `assertEquals()` passed a delta PHPUnit 9 ignores.

# Client-side findings (grid.js 1,553 lines before, ~800 of them accessor boilerplate)

1. **Action-button callbacks broken both ways**: a button with href+label+icon never had its callback called, a
   callback-only button was never rendered, and `return ''` on a `false` result aborted the whole `draw()` (overlay
   stuck, pagination unbound). Column callbacks ignored the documented object form and the third argument.
2. `fetchContentElementReferences()` threw `ReferenceError: name`; `initialize()` called it twice.
3. Grid accessors (`showSearchBar`, `showTitleBar`, `deferInitialize`, `contentGridTitleRow`) were declared on `Column`.
4. `className = undefined` wrote `class="undefined"` on every cell without a class.
5. Filtering kept `currentPage`: filtering on page 5 of a result with one page showed an empty grid.
6. The header always added an actions `<th>`, the totals footer never did: totals shifted one column left.
7. `buildUrl()` concatenated `location.search + '&…'`; `encodeURI()` left `#`, `&`, `+` in values → truncated searches.
8. Title `colSpan` only grew; `aria-expande` typo; `substr()`; `console.log` on every load; totals formatted `en-US`.
9. Client exports dumped every column (hidden ones too) with raw field names and threw without SheetJS; the
   "Excel (PHPSpreadsheet)" entry pointed at the fatal path.
10. Controls were `<a href="javascript:;">` with invalid `gridName`/`fieldName` attributes, no `aria-sort`, no
    keyboard support; search was Enter-only with no clear action.
11. `table-responsive` forced to `overflow: visible` (overlay and dropdowns) disables horizontal scrolling on narrow
    screens. **Left as-is** (changing it clips the action dropdowns on Bootstrap 5.1).

# Improvements made beyond the fixes

- `GridRequest` (typed request) and `GridQuery` (pure SQL builder) are unit-tested without a database on both
  engines; `PhpGrid` is a facade over them. PHPStan now covers the folder (only `SimpleTable` stays excluded).
- New operators `notlike`, `in`, `between`, `isnull`, `notnull`; per-column `searchable` / `sortable`; typed numeric
  cells in XLSX; CSV BOM opt-in; `rowsPerPageOptions`; `setInlineScript(false)` + `Grid.autoInitAll()` for CSP pages;
  `toArray()` / `toJson()` / `getHttpStatus()`; `execute()` false on failure; 400/403/500 answers.
- grid.js: lean class with event delegation, `<button>` controls with ARIA, `URLSearchParams`, numeric right-align,
  "1–10 of 57 · Page 1 of 6", clear-filters button, Escape clears a field, `Grid.searchDebounceMs`, client exports
  with labels and only visible + exportable columns (server fallback without SheetJS), `{column}` placeholders in
  button attributes, empty-state row, `Grid.debug`.

# Behaviour changes to know about

- LIKE on PostgreSQL is case-insensitive now (`CAST(col AS TEXT) ILIKE`), as on MySQL.
- `export_grid_to_excel=1` and `=2` both produce the OpenSpout workbook.
- A column callback returning `null`/`undefined` renders the default cell (it blanked the cell before).
- `execute()` returns false on an SQL error (it returned true); a refused or failed export answers 403/500.
- Column JSON: css strings are `''` instead of `null` when unset; two new keys `searchable`, `sortable`;
  ActionButton JSON gets `cssClass`.
- One-column grids are valid (two were required).

# Deferred

Extended search grammar in grid.js (between / in / null prefixes; the PHP operators exist), custom DOM events,
editable page number, horizontal scrolling of the table on narrow screens.
