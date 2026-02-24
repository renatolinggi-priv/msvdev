<?
//functions.inc.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once '../config.php';

// Liest Anzahl Streicher aus der Parameter-Tabelle; Fallback 3
function getExcludeCount(mysqli $conn, int $year): int {
    $st = $conn->prepare("SELECT excludeCount FROM Parameter WHERE year = ?");
    $st->bind_param('i', $year);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ? max(1, (int)$row['excludeCount']) : 3;
}

// Datenbankverbindung herstellen

function getTotal($kategorie, $year)
{
    global $conn;
    $resultatetotal = array();

    // Überprüfen, ob 'SSM 2024' existiert
    $ssmExists = checkIfSSMExists($kategorie, $year);

    // Mitglieder abrufen
    $sqlMitgliederA = "SELECT m.id FROM `mitglieder` m
        INNER JOIN Waffen w ON w.ID = m.WaffenID
        WHERE w.Kategorie = ? AND m.Status = 1";
    $stmt = $conn->prepare($sqlMitgliederA);
    $stmt->bind_param("s", $kategorie);
    $stmt->execute();
    $mitglieder = $stmt->get_result();

    if ($mitglieder->num_rows > 0) {
        foreach ($mitglieder as $mitglied) {
            $mitgliedID = $mitglied['id'];
            $totalFix = 0;

            // 2.1 Resultate mit Streicher = 0 sammeln
            $sqlResultateFix = "
                SELECT jm.Punkte
                FROM `jmresultate` jm
                INNER JOIN JMDefinition jd ON jd.ID = jm.jmdefinitionID
                WHERE jm.mitgliederID = ? AND jd.Streicher = 0 AND year = ?
            ";
            if ($ssmExists) {
                $sqlResultateFix .= " AND jd.Bezeichnung NOT LIKE '%Sektionsmeisterschaft%'";
            }
            $stmtFix = $conn->prepare($sqlResultateFix);
            $stmtFix->bind_param("ii", $mitgliedID, $year);
            $stmtFix->execute();
            $punkteFix = $stmtFix->get_result();

            if ($punkteFix->num_rows > 0) {
                while ($row = $punkteFix->fetch_assoc()) {
                    $totalFix += $row['Punkte'];
                }
            }
            $stmtFix->close();

            // 2.2 Prüfe welche Wettbewerbe überhaupt Resultate haben
            $sqlWettbewerbeWithResults = "
                SELECT DISTINCT jd.ID, jd.Maxpunkte, jd.Bezeichnung
                FROM JMDefinition jd
                INNER JOIN jmresultate jr ON jd.ID = jr.jmdefinitionID
                WHERE jd.Streicher = 1 AND jd.year = ?
            ";
            if ($ssmExists) {
                $sqlWettbewerbeWithResults .= " AND jd.Bezeichnung NOT LIKE '%Sektionsmeisterschaft%'";
            }
            $stmtWett = $conn->prepare($sqlWettbewerbeWithResults);
            $stmtWett->bind_param("i", $year);
            $stmtWett->execute();
            $wettbewerbeWithResults = $stmtWett->get_result();

            $activeWettbewerbeIDs = array();
            if ($wettbewerbeWithResults->num_rows > 0) {
                while ($row = $wettbewerbeWithResults->fetch_assoc()) {
                    $activeWettbewerbeIDs[] = $row['ID'];
                }
            }
            $stmtWett->close();

            // 2.3 Resultate mit Streicher = 1 sammeln (tatsächliche Teilnahmen)
            $sqlResultateStreicher = "
                SELECT
                    jr.jmdefinitionID AS WettbewerbID,
                    CASE
                        WHEN jd.Maxpunkte != 100 THEN ROUND((jr.Punkte / jd.Maxpunkte) * 100, 2)
                        ELSE jr.Punkte
                    END AS NormalizedPoints
                FROM
                    jmresultate jr
                INNER JOIN
                    JMDefinition jd ON jr.jmdefinitionID = jd.ID
                WHERE
                    jr.mitgliederID = ?
                    AND jd.Streicher = 1
                    AND jd.year = ?
            ";
            if ($ssmExists) {
                $sqlResultateStreicher .= " AND jd.Bezeichnung NOT LIKE '%Sektionsmeisterschaft%'";
            }
            $stmtStreicher = $conn->prepare($sqlResultateStreicher);
            $stmtStreicher->bind_param("ii", $mitgliedID, $year);
            $stmtStreicher->execute();
            $punkteStreicher = $stmtStreicher->get_result();

            $streicherArray = array();
            $teilgenommeneWettbewerbe = array();

            // Tatsächliche Resultate sammeln
            if ($punkteStreicher->num_rows > 0) {
                while ($row = $punkteStreicher->fetch_assoc()) {
                    $streicherArray[] = array(
                        'WettbewerbID' => $row['WettbewerbID'],
                        'NormalizedPoints' => $row['NormalizedPoints']
                    );
                    $teilgenommeneWettbewerbe[] = $row['WettbewerbID'];
                }
            }
            $stmtStreicher->close();

            // Nicht-Teilnahmen nur für Wettbewerbe hinzufügen, die überhaupt Resultate haben
            foreach ($activeWettbewerbeIDs as $wettbewerbID) {
                // Wenn nicht teilgenommen, aber Wettbewerb hat Resultate, als 0-Punkte-Resultat hinzufügen
                if (!in_array($wettbewerbID, $teilgenommeneWettbewerbe)) {
                    $streicherArray[] = array(
                        'WettbewerbID' => $wettbewerbID,
                        'NormalizedPoints' => 0
                    );
                }
            }

            // 2.3 Die drei niedrigsten Resultate streichen
            usort($streicherArray, function($a, $b) {
                return $a['NormalizedPoints'] <=> $b['NormalizedPoints'];
            });

            $excludeCount = getExcludeCount($conn, $year); // Anzahl der Streichergebnisse
            $gestricheneResultate = array_slice($streicherArray, 0, $excludeCount);
            $verbleibendeResultate = array_slice($streicherArray, $excludeCount);

            $totalStreicher = 0;
            foreach ($verbleibendeResultate as $result) {
                $totalStreicher += $result['NormalizedPoints'];
            }

            // 2.4 Gesamtpunktzahl berechnen
            $total = $totalFix + $totalStreicher;
            $resultatetotal[$mitgliedID] = round($total, 2);

            // 2.5 Gestrichene Wettbewerbe speichern
            foreach ($gestricheneResultate as $gestrichen) {
                $MitgliedStreicher[$mitgliedID][] = $gestrichen['WettbewerbID'];
            }
        }
    }
    $stmt->close();
    arsort($resultatetotal);
    return $resultatetotal;
}

function GetStreicher($kategorie, $year)
{
    global $conn;
    $MitgliedStreicher = array();

    // Überprüfen, ob 'SSM 2024' existiert
    $ssmExists = checkIfSSMExists($kategorie, $year);

    $sqlMitgliederA = "SELECT m.id FROM `mitglieder` m
        INNER JOIN Waffen w ON w.ID = m.WaffenID
        WHERE w.Kategorie = ? AND m.Status = 1";
    $stmt = $conn->prepare($sqlMitgliederA);
    $stmt->bind_param("s", $kategorie);
    $stmt->execute();
    $mitglieder = $stmt->get_result();

    if ($mitglieder->num_rows > 0) {
        foreach ($mitglieder as $mitglied) {
            $mitgliedID = $mitglied['id'];

            // Prüfe welche Wettbewerbe überhaupt Resultate haben
            $sqlWettbewerbeWithResults = "
                SELECT DISTINCT jd.ID, jd.Maxpunkte, jd.Bezeichnung
                FROM JMDefinition jd
                INNER JOIN jmresultate jr ON jd.ID = jr.jmdefinitionID
                WHERE jd.Streicher = 1 AND jd.year = ?
            ";
            if ($ssmExists) {
                $sqlWettbewerbeWithResults .= " AND jd.Bezeichnung NOT LIKE '%Sektionsmeisterschaft%'";
            }
            $stmtWett = $conn->prepare($sqlWettbewerbeWithResults);
            $stmtWett->bind_param("i", $year);
            $stmtWett->execute();
            $wettbewerbeWithResults = $stmtWett->get_result();

            $activeWettbewerbeIDs = array();
            if ($wettbewerbeWithResults->num_rows > 0) {
                while ($row = $wettbewerbeWithResults->fetch_assoc()) {
                    $activeWettbewerbeIDs[] = $row['ID'];
                }
            }
            $stmtWett->close();

            // Resultate mit Streicher = 1 sammeln (tatsächliche Teilnahmen)
            $sqlResultateStreicher = "
                SELECT
                    jr.jmdefinitionID AS WettbewerbID,
                    CASE
                        WHEN jd.Maxpunkte != 100 THEN ROUND((jr.Punkte / jd.Maxpunkte) * 100, 2)
                        ELSE jr.Punkte
                    END AS NormalizedPoints
                FROM
                    jmresultate jr
                INNER JOIN
                    JMDefinition jd ON jr.jmdefinitionID = jd.ID
                WHERE
                    jr.mitgliederID = ?
                    AND jd.Streicher = 1
                    AND jd.year = ?
            ";
            if ($ssmExists) {
                $sqlResultateStreicher .= " AND jd.Bezeichnung NOT LIKE '%Sektionsmeisterschaft%'";
            }
            $stmtStreicher = $conn->prepare($sqlResultateStreicher);
            $stmtStreicher->bind_param("ii", $mitgliedID, $year);
            $stmtStreicher->execute();
            $punkteStreicher = $stmtStreicher->get_result();

            $streicherArray = array();
            $teilgenommeneWettbewerbe = array();

            // Tatsächliche Resultate sammeln
            if ($punkteStreicher->num_rows > 0) {
                while ($row = $punkteStreicher->fetch_assoc()) {
                    $streicherArray[] = array(
                        'WettbewerbID' => $row['WettbewerbID'],
                        'NormalizedPoints' => $row['NormalizedPoints']
                    );
                    $teilgenommeneWettbewerbe[] = $row['WettbewerbID'];
                }
            }
            $stmtStreicher->close();

            // Nicht-Teilnahmen nur für Wettbewerbe hinzufügen, die überhaupt Resultate haben
            foreach ($activeWettbewerbeIDs as $wettbewerbID) {
                // Wenn nicht teilgenommen, aber Wettbewerb hat Resultate, als 0-Punkte-Resultat hinzufügen
                if (!in_array($wettbewerbID, $teilgenommeneWettbewerbe)) {
                    $streicherArray[] = array(
                        'WettbewerbID' => $wettbewerbID,
                        'NormalizedPoints' => 0
                    );
                }
            }

            // Die drei niedrigsten Resultate ermitteln
            usort($streicherArray, function($a, $b) {
                return $a['NormalizedPoints'] <=> $b['NormalizedPoints'];
            });

            $excludeCount = getExcludeCount($conn, $year); // Anzahl der Streichergebnisse
            $gestricheneResultate = array_slice($streicherArray, 0, $excludeCount);

            foreach ($gestricheneResultate as $gestrichen) {
                $MitgliedStreicher[$mitgliedID][] = $gestrichen['WettbewerbID'];
            }
        }
    }
    $stmt->close();
    return $MitgliedStreicher;
}

function CheckIfStreicher($wert, $streicher)
{
    if (isset($wert) && in_array($wert, $streicher)) {
        return true;
    } else {
        return false;
    }
}

function checkIfSSMExists($kategorie, $year)
{
    global $conn;
    $ssmLabel = "SSM " . $year;
    $sqlCheckSSM = "SELECT ID FROM JMDefinition WHERE Bezeichnung = ?";
    $stmt = $conn->prepare($sqlCheckSSM);
    $stmt->bind_param("s", $ssmLabel);
    $stmt->execute();
    $resultSSM = $stmt->get_result();
    $exists = ($resultSSM && $resultSSM->num_rows > 0);
    $stmt->close();
    return $exists;
}

?>
