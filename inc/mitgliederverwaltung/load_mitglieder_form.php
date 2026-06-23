<?php
// load_mitglieder_form.php - Hybrid-Version (Read-only Tabelle + Slide-Panel)
include 'config.php';

$sql = "SELECT m.id, m.Anrede, m.vorname, m.name, m.waffenid, m.status, w.bezeichnung AS waffe,
        m.Geburtsdatum, m.Ehrenmitglied, m.Strasse, m.PLZ, m.Ort, m.Email,
        m.Telefon, m.Mobile, m.Notizen, m.Verstorben, m.Vereinsaufnahme, m.Kommunikation
        FROM mitglieder m
        INNER JOIN Waffen w ON m.waffenid = w.id
        ORDER BY m.name ASC, m.vorname ASC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $id   = intval($row['id']);
        $esc  = function($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); };

        $statusVal  = intval($row['status']);
        $ehreVal    = intval($row['Ehrenmitglied']);
        $verstVal   = intval($row['Verstorben']);

        // Geburtsdatum formatieren für Anzeige
        $gebDisplay = '';
        if (!empty($row['Geburtsdatum'])) {
            $ts = strtotime($row['Geburtsdatum']);
            if ($ts) $gebDisplay = date('d.m.Y', $ts);
        }

        $rowClass = $verstVal ? ' style="opacity:0.5;"' : '';

        echo '<tr class="hybrid-row" id="row' . $id . '"'
            . ' data-id="' . $id . '"'
            . ' data-anrede="' . $esc($row['Anrede']) . '"'
            . ' data-name="' . $esc($row['name']) . '"'
            . ' data-vorname="' . $esc($row['vorname']) . '"'
            . ' data-geburtsdatum="' . $esc($row['Geburtsdatum']) . '"'
            . ' data-waffenid="' . intval($row['waffenid']) . '"'
            . ' data-waffe="' . $esc($row['waffe']) . '"'
            . ' data-strasse="' . $esc($row['Strasse']) . '"'
            . ' data-plz="' . $esc($row['PLZ']) . '"'
            . ' data-ort="' . $esc($row['Ort']) . '"'
            . ' data-email="' . $esc($row['Email']) . '"'
            . ' data-telefon="' . $esc($row['Telefon']) . '"'
            . ' data-mobile="' . $esc($row['Mobile']) . '"'
            . ' data-notizen="' . $esc($row['Notizen']) . '"'
            . ' data-status="' . $statusVal . '"'
            . ' data-ehrenmitglied="' . $ehreVal . '"'
            . ' data-verstorben="' . $verstVal . '"'
            . ' data-vereinsaufnahme="' . $esc($row['Vereinsaufnahme']) . '"'
            . ' data-kommunikation="' . $esc($row['Kommunikation']) . '"'
            . $rowClass . '>';

        // Lizenznr.
        echo '<td class="h-nr">' . $id . '</td>';
        // Name
        echo '<td class="h-name">' . $esc($row['name']) . '</td>';
        // Vorname
        echo '<td>' . $esc($row['vorname']) . '</td>';
        // Geburtsdatum
        echo '<td class="h-date">' . $gebDisplay . '</td>';
        // Waffe
        echo '<td class="h-sub">' . $esc($row['waffe']) . '</td>';
        // Ort
        echo '<td class="h-sub">' . $esc($row['Ort']) . '</td>';
        // Email
        echo '<td class="h-email">' . $esc($row['Email']) . '</td>';
        // Mobile
        echo '<td class="h-sub">' . $esc($row['Mobile']) . '</td>';
        // Flag-Dots
        echo '<td>';
        echo '<div class="flag-dots">';
        echo '<div class="flag-dot ' . ($statusVal ? 'on' : 'off') . '" data-flag="status" data-tooltip="Aktiv"><i class="bi bi-check-lg"></i></div>';
        echo '<div class="flag-dot ' . ($ehreVal ? 'on' : 'off') . '" data-flag="ehrenmitglied" data-tooltip="Ehrenmitglied" style="' . ($ehreVal ? 'background:#f59e0b;' : '') . '"><i class="bi bi-award"></i></div>';
        echo '<div class="flag-dot ' . ($verstVal ? 'on' : 'off') . '" data-flag="verstorben" data-tooltip="Verstorben" style="' . ($verstVal ? 'background:#64748b;' : '') . '"><i class="bi bi-dash-circle"></i></div>';
        echo '</div>';
        echo '</td>';

        // Hidden inputs for form submit
        echo '<input type="hidden" name="id[' . $id . ']" value="' . $id . '">';
        echo '<input type="hidden" name="anrede[' . $id . ']" value="' . $esc($row['Anrede']) . '">';
        echo '<input type="hidden" name="name[' . $id . ']" value="' . $esc($row['name']) . '">';
        echo '<input type="hidden" name="vorname[' . $id . ']" value="' . $esc($row['vorname']) . '">';
        echo '<input type="hidden" name="geburtsdatum[' . $id . ']" value="' . $esc($row['Geburtsdatum']) . '">';
        echo '<input type="hidden" name="waffenid[' . $id . ']" value="' . intval($row['waffenid']) . '">';
        echo '<input type="hidden" name="strasse[' . $id . ']" value="' . $esc($row['Strasse']) . '">';
        echo '<input type="hidden" name="plz[' . $id . ']" value="' . $esc($row['PLZ']) . '">';
        echo '<input type="hidden" name="ort[' . $id . ']" value="' . $esc($row['Ort']) . '">';
        echo '<input type="hidden" name="email[' . $id . ']" value="' . $esc($row['Email']) . '">';
        echo '<input type="hidden" name="telefon[' . $id . ']" value="' . $esc($row['Telefon']) . '">';
        echo '<input type="hidden" name="mobile[' . $id . ']" value="' . $esc($row['Mobile']) . '">';
        echo '<input type="hidden" name="notizen[' . $id . ']" value="' . $esc($row['Notizen']) . '">';
        echo '<input type="hidden" name="vereinsaufnahme[' . $id . ']" value="' . $esc($row['Vereinsaufnahme']) . '">';
        echo '<input type="hidden" name="kommunikation[' . $id . ']" value="' . $esc($row['Kommunikation']) . '">';
        if ($statusVal) echo '<input type="hidden" name="status[' . $id . ']" value="1" class="flag-input" data-flag="status">';
        if ($ehreVal)   echo '<input type="hidden" name="ehrenmitglied[' . $id . ']" value="1" class="flag-input" data-flag="ehrenmitglied">';
        if ($verstVal)  echo '<input type="hidden" name="verstorben[' . $id . ']" value="1" class="flag-input" data-flag="verstorben">';

        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="9" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Keine Mitglieder gefunden</td></tr>';
}

$conn->close();
?>
