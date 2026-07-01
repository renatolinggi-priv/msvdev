<?php
// mitgliederfragebogen.php
include 'dbconnect.inc.php';

// Seiten-CSS ausgelagert nach css/mitgliederfragebogen.css (wird vom Header in <style> gewrappt)
$page_specific_css = @file_get_contents(__DIR__ . '/../css/mitgliederfragebogen.css') ?: '';

include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-auto ps-0" style="max-width: 100%;">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <?php $page_title = 'Auswertung Fragebogen'; include 'partials/page_header.inc.php'; ?>
                
                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <form id="fragebogenForm" class="fragebogen-form">
                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Jahr-Auswahl + Aktionen nebeneinander -->
                        <div class="d-flex flex-wrap gap-3 align-items-start mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                                <i class="bi bi-calendar3 me-1"></i>Jahr:
                            </label>
                            <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;"></select>
                        </div>

                        <!-- Aktionsbereich (Bootstrap Collapse) -->
<?php
                        $ac_id = 'fragebogenActions';
                        ob_start();
                        ?>
                                    <div class="row g-2 mb-2">
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                                                <i class="bi bi-save me-2"></i>Speichern
                                            </button>
                                        </div>
                                        <div class="col-12">
                                            <button id="delete-btn" type="button" class="btn btn-outline-danger btn-sm w-100">
                                                <i class="bi bi-trash me-1"></i>Löschen
                                            </button>
                                        </div>
                                    </div>
                                    <div class="border-top pt-2">
                                        <small class="text-muted d-block mb-2"><i class="bi bi-download me-1"></i>Exporte</small>
                                        <div class="row g-2">
                                            <div class="col-12">
                                                <button class="pdf-btn btn btn-outline-info btn-sm w-100">
                                                    <i class="bi bi-file-earmark-pdf me-1"></i>PDF exportieren
                                                </button>
                                            </div>
                                        </div>
                                        <div id="pdf-link" class="mt-2"></div>
                                    </div>
                        <?php
                        $ac_body = ob_get_clean();
                        include 'partials/action_card.inc.php';
                        ?>

                        </div><!-- Ende flex-row Jahr+Aktionen -->

                        <!-- Filter: Nicht-Teilnehmer -->
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <button type="button" id="toggleNichtTeilnehmer" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-eye me-1"></i>Nicht-Teilnehmer anzeigen <span id="nntCount" class="badge bg-secondary ms-1">0</span>
                            </button>
                        </div>

                        <!-- Tabelle -->
                        <div class="table-wrapper">
                            <h5 class="table-title">
                                <i class="bi bi-table me-2"></i>
                                Teilnahme-Übersicht
                            </h5>

                            <!-- Desktop: Tabelle -->
                            <div class="desktop-table-container">
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0" id="fragebogenTabelle">
                                        <thead>
                                            <tr>
                                                <td colspan="100%" class="text-center">
                                                    <div class="loading-spinner">
                                                        <div class="spinner-border-custom me-3"></div>
                                                        Lade Fragebogen...
                                                    </div>
                                                </td>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Dynamisch per AJAX -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Mobile: Card-Ansicht -->
                            <div class="mobile-cards-container" id="fragebogenMobileCards">
                                <div class="mobile-cards-scroll">
                                    <div class="mobile-cards-loading">
                                        <div class="spinner-border text-secondary" role="status">
                                            <span class="visually-hidden">Laden...</span>
                                        </div>
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


<script>
    $(document).ready(function () {
        let currentYear = new Date().getFullYear();
        let basePath = ''; // Falls du einen Pfadprefix hast
        let nntVisible = false; // Nicht-Teilnehmer sichtbar?

        // Nicht-Teilnehmer filtern/anzeigen
        function applyNntFilter() {
            const $btn = $('#toggleNichtTeilnehmer');
            // Desktop: Zeilen mit data-nimmt-nicht-teil oder Waffe-Dropdown = 0
            let nntCount = 0;
            $('#fragebogenTabelle tbody tr').each(function () {
                const $row = $(this);
                const waffeVal = $row.find('select[name*="[waffenID]"]').val();
                if (waffeVal === '0') {
                    nntCount++;
                    $row.toggle(nntVisible);
                }
            });
            // Mobile: Cards mit data-nimmt-nicht-teil oder Waffe-Dropdown = 0
            $('#fragebogenMobileCards .mobile-card').each(function () {
                const $card = $(this);
                const waffeVal = $card.find('.mobile-fb-select[data-field="waffenID"]').val();
                if (waffeVal === '0') {
                    $card.toggle(nntVisible);
                }
            });
            // Button-Text + Badge aktualisieren
            $('#nntCount').text(nntCount);
            if (nntVisible) {
                $btn.html('<i class="bi bi-eye-slash me-1"></i>Nicht-Teilnehmer ausblenden <span id="nntCount" class="badge bg-secondary ms-1">' + nntCount + '</span>');
                $btn.removeClass('btn-outline-secondary').addClass('btn-secondary');
            } else {
                $btn.html('<i class="bi bi-eye me-1"></i>Nicht-Teilnehmer anzeigen <span id="nntCount" class="badge bg-secondary ms-1">' + nntCount + '</span>');
                $btn.removeClass('btn-secondary').addClass('btn-outline-secondary');
            }
        }

        // Toggle-Button Handler
        $('#toggleNichtTeilnehmer').on('click', function () {
            nntVisible = !nntVisible;
            applyNntFilter();
        });

        // Funktion: Aktualisiert die Hintergrundfarbe der Teilnahme-Dropdowns
        function updateSelectColorForParticipation(el) {
            let val = $(el).val();
            if (val === 'teil') {
                $(el).css('background-color', '#d4edda'); // leicht grün
            } else if (val === 'nicht') {
                $(el).css('background-color', '#f8d7da'); // leicht rot
            } else if (val === 'evtl') {
                $(el).css('background-color', '#fff3cd'); // leicht gelb
            } else {
                $(el).css('background-color', '');
            }
        }

        // Funktion: Aktualisiert die Hintergrundfarbe der erweiterten Dropdowns (Ja/Nein)
        function updateSelectColorForErweitert(el) {
            let val = $(el).val();
            if (val === 'ja') {
                $(el).css('background-color', '#d4edda'); // leicht grün
            } else if (val === 'nein') {
                $(el).css('background-color', '#f8d7da'); // leicht rot
            } else {
                $(el).css('background-color', '');
            }
        }

        // 1) Jahr-Dropdown initialisieren
        function initializeYearDropdown() {
            let yearSelect = $('#yearSelect');
            for (let y = currentYear; y >= currentYear - 3; y--) {
                let option = $('<option></option>').val(y).text(y);
                if (y === currentYear) {
                    option.prop('selected', true);
                }
                yearSelect.append(option);
            }
        }

        // 2) Formular via Ajax laden
        function loadFragebogen(year) {
            // Loading State anzeigen
            $('#fragebogenTabelle thead').html(`
            <tr>
                <td colspan="100%" class="text-center">
                    <div class="loading-spinner">
                        <div class="spinner-border-custom me-3"></div>
                        Lade Fragebogen für ${year}...
                    </div>
                </td>
            </tr>
        `);
            $('#fragebogenTabelle tbody').empty();
            MSVMobileCards.showLoading('#fragebogenMobileCards');

            $.ajax({
                url: basePath + 'fragebogen/load_fragebogen_form.php',
                type: 'GET',
                cache: false,
                data: { year: year },
                dataType: 'json',
                success: function (response) {
                    if (response.thead && response.tbody) {
                        $('#fragebogenTabelle thead').html(response.thead);
                        $('#fragebogenTabelle tbody').html(response.tbody);

                        // Setze Hintergrundfarbe für alle Teilnahme-Dropdowns (Tabelle)
                        $('select[name*="[mannschaft]"], select[name*="[gruppen]"]').each(function () {
                            updateSelectColorForParticipation(this);
                        });
                        // Setze Hintergrundfarbe für alle erweiterten Dropdowns (Tabelle)
                        $('select[name*="[erweitert]"]').each(function () {
                            updateSelectColorForErweitert(this);
                        });

                        // Mobile Cards befüllen
                        if (response.mobile_cards) {
                            $('#fragebogenMobileCards').html(response.mobile_cards);
                            // Farben für Mobile-Selects
                            $('#fragebogenMobileCards .mobile-fb-select[data-field="mannschaft"],' +
                              '#fragebogenMobileCards .mobile-fb-select[data-field="gruppen"]').each(function () {
                                updateSelectColorForParticipation(this);
                            });
                            $('#fragebogenMobileCards .mobile-fb-select[data-field="erweitert"]').each(function () {
                                updateSelectColorForErweitert(this);
                            });
                        }

                        // Nicht-Teilnehmer filtern
                        nntVisible = false;
                        applyNntFilter();

                        msvToast('Fragebogen erfolgreich geladen', 'success');
                    } else {
                        $('#fragebogenTabelle thead').html('<tr><th>Keine Daten verfügbar</th></tr>');
                        $('#fragebogenTabelle tbody').html('<tr><td>Keine Daten für dieses Jahr gefunden</td></tr>');
                        MSVMobileCards.showError('#fragebogenMobileCards', 'Keine Daten für dieses Jahr');
                        msvToast('Keine Daten für dieses Jahr gefunden', 'warning');
                    }
                },
                error: function (xhr, status, error) {
                    $('#fragebogenTabelle thead').html('<tr><th class="text-danger">Fehler beim Laden</th></tr>');
                    $('#fragebogenTabelle tbody').html('<tr><td class="text-danger">Fehler beim Laden der Daten</td></tr>');
                    MSVMobileCards.showError('#fragebogenMobileCards');
                    msvToast("Fehler beim Laden des Fragebogens: " + error, 'error');
                }
            });
        }

        // Helper: Badge im Mobile-Card-Header aktualisieren
        function updateMobileBadge(card, field, val) {
            const isParticipation = (field === 'mannschaft' || field === 'gruppen');
            const badgeClass = isParticipation
                ? (val === 'teil' ? 'bg-success' : (val === 'evtl' ? 'bg-warning text-dark' : 'bg-danger'))
                : (val === 'ja'   ? 'bg-success' : 'bg-danger');

            if (field === 'mannschaft') {
                const text = val === 'teil' ? 'MM ✓' : (val === 'evtl' ? 'MM ?' : 'MM ✗');
                card.find('.fb-badge-mannschaft')
                    .removeClass('bg-success bg-warning bg-danger text-dark')
                    .addClass(badgeClass).text(text);
            } else if (field === 'gruppen') {
                const text = val === 'teil' ? 'GM ✓' : (val === 'evtl' ? 'GM ?' : 'GM ✗');
                card.find('.fb-badge-gruppen')
                    .removeClass('bg-success bg-warning bg-danger text-dark')
                    .addClass(badgeClass).text(text);
            }
        }

        // 3) JahrDropdown-Change
        $('#yearSelect').on('change', function () {
            let selectedYear = $(this).val();
            loadFragebogen(selectedYear);
        });

        // Initialisierung
        initializeYearDropdown();
        loadFragebogen(currentYear);

        // 4a) Event-Listener: Waffe-Dropdown → Nicht-Teilnehmer-Filter aktualisieren
        $(document).on('change', 'select[name*="[waffenID]"]', function () {
            applyNntFilter();
        });

        // 4) Event-Listener: Bei Änderung der Teilnahmefelder Hintergrundfarbe aktualisieren
        $(document).on('change', 'select[name*="[mannschaft]"], select[name*="[gruppen]"]', function () {
            updateSelectColorForParticipation(this);
        });

        // 5) Event-Listener: Bei Änderung der erweiterten Felder (Ja/Nein) Hintergrundfarbe aktualisieren
        $(document).on('change', 'select[name*="[erweitert]"]', function () {
            updateSelectColorForErweitert(this);
        });

        // 5b) Sync: Mobile-Card-Select → versteckte Tabellen-Selects (für Formular-Submit)
        $(document).on('change', '.mobile-fb-select', function () {
            const $sel  = $(this);
            const mid   = $sel.data('mid');
            const field = $sel.data('field');
            const val   = $sel.val();
            const $card = $sel.closest('.mobile-card');

            if (field === 'waffenID') {
                $('select[name="fragebogen[' + mid + '][waffenID]"]').val(val);
                applyNntFilter();
            } else if (field === 'mannschaft') {
                $('select[name="fragebogen[' + mid + '][mannschaft]"]').val(val);
                updateSelectColorForParticipation(this);
                updateMobileBadge($card, 'mannschaft', val);
            } else if (field === 'gruppen') {
                $('select[name="fragebogen[' + mid + '][gruppen]"]').val(val);
                updateSelectColorForParticipation(this);
                updateMobileBadge($card, 'gruppen', val);
            } else if (field === 'erweitert') {
                const defid = $sel.data('defid');
                $('select[name="fragebogen[' + mid + '][erweitert][' + defid + ']"]').val(val);
                updateSelectColorForErweitert(this);
            }
        });

        // 6) Formular absenden => Speichern
        $('#fragebogenForm').on('submit', function (e) {
            e.preventDefault();

            let $submitBtn = $(this).find('button[type="submit"]');
            let originalText = $submitBtn.html();
            $submitBtn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

            let selectedYear = $('#yearSelect').val();
            let formData = $(this).serialize();
            formData += '&year=' + selectedYear;

            $.ajax({
                url: basePath + 'fragebogen/save_fragebogen.php',
                type: 'POST',
                data: formData,
                success: function (response) {
                    msvToast("Fragebogen erfolgreich gespeichert!", 'success');
                    loadFragebogen(selectedYear); // Tabelle neu laden
                },
                error: function (xhr, status, error) {
                    msvToast("Fehler beim Speichern des Fragebogens!", 'error');
                },
                complete: function () {
                    $submitBtn.prop('disabled', false).html(originalText);
                }
            });
        });

        // 7) PDF-Generierung
        $('.pdf-btn').on('click', function (e) {
            e.preventDefault();

            let $btn = $(this);
            let originalText = $btn.html();
            $btn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere PDF...');

            var selectedYear = $('#yearSelect').val();
            $.ajax({
                url: 'fragebogen/generate_pdf.php',
                type: 'GET',
                dataType: 'json',
                data: { year: selectedYear },
                success: function (response) {
                    if (response.pdf_link) {
                        $('#pdf-link').html(`
                        <a href="${response.pdf_link}" target="_blank">
                            <i class="bi bi-download"></i>
                            PDF herunterladen (${selectedYear})
                        </a>
                    `);
                        msvToast('PDF erfolgreich generiert!', 'success');
                    } else {
                        msvToast('PDF konnte nicht generiert werden.', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    msvToast('Fehler beim Generieren des PDFs: ' + error, 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        });

        // 8) Event-Listener für den "Löschen" Button (Slide-Panel-Standard: msvConfirmDelete)
        $('#delete-btn').on('click', async function (e) {
            e.preventDefault();
            const year = $('#yearSelect').val();
            const result = await msvConfirmDelete('alle Fragebogen-Daten für ' + year);
            if (!result.isConfirmed) return;

            let $btn = $(this);
            let originalText = $btn.html();
            $btn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Lösche...');

            $.ajax({
                url: 'fragebogen/delete_fragebogen.php',
                method: 'POST',
                data: {
                    year: year,
                    csrf_token: $('input[name="csrf_token"]').val()
                },
                success: function (response) {
                    msvToast('Alle Einträge erfolgreich gelöscht', 'success');
                    loadFragebogen(year); // Ergebnisse neu laden
                },
                error: function (xhr, status, error) {
                    msvToast('Fehler beim Löschen der Einträge', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        });

        // Legacy Message-Funktion für Kompatibilität (falls noch verwendet)
        function showMessage(msg, type) {
            // Konvertiere alte Bootstrap-Klassen zu neuen Toast-Typen
            let toastType = type;
            if (type === 'danger') toastType = 'error';
            msvToast(msg, toastType);
        }

        // Global verfügbar machen für Legacy-Code
        window.showMessage = showMessage;
    });
</script>

<?php
include 'footer.inc.php';
?>