<?php
// api/_umfrage_antwort.inc.php
// Gemeinsame Upsert-/Delete-Logik für Umfrage-Antworten.
// Genutzt von portal_umfrage_save.php (Batch) und portal_umfrage_autosave.php (einzeln).

/**
 * Löscht die Antwort eines Mitglieds auf eine Frage (falls vorhanden).
 */
function deleteUmfrageAntwort($db, $frageId, $mitgliedId) {
    $db->prepare("DELETE FROM umfragen_antworten WHERE frage_id = ? AND mitglied_id = ?")
       ->execute([$frageId, $mitgliedId]);
}

/**
 * Speichert/aktualisiert die Antwort eines Mitglieds auf eine Frage (Upsert).
 */
function upsertUmfrageAntwort($db, $umfrageId, $frageId, $mitgliedId, $val) {
    $stmtCheck = $db->prepare("SELECT id FROM umfragen_antworten WHERE frage_id = ? AND mitglied_id = ? LIMIT 1");
    $stmtCheck->execute([$frageId, $mitgliedId]);
    $existing = $stmtCheck->fetch();

    if ($existing) {
        $db->prepare("UPDATE umfragen_antworten SET antwort = ?, beantwortet_am = NOW() WHERE id = ?")
           ->execute([$val, $existing['id']]);
    } else {
        $db->prepare("INSERT INTO umfragen_antworten (umfrage_id, frage_id, mitglied_id, antwort) VALUES (?, ?, ?, ?)")
           ->execute([$umfrageId, $frageId, $mitgliedId, $val]);
    }
}
