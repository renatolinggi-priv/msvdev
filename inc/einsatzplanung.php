<?php
/**
 * Einsatzplanung – Arbeitseinsätze (Obligatorisch / Feldschiessen / Wiler Chilbi) in der App planen.
 *
 * Ohne ?id: Planliste des Jahres (Neuer Plan: leer, Kopie aus Vorjahr, aus Dokument).
 * Mit ?id:  Plan-Editor – Raster Funktionen × Termine (Slots als Chips, Slide-Panel, Auto-Save)
 *           bzw. Personen × Schichten (Chilbi). Struktur (Termine/Funktionen) in Modals.
 *           Exporte DOCX/PDF/XLSX, Rückmeldung der Vereine einlesen, Freigabe ins Portal.
 *
 * Backend: inc/einsatzplanung/*.php (adminApiGuard + CSRF), Logik in plan_helpers.inc.php.
 * Datenmodell: Migration 050. Lesetabelle fürs Portal bleibt einsatz_zuweisungen (Projektion).
 */
require_once 'dbconnect.inc.php';
require_once __DIR__ . '/csrf.inc.php'; // csrf_token(), Session über session_config
require_once __DIR__ . '/../auth.php';

// Zugriffsschutz: nur Vorstand/Admin (vor header.inc.php, da dieser Output erzeugt)
if (!(isAdmin() || isVorstand()) && (int)($_SESSION['user_id'] ?? 0) !== 1) {
    header('Location: home.php');
    exit();
}
require_once __DIR__ . '/einsatzplanung/plan_helpers.inc.php';
require_once __DIR__ . '/helferabrechnung/abrechnung.inc.php';   // Ansicht «Abrechnung» (Schlossturm, Migration 058)
require_once __DIR__ . '/partials/empty_state.inc.php';   // msv_empty_row()

$db            = getDB();
$planId        = (int)($_GET['id'] ?? 0);
$selected_year = (int)($_GET['year'] ?? date('Y'));
$plan          = null;
$plaene        = [];
$allePlaene    = [];
$dokumente     = [];
$mitglieder    = [];
$years         = [];
$dbFehler      = '';

try {
    if ($planId > 0) {
        $plan = ep_plan_laden($db, $planId);
        if (!$plan) { header('Location: einsatzplanung.php'); exit(); }
    }
    $allePlaene = ep_plan_liste($db, null);
    $plaene     = $plan ? [] : ep_plan_liste($db, $selected_year);
    $mitglieder = ep_mitglieder_map($db);
    foreach ($allePlaene as $p) $years[] = (int)$p['jahr'];
    $dokumente = $db->query("SELECT id, titel, dateiname, jahr FROM vorstand_dokumente
                              WHERE typ = 'einsatzplan' AND LOWER(dateiname) REGEXP '\\\\.(docx|xlsx)$'
                              ORDER BY jahr DESC, titel")->fetchAll();
} catch (Throwable $e) {
    $dbFehler = 'Die Einsatzplanung ist in der Datenbank noch nicht eingerichtet. Unter «Einstellungen → Datenbank aktualisieren» die ausstehenden Aktualisierungen ausführen.';
    error_log('[einsatzplanung] ' . $e->getMessage());
}
// Ansicht «Abrechnung» (Helferabrechnung pro Verein, nur Schlossturm-Pläne): ?id=…&ansicht=abrechnung[&ok=1]
// Logik in helferabrechnung/abrechnung.inc.php, Markup in helferabrechnung/ansicht.inc.php
$ansichtAbr = $plan && $plan['typ'] === 'schlossturm' && ($_GET['ansicht'] ?? '') === 'abrechnung';
$okMit      = (int)($_GET['ok'] ?? 0) === 1;
$a = null; $haFehler = '';
if ($ansichtAbr) {
    try { $a = ha_abrechnung($db, $plan, $okMit); }
    catch (Throwable $e) {
        $haFehler = 'Die Abrechnung konnte nicht geladen werden. Unter «Einstellungen → Datenbank aktualisieren» prüfen, ob alle Aktualisierungen ausgeführt sind.';
        error_log('[einsatzplanung/abrechnung] ' . $e->getMessage());
    }
}
$istA  = $plan ? $plan['layout'] === 'funktion_x_termin' : false;   // Layout A = Funktionen × Termine
// Portal-Umfragen als mögliche Quelle der Verfügbarkeiten (Migration 053)
$umfragen = [];
if ($plan && $istA) {
    try { $umfragen = $db->query("SELECT id, titel, status FROM umfragen WHERE kategorie IN ('arbeitseinsatz','helfer') ORDER BY erstellt_am DESC")->fetchAll(); } catch (Throwable $e) { $umfragen = []; }
}
$hatRollen = $plan && $istA && count(array_filter($plan['funktionen'], fn($f) => !empty($f['rolle']) && $f['rolle'] !== 'OK')) > 0;

// ---------- Ablauf des Plans (Schrittleiste in der Kopf-Card, seit 1.68.56) ----------
// Wer einmal im Jahr plant, sieht hier die Reihenfolge und den nächsten Schritt; der Werkzeugkasten darunter bleibt für Korrekturen.
$ablauf = []; $ablaufWeiter = null; $verfProVerein = [];
if ($plan && !$dbFehler) {
    foreach (EP_VEREINE as $vk => $vl) $verfProVerein[$vk] = ['n' => 0, 'quellen' => []];
    foreach ($plan['verfuegbarkeit'] as $v) { $verfProVerein[$v['verein']]['n']++; $verfProVerein[$v['verein']]['quellen'][$v['quelle']] = true; }
    $vereineGemeldet = count(array_filter($verfProVerein, fn($x) => $x['n'] > 0));

    // Positionen zählen wie im Raster: Layout A feste Positionen je Termin, Layout B (Chilbi) Soll je Zelle
    $posFest = 0; $posVorschlag = 0; $posOffen = 0;
    foreach ($plan['termine'] as $t) foreach ($plan['funktionen'] as $f) {
        $key = $t['id'] . '|' . $f['id'];
        if ($istA) {
            for ($pos = 1, $n = ep_anzahl_pos($plan, (int)$t['id'], $f); $pos <= $n; $pos++) {
                $s = $plan['slot_index'][$key . '|' . $pos] ?? null;
                if ($s && ep_slot_besetzt($s)) { if ((int)($s['vorschlag'] ?? 0) === 1) $posVorschlag++; else $posFest++; }
                else $posOffen++;
            }
        } else {
            $ist = count(array_filter($plan['zellen'][$key] ?? [], 'ep_slot_besetzt'));
            $posFest += $ist;
            $posOffen += max(0, (int)($plan['soll'][$key] ?? 0) - $ist);
        }
    }
    // Anwesenheit: feste Positionen der vergangenen Termine
    $heute = date('Y-m-d'); $tVorbei = []; $ersterTermin = null;
    foreach ($plan['termine'] as $t) { if ($t['datum'] <= $heute) $tVorbei[(int)$t['id']] = true; if ($ersterTermin === null || $t['datum'] < $ersterTermin) $ersterTermin = $t['datum']; }
    $anwTotal = 0; $anwErfasst = 0;
    foreach ($plan['slots'] as $s) {
        if (!isset($tVorbei[(int)$s['termin_id']]) || !ep_slot_besetzt($s) || (int)($s['vorschlag'] ?? 0) === 1) continue;
        $anwTotal++; if ($s['anwesend'] !== null) $anwErfasst++;
    }
    $alleVorbei = $plan['termine'] && count($tVorbei) === count($plan['termine']);

    $strukturOk = $plan['termine'] && $plan['funktionen'];
    $ablauf[] = ['key' => 'struktur', 'name' => $istA ? 'Termine und Funktionen' : 'Schichten und Einsatztypen',
                 'stand' => count($plan['termine']) . ' ' . ($istA ? 'Termine' : 'Schichten') . ' · ' . count($plan['funktionen']) . ' ' . ($istA ? 'Funktionen' : 'Einsatztypen'),
                 'fertig' => (bool)$strukturOk, 'aktion' => !$plan['termine'] ? 'termine' : 'funktionen'];
    if ($istA && $hatRollen) {
        $ablauf[] = ['key' => 'verf', 'name' => 'Verfügbarkeiten', 'aktion' => 'verf',
                     'stand' => count($plan['verfuegbarkeit']) . ' Personen · ' . $vereineGemeldet . ' von ' . count(EP_VEREINE) . ' Vereinen',
                     'fertig' => $vereineGemeldet === count(EP_VEREINE)];
    }
    $ablauf[] = ['key' => 'besetzen', 'name' => 'Positionen besetzen', 'aktion' => $posVorschlag ? 'vorschlaege' : ($hatRollen ? 'einteilen' : 'raster'),
                 'stand' => $posFest . ' von ' . ($posFest + $posVorschlag + $posOffen) . ' besetzt' . ($posVorschlag ? ' · ' . $posVorschlag . ' Vorschläge' : ''),
                 'fertig' => $strukturOk && $posOffen === 0 && $posVorschlag === 0];
    $ablauf[] = ['key' => 'freigeben', 'name' => 'Freigeben', 'aktion' => 'freigeben',
                 'stand' => $plan['status'] === 'entwurf' ? 'nur hier sichtbar' : ($plan['status'] === 'final' ? 'im Portal · final' : 'im Portal'),
                 'fertig' => $plan['status'] !== 'entwurf'];
    if ($plan['termine']) {
        $ablauf[] = ['key' => 'anwesenheit', 'name' => 'Anwesenheit', 'aktion' => 'anwesenheit',
                     'stand' => !$tVorbei ? 'ab ' . ep_datum_kurz($ersterTermin) : $anwErfasst . ' von ' . $anwTotal . ' erfasst',
                     'fertig' => $alleVorbei && $anwErfasst === $anwTotal];
    }
    if ($plan['typ'] === 'schlossturm') {
        $ablauf[] = ['key' => 'abrechnung', 'name' => 'Abrechnung', 'aktion' => 'abrechnung', 'stand' => 'Helferstunden je Verein', 'fertig' => false];
    }

    // Nächster Schritt (genau ein gefüllter Hauptknopf): Reihenfolge der Saison, nicht der Liste
    $weiter = function (string $key, string $text, string $aktion) use (&$ablaufWeiter) { $ablaufWeiter ??= ['key' => $key, 'text' => $text, 'aktion' => $aktion]; };
    if (!$plan['termine'])         $weiter('struktur', $istA ? 'Termine anlegen' : 'Schichten anlegen', 'termine');
    elseif (!$plan['funktionen'])  $weiter('struktur', $istA ? 'Funktionen anlegen' : 'Einsatztypen anlegen', 'funktionen');
    if ($plan['status'] === 'entwurf') {
        if ($istA && $hatRollen && $vereineGemeldet < count(EP_VEREINE)) $weiter('verf', 'Verfügbarkeiten erfassen', 'verf');
        if ($posVorschlag)                                               $weiter('besetzen', 'Vorschläge übernehmen (' . $posVorschlag . ')', 'vorschlaege');
        if ($istA && $hatRollen && $posFest === 0)                       $weiter('besetzen', 'Automatisch einteilen', 'einteilen');
        if ($posFest === 0)                                              $weiter('besetzen', 'Positionen besetzen', 'raster');   // nichts eingeteilt: noch nicht freigeben
        $weiter('freigeben', 'Freigeben', 'freigeben');
    } else {
        if ($posVorschlag)                                   $weiter('besetzen', 'Vorschläge übernehmen (' . $posVorschlag . ')', 'vorschlaege');
        if ($tVorbei && $anwErfasst < $anwTotal)             $weiter('anwesenheit', 'Anwesenheit erfassen', 'anwesenheit');
        if ($plan['typ'] === 'schlossturm' && $alleVorbei)   $weiter('abrechnung', 'Zur Abrechnung', 'abrechnung');
    }
}
// Plan, aus dem dieser kopiert wurde (Titel statt interner Nummer)
$vorlageTitel = '';
if ($plan && $plan['vorlage_plan_id']) foreach ($allePlaene as $p) if ((int)$p['id'] === (int)$plan['vorlage_plan_id']) $vorlageTitel = $p['titel'] . ' (' . (int)$p['jahr'] . ')';
$years = array_values(array_unique($years));
if (!in_array((int)date('Y'), $years, true)) $years[] = (int)date('Y');
if (!in_array($selected_year, $years, true)) $years[] = $selected_year;
if (!in_array((int)date('Y') + 1, $years, true)) $years[] = (int)date('Y') + 1;
rsort($years);

$page_specific_css = <<<'CSS'
.ep-list td { vertical-align: middle; }
.ep-status { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; }
.ep-status.entwurf { background: var(--ui-rand); color: var(--ui-text-2); }
.ep-status.freigegeben { background: var(--ui-akzent-hell); color: var(--ui-akzent-dunkel); }
.ep-status.final { background: var(--ui-ok-bg); color: var(--ui-ok-fg); }
.ep-toolbar { display: flex; flex-wrap: wrap; gap: .5rem 1.1rem; align-items: flex-end; }
.ep-toolbar .btn { white-space: nowrap; }
.ep-tb-group { display: flex; flex-direction: column; gap: .12rem; }
.ep-tb-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--ui-text-2); font-weight: 600; padding-left: .2rem; }
.ep-toolbar .dropdown-menu-sm .dropdown-item { font-size: .82rem; padding: .3rem .8rem; }
/* schmale Fenster: Gruppen brechen um, die Knöpfe bleiben in ihrer Grösse (seit 1.68.56 kein Schrumpfen der Schrift mehr) */
@media (max-width: 1499px) { .ep-toolbar { gap: .4rem .8rem; } }
.ep-toolbar .dropdown-menu-sm .dropdown-item small { font-size: .72rem; }
/* Ablauf: Schrittleiste in der Kopf-Card (seit 1.68.56) */
/* Ohne Verbindungsstriche: beim Umbruch standen sie verwaist am Zeilenanfang; die Nummern tragen die Reihenfolge.
   flex-basis auto: reicht die Zeile nicht für Schritte + Knopf, rutscht zuerst der Knopf unter die Leiste. */
.ep-ablauf { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 0; padding: 0; list-style: none; flex: 1 1 auto; min-width: 0; }
.ep-ablauf > li { display: flex; align-items: center; }
@media (max-width: 767px) {
  .ep-ablauf { flex: 1 1 100%; flex-direction: column; align-items: flex-start; row-gap: 2px; }
}
.ep-schritt { display: flex; align-items: flex-start; gap: 7px; border: 1px solid transparent; background: transparent; padding: 4px 6px 4px 3px; border-radius: 8px; text-align: left; color: var(--ui-text); transition: background-color .15s, border-color .15s; }
.ep-schritt:hover { background: var(--ui-flaeche-2); border-color: var(--ui-rand); }
.ep-schritt-nr { flex: 0 0 22px; height: 22px; margin-top: 1px; border-radius: 50%; border: 1px solid var(--ui-feldrand); background: var(--ui-flaeche); color: var(--ui-text-2);
  font-size: .75rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
.ep-schritt.ist-fertig .ep-schritt-nr { background: var(--ui-ok-bg); border-color: var(--ui-ok-rand); color: var(--ui-ok-fg); }
.ep-schritt.ist-jetzt .ep-schritt-nr { background: var(--ui-akzent-dunkel); border-color: var(--ui-akzent-dunkel); color: var(--ui-flaeche); }
.ep-schritt-text { display: flex; flex-direction: column; line-height: 1.25; min-width: 0; }
.ep-schritt-name { font-size: .85rem; font-weight: 600; white-space: nowrap; }
.ep-schritt.ist-jetzt .ep-schritt-name { color: var(--ui-akzent-dunkel); }
.ep-schritt-stand { font-size: .8rem; color: var(--ui-text-2); white-space: nowrap; }
.ep-ablauf-weiter { margin-left: auto; display: flex; align-items: center; gap: 8px; }a.ep-schritt, a.ep-schritt:hover { text-decoration: none; color: var(--ui-text); }
/* Anwesenheit im Admin (gleiche Optik wie die Portal-Seite); «nicht da» in Bernstein, Rot bleibt dem Löschen */
.an-termine { display: flex; gap: .4rem; flex-wrap: wrap; margin-bottom: .8rem; }
.an-termine button { border: 1px solid var(--ui-rand); border-radius: 999px; padding: .25rem .8rem; font-size: .8rem; color: var(--ui-text); background: var(--ui-flaeche); }
.an-termine button.aktiv { background: var(--ui-akzent-dunkel); color: var(--ui-flaeche); border-color: var(--ui-akzent-dunkel); }
.an-stat { display: flex; gap: .5rem; margin-bottom: .6rem; font-size: .8rem; }
.an-stat span { border-radius: 6px; padding: .2rem .55rem; background: var(--ui-linie-zart); color: var(--ui-text-2); }
.an-stat .da { background: var(--ui-ok-bg); color: var(--ui-ok-fg); } .an-stat .nein { background: var(--ui-warn-bg); color: var(--ui-warn-fg); }
.an-fn { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--ui-text-2); font-weight: 600; margin: .6rem 0 .25rem; }
.an-row { display: flex; align-items: center; gap: .6rem; background: var(--ui-flaeche); border: 1px solid var(--ui-rand); border-radius: 8px; padding: .3rem .55rem; margin-bottom: .3rem; }
.an-row.da { border-color: var(--ui-ok-rand); background: var(--ui-ok-bg); } .an-row.nein { border-color: var(--ui-warn-rand); background: var(--ui-warn-zeile); }
.an-name { flex: 1; min-width: 0; font-weight: 600; font-size: .85rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.an-name small { font-weight: 400; color: var(--ui-text-2); font-size: .72rem; margin-left: .4rem; }
.an-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; background: var(--ui-k-rot); }
.an-dot.freienbach { background: var(--ui-akzent-dunkel); } .an-dot.wollerau { background: var(--ui-ok-fg); }
.an-btn { flex: 0 0 auto; width: 2.6rem; height: 2.1rem; border-radius: 8px; border: 1px solid var(--ui-feldrand); background: var(--ui-flaeche); font-size: 1rem; color: var(--ui-text-2); display: flex; align-items: center; justify-content: center; }
.an-row.da .an-btn.ja { background: var(--ui-ok-fg); border-color: var(--ui-ok-fg); color: var(--ui-flaeche); }
.an-row.nein .an-btn.no { background: var(--ui-warn-fg); border-color: var(--ui-warn-fg); color: var(--ui-flaeche); }
.an-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 0 1rem; }
.ep-grid-wrap { overflow-x: auto; border: 1px solid var(--ui-rand); border-radius: .5rem; background: var(--ui-flaeche); }
.ep-grid { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .85rem; }
.ep-grid th, .ep-grid td { border-right: 1px solid var(--ui-rand); border-bottom: 1px solid var(--ui-rand); padding: .35rem .45rem; vertical-align: top; min-width: 170px; }
.ep-grid th:last-child, .ep-grid td:last-child { border-right: 0; }
.ep-grid tbody tr:last-child td { border-bottom: 0; }
.ep-grid thead th { background: var(--ui-flaeche-2); font-size: .72rem; text-transform: uppercase; color: var(--ui-text-2); font-weight: 600; }
.ep-grid thead th .ep-t-bez { text-transform: none; font-size: .85rem; color: var(--ui-text); }
.ep-grid thead th .ep-t-datum { text-transform: none; font-size: .82rem; color: var(--ui-text); font-weight: 500; }
.ep-grid thead th .ep-t-zeit { text-transform: none; font-weight: 400; }
.ep-grid thead th .ep-t-info { text-transform: none; font-weight: 400; font-size: .72rem; color: var(--ui-text-2); margin-top: .1rem; }
.ep-grid .ep-fn { width: 220px; min-width: 200px; font-weight: 600; background: var(--ui-flaeche-2); color: var(--ui-text); }
.ep-grid .ep-fn small { display: block; font-weight: 400; color: var(--ui-text-2); }
.ep-grid .ep-gruppe td { background: var(--ui-linie-zart); font-weight: 700; font-size: .72rem; text-transform: uppercase; color: var(--ui-text-2); padding: .25rem .45rem; }
.ep-chip, .ep-cell { display: flex; align-items: center; gap: .4rem; width: 100%; text-align: left; border: 1px solid var(--ui-feldrand); background: var(--ui-flaeche);
  border-radius: .4rem; padding: .22rem .5rem; margin-bottom: .25rem; font-size: .82rem; line-height: 1.25; cursor: pointer; min-height: 1.9rem; }
.ep-chip:last-child, .ep-cell:last-child { margin-bottom: 0; }
.ep-chip:hover, .ep-cell:hover { border-color: var(--ui-text-3); background: var(--ui-flaeche-2); }
.ep-chip.selected, .ep-cell.selected { outline: 2px solid var(--ui-akzent-dunkel); outline-offset: 1px; }
.ep-chip.ep-offen, .ep-cell.ep-leer { color: var(--ui-text-2); border-style: dashed; }
/* offene Positionen (kein Name, MSV) und Soll-Platzhalter: Bernstein wie «offen» überall in der App (seit 1.68.56; vorher Rot,
   das mit der Vereinsfarbe MSV Wilen und dem Löschen zusammenfiel) */
.ep-chip.ep-offen.ep-v-msv, .ep-chip.ep-offen:not([data-verein]), .ep-chip.ep-ph { background: var(--ui-warn-zeile); border-color: var(--ui-warn-punkt); color: var(--ui-warn-fg); }
.ep-chip.ep-offen.ep-v-msv .ep-dot, .ep-chip.ep-offen:not([data-verein]) .ep-dot, .ep-chip.ep-ph .ep-dot { background: var(--ui-warn-punkt); }
.ep-chip.ep-offen.ep-v-msv:hover, .ep-chip.ep-ph:hover { background: var(--ui-warn-zeile-hover); border-color: var(--ui-warn-fg); }
.ep-chip.ep-v-freienbach { background: var(--ui-akzent-hell); border-color: var(--ui-akzent-rand); color: var(--ui-akzent-dunkel); }
.ep-chip.ep-v-wollerau   { background: var(--ui-ok-bg); border-color: var(--ui-ok-rand); color: var(--ui-ok-fg); }
.ep-chip.ep-warn { border-color: var(--ui-warn-punkt); background: var(--ui-warn-zeile); }
.ep-chip .ep-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; background: var(--ui-feldrand); }
.ep-chip.ep-v-msv.ep-besetzt .ep-dot { background: var(--ui-k-rot); }
.ep-chip.ep-v-freienbach .ep-dot { background: var(--ui-akzent-dunkel); }
.ep-chip.ep-v-wollerau .ep-dot { background: var(--ui-ok-fg); }
.ep-chip.ep-warn .ep-dot { background: var(--ui-warn-punkt); }
.ep-chip .ep-txt { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ep-chip .ep-bem { font-size: .72rem; color: var(--ui-text-2); }
.ep-chip .ep-anw { font-size: .8rem; flex-shrink: 0; }
.ep-chip .ep-anw-da { color: var(--ui-ok-fg); } .ep-chip .ep-anw-nein { color: var(--ui-warn-punkt); }
.ep-ausw-table td, .ep-ausw-table th { font-size: .8rem; padding: .25rem .4rem; white-space: nowrap; }
.ep-ausw-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
.ep-ausw-table td.ep-fehlt { color: var(--ui-warn-fg); font-weight: 600; }
.ep-legend { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem 1.1rem; font-size: .8rem; color: var(--ui-text-2); }
.ep-legend > span { display: inline-flex; align-items: center; gap: .35rem; }
/* Legende: Muster-Chips statt loser Punkte, damit Form und Farbe gleich gelesen werden wie im Raster */
.ep-mini { display: inline-flex; align-items: center; width: 22px; height: 14px; padding-left: 4px; border-radius: 5px; border: 1px solid var(--ui-feldrand); background: var(--ui-flaeche); }
.ep-mini > i { width: 6px; height: 6px; border-radius: 50%; background: var(--ui-feldrand); }
.ep-mini.ep-v-msv > i { background: var(--ui-k-rot); }
.ep-mini.ep-v-freienbach { background: var(--ui-akzent-hell); border-color: var(--ui-akzent-rand); } .ep-mini.ep-v-freienbach > i { background: var(--ui-akzent-dunkel); }
.ep-mini.ep-v-wollerau { background: var(--ui-ok-bg); border-color: var(--ui-ok-rand); } .ep-mini.ep-v-wollerau > i { background: var(--ui-ok-fg); }
.ep-mini.ep-m-offen { background: var(--ui-warn-zeile); border: 1px dashed var(--ui-warn-punkt); } .ep-mini.ep-m-offen > i { background: var(--ui-warn-punkt); }
.ep-mini.ep-m-warn { background: var(--ui-warn-zeile); border-color: var(--ui-warn-punkt); } .ep-mini.ep-m-warn > i { background: var(--ui-warn-punkt); }
.ep-legend .ep-vs-i { color: var(--ui-akzent-dunkel); }
.ep-legend .ep-anw-da-l { color: var(--ui-ok-fg); } .ep-legend .ep-anw-nein-l { color: var(--ui-warn-punkt); }
/* Editor: Raster links, Mitgliederliste (Drag & Drop) rechts */
.ep-editor-row { display: flex; gap: 1rem; align-items: flex-start; }
.ep-grid-col { flex: 1 1 auto; min-width: 0; }
.ep-side { flex: 0 0 250px; position: sticky; top: 70px; border: 1px solid var(--ui-rand); border-radius: .5rem; background: var(--ui-flaeche); display: flex; flex-direction: column; max-height: calc(100vh - 90px); }
.ep-side-head { padding: .35rem .45rem; border-bottom: 1px solid var(--ui-rand); background: var(--ui-flaeche-2); border-radius: .5rem .5rem 0 0; font-size: .72rem; text-transform: uppercase; color: var(--ui-text-2); font-weight: 600; min-height: 2.65rem; display: flex; align-items: center; }
.ep-side-search { padding: .35rem .45rem; border-bottom: 1px solid var(--ui-rand); }
.ep-side-list { overflow-y: auto; padding: .35rem .45rem; }
/* Listeneintrag = gleiche Chip-Optik wie eine Position (weiss, roter MSV-Punkt), rechts der Zähler */
.ep-mitglied { display: flex; align-items: center; gap: .4rem; width: 100%; border: 1px solid var(--ui-feldrand); background: var(--ui-flaeche); border-radius: .4rem; padding: .22rem .5rem; margin-bottom: .25rem; font-size: .82rem; line-height: 1.25; min-height: 1.9rem; cursor: grab; user-select: none; }
.ep-mitglied:last-child { margin-bottom: 0; }
.ep-mitglied:hover { border-color: var(--ui-text-3); background: var(--ui-flaeche-2); }
.ep-mitglied.dragging { opacity: .5; }
.ep-mitglied .ep-m-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ep-mitglied .ep-m-count { display: none; font-size: .72rem; min-width: 1.35rem; text-align: center; border-radius: .6rem; padding: .05rem .3rem; background: var(--ui-ok-bg); color: var(--ui-ok-fg); font-weight: 600; }
.ep-mitglied.geplant .ep-m-count { display: inline-block; }
.ep-mitglied.inaktiv .ep-m-name { color: var(--ui-text-2); font-style: italic; }
.ep-mitglied.extern { border-style: dashed; }
.ep-mitglied .ep-m-v { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; background: var(--ui-feldrand); }
.ep-mitglied .ep-m-v.ep-v-msv { background: var(--ui-k-rot); } .ep-mitglied .ep-m-v.ep-v-freienbach { background: var(--ui-akzent-dunkel); } .ep-mitglied .ep-m-v.ep-v-wollerau { background: var(--ui-ok-fg); }
.ep-mitglied .ep-m-count-gem { display: inline-block; background: var(--ui-linie-zart); color: var(--ui-text-2); }   /* eingeteilt/angeboten, immer sichtbar */
.ep-mitglied.geplant .ep-m-count-gem { background: var(--ui-ok-bg); color: var(--ui-ok-fg); }
.ep-mitglied.extern .ep-m-name::after { content: " (extern)"; color: var(--ui-text-2); font-size: .72rem; }
.ep-side-sub { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: var(--ui-text-2); font-weight: 600; margin: .35rem 0 .25rem; padding: .3rem .2rem .15rem; border: 0; border-top: 1px dashed var(--ui-rand); background: none; width: 100%; text-align: left; cursor: pointer; user-select: none; display: flex; align-items: center; gap: .3rem; }
.ep-side-sub.ep-side-sub-fest { cursor: default; border-top-style: solid; }
.ep-side-group:first-child .ep-side-sub { border-top: 0; margin-top: 0; }
.ep-side-sub .ep-caret { font-size: .72rem; color: var(--ui-text-2); transition: transform .15s; }
.ep-side-group.ep-collapsed .ep-side-sub .ep-caret { transform: rotate(-90deg); }
.ep-side-sub .ep-g-n { margin-left: auto; font-size: .72rem; min-width: 1.35rem; text-align: center; border-radius: .6rem; padding: .05rem .3rem; background: var(--ui-rand); color: var(--ui-text-2); }
.ep-side-group.ep-collapsed .ep-side-items { display: none; }
.ep-side-foot { padding: .35rem .6rem; border-top: 1px solid var(--ui-linie); font-size: .72rem; color: var(--ui-text-2); }
.ep-side-foot .ep-m-count { display: inline-block; font-size: .72rem; min-width: 1.35rem; text-align: center; border-radius: .6rem; padding: .05rem .3rem; background: var(--ui-ok-bg); color: var(--ui-ok-fg); font-weight: 600; }
.ep-side-stunden { padding: 0 .45rem .35rem; }
.ep-stunden-table { width: 100%; font-size: .74rem; }
.ep-stunden-table td { padding: .1rem .15rem; white-space: nowrap; }
.ep-stunden-table td.ep-st-pos, .ep-stunden-table td.ep-st-std { text-align: right; font-variant-numeric: tabular-nums; }
.ep-stunden-table td.ep-st-vs { font-size: .72rem; text-align: right; }
.ep-stunden-table tr.ep-st-total td { border-top: 1px solid var(--ui-rand); font-weight: 600; }
.ep-chip.ep-drop { outline: 2px dashed var(--ui-akzent-dunkel); outline-offset: 1px; background: var(--ui-akzent-hell); }
/* Einklappbare Funktionszeilen: Klick auf den Funktionsnamen (Knopf, auch per Tastatur); eingeklappt nur Zusammenfassung je Zelle */
.ep-grid .ep-fn { cursor: pointer; user-select: none; }
.ep-fn-toggle { display: block; width: 100%; padding: 0; border: 0; background: none; font: inherit; color: inherit; text-align: left; cursor: pointer; border-radius: 5px; }
.ep-fn-toggle:focus-visible { outline: 2px solid var(--ui-akzent-dunkel); outline-offset: 2px; }
.ep-grid .ep-fn .ep-caret { float: right; color: var(--ui-text-2); font-size: .75rem; margin-top: .15rem; transition: transform .15s; }
.ep-grid tr.ep-collapsed .ep-fn .ep-caret { transform: rotate(-90deg); }
.ep-grid tr.ep-collapsed td .ep-chip:not(.ep-summary) { display: none; }   /* :not() nötig – sonst blendet diese spezifischere Regel auch die Zusammenfassung aus und die Zeile wirkt leer */
.ep-grid .ep-summary { display: none; color: var(--ui-text-2); background: var(--ui-flaeche-2); border-style: solid; cursor: pointer; }
.ep-grid tr.ep-collapsed .ep-summary { display: flex; }
.ep-grid .ep-summary.ep-leer { color: var(--ui-text-2); background: var(--ui-flaeche); border-style: dashed; }
.ep-grid .ep-summary.ep-nichts { border-color: transparent; background: none; color: var(--ui-text-3); justify-content: center; }
.ep-grid .ep-summary .ep-offen-n { color: var(--ui-warn-fg); font-weight: 600; margin-left: auto; font-size: .72rem; }
.ep-grid .ep-fn .ep-fn-n { display: none; font-size: .72rem; min-width: 1.35rem; text-align: center; border-radius: .6rem; padding: .05rem .3rem; background: var(--ui-rand); color: var(--ui-text-2); font-weight: 600; vertical-align: middle; }
.ep-grid tr.ep-collapsed .ep-fn .ep-fn-n { display: inline-block; }
.ep-grid thead th.ep-fn .ep-alle { float: right; font-size: .9rem; line-height: 1; color: var(--ui-akzent-dunkel); cursor: pointer; padding: .1rem .25rem; border: 0; background: none; border-radius: 6px; }
.ep-grid thead th.ep-fn .ep-alle:hover { background: var(--ui-rand); }
/* Einteilungs-Vorschläge (automatisch, noch nicht bestätigt): eigene Form statt Blau, damit sie sich von SV Freienbach
   unterscheiden – weiss, grau gestrichelt, kursiv, Zauberstab wie der Knopf «Einteilen». Der Vereinspunkt bleibt. */
.ep-chip.ep-vorschlag, .ep-chip.ep-vorschlag:hover { background: var(--ui-flaeche); border: 1px dashed var(--ui-text-3); color: var(--ui-text-2); font-style: italic; }
.ep-chip.ep-vorschlag:hover { border-color: var(--ui-akzent-dunkel); }
.ep-chip .ep-vs-i { flex-shrink: 0; color: var(--ui-akzent-dunkel); font-size: .8rem; font-style: normal; }
/* Verfügbarkeits-Filter in der Mitgliederliste */
.ep-mitglied.ep-nv { opacity: .35; }
.ep-verf-table td, .ep-verf-table th { vertical-align: middle; font-size: .8rem; padding: .25rem .35rem; }
.ep-verf-table .form-check-inline { margin-right: .5rem; }
.ep-verf-table .form-check-input { margin-top: .15rem; }
.ep-verf-badge { font-size: .72rem; }
.ep-einteilung-tabelle td, .ep-einteilung-tabelle th { font-size: .8rem; padding: .25rem .4rem; text-align: center; }
.ep-einteilung-tabelle td.ep-fehlt { color: var(--ui-warn-fg); font-weight: 600; }
.ep-chip.ep-add { justify-content: center; color: var(--ui-text-2); border-style: dashed; background: transparent; min-height: 1.6rem; padding: .05rem .5rem; }
.ep-chip.ep-add:hover { color: var(--ui-akzent-dunkel); border-color: var(--ui-akzent-dunkel); background: var(--ui-flaeche-2); }
.ep-chip[draggable="true"] { cursor: grab; }
.ep-chip.dragging { opacity: .5; }
.ep-side.ep-drop-remove { outline: 2px dashed var(--ui-k-rot); outline-offset: 2px; background: var(--ui-fehler-bg); }
.ep-side.ep-drop-remove .ep-side-head { color: var(--ui-k-rot); }
.ep-side-remove-hint { display: none; padding: .5rem .6rem; font-size: .78rem; color: var(--ui-k-rot); text-align: center; }
.ep-side.ep-drop-remove .ep-side-remove-hint { display: block; }
/* Kompaktmodus bei vielen Spalten (Chilbi: 8 Schichten) – passt ohne horizontales Scrollen in den 1500px-Container */
.ep-editor-row.ep-eng .ep-side { flex-basis: 220px; }
.ep-grid.ep-eng th, .ep-grid.ep-eng td { min-width: 112px; padding: .3rem .3rem; }
.ep-grid.ep-eng .ep-fn { width: 150px; min-width: 140px; font-size: .78rem; }
.ep-grid.ep-eng thead th { font-size: .72rem; }
.ep-grid.ep-eng thead th .ep-t-datum { font-size: .8rem; }
.ep-grid.ep-eng thead th .ep-t-zeit { font-size: .72rem; }
.ep-grid.ep-eng .ep-chip { font-size: .74rem; padding: .18rem .35rem; gap: .3rem; min-height: 1.7rem; }
.ep-grid.ep-eng .ep-chip .ep-dot { width: 6px; height: 6px; }
@media (max-width: 991px) {
  /* gestapelt: Raster auf Fensterbreite begrenzen (sonst wächst die Spalte mit dem Raster und die ganze Seite scrollt seitlich) */
  .ep-editor-row { flex-direction: column; align-items: stretch; }
  .ep-grid-col { width: 100%; }
  .ep-side { position: static; flex-basis: auto; width: 100%; max-height: 320px; }
}
/* Handy: msv-styles.css hebt jeden .btn auf 48px – Text mittig statt oben; Werkzeuggruppen bleiben in der Karte,
   Knöpfe einer Gruppe dürfen umbrechen statt über den Rand zu laufen. */
@media (max-width: 767.98px) {
  .msv-kopf .btn, .ep-toolbar .btn { display: inline-flex; align-items: center; justify-content: center; font-size: .875rem; }
  .ep-tb-group { max-width: 100%; min-width: 0; }
  .ep-tb-group.ms-auto { margin-left: 0 !important; }
  .ep-tb-group > .btn-group { display: flex; max-width: 100%; }
  .ep-tb-group > .btn-group > .btn { flex: 0 1 auto; min-width: 0; white-space: normal; line-height: 1.2; }
  .ep-toolbar .btn { padding: .375rem .6rem; }
  .ep-toolbar .btn > .bi.me-1 { display: none; }   /* Symbol vor dem Text weglassen, sonst passt «Verfügbarkeiten · Einteilen · Rückmeldung» nicht in 390px; reine Symbolknöpfe bleiben */
}
/* Zusatz in Grossbuchstaben-Köpfen («Externe · ohne Mitgliedschaft»): normale Schreibweise, bei Platzmangel gekürzt */
.ep-zusatz { text-transform: none; letter-spacing: 0; font-weight: 400; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
.ep-soll-table th:first-child, .ep-soll-table td:first-child { text-align: left; }
.ep-soll-table thead th .ep-t-zeit { text-transform: none; letter-spacing: 0; }
.ep-soll-table .ep-std, .ep-soll-table .ep-std-n { font-size: .75rem; }
.ep-person-td { font-weight: 600; white-space: nowrap; background: var(--ui-flaeche-2); }
.ep-grid tfoot td { background: var(--ui-flaeche-2); font-size: .72rem; color: var(--ui-text-2); border-top: 2px solid var(--ui-rand); border-bottom: 0; vertical-align: top; }
.ep-grid tfoot td.ep-fehlt { color: var(--ui-warn-fg); font-weight: 600; }
.ep-grid th.ep-drop { outline: 2px dashed var(--ui-akzent-dunkel); outline-offset: -2px; background: var(--ui-akzent-hell); }
/* Dialoge mit Zeilen (Termine, Funktionen, Verfügbarkeiten): geänderte, noch nicht gespeicherte Zeile */
tr.ep-zeile-geaendert > td { background: var(--ui-gewaehlt); }
.modal-title .btn-help { margin-left: .5rem; vertical-align: -3px; }
.modal-title .msv-ungespeichert { margin-left: .6rem; vertical-align: 1px; }
.ep-dot-verein { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: .3rem; background: var(--ui-k-rot); }
.ep-dot-verein.freienbach { background: var(--ui-akzent-dunkel); } .ep-dot-verein.wollerau { background: var(--ui-ok-fg); }
.ep-titelfarbe { display: inline-block; width: 14px; height: 14px; border-radius: 5px; margin-right: .5rem; vertical-align: -2px; border: 1px solid var(--ui-rand); }
.ep-panel-stand { min-height: 1.2rem; }
.ep-list tbody tr { cursor: pointer; }
.ep-cell[draggable="true"] { cursor: grab; }
.ep-cell.dragging { opacity: .5; }
.ep-soll-table input { width: 4.2rem; text-align: center; }
.ep-struct-table td { vertical-align: middle; padding: .25rem .3rem; }
.ep-struct-table input.form-control-sm { min-width: 70px; }
.ep-preview { max-height: 420px; overflow: auto; }
.ep-preview td, .ep-preview th { font-size: .8rem; }
#epMitgliedFilter { margin-bottom: .3rem; }
.ep-hint { font-size: .78rem; color: var(--ui-text-2); }
.ep-chip .ep-ok { order: -1; flex-shrink: 0; font-size: .72rem; font-weight: 700; line-height: 1.2; background: var(--ui-warn-bg); color: var(--ui-warn-fg); box-shadow: inset 0 0 0 1px var(--ui-warn-rand); border-radius: 6px; padding: 0 .25rem; }   /* vor dem Namen, wird nie abgeschnitten */
.ep-ok-liste td { vertical-align: middle; }
.ep-ok-liste .form-switch { min-height: 0; margin: 0; }
CSS;
if ($ansichtAbr) $page_specific_css .= "\n" . require __DIR__ . '/helferabrechnung/ansicht_css.inc.php';

include 'header.inc.php';
$csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// Mitglieder-Options (aktive zuerst) – einmal gerendert, im Panel wiederverwendet
$optAktiv = ''; $optInaktiv = '';
foreach ($mitglieder as $m) {
    $label = $h(ep_name_vorname($m));
    if ((int)$m['Verstorben'] === 1) continue;
    if ((int)$m['Status'] === 1) $optAktiv .= '<option value="' . (int)$m['ID'] . '">' . $label . '</option>';
    else $optInaktiv .= '<option value="' . (int)$m['ID'] . '">' . $label . ' (inaktiv)</option>';
}

// Seitenkopf-Aktionen
ob_start();
if ($plan) { ?>
  <a href="einsatzplanung.php?year=<?= (int)$plan['jahr'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Zur Liste</a>
  <?php if ($plan['typ'] === 'schlossturm'): ?>
  <!-- Umschalter Planung ↔ Abrechnung (Helferstunden pro Verein, nur Schlossturm) -->
  <div class="btn-group btn-group-sm" role="group">
    <a href="einsatzplanung.php?id=<?= $planId ?>" class="btn <?= $ansichtAbr ? 'btn-outline-primary' : 'btn-primary' ?>"><i class="bi bi-grid-3x3 me-1"></i>Planung</a>
    <a href="einsatzplanung.php?id=<?= $planId ?>&ansicht=abrechnung" class="btn <?= $ansichtAbr ? 'btn-primary' : 'btn-outline-primary' ?>" data-tooltip="Helferstunden pro Verein: Pauschalen, OK, Vor-/Nacharbeiten, Excel/PDF"><i class="bi bi-calculator me-1"></i>Abrechnung</a>
  </div>
  <?php endif; ?>
  <span class="badge ep-status <?= $h($plan['status']) ?>" id="epStatusBadge"><?= $h(EP_STATUS[$plan['status']] ?? $plan['status']) ?></span>
  <?php if ($ansichtAbr): ?>
  <button type="button" class="btn-help" data-help="einsatzplanung.abrechnung" aria-label="Hilfe"></button>
  <?php else: ?>
  <button type="button" class="btn-help" data-help="einsatzplanung.editor" aria-label="Hilfe"></button>
  <?php endif; ?>
<?php } else { ?>
  <button type="button" class="btn btn-outline-secondary btn-sm js-auswertung" data-jahr="<?= $selected_year ?>" <?= $dbFehler ? 'disabled' : '' ?> data-tooltip="Anwesenheit <?= $selected_year ?>: je Verein und Mitglied über alle freigegebenen Pläne"><i class="bi bi-clipboard-data me-1" aria-hidden="true"></i>Auswertung Anwesenheit</button>
  <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#epNeuModal" <?= $dbFehler ? 'disabled' : '' ?>><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Neuer Plan</button>
<?php }
$page_actions     = ob_get_clean();
$page_show_mobile = true;
if (!$plan) {
    // Liste: «?»-Hilfe und Jahresauswahl direkt neben dem Titel (Kopf-Card-Regel)
    ob_start(); ?>
  <button type="button" class="btn-help" data-help="einsatzplanung.liste" aria-label="Hilfe"></button>
  <form method="get" class="d-inline-flex m-0"><label class="visually-hidden" for="epYear">Jahr</label>
    <select name="year" id="epYear" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $y === $selected_year ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
    </select>
  </form>
<?php $page_title_after = ob_get_clean();
} elseif ($ablauf) {
    // Editor: Ablauf als zweite Zeile der Kopf-Card – Schritte mit Stand, der nächste Schritt als einziger gefüllter Knopf
    ob_start(); ?>
  <ol class="ep-ablauf" aria-label="Ablauf des Plans">
    <?php foreach ($ablauf as $i => $s):
        // In der Ansicht «Abrechnung» gibt es die Dialoge nicht: Schritte führen als Link zurück in die Planung (?schritt=…)
        $jetzt = $ansichtAbr ? $s['aktion'] === 'abrechnung' : ($ablaufWeiter && $ablaufWeiter['key'] === $s['key']);
        $cls   = 'ep-schritt' . ($s['fertig'] ? ' ist-fertig' : '') . ($jetzt ? ' ist-jetzt' : '');
        $link  = $s['aktion'] === 'abrechnung' ? 'einsatzplanung.php?id=' . $planId . '&ansicht=abrechnung'
               : ($ansichtAbr ? 'einsatzplanung.php?id=' . $planId . '&schritt=' . rawurlencode($s['aktion']) : '');
        $inhalt = '<span class="ep-schritt-nr" aria-hidden="true">' . ($s['fertig'] ? '<i class="bi bi-check-lg"></i>' : $i + 1) . '</span>'
                . '<span class="ep-schritt-text"><span class="ep-schritt-name">' . $h($s['name']) . ($s['fertig'] ? '<span class="visually-hidden"> (erledigt)</span>' : '') . '</span>'
                . '<span class="ep-schritt-stand">' . $h($s['stand']) . '</span></span>'; ?>
    <li><?php if ($link !== ''): ?><a class="<?= $cls ?>" href="<?= $h($link) ?>"<?= $jetzt ? ' aria-current="step"' : '' ?>><?= $inhalt ?></a><?php
        else: ?><button type="button" class="<?= $cls ?> js-ablauf" data-aktion="<?= $h($s['aktion']) ?>"<?= $jetzt ? ' aria-current="step"' : '' ?>><?= $inhalt ?></button><?php endif; ?></li>
    <?php endforeach; ?>
  </ol>
  <div class="ep-ablauf-weiter">
    <button type="button" class="btn-help" data-help="einsatzplanung.ablauf" aria-label="Hilfe zum Ablauf"></button>
    <?php if ($ablaufWeiter && !$ansichtAbr): ?>
    <button type="button" class="btn btn-primary btn-sm js-ablauf" data-aktion="<?= $h($ablaufWeiter['aktion']) ?>"><i class="bi bi-arrow-right-circle me-1" aria-hidden="true"></i><?= $h($ablaufWeiter['text']) ?></button>
    <?php endif; ?>
  </div>
<?php $page_extra = ob_get_clean();
}
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 ps-0">
      <div class="main-content-wrapper content-width-wide">
        <?php $page_title = 'Einsatzplanung'; include 'partials/page_header.inc.php'; ?>
        <input type="hidden" id="csrfToken" value="<?= $csrf ?>">

        <?php if ($dbFehler): ?>
          <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><?= $h($dbFehler) ?></div>
        <?php elseif (!$plan): ?>

        <!-- ================= PLANLISTE ================= -->
        <section class="ui-karte" aria-labelledby="epListeTitel">
          <div class="ui-tab-kopf">
            <h2 class="ui-tab-titel mb-0" id="epListeTitel">Einsatzpläne <?= $selected_year ?></h2>
            <span class="ui-chip"><b><?= count($plaene) ?></b> <?= count($plaene) === 1 ? 'Plan' : 'Pläne' ?></span>
          </div>
          <div class="table-responsive">
            <table class="hybrid-table ep-list mb-0">
              <thead><tr><th>Titel</th><th>Typ</th><th style="width:110px">Status</th><th style="width:90px;text-align:center">Termine</th><th style="width:150px;text-align:center">Besetzt</th><th style="width:190px;text-align:right">Aktionen</th></tr></thead>
              <tbody>
              <?php if (!$plaene) { echo msv_empty_row(6, 'Keine Einsatzpläne für ' . $selected_year . ' gefunden', 'bi-inbox'); } ?>
              <?php foreach ($plaene as $p): $offen = (int)$p['anz_slots'] - (int)$p['anz_besetzt']; ?>
                <tr class="hybrid-row" data-href="einsatzplanung.php?id=<?= (int)$p['id'] ?>">
                  <td class="fw-semibold"><a href="einsatzplanung.php?id=<?= (int)$p['id'] ?>" class="text-reset text-decoration-none"><?= $h($p['titel']) ?></a></td>
                  <td><?= $h(EP_TYPEN[$p['typ']] ?? $p['typ']) ?> <small class="text-muted">· <?= $h(EP_LAYOUTS[$p['layout']] ?? '') ?></small></td>
                  <td><span class="badge ep-status <?= $h($p['status']) ?>"><?= $h(EP_STATUS[$p['status']] ?? $p['status']) ?></span></td>
                  <td class="text-center"><?= (int)$p['anz_termine'] ?></td>
                  <td class="text-center"><?= (int)$p['anz_besetzt'] ?> / <?= (int)$p['anz_slots'] ?><?php if ($offen > 0 && $p['layout'] === 'funktion_x_termin'): ?> <span class="ui-chip ms-1"><span class="ui-punkt" aria-hidden="true"></span><?= $offen ?> offen</span><?php endif; ?></td>
                  <td class="text-end text-nowrap">
                    <a class="btn btn-outline-primary btn-sm" href="einsatzplanung.php?id=<?= (int)$p['id'] ?>" data-tooltip="Bearbeiten" aria-label="<?= $h($p['titel']) ?> bearbeiten"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Einsatzplan <?= $h($p['titel']) ?> (Word)">
                      <button type="button" class="btn btn-outline-info js-export" data-plan="<?= (int)$p['id'] ?>" data-fmt="docx" data-tooltip="Einsatzplan (Word)" aria-label="Einsatzplan <?= $h($p['titel']) ?> (Word)"><i class="bi bi-file-earmark-word" aria-hidden="true"></i></button>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Einsatzplan <?= $h($p['titel']) ?>">
                      <button type="button" class="btn btn-outline-info js-export" data-plan="<?= (int)$p['id'] ?>" data-fmt="pdf" data-tooltip="Einsatzplan (PDF; die Umwandlung dauert einige Sekunden)" aria-label="Einsatzplan <?= $h($p['titel']) ?>"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i></button>
                      <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="einsatzplan" data-druck-label="Einsatzplan" data-plan="<?= (int)$p['id'] ?>" data-typ="<?= $h($p['typ']) ?>" data-titel="<?= $h($p['titel']) ?>" aria-label="Einsatzplan <?= $h($p['titel']) ?> direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm js-plan-delete" data-plan="<?= (int)$p['id'] ?>" data-titel="<?= $h($p['titel']) ?>" data-tooltip="Löschen (nur Entwürfe ohne erfasste Einsätze)" aria-label="<?= $h($p['titel']) ?> löschen"><i class="bi bi-trash" aria-hidden="true"></i></button>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <?php elseif ($ansichtAbr):
          // ================= ANSICHT ABRECHNUNG (Schlossturm) =================
          include __DIR__ . '/helferabrechnung/ansicht.inc.php';
        else:
          // ================= PLAN-EDITOR =================
          $terminIndex = []; foreach ($plan['termine'] as $t) $terminIndex[(int)$t['id']] = $t;
        ?>
        <div class="content-background">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
              <h2 class="h5 mb-1"><?php if (!empty($plan['farbe'])): ?><span class="ep-titelfarbe" style="background:<?= $h($plan['farbe']) ?>" data-tooltip="Titelfarbe im Word/PDF"></span><?php endif; ?><?= $h($plan['titel']) ?><?php if (mb_stripos($plan['titel'], (string)(EP_TYPEN[$plan['typ']] ?? $plan['typ'])) === false || !str_contains($plan['titel'], (string)$plan['jahr'])): ?> <small class="text-muted fw-normal">· <?= $h(EP_TYPEN[$plan['typ']] ?? $plan['typ']) ?> <?= (int)$plan['jahr'] ?></small><?php endif; ?></h2>
              <div class="ep-hint"><?= count($plan['termine']) ?> <?= $istA ? 'Termine' : 'Schichten' ?> · <?= count($plan['funktionen']) ?> <?= $istA ? 'Funktionen' : 'Einsatztypen' ?> · <?= count(array_filter($plan['slots'], 'ep_slot_besetzt')) ?> / <?= count($plan['slots']) ?> Positionen besetzt<?php if ($vorlageTitel !== ''): ?> · kopiert aus «<?= $h($vorlageTitel) ?>»<?php endif; ?></div>
            </div>
            <div class="ep-toolbar">
              <!-- Gruppe: Plan einrichten (selten gebraucht: ein Menü statt fünf Knöpfe) -->
              <div class="ep-tb-group"><span class="ep-tb-label" id="epTbPlan">Plan</span>
                <div class="btn-group btn-group-sm dropdown" role="group" aria-labelledby="epTbPlan">
                  <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-sliders me-1" aria-hidden="true"></i>Plan einrichten</button>
                  <ul class="dropdown-menu dropdown-menu-sm">
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#epTermineModal"><i class="bi bi-calendar3 me-2" aria-hidden="true"></i><?= $istA ? 'Termine' : 'Schichten' ?> <small class="text-muted">· Datum, Zeit, Treffpunkt</small></button></li>
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#epFunktionenModal"><i class="bi bi-list-task me-2" aria-hidden="true"></i><?= $istA ? 'Funktionen' : 'Einsatztypen' ?> <small class="text-muted">· <?= $istA ? 'Anzahl, Gruppe, Anfrage-Rolle' : 'Zeilen des Rasters' ?></small></button></li>
                    <?php if ($plan['termine']): ?><li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#epSollModal"><i class="bi bi-bullseye me-2" aria-hidden="true"></i><?= $istA ? 'Positionen je Termin' : 'Soll je Schicht' ?> <small class="text-muted">· <?= $istA ? 'Abweichungen vom Standard' : 'wie viele Personen' ?></small></button></li><?php endif; ?>
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#epMetaModal"><i class="bi bi-type me-2" aria-hidden="true"></i>Titel, Farbe und Fusstext <small class="text-muted">· im Word/PDF</small></button></li>
                    <?php if ($plan['typ'] === 'schlossturm'): ?><li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#epOkModal"><i class="bi bi-award me-2" aria-hidden="true"></i>OK-Mitglieder <small class="text-muted">· im Word «OK» statt «X»</small></button></li><?php endif; ?>
                  </ul>
                </div>
              </div>

              <?php if ($istA && $plan['termine']): ?>
              <!-- Gruppe: Planung (Verfügbarkeiten, Einteilung, Rückmeldung der Vereine) -->
              <div class="ep-tb-group"><span class="ep-tb-label">Planung</span>
                <div class="d-flex gap-2 align-items-center">
                  <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#epVerfModal" data-tooltip="Wer kann wann in welcher Rolle (Umfrage, Excel Personalanfrage, manuell)"><i class="bi bi-person-check me-1" aria-hidden="true"></i>Verfügbarkeiten (<span id="epVerfCount"><?= count($plan['verfuegbarkeit']) ?></span>)</button>
                    <button type="button" class="btn btn-outline-primary js-ablauf" data-aktion="einteilen" data-tooltip="Offene Positionen aus den Verfügbarkeiten vorschlagen lassen"><i class="bi bi-magic me-1" aria-hidden="true"></i>Einteilen</button>
                    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#epRueckModal" data-tooltip="Ausgefülltes Word eines Vereins einlesen"><i class="bi bi-upload me-1" aria-hidden="true"></i>Rückmeldung</button>
                  </div>
                  <div class="btn-group btn-group-sm js-vorschlaege-group <?= $plan['anz_vorschlaege'] ? '' : 'd-none' ?>">
                    <button type="button" class="btn btn-outline-primary js-vorschlaege" data-aktion="uebernehmen" data-tooltip="Alle Vorschläge (gestrichelt, mit Zauberstab) als feste Einteilung übernehmen"><i class="bi bi-check2-all me-1" aria-hidden="true"></i>Vorschläge übernehmen (<span class="ep-vs-n"><?= (int)$plan['anz_vorschlaege'] ?></span>)</button>
                    <button type="button" class="btn btn-outline-secondary js-vorschlaege" data-aktion="verwerfen" data-tooltip="Alle Vorschläge verwerfen (Positionen werden wieder leer)" aria-label="Alle Vorschläge verwerfen"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                  </div>
                </div>
              </div>
              <?php endif; ?>

              <!-- Gruppe: Ausgabe (Dropdown + Direktdruck) -->
              <div class="ep-tb-group"><span class="ep-tb-label">Ausgabe</span>
                <div class="btn-group btn-group-sm" role="group" aria-label="Dokumente">
                  <div class="btn-group btn-group-sm dropdown" role="group">
                  <button type="button" class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-file-earmark-arrow-down me-1" aria-hidden="true"></i>Dokumente</button>
                  <ul class="dropdown-menu dropdown-menu-sm">
                    <li><a class="dropdown-item js-export" href="#" data-plan="<?= $planId ?>" data-fmt="docx"><i class="bi bi-file-earmark-word me-2" aria-hidden="true"></i>Einsatzplan (Word) <small class="text-muted">· <?= $istA ? 'Funktionen × Termine' : 'Personen × Schichten' ?></small></a></li>
                    <li><a class="dropdown-item js-export" href="#" data-plan="<?= $planId ?>" data-fmt="docx" data-ansicht="<?= $istA ? 'personen' : 'funktionen' ?>"><i class="bi bi-file-earmark-word me-2" aria-hidden="true"></i>Einsatzplan (Word) <small class="text-muted">· <?= $istA ? 'Personen: wer arbeitet wann' : 'Funktionen: wer ist wo' ?></small></a></li>
                    <li><a class="dropdown-item js-export" href="#" data-plan="<?= $planId ?>" data-fmt="pdf"><i class="bi bi-file-earmark-pdf me-2" aria-hidden="true"></i>Einsatzplan</a></li>
                    <li><a class="dropdown-item" href="#" id="epPdfAblegen"><i class="bi bi-folder-plus me-2" aria-hidden="true"></i>Einsatzplan ins Portal ablegen <small class="text-muted">· Dokument für alle Mitglieder</small></a></li>
                    <?php if (!$istA): ?><li><a class="dropdown-item js-export" href="#" data-plan="<?= $planId ?>" data-fmt="xlsx"><i class="bi bi-file-earmark-spreadsheet me-2" aria-hidden="true"></i>Einsatzplan (Excel) <small class="text-muted">· Personen × Schichten</small></a></li><?php endif; ?>
                    <?php if ($plan['typ'] === 'schlossturm'): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item js-export" href="#" data-plan="<?= $planId ?>" data-fmt="personalanfrage" data-verein="msv"><i class="bi bi-file-earmark-spreadsheet me-2" aria-hidden="true"></i>Personalanfrage MSV (Excel) <small class="text-muted">· mit unseren Verfügbarkeiten</small></a></li>
                    <li><a class="dropdown-item js-export" href="#" data-plan="<?= $planId ?>" data-fmt="personalanfrage" data-verein="msv" data-leer="1"><i class="bi bi-file-earmark-spreadsheet me-2" aria-hidden="true"></i>Personalanfrage leer (Excel) <small class="text-muted">· Vorlage für die Vereine</small></a></li>
                    <?php endif; ?>
                  </ul>
                  </div>
                  <button type="button" class="btn btn-outline-info msv-druck" data-druck-doctype="einsatzplan" data-druck-label="Einsatzplan" data-plan="<?= $planId ?>" data-typ="<?= $h($plan['typ']) ?>" data-titel="<?= $h($plan['titel']) ?>" aria-label="Einsatzplan direkt drucken"><i class="bi bi-printer" aria-hidden="true"></i></button>
                </div>
              </div>

              <?php if ($plan['termine']): ?>
              <!-- Gruppe: Anwesenheit (alle Layouts) -->
              <div class="ep-tb-group"><span class="ep-tb-label">Anwesenheit</span>
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#epAnwModal" data-tooltip="Wer war da, wer nicht – pro Schicht erfassen"><i class="bi bi-person-check me-1" aria-hidden="true"></i>Erfassen</button>
                  <button type="button" class="btn btn-outline-secondary js-auswertung" data-plan="<?= $planId ?>" data-tooltip="Auswertung dieses Plans: je Verein und Person"><i class="bi bi-clipboard-data me-1" aria-hidden="true"></i>Auswertung</button>
                  <a class="btn btn-outline-secondary" href="../portal/einsatz_anwesenheit.php?id=<?= $planId ?>" target="_blank" rel="noopener" data-tooltip="Mobile Erfassung im Portal (fürs Handy)" aria-label="Anwesenheit mobil im Portal erfassen"><i class="bi bi-phone" aria-hidden="true"></i></a>
                </div>
              </div>
              <?php endif; ?>

              <!-- Gruppe: Status (rechts) -->
              <div class="ep-tb-group ms-auto"><span class="ep-tb-label">Status</span>
                <div class="btn-group btn-group-sm">
                  <?php if ($plan['status'] === 'entwurf'): ?>
                    <button type="button" class="btn btn-outline-primary js-publish" data-status="freigegeben" data-tooltip="Ins Portal übertragen: Mitglieder sehen ihre Einsätze"><i class="bi bi-send-check me-1" aria-hidden="true"></i>Freigeben</button>
                  <?php elseif ($plan['status'] === 'freigegeben'): ?>
                    <button type="button" class="btn btn-outline-primary js-publish" data-status="final" data-tooltip="Alle Namen sind komplett: Plan als definitiv kennzeichnen. Im Portal bleibt alles sichtbar."><i class="bi bi-check2-all me-1" aria-hidden="true"></i>Final setzen</button>
                    <button type="button" class="btn btn-outline-secondary js-publish" data-status="entwurf" data-tooltip="Aus dem Portal zurückziehen" aria-label="Aus dem Portal zurückziehen"><i class="bi bi-eye-slash" aria-hidden="true"></i></button>
                  <?php else: ?>
                    <button type="button" class="btn btn-outline-secondary js-publish" data-status="freigegeben" data-tooltip="Zurück auf «Freigegeben»" aria-label="Zurück auf «Freigegeben»"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
                  <?php endif; ?>
                  <button type="button" class="btn btn-outline-danger js-plan-delete" data-plan="<?= $planId ?>" data-titel="<?= $h($plan['titel']) ?>" data-tooltip="Plan löschen (nur Entwürfe ohne erfasste Einsätze)" aria-label="Plan löschen"><i class="bi bi-trash" aria-hidden="true"></i></button>
                </div>
              </div>
            </div>
          </div>

          <?php
            // Mitgliederliste rechts (beide Layouts): Zähler = Positionen des Mitglieds in diesem Plan
            $planZaehler = [];
            foreach ($plan['slots'] as $s) if (($s['verein'] ?? 'msv') === 'msv' && !empty($s['mitglied_id'])) $planZaehler[(int)$s['mitglied_id']] = ($planZaehler[(int)$s['mitglied_id']] ?? 0) + 1;
            ob_start(); ?>
          <aside class="ep-side" id="epSide">
            <div class="ep-side-head"><i class="bi bi-people me-1" aria-hidden="true"></i>Mitglieder&nbsp;<span class="text-muted ep-zusatz">· auf Position ziehen</span></div>
            <div class="ep-side-remove-hint"><i class="bi bi-eraser me-1"></i>Hier ablegen, um den Einsatz zu entfernen</div>
            <div class="ep-side-search"><input type="text" class="form-control form-control-sm" id="epSideFilter" placeholder="Suchen…" autocomplete="off">
              <?php if ($istA && $plan['verfuegbarkeit']): ?>
              <select class="form-select form-select-sm mt-1" id="epVerfFilter" data-tooltip="Nicht gemeldete Personen ausgrauen">
                <option value="">Verfügbarkeit: alle anzeigen</option>
                <?php foreach ($plan['termine'] as $t): ?><option value="<?= (int)$t['id'] ?>">verfügbar <?= $h(ep_datum_kurz($t['datum']) . ' ' . ep_zeit_text($t)) ?></option><?php endforeach; ?>
                <option value="alle">irgendwann verfügbar</option>
              </select>
              <?php endif; ?>
            </div>
            <div class="ep-side-list" id="epSideList">
              <?php
                $mItem = function (array $m, int $anz, bool $aktiv) use ($h): string {
                    $mid = (int)$m['ID'];
                    return '<div class="ep-mitglied' . ($anz > 0 ? ' geplant' : '') . ($aktiv ? '' : ' inaktiv') . '" draggable="true" data-mid="' . $mid . '" data-name="' . $h(ep_name_vorname($m)) . '" data-nachname="' . $h($m['Name']) . '" data-vorname="' . $h($m['Vorname']) . '" data-tooltip="' . ($anz > 0 ? $anz . ' Einsatz/Einsätze in diesem Plan' : 'noch nicht eingeplant') . '">'
                         . '<span class="ep-m-name">' . $h(ep_name_vorname($m)) . '</span><span class="ep-m-count">' . $anz . '</span></div>';
                };
                $offen = ''; $nOffen = 0; $geplant = ''; $nGeplant = 0;
                foreach ($mitglieder as $m) {
                    $mid = (int)$m['ID']; $anz = $planZaehler[$mid] ?? 0;
                    $aktiv = (int)$m['Status'] === 1 && (int)$m['Verstorben'] === 0;
                    if (!$aktiv && $anz === 0) continue;   // Inaktive nur zeigen, wenn bereits eingeplant
                    if ($anz > 0) { $geplant .= $mItem($m, $anz, $aktiv); $nGeplant++; } else { $offen .= $mItem($m, 0, $aktiv); $nOffen++; }
                }
                $externe = ep_externe_im_plan($plan);
              ?>
              <div class="ep-side-group ep-collapsed" data-gruppe="offen">
                <button type="button" class="ep-side-sub" aria-expanded="false"><i class="bi bi-chevron-down ep-caret" aria-hidden="true"></i>Nicht eingeplant <span class="ep-g-n"><?= $nOffen ?></span></button>
                <div class="ep-side-items" id="epGruppeOffen"><?= $offen ?></div>
              </div>
              <div class="ep-side-group ep-collapsed" data-gruppe="geplant">
                <button type="button" class="ep-side-sub" aria-expanded="false"><i class="bi bi-chevron-down ep-caret" aria-hidden="true"></i>Eingeplant <span class="ep-g-n"><?= $nGeplant ?></span></button>
                <div class="ep-side-items" id="epGruppeGeplant"><?= $geplant ?></div>
              </div>
              <?php if ($istA && $plan['verfuegbarkeit']):
                // Gemeldete Personen (Verfügbarkeiten aller Vereine) als ziehbare Chips – auch Partnervereine, die sonst
                // nur im Word-Platzhalter stehen. Zähler «eingeteilt/angeboten» wird per JS aus dem Raster nachgeführt.
                $gemeldetZaehler = [];
                foreach ($plan['slots'] as $s) if (ep_slot_besetzt($s)) { $pk = ep_person_key((int)$s['mitglied_id'], (string)$s['name_text']); $gemeldetZaehler[$pk] = ($gemeldetZaehler[$pk] ?? 0) + 1; }
                $gemeldet = $plan['verfuegbarkeit'];
                usort($gemeldet, fn($a, $b) => [array_search($a['verein'], array_keys(EP_VEREINE)), mb_strtolower($a['name'])] <=> [array_search($b['verein'], array_keys(EP_VEREINE)), mb_strtolower($b['name'])]);
              ?>
              <div class="ep-side-group ep-collapsed" data-gruppe="gemeldet">
                <button type="button" class="ep-side-sub" aria-expanded="false"><i class="bi bi-chevron-down ep-caret" aria-hidden="true"></i>Gemeldet <span class="ep-zusatz">· alle Vereine</span> <span class="ep-g-n"><?= count($gemeldet) ?></span></button>
                <div class="ep-side-items" id="epGemeldet">
              <?php foreach ($gemeldet as $v): $mid = (int)$v['mitglied_id']; $anz = $gemeldetZaehler[$v['person_key']] ?? 0; [$nn, $vn] = $mid ? [$v['Name'] ?? '', $v['Vorname'] ?? ''] : ep_name_split((string)$v['name']); $angeb = count($v['termin_ids']); ?>
              <div class="ep-mitglied gemeldet<?= $anz > 0 ? ' geplant' : '' ?>" draggable="true" data-mid="<?= $mid ?>" data-name="<?= $h($v['name']) ?>" data-nachname="<?= $h($nn) ?>" data-vorname="<?= $h($vn) ?>" data-verein="<?= $h($v['verein']) ?>" data-angeboten="<?= $angeb ?>"
                   data-tooltip="<?= $h(EP_VEREINE[$v['verein']] ?? $v['verein']) ?> · <?= $h(implode(', ', $v['rollen']) ?: 'keine Rolle') ?> · <?= $angeb ?> Schicht(en) angeboten">
                <span class="ep-m-v ep-v-<?= $h($v['verein']) ?>"></span><span class="ep-m-name"><?= $h($v['name']) ?></span>
                <span class="ep-m-count ep-m-count-gem"><?= $anz ?>/<?= $angeb ?></span>
              </div>
              <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>
              <div class="ep-side-group ep-collapsed" data-gruppe="extern">
                <button type="button" class="ep-side-sub" aria-expanded="false"><i class="bi bi-chevron-down ep-caret" aria-hidden="true"></i>Externe <span class="ep-zusatz">· ohne Mitgliedschaft</span> <span class="ep-g-n"><?= count($externe) ?></span></button>
                <div class="ep-side-items" id="epExterne">
              <?php foreach ($externe as $name => $anz): [$nn, $vn] = ep_name_split($name); ?>
              <div class="ep-mitglied extern geplant" draggable="true" data-mid="0" data-name="<?= $h($name) ?>" data-nachname="<?= $h($nn) ?>" data-vorname="<?= $h($vn) ?>" data-tooltip="<?= $anz ?> Einsatz/Einsätze in diesem Plan">
                <span class="ep-m-name"><?= $h($name) ?></span>
                <span class="ep-m-count"><?= $anz ?></span>
              </div>
              <?php endforeach; ?>
                </div>
              </div>
              <div class="input-group input-group-sm mt-1">
                <input type="text" class="form-control" id="epExternNeu" placeholder="Name Vorname" maxlength="100" autocomplete="off" data-tooltip="Eigene Person ohne Mitgliedschaft erfassen (Enter)">
                <button type="button" class="btn btn-outline-success" id="epExternAdd" data-tooltip="Externe Person zur Liste hinzufügen"><i class="bi bi-plus-lg"></i></button>
              </div>
            </div>
            <?php if ($istA): ?>
            <div class="ep-side-stunden" id="epStunden" data-tooltip="Feste Positionen und Helferstunden (Pauschale bzw. Schichtdauer × Positionen) je Verein, ohne Anwesenheit/OK; Vorschläge separat in Klammern. Abrechnung: Menü «Helferabrechnung»">
              <div class="ep-side-sub ep-side-sub-fest"><i class="bi bi-clock-history" aria-hidden="true"></i>Helferstunden je Verein</div>
              <table class="ep-stunden-table"><tbody>
                <?php foreach (EP_VEREINE as $vk => $vl): ?><tr data-verein="<?= $vk ?>"><td><?= $h($vl) ?></td><td class="ep-st-pos">–</td><td class="ep-st-std">–</td><td class="ep-st-vs text-muted"></td></tr><?php endforeach; ?>
                <tr class="ep-st-total"><td>Total</td><td class="ep-st-pos">–</td><td class="ep-st-std">–</td><td class="ep-st-vs text-muted"></td></tr>
              </tbody></table>
            </div>
            <?php endif; ?>
            <div class="ep-side-foot"><span class="ep-m-count">n</span> = eingeplant (Anzahl Einsätze) · Position auf die Liste ziehen = entfernen, auf eine andere Position = verschieben. Ohne Maus: Position anklicken und im Panel wählen.</div>
          </aside>
          <?php $sideHtml = ob_get_clean(); ?>

          <?php if (!$plan['termine']): ?>
            <div class="ui-leerzustand"><i class="bi bi-calendar3" aria-hidden="true"></i><p>Noch keine <?= $istA ? 'Termine' : 'Schichten' ?>. Der erste Schritt oben (<strong><?= $istA ? 'Termine anlegen' : 'Schichten anlegen' ?></strong>) öffnet die Erfassung<?php if ($istA && in_array($plan['typ'], ['obligatorisch', 'feldschiessen'], true)): ?>; dort lassen sich die Schiesstage auch aus der JM-Definition übernehmen<?php endif; ?>.</p></div>
          <?php else: ?>

          <!-- Raster Funktionen × Termine + Mitgliederliste (Drag & Drop) – beide Layouts.
               Layout A: feste Positionen (anzahl pro Termin). Layout B (Chilbi): Positionen entstehen
               dynamisch, «offen»-Platzhalter gemäss Soll, «+»-Chip zum Hinzufügen. -->
          <?php $eng = count($plan['termine']) > 4 ? ' ep-eng' : ''; ?>
          <div class="ep-editor-row mt-3<?= $eng ?>">
          <div class="ep-grid-col">
          <div class="ep-grid-wrap">
            <table class="ep-grid<?= $eng ?>" id="epGrid">
              <thead>
                <tr>
                  <th class="ep-fn">Funktion <button type="button" class="ep-alle" id="epAlleToggle" data-tooltip="Alle Funktionszeilen aufklappen" aria-label="Alle Funktionszeilen aufklappen"><i class="bi bi-arrows-expand" aria-hidden="true"></i></button></th>
                  <?php foreach ($plan['termine'] as $t): ?>
                    <th data-termin="<?= (int)$t['id'] ?>">
                      <?php if (trim((string)$t['bezeichnung']) !== ''): ?><div class="ep-t-bez"><?= $h($t['bezeichnung']) ?></div><?php endif; ?>
                      <div class="ep-t-datum"><?= $h($eng ? ep_datum_kurz($t['datum']) : ep_datum_lang($t['datum'])) ?></div>
                      <div class="ep-t-zeit"><?= $h(ep_zeit_text($t)) ?></div>
                      <?php if (trim((string)($t['info'] ?? '')) !== ''): ?><div class="ep-t-info"><?= $h($t['info']) ?></div><?php endif; ?>
                    </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
              <?php foreach (ep_funktionen_gruppiert($plan['funktionen']) as $g): ?>
                <?php if ($g['gruppe'] !== ''): ?><tr class="ep-gruppe"><td colspan="<?= count($plan['termine']) + 1 ?>"><?= $h($g['gruppe']) ?></td></tr><?php endif; ?>
                <?php foreach ($g['funktionen'] as $f): $anz = max(1, (int)$f['anzahl']); $anzMax = $istA ? ep_anzahl_max($plan, $f) : $anz;
                    $proTermin = $istA ? array_map(fn($t) => ep_anzahl_pos($plan, (int)$t['id'], $f), $plan['termine']) : [];
                    $variabel = $istA && count(array_unique($proTermin)) > 1;
                    $flabelBase = (trim((string)$f['gruppe']) !== '' ? $f['gruppe'] . ': ' : '') . $f['bezeichnung']; ?>
                <?php $fnTotal = 0; foreach ($plan['termine'] as $t) foreach ($plan['zellen'][$t['id'] . '|' . $f['id']] ?? [] as $s) if (ep_slot_besetzt($s)) $fnTotal++; ?>
                <tr data-fid="<?= (int)$f['id'] ?>" data-okfn="<?= ($f['rolle'] ?? '') === 'OK' ? 1 : 0 ?>" class="ep-collapsed">
                  <td class="ep-fn"><button type="button" class="ep-fn-toggle" aria-expanded="false" data-tooltip="Ein-/ausklappen"><i class="bi bi-chevron-down ep-caret" aria-hidden="true"></i><?= $h($f['bezeichnung']) ?> <span class="ep-fn-n" data-tooltip="eingeteilte Personen über alle Termine"><?= $fnTotal ?></span><?php if ($istA): ?><small><?= $variabel ? 'je Termin ' . $h(implode(' / ', $proTermin)) : $anz . ' pro Termin' ?></small><?php endif; ?></button></td>
                  <?php foreach ($plan['termine'] as $t): $key = $t['id'] . '|' . $f['id'];
                      $tlabel = (trim((string)$t['bezeichnung']) !== '' ? $t['bezeichnung'] . ' · ' : '') . ($istA ? ep_datum_lang($t['datum']) : ep_datum_kurz($t['datum']) . ' ' . ep_zeit_text($t));
                      $anzT = $istA ? ep_anzahl_pos($plan, (int)$t['id'], $f) : 0;
                      if ($istA) { $zellSlots = []; for ($pos = 1; $pos <= $anzT; $pos++) $zellSlots[] = $plan['slot_index'][$key . '|' . $pos] ?? null; }
                      else { $zellSlots = array_values(array_filter($plan['zellen'][$key] ?? [], 'ep_slot_besetzt')); }
                      $soll = $istA ? $anzT : (int)($plan['soll'][$key] ?? 0);
                  ?>
                    <td data-termin="<?= (int)$t['id'] ?>" data-funktion="<?= (int)$f['id'] ?>" data-soll="<?= $soll ?>" data-flabel="<?= $h($flabelBase) ?>" data-tlabel="<?= $h($tlabel) ?>">
                    <?php foreach ($zellSlots as $i => $s): $pos = $i + 1;
                        if (!$s) { echo '<div class="ep-chip ep-offen"><span class="ep-dot"></span><span class="ep-txt">–</span></div>'; continue; }
                        $besetzt = ep_slot_besetzt($s);
                        $text = ep_slot_text($s, $mitglieder);
                        $warn = '';
                        if (!empty($s['mitglied_id']) && isset($mitglieder[(int)$s['mitglied_id']])) {
                            $mm = $mitglieder[(int)$s['mitglied_id']];
                            if ((int)$mm['Verstorben'] === 1) $warn = 'verstorben'; elseif ((int)$mm['Status'] !== 1) $warn = 'inaktiv';
                        }
                        $istVs = (int)($s['vorschlag'] ?? 0) === 1;
                        $cls = 'ep-chip ep-v-' . $h($s['verein']) . ($besetzt ? ' ep-besetzt' : ' ep-offen') . ($warn ? ' ep-warn' : '') . ($istVs ? ' ep-vorschlag' : '');
                        $flabel = $flabelBase . ($istA && $anzMax > 1 ? ' (' . $pos . ')' : '');
                        // Vorlese-Name: Funktion, Termin, Person, Verein und Zustand (sonst hört man nur «offen» oder einen Namen)
                        $aria = $flabel . ', ' . $tlabel . ': ' . ($text !== '' ? $text : 'offen')
                              . (($s['verein'] ?? 'msv') !== 'msv' ? ', ' . (EP_VEREINE[$s['verein']] ?? $s['verein']) : '')
                              . ($istVs ? ', Vorschlag' : '') . ($warn ? ', Mitglied ' . $warn : '');
                    ?>
                      <button type="button" class="<?= $cls ?>" data-slot="<?= (int)$s['id'] ?>" data-verein="<?= $h($s['verein']) ?>" data-mid="<?= (int)$s['mitglied_id'] ?>" data-vorschlag="<?= $istVs ? 1 : 0 ?>"
                              data-name="<?= $h($s['name_text']) ?>" data-bem="<?= $h($s['bemerkung']) ?>" data-warn="<?= $warn ?>" data-ok="<?= ep_slot_ist_ok($s, $f) ? 1 : 0 ?>" data-okslot="<?= (int)($s['ok'] ?? 0) ?>" data-okfn="<?= ($f['rolle'] ?? '') === 'OK' ? 1 : 0 ?>"
                              data-funktion="<?= $h($flabel) ?>" data-termin="<?= $h($tlabel) ?>" aria-label="<?= $h($aria) ?>">
                        <span class="ep-dot" aria-hidden="true"></span><?php if ($istVs): ?><i class="bi bi-magic ep-vs-i" aria-hidden="true"></i><?php endif; ?><span class="ep-txt"<?= $eng && $text !== '' ? ' data-tooltip="' . $h($text) . '"' : '' ?>><?= $text !== '' ? $h($text) : 'offen' ?></span>
                        <?php if ($besetzt && ep_slot_ist_ok($s, $f)): ?><span class="ep-ok" data-tooltip="<?= ($f['rolle'] ?? '') === 'OK' ? 'OK über die Funktion (Rolle «OK»)' : 'OK-Mitglied' ?>">OK</span><?php endif; ?>
                        <?php if (trim((string)$s['bemerkung']) !== ''): ?><i class="bi bi-chat-left-text ep-bem" data-tooltip="<?= $h($s['bemerkung']) ?>"></i><?php endif; ?>
                        <?php if ($s['anwesend'] !== null): ?><i class="bi <?= (int)$s['anwesend'] === 1 ? 'bi-check-circle-fill ep-anw ep-anw-da' : 'bi-x-circle-fill ep-anw ep-anw-nein' ?>" data-tooltip="<?= (int)$s['anwesend'] === 1 ? 'anwesend' : 'nicht erschienen' ?>"></i><?php endif; ?>
                      </button>
                    <?php endforeach; ?>
                    <?php if (!$istA): for ($i = count($zellSlots); $i < $soll; $i++): ?>
                      <div class="ep-chip ep-offen ep-ph" data-tooltip="Soll noch nicht erreicht – Person hierher ziehen"><span class="ep-dot"></span><span class="ep-txt">offen</span></div>
                    <?php endfor; ?>
                      <button type="button" class="ep-chip ep-add" data-tooltip="Person hinzufügen (oder aus der Liste hierher ziehen)"><span class="ep-txt"><i class="bi bi-plus-lg"></i></span></button>
                    <?php endif; ?>
                    <?php $namen = array_map(fn($s) => ep_slot_text($s, $mitglieder), array_filter($zellSlots, fn($s) => $s && ep_slot_besetzt($s))); $nBes = count($namen); $nOffen = max(0, $soll - $nBes); ?>
                      <?php $nichts = $nBes === 0 && $nOffen === 0; /* keine Position, kein Soll: Strich statt «0 Personen» */ ?><div class="ep-chip ep-summary<?= $nBes === 0 ? ' ep-leer' : '' ?><?= $nichts ? ' ep-nichts' : '' ?>" data-tooltip="<?= $h($namen ? implode(', ', $namen) : ($nichts ? 'keine Position' : 'niemand eingeteilt')) ?>"><span class="ep-txt"><?= $nichts ? '–' : $nBes . ' ' . ($nBes === 1 ? 'Person' : 'Personen') ?></span><?php if ($nOffen > 0): ?><span class="ep-offen-n"><?= $nOffen ?> offen</span><?php endif; ?></div>
                    </td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
              <?php if (!$plan['funktionen']) echo msv_empty_row(count($plan['termine']) + 1, 'Noch keine ' . ($istA ? 'Funktionen' : 'Einsatztypen') . ' – unter «Plan einrichten» anlegen', 'bi-list-task'); ?>
              </tbody>
            </table>
          </div>
          <div class="ep-legend mt-2" aria-label="Legende">
            <span><span class="ep-mini ep-v-msv"><i></i></span>MSV Wilen</span>
            <span><span class="ep-mini ep-v-freienbach"><i></i></span>SV Freienbach</span>
            <span><span class="ep-mini ep-v-wollerau"><i></i></span>SV Wollerau</span>
            <span><span class="ep-mini ep-m-offen"><i></i></span>offen</span>
            <?php if ($istA): ?><span><i class="bi bi-magic ep-vs-i" aria-hidden="true"></i><em>Vorschlag</em> – noch nicht übernommen</span><?php endif; ?>
            <span><span class="ep-mini ep-m-warn"><i></i></span>Mitglied inaktiv oder verstorben – prüfen</span>
            <span><i class="bi bi-check-circle-fill ep-anw-da-l" aria-hidden="true"></i>da <i class="bi bi-x-circle-fill ep-anw-nein-l ms-1" aria-hidden="true"></i>nicht da</span>
          </div>
          </div><!-- /ep-grid-col -->
          <?= $sideHtml ?>
          </div><!-- /ep-editor-row -->

          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($plan && !$ansichtAbr):
  // ---------- Slide-Panel: Position bearbeiten (beide Layouts; Verein nur bei Obli/Feld) ----------
  $panel_id = 'epSlotPanel'; $panel_class = 'hybrid-edit-panel'; $panel_overlay_id = 'epSlotOverlay'; $panel_close_id = 'epSlotClose';
  $panel_title = '<i class="bi bi-person-badge me-2"></i><span id="epSlotTitle">Position</span>';
  ob_start(); ?>
    <div class="panel-section"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i><span id="epSlotTermin"></span></div>
    <!-- Speicherstand: jede Änderung wird sofort gespeichert; hier steht, ob das geklappt hat -->
    <div class="ep-panel-stand mb-3" id="epSlotStand" role="status" aria-live="polite"><span class="ep-hint">Änderungen werden sofort gespeichert.</span></div>
    <div class="mb-3<?= $istA ? '' : ' d-none' ?>">
      <label class="panel-label d-block">Verein</label>
      <div class="btn-group btn-group-sm w-100" role="group" id="epVerein">
        <?php foreach (EP_VEREINE as $vk => $vl): ?>
        <input type="radio" class="btn-check" name="epVerein" id="epVerein_<?= $vk ?>" value="<?= $vk ?>"><label class="btn btn-outline-secondary" for="epVerein_<?= $vk ?>"><?= $h($vl) ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="mb-3" id="epMitgliedBlock">
      <label class="panel-label" for="epMitglied">Mitglied</label>
      <input type="text" class="form-control form-control-sm" id="epMitgliedFilter" placeholder="Filtern…" autocomplete="off">
      <select class="form-select form-select-sm" id="epMitglied" size="8">
        <option value="0">– kein Mitglied –</option>
        <optgroup label="Aktive Mitglieder"><?= $optAktiv ?></optgroup>
        <?php if ($optInaktiv): ?><optgroup label="Inaktive"><?= $optInaktiv ?></optgroup><?php endif; ?>
      </select>
      <div class="ep-hint mt-1 ep-verf-badge" id="epVerfBadge"></div>
    </div>
    <div class="mb-3">
      <label class="panel-label" for="epNameText"><span id="epNameTextLabel">Name (extern, ohne Mitgliedschaft)</span></label>
      <input type="text" class="form-control form-control-sm" id="epNameText" maxlength="100" placeholder="Name Vorname">
      <div class="ep-hint mt-1" id="epNameTextHint">Leer lassen, wenn der Verein den Namen erst später meldet – im Word erscheint dann der Vereinsname.</div>
    </div>
    <div class="mb-3">
      <label class="panel-label" for="epBemerkung">Bemerkung</label>
      <input type="text" class="form-control form-control-sm" id="epBemerkung" maxlength="100">
    </div>
    <?php if ($plan['typ'] === 'schlossturm'): ?>
    <div class="form-check form-switch mb-3" data-tooltip="Im Original-Einsatzplan «OK» statt «x»; zählt in der Helferabrechnung nur mit dem Schalter «OK-Einsätze mitzählen»">
      <input class="form-check-input" type="checkbox" role="switch" id="epOk">
      <label class="form-check-label" for="epOk">OK-Mitglied (Organisationskomitee)</label>
      <div class="ep-hint d-none" id="epOkFn">Diese Funktion hat die Rolle «OK» – alle ihre Positionen zählen als OK (Plan einrichten → Funktionen).</div>
    </div>
    <?php endif; ?>
  <?php $panel_body = ob_get_clean();
  ob_start(); ?>
    <div class="d-flex gap-2 flex-wrap w-100">
      <button type="button" class="btn btn-outline-primary btn-sm d-none" id="epSlotVorschlagOk" data-tooltip="Diesen Einteilungs-Vorschlag als feste Einteilung übernehmen"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Vorschlag übernehmen</button>
      <button type="button" class="btn btn-outline-<?= $istA ? 'secondary' : 'danger' ?> btn-sm" id="epSlotLeeren"><i class="bi bi-<?= $istA ? 'eraser' : 'trash' ?> me-1" aria-hidden="true"></i><?= $istA ? 'Leeren' : 'Position entfernen' ?></button>
      <button type="button" class="btn btn-primary btn-sm ms-auto" id="epSlotFertig">Fertig</button>
    </div>
  <?php $panel_footer = ob_get_clean(); include 'partials/side_panel.inc.php'; ?>
<?php endif; ?>

<?php if ($plan && !$ansichtAbr && $plan['termine']): ?>
<?php if ($plan['typ'] === 'schlossturm'):
  // OK-Mitglieder: eine Zeile je Person im Plan (fest besetzte Positionen), Häkchen gilt für alle ihre Positionen
  $okPersonen = [];
  $okStamm = ep_ok_stamm_laden($db);
  $fById = []; foreach ($plan['funktionen'] as $f) $fById[(int)$f['id']] = $f;
  foreach ($plan['slots'] as $s) {
      if (!ep_slot_fix($s)) continue;
      $k = ep_person_key((int)$s['mitglied_id'], (string)$s['name_text']);
      $okPersonen[$k] ??= ['name' => ep_slot_text($s, $mitglieder), 'verein' => $s['verein'] ?? 'msv', 'mid' => (int)$s['mitglied_id'], 'name_text' => (string)$s['name_text'], 'n' => 0, 'ok' => 0, 'fn' => 0,
                           'stamm' => ep_ok_stamm_hat($okStamm, (int)$s['mitglied_id'], (string)$s['name_text'])];
      $okPersonen[$k]['n']++;
      $okPersonen[$k]['vereine'][$s['verein'] ?? 'msv'] = true;
      if ((int)($s['ok'] ?? 0) === 1) $okPersonen[$k]['ok']++;
      if (($fById[(int)$s['funktion_id']]['rolle'] ?? '') === 'OK') $okPersonen[$k]['fn']++;
  }
  $vOrder = array_flip(array_keys(EP_VEREINE));
  uasort($okPersonen, fn($a, $b) => [$vOrder[$a['verein']] ?? 9, $a['name']] <=> [$vOrder[$b['verein']] ?? 9, $b['name']]);
?>
<!-- ================= MODAL: OK-Mitglieder (Schlossturm) ================= -->
<div class="modal fade" id="epOkModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-award me-2" aria-hidden="true"></i>OK-Mitglieder – wer gehört zum Organisationskomitee?<button type="button" class="btn-help" data-help="einsatzplanung.ok" aria-label="Hilfe"></button></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
      <input type="text" class="form-control form-control-sm" id="epOkFilter" placeholder="Name filtern…" style="max-width:220px" autocomplete="off">
      <span class="text-muted small ms-auto"><b id="epOkAnzahl"><?= count(array_filter($okPersonen, fn($p) => $p['ok'] > 0 || $p['fn'] >= $p['n'])) ?></b> von <?= count($okPersonen) ?> Personen als OK markiert</span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm ep-ok-liste mb-0">
        <thead><tr><th>Name</th><th>Verein</th><th class="text-center">Positionen</th><th class="text-center" style="width:110px" data-tooltip="OK in diesem Plan (alle Positionen der Person)">OK im Plan</th><th class="text-center" style="width:110px" data-tooltip="Stammliste: gilt automatisch in jedem künftigen Plan (Import, Kopie, Einteilen)">dauerhaft</th></tr></thead>
        <tbody>
        <?php if (!$okPersonen) echo msv_empty_row(5, 'Noch keine besetzten Positionen'); ?>
        <?php foreach ($okPersonen as $p): $nurFn = $p['fn'] >= $p['n']; ?>
          <tr data-suche="<?= $h(mb_strtolower($p['name'])) ?>">
            <td class="fw-semibold"><?= $h($p['name']) ?><?php if ($p['fn'] > 0): ?> <span class="ui-chip ms-1" data-tooltip="<?= $p['fn'] ?> Position(en) in Funktionen mit Rolle «OK» – zählen immer als OK">Funktion OK<?= $nurFn ? '' : ' ' . $p['fn'] . '/' . $p['n'] ?></span><?php endif; ?></td>
            <td><span class="ep-dot-verein <?= $h($p['verein']) ?>" aria-hidden="true"></span><?= $h(EP_VEREINE[$p['verein']] ?? $p['verein']) ?><?php if (count($p['vereine']) > 1): ?> <span class="ui-chip ms-1" data-tooltip="Diese Person ist in diesem Plan bei mehreren Vereinen eingetragen (<?= $h(implode(' / ', array_map(fn($k) => EP_VEREINE[$k] ?? $k, array_keys($p['vereine'])))) ?>) – im Raster korrigieren"><span class="ui-punkt" aria-hidden="true"></span><?= count($p['vereine']) ?> Vereine – prüfen</span><?php endif; ?></td>
            <td class="text-center"><?= (int)$p['n'] ?></td>
            <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input ep-ok-toggle" type="checkbox" role="switch" data-mid="<?= $p['mid'] ?>" data-name="<?= $h($p['name_text']) ?>" <?= $p['ok'] > 0 || $nurFn ? 'checked' : '' ?> <?= $nurFn ? 'disabled' : '' ?>></div></td>
            <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input ep-ok-stamm" type="checkbox" role="switch" data-mid="<?= $p['mid'] ?>" data-name="<?= $h($p['name_text']) ?>" <?= $p['stamm'] ? 'checked' : '' ?>></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="ep-hint mt-2"><b>OK im Plan</b> setzt das Kennzeichen auf allen Positionen der Person in diesem Plan – wie im Original-Einsatzplan, wo bei OK-Mitgliedern «OK» statt «x» steht. <b>Dauerhaft</b> merkt die Person in der Stammliste: in jedem künftigen Plan (Import, Kopie, Einteilen) ist sie automatisch OK; Ausschalten entfernt nur den Stammeintrag. Im Word wird «OK» gedruckt; in der Abrechnung zählen OK-Positionen nur mit dem Schalter «OK-Einsätze mitzählen».</div>

    <?php $okFnListe = ep_ok_funktionen_laden($db); $slotsProFn = []; foreach ($plan['slots'] as $s) if (ep_slot_fix($s)) $slotsProFn[(int)$s['funktion_id']] = ($slotsProFn[(int)$s['funktion_id']] ?? 0) + 1; ?>
    <h6 class="small text-uppercase text-muted mt-4 mb-1"><i class="bi bi-list-task me-1"></i>Funktionen, die immer vom OK besetzt sind</h6>
    <div class="table-responsive">
      <table class="table table-sm ep-ok-liste mb-0" id="epOkFunktionen">
        <thead><tr><th>Funktion</th><th class="text-center">besetzte Positionen</th><th class="text-center" style="width:110px" data-tooltip="Definition für alle Jahre: alle Positionen dieser Funktion zählen als OK (Rolle «OK» wird gesetzt)">immer OK</th></tr></thead>
        <tbody>
        <?php foreach ($plan['funktionen'] as $f): $inListe = in_array(ep_funktion_norm((string)$f['bezeichnung']), $okFnListe, true); ?>
          <tr data-fid="<?= (int)$f['id'] ?>">
            <td class="fw-semibold"><?= $h(trim(($f['gruppe'] ? $f['gruppe'] . ': ' : '') . $f['bezeichnung'])) ?><?php if (($f['rolle'] ?? '') === 'OK' && !$inListe): ?> <span class="ui-chip ms-1" data-tooltip="Rolle «OK» nur in diesem Plan (Plan einrichten → Funktionen)">nur dieser Plan</span><?php endif; ?></td>
            <td class="text-center"><?= (int)($slotsProFn[(int)$f['id']] ?? 0) ?></td>
            <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input ep-ok-fn" type="checkbox" role="switch" data-fid="<?= (int)$f['id'] ?>" <?= $inListe ? 'checked' : '' ?>></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="ep-hint mt-2">Die Definition gilt für alle Jahre (z.B. EDV / Anlage, Schiessleitung) und wird beim Import, beim Anlegen und Kopieren eines Plans angewendet – hier sofort auf diesen Plan. Personen in solchen Funktionen brauchen kein eigenes Häkchen.</div>
  </div>
  <div class="modal-footer py-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Schliessen</button></div>
</div></div></div>
<?php endif; ?>

<!-- ================= MODAL: Anwesenheit erfassen (Desktop, alle Layouts) ================= -->
<div class="modal fade" id="epAnwModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-person-check me-2"></i>Anwesenheit erfassen – wer war da, wer nicht</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="an-termine" id="epAnwTermine">
      <?php foreach ($plan['termine'] as $t): ?><button type="button" data-termin="<?= (int)$t['id'] ?>" class="<?= $t['datum'] === date('Y-m-d') ? 'aktiv' : '' ?>"><?= $h(ep_datum_kurz($t['datum']) . ' ' . ep_zeit_text($t)) ?><?= $t['datum'] === date('Y-m-d') ? ' · heute' : '' ?></button><?php endforeach; ?>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
      <div class="an-stat mb-0"><span class="da"><i class="bi bi-check-lg"></i> <b id="anDa">0</b> da</span><span class="nein"><i class="bi bi-x-lg"></i> <b id="anNein">0</b> nicht da</span><span><b id="anOffen">0</b> offen</span></div>
      <button type="button" class="btn btn-outline-success btn-sm ms-auto" id="anAlleDa"><i class="bi bi-check2-all me-1"></i>Alle offenen als «da»</button>
    </div>
    <div class="an-grid" id="epAnwListe"></div>
    <div class="ep-hint mt-2">Erneutes Antippen der aktiven Taste setzt auf «nicht erfasst» zurück. Nur feste Positionen mit Person; Vorschläge und leere Positionen erscheinen nicht. Dieselbe Erfassung gibt es im Portal fürs Handy.</div>
  </div>
  <div class="modal-footer py-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Schliessen</button></div>
</div></div></div>
<?php endif; ?>

<!-- ================= MODAL: Auswertung Anwesenheit ================= -->
<div class="modal fade" id="epAuswModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-clipboard-data me-2"></i><span id="epAuswTitel">Auswertung Anwesenheit</span></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="epAuswBody"><div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Lade…</div></div>
  <div class="modal-footer py-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Schliessen</button></div>
</div></div></div>

<!-- ================= MODAL: Neuer Plan ================= -->
<div class="modal fade" id="epNeuModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Neuer Einsatzplan</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-2 mb-3">
      <div class="col-7"><label class="form-label small mb-1">Typ</label>
        <select class="form-select form-select-sm" id="epNeuTyp"><?php foreach (EP_TYPEN as $k => $v): ?><option value="<?= $k ?>"><?= $h($v) ?></option><?php endforeach; ?></select></div>
      <div class="col-5"><label class="form-label small mb-1">Jahr</label>
        <select class="form-select form-select-sm" id="epNeuJahr"><?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $y === (int)date('Y') + 1 ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?></select></div>
      <div class="col-12"><label class="form-label small mb-1">Titel</label><input type="text" class="form-control form-control-sm" id="epNeuTitel" maxlength="100"></div>
    </div>
    <label class="form-label small mb-1">Quelle</label>
    <div class="form-check"><input class="form-check-input" type="radio" name="epNeuQuelle" id="epQuelleLeer" value="leer" checked><label class="form-check-label" for="epQuelleLeer">Neu, ohne Personen <span class="text-muted small">(empfohlen für ein neues Jahr)</span></label></div>
    <div class="form-check ms-4" id="epStrukturBlock"><input class="form-check-input" type="checkbox" id="epNeuStruktur" checked aria-describedby="epStrukturWas"><label class="form-check-label small" for="epNeuStruktur">Aufbau von <strong id="epStrukturVon">–</strong> übernehmen</label>
      <div class="ep-hint" id="epStrukturWas">Funktionen mit Anzahl, Gruppe und Rolle, Titelfarbe und Fusstext; Termine auf den gleichen Wochentag verschoben, sofern sie nicht aus der JM-Definition kommen.</div></div>
    <div class="ep-hint ms-4 d-none" id="epStrukturHint">Noch kein Plan dieses Typs vorhanden – es werden die Standard-Funktionen angelegt.</div>
    <div class="form-check"><input class="form-check-input" type="radio" name="epNeuQuelle" id="epQuelleKopie" value="kopie" <?= $allePlaene ? '' : 'disabled' ?>><label class="form-check-label" for="epQuelleKopie">Kopie eines früheren Plans, mit den Personen <span class="text-muted small">(Stand nach allen Tauschen)</span></label></div>
    <div class="ep-hint ms-4 d-none" id="epKopieHint">Für den gewählten Typ gibt es noch keinen Plan zum Kopieren.</div>
    <select class="form-select form-select-sm ms-4 mb-2 d-none" id="epNeuQuellePlan" style="width:calc(100% - 1.5rem)">
      <?php foreach ($allePlaene as $p): ?><option value="<?= (int)$p['id'] ?>" data-typ="<?= $h($p['typ']) ?>" data-jahr="<?= (int)$p['jahr'] ?>"><?= $h($p['titel']) ?> (<?= (int)$p['jahr'] ?>, <?= $h(EP_STATUS[$p['status']] ?? '') ?>)</option><?php endforeach; ?>
    </select>
    <div class="form-check"><input class="form-check-input" type="radio" name="epNeuQuelle" id="epQuelleDok" value="dokument" <?= $dokumente ? '' : 'disabled' ?>><label class="form-check-label" for="epQuelleDok">Aus hochgeladenem Dokument (Word Obli/Feld, Excel Chilbi)</label></div>
    <select class="form-select form-select-sm ms-4 mb-2 d-none" id="epNeuQuelleDok" style="width:calc(100% - 1.5rem)">
      <?php foreach ($dokumente as $d): ?><option value="<?= (int)$d['id'] ?>" data-jahr="<?= (int)$d['jahr'] ?>"><?= $h($d['titel']) ?> – <?= $h($d['dateiname']) ?></option><?php endforeach; ?>
    </select>
    <div class="form-check mt-2" id="epJmBlock"><input class="form-check-input" type="checkbox" id="epNeuJm" checked><label class="form-check-label" for="epNeuJm">Termine aus der JM-Definition des Jahres übernehmen (Schiesstage)</label></div>
  </div>
  <div class="modal-footer py-2">
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
    <button type="button" class="btn btn-outline-success btn-sm" id="epNeuSpeichern"><i class="bi bi-plus-lg me-1"></i>Anlegen</button>
  </div>
</div></div></div>

<?php if ($plan && !$ansichtAbr): ?>
<!-- ================= MODAL: Termine / Schichten ================= -->
<div class="modal fade" id="epTermineModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-calendar3 me-2"></i><?= $istA ? 'Termine' : 'Schichten' ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <table class="table table-sm ep-struct-table mb-2">
      <thead><tr><th style="width:18%">Bezeichnung</th><th style="width:16%">Datum</th><th style="width:10%">von</th><th style="width:10%">bis</th><th style="width:14%">Zeit-Text</th><th style="width:22%">Info</th><th></th></tr></thead>
      <tbody id="epTermineBody">
        <?php foreach ($plan['termine'] as $t): ?>
        <tr data-id="<?= (int)$t['id'] ?>">
          <td><input type="text" class="form-control form-control-sm t-bez" value="<?= $h($t['bezeichnung']) ?>" aria-label="Bezeichnung"></td>
          <td><input type="date" class="form-control form-control-sm t-datum" value="<?= $h($t['datum']) ?>"></td>
          <td><input type="time" class="form-control form-control-sm t-von" value="<?= $h(substr((string)$t['zeit_von'], 0, 5)) ?>"></td>
          <td><input type="time" class="form-control form-control-sm t-bis" value="<?= $h(substr((string)$t['zeit_bis'], 0, 5)) ?>"></td>
          <td><input type="text" class="form-control form-control-sm t-text" value="<?= $h($t['zeit_text']) ?>" maxlength="30" aria-label="Zeit-Text"></td>
          <td><input type="text" class="form-control form-control-sm t-info" value="<?= $h($t['info'] ?? '') ?>" maxlength="100" aria-label="Info"></td>
          <td class="text-end text-nowrap"><button type="button" class="btn btn-outline-primary btn-sm js-termin-save" data-tooltip="Speichern"><i class="bi bi-check-lg"></i></button> <button type="button" class="btn btn-outline-danger btn-sm js-termin-delete" data-tooltip="Löschen (inkl. Positionen)"><i class="bi bi-trash"></i></button></td>
        </tr>
        <?php endforeach; ?>
        <tr data-id="0" class="table-light">
          <td><input type="text" class="form-control form-control-sm t-bez" placeholder="<?= $istA ? 'z.B. 1. Obli' : 'neu…' ?>" aria-label="Bezeichnung neuer Termin"></td>
          <td><input type="date" class="form-control form-control-sm t-datum" aria-label="Datum neuer Termin"></td>
          <td><input type="time" class="form-control form-control-sm t-von" aria-label="von"></td>
          <td><input type="time" class="form-control form-control-sm t-bis" aria-label="bis"></td>
          <td><input type="text" class="form-control form-control-sm t-text" maxlength="30" placeholder="17:00-fertig" aria-label="Zeit-Text"></td>
          <td><input type="text" class="form-control form-control-sm t-info" maxlength="100" placeholder="Treffpunkt" aria-label="Info"></td>
          <td class="text-end"><button type="button" class="btn btn-outline-success btn-sm js-termin-save" data-tooltip="Hinzufügen"><i class="bi bi-plus-lg"></i></button></td>
        </tr>
      </tbody>
    </table>
    <div class="ep-hint">Zeit-Text überschreibt von/bis in der Anzeige (Chilbi: «17:00-fertig»). Info erscheint klein im Spaltenkopf und im Word (Schlossturm: Treffpunkt / Büro). Geänderte Zeilen sind hinterlegt, bis sie gespeichert sind.<?php if ($plan['status'] !== 'entwurf'): ?> <strong>Der Plan ist freigegeben:</strong> Mitglieder sehen geänderte Daten sofort in «Meine Einsätze».<?php endif; ?></div>
  </div>
  <div class="modal-footer py-2">
    <?php if ($istA && in_array($plan['typ'], ['obligatorisch', 'feldschiessen'], true)): ?>
      <button type="button" class="btn btn-outline-success btn-sm me-auto" id="epTermineAusJm"><i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Aus JM-Definition ergänzen</button>
    <?php endif; ?>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Schliessen</button>
    <button type="button" class="btn btn-primary btn-sm js-zeilen-speichern d-none"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Änderungen speichern (<span class="js-zeilen-n">0</span>)</button>
  </div>
</div></div></div>

<!-- ================= MODAL: Funktionen / Einsatztypen ================= -->
<div class="modal fade" id="epFunktionenModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-list-task me-2"></i><?= $istA ? 'Funktionen' : 'Einsatztypen' ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <table class="table table-sm ep-struct-table mb-2">
      <?php $rolleSelect = function (?string $wert) use ($h): string {
          $o = '<option value="">– keine –</option>';
          foreach (EP_ROLLEN as $k => $label) $o .= '<option value="' . $h($k) . '"' . ($wert === $k ? ' selected' : '') . '>' . $h($label) . '</option>';
          return $o;
      }; ?>
      <thead><tr><?php if ($istA): ?><th style="width:18%">Gruppe</th><?php endif; ?><th>Bezeichnung</th><?php if ($istA): ?><th style="width:11%">pro Termin</th><th style="width:18%" data-tooltip="Rolle in der Personalanfrage/Umfrage – Grundlage der automatischen Einteilung; «OK Schlossturm» = fest durch OK">Anfrage-Rolle</th><?php endif; ?><th style="width:150px"></th></tr></thead>
      <tbody id="epFunktionenBody">
        <?php foreach ($plan['funktionen'] as $f): ?>
        <tr data-id="<?= (int)$f['id'] ?>">
          <?php if ($istA): ?><td><input type="text" class="form-control form-control-sm f-gruppe" value="<?= $h($f['gruppe']) ?>" maxlength="50" aria-label="Gruppe"></td><?php endif; ?>
          <td><input type="text" class="form-control form-control-sm f-bez" value="<?= $h($f['bezeichnung']) ?>" maxlength="100"></td>
          <?php if ($istA): ?><td><input type="number" class="form-control form-control-sm f-anz" value="<?= (int)$f['anzahl'] ?>" min="1" max="20"></td>
          <td><select class="form-select form-select-sm f-rolle"><?= $rolleSelect($f['rolle'] ?? null) ?></select></td><?php endif; ?>
          <td class="text-end text-nowrap">
            <button type="button" class="btn btn-outline-secondary btn-sm js-funktion-move" data-dir="up" data-tooltip="Nach oben"><i class="bi bi-arrow-up"></i></button>
            <button type="button" class="btn btn-outline-secondary btn-sm js-funktion-move" data-dir="down" data-tooltip="Nach unten"><i class="bi bi-arrow-down"></i></button>
            <button type="button" class="btn btn-outline-primary btn-sm js-funktion-save" data-tooltip="Speichern"><i class="bi bi-check-lg"></i></button>
            <button type="button" class="btn btn-outline-danger btn-sm js-funktion-delete" data-tooltip="Löschen"><i class="bi bi-trash"></i></button>
          </td>
        </tr>
        <?php endforeach; ?>
        <tr data-id="0" class="table-light">
          <?php if ($istA): ?><td><input type="text" class="form-control form-control-sm f-gruppe" maxlength="50" placeholder="z.B. Büro" aria-label="Gruppe neue Funktion"></td><?php endif; ?>
          <td><input type="text" class="form-control form-control-sm f-bez" maxlength="100" placeholder="neu…"></td>
          <?php if ($istA): ?><td><input type="number" class="form-control form-control-sm f-anz" value="1" min="1" max="20"></td>
          <td><select class="form-select form-select-sm f-rolle"><?= $rolleSelect(null) ?></select></td><?php endif; ?>
          <td class="text-end"><button type="button" class="btn btn-outline-success btn-sm js-funktion-save" data-tooltip="Hinzufügen"><i class="bi bi-plus-lg"></i></button></td>
        </tr>
      </tbody>
    </table>
    <div class="ep-hint"><?= $istA ? 'Funktionen mit gleicher Gruppe bilden im Word eine Zeile (z.B. «Büro»). «pro Termin» = Anzahl Positionen je Termin; Kürzen ist nur möglich, wenn die oberen Positionen leer sind. Die Anfrage-Rolle verbindet die Funktion mit der Personalanfrage und ist Voraussetzung für «Einteilen».' : 'Einsatztypen stehen in den Zellen des Chilbi-Rasters (z.B. «Einsatz Bar»).' ?> Das Raster wird beim Schliessen aktualisiert.</div>
  </div>
  <div class="modal-footer py-2">
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Schliessen</button>
    <button type="button" class="btn btn-primary btn-sm js-zeilen-speichern d-none"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Änderungen speichern (<span class="js-zeilen-n">0</span>)</button>
  </div>
</div></div></div>

<?php if ($plan['termine']): ?>
<!-- ================= MODAL: Soll / Positionen je Termin ================= -->
<div class="modal fade" id="epSollModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-bullseye me-2"></i><?= $istA ? 'Positionen je Termin' : 'Soll-Besetzung je Schicht' ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="table-responsive">
      <table class="table table-sm ep-soll-table mb-2">
        <thead><tr><th>Funktion<?php if ($istA): ?> <span class="text-muted fw-normal ep-std">(Standard)</span><?php endif; ?></th><?php foreach ($plan['termine'] as $t): ?><th class="text-center"><?= $h(ep_datum_kurz($t['datum'])) ?><br><span class="fw-normal text-muted ep-t-zeit"><?= $h(ep_zeit_text($t)) ?></span></th><?php endforeach; ?></tr></thead>
        <tbody>
          <?php foreach ($plan['funktionen'] as $f): ?>
          <tr><td class="fw-semibold align-middle"><?= $h($f['bezeichnung']) ?><?php if ($istA): ?> <span class="ui-chip ep-std-n" data-tooltip="Standard der Funktion"><?= (int)$f['anzahl'] ?></span><?php endif; ?></td>
            <?php foreach ($plan['termine'] as $t): $key = $t['id'] . '|' . $f['id']; $wert = $istA ? ep_anzahl_pos($plan, (int)$t['id'], $f) : (int)($plan['soll'][$key] ?? 0); ?>
            <td class="text-center"><input type="number" min="<?= $istA ? 1 : 0 ?>" max="99" class="form-control form-control-sm d-inline-block ep-soll<?= $istA && $wert !== (int)$f['anzahl'] ? ' border-primary' : '' ?>" data-key="<?= $key ?>" value="<?= $wert ?>" placeholder="0"></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="ep-hint"><?= $istA
        ? 'Anzahl Positionen dieser Funktion am jeweiligen Termin; blau umrandet = weicht vom Standard der Funktion ab. Kürzen geht nur, wenn die oberen Positionen leer sind. Im Word bekommt jede Funktion so viele Zeilen wie der Termin mit den meisten Positionen; Termine mit weniger Positionen bleiben dort grau.'
        : '0 = kein Soll (keine Anzeige). Fehlen Personen, zeigt das Raster die Lücke bernsteinfarben und das Word schreibt «offen».' ?></div>
  </div>
  <div class="modal-footer py-2">
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
    <button type="button" class="btn btn-primary btn-sm" id="epSollSpeichern"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Speichern</button>
  </div>
</div></div></div>
<?php endif; ?>

<!-- ================= MODAL: Titel / Fusstext ================= -->
<div class="modal fade" id="epMetaModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-type me-2" aria-hidden="true"></i>Titel, Farbe und Fusstext</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label small mb-1" for="epMetaTitel">Titel</label>
    <input type="text" class="form-control form-control-sm mb-3" id="epMetaTitel" maxlength="100" value="<?= $h($plan['titel']) ?>">
    <label class="form-label small mb-1" for="epMetaFarbe">Titelfarbe im Word/PDF</label>
    <div class="d-flex align-items-center gap-2 mb-3">
      <input type="color" class="form-control form-control-color form-control-sm" id="epMetaFarbe" value="<?= $h(preg_match('/^#[0-9a-fA-F]{6}$/', (string)($plan['farbe'] ?? '')) ? $plan['farbe'] : '#D9D9D9') ?>" data-tooltip="Hintergrund der zentrierten Titelzeile; Schrift wird auf dunklen Farben automatisch weiss">
      <div class="d-flex gap-1" id="epMetaFarbePalette">
        <?php foreach (['#D9D9D9' => 'Grau (Standard)', '#2D4373' => 'Vereinsblau', '#C62828' => 'Rot', '#2E7D32' => 'Grün', '#E65100' => 'Orange', '#6A1B9A' => 'Violett', '#F9A825' => 'Gelb'] as $hex => $name): ?>
          <button type="button" class="btn btn-sm p-0 border" style="width:22px;height:22px;background:<?= $hex ?>" data-farbe="<?= $hex ?>" data-tooltip="<?= $name ?>"></button>
        <?php endforeach; ?>
      </div>
      <span class="ep-hint">leer/grau = Standard</span>
    </div>
    <label class="form-label small mb-1" for="epMetaFuss">Fusstext im Word/PDF (eine Zeile pro Absatz)</label>
    <textarea class="form-control form-control-sm" id="epMetaFuss" rows="5"><?= $h($plan['fusstext']) ?></textarea>
  </div>
  <div class="modal-footer py-2">
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
    <button type="button" class="btn btn-primary btn-sm" id="epMetaSpeichern"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Speichern</button>
  </div>
</div></div></div>

<?php if ($istA): ?>
<!-- ================= MODAL: Verfügbarkeiten (Personalanfrage) ================= -->
<div class="modal fade" id="epVerfModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-person-check me-2" aria-hidden="true"></i>Verfügbarkeiten – wer kann wann in welcher Rolle<button type="button" class="btn-help" data-help="einsatzplanung.verfuegbarkeiten" aria-label="Hilfe"></button></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <!-- Quelle 1: Portal-Umfrage (Auswahl seit 1.68.56 hier statt unter «Titel / Fusstext») -->
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
      <label class="small text-muted mb-0" for="epVerfUmfrageWahl">Umfrage im Portal</label>
      <select class="form-select form-select-sm w-auto" id="epVerfUmfrageWahl" data-tooltip="Umfragen der Kategorie «Arbeitseinsatz» oder «Helfer»">
        <option value="0">– keine –</option>
        <?php foreach ($umfragen as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (int)($plan['umfrage_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= $h($u['titel']) ?> (<?= $h($u['status']) ?>)</option><?php endforeach; ?>
      </select>
      <button type="button" class="btn btn-outline-success btn-sm" id="epVerfUmfrage" <?= $plan['umfrage_id'] ? '' : 'disabled' ?>><i class="bi bi-ui-checks me-1" aria-hidden="true"></i>Aus Umfrage übernehmen</button>
      <?php if (!$umfragen): ?><span class="ep-hint">Noch keine Umfrage der Kategorie «Arbeitseinsatz» oder «Helfer» vorhanden.</span><?php endif; ?>
    </div>
    <!-- Quelle 2: Personalanfrage-Excel der Vereine -->
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
      <form id="epVerfExcelForm" class="d-flex flex-wrap gap-2 align-items-center">
        <select class="form-select form-select-sm" name="verein" style="max-width:170px"><?php foreach (EP_VEREINE as $k => $v): ?><option value="<?= $k ?>"><?= $h($v) ?></option><?php endforeach; ?></select>
        <input type="file" class="form-control form-control-sm" name="datei" accept=".xlsx" style="max-width:280px" required>
        <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-upload me-1"></i>Personalanfrage-Excel prüfen</button>
      </form>
      <span class="ms-auto d-flex gap-2">
        <button type="button" class="btn btn-outline-info btn-sm js-export" data-plan="<?= $planId ?>" data-fmt="personalanfrage" data-verein="msv" data-tooltip="Excel mit den MSV-Verfügbarkeiten"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i><span>Personalanfrage MSV (Excel)</span></button>
        <button type="button" class="btn btn-outline-info btn-sm js-export" data-plan="<?= $planId ?>" data-fmt="personalanfrage" data-verein="msv" data-leer="1" data-tooltip="Leere Vorlage für die anderen Vereine"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i><span>Personalanfrage leer (Excel)</span></button>
      </span>
    </div>
    <div id="epVerfImportBox" class="d-none mb-3">
      <div id="epVerfImportMeldungen"></div>
      <div class="table-responsive ep-preview" id="epVerfImportPreview"></div>
      <div class="mt-2 d-flex gap-2"><button type="button" class="btn btn-outline-primary btn-sm" id="epVerfImportSave"><i class="bi bi-check-lg me-1"></i>Ausgewählte übernehmen</button><button type="button" class="btn btn-outline-secondary btn-sm" id="epVerfImportCancel">Abbrechen</button></div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm ep-verf-table mb-2">
        <thead><tr><th style="min-width:170px">Person</th><th style="width:120px">Verein</th><th>Rollen</th><th>Schichten</th><th style="min-width:140px">Bemerkung</th><th style="width:70px">Quelle</th><th style="width:80px"></th></tr></thead>
        <tbody id="epVerfBody">
          <?php $rollenOhneOk = array_filter(EP_ROLLEN, fn($k) => $k !== 'OK', ARRAY_FILTER_USE_KEY);
          foreach ($plan['verfuegbarkeit'] as $v): ?>
          <tr data-id="<?= (int)$v['id'] ?>" data-mid="<?= (int)$v['mitglied_id'] ?>" data-name="<?= $h($v['name_text'] ?? '') ?>">
            <td class="fw-semibold"><?= $h($v['name']) ?><?php if (empty($v['mitglied_id'])): ?> <small class="text-muted fw-normal">(extern)</small><?php endif; ?></td>
            <td><select class="form-select form-select-sm v-verein" <?= !empty($v['mitglied_id']) ? 'disabled' : '' ?>><?php foreach (EP_VEREINE as $k => $lbl): ?><option value="<?= $k ?>" <?= $v['verein'] === $k ? 'selected' : '' ?>><?= $h($lbl) ?></option><?php endforeach; ?></select></td>
            <td><?php foreach ($rollenOhneOk as $k => $lbl): ?><div class="form-check form-check-inline"><input class="form-check-input v-rolle" type="checkbox" value="<?= $h($k) ?>" id="vr<?= (int)$v['id'] ?>_<?= $h($k) ?>" <?= in_array($k, $v['rollen'], true) ? 'checked' : '' ?>><label class="form-check-label" for="vr<?= (int)$v['id'] ?>_<?= $h($k) ?>"><?= $h($k) ?></label></div><?php endforeach; ?></td>
            <td><?php foreach ($plan['termine'] as $t): ?><div class="form-check form-check-inline"><input class="form-check-input v-termin" type="checkbox" value="<?= (int)$t['id'] ?>" id="vt<?= (int)$v['id'] ?>_<?= (int)$t['id'] ?>" <?= in_array((int)$t['id'], $v['termin_ids'], true) ? 'checked' : '' ?>><label class="form-check-label" for="vt<?= (int)$v['id'] ?>_<?= (int)$t['id'] ?>"><?= $h(ep_datum_kurz($t['datum']) . ' ' . substr((string)$t['zeit_von'], 0, 5)) ?></label></div><?php endforeach; ?></td>
            <td><input type="text" class="form-control form-control-sm v-bem" value="<?= $h($v['bemerkung'] ?? '') ?>" maxlength="255"></td>
            <td><span class="ui-chip"><?= $h(EP_QUELLEN[$v['quelle']] ?? $v['quelle']) ?></span></td>
            <td class="text-end text-nowrap"><button type="button" class="btn btn-outline-primary btn-sm js-verf-save" data-tooltip="Diese Zeile speichern" aria-label="Zeile speichern"><i class="bi bi-check-lg" aria-hidden="true"></i></button> <button type="button" class="btn btn-outline-danger btn-sm js-verf-delete" data-tooltip="Löschen" aria-label="Verfügbarkeit löschen"><i class="bi bi-trash" aria-hidden="true"></i></button></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$plan['verfuegbarkeit']) echo msv_empty_row(7, 'Noch keine Verfügbarkeiten – aus der Umfrage übernehmen, Excel einlesen oder unten erfassen', 'bi-person-check'); ?>
          <tr data-id="0" class="table-light">
            <td>
              <select class="form-select form-select-sm v-mitglied mb-1"><option value="0">– Mitglied wählen –</option><optgroup label="Aktive"><?= $optAktiv ?></optgroup><?php if ($optInaktiv): ?><optgroup label="Inaktive"><?= $optInaktiv ?></optgroup><?php endif; ?></select>
              <input type="text" class="form-control form-control-sm v-name" placeholder="oder Name Vorname (extern / anderer Verein)" maxlength="100">
            </td>
            <td><select class="form-select form-select-sm v-verein"><?php foreach (EP_VEREINE as $k => $lbl): ?><option value="<?= $k ?>"><?= $h($lbl) ?></option><?php endforeach; ?></select></td>
            <td><?php foreach ($rollenOhneOk as $k => $lbl): ?><div class="form-check form-check-inline"><input class="form-check-input v-rolle" type="checkbox" value="<?= $h($k) ?>" id="vr0_<?= $h($k) ?>"><label class="form-check-label" for="vr0_<?= $h($k) ?>"><?= $h($k) ?></label></div><?php endforeach; ?></td>
            <td><?php foreach ($plan['termine'] as $t): ?><div class="form-check form-check-inline"><input class="form-check-input v-termin" type="checkbox" value="<?= (int)$t['id'] ?>" id="vt0_<?= (int)$t['id'] ?>"><label class="form-check-label" for="vt0_<?= (int)$t['id'] ?>"><?= $h(ep_datum_kurz($t['datum']) . ' ' . substr((string)$t['zeit_von'], 0, 5)) ?></label></div><?php endforeach; ?></td>
            <td><input type="text" class="form-control form-control-sm v-bem" maxlength="255"></td>
            <td></td>
            <td class="text-end"><button type="button" class="btn btn-outline-success btn-sm js-verf-save" data-tooltip="Hinzufügen" aria-label="Verfügbarkeit hinzufügen"><i class="bi bi-plus-lg" aria-hidden="true"></i></button></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="ep-hint">Umfrage und Excel überschreiben keine manuell erfassten Zeilen. Rolle «OK Schlossturm» wird nicht automatisch eingeteilt. Geänderte Zeilen sind hinterlegt, bis sie gespeichert sind.</div>
  </div>
  <div class="modal-footer py-2">
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Schliessen</button>
    <button type="button" class="btn btn-primary btn-sm js-zeilen-speichern d-none"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Änderungen speichern (<span class="js-zeilen-n">0</span>)</button>
  </div>
</div></div></div>

<!-- ================= MODAL: Automatische Einteilung ================= -->
<div class="modal fade" id="epEinteilungModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-magic me-2" aria-hidden="true"></i>Automatische Einteilung (Vorschlag)<button type="button" class="btn-help" data-help="einsatzplanung.einteilung" aria-label="Hilfe"></button></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <?php
      // Bereitschaft: von allen drei Vereinen müssen Verfügbarkeiten vorliegen, sonst wird nicht eingeteilt
      // $verfProVerein ist oben beim Ablauf berechnet
      $fehlend = array_keys(array_filter($verfProVerein, fn($x) => $x['n'] === 0));
    ?>
    <div class="mb-3">
      <h6 class="small text-uppercase text-muted mb-1">Bereitschaft – Personalanfragen der Vereine</h6>
      <table class="table table-sm table-bordered ep-einteilung-tabelle mb-1" style="max-width:560px">
        <thead><tr><th class="text-start">Verein</th><th>Personen gemeldet</th><th>Quelle</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach (EP_VEREINE as $vk => $vl): $x = $verfProVerein[$vk]; ?>
          <tr><td class="text-start"><?= $h($vl) ?></td><td><?= $x['n'] ?></td><td><?= $h(implode(', ', array_map(fn($q) => EP_QUELLEN[$q] ?? $q, array_keys($x['quellen'])))) ?: '–' ?></td>
              <td class="<?= $x['n'] ? 'text-success' : 'ep-fehlt' ?>"><?= $x['n'] ? '<i class="bi bi-check-circle"></i> vorhanden' : '<i class="bi bi-exclamation-circle"></i> fehlt' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($fehlend): ?>
        <div class="alert alert-warning py-2 px-3 small mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Eingeteilt wird erst, wenn die Personalanfragen aller drei Vereine vorliegen. Es fehlt: <strong><?= $h(implode(', ', array_map(fn($k) => EP_VEREINE[$k], $fehlend))) ?></strong>. Excel-Rückmeldungen unter «Verfügbarkeiten» einlesen.</div>
        <div class="form-check"><input class="form-check-input" type="checkbox" id="epEtTrotzdem"><label class="form-check-label small" for="epEtTrotzdem">Trotzdem einteilen (nur mit den vorhandenen Meldungen, fehlende Vereine bleiben leer)</label></div>
      <?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-3 align-items-center mb-3">
      <div class="form-check"><input class="form-check-input" type="checkbox" id="epEtKonti" checked><label class="form-check-label" for="epEtKonti">Gleiche Funktion über mehrere Schichten bevorzugen</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" id="epEtVerwerfen" checked><label class="form-check-label" for="epEtVerwerfen">Bestehende Vorschläge zuerst verwerfen</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" id="epEtVerein"><label class="form-check-label" for="epEtVerein" data-tooltip="Leere Fremdvereins-Positionen (z.B. aus dem Vorjahr) nur mit Personen dieses Vereins besetzen">Verein der Position beachten</label></div>
      <button type="button" class="btn btn-outline-primary btn-sm ms-auto" id="epEtBerechnen" <?= $fehlend ? 'disabled' : '' ?>><i class="bi bi-magic me-1"></i>Berechnen</button>
    </div>
    <div class="ep-hint mb-2">Fest besetzte Positionen bleiben. Offene Positionen mit Anfrage-Rolle werden nach Knappheit besetzt: Verein im Rückstand zuerst, dann wenig belastete Personen, Kontinuität, wenig flexible Personen vor flexiblen. Eine Person höchstens eine Position pro Schicht, nur wo sie verfügbar ist.</div>
    <div id="epEtErgebnis"></div>
  </div>
  <div class="modal-footer py-2">
    <button type="button" class="btn btn-outline-secondary btn-sm js-vorschlaege d-none" data-aktion="verwerfen">Vorschläge verwerfen</button>
    <button type="button" class="btn btn-outline-primary btn-sm js-vorschlaege d-none" data-aktion="uebernehmen"><i class="bi bi-check2-all me-1" aria-hidden="true"></i>Alle Vorschläge übernehmen</button>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" id="epEtSchliessen">Schliessen und im Raster prüfen</button>
  </div>
</div></div></div>

<!-- ================= MODAL: Rückmeldung einlesen ================= -->
<div class="modal fade" id="epRueckModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header py-2"><h6 class="modal-title"><i class="bi bi-upload me-2" aria-hidden="true"></i>Rückmeldung eines Vereins einlesen<button type="button" class="btn-help" data-help="einsatzplanung.rueckmeldung" aria-label="Hilfe"></button></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <form id="epRueckForm" class="row g-2 align-items-end mb-3">
      <div class="col-md-6"><label class="form-label small mb-1">Ausgefülltes Word (aus diesem Plan exportiert)</label><input type="file" class="form-control form-control-sm" name="datei" accept=".docx" required></div>
      <div class="col-md-3"><label class="form-label small mb-1">Rückmeldung von</label>
        <select class="form-select form-select-sm" name="verein"><option value="beide">beiden Vereinen</option><option value="freienbach">SV Freienbach</option><option value="wollerau">SV Wollerau</option></select></div>
      <div class="col-md-3"><button type="submit" class="btn btn-outline-success btn-sm w-100"><i class="bi bi-search me-1"></i>Prüfen</button></div>
    </form>
    <div id="epRueckMeldungen"></div>
    <div class="ep-preview" id="epRueckPreview"></div>
  </div>
  <div class="modal-footer py-2">
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
    <button type="button" class="btn btn-outline-primary btn-sm d-none" id="epRueckUebernehmen"><i class="bi bi-check-lg me-1"></i>Ausgewählte übernehmen</button>
  </div>
</div></div></div>
<?php endif; ?>
<?php endif; ?>

<script>
$(function () {
  const basePath = (/\/inc(\/|$)/.test(location.pathname)) ? 'einsatzplanung/' : 'inc/einsatzplanung/';
  const CSRF = document.getElementById('csrfToken').value;
  const PLAN_ID = <?= $planId ?>;
  const EP_VEREINE_JS = <?= json_encode(EP_VEREINE) ?>;   // Vereins-Labels zentral aus plan_helpers (EP_VEREINE)
  const post = (file, data, ok, failMsg) => msvPost(basePath + file, Object.assign({ plan_id: PLAN_ID }, data || {}), ok, { csrf: CSRF, failMsg });
  const datumCH = iso => /^\d{4}-\d{2}-\d{2}$/.test(iso || '') ? iso.split('-').reverse().join('.') : (iso || '');
  const mehrzahl = (n, eins, viele) => n + ' ' + (n === 1 ? eins : viele);

  // ---------- Dialoge: Namen fürs Vorlesen, «?»-Hilfe und Rückfragen über einem Dialog ----------
  document.querySelectorAll('.modal').forEach(m => {
    const t = m.querySelector('.modal-title');
    if (t && !m.hasAttribute('aria-labelledby')) { if (!t.id) t.id = m.id + 'Titel'; m.setAttribute('aria-labelledby', t.id); }
    m.querySelectorAll('.btn-close:not([aria-label])').forEach(b => b.setAttribute('aria-label', 'Schliessen'));
  });
  // Bootstrap hält den Fokus im Dialog fest; Hilfefenster und Rückfragen liegen ausserhalb. Solange sie offen sind, Falle lösen.
  function ohneFokusfalle(modalEl, fn) {
    const inst = modalEl && window.bootstrap ? bootstrap.Modal.getInstance(modalEl) : null, falle = inst && inst._focustrap;
    if (falle) falle.deactivate();
    return Promise.resolve(fn()).finally(() => { if (falle && modalEl.classList.contains('show')) falle.activate(); });
  }
  // msv-help.js fängt den Klick in der Capture-Phase ab und stoppt ihn; darum hier ebenfalls in der Capture-Phase am document
  document.addEventListener('click', function (e) {
    const btn = e.target.closest && e.target.closest('.modal .btn-help');
    if (!btn) return;
    const modalEl = btn.closest('.modal'), hilfe = document.getElementById('help-modal');
    if (!hilfe) return;
    ohneFokusfalle(modalEl, () => new Promise(fertig => {
      let offen = false;
      const beob = new MutationObserver(() => {
        if (hilfe.classList.contains('open')) offen = true;
        else if (offen) { beob.disconnect(); fertig(); }
      });
      beob.observe(hilfe, { attributes: true, attributeFilter: ['class'] });
      setTimeout(() => { if (!offen) { beob.disconnect(); fertig(); } }, 4000);   // Hilfetext nicht geladen
    }));
  }, true);
  function dialogOeffnen(id) { const el = document.getElementById(id); if (el) bootstrap.Modal.getOrCreateInstance(el).show(); }
  // Neu laden und denselben Dialog wieder öffnen (z.B. nach dem Hinzufügen einer Person zu den Verfügbarkeiten)
  const dialogKey = 'ep_dialog_' + PLAN_ID;
  function neuLaden(dialogId) { try { if (dialogId) sessionStorage.setItem(dialogKey, dialogId); } catch (e) {} location.reload(); }
  try { const d = sessionStorage.getItem(dialogKey); if (d) { sessionStorage.removeItem(dialogKey); setTimeout(() => dialogOeffnen(d), 0); } } catch (e) {}

  // ---------- Dialoge mit Zeilen (Termine, Funktionen, Verfügbarkeiten, Soll, Titel): nie still verwerfen ----------
  // Geänderte Zeilen werden hinterlegt, im Fuss steht «Änderungen speichern (n)», im Titel «Nicht gespeichert»;
  // Schliessen mit offenen Änderungen fragt nach (Speichern / Verwerfen / Zurück).
  // opts: { modal, zeilen (Selektor im Dialog), speichern($tr, leise) → jqXHR|null  oder  alles() → jqXHR }
  function zeilenSchutz(opts) {
    const $m = $(opts.modal);
    if (!$m.length) return { merken() {}, anzeigen() {} };
    const felder = $z => $z.find('input:not([type=file]), select, textarea');
    const wert = el => (el.type === 'checkbox' || el.type === 'radio') ? (el.checked ? '1' : '0') : String(el.value);
    const alle = () => $m.find(opts.zeilen);
    const offen = () => alle().filter('.ep-zeile-geaendert');
    const geaendert = $z => { const s = $z.data('snap') || []; return felder($z).get().some((el, i) => wert(el) !== s[i]); };
    function merken($z) { $z.each(function () { $(this).data('snap', felder($(this)).map(function () { return wert(this); }).get()).removeClass('ep-zeile-geaendert'); }); }
    function zuruecksetzen($z) { const s = $z.data('snap') || []; felder($z).each(function (i) { if (this.type === 'checkbox' || this.type === 'radio') this.checked = s[i] === '1'; else this.value = s[i]; }); $z.removeClass('ep-zeile-geaendert'); }
    function anzeigen() {
      const n = offen().length;
      $m.find('.js-zeilen-speichern').toggleClass('d-none', n === 0).find('.js-zeilen-n').text(n);
      msvPanelUngespeichert($m.find('.modal-title')[0], n > 0);
    }
    function allesSpeichern() {
      if (opts.alles) { const x = opts.alles(); return x ? $.when(x).then(r => (r && r.success) ? r : $.Deferred().reject()) : $.Deferred().reject(); }
      const liste = offen().get().sort((a, b) => (+!($(a).data('id') > 0)) - (+!($(b).data('id') > 0)));   // neue Zeilen zuletzt
      return liste.reduce((kette, tr) => kette.then(() => {
        const x = opts.speichern($(tr), true);
        return x ? x.then(r => (r && r.success) ? r : $.Deferred().reject()) : $.Deferred().reject();
      }), $.Deferred().resolve());
    }
    let darf = false;
    $m.on('show.bs.modal', function (e) { if (e.target !== this) return; darf = false; merken(alle()); anzeigen(); });
    $m.on('input change', ':input', function () {
      const $z = $(this).closest(opts.zeilen);
      if (!$z.length || !$.contains($m[0], $z[0])) return;
      $z.toggleClass('ep-zeile-geaendert', geaendert($z)); anzeigen();
    });
    $m.find('.js-zeilen-speichern').on('click', function () {
      const n = offen().length, $b = $(this).prop('disabled', true);
      allesSpeichern().done(() => { anzeigen(); msvToast(mehrzahl(n, 'Zeile', 'Zeilen') + ' gespeichert', 'success'); }).always(() => $b.prop('disabled', false));
    });
    $m.on('hide.bs.modal', function (e) {
      if (e.target !== this || darf || !offen().length) return;
      e.preventDefault();
      const schliessen = () => { darf = true; bootstrap.Modal.getInstance(this).hide(); };
      ohneFokusfalle(this, () => msvUngespeichert({})).then(a => {
        if (a === 'speichern') allesSpeichern().done(() => { anzeigen(); schliessen(); });
        else if (a === 'verwerfen') { zuruecksetzen(offen()); anzeigen(); schliessen(); }
      });
    });
    return { merken, anzeigen };
  }

  // ---------- Planliste: ganze Zeile öffnet den Plan ----------
  $(document).on('click', '.ep-list tr[data-href]', function (e) { if (!e.target.closest('button, a, input, select')) location.href = this.dataset.href; });

  // ---------- Export (Liste + Editor) ----------
  // Ausgabe-Baustein msvAusgabe: sperren, Spinner, Download statt neuem Tab (die PDF-Konvertierung dauert
  // einige Sekunden – danach sperrt der Popup-Blocker window.open), Toast. Im Editor-Menü dreht der Spinner am Knopf «Export».
  $(document).on('click', '.js-export', function (e) {
    e.preventDefault();
    const $b = $(this), fmt = String($b.data('fmt'));
    const plan = $b.closest('tr, .ep-tb-group, .modal').find('[data-titel]').first().data('titel')
      || $('.msv-druck[data-plan="' + $b.data('plan') + '"]').first().data('titel') || 'Einsatzplan';
    const art = { docx: 'Word', pdf: 'PDF', xlsx: 'Excel', personalanfrage: 'Personalanfrage' }[fmt] || fmt;
    msvAusgabe(this, {
      url: basePath + 'export_' + fmt + '.php',
      data: { plan_id: $b.data('plan'), ansicht: $b.data('ansicht') || '', verein: $b.data('verein') || '', leer: $b.data('leer') || '' },
      linkPrefix: basePath,
      ladeKnopf: $b.hasClass('dropdown-item') ? $b.closest('.btn-group, .dropdown').find('.dropdown-toggle')[0] : null,
      titel: plan + ' (' + art + ')',
      name: (fmt === 'personalanfrage' ? 'Personalanfrage_' : '') + plan + ($b.data('leer') ? '_leer' : ''),
      fehler: 'Der Export ist fehlgeschlagen. Bitte nochmals versuchen.'
    });
  });

  // ---------- Auswertung Anwesenheit (Plan oder Jahr) ----------
  $(document).on('click', '.js-auswertung', function () {
    const $b = $(this), q = $b.data('plan') ? { plan_id: $b.data('plan') } : { jahr: $b.data('jahr') };
    $('#epAuswTitel').text('Auswertung Anwesenheit ' + ($b.data('plan') ? '' : $b.data('jahr')));
    $('#epAuswBody').html('<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Lade…</div>');
    new bootstrap.Modal(document.getElementById('epAuswModal')).show();
    $.getJSON(basePath + 'anwesenheit_auswertung.php', q).done(r => {
      if (!r || !r.success) { $('#epAuswBody').html('<div class="alert alert-warning small">' + msvEsc((r && r.message) || 'Die Auswertung konnte nicht geladen werden. Bitte später nochmals versuchen.') + '</div>'); return; }
      const V = EP_VEREINE_JS;
      const zelle = (n, cls) => '<td class="num ' + (cls || '') + '">' + n + '</td>';
      let html = '<div class="row g-3"><div class="col-lg-5"><h6 class="small text-uppercase text-muted mb-1">Je Verein</h6><table class="table table-sm table-bordered ep-ausw-table mb-3"><thead><tr><th>Verein</th><th>eingeteilt</th><th>da</th><th>nicht da</th><th>offen</th><th>Std. da</th></tr></thead><tbody>';
      Object.keys(r.vereine).forEach(v => { const z = r.vereine[v]; html += '<tr><td>' + V[v] + '</td>' + zelle(z.eingeteilt) + zelle(z.da) + zelle(z.nein, z.nein ? 'ep-fehlt' : '') + zelle(z.offen) + zelle(z.stunden_da.toLocaleString('de-CH')) + '</tr>'; });
      html += '</tbody></table>';
      if ((r.plaene || []).length > 1) {
        html += '<h6 class="small text-uppercase text-muted mb-1">Je Plan</h6><table class="table table-sm table-bordered ep-ausw-table mb-0"><thead><tr><th>Plan</th><th>eingeteilt</th><th>da</th><th>nicht da</th><th>offen</th></tr></thead><tbody>';
        r.plaene.forEach(p => html += '<tr><td>' + msvEsc(p.titel) + '</td>' + zelle(p.eingeteilt) + zelle(p.da) + zelle(p.nein, p.nein ? 'ep-fehlt' : '') + zelle(p.offen) + '</tr>');
        html += '</tbody></table>';
      }
      html += '</div><div class="col-lg-7"><h6 class="small text-uppercase text-muted mb-1">Je Person (nicht erschienen zuerst)</h6><div class="ep-preview" style="max-height:460px"><table class="table table-sm table-hover ep-ausw-table mb-0"><thead><tr><th>Person</th><th>Verein</th><th>eingeteilt</th><th>da</th><th>nicht da</th><th>offen</th><th>Std. da</th></tr></thead><tbody>';
      (r.personen || []).forEach(p => html += '<tr' + (p.nein ? ' class="ui-offen"' : '') + '><td data-tooltip="' + msvEsc((p.plaene || []).join(', ')) + '">' + msvEsc(p.name) + '</td><td>' + V[p.verein] + '</td>' + zelle(p.eingeteilt) + zelle(p.da) + zelle(p.nein, p.nein ? 'ep-fehlt' : '') + zelle(p.offen) + zelle(p.stunden_da.toLocaleString('de-CH')) + '</tr>');
      html += '</tbody></table></div><div class="ep-hint mt-1">Erfassung pro Schicht im Portal unter «Anwesenheit» (Vorstand/Admin). Std. da = Pauschale bzw. Schichtdauer der Einsätze mit Anwesenheit «da».</div></div></div>';
      $('#epAuswBody').html(html);
    }).fail(xhr => $('#epAuswBody').html('<div class="alert alert-warning small">' + msvEsc(msvXhrMessage(xhr, 'Auswertung nicht verfügbar')) + '</div>'));
  });

  // ---------- Plan löschen: nur Entwürfe ohne erfasste Einsätze, Folgen im Dialog, Sicherung vorher (seit 1.68.56) ----------
  $(document).on('click', '.js-plan-delete', function () {
    const id = parseInt($(this).data('plan'), 10);
    msvPost(basePath + 'plan_delete.php', { plan_id: id, aktion: 'pruefen' }, async info => {
      const titel = '«' + msvEsc(info.titel) + '»';
      if (info.gesperrt) {
        if (info.grund === 'freigegeben') {
          const weiter = await msvLoeschenGesperrt({
            text: 'Der Plan ' + titel + ' ist <strong>' + msvEsc(info.status_label) + '</strong>: Die Einsätze stehen im Portal.',
            bezuege: Object.assign(info.positionen ? { 'Besetzte Positionen': info.positionen } : {}, info.bezuege || {}),
            hinweis: 'Zuerst unter «Status» zurückziehen; danach lässt sich ein Entwurf ohne erfasste Einsätze löschen.',
            alternative: PLAN_ID === id ? 'Zurückziehen …' : 'Plan öffnen'
          });
          if (weiter) { if (PLAN_ID === id) $('.js-publish[data-status="entwurf"]').first().trigger('click'); else location.href = 'einsatzplanung.php?id=' + id; }
        } else {
          await msvLoeschenGesperrt({ text: 'Am Plan ' + titel + ' hängen erfasste Einsätze:', bezuege: info.bezuege || {},
            hinweis: 'Er bleibt als Nachweis der geleisteten Helferstunden erhalten.' });
        }
        return;
      }
      const teile = [];
      if (info.positionen) teile.push('<strong>' + mehrzahl(info.positionen, 'besetzte Position', 'besetzte Positionen') + '</strong> (' + mehrzahl(info.personen, 'Person', 'Personen') + ')');
      if (info.verfuegbarkeiten) teile.push('<strong>' + mehrzahl(info.verfuegbarkeiten, 'gemeldete Verfügbarkeit', 'gemeldete Verfügbarkeiten') + '</strong>');
      const html = 'Möchtest du den Entwurf ' + titel + ' wirklich löschen?'
        + (teile.length ? '<br>Mit gelöscht werden ' + teile.join(' und ') + '.' : '')
        + '<br>Vorher wird die Datenbank gesichert.';
      const res = await msvConfirmDelete('', { html });
      if (!res.isConfirmed) return;
      msvPost(basePath + 'plan_delete.php', { plan_id: id, aktion: 'loeschen' }, r => {
        msvToast(r.message, 'success');
        setTimeout(() => { location.href = 'einsatzplanung.php?year=<?= $plan ? (int)$plan['jahr'] : $selected_year ?>'; }, 900);
      }, { csrf: CSRF, failMsg: 'Der Plan konnte nicht gelöscht werden. Bitte die Seite neu laden und nochmals versuchen.' });
    }, { csrf: CSRF, failMsg: 'Die Prüfung vor dem Löschen ist fehlgeschlagen. Bitte die Seite neu laden.' });
  });

  // ---------- Neuer Plan ----------
  const typLabels = <?= json_encode(EP_TYPEN, JSON_UNESCAPED_UNICODE) ?>;
  function neuTitelVorschlag() {
    const q = $('input[name=epNeuQuelle]:checked').val();
    if (q === 'dokument') return;
    const typ = $('#epNeuTyp').val(), jahr = $('#epNeuJahr').val();
    $('#epNeuTitel').val((typLabels[typ] || '') + ' ' + jahr);
    $('#epJmBlock').toggle(typ === 'obligatorisch' || typ === 'feldschiessen');
  }
  // Quellpläne für «Kopie» nach dem gewählten Typ filtern (der Typ bleibt führend; vorher hat die
  // Quellliste den Typ überschrieben und sprang so auf den ersten Plan «Obligatorisch» zurück).
  function filterQuellePlaene() {
    const typ = $('#epNeuTyp').val();
    let erster = null;
    $('#epNeuQuellePlan option').each(function () {
      const passt = $(this).data('typ') === typ;
      $(this).prop('hidden', !passt).prop('disabled', !passt);
      if (passt && erster === null) erster = this.value;
    });
    const $kopie = $('#epQuelleKopie');
    if (erster !== null) { $('#epNeuQuellePlan').val(erster); $kopie.prop('disabled', false); }
    else { $kopie.prop('disabled', true); if ($kopie.is(':checked')) $('#epQuelleLeer').prop('checked', true).trigger('change'); }
    $('#epKopieHint').toggleClass('d-none', erster !== null);
    // Stammdaten-Vorlage für «Leer»: neuester Plan gleichen Typs (Liste ist nach Jahr absteigend sortiert)
    const $v = $('#epNeuQuellePlan option').filter(function () { return $(this).data('typ') === typ; }).first();
    $('#epStrukturBlock').toggleClass('d-none', !$v.length); $('#epStrukturHint').toggleClass('d-none', !!$v.length);
    $('#epStrukturVon').text($v.length ? $v.text().replace(/\s*\(.*\)$/, '') : '–').data('id', $v.length ? $v.val() : 0);
  }
  $('#epNeuTyp').on('change', function () { filterQuellePlaene(); neuTitelVorschlag(); });
  $('#epNeuJahr').on('change', neuTitelVorschlag);
  $('input[name=epNeuQuelle]').on('change', function () {
    const q = this.value;
    $('#epNeuQuellePlan').toggleClass('d-none', q !== 'kopie');
    $('#epNeuQuelleDok').toggleClass('d-none', q !== 'dokument');
    $('#epStrukturBlock, #epStrukturHint').toggle(q === 'leer');
    $('#epNeuTyp').prop('disabled', q === 'dokument'); // Typ kommt beim Dokument aus der Datei
    if (q === 'dokument') { const o = $('#epNeuQuelleDok option:selected'); if (o.data('jahr')) $('#epNeuJahr').val(String(o.data('jahr'))); $('#epNeuTitel').val(o.text().split(' – ')[0]); }
    else neuTitelVorschlag();
  });
  $('#epNeuQuellePlan').on('change', neuTitelVorschlag);
  $('#epNeuQuelleDok').on('change', function () { $('input[name=epNeuQuelle][value=dokument]').trigger('change'); });
  $('#epNeuModal').on('show.bs.modal', function () { filterQuellePlaene(); neuTitelVorschlag(); });
  $('#epNeuSpeichern').on('click', function () {
    const q = $('input[name=epNeuQuelle]:checked').val();
    const $b = $(this).prop('disabled', true);
    const data = { titel: $('#epNeuTitel').val(), jahr: $('#epNeuJahr').val(), termine_aus_jm: $('#epNeuJm').is(':checked') ? 1 : 0 };
    let file = 'plan_save.php';
    if (q === 'kopie') { data.action = 'copy'; data.quelle_id = $('#epNeuQuellePlan').val(); }
    else if (q === 'dokument') { file = 'plan_from_dokument.php'; data.dokument_id = $('#epNeuQuelleDok').val(); }
    else { data.action = 'create'; data.typ = $('#epNeuTyp').val(); if ($('#epNeuStruktur').is(':checked') && !$('#epStrukturBlock').hasClass('d-none')) data.struktur_von = $('#epStrukturVon').data('id') || 0; }
    msvPost(basePath + file, data, r => { msvToast(r.message, 'success'); setTimeout(() => location.href = 'einsatzplanung.php?id=' + r.plan_id, 500); }, { csrf: CSRF })
      .always(() => $b.prop('disabled', false));
  });

<?php if ($plan && !$ansichtAbr): ?>
  // ---------- Ablauf: Schritte der Schrittleiste und «nächster Schritt» (seit 1.68.56) ----------
  const HAT_ROLLEN = <?= $hatRollen ? 'true' : 'false' ?>;
  const ABLAUF = {
    termine:    () => dialogOeffnen('epTermineModal'),
    funktionen: () => dialogOeffnen('epFunktionenModal'),
    verf:       () => dialogOeffnen('epVerfModal'),
    einteilen:  () => {
      if (HAT_ROLLEN) { dialogOeffnen('epEinteilungModal'); return; }
      msvSwal.fire({ title: 'Zuerst Anfrage-Rollen setzen', icon: 'info', showCancelButton: true,
        html: 'Die automatische Einteilung braucht bei mindestens einer Funktion eine <strong>Anfrage-Rolle</strong> (z.B. Büro, Warner, Parkdienst). Sie verbindet die Funktion mit den gemeldeten Verfügbarkeiten.',
        confirmButtonText: 'Funktionen öffnen', cancelButtonText: 'Abbrechen' })
        .then(r => { if (r.isConfirmed) dialogOeffnen('epFunktionenModal'); });
    },
    vorschlaege: () => $('.ep-toolbar .js-vorschlaege[data-aktion="uebernehmen"]').first().trigger('click'),
    raster:     () => {
      $('#epGrid tbody tr[data-fid]').removeClass('ep-collapsed'); if (typeof collapsedSpeichern === 'function') { collapsedSpeichern(); alleToggleLabel(); }
      document.getElementById('epGrid') && document.getElementById('epGrid').scrollIntoView({ block: 'start' });
      msvToast('Personen aus der Liste rechts auf eine Position ziehen – oder eine Position anklicken und im Panel wählen.', 'info');
    },
    freigeben:  () => $('.js-publish[data-status="freigegeben"]').first().trigger('click'),
    anwesenheit: () => dialogOeffnen('epAnwModal')
  };
  $(document).on('click', '.js-ablauf', function () { const f = ABLAUF[this.dataset.aktion]; if (f) f(); });
  // Aus der Ansicht «Abrechnung» kommend: ?schritt=… öffnet den Schritt direkt
  (function () {
    const s = new URLSearchParams(location.search).get('schritt');
    if (!s || !ABLAUF[s]) return;
    history.replaceState(null, '', location.pathname + '?id=' + PLAN_ID);
    setTimeout(() => ABLAUF[s](), 0);
  })();

  // ---------- Struktur: Termine ----------
  // Termine-Dialog bleibt offen (mehrere Daten nacheinander); Raster wird erst beim Schliessen neu geladen
  let termineGeaendert = false, termineSchutz = null;
  $('#epTermineModal').on('hidden.bs.modal', function (e) { if (e.target === this && termineGeaendert) location.reload(); });
  const TERMIN_KNOEPFE = '<button type="button" class="btn btn-outline-primary btn-sm js-termin-save" data-tooltip="Diese Zeile speichern" aria-label="Zeile speichern"><i class="bi bi-check-lg" aria-hidden="true"></i></button> '
    + '<button type="button" class="btn btn-outline-danger btn-sm js-termin-delete" data-tooltip="Löschen (inkl. Positionen)" aria-label="Termin löschen"><i class="bi bi-trash" aria-hidden="true"></i></button>';
  function terminSpeichern($tr, leise) {
    const neu = !($tr.data('id') > 0);
    if (!$tr.find('.t-datum').val()) { msvToast('Bitte ein Datum wählen.', 'warning'); $tr.find('.t-datum').trigger('focus'); return null; }
    return post('struktur_save.php', { action: 'termin_save', id: $tr.data('id'), bezeichnung: $tr.find('.t-bez').val(), datum: $tr.find('.t-datum').val(),
      zeit_von: $tr.find('.t-von').val(), zeit_bis: $tr.find('.t-bis').val(), zeit_text: $tr.find('.t-text').val(), info: $tr.find('.t-info').val() },
      r => {
        termineGeaendert = true;
        if (!leise) msvToast(r.message, 'success');
        termineSchutz.merken($tr);
        if (neu) {
          // gespeicherte Zeile «einfrieren», neue Eingabezeile mit vorbelegten Zeiten anhängen
          const $neu = $tr.clone();
          $tr.attr('data-id', r.id).data('id', r.id).removeClass('table-light');
          $tr.find('td').last().addClass('text-nowrap').html(TERMIN_KNOEPFE);
          $neu.attr('data-id', '0').data('id', 0);
          $neu.find('.t-bez, .t-datum').val('');   // Zeiten, Zeit-Text und Info bleiben als Vorlage stehen
          $tr.after($neu);
          termineSchutz.merken($neu);
          if (!leise) $neu.find('.t-datum').trigger('focus');
        }
        termineSchutz.anzeigen();
      });
  }
  termineSchutz = zeilenSchutz({ modal: '#epTermineModal', zeilen: '#epTermineBody > tr', speichern: terminSpeichern });
  $('#epTermineBody').on('click', '.js-termin-save', function () { terminSpeichern($(this).closest('tr')); });
  $('#epTermineBody').on('click', '.js-termin-delete', function () {
    const $tr = $(this).closest('tr'), id = $tr.data('id'), datum = datumCH($tr.find('.t-datum').val()), modalEl = document.getElementById('epTermineModal');
    // zuerst fragen, was am Termin hängt: erfasste Anwesenheit sperrt, besetzte Positionen werden genannt und vorher gesichert
    post('struktur_save.php', { action: 'termin_pruefen', id }, info => {
      if (info.gesperrt) {
        ohneFokusfalle(modalEl, () => msvLoeschenGesperrt({ text: 'Der Termin vom <strong>' + msvEsc(datum) + '</strong> wird nicht gelöscht.',
          bezuege: { 'Positionen mit erfasster Anwesenheit': info.anwesenheit }, hinweis: 'Erfasste Einsätze bleiben als Nachweis erhalten.' }));
        return;
      }
      let html = 'Möchtest du den Termin vom <strong>' + msvEsc(datum) + '</strong> wirklich löschen?';
      if (info.positionen) html += '<br>Mit gelöscht werden <strong>' + mehrzahl(info.positionen, 'besetzte Position', 'besetzte Positionen') + '</strong>'
        + (info.mitglieder_portal ? '; ' + mehrzahl(info.mitglieder_portal, 'Mitglied verliert', 'Mitglieder verlieren') + ' den Einsatz in «Meine Einsätze»' : '')
        + '. Vorher wird die Datenbank gesichert.';
      ohneFokusfalle(modalEl, () => msvConfirmDelete('', { html })).then(res => {
        if (!res.isConfirmed) return;
        post('struktur_save.php', { action: 'termin_delete', id }, r => { termineGeaendert = true; $tr.remove(); termineSchutz.anzeigen(); msvToast(r.message, 'success'); });
      });
    });
  });
  // Enter in einer Zeile = diese Zeile speichern
  $('#epTermineBody').on('keydown', 'input', function (e) { if (e.key === 'Enter') { e.preventDefault(); terminSpeichern($(this).closest('tr')); } });
  $('#epTermineAusJm').on('click', function () {
    post('struktur_save.php', { action: 'termine_aus_jm' }, r => { termineGeaendert = true; msvToast(r.message, 'success'); setTimeout(() => neuLaden('epTermineModal'), 700); });
  });

  // ---------- Struktur: Funktionen (speichern ohne Neuladen; das Raster wird beim Schliessen aktualisiert) ----------
  let funktionenGeaendert = false, funktionenSchutz = null;
  $('#epFunktionenModal').on('hidden.bs.modal', function (e) { if (e.target === this && funktionenGeaendert) location.reload(); });
  const FUNKTION_KNOEPFE = '<button type="button" class="btn btn-outline-secondary btn-sm js-funktion-move" data-dir="up" data-tooltip="Nach oben" aria-label="Nach oben"><i class="bi bi-arrow-up" aria-hidden="true"></i></button> '
    + '<button type="button" class="btn btn-outline-secondary btn-sm js-funktion-move" data-dir="down" data-tooltip="Nach unten" aria-label="Nach unten"><i class="bi bi-arrow-down" aria-hidden="true"></i></button> '
    + '<button type="button" class="btn btn-outline-primary btn-sm js-funktion-save" data-tooltip="Diese Zeile speichern" aria-label="Zeile speichern"><i class="bi bi-check-lg" aria-hidden="true"></i></button> '
    + '<button type="button" class="btn btn-outline-danger btn-sm js-funktion-delete" data-tooltip="Löschen" aria-label="Funktion löschen"><i class="bi bi-trash" aria-hidden="true"></i></button>';
  function funktionSpeichern($tr, leise) {
    const neu = !($tr.data('id') > 0);
    if (!$tr.find('.f-bez').val().trim()) { msvToast('Bitte eine Bezeichnung eingeben.', 'warning'); $tr.find('.f-bez').trigger('focus'); return null; }
    return post('struktur_save.php', { action: 'funktion_save', id: $tr.data('id'), gruppe: $tr.find('.f-gruppe').val() || '', bezeichnung: $tr.find('.f-bez').val(), anzahl: $tr.find('.f-anz').val() || 0, rolle: $tr.find('.f-rolle').val() || '' },
      r => {
        funktionenGeaendert = true;
        if (!leise) msvToast(r.message, 'success');
        funktionenSchutz.merken($tr);
        if (neu && r.id) {
          const $neu = $tr.clone();
          $tr.attr('data-id', r.id).data('id', r.id).removeClass('table-light');
          $tr.find('td').last().addClass('text-nowrap').html(FUNKTION_KNOEPFE);
          $neu.attr('data-id', '0').data('id', 0);
          $neu.find('.f-bez').val(''); $neu.find('.f-anz').val('1'); $neu.find('.f-rolle').val('');   // Gruppe bleibt als Vorlage
          $tr.after($neu);
          funktionenSchutz.merken($neu);
          if (!leise) $neu.find('.f-bez').trigger('focus');
        }
        funktionenSchutz.anzeigen();
      });
  }
  funktionenSchutz = zeilenSchutz({ modal: '#epFunktionenModal', zeilen: '#epFunktionenBody > tr', speichern: funktionSpeichern });
  $('#epFunktionenBody').on('click', '.js-funktion-save', function () { funktionSpeichern($(this).closest('tr')); });
  $('#epFunktionenBody').on('keydown', 'input', function (e) { if (e.key === 'Enter') { e.preventDefault(); funktionSpeichern($(this).closest('tr')); } });
  $('#epFunktionenBody').on('click', '.js-funktion-delete', function () {
    const $tr = $(this).closest('tr');
    ohneFokusfalle(document.getElementById('epFunktionenModal'), () => msvConfirmDelete('die Funktion «' + msvEsc($tr.find('.f-bez').val()) + '»')).then(res => {
      if (!res.isConfirmed) return;
      post('struktur_save.php', { action: 'funktion_delete', id: $tr.data('id') }, r => { funktionenGeaendert = true; $tr.remove(); funktionenSchutz.anzeigen(); msvToast(r.message, 'success'); });
    });
  });
  $('#epFunktionenBody').on('click', '.js-funktion-move', function () {
    const btn = this, $tr = $(btn).closest('tr'), dir = btn.dataset.dir;
    const $ziel = dir === 'up' ? $tr.prev('tr') : $tr.next('tr');
    if (!$ziel.length || !($ziel.data('id') > 0)) return;
    post('struktur_save.php', { action: 'funktion_move', id: $tr.data('id'), dir }, () => {
      funktionenGeaendert = true;
      if (dir === 'up') $tr.insertBefore($ziel); else $tr.insertAfter($ziel);
      btn.focus();
    });
  });

  // ---------- Titel, Farbe und Fusstext ----------
  $('#epMetaFarbePalette').on('click', 'button', function () { $('#epMetaFarbe').val(this.dataset.farbe).trigger('change'); });
  function metaSpeichern() {
    return post('plan_save.php', { action: 'update', titel: $('#epMetaTitel').val(), fusstext: $('#epMetaFuss').val(), farbe: $('#epMetaFarbe').val() || '' },
      r => { msvToast(r.message, 'success'); setTimeout(() => location.reload(), 500); });
  }
  zeilenSchutz({ modal: '#epMetaModal', zeilen: '.modal-body', alles: metaSpeichern });
  $('#epMetaSpeichern').on('click', metaSpeichern);

  // ---------- Mitgliederliste rechts (beide Layouts): Zähler, Suche, Drag-Quelle ----------
  // Personen sind Mitglieder (mid > 0) oder Externe (mid = 0, Schlüssel = Name)
  const MitgliederListe = {
    eintrag(mid, name) {   // ohne die Gruppe «Gemeldet» – die hat ihren eigenen Zähler (GemeldetListe)
      return mid > 0 ? $('#epSideList .ep-mitglied[data-mid="' + mid + '"]').not('.gemeldet')
                     : $('#epSideList .ep-mitglied[data-mid="0"]').not('.gemeldet').filter(function () { return (this.dataset.name || '') === (name || ''); });
    },
    verschiebe(altMid, neuMid, altName, neuName) {
      if (altMid === neuMid && (altMid > 0 || (altName || '') === (neuName || ''))) return;
      if (altMid || altName) this.aendere(altMid, -1, altName);
      if (neuMid || neuName) this.aendere(neuMid, +1, neuName);
    },
    aendere(mid, delta, name) {
      const $m = this.eintrag(mid, name);
      if (!$m.length || !delta) return;
      const n = Math.max(0, parseInt($m.find('.ep-m-count').text() || '0', 10) + delta);
      $m.find('.ep-m-count').text(n);
      $m.toggleClass('geplant', n > 0).attr('data-tooltip', n > 0 ? n + ' Einsatz/Einsätze in diesem Plan' : 'noch nicht eingeplant');
      // Mitglieder wandern zwischen den Gruppen «Nicht eingeplant» / «Eingeplant» (alphabetisch einsortiert)
      if (mid > 0) {
        const $ziel = $(n > 0 ? '#epGruppeGeplant' : '#epGruppeOffen');
        if (!$m.parent().is($ziel)) {
          const nm = ($m.data('name') || '').toLowerCase();
          const $vor = $ziel.children('.ep-mitglied').filter(function () { return (this.dataset.name || '').toLowerCase() > nm; }).first();
          if ($vor.length) $m.insertBefore($vor); else $ziel.append($m);
        }
      }
      this.gruppenZaehler();
    },
    gruppenZaehler() {
      $('#epSideList .ep-side-group').each(function () { $(this).find('.ep-g-n').text($(this).find('.ep-mitglied').length); });
    },
    externHinzufuegen(name) {
      name = name.trim().replace(/\s+/g, ' ');
      if (!name) return null;
      const $vorh = this.eintrag(0, name);
      if ($vorh.length) { msvToast('Person ist schon in der Liste', 'info'); return $vorh; }
      const parts = name.split(' '); const vorname = parts.length > 1 ? parts.pop() : ''; const nachname = parts.join(' ');
      const $el = $('<div class="ep-mitglied extern" draggable="true" data-mid="0"></div>')
        .attr({ 'data-name': name, 'data-nachname': nachname, 'data-vorname': vorname, 'data-tooltip': 'noch nicht eingeplant' })
        .append($('<span class="ep-m-name"></span>').text(name)).append('<span class="ep-m-count">0</span>');
      $('#epExterne').append($el);
      $('#epExterne').closest('.ep-side-group').removeClass('ep-collapsed');
      this.gruppenZaehler();
      return $el;
    }
  };
  // Gruppe «Gemeldet» (Verfügbarkeiten aller Vereine): Zähler eingeteilt/angeboten aus dem Raster (feste + vorgeschlagene Positionen)
  const GemeldetListe = {
    refresh() {
      const $chips = $('#epGrid .ep-chip[data-slot].ep-besetzt');
      $('#epGemeldet .ep-mitglied').each(function () {
        const mid = parseInt(this.dataset.mid || '0', 10), name = this.dataset.name || '';
        const n = $chips.filter(function () { return mid > 0 ? parseInt(this.dataset.mid || '0', 10) === mid : (parseInt(this.dataset.mid || '0', 10) === 0 && (this.dataset.name || '') === name); }).length;
        $(this).toggleClass('geplant', n > 0).find('.ep-m-count').text(n + '/' + (this.dataset.angeboten || 0));
      });
    }
  };
  GemeldetListe.refresh();
  // Gruppen der Mitgliederliste ein-/ausklappen (Standard zu; Zustand pro Plan gemerkt)
  const sideKey = 'ep_side_open_' + PLAN_ID;
  const sideAria = () => $('#epSideList .ep-side-group').each(function () { $(this).children('.ep-side-sub').attr('aria-expanded', $(this).hasClass('ep-collapsed') ? 'false' : 'true'); });
  try { JSON.parse(localStorage.getItem(sideKey) || '[]').forEach(g => $('#epSideList .ep-side-group[data-gruppe="' + g + '"]').removeClass('ep-collapsed')); } catch (e) {}
  sideAria();
  $(document).on('click', '#epSideList .ep-side-sub', function () {
    $(this).closest('.ep-side-group').toggleClass('ep-collapsed');
    sideAria();
    try { localStorage.setItem(sideKey, JSON.stringify($('#epSideList .ep-side-group:not(.ep-collapsed)').map(function () { return this.dataset.gruppe; }).get())); } catch (e) {}
  });
  $('#epExternAdd').on('click', function () { if (MitgliederListe.externHinzufuegen($('#epExternNeu').val())) $('#epExternNeu').val('').focus(); });
  $('#epExternNeu').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#epExternAdd').trigger('click'); } });
  $('#epSideFilter').on('input', function () {
    const q = this.value.trim().toLowerCase();
    $('#epSideList .ep-mitglied').each(function () { $(this).toggle(!q || (this.dataset.name || '').toLowerCase().indexOf(q) !== -1); });
    if (q) { $('#epSideList .ep-side-group').removeClass('ep-collapsed'); sideAria(); }   // Suche klappt alle Gruppen auf
  });
  let dragQuelle = null;          // Layout A: Chip, von dem gezogen wird (null = aus der Mitgliederliste)
  let dragQuelleZelle = null;     // Layout B: Zelle, von der gezogen wird
  let sideDropHandler = null;     // wird je Layout gesetzt; liefert true, wenn behandelt
  let dragName = '';              // Name der gezogenen Person (für Externe ohne Mitglieds-ID)
  let dragVerein = 'msv';         // Verein der gezogenen Person (Gruppe «Gemeldet» liefert auch Partnervereine)
  $(document).on('dragstart', '.ep-mitglied', function (e) {
    dragQuelle = null; dragQuelleZelle = null; dragName = this.dataset.name || ''; dragVerein = this.dataset.verein || 'msv';
    e.originalEvent.dataTransfer.setData('text/plain', this.dataset.mid);
    e.originalEvent.dataTransfer.effectAllowed = 'copy';
    $(this).addClass('dragging');
  });
  $(document).on('dragend', '.ep-mitglied', function () { $(this).removeClass('dragging'); $('.ep-chip, .ep-cell, #epGridB th').removeClass('ep-drop'); });
  $('#epSide').on('dragover', function (e) { if (dragQuelle || dragQuelleZelle) { e.preventDefault(); e.originalEvent.dataTransfer.dropEffect = 'move'; } });
  $('#epSide').on('drop', function (e) {
    if (!dragQuelle && !dragQuelleZelle) return;
    e.preventDefault();
    $('#epSide').removeClass('ep-drop-remove');
    if (typeof sideDropHandler === 'function') sideDropHandler();
  });

  // ---------- PDF als Dokument im Portal ablegen ----------
  $('#epPdfAblegen').on('click', function (e) {
    e.preventDefault();
    const $b = $(this);
    msvConfirm('Das PDF wird erzeugt und unter «Dokumente verwalten → Einsatzpläne» für alle Mitglieder abgelegt. Ein bestehendes Dokument wird nicht ersetzt.', 'PDF ins Portal', 'Ja, ablegen').then(res => {
      if (!res.isConfirmed) return;
      const orig = $b.html(); $b.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
      // Nur ablegen; kein neues Fenster mehr (Popup-Blocker nach der Konvertierung). Ansehen unter «Dokumente verwalten».
      post('export_pdf.php', { ablegen: 1 }, r => { msvToast(r.message || 'PDF im Portal abgelegt', 'success'); })
        .always(() => $b.prop('disabled', false).html(orig));
    });
  });

  // ---------- Freigabe: der Dialog nennt, was passiert (wer benachrichtigt wird, was noch offen ist) ----------
  $('.js-publish').on('click', function () {
    const status = $(this).data('status');
    const texte = { freigegeben: 'Der Plan wird ins Portal übertragen: Mitglieder sehen ihre Einsätze unter «Meine Einsätze» und den ganzen Plan unter «Einsatzpläne».',
                    final: 'Der Plan gilt als definitiv, alle Namen sind komplett. Die Einsätze im Portal werden ein letztes Mal aktualisiert; für die Mitglieder ändert sich sonst nichts.',
                    entwurf: 'Der Plan wird aus dem Portal zurückgezogen; die Einsätze der Mitglieder verschwinden aus «Meine Einsätze».' };
    const $fest = $('#epGrid .ep-chip[data-slot].ep-besetzt:not(.ep-vorschlag)');
    const mitglieder = new Set($fest.filter(function () { return (this.dataset.verein || 'msv') === 'msv' && parseInt(this.dataset.mid || '0', 10) > 0; }).map(function () { return this.dataset.mid; }).get()).size;
    const offen = $('#epGrid .ep-chip[data-slot].ep-offen').length + $('#epGrid .ep-chip.ep-ph').length;
    const vs = $('#epGrid .ep-chip.ep-vorschlag').length;
    let html = texte[status];
    if (status !== 'entwurf') {
      const punkte = [mitglieder ? '<strong>' + mehrzahl(mitglieder, 'Mitglied', 'Mitglieder') + '</strong> mit festen Einsätzen' : '<strong>Noch kein Mitglied</strong> fest eingeteilt'];
      if (offen) punkte.push('<strong>' + mehrzahl(offen, 'Position', 'Positionen') + '</strong> noch offen');
      if (vs) punkte.push('<strong>' + mehrzahl(vs, 'Vorschlag', 'Vorschläge') + '</strong> noch nicht übernommen – erscheinen nicht im Portal');
      html += '<ul class="text-start mt-2 mb-0 ps-3">' + punkte.map(p => '<li>' + p + '</li>').join('') + '</ul>';
    }
    msvSwal.fire({ title: { freigegeben: 'Plan freigeben?', final: 'Final setzen?', entwurf: 'Aus dem Portal zurückziehen?' }[status], html,
      icon: status === 'entwurf' ? 'warning' : 'question',
      input: status === 'entwurf' || !mitglieder ? undefined : 'checkbox', inputValue: 1,
      inputPlaceholder: mitglieder ? (mitglieder === 1 ? 'Das eingeteilte Mitglied' : 'Die ' + mitglieder + ' eingeteilten Mitglieder') + ' benachrichtigen (Push und Glocke)' : '',
      showCancelButton: true, confirmButtonText: { freigegeben: 'Freigeben', final: 'Final setzen', entwurf: 'Zurückziehen' }[status], cancelButtonText: 'Abbrechen' })
    .then(res => {
      if (!res.isConfirmed) return;
      post('plan_publish.php', { status, push: res.value ? 1 : 0 }, r => { msvToast(r.message, 'success'); setTimeout(() => location.reload(), 900); });
    });
  });
<?php endif; ?>

<?php if ($plan && !$ansichtAbr): ?>
  // ---------- Positions-Panel (beide Layouts) ----------
  const IST_A = <?= $istA ? 'true' : 'false' ?>;   // false = Chilbi: Positionen dynamisch (add/delete)

  // Neuen Chip in eine Zelle einsetzen (vor Platzhalter/«+»), aus dem Server-Payload befüllen
  function chipEinsetzen($td, s) {
    const $chip = $('<button type="button" class="ep-chip ep-v-msv ep-offen" data-mid="0" data-verein="msv" data-name="" data-bem="" data-ok="0" data-okslot="0" data-okfn="' + ($td.closest('tr').attr('data-okfn') || '0') + '"><span class="ep-dot"></span><span class="ep-txt"></span></button>');
    $chip.attr({ 'data-slot': s.id, 'data-funktion': $td.attr('data-flabel'), 'data-termin': $td.attr('data-tlabel') });
    const $anker = $td.children('.ep-ph, .ep-add').first();
    if ($anker.length) $chip.insertBefore($anker); else $td.append($chip);
    applySlotToChip($chip, s);
    refreshOffen($td);
    return $chip;
  }
  // Helferstunden je Verein (Pauschale bzw. Schichtdauer × feste Positionen), Vorschläge separat – Formel zentral in ep_termin_stunden()
  const EP_TERMIN_STD = <?= json_encode(array_column(array_map(fn($t) => ['id' => (int)$t['id'], 'std' => ep_termin_stunden($t)], $plan['termine']), 'std', 'id')) ?>;
  function updateStunden() {
    const $box = $('#epStunden'); if (!$box.length) return;
    const acc = {}; let tPos = 0, tStd = 0, tVs = 0;
    $('#epGrid td[data-termin]').each(function () {
      const std = EP_TERMIN_STD[this.dataset.termin] || 0;
      $(this).children('.ep-chip[data-slot].ep-besetzt').each(function () {
        const v = this.dataset.verein || 'msv'; acc[v] = acc[v] || { pos: 0, std: 0, vs: 0 };
        if (this.dataset.vorschlag === '1') { acc[v].vs++; tVs++; } else { acc[v].pos++; acc[v].std += std; tPos++; tStd += std; }
      });
    });
    const fmt = n => (Math.round(n * 10) / 10).toLocaleString('de-CH');
    $box.find('tr[data-verein]').each(function () {
      const a = acc[this.dataset.verein] || { pos: 0, std: 0, vs: 0 };
      $(this).find('.ep-st-pos').text(a.pos + ' Pos.'); $(this).find('.ep-st-std').text(fmt(a.std) + ' h'); $(this).find('.ep-st-vs').text(a.vs ? '(+' + a.vs + ' ?)' : '');
    });
    $box.find('tr.ep-st-total .ep-st-pos').text(tPos + ' Pos.'); $box.find('tr.ep-st-total .ep-st-std').text(fmt(tStd) + ' h'); $box.find('tr.ep-st-total .ep-st-vs').text(tVs ? '(+' + tVs + ' ?)' : '');
  }
  updateStunden();

  // Zusammenfassung einer Zelle (eingeklappte Zeile) nachführen
  function refreshSummary($td) {
    updateStunden();
    if (typeof GemeldetListe !== 'undefined') GemeldetListe.refresh();
    if (!$td || !$td.length) return;
    const namen = $td.children('.ep-chip[data-slot].ep-besetzt').map(function () { return $(this).find('.ep-txt').text() + (this.dataset.ok === '1' ? ' (OK)' : ''); }).get();
    const n = namen.length, offen = $td.children('.ep-ph').length + $td.children('.ep-chip[data-slot].ep-offen').length;
    const $s = $td.children('.ep-summary');
    const nichts = n === 0 && offen === 0;
    $s.toggleClass('ep-leer', n === 0).toggleClass('ep-nichts', nichts).attr('data-tooltip', namen.length ? namen.join(', ') : (nichts ? 'keine Position' : 'niemand eingeteilt'));
    $s.find('.ep-txt').text(nichts ? '–' : n + (n === 1 ? ' Person' : ' Personen'));
    $s.find('.ep-offen-n').remove();
    if (offen > 0) $s.append('<span class="ep-offen-n">' + offen + ' offen</span>');
    // Gesamtzahl der Funktion (Anzeige im eingeklappten Zustand)
    const $tr = $td.closest('tr');
    $tr.find('.ep-fn .ep-fn-n').text($tr.find('td .ep-chip[data-slot].ep-besetzt').length);
  }
  // Ein-/Ausklappen der Funktionszeilen: Standard eingeklappt, aufgeklappte pro Plan im Browser gemerkt
  const expandedKey = 'ep_expanded_' + PLAN_ID;
  function expandedLaden() { try { return JSON.parse(localStorage.getItem(expandedKey) || '[]'); } catch (e) { return []; } }
  function collapsedSpeichern() {
    $('#epGrid tbody tr[data-fid]').each(function () { $(this).find('.ep-fn-toggle').attr('aria-expanded', $(this).hasClass('ep-collapsed') ? 'false' : 'true'); });
    try { localStorage.setItem(expandedKey, JSON.stringify($('#epGrid tbody tr[data-fid]:not(.ep-collapsed)').map(function () { return this.dataset.fid; }).get())); } catch (e) {}
  }
  expandedLaden().forEach(fid => $('#epGrid tbody tr[data-fid="' + fid + '"]').removeClass('ep-collapsed'));
  $('#epGrid tbody tr[data-fid]').each(function () { $(this).find('.ep-fn-toggle').attr('aria-expanded', $(this).hasClass('ep-collapsed') ? 'false' : 'true'); });
  // Button-Beschriftung: solange mindestens eine Zeile zu ist → «Alle aufklappen», sonst «Alle einklappen»
  function alleToggleLabel() {
    const $rows = $('#epGrid tbody tr[data-fid]'), zu = $rows.filter('.ep-collapsed').length > 0;
    const text = zu ? 'Alle Funktionszeilen aufklappen' : 'Alle Funktionszeilen einklappen';
    $('#epAlleToggle').attr({ 'data-tooltip': text, 'aria-label': text });
    $('#epAlleToggle i').attr('class', 'bi ' + (zu ? 'bi-arrows-expand' : 'bi-arrows-collapse'));
  }
  alleToggleLabel();
  $(document).on('click', '#epGrid tbody td.ep-fn', function () { $(this).closest('tr').toggleClass('ep-collapsed'); collapsedSpeichern(); alleToggleLabel(); });
  $(document).on('click', '.ep-summary', function () { $(this).closest('tr').removeClass('ep-collapsed'); collapsedSpeichern(); alleToggleLabel(); });
  $('#epAlleToggle').on('click', function () {
    const $rows = $('#epGrid tbody tr[data-fid]');
    const auf = $rows.filter('.ep-collapsed').length > 0;   // mindestens eine zu → alle auf, sonst alle zu
    $rows.toggleClass('ep-collapsed', !auf); collapsedSpeichern(); alleToggleLabel();
  });
  // beim Ziehen über eine eingeklappte Zeile: aufklappen, damit die Zelle als Ablage sichtbar wird
  $(document).on('dragenter', '#epGrid tbody tr.ep-collapsed td', function () { $(this).closest('tr').removeClass('ep-collapsed'); collapsedSpeichern(); alleToggleLabel(); });

  // Chilbi: «offen»-Platzhalter gemäss Soll nachführen
  function refreshOffen($td) {
    if (!$td || !$td.length) return;
    refreshSummary($td);
    if (IST_A) return;
    const soll = parseInt($td.attr('data-soll') || '0', 10), ist = $td.children('.ep-chip[data-slot]').length;
    $td.children('.ep-ph').remove();
    const $add = $td.children('.ep-add');
    for (let i = ist; i < soll; i++) $('<div class="ep-chip ep-offen ep-ph" data-tooltip="Soll noch nicht erreicht – Person hierher ziehen"><span class="ep-dot"></span><span class="ep-txt">offen</span></div>').insertBefore($add);
    refreshSummary($td);
  }
  // Chilbi: Position löschen (Chip verschwindet, Zähler und Platzhalter nachführen)
  function positionLoeschen($chip, ok) {
    const $td = $chip.closest('td');
    post('chilbi_zelle_save.php', { action: 'delete', slot_id: $chip.data('slot') }, r => {
      MitgliederListe.verschiebe(parseInt($chip.attr('data-mid') || '0', 10), 0, chipExternName($chip), '');
      if (Slot.$chip && Slot.$chip.is($chip)) Slot.close();
      $chip.remove(); refreshOffen($td);
      if (typeof ok === 'function') ok(r);
    });
  }
  // Chilbi: Position in Zelle anlegen (mid > 0 oder externer Name)
  function positionAnlegen($td, mid, name, ok) {
    post('chilbi_zelle_save.php', { action: 'add', termin_id: $td.data('termin'), funktion_id: $td.data('funktion'), mitglied_id: mid || 0, name_text: mid ? '' : (name || '') }, r => {
      const $chip = chipEinsetzen($td, r.slot);
      if (r.slot.warnung) msvToast('Mitglied ist ' + r.slot.warnung + ' – bitte prüfen', 'warning');
      if (typeof ok === 'function') ok($chip, r);
    });
  }

  const Slot = {
    $chip: null, $addTd: null,   // $addTd gesetzt = Panel im Modus «neue Position» (Chilbi)
    open(chip) {
      this.$chip = $(chip); this.$addTd = null;
      const d = chip.dataset;
      $('#epSlotTitle').text(d.funktion);
      $('#epSlotTermin').text(d.termin);
      $('input[name=epVerein][value="' + d.verein + '"]').prop('checked', true);
      $('#epMitglied').val(String(d.mid || 0));
      $('#epMitgliedFilter').val('').trigger('input');
      $('#epNameText').val(d.name || '');
      $('#epBemerkung').val(d.bem || '');
      $('#epOk').prop('checked', d.ok === '1').prop('disabled', d.okfn === '1'); $('#epOkFn').toggleClass('d-none', d.okfn !== '1');
      this.vereinUi(d.verein);
      $('#epSlotLeeren').show();
      $('#epSlotVorschlagOk').toggleClass('d-none', d.vorschlag !== '1');
      $('.ep-chip').removeClass('selected'); this.$chip.addClass('selected');
      if (!$('#epSlotPanel').hasClass('open')) panelStand('');
      $('#epSlotPanel').addClass('open'); $('#epSlotOverlay').addClass('show');
    },
    openAdd(td) {
      this.$chip = null; this.$addTd = $(td);
      $('#epSlotTitle').text(this.$addTd.attr('data-flabel') + ' – neue Position');
      $('#epSlotTermin').text(this.$addTd.attr('data-tlabel'));
      $('input[name=epVerein][value="msv"]').prop('checked', true);
      $('#epMitglied').val('0'); $('#epMitgliedFilter').val('').trigger('input');
      $('#epNameText').val(''); $('#epBemerkung').val(''); $('#epOk').prop('checked', false);
      this.vereinUi('msv');
      $('#epSlotLeeren').hide(); $('#epSlotVorschlagOk').addClass('d-none');
      $('.ep-chip').removeClass('selected'); this.$addTd.children('.ep-add').addClass('selected');
      panelStand('');
      $('#epSlotPanel').addClass('open'); $('#epSlotOverlay').addClass('show');
      $('#epMitgliedFilter').trigger('focus');
    },
    close() {
      // Feld mit Fokus zuerst verlassen: das löst «change» und damit das Speichern aus (sonst ginge eine Eingabe mit Esc verloren)
      const a = document.activeElement;
      if (a && document.getElementById('epSlotPanel').contains(a) && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName)) a.blur();
      $('#epSlotPanel').removeClass('open'); $('#epSlotOverlay').removeClass('show'); $('.ep-chip').removeClass('selected'); this.$chip = null; this.$addTd = null;
    },
    vereinUi(v) {
      const msv = v === 'msv';
      $('#epMitgliedBlock').toggle(msv);
      $('#epNameTextLabel').text(msv ? 'Name (extern, ohne Mitgliedschaft)' : 'Name der gemeldeten Person');
      $('#epNameTextHint').toggle(!msv);
    },
    save() {
      if (this.$addTd) {
        // neue Position: sobald Mitglied oder Name gewählt ist
        const mid = parseInt($('#epMitglied').val() || '0', 10), name = $('#epNameText').val().trim(), bem = $('#epBemerkung').val();
        if (!mid && !name) return;
        const $td = this.$addTd;
        panelStand('laeuft');
        positionAnlegen($td, mid, name, $chip => {
          panelStand('ok');
          if (!$('#epSlotPanel').hasClass('open')) {   // Panel inzwischen geschlossen: Bemerkung trotzdem mitspeichern
            if (bem) post('slot_save.php', { slot_id: $chip.data('slot'), verein: 'msv', mitglied_id: mid || 0, name_text: mid ? '' : name, bemerkung: bem }, r => applySlotToChip($chip, r.slot));
            return;
          }
          this.open($chip[0]); if (bem) this.save();
        });
        return;
      }
      if (!this.$chip) return;
      const $chip = this.$chip;
      const verein = $('input[name=epVerein]:checked').val() || 'msv';
      const daten = { slot_id: $chip.data('slot'), verein, mitglied_id: verein === 'msv' ? ($('#epMitglied').val() || 0) : 0,
        name_text: $('#epNameText').val(), bemerkung: $('#epBemerkung').val() };
      if ($('#epOk').length) daten.ok = $('#epOk').is(':checked') ? 1 : 0;   // OK-Kennzeichen nur bei Schlossturm-Plänen im Panel
      panelStand('laeuft');
      post('slot_save.php', daten, r => {
          applySlotToChip($chip, r.slot);
          if (Slot.$chip && Slot.$chip.is($chip)) { panelStand('ok'); if (r.slot.mitglied_id) $('#epNameText').val(''); }
          if (r.slot.warnung) msvToast('Mitglied ist ' + r.slot.warnung + ' – bitte prüfen', 'warning');
        }).done(r => { if (!(r && r.success)) panelStand('fehler'); }).fail(() => panelStand('fehler'));
    }
  };
  // Speicherstand im Panel: das Panel speichert jede Änderung sofort; hier steht, ob es geklappt hat
  function panelStand(zustand) {
    const $s = $('#epSlotStand');
    if (zustand === 'laeuft') $s.html('<span class="ep-hint"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Wird gespeichert …</span>');
    else if (zustand === 'ok') $s.html('<span class="ui-status ok"><span class="ui-punkt" aria-hidden="true"></span>Gespeichert um ' + new Date().toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit' }) + '</span>');
    else if (zustand === 'fehler') $s.html('<span class="ui-status offen"><span class="ui-punkt" aria-hidden="true"></span>Nicht gespeichert – bitte nochmals versuchen</span>');
    else $s.html('<span class="ep-hint">Änderungen werden sofort gespeichert.</span>');
  }
  $(document).on('click', '.ep-chip.ep-add', function () { Slot.openAdd($(this).closest('td')[0]); });

  // OK-Kennzeichen am Chip (Badge vor dem Namen, data-ok) – genutzt vom Panel-Speichern und vom Dialog «OK-Mitglieder»
  function chipOkSetzen($chip, ok) {
    $chip.attr('data-okslot', ok ? '1' : '0');      // eigenes Kennzeichen der Position
    const fn = $chip.attr('data-okfn') === '1';   // Funktion mit Rolle «OK»: immer OK, unabhängig vom Kennzeichen der Position
    ok = ok || fn;
    $chip.find('.ep-ok').remove();
    if (ok && $chip.hasClass('ep-besetzt')) $chip.append('<span class="ep-ok" data-tooltip="' + (fn ? 'OK über die Funktion (Rolle «OK»)' : 'OK-Mitglied') + '">OK</span>');
    $chip.attr('data-ok', ok ? '1' : '0');
  }
  // Vorlese-Name eines Chips: Funktion, Termin, Person, Verein, Zustand (gleich aufgebaut wie im PHP)
  function chipAria($chip) {
    const v = $chip.attr('data-verein') || 'msv', txt = $chip.find('.ep-txt').text() || 'offen';
    $chip.attr('aria-label', ($chip.attr('data-funktion') || '') + ', ' + ($chip.attr('data-termin') || '') + ': ' + txt
      + (v !== 'msv' ? ', ' + (EP_VEREINE_JS[v] || v) : '') + ($chip.hasClass('ep-vorschlag') ? ', Vorschlag' : '')
      + ($chip.attr('data-warn') ? ', Mitglied ' + $chip.attr('data-warn') : ''));
  }
  // Vorschlag-Kennzeichen (Zauberstab vor dem Namen, gestrichelt) setzen oder entfernen
  function chipVorschlag($chip, an) {
    $chip.toggleClass('ep-vorschlag', an).attr('data-vorschlag', an ? '1' : '0');
    $chip.find('.ep-vs-i').remove();
    if (an) $chip.find('.ep-dot').first().after('<i class="bi bi-magic ep-vs-i" aria-hidden="true"></i>');
    chipAria($chip);
  }
  // Chip nach dem Speichern aktualisieren + Zähler in der Mitgliederliste nachführen
  // Externer Name eines Chips (MSV ohne Mitglied), sonst ''
  const chipExternName = $chip => ($chip.attr('data-verein') === 'msv' && !parseInt($chip.attr('data-mid') || '0', 10)) ? ($chip.attr('data-name') || '') : '';
  function applySlotToChip($chip, s) {
    const altMid = parseInt($chip.attr('data-mid') || '0', 10), altName = chipExternName($chip);
    $chip.attr({ 'data-verein': s.verein, 'data-mid': s.mitglied_id, 'data-name': s.name_text, 'data-bem': s.bemerkung, 'data-warn': s.warnung });
    $chip.removeClass('ep-v-msv ep-v-freienbach ep-v-wollerau ep-besetzt ep-offen ep-warn')
         .addClass('ep-v-' + s.verein).addClass(s.besetzt ? 'ep-besetzt' : 'ep-offen').toggleClass('ep-warn', !!s.warnung);
    $chip.find('.ep-txt').text(s.anzeige || 'offen');
    if ($('#epGrid').hasClass('ep-eng')) $chip.find('.ep-txt').attr('data-tooltip', s.anzeige || null);   // Kompaktmodus: voller Name im Tooltip
    $chip.find('.ep-bem').remove();
    chipOkSetzen($chip, !!s.ok);
    if (s.bemerkung) $chip.append('<i class="bi bi-chat-left-text ep-bem" data-tooltip="' + msvEsc(s.bemerkung) + '"></i>');
    const neuName = chipExternName($chip);
    $chip.attr('draggable', (s.mitglied_id || neuName) ? 'true' : 'false');
    chipVorschlag($chip, !!s.vorschlag);   // manuelle Änderung bestätigt einen Vorschlag
    if (typeof updateVorschlagButtons === 'function') updateVorschlagButtons();
    if (neuName && !MitgliederListe.eintrag(0, neuName).length) MitgliederListe.externHinzufuegen(neuName);   // im Panel neu getippter Externer
    MitgliederListe.verschiebe(altMid, parseInt(s.mitglied_id || 0, 10), altName, neuName);
    refreshSummary($chip.closest('td'));
  }
  // Besetzte MSV-Positionen (Mitglied oder Externer) sind ziehbar (entfernen / verschieben)
  $('.ep-chip[data-slot]').each(function () { this.setAttribute('draggable', (parseInt(this.dataset.mid || '0', 10) > 0 || chipExternName($(this))) ? 'true' : 'false'); });

  // Position setzen (gemeinsam für Drop aus Liste und Verschieben); ok() nach Erfolg; name = Externer ohne Mitglied
  function slotSetzen($chip, mid, verein, ok, name) {
    post('slot_save.php', { slot_id: $chip.data('slot'), verein: verein || 'msv', mitglied_id: mid || 0, name_text: mid ? '' : (name || ''), bemerkung: $chip.attr('data-bem') || '' }, r => {
      applySlotToChip($chip, r.slot);
      if (Slot.$chip && Slot.$chip.is($chip)) Slot.open($chip[0]);   // offenes Panel nachführen
      if (r.slot.warnung) msvToast('Mitglied ist ' + r.slot.warnung + ' – bitte prüfen', 'warning');
      if (typeof ok === 'function') ok(r.slot);
    });
  }
  $(document).on('dragstart', '.ep-chip[data-slot][draggable="true"]', function (e) {
    dragQuelle = this; dragName = parseInt(this.dataset.mid || '0', 10) > 0 ? '' : (this.dataset.name || ''); dragVerein = this.dataset.verein || 'msv';   // auch Fremdvereins-Namen verschiebbar
    e.originalEvent.dataTransfer.setData('text/plain', this.dataset.mid);
    e.originalEvent.dataTransfer.effectAllowed = 'move';
    $(this).addClass('dragging');
    $('#epSide').addClass('ep-drop-remove');
  });
  $(document).on('dragend', '.ep-chip[data-slot]', function () { dragQuelle = null; $(this).removeClass('dragging'); $('.ep-chip').removeClass('ep-drop'); $('#epSide').removeClass('ep-drop-remove'); });
  // Ablegen auf der Liste = Position leeren (Obli/Feld) bzw. Position löschen (Chilbi)
  sideDropHandler = function () {
    if (!dragQuelle) return false;
    const $q = $(dragQuelle); dragQuelle = null;
    if (IST_A) slotSetzen($q, 0, $q.attr('data-verein'), () => msvToast('Mitglied von der Position entfernt', 'success'));
    else positionLoeschen($q, () => msvToast('Position entfernt', 'success'));
    return true;
  };
  // Chilbi: Ablegen auf «offen»/«+» = neue Position in dieser Zelle (aus der Liste oder verschoben)
  $(document).on('dragover', '.ep-chip.ep-ph, .ep-chip.ep-add', function (e) { e.preventDefault(); e.originalEvent.dataTransfer.dropEffect = 'copy'; $(this).addClass('ep-drop'); });
  $(document).on('dragleave', '.ep-chip.ep-ph, .ep-chip.ep-add', function () { $(this).removeClass('ep-drop'); });
  $(document).on('drop', '.ep-chip.ep-ph, .ep-chip.ep-add', function (e) {
    e.preventDefault();
    const $td = $(this).removeClass('ep-drop').closest('td');
    const mid = parseInt(e.originalEvent.dataTransfer.getData('text/plain') || '0', 10);
    const name = mid ? '' : dragName;
    const $q = dragQuelle ? $(dragQuelle) : null; dragQuelle = null; dragName = '';
    $('#epSide').removeClass('ep-drop-remove');
    if (!mid && !name) return;
    if ($q && $q.closest('td').is($td)) return;                     // in derselben Zelle
    positionAnlegen($td, mid, name, () => { if ($q) positionLoeschen($q, () => msvToast('Position verschoben', 'success')); });
  });

  $(document).on('dragover', '.ep-chip[data-slot]', function (e) { e.preventDefault(); e.originalEvent.dataTransfer.dropEffect = 'copy'; $(this).addClass('ep-drop'); });
  $(document).on('dragleave', '.ep-chip[data-slot]', function () { $(this).removeClass('ep-drop'); });
  $(document).on('drop', '.ep-chip[data-slot]', function (e) {
    e.preventDefault();
    const $chip = $(this).removeClass('ep-drop');
    const mid = parseInt(e.originalEvent.dataTransfer.getData('text/plain') || '0', 10);
    const name = mid ? '' : dragName;                               // Externer ohne Mitglieds-ID
    const $q = dragQuelle ? $(dragQuelle) : null; dragQuelle = null; dragName = '';
    $('#epSide').removeClass('ep-drop-remove');
    if (!mid && !name) return;
    if ($q && $q.is($chip)) return;                                  // auf sich selbst
    // Ziel-Person: Mitglied oder Klartextname (MSV-Externer oder Partnerverein), Verein des Ziels für den Tausch merken
    const zielMid = parseInt($chip.attr('data-mid') || '0', 10), zielName = zielMid > 0 ? '' : ($chip.attr('data-name') || ''), zielVerein = $chip.attr('data-verein') || 'msv';
    const verein = dragVerein || 'msv'; dragVerein = 'msv';
    if (!$q && zielMid === mid && (mid || zielName === name)) return;
    if ($q) {
      // Verschieben: Ziel bekommt die Person (mit ihrem Verein), Quelle die bisherige Ziel-Person (Tausch) oder wird leer
      const zielBesetzt = zielMid > 0 || zielName !== '';
      slotSetzen($chip, mid, verein, () => {
        if (IST_A || zielBesetzt) slotSetzen($q, zielMid, zielBesetzt ? zielVerein : $q.attr('data-verein'), () => msvToast(zielBesetzt ? 'Positionen getauscht' : 'Person verschoben', 'success'), zielName);
        else positionLoeschen($q, () => msvToast('Person verschoben', 'success'));   // Chilbi: Quelle wird nicht leer, sondern verschwindet
      }, name);
    } else {
      slotSetzen($chip, mid, verein, null, name);
    }
  });
  $(document).on('click', '.ep-chip[data-slot]', function () { Slot.open(this); });
  $('#epSlotClose, #epSlotOverlay, #epSlotFertig').on('click', () => Slot.close());
  $(document).on('keydown', e => { if ($('#epSlotPanel').hasClass('open') && e.key === 'Escape') { Slot.close(); e.stopImmediatePropagation(); } });
  $('input[name=epVerein]').on('change', function () { Slot.vereinUi(this.value); if (this.value !== 'msv') $('#epMitglied').val('0'); Slot.save(); });
  $('#epMitglied').on('change', () => { if ($('#epMitglied').val() !== '0') $('#epNameText').val(''); Slot.save(); });
  $('#epNameText, #epBemerkung, #epOk').on('change', () => Slot.save());   // Auto-Save; #epOk nur bei Schlossturm im Panel
  $('#epNameText').on('input', function () { if (this.value.trim() !== '') $('#epMitglied').val('0'); });
  $('#epSlotLeeren').on('click', () => {
    if (IST_A) { $('#epMitglied').val('0'); $('#epNameText').val(''); $('#epBemerkung').val(''); Slot.save(); return; }
    if (!Slot.$chip) return;
    // Chilbi: die Position verschwindet ganz – mit Person nur nach Rückfrage
    const $c = Slot.$chip, wer = $c.hasClass('ep-besetzt') ? $c.find('.ep-txt').text() : '';
    const weg = () => positionLoeschen($c, () => msvToast('Position entfernt', 'success'));
    if (!wer) { weg(); return; }
    msvConfirmDelete('', { title: 'Position entfernen?', confirmText: 'Ja, entfernen',
      html: 'Die Position «' + msvEsc($c.attr('data-funktion') || '') + '» am ' + msvEsc($c.attr('data-termin') || '') + ' mit <strong>' + msvEsc(wer) + '</strong> wird entfernt.' })
      .then(res => { if (res.isConfirmed) weg(); });
  });
  // Soll-Besetzung / Positionen je Termin speichern
  function sollSpeichern() {
    const map = {};
    $('.ep-soll').each(function () { map[this.dataset.key] = parseInt(this.value || '0', 10) || 0; });
    return post('struktur_save.php', { action: 'soll_save', soll: JSON.stringify(map) }, r => { msvToast(r.message, 'success'); setTimeout(() => location.reload(), 500); });
  }
  zeilenSchutz({ modal: '#epSollModal', zeilen: '.ep-soll-table tbody > tr', alles: sollSpeichern });
  $('#epSollSpeichern').on('click', sollSpeichern);
  // Layout A: Standardanzahl der Funktion als Vorbelegung markieren (blau = Abweichung)
  $('.ep-soll').on('input', function () { const std = parseInt($(this).closest('tr').find('.ep-std-n').text() || '0', 10); if (std) $(this).toggleClass('border-primary', parseInt(this.value || '0', 10) !== std); });
  $('#epMitgliedFilter').on('input', function () {
    const q = this.value.trim().toLowerCase();
    $('#epMitglied option').each(function () { if (this.value === '0') return; $(this).toggle(!q || this.text.toLowerCase().indexOf(q) !== -1); });
  });
<?php endif; ?>

<?php if ($plan && !$ansichtAbr && $plan['termine']): ?>
  // ---------- Anwesenheit erfassen (Desktop-Dialog; Daten aus den Chips, API wie im Portal; alle Layouts) ----------
  const VEREIN_LABEL = EP_VEREINE_JS;
  let anwTermin = 0;
  function chipAnwSetzen($chip, wert) {
    $chip.find('.ep-anw').remove();
    if (wert === 1) $chip.append('<i class="bi bi-check-circle-fill ep-anw ep-anw-da" data-tooltip="anwesend"></i>');
    else if (wert === 0) $chip.append('<i class="bi bi-x-circle-fill ep-anw ep-anw-nein" data-tooltip="nicht erschienen"></i>');
  }
  function anwZaehlen() {
    $('#anDa').text($('#epAnwListe .an-row.da').length); $('#anNein').text($('#epAnwListe .an-row.nein').length);
    $('#anOffen').text($('#epAnwListe .an-row:not(.da):not(.nein)').length);
  }
  function anwLaden(tid) {
    anwTermin = tid;
    $('#epAnwTermine button').removeClass('aktiv').filter('[data-termin="' + tid + '"]').addClass('aktiv');
    let html = '', letzteFn = null;
    $('#epGrid td[data-termin="' + tid + '"]').each(function () {
      const $td = $(this), fn = $td.attr('data-flabel');
      $td.children('.ep-chip[data-slot].ep-besetzt:not(.ep-vorschlag)').each(function () {
        const $c = $(this), wert = $c.find('.ep-anw-da').length ? 'da' : ($c.find('.ep-anw-nein').length ? 'nein' : '');
        if (letzteFn !== fn) { html += '<div class="an-fn" style="grid-column:1/-1">' + msvEsc(fn) + '</div>'; letzteFn = fn; }
        const wer = msvEsc($c.find('.ep-txt').text());
        html += '<div class="an-row ' + wert + '" data-slot="' + $c.data('slot') + '"><span class="an-dot ' + msvEsc($c.attr('data-verein') || 'msv') + '" aria-hidden="true"></span><div class="an-name">' + wer + '<small>' + (VEREIN_LABEL[$c.attr('data-verein')] || '') + '</small></div>'
              + '<button type="button" class="an-btn ja" data-wert="1" aria-pressed="' + (wert === 'da') + '" aria-label="' + wer + ': da"><i class="bi bi-check-lg" aria-hidden="true"></i></button>'
              + '<button type="button" class="an-btn no" data-wert="0" aria-pressed="' + (wert === 'nein') + '" aria-label="' + wer + ': nicht da"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>';
      });
    });
    $('#epAnwListe').html(html || '<div class="text-muted small py-3" style="grid-column:1/-1">In dieser Schicht ist niemand fest eingeteilt.</div>');
    anwZaehlen();
  }
  function anwSenden(data, ok) {
    $.post('../api/einsatz_anwesenheit.php', Object.assign({ csrf_token: CSRF }, data), null, 'json')
      .done(r => { if (r && r.success) ok(r); else msvToast((r && (r.message || r.error)) || 'Die Anwesenheit konnte nicht gespeichert werden. Bitte nochmals versuchen.', 'error'); })
      .fail(xhr => msvToast(msvXhrMessage(xhr, 'Anwesenheit konnte nicht gespeichert werden'), 'error'));
  }
  $('#epAnwModal').on('show.bs.modal', function () {
    const $akt = $('#epAnwTermine button.aktiv'); anwLaden(parseInt(($akt.length ? $akt : $('#epAnwTermine button').first()).data('termin') || 0, 10));
  });
  $('#epAnwTermine').on('click', 'button', function () { anwLaden(parseInt(this.dataset.termin, 10)); });
  $('#epAnwListe').on('click', '.an-btn', function () {
    const $row = $(this).closest('.an-row'), wert = this.dataset.wert;
    const aktiv = (wert === '1' && $row.hasClass('da')) || (wert === '0' && $row.hasClass('nein'));
    anwSenden({ action: 'set', slot_id: $row.data('slot'), anwesend: aktiv ? '' : wert }, r => {
      $row.removeClass('da nein'); if (r.anwesend === 1) $row.addClass('da'); else if (r.anwesend === 0) $row.addClass('nein');
      $row.find('.an-btn.ja').attr('aria-pressed', r.anwesend === 1 ? 'true' : 'false'); $row.find('.an-btn.no').attr('aria-pressed', r.anwesend === 0 ? 'true' : 'false');
      chipAnwSetzen($('#epGrid .ep-chip[data-slot="' + $row.data('slot') + '"]'), r.anwesend); anwZaehlen();
    });
  });
  $('#anAlleDa').on('click', function () {
    anwSenden({ action: 'alle', plan_id: PLAN_ID, termin_id: anwTermin, anwesend: '1' }, r => {
      $('#epAnwListe .an-row:not(.da):not(.nein)').each(function () { $(this).addClass('da').find('.an-btn.ja').attr('aria-pressed', 'true'); chipAnwSetzen($('#epGrid .ep-chip[data-slot="' + $(this).data('slot') + '"]'), 1); });
      anwZaehlen(); msvToast(r.message, 'success');
    });
  });

<?php endif; ?>

<?php if ($plan && !$ansichtAbr && $plan['typ'] === 'schlossturm'): ?>
  // ---------- OK-Mitglieder (Dialog): Häkchen je Person → alle Positionen der Person im Plan ----------
  // OK im Plan (alle Positionen der Person) bzw. dauerhaft (Stammliste, setzt zugleich OK im Plan)
  function okPersonSpeichern(cb, daten) {
    const $tr = $(cb).closest('tr'), $plan = $tr.find('.ep-ok-toggle');
    cb.disabled = true;
    msvPost(basePath + 'ok_person_save.php', Object.assign({ csrf_token: CSRF, plan_id: PLAN_ID, mitglied_id: parseInt(cb.dataset.mid || '0', 10), name_text: parseInt(cb.dataset.mid || '0', 10) ? '' : (cb.dataset.name || '') }, daten), r => {
      if (r.ok !== null && r.ok !== undefined) {
        (r.slot_ids || []).forEach(id => chipOkSetzen($('.ep-chip[data-slot="' + id + '"]'), !!r.ok));
        $('#epGrid td[data-termin]').each(function () { refreshSummary($(this)); });
        if (!$plan.prop('disabled')) $plan.prop('checked', !!r.ok);
      }
      $('#epOkAnzahl').text($('#epOkModal .ep-ok-toggle:checked').length);
      msvToast(r.message, 'success');
    }, { csrf: CSRF, failMsg: 'OK konnte nicht gespeichert werden', fail: () => { cb.checked = !cb.checked; } }).always(() => { cb.disabled = false; });
  }
  $('#epOkModal').on('change', '.ep-ok-toggle', function () { okPersonSpeichern(this, { ok: this.checked ? 1 : 0 }); });
  $('#epOkModal').on('change', '.ep-ok-stamm',  function () { okPersonSpeichern(this, { dauerhaft: this.checked ? 1 : 0 }); });
  // Funktion «immer vom OK besetzt» (Definition für alle Jahre) – Chips sofort nachführen, Personenliste beim Schliessen neu laden
  let okFnGeaendert = false;
  $('#epOkModal').on('change', '.ep-ok-fn', function () {
    const cb = this; cb.disabled = true;
    msvPost(basePath + 'ok_funktion_save.php', { csrf_token: CSRF, plan_id: PLAN_ID, funktion_id: cb.dataset.fid, ok: cb.checked ? 1 : 0 }, r => {
      okFnGeaendert = true;
      $('#epGrid tr[data-fid]').each(function () { const fid = this.dataset.fid; if (fid in r.funktionen) this.dataset.okfn = r.funktionen[fid] ? '1' : '0'; });
      (r.slots || []).forEach(s => { const $c = $('.ep-chip[data-slot="' + s.id + '"]'); if (!$c.length) return; $c.attr('data-okfn', r.funktionen[s.funktion_id] ? '1' : '0'); chipOkSetzen($c, !!s.ok); });
      $('#epGrid td[data-termin]').each(function () { refreshSummary($(this)); });
      msvToast(r.message, 'success');
    }, { csrf: CSRF, failMsg: 'Definition konnte nicht gespeichert werden', fail: () => { cb.checked = !cb.checked; } }).always(() => { cb.disabled = false; });
  });
  $('#epOkModal').on('hidden.bs.modal', () => { if (okFnGeaendert) location.reload(); });
  $('#epOkFilter').on('input', function () {
    const q = this.value.trim().toLowerCase();
    $('#epOkModal tbody tr[data-suche]').each(function () { $(this).toggle(!q || this.dataset.suche.indexOf(q) >= 0); });
  });
  $('#epOkModal').on('shown.bs.modal', () => $('#epOkFilter').trigger('focus'));
<?php endif; ?>

<?php if ($plan && !$ansichtAbr && $plan['layout'] === 'funktion_x_termin'): ?>
  // ---------- Verfügbarkeiten: Anzeige in Liste und Panel ----------
  const EP_VERF = <?= json_encode(array_column(array_map(fn($v) => ['k' => $v['person_key'], 'r' => $v['rollen'], 't' => array_map('intval', $v['termin_ids']), 'n' => $v['name']], $plan['verfuegbarkeit']), null, 'k'), JSON_UNESCAPED_UNICODE) ?>;
  const EP_TERMIN_LABEL = <?= json_encode(array_column(array_map(fn($t) => ['id' => (int)$t['id'], 'l' => ep_datum_kurz($t['datum']) . ' ' . ep_zeit_text($t)], $plan['termine']), 'l', 'id')) ?>;
  const personKey = (mid, name) => mid > 0 ? 'm' + mid : 'n' + String(name || '').trim().replace(/\s+/g, ' ').toLowerCase();
  const verfVon = (mid, name) => EP_VERF[personKey(mid, name)] || null;
  $('#epVerfFilter').on('change', function () {
    const f = this.value;
    $('#epSideList .ep-mitglied').each(function () {
      const v = verfVon(parseInt(this.dataset.mid || '0', 10), this.dataset.name);
      let ok = true;
      if (f === 'alle') ok = !!(v && v.t.length && v.r.length);
      else if (f) ok = !!(v && v.t.indexOf(parseInt(f, 10)) !== -1 && v.r.length);
      $(this).toggleClass('ep-nv', !ok);
      if (v) $(this).attr('data-tooltip', 'Verfügbar: ' + v.r.join(', ') + ' · ' + v.t.map(id => EP_TERMIN_LABEL[id] || id).join(' | '));
    });
    if (f) $('#epSideList .ep-side-group').removeClass('ep-collapsed');
  });
  // Badge im Panel: ist das gewählte Mitglied für die Schicht der Position gemeldet?
  function verfBadge() {
    const $b = $('#epVerfBadge'); if (!$b.length) return;
    if (!Object.keys(EP_VERF).length) { $b.text(''); return; }
    const mid = parseInt($('#epMitglied').val() || '0', 10);
    const $td = Slot.$chip ? Slot.$chip.closest('td') : (Slot.$addTd || $());
    const tid = parseInt($td.data('termin') || '0', 10);
    if (!mid) { $b.text(''); return; }
    const v = verfVon(mid, '');
    if (!v) { $b.html('<i class="bi bi-question-circle me-1"></i>keine Verfügbarkeit gemeldet'); return; }
    const ok = tid && v.t.indexOf(tid) !== -1;
    $b.html('<i class="bi ' + (ok ? 'bi-check-circle text-success' : 'bi-exclamation-circle text-warning') + ' me-1"></i>' + (ok ? 'verfügbar in dieser Schicht' : 'für diese Schicht nicht gemeldet') + ' · ' + msvEsc(v.r.join(', ') || 'keine Rolle'));
  }
  $('#epMitglied').on('change', verfBadge);
  $(document).on('click', '.ep-chip[data-slot], .ep-chip.ep-add', () => setTimeout(verfBadge, 0));

  // ---------- Einteilungs-Vorschläge: Übernehmen / Verwerfen ----------
  function updateVorschlagButtons() {
    const n = $('#epGrid .ep-chip.ep-vorschlag').length;
    $('.ep-toolbar .js-vorschlaege-group').toggleClass('d-none', n === 0);
    $('.ep-vs-n').text(n);
  }
  $(document).on('click', '.js-vorschlaege', function () {
    const aktion = $(this).data('aktion'), n = $('#epGrid .ep-chip.ep-vorschlag').length;
    ohneFokusfalle(this.closest('.modal'), () => msvConfirm(aktion === 'uebernehmen'
        ? mehrzahl(n, 'Vorschlag wird', 'Vorschläge werden') + ' als feste Einteilung übernommen. Erst danach erscheinen sie im Word und – bei freigegebenen Plänen – im Portal.'
        : mehrzahl(n, 'Vorschlag wird', 'Vorschläge werden') + ' verworfen; die Positionen sind danach wieder offen.',
      aktion === 'uebernehmen' ? 'Vorschläge übernehmen?' : 'Vorschläge verwerfen?', aktion === 'uebernehmen' ? 'Übernehmen' : 'Verwerfen')).then(res => {
      if (!res.isConfirmed) return;
      post('einteilung_vorschlag.php', { action: aktion }, r => { msvToast(r.message, 'success'); setTimeout(() => location.reload(), 600); });
    });
  });
  $('#epSlotVorschlagOk').on('click', function () {
    if (!Slot.$chip) return;
    const $chip = Slot.$chip;
    panelStand('laeuft');
    post('einteilung_vorschlag.php', { action: 'uebernehmen', slot_ids: JSON.stringify([$chip.data('slot')]) }, r => {
      chipVorschlag($chip, false); $('#epSlotVorschlagOk').addClass('d-none'); updateVorschlagButtons(); panelStand('ok'); msvToast('Vorschlag übernommen', 'success');
    });
  });

  // ---------- Einteilung berechnen ----------
  $('#epEtTrotzdem').on('change', function () { $('#epEtBerechnen').prop('disabled', !this.checked); });
  $('#epEtBerechnen').on('click', function () {
    const $b = $(this).prop('disabled', true);
    $('#epEtErgebnis').html('<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Berechne…</div>');
    post('einteilung_vorschlag.php', { action: 'berechnen', kontinuitaet: $('#epEtKonti').is(':checked') ? 1 : 0, verwerfen: $('#epEtVerwerfen').is(':checked') ? 1 : 0, verein_beachten: $('#epEtVerein').is(':checked') ? 1 : 0 }, r => {
      let html = '<div class="alert alert-info py-2 px-3 small mb-2"><i class="bi bi-info-circle me-1"></i>' + msvEsc(r.message) + '</div>';
      (r.warnungen || []).forEach(w => html += '<div class="alert alert-warning py-1 px-2 small mb-1">' + msvEsc(w) + '</div>');
      html += '<div class="row g-3"><div class="col-lg-7"><h6 class="small text-uppercase text-muted mb-1">Besetzung je Schicht (Ist / Ziel, inkl. feste und vorgeschlagene)</h6><table class="table table-sm table-bordered ep-einteilung-tabelle mb-2"><thead><tr><th class="text-start">Schicht</th><th>Positionen</th><th>MSV Wilen</th><th>SV Freienbach</th><th>SV Wollerau</th></tr></thead><tbody>';
      Object.values(r.tabelle || {}).forEach(t => {
        html += '<tr><td class="text-start">' + msvEsc(t.termin) + '</td><td>' + t.positionen + '</td>';
        ['msv', 'freienbach', 'wollerau'].forEach(v => { const z = t.vereine[v]; html += '<td class="' + (z.ist < z.ziel - 0.5 ? 'ep-fehlt' : '') + '">' + z.ist + ' / ' + z.ziel + '</td>'; });
        html += '</tr>';
      });
      html += '</tbody></table></div><div class="col-lg-5">';
      html += '<h6 class="small text-uppercase text-muted mb-1">Offen (' + (r.offen || []).length + ')</h6><div class="ep-preview small mb-2" style="max-height:180px">' + ((r.offen || []).map(o => '<div>' + msvEsc(o.termin + ' · ' + o.funktion + ' #' + o.pos) + ' <span class="text-muted">– ' + msvEsc(o.grund) + '</span></div>').join('') || '<span class="text-muted">keine</span>') + '</div>';
      // Alle gemeldeten Personen: nicht eingeteilte zuerst (rot), dann nach grösstem Rest angeboten − eingeteilt
      const gem = r.gemeldet || [];
      const nNull = gem.filter(g => !g.eingeteilt).length;
      html += '<h6 class="small text-uppercase text-muted mb-1">Gemeldete Personen (' + gem.length + ', davon ' + nNull + ' nicht eingeteilt)</h6><div class="ep-preview small" style="max-height:220px"><table class="table table-sm mb-0"><thead><tr><th>Person</th><th>Verein</th><th>Rollen</th><th class="text-end">angeb.</th><th class="text-end">eingeteilt</th></tr></thead><tbody>';
      gem.forEach(g => { const cls = !g.eingeteilt ? 'ep-fehlt' : (g.eingeteilt < g.angeboten ? 'text-warning' : ''); html += '<tr class="' + cls + '"><td class="fw-semibold">' + msvEsc(g.person) + '</td><td>' + msvEsc(EP_VEREINE_JS[g.verein] || g.verein) + '</td><td class="text-muted">' + msvEsc(g.rollen.join('/')) + '</td><td class="text-end">' + g.angeboten + '</td><td class="text-end">' + g.eingeteilt + '</td></tr>'; });
      html += (gem.length ? '' : '<tr><td colspan="5" class="text-muted">keine Verfügbarkeiten</td></tr>') + '</tbody></table></div>';
      html += '<div class="ep-hint mt-2"><i class="bi bi-hand-index me-1" aria-hidden="true"></i>Anpassen: Dialog schliessen – die Vorschläge stehen gestrichelt mit Zauberstab im Raster. Gemeldete Personen aller Vereine findest du rechts in der Liste unter «Gemeldet» (Zähler eingeteilt/angeboten) und ziehst sie auf eine Position; anschliessend «Vorschläge übernehmen».</div></div></div>';
      html += '<h6 class="small text-uppercase text-muted mt-2 mb-1">Vorschläge (' + (r.vorschlaege || []).length + ')</h6><div class="ep-preview" style="max-height:220px"><table class="table table-sm table-hover mb-0"><thead><tr><th>Schicht</th><th>Funktion</th><th>Verein</th><th>Person</th><th>Begründung</th></tr></thead><tbody>';
      (r.vorschlaege || []).forEach(v => html += '<tr><td>' + msvEsc(v.termin) + '</td><td>' + msvEsc(v.funktion) + '</td><td>' + msvEsc(v.verein) + '</td><td class="fw-semibold">' + msvEsc(v.person) + '</td><td class="small text-muted">' + msvEsc(v.grund) + '</td></tr>');
      html += '</tbody></table></div>';
      $('#epEtErgebnis').html(html);
      $('#epEinteilungModal .js-vorschlaege').toggleClass('d-none', !(r.vorschlaege || []).length);
      $('#epEtSchliessen').off('click.reload').on('click.reload', () => location.reload());
    }).always(() => $b.prop('disabled', false));
  });

  // ---------- Verfügbarkeiten: manuell, Umfrage, Excel ----------
  function verfDaten($tr) {
    return { id: $tr.data('id'), verein: $tr.find('.v-verein').val(), mitglied_id: $tr.data('id') > 0 ? ($tr.data('mid') || 0) : ($tr.find('.v-mitglied').val() || 0),
             name_text: $tr.data('id') > 0 ? ($tr.data('name') || '') : ($tr.find('.v-name').val() || ''),
             rollen: $tr.find('.v-rolle:checked').map(function () { return this.value; }).get(),
             termin_ids: $tr.find('.v-termin:checked').map(function () { return this.value; }).get(), bemerkung: $tr.find('.v-bem').val() || '' };
  }
  // Speichern ohne Neuladen; Raster, Liste «Gemeldet» und Ablauf werden beim Schliessen aktualisiert.
  // Eine neue Person erscheint erst nach dem Neuladen in der Liste – der Dialog öffnet sich dann wieder.
  let verfGeaendert = false, verfSchutz = null;
  $('#epVerfModal').on('hidden.bs.modal', function (e) { if (e.target === this && verfGeaendert) location.reload(); });
  function verfSpeichern($tr, leise) {
    const neu = !($tr.data('id') > 0);
    return post('verfuegbarkeit_save.php', Object.assign({ action: 'save' }, verfDaten($tr)), r => {
      verfGeaendert = true;
      if (!leise) msvToast(r.message, 'success');
      verfSchutz.merken($tr); verfSchutz.anzeigen();
      if (neu) setTimeout(() => neuLaden('epVerfModal'), leise ? 0 : 500);
    });
  }
  verfSchutz = zeilenSchutz({ modal: '#epVerfModal', zeilen: '#epVerfBody > tr[data-id]', speichern: verfSpeichern });
  $('#epVerfBody').on('click', '.js-verf-save', function () { verfSpeichern($(this).closest('tr')); });
  $('#epVerfBody').on('click', '.js-verf-delete', function () {
    const $tr = $(this).closest('tr');
    ohneFokusfalle(document.getElementById('epVerfModal'), () => msvConfirmDelete('die Verfügbarkeit von ' + msvEsc($tr.find('td').first().text().trim()))).then(res => {
      if (!res.isConfirmed) return;
      post('verfuegbarkeit_save.php', { action: 'delete', id: $tr.data('id') }, r => { verfGeaendert = true; $tr.remove(); verfSchutz.anzeigen(); msvToast(r.message, 'success'); });
    });
  });
  // Umfrage im Portal als Quelle (seit 1.68.56 hier statt im Dialog «Titel / Fusstext»): Auswahl sofort speichern
  $('#epVerfUmfrageWahl').on('change', function () {
    const id = parseInt(this.value || '0', 10), sel = this;
    post('plan_save.php', { action: 'update', umfrage_id: id }, () => {
      $('#epVerfUmfrage').prop('disabled', !(id > 0));
      msvToast(id > 0 ? 'Umfrage verknüpft – jetzt «Aus Umfrage übernehmen».' : 'Verknüpfung zur Umfrage entfernt.', 'success');
    }).fail(() => { sel.value = sel.dataset.vorher || '0'; });
  }).on('focus', function () { this.dataset.vorher = this.value; });
  $('#epVerfUmfrage').on('click', function () {
    const $b = $(this).prop('disabled', true);
    post('verfuegbarkeit_import.php', { action: 'umfrage' }, r => {
      let html = '<div class="alert alert-success py-2 px-3 small mb-1">' + msvEsc(r.message) + '</div>';
      (r.hinweise || []).forEach(h => html += '<div class="alert alert-warning py-1 px-2 small mb-1">' + msvEsc(h) + '</div>');
      $('#epVerfImportMeldungen').html(html); $('#epVerfImportPreview').empty(); $('#epVerfImportBox').removeClass('d-none'); $('#epVerfImportSave').addClass('d-none');
      $('#epVerfImportCancel').text('Neu laden').off('click.rl').on('click.rl', () => location.reload());
    }).always(() => $b.prop('disabled', false));
  });
  let verfVorschau = [];
  $('#epVerfExcelForm').on('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this); fd.append('action', 'excel_parse'); fd.append('plan_id', PLAN_ID); fd.append('csrf_token', CSRF);
    const $btn = $(this).find('button[type=submit]').prop('disabled', true);
    $.ajax({ url: basePath + 'verfuegbarkeit_import.php', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
      .done(r => {
        if (!r || !r.success) { msvToast((r && r.message) || 'Excel konnte nicht gelesen werden', 'error'); return; }
        verfVorschau = r.vorschau || [];
        let html = '<div class="alert alert-info py-2 px-3 small mb-1">' + msvEsc(r.message) + '</div>';
        (r.warnungen || []).forEach(w => html += '<div class="alert alert-warning py-1 px-2 small mb-1">' + msvEsc(w) + '</div>');
        $('#epVerfImportMeldungen').html(html);
        let t = '<table class="table table-sm table-hover mb-0"><thead><tr><th style="width:30px"><input type="checkbox" id="epVerfAlle" checked></th><th>Name (Excel)</th><th>Mitglied</th><th>Rollen</th><th>Schichten</th><th>Hinweis</th></tr></thead><tbody>';
        verfVorschau.forEach((z, i) => t += '<tr><td><input type="checkbox" class="ep-verf-cb" data-i="' + i + '" checked></td><td>' + msvEsc(z.name) + '</td><td>' + (z.mitglied_id ? '<span class="text-success">' + msvEsc(z.matched_name) + (z.match === 'fuzzy' ? ' <small class="text-warning">(unsicher)</small>' : '') + '</span>' : '<span class="text-muted">' + (z.verein === 'msv' ? 'kein Mitglied – Klartext' : 'Klartext') + '</span>') + '</td><td>' + msvEsc(z.rollen.join(', ')) + '</td><td>' + z.termin_ids.map(id => msvEsc(EP_TERMIN_LABEL[id] || id)).join('<br>') + '</td><td class="small text-warning">' + msvEsc(z.hinweis || '') + '</td></tr>');
        $('#epVerfImportPreview').html(t + '</tbody></table>');
        $('#epVerfImportBox').removeClass('d-none'); $('#epVerfImportSave').removeClass('d-none').data('verein', fd.get('verein'));
        $('#epVerfImportCancel').text('Abbrechen').off('click.rl').on('click.rl', () => $('#epVerfImportBox').addClass('d-none'));
      })
      .fail(xhr => msvToast(msvXhrMessage(xhr, 'Excel konnte nicht gelesen werden'), 'error'))
      .always(() => $btn.prop('disabled', false));
  });
  $(document).on('change', '#epVerfAlle', function () { $('.ep-verf-cb').prop('checked', this.checked); });
  $('#epVerfImportSave').on('click', function () {
    const zeilen = $('.ep-verf-cb:checked').map(function () { return verfVorschau[$(this).data('i')]; }).get();
    if (!zeilen.length) { msvToast('Nichts ausgewählt', 'info'); return; }
    post('verfuegbarkeit_import.php', { action: 'excel_save', verein: $(this).data('verein') || 'msv', zeilen: JSON.stringify(zeilen) }, r => { msvToast(r.message, 'success'); setTimeout(() => location.reload(), 700); });
  });

  // ---------- Rückmeldung einlesen ----------
  let rueckVorschlaege = [];
  $('#epRueckForm').on('submit', function (e) {
    e.preventDefault();
    const fd = new FormData(this); fd.append('plan_id', PLAN_ID); fd.append('csrf_token', CSRF);
    const $btn = $(this).find('button[type=submit]').prop('disabled', true);
    $('#epRueckMeldungen').empty(); $('#epRueckPreview').empty(); $('#epRueckUebernehmen').addClass('d-none');
    $.ajax({ url: basePath + 'rueckmeldung_parse.php', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
      .done(r => {
        if (!r || !r.success) { msvToast((r && r.message) || 'Datei konnte nicht gelesen werden', 'error'); return; }
        rueckVorschlaege = r.vorschlaege || [];
        (r.warnungen || []).forEach(w => $('#epRueckMeldungen').append('<div class="alert alert-warning py-1 px-2 mb-1 small"><i class="bi bi-exclamation-triangle me-1"></i>' + msvEsc(w) + '</div>'));
        if (!rueckVorschlaege.length) { $('#epRueckMeldungen').append('<div class="alert alert-info py-1 px-2 small mb-0">Keine Änderungen gegenüber dem Plan gefunden.</div>'); return; }
        let html = '<table class="table table-sm table-hover mb-0"><thead><tr><th style="width:30px"><input type="checkbox" id="epRueckAlle" checked></th><th>Termin</th><th>Funktion</th><th>Verein</th><th>bisher</th><th>neu</th><th>Hinweis</th></tr></thead><tbody>';
        rueckVorschlaege.forEach((v, i) => {
          html += '<tr><td><input type="checkbox" class="ep-rueck-cb" data-i="' + i + '" ' + (v.vorschlag ? 'checked' : '') + '></td><td>' + msvEsc(v.termin) + '</td><td>' + msvEsc(v.funktion) + '</td><td>' + msvEsc(v.verein_label) + '</td><td class="text-muted">' + msvEsc(v.alt || '–') + '</td><td class="fw-semibold">' + msvEsc(v.neu || '–') + '</td><td class="small text-muted">' + msvEsc(v.hinweis || '') + '</td></tr>';
        });
        $('#epRueckPreview').html(html + '</tbody></table>');
        $('#epRueckUebernehmen').removeClass('d-none');
      })
      .fail(xhr => msvToast(msvXhrMessage(xhr, 'Datei konnte nicht gelesen werden'), 'error'))
      .always(() => $btn.prop('disabled', false));
  });
  $(document).on('change', '#epRueckAlle', function () { $('.ep-rueck-cb').prop('checked', this.checked); });
  $('#epRueckUebernehmen').on('click', function () {
    const aenderungen = $('.ep-rueck-cb:checked').map(function () { const v = rueckVorschlaege[$(this).data('i')]; return { slot_id: v.slot_id, name_text: v.neu }; }).get();
    if (!aenderungen.length) { msvToast('Nichts ausgewählt', 'info'); return; }
    post('rueckmeldung_save.php', { aenderungen: JSON.stringify(aenderungen) }, r => { msvToast(r.message, 'success'); setTimeout(() => location.reload(), 700); });
  });
<?php endif; ?>
});
</script>

<?php include 'partials/direktdruck_scripts.inc.php'; ?>
<script>
// Direktdruck (QZ Tray): PDF der Einsatzliste über export_pdf.php (JSON mit «link» relativ zu inc/einsatzplanung/)
(function () {
  if (!window.MsvDruck) return;
  const basePath = (/\/inc(\/|$)/.test(location.pathname)) ? 'einsatzplanung/' : 'inc/einsatzplanung/';
  MsvDruck.resolve('einsatzplan', btn => ({
    url: basePath + 'export_pdf.php?plan_id=' + encodeURIComponent(btn.dataset.plan || 0),
    jobName: 'Einsatzplan ' + (btn.dataset.titel || ''),
    linkPrefix: basePath,
    orientation: btn.dataset.typ === 'schlossturm' ? 'portrait' : 'landscape'   // Schlossturm-Liste ist A4 hoch
  }));
})();
</script>

<?php include 'footer.inc.php'; ?>
