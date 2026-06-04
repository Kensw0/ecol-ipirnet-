// Generic client-side table filter / sort helper.
//
// Usage:
//   window.gdsTableFilter({
//       table: '#liste-table',          // CSS selector of the <table>
//       controls: [                      // filter / sort controls (any subset is fine)
//           { selector: '#flt-filiere', field: 'filiere', type: 'equals' },
//           { selector: '#flt-classe',  field: 'classe',  type: 'equals' },
//           { selector: '#flt-search',  field: 'search',  type: 'contains', searchFields: ['name','num_inscri'] },
//           { selector: '#flt-sort',    field: 'sort',    type: 'sort' }
//       ]
//   });
//
// Each row to be filtered must carry data-* attributes named after the
// `field` (e.g. data-filiere, data-classe, data-name, data-num_inscri, ...).
//
// For sort controls, each <option> defines:
//   data-sort-key="name"     -> the data-* attribute to sort on
//   data-sort-dir="asc|desc" -> direction (default asc)
//   data-sort-num="1"        -> compare as numbers (default text)
//
// Rows that should never be filtered (e.g. <thead> rows) must NOT have
// data-filterable on them; the script only filters rows tagged with the
// data-filterable attribute.
(function () {
    'use strict';

    function bind(opts) {
        const table = document.querySelector(opts.table);
        if (!table) {
            return;
        }
        const rows = Array.from(table.querySelectorAll('tr[data-filterable]'));
        if (!rows.length) {
            return;
        }
        const parent = rows[0].parentNode;

        const controls = (opts.controls || [])
            .map(function (c) {
                return Object.assign({}, c, { el: document.querySelector(c.selector) });
            })
            .filter(function (c) { return !!c.el; });

        function passes(row, ctrl) {
            const v = (ctrl.el.value || '').trim();
            if (v === '' || v === '__all__') {
                return true;
            }
            if (ctrl.type === 'sort') {
                return true;
            }
            const lower = v.toLowerCase();
            if (ctrl.type === 'contains') {
                const fields = ctrl.searchFields || [ctrl.field];
                return fields.some(function (f) {
                    const data = (row.dataset[f] || '').toLowerCase();
                    return data.indexOf(lower) !== -1;
                });
            }
            if (ctrl.type === 'range') {
                const data = parseFloat(row.dataset[ctrl.field]);
                if (isNaN(data)) {
                    return false;
                }
                const parts = v.split('-');
                const min = parts[0] === '' ? -Infinity : parseFloat(parts[0]);
                const max = (parts.length < 2 || parts[1] === '') ? Infinity : parseFloat(parts[1]);
                return data >= min && data <= max;
            }
            // default: equals
            return (row.dataset[ctrl.field] || '') === v;
        }

        function sortKey(ctrl) {
            if (!ctrl || !ctrl.el) {
                return null;
            }
            const opt = ctrl.el.selectedOptions && ctrl.el.selectedOptions[0];
            if (!opt) {
                return null;
            }
            const key = opt.dataset.sortKey;
            if (!key) {
                return null;
            }
            return {
                key: key,
                dir: opt.dataset.sortDir === 'desc' ? 'desc' : 'asc',
                num: opt.dataset.sortNum === '1',
            };
        }

        function rerender() {
            const visible = rows.filter(function (r) {
                return controls.every(function (c) { return passes(r, c); });
            });
            const sortCtrl = controls.find(function (c) { return c.type === 'sort'; });
            const sk = sortKey(sortCtrl);
            const list = visible.slice();
            if (sk) {
                list.sort(function (a, b) {
                    let av = a.dataset[sk.key] || '';
                    let bv = b.dataset[sk.key] || '';
                    if (sk.num) {
                        av = parseFloat(av);
                        bv = parseFloat(bv);
                        if (isNaN(av)) av = 0;
                        if (isNaN(bv)) bv = 0;
                    } else {
                        av = av.toLowerCase();
                        bv = bv.toLowerCase();
                    }
                    if (av < bv) return sk.dir === 'asc' ? -1 : 1;
                    if (av > bv) return sk.dir === 'asc' ? 1 : -1;
                    return 0;
                });
            }
            // hide all, then re-append visible rows in order
            rows.forEach(function (r) { r.style.display = 'none'; });
            list.forEach(function (r) { r.style.display = ''; parent.appendChild(r); });

            // optional counter
            if (opts.counter) {
                const c = document.querySelector(opts.counter);
                if (c) {
                    c.textContent = String(list.length);
                }
            }
        }

        controls.forEach(function (c) {
            c.el.addEventListener('input', rerender);
            c.el.addEventListener('change', rerender);
        });
        rerender();
    }

    window.gdsTableFilter = bind;
})();
