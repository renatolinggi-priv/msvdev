<?php
/**
 * Gemeinsame Regeln der Mitgliederverwaltung (seit 1.68.57).
 *
 * Eine Prüfung für Hinzufügen, Speichern im Panel und CSV-Import, damit überall dieselben Regeln gelten.
 * Eingebunden von add_mitglied, save_single_mitglied, import_csv, export_csv, load_mitglieder_form,
 * delete_mitglied und der Seite inc/mitgliederverwaltung.php.
 *
 * Mitgliedschaft (Migration 134): Mitgliedsart Aktiv/Passiv/Frei/Ehren, Eintritt = Vereinsaufnahme,
 * Austrittsjahr. «Status» (1 = Mitglied, 0 = ausgetreten) und «Verstorben» bleiben die Spalten, nach denen
 * die übrigen Module filtern; «Ehrenmitglied» wird aus der Mitgliedsart nachgeführt (Schützenabrechnung,
 * Adressliste). Solange Migration 134 nicht gelaufen ist, gibt es nur Aktiv/Ehren und kein Austrittsjahr.
 */

const MV_ARTEN         = ['Aktiv', 'Passiv', 'Frei', 'Ehren'];
const MV_ANREDEN       = ['Herr', 'Frau'];
const MV_KOMMUNIKATION = ['Briefpost', 'Whatsapp', 'Beides'];

// Feld → Spalte in mitglieder (Whitelist für dynamische UPDATE/INSERT)
const MV_SPALTEN = [
    'id' => 'ID', 'anrede' => 'Anrede', 'vorname' => 'Vorname', 'name' => 'Name',
    'geburtsdatum' => 'Geburtsdatum', 'waffenid' => 'WaffenID', 'status' => 'Status',
    'ehrenmitglied' => 'Ehrenmitglied', 'mitgliedsart' => 'Mitgliedsart', 'vereinsaufnahme' => 'Vereinsaufnahme',
    'austrittsjahr' => 'Austrittsjahr', 'verstorben' => 'Verstorben', 'ist_jsk_leiter' => 'ist_jsk_leiter',
    'strasse' => 'Strasse', 'plz' => 'PLZ', 'ort' => 'Ort', 'email' => 'Email', 'telefon' => 'Telefon',
    'mobile' => 'Mobile', 'kommunikation' => 'Kommunikation', 'notizen' => 'Notizen',
];

// Lesbare Feldnamen für Meldungen
const MV_FELDNAMEN = [
    'id' => 'Lizenznr.', 'anrede' => 'Anrede', 'vorname' => 'Vorname', 'name' => 'Name',
    'geburtsdatum' => 'Geburtsdatum', 'waffenid' => 'Waffe', 'status' => 'Status', 'ehrenmitglied' => 'Ehrenmitglied',
    'mitgliedsart' => 'Mitgliedsart', 'vereinsaufnahme' => 'Eintritt', 'austrittsjahr' => 'Austritt',
    'verstorben' => 'Verstorben', 'ist_jsk_leiter' => 'Jungschützenleitung', 'strasse' => 'Strasse', 'plz' => 'PLZ',
    'ort' => 'Ort', 'email' => 'E-Mail', 'telefon' => 'Telefon', 'mobile' => 'Mobile',
    'kommunikation' => 'Kommunikation', 'notizen' => 'Notizen',
];

/** Gibt es die Spalten Mitgliedsart und Austrittsjahr schon (Migration 134)? */
function mvHatMitgliedschaft(mysqli $conn): bool
{
    static $hat = null;
    if ($hat === null) {
        $r = $conn->query("SHOW COLUMNS FROM mitglieder WHERE Field IN ('Mitgliedsart', 'Austrittsjahr')");
        $hat = $r && $r->num_rows === 2;
    }
    return $hat;
}

/** Felder, die geschrieben werden dürfen (ohne Mitgliedschafts-Spalten vor Migration 134). */
function mvFelder(mysqli $conn): array
{
    $felder = array_keys(MV_SPALTEN);
    if (!mvHatMitgliedschaft($conn)) {
        $felder = array_values(array_diff($felder, ['mitgliedsart', 'austrittsjahr']));
    }
    return $felder;
}

/** Waffen als [ID => Bezeichnung]. */
function mvWaffen(mysqli $conn): array
{
    $waffen = [];
    $r = $conn->query('SELECT ID, Bezeichnung FROM Waffen ORDER BY Bezeichnung');
    while ($r && ($w = $r->fetch_assoc())) {
        $waffen[(int)$w['ID']] = (string)$w['Bezeichnung'];
    }
    return $waffen;
}

/**
 * Telefonnummer vereinheitlichen. Schweizer Nummern (079…, 0041…, +41…) werden zu «+41 79 123 45 67»,
 * ausländische mit Vorwahl (+49…, 0049…) bleiben lesbar stehen. null = keine gültige Nummer.
 */
function mvTelefon(string $wert): ?string
{
    $wert = trim(preg_replace('/\s+/u', ' ', $wert));
    if ($wert === '') return '';
    $roh = preg_replace('/[^\d+]/', '', $wert);
    if (str_starts_with($roh, '00')) {
        $roh = '+' . substr($roh, 2);
        $wert = '+' . ltrim(preg_replace('/^\s*00/', '', $wert));
    } elseif (str_starts_with($roh, '0')) {
        $roh = '+41' . substr($roh, 1);
    } elseif (!str_starts_with($roh, '+') && strlen($roh) === 9) {
        $roh = '+41' . $roh;
    }
    if (!str_starts_with($roh, '+')) return null;
    $ziffern = substr($roh, 1);
    if ($ziffern === '' || !ctype_digit($ziffern)) return null;

    if (str_starts_with($ziffern, '41')) {
        $rest = substr($ziffern, 2);
        if (strlen($rest) !== 9) return null;
        return '+41 ' . substr($rest, 0, 2) . ' ' . substr($rest, 2, 3) . ' ' . substr($rest, 5, 2) . ' ' . substr($rest, 7, 2);
    }
    if (strlen($ziffern) < 7 || strlen($ziffern) > 15) return null;
    return mb_substr($wert, 0, 50);
}

/** Datum aus JJJJ-MM-TT oder TT.MM.JJJJ; null = ungültig. */
function mvDatum(string $wert): ?string
{
    $wert = trim($wert);
    foreach (['Y-m-d', 'd.m.Y', 'j.n.Y'] as $f) {
        $d = DateTime::createFromFormat('!' . $f, $wert);
        if ($d && $d->format($f) === $wert) return $d->format('Y-m-d');
    }
    return null;
}

/** Ja/Nein aus 1/0, ja/nein, x, true/false; null = nicht lesbar. */
function mvJaNein($wert): ?int
{
    $w = mb_strtolower(trim((string)$wert));
    if (in_array($w, ['1', 'ja', 'j', 'x', 'true', 'wahr', 'yes'], true)) return 1;
    if (in_array($w, ['0', 'nein', 'n', '', 'false', 'falsch', 'no'], true)) return 0;
    return null;
}

/** Text eines Werts für Vergleich und Anzeige (null → ''). */
function mvText($v): string
{
    return $v === null ? '' : trim((string)$v);
}

/**
 * Prüft und vereinheitlicht ein Mitglied. $m enthält die Felder aus MV_SPALTEN (fehlende gelten als leer).
 * Rückgabe [fehler (feld => Text), sauber (feld => Wert für die Datenbank)].
 */
function mvPruefen(array $m, array $waffen, bool $mitMitgliedschaft): array
{
    $f = [];
    $s = [];
    $jahrMax = (int)date('Y') + 1;

    $s['id'] = (int)($m['id'] ?? 0);
    if ($s['id'] < 1) $f['id'] = 'Bitte die Lizenznummer angeben (nur Ziffern).';

    foreach (['vorname', 'name'] as $k) {
        $s[$k] = trim(preg_replace('/\s+/u', ' ', (string)($m[$k] ?? '')));
        if ($s[$k] === '') $f[$k] = MV_FELDNAMEN[$k] . ' fehlt.';
        elseif (mb_strlen($s[$k]) > 255) $f[$k] = 'Höchstens 255 Zeichen.';
    }

    $geb = mvDatum((string)($m['geburtsdatum'] ?? ''));
    if ($geb === null) {
        $f['geburtsdatum'] = trim((string)($m['geburtsdatum'] ?? '')) === '' ? 'Geburtsdatum fehlt.' : 'Kein gültiges Datum (TT.MM.JJJJ).';
    } elseif ($geb < '1900-01-01' || $geb > date('Y-m-d')) {
        $f['geburtsdatum'] = 'Das Datum liegt ausserhalb von 1900 bis heute.';
    }
    $s['geburtsdatum'] = $geb;

    $s['waffenid'] = (int)($m['waffenid'] ?? 0);
    if (!isset($waffen[$s['waffenid']])) $f['waffenid'] = 'Bitte eine Waffe aus der Liste wählen.';

    $anrede = trim((string)($m['anrede'] ?? ''));
    $s['anrede'] = $anrede === '' ? null : $anrede;
    if ($anrede !== '' && !in_array($anrede, MV_ANREDEN, true)) $f['anrede'] = 'Herr, Frau oder leer.';

    $komm = trim((string)($m['kommunikation'] ?? ''));
    if (strcasecmp($komm, 'WhatsApp') === 0) $komm = 'Whatsapp';
    $s['kommunikation'] = $komm === '' ? null : $komm;
    if ($komm !== '' && !in_array($komm, MV_KOMMUNIKATION, true)) $f['kommunikation'] = 'Briefpost, WhatsApp, Beides oder leer.';

    foreach (['strasse' => 255, 'plz' => 10, 'ort' => 100, 'notizen' => 65000] as $k => $max) {
        $s[$k] = trim((string)($m[$k] ?? ''));
        if (mb_strlen($s[$k]) > $max) $f[$k] = 'Höchstens ' . $max . ' Zeichen.';
    }

    $s['email'] = trim((string)($m['email'] ?? ''));
    if ($s['email'] !== '' && !filter_var($s['email'], FILTER_VALIDATE_EMAIL)) $f['email'] = 'Keine gültige E-Mail-Adresse.';

    foreach (['telefon', 'mobile'] as $k) {
        $t = mvTelefon((string)($m[$k] ?? ''));
        if ($t === null) {
            $f[$k] = 'Keine gültige Nummer. Schweizer Nummer wie 079 123 45 67, ausländische mit Vorwahl wie +49 171 1234567.';
            $s[$k] = trim((string)($m[$k] ?? ''));
        } else {
            $s[$k] = $t;
        }
    }

    foreach (['vereinsaufnahme', 'austrittsjahr'] as $k) {
        $roh = trim((string)($m[$k] ?? ''));
        if ($roh === '' || $roh === '0') { $s[$k] = null; continue; }
        if (!ctype_digit($roh) || (int)$roh < 1901 || (int)$roh > $jahrMax) {
            $f[$k] = 'Jahr zwischen 1901 und ' . $jahrMax . '.';
            $s[$k] = null;
        } else {
            $s[$k] = (int)$roh;
        }
    }

    foreach (['status', 'verstorben', 'ist_jsk_leiter'] as $k) {
        $s[$k] = (int)!empty($m[$k]);
    }

    $art = trim((string)($m['mitgliedsart'] ?? ''));
    foreach (MV_ARTEN as $a) {
        if (strcasecmp($art, $a) === 0) $art = $a;
    }
    if ($art === '') $art = !empty($m['ehrenmitglied']) ? 'Ehren' : 'Aktiv';
    if (!in_array($art, MV_ARTEN, true)) {
        $f['mitgliedsart'] = 'Aktiv, Passiv, Frei oder Ehren.';
        $art = 'Aktiv';
    } elseif (!$mitMitgliedschaft && !in_array($art, ['Aktiv', 'Ehren'], true)) {
        $f['mitgliedsart'] = 'Passiv und Frei gibt es erst nach der Datenbank-Aktualisierung.';
    }
    $s['mitgliedsart'] = $art;
    $s['ehrenmitglied'] = $art === 'Ehren' ? 1 : 0;

    // Wer Mitglied ist, hat kein Austrittsjahr; ausgetreten vor dem Eintritt geht nicht
    if ($s['status'] === 1 && $s['verstorben'] === 0) $s['austrittsjahr'] = null;
    if ($s['austrittsjahr'] !== null && $s['vereinsaufnahme'] !== null && $s['austrittsjahr'] < $s['vereinsaufnahme']) {
        $f['austrittsjahr'] = 'Der Austritt liegt vor dem Eintritt (' . $s['vereinsaufnahme'] . ').';
    }

    if (!$mitMitgliedschaft) unset($s['mitgliedsart'], $s['austrittsjahr']);
    return [$f, $s];
}

/** Anderes Mitglied mit gleichem Namen, Vornamen und Geburtsdatum (mögliches Duplikat) oder null. */
function mvDuplikat(mysqli $conn, string $name, string $vorname, ?string $geb, int $ausserId = 0): ?array
{
    if ($geb === null) return null;
    $st = $conn->prepare('SELECT ID, Vorname, Name FROM mitglieder WHERE Name = ? AND Vorname = ? AND Geburtsdatum = ? AND ID <> ? LIMIT 1');
    if (!$st) return null;
    $st->bind_param('sssi', $name, $vorname, $geb, $ausserId);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $st->close();
    return $r ?: null;
}

/**
 * Schreibt ein Mitglied: $neu = INSERT, sonst UPDATE der übergebenen Felder.
 * Nur Felder aus MV_SPALTEN, die es in der Datenbank gibt. Wirft bei einem Datenbankfehler.
 */
function mvSchreiben(mysqli $conn, int $id, array $werte, bool $neu): void
{
    $erlaubt = mvFelder($conn);
    $werte = array_intersect_key($werte, array_flip($erlaubt));
    unset($werte['id']);
    if (!$werte && !$neu) return;

    $spalten = [];
    $params = [];
    foreach ($werte as $k => $v) {
        $spalten[] = '`' . MV_SPALTEN[$k] . '`';
        $params[] = $v === null ? null : (string)$v;
    }
    if ($neu) {
        $sql = 'INSERT INTO mitglieder (`ID`, ' . implode(', ', $spalten) . ') VALUES (?' . str_repeat(', ?', count($spalten)) . ')';
        array_unshift($params, (string)$id);
    } else {
        $sql = 'UPDATE mitglieder SET ' . implode(' = ?, ', $spalten) . ' = ? WHERE ID = ?';
        $params[] = (string)$id;
    }
    $st = $conn->prepare($sql);
    if (!$st) throw new RuntimeException('Datenbankfehler (prepare): ' . $conn->error);
    $st->bind_param(str_repeat('s', count($params)), ...$params);
    if (!$st->execute()) {
        $fehler = $st->error;
        $st->close();
        throw new RuntimeException('Datenbankfehler: ' . $fehler);
    }
    $st->close();
}

/** Fehler als JSON (422) beenden: message für den Toast, fehler für die Felder. */
function mvFehlerAntwort(array $fehler, int $code = 422, array $extra = []): void
{
    http_response_code($code);
    $n = count($fehler);
    $erstes = $fehler ? (MV_FELDNAMEN[array_key_first($fehler)] ?? '') . ': ' . reset($fehler) : '';
    echo json_encode(array_merge([
        'success' => false,
        'message' => $n === 1 ? $erstes : 'Nicht gespeichert – ' . $n . ' Eingaben prüfen.',
        'fehler'  => (object)$fehler,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}
