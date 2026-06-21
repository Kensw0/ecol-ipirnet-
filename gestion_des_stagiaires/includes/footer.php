<?php
declare(strict_types=1);
$isPublic = !empty($isPublic);
if ($isPublic): ?>
    </main>
    <footer class="public-shell__footer" style="text-align:center; padding:1.5rem; color:rgba(255,255,255,0.3); font-size:0.85rem; border-top:1px solid rgba(255,255,255,0.05); margin-top:3rem;">
        &copy; 2026 IPIRNET — Groupe de formation professionnelle
    </footer>
</div>
<?php else: ?>
    <footer class="gds-admin-footer no-print">
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

<!-- Global GDS Confirmation Modal -->
<div id="gds-confirm-modal" class="modal-overlay" style="display:none; z-index:3000;">
    <div class="modal-card" style="max-width:400px; animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);">
        <div class="modal-header">
            <h2 style="font-family:'Inter', sans-serif; font-size:1.1rem;">Confirmation requise</h2>
            <button class="icon-btn" onclick="closeGdsConfirm()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="text-align:center; padding:2rem 1.5rem;">
            <div style="width:60px; height:60px; border-radius:50%; background:rgba(239,68,68,0.1); color:#ef4444; display:flex; align-items:center; justify-content:center; font-size:1.8rem; margin:0 auto 1.5rem;">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <p id="gds-confirm-text" style="font-size:1.05rem; line-height:1.5; margin:0; color:#e4e4e7;">Êtes-vous sûr ?</p>
        </div>
        <div class="modal-footer" style="background:rgba(0,0,0,0.2); justify-content:center; padding:1.25rem;">
            <button type="button" id="gds-confirm-btn" class="btn" style="background:#ef4444; color:#fff; flex:1; border-radius:8px;">Confirmer</button>
            <button type="button" class="btn secondary" onclick="closeGdsConfirm()" style="flex:1; border-radius:8px;">Annuler</button>
        </div>
    </div>
</div>

<script>
let gdsConfirmCallback = null;
function showGdsConfirm(text, callback) {
    document.getElementById('gds-confirm-text').innerText = text;
    gdsConfirmCallback = callback;
    document.getElementById('gds-confirm-modal').style.display = 'flex';
}
function closeGdsConfirm() {
    document.getElementById('gds-confirm-modal').style.display = 'none';
    gdsConfirmCallback = null;
}
document.getElementById('gds-confirm-btn').addEventListener('click', function() {
    if (gdsConfirmCallback) gdsConfirmCallback();
    closeGdsConfirm();
});
// Global interceptor for all forms with data-confirm-custom
document.addEventListener('submit', function(e) {
    if (e.target.hasAttribute('data-confirm-custom')) {
        const form = e.target;
        if (!form.dataset.confirmed) {
            e.preventDefault();
            const msg = form.getAttribute('data-confirm-msg') || "Confirmer l'opération ?";
            showGdsConfirm(msg, function() {
                form.dataset.confirmed = "true";
                form.submit();
            });
        }
    }
});
</script>
<?php endif; ?>
</body>
</html>
