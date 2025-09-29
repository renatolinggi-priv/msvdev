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
$schwiniFields = ['P1Schuss1', 'P1Schuss2', 'P1Schuss3', 'P1Schuss4', 'P1Schuss5', 'P1Schuss6', 'P2Schuss1', 'P2Schuss2', 'P2Schuss3', 'P2Schuss4', 'P2Schuss5', 'P2Schuss6'];
$zabigFields = ['ZSchuss1', 'ZSchuss2', 'ZSchuss3', 'ZSchuss4', 'ZSchuss5', 'ZSchuss6'];
// Kunst und Glück entfernt, Ansage aus zabigFields entfernt

// Daten laden und ins Antwortarray einfügen - NUR die benötigten Tabellen
$response = array_merge(
    loadData($conn, 'endstich_jung', $endstichFields, $jungschuetzeID, $year),
    loadData($conn, 'schwini_jung', $schwiniFields, $jungschuetzeID, $year),
    loadData($conn, 'zabig_jung', $zabigFields, $jungschuetzeID, $year)
);

echo json_encode($response);

$conn->close();
?>
