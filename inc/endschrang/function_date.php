<?php
function getactdate(){
    // Versuch, das Gebietsschema auf Deutsch zu setzen
    $localeSet = setlocale(LC_TIME, 'de_DE.UTF-8', 'de_DE', 'deu_deu', 'German_Germany.1252');

    if ($localeSet) {
        // Gebietsschema erfolgreich gesetzt
        return strftime('%e. %B %Y');
    } else {
        // Fallback: Manuelles Array verwenden
        $monate = [
            1 => 'Januar',
            2 => 'Februar',
            3 => 'März',
            4 => 'April',
            5 => 'Mai',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'August',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Dezember'
        ];
        $tag = date('j');
        $monat = $monate[date('n')];
        $jahr = date('Y');
        $actdate = "$tag. $monat $jahr";
        return $actdate;
    }
}
?>
