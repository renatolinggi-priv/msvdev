<?php
/**
 * pdf_theme.php — Zentrale, bearbeitbare Stilquelle für alle Dompdf-PDFs (MSV Wilen)
 *
 * Ziel: ein ruhiges, einheitliches Erscheinungsbild über alle PDFs, das im
 * Admin-Bereich (inc/pdf_design.php) ohne Code-Änderung angepasst werden kann.
 *
 *  - Palette + ein paar Layout-Knöpfe liegen in der DB-Tabelle `pdf_theme_settings`.
 *  - Fehlt die Tabelle / ein Wert / die DB-Verbindung, greifen automatisch die
 *    eingebauten Defaults  -> ein PDF kann NIE wegen einer Einstellung kaputtgehen.
 *  - Werte werden streng validiert (Hex-Farbe bzw. Ganzzahl mit Grenzen), bevor sie
 *    in CSS landen.
 *
 * Verwendung:
 *   require_once __DIR__ . '/../pdf/pdf_theme.php';
 *   $html = '<style>' . pdf_theme_css() . $localOverrides . '</style>';
 *   $logo = pdf_logo_base64('dat/MSVWilen_Logo.jpg');
 *
 * WICHTIG: Dieses Theme fasst NUR CSS an. Orientierung (Hoch-/Querformat) wird
 * weiterhin im PHP über $dompdf->setPaper('A4', 'landscape'|'portrait') gesetzt
 * und bleibt pro Datei unverändert.
 */

/**
 * Schema aller einstellbaren Theme-Werte: Typ, Default und (bei Zahlen) Grenzen.
 * Single Source of Truth für Defaults, Validierung und die Editor-Seite.
 */
function pdf_theme_schema(): array
{
    return [
        // --- Allgemein ---
        'text'         => ['type' => 'color', 'default' => '#2d3748', 'label' => 'Text',                 'group' => 'Allgemein'],
        'muted'        => ['type' => 'color', 'default' => '#64748b', 'label' => 'Gedämpft / Fusszeile', 'group' => 'Allgemein'],
        'accent'       => ['type' => 'color', 'default' => '#3b5998', 'label' => 'Akzent (Titel/Total)', 'group' => 'Allgemein'],
        // --- Tabelle ---
        'head_bg'      => ['type' => 'color', 'default' => '#eef2f7', 'label' => 'Kopf-Hintergrund',     'group' => 'Tabelle'],
        'head_text'    => ['type' => 'color', 'default' => '#2d3748', 'label' => 'Kopf-Text',            'group' => 'Tabelle'],
        'head_line'    => ['type' => 'color', 'default' => '#cbd5e0', 'label' => 'Kopf-/Fusslinie',      'group' => 'Tabelle'],
        'border'       => ['type' => 'color', 'default' => '#e2e8f0', 'label' => 'Zellrahmen',           'group' => 'Tabelle'],
        'zebra'        => ['type' => 'color', 'default' => '#f8fafc', 'label' => 'Zebra (gerade Zeilen)', 'group' => 'Tabelle'],
        'total_bg'     => ['type' => 'color', 'default' => '#f1f5f9', 'label' => 'Total-Hintergrund',    'group' => 'Tabelle'],
        // --- Medaillen ---
        'gold_bg'      => ['type' => 'color', 'default' => '#fdf6e3', 'label' => 'Gold Hintergrund',     'group' => 'Medaillen'],
        'gold_tx'      => ['type' => 'color', 'default' => '#8a6d1c', 'label' => 'Gold Text',            'group' => 'Medaillen'],
        'silver_bg'    => ['type' => 'color', 'default' => '#f1f1f1', 'label' => 'Silber Hintergrund',   'group' => 'Medaillen'],
        'silver_tx'    => ['type' => 'color', 'default' => '#6b7280', 'label' => 'Silber Text',          'group' => 'Medaillen'],
        'bronze_bg'    => ['type' => 'color', 'default' => '#f7ede2', 'label' => 'Bronze Hintergrund',   'group' => 'Medaillen'],
        'bronze_tx'    => ['type' => 'color', 'default' => '#9c6b3f', 'label' => 'Bronze Text',          'group' => 'Medaillen'],
        // --- Status ---
        'win'          => ['type' => 'color', 'default' => '#2f855a', 'label' => 'Gewinner (Cup)',       'group' => 'Status'],
        'struck'       => ['type' => 'color', 'default' => '#c0392b', 'label' => 'Gestrichen/Verlierer', 'group' => 'Status'],
        // --- Layout ---
        'logo_width'   => ['type' => 'int', 'default' => 120, 'min' => 40, 'max' => 300, 'label' => 'Logo-Breite (px)',       'group' => 'Layout'],
        'base_font'    => ['type' => 'int', 'default' => 9,   'min' => 6,  'max' => 14,  'label' => 'Basis-Schriftgrösse (px)', 'group' => 'Layout'],
        'border_width' => ['type' => 'int', 'default' => 1,   'min' => 0,  'max' => 4,   'label' => 'Rahmenstärke (px)',       'group' => 'Layout'],
    ];
}

/** Reine Default-Palette (key => default-Wert). */
function pdf_theme_defaults(): array
{
    $out = [];
    foreach (pdf_theme_schema() as $key => $def) {
        $out[$key] = $def['default'];
    }
    return $out;
}

/**
 * Fertige 1-Klick-Presets. Werden auf der Editor-Seite angeboten; danach kann
 * der Benutzer einzelne Werte weiter anpassen. (Nur Abweichungen vom Default
 * angeben — der Rest wird mit den Defaults aufgefüllt.)
 */
function pdf_theme_presets(): array
{
    $defaults = pdf_theme_defaults();
    return [
        'hell_minimal' => [
            'label'  => 'Hell & minimal',
            'values' => $defaults,
        ],
        'dezent_markentreu' => [
            'label'  => 'Dezent markentreu',
            'values' => array_merge($defaults, [
                'head_bg'   => '#3b5998', // Vereins-Blau als Kopfband
                'head_text' => '#ffffff',
                'head_line' => '#2f4470',
                'border'    => '#dbe0e6',
                'zebra'     => '#f5f7fa',
                'total_bg'  => '#eef2f7',
            ]),
        ],
    ];
}

/**
 * Validiert/normalisiert einen einzelnen Wert anhand seiner Schema-Definition.
 * Ungültige Werte fallen auf den Default zurück (CSS-Injection-sicher).
 */
function pdf_theme_sanitize_value($val, array $def)
{
    if ($def['type'] === 'color') {
        if (is_string($val) && preg_match('/^#[0-9a-fA-F]{6}$/', $val)) {
            return strtolower($val);
        }
        return $def['default'];
    }
    // int
    $n = is_numeric($val) ? (int) $val : $def['default'];
    if (isset($def['min'])) { $n = max($def['min'], $n); }
    if (isset($def['max'])) { $n = min($def['max'], $n); }
    return $n;
}

/**
 * Liest die gespeicherten Roh-Werte aus `pdf_theme_settings`.
 * Greift auf PDO (getDB) zurück, sonst auf die mysqli-Verbindung ($conn).
 * Schlägt etwas fehl (keine DB / keine Tabelle), wird [] zurückgegeben.
 * Kein DDL hier — die Tabelle wird von der Editor-Seite angelegt.
 */
function pdf_theme_settings_load(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $values = [];
    try {
        if (function_exists('getDB')) {
            $pdo  = getDB();
            $stmt = $pdo->query('SELECT skey, svalue FROM pdf_theme_settings');
            if ($stmt) {
                $values = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
            }
        } else {
            global $conn;
            if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
                $res = @$conn->query('SELECT skey, svalue FROM pdf_theme_settings');
                if ($res instanceof mysqli_result) {
                    while ($row = $res->fetch_assoc()) {
                        $values[$row['skey']] = $row['svalue'];
                    }
                    $res->free();
                }
            }
        }
    } catch (\Throwable $e) {
        $values = []; // PDF darf nie an einer Einstellung scheitern
    }
    $cache = $values;
    return $cache;
}

/**
 * Liefert die effektive, validierte Palette (Defaults überlagert von DB-Werten).
 * @return array<string,string|int>
 */
function pdf_theme_palette(): array
{
    static $palette = null;
    if ($palette !== null) {
        return $palette;
    }
    $schema = pdf_theme_schema();
    $stored = pdf_theme_settings_load();
    $palette = [];
    foreach ($schema as $key => $def) {
        $palette[$key] = pdf_theme_sanitize_value($stored[$key] ?? null, $def);
    }
    return $palette;
}

/**
 * Legt die Settings-Tabelle an (selbstheilend). Von der Editor-Seite genutzt.
 * Akzeptiert eine PDO-Instanz; ohne Argument wird getDB() verwendet.
 */
function pdf_theme_ensure_table(?PDO $pdo = null): void
{
    $pdo = $pdo ?: getDB();
    $pdo->exec('CREATE TABLE IF NOT EXISTS pdf_theme_settings (
        skey   VARCHAR(64)  NOT NULL,
        svalue VARCHAR(64)  NOT NULL,
        PRIMARY KEY (skey)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

/**
 * Liefert das gemeinsame PDF-CSS (ohne <style>-Tags), aufgebaut aus der
 * aktuellen Palette. Deckt die in den Generatoren verbreiteten Klassennamen ab.
 */
function pdf_theme_css(): string
{
    $c = pdf_theme_palette();
    return <<<CSS
        /* ---- Basis ---- */
        * { box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            font-size: {$c['base_font']}px;
            line-height: 1.4;
            color: {$c['text']};
            margin: 0;
            padding: 0;
        }
        .container { margin: 0 10px 10px 10px; padding: 4px; }

        /* ---- Überschriften / Akzent ---- */
        h1 { text-align: center; margin-bottom: 14px; font-size: 16px; color: {$c['accent']}; }
        h2 { text-align: left; margin-bottom: 12px; font-size: 13px; color: {$c['accent']}; }
        h3 { text-align: left; margin-bottom: 8px; font-size: 11px; color: {$c['accent']}; }
        h5 { font-family: Arial, sans-serif; font-size: 12px; font-weight: bold; color: {$c['text']}; }
        .subtitle { color: {$c['muted']}; font-weight: normal; }

        /* ---- Kopf mit Logo ---- */
        .pdf-header, .header {
            display: flex;
            align-items: center;
            margin: 0 10px 14px 10px;
            position: relative;
            min-height: 56px;
        }
        .logo-container { flex: 0 0 auto; margin-right: 18px; }
        .logo, .header img { width: {$c['logo_width']}px; max-width: {$c['logo_width']}px; height: auto; margin-right: 18px; }
        .header-text { position: absolute; left: 0; right: 0; text-align: center; pointer-events: none; }
        .header-text h1 { margin: 0; font-size: 17px; color: {$c['accent']}; }
        .header-text .subtitle { margin: 4px 0 0 0; font-size: 10px; color: {$c['muted']}; }

        /* ---- Tabelle (Auto-Layout: Spalten orientieren sich am Inhalt) ---- */
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            background-color: #fff;
        }
        .table th, .table td {
            vertical-align: middle;
            padding: 3px 5px;
            border: {$c['border_width']}px solid {$c['border']};
            font-family: Arial, sans-serif;
        }
        /* Heller, ruhiger Tabellenkopf statt dunkler/navyblauer Flächen */
        .table thead { background-color: {$c['head_bg']}; }
        .table th {
            font-weight: bold;
            text-align: left;
            color: {$c['head_text']};
            background-color: {$c['head_bg']};
            border-bottom: 2px solid {$c['head_line']};
        }
        /* Zebra */
        .table tbody tr:nth-child(even) { background-color: {$c['zebra']}; }

        /* Rang-Spalte (erste) zentriert/fett, Total-Spalte (letzte) dezent hervorgehoben */
        .table td:first-child { text-align: center; font-weight: bold; }
        .table td:last-child  { text-align: right; font-weight: bold; background-color: {$c['total_bg']}; color: {$c['accent']}; }

        /* Optionale durchgezogene Rahmen-Variante */
        .table-bordered th, .table-bordered td { border: {$c['border_width']}px solid {$c['border']}; }

        /* ---- Resultat-Spalten ---- */
        .result-col { text-align: center; }
        .result-col .value { font-weight: normal; }
        .result-col .no-data, .no-data { color: {$c['muted']}; }

        /* ---- Rotierte Spaltenüberschriften (Layout bleibt datei-spezifisch) ---- */
        .vertical-header { white-space: nowrap; text-align: left; }

        /* ---- Medaillen (Pastell, ruhig) ---- */
        .rank-1, .rank-1 td { background-color: {$c['gold_bg']} !important;   color: {$c['gold_tx']}; font-weight: bold; }
        .rank-2, .rank-2 td { background-color: {$c['silver_bg']} !important; color: {$c['silver_tx']}; font-weight: bold; }
        .rank-3, .rank-3 td { background-color: {$c['bronze_bg']} !important; color: {$c['bronze_tx']}; font-weight: bold; }

        /* ---- Gestrichene / Gewinner / Verlierer ---- */
        .struck { color: {$c['struck']}; text-decoration: line-through; }
        .winner { color: {$c['win']}; font-weight: bold; }
        .loser  { color: {$c['struck']}; text-decoration: line-through; }

        /* ---- Info-Box ---- */
        .info-box {
            background-color: {$c['total_bg']};
            border-left: 4px solid #94a3b8;
            padding: 8px 10px;
            margin-bottom: 12px;
            font-size: 9px;
        }
        .info-box strong { color: {$c['accent']}; }

        /* ---- Legende ---- */
        .legend {
            margin-top: 14px;
            padding: 8px 10px;
            background-color: {$c['head_bg']};
            border: 1px solid {$c['border']};
            font-size: 8px;
            color: {$c['muted']};
        }
        .legend-item { margin-bottom: 3px; display: block; }
        .legend-item .struck { color: {$c['struck']}; text-decoration: line-through; font-weight: bold; }

        /* ---- Hilfsklassen ---- */
        .bold { font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-muted { color: {$c['muted']}; }

        /* ---- Footer ---- */
        .footer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            width: 100%;
            text-align: center;
            font-size: 8px;
            color: {$c['muted']};
            padding: 8px 0;
            border-top: 1px solid {$c['head_line']};
            background-color: #fff;
        }
        .footer hr { border: none; border-top: 1px solid {$c['head_line']}; margin: 0; }

        /* ---- Diverses ---- */
        .page-break { page-break-after: always; page-break-inside: avoid; }
        td.zwischenzeile { font-size: 3px; height: 5px; background-color: {$c['head_bg']}; }
CSS;
}

/**
 * Konvertiert das Logo (oder ein beliebiges Bild) in eine Base64-Data-URI.
 * DRY-Ersatz für die in vielen Modulen duplizierte imgToBase64()-Funktion.
 * Gibt '' zurück, wenn die Datei fehlt (kein Fatal).
 */
function pdf_logo_base64(string $path = 'dat/MSVWilen_Logo.jpg'): string
{
    if (!is_file($path)) {
        return '';
    }
    $data = @file_get_contents($path);
    if ($data === false) {
        return '';
    }
    $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/jpeg') : 'image/jpeg';
    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

/**
 * Absoluter Dateipfad des zentralen MSV-Wilen-PDF-Logos.
 * (Master-Datei; Modul-Kopien unter inc/*\/dat/ werden beim Speichern auf der
 *  PDF-Design-Seite mit derselben Datei synchronisiert.)
 */
function pdf_logo_path(): string
{
    return dirname(__DIR__, 2) . '/images/MSVWilen_Logo.jpg';
}

/**
 * Base64-Data-URI des zentralen Logos (für Generatoren, die das Logo einbetten,
 * statt eine lokale dat/-Kopie zu laden). '' wenn die Datei fehlt.
 */
function pdf_logo_src(): string
{
    return pdf_logo_base64(pdf_logo_path());
}

/**
 * Einfacher, einheitlicher Footer-Block (für Standalones, die keinen
 * Dompdf-Canvas-Footer verwenden).
 */
function pdf_footer_html(string $center = 'MSV Wilen'): string
{
    return '<div class="footer"><hr><p>&copy; ' . date('Y') . ' ' . htmlspecialchars($center) . '. Alle Rechte vorbehalten.</p></div>';
}
