# Aufgabe: Tooltip-System vereinheitlichen (MSV Wilen Webapp)

## Ziel
Alle `title`-Attribute in der gesamten Webapp durch ein einheitliches, gestyltes Tooltip-System ersetzen, das auf echten DOM-Elementen basiert (kein CSS `::after` Pseudo-Element, kein Browser-Default-Tooltip).

## Referenz-Implementierung
Die fertige Implementierung findest du in `admin/nav_admin.php`. Das Pattern:

### 1. CSS-Klasse (einmal global definieren)
```css
.msv-tooltip {
  position: fixed; white-space: nowrap;
  background: #1e293b; color: #fff; padding: 6px 12px;
  border-radius: 6px; font-size: 0.78rem; font-weight: 500;
  pointer-events: none; z-index: 99999;
}
```

### 2. JavaScript (einmal pro Seite)
```javascript
const $msvTip = $('<div class="msv-tooltip">').appendTo('body').hide();

$(document).on('mouseenter', '[data-tooltip]', function() {
  const text = this.getAttribute('data-tooltip');
  if (!text) return;
  $msvTip.text(text).show();
  const rect = this.getBoundingClientRect();
  const tw = $msvTip.outerWidth();
  let left = rect.left + rect.width / 2 - tw / 2;
  if (left + tw > window.innerWidth - 8) left = window.innerWidth - tw - 8;
  if (left < 8) left = 8;
  $msvTip.css({ top: rect.bottom + 8, left: left });
});

$(document).on('mouseleave', '[data-tooltip]', function() {
  $msvTip.hide();
});
```

### 3. HTML: `title` → `data-tooltip`
```html
<!-- Vorher -->
<button title="Bearbeiten">...</button>

<!-- Nachher -->
<button data-tooltip="Bearbeiten">...</button>
```

## Schritt-für-Schritt Anleitung

### Schritt 1: Globale CSS + JS Datei erstellen
Erstelle `inc/js/msv-tooltips.js` und `inc/css/msv-tooltips.css` (oder füge beides in bestehende globale Dateien ein, z.B. `msv-styles.css` und ein neues Script-Tag in `header.inc.php`).

Damit das Tooltip-System automatisch auf allen Seiten verfügbar ist, ohne es pro Seite einzubinden.

### Schritt 2: Alle Dateien durchsuchen
Durchsuche alle `.php`-Dateien im `inc/`-Verzeichnis (und Unterverzeichnisse) sowie `admin/` nach:

1. **`title="`** Attribute in HTML-Elementen → ersetze durch `data-tooltip="`
2. **CSS Pseudo-Element Tooltips** wie `.flag-dot::after { content: attr(data-tooltip); ... }` → entfernen, da das globale JS-System das übernimmt
3. **Bestehende `.flag-tooltip`** CSS-Klassen in `mitgliederverwaltung.php` und `jmdefinition.php` → durch `.msv-tooltip` ersetzen (einheitlicher Name)
4. **Bestehende tooltip JS-Handler** wie `$(document).on('mouseenter', '.flag-dot[data-tooltip]', ...)` → entfernen, da das globale System `[data-tooltip]` abfängt
5. **`title`-Attribute in dynamisch generiertem JavaScript** (z.B. in Template-Literals) → ebenfalls `data-tooltip` verwenden

### Schritt 3: Dateien die sicher betroffen sind
- `inc/mitgliederverwaltung.php` – hat eigene `.flag-tooltip` CSS + JS, umstellen auf global
- `inc/jmdefinition.php` – hat eigene `.flag-tooltip` CSS + JS, umstellen auf global  
- `admin/nav_admin.php` – bereits umgestellt, aber `.msv-tooltip` CSS + JS kann nach global verschoben werden
- `inc/navigation.inc.php` – prüfen ob `title`-Attribute vorhanden
- `inc/home.php` – prüfen
- Alle Dateien in `inc/` die Tabellen, Buttons oder Icons mit `title` rendern

### Schritt 4: Sonderfälle beachten
- **Dynamisch generierte Elemente** (via jQuery/JS): Funktioniert automatisch, da `$(document).on('mouseenter', '[data-tooltip]', ...)` delegiert ist
- **Bootstrap-Tooltips**: Falls irgendwo `data-bs-toggle="tooltip"` verwendet wird, ebenfalls durch `data-tooltip` ersetzen und Bootstrap-Tooltip-Initialisierung entfernen
- **PHP-generierte `title`-Attribute**: z.B. in `navigation.inc.php` oder `load_*.php` Dateien

### Schritt 5: Testen
Nach der Umstellung sicherstellen, dass:
- Tooltips nie vom Container abgeschnitten werden (position: fixed)
- Tooltips zentriert unter dem Element erscheinen
- Tooltips am Viewport-Rand nach links verschoben werden
- Kein doppelter Tooltip erscheint (Browser-Default + custom)

## Nicht ändern
- `title`-Tags im `<head>` (Seitentitel) – nur HTML-Element-Attribute
- `title`-Attribute auf `<abbr>` oder `<dfn>` Elementen (falls vorhanden, Accessibility)
- Alles ausserhalb von `inc/` und `admin/` (z.B. `portal/`, `login.php`)
