<?php
// monatsblatt.php

include 'dbconnect.inc.php';

// Alle Styles sind jetzt zentral in msv-styles.css verwaltet
$page_specific_css = '
<style>
/* Mobile Optimierung für Monatsblatt */
@media (max-width: 767.98px) {
    textarea.form-control {
        min-height: 120px !important;
        font-size: 16px !important;
    }

    .container-fluid {
        padding: 1rem !important;
    }
}
</style>
';

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
                <?php $page_title = 'Monatsblatt exportieren'; include 'partials/page_header.inc.php'; ?>

                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <form id="pdfExportForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Jahr & Monat Auswahl -->
                        <div class="mb-3">
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
                        <div class="mb-3">
                            <label for="bemerkung" class="form-label fw-bold mb-1">
                                <i class="bi bi-chat-text me-1"></i> Bemerkungen:
                            </label>
                            <textarea id="bemerkung" name="bemerkung" rows="4" class="form-control form-control-sm"
                                      placeholder="Optional: Zusätzliche Bemerkungen für das Monatsblatt..."></textarea>
                        </div>

                        <!-- Button Toolbar -->
                        <div class="button-toolbar">
                            <div class="button-group">
                                <button type="submit" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-file-earmark-pdf me-2"></i>
                                    PDF exportieren
                                </button>
                            </div>
                            <div id="pdf-link"></div>
                        </div>
                    </form>

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
        for (let year = currentYear; year >= currentYear - 3; year--) {
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

    // 2) Export: PDF direkt generieren und herunterladen (fetch -> Blob -> Download)
    $("#pdfExportForm").on("submit", async function(e) {
        e.preventDefault();

        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.html();

        var year = $("#exportYear").val();
        var startMonth = $("#exportStartMonth").val();
        var endMonth = $("#exportEndMonth").val();
        var bemerkung = $("#bemerkung").val();

        if (!year || !startMonth || !endMonth) {
            msvToast('Bitte alle Pflichtfelder ausfüllen', 'warning');
            return;
        }

        $submitBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Generiere PDF...');

        try {
            const params = new URLSearchParams({
                year: year,
                start_month: startMonth,
                end_month: endMonth,
                bemerkung: bemerkung,
                csrf_token: $('input[name="csrf_token"]').val()
            });
            const response = await fetch('monatsblatt/export_monatsblatt.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            });
            if (!response.ok) throw new Error((await response.text()) || 'Fehler beim Generieren');

            const blob = await response.blob();
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `Monatsblatt_${year}_${startMonth}-${endMonth}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(a.href);

            msvToast('PDF heruntergeladen', 'success');
        } catch (err) {
            console.error('PDF Export Error:', err);
            msvToast('Fehler beim Generieren des PDFs: ' + err.message, 'error');
        } finally {
            $submitBtn.prop('disabled', false).html(originalText);
        }
    });
});
</script>

<?php
include 'footer.inc.php';
?>
