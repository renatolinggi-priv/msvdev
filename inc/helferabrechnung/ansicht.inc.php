<?php
/**
 * inc/helferabrechnung/ansicht.inc.php – Ansicht «Abrechnung» im Einsatzplan-Editor (nur Pläne vom Typ schlossturm).
 *
 * Eingebunden von inc/einsatzplanung.php bei ?id=<plan>&ansicht=abrechnung (Umschalter Planung ↔ Abrechnung im
 * Seitenkopf). Erwartet: $plan, $a (ha_abrechnung() oder null), $haFehler, $okMit, $h. CSS: ansicht_css.inc.php.
 *   Tab Abrechnung   Matrix pro Verein, je Schicht, Kennzahlen (read-only; Live-Reload via abrechnung_fragment.php)
 *   Tab Ansätze      Pauschale je Schicht (pauschale_save.php)
 *   Tab Zeilen       manuelle Zeilen: Vor-/Nacharbeiten, OK-Funktionen, Nachträge (zeile_save/zeile_delete.php)
 *   Tab Detail       jede Position mit Stunden; OK, Stundenkorrektur, Bemerkung (slot_abr_save.php)
 * Schalter «OK-Einsätze mitzählen» (&ok=1) zählt Positionen mit OK-Kennzeichen zu den Einsätzen.
 */
?>
<div class="content-background">
<?php if ($haFehler): ?>
  <div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-2"></i><?= $h($haFehler) ?></div>
<?php else: ?>
  <div class="ha-kopf">
    <form method="get" class="d-flex align-items-center gap-2 mb-0">
      <input type="hidden" name="id" value="<?= (int)$plan['id'] ?>">
      <input type="hidden" name="ansicht" value="abrechnung">
      <div class="form-check form-switch ha-switch mb-0" data-tooltip="Positionen mit OK-Kennzeichen zu den Einsätzen zählen (Standard: nein – wie im Original-Einsatzplan)">
        <input class="form-check-input" type="checkbox" role="switch" id="haOk" name="ok" value="1" <?= $okMit ? 'checked' : '' ?> onchange="this.form.submit()">
        <label class="form-check-label" for="haOk">OK-Einsätze mitzählen</label>
      </div>
    </form>
    <span class="text-muted small"><?= count($plan['termine']) ?> Schichten · <?= count($a['zuteilungen']) ?> besetzte Positionen · <?= count($a['manuell']) ?> manuelle Zeilen</span>
    <div class="export-group-btns">
      <button type="button" class="btn btn-outline-info btn-sm js-ha-export" data-fmt="xlsx" data-tooltip="Arbeitsmappe «Helferstunden» mit allen Blättern und Formeln"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Excel</button>
      <button type="button" class="btn btn-outline-info btn-sm js-ha-export" data-fmt="pdf" data-tooltip="Abrechnungsblatt A4 quer"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>
    </div>
  </div>

  <ul class="nav nav-tabs" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabAbrechnung" type="button"><i class="bi bi-calculator me-1"></i>Abrechnung</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAnsaetze" type="button"><i class="bi bi-sliders me-1"></i>Ansätze <span class="badge bg-secondary ms-1"><?= count($plan['termine']) ?></span></button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabZeilen" type="button"><i class="bi bi-list-check me-1"></i>Manuelle Zeilen <span class="badge bg-secondary ms-1"><?= count($a['manuell']) ?></span></button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabDetail" type="button"><i class="bi bi-table me-1"></i>Detail <span class="badge bg-secondary ms-1"><?= count($a['zuteilungen']) ?></span></button></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade show active" id="tabAbrechnung" role="tabpanel"><div id="haAbrBody"><?php include __DIR__ . '/tab_abrechnung.inc.php'; ?></div></div>
    <div class="tab-pane fade" id="tabAnsaetze" role="tabpanel"><?php include __DIR__ . '/tab_ansaetze.inc.php'; ?></div>
    <div class="tab-pane fade" id="tabZeilen" role="tabpanel"><?php include __DIR__ . '/tab_zeilen.inc.php'; ?></div>
    <div class="tab-pane fade" id="tabDetail" role="tabpanel"><?php include __DIR__ . '/tab_detail.inc.php'; ?></div>
  </div>
<?php endif; ?>
</div>

<?php if (!$haFehler):
  // ---------- Slide-Panel: manuelle Zeile ----------
  $haOptAktiv = ''; $haOptInaktiv = '';
  foreach ($a['mitglieder'] as $m) {
      $o = '<option value="' . (int)$m['ID'] . '">' . $h(ep_name_vorname($m)) . '</option>';
      if ((int)$m['Status'] === 1 && (int)$m['Verstorben'] === 0) $haOptAktiv .= $o; else $haOptInaktiv .= $o;
  }
  ob_start(); ?>
  <input type="hidden" id="zId" value="0">
  <div class="mb-3">
    <label class="form-label small text-muted mb-1">Kategorie</label>
    <select class="form-select form-select-sm" id="zKategorie"><?php foreach (HA_KATEGORIEN as $k => $l): ?><option value="<?= $k ?>"><?= $h($l) ?></option><?php endforeach; ?></select>
  </div>
  <div class="mb-3">
    <label class="form-label small text-muted mb-1">Tätigkeit</label>
    <input type="text" class="form-control form-control-sm" id="zTaetigkeit" maxlength="150" list="zTaetigkeitListe" autocomplete="off">
    <datalist id="zTaetigkeitListe"><?php foreach (HA_TAETIGKEITEN as $liste) foreach ($liste as $t): ?><option value="<?= $h($t) ?>"><?php endforeach; ?></datalist>
  </div>
  <div class="mb-3">
    <label class="form-label small text-muted mb-1">Verein</label>
    <select class="form-select form-select-sm" id="zVerein"><?php foreach (EP_VEREINE as $k => $l): ?><option value="<?= $k ?>"><?= $h($l) ?></option><?php endforeach; ?></select>
  </div>
  <div class="mb-3">
    <label class="form-label small text-muted mb-1">Person</label>
    <select class="form-select form-select-sm mb-1" id="zMitglied"><option value="0">– Mitglied wählen –</option><optgroup label="Aktive"><?= $haOptAktiv ?></optgroup><?php if ($haOptInaktiv): ?><optgroup label="Inaktive"><?= $haOptInaktiv ?></optgroup><?php endif; ?></select>
    <input type="text" class="form-control form-control-sm" id="zName" maxlength="100" placeholder="oder Name Vorname (anderer Verein / extern)" autocomplete="off">
    <div class="form-text">Mitglied nur bei MSV Wilen; bei Fremdvereinen gilt der Klartext.</div>
  </div>
  <div class="mb-3">
    <label class="form-label small text-muted mb-1">Stunden</label>
    <input type="number" class="form-control form-control-sm" id="zStunden" step="0.25" min="0" max="9999.99" value="0">
  </div>
  <div class="mb-3">
    <label class="form-label small text-muted mb-1">Bemerkung</label>
    <input type="text" class="form-control form-control-sm" id="zBemerkung" maxlength="255" placeholder="z.B. 2025: Schuler Werner">
  </div>
  <?php $panel_body = ob_get_clean();
  $panel_id = 'zeilePanel'; $panel_overlay_id = 'zeileOverlay'; $panel_close_id = 'zeileClose'; $panel_width = '420px';
  $panel_title = '<i class="bi bi-list-check me-2"></i><span id="zeilePanelTitel">Zeile</span>';
  $panel_footer = '<div class="d-flex gap-2"><button type="button" class="btn btn-outline-primary btn-sm" id="zeileSave"><i class="bi bi-check-lg me-1"></i>Speichern</button><button type="button" class="btn btn-outline-secondary btn-sm" id="zeileCancel">Abbrechen</button></div>';
  include __DIR__ . '/../partials/side_panel.inc.php';

  // ---------- Slide-Panel: Position (Abrechnungsfelder) ----------
  ob_start(); ?>
  <input type="hidden" id="sSlot" value="0">
  <div class="mb-3 small text-muted" id="sInfo"></div>
  <div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" role="switch" id="sOk">
    <label class="form-check-label" for="sOk">OK-Position (Organisationskomitee)</label>
    <div class="form-text">Zählt nur mit dem Schalter «OK-Einsätze mitzählen». Im Original-Einsatzplan steht «OK» statt «x».</div>
    <div class="form-text text-warning" id="sOkFn" style="display:none"><i class="bi bi-info-circle me-1"></i>Die Funktion dieser Position hat die Rolle «OK» – alle ihre Positionen zählen als OK (Funktionen-Dialog im Editor).</div>
  </div>
  <div class="mb-3">
    <label class="form-label small text-muted mb-1">Stundenkorrektur</label>
    <input type="number" class="form-control form-control-sm" id="sKorrektur" step="0.25" min="0" max="99.99" placeholder="leer = Pauschale der Schicht">
    <div class="form-text">Überschreibt die Pauschale nur für diese Position (z.B. 2.50 bei halbem Einsatz).</div>
  </div>
  <div class="mb-3">
    <label class="form-label small text-muted mb-1">Bemerkung</label>
    <input type="text" class="form-control form-control-sm" id="sBemerkung" maxlength="100">
  </div>
  <div class="text-muted small">Verein, Person und Anwesenheit: Ansicht «Planung» dieses Plans.</div>
  <?php $panel_body = ob_get_clean();
  $panel_id = 'slotPanel'; $panel_overlay_id = 'slotOverlay'; $panel_close_id = 'slotClose'; $panel_width = '400px';
  $panel_title = '<i class="bi bi-person-badge me-2"></i><span id="slotPanelTitel">Position</span>';
  $panel_footer = '<div class="d-flex gap-2"><button type="button" class="btn btn-outline-primary btn-sm" id="slotSave"><i class="bi bi-check-lg me-1"></i>Speichern</button><button type="button" class="btn btn-outline-secondary btn-sm" id="slotCancel">Abbrechen</button></div>';
  include __DIR__ . '/../partials/side_panel.inc.php';
?>
<script>
$(function () {
  const haPath = (/\/inc(\/|$)/.test(location.pathname)) ? 'helferabrechnung/' : 'inc/helferabrechnung/';
  const CSRF = document.getElementById('csrfToken').value;
  const PLAN_ID = <?= (int)$plan['id'] ?>;
  const OK = <?= $okMit ? 1 : 0 ?>;
  const post = (file, data, ok, failMsg) => msvPost(haPath + file, Object.assign({ plan_id: PLAN_ID }, data || {}), ok, { csrf: CSRF, failMsg });

  // ---------- Tab über Reload retten (wie dokumente_verwaltung.php) ----------
  function activeTabHash() { const a = document.querySelector('.nav-tabs .nav-link.active'); return a ? a.getAttribute('data-bs-target') : ''; }
  function reloadKeepTab(delay) { const hash = activeTabHash(); setTimeout(() => { if (hash) location.hash = hash; location.reload(); }, delay || 400); }
  if (location.hash) { const t = document.querySelector('[data-bs-target="' + location.hash + '"]'); if (t) new bootstrap.Tab(t).show(); }

  // ---------- Abrechnung neu laden (HTML-Fragment, gleiche Darstellung wie die Seite) ----------
  function refreshAbrechnung() {
    $.get(haPath + 'abrechnung_fragment.php', { plan_id: PLAN_ID, ok: OK })
      .done(html => { $('#haAbrBody').html(html); })   // Tooltips sind delegiert (msv-tooltips.js)
      .fail(xhr => msvToast(msvXhrMessage(xhr, 'Abrechnung konnte nicht aktualisiert werden'), 'error'));
  }

  // ---------- Export ----------
  $(document).on('click', '.js-ha-export', function () {
    const $b = $(this), orig = $b.html();
    $b.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.getJSON(haPath + 'export_' + $b.data('fmt') + '.php', { plan_id: PLAN_ID, ok: OK })
      .done(r => { if (r && r.success && r.link) { window.open(haPath + r.link, '_blank'); msvToast('Datei erstellt', 'success'); } else msvToast((r && r.message) || 'Export fehlgeschlagen', 'error'); })
      .fail(xhr => msvToast(msvXhrMessage(xhr, 'Export fehlgeschlagen'), 'error'))
      .always(() => $b.prop('disabled', false).html(orig));
  });

  // ---------- Ansätze: Pauschale je Schicht (Auto-Save beim Verlassen) ----------
  function pauschaleSpeichern($tr, wert) {
    post('pauschale_save.php', { termin_id: $tr.data('termin'), pauschale_std: wert }, r => {
      $tr.find('.ha-ansatz').text(Number(r.ansatz).toFixed(2));
      $tr.find('.ha-pauschale').val(r.pauschale_std === null ? '' : Number(r.pauschale_std).toFixed(2));
      $tr.addClass('row-saved'); setTimeout(() => $tr.removeClass('row-saved'), 1200);
      msvToast(r.message, 'success');
      refreshAbrechnung();
    }, 'Pauschale konnte nicht gespeichert werden');
  }
  $('#haAnsaetze').on('change', '.ha-pauschale', function () { pauschaleSpeichern($(this).closest('tr'), this.value); });
  $('#haAnsaetze').on('click', '.ha-vorschlag', function () { const $tr = $(this).closest('tr'), vs = $tr.attr('data-vorschlag'); pauschaleSpeichern($tr, vs); $(this).replaceWith('<span class="text-muted">' + msvEsc(vs) + '</span>'); });
  $('#haVorschlaegeAlle').on('click', function () {
    const $offen = $('#haAnsaetze tr[data-termin]').filter(function () { return $(this).find('.ha-pauschale').val() === ''; });
    if (!$offen.length) { msvToast('Alle Schichten haben bereits eine Pauschale', 'info'); return; }
    $offen.each(function () { $(this).find('.ha-vorschlag').trigger('click'); });
  });

  // ---------- Panels ----------
  const Panel = {
    open(id, overlay) { $('#' + id).addClass('open'); $('#' + overlay).addClass('show'); },
    close(id, overlay) { $('#' + id).removeClass('open'); $('#' + overlay).removeClass('show'); $('.hybrid-row').removeClass('selected'); }
  };
  $(document).on('keydown', e => { if (e.key === 'Escape') { Panel.close('zeilePanel', 'zeileOverlay'); Panel.close('slotPanel', 'slotOverlay'); } });

  // ---------- Manuelle Zeilen ----------
  function zeileOeffnen(d) {
    $('#zId').val(d.id || 0); $('#zKategorie').val(d.kategorie || 'vorarbeit'); $('#zTaetigkeit').val(d.taetigkeit || '');
    $('#zVerein').val(d.verein || 'msv'); $('#zMitglied').val(d.mitgliedId || 0); $('#zName').val(d.nameText || '');
    $('#zStunden').val(d.stunden || 0); $('#zBemerkung').val(d.bemerkung || '');
    $('#zeilePanelTitel').text(d.id ? 'Zeile bearbeiten' : 'Neue Zeile');
    zeileVereinSync();
    Panel.open('zeilePanel', 'zeileOverlay');
    setTimeout(() => $('#zTaetigkeit').trigger('focus'), 250);
  }
  function zeileVereinSync() { const msv = $('#zVerein').val() === 'msv'; $('#zMitglied').prop('disabled', !msv); if (!msv) $('#zMitglied').val(0); }
  $('#zVerein').on('change', zeileVereinSync);
  $('#zMitglied').on('change', function () { if (Number(this.value) > 0) $('#zName').val(''); });
  $('#zName').on('input', function () { if (this.value.trim() !== '') $('#zMitglied').val(0); });
  $('.js-zeile-neu').on('click', () => zeileOeffnen({}));
  // OK-Funktionen (je 50 h) für die OK-Mitglieder des Plans vorschlagen (Vorschau → Bestätigung → Anlegen)
  $('.js-zeile-ok').on('click', function () {
    $.getJSON(haPath + 'zeile_ok_vorschlag.php', { plan_id: PLAN_ID }).done(r => {
      if (!r || !r.success) { msvToast((r && r.message) || 'Vorschau fehlgeschlagen', 'error'); return; }
      if (!r.vorschlag.length) { msvToast(r.vorhanden ? 'Alle OK-Mitglieder haben bereits eine OK-Funktion' : 'Keine OK-Mitglieder im Plan – im Editor unter «OK-Mitglieder» kennzeichnen', 'info'); return; }
      const liste = r.vorschlag.map(p => msvEsc(p.name) + ' (' + msvEsc(p.verein) + ')').join(', ');
      msvConfirm(r.vorschlag.length + ' OK-Funktion(en) à ' + Number(r.stunden).toFixed(2) + ' h anlegen für: ' + liste + '? Die Bezeichnung (Präsident, Kasse …) passt du danach in der Zeile an.', 'OK-Funktionen vorschlagen', 'Ja, anlegen')
        .then(res => { if (res && res.isConfirmed) post('zeile_ok_vorschlag.php', {}, rr => { msvToast(rr.message, 'success'); reloadKeepTab(600); }, 'Anlegen fehlgeschlagen'); });
    }).fail(xhr => msvToast(msvXhrMessage(xhr, 'Vorschau fehlgeschlagen'), 'error'));
  });
  // Vor-/Nacharbeiten und OK-Funktionen aus dem letzten Schlossturm-Plan übernehmen (Vorschau → Bestätigung → Kopie)
  $('.js-zeile-vorjahr').on('click', function () {
    const $b = $(this);
    $.getJSON(haPath + 'zeile_copy.php', { plan_id: PLAN_ID }).done(r => {
      if (!r || !r.success) { msvToast((r && r.message) || 'Vorschau fehlgeschlagen', 'error'); return; }
      if (!r.quelle) { msvToast('Kein früherer Schlossturm-Plan mit manuellen Zeilen gefunden', 'info'); return; }
      msvConfirm(r.quelle.anzahl + ' Zeile(n) aus «' + r.quelle.titel + '» (' + r.quelle.jahr + ') übernehmen? Stunden werden als Startwert kopiert, bereits vorhandene Zeilen (gleiche Tätigkeit und Person) übersprungen.', 'Aus Vorjahr übernehmen', 'Ja, übernehmen')
        .then(res => {
          if (!res || !res.isConfirmed) return;
          $b.prop('disabled', true);
          post('zeile_copy.php', { quelle_id: r.quelle.id }, rr => { msvToast(rr.message, 'success'); reloadKeepTab(600); }, 'Übernahme fehlgeschlagen');
          $b.prop('disabled', false);
        });
    }).fail(xhr => msvToast(msvXhrMessage(xhr, 'Vorschau fehlgeschlagen'), 'error'));
  });
  $(document).on('click', '.js-zeile', function (e) {
    if (e.target.closest('button')) return;
    $('.hybrid-row').removeClass('selected'); $(this).addClass('selected');
    zeileOeffnen(this.dataset);
  });
  $('#zeileSave').on('click', function () {
    post('zeile_save.php', {
      id: $('#zId').val(), kategorie: $('#zKategorie').val(), taetigkeit: $('#zTaetigkeit').val(), verein: $('#zVerein').val(),
      mitglied_id: $('#zMitglied').val(), name_text: $('#zName').val(), stunden: $('#zStunden').val(), bemerkung: $('#zBemerkung').val()
    }, r => { msvToast(r.message, 'success'); Panel.close('zeilePanel', 'zeileOverlay'); reloadKeepTab(300); }, 'Zeile konnte nicht gespeichert werden');
  });
  $('#zeileCancel, #zeileClose, #zeileOverlay').on('click', () => Panel.close('zeilePanel', 'zeileOverlay'));
  $(document).on('click', '.js-zeile-delete', function (e) {
    e.stopPropagation();
    const id = $(this).data('id'), label = $(this).data('label');
    msvConfirmDelete(label).then(res => {
      if (!res || !res.isConfirmed) return;
      post('zeile_delete.php', { id }, r => { msvToast(r.message, 'success'); reloadKeepTab(300); }, 'Zeile konnte nicht gelöscht werden');
    });
  });

  // ---------- Detail: Position ----------
  $(document).on('click', '.js-slot', function () {
    const d = this.dataset;
    $('.hybrid-row').removeClass('selected'); $(this).addClass('selected');
    $('#sSlot').val(d.slot); $('#sOk').prop('checked', d.ok === '1').prop('disabled', d.okfn === '1'); $('#sKorrektur').val(d.korrektur || ''); $('#sBemerkung').val(d.bemerkung || '');
    $('#sOkFn').toggle(d.okfn === '1');
    $('#slotPanelTitel').text(d.person); $('#sInfo').text(d.terminLabel + ' · ' + d.funktion);
    Panel.open('slotPanel', 'slotOverlay');
  });
  $('#slotSave').on('click', function () {
    post('slot_abr_save.php', { slot_id: $('#sSlot').val(), ok: $('#sOk').is(':checked') ? 1 : 0, stunden_korrektur: $('#sKorrektur').val(), bemerkung: $('#sBemerkung').val() },
      r => { msvToast(r.message, 'success'); Panel.close('slotPanel', 'slotOverlay'); reloadKeepTab(300); }, 'Position konnte nicht gespeichert werden');
  });
  $('#slotCancel, #slotClose, #slotOverlay').on('click', () => Panel.close('slotPanel', 'slotOverlay'));

  // ---------- Detail: Filter ----------
  function filterDetail() {
    const v = $('#haFVerein').val(), t = $('#haFTermin').val(), s = $('#haFStatus').val(), q = $('#haFSuche').val().trim().toLowerCase();
    let n = 0;
    $('#haDetail tr.js-slot').each(function () {
      const d = this.dataset; let ok = true;
      if (v && d.verein !== v) ok = false;
      if (t && d.termin !== t) ok = false;
      if (s === 'zaehlt' && d.grund !== '') ok = false;
      if (s === 'ok' && d.ok !== '1') ok = false;
      if (s === 'nicht_da' && d.anwesend !== '0') ok = false;
      if (s === 'offen' && d.anwesend !== '') ok = false;
      if (s === 'korrektur' && !d.korrektur) ok = false;
      if (s === 'bemerkung' && !d.bemerkung) ok = false;
      if (q && d.suche.indexOf(q) < 0) ok = false;
      $(this).toggle(ok); if (ok) n++;
    });
    $('#haFCount').text(n + ' von ' + $('#haDetail tr.js-slot').length + ' Positionen');
  }
  $('#haFVerein, #haFTermin, #haFStatus').on('change', filterDetail);
  $('#haFSuche').on('input', filterDetail);
  filterDetail();
});
</script>
<?php endif; ?>
