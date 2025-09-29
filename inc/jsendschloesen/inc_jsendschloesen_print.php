<?php
/**
 * print.php
 * Druckansicht für Jungschützen-Liste
 */

session_start();

try {
    include '../dbconnect.inc.php';
} catch (Exception $e) {
    die("Datenbankverbindung fehlgeschlagen");
}

$jahr = isset($_GET['jahr']) ? intval($_GET['jahr']) : date('Y');

try {
    $sql = "SELECT * FROM jsendschloesen_gaeste 
            WHERE jahr = ? 
            ORDER BY nachname ASC, vorname ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $jahr);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $guests = [];
    $total_sum = 0;
    $total_gp11 = 0;
    $total_gp90 = 0;
    
    while ($row = $result->fetch_assoc()) {
        $guests[] = $row;
        $total_sum += $row['total_preis'];
        $total_gp11 += $row['munition_gp11'];
        $total_gp90 += $row['munition_gp90'];
    }
    
} catch (Exception $e) {
    die("Fehler beim Laden der Daten");
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JS-Endschloesen <?php echo $jahr; ?> - Drucken</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            @page { margin: 1cm; }
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.4;
        }
        
        h1 {
            font-size: 18pt;
            margin-bottom: 10px;
        }
        
        .header-info {
            margin-bottom: 20px;
            padding: 10px;
            background: #f5f5f5;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th {
            background: #333;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 11pt;
        }
        
        td {
            border-bottom: 1px solid #ddd;
            padding: 6px;
            font-size: 10pt;
        }
        
        tr:hover {
            background: #f5f5f5;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .summary {
            margin-top: 20px;
            padding: 15px;
            background: #f0f0f0;
            border: 2px solid #333;
        }
        
        .summary h3 {
            margin-top: 0;
        }
        
        .print-date {
            text-align: right;
            color: #666;
            font-size: 10pt;
            margin-bottom: 10px;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 6px;
            background: #28a745;
            color: white;
            border-radius: 3px;
            font-size: 9pt;
        }
        
        .badge-warning {
            background: #ffc107;
            color: #333;
        }
        
        button {
            padding: 10px 20px;
            font-size: 14pt;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
        }
        
        button:hover {
            background: #0056b3;
        }
        
        .no-print {
            margin-bottom: 20px;
            padding: 10px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">🖨️ Drucken</button>
        <button onclick="window.close()">❌ Schliessen</button>
    </div>
    
    <div class="print-date">
        Gedruckt am: <?php echo date('d.m.Y H:i'); ?> Uhr
    </div>
    
    <h1>Jungschützen Endschloesen <?php echo $jahr; ?></h1>
    
    <div class="header-info">
        <strong>MSV Jegenstorf</strong><br>
        Festes Paket: Endstich, Schwini Passe 1+2, Zabigstich<br>
        Paketpreis: CHF 75.00 | Munition: CHF 1.65 pro Schuss
    </div>
    
    <?php if (count($guests) == 0): ?>
        <p>Keine Jungschützen erfasst für das Jahr <?php echo $jahr; ?></p>
    <?php else: ?>
        
        <table>
            <thead>
                <tr>
                    <th width="5%">Nr.</th>
                    <th width="20%">Name</th>
                    <th width="20%">Vorname</th>
                    <th width="8%" class="text-center">Jg.</th>
                    <th width="15%">Verein</th>
                    <th width="10%" class="text-center">Lizenz</th>
                    <th width="8%" class="text-center">Paket</th>
                    <th width="6%" class="text-center">GP11</th>
                    <th width="6%" class="text-center">GP90</th>
                    <th width="10%" class="text-right">CHF</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $nr = 1;
                foreach ($guests as $guest): 
                ?>
                    <tr>
                        <td><?php echo $nr++; ?></td>
                        <td><?php echo htmlspecialchars($guest['nachname']); ?></td>
                        <td><?php echo htmlspecialchars($guest['vorname']); ?></td>
                        <td class="text-center"><?php echo $guest['jahrgang']; ?></td>
                        <td><?php echo htmlspecialchars($guest['verein'] ?: '-'); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($guest['lizenz_nr'] ?: '-'); ?></td>
                        <td class="text-center">
                            <?php if ($guest['paket_geloest']): ?>
                                <span class="badge">✓</span>
                            <?php else: ?>
                                <span class="badge badge-warning">○</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo $guest['munition_gp11'] ?: '-'; ?></td>
                        <td class="text-center"><?php echo $guest['munition_gp90'] ?: '-'; ?></td>
                        <td class="text-right">
                            <strong><?php echo number_format($guest['total_preis'], 2, '.', "'"); ?></strong>
                        </td>
                    </tr>
                    <?php if (!empty($guest['bemerkung'])): ?>
                    <tr>
                        <td colspan="10" style="padding-left: 50px; font-size: 9pt; color: #666;">
                            Bemerkung: <?php echo htmlspecialchars($guest['bemerkung']); ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="summary">
            <h3>Zusammenfassung</h3>
            <table style="width: auto;">
                <tr>
                    <td style="padding-right: 30px;"><strong>Anzahl Jungschützen:</strong></td>
                    <td><?php echo count($guests); ?></td>
                </tr>
                <tr>
                    <td><strong>Pakete gelöst:</strong></td>
                    <td><?php echo count(array_filter($guests, function($g) { return $g['paket_geloest']; })); ?></td>
                </tr>
                <tr>
                    <td><strong>Munition GP11 Total:</strong></td>
                    <td><?php echo $total_gp11; ?> Schuss</td>
                </tr>
                <tr>
                    <td><strong>Munition GP90 Total:</strong></td>
                    <td><?php echo $total_gp90; ?> Schuss</td>
                </tr>
                <tr style="border-top: 2px solid #333; font-size: 14pt;">
                    <td style="padding-top: 10px;"><strong>Gesamtbetrag:</strong></td>
                    <td style="padding-top: 10px;"><strong>CHF <?php echo number_format($total_sum, 2, '.', "'"); ?></strong></td>
                </tr>
            </table>
        </div>
        
    <?php endif; ?>
    
    <script>
        // Automatisches Drucken beim Laden (optional)
        // window.addEventListener('load', () => window.print());
    </script>
</body>
</html>

<?php $conn->close(); ?>
