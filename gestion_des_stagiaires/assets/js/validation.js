/**
 * GDS — Contrôle de saisie unifié v2
 * Chargé globalement via header.php
 * S'attache automatiquement à tout input/select avec class="gds-validate"
 * ou dont le name correspond à une règle connue.
 */
(function () {
    'use strict';

    /* ── Inject CSS once ──────────────────────────────────────────── */
    if (!document.getElementById('gds-validate-style')) {
        const s = document.createElement('style');
        s.id = 'gds-validate-style';
        s.textContent = `
.gds-validate { transition: border-color .18s, box-shadow .18s; }
.gds-validate.gds-valid  { border: 1.5px solid #10b981 !important; box-shadow: 0 0 0 3px rgba(16,185,129,.15) !important; }
.gds-validate.gds-invalid { border: 1.5px solid #ef4444 !important; box-shadow: 0 0 0 3px rgba(239,68,68,.15) !important; }
.gds-validate.gds-checking { border: 1.5px solid #f59e0b !important; box-shadow: 0 0 0 3px rgba(245,158,11,.15) !important; }
.gds-err {
    color: #ef4444; font-size: 0.72rem; margin-top: 3px; line-height: 1.3;
    display: none; animation: gds-fadein .15s ease;
}
.gds-err.gds-show { display: block; }
.gds-err.gds-warn { color: #f59e0b; }
@keyframes gds-fadein { from{opacity:0;transform:translateY(-3px)} to{opacity:1;transform:none} }
`;
        document.head.appendChild(s);
    }

    /* ── Validation rules ─────────────────────────────────────────── */
    const RULES = {
        cin: {
            fn: v => /^[A-Za-z]{2}[0-9]{6}$/.test(v.toUpperCase()),
            msg: '2 lettres + 6 chiffres requis — ex: WA123456'
        },
        email: {
            fn: v => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v),
            msg: 'Adresse e-mail invalide — ex: nom@gmail.com'
        },
        telephone: {
            fn: v => /^(\+?212|0)[5-7][0-9]{8}$/.test(v.replace(/\s/g,'')),
            msg: 'Téléphone marocain invalide — ex: 0612345678 ou +212612345678'
        },
        telephone_parent: {
            fn: v => /^(\+?212|0)[5-7][0-9]{8}$/.test(v.replace(/\s/g,'')),
            msg: 'Téléphone marocain invalide — ex: 0612345678'
        },
        nom: {
            fn: v => /^[A-Za-zÀ-ÿ\s'\-]{2,}$/.test(v),
            msg: 'Nom invalide — lettres uniquement, minimum 2 caractères'
        },
        prenom: {
            fn: v => /^[A-Za-zÀ-ÿ\s'\-]{2,}$/.test(v),
            msg: 'Prénom invalide — lettres uniquement, minimum 2 caractères'
        },
        nom_tuteur: {
            fn: v => /^[A-Za-zÀ-ÿ\s'\-]{2,}$/.test(v),
            msg: 'Nom du tuteur invalide — lettres uniquement'
        },
        adresse: {
            fn: v => v.length >= 5,
            msg: 'Adresse trop courte — minimum 5 caractères'
        },
        num_inscri: {
            fn: v => /^[A-Za-z0-9\-\/]{3,}$/.test(v),
            msg: 'N° inscription invalide — ex: INS-2025-00001'
        },
        mot_de_passe: {
            fn: v => v.length >= 6,
            msg: 'Mot de passe trop court — minimum 6 caractères',
            skipIfEmpty: true
        },
        date_naissance: {
            fn: function(v) {
                if (!v) return true;
                const age = (Date.now() - new Date(v)) / 31557600000;
                return age >= 15 && age <= 70;
            },
            msg: 'Date de naissance invalide — âge doit être entre 15 et 70 ans'
        },
        date_inscription: {
            fn: function(v) {
                if (!v) return true;
                const d = new Date(v), now = new Date();
                return d <= new Date(now.getFullYear()+1, now.getMonth(), now.getDate());
            },
            msg: 'Date d\'inscription invalide'
        },
    };

    /* ── Fields that need uniqueness check via check_unique.php ─── */
    const UNIQUE_FIELDS = ['cin', 'email'];

    /* ── Debounce helper ─────────────────────────────────────────── */
    function debounce(fn, delay) {
        let timer;
        return function() {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, arguments), delay);
        };
    }

    /* ── Helpers ──────────────────────────────────────────────────── */
    function getErrEl(field) {
        let err = null;
        let el = field.nextElementSibling;
        while (el) {
            if (el.classList.contains('gds-err') && el.dataset.for === field.name) { err = el; break; }
            el = el.nextElementSibling;
        }
        if (!err) {
            const parent = field.parentElement;
            if (parent) {
                el = field.nextSibling;
                while (el) {
                    if (el.nodeType === 1 && el.classList && el.classList.contains('gds-err')) { err = el; break; }
                    el = el.nextSibling;
                }
            }
        }
        if (!err) {
            err = document.createElement('div');
            err.className = 'gds-err';
            err.dataset.for = field.name || '';
            field.insertAdjacentElement('afterend', err);
        }
        return err;
    }

    function setValid(field, err) {
        field.classList.remove('gds-invalid', 'gds-checking');
        field.classList.add('gds-valid');
        err.classList.remove('gds-show', 'gds-warn');
    }

    function setInvalid(field, err, msg) {
        field.classList.remove('gds-valid', 'gds-checking');
        field.classList.add('gds-invalid');
        err.classList.remove('gds-warn');
        err.textContent = msg;
        err.classList.add('gds-show');
    }

    function setChecking(field, err) {
        field.classList.remove('gds-valid', 'gds-invalid');
        field.classList.add('gds-checking');
        err.classList.remove('gds-warn');
        err.textContent = '⏳ Vérification en cours…';
        err.classList.add('gds-show');
    }

    function setNeutral(field, err) {
        field.classList.remove('gds-valid', 'gds-invalid', 'gds-checking');
        err.classList.remove('gds-show', 'gds-warn');
    }

    /* ── Core validate (sync — format only) ──────────────────────── */
    function validateField(field) {
        const name = field.name || '';
        const val  = field.value.trim();
        const rule = RULES[name];
        const err  = getErrEl(field);
        const req  = field.hasAttribute('required');

        if (val === '') {
            if (req) { setInvalid(field, err, 'Ce champ est obligatoire'); return false; }
            if (rule && rule.skipIfEmpty) { setNeutral(field, err); return true; }
            setNeutral(field, err);
            return true;
        }

        if (rule) {
            const ok = rule.fn(val);
            if (!ok) { setInvalid(field, err, field.dataset.error || rule.msg); return false; }
        }

        if (!field.checkValidity()) {
            setInvalid(field, err, field.dataset.error || 'Valeur invalide');
            return false;
        }

        setValid(field, err);
        return true;
    }

    /* ── Async uniqueness check ───────────────────────────────────── */
    function checkUnique(field) {
        const name = field.name || '';
        if (!UNIQUE_FIELDS.includes(name)) return;

        const val = field.value.trim();
        if (val === '') return;

        // Only run if format is currently valid
        if (field.classList.contains('gds-invalid')) return;

        const err = getErrEl(field);

        // Get exclude id from a hidden input [name="modifier_id"] or [name="exclude_id"] in same form
        let excludeId = 0;
        const form = field.closest('form');
        if (form) {
            const hiddenId = form.querySelector('input[name="modifier_id"], input[name="exclude_id"]');
            if (hiddenId) excludeId = parseInt(hiddenId.value) || 0;
        }

        setChecking(field, err);

        // Determine base path dynamically (works in any subdirectory)
        const base = document.querySelector('meta[name="gds-base"]')
            ? document.querySelector('meta[name="gds-base"]').content
            : (window.GDS_BASE || '');

        fetch(base + 'check_unique.php?field=' + encodeURIComponent(name)
            + '&value=' + encodeURIComponent(val)
            + '&exclude=' + excludeId, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                // Make sure the field value hasn't changed while we were waiting
                if (field.value.trim() !== val) return;
                if (data.ok) {
                    setValid(field, err);
                } else {
                    setInvalid(field, err, data.message || 'Valeur déjà utilisée.');
                }
            })
            .catch(() => {
                // Network error — silently fall back to valid (server will catch it anyway)
                setValid(field, err);
            });
    }

    const debouncedCheckUnique = debounce(checkUnique, 600);

    /* ── Auto-uppercase CIN while typing ──────────────────────────── */
    function maybeUppercase(field) {
        if (field.name === 'cin') {
            const pos = field.selectionStart;
            field.value = field.value.toUpperCase();
            try { field.setSelectionRange(pos, pos); } catch(e) {}
        }
    }

    /* ── Bind one field ────────────────────────────────────────────── */
    function bindField(field) {
        if (field._gdsBound) return;
        field._gdsBound = true;
        field.classList.add('gds-validate');

        field.addEventListener('input', function() {
            maybeUppercase(this);
            // Always re-validate format on input
            const formatOk = validateField(this);
            // If format is good and this is a unique field, fire async check (debounced)
            if (formatOk && UNIQUE_FIELDS.includes(this.name)) {
                debouncedCheckUnique(this);
            }
        });

        field.addEventListener('blur', function() {
            const formatOk = validateField(this);
            // On blur: fire uniqueness check immediately (no debounce)
            if (formatOk && UNIQUE_FIELDS.includes(this.name) && this.value.trim() !== '') {
                checkUnique(this);
            }
        });

        if (field.tagName === 'SELECT') {
            field.addEventListener('change', function() { validateField(this); });
        }
    }

    /* ── Bind form submit guard ───────────────────────────────────── */
    function bindForm(form) {
        if (form._gdsBound) return;
        form._gdsBound = true;
        form.addEventListener('submit', function(e) {
            let allOk = true;
            form.querySelectorAll('.gds-validate').forEach(function(f) {
                if (!validateField(f)) allOk = false;
            });
            // Block submit if any field is still in "checking" state
            if (form.querySelector('.gds-checking')) {
                e.preventDefault();
                allOk = false;
            }
            if (!allOk) {
                e.preventDefault();
                const first = form.querySelector('.gds-invalid, .gds-checking');
                if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    /* ── Init: scan DOM for known-name inputs ─────────────────────── */
    function init() {
        Object.keys(RULES).forEach(function(name) {
            document.querySelectorAll('input[name="' + name + '"], select[name="' + name + '"]')
                .forEach(bindField);
        });
        document.querySelectorAll('input.gds-validate, select.gds-validate').forEach(bindField);
        document.querySelectorAll('form').forEach(function(f) {
            if (f.querySelector('.gds-validate')) bindForm(f);
        });
    }

    /* ── MutationObserver for modals / dynamic content ───────────── */
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            m.addedNodes.forEach(function(node) {
                if (node.nodeType !== 1) return;
                node.querySelectorAll('input, select').forEach(function(f) {
                    if (RULES[f.name] || f.classList.contains('gds-validate')) bindField(f);
                });
                [node].concat(Array.from(node.querySelectorAll('form'))).forEach(function(el) {
                    if (el.tagName === 'FORM' && el.querySelector('.gds-validate')) bindForm(el);
                });
            });
        });
    });

    /* ── Boot ──────────────────────────────────────────────────────── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            init();
            observer.observe(document.body, { childList: true, subtree: true });
        });
    } else {
        init();
        observer.observe(document.body, { childList: true, subtree: true });
    }

})();
