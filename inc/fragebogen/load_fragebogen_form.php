<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';  // $conn

// Jahr aus GET oder Standardjahr
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// 1) Mitglieder laden (alle)
$sqlM = "SELECT ID, Vorname, Name, WaffenID FROM mitglieder ORDER BY Name, Vorname";
$resM = $conn->query($sqlM);
$members = [];
while ($row = $resM->fetch_assoc()) {
    $members[] = $row;
}

// 2) Erweitert=1 Definitionen laden
$sqlD = "
  SELECT ID, Bezeichnung 
  FROM JMDefinition 
  WHERE year = $year 
    AND Erweitert = 1
  ORDER BY Reihenfolge
";
$resD = $conn->query($sqlD);
$defs = [];
while($rd = $resD->fetch_assoc()) {
    $defs[] = $rd;
}

// 3) Waffen laden (für Dropdown)
$sqlW = "SELECT ID, Bezeichnung FROM Waffen ORDER BY Bezeichnung";
$resW = $conn->query($sqlW);
$waffen = [];
while($rw = $resW->fetch_assoc()) {
    $waffen[] = $rw;
}

// 4) Bereits gespeicherte Antworten aus mitglieder_fragebogen laden
$sqlFB = "SELECT * FROM mitglieder_fragebogen WHERE jahr = $year";
$resFB = $conn->query($sqlFB);
$fragebogenData = []; // indexiert nach mitgliedID
while ($row = $resFB->fetch_assoc()) {
    // Wir erwarten Felder: ID, mitgliedID, waffenID, mannschaft, gruppen
    $fragebogenData[$row['mitgliedID']] = $row;
}

// 5) Erweiterte Antworten laden (verbunden mit mitglieder_fragebogen)
//    Wir holen alle Einträge für das gewählte Jahr und indexieren nach mitgliedID und jmdefinitionID
$sqlExt = "
    SELECT fe.*, fb.mitgliedID 
    FROM mitglieder_fragebogen_erweitert fe
    JOIN mitglieder_fragebogen fb ON fe.fragebogenID = fb.ID
    WHERE fb.jahr = $year
";
$resExt = $conn->query($sqlExt);
$extData = []; // indexiert: $extData[mitgliedID][jmdefinitionID] = antwort
while ($row = $resExt->fetch_assoc()) {
    $mID = $row['mitgliedID'];
    $jmdefID = $row['jmdefinitionID'];
    $extData[$mID][$jmdefID] = $row['antwort'];
}

// 6) THEAD bauen
$thead = '<tr>
    <th>Mitglied</th>
    <th>Waffe</th>
    <th>ZSMM</th>
    <th>GM</th>';
foreach ($defs as $df) {
    $thead .= '<th>' . htmlspecialchars($df['Bezeichnung']) . '</th>';
}
$thead .= '</tr>';

// 7) TBODY bauen
$tbody = '';
foreach ($members as $m) {
    $mid = $m['ID'];
    $fullname = htmlspecialchars($m['Name'].' '.$m['Vorname']);
    $row  = '<tr>';
    $row .= '<td>' . $fullname . '</td>';

    // Waffe-Dropdown
    $row .= '<td><select name="fragebogen['.$mid.'][waffenID]" class="form-control">';
    // Waffe vorselektieren, falls bereits gespeichert, ansonsten Standardwert aus der Mitgliedstabelle
    $currentWaffe = isset($fragebogenData[$mid]['waffenID']) ? $fragebogenData[$mid]['waffenID'] : $m['WaffenID'];
    foreach ($waffen as $wf) {
        $wfID  = $wf['ID'];
        $wBez  = htmlspecialchars($wf['Bezeichnung']);
        $sel   = ($wfID == $currentWaffe) ? 'selected' : '';
        $row .= '<option value="'.$wfID.'" '.$sel.'>'.$wBez.'</option>';
    }
    $row .= '</select></td>';

    // Mannschaftsmeisterschaft Dropdown – Standard: "nicht", wenn nichts gespeichert ist
    $currentMannschaft = isset($fragebogenData[$mid]['mannschaft']) ? $fragebogenData[$mid]['mannschaft'] : 'nicht';
    $row .= '<td>
        <select name="fragebogen['.$mid.'][mannschaft]" class="form-control">
          <option value="teil" '.($currentMannschaft==="teil" ? "selected" : "").'>Ich nehme teil</option>
          <option value="nicht" '.($currentMannschaft==="nicht" ? "selected" : "").'>Ich nehme nicht teil</option>
          <option value="evtl" '.($currentMannschaft==="evtl" ? "selected" : "").'>Ich nehme nur teil, wenn Gruppe füllt</option>
        </select>
    </td>';

    // Gruppenmeisterschaft Dropdown – Standard: "nicht", wenn nichts gespeichert ist
    $currentGruppen = isset($fragebogenData[$mid]['gruppen']) ? $fragebogenData[$mid]['gruppen'] : 'nicht';
    $row .= '<td>
        <select name="fragebogen['.$mid.'][gruppen]" class="form-control">
          <option value="teil" '.($currentGruppen==="teil" ? "selected" : "").'>Ich nehme teil</option>
          <option value="nicht" '.($currentGruppen==="nicht" ? "selected" : "").'>Ich nehme nicht teil</option>
          <option value="evtl" '.($currentGruppen==="evtl" ? "selected" : "").'>Ich nehme nur teil, wenn Gruppe füllt</option>
        </select>
    </td>';

    // Für jede Erweitert=1 Definition: Dropdown Ja/Nein; Standard ist "nein"
    foreach ($defs as $df) {
        $defID = $df['ID'];
        $currentAnswer = isset($extData[$mid][$defID]) ? $extData[$mid][$defID] : 'nein';
        $row .= '<td>
            <select name="fragebogen['.$mid.'][erweitert]['.$defID.']" class="form-control">
              <option value="nein" '.($currentAnswer==="nein" ? "selected" : "").'>Nein</option>
              <option value="ja" '.($currentAnswer==="ja" ? "selected" : "").'>Ja</option>
            </select>
        </td>';
    }

    $row .= '</tr>';
    $tbody .= $row;
}

// JSON ausgeben
echo json_encode([
    'thead' => $thead,
    'tbody' => $tbody
]);
?>
