# Web Design System & Styling-Konzept

> Allgemeingueltiges Referenzdokument fuer Webprojekte mit Bootstrap 5, jQuery und PHP.
> Definiert Komponenten, Patterns und Konventionen fuer ein konsistentes UI.

---

## Inhaltsverzeichnis

1. [Tech-Stack & Abhängigkeiten](#1-tech-stack--abhängigkeiten)
2. [CSS-Variablen & Farbpalette](#2-css-variablen--farbpalette)
3. [Typografie](#3-typografie)
4. [Layout & Responsive Breakpoints](#4-layout--responsive-breakpoints)
5. [Z-Index-Hierarchie](#5-z-index-hierarchie)
6. [Buttons](#6-buttons)
7. [Tabellen](#7-tabellen)
8. [Slide-Panel (Hybrid-Editor)](#8-slide-panel-hybrid-editor)
9. [Mobile-Cards](#9-mobile-cards)
10. [Custom Tabs](#10-custom-tabs)
11. [Toast & Benachrichtigungen](#11-toast--benachrichtigungen)
12. [Tooltip-System](#12-tooltip-system)
13. [Formulare](#13-formulare)
14. [Karten (Cards)](#14-karten-cards)
15. [Loading-States](#15-loading-states)
16. [Animationen & Transitions](#16-animationen--transitions)
17. [Icons (Bootstrap Icons)](#17-icons-bootstrap-icons)
18. [Page-Specific CSS](#18-page-specific-css)
19. [Flex-Chain fuer scrollbare Tabellen](#19-flex-chain-fuer-scrollbare-tabellen)
20. [Barrierefreiheit (WCAG)](#20-barrierefreiheit-wcag)
21. [CSRF & Sicherheit](#21-csrf--sicherheit)
22. [Designprinzipien](#22-designprinzipien)

---

## 1. Tech-Stack & Abhängigkeiten

### CDN-Libraries

| Library | Version | Zweck |
|---------|---------|-------|
| Bootstrap CSS | 5.3.x | Grid, Utilities, Basis-Komponenten |
| Bootstrap Icons | 1.11.x | Icon-Font |
| jQuery | 3.6+ | DOM-Manipulation, AJAX |
| Bootstrap Bundle JS | 5.3.x | Dropdowns, Collapse, Offcanvas |
| jQuery UI | 1.13.x | Datepicker, Sortable (optional) |
| SweetAlert2 | 11.x | Toasts, Confirm-Dialoge, Modals |

### Empfohlene Dateistruktur

```
project/
├── css/
│   ├── app-styles.css          ← Haupt-Stylesheet, CSS-Variablen, Komponenten
│   └── mobile-cards.css        ← Mobile-Card-Pattern
├── js/
│   ├── app-toast.js            ← Zentrales Toast-System (SweetAlert2-Wrapper)
│   ├── app-tooltips.js         ← Tooltip-System (data-tooltip)
│   └── mobile-cards.js         ← Mobile-Card-Builder
├── inc/
│   ├── header.inc.php          ← Asset-Loading, Navigation, Page-Specific CSS
│   ├── footer.inc.php          ← Schliessendes HTML, zusaetzliche Scripts
│   └── ui/
│       └── buttons.inc.php     ← Button-Rendering-Helpers
└── pages/
    ├── feature-a.php
    └── feature-a/
        ├── save.php            ← AJAX-Endpunkt
        └── load.php            ← AJAX-Endpunkt
```

---

## 2. CSS-Variablen & Farbpalette

### Root-Variablen

```css
:root {
    /* Primaere Farben */
    --primary-color: #dee2e6;
    --secondary-color: #6c757d;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #adb5bd;
    --light-color: #f8f9fa;
    --dark-color: #343a40;

    /* Akzentfarbe (projektspezifisch anpassen) */
    --accent-color: #3b5998;

    /* Layout */
    --border-radius: 0.375rem;
    --transition-speed: 0.3s;
    --box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    --box-shadow-hover: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);

    /* Tabellen (bei Bedarf anpassen) */
    --name-col-w: 200px;
    --cell-w: 90px;
}
```

### Semantische Farben

| Zweck | Hex | Verwendung |
|-------|-----|------------|
| Hintergrund Basis | `#f8f9fa` | Seiten-Hintergrund, Header-Zellen |
| Text Standard | `#343a40` | Body-Text, Ueberschriften |
| Text gedaempft | `#6c757d` | Labels, Hinweise, Platzhalter |
| Borders | `#dee2e6` / `#e2e8f0` | Trennlinien, Card-Raender |
| Erfolg | `#28a745` | Bestaetigungen, Speichern |
| Fehler | `#dc3545` | Fehlermeldungen, Loeschen |
| Warnung | `#ffc107` | Hinweise, Pending-Status |
| Akzent | `#3b5998` | Primaere Aktionen, Links (anpassbar) |
| Hover-Tint | `rgba(99,102,241,0.05)` | Zeilen-Hover |
| Selected-Tint | `rgba(59,130,246,0.08)` | Aktive Auswahl |

### Compact-Button-Farben (Pastel-Varianten)

```css
.btn-compact.btn-info      { background: #bee3f8; color: #2c5282; }
.btn-compact.btn-success   { background: #c6f6d5; color: #276749; }
.btn-compact.btn-primary   { background: #e6f2ff; color: #3b5998; }
.btn-compact.btn-warning   { background: #feebc8; color: #c05621; }
.btn-compact.btn-danger    { background: #fed7d7; color: #c53030; }
.btn-compact.btn-pink      { background: #fed7e2; color: #97266d; }
.btn-compact.btn-secondary { background: #e2e8f0; color: #4a5568; }
```

---

## 3. Typografie

| Element | Groesse | Gewicht |
|---------|---------|---------|
| Body-Text | `0.9em` | 400 |
| Tabellen-Body | `0.85rem` | 400 |
| Tabellen-Header | `0.75rem` | 600 |
| Form-Labels | `0.75rem – 0.8rem` | 600 |
| Compact UI | `0.75rem` | 500 |
| Seitentitel (h5) | `1rem – 1.25rem` | 600–700 |
| Haupttitel (h1) | `1.5rem` | 700 |

**Font-Family:** `'Segoe UI', Tahoma, Geneva, Verdana, sans-serif`

> **Mobile:** Formulare auf `font-size: 16px` setzen, um iOS-Auto-Zoom zu verhindern.

---

## 4. Layout & Responsive Breakpoints

### Bootstrap-5-Breakpoints

| Name | Breite | Typischer Einsatz |
|------|--------|-------------------|
| xs | < 576px | Smartphones |
| sm | >= 576px | Grosse Smartphones |
| md | >= 768px | Tablets |
| lg | >= 992px | Desktop |
| xl | >= 1200px | Grosse Monitore |
| xxl | >= 1400px | Sehr grosse Monitore |

### Container-Abstufungen

```
Card-Padding:      1.25rem
Formular-Row:      0.75rem 1rem
Seitenrand:        2.5rem (Desktop), 1.5rem (Tablet), 1rem (Mobile)
Max-Width Content: 1200px (Oeffentlich), 960px (Admin-Panels)
```

### Desktop/Mobile-Umschaltung

```css
@media (max-width: 767.98px) {
    .desktop-table-container { display: none !important; }
    .mobile-cards-container  { display: flex !important; }
}
@media (min-width: 768px) {
    .mobile-cards-container  { display: none !important; }
}
```

---

## 5. Z-Index-Hierarchie

```
99999   Tooltips
1060    Slide-Panels, Modals
1055    Panel-Overlay
1050    Modal-Backdrops
1040    Dropdowns
1030    Navbar (fixed-top)
100     Sticky Toolbars
11      Sticky Table-Header (Eckspalten)
10      Table-Header (<thead>)
5–6     Sticky Body-Zellen
3–4     Content-Layer
```

> Alle z-index-Werte zentral dokumentieren, um Konflikte zu vermeiden.

---

## 6. Buttons

### Standard-Klassen

```html
<!-- Outline-Varianten (bevorzugt fuer sekundaere Aktionen) -->
<button class="btn btn-outline-info btn-sm">Bearbeiten</button>
<button class="btn btn-outline-danger btn-sm">Loeschen</button>
<button class="btn btn-outline-success btn-sm">Speichern</button>

<!-- Compact-Varianten (38px Hoehe, Pastel-Farben, fuer Toolbars) -->
<button class="btn btn-compact btn-primary">Aktion</button>
<button class="btn btn-compact btn-danger">Loeschen</button>
```

### Compact-Button CSS

```css
.btn-compact {
    min-height: 38px;
    padding: 0.35rem 0.85rem;
    font-size: 0.82rem;
    font-weight: 500;
    border: none;
    border-radius: 8px;
}
```

### Hover-Effekte

```css
.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.12);
}
.btn:active {
    transform: translateY(0);
}
.btn:focus {
    outline: 2px solid var(--secondary-color);
    outline-offset: 2px;
}
```

### Button-Rendering (PHP-Helper)

```php
function renderActionButtons($id, $editLabel = 'Bearbeiten', $deleteLabel = 'Loeschen') {
    // Gibt btn-group mit bi-pencil + bi-trash Icons zurueck
}
```

### Fade-In bei Hover (z.B. Loeschen-Buttons)

```css
.data-row .btn-delete { opacity: 0; transition: opacity 0.2s; }
.data-row:hover .btn-delete { opacity: 1; }
```

---

## 7. Tabellen

### Grundstruktur

```html
<div class="table-wrapper">
    <h5 class="table-title"><i class="bi bi-table"></i> Titel</h5>
    <div class="desktop-table-container">
        <table class="table" id="dataTable">
            <thead><tr><th>...</th></tr></thead>
            <tbody><tr><td>...</td></tr></tbody>
        </table>
    </div>
    <div class="mobile-cards-container" id="mobileContainer"></div>
</div>
```

### Sticky Headers

```css
.table thead th {
    position: sticky;
    top: 0;
    z-index: 5;
    background-color: var(--light-color);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
```

### Sticky erste/letzte Spalte

```css
/* Erste Spalte fixiert (links) */
.table tbody td:first-child {
    position: sticky;
    left: 0;
    z-index: 3;
    background-color: #ffffff;
    border-right: 2px solid #dee2e6;
}

/* Letzte Spalte fixiert (rechts, z.B. Aktionen) */
.table tbody td:last-child {
    position: sticky;
    right: 0;
    z-index: 3;
    background-color: rgba(108,117,125,0.05);
}
```

### Hover-Effekt

```css
.table tbody tr:hover {
    background-color: rgba(108,117,125,0.08) !important;
}
```

### PITFALL: Sticky + Border-Collapse

`border-collapse: collapse` verursacht Scroll-Bleed bei sticky Headers. Fix:

```css
table {
    border-collapse: separate !important;
    border-spacing: 0 !important;
}
thead th {
    /* box-shadow statt border-bottom — blutet nicht durch */
    box-shadow: inset 0 -2px 0 #dee2e6;
}
```

---

## 8. Slide-Panel (Hybrid-Editor)

Zentrales Bearbeitungs-Pattern: Tabellenzeile klicken → rechtes Panel gleitet ein.
Ersetzt verschachtelte Modals und haelt die Datentabelle sichtbar.

### CSS

```css
.panel-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.3);
    z-index: 1055;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s;
}
.panel-overlay.active {
    opacity: 1;
    visibility: visible;
}

.edit-panel {
    position: fixed;
    top: 0;
    right: -540px;
    width: 540px;
    height: 100vh;
    background: #fff;
    box-shadow: -8px 0 30px rgba(0,0,0,0.12);
    z-index: 1060;
    transition: right 0.3s cubic-bezier(0.4,0,0.2,1);
    display: flex;
    flex-direction: column;
}
.edit-panel.open { right: 0; }

/* Mobile: Panel nimmt volle Breite ein */
@media (max-width: 768px) {
    .edit-panel { width: 100vw; right: -100vw; }
}
```

### HTML-Struktur

```html
<div class="panel-overlay" onclick="closePanel()"></div>
<div class="edit-panel" id="editPanel">
    <div class="panel-header">
        <h6>Datensatz bearbeiten</h6>
        <button class="btn-close" onclick="closePanel()"></button>
    </div>
    <div class="panel-body" style="overflow-y: auto; flex: 1;">
        <label class="panel-label">Feldname</label>
        <input class="form-control" />
        <div class="panel-section">...</div>
    </div>
    <div class="panel-footer">
        <button class="btn btn-primary" onclick="save()">Speichern</button>
    </div>
</div>
```

### Klickbare Tabellenzeilen (Hybrid-Rows)

```css
.hybrid-row {
    cursor: pointer;
    transition: background 0.15s;
}
.hybrid-row:hover {
    background: rgba(99,102,241,0.05);
}
.hybrid-row.selected {
    background: rgba(59,130,246,0.08);
    box-shadow: inset 4px 0 0 #3b82f6;
}
```

```html
<tr class="hybrid-row" data-id="123" onclick="openPanel(123)">
    <td>Wert A</td>
    <td>Wert B</td>
</tr>
```

### Interaktions-Regeln

- **Escape** schliesst Panel
- **Overlay-Klick** schliesst Panel
- **Auto-Save** bei Panel-Close oder Datensatz-Wechsel (dirty-Flag pruefen)
- **Enter-Navigation** springt zwischen Inputs im Panel
- Loeschen via SweetAlert2-Confirm statt verschachteltem Modal

---

## 9. Mobile-Cards

Ersetzt Tabellen auf Smartphones durch aufklappbare Karten.

### Card-Struktur

```html
<div class="mobile-card">
    <div class="mobile-card-header" onclick="toggleCard(this)">
        <div>
            <div class="fw-bold">Titel</div>
            <small class="text-muted">Untertitel</small>
        </div>
        <i class="bi bi-chevron-down"></i>
    </div>
    <div class="mobile-card-body">
        <div class="mobile-card-detail-row">
            <span class="mobile-card-detail-label">Label</span>
            <span class="mobile-card-detail-value">Wert</span>
        </div>
    </div>
</div>
```

### Styling

```css
.mobile-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    margin-bottom: 0.5rem;
    overflow: hidden;
}
.mobile-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.875rem 1rem;
    cursor: pointer;
    user-select: none;
    min-height: 44px;           /* WCAG Touch Target */
}
.mobile-card-body {
    display: none;
    padding: 0 1rem 1rem;
    border-top: 1px solid #f1f3f4;
}
.mobile-card.open .mobile-card-body { display: block; }
```

### Rang-Hervorhebung (Top 3)

Fuer Ranglisten oder aehnliche sortierte Darstellungen:

```css
.mobile-card.rank-1 .mobile-card-header {
    background: linear-gradient(135deg, #ffd700, #ffed4e);     /* Gold */
}
.mobile-card.rank-2 .mobile-card-header {
    background: linear-gradient(135deg, #c0c0c0, #e8e8e8);     /* Silber */
}
.mobile-card.rank-3 .mobile-card-header {
    background: linear-gradient(135deg, #cd7f32, #e9a76a);     /* Bronze */
}
```

### PITFALL: Card-Builder + versteckte Zeilen

Ein generischer Card-Builder, der ueber **alle** `<tbody tr>` iteriert, erzeugt kaputte Cards wenn versteckte Detail-Zeilen existieren. Loesung: Builder so filtern, dass nur sichtbare Hauptzeilen verarbeitet werden (z.B. nur `.main-row` statt alle `tr`).

---

## 10. Custom Tabs

Leichtgewichtiges Tab-System ohne Bootstrap-Tabs — volle Kontrolle ueber Styling und Verhalten.

### CSS

```css
.custom-tabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid #dee2e6;
    margin-bottom: 1.25rem;
}
.custom-tab {
    padding: 0.6rem 1.25rem;
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--secondary-color);
    border: none;
    background: none;
    cursor: pointer;
    position: relative;
    transition: color 0.2s;
}
.custom-tab:hover { color: var(--dark-color); }
.custom-tab.active {
    color: var(--dark-color);
    font-weight: 600;
}
.custom-tab.active::after {
    content: "";
    position: absolute;
    bottom: -2px;
    left: 0; right: 0;
    height: 2px;
    background: var(--dark-color);
    border-radius: 2px 2px 0 0;
}

.tab-pane { display: none; }
.tab-pane.active { display: block; }
```

### HTML

```html
<div class="custom-tabs">
    <button class="custom-tab active" data-tab="tab-erfassung">Erfassung</button>
    <button class="custom-tab" data-tab="tab-liste">Liste</button>
    <button class="custom-tab" data-tab="tab-statistik">Statistik</button>
</div>

<div class="tab-pane active" id="tab-erfassung">...</div>
<div class="tab-pane" id="tab-liste">...</div>
<div class="tab-pane" id="tab-statistik">...</div>
```

### JS-Umschaltung

```javascript
document.querySelectorAll('.custom-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.custom-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById(tab.dataset.tab).classList.add('active');
    });
});
```

---

## 11. Toast & Benachrichtigungen

Zentrales Benachrichtigungs-System basierend auf SweetAlert2.

### Empfohlene Wrapper-Funktionen

```javascript
/**
 * Toast-Nachricht (oben rechts, 3 Sekunden, Progress-Bar)
 * @param {string} message
 * @param {string} type - 'success' | 'error' | 'warning' | 'info'
 */
function appToast(message, type = 'success') {
    if (type === 'danger') type = 'error';  // Bootstrap-Mapping
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: type,
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
}

/** Erfolgs-Toast */
function appSuccess(message) { appToast(message, 'success'); }

/** Fehler als Vollbild-Modal (nicht Toast!) */
function appError(message) {
    Swal.fire({ icon: 'error', title: 'Fehler', text: message });
}

/** Loesch-Bestätigung */
function appConfirmDelete(itemName) {
    return Swal.fire({
        title: 'Wirklich loeschen?',
        text: itemName + ' wird unwiderruflich geloescht.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Loeschen',
        cancelButtonText: 'Abbrechen'
    });
}

/** Generische Bestätigung */
function appConfirm(title, text) {
    return Swal.fire({
        title: title,
        text: text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ja',
        cancelButtonText: 'Abbrechen'
    });
}
```

### PITFALL: SweetAlert2-Rueckgabewert

```javascript
// FALSCH — Swal.fire() gibt IMMER ein Objekt zurueck (truthy!)
if (await appConfirm('Sicher?')) { /* wird IMMER ausgefuehrt */ }

// RICHTIG — .isConfirmed pruefen
const result = await appConfirm('Sicher?');
if (!result.isConfirmed) return;
```

### Page-Level Wrapper (optional)

```javascript
function showMessage(msg, type) {
    if (type === 'danger') type = 'error';
    appToast(msg, type);
}
```

---

## 12. Tooltip-System

Eigenes Tooltip-System mit `data-tooltip` Attribut statt nativem `title`.

### Verwendung

```html
<button data-tooltip="Bearbeiten"><i class="bi bi-pencil"></i></button>
<span class="status-dot" data-tooltip="Aktiv">✓</span>
```

### Implementierung

- Ein globales DOM-Element (`<div>`, `position: fixed`, `z-index: 99999`)
- Event-Delegation auf `[data-tooltip]` (mouseenter/mouseleave)
- **Viewport-Clamping:** Tooltip wird links verschoben wenn rechts ueber Bildschirmrand

### CSS

```css
.app-tooltip {
    position: fixed;
    white-space: nowrap;
    background: #1e293b;
    color: #fff;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 500;
    pointer-events: none;
    z-index: 99999;
    transition: opacity 0.15s;
}
```

### JS (Minimalbeispiel)

```javascript
(function() {
    const tip = document.createElement('div');
    tip.className = 'app-tooltip';
    tip.style.display = 'none';
    document.body.appendChild(tip);

    document.addEventListener('mouseenter', function(e) {
        const el = e.target.closest('[data-tooltip]');
        if (!el) return;
        tip.textContent = el.dataset.tooltip;
        tip.style.display = 'block';
        const rect = el.getBoundingClientRect();
        let left = rect.left + rect.width / 2 - tip.offsetWidth / 2;
        if (left + tip.offsetWidth > window.innerWidth - 8) {
            left = window.innerWidth - tip.offsetWidth - 8;
        }
        if (left < 8) left = 8;
        tip.style.left = left + 'px';
        tip.style.top = (rect.top - tip.offsetHeight - 6) + 'px';
    }, true);

    document.addEventListener('mouseleave', function(e) {
        if (e.target.closest('[data-tooltip]')) tip.style.display = 'none';
    }, true);
})();
```

> **Konvention:** Immer `data-tooltip="..."` statt `title="..."` — verhindert nativen Browser-Tooltip und ergibt einheitliches Styling.

---

## 13. Formulare

### Standard-Formular

```html
<div class="form-group mb-3">
    <label class="form-label">Bezeichnung</label>
    <input type="text" class="form-control" id="field" placeholder="...">
</div>

<div class="form-group mb-3">
    <label class="form-label">Auswahl</label>
    <select class="form-select" id="fieldSelect">
        <option value="">-- Bitte waehlen --</option>
    </select>
</div>

<div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" id="fieldCheck">
    <label class="form-check-label" for="fieldCheck">Option</label>
</div>
```

### Compact Form Row (horizontales Filter-/Eingabe-Band)

```css
.compact-form-row {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border: 1px solid #dee2e6;
    border-radius: var(--border-radius);
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    display: flex;
    gap: 0.75rem;
    align-items: flex-end;
    flex-wrap: wrap;
}
.compact-form-row .form-group { flex: 1; min-width: 0; }
.compact-form-row label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--secondary-color);
    margin-bottom: 0.2rem;
    display: block;
    white-space: nowrap;
}
```

### Groessen

```css
.form-control, .form-select {
    padding: 0.4rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 0.375rem;
    min-height: 38px;
}
@media (max-width: 768px) {
    .form-control, .form-select {
        min-height: 44px;       /* WCAG Touch Target */
        font-size: 16px;        /* Verhindert iOS Zoom */
    }
}
```

### Focus-States

```css
.form-control:focus, .form-select:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 0 0.2rem rgba(59, 89, 152, 0.25);
    outline: none;
}
```

### Validierung

```css
.form-control.is-invalid { border-color: #dc3545; }
.form-control.is-invalid:focus {
    box-shadow: 0 0 0 0.2rem rgba(220,53,69,0.25);
}
.form-control.is-valid { border-color: #28a745; }
```

---

## 14. Karten (Cards)

### Standard-Card

```html
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-list"></i> Titel</h6>
        <button class="btn btn-compact btn-primary">Aktion</button>
    </div>
    <div class="card-body">...</div>
</div>
```

### Hover-Card (z.B. Dashboard, Portal)

```css
.hover-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    border: 1px solid #f0f0f0;
    transition: all var(--transition-speed) ease;
}
.hover-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
```

### Kategorie-Karten (Auto-Fill Grid)

```css
.category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.25rem;
}
.category-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}
```

---

## 15. Loading-States

### Spinner (Bootstrap)

```html
<div class="text-center py-5">
    <div class="spinner-border spinner-border-sm me-2"></div>
    Lade Daten...
</div>
```

### Skeleton-Loader

```css
.skeleton {
    height: 20px;
    border-radius: 4px;
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 200% 100%;
    animation: skeleton-loading 1.5s infinite;
}
@keyframes skeleton-loading {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
```

### Verwendung

```html
<!-- Platzhalter waehrend AJAX-Laden -->
<div class="skeleton" style="width: 60%; margin-bottom: 0.5rem;"></div>
<div class="skeleton" style="width: 80%; margin-bottom: 0.5rem;"></div>
<div class="skeleton" style="width: 40%;"></div>
```

---

## 16. Animationen & Transitions

### Standard-Werte

```css
transition: all 0.3s ease;     /* Standard (--transition-speed) */
transition: all 0.2s ease;     /* Schnell (Hover-Effekte) */
transition: all 0.15s;         /* Sehr schnell (UI-Interaktionen) */
```

### Haeufige Transforms

```css
/* Lift bei Hover */
.btn:hover { transform: translateY(-1px); }

/* Chevron drehen bei Aufklappen */
.chevron { transition: transform 0.2s ease; }
[aria-expanded="true"] .chevron { transform: rotate(180deg); }
```

### Pulsierender Dot (Unsaved-Indicator)

```css
.unsaved-dot {
    width: 8px; height: 8px;
    background: #f59e0b;
    border-radius: 50%;
    display: inline-block;
    animation: unsavedPulse 1.5s infinite;
}
@keyframes unsavedPulse {
    0%, 100% { opacity: 1; }
    50%      { opacity: 0.4; }
}
```

### Unsaved-Changes-Banner

```html
<div id="unsavedBadge" class="d-none align-items-center gap-2 px-3 py-2 rounded-3"
     style="background: #fef3c7; border: 1px solid #fcd34d;">
    <span class="unsaved-dot"></span>
    <small class="fw-semibold text-dark">Ungespeicherte Aenderungen</small>
</div>
```

### Status-Indicator-Dots

```css
.status-dot {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    cursor: default;
    transition: transform 0.15s;
}
.status-dot:hover { transform: scale(1.15); }
.status-dot.active   { background: #3b82f6; color: #fff; }
.status-dot.inactive { background: #f1f5f9; color: #cbd5e1; }
```

---

## 17. Icons (Bootstrap Icons)

### Haeufig verwendete Icons

```
Navigation:  bi-house, bi-list-ul, bi-x, bi-chevron-down, bi-arrow-left
Aktionen:    bi-plus-lg, bi-trash, bi-pencil-square, bi-save, bi-check-lg
Status:      bi-check-circle, bi-exclamation-triangle, bi-x-circle, bi-info-circle
Dateien:     bi-file-pdf, bi-file-word, bi-file-earmark-spreadsheet
UI:          bi-search, bi-download, bi-share, bi-eye, bi-grip-vertical
```

### Konventionen

- Icons immer als `<i class="bi bi-..."></i>`
- Icon-Only-Buttons → `data-tooltip="..."` fuer Beschreibung
- Icon + Text: `<i class="bi bi-plus-lg me-1"></i> Hinzufuegen`
- Konsistente Icon-Wahl: gleiche Aktion = gleiches Icon auf allen Seiten

---

## 18. Page-Specific CSS

Mechanismus fuer seitenspezifische Styles ohne separate CSS-Dateien.

### Seite definiert Variable vor Header-Include

```php
<?php
$page_specific_css = <<<'CSS'
    .my-component { max-width: 600px; }
    .my-highlight { background: #fff3cd; }
CSS;
include 'header.inc.php';
?>
```

### Header gibt es als `<style>`-Block aus

```php
<?php if (!empty($page_specific_css)): ?>
<style><?= $page_specific_css ?></style>
<?php endif; ?>
```

> **Vorteile:**
> - Kein separates CSS-File pro Seite noetig
> - Styles sind nah am PHP-Code der Seite
> - Werden nur auf der jeweiligen Seite geladen
> - WICHTIG: Variable muss **vor** dem Header-Include definiert werden

---

## 19. Flex-Chain fuer scrollbare Tabellen

Damit eine Tabelle den verfuegbaren Viewport-Platz ausfuellt und intern scrollt, muss **jeder Container in der Kette** Flex-Properties haben:

```css
.main-content-wrapper,
.page-form,
.table-wrapper {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0 !important;   /* WICHTIG: verhindert Overflow */
}
.table-responsive {
    flex: 1 1 auto;
    min-height: 0;
    overflow: auto !important;
}
```

> **Regel:** Jeder Eltern-Container vom Viewport bis zum scrollbaren Bereich braucht `flex: 1 1 auto` und `min-height: 0`. Fehlt ein einziges Glied in der Kette, scrollt die gesamte Seite statt nur die Tabelle.

---

## 20. Barrierefreiheit (WCAG)

### Touch-Targets (Mobile)

```css
min-height: 44px;   /* WCAG AA */
min-width: 44px;
```

### Farbkontrast

- Minimum **4.5:1** fuer Text auf Hintergrund (WCAG AA)
- Text auf hellem Hintergrund: `#343a40`
- Text auf dunklem Hintergrund: `#ffffff`

### Focus-States

```css
.btn:focus, .form-control:focus {
    outline: 2px solid var(--secondary-color);
    outline-offset: 2px;
}
```

### Keyboard-Navigation

- `tabindex="-1"` fuer dekorative Buttons → Tab springt nur zwischen Eingabefeldern
- **Escape** schliesst Panels und Modals
- **Enter** navigiert zwischen Inputs oder loest Submit aus
- Alle interaktiven Elemente muessen per Tastatur erreichbar sein

### Semantisches HTML

- `<button>` statt `<div onclick="...">` fuer klickbare Elemente
- `<label for="...">` fuer alle Formularfelder
- `aria-expanded="true/false"` fuer aufklappbare Bereiche
- `role="alert"` fuer Fehlermeldungen

---

## 21. CSRF & Sicherheit

### Token generieren (einmal pro Session)

```php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

### In HTML-Formularen

```html
<input type="hidden" name="csrf_token"
       value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
```

### AJAX-Requests (Header oder Body)

```javascript
// Variante 1: Custom Header
fetch(url, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('input[name="csrf_token"]')?.value
    },
    body: JSON.stringify(data)
});

// Variante 2: Im Body mitsenden
const formData = new FormData();
formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value);
```

### Serverseitige Validierung

```php
$token = $_POST['csrf_token']
    ?? $_SERVER['HTTP_X_CSRF_TOKEN']
    ?? '';

if (!hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'CSRF Token ungueltig']));
}
```

### Weitere Sicherheitsregeln

- **Output Escaping:** `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` fuer alle Ausgaben
- **Prepared Statements:** Nie Variablen direkt in SQL — immer Parameterized Queries
- **Content-Type:** AJAX-Antworten mit `header('Content-Type: application/json')` senden
- **Session-Config:** `session.cookie_httponly = true`, `session.cookie_secure = true` (HTTPS)

---

## 22. Designprinzipien

1. **Konsistenz** — CSS-Variablen und Komponenten-Klassen ueberall gleich verwenden
2. **Mobile-First** — 44px Touch-Targets, `font-size: 16px` auf Mobile, Cards statt Tabellen
3. **Slide-Panel statt Modal** — Bearbeitung im rechten Panel, Datentabelle bleibt sichtbar
4. **Sofortiges Feedback** — Toast bei jeder Aktion, Hover-Effekte, Loading-States
5. **Kompakte UI** — Kleine Schriftgroessen, wenig Padding, viel Information auf einen Blick
6. **Progressive Enhancement** — HTML/CSS funktioniert, JS erweitert
7. **Page-Specific CSS** — Styles nur dort laden wo sie gebraucht werden
8. **Hybrid-Rows** — Tabellenzeilen sind klickbar, oeffnen Panel, zeigen Selection-State
9. **Auto-Save** — Dirty-Flag tracken, bei Panel-Close oder Datensatz-Wechsel automatisch speichern
10. **Keine verschachtelten Modals** — SweetAlert2-Confirms statt Modal-in-Modal

---

## Schnellreferenz: Neues Feature erstellen

```
 1. PHP-Seite erstellen
 2. $page_specific_css definieren (vor Header-Include)
 3. Tabelle mit .hybrid-row + data-id Attributen
 4. Slide-Panel (.edit-panel) rechts
 5. Mobile-Cards als Alternative (#mobileContainer)
 6. AJAX-Endpunkte fuer Laden/Speichern/Loeschen
 7. appToast() fuer Feedback, appConfirmDelete() fuer Loeschungen
 8. CSRF-Token in allen Formularen und AJAX-Requests
 9. data-tooltip="..." fuer Icon-Button-Beschreibungen
10. Testen: Desktop (Tabelle + Panel), Mobile (Cards), Keyboard-Navigation
```

---

## Bekannte Pitfalls

| Problem | Loesung |
|---------|---------|
| Sticky Header + `border-collapse: collapse` → Scroll-Bleed | `border-collapse: separate` + `box-shadow` statt `border-bottom` |
| Flex-Chain unterbrochen → ganze Seite scrollt | **Jeder** Container in der Kette braucht `flex: 1 1 auto` + `min-height: 0` |
| Mobile-Card-Builder erzeugt kaputte Cards | Nur sichtbare Hauptzeilen verarbeiten, versteckte Detail-Rows ausschliessen |
| `Swal.fire()` Rueckgabe ist immer truthy | Immer `.isConfirmed` pruefen, nie das Objekt direkt als Boolean verwenden |
| iOS Zoom bei Input-Focus | `font-size: 16px` auf Mobile-Inputs setzen |
| `title="..."` erzeugt doppelte Tooltips | Immer `data-tooltip="..."` verwenden |
| LEFT JOIN + Formular erzeugt Phantom-Records | `hasNonZeroValues()`-Guard vor INSERT/UPDATE |
| `bind_param('i', null)` speichert 0, nicht NULL | SQL-Literal `NULL` verwenden oder `COALESCE()` |
