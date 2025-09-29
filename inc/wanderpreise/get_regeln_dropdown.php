<?php
/**
 * export_wanderpreis_historie.php
 * Generiert einen PDF-Report für die Historie eines einzelnen Wanderpreises
 * Erwartet: GET ?wanderpreis_id=INT
 */

declare(strict_types=1);

// --- DEV-Debug (bei Bedarf kurz aktivieren) ---
// ini_set('display_errors', '1');
// error_reporting(E_ALL);

require_once 'wanderpreise_config.php';
require_once '../dbconnect.inc.php';
require_once 'PDFReports.php';

// WICHTIG: richtige Klasse einbinden!
require_once 'WanderpreisHistorieReport.php';

// -------------------------------------------------------------
// Fallbacks, wenn Helper-Funktionen mal nicht geladen sind
// -------------------------------------------------------------
if (!function_exists('wanderpreise_json_response')) {
    function wanderpreise_json_response(bool $success, string $message = '', array $data = [], int $code = 200): void {
        while (ob_get_level()) { ob_end_clean(); }
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
if (!function_exists('wanderpreise_debug')) {
    function wanderpreise_debug(string $label, array $context = []): void {
        // Minimal-Logger; passe an dein Logging an
        error_log('[Wanderpreise] ' . $label . ' ' . json_encode($context));
    }
}

// -------------------------------------------------------------
// DB-Verbindung
// -------------------------------------------------------------
$conn = get_db_connection();
if (!$conn || ($conn instanceof mysqli && $conn->connect_errno)) {
    wanderpreise_json_response(false, 'Datenbankverbindung fehlgeschlagen', [
        'mysqli_errno' => $conn instanceof mysqli ? $conn->connect_errno : null,
        'mysqli_error' => $conn instanceof mysqli ? $conn->connect_error : null,
    ], 500);
}

// -------------------------------------------------------------
// Input prüfen
// -------------------------------------------------------------
$wanderpreis_id = isset($_GET['wanderpreis_id']) ? (int)$_GET['wanderpreis_id'] : 0;
if ($wanderpreis_id <= 0) {
    wanderpreise_json_response(false, 'Keine gültige Wanderpreis-ID angegeben', [], 400);
}

// Optional: Existenzcheck des Wanderpreises (hilft schönere Fehlermeldungen zu geben)
try {
    $stmt = $conn->prepare('SELECT 1 FROM wanderpreise WHERE id = ? LIMIT 1');
    if ($stmt) {
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
    // Nicht kritisch – weiter versuchen, aber loggen
    wanderpreise_debug('Existenzcheck Fehler', ['err' => $t->getMessage()]);
}

// -------------------------------------------------------------
// Report generieren
// -------------------------------------------------------------
try {
    if (!class_exists('WanderpreisHistorieReport')) {
        throw new RuntimeException('Klasse WanderpreisHistorieReport nicht gefunden. Prüfe require_once und Dateinamen.');
    }

    $report = new WanderpreisHistorieReport($conn, $wanderpreis_id);

    // Erwartung: generate() kümmert sich um PDF-Erstellung und sendet am Ende JSON (pdf_link etc.)
    // Falls generate() nur den Pfad zurückgibt, passe es entsprechend an:
    $result = $report->generate();

    // Wenn deine generate() bereits die JSON-Antwort schickt, sind wir hier nie.
    // Falls nicht, liefern wir hier einheitlich zurück:
    if (is_array($result)) {
        // erwarte: ['pdf_file_path' => '/abs/fs/path', 'pdf_link' => '/absoluter/web/pfad']
        wanderpreise_json_response(true, 'Historie PDF erstellt', $result, 200);
    } elseif (is_string($result)) {
        // Nur Pfad als String? Dann URL daraus ableiten (Dateiname extrahieren)
        $filename = basename($result);
        $publicPath = '/inc/wanderpreise/dat/' . rawurlencode($filename); // analog zu PDFGenerator::outputDownloadLink
        wanderpreise_json_response(true, 'Historie PDF erstellt', [
            'pdf_file_path' => $result,
            'pdf_link'      => $publicPath,
        ], 200);
    } else {
        // generate() hat evtl. selbst schon geantwortet – zur Sicherheit:
        exit;
    }
} catch (Throwable $e) {
    wanderpreise_debug('Historie Export Error', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        // 'trace' => $e->getTraceAsString(), // bei Bedarf
    ]);
    // Puffer leeren, damit kein Misch-Output entsteht
    while (ob_get_level()) { ob_end_clean(); }
    wanderpreise_json_response(false, 'Fehler beim Erstellen des Historien-PDFs: ' . $e->getMessage(), [], 500);
} finally {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}
