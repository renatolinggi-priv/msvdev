# UI/UX Audit – jahresmeisterschaft.msvwilen.ch

**Datum:** 24. Februar 2026  
**Analyse-Methode:** Code-Review aller PHP-Seiten, CSS-Dateien und Templates  
**Geprüfte Dateien:** ~40 Seiten im `inc/`-Verzeichnis, ~12 Portal-Seiten, 4 CSS-Dateien, Header/Footer-Templates, Login/Register

---

## Zusammenfassung

Die Applikation hat bereits eine solide Basis mit `msv-styles.css` als zentralem Stylesheet. Allerdings haben sich über die Zeit viele Inkonsistenzen eingeschlichen – hauptsächlich durch seitenspezifische Inline-Styles (`$page_specific_css`), doppelte CSS-Definitionen und unterschiedliche Layout-Muster für ähnliche Seiten. Die grössten Hebel für eine optische Vereinheitlichung liegen in:

1. **CSS-Variablen bereinigen** (insb. `--primary-color`)
2. **Container-Strukturen standardisieren**
3. **Button-Patterns vereinheitlichen**
4. **Seitenspezifische CSS-Overrides reduzieren**
5. **Portal vs. Admin Design annähern**

---

## 1. CSS-Variablen — Widersprüchliche Definitionen

### Problem
| Variable | msv-styles.css | portal_header.php | cup.css |
|----------|---------------|-------------------|---------|
| `--primary-color` | `#dee2e6` (Hellgrau!) | `#3b5998` (Blau) | `--cup4-primary: #2c3e50` |
| `--nav-height` | nicht definiert | `60px` | – |
| Nav-Height (hardcoded) | `body padding-top: 76px` | `body padding-top: 60px` | – |

**`--primary-color: #dee2e6`** ist das grösste Problem: Eine "Primary"-Farbe, die eigentlich ein helles Grau (Bootstrap `$gray-300`) ist, ist irreführend. Im Portal wird `#3b5998` als echte Primärfarbe verwendet, die Cup-Seite hat nochmals einen eigenen Satz Variablen.

### Empfehlung
- **Einheitliche Primärfarbe** definieren (z.B. `#3b5998` oder ein MSV-Vereinsblau)
- **Alle Custom Properties zentral** in `msv-styles.css :root` zusammenführen
- Cup-spezifische Variablen können als Alias bleiben, sollten aber auf die globalen verweisen
- `--nav-height` einheitlich auf 56px oder 60px setzen

---

## 2. Container-Strukturen — 5 verschiedene Muster

### Aktueller Zustand

| Seite | Container-Struktur |
|-------|-------------------|
| **home.php** | `main-content-wrapper` direkt (kein row/col) |
| **heimrang.php, schuetzenabr.php** | `container-fluid > row > col-xl-10 > main-content-wrapper > content-background` |
| **monatsblatt.php** | `container-fluid > row > col-xl-8 > main-content-wrapper > content-background` |
| **cup.php** | `container-fluid` direkt (kein main-content-wrapper) |
| **wichtigetermine.php, endresultate.php** | Gleiche Struktur wie heimrang, aber mit `height: 100vh` Override |

### Probleme
- Unterschiedliche `col-xl-*` Breiten (8, 10, 12) ohne erkennbares System
- `home.php` ist in der `header.inc.php`-Struktur (`container-fluid > row > col-12`) eingebettet und fügt NOCHMALS einen `main-content-wrapper` hinzu
- `cup.php` ignoriert das Wrapper-Pattern komplett

### Empfehlung
- Standard-Template definieren: `main-content-wrapper > content-background` für alle Seiten
- Einheitliche Spaltenbreite: `col-xl-11 col-lg-12` oder einfach `col-12` mit `max-width` auf dem Wrapper
- Fullscreen-Seiten (Termine, Resultate) per CSS-Klasse steuern statt per Inline-Override

---

## 3. Seiten-Überschriften — Chaos

### Aktueller Zustand
| Seite | Element | Klasse | Farbe |
|-------|---------|--------|-------|
| heimrang.php | `<h2>` | `h4 mb-0` | `color: var(--secondary-color)` (inline) |
| cup.php | `<h4>` | `mb-0` | `color: var(--cup4-primary)` (inline) |
| monatsblatt.php | `<h2>` | `h4 mb-0` | `color: var(--secondary-color)` (inline) |
| home.php | – | hero-title | Gradient-Text |
| jmdefinition.php | – | Eigenes Layout | – |
| Portal-Seiten | `<h1>` | `portal-page-header` | `#2d3748` |

### Probleme
- Verschiedene HTML-Elemente (h1, h2, h4) für gleiche Funktion
- Farbe wird immer inline gesetzt statt per Klasse
- `d-none d-md-flex` auf dem Desktop-Header: auf Mobile verschwindet der Titel ganz (wird nur in der Navbar angezeigt, was OK ist, aber nicht alle Seiten machen das konsistent)

### Empfehlung
- Einheitliche `.page-header`-Klasse definieren
- Immer `<h2>` mit konsistenter Klasse und Icon-Pattern
- Farbe über CSS, nicht inline

---

## 4. Button-Patterns — 4 verschiedene Systeme

### Aktueller Zustand
1. **`btn-compact`** (pastel backgrounds): `btn-compact btn-info`, `btn-compact btn-success` etc.
2. **`btn-compact-standard`** (outline): `btn-compact-standard btn-outline-info`
3. **Standard Bootstrap**: `btn btn-success`, `btn btn-outline-secondary`
4. **Custom inline**: diverse inline-styles für spezielle Buttons

### Probleme
- `header.inc.php` überschreibt globale `.btn`-Styles (padding: 0.4rem 1.2rem, border-radius: 8px) — das kollidiert mit `msv-styles.css` (padding: 0.625rem 1.25rem, border-radius: 0.5rem)
- `btn-compact` hat border: none, was für einige Varianten gewünscht, für andere aber nicht passend ist
- PDF/Export-Buttons sind mal grün (btn-success), mal outline-success, mal btn-compact.btn-dark (grünlicher Hintergrund)

### Empfehlung
- **Ein einziges Button-System**: Standard-Bootstrap-Buttons + max. 1 Custom-Variante
- `header.inc.php` sollte KEINE `.btn`-Overrides enthalten
- Für Aktions-Toolbars: einheitlich `btn-compact-standard` oder `btn-outline-*`
- PDF-Buttons immer gleich stylen (z.B. immer `btn-outline-success`)

---

## 5. Aktions-Bereich — 3 verschiedene Muster

### Aktueller Zustand
| Pattern | Seiten |
|---------|--------|
| **Collapsible Action-Card** | heimrang, kantirang, jmrang, schuetzenabr |
| **Button-Toolbar** | ältere Seiten, via `button-toolbar` Klasse |
| **Slide-Panel** | jmdefinition, endresultate |
| **Inline-Buttons** | cup, mitgliederverwaltung, sieger |

### Empfehlung
- **Standardisieren auf 2 Patterns**: 
  - Action-Card (collapsible) für Seiten mit vielen Optionen
  - Einfache Button-Reihe für Seiten mit wenigen Aktionen
- Das Slide-Panel bei jmdefinition kann bleiben, da es eine spezielle UX bietet

---

## 6. Tabellen-Styling — Doppelte Definitionen

### Problem
Tabellen-Styles sind an 3 Stellen definiert, die sich teilweise widersprechen:

1. **msv-styles.css**: Basis-Tabellen + spezifische IDs (#heimresultateTabelle, #kantiresultateTabelle)
2. **resultate-unified.css**: Überschreibt fast alles mit `!important`
3. **Seitenspezifische CSS**: z.B. jmrang.php, wichtigetermine.php definieren nochmals `.table thead th`

### Konkrete Konflikte
- `msv-styles.css`: `border-right: 2px solid #dee2e6` für sticky erste Spalte
- `resultate-unified.css`: `border-right: 1px solid #dee2e6` für gleiche Spalte
- `.table-title` z-index: 10 in msv-styles.css, 8 in jmdefinition.php, 100 in jmrang.php
- `.table-responsive max-height`: `calc(100vh - 350px)` in msv-styles.css vs `calc(100vh - 250px)` in Mobile-Breakpoint

### Empfehlung
- **resultate-unified.css in msv-styles.css integrieren** und `!important`-Overrides eliminieren
- Z-Index-Hierarchie dokumentieren und einhalten:
  - Navbar: 1030
  - Dropdown: 1040
  - Table-title: 20
  - Table thead: 10
  - Sticky columns: 5
- Seitenspezifische Tabellen-Overrides auf das Minimum reduzieren

---

## 7. Modal-Styling — 2 Definitionen

### Problem
| Quelle | border-radius | Padding | Background |
|--------|--------------|---------|------------|
| header.inc.php | 15px | 1rem/1.5rem | linear-gradient |
| resultate-unified.css | 0.75rem | 1.25rem | linear-gradient |

### Empfehlung
- Eine einzige Modal-Definition in msv-styles.css
- border-radius: 0.75rem (konsistent mit dem Rest)
- header.inc.php `<style>`-Block komplett entfernen oder auf absolute Minima reduzieren

---

## 8. Portal vs. Admin — Zwei Welten

### Aktueller Zustand
Der Admin-Bereich und das Mitgliederportal sehen komplett unterschiedlich aus:

| Aspekt | Admin | Portal |
|--------|-------|--------|
| **Navbar** | Dunkel/komplex, dynamisch aus DB | Weiss/clean, hardcoded |
| **Body background** | `#f8f9fa` | `#f5f6fa` |
| **Primärfarbe** | `#dee2e6` (grau) | `#3b5998` (blau) |
| **Card-Klassen** | `content-wrapper`, `table-wrapper` | `portal-card` |
| **Header-Pattern** | h2.h4 mit Icon | h1 mit Subtitle |
| **CSS-Quelle** | msv-styles.css + 3 weitere | Alles inline in portal_header.php |

### Empfehlung
- Portal-CSS in eigene Datei `portal-styles.css` auslagern
- Gemeinsame Basis-Variablen teilen (Farben, Fonts, Spacing)
- Card-Patterns angleichen: `portal-card` ≈ `content-wrapper`
- Body-Background vereinheitlichen

---

## 9. Mobile-CSS — Copy-Paste-Muster

### Problem
Folgendes CSS wird in **mindestens 6 Seiten** identisch wiederholt via `$page_specific_css`:

```css
@media (max-width: 767.98px) {
    .form-control, .form-select { min-height: 48px !important; font-size: 16px !important; }
    .btn { min-height: 48px !important; font-size: 16px !important; }
}
```

Betroffen: heimrang, monatsblatt, wanderpreise, standbelegung, schuetzenabr, jmrang

### Empfehlung
- Diese Regeln **einmal** in `msv-styles.css` definieren
- Seitenspezifische Mobile-CSS nur für tatsächlich einzigartige Layouts

---

## 10. Home-Page — Quick Access Cards Inkonsistenzen

### Probleme
- "Munitionverkauf" und "Endschiessen Stichausgabe" nutzen beide das gleiche rote Warenkorb-Icon (`bi-cart-check`) mit identischem Gradient — nicht unterscheidbar
- Leere `<p class="quick-access-description">` Tags bei den meisten Karten (unnötiger Whitespace)
- "Zur Erfassung" vs "Zur Erfassung " (mit Leerzeichen) vs "Zur CUP Resultateerfassung" — inkonsistente Link-Texte
- Leerzeichen vor `<i class="bi bi-arrow-right">` fehlt bei einigen Links

### Empfehlung
- Jede Karte braucht ein **eigenes, unterscheidbares Icon** und eine **eigene Farbe**
- Entweder alle Descriptions füllen oder alle entfernen
- Link-Text vereinheitlichen: "Öffnen →" oder "Zur Erfassung →"

---

## 11. `!important`-Epidemie

### Zählung
| Datei | Anzahl `!important` |
|-------|-------------------|
| msv-styles.css | ~120 |
| resultate-unified.css | ~95 |
| Seitenspezifische CSS (geschätzt) | ~50+ |

### Problem
Die massive Nutzung von `!important` macht das CSS extrem schwer wartbar. Jede neue Änderung erfordert wieder `!important`, was einen Teufelskreis erzeugt.

### Empfehlung
- Schrittweises Refactoring: Specificity durch bessere Selektoren statt `!important`
- `.content-wrapper .table-wrapper` statt `.table-wrapper` + `!important`
- BEM-Methodik oder ähnliches einführen

---

## 12. Kleinere Inkonsistenzen

### Breadcrumb
- In `msv-styles.css` definiert mit `→` als Separator
- Im Code steht `â†'` (kaputte UTF-8 Kodierung im CSS-Kommentar) — sollte geprüft werden ob es im Browser korrekt angezeigt wird

### Spacing
- `mb-3` vs `mb-4` für gleiche Abstände zwischen Sektionen
- Padding-Werte: 2.5rem, 2rem, 1.5rem, 1.25rem, 1rem für ähnliche Container

### Schriftgrössen
- Body: `0.9em`
- Tabellen-Header: `0.75rem`
- Buttons: `0.85rem` (msv-styles) vs `0.875rem` (andere)
- Nav-Links Portal: `0.82rem`

### Farbinkonsistenzen bei Outline-Buttons
- `btn-outline-info`: Mal `#0ea5e9`, mal `#17a2b8`
- `btn-outline-success`: Mal `#22c55e`, mal `#28a745`
- Das sind Tailwind- vs Bootstrap-Farben gemischt

---

## 13. Priorisierter Massnahmenplan

### Phase 1: Quick Wins (1-2 Tage)
1. ✅ `--primary-color` korrigieren auf eine echte Primärfarbe
2. ✅ Mobile-CSS-Duplikate in msv-styles.css zentralisieren
3. ✅ header.inc.php `<style>`-Block bereinigen (`.btn`-Overrides entfernen)
4. ✅ Home-Page Quick Access Cards: Icons, Farben, Descriptions vereinheitlichen
5. ✅ Breadcrumb-Encoding prüfen

### Phase 2: CSS-Konsolidierung (2-3 Tage)
6. resultate-unified.css in msv-styles.css integrieren
7. `!important` schrittweise reduzieren
8. Z-Index-Hierarchie dokumentieren und bereinigen
9. Einheitliche Farb-Palette definieren (keine Tailwind/Bootstrap-Mischung)
10. Modal-Styling vereinheitlichen

### Phase 3: Layout-Standardisierung (3-5 Tage)
11. Container-Strukturen standardisieren
12. Page-Header-Komponente erstellen
13. Button-System auf max. 2 Varianten reduzieren
14. Aktions-Bereiche vereinheitlichen

### Phase 4: Portal-Angleichung (2-3 Tage)
15. Portal-CSS auslagern
16. Gemeinsame Variablen/Basis-Styles teilen
17. Design-Language annähern (gleiche Cards, gleiche Buttons)

---

## Fazit

Der Gesamtaufwand für eine vollständige Vereinheitlichung liegt bei ca. **8-13 Arbeitstagen**, wobei die Quick Wins (Phase 1) den grössten optischen Effekt bei geringstem Aufwand haben. Die funktionale Qualität der Applikation ist gut – es geht primär um **visuelle Konsistenz und Wartbarkeit des CSS**.
