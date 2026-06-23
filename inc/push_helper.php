<?php
// push_helper.php - Web-Push-Versand fuer das Mitgliederportal
// Vorlage: benachrichtigungs-konzept.md (Abschnitt 2.5)
//
// Kapselt minishlink/web-push: VAPID-Konfig aus der settings-Tabelle, Versand
// an alle Geraete eines Benutzers, automatische Selbstreinigung toter Abos (404/410).
//
// Die WebPush-Klassen werden NUR innerhalb der Funktionen verwendet -> diese Datei
// laedt auch dann fehlerfrei, wenn `composer update` (web-push) noch nicht lief.

require_once __DIR__ . '/dbconnect.inc.php';   // getDB()
require_once __DIR__ . '/vendor/autoload.php'; // Composer-Autoloader

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Liest einen Wert aus der settings-Tabelle (Key-Value).
 */
if (!function_exists('pushGetSetting')) {
    function pushGetSetting(string $key, ?string $default = null): ?string {
        $stmt = getDB()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val === false || $val === null) ? $default : (string) $val;
    }
}

/**
 * Schreibt einen Wert in die settings-Tabelle (Upsert).
 */
if (!function_exists('pushSetSetting')) {
    function pushSetSetting(string $key, string $value): void {
        $stmt = getDB()->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([$key, $value]);
    }
}

/**
 * Lead-Time (Tage) eines Themas aus den Settings, auf 0..30 geklemmt.
 */
if (!function_exists('pushLeadTime')) {
    function pushLeadTime(string $key, int $default): int {
        $v = pushGetSetting($key);
        $n = ($v === null || $v === '') ? $default : (int) $v;
        return max(0, min(30, $n));
    }
}

/**
 * Sendet einen Push an ALLE Geraete eines Benutzers.
 *
 * @return int Anzahl erfolgreicher Zustellungen (0 = kein Geraet / alles fehlgeschlagen).
 *             Abgelaufene Abos (HTTP 404/410) werden automatisch geloescht.
 */
if (!function_exists('sendePushAnBenutzer')) {
    function sendePushAnBenutzer(int $userId, string $titel, string $text, string $url = '/portal/dashboard.php'): int {
        $db = getDB();

        $stmt = $db->prepare('SELECT endpoint, p256dh, auth_key FROM push_abos WHERE benutzer_id = ?');
        $stmt->execute([$userId]);
        $abos = $stmt->fetchAll();
        if (!$abos) {
            return 0; // kein Geraet abonniert
        }

        $pub     = pushGetSetting('vapid_public_key');
        $priv    = pushGetSetting('vapid_private_key');
        $subject = pushGetSetting('vapid_subject', 'mailto:admin@msvwilen.ch');
        if (!$pub || !$priv) {
            error_log('push_helper: VAPID-Keys fehlen in settings. Bitte tools/vapid_setup.php ausfuehren.');
            return 0;
        }

        try {
            $webPush = new WebPush(['VAPID' => [
                'subject'    => $subject,
                'publicKey'  => $pub,
                'privateKey' => $priv,
            ]]);
            $webPush->setReuseVAPIDHeaders(true);
        } catch (\Throwable $e) {
            error_log('push_helper: WebPush-Init fehlgeschlagen: ' . $e->getMessage());
            return 0;
        }

        $payload = json_encode(
            ['titel' => $titel, 'text' => $text, 'url' => $url],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        foreach ($abos as $abo) {
            try {
                $sub = Subscription::create([
                    'endpoint' => $abo['endpoint'],
                    'keys'     => ['p256dh' => $abo['p256dh'], 'auth' => $abo['auth_key']],
                ]);
                $webPush->queueNotification($sub, $payload);
            } catch (\Throwable $e) {
                error_log('push_helper: queueNotification fehlgeschlagen: ' . $e->getMessage());
            }
        }

        $erfolgreich = 0;
        $delStmt = $db->prepare('DELETE FROM push_abos WHERE endpoint = ?');
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            if ($report->isSuccess()) {
                $erfolgreich++;
                continue;
            }
            $code = $report->getResponse()?->getStatusCode() ?? 0;
            if (in_array($code, [404, 410], true)) {
                // Abgelaufenes Abo entfernen (Selbstreinigung)
                $delStmt->execute([$endpoint]);
            } else {
                error_log('push_helper: Zustellung fehlgeschlagen (HTTP ' . $code . ') fuer ' . $endpoint . ': ' . $report->getReason());
            }
        }

        return $erfolgreich;
    }
}
