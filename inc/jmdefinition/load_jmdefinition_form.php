<?php
// load_jmdefinition_form.php – Hybrid Layout (read-only Tabelle + hidden Inputs)
include '../config.php';

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
        echo "<td class='h-addr'>" . nl2br($adr) . "</td>";

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
    echo "<tr><td colspan='6' class='text-center text-muted py-4'><i class='bi bi-inbox me-2'></i>Keine Einträge gefunden</td></tr>";
}

$conn->close();
?>
