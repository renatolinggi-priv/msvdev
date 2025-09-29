<?php
// test_munition_filter.php - Debug-Tool für Filter-Problem

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('Europe/Zurich');

// Include database
require_once '../dbconnect.inc.php';

// Start session for testing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>Munitionskauf Filter Debug</h2>";
echo "<pre>";

// 1. Zeige aktuelle Zeit-Informationen
echo "=== ZEIT INFORMATIONEN ===\n";
echo "Timezone: " . date_default_timezone_get() . "\n";
echo "Heute: " . date('Y-m-d') . "\n";
echo "Aktuelle Zeit: " . date('Y-m-d H:i:s') . "\n";
echo "Wochentag (N): " . date('N') . " (1=Mo, 7=So)\n";
echo "\n";

// 2. Berechne Wochengrenzen
echo "=== WOCHEN-BERECHNUNG ===\n";
$currentDayOfWeek = date('N');
$daysFromMonday = $currentDayOfWeek - 1;
$daysToSunday = 7 - $currentDayOfWeek;

echo "Tage seit Montag: $daysFromMonday\n";
echo "Tage bis Sonntag: $daysToSunday\n";

$week_start_new = date('Y-m-d', strtotime("-$daysFromMonday days"));
$week_end_new = date('Y-m-d', strtotime("+$daysToSunday days"));

echo "Montag (neue Berechnung): $week_start_new\n";
echo "Sonntag (neue Berechnung): $week_end_new\n";

// Alte Berechnung zum Vergleich
$week_start_old = date('Y-m-d', strtotime('monday this week'));
$week_end_old = date('Y-m-d', strtotime('sunday this week'));

echo "Montag (alte Berechnung): $week_start_old\n";
echo "Sonntag (alte Berechnung): $week_end_old\n";
echo "\n";

// 3. Prüfe Datenbank
echo "=== DATENBANK CHECK ===\n";
$jahr = date('Y');
$today = date('Y-m-d');

// Alle Einträge für heute
$sql = "SELECT id, kauf_datum, created_at, mitglied_id, gast_name 
        FROM munitionskauf 
        WHERE kauf_datum = '$today' AND jahr = $jahr";
$result = $conn->query($sql);

echo "Query für heute ($today):\n";
echo "SQL: $sql\n";
echo "Gefundene Einträge: " . ($result ? $result->num_rows : 'ERROR') . "\n";

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "  - ID: {$row['id']}, Datum: {$row['kauf_datum']}, Created: {$row['created_at']}\n";
    }
}
echo "\n";

// Alle Einträge diese Woche
$sql = "SELECT id, kauf_datum, created_at 
        FROM munitionskauf 
        WHERE kauf_datum BETWEEN '$week_start_new' AND '$week_end_new' 
        AND jahr = $jahr
        ORDER BY kauf_datum";
$result = $conn->query($sql);

echo "Query für diese Woche ($week_start_new bis $week_end_new):\n";
echo "Gefundene Einträge: " . ($result ? $result->num_rows : 'ERROR') . "\n";

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dayName = date('l', strtotime($row['kauf_datum']));
        echo "  - ID: {$row['id']}, Datum: {$row['kauf_datum']} ($dayName)\n";
    }
}
echo "\n";

// Alle Einträge dieses Jahr
$sql = "SELECT COUNT(*) as total, MIN(kauf_datum) as first_date, MAX(kauf_datum) as last_date 
        FROM munitionskauf 
        WHERE jahr = $jahr";
$result = $conn->query($sql);

echo "Statistik für Jahr $jahr:\n";
if ($result) {
    $stats = $result->fetch_assoc();
    echo "  - Total Einträge: {$stats['total']}\n";
    echo "  - Erster Eintrag: {$stats['first_date']}\n";
    echo "  - Letzter Eintrag: {$stats['last_date']}\n";
}
echo "\n";

// Einträge gruppiert nach Datum
$sql = "SELECT kauf_datum, COUNT(*) as count, SUM(total_preis) as total 
        FROM munitionskauf 
        WHERE jahr = $jahr 
        GROUP BY kauf_datum 
        ORDER BY kauf_datum DESC 
        LIMIT 10";
$result = $conn->query($sql);

echo "Letzte 10 Tage mit Einträgen:\n";
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dayName = date('l, d.m.Y', strtotime($row['kauf_datum']));
        $total = number_format($row['total'] / 100, 2);
        echo "  - {$row['kauf_datum']} ($dayName): {$row['count']} Einträge, CHF $total\n";
    }
} else {
    echo "  Keine Einträge gefunden!\n";
}

echo "\n=== API TESTS ===\n";
echo "</pre>";

// Test API calls
$baseUrl = "https://jahresmeisterschaft.msvwilen.ch/inc/munitionskauf/munitionskauf_api.php";

echo "<h3>Direkte API Tests:</h3>";
echo "<ul>";
echo "<li><a href='munitionskauf_api.php?action=get_bestellungen&jahr=$jahr&filter=today' target='_blank'>Filter: Heute</a></li>";
echo "<li><a href='munitionskauf_api.php?action=get_bestellungen&jahr=$jahr&filter=week' target='_blank'>Filter: Woche</a></li>";
echo "<li><a href='munitionskauf_api.php?action=get_bestellungen&jahr=$jahr&filter=month' target='_blank'>Filter: Monat</a></li>";
echo "<li><a href='munitionskauf_api.php?action=get_bestellungen&jahr=$jahr&filter=year' target='_blank'>Filter: Jahr</a></li>";
echo "</ul>";

// JavaScript Test
echo "<h3>JavaScript Filter Test:</h3>";
echo "<button onclick='testFilter(\"today\")'>Test Today</button> ";
echo "<button onclick='testFilter(\"week\")'>Test Week</button> ";
echo "<button onclick='testFilter(\"month\")'>Test Month</button> ";
echo "<button onclick='testFilter(\"year\")'>Test Year</button>";
echo "<div id='result' style='margin-top:20px; padding:10px; background:#f0f0f0;'></div>";

?>

<script>
function testFilter(filter) {
    const jahr = <?php echo $jahr; ?>;
    const url = `munitionskauf_api.php?action=get_bestellungen&jahr=${jahr}&filter=${filter}`;
    
    document.getElementById('result').innerHTML = 'Loading...';
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            console.log('API Response:', data);
            let html = `<h4>Filter: ${filter}</h4>`;
            html += `<p>Success: ${data.success}</p>`;
            html += `<p>Records found: ${data.data ? data.data.length : 0}</p>`;
            
            if (data.data && data.data.length > 0) {
                html += '<ul>';
                data.data.forEach(item => {
                    html += `<li>${item.kauf_datum} - ${item.kaeufer_name} - CHF ${(item.total_preis/100).toFixed(2)}</li>`;
                });
                html += '</ul>';
            } else {
                html += '<p>Keine Daten gefunden</p>';
            }
            
            if (data.totals) {
                html += `<p>Totals: GP11=${data.totals.gp11_total}, GP90=${data.totals.gp90_total}, CHF ${(data.totals.total_preis/100).toFixed(2)}</p>`;
            }
            
            document.getElementById('result').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('result').innerHTML = `<p style="color:red;">Error: ${err.message}</p>`;
            console.error(err);
        });
}
</script>

<?php
$conn->close();
?>
