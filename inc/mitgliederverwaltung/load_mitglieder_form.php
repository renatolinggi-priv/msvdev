<?php
// load_mitglieder_form.php – Zeilen der Mitgliederliste (Read-only-Tabelle + Slide-Panel).
// Alle Werte stehen als data-* an der Zeile; die Spalte «Mitgliedschaft» füllt die Seite per JS
// (mvZelleMitgliedschaft), damit Anzeige und Live-Änderung aus derselben Funktion kommen.
// LEFT JOIN: Ein Mitglied mit unbekannter Waffe bleibt sichtbar (vorher fiel es still aus der Liste).
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('html');
require_once __DIR__ . '/../partials/empty_state.inc.php';
require_once __DIR__ . '/mitglied.inc.php';

$mitMitgliedschaft = mvHatMitgliedschaft($conn);
$result = $conn->query("SELECT m.*, w.Bezeichnung AS waffe
        FROM mitglieder m
        LEFT JOIN Waffen w ON m.WaffenID = w.ID
        ORDER BY m.Name ASC, m.Vorname ASC");

if (!$result) {
    msvFehler('Mitglieder laden', $conn->error);
    http_response_code(500);
    echo 'Die Mitglieder konnten nicht geladen werden.';
    exit;
}

$esc = static fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');

if ($result->num_rows === 0) {
    echo msv_empty_row(9, 'Noch keine Mitglieder erfasst');
}

while ($row = $result->fetch_assoc()) {
    $id = (int)$row['ID'];
    $ehre = (int)$row['Ehrenmitglied'];
    $art = $mitMitgliedschaft ? (string)($row['Mitgliedsart'] ?? 'Aktiv') : ($ehre ? 'Ehren' : 'Aktiv');

    $geb = '';
    if (!empty($row['Geburtsdatum']) && $row['Geburtsdatum'] !== '0000-00-00') {
        $ts = strtotime($row['Geburtsdatum']);
        if ($ts) $geb = date('d.m.Y', $ts);
    }
    $waffe = $row['waffe'] === null
        ? '<span class="mv-fehlt">Waffe fehlt</span>'
        : $esc($row['waffe']);

    $data = [
        'id' => $id, 'anrede' => $row['Anrede'], 'name' => $row['Name'], 'vorname' => $row['Vorname'],
        'geburtsdatum' => $row['Geburtsdatum'], 'waffenid' => (int)$row['WaffenID'], 'waffe' => $row['waffe'] ?? '',
        'strasse' => $row['Strasse'], 'plz' => $row['PLZ'], 'ort' => $row['Ort'], 'email' => $row['Email'],
        'telefon' => $row['Telefon'], 'mobile' => $row['Mobile'], 'notizen' => $row['Notizen'],
        'status' => (int)$row['Status'], 'ehrenmitglied' => $ehre, 'verstorben' => (int)$row['Verstorben'],
        'mitgliedsart' => $art, 'vereinsaufnahme' => $row['Vereinsaufnahme'],
        'austrittsjahr' => $mitMitgliedschaft ? ($row['Austrittsjahr'] ?? '') : '',
        'kommunikation' => $row['Kommunikation'], 'ist_jsk_leiter' => (int)($row['ist_jsk_leiter'] ?? 0),
    ];
    $attrs = '';
    foreach ($data as $k => $v) {
        $attrs .= ' data-' . $k . '="' . $esc($v) . '"';
    }

    echo '<tr class="hybrid-row" id="row' . $id . '"' . $attrs . '>'
        . '<td class="h-nr">' . $id . '</td>'
        . '<td class="h-name">' . $esc($row['Name']) . '</td>'
        . '<td class="h-vorname">' . $esc($row['Vorname']) . '</td>'
        . '<td class="h-date">' . $geb . '</td>'
        . '<td class="h-sub h-waffe">' . $waffe . '</td>'
        . '<td class="h-sub h-ort">' . $esc($row['Ort']) . '</td>'
        . '<td class="h-email">' . $esc($row['Email']) . '</td>'
        . '<td class="h-sub h-mobile">' . $esc($row['Mobile']) . '</td>'
        . '<td class="h-mitgliedschaft"></td>'
        . '</tr>';
}

$conn->close();
