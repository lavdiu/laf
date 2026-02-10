# PhpGrid Callback System — Examples

## Overview

Both `Column` and `ActionButton` support a `callback` property — a JS function name
(or dot-notation path) that is evaluated **per row** during grid rendering.
The callback receives row data and can override how the cell/button is rendered.

Both use the same `_resolveCallback()` helper which supports:
- Simple function names: `"formatStatus"`
- Dot-notation paths: `"MyApp.grid.formatStatus"`

---

## Column Callback

### PHP — register the callback

```php
use Laf\UI\Grid\PhpGrid\Column;

$grid->addColumn(
    (new Column('status', 'Status'))
        ->setInnerElementCssClass('badge')    // default CSS for all rows
        ->setCallback('formatStatusCell')
);
```

### JS — callback contract

```javascript
/**
 * @param {*}      cellValue  - This cell's raw value
 * @param {Object} rowData    - All column values for this row  { id: 5, status: "active", ... }
 * @param {Object} columnDef  - The column definition object
 * @returns {null|undefined|true|string|Object}
 *   null / undefined / true  → render normally (column CSS still applies)
 *   string                   → used as display text (column CSS still applies)
 *   object                   → selective overrides (unspecified props keep column defaults):
 *       value                (string)  override display text
 *       innerHTML            (string)  override with raw HTML
 *       href                 (string)  override/add link URL
 *       target               (string)  override link target
 *       innerElementCssStyle (string)  override inner CSS style
 *       innerElementCssClass (string)  override inner CSS class
 *       outerElementCssStyle (string)  override outer CSS style
 *       outerElementCssClass (string)  override outer CSS class
 *       hidden               (bool)   render empty cell
 */
```

### JS — examples

```javascript
// Simple: return a formatted string (column CSS still applies)
function formatCurrency(cellValue, rowData, col) {
    return '$' + parseFloat(cellValue).toFixed(2);
}

// Object: override CSS per row based on value
function formatStatusCell(cellValue, rowData, col) {
    if (cellValue === 'active')
        return {
            innerHTML: '<span class="badge bg-success">Active</span>'
        };
    if (cellValue === 'overdue')
        return {
            value: 'OVERDUE',
            innerElementCssClass: 'badge bg-danger',
            outerElementCssStyle: 'font-weight:bold;'
        };
    if (cellValue === 'draft')
        return { hidden: true };   // render empty <td>
    return true;                    // default rendering
}

// Cross-column: use another column's value to style this cell
function highlightHighValue(cellValue, rowData, col) {
    if (parseFloat(rowData.amount) > 10000) {
        return {
            innerElementCssClass: 'text-danger fw-bold',
            outerElementCssStyle: 'background-color: #fff3cd;'
        };
    }
    return true;
}

// Dot-notation namespace
window.MyApp = window.MyApp || {};
MyApp.columns = {
    formatDate: function(cellValue, rowData, col) {
        if (!cellValue) return '—';
        return new Date(cellValue).toLocaleDateString();
    }
};
// PHP: ->setCallback('MyApp.columns.formatDate')
```

### Backwards Compatibility

- If no `callback` is set, rendering is identical to before.
- If callback returns `true`, `undefined`, or `null`, column defaults apply unchanged.
- Only properties explicitly returned in the object override the column's defaults;
  unspecified properties keep the column-level CSS/style/href values.

---

## ActionButton Callback

### PHP — register the callback

```php
use Laf\UI\Grid\PhpGrid\ActionButton;

$grid->addActionButton(
    (new ActionButton('Approve', '?action=approve&id={id}', 'fa fa-check'))
        ->setCallback('myApproveCallback')
);
```

### JS — callback contract

```javascript
/**
 * @param {Object} rowData   - The row's column values  { id: 5, status: "closed", ... }
 * @param {Object} buttonDef - The original button definition
 * @returns {null|false|true|Object}
 *   null / false             → hide the button for this row (not rendered)
 *   true / undefined         → render normally
 *   object                   → selective overrides (original is never mutated):
 *       label    (string)    override button label text
 *       icon     (string)    override icon class
 *       href     (string)    override href
 *       cssClass (string)    extra CSS classes on the <a>
 *       disabled (bool)      render greyed-out, non-clickable (opacity 0.5, pointer-events none)
 *       hidden   (bool)      same as returning false — hide entirely
 */
```

### JS — examples

```javascript
function myApproveCallback(rowData, btn) {
    if (rowData.status === 'approved')
        return { disabled: true, label: 'Already Approved' };
    if (rowData.status === 'cancelled')
        return false;   // hide entirely
    return true;        // show normally
}

// Conditional icon + label
function editOrViewCallback(rowData, btn) {
    if (rowData.is_locked === '1')
        return { label: 'View', icon: 'fa fa-eye', href: '?action=view&id=' + rowData.id };
    return true; // default Edit button
}

// Add extra CSS class for certain rows
function highlightDeleteButton(rowData, btn) {
    if (rowData.has_dependents === '1')
        return { cssClass: 'text-danger', label: 'Force Delete' };
    return true;
}
```

### Backwards Compatibility

- If no `callback` is set, button rendering is identical to before.
- The original `buttonDef` object is never mutated; overrides are applied to a
  shallow copy via `Object.assign({}, btnDef, result)`.
- Other rows always see the original, unmodified button definition.

---

## Files Involved

| File | What changed |
|------|-------------|
| `src/Laf/UI/Grid/PhpGrid/Column.php` | Added `callback` property + `getCallback()`/`setCallback()` |
| `src/Laf/UI/Grid/PhpGrid/ActionButton.php` | Added `callback` property + `getCallback()`/`setCallback()` |
| `sample_files/grid.js` | Added `_resolveCallback()` helper; modified cell + action button rendering loops |
| `src/Laf/UI/Grid/PhpGrid/PhpGrid.php` | No changes needed — public properties auto-serialize via `json_encode()` |
