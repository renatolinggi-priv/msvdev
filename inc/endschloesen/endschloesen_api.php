<?php
// endschloesen_api.php - Backend API für Endschiessen Stich-Erfassung (mysqli Version)

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://www.msvwilen.ch');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');
header('Access-Control-Allow-Credentials: true');

// Bei OPTIONS Request (CORS Preflight) sofort beenden
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Helper function für JSON Response
function jsonResponse($success, $data = null, $message = '', $extra = [])
{
    $response = [
        'success' => $success,
        'data' => $data,
        'message' => $message
    ];
    // Füge zusätzliche Felder hinzu
    foreach ($extra as $key => $value) {
        $response[$key] = $value;
    }
    echo json_encode($response);
    exit;
}

try {
    // Database connection
    $dbFile = __DIR__ . '/../dbconnect.inc.php';

    if (!file_exists($dbFile)) {
        error_log("DB file not found at: " . $dbFile);
        jsonResponse(false, null, 'Database configuration error');
    }

    require_once $dbFile;

    // Verwende mysqli
    if (!isset($conn)) {
        error_log("Connection not initialized after including dbconnect");
        jsonResponse(false, null, 'Database connection not initialized');
    }

    // Prüfe Verbindung
    if ($conn->connect_error) {
        error_log("Database connection error: " . $conn->connect_error);
        jsonResponse(false, null, 'Database connection failed');
    }

    // Session für CSRF
    if (!isset($_SESSION)) {
        session_start();
    }

    // CSRF Check für POST requests
    function checkCSRF()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Bei JSON Content-Type aus Header lesen
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';

            // Debug logging
            error_log("CSRF Check - Received token: " . substr($token, 0, 10) . "...");
            error_log("CSRF Check - Session token: " . substr($_SESSION['csrf_token'] ?? 'none', 0, 10) . "...");

            if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
                error_log("CSRF validation failed");
                jsonResponse(false, null, 'CSRF token validation failed');
            }
        }
    }

    // Get action
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {

        case 'get_spezialpreise':
            // Hole alle Spezialpreise
            $sql = "SELECT * FROM endstich_spezialpreise ORDER BY sort_order, typ";
            $result = $conn->query($sql);

            if (!$result) {
                // Tabelle existiert vermutlich noch nicht
                jsonResponse(true, []);
            }

            $preise = [];
            while ($row = $result->fetch_assoc()) {
                $preise[$row['typ']] = $row;
            }

            jsonResponse(true, $preise);
            break;

        case 'update_spezialpreis':
            checkCSRF();

            // Parse input
            $input = null;
            if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
            } else {
                $input = $_POST;
            }

            $typ = $input['typ'] ?? '';
            $price_cents = (int) ($input['price_cents'] ?? 0);

            if (empty($typ)) {
                jsonResponse(false, null, 'Typ fehlt');
            }

            // Prüfe ob Tabelle existiert
            $table_check = $conn->query("SHOW TABLES LIKE 'endstich_spezialpreise'");
            if ($table_check->num_rows == 0) {
                jsonResponse(false, null, 'Spezialpreise-Tabelle existiert noch nicht');
            }

            // Update Preis
            $stmt = $conn->prepare("UPDATE endstich_spezialpreise SET price_cents = ? WHERE typ = ?");
            $stmt->bind_param("is", $price_cents, $typ);

            if ($stmt->execute()) {
                jsonResponse(true, ['typ' => $typ, 'price_cents' => $price_cents], 'Preis aktualisiert');
            } else {
                jsonResponse(false, null, 'Fehler beim Update: ' . $conn->error);
            }
            break;

        case 'list_stiche':
            // Liste aller aktiven Stiche
            $sql = "SELECT id, code, name, shots, price_cents, sort_order 
                    FROM endstich_definition 
                    WHERE active = 1 
                    ORDER BY sort_order, name";

            $result = $conn->query($sql);

            if (!$result) {
                error_log("Query error: " . $conn->error);
                jsonResponse(false, null, 'Datenbankfehler beim Abrufen der Stiche');
            }

            $stiche = [];
            while ($row = $result->fetch_assoc()) {
                $stiche[] = $row;
            }

            jsonResponse(true, $stiche);
            break;

        case 'list_mitglieder':
            // Liste aller aktiven Mitglieder
            $sql = "SELECT 
  m.ID AS id,
  m.Name AS Nachname, m.Vorname,
  m.Geburtsdatum,
  m.WaffenID        AS waffe_id,
  w.Bezeichnung     AS waffe_bez,
  w.Kategorie       AS waffe_kat
FROM mitglieder m
LEFT JOIN Waffen w ON w.ID = m.WaffenID
ORDER BY m.Name, m.Vorname"
            ;

            $result = $conn->query($sql);

            if (!$result) {
                error_log("Query error: " . $conn->error);
                jsonResponse(false, null, 'Datenbankfehler beim Abrufen der Mitglieder');
            }

            $mitglieder = [];
            while ($row = $result->fetch_assoc()) {
                $mitglieder[] = $row;
            }

            jsonResponse(true, $mitglieder);
            break;

        case 'list_waffen':
            // Liste aller aktiven Waffen für Munitionsberechnung
            $sql = "SELECT id as ID, bezeichnung as Bezeichnung, kategorie as Kategorie 
                    FROM Waffen 
                    ORDER BY kategorie, bezeichnung";

            $result = $conn->query($sql);

            if (!$result) {
                error_log("Query error: " . $conn->error);
                jsonResponse(false, null, 'Datenbankfehler beim Abrufen der Waffen');
            }

            $waffen = [];
            while ($row = $result->fetch_assoc()) {
                $waffen[] = $row;
            }

            jsonResponse(true, $waffen);
            break;

        case 'get_selection':
            // Hole gespeicherte Auswahl für Mitglied/Gast/Jahr
            $mitglied_id = isset($_GET['mitglied_id']) ? (int) $_GET['mitglied_id'] : 0;
            $gast_name = isset($_GET['gast_name']) ? trim($_GET['gast_name']) : '';
            $jahr = (int) ($_GET['jahr'] ?? date('Y'));

            if (!$mitglied_id && !$gast_name) {
                jsonResponse(true, []); // Keine Auswahl wenn weder Mitglied noch Gast
            }

            $selected = [];
            $zahlungsmethode = 'bar'; // Default
            $is_js = false; // JungschützeIn Flag

            if ($mitglied_id) {
                // Mitglied-basierte Suche - hole auch Zahlungsmethode
                $stmt = $conn->prepare("SELECT DISTINCT zahlungsmethode FROM endstich_selection WHERE mitglied_id = ? AND jahr = ? AND zahlungsmethode IS NOT NULL LIMIT 1");
                $stmt->bind_param("ii", $mitglied_id, $jahr);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $zahlungsmethode = $row['zahlungsmethode'];
                }

                // Hole Stiche
                $stmt = $conn->prepare("SELECT stich_id FROM endstich_selection WHERE mitglied_id = ? AND jahr = ?");
                $stmt->bind_param("ii", $mitglied_id, $jahr);
            } else {
                // Gast-basierte Suche - erst Gast-ID finden
                $stmt = $conn->prepare("SELECT id, waffen_id, geburtsdatum FROM endstich_gaeste WHERE name = ? AND jahr = ?");
                $stmt->bind_param("si", $gast_name, $jahr);
                $stmt->execute();
                $result = $stmt->get_result();
                $gast = $result->fetch_assoc();

                if ($gast) {
                    $gast_id = $gast['id'];
                    $waffen_id = $gast['waffen_id'];
                    $geburtsdatum = $gast['geburtsdatum']; // Speichere Geburtsdatum
                    
                    // Prüfe ob JungschützeIn (Geburtsdatum vorhanden)
                    $is_js = !empty($gast['geburtsdatum']);

                    // Hole Zahlungsmethode
                    $stmt = $conn->prepare("SELECT DISTINCT zahlungsmethode FROM endstich_selection WHERE gast_id = ? AND jahr = ? AND zahlungsmethode IS NOT NULL LIMIT 1");
                    $stmt->bind_param("ii", $gast_id, $jahr);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $zahlungsmethode = $row['zahlungsmethode'];
                    }

                    // Hole Stiche
                    $stmt = $conn->prepare("SELECT stich_id FROM endstich_selection WHERE gast_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $gast_id, $jahr);
                } else {
                    jsonResponse(true, []); // Gast noch nicht erfasst
                }
            }

            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $selected[] = $row['stich_id'];
            }

            // Prüfe ob Zabig mit Partner ausgewählt ist
            $zabig_partner = false;
            if ($mitglied_id) {
                $stmt = $conn->prepare("SELECT ed.code FROM endstich_selection es 
                                        JOIN endstich_definition ed ON es.stich_id = ed.id 
                                        WHERE es.mitglied_id = ? AND es.jahr = ? AND ed.code = 'ZABIG' AND es.sie_und_er = 1");
                $stmt->bind_param("ii", $mitglied_id, $jahr);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $zabig_partner = true;
                }
            }

            $response = [
                'zahlungsmethode' => $zahlungsmethode, 
                'zabig_partner' => $zabig_partner,
                'is_js' => $is_js  // NEU: Ob es sich um JungschützeIn handelt
            ];
            if (isset($waffen_id) && $waffen_id) {
                $response['waffen_id'] = $waffen_id;
            }
            if (isset($geburtsdatum) && $geburtsdatum) {
                $response['geburtsdatum'] = $geburtsdatum;
            }

            jsonResponse(true, $selected, '', $response);
            break;

        case 'save_selection':
            checkCSRF();

            // Parse JSON body wenn Content-Type application/json
            $input = null;
            if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
            } else {
                $input = $_POST;
            }

            $mitglied_id = isset($input['mitglied_id']) ? (int) $input['mitglied_id'] : 0;
            $gast_name = isset($input['gast_name']) ? trim($input['gast_name']) : '';
            $jahr = (int) ($input['jahr'] ?? date('Y'));
            $stiche = $input['stiche'] ?? [];
            $zahlungsmethode = $input['zahlungsmethode'] ?? 'bar'; // Default: bar
            $gast_spezialpreis = isset($input['gast_spezialpreis']) ? (int) $input['gast_spezialpreis'] : null;
            $zabig_partner = isset($input['zabig_partner']) ? 1 : 0;

            if (!$mitglied_id && !$gast_name) {
                jsonResponse(false, null, 'Kein Mitglied oder Gast angegeben');
            }

            $gast_id = null;
            $is_js = false; // Flag ob JungschützeIn

            // Bei Gast: Prüfe ob er existiert oder lege ihn an
            if ($gast_name && !$mitglied_id) {
                // Prüfe zuerst ob die Tabelle existiert
                $table_check = $conn->query("SHOW TABLES LIKE 'endstich_gaeste'");
                if ($table_check->num_rows == 0) {
                    // Tabelle existiert nicht, erstelle sie
                    $create_table = "CREATE TABLE IF NOT EXISTS `endstich_gaeste` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `name` varchar(200) NOT NULL,
                        `geburtsdatum` date DEFAULT NULL,
                        `waffen_id` int(11) DEFAULT NULL,
                        `jahr` int(4) NOT NULL,
                        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        `created_by` varchar(100) DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `unique_gast_jahr` (`name`, `jahr`),
                        KEY `idx_jahr` (`jahr`),
                        KEY `idx_name` (`name`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    $conn->query($create_table);
                }

                // Prüfe ob waffen_id Spalte existiert
                $col_check = $conn->query("SHOW COLUMNS FROM endstich_gaeste LIKE 'waffen_id'");
                if ($col_check->num_rows == 0) {
                    // Spalte existiert nicht, füge sie hinzu
                    $conn->query("ALTER TABLE endstich_gaeste ADD COLUMN `waffen_id` int(11) DEFAULT NULL AFTER `geburtsdatum`");
                }

                // Prüfe ob Gast bereits existiert
                $stmt = $conn->prepare("SELECT id, geburtsdatum, waffen_id FROM endstich_gaeste WHERE name = ? AND jahr = ?");
                $stmt->bind_param("si", $gast_name, $jahr);
                $stmt->execute();
                $result = $stmt->get_result();
                $existing_gast = $result->fetch_assoc();

                if ($existing_gast) {
                    $gast_id = $existing_gast['id'];
                    
                    // Prüfe ob JungschützeIn
                    $is_js = !empty($existing_gast['geburtsdatum']);

                    // Update waffen_id wenn vorhanden
                    if (isset($input['waffen_id'])) {
                        $waffen_id = (int) $input['waffen_id'];
                        $stmt = $conn->prepare("UPDATE endstich_gaeste SET waffen_id = ? WHERE id = ?");
                        $stmt->bind_param("ii", $waffen_id, $gast_id);
                        $stmt->execute();
                    }
                } else {
                    // Prüfe ob geburtsdatum Spalte existiert
                    $col_check = $conn->query("SHOW COLUMNS FROM endstich_gaeste LIKE 'geburtsdatum'");
                    if ($col_check->num_rows == 0) {
                        // Spalte existiert nicht, füge sie hinzu
                        $conn->query("ALTER TABLE endstich_gaeste ADD COLUMN `geburtsdatum` date DEFAULT NULL AFTER `name`");
                    }

                    // Extrahiere Geburtsdatum aus dem Gast-Namen wenn vorhanden (Format: "Name (DD.MM.YYYY)")
                    $geburtsdatum = null;
                    if (preg_match('/(\d{1,2}\.\d{1,2}\.\d{4})/', $gast_name, $matches)) {
                        // Konvertiere DD.MM.YYYY zu YYYY-MM-DD
                        $date_parts = explode('.', $matches[1]);
                        if (count($date_parts) == 3) {
                            $geburtsdatum = sprintf('%04d-%02d-%02d', $date_parts[2], $date_parts[1], $date_parts[0]);
                            // Entferne das Datum aus dem Namen
                            $gast_name_clean = trim(preg_replace('/\s*\(' . preg_quote($matches[1], '/') . '\)\s*/', '', $gast_name));
                        } else {
                            $gast_name_clean = $gast_name;
                        }
                    } else {
                        $gast_name_clean = $gast_name;
                    }

                    // Erstelle neuen Gast
                    $created_by = $_SESSION['username'] ?? 'system';
                    $waffen_id = isset($input['waffen_id']) ? (int) $input['waffen_id'] : null;

                    $stmt = $conn->prepare("INSERT INTO endstich_gaeste (name, geburtsdatum, waffen_id, jahr, created_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssiis", $gast_name_clean, $geburtsdatum, $waffen_id, $jahr, $created_by);
                    $stmt->execute();
                    $gast_id = $conn->insert_id;
                    
                    // Setze is_js Flag wenn Geburtsdatum vorhanden
                    $is_js = !empty($geburtsdatum);
                }
            }

            // Validiere Mitglied falls angegeben
            if ($mitglied_id) {
                $stmt = $conn->prepare("SELECT ID FROM mitglieder WHERE ID = ?");
                $stmt->bind_param("i", $mitglied_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 0) {
                    error_log("Invalid Mitglied ID: " . $mitglied_id);
                    jsonResponse(false, null, 'Ungültiges Mitglied');
                }
            }

            // Start transaction
            $conn->begin_transaction();

            try {
                // Temporär Foreign Key Checks deaktivieren für diese Session
                $conn->query("SET FOREIGN_KEY_CHECKS = 0");
                // Prüfe ob gast_id Spalte existiert in endstich_selection
                $col_check = $conn->query("SHOW COLUMNS FROM endstich_selection LIKE 'gast_id'");
                if ($col_check->num_rows == 0) {
                    // Spalte existiert nicht, füge sie hinzu
                    $conn->query("ALTER TABLE endstich_selection ADD COLUMN `gast_id` int(11) DEFAULT NULL AFTER `mitglied_id`");
                    $conn->query("ALTER TABLE endstich_selection MODIFY COLUMN `mitglied_id` int(11) DEFAULT NULL");
                }

                // Prüfe ob zahlungsmethode Spalte existiert in endstich_selection
                $col_check = $conn->query("SHOW COLUMNS FROM endstich_selection LIKE 'zahlungsmethode'");
                if ($col_check->num_rows == 0) {
                    // Spalte existiert nicht, füge sie hinzu
                    $conn->query("ALTER TABLE endstich_selection ADD COLUMN `zahlungsmethode` varchar(20) DEFAULT 'bar' AFTER `stich_id`");
                }

                // Prüfe ob gast_spezialpreis Spalte existiert in endstich_selection
                $col_check = $conn->query("SHOW COLUMNS FROM endstich_selection LIKE 'gast_spezialpreis'");
                if ($col_check->num_rows == 0) {
                    // Spalte existiert nicht, füge sie hinzu
                    $conn->query("ALTER TABLE endstich_selection ADD COLUMN `gast_spezialpreis` int(11) DEFAULT NULL AFTER `zahlungsmethode`");
                }

                // Prüfe ob sie_und_er Spalte existiert in endstich_selection
                $col_check = $conn->query("SHOW COLUMNS FROM endstich_selection LIKE 'sie_und_er'");
                if ($col_check->num_rows == 0) {
                    // Spalte existiert nicht, füge sie hinzu
                    $conn->query("ALTER TABLE endstich_selection ADD COLUMN `sie_und_er` tinyint(1) DEFAULT 0 AFTER `gast_spezialpreis`");
                }

                // Prüfe ob gast_id Spalte existiert in endstich_zusatz_schuss
                $col_check = $conn->query("SHOW COLUMNS FROM endstich_zusatz_schuss LIKE 'gast_id'");
                if ($col_check->num_rows == 0) {
                    // Spalte existiert nicht, füge sie hinzu
                    $conn->query("ALTER TABLE endstich_zusatz_schuss ADD COLUMN `gast_id` int(11) DEFAULT NULL AFTER `mitglied_id`");
                    $conn->query("ALTER TABLE endstich_zusatz_schuss MODIFY COLUMN `mitglied_id` int(11) DEFAULT NULL");
                }

                // Hole existierende Stiche
                if ($mitglied_id) {
                    $stmt = $conn->prepare("SELECT stich_id FROM endstich_selection WHERE mitglied_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $mitglied_id, $jahr);
                } else {
                    $stmt = $conn->prepare("SELECT stich_id FROM endstich_selection WHERE gast_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $gast_id, $jahr);
                }
                $stmt->execute();
                $result = $stmt->get_result();

                $existing_stiche = [];
                while ($row = $result->fetch_assoc()) {
                    $existing_stiche[] = (int) $row['stich_id'];
                }

                // Bestimme was hinzugefügt und was entfernt werden muss
                $neue_stiche = array_map('intval', $stiche);
                $zu_loeschen = array_diff($existing_stiche, $neue_stiche);
                $zu_erstellen = array_diff($neue_stiche, $existing_stiche);
                $zu_aktualisieren = array_intersect($existing_stiche, $neue_stiche); // Bestehende die bleiben

                // Aktualisiere Zahlungsmethode und Gast-Spezialpreis für bestehende Stiche
                if (!empty($zu_aktualisieren)) {
                    if ($mitglied_id) {
                        $stmt = $conn->prepare("UPDATE endstich_selection SET zahlungsmethode = ?, sie_und_er = ? WHERE mitglied_id = ? AND jahr = ? AND stich_id = ?");
                        foreach ($zu_aktualisieren as $stich_id) {
                            // Prüfe ob es der Zabig Stich ist
                            $stich_info = $conn->query("SELECT code FROM endstich_definition WHERE id = $stich_id")->fetch_assoc();
                            $sie_und_er_value = ($stich_info && $stich_info['code'] === 'ZABIG' && $zabig_partner) ? 1 : 0;

                            $stmt->bind_param("siiii", $zahlungsmethode, $sie_und_er_value, $mitglied_id, $jahr, $stich_id);
                            $stmt->execute();
                        }
                    } else {
                        // Für Gäste: Setze Spezialpreis nur beim ersten Stich
                        $first_stich = true;
                        $stmt = $conn->prepare("UPDATE endstich_selection SET zahlungsmethode = ?, gast_spezialpreis = ? WHERE gast_id = ? AND jahr = ? AND stich_id = ?");
                        foreach ($zu_aktualisieren as $stich_id) {
                            $preis_for_update = $first_stich ? $gast_spezialpreis : null;
                            $stmt->bind_param("siiii", $zahlungsmethode, $preis_for_update, $gast_id, $jahr, $stich_id);
                            $stmt->execute();
                            $first_stich = false;
                        }
                    }
                }

                // Lösche nur die nicht mehr ausgewählten Stiche
                if (!empty($zu_loeschen)) {
                    $placeholders = implode(',', array_fill(0, count($zu_loeschen), '?'));
                    if ($mitglied_id) {
                        $sql = "DELETE FROM endstich_selection WHERE mitglied_id = ? AND jahr = ? AND stich_id IN ($placeholders)";
                    } else {
                        $sql = "DELETE FROM endstich_selection WHERE gast_id = ? AND jahr = ? AND stich_id IN ($placeholders)";
                    }
                    $stmt = $conn->prepare($sql);

                    $types = 'ii' . str_repeat('i', count($zu_loeschen));
                    // Verwende Variable statt ternären Operator
                    $entity_id = $mitglied_id ?: $gast_id;
                    $params = array_merge([$entity_id, $jahr], $zu_loeschen);

                    $bind_params = [$types];
                    foreach ($params as $key => $value) {
                        $bind_params[] = &$params[$key];
                    }
                    call_user_func_array([$stmt, 'bind_param'], $bind_params);
                    $stmt->execute();
                }

                // Füge nur neue Stiche hinzu
                if (!empty($zu_erstellen)) {
                    $created_by = $_SESSION['username'] ?? 'system';

                    if ($mitglied_id) {
                        $stmt = $conn->prepare("INSERT INTO endstich_selection (mitglied_id, jahr, stich_id, zahlungsmethode, sie_und_er, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                        foreach ($zu_erstellen as $stich_id) {
                            if ($stich_id > 0) {
                                // Prüfe ob es der Zabig Stich ist
                                $stich_info = $conn->query("SELECT code FROM endstich_definition WHERE id = $stich_id")->fetch_assoc();
                                $sie_und_er_value = ($stich_info && $stich_info['code'] === 'ZABIG' && $zabig_partner) ? 1 : 0;

                                $stmt->bind_param("iiisis", $mitglied_id, $jahr, $stich_id, $zahlungsmethode, $sie_und_er_value, $created_by);
                                $stmt->execute();
                            }
                        }
                    } else {
                        // Für JS (JungschützenInnen): Verwende den JS-Paketpreis
                        // Dieser wird nur einmal gesetzt (beim ersten Stich), alle anderen bekommen NULL
                        $js_preis_gesetzt = false;
                        
                        $stmt = $conn->prepare("INSERT INTO endstich_selection (gast_id, jahr, stich_id, zahlungsmethode, gast_spezialpreis, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                        foreach ($zu_erstellen as $stich_id) {
                            if ($stich_id > 0) {
                                // Entscheide ob Spezialpreis gesetzt werden soll
                                $preis_for_insert = null;
                                if ($is_js && !$js_preis_gesetzt) {
                                    // Erster Stich bei JS: Setze JS-Paketpreis
                                    $preis_for_insert = $gast_spezialpreis;
                                    $js_preis_gesetzt = true;
                                } else if (!$is_js && !$js_preis_gesetzt) {
                                    // Normaler Gast: Setze Gast-Spezialpreis einmal
                                    $preis_for_insert = $gast_spezialpreis;
                                    $js_preis_gesetzt = true;
                                }
                                // Alle weiteren Stiche: NULL (wird nicht zusätzlich berechnet)
                                
                                $stmt->bind_param("iiisis", $gast_id, $jahr, $stich_id, $zahlungsmethode, $preis_for_insert, $created_by);
                                $stmt->execute();
                            }
                        }
                    }
                }

                $conn->commit();

                // Foreign Key Checks wieder aktivieren
                $conn->query("SET FOREIGN_KEY_CHECKS = 1");

                // Speichere zusätzliche Schüsse wenn vorhanden
                if (isset($input['zusatz_schuesse']) && is_array($input['zusatz_schuesse'])) {
                    // Lösche alte Einträge
                    if ($mitglied_id) {
                        $stmt = $conn->prepare("DELETE FROM endstich_zusatz_schuss WHERE mitglied_id = ? AND jahr = ?");
                        $stmt->bind_param("ii", $mitglied_id, $jahr);
                    } else {
                        $stmt = $conn->prepare("DELETE FROM endstich_zusatz_schuss WHERE gast_id = ? AND jahr = ?");
                        $stmt->bind_param("ii", $gast_id, $jahr);
                    }
                    $stmt->execute();

                    // Füge neue ein
                    if (!empty($input['zusatz_schuesse'])) {
                        if ($mitglied_id) {
                            $stmt = $conn->prepare("INSERT INTO endstich_zusatz_schuss (mitglied_id, jahr, typ, anzahl, preis_cents, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                        } else {
                            $stmt = $conn->prepare("INSERT INTO endstich_zusatz_schuss (gast_id, jahr, typ, anzahl, preis_cents, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                        }

                        foreach ($input['zusatz_schuesse'] as $zusatz) {
                            $typ = $zusatz['typ'] ?? '';
                            $anzahl = (int) ($zusatz['anzahl'] ?? 0);
                            if ($typ && $anzahl > 0) {
                                $preis = $anzahl * 50; // 50 Rappen pro Schuss
                                // Verwende Variable statt ternären Operator für bind_param
                                $entity_id = $mitglied_id ?: $gast_id;
                                $stmt->bind_param("iisiis", $entity_id, $jahr, $typ, $anzahl, $preis, $created_by);
                                $stmt->execute();
                            }
                        }
                    }
                }

                // Erfolg zurükmelden
                $message = $gast_name ? "Gast '$gast_name' erfolgreich gespeichert" : 'Erfolgreich gespeichert';
                jsonResponse(true, ['message' => $message], $message);

            } catch (Exception $e) {
                $conn->rollback();
                // Foreign Key Checks wieder aktivieren auch bei Fehler
                $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                error_log("Error in save_selection: " . $e->getMessage());
                error_log("SQL Error: " . $conn->error);
                jsonResponse(false, null, 'Fehler beim Speichern: ' . $e->getMessage());
            }
            break;

        case 'get_stich_definitions':
            // Für Admin-Edit: Alle Stich-Definitionen
            $sql = "SELECT * FROM endstich_definition ORDER BY sort_order, name";
            $result = $conn->query($sql);

            if (!$result) {
                error_log("Query error: " . $conn->error);
                jsonResponse(false, null, 'Datenbankfehler beim Abrufen der Definitionen');
            }

            $definitions = [];
            while ($row = $result->fetch_assoc()) {
                $definitions[] = $row;
            }

            jsonResponse(true, $definitions);
            break;

        case 'update_stich_definition':
            checkCSRF();

            // Parse input
            $input = null;
            if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
            } else {
                $input = $_POST;
            }

            $id = (int) ($input['id'] ?? 0);

            // Wenn keine ID -> Neuer Eintrag
            if (!$id) {
                // Insert new stich
                $code = $conn->real_escape_string($input['code'] ?? '');
                $name = $conn->real_escape_string($input['name'] ?? '');
                $shots = (int) ($input['shots'] ?? 0);
                $price_cents = (int) ($input['price_cents'] ?? 0);
                $sort_order = (int) ($input['sort_order'] ?? 100);
                $active = isset($input['active']) ? ($input['active'] ? 1 : 0) : 1;

                if (empty($code) || empty($name)) {
                    jsonResponse(false, null, 'Code und Name sind erforderlich');
                }

                // Check if code already exists
                $stmt = $conn->prepare("SELECT id FROM endstich_definition WHERE code = ?");
                $stmt->bind_param("s", $code);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    jsonResponse(false, null, 'Ein Stich mit diesem Code existiert bereits');
                }

                $stmt = $conn->prepare("INSERT INTO endstich_definition (code, name, shots, price_cents, sort_order, active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssiiii", $code, $name, $shots, $price_cents, $sort_order, $active);

                if ($stmt->execute()) {
                    $newId = $conn->insert_id;
                    jsonResponse(true, ['id' => $newId], 'Neuer Stich erfolgreich erstellt');
                } else {
                    jsonResponse(false, null, 'Fehler beim Erstellen: ' . $conn->error);
                }
                break;
            }

            // Update existing stich
            $updates = [];
            $types = "";
            $params = [];

            if (isset($input['name'])) {
                $updates[] = 'name = ?';
                $types .= 's';
                $params[] = $input['name'];
            }
            if (isset($input['shots'])) {
                $updates[] = 'shots = ?';
                $types .= 'i';
                $params[] = (int) $input['shots'];
            }
            if (isset($input['price_cents'])) {
                $updates[] = 'price_cents = ?';
                $types .= 'i';
                $params[] = (int) $input['price_cents'];
            }
            if (isset($input['sort_order'])) {
                $updates[] = 'sort_order = ?';
                $types .= 'i';
                $params[] = (int) $input['sort_order'];
            }
            if (isset($input['active'])) {
                $updates[] = 'active = ?';
                $types .= 'i';
                $params[] = $input['active'] ? 1 : 0;
            }

            if (empty($updates)) {
                jsonResponse(false, null, 'Keine Änderungen angegeben');
            }

            $types .= 'i'; // für ID
            $params[] = $id;

            $sql = "UPDATE endstich_definition SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);

            // Dynamisches bind_param
            $bind_params = [];
            $bind_params[] = $types;
            foreach ($params as $key => $value) {
                $bind_params[] = &$params[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_params);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    jsonResponse(true, ['id' => $id], 'Stich erfolgreich aktualisiert');
                } else {
                    jsonResponse(true, ['id' => $id], 'Keine Änderungen vorgenommen');
                }
            } else {
                jsonResponse(false, null, 'Fehler beim Update: ' . $conn->error);
            }
            break;

        case 'get_zusatz_schuesse':
            // Hole zusätzliche Schüsse für Mitglied/Gast/Jahr
            $mitglied_id = isset($_GET['mitglied_id']) ? (int) $_GET['mitglied_id'] : 0;
            $gast_name = isset($_GET['gast_name']) ? trim($_GET['gast_name']) : '';
            $jahr = (int) ($_GET['jahr'] ?? date('Y'));

            if (!$mitglied_id && !$gast_name) {
                jsonResponse(true, []);
            }

            if ($mitglied_id) {
                // Mitglied-basierte Suche
                $stmt = $conn->prepare("SELECT typ, anzahl, preis_cents FROM endstich_zusatz_schuss WHERE mitglied_id = ? AND jahr = ?");
                $stmt->bind_param("ii", $mitglied_id, $jahr);
            } else {
                // Gast-basierte Suche - erst Gast-ID finden
                $stmt = $conn->prepare("SELECT id FROM endstich_gaeste WHERE name = ? AND jahr = ?");
                $stmt->bind_param("si", $gast_name, $jahr);
                $stmt->execute();
                $result = $stmt->get_result();
                $gast = $result->fetch_assoc();

                if ($gast) {
                    $gast_id = $gast['id'];
                    $stmt = $conn->prepare("SELECT typ, anzahl, preis_cents FROM endstich_zusatz_schuss WHERE gast_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $gast_id, $jahr);
                } else {
                    jsonResponse(true, []); // Gast noch nicht erfasst
                }
            }

            $stmt->execute();
            $result = $stmt->get_result();

            $zusatz = [];
            while ($row = $result->fetch_assoc()) {
                $zusatz[] = $row;
            }

            jsonResponse(true, $zusatz);
            break;

        case 'get_year_details':
            // Detaillierte Übersicht mit einzelnen Stich-IDs für Matrix-Darstellung
            $jahr = (int) ($_GET['jahr'] ?? date('Y'));
            $debug = isset($_GET['debug']) ? (int)$_GET['debug'] : 0;

            // Hole alle Mitglieder und Gäste die entweder Stiche ODER Munition haben
            // Mit expliziter Collation um Fehler zu vermeiden
            // Sortierung: Erst Mitglieder (1), dann Gäste (2), dann JS (3), jeweils alphabetisch
            // Inkl. Waffentyp für Mitglieder (m.WaffenID) und Gäste (g.waffen_id) via LEFT JOIN Waffen
            $sql = "SELECT 
                'mitglied' COLLATE utf8mb4_general_ci as typ,
                m.ID as entity_id,
                CONCAT(m.Name, ' ', m.Vorname) COLLATE utf8mb4_general_ci as name,
                NULL as geburtsdatum,
                m.WaffenID as waffe_id,
                w.Bezeichnung as waffe_bez,
                w.Kategorie as waffe_kat,
                1 as sort_group
            FROM mitglieder m
            LEFT JOIN Waffen w ON w.ID = m.WaffenID
            WHERE m.ID IN (
                SELECT DISTINCT mitglied_id FROM endstich_selection WHERE jahr = ? AND mitglied_id IS NOT NULL
                UNION
                SELECT DISTINCT mitglied_id FROM endstich_zusatz_schuss WHERE jahr = ? AND mitglied_id IS NOT NULL
            )
            UNION
            SELECT 
                'gast' COLLATE utf8mb4_general_ci as typ,
                g.id as entity_id,
                g.name COLLATE utf8mb4_general_ci as name,
                g.geburtsdatum,
                g.waffen_id as waffe_id,
                w2.Bezeichnung as waffe_bez,
                w2.Kategorie as waffe_kat,
                CASE WHEN g.geburtsdatum IS NOT NULL THEN 3 ELSE 2 END as sort_group
            FROM endstich_gaeste g
            LEFT JOIN Waffen w2 ON w2.ID = g.waffen_id
            WHERE g.jahr = ?
              AND g.id IN (
                SELECT DISTINCT gast_id FROM endstich_selection WHERE jahr = ? AND gast_id IS NOT NULL
                UNION
                SELECT DISTINCT gast_id FROM endstich_zusatz_schuss WHERE jahr = ? AND gast_id IS NOT NULL
            )
            ORDER BY sort_group, name";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiiii", $jahr, $jahr, $jahr, $jahr, $jahr);
            $stmt->execute();
            $result = $stmt->get_result();

            $details = [];

            while ($row = $result->fetch_assoc()) {
                // sichere Defaults, falls keine Waffe hinterlegt ist
                $row['waffe_id'] = isset($row['waffe_id']) ? (int)$row['waffe_id'] : null;
                $row['waffe_bez'] = $row['waffe_bez'] ?? null;
                $row['waffe_kat'] = $row['waffe_kat'] ?? null;

                $row['stiche'] = [];
                $row['zusatz_schuesse'] = [];
                $row['total_shots'] = 0;
                $row['total_price'] = 0;
                $row['zahlungsmethode'] = 'bar'; // Default

                // kompatible ID
                $row['mitglied_id'] = $row['entity_id'];

                // NEU: Munition Felder
                $row['munition_schuss'] = 0;   // Summe Zusatzschüsse
                $row['munition_preis']  = 0;   // Summe Zusatzpreis (Cents)

                // NEU: Split nach Munitionsart
                $row['stich_gp11']  = 0;      // Schüsse aus gelösten Stichen
                $row['stich_gp90']  = 0;
                $row['zusatz_gp11'] = 0;      // Zusatzschüsse
                $row['zusatz_gp90'] = 0;

                $details[] = $row;
            }

            // Hole die Stiche und Munition für alle Entities
            foreach ($details as &$entity) {
                // Robustere Ableitung der Munitionsart aus Waffe
$katbez = ($entity['waffe_kat'] ?? '') . ' ' . ($entity['waffe_bez'] ?? '');
$katbez_lc = function_exists('mb_strtolower')
    ? mb_strtolower(trim(preg_replace('/\s+/', ' ', $katbez)), 'UTF-8')
    : strtolower(trim(preg_replace('/\s+/', ' ', $katbez)));

$ammoPref = null;

/**
 * 1) Feste Zuordnung per Waffen-ID (empfohlen, stabil)
 *    -> Passe die IDs an eure Waffen-Tabelle an.
 *       In deinem JSON:
 *         id=1  => "Standardgewehr"  => GP11
 *         id=2  => "Stgw90"          => GP90
 */
$waffenMap = [
    1 => 'GP11', // Standardgewehr 300m -> GP11
    2 => 'GP90', // Stgw90 -> GP90
    // ggf. weitere IDs ergänzen …
];

if (!empty($entity['waffe_id']) && isset($waffenMap[(int)$entity['waffe_id']])) {
    $ammoPref = $waffenMap[(int)$entity['waffe_id']];
}

/**
 * 2) Heuristik über Bezeichnung, falls IDs mal nicht passen
 *    (z.B. wenn „Standardgewehr“ anderswo auftaucht)
 */
if ($ammoPref === null && preg_match('/\bstandardgewehr\b|\bstdg\b/i', (string)($entity['waffe_bez'] ?? ''))) {
    $ammoPref = 'GP11';
}

/**
 * 3) Regex-Fallbacks über Kat./Bez. (Stgw57/K31/K11/G11 etc.)
 *    Nur ausführen, wenn noch nichts gemappt wurde.
 */
if ($ammoPref === null) {
    // GP90: Stgw90 / PE90 / (S)G 550 / SIG 550 / GP 90 / 5.56 / .223
    if (preg_match('/\b(stgw|stg)\s*90\b|\bpe\s*90\b|\b(sg|sig)\s*550\b|\bgp\s*90\b|\b5\.56\b|\b\.223\b|\b223\b/', $katbez_lc)) {
        $ammoPref = 'GP90';
    }
    // GP11: Stgw57 / K31 / K11 / G11 / Mousqueton / Ordon(n)anz / GP 11 / 7.5 x 55
    elseif (preg_match('/\b(stgw|stg)\s*57\b|\bk[\s-]?31\b|\bkarabiner\s*31\b|\bk[\s-]?11\b|\bg[\s-]?11\b|\bmousqueton\b|\bordonn?anz\b|\bgp\s*11\b|\b7[,\.\s]*5\s*x\s*55\b/', $katbez_lc)) {
        $ammoPref = 'GP11';
    }
}

// Debug-Felder (nur wenn ?debug=1)
if (!empty($debug)) {
    $entity['__debug_katbez']   = $katbez;
    $entity['__debug_ammoPref'] = $ammoPref;
    if ($ammoPref === null) {
        error_log('[ENDSCH] ammoPref ungeklärt: entity_id=' . $entity['entity_id'] . ' katbez="' . $katbez . '"');
    }
}

                if ($entity['typ'] === 'mitglied') {
                    // Stiche für Mitglied - berücksichtige auch alte Daten ohne gast_id
                    // Prüfe zuerst ob zahlungsmethode und sie_und_er Spalten existieren
                    $col_check_zm = $conn->query("SHOW COLUMNS FROM endstich_selection LIKE 'zahlungsmethode'");
                    $col_check_sue = $conn->query("SHOW COLUMNS FROM endstich_selection LIKE 'sie_und_er'");

                    if ($col_check_zm->num_rows > 0 && $col_check_sue->num_rows > 0) {
                        $sql = "SELECT 
                            es.stich_id,
                            es.zahlungsmethode,
                            es.sie_und_er,
                            ed.shots,
                            ed.price_cents,
                            ed.code
                        FROM endstich_selection es
                        JOIN endstich_definition ed ON es.stich_id = ed.id
                        WHERE es.mitglied_id = ? AND es.jahr = ?";
                    } else if ($col_check_zm->num_rows > 0) {
                        $sql = "SELECT 
                            es.stich_id,
                            es.zahlungsmethode,
                            0 as sie_und_er,
                            ed.shots,
                            ed.price_cents,
                            ed.code
                        FROM endstich_selection es
                        JOIN endstich_definition ed ON es.stich_id = ed.id
                        WHERE es.mitglied_id = ? AND es.jahr = ?";
                    } else {
                        $sql = "SELECT 
                            es.stich_id,
                            NULL as zahlungsmethode,
                            0 as sie_und_er,
                            ed.shots,
                            ed.price_cents,
                            ed.code
                        FROM endstich_selection es
                        JOIN endstich_definition ed ON es.stich_id = ed.id
                        WHERE es.mitglied_id = ? AND es.jahr = ?";
                    }

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $entity['entity_id'], $jahr);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    while ($row = $result->fetch_assoc()) {
                        // Probeschüsse: Nur bei JS mitzählen, sonst ignorieren
                        // (Bei Mitgliedern gibt es keine JS, daher immer ignorieren)
                        if (strcasecmp($row['code'] ?? '', 'PROBE') === 0) {
                            continue;
                        }

                        $entity['stiche'][] = (int)$row['stich_id'];
                        $entity['total_shots'] += (int)$row['shots'];

                        // Schüsse aus gelösten Stichen nach Ammo der Waffe zuordnen
                        if ($ammoPref === 'GP11') {
                            $entity['stich_gp11'] += (int)$row['shots'];
                        } elseif ($ammoPref === 'GP90') {
                            $entity['stich_gp90'] += (int)$row['shots'];
                        }

                        // Preis (Zabig Partnerpreis berücksichtigen)
                        $preis = (int)$row['price_cents'];
                        if (($row['code'] ?? '') === 'ZABIG' && (int)($row['sie_und_er'] ?? 0) === 1) {
                            $preis = 1000; // CHF 10.00
                        }
                        $entity['total_price'] += $preis;

                        // Markiere Partner-Stich
                        if (($row['code'] ?? '') === 'ZABIG' && (int)($row['sie_und_er'] ?? 0) === 1) {
                            if (!isset($entity['partner_stiche'])) $entity['partner_stiche'] = [];
                            $entity['partner_stiche'][] = (int)$row['stich_id'];
                        }

                        // Zahlungsmethode ggf. überschreiben
                        if (!empty($row['zahlungsmethode'])) {
                            $entity['zahlungsmethode'] = $row['zahlungsmethode'];
                        }
                    }

                    // Zusätzliche Schüsse für Mitglied
                    $sql = "SELECT typ, anzahl, preis_cents 
                            FROM endstich_zusatz_schuss 
                            WHERE mitglied_id = ? AND jahr = ?";

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $entity['entity_id'], $jahr);

                } else {
                    // Stiche für Gast
                    // Prüfe zuerst ob zahlungsmethode und gast_spezialpreis Spalten existieren
                    $col_check_zm = $conn->query("SHOW COLUMNS FROM endstich_selection LIKE 'zahlungsmethode'");
                    $col_check_gsp = $conn->query("SHOW COLUMNS FROM endstich_selection LIKE 'gast_spezialpreis'");

                    if ($col_check_zm->num_rows > 0 && $col_check_gsp->num_rows > 0) {
                        $sql = "SELECT 
                            es.stich_id,
                            es.zahlungsmethode,
                            es.gast_spezialpreis,
                            ed.shots,
                            ed.price_cents,
                            ed.code
                        FROM endstich_selection es
                        JOIN endstich_definition ed ON es.stich_id = ed.id
                        WHERE es.gast_id = ? AND es.jahr = ?";
                    } else if ($col_check_zm->num_rows > 0) {
                        $sql = "SELECT 
                            es.stich_id,
                            es.zahlungsmethode,
                            NULL as gast_spezialpreis,
                            ed.shots,
                            ed.price_cents,
                            ed.code
                        FROM endstich_selection es
                        JOIN endstich_definition ed ON es.stich_id = ed.id
                        WHERE es.gast_id = ? AND es.jahr = ?";
                    } else {
                        $sql = "SELECT 
                            es.stich_id,
                            NULL as zahlungsmethode,
                            NULL as gast_spezialpreis,
                            ed.shots,
                            ed.price_cents,
                            ed.code
                        FROM endstich_selection es
                        JOIN endstich_definition ed ON es.stich_id = ed.id
                        WHERE es.gast_id = ? AND es.jahr = ?";
                    }

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $entity['entity_id'], $jahr);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    $gast_spezialpreis_gesetzt = false;

                    while ($row = $result->fetch_assoc()) {
                        // Probeschüsse: Bei JS mitzählen, bei normalen Gästen ignorieren
                        $isProbe = strcasecmp($row['code'] ?? '', 'PROBE') === 0;
                        $isJS = !empty($entity['geburtsdatum']);
                        
                        if ($isProbe && !$isJS) {
                            continue; // PROBE ignorieren bei normalen Gästen
                        }

                        $entity['stiche'][] = (int)$row['stich_id'];
                        $entity['total_shots'] += (int)$row['shots'];

                        // Schüsse aus gelösten Stichen nach Ammo der Waffe zuordnen
                        if ($ammoPref === 'GP11') {
                            $entity['stich_gp11'] += (int)$row['shots'];
                        } elseif ($ammoPref === 'GP90') {
                            $entity['stich_gp90'] += (int)$row['shots'];
                        }

                        // Preis (Gast-Spezialpreis priorisieren)
                        if (!$gast_spezialpreis_gesetzt && !empty($row['gast_spezialpreis'])) {
                            $entity['total_price'] = (int)$row['gast_spezialpreis'];
                            $gast_spezialpreis_gesetzt = true;
                        } else if (!$gast_spezialpreis_gesetzt) {
                            $entity['total_price'] += (int)$row['price_cents'];
                        }

                        // Zahlungsmethode ggf. überschreiben
                        if (!empty($row['zahlungsmethode'])) {
                            $entity['zahlungsmethode'] = $row['zahlungsmethode'];
                        }
                    }

                    // Zusätzliche Schüsse für Gast
                    $sql = "SELECT typ, anzahl, preis_cents 
                            FROM endstich_zusatz_schuss 
                            WHERE gast_id = ? AND jahr = ?";

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $entity['entity_id'], $jahr);
                }

                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $entity['zusatz_schuesse'][] = [
                        'typ' => $row['typ'],
                        'anzahl' => (int)$row['anzahl'],
                        'preis_cents' => (int)$row['preis_cents']
                    ];

                    // Debug: Roh-Typen sammeln
                    if ($debug) {
                        if (!isset($entity['__debug_zusatz_types'])) $entity['__debug_zusatz_types'] = [];
                        $entity['__debug_zusatz_types'][] = $row['typ'];
                    }

                    // Zusatzschüsse nach Munitionsart splitten (Typ tolerant normalisieren)
                    $typNorm = strtoupper(str_replace(['-', '_', ' '], '', (string)$row['typ']));
                    if (strpos($typNorm, 'GP11') !== false) {
                        $entity['zusatz_gp11'] += (int)$row['anzahl'];
                    } elseif (strpos($typNorm, 'GP90') !== false) {
                        $entity['zusatz_gp90'] += (int)$row['anzahl'];
                    }

                    // Summen
                    $entity['munition_schuss'] += (int)$row['anzahl'];
                    $entity['munition_preis']  += (int)$row['preis_cents'];
                    $entity['total_price']     += (int)$row['preis_cents'];
                }
            }

            jsonResponse(true, $details);
            break;

        case 'delete_selection':
            checkCSRF();

            // Parse JSON body wenn Content-Type application/json
            $input = null;
            if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);
            } else {
                $input = $_POST;
            }

            $entity_id = isset($input['entity_id']) ? (int) $input['entity_id'] : 0;
            $typ = isset($input['typ']) ? $input['typ'] : '';
            $jahr = (int) ($input['jahr'] ?? date('Y'));

            if (!$entity_id || !$typ) {
                jsonResponse(false, null, 'Fehlende Parameter');
            }

            $conn->begin_transaction();

            try {
                if ($typ === 'mitglied') {
                    // Lösche Stiche für Mitglied
                    $stmt = $conn->prepare("DELETE FROM endstich_selection WHERE mitglied_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $entity_id, $jahr);
                    $stmt->execute();

                    // Lösche Zusatz-Schüsse für Mitglied
                    $stmt = $conn->prepare("DELETE FROM endstich_zusatz_schuss WHERE mitglied_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $entity_id, $jahr);
                    $stmt->execute();

                } else if ($typ === 'gast') {
                    // Lösche Stiche für Gast
                    $stmt = $conn->prepare("DELETE FROM endstich_selection WHERE gast_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $entity_id, $jahr);
                    $stmt->execute();

                    // Lösche Zusatz-Schüsse für Gast
                    $stmt = $conn->prepare("DELETE FROM endstich_zusatz_schuss WHERE gast_id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $entity_id, $jahr);
                    $stmt->execute();

                    // Lösche auch den Gast selbst aus der endstich_gaeste Tabelle
                    $stmt = $conn->prepare("DELETE FROM endstich_gaeste WHERE id = ? AND jahr = ?");
                    $stmt->bind_param("ii", $entity_id, $jahr);
                    $stmt->execute();
                }

                $conn->commit();
                jsonResponse(true, null, 'Erfolgreich gelöscht');

            } catch (Exception $e) {
                $conn->rollback();
                error_log("Error in delete_selection: " . $e->getMessage());
                jsonResponse(false, null, 'Fehler beim Löschen: ' . $e->getMessage());
            }
            break;

        default:
            jsonResponse(false, null, 'Unbekannte Action: ' . $action);
    }

} catch (Exception $e) {
    error_log("API Error in endschloesen_api.php: " . $e->getMessage());
    jsonResponse(false, null, 'Systemfehler: ' . $e->getMessage());
}