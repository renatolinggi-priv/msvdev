<?php
/**
 * inc/helferabrechnung/abrechnung_fragment.php – Tab «Abrechnung» als HTML-Fragment (Live-Aktualisierung
 * nach dem Speichern von Pauschalen, Korrekturen oder manuellen Zeilen). Gleiches Include wie die Seite,
 * damit es genau eine Darstellung gibt.
 *
 * GET plan_id, ok=0|1 → HTML (tab_abrechnung.inc.php) bzw. <div class="alert"> bei Fehler
 */
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('html');
require_once __DIR__ . '/abrechnung.inc.php';
require_once __DIR__ . '/../partials/empty_state.inc.php';   // msv_empty_row()

header('Content-Type: text/html; charset=utf-8');
$db     = getDB();
$planId = (int)($_GET['plan_id'] ?? 0);
$okMit  = (int)($_GET['ok'] ?? 0) === 1;
$h      = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

try {
    $plan = ha_plan_waehlen($db, 0, $planId);
    if (!$plan) { http_response_code(404); echo '<div class="alert alert-warning">Schlossturm-Plan nicht gefunden.</div>'; exit; }
    $a = ha_abrechnung($db, $plan, $okMit);
    include __DIR__ . '/tab_abrechnung.inc.php';
} catch (Throwable $e) {
    error_log('[helferabrechnung/abrechnung_fragment] ' . $e->getMessage());
    http_response_code(500);
    echo '<div class="alert alert-danger">Abrechnung fehlgeschlagen (Migration 058 ausgeführt?)</div>';
}
