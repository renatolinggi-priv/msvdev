<?php
/**
 * Fehlermeldungen für die Oberfläche: Klartext für den Vorstand, technische Details nur ins PHP-Log.
 *
 * Datenbank-, Prepare- und Programmfehler (SQLSTATE, «Prepare failed», TypeError …) gehören nie
 * in eine Antwort an den Browser. Bewusst formulierte Meldungen aus dem eigenen Code
 * (z.B. throw new Exception('Spalte «Name» fehlt in der Datei.')) bleiben dagegen sichtbar.
 *
 *   } catch (Throwable $e) {
 *       echo json_encode(['success' => false, 'message' => msvFehler('Speichern hat nicht geklappt. Bitte nochmals versuchen.', $e)]);
 *   }
 *   // ohne Exception, z.B. nach $stmt === false:
 *   msvFehler('Die Daten konnten nicht geladen werden. Bitte die Seite neu laden.', $conn->error);
 *
 * Eingebunden über inc/config.php und inc/dbconnect.inc.php, steht also in allen Endpunkten bereit.
 */
if (!function_exists('msvFehler')) {
    function msvFehler(string $klartext, $fehler = null): string
    {
        $text = $fehler instanceof Throwable ? $fehler->getMessage() : trim((string)$fehler);

        if ($text !== '') {
            $wo = basename($_SERVER['SCRIPT_NAME'] ?? 'cli');
            $ort = $fehler instanceof Throwable ? ' @ ' . basename($fehler->getFile()) . ':' . $fehler->getLine() : '';
            error_log("[$wo] " . ($fehler instanceof Throwable ? get_class($fehler) . ': ' : '') . $text . $ort);
        }

        // Im Zweifel technisch: dann sieht der Benutzer den Klartext, was nie schadet.
        // Bewusst formuliert ist nur, was der eigene Code wirft; Bibliotheken (vendor/) zählen als technisch.
        $technisch = !($fehler instanceof Throwable)
            || $fehler instanceof mysqli_sql_exception
            || $fehler instanceof PDOException
            || $fehler instanceof Error
            || preg_match('#[/\\\\]vendor[/\\\\]#', $fehler->getFile())
            || $text === ''
            || preg_match('/SQLSTATE|mysqli|PDO|prepare|execute|statement|query|SQL|bind|Duplicate entry|Unknown column|syntax|failed|fehlgeschlagen|vorbereiten|Datenbank|DB-Fehler|\bERR\b|Call to|Undefined|Stack trace|Warning|Deprecated|\/home\/|\.php\b/i', $text);

        return $technisch ? $klartext : $text;
    }
}
