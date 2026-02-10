// munitionskauf.js - Frontend Logic für Munitionsbestellungen
(function() {
  'use strict';
  
  const API = 'munitionskauf/munitionskauf_api.php';
  let deleteConfirmModal = null;
  let pendingDeleteData = null;
  let currentFilter = 'year'; // Start mit 'year' statt 'today'
  
  // === Initialization ===
  document.addEventListener('DOMContentLoaded', function() {
    console.log('=== Munitionskauf JS initialized ===');
    
    initYearSelector();
    initDateField();
    loadMitglieder();
    initEventListeners();
    
    // Start direkt mit "year" Filter um alle Daten zu sehen
    setFilter('year');
    
    loadStatistics();
  });
  
  // === Helper Functions ===
  function fmtCHF(cents) {
    return 'CHF ' + ((cents || 0) / 100).toFixed(2);
  }
  
  // === Initialization Functions ===
  function initYearSelector() {
    const sel = document.getElementById('yearSelect');
    sel.innerHTML = '';
    const currentYear = new Date().getFullYear();
    for (let y = currentYear; y >= 2025; y--) {
      const opt = document.createElement('option');
      opt.value = String(y);
      opt.textContent = String(y);
      if (y === currentYear) opt.selected = true;
      sel.appendChild(opt);
    }
    
    document.getElementById('statsYear').textContent = currentYear;
  }
  
  function initDateField() {
    // Set today as default
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('kaufDatum').value = today;
    
    // Set max date to prevent future dates
    document.getElementById('kaufDatum').max = today;
  }
  
  function loadMitglieder() {
    const sel = document.getElementById('mitgliedSelect');
    sel.innerHTML = '<option value="">– Mitglied wählen –</option>';
    
    fetch(`${API}?action=list_mitglieder`)
      .then(r => {
        console.log('Mitglieder response status:', r.status);
        if (!r.ok) throw new Error('Network response was not ok');
        return r.json();
      })
      .then(data => {
        console.log('Mitglieder data:', data);
        if (data.success && data.data) {
          data.data.forEach(m => {
            const label = `${(m.Nachname || m.Name || '').trim()} ${(m.Vorname || '').trim()}`.trim();
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = label;
            sel.appendChild(opt);
          });
        }
      })
      .catch(err => {
        console.error('Error loading mitglieder:', err);
        // Silently fail - user can still use guest input
      });
  }
  
  // === Event Listeners ===
  function initEventListeners() {
    // Form submit
    document.getElementById('munitionForm').addEventListener('submit', function(e) {
      e.preventDefault();
      saveBestellung();
    });
    
    // Reset button
    document.getElementById('btnReset').addEventListener('click', resetForm);
    
    // Mitglied/Gast selection
    document.getElementById('mitgliedSelect').addEventListener('change', function() {
      if (this.value) {
        document.getElementById('gastName').value = '';
      }
    });
    
    document.getElementById('gastName').addEventListener('input', function() {
      if (this.value.trim()) {
        document.getElementById('mitgliedSelect').value = '';
      }
    });
    
    // Year change
    document.getElementById('yearSelect').addEventListener('change', function() {
      document.getElementById('statsYear').textContent = this.value;
      loadBestellungen(currentFilter);
      loadStatistics();
    });
    
    // Filter buttons
    document.getElementById('btnFilterToday').addEventListener('click', () => setFilter('today'));
    document.getElementById('btnFilterWeek').addEventListener('click', () => setFilter('week'));
    document.getElementById('btnFilterMonth').addEventListener('click', () => setFilter('month'));
    document.getElementById('btnFilterYear').addEventListener('click', () => setFilter('year'));
    
    // PDF button
    document.getElementById('btnGeneratePDF').addEventListener('click', generatePDF);
    
    // Paket checkboxes
    document.querySelectorAll('.paket-check').forEach(cb => {
      cb.addEventListener('change', recalcTotal);
    });
    
    // Custom inputs
    document.querySelectorAll('.custom-anzahl').forEach(input => {
      input.addEventListener('input', function() {
        const anzahl = parseInt(this.value) || 0;
        const preis = anzahl * 50; // 50 Rappen pro Schuss
        const preisText = preis > 0 ? 'CHF ' + (preis / 100).toFixed(0) : 'CHF 0';
        
        if (this.id === 'custom_gp11') {
          this.parentElement.querySelector('.custom-preis').textContent = preisText;
        } else if (this.id === 'custom_gp90') {
          this.parentElement.querySelector('.custom-preis').textContent = preisText;
        }
        
        recalcTotal();
      });
    });
    
    // Delete confirmation modal
    if (!deleteConfirmModal) {
      deleteConfirmModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    }
    
    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
      if (pendingDeleteData) {
        deleteConfirmModal.hide();
        executeDelete(pendingDeleteData.id);
        pendingDeleteData = null;
      }
    });
  }
  
  // === Calculation Functions ===
  function recalcTotal() {
    let gp11Total = 0;
    let gp90Total = 0;
    let totalPreis = 0;
    
    // Standard packages
    document.querySelectorAll('.paket-check:checked').forEach(cb => {
      const anzahl = parseInt(cb.dataset.anzahl) || 0;
      const typ = cb.dataset.typ;
      
      if (typ.startsWith('GP11')) {
        gp11Total += anzahl;
      } else if (typ.startsWith('GP90')) {
        gp90Total += anzahl;
      }
      
      totalPreis += anzahl * 50;
    });
    
    // Custom amounts
    const customGP11 = parseInt(document.getElementById('custom_gp11').value) || 0;
    const customGP90 = parseInt(document.getElementById('custom_gp90').value) || 0;
    
    gp11Total += customGP11;
    gp90Total += customGP90;
    totalPreis += (customGP11 + customGP90) * 50;
    
    // Update display
    document.getElementById('total_gp11').textContent = gp11Total;
    document.getElementById('total_gp90').textContent = gp90Total;
    document.getElementById('total_preis').textContent = fmtCHF(totalPreis);
  }
  
  // === Form Functions ===
  function resetForm() {
    document.getElementById('munitionForm').reset();
    
    // Reset date to today
    initDateField();
    
    // Reset custom prices
    document.querySelectorAll('.custom-preis').forEach(el => {
      el.textContent = 'CHF 0';
    });
    
    // Recalc totals
    recalcTotal();
  }
  
  function saveBestellung() {
    console.log('=== SAVE BESTELLUNG START ===');
    
    const mitglied_id = document.getElementById('mitgliedSelect').value;
    const gast_name = document.getElementById('gastName').value.trim();
    const kauf_datum = document.getElementById('kaufDatum').value;
    const anlass = document.getElementById('anlass').value.trim();
    const jahr = document.getElementById('yearSelect').value;
    
    console.log('Form data:', { mitglied_id, gast_name, kauf_datum, anlass, jahr });
    
    // Validation
    if (!mitglied_id && !gast_name) {
      msvToast('Bitte wähle ein Mitglied oder gib einen Gastnamen ein', 'danger');
      return;
    }
    
    if (mitglied_id && gast_name) {
      msvToast('Bitte nur Mitglied ODER Gast auswählen, nicht beides', 'danger');
      return;
    }
    
    if (!kauf_datum) {
      msvToast('Bitte ein Kaufdatum angeben', 'danger');
      return;
    }
    
    // Collect ammunition data
    const munition = [];
    
    // Standard packages
    document.querySelectorAll('.paket-check:checked').forEach(cb => {
      munition.push({
        typ: cb.dataset.typ,
        anzahl: parseInt(cb.dataset.anzahl)
      });
    });
    
    // Custom amounts
    const customGP11 = parseInt(document.getElementById('custom_gp11').value) || 0;
    if (customGP11 > 0) {
      munition.push({ typ: 'GP11_CUSTOM', anzahl: customGP11 });
    }
    
    const customGP90 = parseInt(document.getElementById('custom_gp90').value) || 0;
    if (customGP90 > 0) {
      munition.push({ typ: 'GP90_CUSTOM', anzahl: customGP90 });
    }
    
    console.log('Munition:', munition);
    
    if (munition.length === 0) {
      msvToast('Bitte mindestens eine Munitionsbestellung auswählen', 'danger');
      return;
    }
    
    // Show spinner
    const spinner = document.getElementById('saveSpinner');
    const btn = document.getElementById('btnSave');
    spinner.classList.remove('d-none');
    btn.disabled = true;
    
    // Prepare data
    const csrfToken = document.querySelector('[name="csrf_token"]')?.value || '';
    
    const requestData = {
      jahr: jahr,
      kauf_datum: kauf_datum,
      anlass: anlass,
      munition: munition,
      csrf_token: csrfToken // Token auch im Body mitsenden
    };
    
    if (mitglied_id) {
      requestData.mitglied_id = mitglied_id;
    } else {
      requestData.gast_name = gast_name;
    }
    
    console.log('Request data:', requestData);
    console.log('Sending to:', `${API}?action=save_bestellung`);
    
    // Send to API
    fetch(`${API}?action=save_bestellung`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(requestData)
    })
    .then(r => {
      console.log('Save response status:', r.status);
      if (!r.ok) throw new Error(`Network response was not ok: ${r.status}`);
      return r.json();
    })
    .then(data => {
      console.log('Save response data:', data);
      
      if (data.success) {
        msvToast('Bestellung erfolgreich gespeichert', 'success');
        resetForm();
        
        // WICHTIG: Nach dem Speichern IMMER die aktuelle Ansicht neu laden
        console.log('Reloading with current filter:', currentFilter);
        
        // Kurze Verzögerung damit die Datenbank Zeit hat
        setTimeout(() => {
          console.log('Executing reload now...');
          loadBestellungen(currentFilter);
          loadStatistics();
        }, 100);
        
      } else {
        console.error('Save failed:', data.message);
        msvToast('Fehler: ' + (data.message || 'Unbekannter Fehler'), 'danger');
      }
    })
    .catch(err => {
      console.error('Save error:', err);
      // Detailliertere Fehlermeldung
      if (err.message && err.message.includes('403')) {
        msvToast('Sitzung abgelaufen - bitte Seite neu laden', 'danger');
        // Optional: Nach 2 Sekunden neu laden
        setTimeout(() => {
          window.location.reload();
        }, 2000);
      } else {
        msvToast('Netzwerkfehler beim Speichern: ' + err.message, 'danger');
      }
    })
    .finally(() => {
      console.log('Save request completed');
      spinner.classList.add('d-none');
      btn.disabled = false;
    });
  }
  
  // === Table Functions ===
  function setFilter(filter) {
    console.log('setFilter called with:', filter);
    currentFilter = filter;
    
    // Update button states
    document.querySelectorAll('.btn-group .btn').forEach(btn => {
      btn.classList.remove('active');
    });
    
    const btnId = 'btnFilter' + filter.charAt(0).toUpperCase() + filter.slice(1);
    const activeBtn = document.getElementById(btnId);
    if (activeBtn) {
      activeBtn.classList.add('active');
    }
    
    console.log('Current filter set to:', currentFilter);
    
    loadBestellungen(filter);
  }
  
  function loadBestellungen(filter) {
    const jahr = document.getElementById('yearSelect').value;
    
    const url = `${API}?action=get_bestellungen&jahr=${jahr}&filter=${filter}`;
    console.log('=== LOAD BESTELLUNGEN ===');
    console.log('URL:', url);
    console.log('Filter:', filter);
    console.log('Jahr:', jahr);
    
    fetch(url)
      .then(r => {
        console.log('Load response status:', r.status);
        console.log('Response headers:', r.headers.get('content-type'));
        
        if (!r.ok) {
          throw new Error(`Network response was not ok: ${r.status}`);
        }
        
        // Prüfe ob die Antwort JSON ist
        const contentType = r.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
          console.error('Response is not JSON:', contentType);
          return r.text().then(text => {
            console.error('Response body:', text);
            throw new Error('Response is not JSON');
          });
        }
        
        return r.json();
      })
      .then(data => {
        console.log('Load response data:', data);
        
        if (data.success) {
          console.log('Data items:', data.data?.length || 0);
          console.log('Totals:', data.totals);
          renderBestellungenTable(data.data || [], data.totals || {});
        } else {
          console.error('API returned error:', data.message);
          msvToast('Fehler beim Laden: ' + data.message, 'danger');
          renderBestellungenTable([], {});
        }
      })
      .catch(err => {
        console.error('Error loading bestellungen:', err);
        console.error('Error details:', err.message);
        msvToast('Fehler beim Laden der Daten: ' + err.message, 'danger');
        // Show empty table
        renderBestellungenTable([], {});
      });
  }
  
  function renderBestellungenTable(bestellungen, totals) {
    console.log('=== RENDER TABLE ===');
    console.log('Entries:', bestellungen.length);
    console.log('Data:', bestellungen);
    
    const tbody = document.getElementById('bestellungenTableBody');
    
    if (!tbody) {
      console.error('Table body element not found!');
      return;
    }
    
    if (!bestellungen || bestellungen.length === 0) {
      console.log('No data to display');
      tbody.innerHTML = '<tr><td colspan="7" class="text-muted text-center">Keine Bestellungen gefunden</td></tr>';
      
      // Update footer totals mit default-Werten
      document.getElementById('footerGP11').textContent = '0';
      document.getElementById('footerGP90').textContent = '0';
      document.getElementById('footerPreis').textContent = 'CHF 0.00';
      return;
    }
    
    tbody.innerHTML = '';
    
    bestellungen.forEach((b, index) => {
      console.log(`Rendering row ${index}:`, b);
      
      const tr = document.createElement('tr');
      
      // Format date - füge 'T00:00:00' hinzu um Zeitzonenprobleme zu vermeiden
      const datum = new Date(b.kauf_datum + 'T00:00:00');
      const datumStr = datum.toLocaleDateString('de-CH');
      
      tr.innerHTML = `
        <td>${datumStr}</td>
        <td>${b.kaeufer_name || 'Unbekannt'}</td>
        <td>${b.anlass || '-'}</td>
        <td class="text-center">${b.gp11_total || '-'}</td>
        <td class="text-center">${b.gp90_total || '-'}</td>
        <td class="text-end">${fmtCHF(b.total_preis)}</td>
        <td class="text-center">
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-danger btn-delete-bestellung" 
                    data-id="${b.id}"
                    data-name="${b.kaeufer_name}"
                    data-datum="${datumStr}"
                    title="Löschen">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </td>
      `;
      
      tbody.appendChild(tr);
    });
    
    // Update footer totals
    console.log('Updating totals:', totals);
    if (totals) {
      document.getElementById('footerGP11').textContent = totals.gp11_total || 0;
      document.getElementById('footerGP90').textContent = totals.gp90_total || 0;
      document.getElementById('footerPreis').textContent = fmtCHF(totals.total_preis || 0);
    }
    
    console.log('Table rendered successfully');
  }
  
  // === Delete Functions ===
  document.addEventListener('click', function(e) {
    if (e.target.closest('.btn-delete-bestellung')) {
      e.preventDefault();
      const btn = e.target.closest('.btn-delete-bestellung');
      const id = btn.dataset.id;
      const name = btn.dataset.name;
      const datum = btn.dataset.datum;
      
      showDeleteConfirmation(id, name, datum);
    }
  });
  
  function showDeleteConfirmation(id, name, datum) {
    pendingDeleteData = { id: id };
    
    const message = `Sie sind dabei, die Munitionsbestellung von <strong>${name}</strong> vom ${datum} zu löschen.`;
    document.getElementById('deleteConfirmMessage').innerHTML = message;
    
    deleteConfirmModal.show();
  }
  
  function executeDelete(id) {
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Lösche...';
    
    const csrfToken = document.querySelector('[name="csrf_token"]')?.value || '';
    
    fetch(`${API}?action=delete_bestellung`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ 
        id: id,
        csrf_token: csrfToken // Token auch im Body mitsenden
      })
    })
    .then(r => {
      if (!r.ok) throw new Error('Network response was not ok');
      return r.json();
    })
    .then(data => {
      if (data.success) {
        msvToast('Bestellung erfolgreich gelöscht', 'success');
        loadBestellungen(currentFilter);
        loadStatistics();
      } else {
        msvToast('Fehler beim Löschen: ' + (data.message || 'Unbekannter Fehler'), 'danger');
      }
    })
    .catch(err => {
      console.error('Delete error:', err);
      msvToast('Netzwerkfehler beim Löschen', 'danger');
    })
    .finally(() => {
      confirmBtn.disabled = false;
      confirmBtn.innerHTML = originalText;
    });
  }
  
  // === Statistics Functions ===
  function loadStatistics() {
    const jahr = document.getElementById('yearSelect').value;
    
    console.log('Loading statistics for year:', jahr);
    
    fetch(`${API}?action=get_statistics&jahr=${jahr}`)
      .then(r => {
        if (!r.ok) throw new Error('Network response was not ok');
        return r.json();
      })
      .then(data => {
        if (data.success) {
          updateStatistics(data.data);
        }
      })
      .catch(err => {
        console.error('Error loading statistics:', err);
        // Set default values on error
        updateStatistics({
          today: 0,
          week: 0,
          month: 0,
          year: 0,
          top_buyers: []
        });
      });
  }
  
  function updateStatistics(stats) {
    document.getElementById('statsToday').textContent = fmtCHF(stats.today || 0);
    document.getElementById('statsWeek').textContent = fmtCHF(stats.week || 0);
    document.getElementById('statsMonth').textContent = fmtCHF(stats.month || 0);
    document.getElementById('statsYearTotal').textContent = fmtCHF(stats.year || 0);
    
    // Update top buyers
    const topList = document.getElementById('topKaeuferList');
    if (stats.top_buyers && stats.top_buyers.length > 0) {
      topList.innerHTML = '';
      stats.top_buyers.forEach((buyer, index) => {
        const item = document.createElement('div');
        item.className = 'käufer-item';
        item.innerHTML = `
          <span>${index + 1}. ${buyer.name}</span>
          <strong>${fmtCHF(buyer.total)}</strong>
        `;
        topList.appendChild(item);
      });
    } else {
      topList.innerHTML = '<div class="text-muted">Keine Daten vorhanden</div>';
    }
  }
  
  // === PDF Generation ===
  function generatePDF() {
    const jahr = document.getElementById('yearSelect').value;
    const btn = document.getElementById('btnGeneratePDF');
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generiere...';
    
    const params = new URLSearchParams({
      action: 'generate_pdf',
      jahr: jahr,
      filter: currentFilter
    });
    
    fetch(`munitionskauf/generate_pdf_munition.php?${params}`)
      .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
      })
      .then(data => {
        if (data.pdf_link) {
          window.open(data.pdf_link, '_blank');
          msvToast('PDF wurde erfolgreich generiert', 'success');
        } else if (data.error) {
          msvToast('Fehler: ' + data.error, 'danger');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        msvToast('Fehler beim Generieren des PDFs', 'danger');
      })
      .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
      });
  }
  
  // === Debug Functions ===
  window.munitionDebug = {
    currentFilter: () => currentFilter,
    reload: () => loadBestellungen(currentFilter),
    testLoad: (filter) => loadBestellungen(filter || currentFilter),
    showFilter: () => {
      console.log('Current filter:', currentFilter);
      console.log('Active button:', document.querySelector('.btn-group .btn.active')?.id);
      console.log('Year:', document.getElementById('yearSelect').value);
    }
  };
  
  console.log('Munitionskauf JS loaded. Debug with: window.munitionDebug');
})();
