/*
 * LAF autocomplete (Laf\UI\Form\Input\Autocomplete and ManyToManyAutocomplete). Vanilla JS, no
 * dependencies, Bootstrap 5 dropdown markup. One delegated listener set handles every autocomplete
 * on the page, including ones added later (modals, tabs loaded on demand).
 *
 * Markup: div.laf-autocomplete[data-laf-autocomplete="table.field"][data-min-chars][data-url]
 *           input[type=hidden].laf-autocomplete-value   the posted key (one value)
 *           input.laf-autocomplete-input                the search box (shows the label)
 *           div.laf-autocomplete-menu                   the result list
 *         Several values (a linker table): [data-multiple][data-name="relation[]"][data-max-items],
 *           input[type=hidden].laf-autocomplete-empty   posts "" so removing every chip is submitted
 *           div.laf-autocomplete-chips                  span.laf-autocomplete-chip[data-value] per value,
 *                                                       each with its hidden input and a remove button
 *         Creating rows: [data-create][data-create-text][data-csrf-name][data-csrf-token]
 *         Two lists (ManyToManyDualList): div.laf-duallist[data-laf-autocomplete][data-name] with
 *           select.laf-duallist-available / select.laf-duallist-selected, div.laf-duallist-values (the posted
 *           hidden inputs, rebuilt from the selected list), input.laf-duallist-filter, input.laf-duallist-new
 * Events: one value: the hidden input fires "change" on a new value; the box fires
 * "laf:autocomplete:select" with detail {value, label} (value '' when cleared). Several values:
 * "laf:autocomplete:add" / "laf:autocomplete:remove" {value, label} and "change" on the box.
 * A created row also fires "laf:autocomplete:create" {value, label}.
 */
(function () {
    'use strict';
    if (window.LafAutocomplete) {
        return;
    }

    var DEBOUNCE_MS = 250;
    var REQUEST_KEY = 'laf_lookup';
    var REQUEST_TERM = 'laf_q';
    var REQUEST_CREATE = 'laf_create';

    function parts(box) {
        return {
            value: box.querySelector('.laf-autocomplete-value'),
            input: box.querySelector('.laf-autocomplete-input'),
            menu: box.querySelector('.laf-autocomplete-menu'),
            chips: box.querySelector('.laf-autocomplete-chips')
        };
    }

    function isMultiple(box) {
        return box.hasAttribute('data-multiple');
    }

    function state(box) {
        if (!box._lafAutocomplete) {
            var p = parts(box);
            box._lafAutocomplete = {
                timer: null,
                controller: null,
                items: [],
                active: -1,
                label: (p.input && !isMultiple(box)) ? p.input.value : ''
            };
        }
        return box._lafAutocomplete;
    }

    function isOpen(box) {
        return parts(box).menu.classList.contains('show');
    }

    function close(box) {
        var p = parts(box);
        var st = state(box);
        p.menu.classList.remove('show');
        p.menu.innerHTML = '';
        p.input.setAttribute('aria-expanded', 'false');
        p.input.removeAttribute('aria-activedescendant');
        st.items = [];
        st.active = -1;
    }

    function setActive(box, index) {
        var p = parts(box);
        var st = state(box);
        var buttons = p.menu.querySelectorAll('.laf-autocomplete-item');
        if (buttons.length === 0) {
            return;
        }
        if (index < 0) {
            index = buttons.length - 1;
        }
        if (index >= buttons.length) {
            index = 0;
        }
        st.active = index;
        buttons.forEach(function (b, i) {
            var on = i === index;
            b.classList.toggle('active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
            if (on) {
                p.input.setAttribute('aria-activedescendant', b.id);
                b.scrollIntoView({block: 'nearest'});
            }
        });
    }

    /* ───────────── chips (several values) ───────────── */

    function chosenValues(box) {
        var p = parts(box);
        if (!p.chips) {
            return [];
        }
        return Array.prototype.map.call(p.chips.querySelectorAll('.laf-autocomplete-chip'), function (chip) {
            return chip.getAttribute('data-value');
        });
    }

    function maxItems(box) {
        var max = parseInt(box.getAttribute('data-max-items') || '0', 10);
        return isNaN(max) || max <= 0 ? 0 : max;
    }

    function addChip(box, value, label) {
        var p = parts(box);
        if (chosenValues(box).indexOf(value) !== -1) {
            return false;
        }
        var max = maxItems(box);
        if (max > 0 && chosenValues(box).length >= max) {
            return false;
        }
        var chip = document.createElement('span');
        chip.className = 'badge bg-secondary laf-autocomplete-chip d-inline-flex align-items-center';
        chip.setAttribute('data-value', value);
        var text = document.createElement('span');
        text.className = 'laf-autocomplete-chip-label';
        text.textContent = label;
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn-close btn-close-white ms-1 laf-autocomplete-remove';
        remove.style.fontSize = '.6em';
        remove.setAttribute('aria-label', 'Remove ' + label);
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = box.getAttribute('data-name') || '';
        hidden.value = value;
        chip.appendChild(text);
        chip.appendChild(remove);
        chip.appendChild(hidden);
        p.chips.appendChild(chip);
        box.dispatchEvent(new CustomEvent('laf:autocomplete:add', {bubbles: true, detail: {value: value, label: label}}));
        box.dispatchEvent(new Event('change', {bubbles: true}));
        return true;
    }

    function removeChip(box, chip) {
        if (!chip) {
            return;
        }
        var value = chip.getAttribute('data-value');
        var labelNode = chip.querySelector('.laf-autocomplete-chip-label');
        var label = labelNode ? labelNode.textContent : value;
        chip.parentNode.removeChild(chip);
        box.dispatchEvent(new CustomEvent('laf:autocomplete:remove', {bubbles: true, detail: {value: value, label: label}}));
        box.dispatchEvent(new Event('change', {bubbles: true}));
    }

    function chipOf(box, value) {
        var chips = parts(box).chips;
        if (!chips) {
            return null;
        }
        var found = null;
        chips.querySelectorAll('.laf-autocomplete-chip').forEach(function (chip) {
            if (chip.getAttribute('data-value') === String(value)) {
                found = chip;
            }
        });
        return found;
    }

    /* ───────────── list ───────────── */

    function itemButton(menu, i, text, extraClass) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'dropdown-item laf-autocomplete-item' + (extraClass ? ' ' + extraClass : '');
        b.id = menu.id + '_' + i;
        b.setAttribute('role', 'option');
        b.setAttribute('aria-selected', 'false');
        b.setAttribute('data-index', String(i));
        b.tabIndex = -1;
        b.textContent = text;
        return b;
    }

    function render(box, data, term) {
        var p = parts(box);
        var st = state(box);
        var results = (data && Array.isArray(data.results)) ? data.results : [];
        if (isMultiple(box)) {
            var chosen = chosenValues(box);
            results = results.filter(function (item) {
                return chosen.indexOf(String(item.value)) === -1;
            });
        }
        var offerCreate = box.hasAttribute('data-create') && term !== '' && !results.some(function (item) {
            return String(item.label).toLowerCase() === term.toLowerCase();
        });
        st.items = results.slice();
        st.active = -1;
        p.menu.innerHTML = '';
        if (results.length === 0 && !offerCreate) {
            var empty = document.createElement('span');
            empty.className = 'dropdown-item-text text-muted';
            empty.textContent = box.getAttribute('data-no-results') || 'No matches';
            p.menu.appendChild(empty);
        }
        results.forEach(function (item, i) {
            p.menu.appendChild(itemButton(p.menu, i, item.label));
        });
        if (data && data.more) {
            var more = document.createElement('span');
            more.className = 'dropdown-item-text small text-muted';
            more.textContent = '…';
            p.menu.appendChild(more);
        }
        if (offerCreate) {
            var text = (box.getAttribute('data-create-text') || 'Create "%s"').replace('%s', term);
            st.items.push({create: true, label: term});
            p.menu.appendChild(itemButton(p.menu, st.items.length - 1, '+ ' + text, 'laf-autocomplete-create'));
        }
        p.menu.classList.add('show');
        p.input.setAttribute('aria-expanded', 'true');
    }

    function showMessage(box, message) {
        var p = parts(box);
        var row = document.createElement('span');
        row.className = 'dropdown-item-text text-danger small laf-autocomplete-error';
        row.setAttribute('role', 'alert');
        row.textContent = message;
        p.menu.appendChild(row);
        p.menu.classList.add('show');
        p.input.setAttribute('aria-expanded', 'true');
    }

    function lookupUrl(box, params) {
        var url = new URL(box.getAttribute('data-url') || window.location.href, window.location.href);
        url.hash = '';
        url.searchParams.set(REQUEST_KEY, box.getAttribute('data-laf-autocomplete') || '');
        Object.keys(params).forEach(function (key) {
            url.searchParams.set(key, params[key]);
        });
        return url.toString();
    }

    function search(box) {
        var p = parts(box);
        var st = state(box);
        var term = p.input.value.trim();
        if (st.controller) {
            st.controller.abort();
        }
        st.controller = typeof AbortController === 'function' ? new AbortController() : null;
        var params = {};
        params[REQUEST_TERM] = term;
        box.classList.add('laf-autocomplete-loading');
        fetch(lookupUrl(box, params), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            signal: st.controller ? st.controller.signal : undefined
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Lookup failed with HTTP ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            box.classList.remove('laf-autocomplete-loading');
            if (document.activeElement === p.input) {
                render(box, data, term);
            }
        }).catch(function (error) {
            box.classList.remove('laf-autocomplete-loading');
            if (error && error.name === 'AbortError') {
                return;
            }
            if (window.console) {
                console.error('LAF autocomplete', error);
            }
        });
    }

    function create(box, term) {
        var params = {};
        params[REQUEST_CREATE] = '1';
        var body = new URLSearchParams();
        body.set(REQUEST_TERM, term);
        var csrfName = box.getAttribute('data-csrf-name');
        if (csrfName) {
            body.set(csrfName, box.getAttribute('data-csrf-token') || '');
        }
        box.classList.add('laf-autocomplete-loading');
        fetch(lookupUrl(box, params), {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: body
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (data) {
                return {ok: response.ok, data: data || {}};
            });
        }).then(function (answer) {
            box.classList.remove('laf-autocomplete-loading');
            if (!answer.ok || answer.data.value === undefined) {
                close(box);
                showMessage(box, answer.data.error || 'The entry could not be added');
                return;
            }
            var value = String(answer.data.value);
            var label = String(answer.data.label);
            choose(box, value, label);
            box.dispatchEvent(new CustomEvent('laf:autocomplete:create', {bubbles: true, detail: {value: value, label: label}}));
        }).catch(function (error) {
            box.classList.remove('laf-autocomplete-loading');
            close(box);
            showMessage(box, 'The entry could not be added');
            if (window.console) {
                console.error('LAF autocomplete', error);
            }
        });
    }

    function schedule(box, immediate) {
        var p = parts(box);
        var st = state(box);
        var min = parseInt(box.getAttribute('data-min-chars') || '1', 10);
        clearTimeout(st.timer);
        if (p.input.value.trim().length < (isNaN(min) ? 1 : min)) {
            close(box);
            return;
        }
        st.timer = setTimeout(function () {
            search(box);
        }, immediate ? 0 : DEBOUNCE_MS);
    }

    function choose(box, value, label) {
        var p = parts(box);
        var st = state(box);
        if (isMultiple(box)) {
            if (value !== '') {
                addChip(box, value, label);
            }
            p.input.value = '';
            st.label = '';
            close(box);
            return;
        }
        var changed = p.value.value !== value;
        p.value.value = value;
        p.input.value = label;
        st.label = label;
        close(box);
        if (changed) {
            p.value.dispatchEvent(new Event('change', {bubbles: true}));
        }
        box.dispatchEvent(new CustomEvent('laf:autocomplete:select', {bubbles: true, detail: {value: value, label: label}}));
    }

    function pick(box, item) {
        if (!item) {
            return;
        }
        if (item.create) {
            create(box, String(item.label));
            return;
        }
        choose(box, String(item.value), String(item.label));
    }

    function boxOf(target) {
        return target && target.closest ? target.closest('.laf-autocomplete[data-laf-autocomplete]') : null;
    }

    document.addEventListener('input', function (e) {
        if (!e.target.classList || !e.target.classList.contains('laf-autocomplete-input')) {
            return;
        }
        var box = boxOf(e.target);
        if (!box) {
            return;
        }
        if (e.target.value === '' && !isMultiple(box)) {
            choose(box, '', '');
        }
        schedule(box, false);
    });

    document.addEventListener('focusin', function (e) {
        if (!e.target.classList || !e.target.classList.contains('laf-autocomplete-input')) {
            return;
        }
        var box = boxOf(e.target);
        if (!box) {
            return;
        }
        var st = state(box);
        if (st.quietFocus) {
            // focus given back after a chip was removed: do not cover the form with the list
            st.quietFocus = false;
            return;
        }
        if (box.getAttribute('data-min-chars') === '0' && !isOpen(box)) {
            schedule(box, true);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (!e.target.classList || !e.target.classList.contains('laf-autocomplete-input')) {
            return;
        }
        var box = boxOf(e.target);
        if (!box) {
            return;
        }
        var st = state(box);
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (!isOpen(box)) {
                schedule(box, true);
                return;
            }
            setActive(box, st.active + (e.key === 'ArrowDown' ? 1 : -1));
        } else if (e.key === 'Enter') {
            if (isOpen(box) && st.active >= 0 && st.items[st.active]) {
                e.preventDefault();
                pick(box, st.items[st.active]);
            } else if (isOpen(box) && st.items.length === 1 && !st.items[0].create) {
                e.preventDefault();
                pick(box, st.items[0]);
            } else if (isOpen(box) || e.target.value !== st.label) {
                // an open list or an unconfirmed search never submits the form
                e.preventDefault();
            }
        } else if (e.key === 'Escape') {
            if (isOpen(box)) {
                e.preventDefault();
                close(box);
            }
            e.target.value = st.label;
        } else if (e.key === 'Backspace' && isMultiple(box) && e.target.value === '') {
            var chips = parts(box).chips ? parts(box).chips.querySelectorAll('.laf-autocomplete-chip') : [];
            if (chips.length > 0) {
                e.preventDefault();
                removeChip(box, chips[chips.length - 1]);
            }
        }
    });

    // mousedown keeps the focus in the input, so focusout does not close the menu before the click
    document.addEventListener('mousedown', function (e) {
        var item = e.target.closest ? e.target.closest('.laf-autocomplete-item') : null;
        if (item) {
            e.preventDefault();
        }
    });

    document.addEventListener('click', function (e) {
        var removeButton = e.target.closest ? e.target.closest('.laf-autocomplete-remove') : null;
        if (removeButton) {
            var chipBox = boxOf(removeButton);
            if (chipBox) {
                removeChip(chipBox, removeButton.closest('.laf-autocomplete-chip'));
                var input = parts(chipBox).input;
                state(chipBox).quietFocus = document.activeElement !== input;
                input.focus();
            }
            return;
        }
        var item = e.target.closest ? e.target.closest('.laf-autocomplete-item') : null;
        if (!item) {
            return;
        }
        var box = boxOf(item);
        var st = box ? state(box) : null;
        var data = st ? st.items[parseInt(item.getAttribute('data-index') || '-1', 10)] : null;
        if (box && data) {
            pick(box, data);
            parts(box).input.focus();
        }
    });

    // leaving the box: close the list; typed text that was not chosen falls back to the chosen label
    document.addEventListener('focusout', function (e) {
        var box = boxOf(e.target);
        if (!box) {
            return;
        }
        setTimeout(function () {
            if (box.contains(document.activeElement)) {
                return;
            }
            var p = parts(box);
            var st = state(box);
            clearTimeout(st.timer);
            if (st.controller) {
                st.controller.abort();
            }
            close(box);
            if (p.input.value !== st.label) {
                p.input.value = st.label;
            }
        }, 0);
    });

    /* ───────────── dual list (ManyToManyDualList) ───────────── */

    function dualParts(box) {
        return {
            available: box.querySelector('.laf-duallist-available'),
            selected: box.querySelector('.laf-duallist-selected'),
            values: box.querySelector('.laf-duallist-values'),
            filter: box.querySelector('.laf-duallist-filter'),
            input: box.querySelector('.laf-duallist-new'),
            error: box.querySelector('.laf-duallist-error')
        };
    }

    function dualBoxOf(target) {
        return target && target.closest ? target.closest('.laf-duallist[data-laf-autocomplete]') : null;
    }

    // the available list keeps the table's order: an option goes back to its place
    function insertByPosition(select, option) {
        var position = parseInt(option.getAttribute('data-position') || '0', 10);
        var before = null;
        Array.prototype.some.call(select.options, function (other) {
            if (parseInt(other.getAttribute('data-position') || '0', 10) > position) {
                before = other;
                return true;
            }
            return false;
        });
        select.insertBefore(option, before);
    }

    function dualSync(box) {
        var p = dualParts(box);
        var name = box.getAttribute('data-name') || '';
        p.values.innerHTML = '';
        Array.prototype.forEach.call(p.selected.options, function (option) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = name;
            hidden.value = option.value;
            p.values.appendChild(hidden);
        });
        box.dispatchEvent(new Event('change', {bubbles: true}));
    }

    function dualFilter(box) {
        var p = dualParts(box);
        var term = p.filter ? p.filter.value.trim().toLowerCase() : '';
        Array.prototype.forEach.call(p.available.options, function (option) {
            var match = term === '' || option.textContent.toLowerCase().indexOf(term) !== -1;
            option.hidden = !match;
            option.disabled = !match;
        });
    }

    function dualMessage(box, text) {
        var p = dualParts(box);
        if (p.error) {
            p.error.textContent = text || '';
        }
    }

    /** Moves options to the selected list (adding = true) or back; all = every visible option */
    function dualMove(box, adding, all) {
        var p = dualParts(box);
        var from = adding ? p.available : p.selected;
        var to = adding ? p.selected : p.available;
        var moving = Array.prototype.filter.call(from.options, function (option) {
            return !option.hidden && (all || option.selected);
        });
        var max = maxItems(box);
        if (adding && max > 0 && p.selected.options.length + moving.length > max) {
            moving = moving.slice(0, Math.max(0, max - p.selected.options.length));
            dualMessage(box, 'At most ' + max);
        } else {
            dualMessage(box, '');
        }
        moving.forEach(function (option) {
            option.selected = false;
            if (adding) {
                to.appendChild(option);
            } else {
                option.hidden = false;
                option.disabled = false;
                insertByPosition(to, option);
            }
            box.dispatchEvent(new CustomEvent(adding ? 'laf:autocomplete:add' : 'laf:autocomplete:remove', {
                bubbles: true,
                detail: {value: option.value, label: option.textContent}
            }));
        });
        if (moving.length > 0) {
            if (!adding) {
                dualFilter(box);
            }
            dualSync(box);
        }
    }

    function dualCreate(box) {
        var p = dualParts(box);
        var term = p.input ? p.input.value.trim() : '';
        if (term === '') {
            return;
        }
        var params = {};
        params[REQUEST_CREATE] = '1';
        var body = new URLSearchParams();
        body.set(REQUEST_TERM, term);
        var csrfName = box.getAttribute('data-csrf-name');
        if (csrfName) {
            body.set(csrfName, box.getAttribute('data-csrf-token') || '');
        }
        fetch(lookupUrl(box, params), {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: body
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (data) {
                return {ok: response.ok, data: data || {}};
            });
        }).then(function (answer) {
            if (!answer.ok || answer.data.value === undefined) {
                dualMessage(box, answer.data.error || 'The entry could not be added');
                return;
            }
            var value = String(answer.data.value);
            var label = String(answer.data.label);
            var existing = null;
            [p.available, p.selected].forEach(function (select) {
                Array.prototype.forEach.call(select.options, function (option) {
                    if (option.value === value) {
                        existing = option;
                    }
                });
            });
            if (existing && existing.parentNode === p.selected) {
                dualMessage(box, '');
            } else {
                var option = existing || new Option(label, value);
                if (!existing) {
                    option.setAttribute('data-position', '999999');
                }
                option.hidden = false;
                option.disabled = false;
                option.selected = true;
                if (option.parentNode !== p.available) {
                    p.available.appendChild(option);
                }
                Array.prototype.forEach.call(p.available.options, function (other) {
                    other.selected = other === option;
                });
                dualMove(box, true, false);
            }
            p.input.value = '';
            box.dispatchEvent(new CustomEvent('laf:autocomplete:create', {bubbles: true, detail: {value: value, label: label}}));
        }).catch(function (error) {
            dualMessage(box, 'The entry could not be added');
            if (window.console) {
                console.error('LAF dual list', error);
            }
        });
    }

    document.addEventListener('click', function (e) {
        var button = e.target.closest ? e.target.closest('.laf-duallist button') : null;
        var box = dualBoxOf(button);
        if (!button || !box) {
            return;
        }
        if (button.classList.contains('laf-duallist-add')) {
            dualMove(box, true, false);
        } else if (button.classList.contains('laf-duallist-add-all')) {
            dualMove(box, true, true);
        } else if (button.classList.contains('laf-duallist-remove')) {
            dualMove(box, false, false);
        } else if (button.classList.contains('laf-duallist-remove-all')) {
            dualMove(box, false, true);
        } else if (button.classList.contains('laf-duallist-create')) {
            dualCreate(box);
        }
    });

    document.addEventListener('dblclick', function (e) {
        var option = e.target.closest ? e.target.closest('option') : null;
        var box = dualBoxOf(option);
        if (!option || !box) {
            return;
        }
        // a double click moves the option clicked, not whatever else is still selected in that list
        Array.prototype.forEach.call(option.parentNode.options, function (other) {
            other.selected = other === option;
        });
        dualMove(box, option.parentNode.classList.contains('laf-duallist-available'), false);
    });

    document.addEventListener('keydown', function (e) {
        var box = dualBoxOf(e.target);
        if (!box || !e.target.classList) {
            return;
        }
        if (e.key === 'Enter' && e.target.classList.contains('laf-duallist-available')) {
            e.preventDefault();
            dualMove(box, true, false);
        } else if ((e.key === 'Delete' || e.key === 'Backspace') && e.target.classList.contains('laf-duallist-selected')) {
            e.preventDefault();
            dualMove(box, false, false);
        } else if (e.key === 'Enter' && (e.target.classList.contains('laf-duallist-new') || e.target.classList.contains('laf-duallist-filter'))) {
            // never submits the form from these boxes
            e.preventDefault();
            if (e.target.classList.contains('laf-duallist-new')) {
                dualCreate(box);
            }
        }
    });

    document.addEventListener('input', function (e) {
        if (e.target.classList && e.target.classList.contains('laf-duallist-filter')) {
            var box = dualBoxOf(e.target);
            if (box) {
                dualFilter(box);
            }
        }
    });

    window.LafAutocomplete = {
        /** Set a value from code: LafAutocomplete.set(box, '12', 'ACME Ltd') (adds a chip on a multi-value box) */
        set: function (box, value, label) {
            choose(box, String(value), String(label));
        },
        /** One value: clear it; several values: remove every chip */
        clear: function (box) {
            if (isMultiple(box)) {
                chosenValues(box).forEach(function (value) {
                    removeChip(box, chipOf(box, value));
                });
                return;
            }
            choose(box, '', '');
        },
        /** Several values: add a chip */
        add: function (box, value, label) {
            if (isMultiple(box)) {
                addChip(box, String(value), String(label));
            }
        },
        /** Several values: remove the chip of a value */
        remove: function (box, value) {
            if (isMultiple(box)) {
                removeChip(box, chipOf(box, value));
            }
        },
        /** Several values: the chosen keys */
        values: function (box) {
            if (box.classList.contains('laf-duallist')) {
                return Array.prototype.map.call(dualParts(box).selected.options, function (option) {
                    return option.value;
                });
            }
            return isMultiple(box) ? chosenValues(box) : [parts(box).value.value].filter(function (v) {
                return v !== '';
            });
        },
        debounceMs: DEBOUNCE_MS
    };
})();
