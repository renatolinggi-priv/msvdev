<?php
//heimrang.php
include 'dbconnect.inc.php';

// Session-Kontrolle wie in jmresultate.php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Alle Styles sind jetzt zentral in msv-styles.css verwaltet
$page_specific_css = '';

include 'header.inc.php';
?>
<!-- Heimrang.php HTML-Gerüst nach jmrang.php Vorbild -->
<div class="container-fluid">
    <div class="row">
        <div class="col-xl-10 col-lg-11 col-12 ps-0">
            <!-- Äußerer weißer Container -->
            <div class="main-content-wrapper">
                <!-- Header außerhalb des inneren Containers -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h2 class="h4 mb-0" style="color: var(--secondary-color);">
                            <i class="bi bi-trophy me-2"></i>
                            Heimmeisterschaft Ranglisten
                        </h2>
                    </div>
                </div>
                
                <!-- Weißer Hintergrund-Container -->
                <div class="content-background">
                <form id="heimresultateForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Jahr-Auswahl in eigener Card -->
                    <div class="year-selection-card">
                        <div class="row align-items-center">
                            <div class="col-md-5">
                                <label for="yearSelect" class="form-label fw-bold">
                                    <i class="bi bi-calendar3 me-1"></i> Jahr auswählen:
                                </label>
                                <select id="yearSelect" class="form-select">
                                    <!-- Optionen werden per JavaScript eingefügt -->
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Button Toolbar -->
                    <div class="button-toolbar">
                        <div class="button-group">
                            <button class="btn btn-compact-standard btn-outline-info pdf-btn" type="button">
                                <i class="bi bi-file-pdf me-2"></i>
                                Rangliste
                            </button>
                            <button id="redirect-btn" class="btn btn-compact-standard btn-outline-success" type="button">
                                <i class="bi bi-pencil-square me-2"></i>
                                Bearbeiten
                            </button>
                        </div>
                        <div id="pdf-link"></div>
                    </div>

                    <!-- Nachrichten Container -->
                    <div id="message"></div>

                    <!-- Kategorie A Tabelle -->
                    <div class="table-wrapper">
                        <h5 class="table-title">
                            <i class="bi bi-star me-2"></i>
                            Heimmeisterschaft Kat. A
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="heimresultateTabelleA">
                                <thead>
                                    <tr>
                                        <th scope="col">Rang</th>
                                        <th scope="col">Name</th>
                                        <th scope="col" class="result-column">Passe 1</th>
                                        <th scope="col" class="result-column">Passe 2</th>
                                        <th scope="col" class="result-column">Passe 3</th>
                                        <th scope="col" class="result-column">Passe 4</th>
                                        <th scope="col" class="result-column">Passe 5</th>
                                        <th scope="col" class="result-column">Passe 6</th>
                                        <th scope="col" class="result-column">Passe 7</th>
                                        <th scope="col" class="result-column">Passe 8</th>
                                        <th scope="col">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamisch per AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Kategorie B Tabelle -->
                    <div class="table-wrapper">
                        <h5 class="table-title">
                            <i class="bi bi-star-half me-2"></i>
                            Heimmeisterschaft Kat. B
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="heimresultateTabelleB">
                                <thead>
                                    <tr>
                                        <th scope="col">Rang</th>
                                        <th scope="col">Name</th>
                                        <th scope="col" class="result-column">Passe 1</th>
                                        <th scope="col" class="result-column">Passe 2</th>
                                        <th scope="col" class="result-column">Passe 3</th>
                                        <th scope="col" class="result-column">Passe 4</th>
                                        <th scope="col" class="result-column">Passe 5</th>
                                        <th scope="col" class="result-column">Passe 6</th>
                                        <th scope="col" class="result-column">Passe 7</th>
                                        <th scope="col" class="result-column">Passe 8</th>
                                        <th scope="col">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dynamisch per AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container"></div>
    <script>
        // Initialisierung des Jahres-Dropdowns
        function initializeYearDropdown() {
            const yearSelect = $('#yearSelect').empty();
            const currentYear = new Date().getFullYear();
            for (let year = 2024; year <= currentYear; year++) {
                const option = $('<option></option>').val(year).text(year);
                if (year === currentYear) {
                    option.prop('selected', true);
                }
                yearSelect.append(option);
            }
        }

        // Redirect Button Handler
        document.addEventListener('DOMContentLoaded', function () {
            var redirectButton = document.getElementById('redirect-btn');
            if (redirectButton) {
                redirectButton.addEventListener('click', function () {
                    console.log('Button clicked, redirecting...');
                    window.location.href = 'https://jahresmeisterschaft.msvwilen.ch/inc/heimresultate.php';
                });
            }
        });

        $(document).ready(function () {
            var basePath = '';

            // Heimresultate für Kat. A laden
            function loadHeimresultatea() {
                var selectedYear = $('#yearSelect').val();
                $.ajax({
                    url: basePath + 'heimrang/load_heimresultate.php',
                    type: 'GET',
                    data: {
                        year: selectedYear,
                        kat: 'A'
                    },
                    success: function (response) {
                        $('#heimresultateTabelleA tbody').html(response);
                    }
                });
            }

            // Heimresultate für Kat. B laden
            function loadHeimresultateb() {
                var selectedYear = $('#yearSelect').val();
                $.ajax({
                    url: basePath + 'heimrang/load_heimresultate.php',
                    type: 'GET',
                    data: {
                        year: selectedYear,
                        kat: 'B'
                    },
                    success: function (response) {
                        $('#heimresultateTabelleB tbody').html(response);
                    }
                });
            }

            // Nachricht anzeigen
            function showMessage(message, type) {
                var messageDiv = $('#message');
                messageDiv.removeClass().addClass('alert alert-' + type).text(message).show();
                setTimeout(function () {
                    messageDiv.fadeOut();
                }, 3000);
            }

            // PDF-Button Handler
            $(document).on('click', '.pdf-btn', function (e) {
                e.preventDefault();
                var selectedYear = $('#yearSelect').val();

                $.ajax({
                    url: 'heimrang/generate_pdf.php',
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        year: selectedYear,
                        kat: 'B'
                    },
                    success: function (response) {
                        if (response.pdf_link) {
                            // PDF direkt herunterladen
                            const link = document.createElement('a');
                            link.href = response.pdf_link;
                            link.download = response.pdf_link.split('/').pop(); // Dateiname extrahieren
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            
                            // PDF-Link Container leeren nach Download
                            $('#pdf-link').empty();
                        } else if (response.error) {
                            alert('Fehler: ' + response.error);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                        alert('Fehler beim Generieren des PDFs: ' + error);
                    }
                });
            });

            // Beim Ändern des Jahres im Dropdown beide Tabellen neu laden
            $('#yearSelect').on('change', function () {
                loadHeimresultatea();
                loadHeimresultateb();
            });

            // Initialisierung beim Laden der Seite
            initializeYearDropdown();
            loadHeimresultatea();
            loadHeimresultateb();
        });
    </script>
    <?php
    include 'footer.inc.php';
    ?>