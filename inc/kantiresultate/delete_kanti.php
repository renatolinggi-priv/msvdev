<?php
/**
 * Löscht alle Kantonalstich-Resultate eines Jahres (vorher automatische DB-Sicherung).
 * POST: jahr, csrf_token, optional nur_zaehlen=1. Ablauf: inc/jahr_loeschen.inc.php
 */
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);
require_once __DIR__ . '/../jahr_loeschen.inc.php';

msvJahrLoeschen($conn, 'kanti-loeschen', 'Kantonalstich-Resultate',
    fn(mysqli $c, int $j): int => msvJahrLoeschenZahl($c, "SELECT COUNT(*) FROM `kantiresultate` WHERE `Jahr` = ?", $j),
    fn(mysqli $c, int $j): int => msvJahrLoeschenAusfuehren($c, "DELETE FROM `kantiresultate` WHERE `Jahr` = ?", $j)
);
