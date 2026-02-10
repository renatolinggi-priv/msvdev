<?php
/**
 * load_partner_resultate.php
 * Lädt und zeigt Partner-Resultate für Endresultate an
 * 
 * @author System
 * @version 1.2
 * @description Lädt Partner-Resultate mit Mitglied-Verknüpfung UND Gäste ohne Resultate
 * @fix Collation-Problem zwischen endresultate_partner und endstich_gaeste behoben
 */

/**
 * Validate year input to prevent invalid values
 * 
 * @param mixed $year Year to validate
 * @return bool True if valid, false otherwise
 */

function validateYear($year) {
    $currentYear = date('Y');
    $minYear = 2000;
    $maxYear = $currentYear + 1;
    return is_numeric($year) && $year >= $minYear && $year <= $maxYear;
}

/**
 * Generate HTML for a partner result row with existing data
 *
 * @param array $row Database row with member and partner data
 * @return string HTML table row
 */

function generatePartnerResultRow($row) {

    // Berechne Summen für neue Struktur
    $endstichSumme = 0;
    for ($i = 1; $i <= 10; $i++) {
        $endstichSumme += ($row['EndstichSchuss' . $i] ?? 0);
    }

    // Sie und Er - Spezielle Berechnung mit Unique-Logik
    $sieErSchuesse = [];
    $sieErValues = [];

    // Sammle alle 10 Sie und Er Schüsse (1-5 von Partner, 6-10 von Mitglied)
    // Partner-Schüsse (1-5)
    for ($i = 1; $i <= 5; $i++) {
        $value = $row['SieErSchuss' . $i] ?? 0;
        if ($value > 0) {
            $sieErSchuesse[] = ['value' => $value, 'source' => 'partner', 'index' => $i];
            $sieErValues[] = intval($value);
        }
    }

    // Mitglied-Schüsse (6-10)
    for ($i = 6; $i <= 10; $i++) {
        $value = $row['SieErSchuss' . $i] ?? 0;
        if ($value > 0) {
            $sieErSchuesse[] = ['value' => $value, 'source' => 'mitglied', 'index' => $i];
            $sieErValues[] = intval($value);
        }
    }

    // Berechne Unique-Summe
    $uniqueValues = array_unique($sieErValues);
    $sieErUniqueSum = array_sum($uniqueValues);

    // Erstelle schöne Badge-Anzeige für Sie und Er mit Markierung
    $sieErDisplay = '';
    if (!empty($sieErSchuesse)) {
        $valueCount = array_count_values($sieErValues);
        $sieErDisplay = '<div class="d-flex flex-wrap gap-1 justify-content-center align-items-center mb-1">';

        // Zeige Partner-Schüsse (rosa/pink)
        $hasPartnerShots = false;
        foreach ($sieErSchuesse as $shotData) {
            if ($shotData['source'] === 'partner') {
                $hasPartnerShots = true;
                $intShot = intval($shotData['value']);

                // Badge-Stil für Partner (rosa/pink)
                if ($valueCount[$intShot] > 1) {

                    // Duplikat prüfen
                    if (isset($valueCount[$intShot . '_shown'])) {

                        // Duplikat - durchgestrichen
                        $sieErDisplay .= '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size: 0.7rem; text-decoration: line-through; opacity: 0.5;" title="Partnerin (Duplikat - zählt nicht)">' . $shotData['value'] . '</span>';
                    } else {

                        // Erstes Vorkommen
                        $sieErDisplay .= '<span class="badge bg-danger text-white" style="font-size: 0.7rem;" title="Partnerin">' . $shotData['value'] . '</span>';
                        $valueCount[$intShot . '_shown'] = true;
                    }
                } else {

                    // Einzigartiger Wert
                    $sieErDisplay .= '<span class="badge bg-danger text-white" style="font-size: 0.7rem;" title="Partnerin">' . $shotData['value'] . '</span>';
                }
            }
        }
        if ($hasPartnerShots) {

            // Trennstrich zwischen Partner und Mitglied
            $sieErDisplay .= '<span class="text-muted mx-1">|</span>';
        }

        // Zeige Mitglied-Schüsse (blau)
        // Reset für zweite Durchgang (falls gleiche Werte bei Partner und Mitglied)
        $tempValueCount = array_count_values($sieErValues);
        foreach ($sieErSchuesse as $shotData) {
            if ($shotData['source'] === 'partner') {
                $intShot = intval($shotData['value']);
                if (isset($tempValueCount[$intShot . '_partner_shown'])) {
                    continue;
                }
                $tempValueCount[$intShot . '_partner_shown'] = true;
            }
        }
        foreach ($sieErSchuesse as $shotData) {
            if ($shotData['source'] === 'mitglied') {
                $intShot = intval($shotData['value']);

                // Badge-Stil für Mitglied (blau)
                if ($valueCount[$intShot] > 1) {

                    // Prüfe ob dieser Wert schon vom Partner oder früher vom Mitglied kam
                    if (isset($tempValueCount[$intShot . '_partner_shown']) || isset($tempValueCount[$intShot . '_mitglied_shown'])) {

                        // Duplikat - durchgestrichen
                        $sieErDisplay .= '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size: 0.7rem; text-decoration: line-through; opacity: 0.5;" title="Mitglied (Duplikat - zählt nicht)">' . $shotData['value'] . '</span>';
                    } else {

                        // Erstes Vorkommen beim Mitglied
                        $sieErDisplay .= '<span class="badge bg-primary text-white" style="font-size: 0.7rem;" title="Mitglied">' . $shotData['value'] . '</span>';
                        $tempValueCount[$intShot . '_mitglied_shown'] = true;
                    }
                } else {

                    // Einzigartiger Wert
                    if (isset($tempValueCount[$intShot . '_partner_shown'])) {

                        // War schon beim Partner - durchstreichen
                        $sieErDisplay .= '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25" style="font-size: 0.7rem; text-decoration: line-through; opacity: 0.5;" title="Mitglied (Duplikat vom Partner - zählt nicht)">' . $shotData['value'] . '</span>';
                    } else {
                        $sieErDisplay .= '<span class="badge bg-primary text-white" style="font-size: 0.7rem;" title="Mitglied">' . $shotData['value'] . '</span>';
                    }
                }
            }
        }
        $sieErDisplay .= '</div>';

        // Legende und Total
        $sieErDisplay .= '<div class="d-flex justify-content-center gap-2 align-items-center" style="font-size: 0.7rem;">';
        $sieErDisplay .= '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger"><i class="bi bi-heart-fill me-1"></i>Partner</span>';
        $sieErDisplay .= '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary"><i class="bi bi-person-fill me-1"></i>Mitglied</span>';
        $sieErDisplay .= '<span class="badge bg-secondary"><i class="bi bi-calculator me-1"></i>Total: ' . $sieErUniqueSum . '</span>';
        $sieErDisplay .= '</div>';
    } else {
        $sieErDisplay = '<span class="text-muted">-</span>';
    }
    $schwiniSumme = 0;
    for ($i = 1; $i <= 6; $i++) {
        $schwiniSumme += ($row['PartnerSchwiniSchuss' . $i] ?? 0);
    }

    // Build HTML with proper escaping
    $html = "<tr>";
    $html .= "<td>" . htmlspecialchars($row['PartnerName'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    $html .= "<td><a href='#' class='edit-partner-btn' data-id='" . htmlspecialchars($row['PartnerID'], ENT_QUOTES, 'UTF-8') . "'>" .
             htmlspecialchars($row['Name'] . " " . $row['Vorname'], ENT_QUOTES, 'UTF-8') . "</a></td>";
    $html .= "<td class='text-center'>" . number_format($endstichSumme, 1) . "</td>";
    $html .= "<td class='text-center'>" . $sieErDisplay . "</td>";
    $html .= "<td class='text-center'>" . number_format($schwiniSumme, 1) . "</td>";

    // Actions column with icon-only buttons
    $html .= "<td class='text-center'>";
    $html .= "<button class='btn btn-outline-primary btn-sm me-1 edit-partner-btn' data-id='" . htmlspecialchars($row['PartnerID'], ENT_QUOTES, 'UTF-8') . "' title='Bearbeiten'>";
    $html .= "<i class='bi bi-pencil'></i></button>";
    $html .= "<button class='btn btn-outline-danger btn-sm delete-partner-btn' data-id='" . htmlspecialchars($row['PartnerID'], ENT_QUOTES, 'UTF-8') . "' title='Löschen'>";
    $html .= "<i class='bi bi-trash'></i></button>";
    $html .= "</td>";
    $html .= "</tr>";
    return $html;
}

/**
 * Generate HTML for a guest without results
 *
 * @param array $guest Guest data from endstich_gaeste
 * @return string HTML table row
 */

function generateGuestWithoutResultRow($guest) {
    $guestName = htmlspecialchars($guest['GuestName'] ?? '', ENT_QUOTES, 'UTF-8');
    $html = "<tr class='table-warning'>";
    $html .= "<td>" . $guestName . " <span class='badge bg-warning text-dark ms-2'><i class='bi bi-clock-history me-1'></i>Gast (noch keine Resultate)</span></td>";
    $html .= "<td class='text-muted'><i class='bi bi-dash'></i></td>";
    $html .= "<td class='text-center text-muted'><i class='bi bi-dash'></i></td>";
    $html .= "<td class='text-center text-muted'><i class='bi bi-dash'></i></td>";
    $html .= "<td class='text-center text-muted'><i class='bi bi-dash'></i></td>";

    // Actions column - nur "Erfassen" Button für Gäste ohne Resultate
    $html .= "<td class='text-center'>";
    $html .= "<button class='btn btn-success btn-sm add-guest-result-btn' data-guest-name='" . $guestName . "' title='Resultate erfassen'>";
    $html .= "<i class='bi bi-plus-circle me-1'></i>Erfassen</button>";
    $html .= "</td>";
    $html .= "</tr>";
    return $html;
}

// Include database configuration
include '../config.php';

// Check database connection with proper error handling
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    echo "<tr><td colspan='6' class='text-center text-danger'>Datenbankverbindung fehlgeschlagen</td></tr>";
    exit;
}

// Check if tables exist
$tableCheckSql = "SHOW TABLES LIKE 'endresultate_partner'";
$tableCheck = $conn->query($tableCheckSql);
if ($tableCheck->num_rows == 0) {
    echo "<tr><td colspan='6' class='text-center text-warning'>
            <i class='bi bi-exclamation-triangle me-2'></i>
            Die Tabelle 'endresultate_partner' existiert noch nicht.<br>
            <small>Bitte führe zuerst das SQL-Setup-Skript aus: <code>inc/endresultate_partner/database_setup.sql</code></small>
          </td></tr>";
    $conn->close();
    exit;
}

// Input validation and sanitization
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
if (!validateYear($year)) {
    error_log("Invalid year provided: " . ($_GET['year'] ?? 'null'));
    $year = date('Y'); // Fallback to current year
}

try {

    // 1. Lade alle Partner mit Resultaten
    $sql = "
    SELECT
        m.ID,
        m.Name,
        m.Vorname,
        ep.ID as PartnerID,
        ep.PartnerName,
        ep.EndstichSchuss1,
        ep.EndstichSchuss2,
        ep.EndstichSchuss3,
        ep.EndstichSchuss4,
        ep.EndstichSchuss5,
        ep.EndstichSchuss6,
        ep.EndstichSchuss7,
        ep.EndstichSchuss8,
        ep.EndstichSchuss9,
        ep.EndstichSchuss10,
        ep.SieErSchuss1,
        ep.SieErSchuss2,
        ep.SieErSchuss3,
        ep.SieErSchuss4,
        ep.SieErSchuss5,
        ep.SieErSchuss6,
        ep.SieErSchuss7,
        ep.SieErSchuss8,
        ep.SieErSchuss9,
        ep.SieErSchuss10,
        ep.PartnerSchwiniSchuss1,
        ep.PartnerSchwiniSchuss2,
        ep.PartnerSchwiniSchuss3,
        ep.PartnerSchwiniSchuss4,
        ep.PartnerSchwiniSchuss5,
        ep.PartnerSchwiniSchuss6
    FROM
        endresultate_partner ep
    JOIN mitglieder m ON ep.MitgliedID = m.ID
    WHERE ep.Jahr = ?
    ORDER BY
        m.Name, m.Vorname
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasResults = false;

    // Zeige Partner mit Resultaten
    if ($result->num_rows > 0) {
        $hasResults = true;
        while ($row = $result->fetch_assoc()) {
            echo generatePartnerResultRow($row);
        }
    }
    $stmt->close();

    // 2. Lade Gäste ohne Resultate (nur ohne Geburtsdatum)
    // FIX: COLLATE hinzugefügt um Collation-Konflikt zu vermeiden
    $guestSql = "
    SELECT
        g.id as GuestID,
        g.name as GuestName
    FROM
        endstich_gaeste g
    WHERE 
        g.jahr = ?
        AND g.geburtsdatum IS NULL
        AND NOT EXISTS (
            SELECT 1 
            FROM endresultate_partner ep 
            WHERE ep.PartnerName COLLATE utf8mb4_general_ci = g.name 
            AND ep.Jahr = ?
        )
    ORDER BY
        g.name
    ";
    $guestStmt = $conn->prepare($guestSql);
    if (!$guestStmt) {
        throw new Exception("Prepare guest query failed: " . $conn->error);
    }
    $guestStmt->bind_param("ii", $year, $year);
    $guestStmt->execute();
    $guestResult = $guestStmt->get_result();

    // Zeige Gäste ohne Resultate
    if ($guestResult->num_rows > 0) {
        $hasResults = true;
        while ($guestRow = $guestResult->fetch_assoc()) {
            echo generateGuestWithoutResultRow($guestRow);
        }
    }
    $guestStmt->close();

    // Wenn weder Partner noch Gäste vorhanden sind
    if (!$hasResults) {
        echo "<tr><td colspan='6' class='text-center py-4'>Noch keine Partnerinnen oder Gäste erfasst für das Jahr $year.</td></tr>";
    }
} catch (Exception $e) {

    // Log error for debugging while showing user-friendly message
    error_log("Database error in load_partner_resultate.php: " . $e->getMessage());
    echo "<tr><td colspan='6' class='text-center text-danger'>Fehler beim Laden der Daten. Bitte versuche es später erneut.</td></tr>";
} finally {

    // Ensure connection is always closed
    $conn->close();
}

?>
