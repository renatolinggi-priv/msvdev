<?php
// save_regel.php
session_start();
require_once '../dbconnect.inc.php';
require_once 'regel_builder.inc.php'; // wp_regel_typen(), wp_build_regel_sql()
require_once __DIR__ . '/../csrf.inc.php';

// Datenbankverbindung herstellen
$conn = get_db_connection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// CSRF Token prüfen
csrf_require(true);

header('Content-Type: application/json; charset=utf-8');

try {
    // Daten validieren
    $regel_code = trim($_POST['regel_code'] ?? '');
    $regel_name = trim($_POST['regel_name'] ?? '');
    $regel_beschreibung = trim($_POST['regel_beschreibung'] ?? '');
    $aktiv = isset($_POST['aktiv']) ? 1 : 0;

    // Regel-Typ: 'custom' (rohes SQL, Experten-Modus) oder 'einzelwettbewerb' (gefuehrt)
    $regel_typ = trim($_POST['regel_typ'] ?? 'custom');
    if (!in_array($regel_typ, wp_regel_typen(), true)) $regel_typ = 'custom';

    $regel_params = null;
    if ($regel_typ === 'custom') {
        // Experten-Modus: SQL kommt direkt aus dem Formular
        $sql_query = trim($_POST['sql_query'] ?? '');
    } else {
        // Gefuehrt (einzelwettbewerb/baukasten): SQL serverseitig aus geprueften
        // Vorlagen generieren. Gepostetes sql_query wird bewusst ignoriert.
        $params = wp_params_from_post($_POST);
        try {
            $sql_query = wp_build_regel_sql($regel_typ, $params);
        } catch (InvalidArgumentException $e) {
            echo json_encode(['success' => false, 'message' => 'Builder-Fehler: ' . $e->getMessage()]);
            exit;
        }
        $regel_params = json_encode($params, JSON_UNESCAPED_UNICODE);
    }

    if ($regel_code === '' || $regel_name === '' || $sql_query === '') {
        echo json_encode(['success' => false, 'message' => 'Code, Name und SQL dürfen nicht leer sein']);
        exit;
    }

    // Builder-Spalten nur verwenden, wenn Migration 027 schon lief (sonst graceful degradation)
    $hasBuilder = wp_regeln_has_builder_columns($conn);

    // Prüfen ob wir eine bestehende Regel bearbeiten
    if (isset($_POST['id']) && !empty($_POST['id'])) {
        // Regel aktualisieren
        $id = intval($_POST['id']);
        
        // Prüfen ob regel_code bereits existiert (außer bei der aktuellen Regel)
        $check_sql = "SELECT id FROM wanderpreise_regeln WHERE regel_code = ? AND id != ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $regel_code, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Eine Regel mit diesem Code existiert bereits']);
            exit;
        }
        
        // Regel aktualisieren
        if ($hasBuilder) {
            $update_sql = "UPDATE wanderpreise_regeln SET regel_code = ?, regel_name = ?, regel_beschreibung = ?, regel_typ = ?, sql_query = ?, regel_params = ?, aktiv = ? WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ssssssii", $regel_code, $regel_name, $regel_beschreibung, $regel_typ, $sql_query, $regel_params, $aktiv, $id);
        } else {
            $update_sql = "UPDATE wanderpreise_regeln SET regel_code = ?, regel_name = ?, regel_beschreibung = ?, sql_query = ?, aktiv = ? WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ssssii", $regel_code, $regel_name, $regel_beschreibung, $sql_query, $aktiv, $id);
        }

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Regel erfolgreich aktualisiert',
                'regel_id' => $id
            ]);
        } else {
            throw new Exception('Fehler beim Aktualisieren der Regel');
        }
    } else {
        // Neue Regel einfügen
        
        // Prüfen ob regel_code bereits existiert
        $check_sql = "SELECT id FROM wanderpreise_regeln WHERE regel_code = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $regel_code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Eine Regel mit diesem Code existiert bereits']);
            exit;
        }
        
        // Regel einfügen
        if ($hasBuilder) {
            $insert_sql = "INSERT INTO wanderpreise_regeln (regel_code, regel_name, regel_beschreibung, regel_typ, sql_query, regel_params, aktiv)
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("ssssssi", $regel_code, $regel_name, $regel_beschreibung, $regel_typ, $sql_query, $regel_params, $aktiv);
        } else {
            $insert_sql = "INSERT INTO wanderpreise_regeln (regel_code, regel_name, regel_beschreibung, sql_query, aktiv)
                           VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("ssssi", $regel_code, $regel_name, $regel_beschreibung, $sql_query, $aktiv);
        }

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Regel erfolgreich gespeichert',
                'regel_id' => $conn->insert_id
            ]);
        } else {
            throw new Exception('Fehler beim Speichern der Regel');
        }
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Fehler: ' . $e->getMessage()
    ]);
}

$conn->close();
?>