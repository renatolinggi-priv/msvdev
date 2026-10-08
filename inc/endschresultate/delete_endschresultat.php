<?php
// delete_endschresultat.php – Endschiessen-Resultate eines Mitglieds für ein Jahr löschen.
//
// POST mitgliedID, jahr, aktion:
//   pruefen  → {success, bezuege{Bereich: Anzahl}} – was gelöscht würde, für den Dialog
//   loeschen → nach Sicherung der Datenbank in einer Transaktion: Endstich, Schwini, Kunst, Glück, Zabig,
//              der Partnerinnen-Eintrag (samt Sie-und-Er-Schüssen 6–10 des Mitglieds) und das JM-Resultat
//              «Endstich» → {success, sicherung}
// Seit 08.10.2026 (Critique): Der Dialog nennt, was gelöscht wird, und vorher wird gesichert.
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
require_once __DIR__ . '/../loesch_sperre.inc.php';
require_once __DIR__ . '/../backup_dump.inc.php';

// CSRF-Schutz
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    msvLoeschAntwort(['success' => false, 'message' => 'Sitzung abgelaufen – bitte die Seite neu laden und nochmals versuchen.'], 403);
}

$mitgliedID = isset($_POST['mitgliedID']) ? intval($_POST['mitgliedID']) : 0;
$jahr = isset($_POST['jahr']) ? intval($_POST['jahr']) : (int)date('Y');
$aktion = (string)($_POST['aktion'] ?? 'loeschen');
if ($mitgliedID <= 0) {
    msvLoeschAntwort(['success' => false, 'message' => 'Ungültige Mitglied-ID'], 400);
}

$tabellen = ['endstich' => 'Endstich', 'schwini' => 'Schwini', 'kunst' => 'Kunst', 'glueck' => 'Glück', 'zabig' => 'Zabig'];

// JM-Definition «Endstich» des Jahres (das JM-Resultat geht mit)
$endstichId = null;
$st = $conn->prepare("SELECT ID FROM JMDefinition WHERE Bezeichnung = 'Endstich' AND year = ?");
$st->bind_param('i', $jahr);
$st->execute();
if ($row = $st->get_result()->fetch_assoc()) $endstichId = (int)$row['ID'];
$st->close();

if ($aktion === 'pruefen') {
    $bezuege = [];
    foreach ($tabellen as $t => $label) {
        $q = $conn->prepare("SELECT COUNT(*) FROM `$t` WHERE Jahr = ? AND MitgliedID = ?");
        $q->bind_param('ii', $jahr, $mitgliedID);
        $q->execute();
        if ((int)$q->get_result()->fetch_row()[0] > 0) $bezuege[$label] = 'Resultate';
        $q->close();
    }
    $q = $conn->prepare('SELECT PartnerName FROM endresultate_partner WHERE Jahr = ? AND MitgliedID = ? LIMIT 1');
    $q->bind_param('ii', $jahr, $mitgliedID);
    $q->execute();
    if ($p = $q->get_result()->fetch_assoc()) {
        $bezuege['Partnerin «' . trim((string)$p['PartnerName']) . '»'] = 'ganzer Eintrag samt Sie und Er';
    }
    $q->close();
    if ($endstichId) {
        $q = $conn->prepare('SELECT COUNT(*) FROM jmresultate WHERE jmdefinitionID = ? AND mitgliederID = ?');
        $q->bind_param('ii', $endstichId, $mitgliedID);
        $q->execute();
        if ((int)$q->get_result()->fetch_row()[0] > 0) $bezuege['Jahresmeisterschaft'] = 'Resultat Endstich';
        $q->close();
    }
    msvLoeschAntwort(['success' => true, 'bezuege' => (object)$bezuege]);
}

if ($aktion !== 'loeschen') msvLoeschAntwort(['success' => false, 'message' => 'Unbekannte Aktion'], 400);

$sicherung = msvBackupVorher('endschiessen-loeschen-' . $mitgliedID . '-' . $jahr);
if (!$sicherung['ok']) {
    error_log('[delete_endschresultat] Sicherung fehlgeschlagen: ' . $sicherung['meldung']);
    msvLoeschAntwort(['success' => false, 'message' => 'Die Sicherung vor dem Löschen hat nicht geklappt. Es wurde nichts gelöscht.'], 500);
}

$conn->begin_transaction();
try {
    $deletedTotal = 0;
    foreach (array_merge(array_keys($tabellen), ['endresultate_partner']) as $t) {
        $stmt = $conn->prepare("DELETE FROM `$t` WHERE Jahr = ? AND MitgliedID = ?");
        if (!$stmt) throw new Exception("Löschen vorbereiten ($t): " . $conn->error);
        $stmt->bind_param('ii', $jahr, $mitgliedID);
        if (!$stmt->execute()) throw new Exception("Löschen aus $t: " . $stmt->error);
        $deletedTotal += $stmt->affected_rows;
        $stmt->close();
    }
    if ($endstichId) {
        $stmt = $conn->prepare('DELETE FROM jmresultate WHERE jmdefinitionID = ? AND mitgliederID = ?');
        if (!$stmt) throw new Exception('Löschen vorbereiten (jmresultate): ' . $conn->error);
        $stmt->bind_param('ii', $endstichId, $mitgliedID);
        if (!$stmt->execute()) throw new Exception('Löschen aus jmresultate: ' . $stmt->error);
        $deletedTotal += $stmt->affected_rows;
        $stmt->close();
    }
    $conn->commit();
    msvLoeschAntwort(['success' => true, 'message' => 'Resultate gelöscht', 'deleted_records' => $deletedTotal,
                      'sicherung' => basename((string)$sicherung['datei'])]);
} catch (Exception $e) {
    $conn->rollback();
    msvLoeschAntwort(['success' => false, 'message' => msvFehler('Löschen hat nicht geklappt. Bitte die Liste neu laden und prüfen.', $e)], 500);
}
