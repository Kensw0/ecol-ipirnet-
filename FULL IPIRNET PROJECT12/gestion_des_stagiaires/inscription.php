<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$isPublic = true;
$curPage = 'inscription';

$isSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim((string) ($_POST['nom'] ?? ''));
    $prenom = trim((string) ($_POST['prenom'] ?? ''));
    $cin = trim((string) ($_POST['cin'] ?? ''));
    $dn = ($_POST['date_naissance'] ?? '') === '' ? null : (string) $_POST['date_naissance'];
    $adr = trim((string) ($_POST['adresse'] ?? ''));
    $em = trim((string) ($_POST['email'] ?? ''));
    $tel = trim((string) ($_POST['telephone'] ?? ''));
    $telp = trim((string) ($_POST['telephone_parent'] ?? ''));
    $tuteur = trim((string) ($_POST['nom_tuteur'] ?? ''));
    $cid = (int) ($_POST['id_classe'] ?? 0);
    if ($nom === '' || $prenom === '' || $cid <= 0) {
        flash_set('Nom, prénom et classe demandée sont obligatoires.');
        redirect('inscription.php');
    }
    
    $errs = [];
    if (preg_match('/[0-9]/', $nom) || preg_match('/[0-9]/', $prenom) || ($tuteur !== '' && preg_match('/[0-9]/', $tuteur))) {
        $errs[] = 'nom/prénom sans chiffres';
    }
    if ($cin !== '' && !preg_match('/^[a-zA-Z]{2}[0-9]{6}$/', $cin)) {
        $errs[] = 'CIN format 2 lettres + 6 chiffres (ex: WA123456)';
    }
    if (($tel !== '' && preg_match('/[a-zA-ZÀ-ÿ]/', $tel)) || ($telp !== '' && preg_match('/[a-zA-ZÀ-ÿ]/', $telp))) {
        $errs[] = 'téléphone sans lettres';
    }
    
    if ($errs) {
        flash_set('Erreur : ' . implode(', ', $errs) . '.');
        redirect('inscription.php');
    }
    $emNull = $em === '' ? null : $em;
    $pdo->prepare(
        'INSERT INTO demandes_inscription (cin, nom, prenom, date_naissance, adresse, email, telephone, telephone_parent, nom_tuteur, id_classe) VALUES (?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $cin === '' ? null : $cin,
        $nom,
        $prenom,
        $dn,
        $adr === '' ? null : $adr,
        $emNull,
        $tel === '' ? null : $tel,
        $telp === '' ? null : $telp,
        $tuteur === '' ? null : $tuteur,
        $cid,
    ]);
    // Flash set for internal routing, but we will catch it here directly
    flash_set('Demande envoyée. Elle sera examinée par l’administration.');
    $isSuccess = true;
}

// Trap the flash if it matches the success pattern on a GET load 
// (though our logic above sets it to true directly during POST too).
$f = flash_get();
if ($f && str_contains($f, 'Demande envoyée')) {
    $isSuccess = true;
    $f = null; // consume it so it doesn't show in the generic banner
}

$pageTitle = 'Candidature en ligne';
require __DIR__ . '/includes/header.php';

$filieres = $pdo->query('SELECT id_filiere, nom_filiere FROM filieres ORDER BY nom_filiere')->fetchAll();
$classes = $pdo->query("SELECT c.id_classe, c.nom_classe, c.annee_scolaire, f.id_filiere, f.nom_filiere FROM classes c JOIN filieres f ON f.id_filiere=c.id_filiere WHERE c.annee_scolaire = '1ère année' ORDER BY c.nom_classe")->fetchAll();
?>

<style>
/* Base public page structural overrides */
.glow-bg {
    position: absolute;
    top: 0; left: 50%; transform: translateX(-50%);
    width: 600px; height: 600px;
    background: radial-gradient(circle, rgba(168,85,247,0.1) 0%, transparent 70%);
    pointer-events: none;
    z-index: 0;
}

.candidature-container {
    position: relative;
    z-index: 10;
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

.hero-section {
    text-align: center;
    margin-bottom: 3rem;
}

.hero-logo {
    width: 80px; height: 80px;
    margin-bottom: 1.5rem;
    filter: drop-shadow(0 0 20px rgba(168,85,247,0.4));
}

.hero-title {
    font-size: 3.5rem;
    font-family: 'Instrument Serif', serif;
    font-weight: bold;
    color: #fff;
    margin-bottom: 1rem;
    line-height: 1;
}

.hero-subtitle {
    color: #a1a1aa;
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.5;
}

/* Progress Steps */
.progress-steps {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 3rem;
    gap: 1rem;
}
.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    color: #71717a;
    font-size: 0.85rem;
    font-weight: 500;
}
.step.active {
    color: #a855f7;
}
.step-dot {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    display: flex; align-items: center; justify-content: center;
    font-weight: bold;
}
.step.active .step-dot {
    background: rgba(168,85,247,0.15);
    border-color: #a855f7;
    color: #d8b4fe;
    box-shadow: 0 0 15px rgba(168,85,247,0.3);
}
.step-line {
    width: 40px; height: 2px;
    background: rgba(255,255,255,0.1);
    margin-bottom: 1.5rem;
}

/* Form Card */
.form-card {
    background: #1a1a2e;
    border-radius: 16px;
    border: 1px solid #3d2a6e;
    padding: 2.5rem;
    box-shadow: 0 20px 40px -10px rgba(0,0,0,0.5), 0 0 40px rgba(168,85,247,0.05);
}

.section-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    color: #fff;
    margin-bottom: 1.5rem;
    padding-left: 1rem;
    border-left: 4px solid #a855f7;
    font-weight: 600;
}

/* Form Layout */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem 1.5rem;
    margin-bottom: 2.5rem;
}
.form-col-full {
    grid-column: span 2;
}

@media (max-width: 650px) {
    .form-grid { grid-template-columns: 1fr; }
    .form-col-full { grid-column: span 1; }
    .progress-steps { flex-direction: column; gap:0.5rem; }
    .step-line { display: none; }
}

.input-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.input-group label {
    font-size: 0.85rem;
    color: #d4d4d8;
    font-weight: 500;
}

.input-box {
    position: relative;
    display: flex;
    align-items: center;
}

.saas-input {
    width: 100%;
    background: #0d0d1a !important;
    border: 1px solid rgba(255,255,255,0.1) !important;
    color: #fff !important;
    padding: 0.8rem 1rem !important;
    border-radius: 8px !important;
    font-size: 0.95rem !important;
    transition: all 0.2s ease !important;
    box-sizing: border-box !important;
    font-family: inherit !important;
    -webkit-text-fill-color: #fff !important;
}
.saas-input:focus {
    outline: none !important;
    border-color: #a855f7 !important;
    box-shadow: 0 0 0 3px rgba(168,85,247,0.2) !important;
}
.saas-input.valid {
    border-color: #10b981 !important;
}
.saas-input.invalid {
    border-color: #ef4444 !important;
}

.valid-icon {
    position: absolute; right: 1rem;
    color: #10b981;
    display: none;
}
.saas-input.valid + .valid-icon { display: block; }

.error-msg {
    color: #ef4444;
    font-size: 0.75rem;
    margin-top: 0.25rem;
    display: none;
}
.saas-input.invalid ~ .error-msg { display: block; }

.char-counter {
    color: #71717a;
    font-size: 0.75rem;
    text-align: right;
    margin-top: 0.25rem;
}

/* Auth Autofill Overrides */
.saas-input:-webkit-autofill,
.saas-input:-webkit-autofill:hover, 
.saas-input:-webkit-autofill:focus, 
.saas-input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 30px #0d0d1a inset !important;
    -webkit-text-fill-color: #fff !important;
    transition: background-color 5000s ease-in-out 0s;
}

.btn-submit {
    width: 100%;
    background: linear-gradient(90deg, #6c2bd9, #9c4dff);
    color: #fff;
    border: none;
    padding: 1rem;
    border-radius: 8px;
    font-size: 1.05rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
}
.btn-submit:hover {
    transform: translateY(-2px) scale(1.01);
    box-shadow: 0 15px 25px -5px rgba(156,77,255,0.4);
}
.btn-submit:active { transform: scale(0.98); }

.spinner {
    display: none;
    width: 18px; height: 18px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff; border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.btn-submit.loading .spinner { display: block; }
.btn-submit.loading span { display: none; }

/* Success Card */
.success-card {
    text-align: center;
    padding: 4rem 2rem;
    animation: popIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes popIn {
    from { opacity: 0; transform: scale(0.95) translateY(20px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.success-icon-wrap {
    width: 100px; height: 100px;
    background: rgba(16,185,129,0.1);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 2rem;
    color: #10b981;
    font-size: 3.5rem;
    box-shadow: 0 0 50px rgba(16,185,129,0.2);
    position: relative;
}
.success-icon-wrap::after {
    content: ''; position: absolute; inset: -10px;
    border: 2px dashed rgba(16,185,129,0.3); border-radius: 50%;
    animation: rotateDash 10s linear infinite;
}
@keyframes rotateDash { to { transform: rotate(360deg); } }

.success-title {
    font-size: 2rem;
    color: #fff;
    margin-bottom: 1rem;
    font-family: inherit;
}

.success-subtitle {
    color: #a1a1aa;
    font-size: 1.1rem;
    max-width: 400px;
    margin: 0 auto 2rem;
    line-height: 1.5;
}

.btn-home {
    background: transparent;
    color: #fff;
    border: 1px solid rgba(255,255,255,0.2);
    padding: 0.8rem 2rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: background 0.2s;
    display: inline-block;
}
.btn-home:hover { background: rgba(255,255,255,0.05); }

</style>

<div class="glow-bg"></div>

<div class="candidature-container">
    
    <div class="hero-section">
        <img src="assets/img/logo.png" alt="IPIRNET" class="hero-logo">
        <h1 class="hero-title">Rejoignez IPIRNET</h1>
        <p class="hero-subtitle">Remplissez le formulaire ci-dessous pour soumettre votre candidature. Votre dossier sera examiné par notre équipe.</p>
    </div>

    <!-- Progress Steps -->
    <div class="progress-steps">
        <div class="step active">
            <div class="step-dot">1</div>
            <div>Remplir le formulaire</div>
        </div>
        <div class="step-line"></div>
        <div class="step">
            <div class="step-dot">2</div>
            <div>Validation</div>
        </div>
        <div class="step-line"></div>
        <div class="step">
            <div class="step-dot">3</div>
            <div>Accès à l'espace</div>
        </div>
    </div>

    <div class="form-card">
        
        <?php if($f): ?><div class="msg" style="margin-bottom:2rem;"><?= h($f) ?></div><?php endif; ?>

        <?php if ($isSuccess): ?>
            
            <div class="success-card">
                <div class="success-icon-wrap">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h2 class="success-title">Votre candidature a été envoyée !</h2>
                <p class="success-subtitle">Notre équipe vous contactera après la validation de votre dossier par le secrétariat.</p>
                <a href="index.php" class="btn-home">Retour à l'accueil</a>
            </div>
            
        <?php else: ?>

            <form method="post" id="inscription-form" novalidate>
                
                <h3 class="section-header">👤 Informations personnelles</h3>
                
                <div class="form-grid">
                    <div class="input-group">
                        <label>Nom *</label>
                        <div class="input-box">
                            <input type="text" name="nom" class="saas-input dynamic-val" required pattern="^[^0-9]+$" data-error="Ce champ est requis (lettres uniquement)">
                            <i class="fa-solid fa-check valid-icon"></i>
                        </div>
                        <div class="error-msg"></div>
                    </div>
                    
                    <div class="input-group">
                        <label>Prénom *</label>
                        <div class="input-box">
                            <input type="text" name="prenom" class="saas-input dynamic-val" required pattern="^[^0-9]+$" data-error="Ce champ est requis (lettres uniquement)">
                            <i class="fa-solid fa-check valid-icon"></i>
                        </div>
                        <div class="error-msg"></div>
                    </div>

                    <div class="input-group">
                        <label>CIN</label>
                        <div class="input-box">
                            <input type="text" name="cin" id="cin-field" class="saas-input dynamic-val" pattern="^[A-Za-z]{2}[0-9]{6}$" style="text-transform:uppercase" data-error="Format invalide (ex: WA123456)" maxlength="8">
                            <i class="fa-solid fa-check valid-icon"></i>
                        </div>
                        <div class="error-msg"></div>
                        <div class="char-counter"><span id="cin-count">0</span> / 8</div>
                    </div>

                    <div class="input-group">
                        <label>Date de naissance</label>
                        <div class="input-box">
                            <input type="date" name="date_naissance" class="saas-input dynamic-val">
                            <i class="fa-solid fa-check valid-icon"></i>
                        </div>
                        <div class="error-msg"></div>
                    </div>

                    <div class="input-group">
                        <label>Téléphone stagiaire</label>
                        <div class="input-box">
                            <input type="tel" name="telephone" class="saas-input dynamic-val" pattern="^[0-9\s\+\-]+$" data-error="Format téléphone invalide">
                            <i class="fa-solid fa-check valid-icon"></i>
                        </div>
                        <div class="error-msg"></div>
                    </div>

                    <div class="input-group">
                        <label>Téléphone parent</label>
                        <div class="input-box">
                            <input type="tel" name="telephone_parent" class="saas-input dynamic-val" pattern="^[0-9\s\+\-]+$" data-error="Format téléphone invalide">
                            <i class="fa-solid fa-check valid-icon"></i>
                        </div>
                        <div class="error-msg"></div>
                    </div>

                    <div class="input-group form-col-full">
                        <label>Adresse</label>
                        <div class="input-box">
                            <input type="text" name="adresse" class="saas-input dynamic-val">
                            <i class="fa-solid fa-check valid-icon"></i>
                        </div>
                        <div class="error-msg"></div>
                    </div>

                    <div class="input-group form-col-full">
                        <label>Email</label>
                        <div class="input-box">
                            <input type="email" name="email" class="saas-input dynamic-val" data-error="Format email invalide">
                            <i class="fa-solid fa-check valid-icon"></i>
                        </div>
                        <div class="error-msg"></div>
                    </div>

                    <div class="input-group form-col-full">
                        <label>Nom complet du père / tuteur</label>
                        <div class="input-box">
                            <input type="text" name="nom_tuteur" class="saas-input dynamic-val" pattern="^[^0-9]+$" data-error="Le nom ne doit pas contenir de chiffres">
                            <i class="fa-solid fa-check valid-icon"></i>
                        </div>
                        <div class="error-msg"></div>
                    </div>
                </div>

                <h3 class="section-header">🎓 Scolarité</h3>
                
                <div class="form-grid">
                    <div class="input-group">
                        <label>Filière souhaitée *</label>
                        <div class="input-box">
                            <select id="form-filiere-select" class="saas-input dynamic-val" required data-error="Veuillez sélectionner une filière">
                                <option value="">— Choisir une filière —</option>
                                <?php foreach ($filieres as $f): ?>
                                    <option value="<?= (int) $f['id_filiere'] ?>"><?= h($f['nom_filiere']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-check valid-icon"></i>
                        </div>
                        <div class="error-msg"></div>
                    </div>

                    <div class="input-group">
                        <label>Classe (1ère année) *</label>
                        <div class="input-box">
                            <select name="id_classe" id="form-classe-select" class="saas-input dynamic-val" required data-error="Veuillez sélectionner une classe">
                                <option value="">— Choisir d'abord une filière —</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?= (int) $c['id_classe'] ?>" data-filiere="<?= (int) $c['id_filiere'] ?>">
                                        <?= h($c['nom_classe'] . ' — ' . $c['annee_scolaire']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-check valid-icon"></i>
                        </div>
                        <div class="error-msg"></div>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <div class="spinner"></div>
                    <span class="btn-text">Envoyer la demande</span>
                </button>

            </form>

        <?php endif; ?>

    </div>
</div>

<script>
(function () {
    // Dropdown linking logic
    var filiereSelect = document.getElementById('form-filiere-select');
    var classeSelect  = document.getElementById('form-classe-select');
    if (filiereSelect && classeSelect) {
        var allOptions = Array.from(classeSelect.querySelectorAll('option[data-filiere]'));
        function filterClasses() {
            var fid = filiereSelect.value;
            var currentVal = classeSelect.value;
            allOptions.forEach(function (opt) {
                var match = (fid === '' || opt.dataset.filiere === fid);
                opt.style.display = match ? '' : 'none';
                opt.disabled = !match;
            });
            if (currentVal && classeSelect.querySelector('option[value="' + currentVal + '"]')?.disabled) {
                classeSelect.value = '';
            }
        }
        filiereSelect.addEventListener('change', filterClasses);
        filterClasses();
    }

    // Inline UI Validation Logic
    const inputs = document.querySelectorAll('.dynamic-val');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        input.addEventListener('input', function() {
            if(this.classList.contains('invalid') || this.classList.contains('valid')) {
                validateField(this);
            }
        });
    });

    function validateField(field) {
        const errorMsg = field.dataset.error || "Champ invalide";
        const msgDiv = field.closest('.input-group').querySelector('.error-msg');
        
        let isValid = true;
        // Check HTML5 validity
        if (!field.checkValidity()) {
            isValid = false;
        } else if (field.hasAttribute('required') && field.value.trim() === '') {
            isValid = false;
        }

        if(field.value.trim() === '' && !field.hasAttribute('required')) {
            // Optional empty fields reset to default neutral state
            field.classList.remove('invalid', 'valid');
            if(msgDiv) msgDiv.style.display = 'none';
            return true;
        }

        if (isValid) {
            field.classList.remove('invalid');
            field.classList.add('valid');
            if(msgDiv) msgDiv.style.display = 'none';
        } else {
            field.classList.remove('valid');
            field.classList.add('invalid');
            if(msgDiv) {
                msgDiv.textContent = errorMsg;
                msgDiv.style.display = 'block';
            }
        }
        return isValid;
    }

    // Character Counter
    const cinField = document.getElementById('cin-field');
    const cinCount = document.getElementById('cin-count');
    if(cinField && cinCount) {
        cinField.addEventListener('input', function() {
            cinCount.textContent = this.value.length;
        });
    }

    // Loading State
    const form = document.getElementById('inscription-form');
    if(form) {
        form.addEventListener('submit', function(e) {
            let formValid = true;
            inputs.forEach(input => {
                if(!validateField(input)) formValid = false;
            });
            
            if(formValid) {
                const btn = document.getElementById('submitBtn');
                btn.classList.add('loading');
                btn.querySelector('.btn-text').textContent = 'Envoi en cours...';
            } else {
                e.preventDefault();
            }
        });
    }
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
