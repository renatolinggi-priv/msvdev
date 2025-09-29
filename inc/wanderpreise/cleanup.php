<?php
/**
 * cleanup.php - Automatische Wartungsfunktionen für Wanderpreise
 * Löscht alte temporäre Dateien
 */

require_once 'wanderpreise_config.php';

// Nur POST erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wanderpreise_json_response(false, 'Method not allowed', [], 405);
}

// Nur in Production oder mit speziellem Key
$action = $_POST['action'] ?? '';
$auth_key = $_POST['key'] ?? '';

// Optional: Zusätzliche Sicherheit mit einem Key
$valid_key = defined('WANDERPREISE_CLEANUP_KEY') ? WANDERPREISE_CLEANUP_KEY : 'your-secret-key-here';

if ($action === 'cleanup_old_files') {
    try {
        // Alte PDFs löschen (älter als 30 Tage)
        $days = isset($_POST['days']) ? (int)$_POST['days'] : 30;
        $cleaned = wanderpreise_cleanup_old_files($days);
        
        wanderpreise_debug('Cleanup ausgeführt', [
            'action' => 'cleanup_old_files',
            'days' => $days,
            'cleaned' => $cleaned
        ]);
        
        wanderpreise_json_response(true, "Cleanup erfolgreich: {$cleaned} Dateien gelöscht", [
            'cleaned' => $cleaned,
            'days' => $days
        ]);
    } catch (Exception $e) {
        wanderpreise_debug('Cleanup Error', ['error' => $e->getMessage()]);
        wanderpreise_json_response(false, 'Cleanup fehlgeschlagen: ' . $e->getMessage(), [], 500);
    }
} else {
    wanderpreise_json_response(false, 'Unbekannte Aktion', [], 400);
}
?>