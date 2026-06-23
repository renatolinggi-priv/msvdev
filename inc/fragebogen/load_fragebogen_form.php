<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';  // $conn

// Jahr aus GET oder Standardjahr
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// 1) Mitglieder laden (alle)
$sqlM = "SELECT ID, Vorname, Name, WaffenID FROM mitglieder WHERE Verstorben = 0 ORDER BY Name, Vorname";
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
// Hilfsfunktion: Lange Bezeichnungen abkürzen
function truncateHeader($text, $maxLen = 14) {
    if (mb_strlen($text) <= $maxLen) {
        return htmlspecialchars($text);
    }
    // Auf letztes Leerzeichen vor maxLen kürzen
    $short = mb_substr($text, 0, $maxLen);
    $lastSpace = mb_strrpos($short, ' ');
    if ($lastSpace > 6) {
        $short = mb_substr($short, 0, $lastSpace);
    }
    return '<span title="' . htmlspecialchars($text) . '" style="cursor:help;border-bottom:1px dotted #999">' . htmlspecialchars($short) . '&hellip;</span>';
}

$thead = '<tr>
    <th>Mitglied</th>
    <th>Waffe</th>
    <th>ZSMM</th>
    <th>GM</th>';
foreach ($defs as $df) {
    $thead .= '<th>' . truncateHeader($df['Bezeichnung']) . '</th>';
}
$thead .= '</tr>';

// 7) TBODY bauen
$tbody = '';
foreach ($members as $m) {
    $mid = $m['ID'];
    $fullname = htmlspecialchars($m['Name'].' '.$m['Vorname']);
    // Waffe vorselektieren, falls bereits gespeichert, ansonsten "Nehme nicht teil" (0)
    $currentWaffe = isset($fragebogenData[$mid]['waffenID']) ? $fragebogenData[$mid]['waffenID'] : 0;
    $nimmtNichtTeil = ($currentWaffe == 0) ? ' data-nimmt-nicht-teil="1"' : '';
    $row  = '<tr'.$nimmtNichtTeil.'>';
    $row .= '<td>' . $fullname . '</td>';

    // Waffe-Dropdown
    $row .= '<td><select name="fragebogen['.$mid.'][waffenID]" class="form-control">';
    // "Nehme nicht teil" Option
    $row .= '<option value="0" '.($currentWaffe == 0 ? 'selected' : '').'>Nehme nicht teil</option>';
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
          <option value="teil" '.($currentMannschaft==="teil" ? "selected" : "").'>Ja</option>
          <option value="nicht" '.($currentMannschaft==="nicht" ? "selected" : "").'>Nein</option>
          <option value="evtl" '.($currentMannschaft==="evtl" ? "selected" : "").'>Auffüllen</option>
        </select>
    </td>';

    // Gruppenmeisterschaft Dropdown – Standard: "nicht", wenn nichts gespeichert ist
    $currentGruppen = isset($fragebogenData[$mid]['gruppen']) ? $fragebogenData[$mid]['gruppen'] : 'nicht';
    $row .= '<td>
        <select name="fragebogen['.$mid.'][gruppen]" class="form-control">
          <option value="teil" '.($currentGruppen==="teil" ? "selected" : "").'>Ja</option>
          <option value="nicht" '.($currentGruppen==="nicht" ? "selected" : "").'>Nein</option>
          <option value="evtl" '.($currentGruppen==="evtl" ? "selected" : "").'>Auffüllen</option>
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

// 8) Mobile Cards bauen
$mobile_cards = '<div class="mobile-cards-scroll">';
if (empty($members)) {
    $mobile_cards .= '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Mitglieder</div></div>';
} else {
    foreach ($members as $m) {
        $mid = $m['ID'];
        $fullname = htmlspecialchars($m['Name'].' '.$m['Vorname']);

        $currentWaffe      = isset($fragebogenData[$mid]['waffenID'])  ? $fragebogenData[$mid]['waffenID']  : 0;
        $currentMannschaft = isset($fragebogenData[$mid]['mannschaft']) ? $fragebogenData[$mid]['mannschaft'] : 'nicht';
        $currentGruppen    = isset($fragebogenData[$mid]['gruppen'])    ? $fragebogenData[$mid]['gruppen']    : 'nicht';

        // Badges im Card-Header
        $mClass = $currentMannschaft === 'teil' ? 'bg-success' : ($currentMannschaft === 'evtl' ? 'bg-warning text-dark' : 'bg-danger');
        $gClass = $currentGruppen    === 'teil' ? 'bg-success' : ($currentGruppen    === 'evtl' ? 'bg-warning text-dark' : 'bg-danger');
        $mText  = $currentMannschaft === 'teil' ? 'MM ✓' : ($currentMannschaft === 'evtl' ? 'MM ?' : 'MM ✗');
        $gText  = $currentGruppen    === 'teil' ? 'GM ✓' : ($currentGruppen    === 'evtl' ? 'GM ?' : 'GM ✗');

        $nntMobile = ($currentWaffe == 0) ? ' data-nimmt-nicht-teil="1"' : '';
        $mobile_cards .= '<div class="mobile-card" data-mid="'.$mid.'"'.$nntMobile.'>';
        $mobile_cards .= '<div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">';
        $mobile_cards .= '<span class="fw-semibold">'.$fullname.'</span>';
        $mobile_cards .= '<div class="d-flex align-items-center gap-1">';
        $mobile_cards .= '<span class="badge '.$mClass.' fb-badge-mannschaft" style="font-size:0.65rem">'.$mText.'</span>';
        $mobile_cards .= '<span class="badge '.$gClass.' fb-badge-gruppen" style="font-size:0.65rem">'.$gText.'</span>';
        $mobile_cards .= '<i class="bi bi-chevron-down ms-1"></i>';
        $mobile_cards .= '</div>';
        $mobile_cards .= '</div>'; // mobile-card-header

        $mobile_cards .= '<div class="mobile-card-body">';

        // Waffe
        $mobile_cards .= '<div class="mobile-card-detail-row">';
        $mobile_cards .= '<label class="mobile-card-detail-label">Waffe</label>';
        $mobile_cards .= '<div class="mobile-card-detail-value">';
        $mobile_cards .= '<select class="form-select form-select-sm mobile-fb-select" data-mid="'.$mid.'" data-field="waffenID">';
        $mobile_cards .= '<option value="0" '.($currentWaffe == 0 ? 'selected' : '').'>Nehme nicht teil</option>';
        foreach ($waffen as $wf) {
            $sel = ($wf['ID'] == $currentWaffe) ? 'selected' : '';
            $mobile_cards .= '<option value="'.$wf['ID'].'" '.$sel.'>'.htmlspecialchars($wf['Bezeichnung']).'</option>';
        }
        $mobile_cards .= '</select></div></div>';

        // Mannschaft
        $mobile_cards .= '<div class="mobile-card-detail-row">';
        $mobile_cards .= '<label class="mobile-card-detail-label">Vereinsmannschaft</label>';
        $mobile_cards .= '<div class="mobile-card-detail-value">';
        $mobile_cards .= '<select class="form-select form-select-sm mobile-fb-select" data-mid="'.$mid.'" data-field="mannschaft">';
        $mobile_cards .= '<option value="teil" '.($currentMannschaft==='teil' ? 'selected' : '').'>Ja</option>';
        $mobile_cards .= '<option value="nicht" '.($currentMannschaft==='nicht' ? 'selected' : '').'>Nein</option>';
        $mobile_cards .= '<option value="evtl" '.($currentMannschaft==='evtl' ? 'selected' : '').'>Evtl.</option>';
        $mobile_cards .= '</select></div></div>';

        // Gruppen
        $mobile_cards .= '<div class="mobile-card-detail-row">';
        $mobile_cards .= '<label class="mobile-card-detail-label">Gruppenmeisterschaft</label>';
        $mobile_cards .= '<div class="mobile-card-detail-value">';
        $mobile_cards .= '<select class="form-select form-select-sm mobile-fb-select" data-mid="'.$mid.'" data-field="gruppen">';
        $mobile_cards .= '<option value="teil" '.($currentGruppen==='teil' ? 'selected' : '').'>Ja</option>';
        $mobile_cards .= '<option value="nicht" '.($currentGruppen==='nicht' ? 'selected' : '').'>Nein</option>';
        $mobile_cards .= '<option value="evtl" '.($currentGruppen==='evtl' ? 'selected' : '').'>Evtl.</option>';
        $mobile_cards .= '</select></div></div>';

        // Erweitert-Felder
        foreach ($defs as $df) {
            $defID         = $df['ID'];
            $currentAnswer = isset($extData[$mid][$defID]) ? $extData[$mid][$defID] : 'nein';
            $mobile_cards .= '<div class="mobile-card-detail-row">';
            $mobile_cards .= '<label class="mobile-card-detail-label">'.htmlspecialchars($df['Bezeichnung']).'</label>';
            $mobile_cards .= '<div class="mobile-card-detail-value">';
            $mobile_cards .= '<select class="form-select form-select-sm mobile-fb-select" data-mid="'.$mid.'" data-field="erweitert" data-defid="'.$defID.'">';
            $mobile_cards .= '<option value="nein" '.($currentAnswer==='nein' ? 'selected' : '').'>Nein</option>';
            $mobile_cards .= '<option value="ja"   '.($currentAnswer==='ja'   ? 'selected' : '').'>Ja</option>';
            $mobile_cards .= '</select></div></div>';
        }

        $mobile_cards .= '</div>'; // mobile-card-body
        $mobile_cards .= '</div>'; // mobile-card
    }
}
$mobile_cards .= '</div>'; // mobile-cards-scroll

// JSON ausgeben
echo json_encode([
    'thead'        => $thead,
    'tbody'        => $tbody,
    'mobile_cards' => $mobile_cards,
]);
?>
