<?php
// portal/meine_jm.php - JM-Uebersicht mit Streicher-Logik
$portal_page_title = 'Jahresmeisterschaft';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();
$db = getDB();

$mitglied_id = $_SESSION['mitglied_id'] ?? null;
$selected_year = intval($_GET['year'] ?? date('Y'));

// CSRF-Token fuer Auto-Save sicherstellen
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Anlaesse, die NICHT vom Mitglied selbst eingebbar sind (kommen aus eigenen Tabellen
// oder erfordern Vorstand-Erfassung mit mehreren Zeilen pro Mitglied).
$NON_EDITABLE_BEZ = ['Endstich', 'Bester Kantonalstich', 'Sektionsmeisterschaft'];
$current_year = (int)date('Y');

// Bestimmt, ob ein Anlass vom Mitglied selbst eingebbar ist
function jmIsEditable(array $s, int $year, int $current_year, ?int $mitglied_id, array $blocked): bool {
    if (!$mitglied_id) return false;
    if ($year !== $current_year) return false;  // nur aktuelles Jahr
    if (in_array($s['Bezeichnung'] ?? '', $blocked, true)) return false;
    if (jmIsVereinscup($s)) return false;       // Cup-Resultat kommt aus inc/cup.php, nicht selbst eingebbar
    if (jmIsTeilnahme($s)) return false;        // Maxpunkte==20: Vorstand traegt ein, nur Ja/Nein-Anzeige
    if (trim((string)($s['Schiesstage'] ?? '')) === '') return false;
    return true;
}

// Rendert den Mitglied-Eingabebereich fuer einen selbst eingebbaren JM-Anlass (Zahlenfeld).
// Teilnahme-Anlaesse (Maxpunkte == 20) sowie Endstich/Kanti/Sektion sind NICHT editierbar und
// erreichen diese Funktion nicht (siehe jmIsEditable).
// Freigegebene Resultate sind gesperrt und werden ohne Bestaetigungs-Hinweis angezeigt.
function renderJmEingabe(array $s): string {
    $maxpunkte = (int)($s['Maxpunkte'] ?? 0);
    $jr_status = $s['jr_status'] ?? null;       // 'entwurf' | 'freigegeben' | null
    $locked    = ($jr_status === 'freigegeben');
    $has_value = ($s['Punkte'] !== null && (int)$s['Punkte'] > 0);
    $val_attr  = $has_value ? (int)$s['Punkte'] : '';
    $defid     = (int)$s['ID'];
    $row_idx   = (int)$s['_idx'];

    ob_start();
    ?>
    <div class="jm-eingabe" onclick="event.stopPropagation()">
        <label<?php echo $locked ? '' : ' for="jmIn' . $defid . '"'; ?>>Mein Resultat:</label>
        <?php if ($locked): ?>
            <span class="jm-punkte-display"><strong><?php echo $val_attr !== '' ? $val_attr : '&ndash;'; ?></strong></span>
            <?php if ($val_attr !== ''): ?><span class="jm-eingabe-max">/ <?php echo $maxpunkte; ?></span><?php endif; ?>
        <?php else: ?>
            <input type="number" inputmode="numeric"
                   min="0" max="<?php echo $maxpunkte; ?>" step="1"
                   class="jm-punkte-input"
                   id="jmIn<?php echo $defid; ?>"
                   aria-describedby="jmErr<?php echo $defid; ?>"
                   value="<?php echo $val_attr; ?>"
                   data-defid="<?php echo $defid; ?>"
                   data-orig="<?php echo $val_attr; ?>"
                   data-max="<?php echo $maxpunkte; ?>"
                   data-row-idx="<?php echo $row_idx; ?>">
            <span class="jm-eingabe-max">/ <?php echo $maxpunkte; ?></span>
            <span class="jm-status-badge jm-status-<?php echo ($jr_status === 'entwurf' ? 'entwurf' : 'leer'); ?>">
                <?php if ($jr_status === 'entwurf'): ?>
                    <i class="bi bi-pencil"></i> Entwurf — wartet auf Freigabe
                <?php else: ?>
                    <i class="bi bi-dash-circle"></i> Noch nicht erfasst
                <?php endif; ?>
            </span>
            <span class="jm-save-spinner d-none"><i class="bi bi-arrow-repeat"></i></span>
            <span class="jm-save-ok d-none"><i class="bi bi-check-circle-fill"></i> gespeichert</span>
            <span class="jm-save-err" id="jmErr<?php echo $defid; ?>" role="alert" hidden></span>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// Read-only Anzeige des Vereinscup-Resultats. Der Wert stammt aus der Cup-Erfassung
// (inc/cup.php -> cupPairs, 1. Runde) und ist im Portal nicht editierbar.
function renderCupInfo(array $s): string {
    $maxpunkte = (int)($s['Maxpunkte'] ?? 0);
    $has_value = ($s['Punkte'] !== null && (int)$s['Punkte'] > 0);
    $val       = $has_value ? (int)$s['Punkte'] : null;

    ob_start();
    ?>
    <div class="jm-eingabe jm-cup-info" onclick="event.stopPropagation()">
        <label>Mein Cup-Resultat:</label>
        <?php if ($val !== null): ?>
            <span class="jm-punkte-display"><strong><?php echo $val; ?></strong></span>
            <span class="jm-eingabe-max">/ <?php echo $maxpunkte; ?></span>
        <?php else: ?>
            <span class="jm-punkte-display text-muted">&ndash;</span>
        <?php endif; ?>
        <span class="jm-status-badge jm-status-cup"><i class="bi bi-trophy"></i> Resultat aus dem Vereinscup</span>
    </div>
    <?php
    return ob_get_clean();
}

// Daten, Streicher und Total: gemeinsam mit der Startseite (portal/inc_jm_stand.php)
require_once __DIR__ . '/inc_jm_stand.php';

include 'portal_header.php';
include __DIR__ . '/inc_resultate_reiter.php';   // Reiter JM/Heim/Kanti/Wanderpreise (nur Vorschau)
?>

<style>
.year-select { max-width: 140px; }
/* Stat-Inhalt + Total-Akzent kommen aus css/portal.css (.p-stat) */

/* Schiessen-Liste: ein Panel mit Trennzeilen (Desktop + Mobile gleich) */
.jm-list {
    background: #fff;
    border: 1px solid var(--p-border);
    border-radius: var(--p-radius);
    box-shadow: var(--p-shadow);
    overflow: hidden;
}
.jm-row { border-top: 1px solid var(--p-border); padding: .55rem var(--p-3); }
.jm-row:first-child { border-top: none; }
.jm-row-main { display: flex; align-items: center; gap: var(--p-3); }
.jm-row-info { flex: 1; min-width: 0; }
.jm-row-title {
    display: flex; align-items: center; gap: .4rem; flex-wrap: wrap;
    font-weight: 600; font-size: .9rem; color: var(--p-text);
}
.jm-row.future .jm-row-title { color: var(--p-text-muted); }
.jm-row-meta { font-size: .75rem; color: var(--p-text-muted); margin-top: 1px; }
.jm-row-result { display: flex; align-items: center; gap: .5rem; flex-shrink: 0; }
.jm-row-points { font-size: 1.1rem; font-weight: 700; color: var(--p-text); font-variant-numeric: tabular-nums; }
.jm-row-points.ok { color: var(--success-color); }
.jm-row-points.streicher { color: #b8860b; text-decoration: line-through; font-size: .95rem; }
.jm-row-points.muted { color: var(--p-text-muted); }
.jm-row-points.no { color: var(--danger-color); }
.jm-row-status { font-size: 1rem; line-height: 1; }
.jm-row-status.ok { color: var(--success-color); }
.jm-row-status.no { color: var(--danger-color); }
.jm-row-status.future { color: var(--p-text-muted); }
.jm-row-detail { margin-top: .5rem; padding-top: .5rem; border-top: 1px dashed var(--p-border); }

/* Einheitliche Pills (Streicher + Status) */
.badge-streicher {
    background: var(--warning-color);
    color: #343a40;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
}

/* Expand/Collapse */
.jm-expand-btn {
    background: none;
    border: none;
    padding: 0 0.25rem;
    color: var(--p-text-muted);
    cursor: pointer;
    font-size: 0.75rem;
    vertical-align: middle;
    transition: color 0.15s;
}
.jm-expand-btn:hover { color: var(--p-text); }
.jm-expand-btn i { transition: transform 0.2s; }
.jm-expand-btn.open i { transform: rotate(180deg); }

.jm-dates-detail {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem 1.5rem;
    font-size: 0.8rem;
    padding: 0.35rem 0;
}
.jm-dates-detail .date-line,
.jm-dates-detail .addr-line {
    display: flex;
    align-items: flex-start;
    gap: 0.3rem;
    white-space: nowrap;
}
.jm-dates-detail .addr-line { color: var(--p-text-muted); white-space: normal; }

/* Mitglied-Eingabe */
.jm-eingabe {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: #fff;
    border: 1px solid var(--p-border);
    border-radius: var(--p-radius-sm);
    font-size: 0.85rem;
}
.jm-eingabe label {
    margin: 0;
    color: var(--p-text);
    font-weight: 600;
    white-space: nowrap;
}
.jm-punkte-input {
    width: 80px;
    padding: 0.25rem 0.5rem;
    border: 1px solid #cbd5e0;
    border-radius: var(--p-radius-sm);
    font-size: 0.95rem;
    font-weight: 600;
    text-align: center;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.jm-punkte-input:focus {
    outline: none;
    border-color: var(--success-color);
    box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.15);
}
.jm-punkte-input:disabled {
    background: #f8f9fa;
    color: var(--p-text-muted);
    cursor: not-allowed;
}
.jm-eingabe-max {
    color: var(--p-text-muted);
    font-size: 0.8rem;
    margin-left: -0.25rem;
}
/* Gesperrtes (freigegebenes) Resultat: reine Anzeige ohne Bestaetigungs-Hinweis */
.jm-punkte-display {
    font-size: 1rem;
    color: var(--p-text);
    padding: 0.25rem 0.25rem;
}
.jm-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
}
.jm-status-leer       { background: #e9ecef; color: var(--p-text-muted); }
.jm-status-entwurf    { background: #fff3cd; color: #856404; }
.jm-status-freigegeben { background: #d4edda; color: #155724; }
.jm-status-cup        { background: #cfe2ff; color: #084298; }
.jm-save-spinner i { animation: jm-spin 0.8s linear infinite; }
.jm-save-ok { color: var(--success-color); font-size: 0.78rem; font-weight: 600; }
.jm-save-err { flex-basis: 100%; color: var(--danger-color); font-size: 0.85rem; font-weight: 600; }
.jm-punkte-input[aria-invalid=true] { border-color: var(--danger-color); box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.15); }
@keyframes jm-spin { to { transform: rotate(360deg); } }

@media (max-width: 575.98px) {
    .jm-eingabe { font-size: 0.8rem; }
    .jm-punkte-input { width: 70px; }
    .jm-status-badge { width: 100%; }
}
</style>

<!-- Page Header -->
<div class="portal-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-bullseye me-2"></i>Jahresmeisterschaft</h1>
        <p class="subtitle mb-0"><?php echo $selected_year; ?> &mdash; Alle Schiessen mit Streicher-Berechnung</p>
    </div>
    <form method="get" class="d-flex align-items-center gap-2 ms-auto">
        <select name="year" class="form-select form-select-sm year-select" aria-label="Jahr" onchange="this.form.submit()">
            <?php foreach ($available_years as $y): ?>
            <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if (!$mitglied_id): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Dein Account ist noch nicht mit einem Mitglied verknüpft. Bitte kontaktiere den Administrator.</div>
<?php else: ?>

<!-- Zusammenfassung -->
<div class="result-summary">
    <div class="rs-item total">
        <span class="rs-num"><?php echo $total_punkte; ?></span>
        <span class="rs-lbl">Total</span>
    </div>
    <div class="rs-item">
        <span class="rs-num"><?php echo $geschossen_count; ?> / <?php echo $total_events; ?></span>
        <span class="rs-lbl">geschossen</span>
    </div>
    <div class="rs-item">
        <span class="rs-num"><?php echo $streicher_used; ?> / <?php echo $exclude_count; ?></span>
        <span class="rs-lbl">Streicher</span>
    </div>
</div>

<!-- Schiessen-Liste (Desktop + Mobile gleich) -->
<div class="jm-list">
    <?php
    foreach ($schiessen_list as $s):
        $is_streicher = in_array($s['_idx'], $all_streicher_idxs);
        $geschossen   = ($s['PunkteNorm'] !== null);
        $punkte_norm  = $s['PunkteNorm'];
        $is_teilnahme = jmIsTeilnahme($s);  // Maxpunkte==20: nur Ja/Nein statt Wert

        // Schiesstage zerlegen. Pro Zeile: Datum + (Zeit nur, wenn nicht mehr als 1 Woche her).
        // Spaetestes Datum bestimmt, ob der Anlass wirklich vorbei ist.
        $all_lines = [];
        if (!empty($s['Schiesstage'])) {
            $all_lines = array_filter(array_map('trim', explode("\n", trim($s['Schiesstage']))));
        }
        $today_str       = date('Y-m-d');
        $event_last_date = null;
        $line_parts      = [];   // ['date' => ..., 'time' => ...]
        foreach ($all_lines as $line) {
            $dt = splitSchiessDatum($line);
            $d  = jmParseDatum($dt['date'], $months_de, $selected_year);
            if ($d !== null && ($event_last_date === null || $d > $event_last_date)) {
                $event_last_date = $d;
            }
            $line_parts[] = $dt;
        }

        // "vorbei" = letzter Tag liegt vor heute (unbekanntes Datum -> nicht als vorbei werten).
        // Vergangene Anlaesse: komplette Terminangabe (Datum + Uhrzeit) ausblenden.
        $is_past    = ($event_last_date !== null && $event_last_date < $today_str);
        $is_future  = !$is_past;
        $show_dates = !$is_past;

        $datum_kurz = '';
        if ($show_dates) {
            $datum_kurz = implode(', ', array_map(function($p) {
                return $p['date'] . ($p['time'] !== '' ? ' ' . $p['time'] : '');
            }, $line_parts));
        }

        $is_editable = jmIsEditable($s, $selected_year, $current_year, $mitglied_id, $NON_EDITABLE_BEZ);
        $is_cup        = jmIsVereinscup($s);   // Resultat aus cup.php, read-only
        // Resultat ist erfassbar, solange der Vorstand es noch nicht freigegeben hat.
        $result_locked = (($s['jr_status'] ?? null) === 'freigegeben');
        $can_enter     = $is_editable && !$result_locked;
        // Aufklappbar: nicht-vergangene wegen Terminen/Adresse; vergangene nur, solange
        // das Resultat noch erfassbar ist (Vorstand hat noch nicht freigegeben). Der
        // Vereinscup ist immer aufklappbar (zeigt den read-only Cup-Hinweis).
        $has_details = ($show_dates && (count($all_lines) > 1 || !empty($s['Adresse']))) || $can_enter || $is_cup;
        $detail_id   = 'jmr-' . $s['_idx'];
    ?>
    <div class="jm-row<?php echo ($is_future && !$geschossen) ? ' future' : ''; ?>"<?php if ($has_details): ?> onclick="toggleJmDetail('<?php echo $detail_id; ?>', this)" style="cursor:pointer"<?php endif; ?>>
        <div class="jm-row-main"<?php if ($has_details): ?> role="button" tabindex="0" aria-expanded="false" aria-controls="<?php echo $detail_id; ?>"<?php endif; ?>>
            <div class="jm-row-info">
                <div class="jm-row-title">
                    <span><?php echo htmlspecialchars($s['Bezeichnung']); ?></span>
                </div>
                <?php if ($datum_kurz !== ''): ?>
                <div class="jm-row-meta card-meta"><?php echo htmlspecialchars($datum_kurz); ?></div>
                <?php endif; ?>
            </div>
            <div class="jm-row-result">
                <?php
                // Reihenfolge: erst "geschossen", dann "kommend", sonst "vergangen & verpasst".
                // Teilnahme-Anlaesse (Maxpunkte==20): Haken statt Zahl.
                if (!empty($portal_fahne)) {
                    // Vorschau «Vereinsfahne»: Zustand in Worten statt Symbolen (kein rotes X, Streicher als Wort)
                    if ($geschossen) {
                        if ($is_teilnahme) {
                            echo '<span class="jm-row-points' . ($is_streicher ? ' streicher' : ' ok') . '"><i class="bi bi-check-lg" aria-hidden="true"></i> <span class="jm-row-tag">teilgenommen</span></span>';
                        } else {
                            $disp = ($punkte_norm == (int)$punkte_norm) ? (string)(int)$punkte_norm : number_format($punkte_norm, 2, '.', '');
                            echo '<span class="jm-row-points' . ($is_streicher ? ' streicher' : '') . '">' . $disp . '</span>';
                        }
                        if ($is_streicher) echo '<span class="jm-row-tag">Streicher</span>';
                    } elseif ($is_future) {
                        echo '<span class="jm-row-points muted" aria-label="noch kein Resultat">&ndash;</span>';
                    } else {
                        echo '<span class="jm-row-tag">ohne Resultat</span>';
                    }
                } elseif ($geschossen) {
                    if ($is_teilnahme) {
                        echo '<span class="jm-row-points ' . ($is_streicher ? 'streicher' : 'ok') . '" title="Teilgenommen"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span class="visually-hidden">Teilgenommen' . ($is_streicher ? ', Streicher' : '') . '</span></span>';
                    } else {
                        $disp = ($punkte_norm == (int)$punkte_norm) ? (string)(int)$punkte_norm : number_format($punkte_norm, 2, '.', '');
                        echo '<span class="jm-row-points ' . ($is_streicher ? 'streicher' : '') . '" title="Bereinigte Punkte">' . $disp . '</span>';
                        echo '<i class="bi bi-check-circle-fill jm-row-status ok" title="Geschossen" aria-hidden="true"></i><span class="visually-hidden">Geschossen' . ($is_streicher ? ', Streicher' : '') . '</span>';
                    }
                } elseif ($is_future) {
                    // noch nicht stattgefunden (oder Datum unbekannt) -> KEIN rotes X
                    echo '<span class="jm-row-points muted" aria-hidden="true">&ndash;</span>';
                    echo '<i class="bi bi-clock jm-row-status future" title="Noch nicht stattgefunden" aria-hidden="true"></i><span class="visually-hidden">Noch nicht stattgefunden</span>';
                } else {
                    // vergangen und nicht absolviert
                    echo '<span class="jm-row-points no" aria-hidden="true">&ndash;</span>';
                    echo '<i class="bi bi-x-circle-fill jm-row-status no" title="Nicht teilgenommen" aria-hidden="true"></i><span class="visually-hidden">Nicht teilgenommen</span>';
                }
                ?>
                <?php if ($has_details): ?><span class="jm-expand-btn" aria-hidden="true"><i class="bi bi-chevron-down"></i></span><?php endif; ?>
            </div>
        </div>
        <?php if ($has_details): ?>
        <div class="jm-row-detail" id="<?php echo $detail_id; ?>" style="display:none;">
            <?php if ($show_dates): ?>
            <div class="jm-dates-detail">
                <?php foreach ($line_parts as $p): ?>
                    <div class="date-line">
                        <i class="bi bi-calendar3 text-muted mt-1"></i>
                        <span>
                            <?php echo htmlspecialchars($p['date']); ?>
                            <?php if ($p['time'] !== ''): ?>
                                <br><span class="text-muted"><?php echo htmlspecialchars($p['time']); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                <?php if (!empty($s['Adresse'])): ?>
                    <div class="addr-line">
                        <i class="bi bi-geo-alt text-muted"></i>
                        <a href="https://maps.google.com/?q=<?php echo urlencode($s['Adresse']); ?>" target="_blank" rel="noopener" class="text-muted" onclick="event.stopPropagation()">
                            <?php echo nl2br(htmlspecialchars($s['Adresse'])); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($is_editable) echo renderJmEingabe($s); ?>
            <?php if ($is_cup) echo renderCupInfo($s); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<script>
function toggleJmDetail(id, triggerEl) {
    const el = document.getElementById(id);
    if (!el) return;
    const isOpen = el.style.display !== 'none' && el.style.display !== '';
    el.style.display = isOpen ? 'none' : (el.tagName === 'TR' ? 'table-row' : 'block');
    // Zustand für Screenreader am Zeilen-Knopf (.jm-row-main) nachführen
    const knopf = triggerEl.querySelector?.('[aria-controls="' + id + '"]');
    if (knopf) knopf.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    // Chevron rotieren
    const chevron = triggerEl.classList.contains('jm-expand-btn')
        ? triggerEl
        : triggerEl.querySelector('.jm-expand-btn, .card-expand-btn');
    if (chevron) chevron.classList.toggle('open', !isOpen);
    // Datum-Vorschau ausblenden wenn aufgeklappt
    const meta = triggerEl.querySelector?.('.card-meta');
    if (meta) meta.style.display = isOpen ? '' : 'none';
}

// Tastatur: Enter und Leertaste auf dem Zeilen-Knopf wirken wie ein Klick auf die Zeile.
// Nur wenn der Knopf selbst den Fokus hat, nie aus einem Feld der Details heraus.
document.querySelectorAll('.jm-row-main[aria-controls]').forEach(function (k) {
    k.addEventListener('keydown', function (e) {
        if ((e.key !== 'Enter' && e.key !== ' ') || e.target !== k) return;
        e.preventDefault();
        k.click();
    });
});

(function() {
    'use strict';
    const CSRF = <?php echo json_encode($csrf_token); ?>;
    const YEAR = <?php echo (int)$selected_year; ?>;
    const SAVE_URL = '../api/portal_jm_save.php';
    const inputs = document.querySelectorAll('.jm-punkte-input');

    function setBadge(badge, status) {
        // Hinweis: Bestaetigte (freigegebene) Resultate sind nicht editierbar und werden ohne
        // Status-Badge gerendert -> dieser Handler erhaelt nur 'entwurf' oder null.
        badge.classList.remove('jm-status-leer', 'jm-status-entwurf', 'jm-status-freigegeben');
        if (status === 'entwurf') {
            badge.classList.add('jm-status-entwurf');
            badge.innerHTML = '<i class="bi bi-pencil"></i> Entwurf — wartet auf Freigabe';
        } else {
            badge.classList.add('jm-status-leer');
            badge.innerHTML = '<i class="bi bi-dash-circle"></i> Noch nicht erfasst';
        }
    }

    // Aktualisiert die Hauptzeile/Karte (Punkte-Anzeige) ohne Page-Reload.
    // Volle Streicher-Neuberechnung erfordert Reload — wir machen einen sanften
    // Reload via location.reload() nach kurzer Bestaetigung.
    function updateAfterSave(input, data) {
        const badge = input.parentElement.querySelector('.jm-status-badge');
        if (badge) setBadge(badge, data.status);
        input.dataset.orig = (data.punkte !== null && data.punkte !== undefined) ? String(data.punkte) : '';
        if (data.punkte === null) {
            input.value = '';
        }
    }

    function showSpinner(input, on) {
        const spinner = input.parentElement.querySelector('.jm-save-spinner');
        if (spinner) spinner.classList.toggle('d-none', !on);
        input.disabled = on;
    }

    function showOk(input) {
        const ok = input.parentElement.querySelector('.jm-save-ok');
        if (!ok) return;
        ok.classList.remove('d-none');
        setTimeout(() => ok.classList.add('d-none'), 2500);
    }

    // Fehler bleiben beim Feld stehen, die Eingabe wird nie verworfen: Feld nochmals verlassen
    // (oder Enter) versucht es erneut; Escape stellt den gespeicherten Wert wieder her.
    function fehlerZeigen(input, text) {
        const box = document.getElementById(input.getAttribute('aria-describedby'));
        input.setAttribute('aria-invalid', 'true');
        if (box) { box.textContent = text; box.hidden = false; }
    }
    function fehlerWeg(input) {
        const box = document.getElementById(input.getAttribute('aria-describedby'));
        input.removeAttribute('aria-invalid');
        if (box) { box.textContent = ''; box.hidden = true; }
    }

    async function saveInput(input) {
        const newVal = input.value.trim();
        const oldVal = input.dataset.orig || '';
        if (newVal === oldVal) return;  // nichts geaendert

        // Client-side Validierung
        if (newVal !== '') {
            if (!/^\d+$/.test(newVal)) {
                fehlerZeigen(input, 'Bitte eine ganze Zahl eingeben.');
                return;
            }
            const max = parseInt(input.dataset.max || '0', 10);
            const num = parseInt(newVal, 10);
            if (max > 0 && num > max) {
                fehlerZeigen(input, `Höchstens ${max} Punkte möglich.`);
                return;
            }
            if (num < 0) {
                fehlerZeigen(input, 'Die Punktzahl darf nicht negativ sein.');
                return;
            }
        }

        showSpinner(input, true);

        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('year', String(YEAR));
        fd.append('jmdefinition_id', input.dataset.defid);
        fd.append('punkte', newVal);

        try {
            const res = await fetch(SAVE_URL, { method: 'POST', body: fd, credentials: 'same-origin' });
            let data = null;
            try { data = await res.json(); } catch (e) { data = null; }
            if (!res.ok || !data || !data.success) {
                throw new Error((data && data.message) || 'Der Server hat nicht geantwortet.');
            }
            fehlerWeg(input);
            updateAfterSave(input, data);
            showOk(input);
        } catch (err) {
            // TypeError = keine Verbindung (fetch); sonst die Meldung des Servers
            const grund = (err instanceof TypeError) ? 'keine Verbindung.' : String(err.message || 'unbekannter Fehler').replace(/\.?$/, '.');
            fehlerZeigen(input, 'Nicht gespeichert: ' + grund + ' Deine Eingabe ist noch da – verlasse das Feld nochmals, um es erneut zu versuchen.');
        } finally {
            showSpinner(input, false);
        }
    }

    inputs.forEach(input => {
        if (input.disabled) return;
        input.addEventListener('blur', () => saveInput(input));
        input.addEventListener('input', () => fehlerWeg(input));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                input.blur();
            } else if (e.key === 'Escape') {
                input.value = input.dataset.orig || '';
                fehlerWeg(input);
                input.blur();
            }
        });
    });

    // Wer mit einer nicht gespeicherten Eingabe die Seite verlässt, wird gewarnt (laufende Speicherungen sind gesperrt und zählen nicht)
    window.addEventListener('beforeunload', (e) => {
        const offen = Array.from(inputs).some(i => !i.disabled && i.value.trim() !== (i.dataset.orig || ''));
        if (offen) { e.preventDefault(); e.returnValue = ''; }
    });
})();
</script>

<?php include 'portal_footer.php'; ?>
