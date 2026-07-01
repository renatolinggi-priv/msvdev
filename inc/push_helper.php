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
 * Sendet einen Push an ALLE Geraete eines Benutzers (Web-Push UND native App).
 *
 * Web-Push (push_abos, VAPID) und Native-Push (push_geraete_native, FCM) laufen
 * PARALLEL und unabhaengig: ein Kanal-Fehler beeintraechtigt den anderen nicht.
 *
 * @return int Anzahl erfolgreicher Zustellungen (web + native summiert).
 *             Abgelaufene Web-Abos (404/410) und tote FCM-Tokens werden automatisch geloescht.
 */
if (!function_exists('sendePushAnBenutzer')) {
    function sendePushAnBenutzer(int $userId, string $titel, string $text, string $url = '/portal/dashboard.php', ?int $badge = null): int {
        $db = getDB();
        $erfolgreich = 0;

        // ===== 1) Web-Push (bestehend, unveraendert) =============================
        $stmt = $db->prepare('SELECT endpoint, p256dh, auth_key FROM push_abos WHERE benutzer_id = ?');
        $stmt->execute([$userId]);
        $abos = $stmt->fetchAll();

        $pub     = pushGetSetting('vapid_public_key');
        $priv    = pushGetSetting('vapid_private_key');
        $subject = pushGetSetting('vapid_subject', 'mailto:admin@msvwilen.ch');

        if ($abos && $pub && $priv) {
            try {
                $webPush = new WebPush(['VAPID' => [
                    'subject'    => $subject,
                    'publicKey'  => $pub,
                    'privateKey' => $priv,
                ]]);
                $webPush->setReuseVAPIDHeaders(true);

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

                $delStmt = $db->prepare('DELETE FROM push_abos WHERE endpoint = ?');
                foreach ($webPush->flush() as $report) {
                    $endpoint = $report->getRequest()->getUri()->__toString();
                    if ($report->isSuccess()) {
                        $erfolgreich++;
                        continue;
                    }
                    $code = $report->getResponse()?->getStatusCode() ?? 0;
                    if (in_array($code, [404, 410], true)) {
                        $delStmt->execute([$endpoint]); // abgelaufenes Abo entfernen (Selbstreinigung)
                    } else {
                        error_log('push_helper: Web-Push fehlgeschlagen (HTTP ' . $code . ') fuer ' . $endpoint . ': ' . $report->getReason());
                    }
                }
            } catch (\Throwable $e) {
                error_log('push_helper: WebPush fehlgeschlagen: ' . $e->getMessage());
            }
        } elseif ($abos && (!$pub || !$priv)) {
            error_log('push_helper: VAPID-Keys fehlen in settings. Bitte tools/vapid_setup.php ausfuehren.');
        }

        // ===== 2) Native-Push (FCM, additiv) ====================================
        // In eigenem try/catch -> kann den Web-Push niemals beeintraechtigen.
        try {
            $erfolgreich += sendeNativePushAnBenutzer($db, $userId, $titel, $text, $url, $badge);
        } catch (\Throwable $e) {
            error_log('push_helper: Native-Push fehlgeschlagen: ' . $e->getMessage());
        }

        return $erfolgreich;
    }
}

/**
 * Zentrale Zustellung einer Benachrichtigung an EINEN Benutzer ueber zwei Kanaele:
 *
 *   1) In-App (IMMER): ein Eintrag in benachrichtigungen_inbox -> erscheint in der
 *      Glocke (Badge + Dropdown + portal/mitteilungen.php), unabhaengig von Geraeten
 *      oder Push-Einstellungen. So ist der Verlauf vollstaendig.
 *   2) Push (BEDINGT): nur wenn benachrichtigung_prefs.push_aktiv aktiv ist
 *      (fehlende Zeile = Default an). Themen-/Kategorie-Filter bleiben bewusst beim
 *      Aufrufer (z.B. chatSendPushToUser prueft die chat-Pref vor dem Aufruf).
 *
 * Fuer reine Push-Tests (api/push.php?action=test) NICHT verwenden -> dort weiterhin
 * direkt sendePushAnBenutzer() aufrufen, damit kein Inbox-Eintrag entsteht.
 *
 * @return int Anzahl erfolgreicher Push-Zustellungen (0 wenn Push aus/keine Geraete).
 */
if (!function_exists('benachrichtigungZustellen')) {
    function benachrichtigungZustellen(int $userId, string $titel, string $text, string $url = '/portal/dashboard.php', string $kategorie = 'allgemein'): int {
        $db = getDB();

        // 1) In-App immer persistieren (best-effort: ein DB-Fehler darf den Push nicht verhindern)
        try {
            $ins = $db->prepare('INSERT INTO benachrichtigungen_inbox (user_id, titel, text, url, kategorie) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$userId, $titel, $text, $url, $kategorie]);
        } catch (\Throwable $e) {
            error_log('benachrichtigungZustellen: Inbox-Insert fehlgeschlagen (user ' . $userId . '): ' . $e->getMessage());
        }

        // 2) Push nur bei aktivem Hauptschalter (fehlende prefs-Zeile = Default an)
        try {
            $stmt = $db->prepare('SELECT COALESCE(push_aktiv, 1) FROM benachrichtigung_prefs WHERE user_id = ?');
            $stmt->execute([$userId]);
            $on = $stmt->fetchColumn();
            if ($on === false || (int) $on === 1) {
                return sendePushAnBenutzer($userId, $titel, $text, $url);
            }
        } catch (\Throwable $e) {
            error_log('benachrichtigungZustellen: Push-Versand fehlgeschlagen (user ' . $userId . '): ' . $e->getMessage());
        }
        return 0;
    }
}

/**
 * Native-Push (FCM HTTP v1) an alle App-Geraete eines Benutzers.
 * Dependency-frei (curl + openssl, kein zusaetzliches Composer-Paket).
 * Stiller No-Op, solange FCM nicht konfiguriert ist (Settings leer / Datei fehlt).
 *
 * @return int Anzahl erfolgreicher Zustellungen. Tote Tokens werden geloescht.
 */
if (!function_exists('sendeNativePushAnBenutzer')) {
    function sendeNativePushAnBenutzer(\PDO $db, int $userId, string $titel, string $text, string $url, ?int $badge = null): int {
        $stmt = $db->prepare('SELECT id, fcm_token FROM push_geraete_native WHERE benutzer_id = ?');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return 0; // kein App-Geraet
        }

        $projectId = pushGetSetting('fcm_project_id');
        $saPath    = pushGetSetting('fcm_service_account_path');
        if (!$projectId || !$saPath || !is_readable($saPath)) {
            return 0; // FCM noch nicht konfiguriert -> still ueberspringen
        }

        $access = fcmGetAccessToken($saPath);
        if (!$access) {
            return 0;
        }

        $endpoint = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/messages:send';
        $headers  = ['Authorization: Bearer ' . $access, 'Content-Type: application/json'];

        $erfolg = 0;
        $dead   = [];
        foreach ($rows as $row) {
            $aps = ['sound' => 'default'];
            if ($badge !== null) {
                $aps['badge'] = $badge;
            }
            $message = ['message' => [
                'token'        => $row['fcm_token'],
                'notification' => ['title' => $titel, 'body' => $text],
                'data'         => ['url' => (string) $url],
                'android'      => ['priority' => 'high', 'notification' => ['default_sound' => true]],
                'apns'         => ['payload' => ['aps' => $aps]],
            ]];

            $resp = pushHttpPost($endpoint, json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $headers);
            if ($resp['code'] === 200) {
                $erfolg++;
            } elseif ($resp['code'] === 404
                || ($resp['code'] === 400 && (strpos($resp['body'], 'UNREGISTERED') !== false || strpos($resp['body'], 'INVALID_ARGUMENT') !== false))) {
                $dead[] = (int) $row['id']; // toter/ungueltiger Token -> entfernen
            } else {
                error_log('push_helper: FCM-Zustellung HTTP ' . $resp['code'] . ': ' . $resp['body']);
            }
        }

        if ($dead) {
            $in = implode(',', array_fill(0, count($dead), '?'));
            $db->prepare('DELETE FROM push_geraete_native WHERE id IN (' . $in . ')')->execute($dead);
        }
        return $erfolg;
    }
}

/**
 * Holt einen OAuth2-Access-Token (Scope firebase.messaging) aus der Service-Account-JSON.
 * RS256-JWT wird mit openssl signiert; Ergebnis wird in settings gecacht (~1 h).
 */
if (!function_exists('fcmGetAccessToken')) {
    function fcmGetAccessToken(string $saPath): ?string {
        $cached = pushGetSetting('fcm_access_token');
        $exp    = (int) pushGetSetting('fcm_access_token_exp', '0');
        if ($cached && $exp > time() + 60) {
            return $cached;
        }

        $json = json_decode((string) @file_get_contents($saPath), true);
        if (!is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            error_log('push_helper: FCM Service-Account ungueltig: ' . $saPath);
            return null;
        }
        $tokenUri = $json['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        $now      = time();

        $b64 = static function (string $d): string {
            return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
        };
        $jwtHeader = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtClaims = $b64(json_encode([
            'iss'   => $json['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => $tokenUri,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));
        $signingInput = $jwtHeader . '.' . $jwtClaims;

        $signature = '';
        if (!openssl_sign($signingInput, $signature, $json['private_key'], 'sha256')) {
            error_log('push_helper: FCM JWT-Signatur fehlgeschlagen.');
            return null;
        }
        $jwt = $signingInput . '.' . $b64($signature);

        $resp = pushHttpPost($tokenUri, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]), ['Content-Type: application/x-www-form-urlencoded']);

        if ($resp['code'] !== 200) {
            error_log('push_helper: FCM Token-Abruf HTTP ' . $resp['code'] . ': ' . $resp['body']);
            return null;
        }
        $data   = json_decode($resp['body'], true);
        $access = $data['access_token'] ?? null;
        if (!$access) {
            return null;
        }
        $expIn = (int) ($data['expires_in'] ?? 3600);
        pushSetSetting('fcm_access_token', $access);
        pushSetSetting('fcm_access_token_exp', (string) ($now + $expIn));
        return $access;
    }
}

/**
 * Minimaler HTTP-POST via curl. @return array{code:int, body:string}
 */
if (!function_exists('pushHttpPost')) {
    function pushHttpPost(string $url, string $body, array $headers): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($resp === false) {
            $resp = curl_error($ch);
            $code = 0;
        }
        curl_close($ch);
        return ['code' => $code, 'body' => (string) $resp];
    }
}
