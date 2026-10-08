<?php
/**
 * Zentrale Jahresauswahl (Admin).
 *
 * - msvJahreMitDaten($conn): alle Jahre, für die Resultate/Definitionen existieren, plus das
 *   laufende Jahr; absteigend. Kurz in der Session zwischengespeichert.
 * - msvJahrStandard(): das zuletzt gewählte Jahr (Cookie msv_jahr, gesetzt von inc/js/msv-jahr.js),
 *   sonst das laufende Jahr. Für Seiten, die das Jahr serverseitig brauchen (?year=…).
 *
 * header.inc.php gibt die Liste als window.MSV_JAHRE aus; im Browser füllt msvJahrAuswahl()
 * die Dropdowns. Gelöscht wird bei einem Jahreswechsel nichts: jedes Jahr bleibt abrufbar.
 */

if (!function_exists('msvJahrStandard')) {
    /** @param int[]|null $erlaubt  optional: nur diese Jahre zulassen (z.B. msvJahreMitDaten) */
    function msvJahrStandard(?array $erlaubt = null): int
    {
        $aktuell = (int)date('Y');
        $roh = $_COOKIE['msv_jahr'] ?? '';
        if (is_string($roh) && preg_match('/^\d{4}$/', $roh)) {
            $j = (int)$roh;
            if ($j >= 1990 && $j <= $aktuell + 1 && ($erlaubt === null || in_array($j, $erlaubt, true))) {
                return $j;
            }
        }
        return $aktuell;
    }

    /** @param mysqli|PDO|null $conn */
    function msvJahreMitDaten($conn): array
    {
        $aktuell = (int)date('Y');
        if (session_status() === PHP_SESSION_ACTIVE
            && isset($_SESSION['msv_jahre']['t'], $_SESSION['msv_jahre']['jahre'])
            && time() - (int)$_SESSION['msv_jahre']['t'] < 600) {
            return $_SESSION['msv_jahre']['jahre'];
        }

        $abfragen = [
            'SELECT DISTINCT year FROM JMDefinition',
            'SELECT DISTINCT Jahr FROM heimresultate',
            'SELECT DISTINCT Jahr FROM kantiresultate',
            'SELECT DISTINCT Jahr FROM endstich',
        ];
        $jahre = [$aktuell => true];
        foreach ($abfragen as $sql) {
            try {
                if ($conn instanceof mysqli) {
                    $res = $conn->query($sql);
                    if ($res) {
                        while ($row = $res->fetch_row()) {
                            $jahre[(int)$row[0]] = true;
                        }
                    }
                } elseif ($conn instanceof PDO) {
                    foreach ($conn->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $j) {
                        $jahre[(int)$j] = true;
                    }
                }
            } catch (Throwable $e) {
                // Tabelle fehlt o.ä.: Liste bleibt trotzdem brauchbar
            }
        }
        $liste = array_values(array_filter(array_keys($jahre),
            static fn($j) => $j >= 1990 && $j <= $aktuell + 1));
        rsort($liste);

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['msv_jahre'] = ['t' => time(), 'jahre' => $liste];
        }
        return $liste;
    }
}
