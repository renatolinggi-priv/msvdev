<?php
/**
 * inc/jmdefinition/jmdefinition_helpers.inc.php
 *
 * Gemeinsame Logik der JM-Definition-Endpoints (Review 09.2026). Vorher lag der
 * Schiesstage-Parser in drei Varianten vor (save_jmdefinition, copy_from_year,
 * export_all_ics) und add_jmdefinition schrieb gar keine JMSchiesstage.
 *
 *  - jm_parse_schiesstage()     Text -> Termine [{date, start, end, year}]
 *  - jm_schiesstage_replace()   JMSchiesstage eines Anlasses neu aus dem Text befuellen
 *  - jm_information_save()      JMInformation als EINE Zeile pflegen (vorher wuchs die Tabelle)
 *  - jm_parameter_save()        Anzahl Streicher pro Jahr (Tabelle Parameter)
 *
 * Kanonisches Zeilenformat: "Samstag 12. April 2025 08:00 – 12:00 Uhr, 13:30 – 17:00 Uhr"
 * Toleriert: fehlender Wochentag, fehlendes Jahr (-> $defaultYear), "08.00" statt "08:00",
 * Gedankenstrich oder Bindestrich, "15.März" ohne Leerzeichen, NBSP/Tabs.
 */

const JM_MONATE = [
    'Januar' => 1, 'Februar' => 2, 'März' => 3, 'April' => 4, 'Mai' => 5, 'Juni' => 6,
    'Juli' => 7, 'August' => 8, 'September' => 9, 'Oktober' => 10, 'November' => 11, 'Dezember' => 12,
];

/**
 * Parst den mehrzeiligen Schiesstage-Text.
 *
 * @param  string     $text         Rohtext aus JMDefinition.Schiesstage
 * @param  int        $defaultYear  Jahr fuer Zeilen ohne Jahresangabe
 * @param  array|null $warnings     Nicht erkannte Zeilen (dedupliziert) werden angehaengt
 * @return array<int, array{date:string,start:string,end:string,year:int}>
 */
function jm_parse_schiesstage(string $text, int $defaultYear, ?array &$warnings = null): array
{
    $termine = [];
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $monate = implode('|', array_keys(JM_MONATE));
    $pattern = '/^(?:\S+\s+)?(?P<day>\d{1,2})\.\s+(?P<mon>' . $monate . ')(?:\s+(?P<year>\d{4}))?\s+(?P<rest>.+)$/u';

    foreach (explode("\n", $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $orig = $line;
        $line = str_replace(["\u{2013}", "\u{2014}"], '-', $line);            // Gedankenstriche
        $line = preg_replace('/[\s\x{00A0}]+/u', ' ', $line);                 // NBSP/Tabs/Mehrfach-Leerzeichen
        $line = preg_replace('/(\d{1,2}\.)(\p{L})/u', '$1 $2', $line);         // "15.März" -> "15. März"

        if (!preg_match($pattern, $line, $m)) {
            if ($warnings !== null) $warnings[$orig] = true;
            continue;
        }
        $year  = !empty($m['year']) ? (int)$m['year'] : $defaultYear;
        $month = JM_MONATE[$m['mon']];
        $day   = (int)$m['day'];
        if (!checkdate($month, $day, $year)) {
            if ($warnings !== null) $warnings[$orig] = true;
            continue;
        }
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

        if (!preg_match_all('/(\d{1,2})[:.](\d{2})\s*-\s*(\d{1,2})[:.](\d{2})/u', $m['rest'], $tm, PREG_SET_ORDER)) {
            if ($warnings !== null) $warnings[$orig] = true;
            continue;
        }
        foreach ($tm as $t) {
            $termine[] = [
                'date'  => $date,
                'start' => sprintf('%02d:%02d:00', (int)$t[1], (int)$t[2]),
                'end'   => sprintf('%02d:%02d:00', (int)$t[3], (int)$t[4]),
                'year'  => $year,
            ];
        }
    }
    return $termine;
}

/**
 * Ersetzt alle JMSchiesstage-Zeilen eines Anlasses durch die aus $text geparsten.
 * Laeuft innerhalb der Transaktion des Aufrufers. Wirft Exception bei DB-Fehlern.
 *
 * @return int Anzahl eingefuegter Termine
 */
function jm_schiesstage_replace(mysqli $conn, int $jmId, string $text, int $defaultYear, ?array &$warnings = null): int
{
    static $stmtDel = null, $stmtIns = null, $stmtConn = null;
    if ($stmtConn !== $conn || $stmtDel === null || $stmtIns === null) {
        $stmtDel = $conn->prepare("DELETE FROM JMSchiesstage WHERE jm_id = ?");
        $stmtIns = $conn->prepare("INSERT INTO JMSchiesstage (jm_id, schiesstag, start_time, end_time, year) VALUES (?, ?, ?, ?, ?)");
        if (!$stmtDel || !$stmtIns) {
            throw new Exception('Prepare (JMSchiesstage) fehlgeschlagen: ' . $conn->error);
        }
        $stmtConn = $conn;
    }
    $stmtDel->bind_param('i', $jmId);
    if (!$stmtDel->execute()) {
        throw new Exception("Schiesstage von Anlass $jmId konnten nicht gelöscht werden: " . $stmtDel->error);
    }
    $n = 0;
    foreach (jm_parse_schiesstage($text, $defaultYear, $warnings) as $t) {
        // Jahr der Zeile speichern (Folgejahr-Termine wie die GV im Januar bleiben korrekt),
        // damit Kalender/Portal nach Datum und nicht nach POST-Jahr filtern koennen.
        $stmtIns->bind_param('isssi', $jmId, $t['date'], $t['start'], $t['end'], $t['year']);
        if (!$stmtIns->execute()) {
            throw new Exception("Schiesstag für Anlass $jmId konnte nicht gespeichert werden: " . $stmtIns->error);
        }
        $n++;
    }
    return $n;
}

/**
 * JMInformation (Zusatztext fuer PDF/Fragebogen) als genau eine Zeile pflegen.
 * Die Tabelle hat nur einen Primaerschluessel auf id, darum legte das fruehere
 * INSERT ... ON DUPLICATE KEY UPDATE bei jedem Speichern eine neue Zeile an.
 */
function jm_information_save(mysqli $conn, string $text): void
{
    $res = $conn->query("SELECT id FROM JMInformation ORDER BY id DESC LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    if ($row) {
        $stmt = $conn->prepare("UPDATE JMInformation SET text = ? WHERE id = ?");
        $id = (int)$row['id'];
        $stmt->bind_param('si', $text, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO JMInformation (text) VALUES (?)");
        $stmt->bind_param('s', $text);
    }
    if (!$stmt || !$stmt->execute()) {
        throw new Exception('Zusatztext konnte nicht gespeichert werden: ' . $conn->error);
    }
    $stmt->close();
}

/**
 * Anzahl Streicher (Parameter.excludeCount) fuer ein Jahr speichern.
 */
function jm_parameter_save(mysqli $conn, int $year, int $excludeCount): void
{
    if ($year < 2000 || $year > 2100) throw new InvalidArgumentException('Ungültiges Jahr');
    if ($excludeCount < 1 || $excludeCount > 9) throw new InvalidArgumentException('Anzahl Streicher muss zwischen 1 und 9 liegen');
    $stmt = $conn->prepare("INSERT INTO Parameter (year, excludeCount) VALUES (?, ?) ON DUPLICATE KEY UPDATE excludeCount = VALUES(excludeCount)");
    $stmt->bind_param('ii', $year, $excludeCount);
    if (!$stmt->execute()) {
        throw new Exception('Anzahl Streicher konnte nicht gespeichert werden: ' . $stmt->error);
    }
    $stmt->close();
}
