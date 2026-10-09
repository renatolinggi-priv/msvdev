<?php
// jsk_dashboard_fahne.inc.php – Startseite der Jungschützen «Vereinsfahne» (Vorschau, Okt 2026),
// gleicher Aufbau wie die Mitglieder-Startseite B (dashboard_fahne.inc.php): ein Verein, eine Startseite.
// Eingebunden von jsk_dashboard.php nach portal_header.php; nutzt dessen Daten ($db, $jsId, $jsUserId,
// $featureAktiv, $anfragen, $infoTitel, $infoText, $termine, $leiterAnzahl, $csrf_token).
// Aufbau: Begrüssung → Hinweis der Leitung → «Jetzt»-Liste (kommende Trainings mit Chat/Absagen, nächster
// Jungschützen-Termin, neue Nachrichten, Training anfragen) → frühere Anmeldungen → ruhige Kacheln.
if (!isset($db) || !($db instanceof PDO)) { http_response_code(404); exit; }

require_once __DIR__ . '/inc_termine.php';

$js_heute = date('Y-m-d');

// Vorname aus dem Jungschützen-Datensatz, sonst erstes Wort des Benutzernamens
$js_vorname = '';
if ($jsId > 0) {
    try {
        $st = $db->prepare('SELECT Vorname FROM jungschuetzen WHERE id = ?');
        $st->execute([$jsId]);
        $js_vorname = trim((string) $st->fetchColumn());
    } catch (Throwable $e) { $js_vorname = ''; }
}
if ($js_vorname === '') $js_vorname = explode(' ', trim((string) $portal_user_name))[0];

// Anmeldungen: kommende (sucht/hat Betreuer) gehören in die Jetzt-Liste, alles andere zugeklappt darunter
$js_kommend = [];
$js_frueher = [];
foreach ($anfragen as $a) {
    if ($a['datum'] >= $js_heute && in_array($a['status'], ['offen', 'vergeben'], true)) $js_kommend[] = $a;
    else $js_frueher[] = $a;
}

// Jetzt-Einträge nach Datum: kommende Trainings und der nächste Jungschützen-Termin
$js_eintraege = [];
foreach ($js_kommend as $a) $js_eintraege[] = ['datum' => $a['datum'], 'zeit' => (string) $a['zeit'], 'anfrage' => $a];
if (!empty($termine[0])) $js_eintraege[] = ['datum' => $termine[0]['date'], 'zeit' => (string) $termine[0]['time'], 'termin' => $termine[0]];
usort($js_eintraege, fn($x, $y) => [$x['datum'], $x['zeit']] <=> [$y['datum'], $y['zeit']]);

// Neue Nachrichten im Jungschützenchat
$js_ungelesen = 0;
try { $js_ungelesen = $jsUserId > 0 ? chatUnreadCount($db, $jsUserId) : 0; } catch (Throwable $e) { $js_ungelesen = 0; }

// Push: Themen der Jungschützen an, aber kein Gerät registriert → Hinweis (sonst kommt nichts an)
$js_push_noetig = false;
if ($jsUserId > 0) {
    try {
        $st = $db->prepare('SELECT push_aktiv, jsk_betreuung, chat FROM benachrichtigung_prefs WHERE user_id = ?');
        $st->execute([$jsUserId]);
        $p = $st->fetch();
        $themen_an = $p ? ((int) $p['push_aktiv'] === 1 && ((int) $p['jsk_betreuung'] || (int) $p['chat'])) : true;   // fehlende Zeile = alles an
        if ($themen_an) {
            $st = $db->prepare('SELECT COUNT(*) FROM push_abos WHERE benutzer_id = ?');
            $st->execute([$jsUserId]);
            $js_push_noetig = (int) $st->fetchColumn() === 0;
        }
    } catch (Throwable $e) { $js_push_noetig = false; }
}

$js_chip = function (string $ymd): string {
    $ts = strtotime($ymd);
    return '<b>' . (int) date('j', $ts) . '</b><small>' . htmlspecialchars(PT_MONAT_KURZ[(int) date('n', $ts)]) . '</small>';
};
$js_wann = fn(string $ymd, string $zeit): string => ptTagKurz($ymd, true) . (trim($zeit) !== '' ? ', ' . trim($zeit) : '');
$js_pfeil = '<i class="bi bi-chevron-right fs-pfeil" aria-hidden="true"></i>';
$js_gold = true;   // der erste datierte Eintrag ist «als Nächstes»
?>

<div class="fs-gruss">
    <div>
        <h1>Hallo <?php echo htmlspecialchars($js_vorname); ?></h1>
        <p><?php echo htmlspecialchars(ptTagLang($js_heute, false)); ?></p>
    </div>
</div>

<?php if (trim($infoTitel) !== '' || trim($infoText) !== ''): ?>
<div class="fs-hinweis">
    <?php if (trim($infoTitel) !== ''): ?><strong><?php echo htmlspecialchars($infoTitel); ?></strong><?php endif; ?>
    <?php if (trim($infoText) !== ''): ?><p><?php echo nl2br(htmlspecialchars($infoText)); ?></p><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($leiterAnzahl === 0): ?>
<div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>Derzeit ist kein Jungschützenleiter im Portal erreichbar – der Leitungs-Chat ist deshalb pausiert. Bitte wende dich direkt an die Leitung.</div>
<?php endif; ?>
<?php if (!$featureAktiv): ?>
<div class="alert alert-warning"><i class="bi bi-info-circle me-2" aria-hidden="true"></i>Die Jungschützen-Betreuung ist derzeit deaktiviert. Bitte später erneut versuchen.</div>
<?php elseif ($jsId <= 0): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>Dein Konto ist noch keinem Jungschützen zugeordnet. Bitte wende dich an den Jungschützenleiter.</div>
<?php endif; ?>

<ul class="fs-jetzt" aria-label="Was jetzt ansteht">
    <?php foreach ($js_eintraege as $e):
        $naehe = ptNaehe($e['datum']);
        $chipKlasse = 'fs-chip' . ($js_gold ? ' is-gold' : '');
        $js_gold = false;
        if (isset($e['anfrage'])):
            $a = $e['anfrage'];
            $datumDe = date('d.m.Y', strtotime($a['datum']));
            $hatBetreuer = $a['status'] === 'vergeben' && !empty($a['betreut_von_user_id']); ?>
    <li class="fs-zeile">
        <span class="<?php echo $chipKlasse; ?>" aria-hidden="true"><?php echo $js_chip($a['datum']); ?></span>
        <span class="fs-txt">
            <strong>Training<?php if ($naehe !== ''): ?> <span class="fs-naehe"><?php echo $naehe; ?></span><?php endif; ?></strong>
            <span class="fs-wann"><?php echo htmlspecialchars($js_wann($a['datum'], (string) $a['zeit'])); ?></span>
            <span class="fs-unter"><?php echo $hatBetreuer
                ? 'Betreuer: ' . htmlspecialchars($a['betreuer_name'] ?: 'gefunden')
                : 'Betreuer gesucht'; ?><?php if (!empty($a['bemerkung'])): ?> · <?php echo htmlspecialchars($a['bemerkung']); ?><?php endif; ?></span>
        </span>
        <span class="fs-aktionen">
            <?php if ($hatBetreuer):
                // Nur lesen; fehlt der Chat noch (Altbestand), legt chat.php?anfrage= ihn genau einmal an
                $matchConv = chatFindMatchConversation($db, $jsUserId, (int) $a['betreut_von_user_id']);
                $chatHref  = $matchConv > 0 ? 'chat.php?c=' . (int) $matchConv : 'chat.php?anfrage=' . (int) $a['id']; ?>
            <a href="<?php echo $chatHref; ?>" class="btn btn-sm btn-outline-primary" aria-label="Chat mit <?php echo htmlspecialchars($a['betreuer_name'] ?: 'dem Betreuer'); ?>"><i class="bi bi-chat-dots" aria-hidden="true"></i></a>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-danger js-cancel" data-id="<?php echo (int) $a['id']; ?>" aria-label="Training vom <?php echo $datumDe; ?> absagen"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </span>
    </li>
        <?php else: $t = $e['termin']; ?>
    <li><a href="jsk_termine.php">
        <span class="<?php echo $chipKlasse; ?>" aria-hidden="true"><?php echo $js_chip($t['date']); ?></span>
        <span class="fs-txt">
            <strong><?php echo htmlspecialchars($t['name']); ?><?php if ($naehe !== ''): ?> <span class="fs-naehe"><?php echo $naehe; ?></span><?php endif; ?></strong>
            <span class="fs-wann"><?php echo htmlspecialchars($js_wann($t['date'], (string) $t['time'])); ?> · Jungschützen-Termin</span>
        </span><?php echo $js_pfeil; ?></a></li>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($js_ungelesen > 0): ?>
    <li><a href="chat.php">
        <span class="fs-chip" aria-hidden="true"><i class="bi bi-chat-dots"></i></span>
        <span class="fs-txt"><strong><?php echo $js_ungelesen === 1 ? '1 neue Nachricht' : $js_ungelesen . ' neue Nachrichten'; ?></strong><span class="fs-wann">im Jungschützenchat</span></span><?php echo $js_pfeil; ?></a></li>
    <?php endif; ?>

    <?php if ($featureAktiv && $jsId > 0): ?>
    <li><a href="jsk_termin.php">
        <span class="fs-chip" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
        <span class="fs-txt"><strong>Training anfragen</strong><span class="fs-wann">Ein Vereinsmitglied begleitet dich</span></span><?php echo $js_pfeil; ?></a></li>
    <?php endif; ?>

    <?php if ($js_push_noetig): ?>
    <li><a href="benachrichtigungen.php">
        <span class="fs-chip" aria-hidden="true"><i class="bi bi-bell"></i></span>
        <span class="fs-txt"><strong>Benachrichtigungen einschalten</strong><span class="fs-wann">damit du erfährst, wenn dich jemand betreut</span></span><?php echo $js_pfeil; ?></a></li>
    <?php endif; ?>
</ul>

<?php if ($js_frueher):
    $js_status = ['offen' => 'nicht betreut', 'vergeben' => 'betreut', 'abgesagt' => 'abgesagt', 'erledigt' => 'erledigt']; ?>
<details class="fs-frueher">
    <summary>Frühere und abgesagte Anmeldungen (<?php echo count($js_frueher); ?>)</summary>
    <ul>
        <?php foreach ($js_frueher as $a): ?>
        <li><span><?php echo htmlspecialchars(ptTagKurz($a['datum'], true) . ' ' . date('Y', strtotime($a['datum']))); ?></span>
            <span><?php echo htmlspecialchars(($js_status[$a['status']] ?? $a['status']) . (!empty($a['betreuer_name']) && $a['status'] !== 'abgesagt' ? ' · ' . $a['betreuer_name'] : '')); ?></span></li>
        <?php endforeach; ?>
    </ul>
</details>
<?php endif; ?>

<h2 class="fs-bereiche-titel">Alles im Portal</h2>
<nav class="fs-kacheln" aria-label="Alle Bereiche">
    <a href="jsk_termin.php"><i class="bi bi-calendar-plus" aria-hidden="true"></i><span>Schiessanfrage</span></a>
    <a href="jsk_termine.php"><i class="bi bi-calendar3" aria-hidden="true"></i><span>Termine</span></a>
    <a href="jsk_resultate.php"><i class="bi bi-trophy" aria-hidden="true"></i><span>Meine Resultate</span></a>
    <a href="chat.php"><i class="bi bi-chat-dots" aria-hidden="true"></i><span>Chat</span></a>
    <a href="jsk_dokumente.php"><i class="bi bi-mortarboard" aria-hidden="true"></i><span>Dokumente</span></a>
    <a href="jsk_profil.php"><i class="bi bi-person-vcard" aria-hidden="true"></i><span>Meine Daten</span></a>
</nav>

<?php if (empty($portal_hide_pwa_install)) include __DIR__ . '/inc_pwa_install.php'; /* «Als App installieren» ganz unten, nur wenn noch nicht installiert */ ?>

<script>
(function () {
  var csrf = <?php echo json_encode($csrf_token); ?>;
  document.querySelectorAll('.js-cancel').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = this.getAttribute('data-id');
      msvConfirm('Dieses Training wirklich absagen?').then(function (r) {
        if (!r.isConfirmed) return;
        fetch('../api/jsk_anfrage.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
          body: JSON.stringify({ action: 'cancel', id: id, csrf_token: csrf })
        }).then(function (res) { return res.json(); }).then(function (data) {
          if (data.success) { msvToast(data.message, 'success'); setTimeout(function () { location.reload(); }, 700); }
          else msvToast(data.message || 'Das Training konnte nicht abgesagt werden. Bitte versuche es nochmals.', 'error');
        }).catch(function () { msvToast('Das Training konnte nicht abgesagt werden. Prüfe die Verbindung und versuche es nochmals.', 'error'); });
      });
    });
  });
})();
</script>
