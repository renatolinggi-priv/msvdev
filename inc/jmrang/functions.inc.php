<?
//functions.inc.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once '../config.php';

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
        WHERE w.Kategorie = '$kategorie' AND m.Status = 1";
    $mitglieder = $conn->query($sqlMitgliederA);

    if ($mitglieder->num_rows > 0) {
        foreach ($mitglieder as $mitglied) {
            $mitgliedID = $mitglied['id'];
            $totalFix = 0;

            // 2.1 Resultate mit Streicher = 0 sammeln
            $sqlResultateFix = "
                SELECT jm.Punkte
                FROM `jmresultate` jm
                INNER JOIN JMDefinition jd ON jd.ID = jm.jmdefinitionID
                WHERE jm.mitgliederID = $mitgliedID AND jd.Streicher = 0 AND year = $year
            ";
            if ($ssmExists) {
                $sqlResultateFix .= " AND jd.Bezeichnung NOT LIKE '%Sektionsmeisterschaft%'";
            }
            $punkteFix = $conn->query($sqlResultateFix);

            if ($punkteFix->num_rows > 0) {
                while ($row = $punkteFix->fetch_assoc()) {
                    $totalFix += $row['Punkte'];
                }
            }

            // 2.2 Prüfe welche Wettbewerbe überhaupt Resultate haben
            $sqlWettbewerbeWithResults = "
                SELECT DISTINCT jd.ID, jd.Maxpunkte, jd.Bezeichnung
                FROM JMDefinition jd
                INNER JOIN jmresultate jr ON jd.ID = jr.jmdefinitionID
                WHERE jd.Streicher = 1 AND jd.year = $year
            ";
            if ($ssmExists) {
                $sqlWettbewerbeWithResults .= " AND jd.Bezeichnung NOT LIKE '%Sektionsmeisterschaft%'";
            }
            $wettbewerbeWithResults = $conn->query($sqlWettbewerbeWithResults);
            
            $activeWettbewerbeIDs = array();
            if ($wettbewerbeWithResults->num_rows > 0) {
                while ($row = $wettbewerbeWithResults->fetch_assoc()) {
                    $activeWettbewerbeIDs[] = $row['ID'];
                }
            }

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
                    jr.mitgliederID = $mitgliedID
                    AND jd.Streicher = 1
                    AND jd.year = $year
            ";
            if ($ssmExists) {
                $sqlResultateStreicher .= " AND jd.Bezeichnung NOT LIKE '%Sektionsmeisterschaft%'";
            }
            $punkteStreicher = $conn->query($sqlResultateStreicher);

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

            $excludeCount = 3; // Anzahl der Streichergebnisse
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
        WHERE w.Kategorie = '$kategorie' AND m.Status = 1";
    $mitglieder = $conn->query($sqlMitgliederA);

    if ($mitglieder->num_rows > 0) {
        foreach ($mitglieder as $mitglied) {
            $mitgliedID = $mitglied['id'];

            // Prüfe welche Wettbewerbe überhaupt Resultate haben
            $sqlWettbewerbeWithResults = "
                SELECT DISTINCT jd.ID, jd.Maxpunkte, jd.Bezeichnung
                FROM JMDefinition jd
                INNER JOIN jmresultate jr ON jd.ID = jr.jmdefinitionID
                WHERE jd.Streicher = 1 AND jd.year = $year
            ";
            if ($ssmExists) {
                $sqlWettbewerbeWithResults .= " AND jd.Bezeichnung NOT LIKE '%Sektionsmeisterschaft%'";
            }
            $wettbewerbeWithResults = $conn->query($sqlWettbewerbeWithResults);
            
            $activeWettbewerbeIDs = array();
            if ($wettbewerbeWithResults->num_rows > 0) {
                while ($row = $wettbewerbeWithResults->fetch_assoc()) {
                    $activeWettbewerbeIDs[] = $row['ID'];
                }
            }

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
                    jr.mitgliederID = $mitgliedID
                    AND jd.Streicher = 1
                    AND jd.year = $year
            ";
            if ($ssmExists) {
                $sqlResultateStreicher .= " AND jd.Bezeichnung NOT LIKE '%Sektionsmeisterschaft%'";
            }
            $punkteStreicher = $conn->query($sqlResultateStreicher);

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

            $excludeCount = 3; // Anzahl der Streichergebnisse
            $gestricheneResultate = array_slice($streicherArray, 0, $excludeCount);

            foreach ($gestricheneResultate as $gestrichen) {
                $MitgliedStreicher[$mitgliedID][] = $gestrichen['WettbewerbID'];
            }
        }
    }
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
    $sqlCheckSSM = "SELECT ID FROM JMDefinition WHERE Bezeichnung = 'SSM $year'";
    $resultSSM = $conn->query($sqlCheckSSM);
    return ($resultSSM && $resultSSM->num_rows > 0);
}

?>