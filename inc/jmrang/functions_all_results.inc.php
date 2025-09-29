<?php
//functions_all_results.inc.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../config.php';

// Datenbankverbindung herstellen

function getTotal($kategorie)
{
    global $conn;
    $resultatetotal = array();

    // Mitglieder abrufen
    $sqlMitgliederA = "SELECT m.id, m.Vorname, m.Name FROM mitglieder m
        INNER JOIN Waffen w ON w.ID = m.WaffenID
        WHERE w.Kategorie = '$kategorie' AND m.Status = 1";
    $mitglieder = $conn->query($sqlMitgliederA);

    // Wettbewerbe abrufen (alle nicht versteckten)
    $sqlWettbewerbe = "
        SELECT * FROM JMDefinition 
        WHERE year = 2024 
        AND hidden = 0
    ";
    $jm = $conn->query($sqlWettbewerbe);
    $wettbewerbe = [];
    while ($rowdef = $jm->fetch_assoc()) {
        $wettbewerbe[$rowdef['ID']] = $rowdef;
    }

    if ($mitglieder->num_rows > 0) {
        foreach ($mitglieder as $mitglied) {
            $mitgliedID = $mitglied['id'];
            $total = 0;

            // Alle Resultate für dieses Mitglied sammeln
            foreach ($wettbewerbe as $wettbewerbID => $wettbewerb) {
                $bezeichnung = $wettbewerb['Bezeichnung'];
                $maxpunkte = $wettbewerb['Maxpunkte'];

                if ($bezeichnung == 'Endstich') {
                    // Endstich Punkte aus der Tabelle 'endstich' berechnen
                    $sqlEndstich = "
                        SELECT 
                            Schuss1, Schuss2, Schuss3, Schuss4, Schuss5, Schuss6, Schuss7, Schuss8, Schuss9, Schuss10
                        FROM 
                            endstich
                        WHERE 
                            MitgliedID = $mitgliedID
                            AND Jahr = 2024
                    ";
                    $endstichResult = $conn->query($sqlEndstich);
                    if ($endstichResult->num_rows > 0) {
                        $endstichRow = $endstichResult->fetch_assoc();
                        $punkte = array_sum($endstichRow);
                    } else {
                        $punkte = null;
                    }
                } else {
                    // Normale Wettbewerbe aus jmresultate
                    $sqlResultate = "
                        SELECT
                            jm.Punkte,
                            jd.Maxpunkte,
                            jd.Bezeichnung
                        FROM 
                            jmresultate jm
                        INNER JOIN 
                            JMDefinition jd ON jd.ID = jm.jmdefinitionID
                        WHERE jm.mitgliederID = $mitgliedID
                        AND jd.ID = $wettbewerbID
                    ";
                    $punkteResult = $conn->query($sqlResultate);
                    if ($punkteResult->num_rows > 0) {
                        $row = $punkteResult->fetch_assoc();
                        $punkte = $row['Punkte'];
                    } else {
                        $punkte = null;
                    }
                }

                // Skalierungsbedingung gemäß Ihren Anforderungen
                if ($punkte !== null && !in_array($bezeichnung, ['Obligatorisch', 'Feldschiessen']) && $maxpunkte < 100) {
                    $punkte = round(($punkte * 100.0 / $maxpunkte), 2);
                }

                if ($punkte !== null) {
                    $total += $punkte;
                }
            }
            $resultatetotal[$mitgliedID] = round($total, 2);
        }
    }

    // Die Funktion gibt die Gesamtsummen für die Mitglieder der angegebenen Kategorie zurück
    return $resultatetotal;
}

function checkIfSSMExists()
{
    global $conn;
    $sqlCheckSSM = "SELECT ID FROM JMDefinition WHERE Bezeichnung = 'SSM'";
    $resultSSM = $conn->query($sqlCheckSSM);
    return ($resultSSM && $resultSSM->num_rows > 0);
}
?>
