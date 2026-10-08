<?php
/**
 * Kantonalstich: SKSG-Abrechnungsformular (xlsm) für ein Jahr befüllen.
 *
 * POST (CSRF): year, verantwortlicher_id (Mitglied), adresse1, adresse2, email
 * Die Kopfangaben werden in `settings` (kanti_*) gemerkt und beim nächsten Mal vorbelegt.
 * Antwort JSON: { success, xls_link, anzahl, warnungen[] } bzw. { success:false, message }
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config.php';                 // $conn (mysqli) + dat-Aufräumen
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('json');
require_once __DIR__ . '/../csrf.inc.php';
csrf_require(true);
require_once __DIR__ . '/kanti_abrechnung_xlsm.inc.php';

header('Content-Type: application/json; charset=utf-8');

$year = (int) ($_POST['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) {
    echo json_encode(['success' => false, 'message' => 'Ungültiges Jahr']);
    exit;
}

$verantwortlicherId = (int) ($_POST['verantwortlicher_id'] ?? 0);
$kopf = [
    'jahr'             => $year,
    'verantwortlicher_id' => (string) $verantwortlicherId,
    'verantwortlicher' => '',
    'adresse1'         => mb_substr(trim((string) ($_POST['adresse1'] ?? '')), 0, 120),
    'adresse2'         => mb_substr(trim((string) ($_POST['adresse2'] ?? '')), 0, 120),
    'email'            => mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 120),
];

try {
    // Verantwortlicher = gewähltes Mitglied (Name aus den Stammdaten)
    if ($verantwortlicherId > 0) {
        $stV = $conn->prepare('SELECT Vorname, Name FROM mitglieder WHERE ID = ?');
        $stV->bind_param('i', $verantwortlicherId);
        $stV->execute();
        if ($v = $stV->get_result()->fetch_assoc()) {
            $kopf['verantwortlicher'] = trim($v['Vorname'] . ' ' . $v['Name']);
        } else {
            $verantwortlicherId = 0;
            $kopf['verantwortlicher_id'] = '0';
        }
        $stV->close();
    }

    // Kopfangaben merken (Mitglied bleibt gewählt, bis es geändert wird)
    $st = $conn->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach (['verantwortlicher_id', 'adresse1', 'adresse2', 'email'] as $k) {
        $key = 'kanti_' . $k;
        $st->bind_param('ss', $key, $kopf[$k]);
        $st->execute();
    }
    $st->close();

    // Schützen mit mindestens einer geschossenen Passe
    $sql = "SELECT m.Name, m.Vorname, YEAR(m.Geburtsdatum) AS Jahrgang, w.Bezeichnung,
                   kr.Passe1, kr.Passe2, kr.Passe3, kr.Passe4, kr.Passe5
            FROM kantiresultate kr
            JOIN mitglieder m ON m.ID = kr.MitgliedID
            LEFT JOIN Waffen w ON w.ID = m.WaffenID
            WHERE kr.Jahr = ?
              AND (kr.Passe1 > 0 OR kr.Passe2 > 0 OR kr.Passe3 > 0 OR kr.Passe4 > 0 OR kr.Passe5 > 0)
            ORDER BY m.Name ASC, m.Vorname ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $res = $stmt->get_result();

    $schuetzen = [];
    while ($r = $res->fetch_assoc()) {
        $schuetzen[] = [
            'name'     => $r['Name'],
            'vorname'  => $r['Vorname'],
            'jahrgang' => $r['Jahrgang'],
            'waffe'    => $r['Bezeichnung'],
            'passen'   => [$r['Passe1'], $r['Passe2'], $r['Passe3'], $r['Passe4'], $r['Passe5']],
        ];
    }
    $stmt->close();

    if (!$schuetzen) {
        echo json_encode(['success' => false, 'message' => 'Keine Resultate für das Jahr ' . $year . ' gefunden']);
        exit;
    }

    $datei = 'Kantonalstich_Abrechnung_' . $year . '_' . date('Y-m-d_H-i-s') . '.xlsm';
    $warnungen = kantiAbrechnungXlsmErzeugen($kopf, $schuetzen, __DIR__ . '/dat/' . $datei);

    echo json_encode([
        'success'   => true,
        'xls_link'  => 'dat/' . $datei,
        'anzahl'    => count($schuetzen),
        'warnungen' => $warnungen,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('generate_kantiabr_xls: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => msvFehler('Das Dokument konnte nicht erstellt werden. Bitte nochmals versuchen.', $e)], JSON_UNESCAPED_UNICODE);
}
