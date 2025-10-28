<?php
/**
 * Test-Script: Detaillierte Prüfung der Berechnung für Mitglied 112140
 * 
 * Zeigt genau wie die neue Logik mit Streichern und 0-Werten umgeht
 */

include '../config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$jahr = 2025;
$mitgliedID = 112140;

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Test: Detaillierte Berechnung für Mitglied 112140 (Jahr $jahr)</h2>";
echo "<style>
    .gestrichen { background-color: #ffcccc; }
    .gezaehlt { background-color: #ccffcc; }
    .nicht-teilgenommen { background-color: #ffffcc; }
    table { border-collapse: collapse; margin: 20px 0; }
    th, td { padding: 8px; border: 1px solid #ccc; text-align: left; }
    th { background-color: #f0f0f0; }
    .total { font-weight: bold; border-top: 2px solid #000; }
</style>";
echo "<pre>";

// =====================================================
// 1. STREICHER=0 (Fix-Punkte)
// =====================================================
echo "\n=== TEIL 1: STREICHER=0 (Fix-Punkte) ===\n";
echo "Diese Punkte zählen immer zum Total, keine Streicher.\n\n";

$fixSql = "
    SELECT jd.Bezeichnung, jd.ID, MAX(jr.Punkte) AS Punkte
    FROM jmresultate jr
    INNER JOIN JMDefinition jd ON jd.ID = jr.jmdefinitionID
    WHERE jr.mitgliederID = ? 
      AND jd.Streicher = 0 
      AND jd.Info = 0 
      AND jd.Erweitert = 0
      AND jd.year = ?
    GROUP BY jr.jmdefinitionID, jd.Bezeichnung, jd.ID
    ORDER BY jd.Reihenfolge
";

$stmt = $conn->prepare($fixSql);
$stmt->bind_param("ii", $mitgliedID, $jahr);
$stmt->execute();
$result = $stmt->get_result();

$totalFix = 0;
echo "<table>";
echo "<tr><th>ID</th><th>Bezeichnung</th><th>Punkte</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo sprintf("<tr><td>%d</td><td>%s</td><td>%d</td></tr>", 
        $row['ID'], $row['Bezeichnung'], $row['Punkte']);
    $totalFix += $row['Punkte'];
}
echo sprintf("<tr class='total'><td colspan='2'>SUMME Fix-Punkte</td><td>%d</td></tr>", $totalFix);
echo "</table>";
$stmt->close();

// =====================================================
// 2. ALLE WETTKÄMPFE MIT STREICHER=1
// =====================================================
echo "\n\n=== TEIL 2: WETTKÄMPFE MIT STREICHER=1 ===\n";
echo "Hier werden die 3 niedrigsten Werte gestrichen (inkl. 0-Werte für nicht teilgenommene Wettkämpfe).\n\n";

// Erst alle Wettkämpfe mit Streicher=1 holen
$allWettkaempfeSql = "
    SELECT ID, Bezeichnung, Maxpunkte 
    FROM JMDefinition 
    WHERE year = ? AND Streicher = 1 AND Info = 0 AND Erweitert = 0
    ORDER BY Reihenfolge
";

$stmt = $conn->prepare($allWettkaempfeSql);
$stmt->bind_param("i", $jahr);
$stmt->execute();
$result = $stmt->get_result();

$alleWettkaempfe = [];
while ($row = $result->fetch_assoc()) {
    $alleWettkaempfe[] = $row;
}
$stmt->close();

echo "Anzahl Wettkämpfe mit Streicher=1: " . count($alleWettkaempfe) . "\n\n";

// Jetzt für jeden Wettkampf das Resultat holen
$streicherArray = [];

echo "<table>";
echo "<tr><th>ID</th><th>Bezeichnung</th><th>Original</th><th>Max</th><th>Normalisiert (auf 100)</th><th>Status</th></tr>";

foreach ($alleWettkaempfe as $wettkampf) {
    $wettbewerbID = $wettkampf['ID'];
    $maxPunkte = $wettkampf['Maxpunkte'];
    $bezeichnung = $wettkampf['Bezeichnung'];
    
    $punkte = null;
    $hatTeilgenommen = false;
    
    // SPEZIALBEHANDLUNG: Endstich aus endstich-Tabelle berechnen
    if ($bezeichnung === 'Endstich') {
        $sqlEndstich = "SELECT 
            COALESCE(Schuss1,0)+COALESCE(Schuss2,0)+COALESCE(Schuss3,0)+COALESCE(Schuss4,0)+COALESCE(Schuss5,0)+
            COALESCE(Schuss6,0)+COALESCE(Schuss7,0)+COALESCE(Schuss8,0)+COALESCE(Schuss9,0)+COALESCE(Schuss10,0) AS P
            FROM endstich WHERE MitgliedID=? AND Jahr=?";
        if ($stmtE = $conn->prepare($sqlEndstich)) {
            $stmtE->bind_param("ii", $mitgliedID, $jahr);
            $stmtE->execute();
            $resE = $stmtE->get_result();
            if ($rowE = $resE->fetch_assoc()) {
                $punkte = (float)$rowE['P'];
                $hatTeilgenommen = ($punkte > 0);
            }
            $stmtE->close();
        }
    }
    // SPEZIALBEHANDLUNG: Bester Kantonalstich aus kantiresultate berechnen
    elseif ($bezeichnung === 'Bester Kantonalstich') {
        $sqlKanti = "SELECT GREATEST(
            COALESCE(Passe1,0),COALESCE(Passe2,0),COALESCE(Passe3,0),COALESCE(Passe4,0),COALESCE(Passe5,0)
        ) AS P FROM kantiresultate WHERE MitgliedID=? AND Jahr=?";
        if ($stmtK = $conn->prepare($sqlKanti)) {
            $stmtK->bind_param("ii", $mitgliedID, $jahr);
            $stmtK->execute();
            $resK = $stmtK->get_result();
            if ($rowK = $resK->fetch_assoc()) {
                $punkte = (float)$rowK['P'];
                $hatTeilgenommen = ($punkte > 0);
            }
            $stmtK->close();
        }
    }
    // NORMALE WETTKÄMPFE: Aus jmresultate holen
    else {
        $punkteSql = "SELECT MAX(Punkte) AS Punkte 
                      FROM jmresultate 
                      WHERE mitgliederID = ? AND jmdefinitionID = ?";
        
        $stmt = $conn->prepare($punkteSql);
        $stmt->bind_param("ii", $mitgliedID, $wettbewerbID);
        $stmt->execute();
        $punkteResult = $stmt->get_result();
        
        if ($row = $punkteResult->fetch_assoc()) {
            if ($row['Punkte'] !== null) {
                $punkte = (float)$row['Punkte'];
                $hatTeilgenommen = true;
            }
        }
        $stmt->close();
    }
    
    // Wenn immer noch null, dann 0 setzen (nicht teilgenommen)
    if ($punkte === null) {
        $punkte = 0;
    }
    
    // Maxpunkte-Limit anwenden
    if ($maxPunkte > 0 && $punkte > $maxPunkte) {
        $punkte = $maxPunkte;
    }
    
    // Normalisieren auf 100 wenn nötig
    if ($maxPunkte != 100 && $maxPunkte > 0) {
        $normalisiert = round(($punkte / $maxPunkte) * 100, 2);
    } else {
        $normalisiert = $punkte;
    }
    
    $streicherArray[] = [
        'WettbewerbID' => $wettbewerbID,
        'Bezeichnung' => $wettkampf['Bezeichnung'],
        'Original' => $punkte,
        'Max' => $maxPunkte,
        'Normalisiert' => $normalisiert,
        'HatTeilgenommen' => $hatTeilgenommen
    ];
    
    $statusClass = $hatTeilgenommen ? '' : 'nicht-teilgenommen';
    $statusText = $hatTeilgenommen ? 'teilgenommen' : 'NICHT teilgenommen → 0';
    if ($bezeichnung === 'Endstich' || $bezeichnung === 'Bester Kantonalstich') {
        $statusText .= " (berechnet aus Tabelle)";
    }
    
    echo sprintf("<tr class='%s'><td>%d</td><td>%s</td><td>%s</td><td>%d</td><td>%.2f</td><td>%s</td></tr>", 
        $statusClass,
        $wettbewerbID,
        $wettkampf['Bezeichnung'],
        $hatTeilgenommen ? number_format($punkte, 1) : '-',
        $maxPunkte,
        $normalisiert,
        $statusText
    );
}
echo "</table>";

// Sortieren nach Normalisiert (niedrigste zuerst)
usort($streicherArray, function($a, $b) {
    return $a['Normalisiert'] <=> $b['Normalisiert'];
});

// =====================================================
// 3. STREICHER BESTIMMEN
// =====================================================
echo "\n\n=== TEIL 3: STREICHER-BERECHNUNG ===\n";
echo "Die 3 niedrigsten Werte werden gestrichen:\n\n";

$gestrichen = array_slice($streicherArray, 0, 3);
$verbleibend = array_slice($streicherArray, 3);

echo "<table>";
echo "<tr><th>ID</th><th>Bezeichnung</th><th>Normalisiert</th><th>Status</th></tr>";

foreach ($gestrichen as $g) {
    echo sprintf("<tr class='gestrichen'><td>%d</td><td>%s</td><td>%.2f</td><td>❌ GESTRICHEN</td></tr>", 
        $g['WettbewerbID'],
        $g['Bezeichnung'],
        $g['Normalisiert']
    );
}

$totalStreicher = 0;
foreach ($verbleibend as $v) {
    echo sprintf("<tr class='gezaehlt'><td>%d</td><td>%s</td><td>%.2f</td><td>✓ zählt</td></tr>", 
        $v['WettbewerbID'],
        $v['Bezeichnung'],
        $v['Normalisiert']
    );
    $totalStreicher += $v['Normalisiert'];
}

echo sprintf("<tr class='total'><td colspan='2'>SUMME nach Streicher-Abzug</td><td>%.2f</td><td></td></tr>", $totalStreicher);
echo "</table>";

// =====================================================
// 4. ZUSAMMENFASSUNG
// =====================================================
echo "\n\n=== TEIL 4: ZUSAMMENFASSUNG ===\n\n";

echo "<table>";
echo "<tr><th>Kategorie</th><th>Anzahl Wettkämpfe</th><th>Punkte</th></tr>";
echo sprintf("<tr><td>Fix-Punkte (Streicher=0)</td><td>-</td><td>%.2f</td></tr>", $totalFix);
echo sprintf("<tr><td>Variable Wettkämpfe total</td><td>%d</td><td>-</td></tr>", count($alleWettkaempfe));
echo sprintf("<tr><td>&nbsp;&nbsp;davon gestrichen</td><td>3</td><td>-</td></tr>", count($alleWettkaempfe));
echo sprintf("<tr><td>&nbsp;&nbsp;davon gezählt</td><td>%d</td><td>%.2f</td></tr>", count($verbleibend), $totalStreicher);

$total = $totalFix + $totalStreicher;
echo sprintf("<tr class='total'><td><strong>GESAMT-TOTAL</strong></td><td></td><td><strong>%.2f</strong></td></tr>", $total);
echo "</table>";

// =====================================================
// 5. ANALYSE
// =====================================================
echo "\n\n=== TEIL 5: ANALYSE ===\n\n";

echo "Erwartetes Ergebnis: 1648.25\n";
echo sprintf("Berechnetes Ergebnis: %.2f\n", $total);

if (abs($total - 1648.25) < 0.01) {
    echo "\n✓✓✓ KORREKT! ✓✓✓\n";
} else {
    $diff = $total - 1648.25;
    echo sprintf("\n❌ DIFFERENZ: %.2f Punkte (zu %s)\n", abs($diff), $diff > 0 ? "HOCH" : "NIEDRIG");
    
    echo "\nMögliche Ursachen:\n";
    if ($diff > 0) {
        echo "- Es werden mehr Wettkämpfe gezählt als erwartet\n";
        echo "- Nicht teilgenommene Wettkämpfe (0-Werte) werden bei den Streichern berücksichtigt\n";
        echo "- Das bedeutet: mehr 0-Werte werden gestrichen, mehr echte Resultate zählen\n";
    } else {
        echo "- Es werden weniger Wettkämpfe gezählt als erwartet\n";
        echo "- Zu viele echte Resultate werden als Streicher markiert\n";
    }
}

// Zusätzliche Info
echo "\n\nWICHTIG:\n";
echo "Die NEUE Logik (Version 3):\n";
echo "1. Holt ALLE Wettkämpfe mit Streicher=1 aus JMDefinition\n";
echo "2. Berechnet Endstich FRISCH aus endstich-Tabelle\n";
echo "3. Berechnet Kantonalstich FRISCH aus kantiresultate-Tabelle\n";
echo "4. Für andere Wettkämpfe: Prüft jmresultate\n";
echo "5. Setzt nicht vorhandene Resultate auf 0\n";
echo "6. Wendet Maxpunkte-Limits an\n";
echo "7. Normalisiert auf 100 wenn Maxpunkte ≠ 100\n";
echo "8. Streicht die 3 NIEDRIGSTEN (inkl. 0-Werte)\n";
echo "9. Summiert nur die verbleibenden\n\n";

echo "Die ALTE Logik:\n";
echo "1. Holte nur Wettkämpfe wo das Mitglied in jmresultate vorhanden war\n";
echo "2. Endstich und Kantonalstich wurden NICHT berechnet (=0)\n";
echo "3. Keine 0-Werte für nicht teilgenommene Wettkämpfe\n";
echo "4. Strich die 3 niedrigsten der vorhandenen Resultate\n";
echo "5. Summierte die verbleibenden\n\n";

echo "KRITISCHER UNTERSCHIED:\n";
echo "Endstich und Kantonalstich werden jetzt KORREKT berechnet!\n";
echo "Diese Werte waren vorher 0 und wurden gestrichen.\n";
echo "Jetzt haben sie ihre echten Werte und können gezählt werden.\n";

echo "\n</pre>";

$conn->close();
?>