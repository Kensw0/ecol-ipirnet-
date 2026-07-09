/* ============================================================
   NEXUS.TERMINAL — full sensory layer
   Matrix rain, CRT boot sequence, glitch cursor, terminal SFX
   (synthesized via WebAudio — no external files), page-transition
   wipe, sidebar collapse, toasts, KPI counters, scroll reveal.
   ============================================================ */
(function () {
    'use strict';

    /* ── WebAudio SFX (synthesized, no assets needed) ────────────── */
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
        hover: function () { beep(1400, 0.03, 'square', 0.02); },
        click: function () { beep(700, 0.05, 'square', 0.05); setTimeout(function () { beep(1100, 0.04, 'square', 0.03); }, 40); },
        toast: function () { beep(880, 0.08, 'sine', 0.04); },
        boot:  function () { beep(220, 0.12, 'sawtooth', 0.03); }
    };

    var firstInteraction = function () {
        ctx();
        window.removeEventListener('pointerdown', firstInteraction);
        window.removeEventListener('keydown', firstInteraction);
    };
    window.addEventListener('pointerdown', firstInteraction);
    window.addEventListener('keydown', firstInteraction);

    /* Bind hover/click sfx to interactive elements */
    document.addEventListener('mouseover', function (e) {
        if (e.target.closest && e.target.closest('a, button, .nav-item, .btn')) window.nxSfx.hover();
    });
    document.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('a, button, .nav-item, .btn')) window.nxSfx.click();
    });

    /* ── Boot sequence typed text ─────────────────────────────────── */
    var bootEl = document.getElementById('nx-boot-lines');
    if (bootEl) {
        var bootScript = [
            '> BOOTING IPIRNET_ADMIN_PORTAL...',
            '> AUTH TOKEN....... OK',
            '> LOADING MODULES.... OK',
            '> RENDER ENGINE..... NEXUS.TERMINAL v2',
            '> WELCOME, OPERATOR.'
        ];
        var flat = bootScript.join('\n');
        var i = 0;
        window.nxSfx.boot();
        var typer = setInterval(function () {
            bootEl.textContent = flat.slice(0, i);
            i += 3;
            if (i > flat.length) clearInterval(typer);
        }, 8);
    }

    /* ── Page-transition wipe on internal link clicks ────────────── */
    var wipe = document.createElement('div');
    wipe.id = 'nx-page-wipe';
    document.body.appendChild(wipe);
    document.addEventListener('click', function (e) {
        var link = e.target.closest && e.target.closest('a[href]');
        if (!link) return;
        var href = link.getAttribute('href') || '';
        if (href.startsWith('#') || href.startsWith('javascript:') || link.target === '_blank' || e.metaKey || e.ctrlKey) return;
        if (link.hostname && link.hostname !== window.location.hostname) return;
        e.preventDefault();
        wipe.classList.add('nx-wipe-active');
        setTimeout(function () { window.location.href = href; }, 260);
    });

    /* ── Custom crosshair cursor — rAF-batched, transform-only (no layout) ── */
    var crosshair = document.getElementById('nx-crosshair');
    if (crosshair && matchMedia('(pointer: fine)').matches) {
        var mx = -100, my = -100, cx = -100, cy = -100;
        document.addEventListener('mousemove', function (e) {
            mx = e.clientX; my = e.clientY;
        }, { passive: true });
        document.addEventListener('mouseover', function (e) {
            if (e.target.closest && e.target.closest('a, button, .nav-item, .btn')) crosshair.classList.add('nx-hover');
        });
        document.addEventListener('mouseout', function (e) {
            if (e.target.closest && e.target.closest('a, button, .nav-item, .btn')) crosshair.classList.remove('nx-hover');
        });
        document.addEventListener('mousedown', function () { crosshair.classList.add('nx-click'); });
        document.addEventListener('mouseup', function () { crosshair.classList.remove('nx-click'); });
        (function loop() {
            cx = mx; cy = my;
            crosshair.style.transform = 'translate3d(' + cx + 'px,' + cy + 'px,0) translate(-50%,-50%)';
            requestAnimationFrame(loop);
        })();
    } else if (crosshair) {
        crosshair.style.display = 'none';
    }

    /* ── Matrix rain canvas — throttled + low-res render, paused when tab hidden ── */
    var canvas = document.getElementById('nx-matrix');
    if (canvas && canvas.getContext && matchMedia('(prefers-reduced-motion: no-preference)').matches) {
        var mctx = canvas.getContext('2d', { alpha: false });
        var chars = 'アイウエオカキクケコサシスセソ01<>/{}[]=+-*'.split('');
        var fontSize = 16;
        var scale = 0.6; // render at reduced resolution, upscale via CSS for perf
        var columns, drops;

        function resize() {
            var w = Math.ceil(window.innerWidth * scale);
            var h = Math.ceil(window.innerHeight * scale);
            canvas.width = w;
            canvas.height = h;
            canvas.style.width = '100vw';
            canvas.style.height = '100vh';
            columns = Math.floor(w / fontSize);
            drops = new Array(columns).fill(1).map(function () { return Math.random() * -40; });
        }
        resize();
        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(resize, 200);
        });

        var running = true;
        document.addEventListener('visibilitychange', function () {
            running = document.visibilityState === 'visible';
        });

        function draw() {
            if (running) {
                mctx.fillStyle = 'rgba(5,8,6,0.14)';
                mctx.fillRect(0, 0, canvas.width, canvas.height);
                mctx.font = fontSize + 'px monospace';
                for (var i = 0; i < columns; i += 2) { // update every other column per tick
                    var text = chars[(Math.random() * chars.length) | 0];
                    mctx.fillStyle = Math.random() > 0.96 ? '#00fff2' : '#39ff14';
                    mctx.fillText(text, i * fontSize, drops[i] * fontSize);
                    if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) drops[i] = 0;
                    drops[i]++;
                }
            }
            setTimeout(function () { requestAnimationFrame(draw); }, 90); // ~11fps, plenty for ambient effect
        }
        requestAnimationFrame(draw);
    } else if (canvas) {
        canvas.style.display = 'none';
    }

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

    /* ── Glitch attribute auto-tag for headings ───────────────────── */
    document.querySelectorAll('h1, .page-title-dash').forEach(function (el) {
        if (!el.hasAttribute('data-glitch')) el.setAttribute('data-glitch', el.textContent.trim());
    });

    /* ── Live clock in topbar ────────────────────────────────────── */
    var clockEl = document.getElementById('nx-clock');
    if (clockEl) {
        var tick = function () {
            var now = new Date();
            var hh = String(now.getHours()).padStart(2, '0');
            var mm = String(now.getMinutes()).padStart(2, '0');
            var ss = String(now.getSeconds()).padStart(2, '0');
            clockEl.textContent = hh + ':' + mm + ':' + ss;
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

    /* ── Counter animation for KPI values ─────────────────────────── */
    function animateCounter(el) {
        var target = parseFloat(el.getAttribute('data-count'));
        if (isNaN(target)) return;
        var duration = 1000, start = performance.now();
        var easeOut = function (t) { return 1 - Math.pow(1 - t, 3); };
        function step(now) {
            var p = Math.min((now - start) / duration, 1);
            el.textContent = Math.round(target * easeOut(p));
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }
    document.querySelectorAll('[data-count]').forEach(animateCounter);

    /* ── Scroll reveal ─────────────────────────────────────────────── */
    if ('IntersectionObserver' in window) {
        var revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry, i) {
                if (entry.isIntersecting) {
                    entry.target.style.transitionDelay = (i * 35) + 'ms';
                    entry.target.classList.add('nx-revealed');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
        document.querySelectorAll('.nx-reveal').forEach(function (el) { revealObserver.observe(el); });
    }
})();
