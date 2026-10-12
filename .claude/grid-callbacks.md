# Grid Callback System (reviewed 2026-10-12)

## Overview
- **Column**, **ActionButton** and the grid itself (`setRowLevelJsCallback()`) carry a `jsCallback` property: a JS function name, dot-notation supported
- PHP: `->setJsCallback('myFn')` (ActionButton keeps the misspelled `setJsCallbackk()` as a deprecated alias); serialised through `jsonSerialize()`
- JS: `_resolveCallback()` in `Grid` resolves string → function via `window` traversal

## Signatures
- Column callback: `fn(cellValue, row, columnDef)` → string (raw HTML) | object {value, innerHTML, href, target, innerElementCssStyle, innerElementCssClass, outerElementCssStyle, outerElementCssClass, hidden} | true | null (default rendering)
- ActionButton callback: `fn(row, buttonDef)` → false/null (hide) | true/undefined | object {label, icon, href, cssClass, disabled, hidden}; called before the button is rendered, applied to a copy
- Row callback: `fn(tr, row)` before the cells are added

## Behavior
- Backwards compatible: no callback = identical behavior; original objects never mutated
- `{column}` and `[query_param]` placeholders are replaced in hrefs and in button attribute values
- Detailed examples in `sample_files/grid-callback-examples.md`; a live page in `sample_files/grid_demo.php`

## Key Files
- `src/Laf/UI/Grid/PhpGrid/Column.php`, `ActionButton.php` (JSON contract), `PhpGrid.php` (facade), `GridRequest.php`, `GridQuery.php`
- `sample_files/grid.js`
