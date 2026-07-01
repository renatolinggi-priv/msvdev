// munitionskauf.js - Frontend Logic für Munitionsbestellungen (Redesign v2)
(function() {
  'use strict';

  const API = 'munitionskauf/munitionskauf_api.php';
  let currentFilter = 'year';

  // === Helper ===
  function fmtCHF(cents) {
    return 'CHF ' + ((cents || 0) / 100).toFixed(2);
  }

  function fmtCHFShort(cents) {
    const val = (cents || 0) / 100;
    return val >= 1000 ? "CHF " + val.toLocaleString('de-CH', {minimumFractionDigits: 0, maximumFractionDigits: 0}) : fmtCHF(cents);
  }

  // === Initialization ===
  document.addEventListener('DOMContentLoaded', function() {
    initTabs();
    initYearSelector();
    initDateField();
    loadMitglieder();
    initEventListeners();
    initMobileFilterPills();
    setFilter('year');
    loadStatistics();
  });

  // === Tab System ===
  function initTabs() {
    document.querySelectorAll('.msv-tab').forEach(function(tab) {
      tab.addEventListener('click', function() {
        var tabId = this.getAttribute('data-tab');

        // Deaktiviere alle Tabs und Panes
        document.querySelectorAll('.msv-tab').forEach(function(t) { t.classList.remove('active'); });
        document.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('active'); });

        // Aktiviere geklickten Tab
        this.classList.add('active');
        var pane = document.getElementById(tabId);
        if (pane) pane.classList.add('active');
      });
    });
  }

  // === Mobile Filter Pills ===
  function initMobileFilterPills() {
    document.querySelectorAll('#mobileFilterPills .filter-pill[data-filter]').forEach(function(pill) {
      pill.addEventListener('click', function() {
        document.querySelectorAll('#mobileFilterPills .filter-pill').forEach(function(p) { p.classList.remove('active'); });
        this.classList.add('active');
        setFilter(this.getAttribute('data-filter'));
      });
    });

    // Mobile PDF Button
    var pdfBtn = document.getElementById('mobilePdfBtn');
    if (pdfBtn) {
      pdfBtn.addEventListener('click', generatePDF);
    }

    // Mobile Search
    var searchInput = document.getElementById('mobileSearchInput');
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        filterMobileCards(this.value.toLowerCase());
      });
    }
  }

  function filterMobileCards(term) {
    document.querySelectorAll('#mobileCardsContainer .mobile-kauf-card').forEach(function(card) {
      var text = card.textContent.toLowerCase();
      card.style.display = text.includes(term) ? '' : 'none';
    });
  }

  // === Year/Date ===
  function initYearSelector() {
    var sel = document.getElementById('yearSelect');
    sel.innerHTML = '';
    var currentYear = new Date().getFullYear();
    for (var y = currentYear; y >= currentYear - 3; y--) {
      var opt = document.createElement('option');
      opt.value = String(y);
      opt.textContent = String(y);
      if (y === currentYear) opt.selected = true;
      sel.appendChild(opt);
    }
    document.getElementById('statsYear').textContent = currentYear;
  }

  function initDateField() {
    var today = new Date().toISOString().split('T')[0];
    var field = document.getElementById('kaufDatum');
    field.value = today;
    field.max = today;
  }

  // === Mitglieder ===
  function loadMitglieder() {
    var sel = document.getElementById('mitgliedSelect');
    sel.innerHTML = '<option value="">– Mitglied wählen –</option>';

    fetch(API + '?action=list_mitglieder')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.data) {
          data.data.forEach(function(m) {
            var label = ((m.Nachname || m.Name || '') + ' ' + (m.Vorname || '')).trim();
            var opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = label;
            sel.appendChild(opt);
          });
        }
        applyMitgliedSelect2();
      })
      .catch(function(err) { console.error('Mitglieder laden:', err); });
  }

  // Select2 auf dem Mitglied-Dropdown aufsetzen (nach dem Befüllen)
  function applyMitgliedSelect2() {
    if (!window.jQuery || !jQuery.fn.select2) return;
    var $sel = jQuery('#mitgliedSelect');
    if ($sel.hasClass('select2-hidden-accessible')) {
      $sel.select2('destroy');
    }
    $sel.select2({
      theme: 'bootstrap-5',
      width: '100%',
      placeholder: '– Mitglied wählen –',
      allowClear: true,
      dropdownCssClass: 'select2-mitglied-dropdown',
      language: {
        noResults: function () { return 'Keine Treffer'; },
        searching: function () { return 'Suche…'; }
      }
    });
    // Gegenseitiger Ausschluss mit Gast – Select2 feuert ein jQuery-change-Event
    $sel.on('change', function() {
      if (this.value) document.getElementById('gastName').value = '';
    });
  }

  // === Event Listeners ===
  function initEventListeners() {
    // Form submit
    document.getElementById('munitionForm').addEventListener('submit', function(e) {
      e.preventDefault();
      saveBestellung();
    });

    // Reset
    document.getElementById('btnReset').addEventListener('click', resetForm);

    // Mitglied/Gast gegenseitig ausschliessen
    document.getElementById('mitgliedSelect').addEventListener('change', function() {
      if (this.value) document.getElementById('gastName').value = '';
    });
    document.getElementById('gastName').addEventListener('input', function() {
      if (this.value.trim()) {
        document.getElementById('mitgliedSelect').value = '';
        if (window.jQuery && jQuery.fn.select2) {
          jQuery('#mitgliedSelect').val('').trigger('change.select2');
        }
      }
    });

    // Year change
    document.getElementById('yearSelect').addEventListener('change', function() {
      document.getElementById('statsYear').textContent = this.value;
      loadBestellungen(currentFilter);
      loadStatistics();
    });

    // Desktop Filter buttons
    document.getElementById('btnFilterToday').addEventListener('click', function() { setFilter('today'); });
    document.getElementById('btnFilterWeek').addEventListener('click', function() { setFilter('week'); });
    document.getElementById('btnFilterMonth').addEventListener('click', function() { setFilter('month'); });
    document.getElementById('btnFilterYear').addEventListener('click', function() { setFilter('year'); });

    // PDF
    document.getElementById('btnGeneratePDF').addEventListener('click', generatePDF);

    // Paket checkboxes
    document.querySelectorAll('.paket-check').forEach(function(cb) {
      cb.addEventListener('change', recalcTotal);
    });

    // Custom inputs
    document.querySelectorAll('.custom-anzahl').forEach(function(input) {
      input.addEventListener('input', function() {
        var anzahl = parseInt(this.value) || 0;
        var preis = anzahl * 50;
        var preisText = preis > 0 ? 'CHF ' + (preis / 100).toFixed(0) : 'CHF 0';
        this.parentElement.querySelector('.custom-preis').textContent = preisText;
        recalcTotal();
      });
    });

    // Delete via event delegation (SweetAlert2)
    document.addEventListener('click', function(e) {
      var btn = e.target.closest('.btn-delete-bestellung');
      if (btn) {
        e.preventDefault();
        var id = btn.dataset.id;
        var name = btn.dataset.name || 'diese Bestellung';
        var datum = btn.dataset.datum || '';

        msvConfirmDelete(name + (datum ? ' (' + datum + ')' : '')).then(function(result) {
          if (result.isConfirmed) {
            executeDelete(id);
          }
        });
      }
    });
  }

  // === Calculation ===
  function recalcTotal() {
    var gp11 = 0, gp90 = 0, preis = 0;

    document.querySelectorAll('.paket-check:checked').forEach(function(cb) {
      var anz = parseInt(cb.dataset.anzahl) || 0;
      if (cb.dataset.typ.indexOf('GP11') !== -1) gp11 += anz;
      else if (cb.dataset.typ.indexOf('GP90') !== -1) gp90 += anz;
      preis += anz * 50;
    });

    var cGP11 = parseInt(document.getElementById('custom_gp11').value) || 0;
    var cGP90 = parseInt(document.getElementById('custom_gp90').value) || 0;
    gp11 += cGP11;
    gp90 += cGP90;
    preis += (cGP11 + cGP90) * 50;

    document.getElementById('total_gp11').textContent = gp11;
    document.getElementById('total_gp90').textContent = gp90;
    document.getElementById('total_preis').textContent = fmtCHF(preis);
  }

  // === Form ===
  function resetForm() {
    document.getElementById('munitionForm').reset();
    initDateField();
    document.querySelectorAll('.custom-preis').forEach(function(el) { el.textContent = 'CHF 0'; });
    recalcTotal();
    // Select2-Anzeige nach reset() nachziehen (native reset aktualisiert Select2 nicht)
    if (window.jQuery && jQuery.fn.select2) {
      jQuery('#mitgliedSelect').val('').trigger('change.select2');
    }
  }

  function saveBestellung() {
    var mitglied_id = document.getElementById('mitgliedSelect').value;
    var gast_name = document.getElementById('gastName').value.trim();
    var kauf_datum = document.getElementById('kaufDatum').value;
    var anlass = document.getElementById('anlass').value.trim();
    var jahr = document.getElementById('yearSelect').value;

    if (!mitglied_id && !gast_name) {
      msvToast('Bitte Mitglied wählen oder Gast-Name eingeben', 'danger');
      return;
    }
    if (mitglied_id && gast_name) {
      msvToast('Bitte nur Mitglied ODER Gast, nicht beides', 'danger');
      return;
    }
    if (!kauf_datum) {
      msvToast('Bitte Kaufdatum angeben', 'danger');
      return;
    }

    var munition = [];
    document.querySelectorAll('.paket-check:checked').forEach(function(cb) {
      munition.push({ typ: cb.dataset.typ, anzahl: parseInt(cb.dataset.anzahl) });
    });

    var cGP11 = parseInt(document.getElementById('custom_gp11').value) || 0;
    if (cGP11 > 0) munition.push({ typ: 'GP11_CUSTOM', anzahl: cGP11 });

    var cGP90 = parseInt(document.getElementById('custom_gp90').value) || 0;
    if (cGP90 > 0) munition.push({ typ: 'GP90_CUSTOM', anzahl: cGP90 });

    if (munition.length === 0) {
      msvToast('Bitte mindestens eine Munition auswählen', 'danger');
      return;
    }

    var spinner = document.getElementById('saveSpinner');
    var btn = document.getElementById('btnSave');
    spinner.classList.remove('d-none');
    btn.disabled = true;

    var csrfToken = document.querySelector('[name="csrf_token"]').value || '';
    var requestData = {
      jahr: jahr,
      kauf_datum: kauf_datum,
      anlass: anlass,
      munition: munition,
      csrf_token: csrfToken
    };

    if (mitglied_id) requestData.mitglied_id = mitglied_id;
    else requestData.gast_name = gast_name;

    fetch(API + '?action=save_bestellung', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify(requestData)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        msvToast('Bestellung erfolgreich gespeichert', 'success');
        resetForm();
        setTimeout(function() {
          loadBestellungen(currentFilter);
          loadStatistics();
        }, 100);
      } else {
        msvToast('Fehler: ' + (data.message || 'Unbekannt'), 'danger');
      }
    })
    .catch(function(err) {
      if (err.message && err.message.indexOf('403') !== -1) {
        msvToast('Sitzung abgelaufen – Seite wird neu geladen', 'danger');
        setTimeout(function() { window.location.reload(); }, 2000);
      } else {
        msvToast('Netzwerkfehler: ' + err.message, 'danger');
      }
    })
    .finally(function() {
      spinner.classList.add('d-none');
      btn.disabled = false;
    });
  }

  // === Filter ===
  function setFilter(filter) {
    currentFilter = filter;

    // Desktop buttons
    document.querySelectorAll('#btnFilterToday, #btnFilterWeek, #btnFilterMonth, #btnFilterYear').forEach(function(btn) {
      btn.classList.remove('active');
    });
    var btnMap = { today: 'btnFilterToday', week: 'btnFilterWeek', month: 'btnFilterMonth', year: 'btnFilterYear' };
    var activeBtn = document.getElementById(btnMap[filter]);
    if (activeBtn) activeBtn.classList.add('active');

    // Mobile pills
    document.querySelectorAll('#mobileFilterPills .filter-pill[data-filter]').forEach(function(p) {
      p.classList.toggle('active', p.getAttribute('data-filter') === filter);
    });

    loadBestellungen(filter);
  }

  // === Load Bestellungen ===
  function loadBestellungen(filter) {
    var jahr = document.getElementById('yearSelect').value;

    fetch(API + '?action=get_bestellungen&jahr=' + jahr + '&filter=' + filter)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) {
          renderDesktopTable(data.data || [], data.totals || {});
          renderMobileCards(data.data || [], data.totals || {});
        } else {
          msvToast('Fehler: ' + data.message, 'danger');
          renderDesktopTable([], {});
          renderMobileCards([], {});
        }
      })
      .catch(function(err) {
        console.error('Load error:', err);
        renderDesktopTable([], {});
        renderMobileCards([], {});
      });
  }

  // === Desktop Table ===
  function renderDesktopTable(bestellungen, totals) {
    var tbody = document.getElementById('bestellungenTableBody');
    if (!tbody) return;

    if (!bestellungen || bestellungen.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-muted text-center">Keine Bestellungen gefunden</td></tr>';
      document.getElementById('footerGP11').textContent = '0';
      document.getElementById('footerGP90').textContent = '0';
      document.getElementById('footerPreis').textContent = 'CHF 0.00';
      return;
    }

    tbody.innerHTML = '';
    bestellungen.forEach(function(b) {
      var datum = new Date(b.kauf_datum + 'T00:00:00');
      var datumStr = datum.toLocaleDateString('de-CH');
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td>' + datumStr + '</td>' +
        '<td>' + (b.kaeufer_name || 'Unbekannt') + '</td>' +
        '<td>' + (b.anlass || '–') + '</td>' +
        '<td class="text-center">' + (b.gp11_total || '–') + '</td>' +
        '<td class="text-center">' + (b.gp90_total || '–') + '</td>' +
        '<td class="text-end">' + fmtCHF(b.total_preis) + '</td>' +
        '<td class="text-center">' +
          '<button class="btn btn-outline-danger btn-sm btn-delete-bestellung" ' +
            'data-id="' + b.id + '" data-name="' + (b.kaeufer_name || '') + '" data-datum="' + datumStr + '" ' +
            'style="padding:0.15rem 0.35rem;">' +
            '<i class="bi bi-trash"></i>' +
          '</button>' +
        '</td>';
      tbody.appendChild(tr);
    });

    document.getElementById('footerGP11').textContent = totals.gp11_total || 0;
    document.getElementById('footerGP90').textContent = totals.gp90_total || 0;
    document.getElementById('footerPreis').textContent = fmtCHF(totals.total_preis || 0);
  }

  // === Mobile Cards ===
  function renderMobileCards(bestellungen, totals) {
    var container = document.getElementById('mobileCardsContainer');
    if (!container) return;

    if (!bestellungen || bestellungen.length === 0) {
      container.innerHTML = '<div class="text-muted text-center py-3"><i class="bi bi-inbox" style="font-size: 2rem;"></i><div class="mt-2">Keine Bestellungen</div></div>';
      updateMobileFooter({});
      return;
    }

    container.innerHTML = '';
    bestellungen.forEach(function(b) {
      var datum = new Date(b.kauf_datum + 'T00:00:00');
      var datumStr = datum.toLocaleDateString('de-CH');
      var card = document.createElement('div');
      card.className = 'mobile-kauf-card';
      card.innerHTML =
        '<div class="mobile-kauf-card-header">' +
          '<div>' +
            '<div class="mobile-kauf-card-title">' + (b.kaeufer_name || 'Unbekannt') + '</div>' +
            '<div class="mobile-kauf-card-subtitle">' + datumStr + (b.anlass ? ' · ' + b.anlass : '') + '</div>' +
          '</div>' +
          '<span class="mobile-kauf-card-badge">' + fmtCHF(b.total_preis) + '</span>' +
        '</div>' +
        '<div class="mobile-kauf-card-body">' +
          '<div class="mobile-kauf-card-row"><span style="color:var(--secondary-color)">GP11</span><strong>' + (b.gp11_total || '–') + '</strong></div>' +
          '<div class="mobile-kauf-card-row"><span style="color:var(--secondary-color)">GP90</span><strong>' + (b.gp90_total || '–') + '</strong></div>' +
        '</div>' +
        '<div class="mobile-kauf-card-actions">' +
          '<button class="btn btn-outline-danger btn-sm btn-delete-bestellung" ' +
            'data-id="' + b.id + '" data-name="' + (b.kaeufer_name || '') + '" data-datum="' + datumStr + '" ' +
            'style="min-height:36px; font-size:14px;">' +
            '<i class="bi bi-trash me-1"></i>Löschen</button>' +
        '</div>';
      container.appendChild(card);
    });

    updateMobileFooter(totals);
  }

  function updateMobileFooter(totals) {
    var gp11El = document.getElementById('mobileFooterGP11');
    var gp90El = document.getElementById('mobileFooterGP90');
    var preisEl = document.getElementById('mobileFooterPreis');
    if (gp11El) gp11El.textContent = totals.gp11_total || 0;
    if (gp90El) gp90El.textContent = totals.gp90_total || 0;
    if (preisEl) preisEl.textContent = fmtCHF(totals.total_preis || 0);
  }

  // === Delete ===
  function executeDelete(id) {
    var csrfToken = document.querySelector('[name="csrf_token"]').value || '';

    fetch(API + '?action=delete_bestellung', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({ id: id, csrf_token: csrfToken })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        msvToast('Bestellung gelöscht', 'success');
        loadBestellungen(currentFilter);
        loadStatistics();
      } else {
        msvToast('Fehler: ' + (data.message || 'Unbekannt'), 'danger');
      }
    })
    .catch(function(err) {
      msvToast('Netzwerkfehler beim Löschen', 'danger');
    });
  }

  // === Statistics ===
  function loadStatistics() {
    var jahr = document.getElementById('yearSelect').value;

    fetch(API + '?action=get_statistics&jahr=' + jahr)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success) updateStatistics(data.data);
      })
      .catch(function(err) {
        console.error('Stats error:', err);
        updateStatistics({ today: 0, week: 0, month: 0, year: 0, top_buyers: [] });
      });
  }

  function updateStatistics(stats) {
    document.getElementById('statsToday').textContent = fmtCHF(stats.today || 0);
    document.getElementById('statsWeek').textContent = fmtCHF(stats.week || 0);
    document.getElementById('statsMonth').textContent = fmtCHF(stats.month || 0);
    document.getElementById('statsYearTotal').textContent = fmtCHF(stats.year || 0);

    // Top Käufer
    var topList = document.getElementById('topKaeuferList');
    if (stats.top_buyers && stats.top_buyers.length > 0) {
      topList.innerHTML = '';
      stats.top_buyers.forEach(function(buyer, i) {
        var item = document.createElement('div');
        item.className = 'top-buyer-item';
        item.innerHTML =
          '<span><span class="top-buyer-rank">' + (i + 1) + '</span>' + buyer.name + '</span>' +
          '<strong class="small">' + fmtCHF(buyer.total) + '</strong>';
        topList.appendChild(item);
      });
    } else {
      topList.innerHTML = '<div class="text-muted small">Keine Daten</div>';
    }

    // Ammo Summary (aus Totals berechnen – wir nutzen die year-Statistik)
    // Hierfür brauchen wir die Jahres-Bestellungen-Totals
    updateAmmoSummary();
  }

  function updateAmmoSummary() {
    var jahr = document.getElementById('yearSelect').value;

    fetch(API + '?action=get_bestellungen&jahr=' + jahr + '&filter=year')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.success && data.totals) {
          var gp11 = data.totals.gp11_total || 0;
          var gp90 = data.totals.gp90_total || 0;

          document.getElementById('ammoGP11').textContent = Number(gp11).toLocaleString('de-CH');
          document.getElementById('ammoGP90').textContent = Number(gp90).toLocaleString('de-CH');
          document.getElementById('ammoGP11Detail').textContent = 'Schuss · ' + fmtCHF(gp11 * 50);
          document.getElementById('ammoGP90Detail').textContent = 'Schuss · ' + fmtCHF(gp90 * 50);
        }
      })
      .catch(function() {});
  }

  // === PDF ===
  function generatePDF() {
    var jahr = document.getElementById('yearSelect').value;
    var btn = document.getElementById('btnGeneratePDF');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>PDF...';
    }

    var params = new URLSearchParams({ action: 'generate_pdf', jahr: jahr, filter: currentFilter });

    fetch('munitionskauf/generate_pdf_munition.php?' + params)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.pdf_link) {
          window.open(data.pdf_link, '_blank');
          msvToast('PDF generiert', 'success');
        } else if (data.error) {
          msvToast('Fehler: ' + data.error, 'danger');
        }
      })
      .catch(function(err) {
        msvToast('Fehler beim PDF-Export', 'danger');
      })
      .finally(function() {
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-file-earmark-pdf me-1"></i>PDF';
        }
      });
  }

})();
