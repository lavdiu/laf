/*
 * LAF PhpGrid client. Pairs with Laf\UI\Grid\PhpGrid\PhpGrid: draw() renders the table shell and the
 * inline script that creates `new Grid(name)`; bootstrap() answers the requests this file sends
 * (?load_grid_by_name=...&page=&limit=&sort=&dir=&searchParams=[...]).
 *
 * Requirements: Bootstrap 5 (dropdowns) and Font Awesome; SheetJS (window.XLSX) is optional and only
 * used for the client-side Excel export (the server export is the fallback). Plain ES2020, no build step,
 * no jQuery.
 *
 * Public API (kept stable for the applications):
 *   new Grid(name), Grid.autoInit(name), Grid.autoInitAll(), Grid.get(name), Grid.debug, Grid.searchDebounceMs
 *   grid.initialize(), refresh(), fetchJson(), buildUrl(allRows), setRowsPerPage(n), clearFilters(), sort(column, dir)
 *   grid.filters (object field => text, setting it reloads), currentPage, sortColumn, sortDir, rowsPerPage,
 *   grid.downloadExcelJs(), downloadCsvJs(), showError(message), clearError()
 *   grid.columns, rows, data, name, title, showTitleBar, showSearchBar, deferInitialize, rowLevelJsCallback
 *   window.grid[name] is the registry every grid registers itself in.
 *
 * Callbacks (names resolved on window, dot-notation allowed):
 *   column:  fn(cellValue, row, columnDef) -> null/undefined/true = default rendering, string = HTML for the cell,
 *            object = {value, innerHTML, href, target, innerElementCssStyle, innerElementCssClass,
 *            outerElementCssStyle, outerElementCssClass, hidden}
 *   button:  fn(row, buttonDef) -> null/false = hide, true/undefined = default, object = {label, icon, href,
 *            cssClass, disabled, hidden}
 *   row:     fn(tr, row) called before the cells are drawn
 */
(function (window, document) {
    'use strict';

    window.grid = window.grid || {};

    const FILTER_PREFIXES = [
        ['<=', 'lteq'], ['>=', 'gteq'], ['!=', 'noteq'], ['<', 'lt'], ['>', 'gt'], ['=', 'eq'],
    ];
    const SEARCH_HINT = 'Type and press Enter. Prefixes: = exact, != not, <, >, <=, >=';

    function registry() {
        if (!window.grid || typeof window.grid !== 'object') {
            window.grid = {};
        }
        return window.grid;
    }

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined && text !== null) {
            node.textContent = text;
        }
        return node;
    }

    function icon(classes) {
        const i = document.createElement('i');
        i.className = classes;
        i.setAttribute('aria-hidden', 'true');
        return i;
    }

    function parseAttributes(value) {
        if (!value) {
            return null;
        }
        if (typeof value === 'object') {
            return value;
        }
        try {
            const parsed = JSON.parse(value);
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    function applyAttributes(node, attributes) {
        if (!attributes) {
            return;
        }
        for (const name of Object.keys(attributes)) {
            if (/^[a-zA-Z_:][-a-zA-Z0-9_:.]*$/.test(name)) {
                node.setAttribute(name, String(attributes[name]));
            }
        }
    }

    function truthy(value) {
        return value === true || value === 1 || value === '1' || value === 'true';
    }

    class Column {
        constructor(definition) {
            const d = definition || {};
            this.data = d;
            this.index = d.index || 0;
            this.fieldName = d.fieldName || '';
            this.label = d.label == null ? this.fieldName : String(d.label);
            this.format = d.format || '';
            this.href = d.href || null;
            this.target = d.target || null;
            this.innerElementCssStyle = d.innerElementCssStyle || '';
            this.innerElementCssClass = d.innerElementCssClass || '';
            this.outerElementCssStyle = d.outerElementCssStyle || '';
            this.outerElementCssClass = d.outerElementCssClass || '';
            this.visible = d.visible === undefined ? true : truthy(d.visible);
            this.exportable = d.exportable === undefined ? true : truthy(d.exportable);
            this.searchable = d.searchable === undefined ? true : truthy(d.searchable);
            this.sortable = d.sortable === undefined ? true : truthy(d.sortable);
            this.innerElementAttributes = parseAttributes(d.innerElementAttributes);
            this.outerElementAttributes = parseAttributes(d.outerElementAttributes);
            this.jsCallback = d.jsCallback || null;
        }

        get isNumeric() {
            return this.format === 'integer' || this.format === 'float';
        }
    }

    class Grid {
        constructor(name) {
            if (typeof name !== 'string' || name.length < 1) {
                throw new Error('Grid: a grid name is required');
            }
            this.id = null;
            this._name = name;
            this._currentPage = 1;
            this.title = '';
            this.pageCount = 1;
            this.rowCount = 0;
            this._rowsPerPage = 10;
            this.rowsPerPageOptions = null;
            this.columns = {};
            this.columnCount = 0;
            this.columnCountVisible = 0;
            this.data = null;
            this.actionButtons = [];
            this.allowExport = true;
            this.rows = [];
            this.message = null;
            this.query = null;
            this.queryCount = null;
            this.url = null;
            this._filters = {};
            this._sortColumn = null;
            this._sortDir = null;
            this._abortController = null;
            this._debounceTimer = null;
            this._errorBannerEl = null;
            this.el = null;
            this.showTitleBar = true;
            this.showSearchBar = true;
            this.rowLevelJsCallback = null;
            this.deferInitialize = false;
            this._hiddenColumns = new Set();
            this._ownsRowsPerPageMenu = false;
            this._autoInitDone = false;
            this._refetchAfterDraw = false;
        }

        // ─── Registry and start-up ──────────────────────────────────────

        static get(name) {
            return registry()[name] || null;
        }

        static log() {
            if (Grid.debug && window.console) {
                console.log.apply(console, ['[grid]'].concat(Array.from(arguments)));
            }
        }

        /**
         * Start a registered grid: at once, or when it scrolls into view when deferInitialize is set
         */
        static autoInit(gridName) {
            const grid = Grid.get(gridName);
            if (!grid || grid._autoInitDone) {
                return;
            }
            grid._autoInitDone = true;

            const start = function () {
                if (!grid.deferInitialize || typeof IntersectionObserver !== 'function') {
                    grid.initialize();
                    return;
                }
                const container = document.getElementById(gridName + '_container');
                if (!container) {
                    grid.initialize();
                    return;
                }
                const observer = new IntersectionObserver(function (entries) {
                    for (const entry of entries) {
                        if (entry.isIntersecting) {
                            observer.disconnect();
                            grid.initialize();
                        }
                    }
                });
                observer.observe(container);
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', start);
            } else {
                start();
            }
        }

        /**
         * Register and start every table PhpGrid::draw() rendered that has no instance yet (pages drawn
         * with setInlineScript(false), or grid.js loaded after the inline scripts)
         */
        static autoInitAll() {
            const tables = document.querySelectorAll('table[data-component-type="Grid"]');
            for (const table of tables) {
                const name = table.id;
                if (!name || Grid.get(name)) {
                    continue;
                }
                const grid = new Grid(name);
                registry()[name] = grid;
                grid._readContainerConfig();
                Grid.autoInit(name);
            }
        }

        // ─── DOM ────────────────────────────────────────────────────────

        _bindDom() {
            const byId = (suffix) => document.getElementById(this.name + suffix);
            const table = byId('');
            if (!table) {
                throw new Error('Grid: table #' + this.name + ' not found');
            }
            const thead = byId('_thead');
            const headRows = thead ? thead.querySelectorAll('tr') : [];
            this.el = {
                container: byId('_container') || table.parentElement,
                table: table,
                thead: thead,
                tbody: byId('_tbody'),
                tfoot: byId('_tfoot'),
                titleRow: byId('_title_row') || headRows[0] || null,
                title: byId('_title'),
                buttons: byId('_buttons'),
                headerRow: byId('_header_row') || headRows[1] || null,
                searchRow: byId('_search_row') || headRows[2] || null,
                loader: byId('_loader'),
                first: byId('_paginationFirstPage'),
                prev: byId('_paginationPrevPage'),
                curr: byId('_paginationCurrPage'),
                next: byId('_paginationNextPage'),
                last: byId('_paginationLastPage'),
                info: byId('_paginationInfoSection'),
                rowsPerPageButton: byId('_rowsPerPageSelector'),
                rowsPerPageMenu: byId('_rowsPerPageMenu'),
            };
            if (!this.el.rowsPerPageMenu && this.el.rowsPerPageButton) {
                const menu = this.el.rowsPerPageButton.parentElement ? this.el.rowsPerPageButton.parentElement.querySelector('.dropdown-menu') : null;
                this.el.rowsPerPageMenu = menu;
            }
        }

        _readContainerConfig() {
            const container = document.getElementById(this.name + '_container');
            if (!container) {
                return;
            }
            const ds = container.dataset;
            if (ds.rowsPerPage !== undefined) {
                this._rowsPerPage = parseInt(ds.rowsPerPage, 10) || 0;
            }
            if (ds.showTitleBar !== undefined) {
                this.showTitleBar = ds.showTitleBar === 'true';
            }
            if (ds.showSearchBar !== undefined) {
                this.showSearchBar = ds.showSearchBar === 'true';
            }
            if (ds.deferInitialize !== undefined) {
                this.deferInitialize = ds.deferInitialize === 'true';
            }
            if (ds.rowsPerPageOptions !== undefined) {
                try {
                    const options = JSON.parse(ds.rowsPerPageOptions);
                    if (Array.isArray(options)) {
                        this.rowsPerPageOptions = options.map((n) => parseInt(n, 10)).filter((n) => !isNaN(n) && n >= 0);
                    }
                } catch (e) { /* ignore */ }
            }
        }

        _storageKey(suffix) {
            return 'laf.grid.' + this.name + '.' + suffix;
        }

        _loadPreferences() {
            try {
                let saved = localStorage.getItem(this._storageKey('rowsPerPage'));
                if (saved === null) {
                    const legacy = localStorage.getItem('laf.grid.rowsPerPage');
                    if (legacy !== null) {
                        saved = legacy;
                        localStorage.setItem(this._storageKey('rowsPerPage'), legacy);
                    }
                }
                if (saved !== null && !isNaN(parseInt(saved, 10))) {
                    this._rowsPerPage = parseInt(saved, 10);
                }
                const hidden = localStorage.getItem(this._storageKey('hiddenColumns'));
                if (hidden) {
                    this._hiddenColumns = new Set(JSON.parse(hidden));
                }
            } catch (e) { /* storage unavailable */ }
        }

        _saveHiddenColumns() {
            try {
                if (this._hiddenColumns.size > 0) {
                    localStorage.setItem(this._storageKey('hiddenColumns'), JSON.stringify(Array.from(this._hiddenColumns)));
                } else {
                    localStorage.removeItem(this._storageKey('hiddenColumns'));
                }
            } catch (e) { /* storage unavailable */ }
        }

        _bindControls() {
            const self = this;
            const e = this.el;
            const go = (page) => () => { self.currentPage = page(); };
            if (e.first) e.first.onclick = go(() => 1);
            if (e.prev) e.prev.onclick = go(() => self.currentPage - 1);
            if (e.next) e.next.onclick = go(() => self.currentPage + 1);
            if (e.last) e.last.onclick = go(() => self.pageCount);

            // the rows-per-page menu is ours when PhpGrid sent the options; an older PhpGrid rendered its own
            if (this.rowsPerPageOptions && e.rowsPerPageMenu) {
                this._ownsRowsPerPageMenu = true;
                this._drawRowsPerPageMenu();
            }

            e.container.addEventListener('click', function (event) {
                const target = event.target instanceof Element ? event.target : null;
                if (!target) {
                    return;
                }
                const sortButton = target.closest('[data-grid-sort]');
                if (sortButton) {
                    event.preventDefault();
                    self._toggleSort(sortButton.getAttribute('data-grid-sort'));
                    return;
                }
                const exportLink = target.closest('[data-grid-export]');
                if (exportLink) {
                    event.preventDefault();
                    self._export(exportLink.getAttribute('data-grid-export'));
                    return;
                }
                const action = target.closest('[data-grid-action]');
                if (action) {
                    event.preventDefault();
                    const name = action.getAttribute('data-grid-action');
                    if (name === 'clear-filters') {
                        self.clearFilters();
                    } else if (name === 'reset-columns') {
                        self._hiddenColumns.clear();
                        self._saveHiddenColumns();
                        self.initColumns();
                        self.draw();
                    }
                    return;
                }
                if (self._ownsRowsPerPageMenu) {
                    const option = target.closest('[data-rows-per-page]');
                    if (option && e.rowsPerPageMenu && e.rowsPerPageMenu.contains(option)) {
                        event.preventDefault();
                        self.setRowsPerPage(parseInt(option.getAttribute('data-rows-per-page'), 10) || 0);
                    }
                }
            });

            e.container.addEventListener('keydown', function (event) {
                const input = event.target instanceof Element ? event.target.closest('input[data-grid-search]') : null;
                if (!input) {
                    return;
                }
                if (event.key === 'Enter') {
                    event.preventDefault();
                    self._applySearchInputs();
                } else if (event.key === 'Escape' && input.value !== '') {
                    event.preventDefault();
                    input.value = '';
                    self._applySearchInputs();
                }
            });

            e.container.addEventListener('input', function (event) {
                const input = event.target instanceof Element ? event.target.closest('input[data-grid-search]') : null;
                if (!input || !(Grid.searchDebounceMs > 0)) {
                    return;
                }
                clearTimeout(self._debounceTimer);
                self._debounceTimer = setTimeout(() => self._applySearchInputs(), Grid.searchDebounceMs);
            });

            e.container.addEventListener('change', function (event) {
                const checkbox = event.target instanceof Element ? event.target.closest('input[data-grid-column-toggle]') : null;
                if (!checkbox) {
                    return;
                }
                const field = checkbox.getAttribute('data-grid-column-toggle');
                if (!self.columns[field]) {
                    return;
                }
                if (checkbox.checked) {
                    self._hiddenColumns.delete(field);
                } else {
                    self._hiddenColumns.add(field);
                }
                self.columns[field].visible = checkbox.checked;
                self._saveHiddenColumns();
                self._recountVisibleColumns();
                self.draw();
            });
        }

        _drawRowsPerPageMenu() {
            const menu = this.el.rowsPerPageMenu;
            if (!menu) {
                return;
            }
            menu.innerHTML = '';
            for (const option of this.rowsPerPageOptions) {
                const li = el('li');
                const link = el('a', 'dropdown-item', option === 0 ? 'All' : String(option));
                link.href = '#';
                link.setAttribute('data-rows-per-page', String(option));
                if (option === this.rowsPerPage) {
                    link.classList.add('active');
                }
                li.appendChild(link);
                menu.appendChild(li);
            }
        }

        _updateRowsPerPageButton() {
            if (this.el && this.el.rowsPerPageButton) {
                this.el.rowsPerPageButton.innerHTML = '';
                this.el.rowsPerPageButton.appendChild(icon('fa fa-bars'));
                this.el.rowsPerPageButton.appendChild(document.createTextNode(' ' + (this.rowsPerPage === 0 ? 'All' : this.rowsPerPage)));
            }
            if (this._ownsRowsPerPageMenu) {
                this._drawRowsPerPageMenu();
            }
        }

        // ─── Loading ────────────────────────────────────────────────────

        /**
         * The URL of the grid's own request: the page URL plus the grid parameters
         */
        buildUrl(allRows = false) {
            const url = new URL(window.location.href);
            const p = url.searchParams;
            p.set('load_grid_by_name', this.name);
            p.set('page', String(allRows ? 1 : this.currentPage));
            p.set('limit', String(allRows ? 0 : this.rowsPerPage));
            if (this.sortColumn) {
                p.set('sort', this.sortColumn);
                p.set('dir', this.sortDir || 'asc');
            } else {
                p.delete('sort');
                p.delete('dir');
            }
            const filters = this.getFilterParams();
            if (filters) {
                p.set('searchParams', filters);
            } else {
                p.delete('searchParams');
            }
            p.delete('export_grid_to_excel');
            p.delete('export_grid_to_csv');
            url.hash = '';
            return url.toString();
        }

        /**
         * The searchParams value: a JSON list of {property, operator, value}, '' when there is no filter.
         * A value may start with <=, >=, !=, <, > or = to choose the operator; everything else is a LIKE.
         */
        getFilterParams() {
            const list = [];
            for (const field of Object.keys(this.filters)) {
                let value = this.filters[field];
                if (value === null || value === undefined) {
                    continue;
                }
                value = String(value);
                let operator = 'like';
                for (const [prefix, op] of FILTER_PREFIXES) {
                    if (value.startsWith(prefix)) {
                        value = value.slice(prefix.length);
                        operator = op;
                        break;
                    }
                }
                list.push({property: field, operator: operator, value: value});
            }
            return list.length ? JSON.stringify(list) : '';
        }

        async fetchJson() {
            if (!this.el) {
                this.initialize();
                return;
            }
            this.url = this.buildUrl();
            this.showLoadingIcon();
            this.clearError();
            Grid.log('loading', this.url);

            if (this._abortController) {
                try { this._abortController.abort(); } catch (e) { /* ignore */ }
            }
            this._abortController = new AbortController();
            const signal = this._abortController.signal;

            try {
                const response = await fetch(this.url, {
                    signal: signal,
                    headers: {'Accept': 'application/json'},
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                let payload = null;
                const text = await response.text();
                try {
                    payload = JSON.parse(text);
                } catch (e) {
                    payload = null;
                }
                if (!response.ok && !(payload && typeof payload === 'object' && 'success' in payload)) {
                    throw new Error('HTTP ' + response.status + (text ? ': ' + text.slice(0, 300) : ''));
                }
                if (!payload || typeof payload !== 'object') {
                    throw new Error('The grid request did not return JSON');
                }
                this.data = payload;
                this.initialize();
                if (payload.success === false) {
                    this.showError(payload.message || 'An error occurred while loading the grid data.');
                }
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
                if (window.console) {
                    console.error('Grid ' + this.name + ': loading failed.', error);
                }
                this.showError('Failed loading data for the table. Please try again.');
            } finally {
                this.hideLoadingIcon();
                this._updateRowsPerPageButton();
            }
        }

        refresh() {
            if (!this.el) {
                this.initialize();
                return;
            }
            this.fetchJson();
        }

        initialize() {
            if (!this.el) {
                this._bindDom();
                this._readContainerConfig();
                this._loadPreferences();
                this._bindControls();
            }
            if (this.data == null) {
                this.fetchJson();
                return;
            }
            this._applyData(this.data);
            this.draw();
            if (this._refetchAfterDraw) {
                this._refetchAfterDraw = false;
                this.fetchJson();
            }
        }

        _applyData(data) {
            this.id = data.id;
            this.title = data.title == null ? '' : String(data.title);
            this.rows = Array.isArray(data.rows) ? data.rows : [];
            this.columns = data.columns || {};
            this.columnCount = data.columnCount || 0;
            this.pageCount = Math.max(1, parseInt(data.pageCount, 10) || 1);
            this.rowCount = parseInt(data.rowCount, 10) || 0;
            if (data.rowsPerPage !== undefined && data.rowsPerPage !== null) {
                this._rowsPerPage = parseInt(data.rowsPerPage, 10) || 0;
            }
            if (Array.isArray(data.rowsPerPageOptions) && data.rowsPerPageOptions.length) {
                this.rowsPerPageOptions = data.rowsPerPageOptions;
            }
            this.message = data.message;
            this.query = data.query;
            this.queryCount = data.queryCount;
            this.actionButtons = Array.isArray(data.actionButtons) ? data.actionButtons : [];
            this.allowExport = data.allowExport !== false;
            this.rowLevelJsCallback = data.rowLevelJsCallback || null;

            // the server confirms the sort it applied; keep it without triggering a reload
            if (data.sort !== undefined && data.sort !== null) {
                this._sortColumn = data.sort;
            }
            if (data.dir !== undefined && data.dir !== null) {
                this._sortDir = String(data.dir).toLowerCase();
            }
            if (this._currentPage > this.pageCount) {
                this._currentPage = this.pageCount;
                this._refetchAfterDraw = this.rows.length === 0 && this.rowCount > 0;
            }
            this.initColumns();
        }

        initColumns() {
            const definitions = this.columns;
            const list = Array.isArray(definitions) ? definitions : Object.values(definitions || {});
            this.columns = {};
            this.columnCountVisible = 0;
            for (const definition of list) {
                const column = definition instanceof Column ? new Column(definition.data) : new Column(definition);
                if (!column.fieldName) {
                    continue;
                }
                if (this._hiddenColumns.has(column.fieldName)) {
                    column.visible = false;
                }
                this.columns[column.fieldName] = column;
                if (column.visible) {
                    this.columnCountVisible++;
                }
            }
        }

        _recountVisibleColumns() {
            this.columnCountVisible = this.visibleColumns().length;
        }

        visibleColumns() {
            return Object.values(this.columns).filter((column) => column.visible);
        }

        get hasActions() {
            return this.actionButtons.length > 0;
        }

        // ─── Drawing ────────────────────────────────────────────────────

        draw() {
            this.drawTableHeader();
            this.drawTableBody();
            this.drawTableFooter();
            this._drawPagination();
            this.hideLoadingIcon();
        }

        drawTableHeader() {
            const e = this.el;
            if (e.thead) {
                e.thead.style.position = 'sticky';
                e.thead.style.top = '0';
                e.thead.style.zIndex = '2';
            }
            if (!e.headerRow || !e.searchRow) {
                return;
            }
            e.headerRow.innerHTML = '';
            e.searchRow.innerHTML = '';

            for (const column of this.visibleColumns()) {
                const th = el('th');
                th.setAttribute('data-grid-column', column.fieldName);
                if (column.isNumeric) {
                    th.classList.add('text-end');
                }
                const label = el('span', 'laf-grid-label', column.label);
                th.appendChild(label);

                if (column.sortable) {
                    const sorted = this.sortColumn === column.fieldName;
                    const button = el('button', 'btn btn-sm btn-link p-0 ms-1 d-print-none laf-grid-sort');
                    button.type = 'button';
                    button.setAttribute('data-grid-sort', column.fieldName);
                    button.setAttribute('aria-label', 'Sort by ' + column.label);
                    button.title = 'Sort by ' + column.label;
                    button.appendChild(icon('fas ' + (sorted ? (this.sortDir === 'desc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort')));
                    th.appendChild(button);
                    th.setAttribute('aria-sort', sorted ? (this.sortDir === 'desc' ? 'descending' : 'ascending') : 'none');
                }
                e.headerRow.appendChild(th);

                const searchTh = el('th');
                if (column.searchable) {
                    const input = el('input', 'form-control form-control-sm');
                    input.type = 'search';
                    input.placeholder = 'Search';
                    input.title = SEARCH_HINT;
                    input.setAttribute('aria-label', 'Search ' + column.label);
                    input.setAttribute('data-grid-search', column.fieldName);
                    input.autocomplete = 'off';
                    if (this.filters[column.fieldName] !== undefined) {
                        input.value = this.filters[column.fieldName];
                    }
                    searchTh.appendChild(input);
                }
                e.searchRow.appendChild(searchTh);
            }

            if (this.hasActions) {
                const actionsTh = el('th', 'laf-grid-actions');
                actionsTh.style.width = '60px';
                e.headerRow.appendChild(actionsTh);
                e.searchRow.appendChild(el('th'));
            }

            if (e.title) {
                e.title.colSpan = 1;
                if (this.columnCountVisible > 3) {
                    e.title.colSpan = 2;
                }
                if (this.columnCountVisible > 5) {
                    e.title.colSpan = 4;
                }
                e.title.textContent = this.title;
            }
            if (e.titleRow) {
                e.titleRow.style.display = this.showTitleBar ? '' : 'none';
            }
            e.searchRow.style.display = this.showSearchBar ? '' : 'none';

            this.drawGridButtons();
        }

        drawGridButtons() {
            const e = this.el;
            if (!e.buttons) {
                return;
            }
            const totalColumns = this.columnCountVisible + (this.hasActions ? 1 : 0);
            e.buttons.colSpan = Math.max(1, totalColumns - (e.title ? e.title.colSpan : 1));
            e.buttons.innerHTML = '';

            if (this.allowExport) {
                const group = el('div', 'btn-group d-print-none');
                const main = el('button', 'btn btn-sm btn-outline-secondary');
                main.type = 'button';
                main.title = 'Export to Excel';
                main.setAttribute('data-grid-export', 'xlsx');
                main.appendChild(icon('fas fa-download'));
                group.appendChild(main);

                const toggle = el('button', 'btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split');
                toggle.type = 'button';
                toggle.setAttribute('data-bs-toggle', 'dropdown');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.appendChild(el('span', 'visually-hidden', 'Export options'));
                group.appendChild(toggle);

                const menu = el('ul', 'dropdown-menu dropdown-menu-end');
                for (const entry of [
                    ['xlsx', 'Excel'], ['csv', 'CSV'], ['xlsx-server', 'Excel (server)'], ['csv-server', 'CSV (server)'],
                ]) {
                    const li = el('li');
                    const link = el('button', 'dropdown-item');
                    link.type = 'button';
                    link.setAttribute('data-grid-export', entry[0]);
                    link.appendChild(icon('fas fa-download'));
                    link.appendChild(document.createTextNode(' ' + entry[1]));
                    li.appendChild(link);
                    menu.appendChild(li);
                }
                group.appendChild(menu);
                e.buttons.appendChild(group);
            }

            this.drawColumnVisibilityToggle();
        }

        drawColumnVisibilityToggle() {
            const group = el('div', 'btn-group ms-1 d-print-none');
            const toggle = el('button', 'btn btn-sm btn-outline-secondary dropdown-toggle');
            toggle.type = 'button';
            toggle.setAttribute('data-bs-toggle', 'dropdown');
            toggle.setAttribute('data-bs-auto-close', 'outside');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.title = 'Show/hide columns';
            toggle.appendChild(icon('fas fa-columns'));
            toggle.appendChild(el('span', 'visually-hidden', 'Show/hide columns'));

            const menu = el('div', 'dropdown-menu dropdown-menu-end');
            menu.style.maxHeight = '300px';
            menu.style.overflowY = 'auto';
            menu.style.minWidth = '200px';

            for (const column of Object.values(this.columns)) {
                const item = el('label', 'dropdown-item d-flex align-items-center gap-2');
                item.style.cursor = 'pointer';
                const checkbox = el('input', 'form-check-input mt-0');
                checkbox.type = 'checkbox';
                checkbox.checked = column.visible;
                checkbox.setAttribute('data-grid-column-toggle', column.fieldName);
                item.appendChild(checkbox);
                item.appendChild(el('span', null, column.label));
                menu.appendChild(item);
            }
            menu.appendChild(el('div', 'dropdown-divider'));
            const reset = el('button', 'dropdown-item text-center', 'Reset all');
            reset.type = 'button';
            reset.setAttribute('data-grid-action', 'reset-columns');
            menu.appendChild(reset);

            group.appendChild(toggle);
            group.appendChild(menu);
            this.el.buttons.appendChild(group);
        }

        drawTableBody() {
            const tbody = this.el.tbody;
            if (!tbody) {
                return;
            }
            tbody.innerHTML = '';
            const fragment = document.createDocumentFragment();
            const columns = this.visibleColumns();
            const rowCallback = this.rowLevelJsCallback ? this._resolveCallback(this.rowLevelJsCallback) : null;

            if (this.rows.length === 0) {
                const tr = el('tr', 'laf-grid-empty');
                const td = el('td', 'text-center text-muted py-3', 'No records found');
                td.colSpan = Math.max(1, columns.length + (this.hasActions ? 1 : 0));
                tr.appendChild(td);
                fragment.appendChild(tr);
            }

            this.rows.forEach((row, rowIndex) => {
                const tr = el('tr');
                tr.id = this.name + '_row_' + rowIndex;
                if (rowCallback) {
                    try {
                        rowCallback(tr, row);
                    } catch (error) {
                        if (window.console) console.error('Grid ' + this.name + ': row callback failed', error);
                    }
                }
                for (const column of columns) {
                    const td = el('td');
                    this._renderCell(td, column, row[column.fieldName], row);
                    tr.appendChild(td);
                }
                if (this.hasActions) {
                    const td = el('td', 'text-center');
                    this._renderActions(td, row);
                    tr.appendChild(td);
                }
                fragment.appendChild(tr);
            });
            tbody.appendChild(fragment);
        }

        _renderCell(td, column, value, row) {
            const def = {
                value: value,
                innerHTML: null,
                href: column.href,
                target: column.target,
                innerElementCssStyle: column.innerElementCssStyle,
                innerElementCssClass: column.innerElementCssClass,
                outerElementCssStyle: column.outerElementCssStyle,
                outerElementCssClass: column.outerElementCssClass,
                hidden: false,
            };
            if (column.jsCallback) {
                const callback = this._resolveCallback(column.jsCallback);
                if (callback) {
                    let result;
                    try {
                        result = callback(value, row, column);
                    } catch (error) {
                        if (window.console) console.error('Grid ' + this.name + ': column callback failed', error);
                    }
                    if (typeof result === 'string') {
                        def.innerHTML = result;
                    } else if (result && typeof result === 'object') {
                        for (const key of Object.keys(def)) {
                            if (result[key] !== undefined) {
                                def[key] = result[key];
                            }
                        }
                    }
                }
            }

            if (def.outerElementCssStyle) td.style.cssText = def.outerElementCssStyle;
            if (def.outerElementCssClass) td.className = def.outerElementCssClass;
            if (column.isNumeric) td.classList.add('text-end');
            applyAttributes(td, column.outerElementAttributes);
            if (def.hidden) {
                return;
            }

            let inner;
            if (def.href) {
                inner = el('a');
                inner.href = this.formatLinkHref(row, def.href);
                if (def.target) {
                    inner.target = def.target;
                }
            } else {
                inner = el('span');
            }
            if (def.innerHTML !== null && def.innerHTML !== undefined) {
                inner.innerHTML = def.innerHTML;
            } else {
                inner.textContent = def.value === null || def.value === undefined ? '' : String(def.value);
            }
            if (def.innerElementCssStyle) inner.style.cssText = def.innerElementCssStyle;
            if (def.innerElementCssClass) inner.className = def.innerElementCssClass;
            applyAttributes(inner, column.innerElementAttributes);
            td.appendChild(inner);
        }

        _renderActions(td, row) {
            const group = el('div', 'btn-group');
            const toggle = el('button', 'btn btn-outline-secondary btn-sm dropdown-toggle');
            toggle.type = 'button';
            toggle.setAttribute('data-bs-toggle', 'dropdown');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Actions');
            toggle.appendChild(icon('fa fa-cog'));
            group.appendChild(toggle);

            const menu = el('div', 'dropdown-menu dropdown-menu-end');
            let count = 0;
            for (const definition of this.actionButtons) {
                let button = definition;
                if (definition && definition.jsCallback) {
                    const callback = this._resolveCallback(definition.jsCallback);
                    if (callback) {
                        let result;
                        try {
                            result = callback(row, definition);
                        } catch (error) {
                            if (window.console) console.error('Grid ' + this.name + ': button callback failed', error);
                            result = true;
                        }
                        if (result === false || result === null) {
                            continue;
                        }
                        if (result && typeof result === 'object') {
                            if (result.hidden === true) {
                                continue;
                            }
                            button = Object.assign({}, definition, result);
                        }
                    }
                }
                if (!button || button.href === undefined || button.label === undefined) {
                    continue;
                }
                const item = el('a', 'dropdown-item' + (button.cssClass ? ' ' + button.cssClass : ''));
                item.href = this.formatLinkHref(row, String(button.href)) || '#';
                if (button.icon) {
                    item.appendChild(icon(button.icon));
                    item.appendChild(document.createTextNode(' '));
                }
                const label = el('span');
                label.innerHTML = String(button.label);
                item.appendChild(label);
                if (button.attributeList && typeof button.attributeList === 'object') {
                    const attributes = {};
                    for (const name of Object.keys(button.attributeList)) {
                        attributes[name] = this.formatLinkHref(row, String(button.attributeList[name]));
                    }
                    applyAttributes(item, attributes);
                }
                if (button.disabled) {
                    item.classList.add('disabled');
                    item.setAttribute('aria-disabled', 'true');
                    item.tabIndex = -1;
                    item.style.opacity = '0.5';
                    item.style.pointerEvents = 'none';
                }
                menu.appendChild(item);
                count++;
            }
            if (count === 0) {
                return;
            }
            group.appendChild(menu);
            td.appendChild(group);
            td.style.width = '60px';
        }

        drawTableFooter() {
            const tfoot = this.el.tfoot;
            if (!tfoot) {
                return;
            }
            tfoot.innerHTML = '';
            const totals = this.data ? this.data.columnTotals : null;
            if (!totals || typeof totals !== 'object' || Array.isArray(totals) || Object.keys(totals).length === 0) {
                return;
            }
            const formatter = new Intl.NumberFormat(document.documentElement.lang || navigator.language || 'en-US', {
                style: 'decimal', minimumFractionDigits: 2, maximumFractionDigits: 2,
            });
            const tr = el('tr', 'laf-grid-totals');
            for (const column of this.visibleColumns()) {
                const th = el('th', 'text-end');
                if (Object.prototype.hasOwnProperty.call(totals, column.fieldName) && totals[column.fieldName] !== null) {
                    const number = Number(totals[column.fieldName]);
                    th.textContent = isNaN(number) ? String(totals[column.fieldName]) : formatter.format(number);
                }
                tr.appendChild(th);
            }
            if (this.hasActions) {
                tr.appendChild(el('th'));
            }
            tfoot.appendChild(tr);
        }

        _drawPagination() {
            const e = this.el;
            const setDisabled = (button, disabled) => {
                if (!button) return;
                const li = button.parentElement;
                if (li) li.classList.toggle('disabled', disabled);
                if (button.tagName === 'BUTTON') button.disabled = disabled;
                button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
            };
            setDisabled(e.first, this.currentPage <= 1);
            setDisabled(e.prev, this.currentPage <= 1);
            setDisabled(e.next, this.currentPage >= this.pageCount);
            setDisabled(e.last, this.currentPage >= this.pageCount);
            if (e.curr) {
                e.curr.textContent = String(this.currentPage);
            }
            if (e.info) {
                e.info.innerHTML = '';
                let text;
                if (this.rowCount === 0) {
                    text = 'No rows';
                } else if (this.rowsPerPage === 0) {
                    text = this.rowCount.toLocaleString() + ' rows';
                } else {
                    const from = (this.currentPage - 1) * this.rowsPerPage + 1;
                    const to = Math.min(this.rowCount, this.currentPage * this.rowsPerPage);
                    text = from.toLocaleString() + '–' + to.toLocaleString() + ' of ' + this.rowCount.toLocaleString()
                        + ' · Page ' + this.currentPage.toLocaleString() + ' of ' + this.pageCount.toLocaleString();
                }
                e.info.appendChild(document.createTextNode(text));
                if (Object.keys(this.filters).length > 0) {
                    const clear = el('button', 'btn btn-sm btn-link py-0 ms-2', 'Clear filters');
                    clear.type = 'button';
                    clear.setAttribute('data-grid-action', 'clear-filters');
                    e.info.appendChild(clear);
                }
            }
        }

        // ─── Interaction ────────────────────────────────────────────────

        _applySearchInputs() {
            const filters = {};
            const inputs = this.el.container.querySelectorAll('input[data-grid-search]');
            for (const input of inputs) {
                const value = (input.value || '').trim();
                if (value.length > 0) {
                    filters[input.getAttribute('data-grid-search')] = value;
                }
            }
            this.filters = filters;
        }

        clearFilters() {
            if (this.el) {
                for (const input of this.el.container.querySelectorAll('input[data-grid-search]')) {
                    input.value = '';
                }
            }
            this.filters = {};
        }

        _toggleSort(field) {
            if (!field || !this.columns[field] || !this.columns[field].sortable) {
                return;
            }
            if (this.sortColumn === field) {
                this.sort(field, this.sortDir === 'asc' ? 'desc' : 'asc');
            } else {
                this.sort(field, 'asc');
            }
        }

        /**
         * Sort by a column and reload (one request)
         */
        sort(column, dir = 'asc') {
            this._sortColumn = column;
            this._sortDir = dir === 'desc' ? 'desc' : 'asc';
            this._currentPage = 1;
            this.refresh();
        }

        setRowsPerPage(numberOfRows) {
            const n = parseInt(numberOfRows, 10);
            this.rowsPerPage = isNaN(n) || n < 0 ? 10 : n;
            try {
                localStorage.setItem(this._storageKey('rowsPerPage'), String(this.rowsPerPage));
            } catch (e) { /* storage unavailable */ }
            this._currentPage = 1;
            this.refresh();
        }

        /**
         * Replace {column} with the row's value and [param] with the page's query parameter
         */
        formatLinkHref(data, href) {
            if (!href) {
                return null;
            }
            let result = String(href);
            if (result.includes('{') && data) {
                for (const column of Object.keys(data)) {
                    const value = data[column];
                    result = result.split('{' + column + '}').join(value === null || value === undefined ? '' : String(value));
                }
            }
            if (result.includes('[')) {
                const params = new URLSearchParams(window.location.search);
                for (const [key, value] of params) {
                    result = result.split('[' + key + ']').join(value);
                }
            }
            return result;
        }

        _resolveCallback(callback) {
            if (typeof callback === 'function') {
                return callback;
            }
            if (typeof callback !== 'string' || !callback) {
                return null;
            }
            let fn = window;
            for (const part of callback.split('.')) {
                fn = fn ? fn[part] : undefined;
                if (!fn) {
                    return null;
                }
            }
            return typeof fn === 'function' ? fn : null;
        }

        // ─── Exports ────────────────────────────────────────────────────

        serverExportUrl(kind) {
            return this.buildUrl(true) + (kind === 'csv' ? '&export_grid_to_csv=1' : '&export_grid_to_excel=2');
        }

        _export(kind) {
            if (kind === 'xlsx') {
                this.downloadExcelJs();
            } else if (kind === 'csv') {
                this.downloadCsvJs();
            } else if (kind === 'csv-server') {
                window.location.href = this.serverExportUrl('csv');
            } else {
                window.location.href = this.serverExportUrl('xlsx');
            }
        }

        _exportFileName(extension) {
            const now = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            return this.name + ' (' + now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate())
                + ' ' + pad(now.getHours()) + pad(now.getMinutes()) + ').' + extension;
        }

        /**
         * Every matching row as objects keyed by column label, visible + exportable columns only
         */
        async _fetchExportRows() {
            const response = await fetch(this.buildUrl(true), {headers: {'Accept': 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const payload = await response.json();
            if (!payload || payload.success === false) {
                throw new Error(payload && payload.message ? payload.message : 'Export failed');
            }
            const columns = this.visibleColumns().filter((column) => column.exportable);
            return (payload.rows || []).map((row) => {
                const out = {};
                for (const column of columns) {
                    const value = row[column.fieldName];
                    out[column.label] = column.isNumeric && value !== null && value !== '' && !isNaN(Number(value)) ? Number(value) : value;
                }
                return out;
            });
        }

        async downloadExcelJs() {
            if (!window.XLSX || !window.XLSX.utils) {
                window.location.href = this.serverExportUrl('xlsx');
                return;
            }
            this.showLoadingIcon();
            try {
                const rows = await this._fetchExportRows();
                const worksheet = window.XLSX.utils.json_to_sheet(rows);
                const workbook = window.XLSX.utils.book_new();
                window.XLSX.utils.book_append_sheet(workbook, worksheet, this.name.slice(0, 30));
                window.XLSX.writeFile(workbook, this._exportFileName('xlsx'), {bookType: 'xlsx'});
            } catch (error) {
                if (window.console) console.error('Grid ' + this.name + ': Excel export failed.', error);
                this.showError('The export failed. Please try again.');
            } finally {
                this.hideLoadingIcon();
            }
        }

        async downloadCsvJs() {
            this.showLoadingIcon();
            try {
                const rows = await this._fetchExportRows();
                const labels = this.visibleColumns().filter((column) => column.exportable).map((column) => column.label);
                const escape = (value) => {
                    const text = value === null || value === undefined ? '' : String(value);
                    return /[",\r\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
                };
                const lines = [labels.map(escape).join(',')];
                for (const row of rows) {
                    lines.push(labels.map((label) => escape(row[label])).join(','));
                }
                const blob = new Blob(['﻿' + lines.join('\r\n')], {type: 'text/csv;charset=utf-8'});
                const link = el('a');
                link.href = URL.createObjectURL(blob);
                link.download = this._exportFileName('csv');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                setTimeout(() => URL.revokeObjectURL(link.href), 1000);
            } catch (error) {
                if (window.console) console.error('Grid ' + this.name + ': CSV export failed.', error);
                this.showError('The export failed. Please try again.');
            } finally {
                this.hideLoadingIcon();
            }
        }

        // ─── Feedback ───────────────────────────────────────────────────

        showLoadingIcon() {
            if (this.el && this.el.loader) {
                this.el.loader.style.display = '';
            }
        }

        hideLoadingIcon() {
            if (this.el && this.el.loader) {
                this.el.loader.style.display = 'none';
            }
        }

        _errorBanner() {
            if (this._errorBannerEl && document.body.contains(this._errorBannerEl)) {
                return this._errorBannerEl;
            }
            const banner = el('div', 'alert alert-danger');
            banner.id = this.name + '_errorBanner';
            banner.setAttribute('role', 'alert');
            banner.style.display = 'none';
            const container = document.getElementById(this.name + '_container');
            if (container && container.parentNode) {
                container.parentNode.insertBefore(banner, container);
            } else {
                document.body.prepend(banner);
            }
            this._errorBannerEl = banner;
            return banner;
        }

        showError(message) {
            const banner = this._errorBanner();
            banner.textContent = message || 'An error occurred.';
            banner.style.display = '';
        }

        clearError() {
            if (this._errorBannerEl) {
                this._errorBannerEl.style.display = 'none';
                this._errorBannerEl.textContent = '';
            }
        }

        // ─── Accessors with behaviour ───────────────────────────────────

        get name() {
            return this._name;
        }

        set name(value) {
            this._name = value;
        }

        get currentPage() {
            return this._currentPage;
        }

        set currentPage(value) {
            let page = parseInt(value, 10);
            if (isNaN(page)) {
                page = 1;
            }
            page = Math.min(Math.max(1, page), Math.max(1, this.pageCount));
            if (page === this._currentPage) {
                return;
            }
            this._currentPage = page;
            Grid.log('page', page);
            if (this.el && this.el.curr) {
                this.el.curr.textContent = String(page);
            }
            this.fetchJson();
        }

        get rowsPerPage() {
            return this._rowsPerPage;
        }

        set rowsPerPage(value) {
            this._rowsPerPage = parseInt(value, 10) || 0;
        }

        get filters() {
            return this._filters;
        }

        set filters(value) {
            this._filters = value && typeof value === 'object' ? value : {};
            this._currentPage = 1;
            this.refresh();
        }

        get sortColumn() {
            return this._sortColumn;
        }

        set sortColumn(value) {
            this._sortColumn = value;
            this._currentPage = 1;
        }

        get sortDir() {
            return this._sortDir;
        }

        set sortDir(value) {
            this._sortDir = value ? String(value).toLowerCase() : null;
            this._currentPage = 1;
            this.refresh();
        }
    }

    Grid.debug = false;
    Grid.searchDebounceMs = 0;
    Grid.Column = Column;

    window.Grid = Grid;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', Grid.autoInitAll);
    } else {
        setTimeout(Grid.autoInitAll, 0);
    }
})(window, document);
