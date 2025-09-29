<?php
/**
 * endresultate_partner.php
 * Eingabemaske für Partner-Endresultate
 * 
 * @author System
 * @version 1.0
 * @description Partner-Eingabemaske für Endstich mit 5 Schuss Mitglied + 5 Schuss Partner + 1 Schwini Partner
 */

session_start();
include 'inc/config.php';
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Endresultate - MSV Jegenstorf</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/msv-styles.css" rel="stylesheet">
</head>
<body>
    <?php include 'inc/header.inc.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col">
                                <h4 class="mb-0">
                                    <i class="bi bi-people"></i> Partner Endresultate
                                </h4>
                            </div>
                            <div class="col-auto">
                                <div class="row g-2">
                                    <div class="col-auto">
                                        <label for="yearSelect" class="form-label">Jahr:</label>
                                        <select class="form-select" id="yearSelect" style="width: auto;">
                                            <?php
                                            $currentYear = date('Y');
                                            for ($year = $currentYear; $year >= 2020; $year--) {
                                                $selected = ($year == $currentYear) ? 'selected' : '';
                                                echo "<option value='$year' $selected>$year</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-auto">
                                        <button type="button" class="btn btn-success" id="addPartnerBtn">
                                            <i class="bi bi-plus"></i> Neuer Partner
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Mitglied</th>
                                        <th>Partner</th>
                                        <th class="text-center">Mitglied (5 Schuss)</th>
                                        <th class="text-center">Partner (5 Schuss)</th>
                                        <th class="text-center">Total Endstich</th>
                                        <th class="text-center">Partner Schwini</th>
                                        <th class="text-center">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody id="partnerResultsTable">
                                    <!-- Data will be loaded here via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Partner Modal -->
    <div class="modal fade" id="partnerModal" tabindex="-1" aria-labelledby="partnerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="partnerModalLabel">Partner Daten eingeben</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="partnerForm">
                    <div class="modal-body">
                        <div class="row">
                            <!-- Mitglied Selection -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="mitgliedSelect" class="form-label">Mitglied auswählen <span class="text-danger">*</span></label>
                                    <select class="form-select" id="mitgliedSelect" name="mitgliedID" required>
                                        <option value="">-- Mitglied wählen --</option>
                                        <?php
                                        // Load members for selection
                                        $sql = "SELECT ID, Name, Vorname FROM mitglieder ORDER BY Name, Vorname";
                                        $result = $conn->query($sql);
                                        if ($result && $result->num_rows > 0) {
                                            while ($row = $result->fetch_assoc()) {
                                                echo "<option value='" . $row['ID'] . "'>" . htmlspecialchars($row['Name'] . " " . $row['Vorname']) . "</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Partner Name -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="partnerName" class="form-label">Partner Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="partnerName" name="partnerName" required>
                                </div>
                            </div>
                        </div>

                        <!-- Endstich Schüsse -->
                        <div class="row">
                            <div class="col-12">
                                <h6 class="mb-3">
                                    <i class="bi bi-target"></i> Endstich (je 5 Schuss)
                                </h6>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Mitglied Schüsse -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Mitglied Schüsse</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <div class="col-md-6 mb-2">
                                                <label for="mitgliedSchuss<?= $i ?>" class="form-label">Schuss <?= $i ?></label>
                                                <input type="number" class="form-control" id="mitgliedSchuss<?= $i ?>" 
                                                       name="MitgliedSchuss<?= $i ?>" min="0" max="10" step="0.1" value="0">
                                            </div>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="mt-2">
                                            <strong>Summe: <span id="mitgliedSumme">0.0</span></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Partner Schüsse -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Partner Schüsse</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <div class="col-md-6 mb-2">
                                                <label for="partnerSchuss<?= $i ?>" class="form-label">Schuss <?= $i ?></label>
                                                <input type="number" class="form-control" id="partnerSchuss<?= $i ?>" 
                                                       name="PartnerSchuss<?= $i ?>" min="0" max="10" step="0.1" value="0">
                                            </div>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="mt-2">
                                            <strong>Summe: <span id="partnerSumme">0.0</span></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gesamt Endstich -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <h5 class="mb-0">Gesamt Endstich: <span id="gesamtEndstich">0.0</span></h5>
                                </div>
                            </div>
                        </div>

                        <!-- Partner Schwini -->
                        <div class="row">
                            <div class="col-12">
                                <h6 class="mb-3">
                                    <i class="bi bi-bullseye"></i> Partner Schwini (6 Schuss)
                                </h6>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="row">
                                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                            <div class="col-md-4 mb-2">
                                                <label for="partnerSchwiniSchuss<?= $i ?>" class="form-label">Schuss <?= $i ?></label>
                                                <input type="number" class="form-control" id="partnerSchwiniSchuss<?= $i ?>" 
                                                       name="PartnerSchwiniSchuss<?= $i ?>" min="0" max="10" step="0.1" value="0">
                                            </div>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="mt-2">
                                            <strong>Schwini Summe: <span id="schwiniSumme">0.0</span></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" id="partnerID" name="partnerID" value="">
                        <input type="hidden" id="jahr" name="jahr" value="<?= date('Y') ?>">
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'inc/footer.inc.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
    $(document).ready(function() {
        let currentYear = <?= date('Y') ?>;
        
        // Load partner results on page load
        loadPartnerResults();
        
        // Year change handler
        $('#yearSelect').on('change', function() {
            currentYear = $(this).val();
            $('#jahr').val(currentYear);
            loadPartnerResults();
        });

        // Add partner button
        $('#addPartnerBtn').on('click', function() {
            resetPartnerForm();
            $('#partnerModalLabel').text('Neuer Partner');
            $('#partnerModal').modal('show');
        });

        // Edit partner button (delegated event)
        $(document).on('click', '.edit-partner-btn', function() {
            const partnerID = $(this).data('id');
            loadPartnerData(partnerID);
        });

        // Add partner button (delegated event)
        $(document).on('click', '.add-partner-btn', function() {
            const mitgliedID = $(this).data('mitglied-id');
            resetPartnerForm();
            $('#mitgliedSelect').val(mitgliedID);
            $('#partnerModalLabel').text('Neuer Partner');
            $('#partnerModal').modal('show');
        });

        // Delete partner button (delegated event)
        $(document).on('click', '.delete-partner-btn', function() {
            const partnerID = $(this).data('id');
            if (confirm('Möchten Sie diese Partner-Daten wirklich löschen?')) {
                deletePartner(partnerID);
            }
        });

        // Form submission
        $('#partnerForm').on('submit', function(e) {
            e.preventDefault();
            savePartnerData();
        });

        // Auto-calculate sums
        $('input[name^="MitgliedSchuss"], input[name^="PartnerSchuss"], input[name^="PartnerSchwiniSchuss"]').on('input', function() {
            calculateSums();
        });

        function loadPartnerResults() {
            $.get('inc/endresultate_partner/load_partner_resultate.php', {
                year: currentYear
            }, function(data) {
                $('#partnerResultsTable').html(data);
            }).fail(function() {
                $('#partnerResultsTable').html('<tr><td colspan="7" class="text-center text-danger">Fehler beim Laden der Daten</td></tr>');
            });
        }

        function loadPartnerData(partnerID) {
            $.get('inc/endresultate_partner/load_partner_data.php', {
                id: partnerID
            }, function(data) {
                if (data.success) {
                    const partner = data.partner;
                    $('#partnerID').val(partner.ID);
                    $('#mitgliedSelect').val(partner.MitgliedID);
                    $('#partnerName').val(partner.PartnerName);
                    
                    // Load shot data
                    for (let i = 1; i <= 5; i++) {
                        $('#mitgliedSchuss' + i).val(partner['MitgliedSchuss' + i] || 0);
                        $('#partnerSchuss' + i).val(partner['PartnerSchuss' + i] || 0);
                    }
                    
                    for (let i = 1; i <= 6; i++) {
                        $('#partnerSchwiniSchuss' + i).val(partner['PartnerSchwiniSchuss' + i] || 0);
                    }
                    
                    calculateSums();
                    $('#partnerModalLabel').text('Partner bearbeiten');
                    $('#partnerModal').modal('show');
                } else {
                    alert('Fehler beim Laden der Partner-Daten: ' + data.error);
                }
            }, 'json').fail(function() {
                alert('Fehler beim Laden der Partner-Daten');
            });
        }

        function savePartnerData() {
            const formData = $('#partnerForm').serialize();
            
            $.post('inc/endresultate_partner/save_partner_schuss.php', formData, function(data) {
                if (data.success) {
                    $('#partnerModal').modal('hide');
                    loadPartnerResults();
                    alert(data.message);
                } else {
                    alert('Fehler beim Speichern: ' + data.error);
                }
            }, 'json').fail(function() {
                alert('Fehler beim Speichern der Daten');
            });
        }

        function deletePartner(partnerID) {
            $.post('inc/endresultate_partner/delete_partner.php', {
                id: partnerID
            }, function(data) {
                if (data.success) {
                    loadPartnerResults();
                    alert(data.message);
                } else {
                    alert('Fehler beim Löschen: ' + data.error);
                }
            }, 'json').fail(function() {
                alert('Fehler beim Löschen der Daten');
            });
        }

        function resetPartnerForm() {
            $('#partnerForm')[0].reset();
            $('#partnerID').val('');
            calculateSums();
        }

        function calculateSums() {
            // Calculate member sum
            let mitgliedSum = 0;
            for (let i = 1; i <= 5; i++) {
                mitgliedSum += parseFloat($('#mitgliedSchuss' + i).val() || 0);
            }
            $('#mitgliedSumme').text(mitgliedSum.toFixed(1));
            
            // Calculate partner sum
            let partnerSum = 0;
            for (let i = 1; i <= 5; i++) {
                partnerSum += parseFloat($('#partnerSchuss' + i).val() || 0);
            }
            $('#partnerSumme').text(partnerSum.toFixed(1));
            
            // Calculate total
            const total = mitgliedSum + partnerSum;
            $('#gesamtEndstich').text(total.toFixed(1));
            
            // Calculate schwini sum
            let schwiniSum = 0;
            for (let i = 1; i <= 6; i++) {
                schwiniSum += parseFloat($('#partnerSchwiniSchuss' + i).val() || 0);
            }
            $('#schwiniSumme').text(schwiniSum.toFixed(1));
        }
    });
    </script>
</body>
</html>

<?php
if (isset($conn)) {
    $conn->close();
}
?>