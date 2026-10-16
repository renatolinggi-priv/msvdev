<?php
// delete_pair.php – eine gespeicherte Cup-Paarung löschen, samt dem, was davon abhängt.
//
// POST pair_id, aktion:
//   pruefen  → {success, paarung, folgen[]} – was mitgelöscht würde, für den Dialog
//   loeschen → nach Sicherung der Datenbank in einer Transaktion → {success, sicherung}
//
// Mitgelöscht wird im Jahr der Paarung (bis 1.68.30 galt das Kalenderjahr: wer im Archiv löschte,
// traf Runde 2 und Finale des laufenden Jahres):
//   Runde 1 → die Paarungen in Runde 2, in denen jemand aus dieser Paarung steht, und deren Finalresultate
//   Runde 2 → die Finalresultate der Teilnehmer dieser Paarung
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
require_once __DIR__ . '/../loesch_sperre.inc.php';
require_once __DIR__ . '/../backup_dump.inc.php';

// CSRF-Schutz
require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    msvLoeschAntwort(['success' => false, 'message' => 'Nur per POST.'], 405);
}

$pairId = (int)($_POST['pair_id'] ?? 0);
$aktion = (string)($_POST['aktion'] ?? 'loeschen');
if ($pairId <= 0) {
    msvLoeschAntwort(['success' => false, 'message' => 'Keine Paarung gewählt. Bitte die Seite neu laden.'], 400);
}

/** Name «Name Vorname» eines Mitglieds (für den Dialog). */
function cupMitgliedName(mysqli $conn, int $id): string
{
    static $cache = [];
    if (!isset($cache[$id])) {
        $st = $conn->prepare('SELECT CONCAT(Name, " ", Vorname) FROM mitglieder WHERE ID = ?');
        $st->bind_param('i', $id);
        $st->execute();
        $cache[$id] = (string)($st->get_result()->fetch_row()[0] ?? ('#' . $id));
        $st->close();
    }
    return $cache[$id];
}

// Die Paarung selbst: Jahr, Runde, Teilnehmer
$st = $conn->prepare('SELECT ID, Year, Round, Participant1, Participant2, Participant3 FROM cupPairs WHERE ID = ?');
$st->bind_param('i', $pairId);
$st->execute();
$pair = $st->get_result()->fetch_assoc();
$st->close();
if (!$pair) {
    msvLoeschAntwort(['success' => false, 'message' => 'Diese Paarung gibt es nicht mehr. Bitte die Seite neu laden.'], 404);
}
$jahr = (int)$pair['Year'];
$runde = (int)$pair['Round'];
$teilnehmer = array_values(array_filter([(int)$pair['Participant1'], (int)$pair['Participant2'], (int)$pair['Participant3']]));
$platzhalter = implode(',', array_fill(0, count($teilnehmer), '?'));

// Runde 2: Paarungen mit jemandem aus dieser Runde-1-Paarung
$r2 = [];
if ($runde === 1) {
    $sql = "SELECT ID, Participant1, Participant2, Participant3 FROM cupPairs
            WHERE Year = ? AND Round = 2
              AND (Participant1 IN ($platzhalter) OR Participant2 IN ($platzhalter) OR Participant3 IN ($platzhalter))";
    $st = $conn->prepare($sql);
    $werte = array_merge([$jahr], $teilnehmer, $teilnehmer, $teilnehmer);
    $st->bind_param(str_repeat('i', count($werte)), ...$werte);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) $r2[] = $row;
    $st->close();
}

// Finalresultate: Teilnehmer dieser Paarung, bei Runde 1 zusätzlich alle aus den betroffenen Runde-2-Paarungen
$finalIds = $teilnehmer;
foreach ($r2 as $row) {
    foreach (['Participant1', 'Participant2', 'Participant3'] as $k) {
        if ((int)$row[$k] > 0) $finalIds[] = (int)$row[$k];
    }
}
$finalIds = array_values(array_unique($finalIds));
$finalPl = implode(',', array_fill(0, count($finalIds), '?'));
$st = $conn->prepare("SELECT ParticipantID, Result FROM cupFinalResults WHERE Year = ? AND ParticipantID IN ($finalPl)");
$werte = array_merge([$jahr], $finalIds);
$st->bind_param(str_repeat('i', count($werte)), ...$werte);
$st->execute();
$finale = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

if ($aktion === 'pruefen') {
    $namen = function (array $row) use ($conn) {
        $n = [];
        foreach (['Participant1', 'Participant2', 'Participant3'] as $k) {
            if ((int)$row[$k] > 0) $n[] = cupMitgliedName($conn, (int)$row[$k]);
        }
        return implode(' – ', $n);
    };
    $folgen = [];
    foreach ($r2 as $row) $folgen[] = 'Runde 2: Paarung ' . $namen($row) . ' mit ihren Resultaten';
    foreach ($finale as $row) {
        $folgen[] = 'Finale: Resultat von ' . cupMitgliedName($conn, (int)$row['ParticipantID'])
                  . ($row['Result'] !== null ? ' (' . (int)$row['Result'] . ' Pkt)' : '');
    }
    msvLoeschAntwort(['success' => true, 'jahr' => $jahr, 'runde' => $runde,
                      'paarung' => $namen($pair), 'folgen' => $folgen]);
}

if ($aktion !== 'loeschen') {
    msvLoeschAntwort(['success' => false, 'message' => 'Unbekannte Aktion.'], 400);
}

$sicherung = msvBackupVorher('cup-paarung-loeschen-' . $pairId . '-' . $jahr);
if (!$sicherung['ok']) {
    error_log('[cup delete_pair] Sicherung fehlgeschlagen: ' . $sicherung['meldung']);
    msvLoeschAntwort(['success' => false, 'message' => 'Die Sicherung vor dem Löschen hat nicht geklappt. Es wurde nichts gelöscht.'], 500);
}

$conn->begin_transaction();
try {
    $ids = array_merge([$pairId], array_map(function ($r) { return (int)$r['ID']; }, $r2));
    $pl = implode(',', array_fill(0, count($ids), '?'));
    $st = $conn->prepare("DELETE FROM cupPairs WHERE ID IN ($pl)");
    $st->bind_param(str_repeat('i', count($ids)), ...$ids);
    if (!$st->execute()) throw new Exception('Paarungen löschen: ' . $st->error);
    $st->close();

    if ($finale) {
        $st = $conn->prepare("DELETE FROM cupFinalResults WHERE Year = ? AND ParticipantID IN ($finalPl)");
        $werte = array_merge([$jahr], $finalIds);
        $st->bind_param(str_repeat('i', count($werte)), ...$werte);
        if (!$st->execute()) throw new Exception('Finalresultate löschen: ' . $st->error);
        $st->close();
    }

    $conn->commit();
    msvLoeschAntwort(['success' => true, 'message' => 'Paarung gelöscht',
                      'sicherung' => basename((string)$sicherung['datei'])]);
} catch (Exception $e) {
    $conn->rollback();
    msvLoeschAntwort(['success' => false, 'message' => msvFehler('Löschen hat nicht geklappt. Es wurde nichts gelöscht. Bitte die Seite neu laden und nochmals versuchen.', $e)], 500);
}
