<?php
include 'dbconnect.inc.php';
include 'header.inc.php';
?>
<style>
/* Mobile Optimierung für Resultatbuch */
@media (max-width: 767.98px) {
    .form-control, .form-select {
        min-height: 48px !important;
        font-size: 16px !important;
    }

    .btn {
        min-height: 48px !important;
        font-size: 16px !important;
        width: 100%;
    }

    .container-fluid {
        padding: 1rem !important;
    }
}
</style>

<div class="container-fluid">
  <!-- Dropdown für Jahresauswahl -->
  <div class="row mb-3">
    <div class="col-md-4 col-12">
      <label for="yearSelect" class="form-label fw-bold">
        <i class="bi bi-calendar3 me-1"></i> Jahr auswählen:
      </label>
      <select id="yearSelect" class="form-select">
        <!-- Optionen werden per JavaScript eingefügt -->
      </select>
    </div>
  </div>

  <!-- Button zum Word erstellen -->
  <div class="row mb-3">
    <div class="col-md-4 col-12">
      <button class="btn btn-info word-btn">
        <i class="bi bi-file-word me-2"></i>Word erstellen
      </button>
    </div>
  </div>

  <!-- Container für den Word-Link -->
  <div class="row">
    <div class="col-md-12" id="word-link">
      <!-- Word-Link wird hier eingefügt -->
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
       // Initialisierung des Jahres-Dropdowns
       function initializeYearDropdown() {
        const yearSelect = $('#yearSelect').empty();
        const currentYear = new Date().getFullYear();
        for (let year = currentYear; year >= currentYear - 3; year--) {
            const option = $('<option></option>').val(year).text(year);
            if (year === currentYear) {
                option.prop('selected', true);
            }
            yearSelect.append(option);
        }
    }
    $(document).on('click', '.word-btn', function(e) {
        
        var selectedYear = $('#yearSelect').val();
        e.preventDefault();
        $.ajax({
            url: 'absenden/generate_absendenbuch.php',
            type: 'GET',
                    data: {
                        year: selectedYear
                    },
            success: function(response) {
                // Prüfen Sie, ob die Antwort bereits ein Objekt ist
                console.log(typeof response);
                console.log(response);

                // Entfernen Sie JSON.parse, wenn die Antwort bereits ein Objekt ist
                var wordLink = response.word_link;
                $('#word-link').html('<a href="absenden/' + wordLink + '" target="_blank">Absendenbüchlein herunterladen</a>');
            },
            error: function(xhr, status, error) {
                msvError('Fehler beim Generieren des Words: ' + error);
            }
        });
    });
    initializeYearDropdown();
});

</script>
