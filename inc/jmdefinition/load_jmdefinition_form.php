<?php
// load_jmdefinition_form.php – Hybrid Layout (read-only Tabelle + hidden Inputs)
include '../config.php';
require_once __DIR__ . '/../partials/empty_state.inc.php';

if (!function_exists('dv_format_adresse')) {
    function dv_format_adresse(string $adr): string {
        $t = trim($adr);
        // Reine Koordinaten: zwei Dezimalzahlen, getrennt durch , / ; oder Leerzeichen
        if (preg_match('/^(-?\d{1,3}[.,]\d+)\s*[,\/;\s]\s*(-?\d{1,3}[.,]\d+)$/u', $t, $m)) {
            $lat = round((float) str_replace(',', '.', $m[1]), 5);
            $lon = round((float) str_replace(',', '.', $m[2]), 5);
            $label = number_format($lat, 5, '.', '') . ', ' . number_format($lon, 5, '.', '');
            $href  = 'https://www.google.com/maps?q=' . $lat . ',' . $lon;
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
                 . '" target="_blank" rel="noopener" data-tooltip="Auf Karte öffnen">'
                 . '<i class="bi bi-geo-alt me-1"></i>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }
        return nl2br($adr);
    }
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

$stmt = $conn->prepare("SELECT * FROM JMDefinition WHERE year = ? AND hidden = 0 ORDER BY Reihenfolge");
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['ID'];
        $bez = htmlspecialchars($row['Bezeichnung'], ENT_QUOTES, 'UTF-8');
        $adr = htmlspecialchars($row['Adresse'] ?? '', ENT_QUOTES, 'UTF-8');
        $sch = htmlspecialchars($row['Schiesstage'] ?? '', ENT_QUOTES, 'UTF-8');
        $max = (int)$row['Maxpunkte'];
        $zus = (int)($row['Zuschlag'] ?? 0);
        $sFlag = $row['Streicher'] ? 1 : 0;
        $eFlag = $row['Erweitert'] ? 1 : 0;
        $iFlag = $row['Info'] ? 1 : 0;
        $gFlag = $row['Gruppe'] ? 1 : 0;

        // Flag-Dot CSS-Klassen
        $sOn = $sFlag ? 'on' : 'off';
        $eOn = $eFlag ? 'on' : 'off';
        $iOn = $iFlag ? 'on' : 'off';
        $gOn = $gFlag ? 'on' : 'off';

        // TR mit data-Attributen für Panel-Zugriff
        echo "<tr id='row{$id}' class='hybrid-row'
              data-id='{$id}'
              data-bezeichnung='{$bez}'
              data-adresse='{$adr}'
              data-schiesstage='{$sch}'
              data-maxpunkte='{$max}'
              data-zuschlag='{$zus}'
              data-streicher='{$sFlag}'
              data-erweitert='{$eFlag}'
              data-info='{$iFlag}'
              data-gruppe='{$gFlag}'
            >";

        // Spalte 1: Nr. (Drag Handle)
        echo "<td class='h-nr'>
                <span class='drag-grip'><i class='bi bi-grip-vertical'></i></span>
                {$row['Reihenfolge']}
              </td>";

        // Spalte 2: Bezeichnung (Read-only)
        echo "<td class='h-title'>" . nl2br($bez) . "</td>";

        // Spalte 3: Adresse (Read-only)
        echo "<td class='h-addr'>" . dv_format_adresse($adr) . "</td>";

        // Spalte 4: Schiesstage (Read-only)
        echo "<td class='h-dates'>" . nl2br($sch) . "</td>";

        // Spalte 5: Max (Read-only)
        echo "<td class='h-max'>{$max}</td>";

        // Spalte 6: Optionen (Flag-Dots)
        echo "<td class='h-flags'>
                <div class='flag-dots'>
                  <span class='flag-dot {$sOn}' data-flag='streicher' data-tooltip='Resultat kann gestrichen werden'><i class='bi bi-dash-circle'></i></span>
                  <span class='flag-dot {$eOn}' data-flag='erweitert' data-tooltip='Gruppenschiessen nicht in JM'><i class='bi bi-plus-circle'></i></span>
                  <span class='flag-dot {$iOn}' data-flag='info' data-tooltip='Info – Nur informativ'><i class='bi bi-info-circle'></i></span>
                  <span class='flag-dot {$gOn}' data-flag='gruppe' data-tooltip='hat Gruppenwettkampf'><i class='bi bi-people'></i></span>
                </div>
              </td>";

        // Hidden Inputs (gleiche name-Attribute wie bisher für save_jmdefinition.php)
        echo "<input type='hidden' name='bezeichnung[{$id}]' value='{$bez}'>";
        echo "<input type='hidden' name='adresse[{$id}]' value='{$adr}'>";
        echo "<input type='hidden' name='schiesstage[{$id}]' value='{$sch}'>";
        echo "<input type='hidden' name='maxpunkte[{$id}]' value='{$max}'>";
        echo "<input type='hidden' name='zuschlag[{$id}]' value='{$zus}'>";

        // Checkbox-Flags: nur Hidden Input wenn aktiv (wie echte Checkboxen)
        if ($sFlag) echo "<input type='hidden' name='streicher[{$id}]' value='1' class='flag-input' data-flag='streicher'>";
        if ($eFlag) echo "<input type='hidden' name='erweitert[{$id}]' value='1' class='flag-input' data-flag='erweitert'>";
        if ($iFlag) echo "<input type='hidden' name='info[{$id}]' value='1' class='flag-input' data-flag='info'>";
        if ($gFlag) echo "<input type='hidden' name='gruppe[{$id}]' value='1' class='flag-input' data-flag='gruppe'>";

        echo "</tr>";
    }
} else {
    echo msv_empty_row(6, 'Keine Einträge gefunden');
}

$conn->close();
?>
