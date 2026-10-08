<?php
// mitgliederverwaltung/delete_mitglied.php – Mitglied löschen nur ohne Geschichte (Entscheid 08.10.2026)
//
// POST id, aktion:
//   pruefen  → {success, name, aktiv, bezuege{Bezeichnung: Anzahl}, gesperrt}
//   loeschen → nur ohne Bezüge, nach Sicherung der Datenbank → {success, sicherung}
//   inaktiv  → Status = 0 (fällt aus den aktuellen Listen, Resultate bleiben) → {success}
// Früher: DELETE ohne Rückfrage nach Bezügen und ohne Sicherung; Resultate blieben verwaist und das
// Mitglied verschwand aus allen früheren Ranglisten.
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once '../config.php';
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/../loesch_sperre.inc.php';
require_once __DIR__ . '/../backup_dump.inc.php';
csrf_require();

$id = (int)($_POST['id'] ?? 0);
$aktion = (string)($_POST['aktion'] ?? 'pruefen');
if ($id < 1) msvLoeschAntwort(['success' => false, 'message' => 'Ungültige ID'], 400);

$st = $conn->prepare('SELECT Vorname, Name, Status FROM mitglieder WHERE ID = ?');
$st->bind_param('i', $id);
$st->execute();
$m = $st->get_result()->fetch_assoc();
$st->close();
if (!$m) msvLoeschAntwort(['success' => false, 'message' => 'Mitglied nicht gefunden'], 404);
$name = trim($m['Vorname'] . ' ' . $m['Name']);

if ($aktion === 'inaktiv') {
    $u = $conn->prepare('UPDATE mitglieder SET Status = 0 WHERE ID = ?');
    $u->bind_param('i', $id);
    $ok = $u->execute();
    $u->close();
    msvLoeschAntwort($ok ? ['success' => true, 'message' => "$name ist jetzt inaktiv"]
                         : ['success' => false, 'message' => 'Konnte nicht auf inaktiv gesetzt werden'], $ok ? 200 : 500);
}

// Alles, was auf das Mitglied zeigt; das Änderungsprotokoll zählt nicht (sonst wäre jeder Fehleintrag gesperrt)
$bezuege = msvBezuege($conn, ['mitglied_id', 'mitgliedid', 'mitgliederid', 'mitglieder_id'], $id,
    ['mitglieder', 'mitglieder_aenderungen'],
    [
        'endstich' => 'Endschiessen-Einträge', 'schwini' => 'Endschiessen-Einträge', 'kunst' => 'Endschiessen-Einträge',
        'glueck' => 'Endschiessen-Einträge', 'zabig' => 'Endschiessen-Einträge', 'endresultate_partner' => 'Endschiessen-Einträge',
        'endstich*' => 'Endschiessen-Einträge', 'endsch*' => 'Endschiessen-Einträge',
        'heimresultate' => 'Heimmeisterschaft', 'kantiresultate' => 'Kantonalstich',
        'jmresultate*' => 'JM-Resultate', 'jmdefinition_gruppen' => 'JM-Gruppen',
        'einzelrangierungen' => 'Einträge in Einzelranglisten', 'sektionsrangierungen' => 'Einträge in Sektionsranglisten',
        'einsatz*' => 'Einsätze', 'users' => 'Benutzerkonto', 'mitglieder_fragebogen*' => 'Fragebogen',
        'umfragen*' => 'Umfrage-Antworten', 'munitionskauf*' => 'Munitionskäufe', 'jsk*' => 'Jungschützen-Betreuung',
        'cup*' => 'Vereinscup',
    ]);

if ($aktion === 'pruefen') {
    msvLoeschAntwort(['success' => true, 'name' => $name, 'aktiv' => (int)$m['Status'] === 1,
                      'bezuege' => (object)$bezuege, 'gesperrt' => (bool)$bezuege]);
}

if ($aktion !== 'loeschen') msvLoeschAntwort(['success' => false, 'message' => 'Unbekannte Aktion'], 400);

if ($bezuege) {
    msvLoeschAntwort(['success' => false, 'gesperrt' => true, 'bezuege' => (object)$bezuege,
                      'message' => "$name hat Daten im Verein und wird darum nicht gelöscht. Auf inaktiv setzen statt löschen."], 409);
}

$sicherung = msvBackupVorher('mitglied-loeschen-' . $id);
if (!$sicherung['ok']) {
    error_log('[delete_mitglied] Sicherung fehlgeschlagen: ' . $sicherung['meldung']);
    msvLoeschAntwort(['success' => false, 'message' => 'Die Sicherung vor dem Löschen ist fehlgeschlagen (' . $sicherung['meldung'] . '). Es wurde nichts gelöscht.'], 500);
}

$d = $conn->prepare('DELETE FROM mitglieder WHERE ID = ?');
$d->bind_param('i', $id);
if (!$d->execute()) {
    error_log('[delete_mitglied] ' . $d->error);
    msvLoeschAntwort(['success' => false, 'message' => 'Löschen fehlgeschlagen. Die Sicherung ' . basename((string)$sicherung['datei']) . ' liegt vor.'], 500);
}
$d->close();
msvLoeschAntwort(['success' => true, 'message' => "$name gelöscht", 'sicherung' => basename((string)$sicherung['datei'])]);
