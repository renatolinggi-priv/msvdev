<?php
include 'dbconnect.inc.php';

// Seitenspezifische Styles definieren
$page_specific_css = "
/* Sieger-spezifische Styles - im Stil von wichtigetermine.php */

/* CSS Variables für dynamische Höhen */
:root {
    --app-header: 76px; /* Standard navbar height */
    --app-footer: 0px;
}

/* Flex-Layout für volle Höhennutzung */
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

/* Sieger Container wird flex */
#siegerListContainer {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
}

/* Moderne Tabellen-Styles */
.table {
    border: none;
    margin-bottom: 0;
    table-layout: fixed; /* Verhindert Spaltenverschiebungen */
}

.table thead th {
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    padding: 1rem;
    background-color: #f8f9fa;
    position: relative; /* Entfernt sticky, das Probleme verursachen kann */
}

.table tbody tr {
    transition: background-color 0.2s ease; /* Nur Hintergrundfarbe animieren */
    border-bottom: 1px solid #f1f3f4;
}

.table tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.04);
    /* transform entfernt - das verursacht das Springen */
}

.table tbody td {
    padding: 0.75rem;
    vertical-align: middle;
    border: none;
}

/* Badge Styles */
.badge {
    font-weight: 500;
    padding: 0.35em 0.65em;
}

/* Button Group in Tabelle */
.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    border-radius: 0.2rem;
}

/* Table Wrapper für Flex-Layout */
.table-wrapper {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0 !important;
    margin-bottom: 0 !important;
    overflow: hidden !important;
}

/* Responsive Table Container */
.table-responsive {
    flex: 1 1 auto;
    min-height: 0 !important;
    overflow: auto !important;
    border-radius: 0 0 var(--border-radius) var(--border-radius);
    -webkit-overflow-scrolling: touch;
    /* Höhe wird dynamisch per JS gesetzt */
}

/* Hover-Effekte für Action Buttons */
.btn-outline-primary:hover,
.btn-outline-danger:hover {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.add-sieger-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 1rem 1.25rem;
    margin-bottom: 1.25rem;
}

/* Kompakte Buttons */
.btn-compact { padding: .45rem .75rem; font-size: .875rem; }

.add-sieger-card h5 {
    color: var(--secondary-color);
    margin-bottom: 0.75rem;
    font-weight: 600;
    font-size: 0.95rem;
}

.filter-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 1rem 1.25rem;
    margin-bottom: 1.25rem;
}

.filter-card h5 {
    color: var(--secondary-color);
    margin-bottom: 0.75rem;
    font-weight: 600;
    font-size: 0.95rem;
}

.sieger-list-card {
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

.sieger-header {
    background: linear-gradient(135deg, var(--light-color) 0%, #e9ecef 100%);
    padding: 1.5rem;
    border-bottom: 1px solid #dee2e6;
    margin: 0;
    color: var(--dark-color);
    font-weight: 600;
    font-size: 1.1rem;
    flex-shrink: 0;
}

/* Custom Close Button */
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

/* Kompakte Form Layouts */
.form-row-compact {
    margin-bottom: 0.75rem;
}

.form-row-compact .form-label {
    margin-bottom: 0.25rem;
    font-size: 0.875rem;
}

/* === KOMPAKTE ACTION BUTTONS === */
.btn-sm,
.delete-sieger {
    padding: 0.2rem 0.4rem !important;
    font-size: 0.75rem !important;
    border-radius: 0.25rem !important;
    line-height: 1.2 !important;
    height: 24px !important;
    min-width: auto !important;
}

/* Nur Icon für Löschen-Button */
.delete-sieger {
    width: 28px !important;
    padding: 0.2rem !important;
}

.delete-sieger i {
    font-size: 0.8rem !important;
}

/* Loading states */
.btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
    transform: none !important;
}

/* Responsive für Sieger */
@media (max-width: 576px) {
    .add-sieger-card, .filter-card {
        padding: 1rem;
    }
}

.spinner-border {
    color: var(--secondary-color) !important;
}

/* Animationen */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.add-sieger-card, .filter-card, .sieger-list-card {
    animation: fadeIn 0.5s ease-out;
}

/* === MOBILE OPTIMIZATION === */
@media (max-width: 767.98px) {
    /* Desktop-Tabelle ausblenden */
    .desktop-table-container {
        display: none !important;
    }

    /* Mobile Cards anzeigen */
    .mobile-cards-container {
        display: block !important;
    }

    /* Formular-Anpassungen für Mobile */
    .add-sieger-card, .filter-card {
        padding: 0.875rem;
    }

    .add-sieger-card h5, .filter-card h5 {
        font-size: 0.9rem;
    }

    /* Button-Anpassungen */
    .btn-compact {
        font-size: 0.8125rem;
        padding: 0.5rem 0.625rem;
    }

    /* Container-Anpassungen */
    .main-content-wrapper {
        padding: 0.5rem;
    }

    .content-background {
        padding: 0.5rem;
    }
}

/* Desktop: Mobile Cards ausblenden */
@media (min-width: 768px) {
    .mobile-cards-container {
        display: none !important;
    }
}
";

include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-xl-10 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4 d-none d-md-flex">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-trophy me-2"></i>
                            Sieger der letzten Jahre
                        </h2>
                        <p class="text-muted mb-0">Übersicht der Sieger nach Jahr</p>
                    </div>
                </div>

                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                    <!-- CSRF Token für Lösch-Aktionen -->
                    <input type="hidden" id="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <div class="row">
                        <!-- Jahr-Filter -->
                        <div class="col-md-4">
                            <div class="filter-card">
                                <h5>
                                    <i class="bi bi-funnel me-2"></i>
                                    Filter
                                </h5>

                                <div class="form-row-compact">
                                    <label for="filterYear" class="form-label">
                                        <i class="bi bi-calendar-date me-1"></i>Jahr anzeigen:
                                    </label>
                                    <select id="filterYear" name="year" class="form-control form-control-sm">
                                        <?php
                                        // Distinct years aus der Tabelle sieger abrufen
                                        $sql = "SELECT DISTINCT year FROM sieger ORDER BY year DESC";
                                        $result = $conn->query($sql);

                                        $currentYear = date("Y");
                                        $lastYear = $currentYear - 1;

                                        if ($result->num_rows > 0) {
                                            while ($row = $result->fetch_assoc()) {
                                                $selected = ($row['year'] == $lastYear) ? "selected" : "";
                                                echo "<option value='" . $row['year'] . "' $selected>" . $row['year'] . "</option>";
                                            }
                                        } else {
                                            echo "<option value='$currentYear'>$currentYear</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <button type="button" id="filterButton" class="btn btn-compact btn-outline-info w-100 mt-2">
                                    <i class="bi bi-search me-1"></i> Anzeigen
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Container für die Sieger Liste -->
                    <div id="siegerListContainer">
                        <div class="sieger-list-card">
                            <div class="sieger-header">
                                <i class="bi bi-trophy me-2"></i>
                                <span id="siegerListTitle">Sieger Liste</span>
                            </div>
                            <div class="p-4 text-center">
                                <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                                Lade Sieger...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal zur Bestätigung für das Löschen -->
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
                    <i class="bi bi-exclamation-triangle text-warning me-3" style="font-size: 2rem;"></i>
                    <div>
                        <strong>Möchtest du diesen Sieger-Eintrag wirklich löschen?</strong>
                        <br><small class="text-muted">Diese Aktion kann nicht rückgängig gemacht werden.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-compact btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-compact btn-outline-danger" id="confirmAction">
                    <i class="bi bi-trash me-1"></i>Löschen
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var siegerId = null;

    // Höhenberechnung für Tabelle
    function calculateTableHeight() {
        const tableResp = $('.table-responsive');
        if (!tableResp.length) return;

        // Navbar Höhe
        const navbar = $('.navbar');
        const navbarHeight = navbar.length ? navbar.outerHeight() : 76;

        // Position der Tabelle
        const tableTop = tableResp.offset().top;

        // Footer und Padding
        const bottomPadding = 30;

        // Verfügbare Höhe berechnen
        const viewportHeight = window.innerHeight;
        const availableHeight = viewportHeight - tableTop - bottomPadding;
        const maxHeight = Math.max(300, availableHeight);

        // Höhe setzen
        tableResp.css({
            'max-height': maxHeight + 'px',
            'overflow-y': 'auto'
        });
    }

    // Sieger für Jahr laden
    function loadSieger(year) {
        $('#siegerListContainer').html(`
            <div class="sieger-list-card">
                <div class="sieger-header">
                    <i class="bi bi-trophy me-2"></i>
                    <span id="siegerListTitle">Sieger des Jahres ${year}</span>
                </div>
                <div class="p-4 text-center">
                    <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                    Lade Sieger für ${year}...
                </div>
            </div>
        `);

        $.ajax({
            url: 'sieger/load_sieger.php',
            method: 'GET',
            data: { year: year },
            success: function(response) {
                $('#siegerListContainer').html(response);
                // Höhe nach dem Laden neu berechnen
                setTimeout(calculateTableHeight, 100);
                // Mobile Cards generieren
                if (typeof buildMobileSiegerCards === 'function') {
                    buildMobileSiegerCards();
                }
            },
            error: function(xhr, status, error) {
                $('#siegerListContainer').html(`
                    <div class="sieger-list-card">
                        <div class="sieger-header">
                            <i class="bi bi-trophy me-2"></i>
                            Sieger Liste
                        </div>
                        <div class="p-4 text-center text-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Fehler beim Laden der Sieger
                        </div>
                    </div>
                `);
                msvToast('Fehler beim Laden der Sieger', 'error');
            }
        });
    }

    // Window Resize Handler
    let resizeTimeout;
    $(window).on('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(calculateTableHeight, 150);
    });

    // Global Scroll auf Tabelle umleiten
    document.addEventListener('wheel', function(e) {
        const tableContainer = $('.table-responsive')[0];
        if (tableContainer && tableContainer.scrollHeight > tableContainer.clientHeight) {
            tableContainer.scrollTop += e.deltaY;
            e.preventDefault();
        }
    }, { passive: false });

    // Filter Button
    $('#filterButton').on('click', function() {
        var selectedYear = $('#filterYear').val();
        loadSieger(selectedYear);
    });

    // Sieger löschen
    $(document).on('click', '.delete-sieger', function() {
        siegerId = $(this).data('id');
        $('#confirmModal').modal('show');
    });

    // Löschen bestätigen
    $('#confirmAction').on('click', function() {
        if (!siegerId) return;
        
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Lösche...');

        $.ajax({
            url: 'sieger/delete_sieger.php',
            method: 'POST',
            data: {
                sieger_id: siegerId,
                csrf_token: $('#csrf_token').val()
            },
            success: function(response) {
                try {
                    const jsonResponse = JSON.parse(response);
                    if (jsonResponse.success) {
                        $('#confirmModal').modal('hide');
                        msvToast('Sieger erfolgreich gelöscht', 'success');
                        setTimeout(() => loadSieger($('#filterYear').val()), 500);
                    } else {
                        msvToast('Fehler: ' + (jsonResponse.message || 'Unbekannter Fehler'), 'error');
                    }
                } catch (e) {
                    $('#confirmModal').modal('hide');
                    msvToast('Sieger erfolgreich gelöscht', 'success');
                    setTimeout(() => loadSieger($('#filterYear').val()), 500);
                }
            },
            error: function(xhr, status, error) {
                msvToast('Fehler beim Löschen des Siegers', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
                siegerId = null;
            }
        });
    });

    // Mobile Cards für Sieger generieren
    function buildMobileSiegerCards() {
        const isMobile = window.matchMedia('(max-width: 767.98px)');
        if (!isMobile.matches) return;

        MSVMobileCards.buildCards('#siegerTable', '#mobileSiegerCards', {
            customHtml: function(row, cells, headers, idx) {
                const name = cells[0]?.textContent?.trim() || '';
                const auszeichnung = cells[1]?.textContent?.trim() || '-';
                const resultat = cells[2]?.textContent?.trim() || '-';
                const jahr = cells[3]?.textContent?.trim() || '-';
                const deleteBtn = cells[4]?.querySelector('.delete-sieger');
                const siegerId = deleteBtn ? deleteBtn.getAttribute('data-id') : '';

                return `
                <div class="mobile-card" data-index="${idx}">
                    <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                        <div class="fw-bold">${name}</div>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="mobile-card-body">
                        <div class="mobile-card-detail-row">
                            <span class="mobile-card-detail-label">Auszeichnung</span>
                            <span class="mobile-card-detail-value">${auszeichnung}</span>
                        </div>
                        <div class="mobile-card-detail-row">
                            <span class="mobile-card-detail-label">Resultat</span>
                            <span class="mobile-card-detail-value"><strong>${resultat}</strong></span>
                        </div>
                        <div class="mobile-card-detail-row">
                            <span class="mobile-card-detail-label">Jahr</span>
                            <span class="mobile-card-detail-value">${jahr}</span>
                        </div>
                        <div class="mt-2 pt-1">
                            <button class="btn btn-outline-danger btn-sm delete-sieger w-100" data-id="${siegerId}">
                                <i class="bi bi-trash me-1"></i> Löschen
                            </button>
                        </div>
                    </div>
                </div>`;
            }
        });
    }

    // Global filterMobileSieger function
    window.filterMobileSieger = function(searchInput) {
        MSVMobileCards.filterCards(searchInput, '#mobileSiegerCards');
    };

    // Initial laden des letzten Jahres
    const initialYear = $('#filterYear').val();
    loadSieger(initialYear);

    // Initiale Höhenberechnung nach kurzer Verzögerung
    setTimeout(calculateTableHeight, 200);
});
</script>

<?php
include 'footer.inc.php';
?>