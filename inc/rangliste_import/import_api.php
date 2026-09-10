<?php
// inc/rangliste_import/import_api.php
// Backend fuer den PDF-Ranglisten-Import (Resultaterfassung).
//
// Actions:
//   parse   – Hochgeladenes PDF parsen, Vereinsmitglieder per Name/Lizenz matchen,
//             Duplikate markieren, Vorschau-Zeilen + Statistik zurueckgeben.
//   import  – Bestaetigte/ausgewaehlte Zeilen speichern:
//               * immer  -> jmresultate (Punkte, status='freigegeben')
//               * Top 10 -> zusaetzlich einzelrangierungen (Rang + Resultat + Preis)
//   members – Mitgliederliste fuer manuelle Zuweisung im Vorschau-Dialog.
//
// Konvention wie die Geschwister-Endpunkte (jmresultate/save_anlass.php,
// einzelrangierung/save_ranking.php): config.php (mysqli $conn) + CSRF gegen
// $_SESSION['csrf_token']. Zugriff wird durch die Admin-Shell gewaehrleistet.

include '../config.php';
require_once __DIR__ . '/../changelog_helper.php';
require_once __DIR__ . '/pdf_rangliste_parser.php';
require_once __DIR__ . '/fsa_teilnehmerliste_parser.php';
require_once __DIR__ . '/../einsatzplan_parser/name_matcher.php'; // matchSingleName(), findDisplayName()

header('Content-Type: application/json; charset=utf-8');

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}
// UTF-8 fuer korrektes Namens-Matching (Umlaute) sicherstellen
$conn->set_charset('utf8mb4');

// CSRF
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_token']) || empty($csrf) || !hash_equals($_SESSION['csrf_token'], (string) $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ungültiger CSRF Token. Bitte Seite neu laden.', 'csrf_expired' => true]);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'parse':
        handleParse($conn);
        break;
    case 'import':
        handleImport($conn);
        break;
    case 'members':
        handleMembers($conn);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Ungültige Aktion']);
}

$conn->close();

// ───────────────────────────────────────────────────────────────
// parse
// ───────────────────────────────────────────────────────────────
function handleParse($conn) {
    $jmdefRaw = $_POST['jmdefinitionID'] ?? '';
    $isOpfs   = ($jmdefRaw === 'opfs'); // Spezialauswahl: Obligatorisch + Feldschiessen (FSA)
    $jmdefId  = intval($jmdefRaw);
    $year     = intval($_POST['year'] ?? date('Y'));
    $debug    = !empty($_POST['debug']);

    if (!$isOpfs && $jmdefId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Bitte zuerst einen Anlass auswählen']);
        return;
    }

    // Upload pruefen
    if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        $errMap = [
            UPLOAD_ERR_INI_SIZE  => 'Datei zu gross (Server-Limit)',
            UPLOAD_ERR_FORM_SIZE => 'Datei zu gross',
            UPLOAD_ERR_PARTIAL   => 'Upload unvollständig',
            UPLOAD_ERR_NO_FILE   => 'Keine Datei ausgewählt',
        ];
        $code = $_FILES['pdf']['error'] ?? UPLOAD_ERR_NO_FILE;
        echo json_encode(['success' => false, 'message' => $errMap[$code] ?? 'Upload-Fehler']);
        return;
    }

    $file = $_FILES['pdf'];

    if ($file['size'] > 20 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Datei zu gross (max. 20 MB)']);
        return;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        echo json_encode(['success' => false, 'message' => 'Ungültiger Upload']);
        return;
    }
    // MIME-Pruefung ueber finfo (nicht ueber Client-Endung)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if ($mime !== 'application/pdf') {
        echo json_encode(['success' => false, 'message' => 'Nur PDF-Dateien werden unterstützt']);
        return;
    }

    // FSA-Teilnehmerliste (Obligatorisch + Feldschiessen)? Auto-Erkennung
    // unabhaengig vom gewaehlten Anlass, damit ein FSA-PDF nie faelschlich
    // als Einzelrangliste importiert wird.
    $fsa = parseFsaTeilnehmerliste($file['tmp_name'], $debug);
    if ($fsa !== null) {
        handleParseOpfs($conn, $fsa, $year, $debug);
        return;
    }
    if ($isOpfs) {
        echo json_encode(['success' => false, 'message' => 'Das PDF wurde nicht als FSA-Teilnehmerliste (bundesuebung.ch) erkannt. Für Einzelranglisten bitte den jeweiligen Anlass wählen.']);
        return;
    }

    // PDF parsen (direkt aus dem temporaeren Upload-Pfad)
    $parsed = parseRanglistePdf($file['tmp_name'], $debug);
    if (!$parsed['success']) {
        $resp = ['success' => false, 'message' => $parsed['message']];
        if ($debug && isset($parsed['debug'])) $resp['debug'] = $parsed['debug'];
        echo json_encode($resp);
        return;
    }

    // Mitglieder + Lookup-Maps laden
    list($mitglieder, $exactMap, $reversedMap, $idSet) = loadMitgliederMaps($conn);

    // Bestehende Eintraege fuer Duplikat-Markierung
    $existJm = existingJmMemberIds($conn, $jmdefId);
    $existEinzel = existingEinzelMemberIds($conn, $jmdefId, $year);

    // Zeilen matchen – nur Vereinsmitglieder behalten, pro Mitglied entdoppeln
    $byMember = []; // mitglied_id => row
    $totalLines = count($parsed['rows']);

    foreach ($parsed['rows'] as $r) {
        $mid = null; $status = 'none'; $matched = '';

        // 1) Lizenz-Match (Lizenznummer == mitglieder.ID)
        if (!empty($r['lizenz']) && isset($idSet[(string) $r['lizenz']])) {
            $mid = (int) $r['lizenz'];
            $status = 'license';
            $matched = $idSet[(string) $r['lizenz']];
        }
        // 2) Namens-Match
        if ($mid === null && $r['raw_name'] !== '') {
            $m = matchSingleName($r['raw_name'], $mitglieder, $exactMap, $reversedMap);
            if ($m['mitglied_id'] !== null) {
                $mid = (int) $m['mitglied_id'];
                $status = $m['match_status'];
                $matched = $m['matched_name'];
            }
        }
        if ($mid === null) {
            continue; // kein Vereinsmitglied -> ignorieren
        }

        $rang = $r['rang'];
        $row = [
            'rang'         => $rang,
            'raw_name'     => $r['raw_name'],
            'lizenz'       => $r['lizenz'],
            'resultat'     => $r['resultat'],
            'preis'        => $r['preis'],
            'mitglied_id'  => $mid,
            'matched_name' => $matched,
            'match_status' => $status,
            'is_top10'     => ($rang !== null && $rang >= 1 && $rang <= 10),
            'dup_jm'       => isset($existJm[$mid]),
            'dup_jm_punkte' => $existJm[$mid] ?? null,
            'dup_einzel'   => isset($existEinzel[$mid]),
        ];

        // Entdoppeln: pro Mitglied die "beste" Zeile behalten
        // (Zeile mit Resultat bevorzugen, dann tiefster Rang)
        if (!isset($byMember[$mid])) {
            $byMember[$mid] = $row;
        } else {
            $cur = $byMember[$mid];
            $better = false;
            if ($cur['resultat'] === null && $row['resultat'] !== null) {
                $better = true;
            } elseif ($cur['resultat'] !== null && $row['resultat'] !== null) {
                $curRang = $cur['rang'] ?? 9999;
                $newRang = $row['rang'] ?? 9999;
                if ($newRang < $curRang) $better = true;
            }
            if ($better) $byMember[$mid] = $row;
        }
    }

    $rows = array_values($byMember);

    // Sortierung: Top-10 zuerst (nach Rang), dann Rest
    usort($rows, function ($a, $b) {
        $ra = ($a['rang'] === null) ? 100000 : $a['rang'];
        $rb = ($b['rang'] === null) ? 100000 : $b['rang'];
        return $ra <=> $rb;
    });

    $stats = [
        'total_lines' => $totalLines,
        'matched'     => count($rows),
        'top10'       => count(array_filter($rows, fn($x) => $x['is_top10'])),
        'duplicates'  => count(array_filter($rows, fn($x) => $x['dup_jm'] || $x['dup_einzel'])),
        'fuzzy'       => count(array_filter($rows, fn($x) => $x['match_status'] === 'fuzzy')),
    ];

    // Sektionsrangierung (eigener Verein), falls im PDF vorhanden + Duplikat-Pruefung
    $sektion = $parsed['sektion'] ?? null;
    if ($sektion) {
        $stmt = $conn->prepare("SELECT id FROM sektionsrangierungen WHERE jmdefinition_id = ? AND year = ? LIMIT 1");
        $stmt->bind_param('ii', $jmdefId, $year);
        $stmt->execute();
        $sektion['dup'] = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    }

    $resp = [
        'success'   => true,
        'rows'      => $rows,
        'sektion'   => $sektion,
        'generator' => $parsed['generator'] ?? null,
        'stats'     => $stats,
        'message'   => $stats['matched'] . ' Vereinsmitglieder von ' . $totalLines . ' Zeilen erkannt',
    ];
    if ($debug && isset($parsed['debug'])) $resp['debug'] = $parsed['debug'];
    echo json_encode($resp);
}

// ───────────────────────────────────────────────────────────────
// import
// ───────────────────────────────────────────────────────────────
function handleImport($conn) {
    // OP/FS-Bonus-Import (FSA-Teilnehmerliste) hat einen eigenen Ablauf
    if (($_POST['mode'] ?? '') === 'opfs') {
        handleImportOpfs($conn);
        return;
    }

    $jmdefId = intval($_POST['jmdefinitionID'] ?? 0);
    $year    = intval($_POST['year'] ?? date('Y'));
    $rows    = json_decode($_POST['rows'] ?? '[]', true);
    if (!is_array($rows)) $rows = [];
    $sektion = json_decode($_POST['sektion'] ?? 'null', true);

    if ($jmdefId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Kein Anlass angegeben']);
        return;
    }
    $hasSektion = is_array($sektion) && isset($sektion['rang']) && $sektion['rang'] !== '';
    if (empty($rows) && !$hasSektion) {
        echo json_encode(['success' => false, 'message' => 'Keine Zeilen zum Importieren ausgewählt']);
        return;
    }

    $vorstandUserId = $_SESSION['user_id'] ?? null;
    $countJm = 0;
    $countEinzel = 0;
    $countSektion = 0;

    try {
        $conn->begin_transaction();

        foreach ($rows as $r) {
            $mid = intval($r['mitglied_id'] ?? 0);
            if ($mid <= 0) continue;

            $resRaw = trim((string) ($r['resultat'] ?? ''));

            // 1) jmresultate – immer (sofern numerisches Resultat vorhanden)
            if ($resRaw !== '' && is_numeric($resRaw)) {
                upsertJmResultat($conn, $mid, $jmdefId, (int) $resRaw, $vorstandUserId);
                $countJm++;
            }

            // 2) einzelrangierungen – nur Top 10 (Rang 1–10)
            $rang = isset($r['rang']) && $r['rang'] !== '' ? intval($r['rang']) : 0;
            if ($rang >= 1 && $rang <= 10) {
                $preis = isset($r['preis']) && $r['preis'] !== '' ? floatval($r['preis']) : 0.0;
                upsertEinzelrangierung($conn, $year, $jmdefId, $mid, $rang, $resRaw, $preis);
                $countEinzel++;
            }
        }

        // 3) sektionsrangierungen – Platzierung des eigenen Vereins (falls vorhanden + gewaehlt)
        if ($hasSektion) {
            $sRang = intval($sektion['rang']);
            $sPreis = isset($sektion['preis']) && $sektion['preis'] !== '' ? floatval($sektion['preis']) : 0.0;
            if ($sRang >= 1 && $sRang <= 999) {
                upsertSektionsrangierung($conn, $year, $jmdefId, $sRang, $sPreis);
                $countSektion = 1;
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('rangliste_import import error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fehler beim Import: ' . $e->getMessage()]);
        return;
    }

    if (function_exists('logChangelog')) {
        logChangelog('resultate', 'aktualisiert', 'JM-Resultate via PDF-Import erfasst',
            ['tabelle' => 'jmresultate', 'jahr' => $year, 'sichtbar' => 0]);
    }

    $msg = "Import abgeschlossen: {$countJm} JM-Resultate, {$countEinzel} Einzelrangierungen";
    if ($countSektion > 0) $msg .= ', 1 Sektionsrangierung';

    echo json_encode([
        'success'       => true,
        'message'       => $msg,
        'count_jm'      => $countJm,
        'count_einzel'  => $countEinzel,
        'count_sektion' => $countSektion,
    ]);
}

// ───────────────────────────────────────────────────────────────
// members (manuelle Zuweisung)
// ───────────────────────────────────────────────────────────────
function handleMembers($conn) {
    $res = $conn->query("SELECT ID, Name, Vorname FROM mitglieder WHERE Verstorben = 0 ORDER BY Name, Vorname");
    $members = [];
    while ($row = $res->fetch_assoc()) {
        $members[] = ['id' => (int) $row['ID'], 'name' => $row['Name'] . ' ' . $row['Vorname']];
    }
    echo json_encode(['success' => true, 'members' => $members]);
}

// ───────────────────────────────────────────────────────────────
// Helper
// ───────────────────────────────────────────────────────────────

/** Laedt alle nicht-verstorbenen Mitglieder + Lookup-Maps (analog matchMitglieder). */
function loadMitgliederMaps($conn) {
    $mitglieder = [];
    $exactMap = [];
    $reversedMap = [];
    $idSet = [];

    $res = $conn->query("SELECT ID, Name, Vorname FROM mitglieder WHERE Verstorben = 0");
    while ($row = $res->fetch_assoc()) {
        $mitglieder[] = $row;
        $name = trim($row['Name']);
        $vorname = trim($row['Vorname']);
        $exactMap[mb_strtolower($name . ' ' . $vorname, 'UTF-8')] = $row['ID'];
        $reversedMap[mb_strtolower($vorname . ' ' . $name, 'UTF-8')] = $row['ID'];
        $idSet[(string) $row['ID']] = $name . ' ' . $vorname;
    }

    return [$mitglieder, $exactMap, $reversedMap, $idSet];
}

/**
 * Mitglieder-IDs mit bestehendem jmresultate-Eintrag fuer diesen Anlass (Info='').
 * Wert = bestehende Punkte (fuer den Vergleich in der Import-Vorschau).
 */
function existingJmMemberIds($conn, $jmdefId) {
    $set = [];
    $stmt = $conn->prepare("SELECT mitgliederID, Punkte FROM jmresultate WHERE jmdefinitionID = ? AND (Info = '' OR Info IS NULL)");
    $stmt->bind_param('i', $jmdefId);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $set[(int) $row['mitgliederID']] = (int) $row['Punkte'];
    $stmt->close();
    return $set;
}

/** Mitglieder-IDs mit bestehender einzelrangierung fuer diesen Anlass/Jahr. */
function existingEinzelMemberIds($conn, $jmdefId, $year) {
    $set = [];
    $stmt = $conn->prepare("SELECT mitglied_id FROM einzelrangierungen WHERE jmdefinition_id = ? AND year = ?");
    $stmt->bind_param('ii', $jmdefId, $year);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $set[(int) $row['mitglied_id']] = true;
    $stmt->close();
    return $set;
}

/**
 * Upsert in jmresultate (Info='') – Punkte setzen, durch Vorstand freigeben.
 * Repliziert die Logik aus jmresultate/save_anlass.php::saveOrDeleteJMResultat().
 */
function upsertJmResultat($conn, $mitgliedID, $definitionID, $punkte, $vorstandUserId) {
    $uidSql = ($vorstandUserId !== null) ? intval($vorstandUserId) : 'NULL';
    $whereInfo = "(Info = '' OR Info IS NULL)";

    $stmt = $conn->prepare("UPDATE jmresultate
                               SET Punkte = ?, status = 'freigegeben',
                                   freigegeben_von = " . $uidSql . ", freigegeben_am = NOW()
                             WHERE mitgliederID = ? AND jmdefinitionID = ? AND $whereInfo");
    $stmt->bind_param('iii', $punkte, $mitgliedID, $definitionID);
    $stmt->execute();
    $stmt->close();

    // Existiert eine Zeile? Sonst INSERT.
    $stmt = $conn->prepare("SELECT 1 FROM jmresultate WHERE mitgliederID = ? AND jmdefinitionID = ? AND $whereInfo LIMIT 1");
    $stmt->bind_param('ii', $mitgliedID, $definitionID);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$exists) {
        $stmt = $conn->prepare("INSERT INTO jmresultate (mitgliederID, jmdefinitionID, Punkte, Info, status, freigegeben_von, freigegeben_am)
                                VALUES (?, ?, ?, '', 'freigegeben', " . $uidSql . ", NOW())");
        $stmt->bind_param('iii', $mitgliedID, $definitionID, $punkte);
        $stmt->execute();
        $stmt->close();
    }
}

/** Upsert in sektionsrangierungen (eine Platzierung des eigenen Vereins pro Anlass/Jahr). */
function upsertSektionsrangierung($conn, $year, $jmdefId, $rang, $preis) {
    $stmt = $conn->prepare("SELECT id FROM sektionsrangierungen WHERE jmdefinition_id = ? AND year = ? LIMIT 1");
    $stmt->bind_param('ii', $jmdefId, $year);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $id = (int) $existing['id'];
        $stmt = $conn->prepare("UPDATE sektionsrangierungen SET rang = ?, preis = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('idi', $rang, $preis, $id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO sektionsrangierungen (year, jmdefinition_id, rang, preis, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param('iiid', $year, $jmdefId, $rang, $preis);
        $stmt->execute();
        $stmt->close();
    }
}

// ───────────────────────────────────────────────────────────────
// Obligatorisch + Feldschiessen (FSA-Teilnehmerliste, Bonus-Import)
// ───────────────────────────────────────────────────────────────

/** Laedt die JMDefinitionen 'Obligatorisch' und 'Feldschiessen' des Jahres. */
function opfsLoadDefs($conn, $year) {
    $out = ['op' => null, 'fs' => null];
    $stmt = $conn->prepare("SELECT ID, Bezeichnung, Maxpunkte FROM JMDefinition
                             WHERE year = ? AND hidden = 0 AND Info = 0 AND Erweitert = 0
                               AND Bezeichnung IN ('Obligatorisch','Feldschiessen')");
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $entry = ['id' => (int) $row['ID'], 'bezeichnung' => $row['Bezeichnung'], 'max' => (int) $row['Maxpunkte']];
        if ($row['Bezeichnung'] === 'Obligatorisch') {
            $out['op'] = $entry;
        } else {
            $out['fs'] = $entry;
        }
    }
    $stmt->close();
    return $out;
}

/**
 * Vorschau fuer den OP/FS-Bonus-Import aus einer FSA-Teilnehmerliste.
 * ALLE Vereins-Abschnitte des PDFs werden beruecksichtigt (ein eigener Schuetze
 * kann bei einem anderen Verein / mit anderer Waffe geschossen haben); pro
 * Mitglied wird zusammengefuehrt: OP absolviert und/oder FS absolviert.
 */
function handleParseOpfs($conn, $parsed, $year, $debug) {
    if (empty($parsed['success'])) {
        echo json_encode(['success' => false, 'mode' => 'opfs', 'message' => $parsed['message'] ?? 'FSA-Teilnehmerliste konnte nicht gelesen werden']);
        return;
    }

    $defs = opfsLoadDefs($conn, $year);
    if ($defs['op'] === null || $defs['fs'] === null) {
        echo json_encode(['success' => false, 'mode' => 'opfs',
            'message' => "Für {$year} sind die Anlässe «Obligatorisch» und/oder «Feldschiessen» nicht definiert. Bitte zuerst in der Anlassdefinition anlegen."]);
        return;
    }

    list($mitglieder, $exactMap, $reversedMap) = loadMitgliederMaps($conn);
    $existOp = existingJmMemberIds($conn, $defs['op']['id']);
    $existFs = existingJmMemberIds($conn, $defs['fs']['id']);

    $byMember = [];
    $totalLines = count($parsed['rows']);

    foreach ($parsed['rows'] as $r) {
        $m = matchSingleName($r['name'], $mitglieder, $exactMap, $reversedMap);
        if ($m['mitglied_id'] === null) {
            continue; // kein Vereinsmitglied
        }
        $mid = (int) $m['mitglied_id'];

        // Bestes OP-Resultat inkl. Nachschiessen (W1/W2); "absolviert" = Wert vorhanden
        $opRes = null;
        foreach (['op', 'op_w1', 'op_w2'] as $k) {
            if ($r[$k] !== null && ($opRes === null || $r[$k] > $opRes)) {
                $opRes = $r[$k];
            }
        }
        $fsRes = $r['fs'];
        $quelle = trim($r['verein'] . ($r['waffe'] !== '' ? ' (' . $r['waffe'] . ')' : ''));

        if (!isset($byMember[$mid])) {
            $byMember[$mid] = [
                'mitglied_id'  => $mid,
                'matched_name' => $m['matched_name'],
                'match_status' => $m['match_status'],
                'raw_name'     => $r['name'],
                'jg'           => $r['jg'],
                'op_resultat'  => $opRes,
                'fs_resultat'  => $fsRes,
                'quellen'      => [],
                'dup_op'       => isset($existOp[$mid]),
                'dup_fs'       => isset($existFs[$mid]),
            ];
        } else {
            if ($opRes !== null && ($byMember[$mid]['op_resultat'] === null || $opRes > $byMember[$mid]['op_resultat'])) {
                $byMember[$mid]['op_resultat'] = $opRes;
            }
            if ($fsRes !== null && ($byMember[$mid]['fs_resultat'] === null || $fsRes > $byMember[$mid]['fs_resultat'])) {
                $byMember[$mid]['fs_resultat'] = $fsRes;
            }
        }
        if ($quelle !== '' && !in_array($quelle, $byMember[$mid]['quellen'], true)) {
            $byMember[$mid]['quellen'][] = $quelle;
        }
    }

    $rows = array_values($byMember);
    foreach ($rows as &$row) {
        $row['quelle'] = implode(', ', $row['quellen']);
        unset($row['quellen']);
    }
    unset($row);
    usort($rows, fn($a, $b) => strcasecmp($a['matched_name'], $b['matched_name']));

    $stats = [
        'total_lines' => $totalLines,
        'matched'     => count($rows),
        'op_count'    => count(array_filter($rows, fn($x) => $x['op_resultat'] !== null)),
        'fs_count'    => count(array_filter($rows, fn($x) => $x['fs_resultat'] !== null)),
        'duplicates'  => count(array_filter($rows, fn($x) => $x['dup_op'] || $x['dup_fs'])),
        'fuzzy'       => count(array_filter($rows, fn($x) => $x['match_status'] === 'fuzzy')),
    ];

    $resp = [
        'success' => true,
        'mode'    => 'opfs',
        'rows'    => $rows,
        'defs'    => $defs,
        'stats'   => $stats,
        'message' => $stats['matched'] . ' Vereinsmitglieder von ' . $totalLines . ' Teilnehmer-Zeilen erkannt',
    ];
    if ($debug && isset($parsed['debug'])) $resp['debug'] = $parsed['debug'];
    echo json_encode($resp);
}

/**
 * Import des OP/FS-Bonus: schreibt pro ausgewaehltem Mitglied die Bonus-Punkte
 * in jmresultate (Obligatorisch bzw. Feldschiessen, status='freigegeben').
 * Leere Felder werden uebersprungen.
 */
function handleImportOpfs($conn) {
    $year = intval($_POST['year'] ?? date('Y'));
    $rows = json_decode($_POST['rows'] ?? '[]', true);
    if (!is_array($rows) || empty($rows)) {
        echo json_encode(['success' => false, 'message' => 'Keine Zeilen zum Importieren ausgewählt']);
        return;
    }

    $defs = opfsLoadDefs($conn, $year);
    if ($defs['op'] === null || $defs['fs'] === null) {
        echo json_encode(['success' => false, 'message' => "Für {$year} sind die Anlässe «Obligatorisch»/«Feldschiessen» nicht definiert."]);
        return;
    }

    $vorstandUserId = $_SESSION['user_id'] ?? null;
    $countOp = 0;
    $countFs = 0;

    try {
        $conn->begin_transaction();

        foreach ($rows as $r) {
            $mid = intval($r['mitglied_id'] ?? 0);
            if ($mid <= 0) continue;

            $opRaw = trim((string) ($r['op_punkte'] ?? ''));
            if ($opRaw !== '' && is_numeric($opRaw)) {
                upsertJmResultat($conn, $mid, $defs['op']['id'], (int) $opRaw, $vorstandUserId);
                $countOp++;
            }
            $fsRaw = trim((string) ($r['fs_punkte'] ?? ''));
            if ($fsRaw !== '' && is_numeric($fsRaw)) {
                upsertJmResultat($conn, $mid, $defs['fs']['id'], (int) $fsRaw, $vorstandUserId);
                $countFs++;
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('rangliste_import opfs import error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fehler beim Import: ' . $e->getMessage()]);
        return;
    }

    if (function_exists('logChangelog')) {
        logChangelog('resultate', 'aktualisiert', 'Obligatorisch/Feldschiessen-Bonus via FSA-Import erfasst',
            ['tabelle' => 'jmresultate', 'jahr' => $year, 'sichtbar' => 0]);
    }

    echo json_encode([
        'success'  => true,
        'message'  => "Import abgeschlossen: {$countOp}× Obligatorisch, {$countFs}× Feldschiessen",
        'count_op' => $countOp,
        'count_fs' => $countFs,
    ]);
}

/** Upsert in einzelrangierungen (INSERT, sonst UPDATE bei vorhandenem Eintrag). */
function upsertEinzelrangierung($conn, $year, $jmdefId, $mitgliedId, $rang, $resultat, $preis) {
    $stmt = $conn->prepare("SELECT id FROM einzelrangierungen WHERE year = ? AND jmdefinition_id = ? AND mitglied_id = ? LIMIT 1");
    $stmt->bind_param('iii', $year, $jmdefId, $mitgliedId);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = $res->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $id = (int) $existing['id'];
        $stmt = $conn->prepare("UPDATE einzelrangierungen
                                   SET rang = ?, resultat = ?, preis = ?, updated_at = CURRENT_TIMESTAMP
                                 WHERE id = ?");
        $stmt->bind_param('isdi', $rang, $resultat, $preis, $id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO einzelrangierungen (year, jmdefinition_id, mitglied_id, rang, resultat, preis)
                                VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iiiisd', $year, $jmdefId, $mitgliedId, $rang, $resultat, $preis);
        $stmt->execute();
        $stmt->close();
    }
}
