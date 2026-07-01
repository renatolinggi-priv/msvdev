<?php
// save_jmdefinition.php
include '../config.php';

// CSRF-Schutz
require_once __DIR__ . '/../session_config.inc.php';
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Ungültige Anfrage']));
}

header('Content-Type: application/json; charset=utf-8');

// Debug-Funktion (schreibt nur bei aktiviertem Debug-Flag in die PHP-Error-Logs)
// In Produktion standardmaessig aus. Zum Aktivieren: JMDEF_DEBUG = true setzen.
if (!defined('JMDEF_DEBUG')) define('JMDEF_DEBUG', false);
function debug_log($message) {
    if (!JMDEF_DEBUG) return;
    error_log("[save_jmdefinition DEBUG] " . $message);
}

// Überprüfen der Datenbankverbindung
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $conn->connect_error]));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Parameter aus dem POST-Request
    $order = isset($_POST['order']) ? explode(',', $_POST['order']) : [];
    $bezeichnungen = isset($_POST['bezeichnung']) ? $_POST['bezeichnung'] : [];
    $maxpunkte = isset($_POST['maxpunkte']) ? $_POST['maxpunkte'] : [];
    $streicher = isset($_POST['streicher']) ? $_POST['streicher'] : [];
    $erweitert = isset($_POST['erweitert']) ? $_POST['erweitert'] : [];
    $adresse    = isset($_POST['adresse']) ? $_POST['adresse'] : [];
    $gruppe    = isset($_POST['gruppe']) ? $_POST['gruppe'] : [];
    $zuschlag = isset($_POST['zuschlag']) ? $_POST['zuschlag'] : [];
    $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
    $schiesstage = isset($_POST['schiesstage']) ? $_POST['schiesstage'] : []; // Mehrzeiliger Text pro Event
    $info = isset($_POST['info']) ? $_POST['info'] : [];

    // Debug: Zeige empfangene Daten
    debug_log("Year: " . $year);
    debug_log("Adresse Array: " . print_r($adresse, true));
    debug_log("Bezeichnungen Array Keys: " . implode(',', array_keys($bezeichnungen)));

    // Beginne Transaktion
    $conn->begin_transaction();

    // Sammelt Schiesstag-Zeilen, die nicht automatisch erkannt werden konnten
    $parseWarnings = [];

    try {
        // 1. Aktualisiere JMDefinition-Daten
        $stmtUpdate = $conn->prepare("UPDATE JMDefinition SET Bezeichnung = ?, Maxpunkte = ?, Streicher = ?, Erweitert = ?, Schiesstage = ?, Info = ?, Gruppe = ?, Adresse = ?, Zuschlag = ? WHERE ID = ?");
        if (!$stmtUpdate) {
            throw new Exception("Fehler beim Vorbereiten des Update-Statements: " . $conn->error);
        }
        foreach ($bezeichnungen as $id => $bezeichnung) {
            $id = intval($id);
            $bezeichnung = trim($bezeichnung);
            $maxpunkt = isset($maxpunkte[$id]) ? intval($maxpunkte[$id]) : 0;
            $isStreicher = isset($streicher[$id]) ? 1 : 0;
            $isErweitert = isset($erweitert[$id]) ? 1 : 0;
            $isInfo = isset($info[$id]) ? 1 : 0;
            $isGruppe = isset($gruppe[$id]) ? 1 : 0;
            $zuschlagValue = isset($zuschlag[$id]) ? intval($zuschlag[$id]) : 0;
            $schiesstageValue = isset($schiesstage[$id]) ? trim($schiesstage[$id]) : '';
            $adresseValue = isset($adresse[$id]) ? trim($adresse[$id]) : '';

            $stmtUpdate->bind_param(
                "siiisiisii",
                $bezeichnung,      // s (1)
                $maxpunkt,         // i (2)
                $isStreicher,      // i (3)
                $isErweitert,      // i (4)
                $schiesstageValue, // s (5)
                $isInfo,           // i (6)
                $isGruppe,         // i (7)
                $adresseValue,     // s (8)
                $zuschlagValue,    // i (9)
                $id                // i (10)
            );
            
            debug_log("Updating ID $id: Adresse='$adresseValue'");
            
            if (!$stmtUpdate->execute()) {
                throw new Exception("Fehler beim Aktualisieren von Event ID $id: " . $stmtUpdate->error);
            }
        }
        $stmtUpdate->close();

        // 2. Aktualisiere JMSchiesstage-Daten
        // Wir nehmen an, dass in der Tabelle JMSchiesstage die Felder vorhanden sind:
        // jm_id (int), schiesstag (DATE), start_time (TIME), end_time (TIME), year (int)
        $stmtDelete = $conn->prepare("DELETE FROM JMSchiesstage WHERE jm_id = ?");
        if (!$stmtDelete) {
            throw new Exception("Fehler beim Vorbereiten des Delete-Statements für JMSchiesstage: " . $conn->error);
        }
        $stmtInsert = $conn->prepare("INSERT INTO JMSchiesstage (jm_id, schiesstag, start_time, end_time, year) VALUES (?, ?, ?, ?, ?)");
        if (!$stmtInsert) {
            throw new Exception("Fehler beim Vorbereiten des Insert-Statements für JMSchiesstage: " . $conn->error);
        }
        
        // Mapping deutscher Monatsnamen in zweistellige Zahlen
        $months = [
            "Januar" => "01",
            "Februar" => "02",
            "März" => "03",
            "April" => "04",
            "Mai" => "05",
            "Juni" => "06",
            "Juli" => "07",
            "August" => "08",
            "September" => "09",
            "Oktober" => "10",
            "November" => "11",
            "Dezember" => "12"
        ];

        foreach ($bezeichnungen as $id => $dummy) {
            $id = intval($id);
            // Lösche alte Schießtage für dieses Event
            $stmtDelete->bind_param("i", $id);
            if (!$stmtDelete->execute()) {
                throw new Exception("Fehler beim Löschen der alten Schiesstage für Event ID $id: " . $stmtDelete->error);
            }
            
            // Falls neue Schießstage vorhanden sind
            if (isset($schiesstage[$id]) && !empty(trim($schiesstage[$id]))) {
                // Normalisiere Zeilenumbrüche
                $normalized = str_replace(["\r\n", "\r"], "\n", trim($schiesstage[$id]));
                debug_log("Event ID $id - Normalisierte schiesstage: " . $normalized);
                
                // Zerlege den Text in Zeilen
                $lines = preg_split("/\r?\n/", $normalized);
                debug_log("Event ID $id - Gefundene Zeilen: " . print_r($lines, true));
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    // Whitespace robust normalisieren: geschützte Leerzeichen (NBSP),
                    // Tabs und Mehrfach-Leerzeichen -> ein einzelnes Leerzeichen.
                    $line = preg_replace('/[\s\x{00A0}]+/u', ' ', $line);
                    // Fehlendes Leerzeichen nach dem Tages-Punkt ergänzen ("15.März" -> "15. März")
                    $line = preg_replace('/(\d{1,2}\.)(\p{L})/u', '$1 $2', $line);
                    $line = trim($line);

                    /* Wir gehen davon aus, dass jede Zeile im Format ist:
                       "Samstag 12. April 2025 08:00 – 12:00 Uhr, 13:30 – 17:00 Uhr"
                       Wir möchten zunächst den gemeinsamen Datumsteil (Prefix) ermitteln.
                    */
                    $patternPrefix = '/^(?P<prefix>(?:(?:\S+\s+)?\d{1,2}\.\s+(?:Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember)(?:\s+\d{4})?))\s+(?P<rest>.+)$/u';
                    if (preg_match($patternPrefix, $line, $prefixMatches)) {
                        $commonPrefix = $prefixMatches['prefix'];  // z. B. "Samstag 12. April 2025"
                        $restText = $prefixMatches['rest'];          // z. B. "08:00 – 12:00 Uhr, 13:30 – 17:00 Uhr"
                    } else {
                        // Falls kein Präfix gefunden wird, verwenden wir die ganze Zeile als restText
                        $commonPrefix = "";
                        $restText = $line;
                    }
                    
                    // Teile den restlichen Text an Kommas auf, falls mehrere Intervalle vorhanden sind
                    $parts = explode(",", $restText);
                    
                    foreach ($parts as $part) {
                        $part = trim($part);
                        // Falls der Teil nicht mit einer Zeit beginnt, fügen wir den gemeinsamen Präfix hinzu.
                        if (!preg_match('/^\d{1,2}:\d{2}/', $part)) {
                            $fullPart = $commonPrefix . ' ' . $part;
                        } else {
                            $fullPart = $commonPrefix . ' ' . $part;
                        }
                        
                        // Nun parsen wir den vollständigen String. Dieser sollte im Format sein:
                        // "Samstag 12. April 2025 08:00 – 12:00 Uhr"
                        // Wir ignorieren das Wort "Uhr" am Ende.
                        //$patternFull = '/^(?:(\S+)\s+)?(\d{1,2})\.\s+(Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember)(?:\s+\d{4})?\s+(\d{1,2}:\d{2})\s*[-–]\s*(\d{1,2}:\d{2})(?:\s*Uhr)?/u';
                        $patternFull = '/^(?:(\S+)\s+)?(\d{1,2})\.\s+(Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember)(?:\s+\d{4})?\s+(\d{1,2}[:\.]\d{2})\s*[-–]\s*(\d{1,2}[:\.]\d{2})(?:\s*Uhr)?/u';

                        if (preg_match($patternFull, $fullPart, $matches)) {
                            // Wir extrahieren Tag, Monat, Startzeit und Endzeit (der Wochentag wird hier ignoriert)
                            $day = $matches[2];
                            $monthGerman = $matches[3];
                            $startTime = $matches[4];
                            $endTime = $matches[5];
                            
                            $startTime = str_replace('.', ':', $startTime);
                            $endTime   = str_replace('.', ':', $endTime);
                            // Konvertiere Tag in zweistellig
                            $day = str_pad($day, 2, '0', STR_PAD_LEFT);
                            // Hole die zweistellige Monatszahl
                            $monthNum = isset($months[$monthGerman]) ? $months[$monthGerman] : '00';
                            // Erstelle einen Datum-String im Format "YYYY-MM-DD"
                            $dateStr = $year . "-" . $monthNum . "-" . $day;
                            // Formatiere die Zeiten (als TIME, hier im Format "HH:MM:SS")
                            $startTimeFormatted = $startTime . ":00";
                            $endTimeFormatted = $endTime . ":00";
                            
                            debug_log("Event ID $id - Insert: Datum: $dateStr, Start: $startTimeFormatted, End: $endTimeFormatted");
                            
                            $stmtInsert->bind_param("isssi", $id, $dateStr, $startTimeFormatted, $endTimeFormatted, $year);
                            if (!$stmtInsert->execute()) {
                                throw new Exception("Fehler beim Einfügen von Schiesstag für Event ID $id: " . $stmtInsert->error);
                            }
                        } else {
                            debug_log("Kein Regex-Treffer für: $fullPart");
                            $parseWarnings[$line] = true; // Zeile als nicht erkannt vormerken (dedupliziert)
                        }
                    }
                }
            }
        }
        $stmtDelete->close();
        $stmtInsert->close();

        $conn->commit();
        $response = ['success' => true, 'message' => 'Alle Änderungen wurden erfolgreich gespeichert'];
        if (!empty($parseWarnings)) {
            // Schlüssel sind die nicht erkannten Zeilen (dedupliziert)
            $response['warnings'] = array_keys($parseWarnings);
        }
        echo json_encode($response);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern der Änderungen: ' . $e->getMessage()]);
        exit;
    }

    // Zusätzliche Logik für Sektionsmeisterschaften (wie gehabt)
    try {
        $stmtCheckSSM = $conn->prepare("SELECT COUNT(*) AS AnzSSM FROM JMDefinition WHERE Bezeichnung LIKE ? AND year = ?");
        if (!$stmtCheckSSM) {
            throw new Exception("Fehler beim Vorbereiten des SSM-Überprüfungs-Statements: " . $conn->error);
        }
        $ssmPattern = '%Sektionsmeisterschaft%';
        $stmtCheckSSM->bind_param("si", $ssmPattern, $year);
        if (!$stmtCheckSSM->execute()) {
            throw new Exception("Fehler beim Ausführen des SSM-Überprüfungs-Statements: " . $stmtCheckSSM->error);
        }
        $resultSSM = $stmtCheckSSM->get_result();
        if ($resultSSM) {
            $row = $resultSSM->fetch_assoc();
            $anzSSM = $row['AnzSSM'];
            if ($anzSSM > 1) {
                $stmtInsertSSM = $conn->prepare("INSERT INTO JMDefinition (Reihenfolge, Bezeichnung, Maxpunkte, Streicher, hidden, year, Erweitert, Schiesstage, Gruppe) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmtInsertSSM) {
                    throw new Exception("Fehler beim Vorbereiten des SSM-Einfüge-Statements: " . $conn->error);
                }
                $stmtMaxReihenfolge = $conn->prepare("SELECT MAX(Reihenfolge) AS maxReihenfolge FROM JMDefinition WHERE year = ?");
                if (!$stmtMaxReihenfolge) {
                    throw new Exception("Fehler beim Vorbereiten des MaxReihenfolge-Statements: " . $conn->error);
                }
                $stmtMaxReihenfolge->bind_param("i", $year);
                if (!$stmtMaxReihenfolge->execute()) {
                    throw new Exception("Fehler beim Ausführen des MaxReihenfolge-Statements: " . $stmtMaxReihenfolge->error);
                }
                $resultMax = $stmtMaxReihenfolge->get_result();
                $rowMax = $resultMax->fetch_assoc();
                $nextReihenfolge = ($rowMax['maxReihenfolge'] !== null) ? ($rowMax['maxReihenfolge'] + 1) : 1;
                $stmtMaxReihenfolge->close();
                $reihenfolgeSSM = $nextReihenfolge;
                $bezeichnungSSM = 'SSM';
                $maxpunkteSSM = 100;
                $streicherSSM = 0;
                $hiddenSSM = 1;
                $erweitertSSM = 0;
                $stmtInsertSSM->bind_param("isiiisiis", $reihenfolgeSSM, $bezeichnungSSM, $maxpunkteSSM, $streicherSSM, $hiddenSSM, $year, $erweitertSSM, '', 0);
                if ($stmtInsertSSM->execute()) {
                    error_log("SSM-Eintrag erfolgreich hinzugefügt.");
                } else {
                    throw new Exception("Fehler beim Einfügen von SSM: " . $stmtInsertSSM->error);
                }
                $stmtInsertSSM->close();
            }
        } else {
            throw new Exception("Fehler bei der SSM-Abfrage: " . $conn->error);
        }
        $stmtCheckSSM->close();
    } catch (Exception $e) {
        error_log("Fehler bei der SSM-Logik: " . $e->getMessage());
    }
}

$conn->close();
?>
