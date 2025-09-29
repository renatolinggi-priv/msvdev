<?php
// wanderpreise/delete_wanderpreis.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';

header('Content-Type: application/json; charset=utf-8');

$conn = get_db_connection();
if (!$conn) {
  wanderpreise_json_response(false, 'Datenbankverbindung fehlgeschlagen', [], 500);
}

// Nur POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  wanderpreise_json_response(false, 'Method not allowed', [], 405);
}

// CSRF
wanderpreise_check_csrf();

// ID prüfen
$wanderpreis_id = isset($_POST['wanderpreis_id']) ? (int)$_POST['wanderpreis_id'] : 0;
if ($wanderpreis_id <= 0) {
  wanderpreise_json_response(false, 'Ungültige Wanderpreis-ID', [], 400);
}

try {
  // Existenz + Gewinnerzahl prüfen (ohne nicht existente Spalten)
  $check_sql = "SELECT 
                  w.id,
                  w.bezeichnung,
                  (SELECT COUNT(*) 
                     FROM wanderpreise_gewinner AS wg 
                    WHERE wg.wanderpreis_id = w.id) AS anzahl_gewinner
                FROM wanderpreise AS w
                WHERE w.id = ?";
  $check_stmt = $conn->prepare($check_sql);
  $check_stmt->bind_param('i', $wanderpreis_id);
  $check_stmt->execute();
  $check_result = $check_stmt->get_result();

  if ($check_result->num_rows === 0) {
    wanderpreise_json_response(false, 'Wanderpreis nicht gefunden', [], 404);
  }

  $wanderpreis = $check_result->fetch_assoc();
  $anzahl_gewinner = (int)$wanderpreis['anzahl_gewinner'];

  // ggf. Bestätigung verlangen
  if ($anzahl_gewinner > 0) {
    $force_delete = isset($_POST['force_delete']) && $_POST['force_delete'] === 'true';
    if (!$force_delete) {
      wanderpreise_json_response(false, 
        'Dieser Wanderpreis hat bereits '.$anzahl_gewinner.' Gewinner. Löschen würde alle Gewinner-Daten entfernen.',
        ['require_confirmation' => true, 'gewinner_count' => $anzahl_gewinner],
        409
      );
    }
  }

  // Transaktion
  $conn->autocommit(false);
  try {
    // Kinder zuerst löschen (falls vorhanden)
    $geloeschte_gewinner = 0;
    if ($anzahl_gewinner > 0) {
      $delG = $conn->prepare("DELETE FROM wanderpreise_gewinner WHERE wanderpreis_id = ?");
      $delG->bind_param('i', $wanderpreis_id);
      if (!$delG->execute()) throw new Exception('Fehler beim Löschen der Gewinner-Einträge: '.$delG->error);
      $geloeschte_gewinner = $delG->affected_rows;
    }

    // Hauptdatensatz löschen
    $delW = $conn->prepare("DELETE FROM wanderpreise WHERE id = ? LIMIT 1");
    $delW->bind_param('i', $wanderpreis_id);
    if (!$delW->execute()) throw new Exception('Fehler beim Löschen des Wanderpreises: '.$delW->error);
    if ($delW->affected_rows === 0) throw new Exception('Wanderpreis konnte nicht gelöscht werden - möglicherweise bereits gelöscht');

    $conn->commit();

    $msg = "Wanderpreis '".$wanderpreis['bezeichnung']."' erfolgreich gelöscht";
    if ($geloeschte_gewinner > 0) $msg .= " (inkl. $geloeschte_gewinner Gewinner-Einträge)";

    wanderpreise_json_response(true, $msg, ['geloeschte_gewinner' => $geloeschte_gewinner]);
  } catch (Throwable $tx) {
    $conn->rollback();
    throw $tx;
  } finally {
    // Sicherheitshalber immer wieder aktivieren
    $conn->autocommit(true);
  }

} catch (Throwable $e) {
  wanderpreise_debug('Wanderpreis Delete Error', ['error' => $e->getMessage()]);
  wanderpreise_json_response(false, 'Fehler beim Löschen des Wanderpreises: '.$e->getMessage(), [], 500);
}

$conn->close();
