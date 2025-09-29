<?php
/**
 * Load Heimresultate Form
 * Lädt die Heimresultate für das ausgewählte Jahr mit Sicherheitsverbesserungen
 *
 * Security Features:
 * - SQL Injection Prevention mit prepared statements
 * - XSS Protection mit htmlspecialchars
 * - Input validation
 * - Error handling
 */

include '../config.php';

// Error Reporting für Development (in Production auskommentieren)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Sicherheitsfunktionen
 */
function sanitizeOutput($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function logError($message, $context = []) {
    error_log(date('Y-m-d H:i:s') . " [HEIMRESULTATE_LOAD] " . $message . " Context: " . json_encode($context));
}

/**
 * Generiert eine sichere Tabellenzeile für ein Mitglied
 */
function generateMemberRow($mitglied, $resultate, $year) {
    $html = '<tr>';
    
    // Name mit Security-Scaping
    $vorname = sanitizeOutput($mitglied['Vorname']);
    $name = sanitizeOutput($mitglied['Name']);
    $html .= '<td class="text-start fw-semibold">';
    $html .= $name . ' ' . $vorname;
    $html .= '</td>';

    // Resultate für jede Passe (1-8)
    for ($i = 1; $i <= 8; $i++) {
        $passe = isset($resultate["Passe$i"]) ? sanitizeOutput($resultate["Passe$i"]) : '';
        $html .= '<td>';
        $html .= '<input type="text" ';
        $html .= 'class="form-control form-control-sm small-input text-center" ';
        $html .= 'name="passe[' . (int)$mitglied['ID'] . '][' . $i . ']" ';
        $html .= 'value="' . $passe . '" ';
        $html .= 'autocomplete="off" ';
        $html .= 'maxlength="2" ';
        $html .= 'pattern="[0-9]*" ';
        $html .= 'title="Nur Zahlen erlaubt">';
        $html .= '</td>';
    }
    
    $html .= '</tr>';
    return $html;
}

try {
    // Datenbankverbindung prüfen
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Jahr validieren und sanitieren
    $year = isset($_GET['year']) ? filter_var($_GET['year'], FILTER_VALIDATE_INT) : date('Y');
    
    if ($year === false || $year < 2020 || $year > date('Y') + 5) {
        throw new Exception("Invalid year parameter");
    }

    // Mitglieder mit prepared statement laden
    $mitgliederSql = "SELECT ID, Name, Vorname FROM mitglieder ORDER BY Name ASC, Vorname ASC";
    $mitgliederStmt = $conn->prepare($mitgliederSql);
    
    if (!$mitgliederStmt) {
        throw new Exception("Failed to prepare members query: " . $conn->error);
    }
    
    if (!$mitgliederStmt->execute()) {
        throw new Exception("Failed to execute members query: " . $mitgliederStmt->error);
    }
    
    $mitgliederResult = $mitgliederStmt->get_result();
    
    if (!$mitgliederResult) {
        throw new Exception("Failed to get members result: " . $conn->error);
    }

    // Alle Heimresultate für das Jahr in einem Query laden (Performance-Optimierung)
    $heimresultateSql = "SELECT MitgliedID, Passe1, Passe2, Passe3, Passe4, Passe5, Passe6, Passe7, Passe8
                          FROM heimresultate
                          WHERE Jahr = ?";
    $heimresultateStmt = $conn->prepare($heimresultateSql);
    
    if (!$heimresultateStmt) {
        throw new Exception("Failed to prepare heimresultate query: " . $conn->error);
    }
    
    $heimresultateStmt->bind_param("i", $year);
    
    if (!$heimresultateStmt->execute()) {
        throw new Exception("Failed to execute heimresultate query: " . $heimresultateStmt->error);
    }
    
    $heimresultateResult = $heimresultateStmt->get_result();
    
    // Resultate in Array für schnellen Zugriff speichern
    $resultateArray = [];
    while ($row = $heimresultateResult->fetch_assoc()) {
        $resultateArray[$row['MitgliedID']] = $row;
    }

    // Tabellenzeilen generieren
    $output = '';
    $memberCount = 0;
    
    while ($mitglied = $mitgliederResult->fetch_assoc()) {
        $mitgliedID = (int)$mitglied['ID'];
        $resultate = isset($resultateArray[$mitgliedID]) ? $resultateArray[$mitgliedID] : [];
        
        $output .= generateMemberRow($mitglied, $resultate, $year);
        $memberCount++;
    }
    
    // Falls keine Mitglieder gefunden
    if ($memberCount === 0) {
        $output = '<tr><td colspan="9" class="text-center text-muted py-4">';
        $output .= '<i class="bi bi-info-circle me-2"></i>';
        $output .= 'Keine Mitglieder gefunden';
        $output .= '</td></tr>';
        logError("No members found");
    }
    
    echo $output;
    
    // Statements schließen
    $mitgliederStmt->close();
    $heimresultateStmt->close();
    
    // Erfolg loggen
    logError("Successfully loaded heimresultate form", [
        'year' => $year,
        'member_count' => $memberCount
    ]);

} catch (Exception $e) {
    // Fehler loggen
    logError("Error in load_heimresultate_form: " . $e->getMessage(), [
        'year' => isset($year) ? $year : 'undefined',
        'file' => __FILE__,
        'line' => $e->getLine()
    ]);
    
    // Benutzerfreundliche Fehlermeldung ausgeben
    echo '<tr><td colspan="9" class="text-center text-danger py-4">';
    echo '<i class="bi bi-exclamation-triangle me-2"></i>';
    echo 'Fehler beim Laden der Daten. Bitte versuchen Sie es erneut.';
    echo '</td></tr>';
    
} finally {
    // Datenbankverbindung schließen
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>
