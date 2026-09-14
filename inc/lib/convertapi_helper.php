<?php
/**
 * convertapi_helper.php — Office → PDF (DOCX, XLSX) über einen externen Konvertierungsdienst
 *
 * Der Dateiname ist historisch (ursprünglich nur ConvertAPI). Seit 11.09.2026 ist der
 * Dienst über msvjm_config.php umschaltbar, damit ein Rückbau ohne Code-Änderung möglich ist:
 *
 *   'pdf_converter' => 'iloveapi',            // 'iloveapi' | 'convertapi'
 *   'iloveapi'   => [
 *       'public' => 'project_public_…',       // Pflicht: Projekt-Public-Key (iloveapi.com → Projects)
 *       'secret' => 'secret_key_…',           // optional: erlaubt lokal signierte Tokens ohne Auth-Request
 *   ],
 *   'convertapi' => ['secret' => '…'],        // ConvertAPI (Trial läuft nach 30 Tagen aus)
 *
 * Ohne 'pdf_converter' gilt: iloveapi, wenn dessen Public-Key gesetzt ist, sonst convertapi.
 * Rückbau auf ConvertAPI: 'pdf_converter' => 'convertapi' setzen.
 *
 * Verwendung:
 *   require_once __DIR__ . '/../lib/convertapi_helper.php';
 *   $pdfPath = convertToPdf('/tmp/standblatt.xlsx', 'xlsx');   // oder convertDocxToPdf($docx)
 *   // ... PDF verwenden ...
 *   unlink($pdfPath); // Caller raeumt auf
 *
 * Aufrufer: inc/endschloesen/generate_standblatt.php (?format=pdf),
 *           inc/jmstandblatt/generate_jmstandblatt_pdf.php, generate_jmstandblatt_all_pdf.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ConvertApi\ConvertApi;

/** Externe Konfiguration (msvjm_config.php eine Ebene über dem Docroot) laden. */
function msvPdfKonverterConfig(): array {
    static $config = null;
    if ($config === null) {
        $configPath = __DIR__ . '/../../../msvjm_config.php';
        if (!file_exists($configPath)) {
            throw new RuntimeException('Config-Datei nicht gefunden');
        }
        $config = require $configPath;
        if (!is_array($config)) $config = [];
    }
    return $config;
}

/** Aktiver Dienst: 'iloveapi' oder 'convertapi'. */
function msvPdfKonverter(): string {
    $c = msvPdfKonverterConfig();
    $wahl = strtolower(trim((string)($c['pdf_converter'] ?? '')));
    if ($wahl === 'iloveapi' || $wahl === 'convertapi') return $wahl;
    return !empty($c['iloveapi']['public']) ? 'iloveapi' : 'convertapi';
}

/**
 * Konvertiert eine DOCX-Datei zu PDF (Kompatibilitäts-Wrapper).
 *
 * @return string Pfad zur generierten PDF-Datei (Temp-Datei, Caller muss loeschen)
 */
function convertDocxToPdf(string $docxPath): string {
    return convertToPdf($docxPath, 'docx');
}

/**
 * Konvertiert eine Office-Datei (docx, xlsx, …) zu PDF über den konfigurierten Dienst.
 * XLSX: Seiteneinrichtung und Druckbereich der Arbeitsmappe werden uebernommen.
 *
 * @param string $inputPath  Pfad zur Eingabedatei
 * @param string $fromFormat 'docx' | 'xlsx'
 * @return string Pfad zur generierten PDF-Datei (Temp-Datei, Caller muss loeschen)
 * @throws RuntimeException Falls Zugangsdaten fehlen oder die Konvertierung fehlschlaegt
 */
function convertToPdf(string $inputPath, string $fromFormat = 'docx'): string {
    if (!file_exists($inputPath)) {
        throw new RuntimeException(strtoupper($fromFormat) . '-Datei nicht gefunden: ' . $inputPath);
    }
    return msvPdfKonverter() === 'iloveapi'
        ? convertToPdfViaIloveApi($inputPath, $fromFormat)
        : convertToPdfViaConvertApi($inputPath, $fromFormat);
}

// ===========================================================================
//  ConvertAPI (convertapi.com) — Composer-Paket convertapi/convertapi-php
// ===========================================================================
function convertToPdfViaConvertApi(string $inputPath, string $fromFormat): string {
    $secret = (string)(msvPdfKonverterConfig()['convertapi']['secret'] ?? '');
    if ($secret === '') {
        throw new RuntimeException('ConvertAPI Secret nicht konfiguriert (msvjm_config.php → convertapi.secret)');
    }
    ConvertApi::setApiSecret($secret);
    try {
        $result = ConvertApi::convert('pdf', ['File' => $inputPath], $fromFormat);
        $savedFiles = $result->saveFiles(sys_get_temp_dir());
        if (empty($savedFiles) || !file_exists($savedFiles[0])) {
            throw new RuntimeException('keine Datei zurueckgegeben');
        }
        return $savedFiles[0];
    } catch (\Exception $e) {
        error_log('[ConvertAPI] Fehler: ' . $e->getMessage());
        throw new RuntimeException('ConvertAPI: ' . $e->getMessage());
    }
}

// ===========================================================================
//  iLoveAPI (iloveapi.com / iLovePDF) — REST direkt per cURL, kein Composer-Paket nötig
//  Ablauf: Token → GET /v1/start/officepdf → POST upload → POST process → GET download
//  Kosten: «Office to PDF» = 10 Credits pro Datei (Gratis-Kontingent 2500 Credits/Monat).
// ===========================================================================
function convertToPdfViaIloveApi(string $inputPath, string $fromFormat): string {
    $cfg    = msvPdfKonverterConfig()['iloveapi'] ?? [];
    $public = trim((string)($cfg['public'] ?? ''));
    $secret = trim((string)($cfg['secret'] ?? ''));
    if ($public === '') {
        throw new RuntimeException('iLoveAPI Public-Key nicht konfiguriert (msvjm_config.php → iloveapi.public)');
    }

    try {
        $token = iloveapiToken($public, $secret);

        // 1) Task starten → zugewiesener Worker-Server + Task-ID
        $start  = iloveapiRequest('GET', 'https://api.ilovepdf.com/v1/start/officepdf', $token);
        $server = (string)($start['server'] ?? '');
        $task   = (string)($start['task'] ?? '');
        if ($server === '' || $task === '') {
            throw new RuntimeException('Task konnte nicht gestartet werden');
        }
        $base = 'https://' . $server . '/v1';

        // 2) Datei hochladen
        $upload = iloveapiRequest('POST', $base . '/upload', $token, [
            'task' => $task,
            'file' => new CURLFile($inputPath, null, basename($inputPath)),
        ], true);
        $serverFilename = (string)($upload['server_filename'] ?? '');
        if ($serverFilename === '') {
            throw new RuntimeException('Upload ohne server_filename');
        }

        // 3) Verarbeiten
        $ausgabeName = pathinfo($inputPath, PATHINFO_FILENAME);
        $process = iloveapiRequest('POST', $base . '/process', $token, [
            'task'  => $task,
            'tool'  => 'officepdf',
            'files' => [['server_filename' => $serverFilename, 'filename' => $ausgabeName . '.' . $fromFormat]],
        ]);
        if (($process['status'] ?? '') !== 'TaskSuccess') {
            throw new RuntimeException('Verarbeitung: ' . ($process['status'] ?? 'unbekannter Status'));
        }

        // 4) Ergebnis laden (Binärdaten; bei Fehler JSON)
        $pdf = iloveapiRequest('GET', $base . '/download/' . rawurlencode($task), $token, null, false, true);
        if (!is_string($pdf) || strncmp($pdf, '%PDF', 4) !== 0) {
            throw new RuntimeException('Download lieferte kein PDF');
        }
        $tmpPdf = tempnam(sys_get_temp_dir(), 'iloveapi_');
        rename($tmpPdf, $tmpPdf . '.pdf'); $tmpPdf .= '.pdf'; // Stub umbenennen statt liegen lassen
        if (file_put_contents($tmpPdf, $pdf) === false) {
            throw new RuntimeException('PDF konnte nicht gespeichert werden');
        }
        return $tmpPdf;

    } catch (\Exception $e) {
        error_log('[iLoveAPI] Fehler: ' . $e->getMessage());
        throw new RuntimeException('iLoveAPI: ' . $e->getMessage());
    }
}

/**
 * JWT für iLoveAPI: mit Secret lokal signiert (HS256, wie das offizielle SDK, jti = Public-Key);
 * ohne Secret über POST /v1/auth mit dem Public-Key.
 */
function iloveapiToken(string $public, string $secret): string {
    if ($secret !== '') {
        $b64 = static fn(string $d): string => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
        $t = time();
        $header  = $b64(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = $b64(json_encode([
            'iss' => $_SERVER['HTTP_HOST'] ?? 'msvwilen.ch',
            'aud' => '',
            'iat' => $t,
            'nbf' => $t,
            'exp' => $t + 7200,
            'jti' => $public,
        ]));
        $sig = $b64(hash_hmac('sha256', $header . '.' . $payload, $secret, true));
        return $header . '.' . $payload . '.' . $sig;
    }
    $auth = iloveapiRequest('POST', 'https://api.ilovepdf.com/v1/auth', null, ['public_key' => $public]);
    $token = (string)($auth['token'] ?? '');
    if ($token === '') {
        throw new RuntimeException('Auth lieferte kein Token');
    }
    return $token;
}

/**
 * HTTP-Aufruf gegen iLoveAPI.
 * @param array|null $body      JSON-Body (oder Multipart-Felder bei $multipart)
 * @param bool       $multipart Multipart-Upload (CURLFile) statt JSON
 * @param bool       $binary    Rohantwort (Download) statt JSON-Array zurückgeben
 * @return array|string
 */
function iloveapiRequest(string $method, string $url, ?string $token, ?array $body = null, bool $multipart = false, bool $binary = false) {
    $headers = ['Accept: application/json'];
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST  => $method,
    ];
    if ($body !== null) {
        if ($multipart) {
            $opts[CURLOPT_POSTFIELDS] = $body; // cURL setzt multipart/form-data selbst
        } else {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        throw new RuntimeException('Verbindung fehlgeschlagen (' . $err . ')');
    }
    if ($code >= 400) {
        $j = json_decode((string)$resp, true);
        $msg = $j['error']['message'] ?? $j['message'] ?? $j['error'] ?? substr((string)$resp, 0, 200);
        if (is_array($msg)) $msg = json_encode($msg);
        throw new RuntimeException('HTTP ' . $code . ' – ' . $msg);
    }
    if ($binary) return (string)$resp;
    $j = json_decode((string)$resp, true);
    if (!is_array($j)) {
        throw new RuntimeException('Ungültige Antwort von ' . parse_url($url, PHP_URL_PATH));
    }
    return $j;
}
