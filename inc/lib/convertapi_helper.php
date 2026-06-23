<?php
/**
 * ConvertAPI Helper — DOCX → PDF Konvertierung via ConvertAPI.com
 *
 * Zentrale Funktion fuer alle DOCX→PDF Konvertierungen im Projekt.
 * API-Secret wird aus msvjm_config.php geladen.
 *
 * Verwendung:
 *   require_once __DIR__ . '/../lib/convertapi_helper.php';
 *   $pdfPath = convertDocxToPdf('/tmp/dokument.docx');
 *   // ... PDF verwenden ...
 *   unlink($pdfPath); // Caller raeumt auf
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ConvertApi\ConvertApi;

/**
 * Konvertiert eine DOCX-Datei zu PDF via ConvertAPI.
 *
 * @param string $docxPath Pfad zur DOCX-Datei
 * @return string Pfad zur generierten PDF-Datei (Temp-Datei, Caller muss loeschen)
 * @throws RuntimeException Falls API-Secret fehlt oder Konvertierung fehlschlaegt
 */
function convertDocxToPdf(string $docxPath): string {
    if (!file_exists($docxPath)) {
        throw new RuntimeException('DOCX-Datei nicht gefunden: ' . $docxPath);
    }

    // API-Secret aus Config laden (gleicher Pfad wie inc/config.php)
    $configPath = __DIR__ . '/../../../msvjm_config.php';

    if (!file_exists($configPath)) {
        throw new RuntimeException('Config-Datei nicht gefunden');
    }

    $config = require $configPath;
    $secret = $config['convertapi']['secret'] ?? '';

    if ($secret === '') {
        throw new RuntimeException('ConvertAPI Secret nicht konfiguriert (msvjm_config.php → convertapi.secret)');
    }

    // ConvertAPI initialisieren
    ConvertApi::setApiSecret($secret);

    // Konvertierung ausfuehren
    try {
        $result = ConvertApi::convert('pdf', [
            'File' => $docxPath,
        ], 'docx');

        // PDF in Temp-Verzeichnis speichern
        $savedFiles = $result->saveFiles(sys_get_temp_dir());

        if (empty($savedFiles) || !file_exists($savedFiles[0])) {
            throw new RuntimeException('ConvertAPI hat keine Datei zurueckgegeben');
        }

        return $savedFiles[0];

    } catch (\Exception $e) {
        error_log('[ConvertAPI] Fehler: ' . $e->getMessage());
        throw new RuntimeException('PDF-Konvertierung fehlgeschlagen: ' . $e->getMessage());
    }
}
