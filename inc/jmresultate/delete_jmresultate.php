<?php
/**
 * Löscht alle JM-Resultate eines Jahres (vorher automatische DB-Sicherung).
 * POST: year, csrf_token, optional nur_zaehlen=1. Ablauf: inc/jahr_loeschen.inc.php
 */
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);
require_once __DIR__ . '/../changelog_helper.php';
require_once __DIR__ . '/../jahr_loeschen.inc.php';

msvJahrLoeschen($conn, 'jm-loeschen', 'JM-Resultate',
    fn(mysqli $c, int $j): int => msvJahrLoeschenZahl($c,
        "SELECT COUNT(*) FROM jmresultate r JOIN JMDefinition d ON d.ID = r.jmdefinitionID WHERE d.year = ?", $j),
    fn(mysqli $c, int $j): int => msvJahrLoeschenAusfuehren($c,
        "DELETE r FROM jmresultate r JOIN JMDefinition d ON d.ID = r.jmdefinitionID WHERE d.year = ?", $j),
    function (int $j, int $n): void {
        logChangelog('resultate', 'geloescht', "JM-Resultate $j gelöscht",
            ['tabelle' => 'jmresultate', 'jahr' => $j, 'sichtbar' => 0]);
    }
);
