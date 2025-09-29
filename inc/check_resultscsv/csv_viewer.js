// check_resultscsv/csv_viewer.js - CSV Viewer mit endsch_import Parse-Logik und Scroll-Layout
const CSVViewer = {
    uploadArea: null,
    fileInput: null,
    allPrograms: [],
    
    init() {
        console.log('[CSV-VIEWER] Initializing...');
        
        this.uploadArea = document.getElementById('uploadArea');
        this.fileInput = document.getElementById('fileInput');
        
        if (!this.uploadArea || !this.fileInput) {
            console.error('[CSV-VIEWER] Missing DOM elements');
            return false;
        }
        
        this.setupEventListeners();
        console.log('[CSV-VIEWER] Initialization complete');
        return true;
    },
    
    setupEventListeners() {
        console.log('[CSV-VIEWER] Setting up event listeners...');
        
        // File Input über Upload Area positionieren (wie bei endsch_import)
        this.fileInput.style.position = 'absolute';
        this.fileInput.style.top = '0';
        this.fileInput.style.left = '0';
        this.fileInput.style.width = '100%';
        this.fileInput.style.height = '100%';
        this.fileInput.style.opacity = '0';
        this.fileInput.style.cursor = 'pointer';
        this.fileInput.style.display = 'block';
        this.fileInput.style.zIndex = '10';
        
        // Upload Area muss position relative haben
        this.uploadArea.style.position = 'relative';
        
        // File Input in Upload Area verschieben falls noch nicht dort
        if (this.fileInput.parentElement !== this.uploadArea) {
            this.uploadArea.appendChild(this.fileInput);
        }
        
        // File input change handler
        this.fileInput.addEventListener('change', (e) => {
            if (e.target.files && e.target.files.length > 0) {
                this.handleFile(e.target.files[0]);
            }
        });
        
        // Drag & Drop
        this.fileInput.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.uploadArea.classList.add('dragover');
        });
        
        this.fileInput.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.uploadArea.classList.remove('dragover');
        });
        
        this.fileInput.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.uploadArea.classList.remove('dragover');
            
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                this.handleFile(e.dataTransfer.files[0]);
            }
        });
        
        console.log('[CSV-VIEWER] Event listeners setup complete');
    },
    
    handleFile(file) {
        console.log('[CSV-VIEWER] Processing file:', file.name);
        
        if (!file.name.toLowerCase().endsWith('.csv')) {
            UIHelper.showToast('Bitte nur CSV-Dateien hochladen!', 'warning');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = (e) => this.processCSV(e.target.result, file.name);
        reader.readAsText(file);
    },
    
    // Parser wie bei endsch_import
    parseHeaderLine(line) {
        const m = line.match(/^(\d+);([^;]*);(\d{2}\.\d{2}\.\d{4}-\d{2}:\d{2}:\d{2});.*Total:\s*(\d+)/);
        if (!m) return null;
        return {
            number: String(m[1]),
            title: (m[2] || '').trim(),
            dtRaw: m[3],
            total: parseInt(m[4], 10) || 0
        };
    },
    
    parseDateTime(dtStr) {
        const m = dtStr?.match(/(\d{2})\.(\d{2})\.(\d{4})-(\d{2}):(\d{2}):(\d{2})/);
        if (!m) return { sortKey: dtStr || '', display: dtStr || '' };
        const [_, d, mo, y, h, mi, s] = m;
        return {
            sortKey: `${y}-${mo}-${d} ${h}:${mi}:${s}`,
            display: `${d}.${mo}.${y} ${h}:${mi}`
        };
    },
    
    parseShotLine(line) {
        const parts = line.split(';');
        if (parts.length < 5) return null;
        if (!/^\d+$/.test(parts[0])) return null;
        
        const wertung = parseInt(parts[3], 10);      // 4. Spalte = normale Wertung
        const hunderter = parseInt(parts[4], 10);    // 5. Spalte = 100er Wertung
        
        if (!Number.isFinite(wertung)) return null;
        
        return {
            nr: parseInt(parts[0], 10) || 0,
            wertung: wertung,
            hunderterWertung: hunderter
        };
    },
    
    processCSV(csvText, fileName) {
        const lines = csvText.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
        this.allPrograms = [];
        
        // Scan durch alle Zeilen
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            if (!line) continue;
            
            // Header erkennen
            const head = this.parseHeaderLine(line);
            if (!head) continue;
            
            const dt = this.parseDateTime(head.dtRaw);
            const program = {
                number: head.number,
                title: head.title || `Programm ${head.number}`,
                total: head.total,
                datetime: dt.display,
                sortKey: dt.sortKey || head.dtRaw,
                shots: []
            };
            
            // Folgezeilen einsammeln (Schüsse)
            for (let j = i + 1; j < lines.length && j < i + 50; j++) {
                const l2 = lines[j]?.trim();
                if (!l2) break;
                
                // Nächster Header?
                if (/^\d+;[^;]*;\d{2}\.\d{2}\.\d{4}-\d{2}:\d{2}:\d{2};/.test(l2)) break;
                
                const shot = this.parseShotLine(l2);
                if (shot && shot.wertung > 0) {
                    program.shots.push(shot);
                }
            }
            
            // Nur Programme mit Total > 0 speichern
            if (program.total > 0) {
                this.allPrograms.push(program);
            }
        }
        
        // Chronologisch sortieren
        this.allPrograms.sort((a, b) => (a.sortKey < b.sortKey ? -1 : (a.sortKey > b.sortKey ? 1 : 0)));
        
        // UI befüllen
        this.renderResultsUI(fileName);
        
        // Rohdaten-Vorschau
        const rawPreview = document.getElementById('rawDataPreview');
        if (rawPreview) {
            rawPreview.textContent = lines.slice(0, 50).join('\n') + 
                (lines.length > 50 ? `\n\n... (weitere ${lines.length - 50} Zeilen)` : '');
        }
        
        UIHelper.showToast(`${this.allPrograms.length} Programme gefunden!`, 'success');
    },
    
    renderResultsUI(fileName) {
        // File Info anzeigen
        const info = document.getElementById('fileInfo');
        if (info) {
            const programNumbers = [...new Set(this.allPrograms.map(p => p.number))].sort();
            info.innerHTML = `
                <div class="row">
                    <div class="col-md-4"><strong>Datei:</strong> ${fileName}</div>
                    <div class="col-md-4"><strong>Programme gefunden:</strong> ${this.allPrograms.length}</div>
                    <div class="col-md-4"><strong>Programmnummern:</strong> ${programNumbers.join(', ') || 'keine'}</div>
                </div>`;
            info.style.display = 'block';
        }
        
        // Programme anzeigen
        this.displayPrograms();
        
        // Container anzeigen mit flex Layout
        document.getElementById('resultsContainer').style.display = 'flex';
        
        // Upload-Area ausblenden nach erfolgreichem Upload
        document.getElementById('uploadPhase').style.display = 'none';
        
        // Debug-Button hinzufügen
        if (!document.getElementById('debugToggle')) {
            const debugBtn = document.createElement('button');
            debugBtn.id = 'debugToggle';
            debugBtn.className = 'btn btn-outline-secondary btn-sm mt-3';
            debugBtn.innerHTML = '<i class="bi bi-bug me-1"></i>Debug anzeigen';
            debugBtn.onclick = () => {
                const debugSection = document.getElementById('debugSection');
                if (debugSection) {
                    debugSection.style.display = debugSection.style.display === 'none' ? 'block' : 'none';
                    debugBtn.innerHTML = debugSection.style.display === 'none' ? 
                        '<i class="bi bi-bug me-1"></i>Debug anzeigen' : 
                        '<i class="bi bi-bug me-1"></i>Debug ausblenden';
                }
            };
            document.getElementById('resultsContainer').appendChild(debugBtn);
        }
    },
    
    displayPrograms() {
        const container = document.getElementById('stichProgramsContainer');
        if (!container) return;
        
        // Erstelle Tabelle mit Scroll-Wrapper für responsive Layout
        let html = `
            <div class="col-12">
                <div class="card shadow-sm results-table-wrapper">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0">
                            <i class="bi bi-list-check me-2"></i>
                            Alle Programme in der CSV-Datei
                        </h6>
                    </div>
                    <div class="card-body p-0 results-table-responsive">
                        <table class="table table-hover mb-0" style="font-size: 0.85rem;">
                            <thead class="sticky-top bg-white">
                                <tr class="table-light sticky-top">
                                    <th width="5%" class="text-center py-2">#</th>
                                    <th width="8%" class="text-center py-2">Prog.</th>
                                    <th width="20%" class="py-2">Titel</th>
                                    <th width="15%" class="py-2">Datum/Zeit</th>
                                    <th width="37%" class="py-2">Schüsse</th>
                                    <th width="10%" class="text-center py-2">Total</th>
                                    <th width="5%" class="text-center py-2">
                                        <i class="bi bi-bar-chart" title="Details"></i>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>`;
        
        this.allPrograms.forEach((prog, idx) => {
            const shots = prog.shots.map(s => s.wertung).filter(n => Number.isFinite(n));
            
            // Schüsse kompakt anzeigen
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
            
            // Zebrastreifen
            const rowClass = (idx + 1) % 2 === 0 ? '' : 'bg-light bg-opacity-50';
            
            // Programm-Farbe basierend auf Nummer
            let badgeColor = 'secondary';
            if (prog.number === '522') badgeColor = 'primary';      // Endstich
            else if (prog.number === '519') badgeColor = 'info';    // Kunst
            else if (prog.number === '521') badgeColor = 'success'; // Glück
            else if (prog.number === '523') badgeColor = 'warning'; // Zabig
            else if (prog.number === '525') badgeColor = 'danger';  // Schwini
            else if (prog.number === '133' || prog.number === '134') badgeColor = 'primary'; // Heim
            else if (prog.number === '520') badgeColor = 'info';    // Kanti
            
            html += `
                <tr class="${rowClass}">
                    <td class="text-center py-1">
                        <small class="text-muted">${idx + 1}</small>
                    </td>
                    <td class="text-center py-1">
                        <span class="badge bg-${badgeColor} bg-opacity-10 text-${badgeColor} border border-${badgeColor} border-opacity-25" style="font-size: 0.75rem;">
                            ${prog.number}
                        </span>
                    </td>
                    <td class="py-1">
                        <small title="${prog.title}">${prog.title || '-'}</small>
                    </td>
                    <td class="py-1">
                        <small>${prog.datetime}</small>
                    </td>
                    <td class="py-1">
                        ${shotsDisplay}
                    </td>
                    <td class="text-center py-1">
                        <strong class="text-${badgeColor}">${prog.total}</strong>
                    </td>
                    <td class="text-center py-1">
                        <button class="btn btn-sm btn-link p-0" onclick="CSVViewer.toggleDetails(${idx})" title="Details anzeigen">
                            <i class="bi bi-chevron-down" id="toggle-icon-${idx}"></i>
                        </button>
                    </td>
                </tr>
                <tr id="details-${idx}" class="collapse">
                    <td colspan="7" class="p-0">
                        <div class="bg-light p-3">
                            <h6 class="mb-3">
                                <i class="bi bi-bullseye me-2"></i>
                                Detaillierte Schuss-Übersicht für: <span class="text-primary">${prog.title || 'Programm ' + prog.number}</span>
                            </h6>
                            <div class="row">
                                ${this.renderShotDetails(prog)}
                            </div>
                        </div>
                    </td>
                </tr>`;
        });
        
        html += `
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-light py-2">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            ${this.allPrograms.length} Programme • 
                            Gesamttotal: ${this.allPrograms.reduce((sum, p) => sum + p.total, 0)}
                        </small>
                    </div>
                </div>
            </div>`;
        
        container.innerHTML = html;
        
        // Nach dem Rendern Scroll-Höhe berechnen
        setTimeout(() => {
            if (typeof calculateScrollHeight === 'function') {
                calculateScrollHeight();
            }
        }, 100);
    },
    
    renderShotDetails(program) {
        if (!program.shots || program.shots.length === 0) {
            return '<div class="col-12"><p class="text-muted mb-0">Keine Schussdaten vorhanden</p></div>';
        }
        
        let html = '<div class="col-12"><div class="table-responsive"><table class="table table-sm mb-0">';
        html += '<thead><tr class="table-secondary">';
        html += '<th width="20%">Schuss Nr.</th>';
        html += '<th width="40%">Wertung (10er)</th>';
        html += '<th width="40%">100er Wertung</th>';
        html += '</tr></thead><tbody>';
        
        program.shots.forEach(shot => {
            html += `
                <tr>
                    <td><strong>${shot.nr}</strong></td>
                    <td><span class="badge bg-light text-dark">${shot.wertung}</span></td>
                    <td><span class="badge bg-light text-dark">${shot.hunderterWertung || '-'}</span></td>
                </tr>`;
        });
        
        html += '</tbody></table></div></div>';
        return html;
    },
    
    toggleDetails(idx) {
        const detailsRow = document.getElementById(`details-${idx}`);
        const icon = document.getElementById(`toggle-icon-${idx}`);
        
        if (detailsRow.classList.contains('show')) {
            detailsRow.classList.remove('show');
            icon.className = 'bi bi-chevron-down';
        } else {
            detailsRow.classList.add('show');
            icon.className = 'bi bi-chevron-up';
        }
    }
};

// Globaler Export
window.CSVViewer = CSVViewer;
