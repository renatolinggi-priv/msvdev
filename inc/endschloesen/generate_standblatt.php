<?php
/**
 * Generiert ein Endschiessen-Standblatt als PDF (Download und QZ-Tray-Direktdruck).
 * Seit 1.68.50 direkt mit Dompdf (standblatt_pdf.inc.php), ohne Excel-Vorlage und PDF-Dienst.
 * GET-Parameter: jahr, mitglied_id (oder gast_name), stiche (kommasepariert, gelöste Codes),
 *                waffen_id (optional; sonst gespeicherte Lösung bzw. Stammdaten).
 *                format=pdf wird noch angenommen (Direktdruck), das Ergebnis ist immer PDF.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('plain'); // Zugriff nur Admin-Bereich (admin/vorstand)
require_once __DIR__ . '/../pdf/pdf_theme.php';
require_once __DIR__ . '/standblatt_pdf.inc.php';

// --- Parameter ---
$jahr = intval($_GET['jahr'] ?? date('Y'));
$mitglied_id = intval($_GET['mitglied_id'] ?? 0);
$gast_name = trim($_GET['gast_name'] ?? '');
$sticheCsv = trim($_GET['stiche'] ?? '');
$geloeste = $sticheCsv !== '' ? array_map('trim', explode(',', $sticheCsv)) : [];

// --- Mitglied-Daten laden ---
$vorname = '';
$nachname = '';
$lizenz = 0;

if ($mitglied_id > 0 && isset($conn)) {
    $stmt = $conn->prepare("SELECT Vorname, Name, ID FROM mitglieder WHERE ID = ?");
    $stmt->bind_param('i', $mitglied_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $vorname = $row['Vorname'];
        $nachname = $row['Name'];
        $lizenz = (int) $row['ID'];   // Lizenz-Barcode nur für Mitglieder
    }
    $stmt->close();
} elseif ($gast_name !== '') {
    // Gast: Name splitten (Format "Vorname Nachname" oder einfach den ganzen String)
    $parts = explode(' ', $gast_name, 2);
    $vorname = $parts[0];
    $nachname = $parts[1] ?? '';
}

// --- Waffe ---
// Vorrang: 1. waffen_id aus dem Formular, 2. bei der Lösung gespeicherte Waffe
// (endstich_selection.waffen_id, Migration 047), 3. Stammdaten (mitglieder.WaffenID
// bzw. endstich_gaeste.waffen_id).
$waffenId = intval($_GET['waffen_id'] ?? 0);
$waffeBez = '';
if (isset($conn)) {
    $einWert = static function (string $sql, string $types, array $params) use ($conn) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();
        return $row ? $row[0] : null;
    };
    if ($waffenId <= 0) {
        $res = $conn->query("SHOW COLUMNS FROM endstich_selection LIKE 'waffen_id'");
        $hatWaffeSpalte = $res ? $res->num_rows > 0 : false;
        if ($mitglied_id > 0) {
            if ($hatWaffeSpalte) {
                $waffenId = (int) $einWert("SELECT waffen_id FROM endstich_selection WHERE mitglied_id = ? AND jahr = ? AND waffen_id IS NOT NULL LIMIT 1", 'ii', [$mitglied_id, $jahr]);
            }
            if ($waffenId <= 0) {
                $waffenId = (int) $einWert("SELECT WaffenID FROM mitglieder WHERE ID = ?", 'i', [$mitglied_id]);
            }
        } elseif ($gast_name !== '') {
            $gastId = (int) $einWert("SELECT id FROM endstich_gaeste WHERE name = ? AND jahr = ?", 'si', [$gast_name, $jahr]);
            if ($gastId > 0 && $hatWaffeSpalte) {
                $waffenId = (int) $einWert("SELECT waffen_id FROM endstich_selection WHERE gast_id = ? AND jahr = ? AND waffen_id IS NOT NULL LIMIT 1", 'ii', [$gastId, $jahr]);
            }
            if ($gastId > 0 && $waffenId <= 0) {
                $waffenId = (int) $einWert("SELECT waffen_id FROM endstich_gaeste WHERE id = ?", 'i', [$gastId]);
            }
        }
    }
    if ($waffenId > 0) {
        $waffeBez = (string) ($einWert("SELECT Bezeichnung FROM Waffen WHERE ID = ?", 'i', [$waffenId]) ?? '');
    }
}

// --- PDF ---
try {
    $pdf = sb_pdf_rendern(esb_dokument([
        'name'     => trim($vorname . ' ' . $nachname),
        'waffe'    => $waffeBez,
        'jahr'     => $jahr,
        'lizenz'   => $lizenz,
        'geloeste' => $geloeste,
        'preise'   => esb_preise($conn),
        'nummern'  => esb_stichnummern($conn),
    ]));
    // Dateiname ohne Leer- und Sonderzeichen (Content-Disposition)
    $basis = preg_replace('/[^A-Za-z0-9_-]/', '', strtr($vorname . $nachname, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'é' => 'e', 'è' => 'e', 'à' => 'a'])) ?: 'Teilnehmer';
    sb_pdf_senden($pdf, 'Endschiessen_' . $jahr . '_' . $basis . '.pdf');
} catch (Throwable $e) {
    error_log('[generate_standblatt] ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo msvFehler('Das Standblatt konnte nicht erstellt werden. Bitte nochmals versuchen.', $e);
}
exit;
