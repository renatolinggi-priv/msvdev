<?php
// export_js.php - CSV Export für JS-Endschiessen

session_start();

try {
    include '../dbconnect.inc.php';
} catch (Exception $e) {
    die("Datenbankverbindung fehlgeschlagen");
}

$jahr = isset($_GET['jahr']) ? intval($_GET['jahr']) : date('Y');

try {
    $sql = "SELECT 
            g.name,
            g.geburtsdatum,
            SUBSTRING_INDEX(g.name, ' ', 1) as vorname,
            SUBSTRING_INDEX(g.name, ' ', -1) as nachname,
            (SELECT zahlungsmethode FROM endstich_selection WHERE gast_id = g.id AND jahr = ? LIMIT 1) as zahlungsmethode,
            DATE_FORMAT(g.created_at, '%d.%m.%Y %H:%i') as erfasst_am,
            7500 as paket_preis
        FROM endstich_gaeste g
        WHERE g.jahr = ?
        AND g.id IN (
            SELECT DISTINCT gast_id FROM endstich_selection WHERE jahr = ? AND gast_id IS NOT NULL
        )
        ORDER BY g.name";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $jahr, $jahr, $jahr);
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
        'Nachname',
        'Vorname',
        'Geburtsdatum',
        'Alter',
        'GP11',
        'GP90',
        'Zahlungsmethode',
        'Total CHF',
        'Erfasst am'
    ], ';');
    
    // Daten schreiben
    $total_sum = 0;
    $total_js = 0;
    $total_gp11 = 0;
    $total_gp90 = 0;
    
    while ($row = $result->fetch_assoc()) {
        // Hole Munition für diesen JS
        $stmt2 = $conn->prepare("SELECT typ, anzahl, preis_cents FROM endstich_zusatz_schuss WHERE gast_id = (SELECT id FROM endstich_gaeste WHERE name = ? AND jahr = ?) AND jahr = ?");
        $stmt2->bind_param("sii", $row['name'], $jahr, $jahr);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        $gp11 = 0;
        $gp90 = 0;
        $munition_preis = 0;
        
        while ($zusatz = $result2->fetch_assoc()) {
            if ($zusatz['typ'] === 'GP11_60' || $zusatz['typ'] === 'GP11_CUSTOM') {
                $gp11 += $zusatz['anzahl'];
            } else if ($zusatz['typ'] === 'GP90_50' || $zusatz['typ'] === 'GP90_CUSTOM') {
                $gp90 += $zusatz['anzahl'];
            }
            $munition_preis += $zusatz['preis_cents'];
        }
        
        $total_preis = ($row['paket_preis'] + $munition_preis) / 100;
        
        // Berechne Alter aus Geburtsdatum
        $alter = '';
        $geburtsdatum_format = '';
        if ($row['geburtsdatum']) {
            $geb = new DateTime($row['geburtsdatum']);
            $heute = new DateTime();
            $diff = $heute->diff($geb);
            $alter = $diff->y;
            $geburtsdatum_format = $geb->format('d.m.Y');
        }
        
        fputcsv($output, [
            $row['nachname'],
            $row['vorname'],
            $geburtsdatum_format,
            $alter,
            $gp11 ?: '',
            $gp90 ?: '',
            $row['zahlungsmethode'] === 'karte' ? 'Karte' : 'Bar',
            number_format($total_preis, 2, '.', ''),
            $row['erfasst_am']
        ], ';');
        
        $total_sum += $total_preis;
        $total_js++;
        $total_gp11 += $gp11;
        $total_gp90 += $gp90;
    }
    
    // Leerzeile und Zusammenfassung
    fputcsv($output, [], ';');
    fputcsv($output, ['ZUSAMMENFASSUNG'], ';');
    fputcsv($output, ['Anzahl Jungschützen:', $total_js], ';');
    fputcsv($output, ['Total Pakete:', $total_js . ' x CHF 75.00 = CHF ' . number_format($total_js * 75, 2, '.', '')], ';');
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