<?php
/**
 * Löscht alle Endschiessen-Resultate eines Jahres (vorher automatische DB-Sicherung):
 * endstich, glueck, kunst, schwini, zabig sowie die jmresultate des Endstich-Anlasses.
 * POST: jahr, csrf_token, optional nur_zaehlen=1. Ablauf: inc/jahr_loeschen.inc.php
 */
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);
require_once __DIR__ . '/../jahr_loeschen.inc.php';

const ENDSCH_TABELLEN = ['endstich', 'glueck', 'kunst', 'schwini', 'zabig'];
const ENDSCH_JM_SQL   = "FROM jmresultate WHERE jmdefinitionID IN
                         (SELECT ID FROM JMDefinition WHERE Bezeichnung = 'Endstich' AND year = ?)";

msvJahrLoeschen($conn, 'endsch-loeschen', 'Endschiessen-Einträge',
    function (mysqli $c, int $j): int {
        $n = 0;
        foreach (ENDSCH_TABELLEN as $t) {
            $n += msvJahrLoeschenZahl($c, "SELECT COUNT(*) FROM `$t` WHERE `Jahr` = ?", $j);
        }
        return $n + msvJahrLoeschenZahl($c, 'SELECT COUNT(*) ' . ENDSCH_JM_SQL, $j);
    },
    function (mysqli $c, int $j): int {
        $n = 0;
        foreach (ENDSCH_TABELLEN as $t) {
            $n += msvJahrLoeschenAusfuehren($c, "DELETE FROM `$t` WHERE `Jahr` = ?", $j);
        }
        return $n + msvJahrLoeschenAusfuehren($c, 'DELETE ' . ENDSCH_JM_SQL, $j);
    }
);
