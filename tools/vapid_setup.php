<?php
/**
 * vapid_setup.php - EINMALIGES Setup fuer Web-Push (VAPID + Cron-Token)
 * Vorlage: benachrichtigungs-konzept.md (Abschnitt 2.1)
 *
 * Erzeugt das VAPID-Schluesselpaar + einen Cron-Trigger-Token und legt beides in
 * der settings-Tabelle ab. Laeuft per CLI (Cron/Shell) oder per HTTP (nur fuer
 * eingeloggte Admins).
 *
 *   CLI : php tools/vapid_setup.php [--subject=mailto:webmaster@msvwilen.ch]
 *   HTTP: https://admin.msvwilen.ch/tools/vapid_setup.php   (Admin-Login noetig)
 *
 * WICHTIG: Das Skript bricht ab, wenn bereits ein vapid_private_key existiert
 * (kein versehentliches Rotieren -> wuerde alle bestehenden Abos brechen).
 * Nach erfolgreichem Lauf darf die Datei geloescht werden.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/push_helper.php'; // settings-Helfer + Autoloader

use Minishlink\WebPush\VAPID;

$cli = (PHP_SAPI === 'cli');

if (!$cli) {
    // HTTP nur fuer eingeloggte Admins
    require_once __DIR__ . '/../auth.php';
    requireRole('admin');
    header('Content-Type: text/plain; charset=utf-8');
}

function out(string $msg): void { echo $msg . PHP_EOL; }

// --- Re-Run-Schutz: niemals bestehende Keys ueberschreiben -------------------
$vorhanden = pushGetSetting('vapid_private_key');
if (!empty($vorhanden)) {
    out('ABBRUCH: vapid_private_key existiert bereits.');
    out('Ein erneutes Erzeugen wuerde alle bestehenden Push-Abos unbrauchbar machen.');
    out('Falls du die Keys wirklich neu erzeugen willst, loesche zuerst die vier');
    out('settings-Eintraege (vapid_public_key, vapid_private_key, vapid_subject, cron_trigger_key).');
    exit($cli ? 1 : 0);
}

// --- Subject (mailto:) bestimmen --------------------------------------------
$subject = 'mailto:webmaster@msvwilen.ch';
if ($cli) {
    $args = getopt('', ['subject::']);
    if (!empty($args['subject'])) $subject = (string) $args['subject'];
} elseif (!empty($_GET['subject'])) {
    $subject = (string) $_GET['subject'];
}

// --- VAPID-Keys + Cron-Token erzeugen ---------------------------------------
try {
    $keys = VAPID::createVapidKeys(); // ['publicKey' => ..., 'privateKey' => ...]
} catch (\Throwable $e) {
    out('FEHLER beim Erzeugen der VAPID-Keys: ' . $e->getMessage());
    out('Pruefe, ob die PHP-Extensions openssl + gmp/bcmath aktiv sind und web-push installiert ist.');
    exit($cli ? 1 : 0);
}

$cronToken = bin2hex(random_bytes(24));

pushSetSetting('vapid_public_key',  $keys['publicKey']);
pushSetSetting('vapid_private_key', $keys['privateKey']);
pushSetSetting('vapid_subject',     $subject);
pushSetSetting('cron_trigger_key',  $cronToken);

out('VAPID-Setup erfolgreich abgeschlossen.');
out('');
out('VAPID Public Key : ' . $keys['publicKey']);
out('VAPID Subject    : ' . $subject);
out('Cron-Trigger-Key : ' . $cronToken);
out('');
out('Naechste Schritte:');
out('1. Cronjob beim Hoster einrichten (taeglich, z.B. 08:00):');
out('   CLI : php ' . realpath(__DIR__ . '/../cron/check_benachrichtigungen.php'));
out('   HTTP: https://admin.msvwilen.ch/cron/check_benachrichtigungen.php?key=' . $cronToken);
out('2. Diese Datei (tools/vapid_setup.php) anschliessend loeschen.');
