<?php
/**
 * Gemeinsamer Ablauf für «Alle Resultate eines Jahres löschen»
 * (JM, Endschiessen, Heim, Kanti).
 *
 * Der Endpunkt erledigt Guard und CSRF selbst und ruft danach:
 *
 *   msvJahrLoeschen($conn, 'heim-loeschen', 'Heimresultate',
 *       fn(mysqli $c, int $j): int => ...Anzahl...,
 *       fn(mysqli $c, int $j): int => ...gelöschte Zeilen...);
 *
 * POST: year oder jahr (int); nur_zaehlen=1 liefert nur die Anzahl für den Dialog.
 *
 * Ablauf: zählen -> Sicherung der ganzen DB (backup_dump.inc.php) -> löschen in
 * einer Transaktion -> JSON. Schlägt die Sicherung fehl, wird nichts gelöscht.
 * Antwort immer JSON: {success, year, anzahl, sicherung?, message?}.
 * Gegenstück im Browser: msvJahrLoeschen() in inc/js/msv-toast.js.
 */
require_once __DIR__ . '/backup_dump.inc.php';

if (!function_exists('msvJahrLoeschen')) {
    function msvJahrLoeschenAntwort(array $daten, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($daten, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * @param callable $zaehlen  fn(mysqli, int $jahr): int
     * @param callable $loeschen fn(mysqli, int $jahr): int  (läuft in einer Transaktion)
     * @param callable|null $nachher fn(int $jahr, int $geloescht): void  z.B. Changelog
     */
    function msvJahrLoeschen(mysqli $conn, string $anlass, string $bezeichnung,
                             callable $zaehlen, callable $loeschen, ?callable $nachher = null): void
    {
        $roh  = $_POST['year'] ?? $_POST['jahr'] ?? '';
        $jahr = (int)$roh;
        if ($jahr < 1900 || $jahr > 2200) {
            msvJahrLoeschenAntwort(['success' => false, 'message' => 'Ungültiges Jahr.'], 400);
        }

        try {
            $anzahl = (int)$zaehlen($conn, $jahr);
        } catch (Throwable $e) {
            error_log("[$anlass] Zählen: " . $e->getMessage());
            msvJahrLoeschenAntwort(['success' => false, 'message' => "Die $bezeichnung konnten nicht gezählt werden."], 500);
        }

        if (!empty($_POST['nur_zaehlen'])) {
            msvJahrLoeschenAntwort(['success' => true, 'year' => $jahr, 'anzahl' => $anzahl]);
        }
        if ($anzahl === 0) {
            msvJahrLoeschenAntwort(['success' => true, 'year' => $jahr, 'anzahl' => 0, 'sicherung' => null,
                'message' => "Für $jahr sind keine $bezeichnung erfasst."]);
        }

        $sicherung = msvBackupVorher($anlass . '-' . $jahr);
        if (!$sicherung['ok']) {
            error_log("[$anlass] Sicherung fehlgeschlagen: " . $sicherung['meldung']);
            msvJahrLoeschenAntwort(['success' => false,
                'message' => 'Die Sicherung vor dem Löschen ist fehlgeschlagen (' . $sicherung['meldung']
                           . '). Es wurde nichts gelöscht.'], 500);
        }

        $conn->begin_transaction();
        try {
            $geloescht = (int)$loeschen($conn, $jahr);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log("[$anlass] Löschen: " . $e->getMessage());
            msvJahrLoeschenAntwort(['success' => false,
                'message' => 'Löschen fehlgeschlagen, es wurde nichts geändert. Die Sicherung '
                           . $sicherung['datei'] . ' liegt trotzdem vor.'], 500);
        }

        if ($nachher) {
            try {
                $nachher($jahr, $geloescht);
            } catch (Throwable $e) {
                error_log("[$anlass] Nachlauf: " . $e->getMessage());
            }
        }

        msvJahrLoeschenAntwort([
            'success'   => true,
            'year'      => $jahr,
            'anzahl'    => $geloescht,
            'sicherung' => $sicherung['datei'],
            'message'   => "$bezeichnung $jahr gelöscht. Sicherung: " . $sicherung['datei'],
        ]);
    }

    /** Hilfsfunktion: vorbereitete Abfrage mit einem int-Parameter ausführen. */
    function msvJahrLoeschenSql(mysqli $conn, string $sql, int $wert): mysqli_stmt
    {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException($conn->error);
        }
        $stmt->bind_param('i', $wert);
        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
        return $stmt;
    }

    /** COUNT(*)-Abfrage mit einem int-Parameter. */
    function msvJahrLoeschenZahl(mysqli $conn, string $sql, int $wert): int
    {
        $stmt = msvJahrLoeschenSql($conn, $sql, $wert);
        $row  = $stmt->get_result()->fetch_row();
        $stmt->close();
        return (int)($row[0] ?? 0);
    }

    /** DELETE mit einem int-Parameter, liefert die Anzahl betroffener Zeilen. */
    function msvJahrLoeschenAusfuehren(mysqli $conn, string $sql, int $wert): int
    {
        $stmt = msvJahrLoeschenSql($conn, $sql, $wert);
        $n = max(0, $stmt->affected_rows);
        $stmt->close();
        return $n;
    }
}
