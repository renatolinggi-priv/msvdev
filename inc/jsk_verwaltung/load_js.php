<?php
// inc/jsk_verwaltung/load_js.php
// Rendert die Jungschuetzen-Tabellenzeilen (Hybrid-Row) inkl. Konto-Status.
// Read-only Fragment fuer die JSK-Verwaltung. Vorstand/Admin.

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../dbconnect.inc.php';

if (!isLoggedIn() || !(isAdmin() || isVorstand())) {
    http_response_code(403);
    exit('Zugriff verweigert');
}
header('Content-Type: text/html; charset=utf-8');

$esc = function ($v) { return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8'); };

$db = getDB();
$rows = $db->query(
    "SELECT j.id, j.Vorname, j.Name, j.Geburtsdatum, j.AHVNummer, j.Strasse, j.PLZ, j.Ort,
            j.Email, j.Mobile, j.KursNummer, j.KursJahr, j.Aktiv,
            u.id AS konto_user_id, u.status AS konto_status, u.role AS konto_role
       FROM jungschuetzen j
       LEFT JOIN users u ON u.jungschuetze_id = j.id
      ORDER BY j.Name ASC, j.Vorname ASC"
)->fetchAll();

if (!$rows) {
    echo '<tr><td colspan="9" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Keine Jungschützen erfasst</td></tr>';
    return;
}

// Konto-Badge je Status
$kontoBadge = function ($status) {
    switch ($status) {
        case 'approved': return '<span class="badge bg-success">Konto aktiv</span>';
        case 'pending':  return '<span class="badge bg-warning text-dark">Freigabe offen</span>';
        case 'rejected': return '<span class="badge bg-danger">Abgelehnt</span>';
        case 'disabled': return '<span class="badge bg-secondary">Deaktiviert</span>';
        default:         return '<span class="badge bg-light text-muted border">Kein Konto</span>';
    }
};

foreach ($rows as $r) {
    $id = (int) $r['id'];
    $aktiv = (int) $r['Aktiv'];

    $gebDisplay = '';
    if (!empty($r['Geburtsdatum'])) {
        $ts = strtotime($r['Geburtsdatum']);
        if ($ts) $gebDisplay = date('d.m.Y', $ts);
    }
    $kurs = $r['KursNummer'] ? ('K' . (int) $r['KursNummer']) : '';
    if (!empty($r['KursJahr'])) $kurs = trim($kurs . ' / ' . (int) $r['KursJahr']);

    $rowStyle = $aktiv ? '' : ' style="opacity:0.5;"';

    echo '<tr class="hybrid-row" id="jrow' . $id . '"'
        . ' data-id="' . $id . '"'
        . ' data-name="' . $esc($r['Name']) . '"'
        . ' data-vorname="' . $esc($r['Vorname']) . '"'
        . ' data-geburtsdatum="' . $esc($r['Geburtsdatum']) . '"'
        . ' data-ahvnummer="' . $esc($r['AHVNummer']) . '"'
        . ' data-strasse="' . $esc($r['Strasse']) . '"'
        . ' data-plz="' . $esc($r['PLZ']) . '"'
        . ' data-ort="' . $esc($r['Ort']) . '"'
        . ' data-email="' . $esc($r['Email']) . '"'
        . ' data-mobile="' . $esc($r['Mobile']) . '"'
        . ' data-kursnummer="' . (int) $r['KursNummer'] . '"'
        . ' data-kursjahr="' . $esc($r['KursJahr']) . '"'
        . ' data-aktiv="' . $aktiv . '"'
        . ' data-konto-user-id="' . (int) ($r['konto_user_id'] ?? 0) . '"'
        . ' data-konto-status="' . $esc($r['konto_status']) . '"'
        . $rowStyle . '>';

    echo '<td class="h-name">' . $esc($r['Name']) . '</td>';
    echo '<td>' . $esc($r['Vorname']) . '</td>';
    echo '<td class="h-date">' . $gebDisplay . '</td>';
    echo '<td class="h-sub">' . $esc($r['Ort']) . '</td>';
    echo '<td class="h-email">' . $esc($r['Email']) . '</td>';
    echo '<td class="h-sub">' . $esc($r['Mobile']) . '</td>';
    echo '<td class="h-sub text-center">' . $esc($kurs) . '</td>';
    echo '<td class="text-center">' . $kontoBadge($r['konto_status']) . '</td>';
    echo '<td class="text-center">';
    echo '<span class="flag-dot ' . ($aktiv ? 'on' : 'off') . '" data-tooltip="Aktiv"><i class="bi bi-check-lg"></i></span>';
    echo '</td>';

    echo '</tr>';
}
