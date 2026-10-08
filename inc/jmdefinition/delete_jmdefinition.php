<?php
// delete_jmdefinition.php – JM-Anlass löschen nur ohne Daten (Entscheid 08.10.2026)
//
// POST id, aktion:
//   pruefen  → {success, name, bezuege{Bezeichnung: Anzahl}, gesperrt}
//   loeschen → nur ohne Bezüge, nach Sicherung der Datenbank: Anlass samt Schiesstagen und
//              Gruppen-Zuordnungen in einer Transaktion (keine FKs im Schema) → {success, sicherung}
// Früher blieben Resultate, importierte Ranglisten und Fragebogen-Antworten verwaist zurück, und die
// Galerie verschwand per ON DELETE CASCADE samt Foto-Einträgen (die Bilddateien blieben liegen).
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
require_once __DIR__ . '/../loesch_sperre.inc.php';
require_once __DIR__ . '/../backup_dump.inc.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') msvLoeschAntwort(['success' => false, 'message' => 'Methode nicht erlaubt'], 405);
csrf_require(true);

$id = (int)($_POST['id'] ?? 0);
$aktion = (string)($_POST['aktion'] ?? 'loeschen');
if ($id < 1) msvLoeschAntwort(['success' => false, 'message' => 'Ungültige ID'], 400);

$st = $conn->prepare('SELECT Bezeichnung, year FROM JMDefinition WHERE ID = ?');
$st->bind_param('i', $id);
$st->execute();
$def = $st->get_result()->fetch_assoc();
$st->close();
if (!$def) msvLoeschAntwort(['success' => false, 'message' => 'Anlass nicht gefunden'], 404);
$name = trim((string)$def['Bezeichnung']);

// Daten am Anlass; Schiesstage, Gruppen-Zuordnungen und Kranzlimiten sind Teil der Definition und gehen mit
$bezuege = msvBezuege($conn, ['jmdefinitionid', 'jmdefinition_id'], $id,
    ['jmdefinition', 'jmdefinition_gruppen', 'skranzlimiten', 'anlass_galerie'],
    [
        'jmresultate' => 'Resultate', 'jmresultate*' => 'Resultate (Archiv)',
        'einzelrangierungen' => 'Einträge in Einzelranglisten', 'sektionsrangierungen' => 'Einträge in Sektionsranglisten',
        'mitglieder_fragebogen*' => 'Fragebogen-Antworten',
    ]);
// Galerie: zählt nur, wenn Fotos darin sind (eine leere, freigeschaltete Galerie geht mit)
$f = $conn->prepare('SELECT COUNT(*) FROM anlass_fotos f JOIN anlass_galerie g ON g.id = f.galerie_id WHERE g.jmdefinition_id = ?');
if ($f) {
    $f->bind_param('i', $id);
    $f->execute();
    $fotos = (int)$f->get_result()->fetch_row()[0];
    $f->close();
    if ($fotos > 0) $bezuege['Fotos in der Galerie'] = $fotos;
}

if ($aktion === 'pruefen') {
    msvLoeschAntwort(['success' => true, 'name' => $name, 'bezuege' => (object)$bezuege, 'gesperrt' => (bool)$bezuege]);
}
if ($aktion !== 'loeschen') msvLoeschAntwort(['success' => false, 'message' => 'Unbekannte Aktion'], 400);

if ($bezuege) {
    msvLoeschAntwort(['success' => false, 'gesperrt' => true, 'bezuege' => (object)$bezuege,
                      'message' => "Am Anlass «$name» hängen Daten; er wird darum nicht gelöscht."], 409);
}

$sicherung = msvBackupVorher('jm-anlass-loeschen-' . $id);
if (!$sicherung['ok']) {
    error_log('[delete_jmdefinition] Sicherung fehlgeschlagen: ' . $sicherung['meldung']);
    msvLoeschAntwort(['success' => false, 'message' => 'Die Sicherung vor dem Löschen ist fehlgeschlagen (' . $sicherung['meldung'] . '). Es wurde nichts gelöscht.'], 500);
}

$conn->begin_transaction();
try {
    foreach ([
        "DELETE FROM JMSchiesstage WHERE jm_id = ?",
        "DELETE FROM JMDefinition_Gruppen WHERE JMDefinitionID = ?",
        "DELETE FROM sKranzLimiten WHERE JMDefinitionID = ?",
        "DELETE FROM JMDefinition WHERE ID = ?",
    ] as $sql) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('Prepare fehlgeschlagen: ' . $conn->error);
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) throw new Exception('Löschen fehlgeschlagen: ' . $stmt->error);
        $stmt->close();
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[delete_jmdefinition] ' . $e->getMessage());
    msvLoeschAntwort(['success' => false, 'message' => 'Löschen fehlgeschlagen, es wurde nichts geändert. Die Sicherung '
                      . basename((string)$sicherung['datei']) . ' liegt vor.'], 500);
}
msvLoeschAntwort(['success' => true, 'message' => "Anlass «$name» gelöscht", 'sicherung' => basename((string)$sicherung['datei'])]);
