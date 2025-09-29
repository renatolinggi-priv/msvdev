// csv_handler.js - Optimierter 3-Phasen Import Workflow
const FileHandler = {
    uploadArea: null,
    fileInput: null,
    resultsContainer: null,
    heimPrograms: [],
    kantiPrograms: [],
    allAllowedPrograms: [],
    
    // Workflow-Status
    currentPhase: 1, // 1, 2, 3
    csvData: null,
    
    async init() {
        this.uploadArea = document.getElementById('uploadArea');
        this.fileInput = document.getElementById('fileInput');
        
        await this.loadAllDefinitions();
        this.setupEventListeners();
    },
    
    async loadAllDefinitions() {
        try {
            const [heimRes, kantiRes] = await Promise.all([
                fetch(`heimkanti_import/import_handler.php?action=get_stich_definition&stich=Heimmeisterschaft`),
                fetch(`heimkanti_import/import_handler.php?action=get_stich_definition&stich=Kantonalstich`)
            ]);
            
            const [heimData, kantiData] = await Promise.all([
                heimRes.json(),
                kantiRes.json()
            ]);
            
            this.heimPrograms = heimData.success && heimData.numbers ? heimData.numbers.map(String) : ['133', '134', '521'];
            this.kantiPrograms = kantiData.success && kantiData.numbers ? kantiData.numbers.map(String) : ['520'];
            this.allAllowedPrograms = [...this.heimPrograms, ...this.kantiPrograms];
            
            console.log('Heim-Programme:', this.heimPrograms);
            console.log('Kanti-Programme:', this.kantiPrograms);
            
        } catch (error) {
            console.error('Fehler beim Laden der Stichdefinitionen:', error);
            this.heimPrograms = ['133', '134', '521'];
            this.kantiPrograms = ['520'];
            this.allAllowedPrograms = ['133', '134', '521', '520'];
        }
    },
    
    setupEventListeners() {
        // Upload Area Events
        this.uploadArea.addEventListener('click', () => this.fileInput.click());
        this.fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) this.handleFile(e.target.files[0]);
        });
        
        // Drag & Drop
        this.uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.uploadArea.classList.add('dragover');
        });
        this.uploadArea.addEventListener('dragleave', () => {
            this.uploadArea.classList.remove('dragover');
        });
        this.uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            this.uploadArea.classList.remove('dragover');
            if (e.dataTransfer.files.length > 0) this.handleFile(e.dataTransfer.files[0]);
        });
    },
    
    // ===== PHASE 1: CSV UPLOAD & PARSING =====
    handleFile(file) {
        if (!file.name.toLowerCase().endsWith('.csv')) {
            UIHelper.showToast('Bitte nur CSV-Dateien hochladen!', 'warning');
            return;
        }
        
        // Extrahiere Lizenznummer
        const lizenzNr = this.extractLicenseFromFilename(file.name);
        
        const reader = new FileReader();
        reader.onload = (e) => this.phase1_ProcessCSV(e.target.result, file.name, lizenzNr);
        reader.readAsText(file);
    },
    
    extractLicenseFromFilename(fileName) {
        const nameWithoutExt = fileName.replace('.csv', '');
        const match = nameWithoutExt.match(/^(\d{6})/);
        if (match) return match[1];
        
        const parts = nameWithoutExt.split('_');
        for (let part of parts) {
            if (/^\d{6}$/.test(part)) return part;
        }
        return null;
    },
    
    parseDateTime(dateTimeStr) {
        if (!dateTimeStr) return null;
        
        // Format: DD.MM.YYYY-HH:MM:SS
        const match = dateTimeStr.match(/(\d{2})\.(\d{2})\.(\d{4})-(\d{2}):(\d{2}):(\d{2})/);
        if (match) {
            const [_, day, month, year, hour, minute] = match;
            return {
                sortKey: `${year}-${month}-${day} ${hour}:${minute}`,
                display: `${day}.${month}.${year} ${hour}:${minute}`
            };
        }
        return null;
    },
    
    phase1_ProcessCSV(csvContent, fileName, lizenzNr) {
        const lines = csvContent.split('\n');
        const foundPrograms = this.parseCSVPrograms(lines);
        
        if (foundPrograms.length === 0) {
            UIHelper.showToast('Keine relevanten Programme in der CSV gefunden!', 'warning');
            return;
        }
        
        // Phase 1 → Phase 2: Zeige Auswahl
        this.phase2_ShowSelection(foundPrograms, fileName, lizenzNr);
        
        // Mitglied vorselektieren falls Lizenz gefunden
        if (lizenzNr) {
            setTimeout(() => ImportManager.preselectMemberByLicense(lizenzNr), 500);
        }
        
        UIHelper.showToast(`${foundPrograms.length} Programme gefunden!`, 'success');
    },
    
    parseCSVPrograms(lines) {
        const programs = [];
        
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            if (!line) continue;
            
            // Suche Header: Nummer;Titel;Datum-Zeit;...;Total: XXX
            const headerMatch = line.match(/^(\d+);([^;]*);(\d{2}\.\d{2}\.\d{4}-\d{2}:\d{2}:\d{2});.*Total:\s*(\d+)/);
            
            if (headerMatch) {
                const [_, programNumber, title, datetimeStr, totalStr] = headerMatch;
                const total = parseInt(totalStr) || 0;
                
                if (total > 0 && this.allAllowedPrograms.includes(programNumber)) {
                    const dateTime = this.parseDateTime(datetimeStr);
                    const stichType = this.getStichTypeForProgram(programNumber);
                    
                    programs.push({
                        number: programNumber,
                        total: total,
                        title: title || `Programm ${programNumber}`,
                        datetime: dateTime ? dateTime.display : datetimeStr,
                        sortKey: dateTime ? dateTime.sortKey : datetimeStr,
                        stichType: stichType,
                        selected: true // Initial alle ausgewählt
                    });
                }
            }
        }
        
        // Chronologisch sortieren
        programs.sort((a, b) => a.sortKey.localeCompare(b.sortKey));
        return programs;
    },
    
    getStichTypeForProgram(programNumber) {
        if (this.heimPrograms.includes(programNumber)) return 'Heimmeisterschaft';
        if (this.kantiPrograms.includes(programNumber)) return 'Kantonalstich';
        return null;
    },
    
    // ===== PHASE 2: PROGRAMM-AUSWAHL =====
    phase2_ShowSelection(programs, fileName, lizenzNr) {
        this.currentPhase = 2;
        this.csvData = { programs, fileName, lizenzNr };
        
        // Store data for later use
        this.foundPrograms = programs;
        this.fileName = fileName;
        this.lizenzNr = lizenzNr;
        
        // Switch to phase 2
        this.goToPhase(2);
        
        // Render program selection in phase 2 container
        this.renderProgramSelection();
        this.setupSelectionEventHandlers();
    },
    
    renderProgramSelection() {
        const programs = this.foundPrograms;
        const heimPrograms = programs.filter(p => p.stichType === 'Heimmeisterschaft');
        const kantiPrograms = programs.filter(p => p.stichType === 'Kantonalstich');
        
        // Show file info
        const fileInfoHtml = `
            <div class="alert alert-info">
                <h6 class="mb-1">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Datei: <strong>${this.fileName}</strong>
                </h6>
                <small class="text-muted">
                    ${programs.length} relevante Programme gefunden
                    ${this.lizenzNr ? ` • Lizenznummer: ${this.lizenzNr}` : ''}
                </small>
            </div>
        `;
        $('#fileInfo').html(fileInfoHtml).show();
        
        // Render program selection
        const selectionHtml = `
            <div class="row">
                ${heimPrograms.length > 0 ? this.renderProgramTable('Heimmeisterschaft', heimPrograms, 'heim', 8) : ''}
                ${kantiPrograms.length > 0 ? this.renderProgramTable('Kantonalstich', kantiPrograms, 'kanti', 5) : ''}
            </div>
        `;
        
        $('#programSelectionContainer').html(selectionHtml);
        this.updateSelectionSummary();
    },
    
    renderProgramTable(title, programs, type, maxPasses) {
        const icon = type === 'heim' ? 'bi-home' : 'bi-flag';
        
        let tableHtml = `
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi ${icon} me-2"></i>
                            ${title} (max. ${maxPasses} Pässe)
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th width="10%">
                                            <input type="checkbox" id="selectAll${type}" class="form-check-input" checked>
                                        </th>
                                        <th width="15%">Programm</th>
                                        <th width="25%">Datum/Zeit</th>
                                        <th width="35%">Titel</th>
                                        <th width="15%" class="text-center">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
        `;
        
        programs.forEach((prog, idx) => {
            const rowClass = idx < maxPasses ? '' : 'table-warning';
            const warningIcon = idx >= maxPasses ? '<i class="bi bi-exclamation-triangle text-warning me-1"></i>' : '';
            
            tableHtml += `
                <tr class="${rowClass}">
                    <td>
                        <input type="checkbox" class="form-check-input program-checkbox"
                               data-type="${type}" data-index="${idx}"
                               data-category="${type === 'heim' ? 'heimmeisterschaft' : 'kantonalstich'}"
                               ${prog.selected ? 'checked' : ''}>
                    </td>
                    <td>
                        <span class="badge bg-primary">Programm ${prog.number}</span>
                    </td>
                    <td class="small">${prog.datetime}</td>
                    <td class="small">${warningIcon}${prog.title}</td>
                    <td class="text-center">
                        <strong class="text-success">${prog.total}</strong>
                    </td>
                </tr>
            `;
        });
        
        tableHtml += `
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="alert alert-info mt-2 mb-0">
                            <small>
                                <i class="bi bi-info-circle me-1"></i>
                                Maximale Anzahl: ${maxPasses} Pässe.
                                ${programs.length > maxPasses ? 'Überzählige Programme sind gelb markiert.' : ''}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        return tableHtml;
    },
    
    setupSelectionEventHandlers() {
        // "Alle auswählen" Checkboxen
        document.getElementById('selectAllheim')?.addEventListener('change', (e) => {
            this.toggleAllPrograms('heim', e.target.checked);
        });
        document.getElementById('selectAllkanti')?.addEventListener('change', (e) => {
            this.toggleAllPrograms('kanti', e.target.checked);
        });
        
        // Einzelne Checkboxen
        document.querySelectorAll('.program-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => this.updateSelectionSummary());
        });
    },
    
    toggleAllPrograms(type, checked) {
        document.querySelectorAll(`[data-type="${type}"].program-checkbox`).forEach(cb => {
            cb.checked = checked;
        });
        this.updateSelectionSummary();
    },
    
    updateSelectionSummary() {
        const heimSelected = document.querySelectorAll('[data-type="heim"].program-checkbox:checked').length;
        const kantiSelected = document.querySelectorAll('[data-type="kanti"].program-checkbox:checked').length;
        const totalSelected = heimSelected + kantiSelected;
        
        // Update "Alle auswählen" Checkboxen
        const heimTotal = document.querySelectorAll('[data-type="heim"].program-checkbox').length;
        const kantiTotal = document.querySelectorAll('[data-type="kanti"].program-checkbox').length;
        
        const selectAllHeim = document.getElementById('selectAllheim');
        const selectAllKanti = document.getElementById('selectAllkanti');
        
        if (selectAllHeim) selectAllHeim.checked = heimSelected === heimTotal && heimTotal > 0;
        if (selectAllKanti) selectAllKanti.checked = kantiSelected === kantiTotal && kantiTotal > 0;
        
        // Button aktivieren/deaktivieren
        const proceedBtn = document.getElementById('proceedToImportBtn');
        if (proceedBtn) {
            proceedBtn.disabled = totalSelected === 0;
            proceedBtn.innerHTML = totalSelected > 0
                ? `<i class="bi bi-arrow-right me-2"></i>Weiter zum Import (${totalSelected} Programme)`
                : '<i class="bi bi-arrow-right me-2"></i>Keine Programme ausgewählt';
        }
    },
    
    // Direkt das Modal zeigen - Phase 3 wird übersprungen
    proceedToImport() {
        if (!this.validateSelection()) {
            return;
        }
        
        console.log('Proceeding directly to import modal');
        
        // Update ImportManager with selected programs
        ImportManager.selectedHeimPrograms = this.getSelectedPrograms('heim');
        ImportManager.selectedKantiPrograms = this.getSelectedPrograms('kanti');
        
        // Direkt das Bestätigungsmodal zeigen (wie bei endsch_import)
        this.showImportConfirmationModal();
    },
    
    validateSelection() {
        const mitgliedSelect = document.getElementById('mitgliedSelect');
        if (!mitgliedSelect || !mitgliedSelect.value) {
            UIHelper.showToast('Bitte zuerst ein Mitglied auswählen!', 'warning');
            return false;
        }
        
        const totalSelected = document.querySelectorAll('.program-checkbox:checked').length;
        if (totalSelected === 0) {
            UIHelper.showToast('Bitte mindestens ein Programm auswählen!', 'warning');
            return false;
        }
        
        return true;
    },
    
    getSelectedPrograms(type) {
        const selected = [];
        const typeSelector = type === 'heim' ? 'heimmeisterschaft' : 'kantonalstich';
        
        $(`.program-checkbox[data-category="${typeSelector}"]:checked`).each(function() {
            const index = $(this).data('index');
            const programs = FileHandler.foundPrograms.filter(p =>
                p.stichType === (type === 'heim' ? 'Heimmeisterschaft' : 'Kantonalstich')
            );
            const program = programs[index];
            
            if (program) {
                selected.push({
                    number: program.number,
                    passeNr: selected.length + 1, // Sequential numbering
                    total: program.total,
                    datetime: program.datetime,
                    stichType: program.stichType,
                    originalIndex: index
                });
            }
        });
        
        // Sort by datetime to ensure chronological order
        selected.sort((a, b) => new Date(a.datetime) - new Date(b.datetime));
        
        // Reassign pass numbers after sorting
        selected.forEach((prog, index) => {
            prog.passeNr = index + 1;
        });
        
        return selected;
    },
    
    // ===== IMPORT-BESTÄTIGUNG IM ENDSCH_IMPORT STIL =====
    async showImportConfirmationModal() {
        const mitgliedSelect = document.getElementById('mitgliedSelect');
        const jahrSelect = document.getElementById('jahrSelect');
        
        if (!mitgliedSelect.value) {
            UIHelper.showToast('Bitte zuerst ein Mitglied auswählen!', 'warning');
            return;
        }
        
        const memberName = mitgliedSelect.options[mitgliedSelect.selectedIndex].text;
        const mitgliedId = mitgliedSelect.value;
        const jahr = jahrSelect.value;
        
        const heimPrograms = ImportManager.selectedHeimPrograms;
        const kantiPrograms = ImportManager.selectedKantiPrograms;
        const totalPrograms = heimPrograms.length + kantiPrograms.length;
        
        if (totalPrograms === 0) {
            UIHelper.showToast('Keine Programme zum Import ausgewählt!', 'warning');
            return;
        }
        
        // Prüfe auf bestehende Daten in der Datenbank
        let existingData = {};
        let hasExistingData = false;
        
        try {
            const checkPromises = [];
            
            if (heimPrograms.length > 0) {
                checkPromises.push(
                    fetch(`heimkanti_import/import_handler.php?action=check_existing&mitglied_id=${mitgliedId}&jahr=${jahr}&stich_type=Heimmeisterschaft`, { cache: 'no-store' })
                        .then(res => res.json())
                        .then(data => ({ ...data, type: 'heim' }))
                );
            }
            
            if (kantiPrograms.length > 0) {
                checkPromises.push(
                    fetch(`heimkanti_import/import_handler.php?action=check_existing&mitglied_id=${mitgliedId}&jahr=${jahr}&stich_type=Kantonalstich`, { cache: 'no-store' })
                        .then(res => res.json())
                        .then(data => ({ ...data, type: 'kanti' }))
                );
            }
            
            const checkResults = await Promise.all(checkPromises);
            
            checkResults.forEach(result => {
                if (result.success && result.exists) {
                    hasExistingData = true;
                    existingData[result.type] = result;
                }
            });
            
        } catch (error) {
            console.error('Error checking existing data:', error);
        }
        
        // Modal im endsch_import Stil erstellen
        this.renderImportModal(memberName, jahr, heimPrograms, kantiPrograms, hasExistingData, existingData);
    },
    
    renderImportModal(memberName, jahr, heimPrograms, kantiPrograms, hasExistingData, existingData) {
        const totalPrograms = heimPrograms.length + kantiPrograms.length;
        
        // Modal-Titel und Alert-Style je nach bestehenden Daten
        const modalTitle = hasExistingData ? 'Bestehende Daten überschreiben?' : 'Import bestätigen';
        const modalIcon = hasExistingData ? 'bi-exclamation-triangle text-warning' : 'bi-download text-primary';
        const alertClass = hasExistingData ? 'alert-warning' : 'alert-info';
        const alertText = hasExistingData ?
            `<strong>Achtung:</strong> Für <strong>${memberName}</strong> (${jahr}) sind bereits Daten erfasst. Diese werden überschrieben:` :
            `<strong>Import wird durchgeführt für ${memberName}</strong> (${jahr}) - ${totalPrograms} Programme.`;
        
        // Accordion für Programme erstellen (wie bei endsch_import)
        let programsHtml = '';
        
        if (hasExistingData) {
            // Vergleichsansicht mit bestehenden und neuen Daten
            programsHtml = this.renderComparisonAccordion(heimPrograms, kantiPrograms, existingData);
        } else {
            // Nur neue Daten zeigen
            programsHtml = this.renderNewDataAccordion(heimPrograms, kantiPrograms);
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
                            
                            ${programsHtml}
                            
                            ${hasExistingData ? `
                                <div class="alert alert-info mt-3 mb-0">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Import wird durchgeführt für ${totalPrograms} Programme.</strong>
                                    Die rot markierten Daten werden durch die grün markierten ersetzt.
                                </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle me-2"></i>Abbrechen
                            </button>
                            <button type="button" class="btn ${hasExistingData ? 'btn-warning' : 'btn-primary'}" id="confirmOverwriteBtn">
                                <i class="bi ${hasExistingData ? 'bi-exclamation-triangle' : 'bi-download'} me-2"></i>
                                ${hasExistingData ? 'Ja, überschreiben' : 'Import starten'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Modal hinzufügen/ersetzen (wie bei endsch_import)
        $('#overwriteModal').modal('hide');
        $('.modal-backdrop').remove();
        $('#overwriteModal').remove();
        $('body').removeClass('modal-open').css('padding-right', '');
        
        setTimeout(() => {
            $('body').append(modalHtml);
            
            // Event-Handler für Bestätigung
            $('#confirmOverwriteBtn').on('click', () => {
                $('#overwriteModal').modal('hide');
                setTimeout(() => {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css('padding-right', '');
                }, 300);
                
                // Import ausführen
                this.executeImport();
            });
            
            // Event-Handler für Modal schließen
            $('#overwriteModal').on('hidden.bs.modal', function () {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('padding-right', '');
            });
            
            // Modal anzeigen
            $('#overwriteModal').modal('show');
        }, 100);
    },
    
    renderComparisonAccordion(heimPrograms, kantiPrograms, existingData) {
        let html = '<div class="accordion" id="compareAccordion">';
        let accordionIndex = 0;
        
        // Heim-Programme
        if (heimPrograms.length > 0) {
            const existingHeim = existingData.heim || {};
            html += this.renderStichAccordion('Heimmeisterschaft', heimPrograms, 'bi-home', 'secondary', accordionIndex++, existingHeim.existing_passes || [], existingHeim.data || {});
        }
        
        // Kanti-Programme
        if (kantiPrograms.length > 0) {
            const existingKanti = existingData.kanti || {};
            html += this.renderStichAccordion('Kantonalstich', kantiPrograms, 'bi-flag', 'secondary', accordionIndex++, existingKanti.existing_passes || [], existingKanti.data || {});
        }
        
        html += '</div>';
        return html;
    },
    
    renderNewDataAccordion(heimPrograms, kantiPrograms) {
        let html = '<div class="accordion" id="importAccordion">';
        let accordionIndex = 0;
        
        // Heim-Programme
        if (heimPrograms.length > 0) {
            html += this.renderStichAccordion('Heimmeisterschaft', heimPrograms, 'bi-home', 'secondary', accordionIndex++);
        }
        
        // Kanti-Programme
        if (kantiPrograms.length > 0) {
            html += this.renderStichAccordion('Kantonalstich', kantiPrograms, 'bi-flag', 'secondary', accordionIndex++);
        }
        
        html += '</div>';
        return html;
    },
    
    renderStichAccordion(title, programs, icon, color, index, existingPasses = [], existingData = {}) {
        const hasExisting = existingPasses.length > 0;
        const newTotal = programs.reduce((sum, prog) => sum + (prog.total || 0), 0);
        
        let html = `
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading${index}">
                    <button class="accordion-button ${index > 0 ? 'collapsed' : ''}" type="button"
                            data-bs-toggle="collapse" data-bs-target="#collapse${index}"
                            aria-expanded="${index === 0 ? 'true' : 'false'}">
                        <span class="d-flex align-items-center w-100">
                            <i class="bi ${icon} text-${color} me-2 fs-5"></i>
                            <strong>${title}</strong>
                            <span class="badge bg-${color} ms-2">${programs.length} ${programs.length === 1 ? 'Programm' : 'Programme'}</span>
                            <span class="ms-auto me-3">
                                ${hasExisting ? `<span class="badge bg-danger me-2">Aktuell: ${Object.keys(existingData).length}</span>` : ''}
                                <span class="badge bg-success">Neu: ${newTotal}</span>
                            </span>
                        </span>
                    </button>
                </h2>
                <div id="collapse${index}" class="accordion-collapse collapse ${index === 0 ? 'show' : ''}"
                     data-bs-parent="#${hasExisting ? 'compareAccordion' : 'importAccordion'}">
                    <div class="accordion-body p-2">`;
        
        // Bestehende Daten anzeigen (ignoriere Werte mit 0)
        if (hasExisting) {
            // Filtere bestehende Passen - ignoriere NULL, 0, leere Werte
            const validExistingPasses = existingPasses.filter(passeNr => {
                const passeKey = `Passe${passeNr}`;
                const existingValue = existingData[passeKey];
                
                // Ignoriere NULL, undefined, leere Strings, 0-Werte
                if (!existingValue || existingValue === null || existingValue === undefined || existingValue === '') {
                    return false;
                }
                
                // Ignoriere "0" als String oder 0 als Zahl
                if (existingValue === '0' || parseInt(existingValue) === 0) {
                    return false;
                }
                
                // Ignoriere "NULL" als String (falls die DB NULL als String zurückgibt)
                if (existingValue === 'NULL' || existingValue === 'null') {
                    return false;
                }
                
                return true;
            });
            
            if (validExistingPasses.length > 0) {
                html += `
                    <div class="card mb-2 border-danger border-opacity-25">
                        <div class="card-header bg-danger bg-opacity-10">
                            <i class="bi bi-database text-danger me-2"></i>
                            <strong>Aktuelle Daten (werden überschrieben)</strong>
                        </div>
                        <div class="card-body p-2">`;
                
                validExistingPasses.forEach(passeNr => {
                    const passeKey = `Passe${passeNr}`;
                    const existingValue = existingData[passeKey] || 'N/A';
                    html += `<div class="text-danger mb-1"><strong>Passe ${passeNr}:</strong> ${existingValue}</div>`;
                });
                
                html += `
                        </div>
                    </div>`;
            }
        }
        
        // Neue Daten anzeigen
        html += `
            <div class="card border-success border-opacity-25">
                <div class="card-header bg-success bg-opacity-10">
                    <i class="bi bi-download text-success me-2"></i>
                    <strong>Neue Daten aus CSV</strong>
                </div>
                <div class="card-body p-2">`;
        
        programs.forEach((prog, idx) => {
            html += `
                <div class="card mb-2 border-${color} border-opacity-25">
                    <div class="card-body p-2">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-light text-dark me-2">Passe ${prog.passeNr}</span>
                                    <div>
                                        <div class="small text-muted">Programm ${prog.number}</div>
                                        <div class="fw-bold small">${prog.datetime}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <small class="text-muted">${prog.stichType}</small>
                            </div>
                        </div>
                    </div>
                </div>`;
        });
        
        html += `
                </div>
            </div>
                    </div>
                </div>
            </div>`;
        
        return html;
    },
    
    renderConfirmationTable(title, programs, icon) {
        let html = `
            <h6 class="mt-3">
                <i class="bi ${icon} me-2"></i>${title}
            </h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Passe</th>
                            <th>Programm</th>
                            <th>Datum/Zeit</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        programs.forEach(prog => {
            html += `
                <tr>
                    <td><strong>Passe ${prog.passeNr}</strong></td>
                    <td><span class="badge bg-primary">Programm ${prog.number}</span></td>
                    <td class="small">${prog.datetime}</td>
                    <td class="text-center"><strong class="text-success">${prog.total}</strong></td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
        
        return html;
    },
    
    async executeImport() {
        // Modal schließen
        const modal = bootstrap.Modal.getInstance(document.getElementById('importConfirmationModal'));
        if (modal) {
            modal.hide();
        }
        
        // Import durchführen über ImportManager
        await ImportManager.executeImport(true);
    },
    
    // ===== UTILITY FUNCTIONS =====
    goToPhase(phaseNumber) {
        // Hide all phases
        $('.workflow-phase').hide();
        
        // Show target phase
        $(`#phase${phaseNumber}`).show();
        
        // Update progress indicator if available
        if (window.WorkflowHelper) {
            window.WorkflowHelper.updateProgress(phaseNumber);
        }
        
        this.currentPhase = phaseNumber;
        console.log(`Switched to phase ${phaseNumber}`);
    },
    
    resetUpload() {
        this.currentPhase = 1;
        this.csvData = null;
        this.foundPrograms = [];
        
        $('#fileInfo').hide();
        $('#programSelectionContainer').empty();
        $('#importSummary').empty();
        $('#proceedToImportBtn').prop('disabled', true);
        $('#executeImportBtn').prop('disabled', true);
        
        // Clear file input
        $('#fileInput').val('');
        
        // Reset ImportManager
        ImportManager.reset();
        
        // Go back to phase 1
        this.goToPhase(1);
        
        UIHelper.showToast('Bereit für neue Datei', 'info');
        console.log('Upload reset completed');
    }
};