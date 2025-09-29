// endsch_import/csv_handler.js — CSV-Handler für Einzel-Stiche (Endstich, Kunst, Glück, Zabig, Schwini)
// Architektur: init() -> loadProgramMap() -> setupEvents() -> handleFile() -> processCSV()
// Erkennt Programme automatisch via Programmnummern-Mapping aus der DB (get_all_stich_definitions)

const FileHandler = {
  // DOM-Refs
  uploadArea: null,
  fileInput: null,

  // Mapping: "522": { stich: "Endstich", restable: "endstich" }, ...
  programMap: {},
  lastProcessedFile: null,

  // --- Init & Bootstrapping ---
  async init() {
    console.log('[ENDSCH-CSV] Initializing FileHandler...');
    
    this.uploadArea = document.getElementById('uploadArea');
    this.fileInput = document.getElementById('fileInput');
    
    if (!this.uploadArea) {
      console.error('[ENDSCH-CSV] Upload area not found!');
      return false;
    }
    if (!this.fileInput) {
      console.error('[ENDSCH-CSV] File input not found!');
      return false;
    }
    
    console.log('[ENDSCH-CSV] DOM elements found successfully');
    console.log('[ENDSCH-CSV] Upload area:', this.uploadArea);
    console.log('[ENDSCH-CSV] File input:', this.fileInput);

    await this.loadProgramMap();
    this.setupEventListeners();
    
    console.log('[ENDSCH-CSV] FileHandler initialization complete');
    return true;
  },

  async loadProgramMap() {
    try {
      const res = await fetch('endsch_import/import_handler.php?action=get_all_stich_definitions', { cache: 'no-store' });
      const data = await res.json();
      if (data?.success && data.program_map) {
        this.programMap = data.program_map; // e.g. {"522":{"stich":"Endstich","restable":"endstich"}, ...}
      } else {
        this.programMap = {};
      }
    } catch (err) {
      console.error('loadProgramMap failed:', err);
      this.programMap = {};
    }
  },

  setupEventListeners() {
    console.log('[ENDSCH-CSV] Setting up event listeners...');
    
    if (!this.uploadArea || !this.fileInput) {
      console.error('[ENDSCH-CSV] Cannot setup events - missing DOM elements');
      return;
    }
    
    // NEUER ANSATZ: File Input über Upload Area positionieren mit opacity 0
    // Damit klickt der User direkt auf das Input statt auf die Area
    this.fileInput.style.position = 'absolute';
    this.fileInput.style.top = '0';
    this.fileInput.style.left = '0';
    this.fileInput.style.width = '100%';
    this.fileInput.style.height = '100%';
    this.fileInput.style.opacity = '0';
    this.fileInput.style.cursor = 'pointer';
    this.fileInput.style.display = 'block';
    this.fileInput.style.zIndex = '10';
    
    // Upload Area muss position relative haben für absolute child
    this.uploadArea.style.position = 'relative';
    
    // File Input in Upload Area verschieben falls noch nicht dort
    if (this.fileInput.parentElement !== this.uploadArea) {
      this.uploadArea.appendChild(this.fileInput);
    }
    
    console.log('[ENDSCH-CSV] File input repositioned over upload area');

    // File input change handler - kein Click-Handler mehr nötig!
    this.handleFileChange = (e) => {
      console.log('[ENDSCH-CSV] File input changed:', e.target.files);
      if (e.target.files && e.target.files.length > 0) {
        // Verhindere mehrfaches Verarbeiten derselben Datei
        const file = e.target.files[0];
        const fileId = file.name + file.lastModified;
        console.log('[ENDSCH-CSV] Processing file:', file.name, 'ID:', fileId);
        
        if (this.lastProcessedFile !== fileId) {
          this.lastProcessedFile = fileId;
          this.handleFile(file);
        } else {
          console.log('[ENDSCH-CSV] File already processed, skipping');
        }
      }
    };
    this.fileInput.addEventListener('change', this.handleFileChange);

    // Drag & drop handlers - jetzt am File Input statt Upload Area
    // Da das Input über der Area liegt, müssen wir dort die Events abfangen
    this.handleDragOver = (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.uploadArea.classList.add('dragover');
    };
    this.handleDragLeave = (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.uploadArea.classList.remove('dragover');
    };
    this.handleDrop = (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.uploadArea.classList.remove('dragover');
      console.log('[ENDSCH-CSV] Files dropped:', e.dataTransfer.files);
      
      if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        const file = e.dataTransfer.files[0];
        const fileId = file.name + file.lastModified;
        
        if (this.lastProcessedFile !== fileId) {
          this.lastProcessedFile = fileId;
          this.handleFile(file);
        }
      }
    };
    
    // Entferne alte Handler falls vorhanden
    this.fileInput.removeEventListener('dragover', this.handleDragOver);
    this.fileInput.removeEventListener('dragleave', this.handleDragLeave);
    this.fileInput.removeEventListener('drop', this.handleDrop);
    
    // Füge neue Handler am File Input hinzu (da es jetzt oben liegt)
    this.fileInput.addEventListener('dragover', this.handleDragOver);
    this.fileInput.addEventListener('dragleave', this.handleDragLeave);
    this.fileInput.addEventListener('drop', this.handleDrop);
    
    console.log('[ENDSCH-CSV] Event listeners setup complete - File input overlays upload area');
  },

  // --- Datei laden & parsen ---
  handleFile(file) {
    console.log('[ENDSCH-CSV] handleFile called with:', file);
    
    if (!file || !file.name) {
      console.error('[ENDSCH-CSV] Invalid file object');
      if (typeof UIHelper !== 'undefined') {
        UIHelper.showToast('Ungültige Datei', 'error');
      }
      return;
    }
    
    if (!file.name.toLowerCase().endsWith('.csv')) {
      console.log('[ENDSCH-CSV] File is not CSV:', file.name);
      if (typeof UIHelper !== 'undefined') {
        UIHelper.showToast('Bitte eine CSV-Datei wählen', 'warning');
      } else {
        alert('Bitte eine CSV-Datei wählen');
      }
      return;
    }

    console.log('[ENDSCH-CSV] Processing CSV file:', file.name, 'Size:', file.size, 'bytes');

    // Lizenznummer evtl. aus Dateinamen (6-stellig) ermitteln
    const base = file.name.replace(/\.csv$/i, '');
    console.log('[ENDSCH-CSV] Base filename (without .csv):', base);
    
    let lizenzNr = null;
    const direct = base.match(/^(\d{6})/);
    if (direct) {
      lizenzNr = direct[1];
      console.log('[ENDSCH-CSV] Found license number at start of filename:', lizenzNr);
    }
    
    if (!lizenzNr) {
      console.log('[ENDSCH-CSV] No license at start, checking filename parts...');
      const parts = base.split(/[_\-\s]+/);
      console.log('[ENDSCH-CSV] Filename parts:', parts);
      
      for (const part of parts) {
        if (/^\d{6}$/.test(part)) {
          lizenzNr = part;
          console.log('[ENDSCH-CSV] Found 6-digit license number in parts:', lizenzNr);
          break;
        }
      }
    }

    console.log('[ENDSCH-CSV] Final extracted license number:', lizenzNr);

    const reader = new FileReader();
    reader.onload = (e) => this.processCSV(e.target.result, file.name, lizenzNr);
    reader.readAsText(file);
  },

  // Erwarteter Header: "ProgrammNr;Titel;DD.MM.YYYY-HH:MM:SS;...;Total: N"
  parseHeaderLine(line) {
    // Sehr ähnlich zu deiner Heim/Kanti-Logik – robust auf "Total: <Zahl>"
    const m = line.match(/^(\d+);([^;]*);(\d{2}\.\d{2}\.\d{4}-\d{2}:\d{2}:\d{2});.*Total:\s*(\d+)/);
    if (!m) return null;
    return {
      number: String(m[1]),
      title: (m[2] || '').trim(),
      dtRaw: m[3],
      total: parseInt(m[4], 10) || 0,
      totalFromHeader: parseInt(m[4], 10) || 0  // Original-Total aus Header speichern
    };
  },

  parseDateTime(dtStr) {
    // "DD.MM.YYYY-HH:MM:SS" -> sortKey & display
    const m = dtStr?.match(/(\d{2})\.(\d{2})\.(\d{4})-(\d{2}):(\d{2}):(\d{2})/);
    if (!m) return { sortKey: dtStr || '', display: dtStr || '' };
    const [_, d, mo, y, h, mi, s] = m;
    return {
      sortKey: `${y}-${mo}-${d} ${h}:${mi}:${s}`,
      display: `${d}.${mo}.${y} ${h}:${mi}`
    };
  },

  // Shots-Zeilen: "Nr;Wettkampfschuss;Passe;Wertung;100er Wertung;..."
  parseShotLine(line, programNumber) {
    const parts = line.split(';');
    if (parts.length < 5) return null;
    if (!/^\d+$/.test(parts[0])) return null;

    const wertung = parseInt(parts[3], 10);      // 4. Spalte = normale Wertung (10er-Scheibe)
    const hunderter = parseInt(parts[4], 10);    // 5. Spalte = 100er Wertung (genaue Punktzahl)
    
    // Bei Kunst, Glück, Zabig die 100er-Wertung verwenden
    const meta = this.programMap[programNumber];
    const use100er = meta && ['kunst', 'glueck', 'zabig'].includes(meta.restable);
    
    // Für Endstich: normale Wertung für Schüsse, aber 100er Wertung für Tiefschuss-Berechnung
    const finalWertung = use100er ? hunderter : wertung;
    if (!Number.isFinite(finalWertung)) return null;
    
    // Zusätzlich: bei Endstich beide Wertungen verfügbar machen
    const result = {
      nr: parseInt(parts[0], 10) || 0,
      wertung: finalWertung,  // Je nach Stich die richtige Wertung
      normalWertung: wertung,
      hunderterWertung: hunderter
    };
    
    // Für Endstich: normale Wertung für Schüsse verwenden
    if (meta && meta.restable === 'endstich') {
      result.wertung = wertung;  // Normale Wertung für Schuss1-10
      result.tiefschussWertung = hunderter;  // 100er Wertung für Tiefschuss-Berechnung
      
      // DEBUG für Endstich
      console.log(`[ENDSCH-CSV-DEBUG] Endstich shot parsed - Nr: ${result.nr}, Normal: ${wertung}, 100er: ${hunderter}, Line: "${line}"`);
    }

    return result;
  },

  processCSV(csvText, fileName, lizenzNr = null) {
    const lines = csvText.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
    const relevantPrograms = [];    // erkannte Programme (nur die mit programmMap-Treffer)
    const allPrograms = new Map();  // Übersicht (Programmnummer -> {total,datetime}[])

    // Scan durch alle Zeilen, Satz für Satz
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i].trim();
      if (!line) continue;

      // 1) Header erkennen
      const head = this.parseHeaderLine(line);
      if (!head) continue;

      // 2) datetime & Grundgerüst
      const dt = this.parseDateTime(head.dtRaw);
      const program = {
        number: head.number,               // "522"
        title: head.title || `Programm ${head.number}`,
        total: head.total,
        datetime: dt.display,              // z.B. "14.03.2025 08:00"
        sortKey: dt.sortKey || head.dtRaw, // sortable
        data: []                           // wird im nächsten Schritt gefüllt
      };

      // 3) Folgezeilen bis zum nächsten Header einsammeln (hard limit ~50 Zeilen)
      for (let j = i + 1; j < lines.length && j < i + 50; j++) {
        const l2 = lines[j]?.trim();
        if (!l2) break;
        // nächster Header?
        if (/^\d+;[^;]*;\d{2}\.\d{2}\.\d{4}-\d{2}:\d{2}:\d{2};/.test(l2)) break;

        const shot = this.parseShotLine(l2, program.number);
        // Shots wie bei dir: nur >0 Wertungen berücksichtigen
        if (shot && shot.wertung > 0) {
          program.data.push(shot);
        }
      }

      // 4) Übersicht füllen
      if (program.total > 0) {
        if (!allPrograms.has(program.number)) allPrograms.set(program.number, []);
        allPrograms.get(program.number).push({ total: program.total, datetime: program.datetime });
      }

      // 5) Relevanz via programMap (alle Stiche)
      const meta = this.programMap[program.number];
      if (meta) {
        program.stich = meta.stich;       // e.g. "Endstich"
        program.restable = meta.restable; // e.g. "endstich"
        
        // Spezielle Total-Berechnungen je nach Stich
        if (program.data.length > 0) {
          switch (meta.restable) {
            case 'glueck':
              // Bei Glück: Total = höchster Wert (Maximum)
              const maxValue = Math.max(...program.data.map(d => d.wertung || 0));
              program.total = maxValue;
              console.log(`[ENDSCH-CSV] Glück - Total berechnet als Maximum: ${program.total}`);
              break;
              
            case 'schwini':
              // Bei Schwini: Nur die ersten 6 Schüsse zählen (eine Passe)
              const schwiniShots = program.data.slice(0, 6); // Nur erste 6 Schüsse
              const schwiniSum = schwiniShots.reduce((sum, d) => sum + (d.wertung || 0), 0);
              program.total = schwiniSum;
              console.log(`[ENDSCH-CSV] Schwini - Total berechnet als Summe der ersten 6 Schüsse: ${program.total} (${schwiniShots.length} Schüsse)`);
              break;
              
            case 'zabig':
              // Bei Zabig: Total = Summe aller Schüsse (normalerweise 6 Schüsse)
              const zabigSum = program.data.reduce((sum, d) => sum + (d.wertung || 0), 0);
              program.total = zabigSum;
              console.log(`[ENDSCH-CSV] Zabig - Total berechnet als Summe: ${program.total} (${program.data.length} Schüsse)`);
              break;
              
            default:
              // Für Endstich und Kunst: Header-Total verwenden (bereits gesetzt)
              console.log(`[ENDSCH-CSV] ${meta.restable} - Using header total: ${program.total}`);
              break;
          }
        }
        
        relevantPrograms.push(program);
      }
    }

    // 6) Chronologisch sortieren
    relevantPrograms.sort((a, b) => (a.sortKey < b.sortKey ? -1 : (a.sortKey > b.sortKey ? 1 : 0)));

    // 7) UI befüllen
    this.renderResultsUI(fileName, relevantPrograms, allPrograms);

    // 8) Lizenz → Mitglied vorselektieren
    console.log('[ENDSCH-CSV] Checking license for preselection:', lizenzNr);
    if (lizenzNr) {
      console.log('[ENDSCH-CSV] License found, will call preselectMemberByLicense in 400ms');
      setTimeout(() => {
        console.log('[ENDSCH-CSV] Now calling ImportManagerSingle.preselectMemberByLicense with:', lizenzNr);
        ImportManagerSingle.preselectMemberByLicense(lizenzNr);
      }, 400);
    } else {
      console.log('[ENDSCH-CSV] No license number found, skipping preselection');
    }

    // 9) Rohdaten-Vorschau
    const rawPreview = document.getElementById('rawDataPreview');
    if (rawPreview) {
      rawPreview.textContent = lines.slice(0, 50).join('\n') + (lines.length > 50 ? `\n\n... (weitere ${lines.length - 50} Zeilen)` : '');
    }

    // 10) Final-Storage für ImportManagerSingle
    window._allProgramsForSingle = relevantPrograms;

    UIHelper.showToast('CSV erfolgreich verarbeitet!', 'success');
  },

  // --- UI-Helfer ---
  renderResultsUI(fileName, relevantPrograms, allPrograms) {
    // Zu Phase 2 wechseln
    if (typeof WorkflowHelper !== 'undefined') {
      WorkflowHelper.showPhase(2);
    } else {
      // Fallback: Direkt Phase umschalten
      document.getElementById('phase1').style.display = 'none';
      document.getElementById('phase2').style.display = 'block';
    }

    // Datei-Info
    const info = document.getElementById('fileInfo');
    if (info) {
      const kinds = [...new Set(relevantPrograms.map(p => `${p.number} (${p.stich})`))].join(', ') || 'keine';
      info.innerHTML = `
        <div class="row">
          <div class="col-md-4"><strong>Datei:</strong> ${fileName}</div>
          <div class="col-md-4"><strong>Relevante Programme:</strong> ${relevantPrograms.length}</div>
          <div class="col-md-4"><strong>Erkannt:</strong> ${kinds}</div>
        </div>`;
    }

    // Programme nach Stich gruppiert anzeigen als schöne Kacheln
    this.displayStichPrograms(relevantPrograms);

    // Button "Weiter zum Import" aktivieren
    setTimeout(() => {
      const btnProceed = document.getElementById('proceedToImportBtn');
      
      if (btnProceed) {
        // Button aktivieren wenn Programme vorhanden sind
        if (relevantPrograms.length > 0) {
          btnProceed.disabled = false;
          btnProceed.removeAttribute('disabled');
          btnProceed.classList.remove('disabled');
          
          console.log('[ENDSCH-CSV] Proceed button aktiviert - gefunden:', relevantPrograms.length, 'Programme');
        } else {
          btnProceed.disabled = true;
          btnProceed.setAttribute('disabled', 'disabled');
          console.log('[ENDSCH-CSV] Proceed button deaktiviert - keine relevanten Programme gefunden');
        }
      } else {
        console.error('[ENDSCH-CSV] Button #proceedToImportBtn nicht gefunden!');
      }
    }, 100); // 100ms Verzögerung
  },

  // Programme nach Stich gruppiert anzeigen als kompakte Liste
  displayStichPrograms(programs) {
    const container = document.getElementById('stichProgramsContainer');
    if (!container) return;

    // Gruppiere Programme nach Stich
    const groupedByStich = {};
    programs.forEach((p, idx) => {
      const stich = p.stich || 'Unbekannt';
      if (!groupedByStich[stich]) groupedByStich[stich] = [];
      p.index = idx;
      groupedByStich[stich].push(p);
    });

    // Icons für verschiedene Sticharten
    const stichIcons = {
      'Endstich': 'bi-bullseye',
      'Kunst': 'bi-palette',
      'Glück': 'bi-dice-6',
      'Zabig': 'bi-star',
      'Schwini': 'bi-piggy-bank',
      'Sie und Er': 'bi-people'
    };
    
    // Farben für verschiedene Sticharten
    const stichColors = {
      'Endstich': 'primary',
      'Kunst': 'info',
      'Glück': 'success',
      'Zabig': 'warning',
      'Schwini': 'danger',
      'Sie und Er': 'secondary'
    };
    
    // Erstelle eine übersichtliche Tabelle mit allen Programmen
    let html = `
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-header bg-light py-2">
            <h6 class="mb-0">
              <i class="bi bi-list-check me-2"></i>
              Erkannte Programme zum Import
            </h6>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover mb-0" style="font-size: 0.85rem;">
                <thead>
                  <tr class="table-light">
                    <th width="5%" class="text-center py-2">#</th>
                    <th width="12%" class="py-2">Stich</th>
                    <th width="8%" class="text-center py-2">Prog.</th>
                    <th width="18%" class="py-2">Datum/Zeit</th>
                    <th width="42%" class="py-2">Schüsse</th>
                    <th width="10%" class="text-center py-2">Total</th>
                    <th width="5%" class="text-center py-2">
                      <i class="bi bi-info-circle" title="Status"></i>
                    </th>
                  </tr>
                </thead>
                <tbody>`;
    
    let globalIdx = 0;
    
    // Sortiere Sticharten für konsistente Anzeige
    const sortedStichs = Object.keys(groupedByStich).sort();
    
    sortedStichs.forEach(stich => {
      const stichPrograms = groupedByStich[stich];
      const icon = stichIcons[stich] || 'bi-target';
      const color = stichColors[stich] || 'secondary';
      
      // Programme dieses Stichs durchgehen
      stichPrograms.forEach((p, localIdx) => {
        globalIdx++;
        
        const shots = (p.data || [])
          .map(d => parseInt(d.wertung, 10))
          .filter(n => Number.isFinite(n));
        
        // Schüsse kompakt anzeigen mit Hover für vollständige Liste
        let shotsDisplay = '';
        let shotsTitle = shots.join(', ');
        
        if (shots.length > 0) {
          if (shots.length > 10) {
            // Bei mehr als 10 Schüssen: erste 5 + ... + letzte 2
            shotsDisplay = `${shots.slice(0, 5).join(' ')} <span class="text-muted">...</span> ${shots.slice(-2).join(' ')}`;
          } else {
            shotsDisplay = shots.join(' ');
          }
          shotsDisplay = `<small class="text-muted" title="${shotsTitle}">${shotsDisplay}</small>`;
        } else {
          shotsDisplay = '<small class="text-muted">-</small>';
        }
        
        // Datum kürzer anzeigen
        const currentYear = new Date().getFullYear();
        let shortDate = p.datetime;
        if (p.datetime.includes(currentYear.toString())) {
          shortDate = p.datetime.replace(`.${currentYear}`, '');
        }
        
        // Zebraschatten für bessere Lesbarkeit
        const rowClass = globalIdx % 2 === 0 ? '' : 'bg-light bg-opacity-50';
        
        html += `
          <tr class="program-row ${rowClass}" data-index="${p.index}" id="program-${p.index}">
            <td class="text-center py-1">
              <small class="text-muted">${globalIdx}</small>
            </td>
            <td class="py-1">
              <span class="badge bg-${color} bg-opacity-10 text-${color} border border-${color} border-opacity-25" style="font-size: 0.75rem;">
                <i class="bi ${icon}" style="font-size: 0.7rem;"></i> ${stich}
              </span>
            </td>
            <td class="text-center py-1">
              <small class="fw-bold">${p.number}</small>
            </td>
            <td class="py-1">
              <small>${shortDate}</small>
            </td>
            <td class="py-1">
              ${shotsDisplay}
            </td>
            <td class="text-center py-1">
              <strong class="text-${color}">${p.total}</strong>
            </td>
            <td class="text-center py-1">
              <i class="bi bi-check-circle text-success" style="font-size: 0.9rem;" title="Bereit zum Import"></i>
            </td>
          </tr>`;
      });
    });
    
    html += `
                </tbody>
              </table>
            </div>
          </div>
          <div class="card-footer bg-light py-2">
            <div class="d-flex justify-content-between align-items-center">
              <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i>
                ${programs.length} Programme gefunden
              </small>
              <div>`;
    
    // Zusammenfassung der Sticharten
    sortedStichs.forEach(stich => {
      const count = groupedByStich[stich].length;
      const color = stichColors[stich] || 'secondary';
      html += `<span class="badge bg-${color} bg-opacity-10 text-${color} border border-${color} border-opacity-25 me-1" style="font-size: 0.7rem;">
                ${stich}: ${count}
              </span>`;
    });
    
    html += `
              </div>
            </div>
          </div>
        </div>
      </div>`;
    
    container.innerHTML = html;
    
    // Keine Event-Handler mehr nötig, da keine Checkboxen mehr vorhanden
  },



  // Reset für neuen Upload
  resetUpload() {
    // Zurück zu Phase 1
    if (typeof WorkflowHelper !== 'undefined') {
      WorkflowHelper.showPhase(1);
    } else {
      document.getElementById('phase1').style.display = 'block';
      document.getElementById('phase2').style.display = 'none';
    }
    this.fileInput.value = '';
    this.lastProcessedFile = null; // WICHTIG: Reset der Datei-ID, damit neue Dateien verarbeitet werden können
    window._allProgramsForSingle = [];
    UIHelper.showToast('Bereit für neue Datei', 'info');
  },
  
  // Zusätzliche Methode für Phase-Wechsel
  goToPhase(phaseNumber) {
    if (typeof WorkflowHelper !== 'undefined') {
      WorkflowHelper.goToPhase(phaseNumber);
    }
  },
  
  // Methode zum Fortfahren zum Import
  proceedToImport() {
    if (typeof WorkflowHelper !== 'undefined') {
      WorkflowHelper.proceedToImport();
    }
  }
};

// Globalen Export sicherstellen
window.FileHandler = FileHandler;

// Boot - wird jetzt nur noch von endsch_import.php aufgerufen
// document.addEventListener('DOMContentLoaded', () => FileHandler.init());
