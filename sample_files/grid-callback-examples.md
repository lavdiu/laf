# PhpGrid callbacks (grid.js)

`Column`, `ActionButton` and the grid itself can name a JavaScript function that grid.js calls while it
renders a page of rows. The name is a global function or a dot-notation path (`MyApp.grid.formatStatus`),
resolved on `window` by `Grid.prototype._resolveCallback()`. The PHP property is `jsCallback`:

```php
$grid->addColumn((new Column('status', 'Status'))->setJsCallback('formatStatusCell'));
$grid->addActionButton((new ActionButton('Approve', '?action=approve&id={id}', 'fa fa-check'))->setJsCallback('approveButton'));
$grid->setRowLevelJsCallback('highlightRow');
```

The functions must exist before the grid loads its data (a `<script>` in the page head, or any script that
runs before `DOMContentLoaded`).

## Column callback

```javascript
/**
 * @param {*}      cellValue  the cell's raw value
 * @param {Object} row        every column of the row: { id: 5, status: "active", ... }
 * @param {Object} column     the column definition (fieldName, label, format, href, ...)
 * @returns {null|undefined|true|string|Object}
 *   null / undefined / true  default rendering (the column's href and CSS apply)
 *   string                   raw HTML for the cell (the column's CSS still applies)
 *   object                   overrides, unspecified keys keep the column defaults:
 *       value                (string)  display text (escaped)
 *       innerHTML            (string)  raw HTML instead of the text
 *       href                 (string)  link target ({column} and [param] are replaced)
 *       target               (string)  link target attribute
 *       innerElementCssStyle (string)  style of the span / link
 *       innerElementCssClass (string)  class of the span / link
 *       outerElementCssStyle (string)  style of the td
 *       outerElementCssClass (string)  class of the td
 *       hidden               (bool)    render an empty td
 */
function formatStatusCell(cellValue, row, column) {
    if (cellValue === 'active') {
        return '<span class="badge bg-success">Active</span>';
    }
    if (cellValue === 'overdue') {
        return {value: 'OVERDUE', innerElementCssClass: 'badge bg-danger', outerElementCssStyle: 'font-weight:bold;'};
    }
    if (cellValue === 'draft') {
        return {hidden: true};
    }
    return true;
}

// a link built from another column
function emailCell(cellValue, row, column) {
    return cellValue ? {href: 'mailto:' + cellValue, value: cellValue} : {value: '—'};
}

// namespaced: ->setJsCallback('MyApp.columns.formatDate')
window.MyApp = window.MyApp || {};
MyApp.columns = {
    formatDate: function (cellValue, row, column) {
        return cellValue ? new Date(cellValue).toLocaleDateString() : '—';
    }
};
```

A string result is inserted as HTML (that is what the applications rely on); use the object form with
`value` when the text comes from user data.

## Action button callback

```javascript
/**
 * @param {Object} row     the row's values
 * @param {Object} button  the button definition (label, href, icon, cssClass, attributeList)
 * @returns {null|false|true|undefined|Object}
 *   null / false             hide the button for this row
 *   true / undefined         render it as defined
 *   object                   overrides applied to a copy (the definition is never changed):
 *       label    (string)    label (HTML)
 *       icon     (string)    icon class
 *       href     (string)    link ({column} and [param] are replaced)
 *       cssClass (string)    extra classes on the link
 *       disabled (bool)      greyed out, not clickable
 *       hidden   (bool)      same as returning false
 */
function approveButton(row, button) {
    if (row.status === 'approved') {
        return {disabled: true, label: 'Already approved'};
    }
    if (row.status === 'cancelled') {
        return false;
    }
    return true;
}
```

Attributes added with `ActionButton::addAttribute()` are set on the link; `{column}` placeholders in their
values are replaced like in the href, except in event handlers (`on*`), which are set verbatim: row data must
never land inside script. Read the row from the link instead, e.g. `addAttribute('data-name', '{name}')` and
`addAttribute('onclick', 'return confirm("Delete " + this.dataset.name + "?")')`.

## Row callback

```javascript
/**
 * Called with the <tr> before its cells are added.
 * @param {HTMLTableRowElement} tr
 * @param {Object} row
 */
function highlightRow(tr, row) {
    if (parseFloat(row.amount) > 10000) {
        tr.classList.add('table-warning');
    }
}
```

## Other hooks

- `Grid.get('grid_name')` returns the instance; `grid.refresh()` reloads it, `grid.filters = {city: 'Boston'}`
  filters it (values may start with `=`, `!=`, `<`, `>`, `<=`, `>=`), `grid.sort('name', 'desc')`,
  `grid.setRowsPerPage(50)`, `grid.clearFilters()`.
- `Grid.searchDebounceMs = 400` searches while typing; the default `0` searches on Enter.
- `Grid.debug = true` logs every request to the console.
- A grid drawn with `setInlineScript(false)` is registered by `Grid.autoInitAll()` on `DOMContentLoaded`.
