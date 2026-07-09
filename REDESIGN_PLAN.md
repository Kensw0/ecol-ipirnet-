# IPIRNET — Plan de Redesign Complet
## « NEXUS » — Full Visual & Motion Overhaul

---

## 1. Concept Créatif

### Nom : **NEXUS**
> *Le portail qui relie chaque stagiaire à son avenir professionnel.*

**Métaphore visuelle :** Un nœud de réseau — fils d'énergie électrique qui convergent vers un centre. Évoque la connexion, la précision, le flux d'information. Parfaitement adapté à un centre de formation IT/management.

**Ton :** Institutionnel mais vivant. Sombre, précis, ambitieux. Ni froid ni corporate — chaleureux dans ses micro-interactions, impressionnant dans ses transitions.

**Références :**
- Linear.app (rigueur + animation fluide)
- Stripe Dashboard (densité + élégance)
- MIT Media Lab (science + identité forte)
- Apple Intelligence UI (matière, profondeur, mouvement subtil)

---

## 2. Système de Couleurs

### Palette principale (CSS Custom Properties)

```css
:root {
  /* Fond */
  --bg-base:       #07070f;   /* Noir absolu avec teinte bleue */
  --bg-surface:    #0d0d1c;   /* Cartes et panneaux */
  --bg-elevated:   #12122a;   /* Modales, tooltips, surbrillance */
  --bg-hover:      #1a1a35;

  /* Accent primaire — Indigo électrique (remplace l'actuel violet) */
  --accent-100:    #e0e7ff;
  --accent-200:    #c7d2fe;
  --accent-400:    #818cf8;
  --accent-500:    #6366f1;   /* ← couleur principale */
  --accent-600:    #4f46e5;
  --accent-glow:   rgba(99, 102, 241, 0.35);

  /* Secondaire — Cyan (status live, graphiques) */
  --cyan-400:      #22d3ee;
  --cyan-500:      #06b6d4;
  --cyan-glow:     rgba(34, 211, 238, 0.25);

  /* Ambre — alertes, attention */
  --amber-400:     #fbbf24;
  --amber-500:     #f59e0b;

  /* Émeraude — succès, paiements */
  --emerald-400:   #34d399;
  --emerald-500:   #10b981;

  /* Rose — erreurs, absences */
  --rose-400:      #fb7185;
  --rose-500:      #f43f5e;

  /* Or — prestige, réussite (attestations, diplômes) */
  --gold-400:      #fcd34d;
  --gold-500:      #f59e0b;

  /* Texte */
  --text-primary:   #f1f5f9;
  --text-secondary: #94a3b8;
  --text-muted:     #475569;
  --text-disabled:  #334155;

  /* Bordures */
  --border-subtle:  rgba(255,255,255,0.04);
  --border-default: rgba(255,255,255,0.08);
  --border-strong:  rgba(255,255,255,0.16);
  --border-accent:  rgba(99,102,241,0.4);

  /* Effets */
  --blur-glass:    16px;
  --radius-card:   16px;
  --radius-sm:     8px;
  --radius-pill:   999px;

  /* Ombres */
  --shadow-card:   0 4px 24px rgba(0,0,0,0.4), 0 1px 4px rgba(0,0,0,0.3);
  --shadow-glow:   0 0 40px rgba(99,102,241,0.15);
  --shadow-float:  0 20px 60px rgba(0,0,0,0.6);
}
```

### Logique chromatique par module

| Module | Couleur dominante | Pourquoi |
|--------|-------------------|---------|
| Stagiaires | Indigo `--accent-500` | Identité principale |
| Notes | Cyan `--cyan-400` | Précision, données |
| Absences | Rose `--rose-400` | Alerte douce |
| Paiements | Émeraude `--emerald-400` | Finance/santé |
| Stages | Or `--gold-400` | Réussite, carrière |
| Modules | Violet `#a78bfa` | Académique |
| Classes | Bleu ciel `#38bdf8` | Organisation |
| Rapports | Ambre `--amber-400` | Analyse |

---

## 3. Typographie

### Paires de polices

```
Titres display   : "Cabinet Grotesk"  (ou "Clash Display" — modern grotesque)
Corps de texte   : "Inter Variable"   (0.4px tracking, weight 300–700)
Monospace/data   : "JetBrains Mono"   (chiffres, codes, tableaux de données)
Documents papier : "EB Garamond"      (attestations, certificats)
```

### Échelle typographique (rem)

```
display-xl   : 3.5rem / 4.25rem line-height / weight 800 / tracking -0.04em
display-lg   : 2.5rem / 3rem    / weight 700 / tracking -0.03em
heading-xl   : 2rem   / 2.5rem  / weight 700 / tracking -0.02em
heading-lg   : 1.5rem / 2rem    / weight 600
heading-md   : 1.25rem / 1.75rem / weight 600
body-lg      : 1rem   / 1.625rem / weight 400
body-md      : 0.875rem / 1.5rem / weight 400
body-sm      : 0.8rem  / 1.375rem / weight 400
caption      : 0.7rem  / 1.2rem  / weight 500 / uppercase + tracking 0.08em
mono-data    : 0.875rem / tabular-nums
```

---

## 4. Architecture Layout

### Nouveau shell admin

```
┌─────────────────────────────────────────────────┐
│  TOPBAR (64px)  — logo | breadcrumb | actions   │
├──────────┬──────────────────────────────────────┤
│          │  ┌──────────────────────────────────┐│
│ SIDEBAR  │  │  PAGE HEADER                     ││
│ (240px)  │  │  titre + sous-titre + actions    ││
│          │  └──────────────────────────────────┘│
│ collaps- │  ┌──────────────────────────────────┐│
│ ible to  │  │                                  ││
│  64px    │  │  CONTENT AREA                    ││
│          │  │  (padding 2rem)                  ││
│ icon-    │  │                                  ││
│ only     │  │                                  ││
│ mode     │  │                                  ││
│          │  └──────────────────────────────────┘│
└──────────┴──────────────────────────────────────┘
```

**Topbar** (nouvelle — absente actuellement) :
- Logo IPIRNET à gauche + chevron collapse sidebar
- Breadcrumb dynamique au centre (Dashboard > Stagiaires > Profil)
- À droite : sélecteur d'année scolaire (redesigné en pill avec chevron), notification bell (badge), avatar utilisateur + dropdown

**Sidebar** :
- Largeur : 240px expanded / 64px collapsed (toggle via topbar)
- Transition : `width 0.3s cubic-bezier(0.4, 0, 0.2, 1)`
- En mode collapsed, seules les icônes + tooltip au hover
- Section groupée avec séparateurs stylisés
- Active state : fond gradient avec glow lateral
- Footer : avatar + nom + rôle + logout

---

## 5. Fond Animé Global (Background Layer)

Appliqué sur `<body>` via un canvas ou pur CSS :

```css
/* Mesh gradient animé — 3 orbes qui se déplacent lentement */
body::before {
  content: '';
  position: fixed; inset: 0; z-index: -1;
  background:
    radial-gradient(ellipse 800px 600px at 20% 20%,
      rgba(99,102,241,0.07) 0%, transparent 70%),
    radial-gradient(ellipse 600px 500px at 80% 70%,
      rgba(34,211,238,0.05) 0%, transparent 70%),
    radial-gradient(ellipse 900px 700px at 50% 100%,
      rgba(168,85,247,0.04) 0%, transparent 70%);
  animation: meshDrift 20s ease-in-out infinite alternate;
}

@keyframes meshDrift {
  0%   { transform: scale(1)    translate(0, 0); }
  33%  { transform: scale(1.05) translate(2%, 1%); }
  66%  { transform: scale(0.98) translate(-1%, 2%); }
  100% { transform: scale(1.02) translate(1%, -1%); }
}
```

**Noise texture** en overlay (très subtile, opacity 0.025) via SVG inline :
```css
body::after {
  content: '';
  position: fixed; inset: 0; z-index: -1;
  background-image: url("data:image/svg+xml,...grain...");
  opacity: 0.025;
  pointer-events: none;
}
```

---

## 6. Cartes (Cards) — Nouveau Style

```css
.card {
  background:
    linear-gradient(
      135deg,
      rgba(255,255,255,0.035) 0%,
      rgba(255,255,255,0.01) 100%
    );
  border: 1px solid var(--border-default);
  border-radius: var(--radius-card);
  box-shadow: var(--shadow-card);
  backdrop-filter: blur(var(--blur-glass));
  -webkit-backdrop-filter: blur(var(--blur-glass));
  position: relative;
  overflow: hidden;
}

/* Bord lumineux supérieur (shimmer) */
.card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(
    90deg,
    transparent 0%,
    rgba(255,255,255,0.12) 30%,
    rgba(99,102,241,0.25) 50%,
    rgba(255,255,255,0.12) 70%,
    transparent 100%
  );
}

/* Hover lift effect */
.card:hover {
  transform: translateY(-2px);
  border-color: var(--border-accent);
  box-shadow: var(--shadow-card), var(--shadow-glow);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
```

---

## 7. Dashboard — Redesign Complet

### Hero banner (remplace le "Bienvenue" actuel)

```
┌──────────────────────────────────────────────────────────────┐
│  [Mesh gradient: indigo → cyan → purple animé en arrière-plan]
│                                                              │
│  Bonjour, [Prénom] 👋                 [Particle animation]   │
│  Voici ce qui se passe à IPIRNET                            │
│  mercredi 18 juin 2025                                       │
│                                                              │
│  ○ Année 2025/2026 active              [Pill selector]       │
└──────────────────────────────────────────────────────────────┘
```

Animation d'entrée du hero : opacité 0→1 + translateY(20px→0) sur 0.8s ease-out.

### KPI Cards — v2 (counter animation)

4 cartes en grille 2×2 sur mobile, 4×1 sur desktop.

Chaque carte :
```
┌────────────────────────────────────────────┐
│  ┌─────────┐  Label          trend badge   │
│  │  Icon   │  [COUNT-UP]                   │  ← SVG animated counter
│  │  [glow] │  sous-texte                   │
│  └─────────┘                               │
│  ─────────────────────── [sparkline SVG]   │
└────────────────────────────────────────────┘
```

**Sparkline** : mini graphique en ligne sur 7 jours en bas de chaque carte (1px stroke, couleur d'accent, no fill).

**Counter animation (JS natif) :**
```javascript
function animateCounter(el, target, duration = 1200) {
  const start = performance.now();
  const from = 0;
  const easeOut = t => 1 - Math.pow(1 - t, 3);
  function step(now) {
    const p = Math.min((now - start) / duration, 1);
    el.textContent = Math.round(from + (target - from) * easeOut(p));
    if (p < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}
```

### Graphiques — Remplacement du SVG brut

**Anneau de stages** → Anneau concentriques (donut multi-niveaux) :
- Anneau extérieur : % stages 2ème année
- Anneau intérieur : % stages 1ère année
- Animation : `stroke-dashoffset` de `2πr` → valeur cible sur 1.5s avec `cubic-bezier(0.4, 0, 0.2, 1)`
- Centre : chiffre principal + libellé
- Tooltip au hover sur chaque arc

**Histogramme absences** → Barres avec animation bottom-up :
```css
@keyframes barGrow {
  from { transform: scaleY(0); transform-origin: bottom; }
  to   { transform: scaleY(1); transform-origin: bottom; }
}
.bar { animation: barGrow 0.6s cubic-bezier(0.34,1.56,0.64,1) forwards; }
/* Stagger : chaque barre décalée de 80ms */
```

**Nouveau graphique ajouté — Heatmap des absences** (style GitHub contribution graph) :
- 52 colonnes × 7 lignes = 364 cellules colorées de `--bg-elevated` (0 absences) à `--rose-500` (max)
- Tooltip au hover : "Semaine du X au Y : N absences"

### Flux d'activité — v2

Remplacer les lignes de texte par des **Timeline cards** :
```
    │
    ○── [Icon coloré] ── Nouveau stagiaire — Ahmed Berrada ──────── il y a 3h
    │                    Classe 2A TSDI · Filière TSDI 2025/2026
    │
    ○── [Icon vert]   ── Paiement reçu — Karima El Fassi ───────── hier
    │                    350 MAD · Juin 2025
    │
    ○── [Icon rose]   ── Absence injustifiée — Yassine Bakkali ─── hier
    │                    Module: Algorithmique · 14h-16h
    │
```

---

## 8. Navigation Sidebar — Redesign

### Structure HTML redessinée

```html
<aside class="sidebar" data-collapsed="false">

  <!-- Logo + collapse toggle -->
  <div class="sidebar-header">
    <a class="brand" href="index.php">
      <div class="brand-icon">
        <img src="assets/img/logo.png" alt="IPIRNET">
        <div class="brand-icon-glow"></div>
      </div>
      <div class="brand-text">
        <span class="brand-name">IPIRNET</span>
        <span class="brand-sub">Centre de Formation</span>
      </div>
    </a>
    <button class="sidebar-toggle" aria-label="Réduire">
      <svg>...</svg>  <!-- chevron animé -->
    </button>
  </div>

  <!-- Sélecteur d'année (pill redesigné) -->
  <div class="year-selector">
    <div class="year-pill">
      <i class="icon-calendar"></i>
      <span class="year-label">2025/2026</span>
      <i class="icon-chevron"></i>
    </div>
  </div>

  <!-- Navigation principale -->
  <nav class="sidebar-nav">
    <div class="nav-section">
      <span class="nav-section-label">Vue d'ensemble</span>
      <a class="nav-item active" href="index.php">
        <div class="nav-icon"><svg>dashboard</svg></div>
        <span>Tableau de bord</span>
      </a>
    </div>

    <div class="nav-section">
      <span class="nav-section-label">Dossiers</span>
      <a class="nav-item" href="stagiaires.php">
        <div class="nav-icon"><svg>users</svg></div>
        <span>Stagiaires</span>
        <span class="nav-count">360</span>
      </a>
      <a class="nav-item" href="demandes_inscription.php">
        <div class="nav-icon"><svg>clock</svg></div>
        <span>Pré-inscriptions</span>
        <span class="nav-badge">12</span>  <!-- badge rouge pulsant -->
      </a>
      <a class="nav-item" href="notes.php">
        <div class="nav-icon"><svg>bar-chart</svg></div>
        <span>Notes & Évaluations</span>
      </a>
      <a class="nav-item" href="absences.php">
        <div class="nav-icon"><svg>calendar-x</svg></div>
        <span>Absences</span>
      </a>
      <a class="nav-item" href="stages.php">
        <div class="nav-icon"><svg>briefcase</svg></div>
        <span>Stages / PFE</span>
      </a>
      <a class="nav-item" href="cotisations.php">
        <div class="nav-icon"><svg>credit-card</svg></div>
        <span>Cotisations</span>
      </a>
    </div>

    <div class="nav-section">
      <span class="nav-section-label">Administration</span>
      <a class="nav-item" href="gestion_modules.php">
        <div class="nav-icon"><svg>book-open</svg></div>
        <span>Modules</span>
      </a>
      <a class="nav-item" href="gestion_classes.php">
        <div class="nav-icon"><svg>layout-grid</svg></div>
        <span>Classes</span>
      </a>
      <a class="nav-item" href="rapports.php">
        <div class="nav-icon"><svg>file-text</svg></div>
        <span>Rapports & Exports</span>
      </a>
      <a class="nav-item" href="audit_trail.php">
        <div class="nav-icon"><svg>shield</svg></div>
        <span>Journal d'audit</span>
      </a>
    </div>
  </nav>

  <!-- Footer utilisateur -->
  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar">AD</div>
      <div class="user-info">
        <span class="user-name">Admin</span>
        <span class="user-role">Directeur</span>
      </div>
      <a href="logout.php" class="logout-btn" title="Déconnexion">
        <svg>log-out</svg>
      </a>
    </div>
  </div>
</aside>
```

### CSS Sidebar animée

```css
.sidebar {
  width: 240px;
  transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  overflow: hidden;
}
.sidebar[data-collapsed="true"] {
  width: 64px;
}
.sidebar[data-collapsed="true"] .brand-text,
.sidebar[data-collapsed="true"] .nav-section-label,
.sidebar[data-collapsed="true"] nav span,
.sidebar[data-collapsed="true"] .user-info {
  opacity: 0;
  width: 0;
  overflow: hidden;
  transition: opacity 0.2s, width 0.3s;
}
.sidebar[data-collapsed="true"] .nav-item {
  justify-content: center;
  padding: 12px;
}

/* Active item — gradient glow à gauche */
.nav-item.active {
  background: linear-gradient(
    90deg,
    rgba(99,102,241,0.2) 0%,
    rgba(99,102,241,0.05) 100%
  );
  border-left: 3px solid var(--accent-500);
  color: #fff;
}
.nav-item.active .nav-icon svg {
  stroke: var(--accent-400);
  filter: drop-shadow(0 0 6px rgba(99,102,241,0.6));
}

/* Tooltip en mode collapsed */
.sidebar[data-collapsed="true"] .nav-item:hover::after {
  content: attr(data-tooltip);
  position: absolute;
  left: 72px;
  background: var(--bg-elevated);
  color: var(--text-primary);
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 0.82rem;
  white-space: nowrap;
  border: 1px solid var(--border-strong);
  box-shadow: var(--shadow-float);
  animation: tooltipIn 0.15s ease-out;
}
```

---

## 9. Page Stagiaires — Hub Central

### Barre de recherche redessinée

```
┌────────────────────────────────────────────────────────────────┐
│  🔍 Rechercher un stagiaire...          [Filtres ▾] [+ Ajouter]│
└────────────────────────────────────────────────────────────────┘
```

Barre de recherche avec animation focus-expand :
- Au focus : border passe de `--border-default` → `--border-accent` + box-shadow glow
- Raccourci clavier `⌘K` ou `/` ouvre une **Command Palette** modale style Linear :

```
┌─────────────────────────────────────────────┐
│  🔍  Rechercher ou exécuter une commande…   │
├─────────────────────────────────────────────┤
│  > Ahmed Berrada — 2A TSDI                  │
│  > Karima El Fassi — 1A TSGE                │
│  ─────────────────────────────────────────  │
│  Actions rapides :                          │
│  ⌘N  Nouveau stagiaire                      │
│  ⌘E  Exporter CSV                          │
│  ⌘R  Rapports                              │
└─────────────────────────────────────────────┘
```

Animation d'ouverture : scale(0.95)→scale(1) + opacity 0→1 sur 0.2s.

### Table des stagiaires — v2

Remplacer les lignes plates par des **Row cards** avec avatar :

```
│ ┌──────────────────────────────────────────────────────────────┐│
│ │  [AV]  Ahmed BERRADA         2A TSDI   2025/2026             ││ ← hover: fond +glow
│ │        CIN: BA100034         ●Active   [Voir] [Note] [Abs.]  ││
│ └──────────────────────────────────────────────────────────────┘│
```

- **Avatar** : initiales dans un cercle coloré (couleur basée sur le hash du nom)
- **Status badge** : pill colorée (Actif/Radié)
- **Row stagger animation** : chaque ligne entre avec un délai `animation-delay: calc(var(--i) * 40ms)`
- **Row hover** : `translateX(4px)` + highlight gauche

### Profil Stagiaire — Page de détail complète

Redessin total en **3 colonnes** :

```
┌──────────────────┬──────────────────────────────────────────┐
│   CARTE PROFIL   │  TABS: Dossier | Notes | Absences |       │
│                  │         Paiements | Stage | Historique    │
│  [Photo/Avatar]  ├──────────────────────────────────────────┤
│  Nom Prénom      │                                          │
│  Filière · Classe│  [CONTENU DU TAB ACTIF]                  │
│  CIN / N° insc.  │                                          │
│  ─────────────── │                                          │
│  Stats rapides : │                                          │
│  • Moy. générale │                                          │
│  • Absences      │                                          │
│  • Stage         │                                          │
│  • Cotisations   │                                          │
│  ─────────────── │                                          │
│  [Actions]       │                                          │
└──────────────────┴──────────────────────────────────────────┘
```

**Tab switching** : glissement du contenu avec `translateX(-20px)→0 + opacity 0→1`.

---

## 10. Page Notes — Redesign

### Tableau de notes interactif

- Entête de colonnes collant (sticky) avec fond flou backdrop-filter
- Cellules de notes : couleur dynamique par seuil (rouge <10, ambre 10-12, vert >12, or >16)
- Animation de tri des colonnes (flèche rotative + resort des lignes)
- **Heatmap de performance** : grille modules × étudiants en mini-cellules colorées

### Graphique de distribution des notes

Nouveau graphique en cloche (bell curve) SVG animé :
- Path SVG tracé progressivement via `stroke-dashoffset`
- Points de données visibles au hover
- Ligne de moyenne animée qui glisse en place

---

## 11. Page Absences — Redesign

### Vue calendrier mensuelle (nouvelle)

```
     Lun  Mar  Mer  Jeu  Ven
S1 │  ░   ●   ░   ░   ░   │  ← ● = absence
S2 │  ░   ░   ░   ●●  ░   │
S3 │  ●   ░   ░   ░   ░   │
S4 │  ░   ░   ●   ░   ░   │
```

Cellules colorées selon le taux d'absences de la classe ce jour.
Clic sur un jour → panel drawer latéral avec la liste des absents.

### Drawer latéral (nouveau composant)

```css
.drawer {
  position: fixed; right: 0; top: 0; bottom: 0;
  width: 420px;
  background: var(--bg-surface);
  border-left: 1px solid var(--border-default);
  transform: translateX(100%);
  transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: 500;
  box-shadow: -20px 0 60px rgba(0,0,0,0.5);
}
.drawer.open {
  transform: translateX(0);
}
```

Backdrop semi-transparent avec `backdrop-filter: blur(4px)` au dessus du contenu.

---

## 12. Page Stages — Redesign

### Vue Kanban (nouvelle — optionnelle)

3 colonnes en kanban scrollable :

```
┌───────────────┐  ┌───────────────┐  ┌───────────────┐
│  SANS STAGE   │  │  EN COURS     │  │  TERMINÉ      │
│   (N élèves)  │  │  (N élèves)   │  │  (N élèves)   │
│               │  │               │  │               │
│ ┌───────────┐ │  │ ┌───────────┐ │  │ ┌───────────┐ │
│ │ Stagiaire │ │  │ │ Stagiaire │ │  │ │ Stagiaire │ │
│ │ Entreprise│ │  │ │ Entreprise│ │  │ │ Note: 16  │ │
│ └───────────┘ │  │ └───────────┘ │  │ └───────────┘ │
└───────────────┘  └───────────────┘  └───────────────┘
```

Cards du kanban : drag-and-drop (vanilla JS — Sortable.js léger).

**Carte de stage détaillée :**
```
┌─────────────────────────────────────────────────┐
│ [Avatar]  Ahmed BERRADA                  [●●●]  │
│           2A TSDI                               │
│ ─────────────────────────────────────────────── │
│ 🏢 Maroc Telecom                                │
│ 📋 Développement web PHP MySQL                  │
│ 📅 15 Fév → 30 Avr 2026   (75 jours)           │
│ ⭐ Note : 17.5/20           [Très bien]          │
└─────────────────────────────────────────────────┘
```

---

## 13. Page Cotisations — Redesign

### Vue paiements avec timeline financière

**Grille de paiements mensuels :**
```
        Sept  Oct  Nov  Déc  Jan  Fév  Mar  Avr  Mai  Juin
Ahmed    ✓     ✓    ✓    ✗    ✓    ✓    ✓    —    —    —
Karima   ✓     ✓    ✓    ✓    ✓    ✓    ✓    —    —    —
Youssef  ✓     ✗    ✓    ✓    ✓    ✓    ✗    —    —    —
```

Cellules : vert (payé), rouge (impayé), gris (mois futur).
Au clic sur rouge → drawer latéral pour enregistrer le paiement.

---

## 14. Page Pré-inscriptions — Redesign

### Cards au lieu de tableau

Chaque demande en **card horizontale** avec avatar et actions rapides :
```
┌──────────────────────────────────────────────────────────────────┐
│  [AV]  Mohammed ALAMI        Demande : 18 juin 2025   [En attente]│
│        maroc@email.com       Filière souhaitée : TSDI             │
│                              CIN : AB123456                       │
│  [Voir le dossier]                       [✓ Accepter] [✗ Refuser]│
└──────────────────────────────────────────────────────────────────┘
```

Animation d'acceptation/refus : la carte glisse et disparaît vers le bas avec un fondu.

---

## 15. Page Rapports — Redesign

### Hub d'export redessiné

4 grandes cards d'export avec preview :

```
┌────────────────────┐  ┌────────────────────┐
│  👥 Stagiaires     │  │  📊 Notes          │
│                    │  │                    │
│  [aperçu colonnes] │  │  [aperçu colonnes] │
│                    │  │                    │
│  [Filtres ▾]       │  │  [Filtres ▾]       │
│       [⬇ CSV]      │  │       [⬇ CSV]      │
└────────────────────┘  └────────────────────┘

┌────────────────────┐  ┌────────────────────┐
│  💳 Paiements      │  │  ❌ Absences        │
│  ...               │  │  ...               │
└────────────────────┘  └────────────────────┘
```

---

## 16. Page Publique — Pre-inscription (Public Shell)

C'est la seule page **visible de l'extérieur** — elle mérite un redesign de type **landing page**.

### Nouveau concept : "Portail d'admission IPIRNET"

**Section hero (plein écran, scroll pour continuer) :**
```
┌──────────────────────────────────────────────────────────────────────┐
│                                                                      │
│   [Fond : mesh gradient animé + particules légères flottantes]       │
│                                                                      │
│         ○ ─────────────── IPIRNET ─────────────── ○                 │
│                                                                      │
│              Donnez-vous les moyens                                  │
│              de votre ambition.                           [fade-in]  │
│                                                                      │
│         Formation professionnelle d'excellence                       │
│         TSDI · TGI · TSGE                                            │
│                                                                      │
│              [ Déposer ma candidature ↓ ]    [← CTA pill animé]     │
│                                                                      │
│                      ↓ Scroll                                        │
└──────────────────────────────────────────────────────────────────────┘
```

**Particules JS (léger, vanilla) :**
```javascript
// 40-60 particules circulaires, 1-3px, opacity 0.1-0.3
// Mouvement brownien lent, pas de connexions entre elles
// Pur canvas 2D, < 50 lignes JS
```

**Section filières (scroll reveal) :**
3 cards horizontales qui glissent depuis la gauche avec stagger.
```
 ┌───────────────────────────────────────────┐
 │  [Icône]  TSDI                            │
 │  Technicien Spécialisé Développement Info │
 │  2 ans · 30 places disponibles            │
 └───────────────────────────────────────────┘
```
Animation : `translate(-60px, 0) → (0, 0)` + opacity 0→1, stagger 150ms.

**Section statistiques (counter animation au scroll) :**
```
    [ 360 ]       [ 85% ]       [ 6 ]
    Stagiaires    Taux réussite  Filières
```
Les chiffres animent quand ils entrent dans le viewport (IntersectionObserver).

**Formulaire de candidature (scroll reveal depuis bas) :**
- Design en 3 étapes avec progress bar animée :
  ```
  ● ────────────────── ○ ────────────────── ○
  Informations         Filière              Confirmation
  personnelles         choisie
  ```
- Navigation entre étapes : slide left/right avec transition CSS
- Validation en temps réel (bordure verte/rouge + message inline)
- Bouton soumettre : loading spinner intégré pendant l'envoi

---

## 17. Documents Imprimables — Redesign

### Attestation de réussite

Nouveau design inspiré des diplômes premium :

```
┌─────────────────────────────────────────────────────────────────┐
│  [Bandeau supérieur doré avec motif géométrique]                │
│                                                                 │
│     [Logo IPIRNET]         République du Maroc                  │
│                            Ministère de la Formation Pro.       │
│                                                                 │
│  ═══════════════════════════════════════════════════════        │
│                                                                 │
│              ATTESTATION DE RÉUSSITE                            │
│              ───────────────────────                            │
│                                                                 │
│  Nous attestons que                                             │
│                                                                 │
│              M. / Mme  ___PRÉNOM NOM___                         │
│                    (en EB Garamond italic, grande taille)       │
│                                                                 │
│  a réussi la formation ________________________                 │
│  dispensée au Centre IPIRNET, session _________.                │
│                                                                 │
│  ─────────────────────────────────────────────────────         │
│  [Signature + sceau]                    [QR code vérif]        │
│                                                                 │
│  [Bandeau inférieur doré miroir du supérieur]                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## 18. Système d'Animations — Référentiel Complet

### A. Transitions de page

```javascript
// Chaque navigation = fadeOut(150ms) → swap URL → fadeIn(250ms)
// Implémenté via View Transitions API (Chrome 111+) avec fallback
document.addEventListener('click', e => {
  const link = e.target.closest('a[href]');
  if (!link || link.target || e.ctrlKey) return;
  e.preventDefault();
  document.documentElement.classList.add('page-exit');
  setTimeout(() => {
    window.location.href = link.href;
  }, 150);
});
// Sur chaque page load:
document.documentElement.classList.add('page-enter');
```

```css
@view-transition { navigation: auto; }
::view-transition-old(root) {
  animation: 120ms ease-out both pageExit;
}
::view-transition-new(root) {
  animation: 280ms ease-out both pageEnter;
}
@keyframes pageExit {
  to { opacity: 0; transform: translateY(-8px) scale(0.99); }
}
@keyframes pageEnter {
  from { opacity: 0; transform: translateY(12px) scale(0.99); }
}
```

### B. Scroll Reveal (IntersectionObserver)

```javascript
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach((entry, i) => {
    if (entry.isIntersecting) {
      entry.target.style.animationDelay = `${i * 60}ms`;
      entry.target.classList.add('revealed');
      revealObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));
```

```css
.reveal {
  opacity: 0;
  transform: translateY(24px);
  transition: opacity 0.5s ease, transform 0.5s cubic-bezier(0.4,0,0.2,1);
}
.reveal.revealed {
  opacity: 1;
  transform: translateY(0);
}
```

### C. Mouse Lighting (amélioration du système existant)

```javascript
// Déjà présent — améliorer avec :
// - Rayon d'effet réduit à 300px (plus chirurgical)
// - Opacity max 0.06 (plus subtil)
// - Smooth lerp pour suivre la souris avec latence
let targetX = 0, targetY = 0, currentX = 0, currentY = 0;
function lerp(a, b, t) { return a + (b - a) * t; }
function animateLight() {
  currentX = lerp(currentX, targetX, 0.08);
  currentY = lerp(currentY, targetY, 0.08);
  document.documentElement.style.setProperty('--mx', currentX + 'px');
  document.documentElement.style.setProperty('--my', currentY + 'px');
  requestAnimationFrame(animateLight);
}
document.addEventListener('mousemove', e => { targetX = e.clientX; targetY = e.clientY; });
animateLight();
```

### D. Toast Notifications (remplace les flash messages)

```javascript
function toast(message, type = 'success', duration = 4000) {
  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.innerHTML = `<i class="icon-${type}"></i> <span>${message}</span>`;
  document.getElementById('toast-container').appendChild(el);
  // Entrée : slide depuis le bas
  requestAnimationFrame(() => el.classList.add('toast-visible'));
  setTimeout(() => {
    el.classList.remove('toast-visible');
    setTimeout(() => el.remove(), 300);
  }, duration);
}
```

```css
#toast-container {
  position: fixed; bottom: 2rem; right: 2rem;
  z-index: 9999;
  display: flex; flex-direction: column; gap: 0.75rem;
}
.toast {
  padding: 0.875rem 1.25rem;
  border-radius: 12px;
  border: 1px solid;
  backdrop-filter: blur(16px);
  font-size: 0.875rem;
  min-width: 280px;
  transform: translateX(120%);
  transition: transform 0.35s cubic-bezier(0.34,1.56,0.64,1);
  display: flex; align-items: center; gap: 0.75rem;
}
.toast.toast-visible { transform: translateX(0); }
.toast-success { background: rgba(16,185,129,0.15); border-color: rgba(16,185,129,0.4); color: #6ee7b7; }
.toast-error   { background: rgba(244,63,94,0.15);  border-color: rgba(244,63,94,0.4);  color: #fda4af; }
.toast-warning { background: rgba(245,158,11,0.15); border-color: rgba(245,158,11,0.4); color: #fcd34d; }
```

### E. Skeletons (états de chargement)

```css
@keyframes shimmer {
  0%   { background-position: -200% 0; }
  100% { background-position:  200% 0; }
}
.skeleton {
  background: linear-gradient(
    90deg,
    var(--bg-elevated) 25%,
    rgba(255,255,255,0.04) 50%,
    var(--bg-elevated) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 6px;
}
```

### F. Modales — Redesign

```css
.modal-backdrop {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.7);
  backdrop-filter: blur(6px);
  z-index: 900;
  opacity: 0;
  transition: opacity 0.25s;
}
.modal-backdrop.open { opacity: 1; }

.modal {
  background: var(--bg-surface);
  border: 1px solid var(--border-strong);
  border-radius: 20px;
  box-shadow: var(--shadow-float);
  transform: scale(0.94) translateY(16px);
  opacity: 0;
  transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);
}
.modal.open {
  transform: scale(1) translateY(0);
  opacity: 1;
}
```

---

## 19. Composants UI Complets — Référentiel

### Boutons

```css
/* Primaire */
.btn-primary {
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #fff;
  padding: 0.625rem 1.25rem;
  border-radius: var(--radius-sm);
  font-weight: 600;
  font-size: 0.875rem;
  border: 1px solid rgba(99,102,241,0.5);
  box-shadow: 0 1px 3px rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,255,255,0.1);
  transition: all 0.2s;
  cursor: pointer;
}
.btn-primary:hover {
  background: linear-gradient(135deg, #818cf8, #6366f1);
  box-shadow: 0 4px 16px rgba(99,102,241,0.4), inset 0 1px 0 rgba(255,255,255,0.15);
  transform: translateY(-1px);
}
.btn-primary:active { transform: translateY(0); }

/* Ghost */
.btn-ghost {
  background: transparent;
  color: var(--text-secondary);
  border: 1px solid var(--border-default);
  padding: 0.625rem 1.25rem;
  border-radius: var(--radius-sm);
  transition: all 0.2s;
}
.btn-ghost:hover {
  background: var(--bg-hover);
  color: var(--text-primary);
  border-color: var(--border-strong);
}

/* Danger */
.btn-danger {
  background: rgba(244,63,94,0.1);
  color: var(--rose-400);
  border: 1px solid rgba(244,63,94,0.3);
}
.btn-danger:hover {
  background: rgba(244,63,94,0.2);
  box-shadow: 0 0 0 3px rgba(244,63,94,0.15);
}
```

### Inputs

```css
.input {
  background: var(--bg-elevated);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-sm);
  color: var(--text-primary);
  padding: 0.625rem 0.875rem;
  font-size: 0.875rem;
  width: 100%;
  transition: border-color 0.2s, box-shadow 0.2s;
  outline: none;
}
.input:focus {
  border-color: var(--accent-500);
  box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
}
.input::placeholder { color: var(--text-muted); }

/* Label flottant */
.input-group {
  position: relative;
}
.input-group label {
  position: absolute;
  top: 50%; left: 0.875rem;
  transform: translateY(-50%);
  font-size: 0.875rem;
  color: var(--text-muted);
  pointer-events: none;
  transition: all 0.2s;
  background: var(--bg-elevated);
  padding: 0 4px;
}
.input-group input:focus + label,
.input-group input:not(:placeholder-shown) + label {
  top: 0;
  font-size: 0.72rem;
  color: var(--accent-400);
}
```

### Badges / Pills

```css
.badge {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.2rem 0.6rem;
  border-radius: var(--radius-pill);
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.02em;
}
.badge-active   { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
.badge-pending  { background: rgba(251,191,36,0.15); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); }
.badge-inactive { background: rgba(100,116,139,0.15); color: #94a3b8; border: 1px solid rgba(100,116,139,0.3); }
.badge-danger   { background: rgba(244,63,94,0.15); color: var(--rose-400); border: 1px solid rgba(244,63,94,0.3); }
```

---

## 20. Plan d'Implémentation — Ordre des Priorités

### Phase 1 — Fondations CSS (2-3 jours)
1. Nouveau `app.css` — variables, reset, base
2. Fond animé (mesh gradient + noise)
3. Cards redessinées
4. Boutons, inputs, badges v2
5. Sidebar animée (collapse/expand)
6. Topbar

### Phase 2 — Dashboard (1-2 jours)
1. Hero banner
2. KPI cards avec counter animation
3. Flux activité v2 (timeline)
4. Donut chart animé
5. Histogramme avec barGrow animation
6. Toasts (remplace flash messages)

### Phase 3 — Pages Dossiers (3-4 jours)
1. Table stagiaires v2 (row cards + stagger)
2. Profil stagiaire (3 colonnes + tabs)
3. Notes (heatmap + tri animé)
4. Absences (calendrier + drawer)
5. Stages (kanban ou table redessinée)
6. Cotisations (grille paiements mensuelle)

### Phase 4 — Pages Admin (1-2 jours)
1. Pré-inscriptions (cards + animations acceptation)
2. Rapports (hub export 4 cards)
3. Modules & Classes (tables admin)

### Phase 5 — Expérience publique (1-2 jours)
1. Hero plein écran + particules
2. Sections filières + stats avec scroll reveal
3. Formulaire multi-étapes animé

### Phase 6 — Documents imprimables
1. Redesign attestations (style premium)
2. Bulletins de notes redessinés

---

## 21. Fichiers à Créer / Modifier

```
assets/css/
  app.css              ← Réécriture complète
  nexus-motion.css     ← Système d'animations
  nexus-components.css ← Composants (boutons, inputs, badges, modales, toasts)
  print.css            ← Styles impression redessinés

assets/js/
  nexus-core.js        ← Sidebar toggle, topbar, page transitions
  nexus-motion.js      ← Scroll reveal, counters, skeletons
  nexus-charts.js      ← Donut, histogramme, sparklines, heatmap
  nexus-toast.js       ← Système toast
  nexus-command.js     ← Command palette ⌘K
  nexus-particles.js   ← Particules page publique (< 80 lignes)
  filiere-filter.js    ← Conservé, mis à jour
  gds-table-filter.js  ← Conservé, mis à jour
  validation.js        ← Conservé, mis à jour

includes/
  header.php           ← Nouveau topbar + sidebar HTML
```

---

## Résumé visuel de la transformation

| Avant | Après |
|-------|-------|
| Fond `#12121a` fixe | Fond animé mesh gradient + noise texture |
| Sidebar statique 220px | Sidebar collapsible 240px ↔ 64px, tooltip mode |
| Pas de topbar | Topbar 64px : breadcrumb + year selector + bell |
| Flash messages inline | Toasts animés bottom-right |
| SVG ring statique | Donut double anneau animé |
| Barres CSS statiques | Barres animées `barGrow` avec stagger |
| Compteurs statiques PHP | Count-up animation JS au load |
| Lignes de table plates | Row cards avec avatar + stagger reveal |
| Pas de transition de page | View Transitions API + fallback CSS |
| Flash message PHP reload | Toasts JS instantanés |
| Formulaire pré-inscription page basique | Landing page plein écran + multi-étapes |
| Attestations A4 basiques | Documents premium avec bande dorée + QR |
| Aucun skeleton loader | Skeletons shimmer sur toutes les données async |
| Mouse lighting basique | Mouse lighting lerp smooth + limité au contenu |
