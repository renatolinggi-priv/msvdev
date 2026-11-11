<?php
// import_csv.php – Mitglieder aus einer CSV-Datei, in zwei Schritten (seit 1.68.57).
//
// POST datei (Upload), aktion:
//   pruefen     → nichts wird geschrieben; Antwort: neu / geändert (Feld alt → neu) / unverändert / Fehler
//   uebernehmen → dieselbe Datei erneut; Datenbank wird vorher gesichert, dann alles in einer Transaktion
//
// Regeln: Die Datei wird hier gelesen (fgetcsv, Anführungszeichen korrekt), Trennzeichen ; , oder Tab,
// UTF-8 oder Windows-1252 (Excel «CSV»). Die Spalte ID (Lizenznummer) bestimmt, wer aktualisiert und wer
// neu angelegt wird. Geändert werden NUR Spalten, die in der Datei stehen; fehlende Spalten bleiben, wie
// sie sind. Geprüft wird mit denselben Regeln wie im Panel (mitglied.inc.php). Waffe per WaffenID oder
// Bezeichnung (Spalte «Waffe»). Früher: Zeilen per split(';') im Browser, fehlende Spalten wurden geleert
// bzw. auf inaktiv gesetzt, Anführungszeichen landeten in der Datenbank, keine Sicherung.
require_once '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/../backup_dump.inc.php';
require_once __DIR__ . '/mitglied.inc.php';

header('Content-Type: application/json; charset=utf-8');

function imp_ende(array $antwort, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($antwort, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') imp_ende(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
csrf_require(true);

$aktion = (string)($_POST['aktion'] ?? 'pruefen');
if (!in_array($aktion, ['pruefen', 'uebernehmen'], true)) imp_ende(['success' => false, 'message' => 'Unbekannte Aktion'], 400);

$datei = $_FILES['datei'] ?? null;
if (!$datei || ($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($datei['tmp_name'])) {
    imp_ende(['success' => false, 'message' => 'Die Datei ist nicht angekommen. Bitte nochmals wählen.'], 400);
}
if ($datei['size'] > 2 * 1024 * 1024) imp_ende(['success' => false, 'message' => 'Die Datei ist grösser als 2 MB. Ist es wirklich die Mitgliederliste?'], 413);

// ---------- Datei lesen ----------
$inhalt = (string)file_get_contents($datei['tmp_name']);
$inhalt = preg_replace('/^\xEF\xBB\xBF/', '', $inhalt);
$kodierung = 'UTF-8';
if (!mb_check_encoding($inhalt, 'UTF-8')) {
    $inhalt = mb_convert_encoding($inhalt, 'UTF-8', 'Windows-1252');
    $kodierung = 'Windows-1252';
}
$ersteZeile = strtok($inhalt, "\r\n") ?: '';
$trenner = ';';
$zaehlung = [';' => substr_count($ersteZeile, ';'), ',' => substr_count($ersteZeile, ','), "\t" => substr_count($ersteZeile, "\t")];
arsort($zaehlung);
if (reset($zaehlung) > 0) $trenner = (string)array_key_first($zaehlung);

$h = fopen('php://temp', 'r+');
fwrite($h, $inhalt);
rewind($h);
$kopf = fgetcsv($h, 0, $trenner, '"', '');
if (!$kopf || count(array_filter($kopf, fn($k) => trim((string)$k) !== '')) === 0) {
    imp_ende(['success' => false, 'message' => 'Die Datei ist leer oder hat keine Kopfzeile.'], 422);
}

// Spaltennamen → Feld (Gross/klein egal, gängige Varianten)
$namen = [
    'id' => 'id', 'lizenz' => 'id', 'lizenznr' => 'id', 'lizenznr.' => 'id', 'lizenznummer' => 'id',
    'anrede' => 'anrede', 'vorname' => 'vorname', 'name' => 'name', 'nachname' => 'name',
    'geburtsdatum' => 'geburtsdatum', 'geb.datum' => 'geburtsdatum', 'geburtstag' => 'geburtsdatum',
    'waffenid' => 'waffenid', 'waffe' => 'waffe', 'sportgerät' => 'waffe',
    'status' => 'status', 'aktiv' => 'status', 'mitglied' => 'status',
    'ehrenmitglied' => 'ehrenmitglied', 'mitgliedsart' => 'mitgliedsart', 'art' => 'mitgliedsart',
    'vereinsaufnahme' => 'vereinsaufnahme', 'eintritt' => 'vereinsaufnahme', 'eintrittsjahr' => 'vereinsaufnahme',
    'austritt' => 'austrittsjahr', 'austrittsjahr' => 'austrittsjahr', 'verstorben' => 'verstorben',
    'strasse' => 'strasse', 'straße' => 'strasse', 'adresse' => 'strasse', 'plz' => 'plz', 'ort' => 'ort',
    'email' => 'email', 'e-mail' => 'email', 'mail' => 'email', 'telefon' => 'telefon', 'tel' => 'telefon',
    'mobile' => 'mobile', 'mobil' => 'mobile', 'natel' => 'mobile', 'handy' => 'mobile',
    'kommunikation' => 'kommunikation', 'notizen' => 'notizen', 'bemerkungen' => 'notizen',
];
$spalten = [];      // Index → Feld
$ignoriert = [];
foreach ($kopf as $i => $k) {
    $schluessel = mb_strtolower(trim((string)$k));
    if ($schluessel === '') continue;
    if (isset($namen[$schluessel]) && !in_array($namen[$schluessel], $spalten, true)) $spalten[$i] = $namen[$schluessel];
    else $ignoriert[] = trim((string)$k);
}
if (!in_array('id', $spalten, true)) {
    imp_ende(['success' => false, 'message' => 'Die Spalte «ID» (Lizenznummer) fehlt. Am einfachsten die Vorlage «Mitglieder (CSV)» herunterladen und darin ändern.'], 422);
}
$mitMitgliedschaft = mvHatMitgliedschaft($conn);
if (!$mitMitgliedschaft) {
    $i = array_search('austrittsjahr', $spalten, true);
    if ($i !== false) { unset($spalten[$i]); $ignoriert[] = 'Austritt (erst nach der Datenbank-Aktualisierung)'; }
}

// ---------- Bestand laden ----------
$waffen = mvWaffen($conn);
$waffeNachName = [];
foreach ($waffen as $wid => $bez) $waffeNachName[mb_strtolower($bez)] = $wid;

$bestand = [];
$r = $conn->query('SELECT * FROM mitglieder');
while ($r && ($m = $r->fetch_assoc())) {
    $bestand[(int)$m['ID']] = [
        'id' => (int)$m['ID'], 'anrede' => $m['Anrede'], 'vorname' => $m['Vorname'], 'name' => $m['Name'],
        'geburtsdatum' => $m['Geburtsdatum'], 'waffenid' => (int)$m['WaffenID'], 'status' => (int)$m['Status'],
        'ehrenmitglied' => (int)$m['Ehrenmitglied'], 'verstorben' => (int)$m['Verstorben'],
        'ist_jsk_leiter' => (int)($m['ist_jsk_leiter'] ?? 0),
        'mitgliedsart' => $mitMitgliedschaft ? $m['Mitgliedsart'] : ((int)$m['Ehrenmitglied'] ? 'Ehren' : 'Aktiv'),
        'vereinsaufnahme' => $m['Vereinsaufnahme'], 'austrittsjahr' => $mitMitgliedschaft ? $m['Austrittsjahr'] : null,
        'strasse' => $m['Strasse'], 'plz' => $m['PLZ'], 'ort' => $m['Ort'], 'email' => $m['Email'],
        'telefon' => $m['Telefon'], 'mobile' => $m['Mobile'], 'kommunikation' => $m['Kommunikation'], 'notizen' => $m['Notizen'],
    ];
}

// Anzeige eines Werts in der Vorschau
function imp_zeigen(string $feld, $v, array $waffen): string
{
    if ($v === null || $v === '') return '–';
    if ($feld === 'waffenid') return $waffen[(int)$v] ?? ('Nr. ' . $v);
    if (in_array($feld, ['status', 'verstorben', 'ist_jsk_leiter', 'ehrenmitglied'], true)) return (int)$v ? 'ja' : 'nein';
    if ($feld === 'geburtsdatum' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$v, $t)) return "$t[3].$t[2].$t[1]";
    $s = (string)$v;
    return mb_strlen($s) > 60 ? mb_substr($s, 0, 57) . '…' : $s;
}

// ---------- Zeilen prüfen ----------
$aenderungen = [];   // ['id','name','art' => neu|geaendert,'felder' => [[feld, alt, neu]], 'werte' => [...]]
$fehler = [];
$unveraendert = 0;
$gesehen = [];
$zeile = 1;
$vergleich = ['anrede', 'vorname', 'name', 'geburtsdatum', 'waffenid', 'status', 'mitgliedsart', 'ehrenmitglied',
    'vereinsaufnahme', 'austrittsjahr', 'verstorben', 'strasse', 'plz', 'ort', 'email', 'telefon', 'mobile',
    'kommunikation', 'notizen'];
if (!$mitMitgliedschaft) $vergleich = array_values(array_diff($vergleich, ['mitgliedsart', 'austrittsjahr']));

while (($werte = fgetcsv($h, 0, $trenner, '"', '')) !== false) {
    $zeile++;
    if ($werte === [null] || count(array_filter($werte, fn($w) => trim((string)$w) !== '')) === 0) continue;

    $z = [];
    foreach ($spalten as $i => $feld) $z[$feld] = trim((string)($werte[$i] ?? ''));
    $idRoh = $z['id'] ?? '';
    $bez = trim(($z['vorname'] ?? '') . ' ' . ($z['name'] ?? ''));
    $wer = 'Zeile ' . $zeile . ($bez !== '' ? ' (' . $bez . ')' : '');

    if (!ctype_digit($idRoh) || (int)$idRoh < 1) { $fehler[] = $wer . ': keine gültige Lizenznummer in der Spalte ID.'; continue; }
    $id = (int)$idRoh;
    if (isset($gesehen[$id])) { $fehler[] = $wer . ': die Lizenznummer ' . $id . ' steht schon in Zeile ' . $gesehen[$id] . '.'; continue; }
    $gesehen[$id] = $zeile;

    $alt = $bestand[$id] ?? null;
    $neu = $alt === null;
    $m = $alt ?? ['id' => $id, 'status' => 1, 'verstorben' => 0, 'ehrenmitglied' => 0, 'ist_jsk_leiter' => 0, 'mitgliedsart' => 'Aktiv'];
    $zeilenFehler = [];

    foreach ($z as $feld => $wert) {
        switch ($feld) {
            case 'id': case 'waffe': break;
            case 'status': case 'verstorben': case 'ehrenmitglied':
                $jn = mvJaNein($wert);
                if ($jn === null) $zeilenFehler[] = MV_FELDNAMEN[$feld] . ' «' . $wert . '» ist weder ja noch nein';
                else $m[$feld] = $jn;
                break;
            case 'waffenid':
                $m['waffenid'] = ctype_digit($wert) ? (int)$wert : 0;
                break;
            default:
                $m[$feld] = $wert;
        }
    }
    if (isset($z['waffe']) && !isset($z['waffenid'])) {
        $wid = $waffeNachName[mb_strtolower($z['waffe'])] ?? null;
        if ($wid === null && $z['waffe'] !== '') $zeilenFehler[] = 'Waffe «' . $z['waffe'] . '» gibt es nicht (' . implode(', ', $waffen) . ')';
        elseif ($wid !== null) $m['waffenid'] = $wid;
    }
    // Ehrenmitglied ohne Spalte Mitgliedsart: Ehren setzen bzw. wieder zurück auf Aktiv
    if (isset($z['ehrenmitglied']) && !isset($z['mitgliedsart']) && isset($m['ehrenmitglied'])) {
        if ((int)$m['ehrenmitglied'] === 1) $m['mitgliedsart'] = 'Ehren';
        elseif (($m['mitgliedsart'] ?? 'Aktiv') === 'Ehren') $m['mitgliedsart'] = 'Aktiv';
    }
    if (!isset($z['mitgliedsart'])) $m['ehrenmitglied'] = ($m['mitgliedsart'] ?? 'Aktiv') === 'Ehren' ? 1 : 0;
    // Ausgetreten ohne Jahr in der Datei: Jahr beibehalten
    if (!isset($z['austrittsjahr']) && $alt !== null) $m['austrittsjahr'] = $alt['austrittsjahr'];

    [$pf, $sauber] = mvPruefen($m, $waffen, $mitMitgliedschaft);
    foreach ($pf as $feld => $text) $zeilenFehler[] = (MV_FELDNAMEN[$feld] ?? $feld) . ': ' . rtrim($text, '.');
    if ($zeilenFehler) { $fehler[] = $wer . ': ' . implode('; ', $zeilenFehler) . '.'; continue; }

    // Unterschiede zum Bestand
    $diff = [];
    foreach ($vergleich as $feld) {
        $a = $alt === null ? null : $alt[$feld];
        $n = $sauber[$feld] ?? null;
        if ($alt !== null && mvText($a) === mvText($n)) continue;
        if ($alt === null && mvText($n) === '') continue;
        $diff[] = [MV_FELDNAMEN[$feld] ?? $feld, $alt === null ? '' : imp_zeigen($feld, $a, $waffen), imp_zeigen($feld, $n, $waffen)];
    }
    if ($alt !== null && !$diff) { $unveraendert++; continue; }

    // Geschrieben werden nur geänderte Felder (bzw. alle bei neuen Mitgliedern)
    $schreiben = $neu ? $sauber : array_intersect_key($sauber, array_flip(array_filter($vergleich,
        fn($f) => mvText($alt[$f]) !== mvText($sauber[$f] ?? null))));
    $aenderungen[] = [
        'id' => $id, 'name' => trim($sauber['vorname'] . ' ' . $sauber['name']),
        'art' => $neu ? 'neu' : 'geaendert', 'felder' => $neu ? [] : $diff, 'werte' => $schreiben,
    ];
}
fclose($h);

$anzNeu = count(array_filter($aenderungen, fn($a) => $a['art'] === 'neu'));
$anzGeaendert = count($aenderungen) - $anzNeu;
$zusammenfassung = [
    'neu' => $anzNeu, 'geaendert' => $anzGeaendert, 'unveraendert' => $unveraendert, 'fehler' => $fehler,
    'spalten' => array_values(array_map(fn($i) => trim((string)$kopf[$i]), array_keys($spalten))),
    'ignoriert' => $ignoriert, 'kodierung' => $kodierung, 'trenner' => $trenner === "\t" ? 'Tab' : $trenner,
];

if ($aktion === 'pruefen') {
    imp_ende(array_merge(['success' => true], $zusammenfassung, [
        'aenderungen' => array_map(fn($a) => array_diff_key($a, ['werte' => 1]), array_slice($aenderungen, 0, 200)),
    ]));
}

// ---------- Übernehmen ----------
if (!$aenderungen) imp_ende(array_merge(['success' => true, 'message' => 'Nichts zu übernehmen: Die Datei enthält keine Änderungen.'], $zusammenfassung));

$sicherung = msvBackupVorher('mitglieder-import');
if (!$sicherung['ok']) {
    error_log('[import_csv] Sicherung fehlgeschlagen: ' . $sicherung['meldung']);
    imp_ende(['success' => false, 'message' => 'Die Sicherung vor dem Import hat nicht geklappt. Es wurde nichts geändert. Bitte später nochmals versuchen.'], 500);
}

try {
    $conn->begin_transaction();
    foreach ($aenderungen as $a) {
        mvSchreiben($conn, $a['id'], $a['werte'], $a['art'] === 'neu');
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    imp_ende(['success' => false, 'message' => msvFehler('Der Import ist fehlgeschlagen; es wurde nichts geändert. Die Sicherung von vorher liegt vor.', $e)], 500);
}
$conn->close();

imp_ende(array_merge(['success' => true,
    'message' => $anzNeu . ' neu, ' . $anzGeaendert . ' geändert. Die Datenbank wurde vorher gesichert.',
    'sicherung' => basename((string)$sicherung['datei'])], $zusammenfassung));
