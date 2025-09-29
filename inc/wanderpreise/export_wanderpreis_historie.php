<?php
/**
 * export_wanderpreis_historie.php
 * Generiert einen PDF-Report für die Historie eines Wanderpreises
 * Erwartet: GET ?wanderpreis_id=INT
 */

declare(strict_types=1);

// --- bei Bedarf fürs Debuggen kurz aktivieren ---
// ini_set('display_errors', '1');
// error_reporting(E_ALL);

require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';

// Nur DIESE Datei, denn sie enthält bereits die Klasse WanderpreisHistorieReport
require_once 'PDFReports.php';

// Fallbacks, falls Helper aus wanderpreise_config.php mal nicht geladen sind
if (!function_exists('wanderpreise_json_response')) {
    function wanderpreise_json_response($success, $message = '', $data = [], $code = 200) {
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        $resp = ['success' => $success, 'message' => $message];
        if (!empty($data)) $resp = array_merge($resp, $data);
        echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
if (!function_exists('wanderpreise_debug')) {
    function wanderpreise_debug($label, array $ctx = []) {
        error_log('[Wanderpreise] ' . $label . ' ' . json_encode($ctx));
    }
}

// --- DB verbinden ---
$conn = get_db_connection();
if (!$conn || ($conn instanceof mysqli && $conn->connect_errno)) {
    wanderpreise_json_response(false, 'Datenbankverbindung fehlgeschlagen', [
        'mysqli_errno' => $conn instanceof mysqli ? $conn->connect_errno : null,
        'mysqli_error' => $conn instanceof mysqli ? $conn->connect_error : null,
    ], 500);
}

// --- Eingabe prüfen ---
$wanderpreis_id = isset($_GET['wanderpreis_id']) ? (int)$_GET['wanderpreis_id'] : 0;
if ($wanderpreis_id <= 0) {
    $conn->close();
    wanderpreise_json_response(false, 'Keine gültige Wanderpreis-ID angegeben', [], 400);
}

// Optional: Existenzcheck (liefert klarere Fehlermeldungen als 500)
try {
    if ($stmt = $conn->prepare('SELECT 1 FROM wanderpreise WHERE id = ? LIMIT 1')) {
        $stmt->bind_param('i', $wanderpreis_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $stmt->close();
            $conn->close();
            wanderpreise_json_response(false, 'Wanderpreis nicht gefunden', ['wanderpreis_id' => $wanderpreis_id], 404);
        }
        $stmt->close();
    }
} catch (Throwable $t) {
    // kein harter Abbruch, aber loggen
    wanderpreise_debug('Existenzcheck Fehler', ['err' => $t->getMessage()]);
}

// --- Report generieren ---
try {
    if (!class_exists('WanderpreisHistorieReport')) {
        throw new RuntimeException('Klasse WanderpreisHistorieReport nicht gefunden (liegt in PDFReports.php).');
    }

    $report = new WanderpreisHistorieReport($conn, $wanderpreis_id);

    // generate() erzeugt das PDF und antwortet bereits mit JSON (pdf_link)
    $ret = $report->generate();

    // Falls generate() ausnahmsweise nichts ausgibt, hier minimaler Fallback:
    if (is_string($ret) && $ret !== '') {
        $filename = basename($ret);
        $publicPath = '/inc/wanderpreise/dat/' . rawurlencode($filename);
        wanderpreise_json_response(true, 'Historien-PDF erstellt', [
            'pdf_file_path' => $ret,
            'pdf_link'      => $publicPath,
        ], 200);
    }
    // sonst: generate() hat schon geantwortet
    exit;

} catch (Throwable $e) {
    while (ob_get_level()) { ob_end_clean(); }
    wanderpreise_debug('Historie Export Error', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
    ]);
    wanderpreise_json_response(false, 'Fehler beim Erstellen des Historien-PDFs: ' . $e->getMessage(), [], 500);
} finally {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}
