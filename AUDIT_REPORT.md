# Rapport d'Audit – Gestion des Stagiaires IPIRNET
**Date :** 2026-07-09  
**Périmètre :** Tous les fichiers `.php`, `.js`, `.css` et le schéma SQL  
**Catégories :** Critique · Élevé · Moyen · Faible

---

## 1. Sécurité

### 🔴 CRITIQUE — Messages d'exception bruts exposés dans les réponses JSON
**Fichiers :** `gestion_classes.php` (lignes ~92, ~118, ~149, ~208) · `gestion_modules.php` (lignes ~96, ~137)  
**Problème :** Les blocs `catch` retournaient `'Erreur : ' . $e->getMessage()` directement dans les réponses JSON. Cela peut exposer des noms de tables, colonnes, contraintes et autres informations internes de la base de données à l'utilisateur.  
**Correction appliquée :** Remplacement par un message générique (`'Une erreur est survenue. Veuillez réessayer.'`) et journalisation complète via `error_log()`.

### 🔴 CRITIQUE — Message d'exception brut dans un flash message (inscription groupée)
**Fichier :** `demandes_inscription.php` (ligne ~604)  
**Problème :** Le bloc `catch` du handler `bulk_accepter` appelait `flash_set('Erreur lors de l\'inscription groupée : ' . $e->getMessage())`, exposant le message d'erreur interne à l'utilisateur.  
**Correction appliquée :** Message générique + `error_log()`.

### 🟡 ÉLEVÉ — Absence de vérification de capacité sur l'acceptation individuelle
**Fichier :** `demandes_inscription.php` (autour de la ligne 360)  
**Problème :** Lors de l'acceptation individuelle d'une pré-inscription, le code ne vérifiait pas la capacité (`classes.capacite`) avant d'insérer le stagiaire. Un directeur pouvait inscrire un élève dans une classe déjà pleine.  
**Correction appliquée :** Ajout d'une vérification `places_libres = capacite - effectif` avant l'INSERT, avec rollback et message d'erreur si la classe est pleine.

### 🟢 FAIBLE — Utilisation de `$e->getMessage()` dans `stagiaires.php`
**Fichier :** `stagiaires.php` (lignes ~331–389)  
**Statut :** ✅ Non exposé — le message n'est utilisé qu'en interne pour détecter les codes d'erreur spécifiques (`1062`, `Duplicate entry`, `cin`, `email`) afin d'afficher un message utilisateur approprié. Le message brut n'est jamais envoyé au navigateur.

---

## 2. Bugs et Code Incorrect

### 🟡 ÉLEVÉ — Vérification `id_module` côté serveur (absences)
**Fichier :** `absences.php` (lignes ~143–150)  
**Statut :** ✅ Déjà correct — le serveur retourne `'Le module est obligatoire.'` si `$idModule <= 0`. Le HTML utilise `required` sur le `<select>` du module.

### 🟡 ÉLEVÉ — `sexe` obligatoire dans le formulaire stagiaire
**Fichier :** `stagiaires.php`  
**Statut :** ✅ Conforme — `sexe` est un champ `NOT NULL` dans la DB, le formulaire d'ajout inclut le champ `sexe`, et lors de la conversion pré-inscription → stagiaire, `sexe` est copié avec fallback `'M'`.

### 🟢 FAIBLE — Valeurs de tarifs en dur
**Fichier :** `stagiaires.php` et `print_recu_paiement.php`  
**Problème :** Les tarifs mensuels sont hardcodés (ex. `2 => 700.0`) associés à des IDs de filières. Si les tarifs ou les IDs changent, le code doit être modifié manuellement.  
**Recommandation :** Ajouter une colonne `tarif_mensuel DECIMAL(8,2)` dans la table `filieres` pour centraliser cette configuration.

### 🟢 FAIBLE — Formule de note dans `bulletins.php`
**Fichier :** `bulletins.php`  
**Statut :** ✅ La formule `(avg_controles × 0.4) + (theorique × 0.3) + (pratique × 0.3)` est calculée via la vue `v_moyennes_par_module` en SQL, ce qui garantit la cohérence. La page `notes.php` ne recalcule pas elle-même la moyenne.

---

## 3. Violations des Règles Métier

### 🟡 ÉLEVÉ — Contrainte UNIQUE sur les stages
**Fichier :** `gestion_des_stagiaires.sql`  
**Statut :** ✅ La contrainte `UNIQUE KEY uq_stage_per_year (id_stagiaire, type_stage, annee_scolaire)` est bien présente dans le schéma SQL. Le code PHP vérifie également ce doublon avant insertion.

### 🟡 ÉLEVÉ — Remise mensuelle dans les exports CSV de rapports
**Fichier :** `rapports.php`  
**Statut :** ✅ Conforme — les requêtes SQL utilisent `GREATEST(0, mn.montant_total - COALESCE(mn.remise, COALESCE(s.remise_mensuelle, 0)))` pour calculer les montants dus, respectant bien la logique de remise.

---

## 4. Performance

### 🟡 MOYEN — Index manquant sur `absences.date_absence`
**Fichier :** `gestion_des_stagiaires.sql`  
**Problème :** La colonne `date_absence` est fréquemment utilisée dans des clauses `WHERE` et `ORDER BY` (rapports, absences par période) sans index.  
**Correction appliquée :** Ajout de `ADD KEY idx_absences_date (date_absence)` dans le schéma SQL.

### 🟢 FAIBLE — Index existants (vérifiés comme corrects)
- `mensualites` : `idx_mensualites_mois (mois_ref, est_paye)` ✅
- `stagiaires` : `uk_stagiaires_num_inscri`, `uk_stagiaires_email` ✅
- `classes` : `idx_classes_filiere_annee (id_filiere, annee_scolaire)` ✅
- `stages` : `uq_stage_per_year` ✅

### 🟡 MOYEN — Requêtes N+1 potentielles dans `bulletins.php`
**Fichier :** `bulletins.php`  
**Problème :** Le calcul des rangs et moyennes générales est effectué en PHP sur les données retournées, ce qui peut induire des passes multiples sur les données.  
**Recommandation :** Utiliser `RANK() OVER (ORDER BY moy_gen DESC)` (fenêtre SQL) dans la vue `v_moyennes_par_module` ou dans la requête principale pour déléguer le classement à MariaDB.

---

## 5. Commentaires Français et Documentation

### 🟢 FAIBLE — Docblocks présents sur les fichiers principaux
**Statut :** ✅ Les fichiers `auth.php`, `helpers.php`, `bootstrap.php`, `db.php`, `gestion_modules.php`, `bulletins.php` ont des docblocks Français complets en tête et sur chaque fonction.

### 🟢 FAIBLE — Commentaires manquants dans `stagiaires.php`
**Fichier :** `stagiaires.php`  
**Problème :** Certains blocs de logique complexe (génération de JS inline, gestion des onglets du hub) n'ont pas de commentaires inline en Français.  
**Recommandation :** Ajouter des `// ── Section ... ──` et des commentaires sur les blocs non-évidents.

---

## 6. Cohérence et Style

### 🟢 FAIBLE — `print_billet_excuse.php` : footer inline
**Fichier :** `print_billet_excuse.php`  
**Problème :** Le pied de page est défini inline (`<div class="cs-footer">`) plutôt que via `include print_footer.php`. Cependant, `print_footer.php` requiert les variables `$SCHOOL_ADDRESS` et `$SCHOOL_LEGAL` non définies dans ce fichier, donc l'inline est intentionnel mais non documenté.  
**Recommandation :** Définir `$SCHOOL_ADDRESS` et `$SCHOOL_LEGAL` dans ce fichier et utiliser `require __DIR__ . '/includes/print_footer.php'` pour la cohérence.

### ✅ Conforme — Utilisation de `h()` pour l'échappement HTML
Tous les fichiers analysés utilisent systématiquement `h()` pour l'affichage des données utilisateur. Aucun oubli d'échappement détecté.

### ✅ Conforme — Utilisation de `flash_set()` / `flash_get()`
Les messages flash sont gérés uniformément via ces helpers.

### ✅ Conforme — En-tête/pied de page partagés
Toutes les pages utilisent `require __DIR__ . '/includes/header.php'` et `require __DIR__ . '/includes/footer.php'`.

### ✅ Conforme — Pages d'impression
Les fichiers `print_*.php` incluent `print_letterhead.php` et définissent les constantes requises (`$SCHOOL_ORG`, `$SCHOOL_TAGLINE_1`, etc.).

---

## Résumé des Corrections Appliquées

| # | Sévérité | Fichier | Description | Statut |
|---|----------|---------|-------------|--------|
| 1 | 🔴 Critique | `gestion_classes.php` | 4× `$e->getMessage()` exposé dans JSON | ✅ Corrigé |
| 2 | 🔴 Critique | `gestion_modules.php` | 2× `$e->getMessage()` exposé dans JSON | ✅ Corrigé |
| 3 | 🔴 Critique | `demandes_inscription.php` | `$e->getMessage()` dans flash message | ✅ Corrigé |
| 4 | 🟡 Élevé | `demandes_inscription.php` | Pas de vérification capacité sur acceptation simple | ✅ Corrigé |
| 5 | 🟡 Moyen | `gestion_des_stagiaires.sql` | Index manquant sur `absences.date_absence` | ✅ Corrigé |

---

## Recommandations Non Implémentées (à faire manuellement)

| # | Sévérité | Fichier | Recommandation |
|---|----------|---------|----------------|
| 6 | 🟢 Faible | `filieres` table | Ajouter `tarif_mensuel DECIMAL(8,2)` pour éviter les tarifs hardcodés |
| 7 | 🟢 Faible | `print_billet_excuse.php` | Définir `$SCHOOL_ADDRESS`/`$SCHOOL_LEGAL` et utiliser `print_footer.php` |
| 8 | 🟡 Moyen | `bulletins.php` | Utiliser `RANK() OVER` SQL pour le classement au lieu du PHP |
| 9 | 🟢 Faible | `stagiaires.php` | Enrichir les commentaires inline Français |
