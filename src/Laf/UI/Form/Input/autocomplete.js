/*
 * LAF foreign-key autocomplete (Laf\UI\Form\Input\Autocomplete). Vanilla JS, no dependencies,
 * Bootstrap 5 dropdown markup. One delegated listener set handles every autocomplete on the page,
 * including ones added later (modals, tabs loaded on demand).
 *
 * Markup: div.laf-autocomplete[data-laf-autocomplete="table.field"][data-min-chars][data-url]
 *           input[type=hidden].laf-autocomplete-value   the posted key
 *           input.laf-autocomplete-input                the search box (shows the label)
 *           div.laf-autocomplete-menu                   the result list
 * Events: the hidden input fires "change" on a new value; the box fires
 * "laf:autocomplete:select" with detail {value, label} (value '' when cleared).
 */
(function () {
    'use strict';
    if (window.LafAutocomplete) {
        return;
    }

    var DEBOUNCE_MS = 250;
    var REQUEST_KEY = 'laf_lookup';
    var REQUEST_TERM = 'laf_q';

    function parts(box) {
        return {
            value: box.querySelector('.laf-autocomplete-value'),
            input: box.querySelector('.laf-autocomplete-input'),
            menu: box.querySelector('.laf-autocomplete-menu')
        };
    }

    function state(box) {
        if (!box._lafAutocomplete) {
            var p = parts(box);
            box._lafAutocomplete = {timer: null, controller: null, items: [], active: -1, label: p.input ? p.input.value : ''};
        }
        return box._lafAutocomplete;
    }

    function isOpen(box) {
        var p = parts(box);
        return p.menu.classList.contains('show');
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

    function render(box, data) {
        var p = parts(box);
        var st = state(box);
        var results = (data && Array.isArray(data.results)) ? data.results : [];
        st.items = results;
        st.active = -1;
        p.menu.innerHTML = '';
        if (results.length === 0) {
            var empty = document.createElement('span');
            empty.className = 'dropdown-item-text text-muted';
            empty.textContent = box.getAttribute('data-no-results') || 'No matches';
            p.menu.appendChild(empty);
        }
        results.forEach(function (item, i) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'dropdown-item laf-autocomplete-item';
            b.id = p.menu.id + '_' + i;
            b.setAttribute('role', 'option');
            b.setAttribute('aria-selected', 'false');
            b.setAttribute('data-index', String(i));
            b.tabIndex = -1;
            b.textContent = item.label;
            p.menu.appendChild(b);
        });
        if (data && data.more) {
            var more = document.createElement('span');
            more.className = 'dropdown-item-text small text-muted';
            more.textContent = '…';
            p.menu.appendChild(more);
        }
        p.menu.classList.add('show');
        p.input.setAttribute('aria-expanded', 'true');
    }

    function lookupUrl(box, term) {
        var url = new URL(box.getAttribute('data-url') || window.location.href, window.location.href);
        url.hash = '';
        url.searchParams.set(REQUEST_KEY, box.getAttribute('data-laf-autocomplete') || '');
        url.searchParams.set(REQUEST_TERM, term);
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
        box.classList.add('laf-autocomplete-loading');
        fetch(lookupUrl(box, term), {
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
                render(box, data);
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
        if (e.target.value === '') {
            choose(box, '', '');
        }
        schedule(box, false);
    });

    document.addEventListener('focusin', function (e) {
        if (!e.target.classList || !e.target.classList.contains('laf-autocomplete-input')) {
            return;
        }
        var box = boxOf(e.target);
        if (box && box.getAttribute('data-min-chars') === '0' && !isOpen(box)) {
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
                choose(box, String(st.items[st.active].value), String(st.items[st.active].label));
            } else if (isOpen(box) && st.items.length === 1) {
                e.preventDefault();
                choose(box, String(st.items[0].value), String(st.items[0].label));
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
        var item = e.target.closest ? e.target.closest('.laf-autocomplete-item') : null;
        if (!item) {
            return;
        }
        var box = boxOf(item);
        var st = box ? state(box) : null;
        var data = st ? st.items[parseInt(item.getAttribute('data-index') || '-1', 10)] : null;
        if (box && data) {
            choose(box, String(data.value), String(data.label));
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

    window.LafAutocomplete = {
        /** Set a value from code: LafAutocomplete.set(box, '12', 'ACME Ltd') */
        set: function (box, value, label) {
            choose(box, String(value), String(label));
        },
        clear: function (box) {
            choose(box, '', '');
        },
        debounceMs: DEBOUNCE_MS
    };
})();
