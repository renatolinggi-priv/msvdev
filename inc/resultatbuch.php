<?php
include 'dbconnect.inc.php';
include 'header.inc.php';
?>
<div class="container-fluid">
  <!-- Dropdown für Jahresauswahl -->
  <div class="row mb-1">
    <div class="col-md-1">
      <label for="yearSelect"><strong>Jahr auswählen:</strong></label>
      <select id="yearSelect" class="form-control">
        <!-- Optionen werden per JavaScript eingefügt -->
      </select>
    </div>
  </div>

  <!-- Button zum Word erstellen -->
  <div class="row mb-1">
    <div class="col-md-12">
      <button class="btn btn-info word-btn">Word erstellen</button>
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
        for (let year = 2024; year <= currentYear; year++) {
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
                alert('Fehler beim Generieren des Words: ' + error);
            }
        });
    });
    initializeYearDropdown();
});


</script>
