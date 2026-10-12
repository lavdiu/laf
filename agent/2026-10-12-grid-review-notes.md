Change notes for the PhpGrid + grid.js rewrite on `improvements1`, 2026-10-12 (findings in `2026-10-12-grid-review.md`): PhpGrid split into a facade over `GridRequest` and `GridQuery`, Column/ActionButton typed and JSON-serialisable, grid.js rewritten as a lean Bootstrap 5 class, 38 new tests, every suite and PHPStan green, verified in the browser with the new and the old grid.js.

# Files

| File | Change |
|---|---|
| `src/Laf/UI/Grid/PhpGrid/PhpGrid.php` | rewritten (1,573 → ~1,000 lines): typed properties, `static` fluent returns, `match`, no PhpSpreadsheet / JetBrains imports; `execute()` false on failure; `toArray()`, `toJson()`, `getHttpStatus()`, `getErrorCode()`, `getRows()`, `getRequest()`, `getGeneratedBindings()`; `outputJsonWithHeaders($reload, $terminate)`, `exportToCsv($terminate)`, `exportToXlsx($terminate)`, `bootstrap($terminate)`; `setMaxRowsPerPage()`, `setRowsPerPageOptions()`, `setInlineScript()`, `setCsvBom()`; `draw()` Bootstrap 5 with escaped attributes |
| `src/Laf/UI/Grid/PhpGrid/GridRequest.php` | new: typed request (page, limit, sort, dir, filters, gridName, export) |
| `src/Laf/UI/Grid/PhpGrid/GridQuery.php` | new: data / count / totals SQL with bindings, engine-aware quoting and casts |
| `src/Laf/UI/Grid/PhpGrid/Column.php` | typed public properties, `JsonSerializable` (same keys + `searchable`, `sortable`), null-safe constructor and getters, `setPropertyValue(string, mixed)` with per-property casts, typed tuning setters, `FORMAT_*` / `WILDCARD_*` constants, `isNumeric()` |
| `src/Laf/UI/Grid/PhpGrid/ActionButton.php` | typed, `JsonSerializable` (+ `cssClass`), `getIcon(): ?string`, `setJsCallback()`, `setJsCallbackk()` deprecated alias, `createFromAssocArray()` |
| `sample_files/grid.js` | rewritten (1,553 → ~1,390 lines): IIFE, `Grid` + `Column`, event delegation, `Grid.autoInitAll()`, `Grid.get()`, `Grid.debug`, `Grid.searchDebounceMs`, `grid.sort()`, `grid.clearFilters()`, `serverExportUrl()` |
| `sample_files/grid_demo.php` | new demo page (creates `grid_demo`, every feature, `?demo_break=1`, `?js=old`) |
| `sample_files/grid-callback-examples.md`, `.claude/grid-callbacks.md`, `CLAUDE.md` | documentation corrected (`jsCallback`, string = HTML, row callback, new switches) |
| `phpstan.neon`, `rector.php` | exclusion narrowed to `SimpleTable.php` |
| `tests/GridRequestTest.php`, `tests/GridQueryTest.php`, `tests/PhpGridUnitTest.php` | new (Unit) |
| `tests/PhpGridColumnActionButtonTest.php`, `tests/PhpGridTest.php` | extended (JSON contract, round-trip, payload snapshot, exports, operators, reserved words, GROUP BY, error paths) |

Untouched: `SimpleTable.php`, `Grid/PhpGrid/Settings.php`, `PageGenerator.php`, generated pages.

# Compatibility

Every method the applications call keeps its name, arguments and fluent return (`new PhpGrid('name')`, the
setters, `getColumn()->setJsCallback()`, `isReadyToHandleRequests()`, `bootstrap()`, `draw()`, the Column
positional constructor up to nine arguments, `ActionButton(label, href, icon)->addAttribute()`). Verified both
ways in the browser:

- **new PhpGrid + old grid.js** (`grid_demo.php?js=old`, routerarena's copy): loads, sort toggles asc/desc (the
  payload echoes `dir` as sent), the PHP-rendered rows-per-page items keep their `onclick`, pagination, search,
  buttons without icon render (`icon: null` kept), no stray footer row (`columnTotals: []`), the old
  "Excel (PHPSpreadsheet)" entry (`export_grid_to_excel=1`) downloads the OpenSpout workbook. The old script's own
  bugs stay (object callback results show `[object Object]`, the page is not reset on filter).
- **new grid.js + old PhpGrid payload**: no `rowsPerPageOptions` → the PHP menu is left alone; header/search rows
  found by index; missing `searchable`/`sortable` default to true.
- asm's older bundle (no `Grid.autoInit`) works again through the inline script's `initialize()` fallback.

Behaviour changes: PostgreSQL LIKE is case-insensitive (`CAST(col AS TEXT) ILIKE`), `execute()` returns false on
an SQL error, a refused / failed export answers 403 / 500 instead of an empty page or a header-only file, a column
callback returning null renders the default cell, css strings in the column JSON are `''` instead of `null`, a
one-column grid is valid.

# Verification

| Check | Result |
|---|---|
| `php -d error_reporting=E_ALL vendor/bin/phpunit --testsuite Unit` | 712 tests, 2,944 assertions (was 674 / 2,694) |
| Integration, MariaDB | 179 tests, 875 assertions (was 159 / 762) |
| Integration, PostgreSQL | 179 tests, 876 assertions (was 159 / 763) |
| `vendor/bin/phpstan analyse` (PhpGrid folder included) | 0 errors |
| `node --check sample_files/grid.js` | ok |
| `vendor/bin/rector process src/Laf/UI/Grid/PhpGrid --dry-run` | only constructor-promotion / static-method style suggestions (advisory) |
| `http://localhost/laf/sample_files/grid_demo.php` (Playwright, Apache + PHP 8.5.4, MariaDB) | load, sort both ways, `=`/`>` prefixes, case-insensitive `=`, Escape, clear filters, last/prev page, filter resets the page, All / 5 rows per page persisted, callbacks (row highlight, mailto object, HTML badge, disabled Edit, hidden Odd button, `{id}` in the delete `onclick`), column toggle with totals still aligned, export rows (labels, visible + exportable, numeric), deferred grid in a hidden tab via `Grid.autoInitAll()`, broken SQL → 500 JSON + banner + "No records found", console free of errors |
| Same page with `?js=old` | see Compatibility |

The Chrome extension was not connected, so the walkthrough used the Playwright plugin; `var/grid_old.js` is a manual copy of `D:\www\routerarena.com\public\manage\assets\src\grid.js` (gitignored).

# For the applications

After updating their LAF copy, asm and routerarena should copy `sample_files/grid.js` over their bundled copy and
rebuild (`assets/src/custom/js/grid.js` in asm, `public/manage/assets/src/grid.js` in routerarena); nothing in their
PHP needs to change. routerarena (`dev-master`) gets all of this only when `improvements1` is merged.
