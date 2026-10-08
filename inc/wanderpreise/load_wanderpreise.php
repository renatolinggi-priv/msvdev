<?php
// load_wanderpreise.php – Wanderpreise mit ihrem Stand für ein Jahr (HTML-Fragment für wanderpreise.php).
// Stand je Preis, gleiche Regel wie «Absenden vorbereiten» (endschrang/absenden_bereit.php):
//   im Umlauf = bis zum Jahr angeschafft und nicht in einem früheren Jahr definitiv gewonnen
//   vergeben  = für das Jahr ist ein Gewinner eingetragen
//   offen     = im Umlauf, aber noch ohne Gewinner für das Jahr
//   ausser    = nicht im Umlauf (später angeschafft oder früher definitiv gewonnen)
// Reihenfolge: offen, vergeben, ausser – zuerst steht, was jetzt ansteht.
require_once 'wanderpreise_config.php';
require_once __DIR__ . '/../admin_api_guard.inc.php';
adminApiGuard('html');
require_once '../dbconnect.inc.php';

$aktuell = (int)date('Y');
$jahr = (int)($_GET['jahr'] ?? $aktuell);
if ($jahr < 1990 || $jahr > $aktuell + 1) $jahr = $aktuell;

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

try {
    $preise = $conn->query(
        "SELECT id, bezeichnung, beschreibung, beschaffung_datum, min_anzahl_gewinne, hersteller
           FROM wanderpreise ORDER BY bezeichnung"
    )->fetch_all(MYSQLI_ASSOC);

    // Alle Gewinne mit Namen auf einmal; pro Preis nach Jahr aufsteigend
    $gewinne = [];
    $r = $conn->query(
        "SELECT g.wanderpreis_id, g.jahr, g.rang, g.ist_definitiv, g.anzahl_gewinne,
                TRIM(CONCAT(COALESCE(m.Name, ''), ' ', COALESCE(m.Vorname, ''))) AS name
           FROM wanderpreise_gewinner g
           LEFT JOIN mitglieder m ON m.ID = g.gewinner_id
          ORDER BY g.wanderpreis_id, g.jahr"
    );
    while ($g = $r->fetch_assoc()) $gewinne[(int)$g['wanderpreis_id']][] = $g;

    if (!$preise) {
        echo '<div class="ui-leerzustand"><i class="bi bi-trophy" aria-hidden="true"></i>'
           . 'Noch keine Wanderpreise erfasst. Unter «Weitere» › «Wanderpreis anlegen» den ersten anlegen.</div>';
        exit;
    }

    $zeilen = [];
    foreach ($preise as $p) {
        $id   = (int)$p['id'];
        $min  = max(1, (int)$p['min_anzahl_gewinne']);
        $alle = $gewinne[$id] ?? [];
        $imJahr = null; $zuletzt = null; $definitivVorher = null;
        foreach ($alle as $g) {
            $gj = (int)$g['jahr'];
            if ($gj === $jahr) $imJahr = $g;
            if ($gj < $jahr) {
                $zuletzt = $g;                                   // aufsteigend sortiert: der letzte gewinnt
                if ((int)$g['ist_definitiv'] === 1) $definitivVorher = $g;
            }
        }
        $angeschafft = (int)$p['beschaffung_datum'];
        if ($imJahr)                          $status = 'vergeben';
        elseif ($definitivVorher)             $status = 'ausser';
        elseif ($angeschafft > $jahr)         $status = 'ausser';
        else                                  $status = 'offen';
        $zeilen[] = compact('p', 'id', 'min', 'imJahr', 'zuletzt', 'definitivVorher', 'angeschafft', 'status');
    }
    $rang = ['offen' => 0, 'vergeben' => 1, 'ausser' => 2];
    usort($zeilen, fn($a, $b) => $rang[$a['status']] <=> $rang[$b['status']]
        ?: strcasecmp($a['p']['bezeichnung'], $b['p']['bezeichnung']));

    $n = ['alle' => count($zeilen), 'offen' => 0, 'vergeben' => 0, 'ausser' => 0];
    foreach ($zeilen as $z) $n[$z['status']]++;

    // Bausteine je Zeile (Tabelle und Handy-Karte nutzen dieselben Texte)
    $teile = function (array $z) use ($h, $jahr) {
        $p = $z['p'];
        $meta = [];
        if ($z['angeschafft'] > 0) $meta[] = 'seit ' . $z['angeschafft'];
        if (!empty($p['hersteller'])) $meta[] = 'Hersteller ' . $h($p['hersteller']);
        $siege = fn($g) => ((int)$g['ist_definitiv'] === 1)
            ? '<span class="wp-sub wp-definitiv">definitiv gewonnen</span>'
            : '<span class="wp-sub">Sieg ' . max(1, (int)$g['anzahl_gewinne']) . ' von ' . $z['min'] . '</span>';

        if ($z['status'] === 'vergeben') {
            $g = $z['imJahr'];
            $stand = '<span class="wp-gewinner">' . $h($g['name'] ?: 'Mitglied unbekannt') . '</span>'
                   . (trim((string)$g['rang']) !== '' ? '<span class="wp-sub">' . $h($g['rang']) . '</span>' : '')
                   . $siege($g);
        } elseif ($z['status'] === 'offen') {
            $stand = '<span class="ui-status offen"><span class="ui-punkt"></span>offen</span>';
        } elseif ($z['definitivVorher']) {
            $d = $z['definitivVorher'];
            $stand = '<span class="wp-sub">definitiv bei ' . $h($d['name']) . ' (' . (int)$d['jahr'] . ')</span>';
        } else {
            $stand = '<span class="wp-sub">angeschafft ' . $z['angeschafft'] . '</span>';
        }

        if ($z['zuletzt']) {
            $g = $z['zuletzt'];
            $zuletzt = '<span class="wp-gewinner-alt">' . $h($g['name'] ?: 'Mitglied unbekannt') . '</span>'
                     . '<span class="wp-sub">' . (int)$g['jahr'] . ' · ' . strip_tags($siege($g)) . '</span>';
        } else {
            $zuletzt = '<span class="cell-empty">–</span>';
        }
        return [$meta ? implode(' · ', $meta) : '', $stand, $zuletzt];
    };

    // Zähler für Kopf, Filter und Fortschritt (liest wanderpreise.php)
    echo '<div id="wpStandDaten" hidden data-jahr="' . $jahr . '" data-alle="' . $n['alle'] . '" data-offen="' . $n['offen']
       . '" data-vergeben="' . $n['vergeben'] . '" data-ausser="' . $n['ausser'] . '"></div>';

    // ---------- Desktop: Tabelle ----------
    echo '<div class="desktop-table-container"><div class="table-responsive">';
    echo '<table class="table table-hover mb-0" id="wanderpreiseTable">';
    echo '<thead><tr><th scope="col">Wanderpreis</th><th scope="col">Gewinner ' . $jahr . '</th>'
       . '<th scope="col">Zuletzt</th><th scope="col"><span class="visually-hidden">Aktionen</span></th></tr></thead><tbody>';
    foreach ($zeilen as $z) {
        [$meta, $stand, $zuletzt] = $teile($z);
        $p = $z['p'];
        $klasse = $z['status'] === 'offen' ? ' ui-offen' : ($z['status'] === 'ausser' ? ' wp-ausser' : '');
        $tip = trim((string)$p['beschreibung']) !== '' ? ' data-tooltip="' . $h($p['beschreibung']) . '"' : '';
        echo '<tr class="wanderpreis-row' . $klasse . '" data-wanderpreis-id="' . $z['id'] . '" data-status="' . $z['status']
           . '" data-bezeichnung="' . $h($p['bezeichnung']) . '">';
        echo '<td><button type="button" class="wp-name view-gewinner" data-id="' . $z['id'] . '"' . $tip . '>' . $h($p['bezeichnung']) . '</button>'
           . ($meta !== '' ? '<span class="wp-meta">' . $meta . '</span>' : '') . '</td>';
        echo '<td>' . $stand . '</td>';
        echo '<td>' . $zuletzt . '</td>';
        echo '<td class="wp-aktionen">'
           . '<button type="button" class="btn btn-outline-primary btn-sm btn-icon edit-wanderpreis" data-id="' . $z['id'] . '" data-tooltip="Bearbeiten" aria-label="' . $h($p['bezeichnung']) . ' bearbeiten"><i class="bi bi-pencil" aria-hidden="true"></i></button> '
           . '<button type="button" class="btn btn-outline-danger btn-sm btn-icon delete-wanderpreis" data-id="' . $z['id'] . '" data-tooltip="Löschen" aria-label="' . $h($p['bezeichnung']) . ' löschen"><i class="bi bi-trash" aria-hidden="true"></i></button>'
           . '</td>';
        echo '</tr>';
    }
    echo '<tr class="wp-keine" hidden><td colspan="4"><div class="ui-leerzustand"><i class="bi bi-funnel" aria-hidden="true"></i><span class="wp-keine-text">Keine Wanderpreise in dieser Auswahl.</span></div></td></tr>';
    echo '</tbody></table></div></div>';

    // ---------- Handy: Karten (gleiche Daten, gleiche Reihenfolge) ----------
    echo '<div class="mobile-cards-container" id="mobileWanderpreiseCards"><div class="mobile-cards-scroll">';
    foreach ($zeilen as $z) {
        [$meta, $stand, $zuletzt] = $teile($z);
        $p = $z['p'];
        echo '<div class="mobile-card wp-karte' . ($z['status'] === 'ausser' ? ' wp-ausser' : '') . '" data-status="' . $z['status'] . '" data-bezeichnung="' . $h($p['bezeichnung']) . '">'
           . '<div class="mobile-card-header">'
           . '<div class="min-w-0"><button type="button" class="wp-name view-gewinner" data-id="' . $z['id'] . '">' . $h($p['bezeichnung']) . '</button>'
           . ($meta !== '' ? '<span class="wp-meta">' . $meta . '</span>' : '') . '</div>'
           . '<div class="d-flex gap-1 flex-shrink-0">'
           . '<button type="button" class="btn btn-outline-primary btn-sm edit-wanderpreis" data-id="' . $z['id'] . '" aria-label="' . $h($p['bezeichnung']) . ' bearbeiten"><i class="bi bi-pencil" aria-hidden="true"></i></button>'
           . '<button type="button" class="btn btn-outline-danger btn-sm delete-wanderpreis" data-id="' . $z['id'] . '" aria-label="' . $h($p['bezeichnung']) . ' löschen"><i class="bi bi-trash" aria-hidden="true"></i></button>'
           . '</div></div>'
           . '<div class="mobile-card-body">'
           . '<div class="mobile-card-row"><span class="mobile-card-label">Gewinner ' . $jahr . '</span><span class="mobile-card-value">' . $stand . '</span></div>'
           . '<div class="mobile-card-row"><span class="mobile-card-label">Zuletzt</span><span class="mobile-card-value">' . $zuletzt . '</span></div>'
           . '</div></div>';
    }
    echo '<div class="wp-keine ui-leerzustand" hidden><i class="bi bi-funnel" aria-hidden="true"></i><span class="wp-keine-text">Keine Wanderpreise in dieser Auswahl.</span></div>';
    echo '</div></div>';

} catch (Throwable $e) {
    http_response_code(500);
    echo '<div class="ui-leerzustand text-danger"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i>'
       . $h(msvFehler('Die Wanderpreise konnten nicht geladen werden. Bitte die Seite neu laden.', $e)) . '</div>';
}
