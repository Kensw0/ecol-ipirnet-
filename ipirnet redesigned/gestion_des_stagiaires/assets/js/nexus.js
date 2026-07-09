/* ============================================================
   NEXUS.TERMINAL v3 — lean sensory layer
   Rebuilt for performance: no canvas rain loop, no per-frame
   custom-cursor tracking. Only one-shot / rarely-fired work:
   session-scoped intrusion boot line, terminal SFX (WebAudio,
   fires only on interaction), single-element tab-switch wipe,
   sidebar collapse, toasts, KPI counters, one-shot scroll reveal.
   ============================================================ */
(function () {
    'use strict';

    /* ── WebAudio SFX (synthesized, no assets, fires on demand only) ── */
    var actx = null;
    function ctx() {
        if (!actx) {
            var AC = window.AudioContext || window.webkitAudioContext;
            if (AC) actx = new AC();
        }
        return actx;
    }
    function beep(freq, dur, type, gainVal) {
        var a = ctx();
        if (!a) return;
        if (a.state === 'suspended') a.resume();
        var osc = a.createOscillator();
        var gain = a.createGain();
        osc.type = type || 'square';
        osc.frequency.setValueAtTime(freq, a.currentTime);
        gain.gain.setValueAtTime(gainVal || 0.05, a.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.0001, a.currentTime + dur);
        osc.connect(gain).connect(a.destination);
        osc.start();
        osc.stop(a.currentTime + dur);
    }
    window.nxSfx = {
        click: function () { beep(700, 0.05, 'square', 0.05); },
        toast: function () { beep(880, 0.08, 'sine', 0.04); },
        boot:  function () { beep(220, 0.12, 'sawtooth', 0.03); }
    };
    document.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('a, button, .nav-item, .btn')) window.nxSfx.click();
    });

    /* ── Intrusion loader — plays once per browser session ────────── */
    var intrusion = document.getElementById('nx-intrusion-loader');
    var intrusionText = document.getElementById('nx-intrusion-text');
    if (intrusion && intrusionText && !sessionStorage.getItem('nxBooted')) {
        sessionStorage.setItem('nxBooted', '1');
        intrusion.classList.add('nx-intrusion-active');
        window.nxSfx.boot();
        var line = 'Warning: Intrusion detected.';
        var idx = 0;
        var typer = setInterval(function () {
            intrusionText.textContent = line.slice(0, idx);
            idx++;
            if (idx > line.length) {
                clearInterval(typer);
                setTimeout(function () {
                    intrusion.classList.add('nx-intrusion-out');
                    setTimeout(function () { intrusion.remove(); }, 350);
                }, 450);
            }
        }, 55);
    } else if (intrusion) {
        intrusion.remove();
    }

    /* ── Tab-switch transition — single flat layer, sidebar nav only ──
       Scoped strictly to .sidebar .nav-item links: never fires for
       form submits, buttons, topbar, logout, or any other link. */
    var wipe = document.getElementById('nx-page-wipe');
    document.addEventListener('click', function (e) {
        var link = e.target.closest && e.target.closest('.sidebar .nav-item[href]');
        if (!link || !wipe) return;
        var href = link.getAttribute('href') || '';
        if (href.startsWith('#') || href.startsWith('javascript:') || link.target === '_blank' || e.metaKey || e.ctrlKey) return;
        if (link.hostname && link.hostname !== window.location.hostname) return;
        e.preventDefault();
        wipe.classList.add('nx-wipe-active');
        setTimeout(function () { window.location.href = href; }, 280);
    });

    /* ── Sidebar collapse toggle ─────────────────────────────────── */
    var layout = document.querySelector('.admin-layout');
    if (layout) {
        var saved = localStorage.getItem('nxSidebarCollapsed');
        if (saved === '1') layout.setAttribute('data-sidebar-collapsed', 'true');

        var toggleBtn = document.getElementById('nx-sidebar-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                var collapsed = layout.getAttribute('data-sidebar-collapsed') === 'true';
                layout.setAttribute('data-sidebar-collapsed', collapsed ? 'false' : 'true');
                localStorage.setItem('nxSidebarCollapsed', collapsed ? '0' : '1');
            });
        }
        document.querySelectorAll('.sidebar .nav-item').forEach(function (el) {
            var span = el.querySelector('span');
            if (span && !el.hasAttribute('data-tooltip')) {
                el.setAttribute('data-tooltip', span.textContent.trim());
            }
        });
    }

    /* ── Live clock in topbar (1 timer, 1s tick — negligible cost) ──── */
    var clockEl = document.getElementById('nx-clock');
    if (clockEl) {
        var tick = function () {
            var now = new Date();
            clockEl.textContent = String(now.getHours()).padStart(2, '0') + ':' +
                String(now.getMinutes()).padStart(2, '0') + ':' +
                String(now.getSeconds()).padStart(2, '0');
        };
        tick();
        setInterval(tick, 1000);
    }

    /* ── Toast system ─────────────────────────────────────────────── */
    function ensureToastContainer() {
        var c = document.getElementById('nx-toast-container');
        if (!c) { c = document.createElement('div'); c.id = 'nx-toast-container'; document.body.appendChild(c); }
        return c;
    }
    var iconByType = { success: 'fa-circle-check', error: 'fa-circle-xmark', warning: 'fa-triangle-exclamation', info: 'fa-circle-info' };
    window.nxToast = function (message, type, duration) {
        type = type || 'info'; duration = duration || 4200;
        var container = ensureToastContainer();
        var el = document.createElement('div');
        el.className = 'nx-toast nx-toast-' + type;
        el.innerHTML = '<i class="fa-solid ' + (iconByType[type] || iconByType.info) + '"></i><span></span>';
        el.querySelector('span').textContent = message;
        container.appendChild(el);
        window.nxSfx.toast();
        requestAnimationFrame(function () { el.classList.add('nx-toast-visible'); });
        setTimeout(function () {
            el.classList.remove('nx-toast-visible');
            setTimeout(function () { el.remove(); }, 300);
        }, duration);
    };
    var flashData = document.getElementById('nx-flash-data');
    if (flashData) {
        var msg = flashData.getAttribute('data-msg');
        var flashType = flashData.getAttribute('data-type') || 'info';
        if (msg) window.nxToast(msg, flashType);
    }

    /* ── Counter animation for KPI values (runs once on load) ────── */
    function animateCounter(el) {
        var target = parseFloat(el.getAttribute('data-count'));
        if (isNaN(target)) return;
        var duration = 900, start = performance.now();
        var easeOut = function (t) { return 1 - Math.pow(1 - t, 3); };
        function step(now) {
            var p = Math.min((now - start) / duration, 1);
            el.textContent = Math.round(target * easeOut(p));
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }
    document.querySelectorAll('[data-count]').forEach(animateCounter);

    /* ── Scroll reveal — observer disconnects per element after firing ── */
    if ('IntersectionObserver' in window) {
        var revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry, i) {
                if (entry.isIntersecting) {
                    entry.target.style.transitionDelay = (i * 30) + 'ms';
                    entry.target.classList.add('nx-revealed');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
        document.querySelectorAll('.nx-reveal').forEach(function (el) { revealObserver.observe(el); });
    }
})();
