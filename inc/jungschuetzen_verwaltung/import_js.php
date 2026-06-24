<?php
// inc/jungschuetzen_verwaltung/import_js.php
// Excel-Import fuer Jungschuetzen (SSV-Mitgliederverzeichnis o.ae.).
//   action=preview : hochgeladene Datei parsen, Spalten mappen, nach JSK-Jahrgang
//                    filtern -> JSON-Vorschau (nur Vorschlaege).
//   action=import  : bestaetigte Zeilen (JSON) einfuegen/aktualisieren.
// PDO, CSRF, Vorstand/Admin.

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../dbconnect.inc.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;

header('Content-Type: application/json; charset=utf-8');
requireRoleJson(['admin', 'vorstand']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}
$csrf = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validateCsrf($csrf)) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.', 403);
}

$action = $_POST['action'] ?? 'preview';

// Mindest-/Hoechstalter fuer den Jungschuetzen-Vorschlag (Stand: heute)
const JSK_MIN_ALTER = 8;
const JSK_MAX_ALTER = 22;

/** Normalisiert einen Header-Namen fuer den Spalten-Abgleich. */
function jsImportNorm(string $h): string {
    return strtolower(preg_replace('/[^a-z0-9]/i', '', $h));
}

/** Findet die erste passende Spalte (Header-Synonyme) -> Spaltenbuchstabe oder null. */
function jsImportCol(array $headerMap, array $candidates): ?string {
    foreach ($candidates as $c) {
        $key = jsImportNorm($c);
        if (isset($headerMap[$key])) return $headerMap[$key];
    }
    return null;
}

/** Wandelt einen Zellwert (Datum) in 'Y-m-d' oder null. */
function jsImportDate($cell): ?string {
    if ($cell === null || $cell === '') return null;
    if (is_numeric($cell)) {
        try { return XlsDate::excelToDateTimeObject((float) $cell)->format('Y-m-d'); }
        catch (Throwable $e) { /* weiter unten als String versuchen */ }
    }
    $s = trim((string) $cell);
    if ($s === '') return null;
    // gaengige Formate: dd.mm.yyyy, yyyy-mm-dd, dd/mm/yyyy
    $s2 = str_replace('/', '.', $s);
    foreach (['d.m.Y', 'Y-m-d', 'd.m.y'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $s2);
        if ($dt && $dt->format($fmt) === $s2) return $dt->format('Y-m-d');
    }
    $ts = strtotime($s);
    return $ts ? date('Y-m-d', $ts) : null;
}

// ---------------------------------------------------------------------------
// PREVIEW: Datei einlesen, mappen, filtern
// ---------------------------------------------------------------------------
if ($action === 'preview') {
    if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        json_error('Keine Datei hochgeladen.');
    }
    if ($_FILES['file']['size'] > 15 * 1024 * 1024) {
        json_error('Datei zu gross (max. 15 MB).');
    }
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
        json_error('Nur .xlsx, .xls oder .csv erlaubt.');
    }

    try {
        $reader = IOFactory::createReaderForFile($_FILES['file']['tmp_name']);
        if (method_exists($reader, 'setReadDataOnly')) $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($_FILES['file']['tmp_name']);
    } catch (Throwable $e) {
        error_log('import_js preview load: ' . $e->getMessage());
        json_error('Datei konnte nicht gelesen werden.');
    }

    $sheet = $spreadsheet->getActiveSheet();
    $rows  = $sheet->toArray(null, true, true, true); // [rowNum => [col => value]]
    if (count($rows) < 2) {
        json_error('Datei enthält keine Datenzeilen.');
    }

    // Header-Zeile (erste Zeile) -> normalisierter Name => Spaltenbuchstabe
    $headerRowNum = array_key_first($rows);
    $headerMap = [];
    foreach ($rows[$headerRowNum] as $col => $val) {
        if ($val !== null && trim((string) $val) !== '') {
            $headerMap[jsImportNorm((string) $val)] = $col;
        }
    }

    $cVorname = jsImportCol($headerMap, ['FirstName', 'Vorname']);
    $cName    = jsImportCol($headerMap, ['LastName', 'Name', 'Nachname']);
    $cBirth   = jsImportCol($headerMap, ['BirthDate', 'Geburtsdatum', 'Geburtstag']);
    $cStreet  = jsImportCol($headerMap, ['Street', 'Strasse', 'Adresse']);
    $cPlz     = jsImportCol($headerMap, ['PostCode', 'PLZ', 'Postleitzahl']);
    $cCity    = jsImportCol($headerMap, ['City', 'Ort']);
    $cMobile  = jsImportCol($headerMap, ['PrivateMobilePhone', 'Mobile', 'Handy', 'BusinessMobilePhone']);
    $cEmail   = jsImportCol($headerMap, ['PrimaryEmail', 'Email', 'EMail', 'AdditionalEmail']);
    $cAhv     = jsImportCol($headerMap, ['InsuranceNumber', 'AHVNummer', 'AHV']);

    if (!$cVorname || !$cName) {
        json_error('Spalten "Vorname"/"Name" (FirstName/LastName) wurden nicht gefunden.');
    }

    $today = new DateTime('today');
    $out = [];
    $skipped = 0;
    $total = 0;

    foreach ($rows as $rNum => $row) {
        if ($rNum == $headerRowNum) continue;
        $vorname = trim((string) ($row[$cVorname] ?? ''));
        $name    = trim((string) ($row[$cName] ?? ''));
        if ($vorname === '' && $name === '') continue; // Leerzeile
        $total++;

        $geb = $cBirth ? jsImportDate($row[$cBirth] ?? null) : null;
        $age = null;
        if ($geb) {
            try { $age = (new DateTime($geb))->diff($today)->y; } catch (Throwable $e) { $age = null; }
        }
        // Vorschlag nur fuer plausible Jungschuetzen-Jahrgaenge (oder unbekanntes Alter)
        $suggested = ($age === null) || ($age >= JSK_MIN_ALTER && $age <= JSK_MAX_ALTER);
        if (!$suggested) { $skipped++; continue; }

        $out[] = [
            'vorname'      => $vorname,
            'name'         => $name,
            'geburtsdatum' => $geb,
            'strasse'      => $cStreet ? trim((string) ($row[$cStreet] ?? '')) : '',
            'plz'          => $cPlz ? trim((string) ($row[$cPlz] ?? '')) : '',
            'ort'          => $cCity ? trim((string) ($row[$cCity] ?? '')) : '',
            'email'        => $cEmail ? trim((string) ($row[$cEmail] ?? '')) : '',
            'mobile'       => $cMobile ? trim((string) ($row[$cMobile] ?? '')) : '',
            'ahvnummer'    => $cAhv ? trim((string) ($row[$cAhv] ?? '')) : '',
            'alter'        => $age,
        ];
    }

    echo json_encode([
        'success' => true,
        'rows'    => $out,
        'total'   => $total,
        'skipped' => $skipped,
        'message' => count($out) . ' Vorschläge (Jahrgänge ' . JSK_MIN_ALTER . '–' . JSK_MAX_ALTER . ' J.), '
                     . $skipped . ' übersprungen.',
    ]);
    exit;
}

// ---------------------------------------------------------------------------
// IMPORT: bestaetigte Zeilen speichern
// ---------------------------------------------------------------------------
if ($action === 'import') {
    $rows = json_decode($_POST['rows'] ?? '[]', true);
    if (!is_array($rows) || !$rows) {
        json_error('Keine Daten zum Importieren.');
    }
    $kursJahr = ($_POST['kursjahr'] ?? '') === '' ? null : (int) $_POST['kursjahr'];
    $kursNr   = ($_POST['kursnummer'] ?? '') === '' ? 0 : (int) $_POST['kursnummer'];

    $db = getDB();
    $imported = 0; $updated = 0; $skipped = 0; $errors = [];

    $findByEmail = $db->prepare('SELECT id FROM jungschuetzen WHERE Email = ? LIMIT 1');
    $findByNameGeb = $db->prepare('SELECT id FROM jungschuetzen WHERE Vorname = ? AND Name = ? AND Geburtsdatum <=> ? LIMIT 1');
    $ins = $db->prepare(
        'INSERT INTO jungschuetzen (Vorname, Name, Geburtsdatum, AHVNummer, Strasse, PLZ, Ort, Email, Mobile, KursNummer, KursJahr, Aktiv)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
    );
    $upd = $db->prepare(
        'UPDATE jungschuetzen SET Geburtsdatum = ?, AHVNummer = ?, Strasse = ?, PLZ = ?, Ort = ?,
                Email = ?, Mobile = ?, KursNummer = ?, KursJahr = ?, Aktiv = 1
          WHERE id = ?'
    );

    foreach ($rows as $r) {
        $vorname = trim((string) ($r['vorname'] ?? ''));
        $name    = trim((string) ($r['name'] ?? ''));
        if ($vorname === '' || $name === '') { $skipped++; continue; }

        $geb   = ($r['geburtsdatum'] ?? '') ?: null;
        $ahv   = ($r['ahvnummer'] ?? '') ?: null;
        $email = trim((string) ($r['email'] ?? ''));
        $emailVal = ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : null;
        $strasse = trim((string) ($r['strasse'] ?? ''));
        $plz     = trim((string) ($r['plz'] ?? ''));
        $ort     = trim((string) ($r['ort'] ?? ''));
        $mobile  = trim((string) ($r['mobile'] ?? ''));

        try {
            // Bestehenden Datensatz finden (zuerst per E-Mail, dann Name+Geburtsdatum)
            $existId = null;
            if ($emailVal !== null) {
                $findByEmail->execute([$emailVal]);
                $existId = $findByEmail->fetchColumn() ?: null;
            }
            if ($existId === null) {
                $findByNameGeb->execute([$vorname, $name, $geb]);
                $existId = $findByNameGeb->fetchColumn() ?: null;
            }

            if ($existId) {
                $upd->execute([$geb, $ahv, $strasse, $plz, $ort, $emailVal, $mobile, $kursNr, $kursJahr, (int) $existId]);
                $updated++;
            } else {
                $ins->execute([$vorname, $name, $geb, $ahv, $strasse, $plz, $ort, $emailVal, $mobile, $kursNr, $kursJahr]);
                $imported++;
            }
        } catch (Throwable $e) {
            error_log('import_js row: ' . $e->getMessage());
            $errors[] = $vorname . ' ' . $name . ': Speicherfehler';
        }
    }

    echo json_encode([
        'success'  => true,
        'imported' => $imported,
        'updated'  => $updated,
        'skipped'  => $skipped,
        'errors'   => $errors,
        'message'  => "Import: $imported neu, $updated aktualisiert" . ($skipped ? ", $skipped übersprungen" : ''),
    ]);
    exit;
}

json_error('Unbekannte Aktion.');
