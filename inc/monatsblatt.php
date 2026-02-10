<?php
// monatsblatt.php

include 'dbconnect.inc.php';

// Alle Styles sind jetzt zentral in msv-styles.css verwaltet
$page_specific_css = '';

include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-xl-8 col-lg-10 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-file-earmark-text me-2"></i>
                            Monatsblatt exportieren
                        </h2>
                    </div>
                </div>

                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <form id="pdfExportForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Jahr & Monat Auswahl -->
                        <div class="year-selection-card mb-3">
                            <div class="row align-items-end g-3">
                                <!-- Jahr -->
                                <div class="col-md-3 col-sm-6">
                                    <label for="exportYear" class="form-label fw-bold mb-1">
                                        <i class="bi bi-calendar3 me-1"></i> Jahr:
                                    </label>
                                    <select id="exportYear" name="year" class="form-select form-select-sm">
                                        <!-- Optionen werden dynamisch per JS eingefügt -->
                                    </select>
                                </div>

                                <!-- Start-Monat -->
                                <div class="col-md-3 col-sm-6">
                                    <label for="exportStartMonth" class="form-label fw-bold mb-1">
                                        <i class="bi bi-calendar-date me-1"></i> Von:
                                    </label>
                                    <select id="exportStartMonth" name="start_month" class="form-select form-select-sm">
                                        <option value="01">Januar</option>
                                        <option value="02">Februar</option>
                                        <option value="03">März</option>
                                        <option value="04">April</option>
                                        <option value="05">Mai</option>
                                        <option value="06">Juni</option>
                                        <option value="07">Juli</option>
                                        <option value="08">August</option>
                                        <option value="09">September</option>
                                        <option value="10">Oktober</option>
                                        <option value="11">November</option>
                                        <option value="12">Dezember</option>
                                    </select>
                                </div>

                                <!-- End-Monat -->
                                <div class="col-md-3 col-sm-6">
                                    <label for="exportEndMonth" class="form-label fw-bold mb-1">
                                        <i class="bi bi-calendar-check me-1"></i> Bis:
                                    </label>
                                    <select id="exportEndMonth" name="end_month" class="form-select form-select-sm">
                                        <option value="01">Januar</option>
                                        <option value="02">Februar</option>
                                        <option value="03">März</option>
                                        <option value="04">April</option>
                                        <option value="05">Mai</option>
                                        <option value="06">Juni</option>
                                        <option value="07">Juli</option>
                                        <option value="08">August</option>
                                        <option value="09">September</option>
                                        <option value="10">Oktober</option>
                                        <option value="11">November</option>
                                        <option value="12">Dezember</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Bemerkungsfeld -->
                        <div class="year-selection-card mb-3">
                            <label for="bemerkung" class="form-label fw-bold mb-1">
                                <i class="bi bi-chat-text me-1"></i> Bemerkungen:
                            </label>
                            <textarea id="bemerkung" name="bemerkung" rows="4" class="form-control form-control-sm"
                                      placeholder="Optional: Zusätzliche Bemerkungen für das Monatsblatt..."></textarea>
                        </div>

                        <!-- Button Toolbar -->
                        <div class="button-toolbar">
                            <div class="button-group">
                                <button type="submit" class="btn btn-compact-standard btn-outline-info">
                                    <i class="bi bi-file-earmark-pdf me-2"></i>
                                    PDF exportieren
                                </button>
                            </div>
                            <div id="pdf-link"></div>
                        </div>
                    </form>

                    <!-- Nachrichten Container -->
                    <div id="message"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {

    // 1) Initialisierung: Jahr-Dropdown
    function initializeExportYearDropdown() {
        const exportYear = $('#exportYear').empty();
        const currentYear = new Date().getFullYear();
        for (let year = 2024; year <= currentYear; year++) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) {
                option.prop('selected', true);
            }
            exportYear.append(option);
        }
    }
    initializeExportYearDropdown();

    // Standard-Werte setzen: aktueller Monat
    const currentMonth = new Date().getMonth() + 1;
    $('#exportStartMonth').val(currentMonth.toString().padStart(2, '0'));
    $('#exportEndMonth').val(currentMonth.toString().padStart(2, '0'));

    // 2) Export via Ajax
    $("#pdfExportForm").on("submit", function(e) {
        e.preventDefault();

        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere PDF...');

        var year = $("#exportYear").val();
        var startMonth = $("#exportStartMonth").val();
        var endMonth = $("#exportEndMonth").val();
        var bemerkung = $("#bemerkung").val();

        if (!year || !startMonth || !endMonth) {
            msvToast('Bitte alle Pflichtfelder ausfüllen', 'warning');
            $submitBtn.prop('disabled', false).html(originalText);
            return;
        }

        msvToast('PDF wird generiert...', 'info');

        $.ajax({
            url: 'monatsblatt/export_monatsblatt.php',
            method: 'GET',
            data: {
                year: year,
                start_month: startMonth,
                end_month: endMonth,
                bemerkung: bemerkung,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            dataType: 'json',
            success: function(response) {
                if (response.pdf_link) {
                    $('#pdf-link').html(
                        '<a href="monatsblatt/' + response.pdf_link + '" target="_blank" class="btn btn-compact-standard btn-outline-success">' +
                        '<i class="bi bi-download me-2"></i>Monatsblatt PDF herunterladen</a>'
                    );
                    msvToast('PDF erfolgreich generiert!', 'success');
                } else {
                    msvToast('PDF konnte nicht generiert werden.', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('PDF Export Error:', xhr.responseText);
                msvToast('Fehler beim Generieren des PDFs: ' + error, 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>

<?php
include 'footer.inc.php';
?>
