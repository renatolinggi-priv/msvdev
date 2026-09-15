<?php
// load_fragebogen_form.php – Antwort-Tabelle (thead/tbody) und Mobile-Cards eines Jahres als JSON
require_once '../config.php';  // $conn
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');

header('Content-Type: application/json; charset=utf-8');

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// 1) Mitglieder
$members = [];
$res = $conn->query("SELECT ID, Vorname, Name, WaffenID FROM mitglieder WHERE Verstorben = 0 ORDER BY Name, Vorname");
while ($res && ($row = $res->fetch_assoc())) $members[] = $row;

// 2) Erweiterte Fragen (JMDefinition.Erweitert = 1) des Jahres
$defs = [];
$stmt = $conn->prepare("SELECT ID, Bezeichnung FROM JMDefinition WHERE year = ? AND Erweitert = 1 ORDER BY Reihenfolge");
$stmt->bind_param('i', $year);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $defs[] = $row;
$stmt->close();

// 3) Waffen
$waffen = [];
$res = $conn->query("SELECT ID, Bezeichnung FROM Waffen ORDER BY Bezeichnung");
while ($res && ($row = $res->fetch_assoc())) $waffen[] = $row;

// 4) Gespeicherte Antworten
$fragebogenData = []; // [mitgliedID] => Zeile
$stmt = $conn->prepare("SELECT ID, mitgliedID, waffenID, mannschaft, gruppen FROM mitglieder_fragebogen WHERE jahr = ?");
$stmt->bind_param('i', $year);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $fragebogenData[(int)$row['mitgliedID']] = $row;
$stmt->close();

// 5) Erweiterte Antworten
$extData = []; // [mitgliedID][jmdefinitionID] => antwort
$stmt = $conn->prepare("SELECT fe.jmdefinitionID, fe.antwort, fb.mitgliedID
                        FROM mitglieder_fragebogen_erweitert fe
                        JOIN mitglieder_fragebogen fb ON fe.fragebogenID = fb.ID
                        WHERE fb.jahr = ?");
$stmt->bind_param('i', $year);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $extData[(int)$row['mitgliedID']][(int)$row['jmdefinitionID']] = $row['antwort'];
$stmt->close();

// Lange Bezeichnungen im Tabellenkopf kuerzen (Volltext als Tooltip)
function truncateHeader(string $text, int $maxLen = 14): string {
    if (mb_strlen($text) <= $maxLen) return htmlspecialchars($text);
    $short = mb_substr($text, 0, $maxLen);
    $lastSpace = mb_strrpos($short, ' ');
    if ($lastSpace > 6) $short = mb_substr($short, 0, $lastSpace);
    return '<span class="fb-th-hint" data-tooltip="' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($short) . '&hellip;</span>';
}

function optionen(array $pairs, string $current): string {
    $html = '';
    foreach ($pairs as $val => $label) {
        $html .= '<option value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"' . ($current === (string)$val ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
    }
    return $html;
}

$teilnahme = ['teil' => 'Ja', 'nicht' => 'Nein', 'evtl' => 'Auffüllen'];
$janein    = ['nein' => 'Nein', 'ja' => 'Ja'];

// 6) THEAD
$thead = '<tr><th>Mitglied</th><th>Waffe</th><th>ZSMM</th><th>GM</th>';
foreach ($defs as $df) $thead .= '<th>' . truncateHeader($df['Bezeichnung']) . '</th>';
$thead .= '</tr>';

// 7) TBODY
$tbody = '';
foreach ($members as $m) {
    $mid      = (int)$m['ID'];
    $fullname = htmlspecialchars($m['Name'] . ' ' . $m['Vorname']);
    $fb       = $fragebogenData[$mid] ?? null;
    $currentWaffe      = $fb ? (int)$fb['waffenID'] : 0; // 0 = nimmt nicht teil
    $currentMannschaft = $fb ? (string)$fb['mannschaft'] : 'nicht';
    $currentGruppen    = $fb ? (string)$fb['gruppen'] : 'nicht';

    $waffenOpts = ['0' => 'Nehme nicht teil'];
    foreach ($waffen as $wf) $waffenOpts[(string)$wf['ID']] = $wf['Bezeichnung'];

    $row  = '<tr' . ($currentWaffe === 0 ? ' data-nimmt-nicht-teil="1"' : '') . '>';
    $row .= '<td>' . $fullname . '</td>';
    $row .= '<td><select name="fragebogen[' . $mid . '][waffenID]" class="form-select form-select-sm" aria-label="Waffe ' . $fullname . '">' . optionen($waffenOpts, (string)$currentWaffe) . '</select></td>';
    $row .= '<td><select name="fragebogen[' . $mid . '][mannschaft]" class="form-select form-select-sm" aria-label="Mannschaft ' . $fullname . '">' . optionen($teilnahme, $currentMannschaft) . '</select></td>';
    $row .= '<td><select name="fragebogen[' . $mid . '][gruppen]" class="form-select form-select-sm" aria-label="Gruppen ' . $fullname . '">' . optionen($teilnahme, $currentGruppen) . '</select></td>';
    foreach ($defs as $df) {
        $defID = (int)$df['ID'];
        $row .= '<td><select name="fragebogen[' . $mid . '][erweitert][' . $defID . ']" class="form-select form-select-sm" aria-label="' . htmlspecialchars($df['Bezeichnung'], ENT_QUOTES, 'UTF-8') . ' ' . $fullname . '">' . optionen($janein, (string)($extData[$mid][$defID] ?? 'nein')) . '</select></td>';
    }
    $row .= '</tr>';
    $tbody .= $row;
}

// 8) Mobile Cards
$mobile_cards = '<div class="mobile-cards-scroll">';
if (!$members) {
    $mobile_cards .= '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Mitglieder gefunden</div></div>';
}
foreach ($members as $m) {
    $mid      = (int)$m['ID'];
    $fullname = htmlspecialchars($m['Name'] . ' ' . $m['Vorname']);
    $fb       = $fragebogenData[$mid] ?? null;
    $currentWaffe      = $fb ? (int)$fb['waffenID'] : 0;
    $currentMannschaft = $fb ? (string)$fb['mannschaft'] : 'nicht';
    $currentGruppen    = $fb ? (string)$fb['gruppen'] : 'nicht';

    $badge = static function (string $prefix, string $v): string {
        $cls  = $v === 'teil' ? 'bg-success' : ($v === 'evtl' ? 'bg-warning text-dark' : 'bg-danger');
        $mark = $v === 'teil' ? '✓' : ($v === 'evtl' ? '?' : '✗');
        return '<span class="badge ' . $cls . ' fb-badge-' . ($prefix === 'MM' ? 'mannschaft' : 'gruppen') . '" style="font-size:0.65rem">' . $prefix . ' ' . $mark . '</span>';
    };

    $waffenOpts = ['0' => 'Nehme nicht teil'];
    foreach ($waffen as $wf) $waffenOpts[(string)$wf['ID']] = $wf['Bezeichnung'];
    $teilnahmeMobil = ['teil' => 'Ja', 'nicht' => 'Nein', 'evtl' => 'Evtl.'];

    $mobile_cards .= '<div class="mobile-card" data-mid="' . $mid . '"' . ($currentWaffe === 0 ? ' data-nimmt-nicht-teil="1"' : '') . '>';
    $mobile_cards .= '<div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)"><span class="fw-semibold">' . $fullname . '</span>'
                   . '<div class="d-flex align-items-center gap-1">' . $badge('MM', $currentMannschaft) . $badge('GM', $currentGruppen) . '<i class="bi bi-chevron-down ms-1"></i></div></div>';
    $mobile_cards .= '<div class="mobile-card-body">';

    $detail = static function (string $label, string $select): string {
        return '<div class="mobile-card-detail-row"><label class="mobile-card-detail-label">' . $label . '</label><div class="mobile-card-detail-value">' . $select . '</div></div>';
    };
    $mobile_cards .= $detail('Waffe', '<select class="form-select form-select-sm mobile-fb-select" data-mid="' . $mid . '" data-field="waffenID">' . optionen($waffenOpts, (string)$currentWaffe) . '</select>');
    $mobile_cards .= $detail('Vereinsmannschaft', '<select class="form-select form-select-sm mobile-fb-select" data-mid="' . $mid . '" data-field="mannschaft">' . optionen($teilnahmeMobil, $currentMannschaft) . '</select>');
    $mobile_cards .= $detail('Gruppenmeisterschaft', '<select class="form-select form-select-sm mobile-fb-select" data-mid="' . $mid . '" data-field="gruppen">' . optionen($teilnahmeMobil, $currentGruppen) . '</select>');
    foreach ($defs as $df) {
        $defID = (int)$df['ID'];
        $mobile_cards .= $detail(htmlspecialchars($df['Bezeichnung']), '<select class="form-select form-select-sm mobile-fb-select" data-mid="' . $mid . '" data-field="erweitert" data-defid="' . $defID . '">' . optionen($janein, (string)($extData[$mid][$defID] ?? 'nein')) . '</select>');
    }
    $mobile_cards .= '</div></div>';
}
$mobile_cards .= '</div>';

echo json_encode([
    'success'      => true,
    'year'         => $year,
    'thead'        => $thead,
    'tbody'        => $tbody,
    'mobile_cards' => $mobile_cards,
]);
