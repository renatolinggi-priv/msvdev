// endsch_import/import_manager.js
// Steuert Programmauswahl, Vorschau und den eigentlichen Import

const ImportManagerSingle = {
  selectedIndex: null,

  get stich() { return document.getElementById('endschImportApp')?.dataset.stich || ''; },
  get jahr()  { return $('#jahrSelect').val(); },
  get mitgliedId() { return $('#mitgliedSelect').val(); },

  // ===== Initialisierung =====
  init() {
    console.log('[ENDSCH-DEBUG] ImportManagerSingle.init() called');
    // Initialisierung falls nötig - aktuell keine speziellen Setup-Schritte erforderlich
    return true;
  },

  // ===== Keine Programm-Auswahl mehr nötig - alle werden importiert =====
  // Diese Funktionen bleiben für Kompatibilität, tun aber nichts
  toggleProgram(index) {
    // Nichts zu tun - alle Programme werden immer importiert
  },

  updateProgramSelection(index, isSelected) {
    // Nichts zu tun - alle Programme werden immer importiert
  },

  updateProceedButton() {
    // Button ist immer aktiviert wenn Programme vorhanden sind
    const btnProceed = $('#proceedToImportBtn');
    if (btnProceed.length > 0) {
      btnProceed.prop('disabled', false).removeClass('disabled');
    }
  },

  // ===== Auswahl einer Programmzeile (OPTIONAL - nur für visuelle Hervorhebung) =====
  select(i) {
    this.selectedIndex = i;
    // Alle deselektieren
    $('.program-row').removeClass('selected');
    // Gewählte selektieren  
    $(`.program-row[data-index=${i}]`).addClass('selected');
    // Button ist bereits aktiviert, nichts zu tun
  },

  // ===== Direkt zum Import-Modal springen =====
  showPreview() {
    const allPrograms = window._allProgramsForSingle || [];
    const mitgliedId = this.mitgliedId;
    const jahr = this.jahr || new Date().getFullYear();
    
    // Prüfe ob Programme vorhanden
    if (allPrograms.length === 0) {
      UIHelper.showToast('Keine Programme zum Import gefunden', 'warning');
      return;
    }
    
    // Prüfe ob Mitglied ausgewählt
    if (!mitgliedId) {
      UIHelper.showToast('Bitte zuerst ein Mitglied wählen.', 'warning');
      return;
    }
    
    // Direkt executeImport aufrufen, was das Modal zeigt
    this.executeImport(false);
  },

  // ===== Import ALLER Programme durchführen =====
  async executeImport(forceOverwrite = false, additionalParams = {}) {
    const allPrograms = window._allProgramsForSingle || [];
    const mitgliedId = this.mitgliedId;
    const jahr = this.jahr || new Date().getFullYear();

    if (!mitgliedId) {
      UIHelper.showToast('Bitte zuerst ein Mitglied wählen.', 'warning');
      return;
    }

    if (allPrograms.length === 0) {
      UIHelper.showToast('Keine Programme zum Import gefunden.', 'warning');
      return;
    }

    // Prüfe auf bestehende Daten und zeige immer Bestätigungsmodal
    if (!forceOverwrite) {
      try {
        const checkRes = await fetch(`endsch_import/import_handler.php?action=check_existing_data&mitglied_id=${mitgliedId}&jahr=${jahr}`, {
          cache: 'no-store'
        });
        const checkData = await checkRes.json();
        
        // Zeige Bestätigungsmodal (auch wenn keine bestehenden Daten)
        this.showImportModal(
          checkData.success ? checkData.existing_stiche : [],
          allPrograms.length,
          checkData.success ? checkData.detailed_data : {}
        );
        return; // Stoppe hier, Modal übernimmt
      } catch (e) {
        console.error('[ENDSCH-DEBUG] Error checking existing data:', e);
        // Wenn Prüfung fehlschlägt, zeige trotzdem Modal
        this.showImportModal([], allPrograms.length, {});
        return;
      }
    }

    console.log('[ENDSCH-DEBUG] Starting bulk import for', allPrograms.length, 'programs');
    console.log('[ENDSCH-DEBUG] Member ID:', mitgliedId, 'Jahr:', jahr);

    // Button während Import deaktivieren
    $('#prepareImportBtn').prop('disabled', true).text('Importiere...');
    
    let successCount = 0;
    let errorCount = 0;
    const importResults = [];

    // Alle Programme nacheinander importieren (außer ausgeschlossene)
    for (let i = 0; i < allPrograms.length; i++) {
      // Überspringe ausgeschlossene Programme
      if (window._excludedPrograms && window._excludedPrograms.has(i)) {
        console.log(`[ENDSCH-DEBUG] Skipping excluded program at index ${i}`);
        continue;
      }
      
      const prog = allPrograms[i];
      
      try {
        // Für Endstich: beide Wertungen übertragen
        let shots, tiefschussWerte = null;
        
        if (prog.restable === 'endstich') {
          console.log(`[ENDSCH-DEBUG] Processing Endstich data:`, prog.data);
          
          // Debug: zeige alle Datenfelder für den ersten Schuss
          if (prog.data && prog.data.length > 0) {
            console.log(`[ENDSCH-DEBUG] First shot data structure:`, prog.data[0]);
          }
          
          // Normale Wertungen für Schüsse
          shots = (prog.data || [])
            .map(d => {
              const normalWert = parseInt(d.normalWertung || d.wertung, 10);
              console.log(`[ENDSCH-DEBUG] Shot normalWertung:`, d.normalWertung, 'wertung:', d.wertung, 'result:', normalWert);
              return normalWert;
            })
            .filter(n => Number.isFinite(n));
          
          // 100er Wertungen für Tiefschuss-Berechnung
          tiefschussWerte = (prog.data || [])
            .map(d => {
              const tiefWert = parseInt(d.hunderterWertung || d.tiefschussWertung, 10);
              console.log(`[ENDSCH-DEBUG] Shot hunderterWertung:`, d.hunderterWertung, 'tiefschussWertung:', d.tiefschussWertung, 'result:', tiefWert);
              return tiefWert;
            })
            .filter(n => Number.isFinite(n));
        } else {
          // Für andere Stiche: normale Logik
          shots = (prog.data || [])
            .map(d => parseInt(d.wertung, 10))
            .filter(n => Number.isFinite(n));
        }

        console.log(`[ENDSCH-DEBUG] Final shots for ${prog.stich} (Program ${prog.number}):`, shots);
        if (tiefschussWerte) {
          console.log(`[ENDSCH-DEBUG] Final tiefschuss-Werte für ${prog.stich}:`, tiefschussWerte);
        }

        const payload = new URLSearchParams();
        payload.set('action', 'import_stich_shots');
        
        // CSRF Token richtig referenzieren - es ist als globale Konstante definiert
        const csrfToken = typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '';
        payload.set('csrf_token', csrfToken);
        payload.set('mitglied_id', mitgliedId);
        payload.set('jahr', jahr);
        payload.set('program_number', prog.number);
        payload.set('shots', JSON.stringify(shots));
        
        // Zusätzlich für Endstich: Tiefschuss-Werte
        if (tiefschussWerte) {
          payload.set('tiefschuss_werte', JSON.stringify(tiefschussWerte));
        }
        
        // Zusätzliche Parameter je nach Stich hinzufügen
        if (prog.restable === 'zabig' && additionalParams.zabigAnsage !== undefined) {
          payload.set('zabig_ansage', additionalParams.zabigAnsage);
        }
        if (prog.restable === 'endstich' && additionalParams.endstichAbsendenAnmeldung !== undefined) {
          payload.set('endstich_absenden_anmeldung', additionalParams.endstichAbsendenAnmeldung);
        }

        console.log('[ENDSCH-DEBUG] CSRF Token being sent:', csrfToken);
        console.log('[ENDSCH-DEBUG] Token length:', csrfToken.length);

        const res = await fetch('endsch_import/import_handler.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: payload.toString(),
          cache: 'no-store',
        });
        const data = await res.json();

        if (data?.success) {
          successCount++;
          importResults.push(`✓ ${prog.stich}`);
          console.log(`[ENDSCH-DEBUG] Successfully imported ${prog.stich}`);
          
          // Programm als importiert markieren
          $(`.program-selection-card[data-index=${i}]`).addClass('imported').css('opacity', '0.6');
        } else {
          errorCount++;
          importResults.push(`✗ ${prog.stich}: ${data?.message || 'Fehler'}`);
          console.error(`[ENDSCH-DEBUG] Failed to import ${prog.stich}:`, data?.message);
        }
      } catch (e) {
        errorCount++;
        importResults.push(`✗ ${prog.stich}: Netzwerkfehler`);
        console.error(`[ENDSCH-DEBUG] Network error importing ${prog.stich}:`, e);
      }
    }

    // Zusammenfassung anzeigen
    const memberName = $('#mitgliedSelect option:selected').text();
    let importSuccessful = false;
    
    if (successCount > 0 && errorCount === 0) {
      UIHelper.showToast(`Alle ${successCount} Programme erfolgreich für ${memberName} importiert!`, 'success');
      importSuccessful = true;
    } else if (successCount > 0 && errorCount > 0) {
      UIHelper.showToast(`${successCount} Programme importiert, ${errorCount} Fehler. Details in Console.`, 'warning');
      console.log('[ENDSCH-DEBUG] Import results:', importResults);
      importSuccessful = true; // Teilweise erfolgreich
    } else {
      UIHelper.showToast(`Import fehlgeschlagen (${errorCount} Fehler). Details in Console.`, 'error');
      console.log('[ENDSCH-DEBUG] Import results:', importResults);
    }

    // Button wieder aktivieren
    $('#prepareImportBtn').prop('disabled', false).html('<i class="bi bi-download me-2"></i>Importieren');
    
    // Nach erfolgreichem Import fragen, ob weiteres File geladen werden soll
    if (importSuccessful) {
      setTimeout(() => {
        this.showNextFileQuestion(successCount, memberName);
      }, 1500); // Kurze Verzögerung damit Toast sichtbar ist
    }
  },
  
  // Neue Methode: Frage nach weiterem File
  showNextFileQuestion(successCount, memberName) {
    const modalHtml = `
      <div class="modal fade" id="nextFileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header bg-success text-white">
              <h5 class="modal-title">
                <i class="bi bi-check-circle me-2"></i>
                Import erfolgreich
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="text-center mb-4">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                <h4 class="mt-3">${successCount} Programme importiert</h4>
                <p class="text-muted">für ${memberName}</p>
              </div>
              
              <div class="alert alert-info">
                <i class="bi bi-question-circle me-2"></i>
                <strong>Möchten Sie eine weitere CSV-Datei importieren?</strong>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-2"></i>Nein, fertig
              </button>
              <button type="button" class="btn btn-primary" id="loadNextFileBtn">
                <i class="bi bi-file-earmark-plus me-2"></i>Ja, weitere Datei laden
              </button>
            </div>
          </div>
        </div>
      </div>
    `;
    
    // Modal hinzufügen
    $('#nextFileModal').remove();
    $('body').append(modalHtml);
    
    // Event-Handler für "Ja, weitere Datei" - mit .off() Cleanup
    $('#loadNextFileBtn').off('click.nextFile').on('click.nextFile', () => {
      $('#nextFileModal').modal('hide');

      // Zurück zu Phase 1 für neuen Upload
      setTimeout(() => {
        // Reset und zurück zur Upload-Phase
        if (typeof WorkflowHelper !== 'undefined') {
          WorkflowHelper.goToPhase(1);
        } else if (typeof FileHandler !== 'undefined') {
          FileHandler.resetUpload();
        } else {
          // Fallback: Direkt Phase umschalten
          document.getElementById('phase1').style.display = 'block';
          document.getElementById('phase2').style.display = 'none';
        }

        // File Input zurücksetzen
        $('#fileInput').val('');

        // Global storage zurücksetzen
        window._allProgramsForSingle = [];

        UIHelper.showToast('Bereit für neue CSV-Datei', 'info');
      }, 300);
    });
    
    // Modal anzeigen
    $('#nextFileModal').modal('show');
    
    // Aufräumen beim Schließen - .one() für einmalige Ausführung
    $('#nextFileModal').one('hidden.bs.modal', function () {
      $('.modal-backdrop').remove();
      $('body').removeClass('modal-open').css('padding-right', '');
      $('#nextFileModal').remove(); // Entfernt auch Event-Handler!
    });
  },

  // ===== Modal für Import-Bestätigung (mit zusätzlichen Eingaben) =====
  showImportModal(existingStiche, programCount, detailedData = {}) {
    // Debug-Ausgabe
    console.log('[ENDSCH-DEBUG] showImportModal called with:', {
      existingStiche: existingStiche,
      programCount: programCount,
      detailedData: detailedData
    });
    
    const memberName = $('#mitgliedSelect option:selected').text();
    const allPrograms = window._allProgramsForSingle || [];
    
    // FIX: Sicherstellen dass existingStiche ein Array ist
    if (!Array.isArray(existingStiche)) {
      console.log('[ENDSCH-DEBUG] existingStiche was not an array, converting:', existingStiche);
      existingStiche = [];
    }
    const hasExistingData = (existingStiche.length > 0);
    
    // Sammle alle zu importierenden Stiche (für Eingabefelder)
    const programsToImport = new Set();
    allPrograms.forEach(prog => {
      if (prog.restable === 'schwini') {
        programsToImport.add('Schwini');
      } else {
        programsToImport.add(prog.stich);
      }
    });
    
    // Vergleichstabelle erstellen (nur wenn bestehende Daten vorhanden) - VERBESSERTE VERSION MIT ACCORDION
    let comparisonHtml = '';
    if (hasExistingData) {
      // Gruppiere Programme nach Stich für bessere Übersicht
      const groupedByStich = {};
      allPrograms.forEach(prog => {
        const stich = prog.stich || 'Unbekannt';
        if (!groupedByStich[stich]) groupedByStich[stich] = [];
        groupedByStich[stich].push(prog);
      });
      
      comparisonHtml = `
        <div class="accordion" id="compareAccordion">`;
      
      let accordionIndex = 0;
      Object.keys(groupedByStich).forEach(stich => {
        const stichPrograms = groupedByStich[stich];
        
        // Stich-spezifische Icons (ohne bunte Farben)
        const stichConfig = {
        'Endstich': { icon: 'bi-bullseye', color: 'secondary' },
        'Kunst': { icon: 'bi-palette', color: 'secondary' },
        'Glück': { icon: 'bi-star', color: 'secondary' },
        'Zabig': { icon: 'bi-arrow-up-circle', color: 'secondary' },
        'Schwini': { icon: 'bi-chevron-double-up', color: 'secondary' },
          'Sie und Er': { icon: 'bi-people', color: 'secondary' }
      };
        const config = stichConfig[stich] || { icon: 'bi-target', color: 'secondary' };
        
        // Berechne Totals
        const newTotal = stichPrograms.reduce((sum, prog) => sum + (prog.total || 0), 0);
        let existingTotal = 0;
        
        // Hole existierende Daten für diesen Stich
        let existingDisplay = '';
        if (stich === 'Schwini') {
          // Schwini hat P1 und P2
          if (detailedData['Schwini P1']) {
            existingTotal += parseInt(detailedData['Schwini P1'].total || 0);
            existingDisplay += `P1: ${detailedData['Schwini P1'].display || 'N/A'}<br>`;
          }
          if (detailedData['Schwini P2']) {
            existingTotal += parseInt(detailedData['Schwini P2'].total || 0);
            existingDisplay += `P2: ${detailedData['Schwini P2'].display || 'N/A'}`;
          }
        } else if (detailedData[stich]) {
          existingTotal = parseInt(detailedData[stich].total || 0);
          existingDisplay = detailedData[stich].display || 'N/A';
        }
        
        comparisonHtml += `
          <div class="accordion-item">
            <h2 class="accordion-header" id="compareHeading${accordionIndex}">
              <button class="accordion-button ${accordionIndex > 0 ? 'collapsed' : ''}" type="button" 
                      data-bs-toggle="collapse" data-bs-target="#compareCollapse${accordionIndex}" 
                      aria-expanded="${accordionIndex === 0 ? 'true' : 'false'}">
                <span class="d-flex align-items-center w-100">
                  <i class="bi ${config.icon} text-${config.color} me-2 fs-5"></i>
                  <strong>${stich}</strong>
                  <span class="badge bg-${config.color} ms-2">${stichPrograms.length} ${stichPrograms.length === 1 ? 'Neu' : 'Neue'}</span>
                  <span class="ms-auto">
                    <span class="badge bg-danger me-2">Alt: ${existingTotal}</span>
                    <span class="badge bg-success">Neu: ${newTotal}</span>
                  </span>
                </span>
              </button>
            </h2>
            <div id="compareCollapse${accordionIndex}" class="accordion-collapse collapse ${accordionIndex === 0 ? 'show' : ''}" 
                 data-bs-parent="#compareAccordion">
              <div class="accordion-body p-2">`;
        
        // Zeige bestehende Daten
        if (existingDisplay) {
          comparisonHtml += `
            <div class="card mb-2 border-danger border-opacity-25">
              <div class="card-header bg-danger bg-opacity-10">
                <i class="bi bi-database text-danger me-2"></i>
                <strong>Aktuelle Daten (werden überschrieben)</strong>
              </div>
              <div class="card-body p-2">
                <div class="text-danger">${existingDisplay}</div>
              </div>
            </div>`;
        }
        
        // Zeige neue Daten
        comparisonHtml += `
            <div class="card border-success border-opacity-25">
              <div class="card-header bg-success bg-opacity-10">
                <i class="bi bi-download text-success me-2"></i>
                <strong>Neue Daten aus CSV</strong>
              </div>
              <div class="card-body p-2">`;
        
        stichPrograms.forEach((prog, idx) => {
          const shots = (prog.data || [])
            .map(d => parseInt(d.wertung, 10))
            .filter(n => Number.isFinite(n));
          
          let displayLabel = 'Total';
          if (prog.restable === 'glueck') {
            displayLabel = 'Maximum';
          }
          
          comparisonHtml += `
            <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
              <div>
                <span class="badge bg-secondary me-2">#${idx + 1}</span>
                <span class="fw-bold">${prog.datetime}</span>
                <div class="small text-muted mt-1">`;
          
          // Zeige Schüsse kompakt
          if (shots.length > 8) {
            comparisonHtml += `Schüsse: ${shots.slice(0, 3).join(', ')}, ..., ${shots.slice(-2).join(', ')}`;
          } else if (shots.length > 0) {
            comparisonHtml += `Schüsse: ${shots.join(', ')}`;
          }
          
          comparisonHtml += `
                </div>
              </div>
              <div class="text-end">
                <span class="badge bg-success fs-6">${displayLabel}: ${prog.total}</span>
              </div>
            </div>`;
        });
        
        comparisonHtml += `
              </div>
            </div>
              </div>
            </div>
          </div>`;
        
        accordionIndex++;
      });
      
      comparisonHtml += `
        </div>`;
    }
    
    // Zusätzliche Eingabefelder für spezielle Parameter
    let additionalInputsHtml = '';
    
    // Zabig Ansage Eingabe - Verbesserte Version
    if (programsToImport.has('Zabig')) {
      const existingAnsage = hasExistingData && detailedData['Zabig'] ? detailedData['Zabig'].ansage || 0 : 0;
      additionalInputsHtml += `
        <div class="mb-3">
          <label for="zabigAnsage" class="form-label">
            <strong><i class="bi bi-arrow-up-circle me-2"></i>Zabig - Ansage:</strong>
            ${hasExistingData && detailedData['Zabig'] 
              ? `<span class="badge bg-secondary ms-2">Aktuell: ${existingAnsage}</span>` 
              : '<span class="badge bg-info ms-2">Neu</span>'}
          </label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-bullseye"></i></span>
            <input type="number" class="form-control form-control-lg" id="zabigAnsage"
                   value="${existingAnsage}" min="0" max="999" step="1"
                   placeholder="Ansage eingeben (0-999)">
            <span class="input-group-text">Punkte</span>
          </div>
          <div class="form-text">
            <i class="bi bi-info-circle me-1"></i>
            Die Ansage für den Zabig-Stich (0-999 Punkte). Standard ist 0 wenn keine Ansage gemacht wurde.
          </div>
        </div>
      `;
    }
    
    // Endstich AbsendenAnmeldung Eingabe - Verbesserte Version
    if (programsToImport.has('Endstich')) {
      // Existierende Anmeldungen aus der DB holen (falls vorhanden)
      const existingAnmeldungen = hasExistingData && detailedData['Endstich'] 
        ? detailedData['Endstich'].anmeldungen || 0 
        : 0;
      
      additionalInputsHtml += `
        <div class="mb-3">
          <label for="endstichAbsenden" class="form-label">
            <strong><i class="bi bi-bullseye me-2"></i>Endstich - Anzahl Anmeldungen (Absenden):</strong>
            ${hasExistingData && detailedData['Endstich'] 
              ? `<span class="badge bg-secondary ms-2">Aktuell: ${existingAnmeldungen}</span>` 
              : '<span class="badge bg-info ms-2">Neu</span>'}
          </label>
          <div class="btn-group d-flex" role="group" aria-label="Anzahl Anmeldungen">
            <input type="radio" class="btn-check" name="endstichAnmeldung" id="anmeldung0" 
                   value="0" ${existingAnmeldungen === 0 ? 'checked' : ''}>
            <label class="btn btn-outline-primary" for="anmeldung0">
              <i class="bi bi-x-circle me-1"></i>0 - Keine
            </label>
            
            <input type="radio" class="btn-check" name="endstichAnmeldung" id="anmeldung1" 
                   value="1" ${existingAnmeldungen === 1 ? 'checked' : ''}>
            <label class="btn btn-outline-primary" for="anmeldung1">
              <i class="bi bi-check-circle me-1"></i>1 - Eine
            </label>
            
            <input type="radio" class="btn-check" name="endstichAnmeldung" id="anmeldung2" 
                   value="2" ${existingAnmeldungen === 2 ? 'checked' : ''}>
            <label class="btn btn-outline-primary" for="anmeldung2">
              <i class="bi bi-check-circle-fill me-1"></i>2 - Zwei
            </label>
          </div>
          <!-- Verstecktes Input-Feld für den Wert -->
          <input type="hidden" id="endstichAbsenden" value="${existingAnmeldungen}">
          <div class="form-text">
            <i class="bi bi-info-circle me-1"></i>
            Anzahl der Anmeldungen für den Endstich. Normalerweise 0 (keine) oder 1 (eine Anmeldung).
            Maximal 2 Anmeldungen möglich.
          </div>
        </div>
      `;
    }
    
    // Modal-Titel und Warntext anpassen
    const modalTitle = hasExistingData ?
      'Bestehende Daten überschreiben?' :
      'Import bestätigen';
    const modalIcon = hasExistingData ?
      'bi-exclamation-triangle text-warning' :
      'bi-download text-primary';
    const alertClass = hasExistingData ? 'alert-warning' : 'alert-info';
    const alertText = hasExistingData ?
      `<strong>Achtung:</strong> Für <strong>${memberName}</strong> (${this.jahr || new Date().getFullYear()}) sind bereits Daten erfasst. Diese werden überschrieben:` :
      `<strong>Import wird durchgeführt für ${memberName}</strong> (${this.jahr || new Date().getFullYear()}) - ${programCount} Programme.`;
    
    // Wenn keine existierenden Daten, zeige was importiert wird - VERBESSERTE VERSION
    let newDataPreview = '';
    if (!hasExistingData && allPrograms.length > 0) {
      // Gruppiere Programme nach Stich für bessere Übersicht
      const groupedByStich = {};
      allPrograms.forEach(prog => {
        const stich = prog.stich || 'Unbekannt';
        if (!groupedByStich[stich]) groupedByStich[stich] = [];
        groupedByStich[stich].push(prog);
      });
      
      newDataPreview = `
        <div class="accordion" id="importAccordion">`;
      
      let accordionIndex = 0;
      Object.keys(groupedByStich).forEach(stich => {
        const stichPrograms = groupedByStich[stich];
        const firstProgram = stichPrograms[0];
        
        // Stich-spezifische Icons (dezente Farben)
        const stichConfig = {
          'Endstich': { icon: 'bi-bullseye', color: 'secondary' },
          'Kunst': { icon: 'bi-palette', color: 'secondary' },
          'Glück': { icon: 'bi-star', color: 'secondary' },
          'Zabig': { icon: 'bi-arrow-up-circle', color: 'secondary' },
          'Schwini': { icon: 'bi-chevron-double-up', color: 'secondary' },
          'Sie und Er': { icon: 'bi-people', color: 'secondary' }
        };
        const config = stichConfig[stich] || { icon: 'bi-target', color: 'secondary' };
        
        // Berechne Gesamt-Total für diesen Stich
        const stichTotal = stichPrograms.reduce((sum, prog) => sum + (prog.total || 0), 0);
        
        newDataPreview += `
          <div class="accordion-item">
            <h2 class="accordion-header" id="heading${accordionIndex}">
              <button class="accordion-button ${accordionIndex > 0 ? 'collapsed' : ''}" type="button" 
                      data-bs-toggle="collapse" data-bs-target="#collapse${accordionIndex}" 
                      aria-expanded="${accordionIndex === 0 ? 'true' : 'false'}">
                <span class="d-flex align-items-center w-100">
                  <i class="bi ${config.icon} text-${config.color} me-2 fs-5"></i>
                  <strong>${stich}</strong>
                  <span class="badge bg-${config.color} ms-2">${stichPrograms.length} ${stichPrograms.length === 1 ? 'Eintrag' : 'Einträge'}</span>
                  <span class="ms-auto me-3">
                    <span class="badge bg-success fs-6">Total: ${stichTotal}</span>
                  </span>
                </span>
              </button>
            </h2>
            <div id="collapse${accordionIndex}" class="accordion-collapse collapse ${accordionIndex === 0 ? 'show' : ''}" 
                 data-bs-parent="#importAccordion">
              <div class="accordion-body p-2">`;
        
        // Zeige Details für jeden Eintrag
        stichPrograms.forEach((prog, idx) => {
          const shots = (prog.data || [])
            .map(d => parseInt(d.wertung, 10))
            .filter(n => Number.isFinite(n));
          
          let displayLabel = 'Total';
          let displayClass = 'success';
          if (prog.restable === 'glueck') {
            displayLabel = 'Maximum';
            displayClass = 'warning';
          }
          
          // Kompakte Darstellung mit allen wichtigen Infos
          newDataPreview += `
            <div class="card mb-2 border-${config.color} border-opacity-25">
              <div class="card-body p-2">
                <div class="row align-items-center">
                  <div class="col-md-3">
                    <div class="d-flex align-items-center">
                      <span class="badge bg-light text-dark me-2">#${idx + 1}</span>
                      <div>
                        <div class="small text-muted">Programm ${prog.number}</div>
                        <div class="fw-bold small">${prog.datetime}</div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="d-flex flex-wrap gap-1">`;
          
          // Zeige die Schüsse kompakt
          if (shots.length > 0) {
            // Bei vielen Schüssen nur die ersten und letzten zeigen
            if (shots.length > 8) {
              shots.slice(0, 4).forEach((v, i) => {
                newDataPreview += `<span class="badge bg-light text-dark">${i+1}: ${v}</span>`;
              });
              newDataPreview += `<span class="badge bg-secondary">...</span>`;
              shots.slice(-3).forEach((v, i) => {
                newDataPreview += `<span class="badge bg-light text-dark">${shots.length - 2 + i}: ${v}</span>`;
              });
            } else {
              shots.forEach((v, i) => {
                newDataPreview += `<span class="badge bg-light text-dark">${i+1}: ${v}</span>`;
              });
            }
          } else {
            newDataPreview += `<span class="text-muted small">Keine Werte</span>`;
          }
          
          newDataPreview += `
                    </div>
                  </div>
                  <div class="col-md-3 text-end">
                    <span class="badge bg-${displayClass} fs-6">${displayLabel}: ${prog.total}</span>
                  </div>
                </div>
              </div>
            </div>`;
        });
        
        newDataPreview += `
              </div>
            </div>
          </div>`;
        
        accordionIndex++;
      });
      
      newDataPreview += `
        </div>`;
    }
    
    const modalHtml = `
      <div class="modal fade" id="overwriteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                <i class="bi ${modalIcon} me-2"></i>
                ${modalTitle}
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="alert ${alertClass}">
                ${alertText}
              </div>
              
              ${hasExistingData ? comparisonHtml : newDataPreview}
              
              ${additionalInputsHtml ? `
                <div class="card">
                  <div class="card-header">
                    <h6 class="card-title mb-0">
                      <i class="bi bi-gear me-2"></i>Zusätzliche Parameter
                    </h6>
                  </div>
                  <div class="card-body">
                    ${additionalInputsHtml}
                  </div>
                </div>
              ` : ''}
              
              ${hasExistingData ? `
                <div class="alert alert-info mt-3 mb-0">
                  <i class="bi bi-info-circle me-2"></i>
                  <strong>Import wird durchgeführt für ${programCount} Programme.</strong>
                  Die rot markierten Daten werden durch die grün markierten ersetzt.
                </div>
              ` : ''}
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-2"></i>Abbrechen
              </button>
              <button type="button" class="btn ${hasExistingData ? 'btn-warning' : 'btn-primary'}" id="confirmOverwriteBtn">
                <i class="bi bi-${hasExistingData ? 'exclamation-triangle' : 'download'} me-2"></i>
                ${hasExistingData ? 'Ja, überschreiben' : 'Import starten'}
              </button>
            </div>
          </div>
        </div>
      </div>
    `;
    
    // Modal hinzufügen oder ersetzen - WICHTIG: Alte Instanz richtig entfernen
    // Entferne zuerst alle existierenden Modals und Backdrops
    $('#overwriteModal').modal('hide');
    $('.modal-backdrop').remove();
    $('#overwriteModal').remove();
    $('body').removeClass('modal-open').css('padding-right', '');
    
    // Kurze Verzögerung bevor neues Modal erstellt wird
    setTimeout(() => {
      $('body').append(modalHtml);
      
      // Event-Handler für Bestätigung - mit .off() Cleanup
      $('#confirmOverwriteBtn').off('click.confirmImport').on('click.confirmImport', () => {
        // Sammle zusätzliche Eingabewerte
        const additionalParams = {};

        // Zabig Ansage
        const zabigAnsageInput = $('#zabigAnsage');
        if (zabigAnsageInput.length > 0) {
          additionalParams.zabigAnsage = parseInt(zabigAnsageInput.val()) || 0;
        }

        // Endstich AbsendenAnmeldung - Von Radio Buttons lesen
        const selectedAnmeldung = $('input[name="endstichAnmeldung"]:checked');
        if (selectedAnmeldung.length > 0) {
          additionalParams.endstichAbsendenAnmeldung = parseInt(selectedAnmeldung.val()) || 0;
          // Update hidden field für Konsistenz
          $('#endstichAbsenden').val(additionalParams.endstichAbsendenAnmeldung);
        } else {
          // Fallback auf hidden field
          const endstichAbsendenInput = $('#endstichAbsenden');
          if (endstichAbsendenInput.length > 0) {
            additionalParams.endstichAbsendenAnmeldung = parseInt(endstichAbsendenInput.val()) || 0;
          }
        }

        console.log('[ENDSCH-DEBUG] Additional parameters from modal:', additionalParams);

        $('#overwriteModal').modal('hide');
        // Aufräumen nach Modal schließen
        setTimeout(() => {
          $('.modal-backdrop').remove();
          $('body').removeClass('modal-open').css('padding-right', '');
        }, 300);

        // Import mit force=true und zusätzlichen Parametern ausführen
        this.executeImport(true, additionalParams);
      });
      
      // Event-Handler für Modal schließen (X-Button und Abbrechen) - .one() für einmalige Ausführung
      $('#overwriteModal').one('hidden.bs.modal', function () {
        // Stelle sicher dass alles aufgeräumt wird
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right', '');
        // Button wieder aktivieren
        $('#prepareImportBtn').prop('disabled', false).show();
      });
      
      // Modal anzeigen
      $('#overwriteModal').modal('show');
    }, 100);
  },

  // ===== NEU: Mitglied anhand Lizenznummer vorselektieren =====
  // Wird von FileHandler.processCSV() nach dem Parsen aufgerufen
  preselectMemberByLicense(lizenzNr) {
    console.log('[ENDSCH-DEBUG] preselectMemberByLicense called with:', lizenzNr);
    
    if (!lizenzNr) {
      console.log('[ENDSCH-DEBUG] No license number provided');
      return;
    }

    // Normalisieren: nur Ziffern, 6-stellig
    lizenzNr = String(lizenzNr).replace(/\D/g, '').substring(0, 6);
    if (!/^\d{6}$/.test(lizenzNr)) {
      console.log('[ENDSCH-DEBUG] Invalid license format after normalization:', lizenzNr);
      return;
    }

    console.log('[ENDSCH-DEBUG] Normalized license number:', lizenzNr);
    console.log('[ENDSCH-DEBUG] Making AJAX request to find member...');

    const timestamp = new Date().getTime();
    
    // FIX: Action in URL und license als Parameter
    $.ajax({
      url: 'endsch_import/import_handler.php?action=find_member_by_license&t=' + timestamp + '&license=' + lizenzNr,
      type: 'GET',
      cache: false,
      dataType: 'json',
      success: (response) => {
        console.log('[ENDSCH-DEBUG] Member search response:', response);
        
        if (response.success && response.member_id) {
          console.log('[ENDSCH-DEBUG] Found member:', response.member_name, 'ID:', response.member_id);
          
          setTimeout(() => {
            const $select = $('#mitgliedSelect');
            console.log('[ENDSCH-DEBUG] Select element found:', $select.length > 0);
            console.log('[ENDSCH-DEBUG] Current select options count:', $select.find('option').length);
            
            // Debug: Liste alle verfügbaren Optionen
            $select.find('option').each(function(i) {
              console.log('[ENDSCH-DEBUG] Option', i, ':', $(this).val(), '-', $(this).text());
            });
            
            // Versuche Mitglied zu setzen
            const previousValue = $select.val();
            console.log('[ENDSCH-DEBUG] Previous select value:', previousValue);
            
            $select.val(response.member_id);
            const newValue = $select.val();
            console.log('[ENDSCH-DEBUG] After setting, select value:', newValue);
            
            if (newValue === String(response.member_id)) {
              console.log('[ENDSCH-DEBUG] Successfully set member, triggering change event');
              $select.trigger('change');
              UIHelper.showToast(`Mitglied ${response.member_name} wurde anhand der Lizenznummer vorselektiert`, 'success');
            } else {
              console.log('[ENDSCH-DEBUG] Failed to set member value. Looking for option...');
              const $option = $select.find(`option[value="${response.member_id}"]`);
              console.log('[ENDSCH-DEBUG] Option exists:', $option.length > 0);
              
              if ($option.length === 0) {
                console.log('[ENDSCH-DEBUG] Option not found, will try to add it');
                $select.append(`<option value="${response.member_id}">${response.member_name}</option>`);
                $select.val(response.member_id).trigger('change');
                UIHelper.showToast(`Mitglied ${response.member_name} wurde anhand der Lizenznummer vorselektiert`, 'success');
              } else {
                console.log('[ENDSCH-DEBUG] Option exists but setting failed - possible interference');
                UIHelper.showToast('Vorselektion nicht möglich (Dropdown wird möglicherweise überschrieben)', 'warning');
              }
            }
          }, 500);
        } else {
          console.log('[ENDSCH-DEBUG] No member found for license:', lizenzNr);
          UIHelper.showToast(`Kein Mitglied mit Lizenznummer ${lizenzNr} gefunden`, 'warning');
        }
      },
      error: (xhr, status, error) => {
        console.error('[ENDSCH-DEBUG] Error finding member:', error);
        console.error('[ENDSCH-DEBUG] Response:', xhr.responseText);
        UIHelper.showToast('Suche nach Lizenznummer fehlgeschlagen.', 'error');
      }
    });
  }
};
// ==== DEBUG HELPER ====
window._ImportDebug = {
  log(...a){ console.log('[ImportDebug]', ...a); },
  warn(...a){ console.warn('[ImportDebug]', ...a); },
  err(...a){ console.error('[ImportDebug]', ...a); },

  dumpSelect($sel){
    const arr = [];
    $sel.find('option').each(function(){
      arr.push({value: $(this).val(), text: $(this).text()});
    });
    console.table(arr);
    return arr;
  },

  // Manuell testen: _ImportDebug.forceSelect('#mitgliedSelect','123')
  forceSelect(sel, val){
    const $s=$(sel);
    this.log('forceSelect', {exists: !!$s.length, val});
    if(!$s.length){ this.warn('Select not found'); return; }
    // Falls Option fehlt: provisorisch anlegen, damit wir sehen ob UI blockt
    if ($s.find(`option[value="${val}"]`).length===0) {
      this.warn('Option fehlte – wird testweise ergänzt');
      $s.append($(`<option value="${val}">Debug-Mitglied ${val}</option>`));
    }
    $s.val(String(val)).trigger('change');
    this.log('after set -> current value:', $s.val());
  },

  // MutationObserver: beobachte Optionen-Nachladung (Select2/Chosen/async)
  observeOptions($sel, cb){
    if (!$sel.length) return () => {};
    const target = $sel[0];
    const obs = new MutationObserver(() => cb && cb());
    obs.observe(target, { childList: true, subtree: true });
    this.log('MutationObserver attached to', $sel.attr('id') || $sel.selector);
    return () => obs.disconnect();
  }
};

// Optional global für Debug
window.ImportManagerSingle = ImportManagerSingle;
