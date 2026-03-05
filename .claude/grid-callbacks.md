# Grid Callback System

## Overview
- **Column** and **ActionButton** both have `callback` property (JS function name, dot-notation supported)
- PHP: `->setCallback('myFn')` — auto-serialized to JSON via public property
- JS: `_resolveCallback()` in `Grid` class resolves string → function via `window` traversal

## Signatures
- Column callback: `fn(cellValue, rowData, columnDef)` → string|object|true|null
- ActionButton callback: `fn(rowData, buttonDef)` → false|true|object|null

## Behavior
- Backwards compatible: no callback = identical behavior; original objects never mutated
- Detailed examples in `.claude/grid-callback-examples.md` (project root)

## Key Files
- `src/Laf/UI/Grid/PhpGrid/Column.php`
- `src/Laf/UI/Grid/PhpGrid/ActionButton.php`
- `sample_files/grid.js`

## Implementation Details
- PhpGrid serializes Column/ActionButton via `json_encode()` on public properties
- `Column::setPropertyValue()` uses `property_exists()` so new properties auto-work with `createFromAssocArray()`
- Grid JS uses `column.href` with `formatLinkHref()` for template substitution (`{id}`, `[module]`)
