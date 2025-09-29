<?php
// inc/cuprang/cup_repository.php
// Read-only Repository mit robuster Spaltenerkennung (cupStandFinal kann je nach DB variieren)

if (!function_exists('cup_dbconn')) {
    function cup_dbconn() {
        if (function_exists('get_db_connection')) return get_db_connection();
        if (function_exists('connect_db_mysqli')) return connect_db_mysqli();
        throw new Exception('Keine DB-Verbindungsfunktion gefunden.');
    }
}

/**
 * Prepared-Statement Helper (typsicher, mit Referenzen)
 */
if (!function_exists('cup_prepare_exec')) {
    function cup_prepare_exec(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_result {
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new RuntimeException('SQL prepare fehlgeschlagen: '.$conn->error.' | '.$sql);
        if ($types !== '' && $params) {
            $bind = [$types];
            foreach ($params as $k => $v) $bind[] = &$params[$k];
            if (!call_user_func_array([$stmt,'bind_param'],$bind)) {
                $stmt->close(); throw new RuntimeException('bind_param fehlgeschlagen');
            }
        }
        if (!$stmt->execute()) {
            $err = $stmt->error; $stmt->close();
            throw new RuntimeException('SQL execute fehlgeschlagen: '.$err.' | '.$sql);
        }
        $res = $stmt->get_result();
        $stmt->close();
        return $res;
    }
}

/**
 * Prüft, ob in einer Tabelle bestimmte Spalten existieren.
 */
if (!function_exists('cup_table_has_columns')) {
    function cup_table_has_columns(mysqli $conn, string $table, array $cols): array {
        $existing = [];
        $res = $conn->query("SHOW COLUMNS FROM `$table`");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $existing[strtolower($row['Field'])] = true;
            }
            $res->close();
        }
        $out = [];
        foreach ($cols as $c) $out[$c] = isset($existing[strtolower($c)]);
        return $out;
    }
}

if (!function_exists('cup_fetch_pairs')) {
    function cup_fetch_pairs(mysqli $conn, int $year): array {
        $sql = "SELECT ID, Participant1, Participant2, Participant3,
                       Result1, Result2, Result3,
                       LowShot1, LowShot2, LowShot3,
                       ManualWinner, ManualWinnerReason,
                       `Round`, `Year`
                FROM cupPairs
                WHERE `Year` = ?
                ORDER BY `Round` ASC, ID ASC";
        $res = cup_prepare_exec($conn, $sql, 'i', [$year]);
        return $res->fetch_all(MYSQLI_ASSOC);
    }
}

if (!function_exists('cup_fetch_final_results')) {
    function cup_fetch_final_results(mysqli $conn, int $year): array {
        // Versuch mit JOIN (falls Tabelle mitglieder existiert)
        try {
            $sqlJoin = "SELECT fr.ParticipantID,
                               fr.Result AS Punkte,
                               fr.LowShot AS Tiefschuss,
                               CONCAT(m.Name, ' ', m.Vorname) AS Teilnehmer
                        FROM cupFinalResults fr
                        JOIN mitglieder m ON m.ID = fr.ParticipantID
                        WHERE fr.`Year` = ?";
            $res = cup_prepare_exec($conn, $sqlJoin, 'i', [$year]);
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            if (!empty($rows)) return $rows;
        } catch (\Throwable $e) {
            // Fällt unten auf Fallback zurück
        }

        // Fallback ohne JOIN
        $sql = "SELECT ParticipantID, Result AS Punkte, LowShot AS Tiefschuss
                FROM cupFinalResults WHERE `Year` = ?";
        $res2 = cup_prepare_exec($conn, $sql, 'i', [$year]);
        return $res2->fetch_all(MYSQLI_ASSOC);
    }
}

if (!function_exists('cup_fetch_standcup_final')) {
    /**
     * Robust gegen verschiedene Schemata:
     *  - Name:  ParticipantName ODER (ParticipantID -> später Namensauflösung)
     *  - Verein: club ODER Club
     *  - Punkte: Result ODER Punkte
     */
    function cup_fetch_standcup_final(mysqli $conn, int $year): array {
        $cols = cup_table_has_columns($conn, 'cupStandFinal', [
            'ParticipantName','ParticipantID','club','Club','Result','Punkte','Year'
        ]);

        // Welche Spalten gibt's?
        $hasName = $cols['ParticipantName'];
        $hasPID  = $cols['ParticipantID'];
        $clubCol = $cols['club'] ? 'club' : ($cols['Club'] ? 'Club' : null);
        $ptsCol  = $cols['Result'] ? 'Result' : ($cols['Punkte'] ? 'Punkte' : null);

        // Minimale Absicherung: wenn weder Name noch ID existiert, gib leer zurück
        if (!$hasName && !$hasPID) return [];

        // SELECT dynamisch zusammenbauen
        $select = [];
        if ($hasPID)  $select[] = 'ParticipantID';
        if ($hasName) $select[] = 'ParticipantName';
        if ($clubCol) $select[] = "`$clubCol` AS club";
        if ($ptsCol)  $select[] = "`$ptsCol` AS Result";  // wir mappen intern auf "Result"

        // Falls gar keine Punkte-Spalte existiert, trotzdem minimal selektieren
        if (empty($select)) $select[] = '*';

        $sql = "SELECT ".implode(', ', $select)." FROM cupStandFinal WHERE `Year` = ?";

        // Sortierung nur, wenn wir Punkte haben
        if ($ptsCol) $sql .= " ORDER BY `$ptsCol` DESC";

        $res = cup_prepare_exec($conn, $sql, 'i', [$year]);
        return $res->fetch_all(MYSQLI_ASSOC);
    }
}

if (!function_exists('get_member_name')) {
    function get_member_name(mysqli $conn, int $memberId): string {
        try {
            $res = cup_prepare_exec($conn, "SELECT CONCAT(Name,' ',Vorname) AS FullName FROM mitglieder WHERE ID = ?", 'i', [$memberId]);
            if ($row = $res->fetch_assoc()) return $row['FullName'] ?: ('Mitglied #'.$memberId);
        } catch (\Throwable $e) { /* ignore */ }
        return 'Mitglied #' . $memberId;
    }
}

if (!function_exists('get_member_names_bulk')) {
    function get_member_names_bulk(mysqli $conn, array $memberIds): array {
        $ids = array_values(array_unique(array_map('intval', array_filter($memberIds, fn($v)=>$v!==null && $v!==''))));
        if (!$ids) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $sql = "SELECT ID, CONCAT(Name,' ',Vorname) AS FullName FROM mitglieder WHERE ID IN ($ph)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];
        $bind = [$types];
        foreach ($ids as $k => $v) $bind[] = &$ids[$k];
        call_user_func_array([$stmt,'bind_param'],$bind);
        if (!$stmt->execute()) { $stmt->close(); return []; }
        $res = $stmt->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[(int)$row['ID']] = $row['FullName'] ?: ('Mitglied #'.$row['ID']);
        }
        $stmt->close();
        return $out;
    }
}
