<?php
// api/portal_jm_save.php - Mitglied speichert eigenes JM-Resultat (Auto-Save)
// Workflow:
//  - Mitglied gibt Punkte ein -> status='entwurf', editierbar
//  - Sobald Vorstand das Resultat speichert -> status='freigegeben', gesperrt
//  - Vorstand/Admin koennen jederzeit ueber inc/jmresultate.php korrigieren

require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../inc/changelog_helper.php';

header('Content-Type: application/json; charset=utf-8');

// json_error() wird zentral in auth.php bereitgestellt

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Ungültige Anfrage', 405);
}

requireLogin();

// CSRF (POST-Feld oder X-CSRF-TOKEN-Header)
if (!validateCsrfRequest()) {
    json_error('Ungültiges Sicherheits-Token. Bitte Seite neu laden.', 403);
}

$mitglied_id = $_SESSION['mitglied_id'] ?? null;
$user_id     = $_SESSION['user_id'] ?? null;
if (!$mitglied_id) {
    json_error('Kein Mitglied mit diesem Konto verknüpft.');
}

$year             = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');
$jmdefinition_id  = isset($_POST['jmdefinition_id']) ? (int)$_POST['jmdefinition_id'] : 0;
$punkte_raw       = $_POST['punkte'] ?? null;

if ($jmdefinition_id <= 0) {
    json_error('Ungültiger Wettbewerb.');
}
// Nur aktuelles Jahr erlauben
if ($year !== (int)date('Y')) {
    json_error('Eingaben sind nur für das aktuelle Jahr möglich.');
}

$db = getDB();

// JMDefinition laden + Berechtigung pruefen
$stmt = $db->prepare("
    SELECT ID, Bezeichnung, Maxpunkte, Schiesstage, hidden, Erweitert, Info, year
    FROM JMDefinition
    WHERE ID = ?
");
$stmt->execute([$jmdefinition_id]);
$def = $stmt->fetch();
if (!$def) {
    json_error('Wettbewerb nicht gefunden.');
}
if ((int)$def['year'] !== $year) {
    json_error('Wettbewerb gehört nicht zum gewählten Jahr.');
}
if ((int)$def['hidden'] === 1 || (int)$def['Erweitert'] === 1 || (int)$def['Info'] === 1) {
    json_error('Dieser Wettbewerb erlaubt keine Selbsteingabe.');
}
if (trim((string)($def['Schiesstage'] ?? '')) === '') {
    json_error('Dieser Wettbewerb erlaubt keine Selbsteingabe.');
}
$blocked = ['Endstich', 'Bester Kantonalstich', 'Sektionsmeisterschaft'];
if (in_array($def['Bezeichnung'], $blocked, true)) {
    json_error('Dieser Wettbewerb wird vom Vorstand erfasst.');
}
// Vereinscup: Resultat stammt aus der Cup-Erfassung (inc/cup.php), keine Selbsteingabe.
if (preg_match('/Vereins[- ]?cup/i', (string)$def['Bezeichnung'])) {
    json_error('Das Cup-Resultat wird über die Cup-Erfassung geführt und kann hier nicht erfasst werden.');
}
// Teilnahme-Anlaesse (Maxpunkte == 20): Teilnahme = immer volle Punktzahl, der Vorstand
// traegt diese ein. Im Portal nicht selbst erfassbar (analog zu jmIsTeilnahme in meine_jm.php).
if ((int)$def['Maxpunkte'] === 20) {
    json_error('Dieser Wettbewerb wird vom Vorstand erfasst.');
}

// Punkte validieren (leer/null/'' = "nicht teilgenommen" -> Eintrag loeschen)
$punkte_str = is_null($punkte_raw) ? '' : trim((string)$punkte_raw);
$delete_mode = ($punkte_str === '');

if (!$delete_mode) {
    if (!is_numeric($punkte_str)) {
        json_error('Punktzahl muss eine Zahl sein.');
    }
    $punkte = (int)$punkte_str;
    $maxpunkte = (int)$def['Maxpunkte'];
    if ($punkte < 0) {
        json_error('Punktzahl darf nicht negativ sein.');
    }
    if ($maxpunkte > 0 && $punkte > $maxpunkte) {
        json_error("Punktzahl darf maximal $maxpunkte betragen.");
    }
}

try {
    // Existierenden Eintrag (Info='') laden
    $sel = $db->prepare("
        SELECT ID, Punkte, status
        FROM jmresultate
        WHERE mitgliederID = ? AND jmdefinitionID = ? AND (Info = '' OR Info IS NULL)
        LIMIT 1
    ");
    $sel->execute([$mitglied_id, $jmdefinition_id]);
    $existing = $sel->fetch();

    // Lock-Check: freigegebene Eintraege darf das Mitglied nicht aendern
    if ($existing && $existing['status'] === 'freigegeben') {
        json_error('Dieses Resultat wurde vom Vorstand bestätigt und kann nicht mehr geändert werden.', 403);
    }

    if ($delete_mode) {
        if ($existing) {
            $del = $db->prepare("DELETE FROM jmresultate WHERE ID = ?");
            $del->execute([(int)$existing['ID']]);
            logChangelog('resultate', 'geloescht', 'JM-Eingabe (Mitglied) zurueckgenommen', [
                'tabelle' => 'jmresultate',
                'jahr'    => $year,
                'sichtbar'=> 0,
                'details' => [
                    'mitglied_id'      => (int)$mitglied_id,
                    'jmdefinition_id'  => $jmdefinition_id,
                    'bezeichnung'      => $def['Bezeichnung'],
                ],
            ]);
        }
        echo json_encode([
            'success' => true,
            'message' => 'Eingabe entfernt',
            'status'  => null,
            'punkte'  => null,
        ]);
        return;
    }

    if ($existing) {
        $upd = $db->prepare("
            UPDATE jmresultate
               SET Punkte = ?, status = 'entwurf',
                   eingegeben_von = ?, eingegeben_am = NOW(),
                   freigegeben_von = NULL, freigegeben_am = NULL
             WHERE ID = ?
        ");
        $upd->execute([$punkte, $user_id, (int)$existing['ID']]);
    } else {
        $ins = $db->prepare("
            INSERT INTO jmresultate
                (mitgliederID, jmdefinitionID, Punkte, Info, status, eingegeben_von, eingegeben_am)
            VALUES (?, ?, ?, '', 'entwurf', ?, NOW())
        ");
        $ins->execute([$mitglied_id, $jmdefinition_id, $punkte, $user_id]);
    }

    logChangelog('resultate', 'aktualisiert', 'JM-Eingabe (Mitglied) gespeichert', [
        'tabelle' => 'jmresultate',
        'jahr'    => $year,
        'sichtbar'=> 0,
        'details' => [
            'mitglied_id'      => (int)$mitglied_id,
            'jmdefinition_id'  => $jmdefinition_id,
            'bezeichnung'      => $def['Bezeichnung'],
            'punkte'           => $punkte,
            'aktion'           => 'entwurf_eingegeben',
        ],
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Resultat gespeichert',
        'status'  => 'entwurf',
        'punkte'  => $punkte,
    ]);
} catch (Throwable $e) {
    error_log('[portal_jm_save] ' . $e->getMessage());
    json_error('Fehler beim Speichern.', 500);
}
