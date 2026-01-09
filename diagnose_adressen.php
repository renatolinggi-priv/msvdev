<?php
/**
 * Diagnose-Script: Zeigt wie Adressen im ICS interpretiert werden
 */
include 'inc/config.php';

function parseCoordinates($text) {
    $text = trim($text);
    // Akzeptiert: Komma, Semikolon, Schrägstrich als Trennzeichen
    if (preg_match('/^(-?\d+\.\d+)\s*[,;\/]\s*(-?\d+\.\d+)$/', $text, $matches)) {
        $lat = floatval($matches[1]);
        $lon = floatval($matches[2]);
        if ($lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
            return ['lat' => $lat, 'lon' => $lon];
        }
    }
    return false;
}

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$sql = "SELECT ID, Bezeichnung, Adresse FROM JMDefinition WHERE year = ? AND hidden = 0 ORDER BY Reihenfolge";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>ICS Adress-Diagnose</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; }
        .geo { color: #059669; font-weight: 600; }
        .location { color: #2563eb; }
        .empty { color: #9ca3af; font-style: italic; }
        code { background: #f1f5f9; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.9rem; }
        h1 { color: #1e293b; }
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600; }
        .badge-geo { background: #d1fae5; color: #065f46; }
        .badge-loc { background: #dbeafe; color: #1e40af; }
        .badge-none { background: #f3f4f6; color: #6b7280; }
    </style>
</head>
<body>
    <h1>🗺️ ICS Adress-Diagnose (<?= $year ?>)</h1>
    <p>Zeigt, wie jede Adresse im Kalender-Export interpretiert wird.</p>
    
    <table>
        <thead>
            <tr>
                <th>Anlass</th>
                <th>Adresse (Eingabe)</th>
                <th>ICS-Ausgabe</th>
            </tr>
        </thead>
        <tbody>
<?php while ($row = $result->fetch_assoc()): 
    $adresse = $row['Adresse'];
    $coords = parseCoordinates($adresse);
?>
            <tr>
                <td><?= htmlspecialchars($row['Bezeichnung']) ?></td>
                <td>
                    <?php if (empty($adresse)): ?>
                        <span class="empty">– keine –</span>
                    <?php else: ?>
                        <?= htmlspecialchars($adresse) ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (empty($adresse)): ?>
                        <span class="badge badge-none">Kein Ort</span>
                    <?php elseif ($coords): ?>
                        <?php 
                        $lat = number_format($coords['lat'], 6, '.', '');
                        $lon = number_format($coords['lon'], 6, '.', '');
                        ?>
                        <span class="badge badge-geo">GEO</span>
                        <code class="geo">GEO:<?= $lat ?>;<?= $lon ?></code>
                    <?php else: ?>
                        <span class="badge badge-loc">LOCATION</span>
                        <code class="location">LOCATION:<?= htmlspecialchars($adresse) ?></code>
                    <?php endif; ?>
                </td>
            </tr>
<?php endwhile; ?>
        </tbody>
    </table>
    
    <h2 style="margin-top: 2rem;">📋 Beispiel-Formate für Koordinaten</h2>
    <ul>
        <li><code>47.2034567, 8.7812345</code> ✅ wird erkannt</li>
        <li><code>47.2034567,8.7812345</code> ✅ wird erkannt</li>
        <li><code>47.2034567; 8.7812345</code> ✅ wird erkannt</li>
        <li><code>47.3166/8.8206</code> ✅ wird erkannt</li>
        <li><code>Eichenstrasse 18, 8808 Pfäffikon</code> → normale Adresse</li>
    </ul>
</body>
</html>
<?php
$stmt->close();
$conn->close();
