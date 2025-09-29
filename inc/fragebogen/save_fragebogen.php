<?php
header('Content-Type: text/plain; charset=utf-8');
require_once '../config.php';

// Jahr
$year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');

if (!isset($_POST['fragebogen']) || !is_array($_POST['fragebogen'])) {
    echo "Keine Daten empfangen.";
    exit;
}

$conn->begin_transaction();

try {
    foreach ($_POST['fragebogen'] as $mid => $data) {
        $mid = (int)$mid;
        $waffenID    = (int)$data['waffenID'];
        $mannschaft  = $conn->real_escape_string($data['mannschaft']);
        $gruppen     = $conn->real_escape_string($data['gruppen']);

        // 1) Upsert in Tabelle mitglieder_fragebogen
        //    Prüfen, ob bereits ein Eintrag existiert
        $checkSql = "
            SELECT ID FROM mitglieder_fragebogen
            WHERE mitgliedID = $mid
              AND jahr = $year
            LIMIT 1
        ";
        $resCheck = $conn->query($checkSql);
        if ($resCheck && $resCheck->num_rows > 0) {
            $rowF = $resCheck->fetch_assoc();
            $fid  = (int)$rowF['ID'];
            // UPDATE in mitglieder_fragebogen
            $updSql = "
                UPDATE mitglieder_fragebogen
                SET waffenID = $waffenID,
                    mannschaft = '$mannschaft',
                    gruppen = '$gruppen'
                WHERE ID = $fid
            ";
            $conn->query($updSql);
        } else {
            // INSERT in mitglieder_fragebogen
            $insSql = "
                INSERT INTO mitglieder_fragebogen (mitgliedID, jahr, waffenID, mannschaft, gruppen)
                VALUES ($mid, $year, $waffenID, '$mannschaft', '$gruppen')
            ";
            $conn->query($insSql);
            $fid = $conn->insert_id;
        }
        
        // *** Hier wird auch die Mitglieder-Tabelle aktualisiert ***
        $updMemberSql = "UPDATE mitglieder SET WaffenID = $waffenID WHERE ID = $mid";
        $conn->query($updMemberSql);

        // 2) Erweitert – in Tabelle mitglieder_fragebogen_erweitert
        if (isset($data['erweitert']) && is_array($data['erweitert'])) {
            foreach ($data['erweitert'] as $defID => $ans) {
                $defID = (int)$defID;
                $ans   = $conn->real_escape_string($ans); // 'ja' oder 'nein'

                // Upsert in mitglieder_fragebogen_erweitert
                $check2 = "
                    SELECT ID 
                    FROM mitglieder_fragebogen_erweitert
                    WHERE fragebogenID = $fid
                      AND jmdefinitionID = $defID
                    LIMIT 1
                ";
                $resC2 = $conn->query($check2);
                if ($resC2 && $resC2->num_rows > 0) {
                    $rowE = $resC2->fetch_assoc();
                    $eID  = (int)$rowE['ID'];
                    $upd2 = "
                        UPDATE mitglieder_fragebogen_erweitert
                        SET antwort = '$ans'
                        WHERE ID = $eID
                    ";
                    $conn->query($upd2);
                } else {
                    $ins2 = "
                        INSERT INTO mitglieder_fragebogen_erweitert (fragebogenID, jmdefinitionID, antwort)
                        VALUES ($fid, $defID, '$ans')
                    ";
                    $conn->query($ins2);
                }
            }
        }
    }

    $conn->commit();
    echo "Daten gespeichert.";
} catch (Exception $e) {
    $conn->rollback();
    echo "Fehler: " . $e->getMessage();
}
?>
