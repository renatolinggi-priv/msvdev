<?php
// kantiresultate.php – Neuaufbau nach wichtigetermine-Pattern
include 'dbconnect.inc.php';

// Seitenspezifische Styles
$page_specific_css = "
/* Kantiresultate-spezifische Styles */

:root {
    --app-header: 76px;
    --app-footer: 0px;
}

.main-content-wrapper {
    display: flex;
    flex-direction: column;
    min-height: 0 !important;
    height: calc(100vh - var(--app-header) - var(--app-footer) - 20px) !important;
    margin-bottom: 0 !important;
}

.content-background {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0 !important;
    overflow: hidden;
}

#kantiresultateForm {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0 !important;
}

.table-wrapper {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0 !important;
    margin-bottom: 0 !important;
    overflow: hidden !important;
}

.table-responsive {
    flex: 1 1 auto;
    min-height: 0 !important;
    overflow: auto !important;
    border-radius: 0 0 var(--border-radius) var(--border-radius);
    -webkit-overflow-scrolling: touch;
}

.table {
    border: none;
    margin-bottom: 0;
    table-layout: fixed;
}

.table thead th {
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    padding: 1rem;
    background-color: #f8f9fa;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table tbody tr {
    transition: background-color 0.2s ease;
    border-bottom: 1px solid #f1f3f4;
}

.table tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.04);
}

.table tbody td {
    padding: 0.5rem;
    vertical-align: middle;
    border: none;
    text-align: center;
}

.table tbody td:first-child {
    text-align: left;
}

.passe-input {
    width: 55px !important;
    text-align: center !important;
    padding: 0.25rem 0.1rem !important;
    font-size: 0.9rem !important;
    border: 1px solid #dee2e6 !important;
    border-radius: 0.25rem !important;
    transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
}

.passe-input:focus {
    border-color: var(--secondary-color) !important;
    box-shadow: 0 0 0 0.2rem rgba(108, 117, 125, 0.25) !important;
    outline: none !important;
}

.button-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    align-items: center;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 1.25rem;
    margin-bottom: 1.25rem;
    flex-shrink: 0;
}


.results-list-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    overflow: hidden;
    margin-bottom: 0;
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}

.results-header {
    background: linear-gradient(135deg, var(--light-color) 0%, #e9ecef 100%);
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #dee2e6;
    color: var(--dark-color);
    font-weight: 600;
    font-size: 1rem;
    flex-shrink: 0;
}

.btn-compact { padding: .45rem .75rem; font-size: .875rem; }

.custom-close {
    background: none;
    border: none;
    color: var(--secondary-color);
    font-size: 1.5rem;
    opacity: 0.7;
    transition: all var(--transition-speed) ease;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.custom-close:hover {
    opacity: 1;
    background-color: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
    transform: scale(1.1);
}

@media (max-width: 576px) {
    .button-toolbar { flex-direction: column; }
    .button-toolbar .btn { width: 100%; }
}

.spinner-border { color: var(--secondary-color) !important; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.results-list-card {
    animation: fadeIn 0.5s ease-out;
}

/* =========================================
   Mobile Cards Optimierung
   ========================================= */
@media (max-width: 767.98px) {
    .desktop-table-container { display: none !important; }
    .mobile-cards-container { display: flex !important; }

    /* Mobile Scroll Fix: fixe Höhe aufheben */
    .main-content-wrapper {
        height: auto !important;
        min-height: calc(100vh - var(--app-header) - 10px) !important;
    }

    .content-background {
        overflow: visible !important;
    }

    .table-wrapper {
        overflow: visible !important;
    }

    /* WCAG AAA Touch Targets: Alle Form-Elemente */
    .form-control,
    .form-select,
    input[type=\"text\"],
    input[type=\"number\"],
    select {
        min-height: 48px !important;
        font-size: 16px !important; /* Verhindert iOS Auto-Zoom */
    }

    .btn {
        min-height: 48px !important;
        font-size: 16px !important;
        padding: 0.5rem 1rem !important;
    }

    .mobile-card-body .passe-input-mobile {
        min-height: 48px !important;
        font-size: 16px !important;
        padding: 0.5rem !important;
        text-align: center !important;
        font-weight: 500 !important;
    }

    .mobile-card-body .mb-3 {
        margin-bottom: 1rem !important;
    }

    .mobile-card-body .form-label {
        margin-bottom: 0.35rem !important;
        color: #475569 !important;
        font-size: 0.875rem !important;
    }

    .button-toolbar .btn {
        min-height: 48px !important;
        font-size: 0.95rem !important;
    }


}
";

include 'header.inc.php';
?>
<style><?= $page_specific_css ?></style>
<?php
// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-xl-8 col-lg-11 col-12 ps-0">
            <div class="main-content-wrapper">
                <!-- Header -->
                <div class="row mb-4 d-none d-md-flex">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-target me-2"></i>
                            Kantonalstich Resultaterfassung
                        </h2>
                        <p class="text-muted mb-0">Resultate erfassen und verwalten</p>
                    </div>
                </div>

                <div class="content-background">
                    <form id="kantiresultateForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Jahr-Auswahl + Aktionen nebeneinander -->
                        <div class="d-flex flex-wrap gap-3 align-items-start mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <label for="yearSelect" class="form-label fw-bold mb-0 text-nowrap">
                                <i class="bi bi-calendar3 me-1"></i>Jahr:
                            </label>
                            <select id="yearSelect" class="form-select form-select-sm" style="width: auto; min-width: 90px;"></select>
                        </div>

                        <!-- Aktionsbereich (Bootstrap Collapse) -->
                        <div class="card action-card mb-0">
                            <div class="card-header action-card-header d-flex justify-content-between align-items-center py-2"
                                 data-bs-toggle="collapse" data-bs-target="#kantiresultateActions"
                                 aria-expanded="false" aria-controls="kantiresultateActions">
                                <span class="fw-semibold"><i class="bi bi-tools me-2"></i>Aktionen</span>
                                <i class="bi bi-chevron-down action-chevron"></i>
                            </div>
                            <div class="collapse" id="kantiresultateActions">
                                <div class="card-body pt-2 pb-3 px-3">
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-save me-2"></i>Ergebnisse speichern
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button id="redirect-btn" type="button" class="btn btn-outline-success w-100">
                                                <i class="bi bi-trophy me-1"></i>Rangliste
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button id="delete-btn" type="button" class="btn btn-outline-danger w-100">
                                                <i class="bi bi-trash me-1"></i>Löschen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div><!-- Ende flex-row Jahr+Aktionen -->

                        <!-- Tabelle Container -->
                        <div id="resultateContainer">
                            <div class="results-list-card">
                                <div class="results-header">
                                    <i class="bi bi-table me-2"></i>
                                    Resultate
                                </div>
                                <div class="table-wrapper">
                                    <!-- Desktop: Tabelle -->
                                    <div class="desktop-table-container">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0" id="kantiresultateTabelle">
                                                <thead>
                                                    <tr>
                                                        <th scope="col" style="min-width: 180px; width: 200px;">
                                                            <i class="bi bi-person me-1"></i>Mitglied
                                                        </th>
                                                        <th scope="col" class="text-center" style="width: 80px;">Passe 1</th>
                                                        <th scope="col" class="text-center" style="width: 80px;">Passe 2</th>
                                                        <th scope="col" class="text-center" style="width: 80px;">Passe 3</th>
                                                        <th scope="col" class="text-center" style="width: 80px;">Passe 4</th>
                                                        <th scope="col" class="text-center" style="width: 80px;">Passe 5</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-4">
                                                            <div class="spinner-border spinner-border-sm me-2"></div>
                                                            Lade Resultate...
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Mobile: Cards -->
                                    <div class="mobile-cards-container" id="mobileCardsKanti">
                                        <div class="mobile-search">
                                            <div class="position-relative">
                                                <i class="bi bi-search search-icon"></i>
                                                <input type="text" class="form-control" placeholder="Mitglied suchen..."
                                                       oninput="filterMobileKanti(this)">
                                            </div>
                                        </div>
                                        <div class="mobile-cards-scroll">
                                            <!-- Cards werden per JavaScript generiert -->
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

<!-- Lösch-Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">
                    <i class="bi bi-exclamation-triangle text-warning"></i> Bestätigung erforderlich
                </h5>
                <button type="button" class="custom-close" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle text-danger me-3" style="font-size: 2rem;"></i>
                    <div>
                        <strong>Möchtest du wirklich ALLE Resultate des aktuellen Jahres löschen?</strong>
                        <br><small class="text-muted">Diese Aktion kann nicht rückgängig gemacht werden!</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-compact btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-compact btn-outline-danger" id="confirmDeleteButton">
                    <i class="bi bi-trash me-1"></i>Löschen
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {

    function calculateTableHeight() {
        const tableResp = $('.table-responsive');
        if (!tableResp.length) return;
        const tableTop = tableResp.offset().top;
        const availableHeight = window.innerHeight - tableTop - 30;
        tableResp.css({ 'max-height': Math.max(300, availableHeight) + 'px', 'overflow-y': 'auto' });
    }

    function initializeYearDropdown() {
        const $yearSelect = $('#yearSelect').empty();
        const currentYear = new Date().getFullYear();
        for (let year = currentYear; year >= currentYear - 3; year--) {
            const $option = $('<option></option>').val(year).text(year);
            if (year === currentYear) $option.prop('selected', true);
            $yearSelect.append($option);
        }
    }

    function loadResultate(year) {
        var $tbody = $('#kantiresultateTabelle tbody');
        $tbody.html(
            '<tr><td colspan="6" class="text-center py-4">' +
            '<div class="spinner-border spinner-border-sm me-2"></div>' +
            'Lade Resultate...</td></tr>'
        );

        $.ajax({
            url: 'kantiresultate/load_kantiresultate_form.php',
            method: 'GET',
            data: { year: year },
            success: function(response) {
                $tbody.html(response);
                bindInputs();
                setTimeout(calculateTableHeight, 100);
                buildMobileKantiCards();
            },
            error: function() {
                $tbody.html(
                    '<tr><td colspan="6" class="text-center text-danger py-4">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    'Fehler beim Laden der Daten</td></tr>'
                );
                msvToast('Fehler beim Laden der Resultate', 'error');
            }
        });
    }

    function bindInputs() {
        var $inputs = $('#kantiresultateTabelle input');

        $inputs.off('keydown.kanti').on('keydown.kanti', function(e) {
            if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                var inputs = $('#kantiresultateTabelle input');
                var currentIndex = inputs.index(this);
                var nextIndex = e.shiftKey ? currentIndex - 1 : currentIndex + 1;
                var nextInput = inputs.eq(nextIndex);
                if (nextInput.length) nextInput.focus().select();
            }
        });

        $inputs.off('focus.kanti').on('focus.kanti', function() {
            var $this = $(this);
            if ($this.val() === '0') $this.val('').select();
            else if ($this.val() !== '') $this.select();
        });

        $inputs.off('blur.kanti').on('blur.kanti', function() {
            if ($(this).val().trim() === '') $(this).val('0');
        });

        $inputs.off('input.kanti').on('input.kanti', function() {
            var value = $(this).val().replace(/[^0-9]/g, '');
            if (value.length > 2) value = value.substring(0, 2);
            $(this).val(value);
        });
    }

    function fillEmptyWithZero() {
        $('#kantiresultateTabelle tbody tr').each(function() {
            var inputs = $(this).find('input');
            var hasLaterValue = false;
            for (var i = inputs.length - 1; i >= 0; i--) {
                var $input = $(inputs[i]);
                var val = $input.val().trim();
                if (val !== '' && val !== '0') hasLaterValue = true;
                else if (hasLaterValue && val === '') $input.val('0');
            }
        });
    }

    // Speichern
    $('#kantiresultateForm').on('submit', function(e) {
        e.preventDefault();
        var $submitBtn = $(this).find('button[type="submit"]');
        var originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

        fillEmptyWithZero();
        var selectedYear = $('#yearSelect').val();
        var formData = $(this).serialize() + '&year=' + selectedYear + '&jahr=' + selectedYear;

        $.ajax({
            url: 'kantiresultate/save_kantiresultate.php',
            type: 'POST',
            data: formData,
            success: function() {
                msvToast('Ergebnisse erfolgreich gespeichert!', 'success');
                setTimeout(function() { loadResultate(selectedYear); }, 1000);
            },
            error: function() {
                msvToast('Fehler beim Speichern der Ergebnisse', 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Löschen
    $('#delete-btn').on('click', function() { $('#confirmModal').modal('show'); });

    $('#confirmDeleteButton').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.html();
        var selectedYear = $('#yearSelect').val();

        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Lösche...');

        $.ajax({
            url: 'kantiresultate/delete_kanti.php',
            method: 'POST',
            data: { jahr: selectedYear, csrf_token: $('input[name="csrf_token"]').val() },
            success: function() {
                $('#confirmModal').modal('hide');
                msvToast('Alle Resultate erfolgreich gelöscht', 'success');
                setTimeout(function() { loadResultate(selectedYear); }, 500);
            },
            error: function() { msvToast('Fehler beim Löschen', 'error'); },
            complete: function() { $btn.prop('disabled', false).html(originalText); }
        });
    });

    // Rangliste
    $('#redirect-btn').on('click', function() { window.location.href = 'kantirang.php'; });

    // Jahreswechsel
    $('#yearSelect').on('change', function() { loadResultate($(this).val()); });

    // Global Scroll
    document.addEventListener('wheel', function(e) {
        var tableContainer = $('.table-responsive')[0];
        if (tableContainer && tableContainer.scrollHeight > tableContainer.clientHeight) {
            tableContainer.scrollTop += e.deltaY;
            e.preventDefault();
        }
    }, { passive: false });

    // Resize
    var resizeTimeout;
    $(window).on('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(calculateTableHeight, 150);
    });

    // Mobile Cards für Kanti-Resultate generieren
    function buildMobileKantiCards() {
        const isMobile = window.matchMedia('(max-width: 767.98px)');
        if (!isMobile.matches) return;

        const table = document.getElementById('kantiresultateTabelle');
        const container = document.querySelector('#mobileCardsKanti .mobile-cards-scroll');
        if (!table || !container) return;

        const tbody = table.querySelector('tbody');
        if (!tbody) {
            container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten vorhanden</div></div>';
            return;
        }

        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 0) {
            container.innerHTML = '<div class="mobile-cards-empty"><i class="bi bi-inbox"></i><div>Keine Daten vorhanden</div></div>';
            return;
        }

        let html = '';
        rows.forEach((row, idx) => {
            const cells = Array.from(row.querySelectorAll('td'));
            if (cells.length === 0) return;

            // Erste Zelle: Mitgliedername
            const memberName = cells[0]?.textContent?.trim() || 'Unbekannt';

            // Passe-Inputs extrahieren (Spalten 1-5)
            const inputs = Array.from(row.querySelectorAll('input'));
            if (inputs.length < 5) return;

            let fieldsHtml = '';
            const passeLabels = ['Passe 1', 'Passe 2', 'Passe 3', 'Passe 4', 'Passe 5'];

            inputs.forEach((input, i) => {
                if (i >= 5) return; // Nur erste 5 Passen
                const label = passeLabels[i];
                const inputName = input.name || '';
                const inputValue = input.value || '';

                fieldsHtml += `
                    <div class="mb-3">
                        <label class="form-label fw-bold small">${label}</label>
                        <input type="number"
                               class="form-control passe-input-mobile"
                               data-name="${inputName}"
                               value="${inputValue}"
                               inputmode="numeric"
                               pattern="[0-9]*"
                               maxlength="2">
                    </div>`;
            });

            html += `
            <div class="mobile-card" data-index="${idx}">
                <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                    <div>
                        <div class="fw-bold">${memberName}</div>
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="mobile-card-body">
                    ${fieldsHtml}
                </div>
            </div>`;
        });

        container.innerHTML = html;

        // Event-Listener für Inputs: Sync zu Desktop-Tabelle
        container.querySelectorAll('input[data-name]').forEach(input => {
            input.addEventListener('input', function() {
                const inputName = this.getAttribute('data-name');
                const desktopInput = table.querySelector(`input[name="${inputName}"]`);
                if (desktopInput) {
                    desktopInput.value = this.value;
                    // Trigger input event für Validierung
                    $(desktopInput).trigger('input');
                }
            });

            // Keyboard navigation (Enter/Tab)
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === 'Tab') {
                    e.preventDefault();
                    const allInputs = Array.from(container.querySelectorAll('input[data-name]'));
                    const currentIndex = allInputs.indexOf(this);
                    const nextIndex = e.shiftKey ? currentIndex - 1 : currentIndex + 1;
                    if (allInputs[nextIndex]) {
                        allInputs[nextIndex].focus();
                        allInputs[nextIndex].select();
                    }
                }
            });

            // Focus: Select on focus
            input.addEventListener('focus', function() {
                if (this.value === '0') {
                    this.value = '';
                }
                this.select();
            });

            // Blur: Set to 0 if empty
            input.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.value = '0';
                    const inputName = this.getAttribute('data-name');
                    const desktopInput = table.querySelector(`input[name="${inputName}"]`);
                    if (desktopInput) desktopInput.value = '0';
                }
            });
        });
    }

    // Mobile Search Filter (global für inline oninput)
    window.filterMobileKanti = function(searchInput) {
        const query = searchInput.value.toLowerCase();
        const cards = document.querySelectorAll('#mobileCardsKanti .mobile-card');

        let visibleCount = 0;
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            const isVisible = text.includes(query);
            card.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });

        const container = document.querySelector('#mobileCardsKanti .mobile-cards-scroll');
        const existingEmpty = container.querySelector('.mobile-cards-empty');
        if (visibleCount === 0 && !existingEmpty) {
            container.insertAdjacentHTML('beforeend', `
                <div class="mobile-cards-empty">
                    <i class="bi bi-search"></i>
                    <div>Keine Treffer gefunden</div>
                </div>`);
        } else if (visibleCount > 0 && existingEmpty) {
            existingEmpty.remove();
        }
    };

    // Resize-Listener für Mobile Cards
    let wasDesktop = window.matchMedia('(min-width: 768px)').matches;
    window.addEventListener('resize', function() {
        const isNowDesktop = window.matchMedia('(min-width: 768px)').matches;
        if (wasDesktop && !isNowDesktop) {
            buildMobileKantiCards();
        }
        wasDesktop = isNowDesktop;
    });

    // Init
    initializeYearDropdown();
    loadResultate(new Date().getFullYear());
    setTimeout(calculateTableHeight, 200);
});
</script>

<?php include 'footer.inc.php'; ?>
