<?php
// portal/meine_einsaetze.php — Alle Arbeitseinsaetze des Mitglieds
$portal_page_title = 'Meine Einsätze';
require_once __DIR__ . '/../inc/dbconnect.inc.php';
require_once __DIR__ . '/../auth.php';
requireLogin();
$db = getDB();

$mitglied_id = $_SESSION['mitglied_id'] ?? null;
$einsaetze_kommend = [];
$einsaetze_vergangen = [];
$table_exists = true;
$csrf = ensureCsrfToken();

// Offene Tausch-/Übernahme-Anfragen (an mich = eingehend, von mir = ausgehend)
$tausch_eingehend = [];
$tausch_ausgehend = [];

if ($mitglied_id) {
    try {
        $stmt = $db->prepare("
            SELECT id, bezeichnung, event_datum, event_zeit, funktion, typ, jahr
            FROM einsatz_zuweisungen
            WHERE mitglied_id = ?
            ORDER BY event_datum ASC
        ");
        $stmt->execute([$mitglied_id]);
        $alle = $stmt->fetchAll();

        $today = date('Y-m-d');
        foreach ($alle as $e) {
            if ($e['event_datum'] >= $today) {
                $einsaetze_kommend[] = $e;
            } else {
                $einsaetze_vergangen[] = $e;
            }
        }
        // Vergangene: neueste zuerst
        $einsaetze_vergangen = array_reverse($einsaetze_vergangen);
    } catch (Exception $e) {
        $table_exists = false;
    }

    // Tausch-Anfragen laden (Tabelle existiert evtl. noch nicht -> still ignorieren)
    try {
        $te = $db->prepare("
            SELECT t.id, t.typ, t.nachricht, t.von_mitglied_id,
                   vm.Vorname AS p_vorname, vm.Name AS p_name,
                   ea.bezeichnung AS a_bez, ea.event_datum AS a_datum, ea.event_zeit AS a_zeit, ea.funktion AS a_funktion,
                   eb.bezeichnung AS b_bez, eb.event_datum AS b_datum, eb.event_zeit AS b_zeit, eb.funktion AS b_funktion
              FROM einsatz_tausch t
              JOIN mitglieder vm ON vm.ID = t.von_mitglied_id
              LEFT JOIN einsatz_zuweisungen ea ON ea.id = t.einsatz_a_id
              LEFT JOIN einsatz_zuweisungen eb ON eb.id = t.einsatz_b_id
             WHERE t.an_mitglied_id = :me AND t.status = 'offen'
             ORDER BY t.erstellt_am DESC
        ");
        $te->execute([':me' => $mitglied_id]);
        $tausch_eingehend = $te->fetchAll();

        $ta = $db->prepare("
            SELECT t.id, t.typ, t.nachricht, t.an_mitglied_id,
                   am.Vorname AS p_vorname, am.Name AS p_name,
                   ea.bezeichnung AS a_bez, ea.event_datum AS a_datum, ea.event_zeit AS a_zeit, ea.funktion AS a_funktion,
                   eb.bezeichnung AS b_bez, eb.event_datum AS b_datum, eb.event_zeit AS b_zeit, eb.funktion AS b_funktion
              FROM einsatz_tausch t
              JOIN mitglieder am ON am.ID = t.an_mitglied_id
              LEFT JOIN einsatz_zuweisungen ea ON ea.id = t.einsatz_a_id
              LEFT JOIN einsatz_zuweisungen eb ON eb.id = t.einsatz_b_id
             WHERE t.von_mitglied_id = :me AND t.status = 'offen'
             ORDER BY t.erstellt_am DESC
        ");
        $ta->execute([':me' => $mitglied_id]);
        $tausch_ausgehend = $ta->fetchAll();
    } catch (Exception $e) {
        // Migration 034 evtl. noch nicht eingespielt -> keine Tausch-Funktion anzeigen
    }
}

$weekdays = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
$months   = ['', 'Jan.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sep.', 'Okt.', 'Nov.', 'Dez.'];

function einsatzDatum(string $datum, array $weekdays, array $months): string {
    $dt = new DateTime($datum);
    return $weekdays[$dt->format('w')] . ', ' . $dt->format('j') . '. ' . $months[(int)$dt->format('n')] . ' ' . $dt->format('Y');
}

// Meta-Zeile (Datum · Zeit · Funktion) für eine Tausch-Anfrage formatieren.
$tauschMeta = function (?string $datum, ?string $zeit, ?string $funktion) use ($weekdays, $months): string {
    $parts = [];
    if (!empty($datum)) $parts[] = einsatzDatum($datum, $weekdays, $months);
    if (!empty($zeit))  $parts[] = $zeit;
    if (!empty($funktion)) $parts[] = $funktion;
    return htmlspecialchars(implode(' · ', $parts));
};

include 'portal_header.php';
?>

<style>
/* Vergangen-Variante: gedaempfter Look auf .p-list-row */
.p-list-row.vergangen {
    background: #fafafa;
    border-color: #ebebeb;
    opacity: 0.7;
}
.einsatz-datum {
    font-size: 0.78rem;
    color: #718096;
    margin-bottom: 0.1rem;
}
.p-list-row .einsatz-name {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.p-list-row.vergangen .einsatz-name {
    color: #718096;
}
.einsatz-funktion {
    font-size: 0.78rem;
    color: #718096;
    margin-top: 0.1rem;
}
.einsatz-time-badge {
    font-size: 0.72rem;
    background: #f0f0f0;
    color: #555;
    border-radius: 4px;
    padding: 0.1rem 0.4rem;
    flex-shrink: 0;
    white-space: nowrap;
}
.p-list-row:not(.vergangen) .einsatz-time-badge {
    background: #fff3e0;
    color: #e65100;
}
.naechster-badge {
    font-size: 0.65rem;
    font-weight: 700;
    background: #e65100;
    color: white;
    border-radius: 3px;
    padding: 0.1rem 0.35rem;
    margin-left: 0.4rem;
    vertical-align: middle;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.empty-state {
    text-align: center;
    padding: 2.5rem 1rem;
    color: #a0aec0;
}
.empty-state i {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
    display: block;
}
.summary-bar {
    background: linear-gradient(135deg, #fff8e1, #ffecb3);
    border: 1px solid #ffe082;
    border-radius: 0.65rem;
    padding: 0.6rem 1rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}
.summary-bar .stat {
    font-size: 0.82rem;
    color: #5d4037;
}
.summary-bar .stat strong {
    font-weight: 700;
    color: #e65100;
}
.vergangene-toggle {
    background: none;
    border: none;
    color: #718096;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.vergangene-toggle:hover { color: #4a5568; }

/* Tausch-/Übernahme-Anfragen */
.tausch-section { margin-bottom: 1.25rem; }
.tausch-card {
    border: 1px solid #ffe082;
    background: linear-gradient(135deg, #fff8e1, #fff3e0);
    border-radius: 0.65rem;
    padding: 0.7rem 0.85rem;
    margin-bottom: 0.6rem;
}
.tausch-card.ausgehend { border-color: #cfd8e3; background: #f7f9fc; }
.tausch-card-head {
    display: flex; align-items: center; gap: 0.4rem;
    font-size: 0.82rem; font-weight: 700; color: #bf6000; margin-bottom: 0.4rem;
}
.tausch-card.ausgehend .tausch-card-head { color: #5a6b80; }
.tausch-line { font-size: 0.86rem; margin: 0.15rem 0; }
.tausch-tag {
    display: inline-block; font-size: 0.62rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.03em; border-radius: 3px;
    padding: 0.08rem 0.32rem; margin-right: 0.35rem; vertical-align: middle;
}
.tausch-tag.du-bekommst { background: #e8f5e9; color: #2e7d32; }
.tausch-tag.du-gibst    { background: #ffebee; color: #c62828; }
.tausch-meta { color: #718096; font-size: 0.76rem; display: block; margin-top: 0.05rem; }
.tausch-nachricht { font-size: 0.78rem; color: #5d4037; margin-top: 0.35rem; }
.tausch-card-actions { display: flex; gap: 0.5rem; margin-top: 0.6rem; }
.einsatz-tausch-btn { flex-shrink: 0; align-self: center; }
.tausch-typ-hint { font-size: 0.76rem; color: #718096; }
</style>

<div class="portal-page-header">
    <h1><i class="bi bi-person-badge me-2"></i>Meine Einsätze</h1>
    <p class="subtitle">Deine Arbeitseinsätze im Verein</p>
</div>

<?php if (!empty($tausch_eingehend) || !empty($tausch_ausgehend)): ?>
<div class="tausch-section">
    <?php if (!empty($tausch_eingehend)): ?>
    <div class="p-eyebrow"><i class="bi bi-inbox me-1"></i>Anfragen an dich</div>
    <?php foreach ($tausch_eingehend as $r): $name = trim($r['p_vorname'] . ' ' . $r['p_name']); ?>
    <div class="tausch-card eingehend">
        <div class="tausch-card-head">
            <i class="bi bi-arrow-left-right"></i>
            <span><?php echo htmlspecialchars($name); ?> <?php echo $r['typ'] === 'tausch' ? 'möchte mit dir tauschen' : 'bittet dich um eine Übernahme'; ?></span>
        </div>
        <div class="tausch-card-body">
            <div class="tausch-line">
                <span class="tausch-tag du-bekommst">Du übernimmst</span>
                <strong><?php echo htmlspecialchars($r['a_bez'] ?? '—'); ?></strong>
                <span class="tausch-meta"><?php echo $tauschMeta($r['a_datum'] ?? null, $r['a_zeit'] ?? null, $r['a_funktion'] ?? null); ?></span>
            </div>
            <?php if ($r['typ'] === 'tausch' && !empty($r['b_bez'])): ?>
            <div class="tausch-line">
                <span class="tausch-tag du-gibst">Du gibst ab</span>
                <strong><?php echo htmlspecialchars($r['b_bez']); ?></strong>
                <span class="tausch-meta"><?php echo $tauschMeta($r['b_datum'] ?? null, $r['b_zeit'] ?? null, $r['b_funktion'] ?? null); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($r['nachricht'])): ?>
            <div class="tausch-nachricht"><i class="bi bi-chat-left-text me-1"></i><?php echo htmlspecialchars($r['nachricht']); ?></div>
            <?php endif; ?>
        </div>
        <div class="tausch-card-actions">
            <button class="btn btn-success btn-sm tausch-accept" data-id="<?php echo (int) $r['id']; ?>"><i class="bi bi-check-lg me-1"></i>Akzeptieren</button>
            <button class="btn btn-outline-danger btn-sm tausch-decline" data-id="<?php echo (int) $r['id']; ?>"><i class="bi bi-x-lg me-1"></i>Ablehnen</button>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($tausch_ausgehend)): ?>
    <div class="p-eyebrow"><i class="bi bi-send me-1"></i>Deine Anfragen</div>
    <?php foreach ($tausch_ausgehend as $r): $name = trim($r['p_vorname'] . ' ' . $r['p_name']); ?>
    <div class="tausch-card ausgehend">
        <div class="tausch-card-head">
            <i class="bi bi-hourglass-split"></i>
            <span>Wartet auf <?php echo htmlspecialchars($name); ?></span>
        </div>
        <div class="tausch-card-body">
            <div class="tausch-line">
                <span class="tausch-tag du-gibst"><?php echo $r['typ'] === 'tausch' ? 'Du gibst ab' : 'Wird übernommen'; ?></span>
                <strong><?php echo htmlspecialchars($r['a_bez'] ?? '—'); ?></strong>
                <span class="tausch-meta"><?php echo $tauschMeta($r['a_datum'] ?? null, $r['a_zeit'] ?? null, $r['a_funktion'] ?? null); ?></span>
            </div>
            <?php if ($r['typ'] === 'tausch' && !empty($r['b_bez'])): ?>
            <div class="tausch-line">
                <span class="tausch-tag du-bekommst">Du übernimmst</span>
                <strong><?php echo htmlspecialchars($r['b_bez']); ?></strong>
                <span class="tausch-meta"><?php echo $tauschMeta($r['b_datum'] ?? null, $r['b_zeit'] ?? null, $r['b_funktion'] ?? null); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <div class="tausch-card-actions">
            <button class="btn btn-outline-secondary btn-sm tausch-withdraw" data-id="<?php echo (int) $r['id']; ?>"><i class="bi bi-arrow-counterclockwise me-1"></i>Zurückziehen</button>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$total = count($einsaetze_kommend) + count($einsaetze_vergangen);
if (!$mitglied_id): ?>
    <div class="empty-state">
        <i class="bi bi-person-x"></i>
        <div>Dein Konto ist keinem Vereinsmitglied zugeordnet.<br>Bitte kontaktiere den Administrator.</div>
    </div>
<?php elseif (!$table_exists): ?>
    <div class="empty-state">
        <i class="bi bi-calendar-x"></i>
        <div>Einsätze sind noch nicht konfiguriert.</div>
    </div>
<?php elseif ($total === 0): ?>
    <div class="empty-state">
        <i class="bi bi-calendar-check"></i>
        <div>Es sind noch keine Einsätze für dich erfasst.</div>
    </div>
<?php else: ?>

    <!-- Summary -->
    <div class="summary-bar">
        <?php if (count($einsaetze_kommend) > 0): ?>
        <span class="stat"><strong><?php echo count($einsaetze_kommend); ?></strong> kommende<?php echo count($einsaetze_kommend) === 1 ? 'r' : ''; ?> Einsatz<?php echo count($einsaetze_kommend) !== 1 ? 'ätze' : ''; ?></span>
        <?php endif; ?>
        <?php if (count($einsaetze_vergangen) > 0): ?>
        <span class="stat" style="color:#a0907a"><strong><?php echo count($einsaetze_vergangen); ?></strong> vergangene<?php echo count($einsaetze_vergangen) !== 1 ? '' : 'r'; ?></span>
        <?php endif; ?>
    </div>

    <!-- Kommende Einsaetze -->
    <?php if (!empty($einsaetze_kommend)): ?>
    <div class="p-eyebrow">Kommend</div>
    <?php if (array_filter($einsaetze_kommend, fn($e) => !empty($e['event_zeit']))): ?>
    <div class="alert alert-info py-2 px-3 small mb-2" role="alert">
        <i class="bi bi-info-circle me-1"></i>
        Bitte <strong>30 Minuten vor Arbeitsbeginn</strong> vor Ort erscheinen.
    </div>
    <?php endif; ?>
    <div class="p-list">
    <?php foreach ($einsaetze_kommend as $i => $e): ?>
    <div class="p-list-row">
        <div class="p-chip lg orange"><i class="bi bi-person-badge"></i></div>
        <div class="p-list-body">
            <div class="einsatz-datum">
                <?php echo einsatzDatum($e['event_datum'], $weekdays, $months); ?>
                <?php if ($i === 0): ?><span class="naechster-badge">Nächster</span><?php endif; ?>
            </div>
            <div class="p-list-title einsatz-name"><?php echo htmlspecialchars($e['bezeichnung']); ?></div>
            <?php if (!empty($e['funktion'])): ?>
            <div class="einsatz-funktion"><i class="bi bi-wrench me-1"></i><?php echo htmlspecialchars($e['funktion']); ?></div>
            <?php endif; ?>
        </div>
        <?php if (!empty($e['event_zeit'])): ?>
        <div class="einsatz-time-badge"><i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($e['event_zeit']); ?></div>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-outline-primary einsatz-tausch-btn"
                data-id="<?php echo (int) $e['id']; ?>"
                data-bez="<?php echo htmlspecialchars($e['bezeichnung'], ENT_QUOTES); ?>"
                data-datum="<?php echo htmlspecialchars(einsatzDatum($e['event_datum'], $weekdays, $months), ENT_QUOTES); ?>"
                data-tooltip="Abtauschen / übergeben" aria-label="Einsatz abtauschen">
            <i class="bi bi-arrow-left-right"></i>
        </button>
    </div>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="p-eyebrow">Kommend</div>
    <div class="empty-state" style="padding: 1.5rem; color: #a0aec0; font-size: 0.85rem; text-align: center;">
        <i class="bi bi-check-circle" style="font-size: 1.5rem; display: block; margin-bottom: 0.4rem;"></i>
        Keine kommenden Einsätze geplant.
    </div>
    <?php endif; ?>

    <!-- Vergangene Einsaetze -->
    <?php if (!empty($einsaetze_vergangen)): ?>
    <div class="p-eyebrow d-flex align-items-center justify-content-between">
        <span>Vergangen</span>
        <button class="vergangene-toggle" onclick="toggleVergangene(this)" data-open="0">
            <i class="bi bi-chevron-down"></i> Anzeigen
        </button>
    </div>
    <div id="vergangene-list" class="p-list" style="display:none;">
    <?php foreach ($einsaetze_vergangen as $e): ?>
    <div class="p-list-row vergangen">
        <div class="p-chip lg gray"><i class="bi bi-person-badge"></i></div>
        <div class="p-list-body">
            <div class="einsatz-datum"><?php echo einsatzDatum($e['event_datum'], $weekdays, $months); ?></div>
            <div class="p-list-title einsatz-name"><?php echo htmlspecialchars($e['bezeichnung']); ?></div>
            <?php if (!empty($e['funktion'])): ?>
            <div class="einsatz-funktion"><i class="bi bi-wrench me-1"></i><?php echo htmlspecialchars($e['funktion']); ?></div>
            <?php endif; ?>
        </div>
        <?php if (!empty($e['event_zeit'])): ?>
        <div class="einsatz-time-badge"><?php echo htmlspecialchars($e['event_zeit']); ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php endif; ?>

<script>
function toggleVergangene(btn) {
    const list = document.getElementById('vergangene-list');
    const open = btn.dataset.open === '1';
    if (open) {
        list.style.display = 'none';
        btn.innerHTML = '<i class="bi bi-chevron-down"></i> Anzeigen';
        btn.dataset.open = '0';
    } else {
        list.style.display = 'block';
        btn.innerHTML = '<i class="bi bi-chevron-up"></i> Ausblenden';
        btn.dataset.open = '1';
    }
}
</script>

<?php if ($mitglied_id): ?>
<!-- Abtausch-Modal -->
<div class="modal fade" id="tauschModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Einsatz abtauschen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schliessen"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Dein Einsatz: <strong id="tauschEinsatzLabel"></strong></p>
                <input type="hidden" id="tauschEinsatzA">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Art</label>
                    <div class="btn-group w-100" role="group" aria-label="Art des Abtauschs">
                        <input type="radio" class="btn-check" name="tauschTyp" id="tauschTypU" value="uebernahme" checked>
                        <label class="btn btn-outline-primary btn-sm" for="tauschTypU"><i class="bi bi-person-check me-1"></i>Übernahme</label>
                        <input type="radio" class="btn-check" name="tauschTyp" id="tauschTypT" value="tausch">
                        <label class="btn btn-outline-primary btn-sm" for="tauschTypT"><i class="bi bi-arrow-left-right me-1"></i>Tausch</label>
                    </div>
                    <div class="tausch-typ-hint mt-1" id="tauschTypHint">Jemand übernimmt deinen Einsatz – du gibst nichts zurück.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" for="tauschPartner" id="tauschPartnerLabel">Wer übernimmt?</label>
                    <select class="form-select" id="tauschPartner">
                        <option value="">– Mitglied wählen –</option>
                    </select>
                </div>

                <div class="mb-3 d-none" id="tauschGegenWrap">
                    <label class="form-label fw-semibold" for="tauschGegen">Dessen Einsatz, den du übernimmst</label>
                    <select class="form-select" id="tauschGegen">
                        <option value="">– zuerst Mitglied wählen –</option>
                    </select>
                    <div class="tausch-typ-hint mt-1" id="tauschGegenHint"></div>
                </div>

                <div class="mb-1">
                    <label class="form-label fw-semibold" for="tauschNachricht">Nachricht (optional)</label>
                    <textarea class="form-control" id="tauschNachricht" rows="2" maxlength="500" placeholder="z.B. Grund oder Absprache"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary btn-sm" id="tauschSenden"><i class="bi bi-send me-1"></i>Anfrage senden</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var TAUSCH_API  = '../api/einsatz_tausch.php';
    var TAUSCH_CSRF = <?php echo json_encode($csrf); ?>;
    var partnersLoaded = false;

    function toast(m, t) { if (typeof msvToast === 'function') msvToast(m, t || 'info'); }
    function el(id) { return document.getElementById(id); }
    function modal() { return bootstrap.Modal.getOrCreateInstance(el('tauschModal')); }

    function tauschPost(params) {
        params.csrf_token = TAUSCH_CSRF;
        return fetch(TAUSCH_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(params).toString()
        }).then(function (r) { return r.json(); });
    }
    function tauschGet(params) {
        return fetch(TAUSCH_API + '?' + new URLSearchParams(params).toString())
            .then(function (r) { return r.json(); });
    }

    // ---------- Anfrage erstellen (A) ----------
    function applyTauschTyp() {
        var isTausch = el('tauschTypT').checked;
        el('tauschGegenWrap').classList.toggle('d-none', !isTausch);
        el('tauschPartnerLabel').textContent = isTausch ? 'Mit wem tauschen?' : 'Wer übernimmt?';
        el('tauschTypHint').textContent = isTausch
            ? 'Ihr tauscht: du gibst deinen Einsatz ab und übernimmst im Gegenzug einen Einsatz des anderen Mitglieds (auch aus einem anderen Anlass).'
            : 'Jemand übernimmt deinen Einsatz – du gibst nichts zurück.';
        if (isTausch) loadPartnerEinsaetze();
    }

    function loadPartners() {
        if (partnersLoaded) return;
        tauschGet({ action: 'partner_kandidaten' }).then(function (d) {
            if (!d.success) return;
            var sel = el('tauschPartner');
            d.data.forEach(function (m) {
                var o = document.createElement('option');
                o.value = m.id;
                o.textContent = m.name + ' ' + m.vorname;
                sel.appendChild(o);
            });
            partnersLoaded = true;
        }).catch(function () {});
    }

    function loadPartnerEinsaetze() {
        var b = el('tauschPartner').value;
        var sel = el('tauschGegen');
        if (!b) {
            sel.innerHTML = '<option value="">– zuerst Mitglied wählen –</option>';
            el('tauschGegenHint').textContent = '';
            return;
        }
        sel.innerHTML = '<option value="">– lädt … –</option>';
        tauschGet({ action: 'partner_einsaetze', mitglied_id: b }).then(function (d) {
            sel.innerHTML = '';
            if (!d.success || !d.data.length) {
                sel.innerHTML = '<option value="">– keine kommenden Einsätze –</option>';
                el('tauschGegenHint').textContent = 'Dieses Mitglied hat keine kommenden Einsätze zum Tauschen.';
                return;
            }
            var opt0 = document.createElement('option');
            opt0.value = ''; opt0.textContent = '– Einsatz wählen –';
            sel.appendChild(opt0);
            d.data.forEach(function (e) {
                var o = document.createElement('option');
                o.value = e.id;
                var dt = '';
                try { if (e.event_datum) dt = new Date(e.event_datum).toLocaleDateString('de-CH'); } catch (x) {}
                o.textContent = e.bezeichnung + (dt ? ' · ' + dt : '') + (e.funktion ? ' · ' + e.funktion : '');
                sel.appendChild(o);
            });
            el('tauschGegenHint').textContent = '';
        }).catch(function () {
            sel.innerHTML = '<option value="">– Fehler beim Laden –</option>';
        });
    }

    function openTauschModal(btn) {
        el('tauschEinsatzA').value = btn.dataset.id;
        el('tauschEinsatzLabel').textContent = btn.dataset.bez + (btn.dataset.datum ? ' — ' + btn.dataset.datum : '');
        el('tauschTypU').checked = true;
        el('tauschPartner').value = '';
        el('tauschNachricht').value = '';
        el('tauschGegen').innerHTML = '<option value="">– zuerst Mitglied wählen –</option>';
        applyTauschTyp();
        loadPartners();
        modal().show();
    }

    el('tauschTypU').addEventListener('change', applyTauschTyp);
    el('tauschTypT').addEventListener('change', applyTauschTyp);
    el('tauschPartner').addEventListener('change', function () {
        if (el('tauschTypT').checked) loadPartnerEinsaetze();
    });

    el('tauschSenden').addEventListener('click', function () {
        var typ = el('tauschTypT').checked ? 'tausch' : 'uebernahme';
        var partner = el('tauschPartner').value;
        var gegen = el('tauschGegen').value;
        if (!partner) { toast('Bitte ein Mitglied wählen.', 'error'); return; }
        if (typ === 'tausch' && !gegen) { toast('Bitte den Gegen-Einsatz wählen.', 'error'); return; }
        var btn = this;
        btn.disabled = true;
        tauschPost({
            action: 'create',
            typ: typ,
            einsatz_a_id: el('tauschEinsatzA').value,
            an_mitglied_id: partner,
            einsatz_b_id: gegen || '',
            nachricht: el('tauschNachricht').value
        }).then(function (d) {
            toast(d.message || (d.success ? 'Gesendet' : 'Fehler'), d.success ? 'success' : 'error');
            if (d.success) { modal().hide(); setTimeout(function () { location.reload(); }, 800); }
        }).catch(function () { toast('Verbindungsfehler', 'error'); })
          .finally(function () { btn.disabled = false; });
    });

    // ---------- Entscheidungen (B akzeptiert/lehnt ab, A zieht zurück) ----------
    function decide(id, action, message, title, confirmText) {
        msvConfirm(message, title, confirmText).then(function (res) {
            if (!res || !res.isConfirmed) return;
            tauschPost({ action: action, id: id }).then(function (d) {
                toast(d.message || (d.success ? 'OK' : 'Fehler'), d.success ? 'success' : 'error');
                if (d.success) setTimeout(function () { location.reload(); }, 700);
            }).catch(function () { toast('Verbindungsfehler', 'error'); });
        });
    }

    document.addEventListener('click', function (ev) {
        var t;
        if ((t = ev.target.closest('.einsatz-tausch-btn'))) { openTauschModal(t); return; }
        if ((t = ev.target.closest('.tausch-accept')))      { decide(t.dataset.id, 'accept',   'Du übernimmst diesen Einsatz verbindlich.', 'Einsatz übernehmen', 'Ja, übernehmen'); return; }
        if ((t = ev.target.closest('.tausch-decline')))     { decide(t.dataset.id, 'decline',  'Möchtest du diese Anfrage ablehnen?', 'Anfrage ablehnen', 'Ablehnen'); return; }
        if ((t = ev.target.closest('.tausch-withdraw')))    { decide(t.dataset.id, 'withdraw', 'Möchtest du deine Anfrage zurückziehen?', 'Anfrage zurückziehen', 'Zurückziehen'); return; }
    });
})();
</script>
<?php endif; ?>

<?php include 'portal_footer.php'; ?>
