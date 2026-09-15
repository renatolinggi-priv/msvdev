<?php
/**
 * inc/lib/dokument_datei.inc.php
 *
 * Gemeinsame Pruefungen fuer Dokument-Uploads (api/dokument_upload.php, api/dokument_update.php).
 * Vorher lag die MIME-Whitelist doppelt vor; finfo meldet Office-Dateien (OOXML = ZIP-Container)
 * gelegentlich als application/zip, was den DOCX/XLSX-Upload ablehnte.
 */

const DOKUMENT_MAX_BYTES = 10 * 1024 * 1024;

const DOKUMENT_MIMES = [
    'application/pdf'                                                         => 'pdf',
    'image/jpeg'                                                              => 'jpg',
    'image/png'                                                               => 'png',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'       => 'xlsx',
    'application/vnd.ms-excel'                                                => 'xls',
];

/**
 * Prueft eine hochgeladene Datei ($_FILES['x']) und liefert die vertrauenswuerdige Endung.
 *
 * @return array{ok:bool, ext:string, message:string}
 */
function dokument_datei_pruefen(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE  => 'Datei zu gross (Server-Limit)',
            UPLOAD_ERR_FORM_SIZE => 'Datei zu gross',
            UPLOAD_ERR_PARTIAL   => 'Upload unvollständig',
            UPLOAD_ERR_NO_FILE   => 'Keine Datei ausgewählt',
        ];
        return ['ok' => false, 'ext' => '', 'message' => $errors[$file['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Upload-Fehler'];
    }
    if (($file['size'] ?? 0) > DOKUMENT_MAX_BYTES) {
        return ['ok' => false, 'ext' => '', 'message' => 'Datei zu gross (max. 10 MB)'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? (string)finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) finfo_close($finfo);

    $ext = DOKUMENT_MIMES[$mime] ?? '';
    if ($ext === '' && in_array($mime, ['application/zip', 'application/octet-stream'], true)) {
        // OOXML ist ein ZIP-Container: Endung des Client-Namens nur fuer docx/xlsx akzeptieren
        // und den Container-Inhalt kurz gegenpruefen ([Content_Types].xml muss existieren).
        $clientExt = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (in_array($clientExt, ['docx', 'xlsx'], true) && class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($file['tmp_name']) === true) {
                $istOoxml = $zip->locateName('[Content_Types].xml') !== false;
                $zip->close();
                if ($istOoxml) $ext = $clientExt;
            }
        }
    }
    if ($ext === '') {
        return ['ok' => false, 'ext' => '', 'message' => 'Dateityp nicht erlaubt (PDF, DOCX, XLSX, JPG, PNG)'];
    }
    return ['ok' => true, 'ext' => $ext, 'message' => ''];
}

/**
 * Datum aus dem Formular pruefen. Leer -> null; ungueltig -> Exception
 * (vorher lief strtotime('foo') auf false und jahr wurde 1970).
 */
function dokument_datum_pruefen($datum): ?string
{
    $datum = trim((string)$datum);
    if ($datum === '') return null;
    $d = DateTime::createFromFormat('Y-m-d', $datum);
    if (!$d || $d->format('Y-m-d') !== $datum) {
        throw new InvalidArgumentException('Ungültiges Datum (erwartet JJJJ-MM-TT)');
    }
    return $datum;
}

/** Sicherer Dateiname auf der Platte: Zeitstempel + bereinigter Originalname + verifizierte Endung. */
function dokument_zielname(string $originalName, string $ext): string
{
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME)) ?: 'dokument';
    return time() . '_' . $safe . '.' . $ext;
}
