<?php
require_once __DIR__ . '/../debug_log.inc.php';
/**
 * Load Kantiresultate Form
 * Lädt die Kantiresultate für das ausgewählte Jahr mit Sicherheitsverbesserungen
 *
 * Security Features:
 * - SQL Injection Prevention mit prepared statements
 * - XSS Protection mit htmlspecialchars
 * - Input validation
 * - Error handling
 */

include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('html'); // Zugriff nur Admin-Bereich (admin/vorstand)

// Fehler ins Log, nie in die Ausgabe (das Fragment landet direkt in der Tabelle)
ini_set('display_errors', '0');
error_reporting(E_ALL);

/**
 * Sicherheitsfunktionen
 */
function sanitizeOutput($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function logError($message, $context = []) {
    error_log(date('Y-m-d H:i:s') . " [KANTIRESULTATE_LOAD] " . $message . " Context: " . json_encode($context));
}

/**
 * Generiert eine sichere Tabellenzeile für ein Mitglied
 */
function generateMemberRow($mitglied, $resultate, $year) {
    // data-kat (A/B aus der Waffe): die Seite rechnet daraus den Rang je Kategorie
    $kat = preg_match('/Kat\.\s*([AB])/', (string)($mitglied['Kategorie'] ?? ''), $km) ? $km[1] : '';
    $html = '<tr data-mid="' . (int)$mitglied['ID'] . '" data-kat="' . $kat . '">';

    // Serverseitig Summe, Beste und Filled berechnen
    $sum = 0;
    $best = 0;
    $bestIdx = -1;
    $filled = 0;
    $values = [];
    for ($i = 1; $i <= 5; $i++) {
        $v = isset($resultate["Passe$i"]) ? (int)$resultate["Passe$i"] : 0;
        $values[$i] = $v;
        if (isset($resultate["Passe$i"]) && $resultate["Passe$i"] !== '' && $resultate["Passe$i"] != 0) {
            $filled++;
        }
        $sum += $v;
        if ($v > $best) { $best = $v; $bestIdx = $i; }
    }

    // Name + Anzahl erfasster Passen (die Seite führt beides beim Tippen nach). Der Name ist ein Knopf: öffnet die Schnellerfassung.
    // Nicht mehr aktive Mitglieder stehen nur hier, weil sie in diesem Jahr Resultate haben (die Rangliste zählt sie mit).
    $vorname = sanitizeOutput($mitglied['Vorname']);
    $name = sanitizeOutput($mitglied['Name']);
    $status = !empty($mitglied['Verstorben']) ? 'verstorben' : ((int)($mitglied['Status'] ?? 1) !== 1 ? 'inaktiv' : '');
    $html .= '<td><div class="hk-zelle"><span class="hk-rang"></span><button type="button" class="hk-name" aria-haspopup="dialog">' . $name . ' ' . $vorname . '</button>'
           . ($status ? '<span class="hk-status" data-tooltip="In den Stammdaten ' . $status . '; steht hier, weil in diesem Jahr Resultate erfasst sind">' . $status . '</span>' : '')
           . '<span class="hk-anzahl' . ($filled === 5 ? ' voll' : '') . '">' . ($filled > 0 ? $filled . '/5' : '') . '</span></div></td>';

    // Resultate für jede Passe (1-5)
    for ($i = 1; $i <= 5; $i++) {
        $passe = isset($resultate["Passe$i"]) ? sanitizeOutput($resultate["Passe$i"]) : '';
        $isBest = ($i === $bestIdx && $best > 0);
        $classes = 'form-control form-control-sm small-input text-center';
        if ($passe !== '' && $passe !== '0') $classes .= ' filled';
        if ($isBest) $classes .= ' best-passe';
        $html .= '<td>';
        $html .= '<input type="text" ';
        $html .= 'class="' . $classes . '" ';
        $html .= 'name="passe[' . (int)$mitglied['ID'] . '][' . $i . ']" ';
        $html .= 'value="' . $passe . '" ';
        $html .= 'autocomplete="off" ';
        $html .= 'maxlength="3" ';
        $html .= 'data-max="100" ';
        $html .= 'aria-label="' . $name . ' ' . $vorname . ', Passe ' . $i . '" ';
        $html .= 'pattern="[0-9]*" ';
        $html .= '>';
        $html .= '</td>';
    }

    // Summe-Spalte (serverseitig vorberechnet)
    $sumText = $sum > 0 ? $sum : '&ndash;';
    $sumClass = $sum > 0 ? 'sum-cell' : 'sum-cell empty';
    $html .= '<td><span class="' . $sumClass . '">' . $sumText . '</span></td>';

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
    
    if ($year === false || $year < 2000 || $year > date('Y') + 5) {
        throw new Exception("Invalid year parameter");
    }

    // Aktive Mitglieder plus alle, die im gewählten Jahr ein Resultat über 0 haben (inaktiv, verstorben):
    // die PDF-Rangliste zählt sie mit (gleiche Regel wie kantirang/generate_pdf.php), also müssen sie auch hier
    // stehen und korrigierbar sein.
    $mitgliederSql = "SELECT m.ID, m.Name, m.Vorname, m.Status, m.Verstorben, w.Kategorie FROM mitglieder m
                      LEFT JOIN Waffen w ON w.ID = m.WaffenID
                      WHERE (m.Status = 1 AND m.Verstorben = 0)
                         OR m.ID IN (SELECT MitgliedID FROM kantiresultate WHERE Jahr = ?
                                     AND (Passe1 > 0 OR Passe2 > 0 OR Passe3 > 0 OR Passe4 > 0 OR Passe5 > 0))
                      ORDER BY m.Name ASC, m.Vorname ASC";
    $mitgliederStmt = $conn->prepare($mitgliederSql);

    if (!$mitgliederStmt) {
        throw new Exception("Failed to prepare members query: " . $conn->error);
    }
    $mitgliederStmt->bind_param("i", $year);
    
    if (!$mitgliederStmt->execute()) {
        throw new Exception("Failed to execute members query: " . $mitgliederStmt->error);
    }
    
    $mitgliederResult = $mitgliederStmt->get_result();
    
    if (!$mitgliederResult) {
        throw new Exception("Failed to get members result: " . $conn->error);
    }

    // Alle Kantiresultate für das Jahr in einem Query laden (Performance-Optimierung)
    $kantiresultateSql = "SELECT MitgliedID, Passe1, Passe2, Passe3, Passe4, Passe5 
                          FROM kantiresultate 
                          WHERE Jahr = ?";
    $kantiresultateStmt = $conn->prepare($kantiresultateSql);
    
    if (!$kantiresultateStmt) {
        throw new Exception("Failed to prepare kantiresultate query: " . $conn->error);
    }
    
    $kantiresultateStmt->bind_param("i", $year);
    
    if (!$kantiresultateStmt->execute()) {
        throw new Exception("Failed to execute kantiresultate query: " . $kantiresultateStmt->error);
    }
    
    $kantiresultateResult = $kantiresultateStmt->get_result();
    
    // Resultate in Array für schnellen Zugriff speichern
    $resultateArray = [];
    while ($row = $kantiresultateResult->fetch_assoc()) {
        $resultateArray[$row['MitgliedID']] = $row;
    }

    // Mitglieder in zwei Gruppen aufteilen: mit / ohne erfasste Resultate.
    // Beide Gruppen bleiben alphabetisch (Query sortiert bereits nach Name/Vorname).
    $withResults = [];
    $withoutResults = [];
    $memberCount = 0;

    while ($mitglied = $mitgliederResult->fetch_assoc()) {
        $mitgliedID = (int)$mitglied['ID'];
        $resultate = isset($resultateArray[$mitgliedID]) ? $resultateArray[$mitgliedID] : [];

        $hasResult = false;
        for ($i = 1; $i <= 5; $i++) {
            if (isset($resultate["Passe$i"]) && $resultate["Passe$i"] !== '' && (int)$resultate["Passe$i"] != 0) {
                $hasResult = true;
                break;
            }
        }

        $entry = ['mitglied' => $mitglied, 'resultate' => $resultate];
        if ($hasResult) {
            $withResults[] = $entry;
        } else {
            $withoutResults[] = $entry;
        }
        $memberCount++;
    }

    // Gruppen-Trennzeile (colspan = Name + 5 Passen + Total = 7)
    $groupHeader = function ($label, $icon) {
        return '<tr class="group-header"><td colspan="7" class="group-header-cell">' . $label . '</td></tr>';
    };

    // Tabellenzeilen generieren
    $output = '';

    if (count($withResults) > 0) {
        $output .= $groupHeader('Mit Resultaten (' . count($withResults) . ')', 'bi-check-circle');
        foreach ($withResults as $entry) {
            $output .= generateMemberRow($entry['mitglied'], $entry['resultate'], $year);
        }
    }

    if (count($withoutResults) > 0) {
        $output .= $groupHeader('Noch keine Resultate (' . count($withoutResults) . ')', 'bi-dash-circle');
        foreach ($withoutResults as $entry) {
            $output .= generateMemberRow($entry['mitglied'], $entry['resultate'], $year);
        }
    }

    // Falls keine Mitglieder gefunden
    if ($memberCount === 0) {
        $output = '<tr class="ui-leer"><td colspan="7">Keine aktiven Mitglieder gefunden</td></tr>';
        logError("No members found");
    }
    
    echo $output;
    
    // Statements schliessen
    $mitgliederStmt->close();
    $kantiresultateStmt->close();
    
    // Erfolg loggen
    msv_debug_log_ctx('kantiresultate', "Successfully loaded kantiresultate form", [
        'year' => $year,
        'member_count' => $memberCount
    ]);

} catch (Exception $e) {
    // Fehler loggen
    logError("Error in load_kantiresultate_form: " . $e->getMessage(), [
        'year' => isset($year) ? $year : 'undefined',
        'file' => __FILE__,
        'line' => $e->getLine()
    ]);
    
    // Benutzerfreundliche Fehlermeldung ausgeben
    echo '<tr class="ui-leer"><td colspan="7" class="text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Laden der Daten. Bitte die Seite neu laden.</td></tr>';
    
} finally {
    // Datenbankverbindung schliessen
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>
