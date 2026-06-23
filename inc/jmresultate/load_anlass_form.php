<?php
/**
 * Lädt alle Mitglieder + deren Resultat für EINEN bestimmten JM-Anlass.
 * GET-Parameter: year, jmdefinitionID
 * Rückgabe: JSON
 */
include '../config.php';

header('Content-Type: application/json; charset=utf-8');

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$jmdefID = isset($_GET['jmdefinitionID']) ? intval($_GET['jmdefinitionID']) : 0;

if ($jmdefID === 0) {
    echo json_encode(['success' => false, 'message' => 'Kein Anlass angegeben']);
    exit;
}

try {
    // JMDefinition-Details laden
    $stmt = $conn->prepare("SELECT * FROM JMDefinition WHERE ID = ? AND year = ?");
    $stmt->bind_param("ii", $jmdefID, $year);
    $stmt->execute();
    $definition = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$definition) {
        echo json_encode(['success' => false, 'message' => 'Anlass nicht gefunden']);
        exit;
    }

    // Prüfen ob Sektionsmeisterschaft (hat 2 Runden)
    $isSektionsmeisterschaft = (strpos($definition['Bezeichnung'], 'Sektionsmeisterschaft') !== false);

    // Prüfen ob readonly (Endstich, Bester Kantonalstich)
    $isReadonly = in_array($definition['Bezeichnung'], ['Endstich', 'Bester Kantonalstich']);

    // Alle aktiven Mitglieder laden
    $mitglieder = [];
    $result = $conn->query("SELECT ID, Name, Vorname FROM mitglieder WHERE status = 1 AND Verstorben = 0 ORDER BY Name ASC, Vorname ASC");
    while ($row = $result->fetch_assoc()) {
        $mitglieder[] = $row;
    }

    // Resultate laden
    $members = [];
    foreach ($mitglieder as $m) {
        $entry = [
            'mitgliedID' => (int)$m['ID'],
            'name' => $m['Name'] . ' ' . $m['Vorname'],
            'punkte' => null,
            'punkte_runde1' => null,
            'punkte_runde2' => null,
            'status' => null   // 'entwurf' = vom Mitglied gemeldet, 'freigegeben' = vom Vorstand bestaetigt
        ];

        if ($isReadonly) {
            // Berechnete Werte laden
            if ($definition['Bezeichnung'] === 'Endstich') {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(Schuss1+Schuss2+Schuss3+Schuss4+Schuss5+Schuss6+Schuss7+Schuss8+Schuss9+Schuss10),0) AS Punkte FROM endstich WHERE Jahr=? AND MitgliedID=?");
                $stmt->bind_param("ii", $year, $m['ID']);
            } else { // Bester Kantonalstich
                $stmt = $conn->prepare("SELECT GREATEST(COALESCE(Passe1,0),COALESCE(Passe2,0),COALESCE(Passe3,0),COALESCE(Passe4,0),COALESCE(Passe5,0)) AS Punkte FROM kantiresultate WHERE Jahr=? AND MitgliedID=?");
                $stmt->bind_param("ii", $year, $m['ID']);
            }
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $entry['punkte'] = ($r && $r['Punkte'] != 0) ? (int)$r['Punkte'] : null;
        } elseif ($isSektionsmeisterschaft) {
            // Runde 1
            $stmt = $conn->prepare("SELECT Punkte FROM jmresultate WHERE mitgliederID=? AND jmdefinitionID=? AND Info='runde 1'");
            $stmt->bind_param("ii", $m['ID'], $jmdefID);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $entry['punkte_runde1'] = $r ? $r['Punkte'] : null;

            // Runde 2
            $stmt = $conn->prepare("SELECT Punkte FROM jmresultate WHERE mitgliederID=? AND jmdefinitionID=? AND Info='runde 2'");
            $stmt->bind_param("ii", $m['ID'], $jmdefID);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $entry['punkte_runde2'] = $r ? $r['Punkte'] : null;
        } else {
            // Normaler Anlass
            $stmt = $conn->prepare("SELECT Punkte, status FROM jmresultate WHERE mitgliederID=? AND jmdefinitionID=?");
            $stmt->bind_param("ii", $m['ID'], $jmdefID);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $entry['punkte'] = $r ? $r['Punkte'] : null;
            $entry['status'] = $r ? ($r['status'] ?? null) : null;
        }

        $members[] = $entry;
    }

    echo json_encode([
        'success' => true,
        'definition' => [
            'id' => (int)$definition['ID'],
            'bezeichnung' => $definition['Bezeichnung'],
            'maxpunkte' => (int)$definition['Maxpunkte'],
            'streicher' => (bool)$definition['Streicher'],
            'isSektionsmeisterschaft' => $isSektionsmeisterschaft,
            'isReadonly' => $isReadonly
        ],
        'members' => $members,
        'totalMembers' => count($members),
        'filledCount' => count(array_filter($members, function($m) use ($isSektionsmeisterschaft) {
            if ($isSektionsmeisterschaft) {
                return $m['punkte_runde1'] !== null || $m['punkte_runde2'] !== null;
            }
            return $m['punkte'] !== null;
        }))
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
