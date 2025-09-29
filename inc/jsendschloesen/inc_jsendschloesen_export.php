<?php
/**
 * export.php
 * Exportiert die Jungschützen-Daten als CSV
 */

session_start();

try {
    include '../dbconnect.inc.php';
} catch (Exception $e) {
    die("Datenbankverbindung fehlgeschlagen");
}

$jahr = isset($_GET['jahr']) ? intval($_GET['jahr']) : date('Y');

try {
    $sql = "SELECT 
            vorname, nachname, jahrgang, verein, lizenz_nr,
            CASE WHEN paket_geloest = 1 THEN 'Ja' ELSE 'Nein' END as paket_status,
            munition_gp11, munition_gp90, total_preis, bemerkung,
            DATE_FORMAT(created_at, '%d.%m.%Y %H:%i') as erfasst_am
            FROM jsendschloesen_gaeste 
            WHERE jahr = ? 
            ORDER BY nachname ASC, vorname ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $jahr);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // CSV-Header
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="JS_Endschloesen_' . $jahr . '_' . date('Y-m-d') . '.csv"');
    
    // BOM für Excel UTF-8 Erkennung
    echo "\xEF\xBB\xBF";
    
    // Output stream öffnen
    $output = fopen('php://output', 'w');
    
    // Header schreiben
    fputcsv($output, [
        'Vorname',
        'Nachname', 
        'Jahrgang',
        'Verein',
        'Lizenz-Nr',
        'Paket gelöst',
        'GP11',
        'GP90',
        'Total CHF',
        'Bemerkung',
        'Erfasst am'
    ], ';');
    
    // Daten schreiben
    $total_sum = 0;
    $total_guests = 0;
    $total_gp11 = 0;
    $total_gp90 = 0;
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['vorname'],
            $row['nachname'],
            $row['jahrgang'],
            $row['verein'],
            $row['lizenz_nr'],
            $row['paket_status'],
            $row['munition_gp11'],
            $row['munition_gp90'],
            number_format($row['total_preis'], 2, '.', ''),
            $row['bemerkung'],
            $row['erfasst_am']
        ], ';');
        
        $total_sum += $row['total_preis'];
        $total_guests++;
        $total_gp11 += $row['munition_gp11'];
        $total_gp90 += $row['munition_gp90'];
    }
    
    // Leerzeile und Zusammenfassung
    fputcsv($output, [], ';');
    fputcsv($output, ['ZUSAMMENFASSUNG'], ';');
    fputcsv($output, ['Anzahl Jungschützen:', $total_guests], ';');
    fputcsv($output, ['Total GP11:', $total_gp11], ';');
    fputcsv($output, ['Total GP90:', $total_gp90], ';');
    fputcsv($output, ['Gesamtbetrag CHF:', number_format($total_sum, 2, '.', '')], ';');
    
    fclose($output);
    
} catch (Exception $e) {
    header('Content-Type: text/plain');
    echo "Fehler beim Export: " . $e->getMessage();
} finally {
    $conn->close();
}
?>
