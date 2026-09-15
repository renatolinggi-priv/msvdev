<?php
// mitgliederfragebogen.php – Auswertung Fragebogen (Waffe, Mannschaft, Gruppen, erweiterte Fragen pro Mitglied)
include 'dbconnect.inc.php';

// Seiten-CSS ausgelagert nach css/mitgliederfragebogen.css (wird vom Header in <style> gewrappt)
$page_specific_css = @file_get_contents(__DIR__ . '/../css/mitgliederfragebogen.css') ?: '';

include 'header.inc.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 ps-0">
            <div class="main-content-wrapper content-width-wide">
                <?php $page_title = 'Auswertung Fragebogen'; include 'partials/page_header.inc.php'; ?>

                <div class="content-background">
                    <form id="fragebogenForm" class="fragebogen-form">
                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Jahr + Speichern (Primäraktion sichtbar) + Exporte -->
                        <div class="export-toolbar mb-3">
                            <div class="export-toolbar-head">
                                <label for="yearSelect" class="export-year-label mb-0"><i class="bi bi-calendar3 me-1"></i>Jahr:</label>
                                <select id="yearSelect" class="form-select form-select-sm export-year-select"></select>
                                <span class="export-toolbar-divider" aria-hidden="true"></span>
                                <i class="bi bi-file-earmark-arrow-down"></i>
                                <span>Dokumente erstellen</span>
                                <button type="submit" class="btn btn-outline-primary btn-sm ms-auto" id="btnSave">
                                    <i class="bi bi-save me-1"></i>Speichern
                                </button>
                            </div>
                            <div class="export-group-btns">
                                <button type="button" class="pdf-btn btn btn-outline-info btn-sm">
                                    <i class="bi bi-file-earmark-pdf me-1"></i><span>Fragebogen PDF</span>
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm msv-druck"
                                        data-druck-doctype="fragebogen" data-druck-label="Fragebogen" data-druck-linkprefix=""
                                        aria-label="Fragebogen direkt drucken"><i class="bi bi-printer"></i></button>
                            </div>
                            <div id="pdf-link" class="mt-2"></div>
                        </div>

                        <!-- Filter + weitere Aktionen -->
                        <div class="d-flex flex-wrap align-items-start gap-3 mb-3">
                            <button type="button" id="toggleNichtTeilnehmer" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-eye me-1"></i><span class="nnt-text">Nicht-Teilnehmer anzeigen</span>
                                <span id="nntCount" class="badge bg-secondary ms-1">0</span>
                            </button>
                            <?php
                            $ac_id = 'fragebogenActions';
                            ob_start(); ?>
                                <div class="row g-2">
                                    <div class="col-12">
                                        <button id="delete-btn" type="button" class="btn btn-outline-danger btn-sm w-100">
                                            <i class="bi bi-trash me-1"></i>Alle Antworten des Jahres löschen
                                        </button>
                                    </div>
                                </div>
                            <?php
                            $ac_body = ob_get_clean();
                            include 'partials/action_card.inc.php';
                            ?>
                        </div>

                        <!-- Tabelle -->
                        <div class="table-wrapper">
                            <div class="desktop-table-container">
                                <div class="table-responsive">
                                    <table class="table mb-0" id="fragebogenTabelle">
                                        <thead>
                                            <tr><th colspan="6" class="text-center fw-normal"><div class="loading-spinner"><div class="spinner-border spinner-border-sm me-2"></div>Lade Fragebogen...</div></th></tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Mobile: Card-Ansicht -->
                            <div class="mobile-cards-container" id="fragebogenMobileCards">
                                <div class="mobile-cards-scroll">
                                    <div class="mobile-cards-loading">
                                        <div class="spinner-border text-secondary" role="status"><span class="visually-hidden">Laden...</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'partials/direktdruck_scripts.inc.php'; ?>

<script>
$(function () {
    const currentYear = new Date().getFullYear();
    let nntVisible = false; // Nicht-Teilnehmer sichtbar?

    // Farbzustand eines Selects als Klasse setzen (CSS: .fb-teil/.fb-nicht/.fb-evtl/.fb-ja/.fb-nein)
    function paintSelect(el) {
        const v = $(el).val();
        $(el).removeClass('fb-teil fb-nicht fb-evtl fb-ja fb-nein');
        if (['teil', 'nicht', 'evtl', 'ja', 'nein'].includes(v)) $(el).addClass('fb-' + v);
    }
    function paintAll() {
        $('#fragebogenTabelle select[name*="[mannschaft]"], #fragebogenTabelle select[name*="[gruppen]"], #fragebogenTabelle select[name*="[erweitert]"], ' +
          '#fragebogenMobileCards .mobile-fb-select[data-field="mannschaft"], #fragebogenMobileCards .mobile-fb-select[data-field="gruppen"], #fragebogenMobileCards .mobile-fb-select[data-field="erweitert"]')
            .each(function () { paintSelect(this); });
    }

    // Nicht-Teilnehmer (Waffe = "Nehme nicht teil") ein-/ausblenden
    function applyNntFilter() {
        let nntCount = 0;
        $('#fragebogenTabelle tbody tr').each(function () {
            if ($(this).find('select[name*="[waffenID]"]').val() === '0') { nntCount++; $(this).toggle(nntVisible); }
        });
        $('#fragebogenMobileCards .mobile-card').each(function () {
            if ($(this).find('.mobile-fb-select[data-field="waffenID"]').val() === '0') $(this).toggle(nntVisible);
        });
        const $btn = $('#toggleNichtTeilnehmer');
        $('#nntCount').text(nntCount);
        $btn.find('.nnt-text').text(nntVisible ? 'Nicht-Teilnehmer ausblenden' : 'Nicht-Teilnehmer anzeigen');
        $btn.find('i').attr('class', nntVisible ? 'bi bi-eye-slash me-1' : 'bi bi-eye me-1');
        $btn.toggleClass('btn-secondary', nntVisible).toggleClass('btn-outline-secondary', !nntVisible);
    }
    $('#toggleNichtTeilnehmer').on('click', function () { nntVisible = !nntVisible; applyNntFilter(); });

    // Jahr-Dropdown
    (function () {
        const $y = $('#yearSelect');
        for (let y = currentYear + 1; y >= currentYear - 3; y--) {
            $y.append($('<option></option>').val(y).text(y).prop('selected', y === currentYear));
        }
    })();

    function ajaxMsg(xhr, fallback) { return msvXhrMessage(xhr, fallback); } // zentral in msv-toast.js

    // Formular laden
    function loadFragebogen(year) {
        $('#fragebogenTabelle thead').html('<tr><th colspan="6" class="text-center fw-normal"><div class="loading-spinner"><div class="spinner-border spinner-border-sm me-2"></div>Lade Fragebogen für ' + $('<i>').text(year).html() + '...</div></th></tr>');
        $('#fragebogenTabelle tbody').empty();
        MSVMobileCards.showLoading('#fragebogenMobileCards');

        $.getJSON('fragebogen/load_fragebogen_form.php', { year, _: Date.now() })
            .done(function (response) {
                if (response && response.success && response.thead && response.tbody) {
                    $('#fragebogenTabelle thead').html(response.thead);
                    $('#fragebogenTabelle tbody').html(response.tbody);
                    if (response.mobile_cards) $('#fragebogenMobileCards').html(response.mobile_cards);
                    paintAll();
                    nntVisible = false;
                    applyNntFilter();
                } else {
                    $('#fragebogenTabelle thead').html('');
                    $('#fragebogenTabelle tbody').html('<tr class="msv-empty-row"><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox d-block mb-2" style="font-size:1.6rem;opacity:.5;"></i>Keine Mitglieder gefunden</td></tr>');
                    MSVMobileCards.showError('#fragebogenMobileCards', 'Keine Mitglieder gefunden');
                }
            })
            .fail(function (xhr) {
                $('#fragebogenTabelle thead').html('');
                $('#fragebogenTabelle tbody').html('<tr><td colspan="6" class="text-center text-danger py-3"><i class="bi bi-exclamation-triangle me-2"></i>Fehler beim Laden</td></tr>');
                MSVMobileCards.showError('#fragebogenMobileCards');
                msvToast(ajaxMsg(xhr, 'Fehler beim Laden des Fragebogens'), 'error');
            });
    }

    // Badge im Mobile-Card-Header aktualisieren
    function updateMobileBadge($card, field, val) {
        const cls = val === 'teil' ? 'bg-success' : (val === 'evtl' ? 'bg-warning text-dark' : 'bg-danger');
        const prefix = field === 'mannschaft' ? 'MM' : 'GM';
        const text = prefix + (val === 'teil' ? ' ✓' : (val === 'evtl' ? ' ?' : ' ✗'));
        $card.find('.fb-badge-' + field).removeClass('bg-success bg-warning bg-danger text-dark').addClass(cls).text(text);
    }

    $('#yearSelect').on('change', function () { loadFragebogen(this.value); });
    loadFragebogen(currentYear);

    // Tabelle: Änderungen
    $(document).on('change', '#fragebogenTabelle select[name*="[waffenID]"]', applyNntFilter);
    $(document).on('change', '#fragebogenTabelle select[name*="[mannschaft]"], #fragebogenTabelle select[name*="[gruppen]"], #fragebogenTabelle select[name*="[erweitert]"]', function () { paintSelect(this); });

    // Mobile-Card-Select -> Tabellen-Select (Formular-Submit liest nur die Tabelle)
    $(document).on('change', '.mobile-fb-select', function () {
        const $sel = $(this), mid = $sel.data('mid'), field = $sel.data('field'), val = $sel.val();
        const $card = $sel.closest('.mobile-card');
        if (field === 'waffenID') {
            $('select[name="fragebogen[' + mid + '][waffenID]"]').val(val);
            applyNntFilter();
        } else if (field === 'erweitert') {
            $('select[name="fragebogen[' + mid + '][erweitert][' + $sel.data('defid') + ']"]').val(val).each(function () { paintSelect(this); });
            paintSelect(this);
        } else {
            $('select[name="fragebogen[' + mid + '][' + field + ']"]').val(val).each(function () { paintSelect(this); });
            paintSelect(this);
            updateMobileBadge($card, field, val);
        }
    });

    // Speichern
    $('#fragebogenForm').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#btnSave'), orig = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');
        const year = $('#yearSelect').val();

        $.post('fragebogen/save_fragebogen.php', $(this).serialize() + '&year=' + encodeURIComponent(year), null, 'json')
            .done(function (r) {
                if (r && r.success) { msvToast('Fragebogen gespeichert', 'success'); loadFragebogen(year); }
                else msvToast((r && r.message) || 'Fehler beim Speichern', 'error');
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Speichern des Fragebogens'), 'error'))
            .always(() => $btn.prop('disabled', false).html(orig));
    });

    // PDF
    $('.pdf-btn').on('click', function () {
        const $btn = $(this), orig = $btn.html(), year = $('#yearSelect').val();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere PDF...');
        $.getJSON('fragebogen/generate_pdf.php', { year })
            .done(function (r) {
                if (r && r.success && r.pdf_link) {
                    $('#pdf-link').html('<a href="' + r.pdf_link + '" target="_blank" class="btn btn-outline-info btn-sm"><i class="bi bi-download me-1"></i>PDF herunterladen (' + year + ')</a>');
                    msvToast('PDF erstellt', 'success');
                } else msvToast((r && r.message) || 'PDF konnte nicht generiert werden', 'error');
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Generieren des PDFs'), 'error'))
            .always(() => $btn.prop('disabled', false).html(orig));
    });

    // Direktdruck (Profil «fragebogen»): Generator liefert JSON mit pdf_link relativ zu inc/
    if (typeof MsvDruck !== 'undefined') {
        MsvDruck.resolve('fragebogen', () => ({
            url: 'fragebogen/generate_pdf.php?year=' + encodeURIComponent($('#yearSelect').val()),
            jobName: 'Fragebogen ' + $('#yearSelect').val(),
            orientation: 'landscape'
        }));
    }

    // Alle Antworten eines Jahres löschen
    $('#delete-btn').on('click', async function () {
        const year = $('#yearSelect').val();
        const result = await msvConfirmDelete('alle Fragebogen-Antworten für ' + year);
        if (!result.isConfirmed) return;
        const $btn = $(this), orig = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Lösche...');
        $.post('fragebogen/delete_fragebogen.php', { year, csrf_token: $('input[name="csrf_token"]').val() }, null, 'json')
            .done(function (r) {
                if (r && r.success) { msvToast((r.count || 0) + ' Antworten gelöscht', 'success'); loadFragebogen(year); }
                else msvToast((r && r.message) || 'Fehler beim Löschen', 'error');
            })
            .fail(xhr => msvToast(ajaxMsg(xhr, 'Fehler beim Löschen der Einträge'), 'error'))
            .always(() => $btn.prop('disabled', false).html(orig));
    });
});
</script>

<?php include 'footer.inc.php'; ?>
