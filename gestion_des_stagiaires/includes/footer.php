<?php
declare(strict_types=1);

// ============================================================
//  footer.php — Pied de page HTML commun à toutes les pages
//
//  Doit être inclus en fin de page, après tout le contenu.
//  Ferme les conteneurs ouverts dans header.php et injecte :
//    - Mode public  : pied de page simple + fermeture du shell
//    - Mode admin   : effet de lumière souris, modale de confirmation
//                     globale, intercepteur de formulaires "data-confirm"
// ============================================================

$isPublic = !empty($isPublic);

if ($isPublic): ?>
<!-- ============================================================
     BRANCHE PUBLIC — Fermeture du shell de pré-inscription
     ============================================================ -->
    </main><!-- .public-shell__main -->
    <footer class="public-shell__footer"
            style="text-align:center; padding:1.5rem; color:rgba(255,255,255,0.3); font-size:0.85rem; border-top:1px solid rgba(255,255,255,0.05); margin-top:3rem;">
        &copy; 2026 IPIRNET — Groupe de formation professionnelle
    </footer>
</div><!-- .public-shell -->

<?php else: ?>
<!-- ============================================================
     BRANCHE ADMIN — Fermeture du layout + scripts interactifs
     ============================================================ -->
    <footer class="gds-admin-footer no-print"></footer>
        </div><!-- .page-container -->
    </main><!-- .main-content -->
</div><!-- .admin-layout -->

<script>
// ── Effet de lumière suivant le curseur ───────────────────────────────────
// Met à jour deux variables CSS (--mouse-x, --mouse-y) au mouvement de la
// souris. L'overlay #mouse-lighting-overlay les utilise via un radial-gradient
// dans app.css pour simuler un éclairage ambiant dynamique.
(function () {
    var overlay = document.getElementById('mouse-lighting-overlay');
    if (!overlay) return;
    document.addEventListener('mousemove', function (e) {
        document.documentElement.style.setProperty('--mouse-x', e.clientX + 'px');
        document.documentElement.style.setProperty('--mouse-y', e.clientY + 'px');
    }, { passive: true }); // passive:true = ne bloque pas le scroll
})();
</script>

<!-- ── Modale de confirmation globale ────────────────────────────────────
     Remplace les window.confirm() natifs du navigateur.
     Utilisée via showGdsConfirm(texte, callback) depuis n'importe quelle page.
     Les formulaires avec l'attribut data-confirm-custom sont interceptés
     automatiquement par le listener submit ci-dessous.
     ──────────────────────────────────────────────────────────────────── -->
<div id="gds-confirm-modal" class="modal-overlay" style="display:none; z-index:3000;">
    <div class="modal-card" style="max-width:400px; animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);">
        <div class="modal-header">
            <h2 style="font-family:'Inter', sans-serif; font-size:1.1rem;">Confirmation requise</h2>
            <button class="icon-btn" onclick="closeGdsConfirm()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" style="text-align:center; padding:2rem 1.5rem;">
            <div style="width:60px; height:60px; border-radius:50%; background:rgba(239,68,68,0.1); color:#ef4444; display:flex; align-items:center; justify-content:center; font-size:1.8rem; margin:0 auto 1.5rem;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <!-- Texte dynamique injecté par showGdsConfirm() -->
            <p id="gds-confirm-text" style="font-size:1.05rem; line-height:1.5; margin:0; color:#e4e4e7;">Êtes-vous sûr ?</p>
        </div>
        <div class="modal-footer" style="background:rgba(0,0,0,0.2); justify-content:center; padding:1.25rem;">
            <button type="button" id="gds-confirm-btn" class="btn"
                    style="background:#ef4444; color:#fff; flex:1; border-radius:8px;">Confirmer</button>
            <button type="button" class="btn secondary"
                    onclick="closeGdsConfirm()"
                    style="flex:1; border-radius:8px;">Annuler</button>
        </div>
    </div>
</div>

<script>
// ── API de la modale de confirmation ─────────────────────────────────────

/** Callback stocké entre showGdsConfirm() et le clic sur "Confirmer". */
let gdsConfirmCallback = null;

/**
 * Affiche la modale avec un message personnalisé.
 * @param {string}   text     Texte de la question posée à l'utilisateur.
 * @param {Function} callback Fonction appelée si l'utilisateur confirme.
 */
function showGdsConfirm(text, callback) {
    document.getElementById('gds-confirm-text').innerText = text;
    gdsConfirmCallback = callback;
    document.getElementById('gds-confirm-modal').style.display = 'flex';
}

/** Ferme la modale et annule le callback en attente. */
function closeGdsConfirm() {
    document.getElementById('gds-confirm-modal').style.display = 'none';
    gdsConfirmCallback = null;
}

// Bouton "Confirmer" : exécute le callback puis ferme la modale.
document.getElementById('gds-confirm-btn').addEventListener('click', function () {
    if (gdsConfirmCallback) gdsConfirmCallback();
    closeGdsConfirm();
});

// ── Intercepteur global des formulaires avec confirmation ─────────────────
// Tout formulaire portant l'attribut data-confirm-custom est intercepté
// au moment du submit. La soumission réelle n'a lieu qu'après confirmation
// via la modale (le flag data-confirmed évite la boucle infinie).
document.addEventListener('submit', function (e) {
    if (e.target.hasAttribute('data-confirm-custom')) {
        const formulaire = e.target;
        if (!formulaire.dataset.confirmed) {
            e.preventDefault();
            const message = formulaire.getAttribute('data-confirm-msg') || "Confirmer l'opération ?";
            showGdsConfirm(message, function () {
                formulaire.dataset.confirmed = 'true';
                formulaire.submit();
            });
        }
    }
});
</script>
<?php endif; ?>
</body>
</html>
