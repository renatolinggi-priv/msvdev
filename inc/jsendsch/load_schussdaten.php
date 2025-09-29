<?php
include '../config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$jungschuetzeID = isset($_GET['jungschuetzeID']) ? intval($_GET['jungschuetzeID']) : 0;
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

if ($jungschuetzeID == 0) {
    die(json_encode(['error' => 'Ungültige JungschuetzeID']));
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$response = [];

// Funktion zum Laden der Daten aus einer Tabelle mit Jahr
function loadData($conn, $table, $fields, $jungschuetzeID, $year) {
    $fieldList = implode(", ", $fields);
    $stmt = $conn->prepare("SELECT $fieldList FROM $table WHERE JungschuetzeID = ? AND Jahr = ?");
    $stmt->bind_param("ii", $jungschuetzeID, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return [];
}

// Felder für die Tabellen - NUR die, die wir im Modal brauchen
$endstichFields = ['Schuss1', 'Schuss2', 'Schuss3', 'Schuss4', 'Schuss5', 'Schuss6', 'Schuss7', 'Schuss8', 'Schuss9', 'Schuss10', 'Tiefschuss', 'AbsendenAnmeldung'];
// Nur P1 Felder laden, da wir nur eine Schwini-Passe haben (6 Schüsse)
$schwiniFieldsDB = ['P1Schuss1', 'P1Schuss2', 'P1Schuss3', 'P1Schuss4', 'P1Schuss5', 'P1Schuss6'];
$zabigFields = ['ZSchuss1', 'ZSchuss2', 'ZSchuss3', 'ZSchuss4', 'ZSchuss5', 'ZSchuss6'];

// Daten laden
$endstichData = loadData($conn, 'endstich_jung', $endstichFields, $jungschuetzeID, $year);
$schwiniData = loadData($conn, 'schwini_jung', $schwiniFieldsDB, $jungschuetzeID, $year);
$zabigData = loadData($conn, 'zabig_jung', $zabigFields, $jungschuetzeID, $year);

// Schwini-Daten umwandeln für die UI (P1Schuss -> SchwiniSchuss)
$schwiniMapped = [];
for ($i = 1; $i <= 6; $i++) {
    if (isset($schwiniData['P1Schuss' . $i])) {
        $schwiniMapped['SchwiniSchuss' . $i] = $schwiniData['P1Schuss' . $i];
    }
}

// Response zusammenbauen
$response = array_merge(
    $endstichData,
    $schwiniMapped,
    $zabigData
);

echo json_encode($response);

$conn->close();
?>
