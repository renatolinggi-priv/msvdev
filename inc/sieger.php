<?php
include 'dbconnect.inc.php';

// Seitenspezifische Styles definieren
$page_specific_css = "
/* === NAVIGATION Z-INDEX FIX === */
/* Navigation muss immer über allen anderen Elementen sein */
.navbar {
    z-index: 1030 !important;
}

.dropdown-menu {
    z-index: 1040 !important;
}

/* === CONTAINER STYLES === */
.main-content-wrapper {
    position: relative;
}

.content-background {
    position: relative;
}

/* Container-fluid Anpassung */
.container-fluid {
    position: relative;
}

/* Sieger-spezifische Styles */
.add-sieger-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 2rem;
    margin-bottom: 2rem;
    border-left: 4px solid var(--success-color);
}

.filter-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-left: 4px solid var(--info-color);
}

/* === TABELLEN CONTAINER === */
.table-wrapper {
    position: relative;
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
}

/* Table Title mit niedrigem z-index */
.table-wrapper .table-title {
    position: relative;
    background: linear-gradient(135deg, var(--light-color) 0%, #e9ecef 100%);
    padding: 1rem;
    margin: 0;
    border-radius: 0.5rem 0.5rem 0 0;
    border-bottom: 2px solid #dee2e6;
    z-index: 5; /* Niedrig genug für Navigation */
}

/* Table Container mit Scrolling */
.table-container {
    max-height: calc(100vh - 400px);
    overflow: auto;
    position: relative;
    border-radius: 0 0 0.5rem 0.5rem;
}

.card-title {
    color: var(--secondary-color);
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* === TABELLEN HEADER === */
#siegerTable thead {
    position: sticky;
    top: 0;
    z-index: 4; /* Unter Navigation */
}

#siegerTable thead th {
    position: sticky;
    top: 0;
    background: var(--secondary-color);
    color: white;
    z-index: 4; /* Unter Navigation */
    border-bottom: 2px solid var(--secondary-color);
    white-space: nowrap;
    font-size: 0.85rem;
    padding: 0.4rem 0.4rem;
    vertical-align: middle;
}

/* === TABELLEN BODY === */
#siegerTable tbody td {
    background: white;
    position: relative;
    z-index: 1; /* Niedrigster z-index */
    white-space: nowrap;
    font-size: 0.85rem;
    padding: 0.4rem 0.4rem;
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
}

/* Hover-Effekt für tbody */
#siegerTable tbody tr:hover {
    background-color: rgba(108, 117, 125, 0.08) !important;
}

#siegerTable tbody tr:hover td {
    background-color: rgba(108, 117, 125, 0.08) !important;
}

/* Erweiterte Tabelle für mehr Spalten */
#siegerTable {
    min-width: 100%;
    margin-bottom: 0;
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
@media (max-width: 768px) {
    .add-sieger-card, .filter-card, .sieger-list-card {
        padding: 1rem;
        margin: 0 0 2rem 0;
        border-radius: 0;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
}

/* Animationen */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.add-sieger-card, .filter-card, .sieger-list-card {
    animation: fadeIn 0.5s ease-out;
}

/* Accessibility */
.btn:focus {
    outline: 2px solid var(--secondary-color);
    outline-offset: 2px;
}

/* Kompakte Form Layouts */
.form-row-compact {
    margin-bottom: 1rem;
}

.form-row-compact .form-label {
    margin-bottom: 0.25rem;
    font-size: 0.875rem;
}

.form-row-compact .form-control {
    padding: 0.5rem;
}

/* Alert Messages */
.alert {
    border: none;
    border-radius: var(--border-radius);
    font-weight: 500;
    box-shadow: var(--box-shadow);
}

.alert-success {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    color: #155724;
    border-left: 4px solid var(--success-color);
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da, #f5c6cb);
    color: #721c24;
    border-left: 4px solid var(--danger-color);
}
";

include 'header.inc.php';

// CSRF Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="container-fluid" style="max-width: 1200px; padding-left: 1rem; padding-right: 1rem; margin-left: 0;">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                <i class="bi bi-trophy me-2"></i>
                Sieger verwalten
            </h2>
            <p class="text-muted mb-0">Sieger erfassen und anzeigen</p>
        </div>
        <div class="col-md-4">
            <div id="message"></div>
        </div>
    </div>

    <div class="row">
        <!-- Neuen Sieger hinzufügen -->
        <div class="col-md-6">
            <div class="add-sieger-card">
                <h5 class="card-title">
                    <i class="bi bi-plus-circle"></i>
                    Neuen Sieger erfassen
                </h5>
                
                <form id="addSiegerForm" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    
                    <div class="form-row-compact">
                        <label for="member" class="form-label">
                            <i class="bi bi-person me-1"></i>Mitglied:
                        </label>
                        <select id="member" name="member_id" class="form-control" required>
                            <option value="">Mitglied auswählen...</option>
                            <?php
                            // Mitglieder abrufen
                            $sql = "SELECT ID, Vorname, Name FROM mitglieder ORDER BY Name";
                            $result = $conn->query($sql);

                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='" . $row['ID'] . "'>" . htmlspecialchars($row['Name'] . " " . $row['Vorname']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-row-compact">
                        <label for="wert" class="form-label">
                            <i class="bi bi-award me-1"></i>Wert:
                        </label>
                        <input type="number" id="wert" name="wert" class="form-control" required>
                    </div>

                    <div class="form-row-compact">
                        <label for="siegerdef" class="form-label">
                            <i class="bi bi-bookmark me-1"></i>Kategorie:
                        </label>
                        <select id="siegerdef" name="siegerdef" class="form-control" required>
                            <option value="">Kategorie auswählen...</option>
                            <?php
                            // Siegerdef abrufen
                            $sql = "SELECT ID, Bezeichnung FROM siegerdef ORDER BY Bezeichnung";
                            $result = $conn->query($sql);

                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<option value='" . $row['ID'] . "'>" . htmlspecialchars($row['Bezeichnung']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-row-compact">
                        <label for="year" class="form-label">
                            <i class="bi bi-calendar3 me-1"></i>Jahr:
                        </label>
                        <input type="number" id="year" name="year" class="form-control" min="1900" max="2100" value="<?= date('Y') ?>" required>
                    </div>

                    <button type="submit" class="btn btn-outline-success w-100 mt-3">
                        <i class="bi bi-save me-1"></i> Sieger speichern
                    </button>
                </form>
            </div>
        </div>

        <!-- Jahr-Filter -->
        <div class="col-md-6">
            <div class="filter-card">
                <h5 class="card-title">
                    <i class="bi bi-funnel"></i>
                    Filter
                </h5>
                
                <div class="form-row-compact">
                    <label for="filterYear" class="form-label">
                        <i class="bi bi-calendar-date me-1"></i>Jahr anzeigen:
                    </label>
                    <select id="filterYear" name="year" class="form-control">
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

                <button type="button" id="filterButton" class="btn btn-outline-info w-100 mt-3">
                    <i class="bi bi-search me-1"></i> Anzeigen
                </button>
            </div>
        </div>
    </div>

    <!-- Sieger Liste -->
    <div class="row">
        <div class="col-12">
            <div class="table-wrapper">
                <h5 class="table-title">
                    <i class="bi bi-trophy me-2"></i>
                    <span id="siegerListTitle">Sieger Liste</span>
                </h5>
                <div class="table-container">
                    <div class="table-responsive">
                        <div id="siegerTableContainer">
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
                    <i class="bi bi-exclamation-triangle"></i> Bestätigung erforderlich
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
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
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Abbrechen
                </button>
                <button type="button" class="btn btn-outline-danger" id="confirmAction">
                    <i class="bi bi-trash me-1"></i>Löschen bestätigen
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999;"></div>

<script>
$(document).ready(function() {
    var siegerId = null;

    // Toast Container hinzufügen falls nicht vorhanden
    if ($('#toast-container').length === 0) {
        $('body').append('<div id="toast-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999;"></div>');
    }

    // Toast-Funktion
    function showToast(message, type = 'info') {
        const colors = {
            'success': '#28a745',
            'error': '#dc3545',
            'warning': '#ffc107',
            'info': '#6c757d'
        };
        
        const icons = {
            'success': 'bi-check-circle',
            'error': 'bi-exclamation-circle',
            'warning': 'bi-exclamation-triangle',
            'info': 'bi-info-circle'
        };
        
        const toast = $('<div>')
            .css({
                'background-color': colors[type] || colors.info,
                'color': 'white',
                'padding': '12px 20px',
                'margin-bottom': '10px',
                'border-radius': '6px',
                'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
                'opacity': '0',
                'transform': 'translateX(100%)',
                'transition': 'all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)',
                'font-weight': '500',
                'display': 'flex',
                'align-items': 'center',
                'min-width': '250px'
            })
            .html(`<i class="bi ${icons[type]} me-2"></i>${message}`);
        
        $('#toast-container').append(toast);
        
        setTimeout(() => {
            toast.css({
                'opacity': '1',
                'transform': 'translateX(0)'
            });
        }, 100);
        
        setTimeout(() => {
            toast.css({
                'opacity': '0',
                'transform': 'translateX(100%)'
            });
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // Sieger für Jahr laden
    function loadSieger(year) {
        $('#siegerTableContainer').html(`
            <div class="p-4 text-center">
                <div class="spinner-border spinner-border-sm me-2" style="color: var(--secondary-color);"></div>
                Lade Sieger für ${year}...
            </div>
        `);
        
        $('#siegerListTitle').text(`Sieger des Jahres ${year}`);
        
        $.ajax({
            url: 'sieger/load_sieger.php',
            method: 'GET',
            data: { year: year },
            success: function(response) {
                $('#siegerTableContainer').html(response);
                showToast('Sieger erfolgreich geladen', 'success');
            },
            error: function(xhr, status, error) {
                $('#siegerTableContainer').html(`
                    <div class="p-4 text-center text-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Fehler beim Laden der Sieger
                    </div>
                `);
                showToast('Fehler beim Laden der Sieger', 'error');
            }
        });
    }

    // Filter Button
    $('#filterButton').on('click', function() {
        var selectedYear = $('#filterYear').val();
        loadSieger(selectedYear);
    });

    // Neuen Sieger hinzufügen
    $('#addSiegerForm').on('submit', function(e) {
        e.preventDefault();
        
        var $submitBtn = $(this).find('button[type="submit"]');
        var originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');

        var memberId = $('#member').val();
        var wert = $('#wert').val();
        var siegerdef = $('#siegerdef').val();
        var year = $('#year').val();

        if (!memberId || !wert || !siegerdef || !year) {
            showToast('Bitte alle Felder ausfüllen', 'warning');
            $submitBtn.prop('disabled', false).html(originalText);
            return;
        }

        $.ajax({
            url: 'sieger/add_sieger.php',
            method: 'POST',
            data: {
                member_id: memberId,
                wert: wert,
                siegerdef: siegerdef,
                year: year,
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                try {
                    const jsonResponse = JSON.parse(response);
                    if (jsonResponse.success) {
                        showToast('Sieger erfolgreich hinzugefügt!', 'success');
                        // Formular zurücksetzen
                        $('#member').val('');
                        $('#wert').val('');
                        $('#siegerdef').val('');
                        // Jahr bleibt das aktuelle Jahr
                        // Liste neu laden
                        setTimeout(() => loadSieger(year), 500);
                    } else {
                        showToast('Fehler: ' + (jsonResponse.message || 'Unbekannter Fehler'), 'error');
                    }
                } catch (e) {
                    showToast('Sieger erfolgreich hinzugefügt!', 'success');
                    $('#member').val('');
                    $('#wert').val('');
                    $('#siegerdef').val('');
                    setTimeout(() => loadSieger(year), 500);
                }
            },
            error: function(xhr, status, error) {
                showToast('Fehler beim Hinzufügen des Siegers', 'error');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
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
                csrf_token: $('input[name="csrf_token"]').val()
            },
            success: function(response) {
                try {
                    const jsonResponse = JSON.parse(response);
                    if (jsonResponse.success) {
                        $('#confirmModal').modal('hide');
                        showToast('Sieger erfolgreich gelöscht', 'success');
                        setTimeout(() => loadSieger($('#filterYear').val()), 500);
                    } else {
                        showToast('Fehler: ' + (jsonResponse.message || 'Unbekannter Fehler'), 'error');
                    }
                } catch (e) {
                    $('#confirmModal').modal('hide');
                    showToast('Sieger erfolgreich gelöscht', 'success');
                    setTimeout(() => loadSieger($('#filterYear').val()), 500);
                }
            },
            error: function(xhr, status, error) {
                showToast('Fehler beim Löschen des Siegers', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
                siegerId = null;
            }
        });
    });

    // Initial laden des letzten Jahres
    const initialYear = $('#filterYear').val();
    loadSieger(initialYear);
});
</script>

<?php
include 'footer.inc.php';
?>