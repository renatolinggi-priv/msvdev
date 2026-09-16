<?php
/**
 * cron/einsatzplanung_bootstrap.php – Einsatzplan aus einem Dokument per Kommandozeile anlegen
 * (einmaliges Bootstrapping, z.B. das laufende Jahr als Kopiervorlage). Nur CLI, per SSH:
 *
 *   php cron/einsatzplanung_bootstrap.php --dokument=22            # vorstand_dokumente.id (typ einsatzplan)
 *   php cron/einsatzplanung_bootstrap.php --datei=/pfad/Obli.docx --jahr=2026 [--typ=obligatorisch] [--titel="Obligatorisch 2026"]
 *   Optionen: --dry (nur Dokument lesen und Struktur zeigen), --force (auch wenn für Jahr/Typ schon ein Plan existiert)
 *
 * Logik: inc/einsatzplanung/plan_import.inc.php (ep_plan_aus_dokument) – identisch zum Web-Endpunkt.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Nur per Kommandozeile.'); }

require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../inc/einsatzplanung/plan_import.inc.php';

$opt = getopt('', ['dokument:', 'datei:', 'jahr:', 'typ:', 'titel:', 'dry', 'force']);
$db  = getDB();

$pfad = ''; $dokId = null; $titel = (string)($opt['titel'] ?? ''); $jahr = (int)($opt['jahr'] ?? 0); $typ = (string)($opt['typ'] ?? '');
if (!empty($opt['dokument'])) {
    $st = $db->prepare("SELECT * FROM vorstand_dokumente WHERE id = ? AND typ = 'einsatzplan'");
    $st->execute([(int)$opt['dokument']]);
    $dok = $st->fetch();
    if (!$dok) { fwrite(STDERR, "Dokument nicht gefunden\n"); exit(1); }
    $dokId = (int)$dok['id'];
    $pfad  = $dok['dateipfad'];
    if (!is_file($pfad)) $pfad = __DIR__ . '/../portal/uploads/dokumente/einsatzplan/' . basename($pfad);
    if ($titel === '') $titel = (string)$dok['titel'];
    if ($jahr === 0) $jahr = (int)($dok['jahr'] ?? 0);
    echo "Dokument #{$dokId}: {$dok['titel']} ({$dok['dateiname']})\n";
} elseif (!empty($opt['datei'])) {
    $pfad = (string)$opt['datei'];
} else {
    fwrite(STDERR, "Bitte --dokument=<id> oder --datei=<pfad> angeben.\n"); exit(1);
}
if (!is_file($pfad)) { fwrite(STDERR, "Datei nicht gefunden: $pfad\n"); exit(1); }

// Struktur-Vorschau (--dry): nur lesen
if (isset($opt['dry'])) {
    $ext = strtolower(pathinfo($pfad, PATHINFO_EXTENSION));
    if ($ext === 'docx') { require_once __DIR__ . '/../inc/einsatzplan_parser/docx_parser.php'; $res = parseEinsatzplanDocx($pfad, true); }
    else { require_once __DIR__ . '/../inc/einsatzplan_parser/xlsx_parser.php'; $res = parseEinsatzplanXlsx($pfad, true); }
    echo $res['message'] . (isset($res['format']) ? ' (Format ' . $res['format'] . ')' : '') . "\n";
    foreach ($res['data'] ?? [] as $z) {
        printf("  %s %-14s %-40s %-10s %-28s %s\n", $z['event_datum'], $z['event_zeit'] ?? '', mb_substr($z['funktion'], 0, 40), $z['verein'] ?? 'msv', $z['mitglied_name'], $z['info'] ?? '');
    }
    exit(0);
}

// Doppelten Import verhindern
$typErk = $typ;
if ($typErk === '') {
    $ext = strtolower(pathinfo($pfad, PATHINFO_EXTENSION));
    $n = mb_strtolower(basename($pfad) . ' ' . $titel);
    $typErk = str_contains($n, 'schlossturm') ? 'schlossturm' : (str_contains($n, 'chilbi') || ($ext === 'xlsx' && !str_contains($n, 'schloss')) ? 'chilbi' : (str_contains($n, 'feld') ? 'feldschiessen' : (str_contains($n, 'obli') ? 'obligatorisch' : '')));
}
if ($jahr > 0 && $typErk !== '' && !isset($opt['force'])) {
    $st = $db->prepare("SELECT id, titel, status FROM einsatz_plaene WHERE jahr = ? AND typ = ?");
    $st->execute([$jahr, $typErk]);
    if ($vorhanden = $st->fetchAll()) {
        foreach ($vorhanden as $v) echo "Bereits vorhanden: Plan #{$v['id']} «{$v['titel']}» ({$v['status']})\n";
        echo "Abbruch – mit --force trotzdem anlegen.\n";
        exit(2);
    }
}

try {
    $r = ep_plan_aus_dokument($db, $pfad, 0, ['dokument_id' => $dokId, 'titel' => $titel, 'typ' => $typ, 'jahr' => $jahr]);
} catch (Throwable $e) {
    fwrite(STDERR, "FEHLER: " . $e->getMessage() . "\n"); exit(1);
}
$s = $r['statistik'];
echo "Plan #{$r['plan_id']} «{$r['titel']}» ({$r['typ']}, {$r['layout']}) angelegt: {$s['termine']} Termine, {$s['funktionen']} Funktionen, {$s['slots']} Positionen, {$s['verknuepft']} bestehende Einsätze verknüpft\n";
if ($s['ohne_match']) echo "Ohne Mitglied (Klartext übernommen): " . implode(', ', $s['ohne_match']) . "\n";
exit(0);
