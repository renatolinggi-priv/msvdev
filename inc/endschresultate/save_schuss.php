<?php
// save_schuss.php – Schüsse eines Teilnehmers speichern (Endschiessen Resultate erfassen)
//
// POST teilnehmer (m<ID> = Mitglied, g<ID> = Gast/Jungschütze; seit Migration 094) oder mitgliedID (alt), jahr,
// csrf_token und die Felder der gelösten Stiche. Nicht mitgeschickte Stiche (gesperrt) bleiben unverändert.
// Sie und Er: SieErSchuss1–5 (eigene Schüsse) und SieUnderPartner (Schlüssel der Partnerin/des Partners oder leer).
// Partnerrang (1/0, nur Mitglieder): zusätzlich in der Partner-Rangliste (Migration 096).
include '../config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json'); // Zugriff nur Admin-Bereich (admin/vorstand)
require_once __DIR__ . '/teilnehmer.inc.php';

// CSRF-Schutz
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Ungültige Anfrage']));
}

header('Content-Type: application/json; charset=utf-8');

$jahr = isset($_POST['jahr']) ? intval($_POST['jahr']) : intval(date('Y'));
$schussData = $_POST;
$t = endschTeilnehmer($_POST['teilnehmer'] ?? (isset($_POST['mitgliedID']) ? 'm' . (int)$_POST['mitgliedID'] : ''));
if (!$t) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Kein Teilnehmer gewählt. Bitte die Liste neu laden.']));
}
$spalte = $t['spalte'];   // MitgliedID | GastID – fester Wert aus endschTeilnehmer(), sicher im SQL
$personID = $t['id'];

// Werte prüfen, bevor etwas geschrieben wird (früher übernahm intval jede Eingabe, auch «77»)
require_once __DIR__ . '/../eingabe_pruefen.inc.php';
msvPruefeZahlenOderAbbruch($schussData,
    msvZahlRegeln('Schuss', 1, 10, 0, 10, 0, 'Endstich Schuss')
    + ['Tiefschuss' => [0, 100, 0, 'Endstich Tiefschuss']]
    + msvZahlRegeln('P1Schuss', 1, 6, 0, 10, 0, 'Schwini Passe 1, Schuss')
    + msvZahlRegeln('P2Schuss', 1, 6, 0, 10, 0, 'Schwini Passe 2, Schuss')
    + msvZahlRegeln('KSchuss', 1, 5, 0, 100, 0, 'Kunst Schuss')
    + msvZahlRegeln('GSchuss', 1, 3, 0, 100, 0, 'Glück Schuss')
    + msvZahlRegeln('ZSchuss', 1, 6, 0, 100, 0, 'Zabig Schuss')
    + ['Ansage' => [0, 999, 0, 'Ansage']]
    + msvZahlRegeln('SieErSchuss', 1, 5, 0, 10, 0, 'Sie und Er Schuss'));

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $conn->connect_error]));
}

// Die Person muss es geben; Gäste gelten nur für ihr Jahr
if ($spalte === 'GastID') {
    $st = $conn->prepare('SELECT id FROM endstich_gaeste WHERE id = ? AND jahr = ?');
    $st->bind_param('ii', $personID, $jahr);
} else {
    $st = $conn->prepare('SELECT ID FROM mitglieder WHERE ID = ? AND ? > 0');
    $st->bind_param('ii', $personID, $jahr);
}
$st->execute();
$gibtEs = (bool)$st->get_result()->fetch_row();
$st->close();
if (!$gibtEs) {
    http_response_code(404);
    die(json_encode(['success' => false, 'message' => 'Diesen Teilnehmer gibt es im gewählten Jahr nicht. Bitte die Liste neu laden.']));
}

function saveSchuss($conn, string $spalte, int $personID, $jahr, $schussData, $table, $fields) {
    // Überprüfen, ob Daten für diese Person und Jahr bereits existieren
    // $table, $spalte und $fields stammen aus hardcodierten Werten – sicher für direkte Verwendung
    $checkStmt = $conn->prepare("SELECT ID FROM $table WHERE $spalte = ? AND Jahr = ?");
    $checkStmt->bind_param("ii", $personID, $jahr);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    $fieldValues = array_map(function($field) use ($schussData) {
        return intval($schussData[$field] ?? 0);
    }, $fields);

    if ($checkResult->num_rows > 0) {
        // Update
        $row = $checkResult->fetch_assoc();
        $id = $row['ID'];
        $checkStmt->close();

        $setParts = implode(", ", array_map(function($field) { return "$field = ?"; }, $fields));
        $sql = "UPDATE $table SET $setParts WHERE ID = ? AND Jahr = ?";
        $stmt = $conn->prepare($sql);
        $types = str_repeat('i', count($fields)) . 'ii';
        $params = array_merge($fieldValues, [$id, $jahr]);
        $stmt->bind_param($types, ...$params);
    } else {
        // Insert
        $checkStmt->close();

        $columns = implode(", ", $fields);
        $placeholders = implode(", ", array_fill(0, count($fields), '?'));
        $sql = "INSERT INTO $table ($spalte, Jahr, $columns) VALUES (?, ?, $placeholders)";
        $stmt = $conn->prepare($sql);
        $types = 'ii' . str_repeat('i', count($fields));
        $params = array_merge([$personID, $jahr], $fieldValues);
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute() === FALSE) {
        throw new Exception("Fehler beim Speichern: " . $stmt->error);
    }
    $stmt->close();
}

/**
 * Sie und Er (Migration 094): eigene fünf Schüsse in endstich_sieunder und den Verweis auf die Partnerin bzw.
 * den Partner. Der Verweis gilt in beide Richtungen: die gewählte Person zeigt danach auf diese Person zurück,
 * frühere Verknüpfungen beider Seiten werden gelöst. Rückgabe true, wenn vorhandene Schüsse geleert wurden.
 */
function saveSieUnder(mysqli $conn, array $t, int $jahr, array $daten): bool
{
    $spalte = $t['spalte'];
    $id = $t['id'];
    $schuesse = [];
    for ($i = 1; $i <= 5; $i++) {
        $v = trim((string)($daten['SieErSchuss' . $i] ?? ''));
        $schuesse[] = $v === '' ? null : (int)$v;
    }
    $hatSchuesse = (bool)array_filter($schuesse, fn($s) => $s !== null && $s > 0);

    // Partner: leer = keiner; sonst muss es die Person im Jahr geben und es darf nicht man selbst sein
    $partner = null;
    $partnerRoh = trim((string)($daten['SieUnderPartner'] ?? ''));
    if ($partnerRoh !== '') {
        $partner = endschTeilnehmer($partnerRoh);
        if (!$partner || $partner['schluessel'] === $t['schluessel']) {
            throw new InvalidArgumentException('Die gewählte Partnerin bzw. der gewählte Partner ist ungültig. Bitte neu wählen.');
        }
        $st = $partner['spalte'] === 'GastID'
            ? $conn->prepare('SELECT id FROM endstich_gaeste WHERE id = ? AND jahr = ?')
            : $conn->prepare('SELECT ID FROM mitglieder WHERE ID = ? AND ? > 0');
        $st->bind_param('ii', $partner['id'], $jahr);
        $st->execute();
        $ok = (bool)$st->get_result()->fetch_row();
        $st->close();
        if (!$ok) {
            throw new InvalidArgumentException('Die gewählte Partnerin bzw. der gewählte Partner ist im gewählten Jahr nicht erfasst. Bitte die Seite neu laden.');
        }
    }

    // Bisherige Zeile
    $st = $conn->prepare("SELECT ID, Schuss1, Schuss2, Schuss3, Schuss4, Schuss5, PartnerMitgliedID, PartnerGastID
                          FROM endstich_sieunder WHERE $spalte = ? AND Jahr = ?");
    $st->bind_param('ii', $id, $jahr);
    $st->execute();
    $alt = $st->get_result()->fetch_assoc();
    $st->close();
    $altPartner = ($alt && ($alt['PartnerMitgliedID'] || $alt['PartnerGastID']))
        ? endschTeilnehmer(endschSchluessel($alt['PartnerMitgliedID'], $alt['PartnerGastID'])) : null;
    $altHatteSchuesse = $alt && array_filter([$alt['Schuss1'], $alt['Schuss2'], $alt['Schuss3'], $alt['Schuss4'], $alt['Schuss5']],
                                             fn($s) => $s !== null && (int)$s > 0);

    // Verweis einer Person auf jemanden lösen (nur wenn er auf $ziel zeigt)
    $loese = function (array $person, array $ziel) use ($conn, $jahr) {
        $zielSpalte = $ziel['spalte'] === 'GastID' ? 'PartnerGastID' : 'PartnerMitgliedID';
        $st = $conn->prepare("UPDATE endstich_sieunder SET PartnerMitgliedID = NULL, PartnerGastID = NULL
                              WHERE {$person['spalte']} = ? AND Jahr = ? AND $zielSpalte = ?");
        $st->bind_param('iii', $person['id'], $jahr, $ziel['id']);
        $st->execute();
        $st->close();
    };
    // Verweis setzen (Zeile anlegen, falls die Person noch keine hat)
    $setze = function (array $person, ?array $ziel) use ($conn, $jahr) {
        $pm = ($ziel && $ziel['spalte'] === 'MitgliedID') ? $ziel['id'] : null;
        $pg = ($ziel && $ziel['spalte'] === 'GastID') ? $ziel['id'] : null;
        $st = $conn->prepare("INSERT INTO endstich_sieunder ({$person['spalte']}, Jahr, PartnerMitgliedID, PartnerGastID) VALUES (?, ?, ?, ?)
                              ON DUPLICATE KEY UPDATE PartnerMitgliedID = VALUES(PartnerMitgliedID), PartnerGastID = VALUES(PartnerGastID)");
        $st->bind_param('iiii', $person['id'], $jahr, $pm, $pg);
        $st->execute();
        $st->close();
    };

    $partnerGeaendert = ($altPartner['schluessel'] ?? null) !== ($partner['schluessel'] ?? null);
    if ($partnerGeaendert) {
        // Die bisherige Partnerin zeigt nicht mehr hierher
        if ($altPartner) $loese($altPartner, $t);
        if ($partner) {
            // Wer bisher mit der neuen Partnerin verknüpft war, verliert diese Verknüpfung
            $st = $conn->prepare("SELECT PartnerMitgliedID, PartnerGastID FROM endstich_sieunder WHERE {$partner['spalte']} = ? AND Jahr = ?");
            $st->bind_param('ii', $partner['id'], $jahr);
            $st->execute();
            $pz = $st->get_result()->fetch_assoc();
            $st->close();
            if ($pz && ($pz['PartnerMitgliedID'] || $pz['PartnerGastID'])) {
                $dritte = endschTeilnehmer(endschSchluessel($pz['PartnerMitgliedID'], $pz['PartnerGastID']));
                if ($dritte && $dritte['schluessel'] !== $t['schluessel']) $loese($dritte, $partner);
            }
            $setze($partner, $t);
        }
    }

    if (!$hatSchuesse && !$partner) {
        // Nichts mehr da: Zeile entfernen
        if ($alt) {
            $st = $conn->prepare('DELETE FROM endstich_sieunder WHERE ID = ?');
            $st->bind_param('i', $alt['ID']);
            $st->execute();
            $st->close();
        }
        return (bool)$altHatteSchuesse;
    }

    $pm = ($partner && $partner['spalte'] === 'MitgliedID') ? $partner['id'] : null;
    $pg = ($partner && $partner['spalte'] === 'GastID') ? $partner['id'] : null;
    $st = $conn->prepare("INSERT INTO endstich_sieunder ($spalte, Jahr, Schuss1, Schuss2, Schuss3, Schuss4, Schuss5, PartnerMitgliedID, PartnerGastID)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE Schuss1 = VALUES(Schuss1), Schuss2 = VALUES(Schuss2), Schuss3 = VALUES(Schuss3),
                                                  Schuss4 = VALUES(Schuss4), Schuss5 = VALUES(Schuss5),
                                                  PartnerMitgliedID = VALUES(PartnerMitgliedID), PartnerGastID = VALUES(PartnerGastID)");
    $st->bind_param('iiiiiiiii', $id, $jahr, $schuesse[0], $schuesse[1], $schuesse[2], $schuesse[3], $schuesse[4], $pm, $pg);
    if (!$st->execute()) {
        throw new Exception('Fehler bei Sie und Er: ' . $st->error);
    }
    $st->close();
    return $altHatteSchuesse && !$hatSchuesse;
}

/**
 * Prüft ob mindestens ein Feld im Datensatz einen Wert > 0 hat
 */
function hasNonZeroValues($schussData, $fields) {
    foreach ($fields as $field) {
        if (isset($schussData[$field]) && intval($schussData[$field]) > 0) {
            return true;
        }
    }
    return false;
}

/**
 * Leert einen Stich, wenn alle seine Felder mitgeschickt wurden und alle leer oder 0 sind.
 * $optional: Felder, die mitgeleert werden, falls sie mitgeschickt wurden (z.B. Absenden-Anmeldung).
 * Rückgabe true, wenn dabei tatsächlich Werte verschwunden sind.
 */
function leereWennGeleert($conn, string $table, string $spalte, int $personID, int $jahr, array $daten, array $felder, array $optional = []): bool {
    foreach ($felder as $f) {
        if (!array_key_exists($f, $daten)) return false;
    }
    $alle = array_merge($felder, array_values(array_filter($optional, fn($f) => array_key_exists($f, $daten))));
    foreach ($alle as $f) {
        $v = trim((string)$daten[$f]);
        if ($v !== '' && (float)str_replace(',', '.', $v) != 0) return false;
    }
    $set = implode(', ', array_map(fn($f) => "`$f` = 0", $alle));
    $st = $conn->prepare("UPDATE `$table` SET $set WHERE $spalte = ? AND Jahr = ?");
    if (!$st) throw new Exception('Leeren vorbereiten: ' . $conn->error);
    $st->bind_param('ii', $personID, $jahr);
    $st->execute();
    $n = $st->affected_rows;
    $st->close();
    return $n > 0;
}

// Felder für die Tabellen
$endstichFields = ['Schuss1', 'Schuss2', 'Schuss3', 'Schuss4', 'Schuss5', 'Schuss6', 'Schuss7', 'Schuss8', 'Schuss9', 'Schuss10', 'Tiefschuss', 'AbsendenAnmeldung'];
$schwiniFields = ['P1Schuss1', 'P1Schuss2', 'P1Schuss3', 'P1Schuss4', 'P1Schuss5', 'P1Schuss6', 'P2Schuss1', 'P2Schuss2', 'P2Schuss3', 'P2Schuss4', 'P2Schuss5', 'P2Schuss6'];
$kunstFields = ['KSchuss1', 'KSchuss2', 'KSchuss3', 'KSchuss4', 'KSchuss5'];
$glueckFields = ['GSchuss1', 'GSchuss2', 'GSchuss3'];
$zabigFields = ['ZSchuss1', 'ZSchuss2', 'ZSchuss3', 'ZSchuss4', 'ZSchuss5', 'ZSchuss6', 'Ansage'];
$sieunderFields = ['SieErSchuss1', 'SieErSchuss2', 'SieErSchuss3', 'SieErSchuss4', 'SieErSchuss5'];

// Daten speichern – nur wenn mindestens ein Wert > 0 vorhanden ist (verhindert Phantom-Datensätze)
$geleert = [];   // Stiche, die mit diesem Speichern geleert wurden (für die Meldung)
$conn->begin_transaction();
try {
    $endstichData = array_intersect_key($schussData, array_flip($endstichFields));
    if (hasNonZeroValues($schussData, $endstichFields) || !empty($schussData['AbsendenAnmeldung'] ?? '')) {
        saveSchuss($conn, $spalte, $personID, $jahr, $endstichData, 'endstich', $endstichFields);
    } elseif (leereWennGeleert($conn, 'endstich', $spalte, $personID, $jahr, $schussData, array_slice($endstichFields, 0, 11), ['AbsendenAnmeldung'])) {
        $geleert[] = 'Endstich';
    }

    $schwiniData = array_intersect_key($schussData, array_flip($schwiniFields));
    if (hasNonZeroValues($schussData, $schwiniFields)) {
        saveSchuss($conn, $spalte, $personID, $jahr, $schwiniData, 'schwini', $schwiniFields);
    } else {
        // Passen werden einzeln gelöst und darum einzeln geleert
        if (leereWennGeleert($conn, 'schwini', $spalte, $personID, $jahr, $schussData, array_slice($schwiniFields, 0, 6))) $geleert[] = 'Schwini Passe 1';
        if (leereWennGeleert($conn, 'schwini', $spalte, $personID, $jahr, $schussData, array_slice($schwiniFields, 6, 6))) $geleert[] = 'Schwini Passe 2';
    }

    $kunstData = array_intersect_key($schussData, array_flip($kunstFields));
    if (hasNonZeroValues($schussData, $kunstFields)) {
        saveSchuss($conn, $spalte, $personID, $jahr, $kunstData, 'kunst', $kunstFields);
    } elseif (leereWennGeleert($conn, 'kunst', $spalte, $personID, $jahr, $schussData, $kunstFields)) {
        $geleert[] = 'Kunst';
    }

    $glueckData = array_intersect_key($schussData, array_flip($glueckFields));
    if (hasNonZeroValues($schussData, $glueckFields)) {
        saveSchuss($conn, $spalte, $personID, $jahr, $glueckData, 'glueck', $glueckFields);
    } elseif (leereWennGeleert($conn, 'glueck', $spalte, $personID, $jahr, $schussData, $glueckFields)) {
        $geleert[] = 'Glück';
    }

    $zabigData = array_intersect_key($schussData, array_flip($zabigFields));
    if (hasNonZeroValues($schussData, $zabigFields) || !empty($schussData['Ansage'] ?? '')) {
        saveSchuss($conn, $spalte, $personID, $jahr, $zabigData, 'zabig', $zabigFields);
    } elseif (leereWennGeleert($conn, 'zabig', $spalte, $personID, $jahr, $schussData, array_slice($zabigFields, 0, 6))) {
        $geleert[] = 'Zabig';
    }

    // Partner-Rangliste: Mitglied zusätzlich aufführen (Migration 096), nur Mitglieder, nur wenn mitgeschickt
    if ($spalte === 'MitgliedID' && array_key_exists('Partnerrang', $schussData)) {
        $st = (string)$schussData['Partnerrang'] === '1'
            ? $conn->prepare('INSERT IGNORE INTO endstich_partnerrang (MitgliedID, Jahr) VALUES (?, ?)')
            : $conn->prepare('DELETE FROM endstich_partnerrang WHERE MitgliedID = ? AND Jahr = ?');
        $st->bind_param('ii', $personID, $jahr);
        $st->execute();
        $st->close();
    }

    // Sie und Er: nur wenn der Abschnitt offen war (Felder mitgeschickt)
    if (array_key_exists('SieErSchuss1', $schussData) || array_key_exists('SieUnderPartner', $schussData)) {
        if (saveSieUnder($conn, $t, $jahr, $schussData)) {
            $geleert[] = 'Sie und Er';
        }
    }

    $conn->commit();
    $conn->close();
    echo json_encode(['success' => true, 'message' => 'Schüsse erfolgreich gespeichert', 'geleert' => $geleert], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    $conn->rollback();
    $conn->close();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $conn->rollback();
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => msvFehler('Speichern hat nicht geklappt. Bitte nochmals versuchen.', $e)]);
}
