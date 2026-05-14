(function () {
    'use strict';

    function applyFilter(form) {
        var filiereSel = form.querySelector('[data-role="filiere-filter"]');
        if (!filiereSel) {
            return;
        }
        var targets = form.querySelectorAll('select[data-filiere-filter="true"]');
        var val = filiereSel.value;
        Array.prototype.forEach.call(targets, function (sel) {
            var currentStillVisible = false;
            Array.prototype.forEach.call(sel.options, function (opt) {
                if (!opt.value) {
                    opt.hidden = false;
                    opt.disabled = false;
                    return;
                }
                var f = opt.getAttribute('data-filiere-id') || '';
                var match = !val || f === val;
                opt.hidden = !match;
                opt.disabled = !match;
                if (sel.value === opt.value && match) {
                    currentStillVisible = true;
                }
            });
            if (sel.value && !currentStillVisible) {
                sel.value = '';
            }
        });
    }

    function initForm(form) {
        var filiereSel = form.querySelector('[data-role="filiere-filter"]');
        if (!filiereSel) {
            return;
        }

        if (!filiereSel.value) {
            var targets = form.querySelectorAll('select[data-filiere-filter="true"]');
            Array.prototype.some.call(targets, function (sel) {
                var idx = sel.selectedIndex;
                if (idx < 0) {
                    return false;
                }
                var opt = sel.options[idx];
                if (!opt || !opt.value) {
                    return false;
                }
                var f = opt.getAttribute('data-filiere-id');
                if (f) {
                    filiereSel.value = f;
                    return true;
                }
                return false;
            });
        }

        filiereSel.addEventListener('change', function () {
            applyFilter(form);
        });

        var stagiaireSel = form.querySelector('select[name="id_stagiaire"][data-filiere-filter="true"]');
        if (stagiaireSel) {
            stagiaireSel.addEventListener('change', function () {
                var opt = stagiaireSel.options[stagiaireSel.selectedIndex];
                if (!opt || !opt.value) {
                    return;
                }
                var f = opt.getAttribute('data-filiere-id');
                if (f && f !== filiereSel.value) {
                    filiereSel.value = f;
                    applyFilter(form);
                }
            });
        }

        applyFilter(form);
    }

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    ready(function () {
        var forms = document.querySelectorAll('form[data-filiere-form="true"]');
        Array.prototype.forEach.call(forms, initForm);
    });
})();
