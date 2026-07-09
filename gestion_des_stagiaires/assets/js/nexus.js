/* ============================================================
   NEXUS — Foundation motion layer (IPIRNET Admin Portal)
   Sidebar collapse, toasts, counter animation, scroll reveal,
   smooth mouse lighting.
   ============================================================ */
(function () {
    'use strict';

    /* ── Sidebar collapse toggle ─────────────────────────────── */
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
        // Populate data-tooltip from the link's visible label for collapsed mode.
        document.querySelectorAll('.sidebar .nav-item').forEach(function (el) {
            var span = el.querySelector('span');
            if (span && !el.hasAttribute('data-tooltip')) {
                el.setAttribute('data-tooltip', span.textContent.trim());
            }
        });
    }

    /* ── Live clock in topbar ────────────────────────────────── */
    var clockEl = document.getElementById('nx-clock');
    if (clockEl) {
        var tick = function () {
            var now = new Date();
            var hh = String(now.getHours()).padStart(2, '0');
            var mm = String(now.getMinutes()).padStart(2, '0');
            clockEl.textContent = hh + ':' + mm;
        };
        tick();
        setInterval(tick, 30000);
    }

    /* ── Toast system ─────────────────────────────────────────── */
    function ensureToastContainer() {
        var c = document.getElementById('nx-toast-container');
        if (!c) {
            c = document.createElement('div');
            c.id = 'nx-toast-container';
            document.body.appendChild(c);
        }
        return c;
    }

    var iconByType = {
        success: 'fa-circle-check',
        error: 'fa-circle-xmark',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info'
    };

    window.nxToast = function (message, type, duration) {
        type = type || 'info';
        duration = duration || 4200;
        var container = ensureToastContainer();
        var el = document.createElement('div');
        el.className = 'nx-toast nx-toast-' + type;
        var icon = iconByType[type] || iconByType.info;
        el.innerHTML = '<i class="fa-solid ' + icon + '"></i><span></span>';
        el.querySelector('span').textContent = message;
        container.appendChild(el);
        requestAnimationFrame(function () { el.classList.add('nx-toast-visible'); });
        setTimeout(function () {
            el.classList.remove('nx-toast-visible');
            setTimeout(function () { el.remove(); }, 300);
        }, duration);
    };

    // Auto-toast the PHP flash message, if present.
    var flashData = document.getElementById('nx-flash-data');
    if (flashData) {
        var msg = flashData.getAttribute('data-msg');
        var flashType = flashData.getAttribute('data-type') || 'info';
        if (msg) window.nxToast(msg, flashType);
    }

    /* ── Counter animation for KPI values ────────────────────── */
    function animateCounter(el) {
        var target = parseFloat(el.getAttribute('data-count'));
        if (isNaN(target)) return;
        var duration = 1100;
        var start = performance.now();
        var easeOut = function (t) { return 1 - Math.pow(1 - t, 3); };
        function step(now) {
            var p = Math.min((now - start) / duration, 1);
            el.textContent = Math.round(target * easeOut(p));
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }
    document.querySelectorAll('[data-count]').forEach(animateCounter);

    /* ── Scroll reveal ────────────────────────────────────────── */
    if ('IntersectionObserver' in window) {
        var revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry, i) {
                if (entry.isIntersecting) {
                    entry.target.style.transitionDelay = (i * 40) + 'ms';
                    entry.target.classList.add('nx-revealed');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
        document.querySelectorAll('.nx-reveal').forEach(function (el) { revealObserver.observe(el); });
    }

    /* ── Smooth mouse lighting (lerp) ─────────────────────────── */
    var overlay = document.getElementById('mouse-lighting-overlay');
    if (overlay) {
        var targetX = 0, targetY = 0, currentX = 0, currentY = 0;
        var lerp = function (a, b, t) { return a + (b - a) * t; };
        document.addEventListener('mousemove', function (e) {
            targetX = e.clientX; targetY = e.clientY;
        }, { passive: true });
        (function animate() {
            currentX = lerp(currentX, targetX, 0.08);
            currentY = lerp(currentY, targetY, 0.08);
            document.documentElement.style.setProperty('--mouse-x', currentX + 'px');
            document.documentElement.style.setProperty('--mouse-y', currentY + 'px');
            requestAnimationFrame(animate);
        })();
    }
})();
