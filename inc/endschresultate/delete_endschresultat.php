<?php
// delete_endschresultat.php – Endschiessen-Resultate eines Teilnehmers für ein Jahr löschen.
//
// POST teilnehmer (m<ID> = Mitglied, g<ID> = Gast/Jungschütze; seit Migration 094) oder mitgliedID (alt), jahr, aktion:
//   pruefen  → {success, bezuege{Bereich: Anzahl}} – was gelöscht würde, für den Dialog
//   loeschen → nach Sicherung der Datenbank in einer Transaktion: Endstich, Schwini, Kunst, Glück, Zabig,
//              die eigenen Sie-und-Er-Schüsse (die Partnerin behält ihre, die Verknüpfung wird gelöst) und bei
//              Mitgliedern das JM-Resultat «Endstich» → {success, sicherung}
// Seit 08.10.2026 (Critique): Der Dialog nennt, was gelöscht wird, und vorher wird gesichert.
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
require_once __DIR__ . '/../loesch_sperre.inc.php';
require_once __DIR__ . '/../backup_dump.inc.php';
require_once __DIR__ . '/teilnehmer.inc.php';

// CSRF-Schutz
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    msvLoeschAntwort(['success' => false, 'message' => 'Sitzung abgelaufen – bitte die Seite neu laden und nochmals versuchen.'], 403);
}

$t = endschTeilnehmer($_POST['teilnehmer'] ?? (isset($_POST['mitgliedID']) ? 'm' . (int)$_POST['mitgliedID'] : ''));
$jahr = isset($_POST['jahr']) ? intval($_POST['jahr']) : (int)date('Y');
$aktion = (string)($_POST['aktion'] ?? 'loeschen');
if (!$t) {
    msvLoeschAntwort(['success' => false, 'message' => 'Kein Teilnehmer gewählt. Bitte die Liste neu laden.'], 400);
}
$spalte = $t['spalte'];   // MitgliedID | GastID – fester Wert aus endschTeilnehmer()
$personID = $t['id'];
// Das JM-Resultat gibt es nur bei Mitgliedern
$mitgliedID = $spalte === 'MitgliedID' ? $personID : 0;

$tabellen = ['endstich' => 'Endstich', 'schwini' => 'Schwini', 'kunst' => 'Kunst', 'glueck' => 'Glück', 'zabig' => 'Zabig', 'endstich_sieunder' => 'Sie und Er'];

// JM-Definition «Endstich» des Jahres (das JM-Resultat geht mit)
$endstichId = null;
$st = $conn->prepare("SELECT ID FROM JMDefinition WHERE Bezeichnung = 'Endstich' AND year = ?");
$st->bind_param('i', $jahr);
$st->execute();
if (($row = $st->get_result()->fetch_assoc()) && $mitgliedID) $endstichId = (int)$row['ID'];
$st->close();

if ($aktion === 'pruefen') {
    $bezuege = [];
    foreach ($tabellen as $tab => $label) {
        $q = $conn->prepare("SELECT COUNT(*) FROM `$tab` WHERE Jahr = ? AND $spalte = ?");
        $q->bind_param('ii', $jahr, $personID);
        $q->execute();
        if ((int)$q->get_result()->fetch_row()[0] > 0) $bezuege[$label] = $tab === 'endstich_sieunder' ? 'eigene Schüsse, die Verknüpfung wird gelöst' : 'Resultate';
        $q->close();
    }
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

$sicherung = msvBackupVorher('endschiessen-loeschen-' . $t['schluessel'] . '-' . $jahr);
if (!$sicherung['ok']) {
    error_log('[delete_endschresultat] Sicherung fehlgeschlagen: ' . $sicherung['meldung']);
    msvLoeschAntwort(['success' => false, 'message' => 'Die Sicherung vor dem Löschen hat nicht geklappt. Es wurde nichts gelöscht.'], 500);
}

$conn->begin_transaction();
try {
    $deletedTotal = 0;
    // Markierung «Partner-Rangliste» (nur Mitglieder, Migration 096)
    if ($spalte === 'MitgliedID') {
        $stmt = $conn->prepare('DELETE FROM endstich_partnerrang WHERE Jahr = ? AND MitgliedID = ?');
        $stmt->bind_param('ii', $jahr, $personID);
        $stmt->execute();
        $stmt->close();
    }
    // Wer mit dieser Person ein Sie-und-Er-Paar bildet, behält seine Schüsse, nur der Verweis fällt weg
    $verweis = $spalte === 'GastID' ? 'PartnerGastID' : 'PartnerMitgliedID';
    $stmt = $conn->prepare("UPDATE endstich_sieunder SET PartnerMitgliedID = NULL, PartnerGastID = NULL WHERE Jahr = ? AND $verweis = ?");
    $stmt->bind_param('ii', $jahr, $personID);
    $stmt->execute();
    $stmt->close();
    foreach (array_keys($tabellen) as $tab) {
        $stmt = $conn->prepare("DELETE FROM `$tab` WHERE Jahr = ? AND $spalte = ?");
        if (!$stmt) throw new Exception("Löschen vorbereiten ($tab): " . $conn->error);
        $stmt->bind_param('ii', $jahr, $personID);
        if (!$stmt->execute()) throw new Exception("Löschen aus $tab: " . $stmt->error);
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
