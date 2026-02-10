<?php
/**
 * load_endschresultate_improved.php
 * Improved version with security fixes, better structure, and maintainability
 * 
 * @author System Improvement
 * @version 2.0
 * @description Loads and displays shooting competition results with improved security and structure
 */

// Move function definitions to the top for better organization
/**
 * Calculate points based on shooting score using defined thresholds
 * 
 * @param int|float $value The shooting score
 * @return int Points awarded (0-10)
 */
function calculatePoints($value) {
    // Define scoring thresholds as constants for better maintainability
    $thresholds = [
        91 => 10, 81 => 9, 71 => 8, 61 => 7, 51 => 6,
        41 => 5, 31 => 4, 21 => 3, 11 => 2, 1 => 1
    ];
    
    foreach ($thresholds as $threshold => $points) {
        if ($value >= $threshold) {
            return $points;
        }
    }
    return 0;
}

/**
 * Validate year input to prevent invalid values
 * 
 * @param mixed $year Year to validate
 * @return bool True if valid, false otherwise
 */
function validateYear($year) {
    $currentYear = date('Y');
    $minYear = 2000; // Adjust based on your data range
    $maxYear = $currentYear + 1;
    
    return is_numeric($year) && $year >= $minYear && $year <= $maxYear;
}

/**
 * Generate HTML for a result row with existing data
 *
 * @param array $row Database row with member and score data
 * @return string HTML table row
 */
function generateResultRow($row) {
    $schwini_hoeher = max($row['Schwini_Summe1'], $row['Schwini_Summe2']);
    $schwini_tiefer = min($row['Schwini_Summe1'], $row['Schwini_Summe2']);
    
    // Calculate Z-result points
    $ZResult = 0;
    for ($i = 1; $i <= 6; $i++) {
        $ZResult += calculatePoints($row['ZSchuss' . $i] ?? 0);
    }
    
    // Build HTML with proper escaping
    $html = "<tr>";
    $html .= "<td><a href='#' class='edit-btn' data-id='" . htmlspecialchars($row['ID'], ENT_QUOTES, 'UTF-8') . "'>" .
             htmlspecialchars($row['Name'] . " " . $row['Vorname'], ENT_QUOTES, 'UTF-8') . "</a></td>";
    $html .= "<td class='text-center'>" . htmlspecialchars($row['Endstich_Summe'], ENT_QUOTES, 'UTF-8') . "</td>";
    $html .= "<td class='text-center'>" . htmlspecialchars($schwini_hoeher . ", " . $schwini_tiefer, ENT_QUOTES, 'UTF-8') . "</td>";
    $html .= "<td class='text-center'>" . htmlspecialchars($row['Kunst_Summe'], ENT_QUOTES, 'UTF-8') . "</td>";
    $html .= "<td class='text-center'>" . htmlspecialchars($row['max_glueck'] . " (" . $row['GSchuss1'] . "," . $row['GSchuss2'] . "," . $row['GSchuss3'] . ")", ENT_QUOTES, 'UTF-8') . "</td>";
    $html .= "<td class='text-center'>" . htmlspecialchars($ZResult, ENT_QUOTES, 'UTF-8') . "</td>";
    $html .= "<td class='text-center'>" . htmlspecialchars($row['SieUndEr_Summe'] ?? '-', ENT_QUOTES, 'UTF-8') . "</td>";
    $html .= "<td class='text-center'>" . htmlspecialchars($row['Ansage'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    
    // Actions column with icon-only buttons
    $html .= "<td class='text-center'>";
    $html .= "<button class='btn btn-outline-primary btn-sm me-1 edit-btn' data-id='" . htmlspecialchars($row['ID'], ENT_QUOTES, 'UTF-8') . "' title='Bearbeiten'>";
    $html .= "<i class='bi bi-pencil'></i></button>";
    $html .= "<button class='btn btn-outline-danger btn-sm delete-btn' data-id='" . htmlspecialchars($row['ID'], ENT_QUOTES, 'UTF-8') . "' title='Löschen'>";
    $html .= "<i class='bi bi-trash'></i></button>";
    $html .= "</td>";
    $html .= "</tr>";
    
    return $html;
}

/**
 * Generate HTML for an empty input row when no data exists
 *
 * @param array $mitglied Member data
 * @return string HTML table row with input fields
 */
function generateEmptyRow($mitglied) {
    $html = "<tr>";
    $html .= "<td>" . htmlspecialchars($mitglied['Name'] . " " . $mitglied['Vorname'], ENT_QUOTES, 'UTF-8') . "</td>";
    $html .= "<td class='text-center'>-</td>";
    $html .= "<td class='text-center'>-</td>";
    $html .= "<td class='text-center'>-</td>";
    $html .= "<td class='text-center'>-</td>";
    $html .= "<td class='text-center'>-</td>";
    $html .= "<td class='text-center'>-</td>";
    $html .= "<td class='text-center'>-</td>";
    
    // Actions column with icon-only buttons
    $html .= "<td class='text-center'>";
    $html .= "<button class='btn btn-outline-primary btn-sm me-1 edit-btn' data-id='" . htmlspecialchars($mitglied['ID'], ENT_QUOTES, 'UTF-8') . "' title='Bearbeiten'>";
    $html .= "<i class='bi bi-pencil'></i></button>";
    $html .= "<button class='btn btn-outline-danger btn-sm delete-btn' data-id='" . htmlspecialchars($mitglied['ID'], ENT_QUOTES, 'UTF-8') . "' title='Löschen'>";
    $html .= "<i class='bi bi-trash'></i></button>";
    $html .= "</td>";
    $html .= "</tr>";
    
    return $html;
}

// Include database configuration
include '../config.php';

// Check database connection with proper error handling
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed. Please try again later.");
}

// Input validation and sanitization
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

if (!validateYear($year)) {
    error_log("Invalid year provided: " . ($_GET['year'] ?? 'null'));
    $year = date('Y'); // Fallback to current year
}

// Prepare SQL statement to prevent SQL injection
$sql = "
SELECT
    m.ID,
    m.Name,
    m.Vorname,
    g.MitgliedID,
    g.GSchuss1,
    g.GSchuss2,
    g.GSchuss3,
    z.ZSchuss1,
    z.ZSchuss2,
    z.ZSchuss3,
    z.ZSchuss4,
    z.ZSchuss5,
    z.ZSchuss6,
    z.Ansage,
    GREATEST(COALESCE(g.GSchuss1, 0), COALESCE(g.GSchuss2, 0), COALESCE(g.GSchuss3, 0)) AS max_glueck,
    COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) AS Endstich_Summe,
    COALESCE(SUM(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6), 0) AS Schwini_Summe1,
    COALESCE(SUM(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6), 0) AS Schwini_Summe2,
    COALESCE(ROUND(SUM(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) / 10, 1), 0) AS Kunst_Summe,
    COALESCE(ep.SieErSchuss6 + ep.SieErSchuss7 + ep.SieErSchuss8 + ep.SieErSchuss9 + ep.SieErSchuss10, 0) AS SieUndEr_Summe
FROM
    mitglieder m
LEFT JOIN endstich e ON m.ID = e.MitgliedID AND e.Jahr = ?
LEFT JOIN schwini s ON m.ID = s.MitgliedID AND s.Jahr = ?
LEFT JOIN kunst k ON m.ID = k.MitgliedID AND k.Jahr = ?
LEFT JOIN glueck g ON m.ID = g.MitgliedID AND g.Jahr = ?
LEFT JOIN zabig z ON m.ID = z.MitgliedID AND z.Jahr = ?
LEFT JOIN endresultate_partner ep ON m.ID = ep.MitgliedID AND ep.Jahr = ?
GROUP BY
    m.ID, m.Vorname, m.Name
ORDER BY
    m.Name, m.Vorname
";

try {
    // Use prepared statements for security
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // Bind parameters to prevent SQL injection
    $stmt->bind_param("iiiiii", $year, $year, $year, $year, $year, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Generate rows with data
        while ($row = $result->fetch_assoc()) {
            echo generateResultRow($row);
        }
    } else {
        // No results found - show empty input fields
        $sqlMitglieder = "SELECT ID, Name, Vorname FROM mitglieder ORDER BY Name, Vorname";
        $mitgliederStmt = $conn->prepare($sqlMitglieder);
        
        if (!$mitgliederStmt) {
            throw new Exception("Prepare failed for members query: " . $conn->error);
        }
        
        $mitgliederStmt->execute();
        $mitgliederResult = $mitgliederStmt->get_result();
        
        while ($mitglied = $mitgliederResult->fetch_assoc()) {
            echo generateEmptyRow($mitglied);
        }
        
        $mitgliederStmt->close();
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    // Log error for debugging while showing user-friendly message
    error_log("Database error in load_endschresultate.php: " . $e->getMessage());
    echo "<tr><td colspan='9'>Fehler beim Laden der Daten. Bitte versuchen Sie es später erneut.</td></tr>";
} finally {
    // Ensure connection is always closed
    $conn->close();
}
?>