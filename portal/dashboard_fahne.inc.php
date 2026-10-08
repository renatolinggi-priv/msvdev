<?php
// dashboard_fahne.inc.php – Startseite «Vereinsfahne» (Vorschau, nur Admins mit Schalter, Okt 2026).
// Eingebunden von dashboard.php nach portal_header.php. Nutzt dessen Daten ($vorname, $mitglied_id,
// $next_event, $next_einsatz, $next_termine, $offene_tausch, $offene_umfragen, $push_setup_noetig)
// und rechnet den JM-Stand mit inc_jm_stand.php (gleich wie meine_jm.php).
// Aufbau: Fahnenband (Begrüssung + Saisonband) → Jetzt (JM-Stand, nächster Anlass, nächster Einsatz)
// → Für dich offen → Vereinstermine → Fotos vom letzten Anlass → alle Bereiche.
if (!isset($db) || !($db instanceof PDO)) { http_response_code(404); exit; }

$selected_year = (int) date('Y');
require_once __DIR__ . '/inc_jm_stand.php';
require_once __DIR__ . '/../inc/fotogalerie.inc.php';

$fs_tage   = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
$fs_monate = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
$fs_heute  = date('Y-m-d');

// Punkte wie in meine_jm.php: ganze Zahl oder zwei Stellen
$fs_zahl = function ($p): string {
    $p = (float) $p;
    return ($p == (int) $p) ? (string) (int) $p : number_format($p, 2, '.', '');
};
$fs_datum_lang = function (string $ymd) use ($fs_tage, $fs_monate): string {
    $t = strtotime($ymd);
    return $fs_tage[(int) date('w', $t)] . ', ' . (int) date('j', $t) . '. ' . $fs_monate[(int) date('n', $t)];
};

// ---- Saisonband: ein Feld pro JM-Anlass in Reihenfolge -----------------------------------
$fs_band = [];
$fs_naechster = null;   // Index im Band
foreach ($schiessen_list as $s) {
    if (in_array($s['_idx'], $sektions_streicher_idxs)) continue;   // Duplikate der Sektionsmeisterschaft
    $zeilen = [];
    $erster = null;
    $letzter = null;
    foreach (array_filter(array_map('trim', explode("\n", (string) ($s['Schiesstage'] ?? '')))) as $zeile) {
        $dt = splitSchiessDatum($zeile);
        $d  = jmParseDatum($dt['date'], $months_de, $selected_year);
        $zeilen[] = $dt;
        if ($d === null) continue;
        if ($erster === null || $d < $erster) $erster = $d;
        if ($letzter === null || $d > $letzter) $letzter = $d;
    }
    $geschossen = ($s['PunkteNorm'] !== null);
    $vorbei     = ($letzter !== null && $letzter < $fs_heute);
    if ($fs_naechster === null && !$geschossen && !$vorbei && $letzter !== null) {
        $fs_naechster = count($fs_band);
    }
    $fs_band[] = [
        'name'       => (string) $s['Bezeichnung'],
        'erster'     => $erster,
        'zeilen'     => $zeilen,
        'adresse'    => (string) ($s['Adresse'] ?? ''),
        'geschossen' => $geschossen,
        'streicher'  => in_array($s['_idx'], $all_streicher_idxs),
        'teilnahme'  => jmIsTeilnahme($s),
        'punkte'     => $s['PunkteNorm'],
        'vorbei'     => $vorbei,
    ];
}
$fs_geschossen = count(array_filter($fs_band, fn($f) => $f['geschossen']));
$fs_anzahl     = count($fs_band);

// ---- Fotos vom letzten Anlass mit Bildern --------------------------------------------------
$fs_galerie = null;
if (fotoFeatureAktiv()) {
    try {
        $st = $db->query(
            "SELECT g.id, d.Bezeichnung AS name, d.year AS jahr,
                    (SELECT COUNT(*) FROM anlass_fotos f WHERE f.galerie_id = g.id AND f.status = 'approved') AS total,
                    COALESCE(
                      (SELECT f.id FROM anlass_fotos f WHERE f.id = g.cover_foto_id AND f.galerie_id = g.id AND f.status = 'approved'),
                      (SELECT f.id FROM anlass_fotos f WHERE f.galerie_id = g.id AND f.status = 'approved'
                         ORDER BY " . fotoOrderBySql('f.') . " LIMIT 1)
                    ) AS cover_id
               FROM anlass_galerie g
               JOIN JMDefinition d ON d.ID = g.jmdefinition_id
              WHERE g.freigeschaltet = 1
             HAVING cover_id IS NOT NULL
              ORDER BY d.year DESC, d.Reihenfolge DESC
              LIMIT 1"
        );
        $fs_galerie = $st->fetch() ?: null;
    } catch (Throwable $e) { $fs_galerie = null; }
}

$fs_offen = [];
if ($offene_tausch > 0) {
    $fs_offen[] = ['meine_einsaetze.php', 'bi-arrow-left-right', $offene_tausch === 1 ? '1 Tausch-Anfrage wartet auf deine Antwort' : $offene_tausch . ' Tausch-Anfragen warten auf deine Antwort'];
}
if ($offene_umfragen > 0) {
    $fs_offen[] = ['mein_fragebogen.php', 'bi-clipboard-check', $offene_umfragen === 1 ? '1 Umfrage wartet auf deine Antwort' : $offene_umfragen . ' Umfragen warten auf deine Antworten'];
}
if (!empty($push_setup_noetig)) {
    $fs_offen[] = ['benachrichtigungen.php', 'bi-bell', 'Benachrichtigungen auf diesem Gerät einschalten, damit sie ankommen'];
}
?>

<!-- Fahnenband: Begrüssung und Saison -->
<section class="fs-fahne" aria-labelledby="fsGruss">
    <div class="fs-gruss">
        <div>
            <h1 id="fsGruss">Hallo <?php echo htmlspecialchars($vorname); ?></h1>
            <p class="fs-heute"><?php echo htmlspecialchars($fs_datum_lang($fs_heute) . ' ' . date('Y')); ?></p>
        </div>
        <?php if ($mitglied_id): ?>
        <button type="button" class="fs-barcode" id="barcodeBtn" aria-label="SSV-Lizenz als Barcode zeigen">
            <i class="bi bi-upc-scan" aria-hidden="true"></i><span>Lizenz</span>
        </button>
        <?php endif; ?>
    </div>

    <?php if ($fs_anzahl > 0): ?>
    <div class="fs-saison">
        <div class="fs-saison-kopf">
            <h2>Saison <?php echo $selected_year; ?></h2>
            <a href="meine_jm.php">Alle Resultate<i class="bi bi-chevron-right" aria-hidden="true"></i></a>
        </div>
        <ol class="fs-band">
            <?php foreach ($fs_band as $i => $f):
                $klasse = $f['geschossen'] ? 'is-geschossen' : ($f['vorbei'] ? 'is-vorbei' : 'is-offen');
                if ($i === $fs_naechster) $klasse .= ' is-naechster';
                if ($f['streicher']) $klasse .= ' is-streicher';
                if ($f['geschossen']) {
                    $wert  = $f['teilnahme'] ? '<i class="bi bi-check-lg" aria-hidden="true"></i>' : htmlspecialchars($fs_zahl($f['punkte']));
                    $stand = $f['teilnahme'] ? 'teilgenommen' : $fs_zahl($f['punkte']) . ' Punkte';
                    if ($f['streicher']) $stand .= ', Streicher';
                } elseif ($f['erster'] !== null && !$f['vorbei']) {
                    $wert  = date('j.n.', strtotime($f['erster']));
                    $stand = ($i === $fs_naechster ? 'als Nächstes, ' : 'offen, ') . $fs_datum_lang($f['erster']);
                } else {
                    $wert  = '–';
                    $stand = $f['vorbei'] ? 'ohne Resultat' : 'Datum noch offen';
                }
            ?>
            <li class="fs-feld <?php echo $klasse; ?>">
                <span class="fs-wert" aria-hidden="true"><?php echo $wert; ?></span>
                <span class="fs-name" aria-hidden="true"><?php echo htmlspecialchars($f['name']); ?></span>
                <span class="visually-hidden"><?php echo htmlspecialchars($f['name'] . ': ' . $stand); ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
        <p class="fs-saison-text">
            <?php echo $fs_geschossen; ?> von <?php echo $fs_anzahl; ?> Anlässen geschossen<?php
            if ($fs_naechster !== null) {
                echo ' · als Nächstes: ' . htmlspecialchars($fs_band[$fs_naechster]['name']);
            } ?>
        </p>
    </div>
    <?php endif; ?>
</section>

<!-- Jetzt: was für dich gerade zählt -->
<div class="fs-jetzt">
    <?php if ($mitglied_id && $fs_anzahl > 0): ?>
    <a class="fs-karte" href="meine_jm.php">
        <h2>Dein JM-Stand</h2>
        <p class="fs-stand"><span class="fs-gross"><?php echo htmlspecialchars($fs_zahl($total_punkte)); ?></span> Punkte</p>
        <p class="fs-meta">
            nach <?php echo $fs_geschossen; ?> von <?php echo $fs_anzahl; ?> Anlässen<?php
            if (!empty($streicher_idxs)) echo ' · ' . count($streicher_idxs) . ' Streicher'; ?>
        </p>
    </a>
    <?php endif; ?>

    <?php
    // Nächster Anlass: aus dem Saisonband; ohne JM-Anlass der nächste Schiessanlass wie bisher
    $fs_anlass = ($fs_naechster !== null) ? $fs_band[$fs_naechster] : null;
    if ($fs_anlass || $next_event):
        $fs_an_name    = $fs_anlass ? $fs_anlass['name'] : (string) $next_event['Bezeichnung'];
        $fs_an_datum   = $fs_anlass ? $fs_anlass['erster'] : null;
        $fs_an_adresse = $fs_anlass ? $fs_anlass['adresse'] : (string) ($next_event['Adresse'] ?? '');
    ?>
    <a class="fs-karte fs-anlass" href="termine.php">
        <?php if ($fs_an_datum): $t = strtotime($fs_an_datum); ?>
        <div class="fs-datumblock" aria-hidden="true">
            <span class="fs-tag"><?php echo (int) date('j', $t); ?></span>
            <span class="fs-monat"><?php echo htmlspecialchars($fs_monate[(int) date('n', $t)]); ?></span>
        </div>
        <?php endif; ?>
        <div class="fs-anlass-text">
            <h2>Nächster Anlass</h2>
            <p class="fs-titel"><?php echo htmlspecialchars($fs_an_name); ?></p>
            <p class="fs-meta">
                <?php if ($fs_anlass):
                    foreach ($fs_anlass['zeilen'] as $z) {
                        echo htmlspecialchars($z['date'] . ($z['time'] !== '' ? ', ' . $z['time'] : '')) . '<br>';
                    }
                else:
                    echo nl2br(htmlspecialchars((string) $next_event['Schiesstage'])) . '<br>';
                endif; ?>
                <?php if ($fs_an_adresse !== ''): ?><i class="bi bi-geo-alt" aria-hidden="true"></i> <?php echo htmlspecialchars($fs_an_adresse); ?><?php endif; ?>
            </p>
        </div>
    </a>
    <?php endif; ?>

    <?php if ($next_einsatz): $t = strtotime($next_einsatz['event_datum']); ?>
    <a class="fs-karte fs-anlass" href="meine_einsaetze.php">
        <div class="fs-datumblock is-einsatz" aria-hidden="true">
            <span class="fs-tag"><?php echo (int) date('j', $t); ?></span>
            <span class="fs-monat"><?php echo htmlspecialchars($fs_monate[(int) date('n', $t)]); ?></span>
        </div>
        <div class="fs-anlass-text">
            <h2>Dein nächster Einsatz</h2>
            <p class="fs-titel"><?php echo htmlspecialchars($next_einsatz['bezeichnung']); ?></p>
            <p class="fs-meta">
                <?php echo htmlspecialchars($fs_datum_lang($next_einsatz['event_datum'])); ?><?php if (!empty($next_einsatz['event_zeit'])): ?>, <?php echo htmlspecialchars($next_einsatz['event_zeit']); ?><?php endif; ?><br>
                <?php echo htmlspecialchars($next_einsatz['funktion']); ?>
            </p>
        </div>
    </a>
    <?php endif; ?>
</div>

<?php if (empty($portal_hide_pwa_install)) include __DIR__ . '/inc_pwa_install.php'; /* «Als App installieren», nur wenn noch nicht installiert */ ?>

<?php if ($fs_offen): ?>
<section class="fs-block" aria-labelledby="fsOffen">
    <h2 id="fsOffen">Für dich offen</h2>
    <ul class="fs-links">
        <?php foreach ($fs_offen as [$href, $icon, $text]): ?>
        <li><a href="<?php echo $href; ?>"><i class="bi <?php echo $icon; ?>" aria-hidden="true"></i><span><?php echo htmlspecialchars($text); ?></span><i class="bi bi-chevron-right fs-pfeil" aria-hidden="true"></i></a></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if ($next_termine): ?>
<section class="fs-block" aria-labelledby="fsTermine">
    <h2 id="fsTermine">Vereinstermine</h2>
    <ul class="fs-termine">
        <?php foreach ($next_termine as $nt): $t = strtotime($nt['date']); ?>
        <li>
            <span class="fs-termin-datum"><?php echo htmlspecialchars(mb_substr($fs_tage[(int) date('w', $t)], 0, 2) . ' ' . date('j.n.', $t)); ?></span>
            <span class="fs-termin-name"><?php echo htmlspecialchars($nt['name']); ?></span>
            <?php if (!empty($nt['time'])): ?><span class="fs-termin-zeit"><?php echo htmlspecialchars(substr($nt['time'], 0, 5)); ?></span><?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <a class="fs-mehr" href="termine.php">Alle Termine<i class="bi bi-chevron-right" aria-hidden="true"></i></a>
</section>
<?php endif; ?>

<?php if ($fs_galerie): ?>
<a class="fs-foto" href="anlaesse.php">
    <img src="../api/foto_serve.php?id=<?php echo (int) $fs_galerie['cover_id']; ?>&amp;size=medium" alt="" loading="lazy">
    <span class="fs-foto-text">
        <strong>Fotos vom <?php echo htmlspecialchars($fs_galerie['name'] . ' ' . $fs_galerie['jahr']); ?></strong>
        <span><?php echo (int) $fs_galerie['total']; ?> Fotos ansehen</span>
    </span>
</a>
<?php endif; ?>

<section class="fs-block" aria-labelledby="fsAlles">
    <h2 id="fsAlles">Resultate</h2>
    <ul class="fs-links">
        <li><a href="meine_jm.php"><i class="bi bi-bullseye" aria-hidden="true"></i><span>Jahresmeisterschaft</span><i class="bi bi-chevron-right fs-pfeil" aria-hidden="true"></i></a></li>
        <li><a href="meine_heim.php"><i class="bi bi-house-heart" aria-hidden="true"></i><span>Heimmeisterschaft</span><i class="bi bi-chevron-right fs-pfeil" aria-hidden="true"></i></a></li>
        <li><a href="meine_kanti.php"><i class="bi bi-shield" aria-hidden="true"></i><span>Kantonalstich</span><i class="bi bi-chevron-right fs-pfeil" aria-hidden="true"></i></a></li>
        <li><a href="meine_wanderpreise.php"><i class="bi bi-trophy" aria-hidden="true"></i><span>Wanderpreise</span><i class="bi bi-chevron-right fs-pfeil" aria-hidden="true"></i></a></li>
    </ul>
    <h2>Verein</h2>
    <ul class="fs-links">
        <li><a href="termine.php"><i class="bi bi-calendar3" aria-hidden="true"></i><span>Termine</span><i class="bi bi-chevron-right fs-pfeil" aria-hidden="true"></i></a></li>
        <li><a href="anlaesse.php"><i class="bi bi-images" aria-hidden="true"></i><span>Fotos</span><i class="bi bi-chevron-right fs-pfeil" aria-hidden="true"></i></a></li>
        <li><a href="meine_einsaetze.php"><i class="bi bi-person-badge" aria-hidden="true"></i><span>Meine Einsätze</span><i class="bi bi-chevron-right fs-pfeil" aria-hidden="true"></i></a></li>
        <li><a href="mein_fragebogen.php"><i class="bi bi-clipboard-check" aria-hidden="true"></i><span>Umfragen</span><i class="bi bi-chevron-right fs-pfeil" aria-hidden="true"></i></a></li>
        <li><a href="einsatzplaene.php"><i class="bi bi-calendar-check" aria-hidden="true"></i><span>Einsatzpläne</span><i class="bi bi-chevron-right fs-pfeil" aria-hidden="true"></i></a></li>
        <li><a href="protokolle.php"><i class="bi bi-file-text" aria-hidden="true"></i><span>Protokolle</span><i class="bi bi-chevron-right fs-pfeil" aria-hidden="true"></i></a></li>
        <li><a href="kalender_abo.php"><i class="bi bi-calendar-plus" aria-hidden="true"></i><span>Termine im Handy-Kalender</span><i class="bi bi-chevron-right fs-pfeil" aria-hidden="true"></i></a></li>
    </ul>
</section>
