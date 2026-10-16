<?php
// inc/jmresultate/anlaesse_data.php
// Gemeinsame Datenbeschaffung fuer die JM-Anlaesse-Uebersicht.
// Genutzt von load_anlaesse.php (AJAX) UND vom serverseitigen Initial-Rendering
// in jmresultate.php, damit die Anlass-Karten ohne AJAX-Verzoegerung erscheinen.

if (!function_exists('getJmAnlaesse')) {
    /**
     * Liefert die Anlaesse eines Jahres inkl. Fortschritt (filledCount/totalMembers) und
     * Anzahl Mitglied-Meldungen, die der Vorstand noch nicht bestaetigt hat (gemeldetCount).
     *
     * @param mysqli $conn
     * @param int    $year
     * @return array ['anlaesse' => [...], 'totalMembers' => int]
     */
    function getJmAnlaesse($conn, $year) {
        $year = (int) $year;

        $stmt = $conn->prepare("
            SELECT ID, Bezeichnung, Maxpunkte, Streicher, Reihenfolge, Schiesstage
            FROM JMDefinition
            WHERE hidden = 0 AND Info = 0 AND Erweitert = 0 AND year = ?
            ORDER BY
                CASE
                    WHEN Bezeichnung = 'Obligatorisch' THEN 1
                    WHEN Bezeichnung = 'Feldschiessen' THEN 2
                    WHEN Bezeichnung LIKE '%Kantonalstich%' THEN 3
                    WHEN Bezeichnung LIKE '%Sektionsmeisterschaft%' THEN 4
                    ELSE 5
                END,
                Reihenfolge
        ");
        $stmt->bind_param("i", $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $totalMembers = (int) $conn->query("SELECT COUNT(*) as cnt FROM mitglieder WHERE status=1 AND Verstorben=0")
            ->fetch_assoc()['cnt'];

        // Letztes Schiessdatum je Anlass (wie die Rangliste: JMSchiesstage, sonst Freitext Schiesstage),
        // damit die Karte zeigt, wann geschossen wurde und ob noch nichts erfasst ist
        require_once __DIR__ . '/../jm_berechnung.inc.php';
        $datum = [];
        $stD = $conn->prepare("SELECT jm_id, MAX(schiesstag) AS d FROM JMSchiesstage WHERE year = ? GROUP BY jm_id");
        $stD->bind_param("i", $year);
        $stD->execute();
        foreach ($stD->get_result()->fetch_all(MYSQLI_ASSOC) as $d) {
            if (!empty($d['d'])) $datum[(int) $d['jm_id']] = $d['d'];
        }
        $stD->close();

        $anlaesse = [];
        while ($row = $result->fetch_assoc()) {
            $defID = (int) $row['ID'];
            $isSektionsmeisterschaft = (strpos($row['Bezeichnung'], 'Sektionsmeisterschaft') !== false);
            $isReadonly = in_array($row['Bezeichnung'], ['Endstich', 'Bester Kantonalstich']);

            if ($isReadonly) {
                $filledCount = 0; // readonly, kein Fortschritt noetig
                $gemeldetCount = 0;
            } elseif ($isSektionsmeisterschaft) {
                $stmt2 = $conn->prepare("SELECT COUNT(DISTINCT CASE WHEN Punkte > 0 THEN mitgliederID END) as cnt,
                                                COUNT(DISTINCT CASE WHEN status = 'entwurf' THEN mitgliederID END) as gemeldet
                                           FROM jmresultate WHERE jmdefinitionID=? AND (Info='runde 1' OR Info='runde 2')");
                $stmt2->bind_param("i", $defID);
                $stmt2->execute();
                $zahlen = $stmt2->get_result()->fetch_assoc();
                $filledCount = (int) $zahlen['cnt'];
                $gemeldetCount = (int) $zahlen['gemeldet'];
                $stmt2->close();
            } else {
                $stmt2 = $conn->prepare("SELECT SUM(Punkte > 0) as cnt, SUM(status = 'entwurf') as gemeldet
                                           FROM jmresultate WHERE jmdefinitionID=? AND (Info='' OR Info IS NULL)");
                $stmt2->bind_param("i", $defID);
                $stmt2->execute();
                $zahlen = $stmt2->get_result()->fetch_assoc();
                $filledCount = (int) $zahlen['cnt'];
                $gemeldetCount = (int) $zahlen['gemeldet'];
                $stmt2->close();
            }

            $anlaesse[] = [
                'id' => $defID,
                'bezeichnung' => $row['Bezeichnung'],
                'maxpunkte' => (int) $row['Maxpunkte'],
                'streicher' => (bool) $row['Streicher'],
                'isSektionsmeisterschaft' => $isSektionsmeisterschaft,
                'isReadonly' => $isReadonly,
                'filledCount' => $filledCount,
                'gemeldetCount' => $gemeldetCount,
                'totalMembers' => $totalMembers,
                'datum' => $datum[$defID] ?? (!empty($row['Schiesstage']) ? jmLetztesDatumAusText((string) $row['Schiesstage'], $year) : null)
            ];
        }

        return ['anlaesse' => $anlaesse, 'totalMembers' => $totalMembers];
    }
}
