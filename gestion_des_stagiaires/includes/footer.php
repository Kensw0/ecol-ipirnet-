<?php
declare(strict_types=1);
$isPublic = !empty($isPublic);
if ($isPublic): ?>
    </main>
    <footer class="public-shell__footer">
        Application de démonstration — données fictives. Connexion sécurisée à prévoir en production (CDC §2.2).
    </footer>
</div>
<?php else: ?>
    <footer class="gds-admin-footer no-print">
        <p>Application de démonstration — données fictives. Connexion sécurisée à prévoir en production (CDC §2.2).</p>
    </footer>
        </div><!-- .page-container -->
    </main>
</div><!-- .admin-layout -->
<script>
(function () {
    var o = document.getElementById('mouse-lighting-overlay');
    if (!o) return;
    document.addEventListener('mousemove', function (e) {
        document.documentElement.style.setProperty('--mouse-x', e.clientX + 'px');
        document.documentElement.style.setProperty('--mouse-y', e.clientY + 'px');
    }, { passive: true });
})();
</script>
<?php endif; ?>
</body>
</html>
