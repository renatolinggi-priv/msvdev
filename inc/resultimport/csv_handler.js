// csv_handler.js - Handling der CSV-Datei und Parsing
const FileHandler = {
    uploadArea: null,
    fileInput: null,
    resultsContainer: null,
    
    init() {
        this.uploadArea = document.getElementById('uploadArea');
        this.fileInput = document.getElementById('fileInput');
        this.resultsContainer = document.getElementById('resultsContainer');
        
        this.setupEventListeners();
    },
    
    setupEventListeners() {
        // Click to upload
        this.uploadArea.addEventListener('click', () => {
            this.fileInput.click();
        });
        
        // File input change
        this.fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                this.handleFile(e.target.files[0]);
            }
        });
        
        // Drag and drop
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
            
            if (e.dataTransfer.files.length > 0) {
                this.handleFile(e.dataTransfer.files[0]);
            }
        });
    },
    
    handleFile(file) {
        if (!file.name.toLowerCase().endsWith('.csv')) {
            UIHelper.showToast('Bitte nur CSV-Dateien hochladen!', 'warning');
            return;
        }
        
        // Extrahiere Lizenznummer aus Dateinamen
        // Format: 112101_Renato_Linggi_001009230176078.csv
        // Die ersten 6 Ziffern sind die Lizenznummer
        const fileName = file.name.replace('.csv', '');
        let lizenzNr = null;
        
        console.log('Filename:', file.name);
        
        // Extrahiere die ersten 6 Ziffern aus dem Dateinamen
        const match = fileName.match(/^(\d{6})/);
        if (match) {
            lizenzNr = match[1];
            console.log('Found license number (first 6 digits):', lizenzNr);
        } else {
            // Fallback: Suche nach 6-stelliger Nummer in den Parts
            const fileNameParts = fileName.split('_');
            console.log('Filename parts:', fileNameParts);
            
            for (let part of fileNameParts) {
                if (/^\d{6}$/.test(part)) {
                    lizenzNr = part;
                    console.log('Found 6-digit license number in parts:', lizenzNr);
                    break;
                }
            }
        }
        
        console.log('Final license number:', lizenzNr);
        
        const reader = new FileReader();
        reader.onload = (e) => {
            this.processCSV(e.target.result, file.name, lizenzNr);
        };
        reader.readAsText(file);
    },
    
    processCSV(csvContent, fileName, lizenzNr = null) {
        console.log('Processing CSV, license:', lizenzNr);
        const lines = csvContent.split('\n');
        const programs133 = [];
        const programs134 = [];
        const allPrograms = new Map();
        
        let currentProgram = null;
        
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            
            // Check if it's a program header line
            if (line && !line.startsWith('Nr;') && line.includes('Total:')) {
                const parts = line.split(';');
                const programNumber = parts[0];
                const totalMatch = line.match(/Total:\s*(\d+)/);
                const total = totalMatch ? parseInt(totalMatch[1]) : 0;
                
                // Save previous program if it was 133 or 134
                if (currentProgram) {
                    if (currentProgram.number === '133' && currentProgram.total > 0) {
                        programs133.push(currentProgram);
                    } else if (currentProgram.number === '134' && currentProgram.total > 0) {
                        programs134.push(currentProgram);
                    }
                }
                
                // Start new program
                currentProgram = {
                    number: programNumber,
                    total: total,
                    fullLine: line,
                    lineNumber: i + 1,
                    title: parts[1] || '',
                    datetime: parts[2] || '',
                    data: []
                };
                
                // Track all programs
                if (!allPrograms.has(programNumber)) {
                    allPrograms.set(programNumber, []);
                }
                allPrograms.get(programNumber).push({
                    total: total,
                    line: i + 1
                });
            }
            // Collect data lines for current program
            else if (currentProgram && line && !line.startsWith('Nr;')) {
                const parts = line.split(';');
                if (parts.length >= 4) {
                    currentProgram.data.push({
                        nr: parts[0],
                        wertung: parts[3]
                    });
                }
            }
        }
        
        // Don't forget the last program
        if (currentProgram) {
            if (currentProgram.number === '133' && currentProgram.total > 0) {
                programs133.push(currentProgram);
            } else if (currentProgram.number === '134' && currentProgram.total > 0) {
                programs134.push(currentProgram);
            }
        }
        
        console.log('Found programs 133:', programs133.length);
        console.log('Found programs 134:', programs134.length);
        
        // Speichere global für Import
        ImportManager.setPrograms(programs133, programs134);
        
        // Display results
        this.displayResults(programs133, programs134, allPrograms, fileName, lines.length);
        
        // Wenn Lizenznummer gefunden, Mitglied vorselektieren
        if (lizenzNr) {
            console.log('Calling preselectMemberByLicense with:', lizenzNr);
            setTimeout(() => {
                ImportManager.preselectMemberByLicense(lizenzNr);
            }, 500);
        }
        
        // Show raw data preview
        document.getElementById('rawDataPreview').textContent = 
            lines.slice(0, 50).join('\n') + 
            (lines.length > 50 ? '\n\n... (weitere ' + (lines.length - 50) + ' Zeilen)' : '');
        
        // Success message
        UIHelper.showToast('CSV erfolgreich verarbeitet!', 'success');
        
        // Button aktivieren wenn Programme gefunden wurden
        if (programs133.length > 0 || programs134.length > 0) {
            document.getElementById('prepareImportBtn').disabled = false;
        }
    },
    
    displayResults(programs133, programs134, allPrograms, fileName, totalLines) {
        // Hide upload area, show results
        this.uploadArea.style.display = 'none';
        this.resultsContainer.style.display = 'block';
        
        // File info
        document.getElementById('fileInfo').innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <strong><i class="bi bi-file-earmark-text me-1"></i> Datei:</strong> ${fileName}
                </div>
                <div class="col-md-4">
                    <strong><i class="bi bi-list-ol me-1"></i> Zeilen total:</strong> ${totalLines}
                </div>
                <div class="col-md-4">
                    <strong><i class="bi bi-grid-3x3-gap me-1"></i> Programme gefunden:</strong> ${allPrograms.size} verschiedene
                </div>
            </div>
        `;
        
        // Program 133 results
        this.displayProgram(programs133, '133', 'results133', 'count133');
        
        // Program 134 results
        this.displayProgram(programs134, '134', 'results134', 'count134');
        
        // All programs overview
        this.displayAllPrograms(allPrograms);
    },
    
    displayProgram(programs, programNumber, resultsId, countId) {
        const resultsDiv = document.getElementById(resultsId);
        const countElement = document.getElementById(countId);
        
        countElement.textContent = programs.length + ' gefunden';
        countElement.className = programs.length > 0 ? 'badge bg-success float-end' : 'badge bg-secondary float-end';
        
        if (programs.length > 0) {
            let html = '';
            programs.forEach((prog, idx) => {
                html += `
                    <div class="program-overview-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>Eintrag ${idx + 1}</strong>
                                <span class="badge bg-light text-dark ms-2">Zeile ${prog.lineNumber}</span><br>
                                <small class="text-muted">${prog.title}</small><br>
                                <small class="text-muted">${prog.datetime}</small>
                            </div>
                            <div class="text-end">
                                <div class="total-value">${prog.total}</div>
                                <small class="text-muted">Total</small>
                            </div>
                        </div>
                        ${prog.data.length > 0 ? `<div class="mt-2"><span class="badge bg-info">${prog.data.length} Datenpunkte</span></div>` : ''}
                    </div>
                `;
            });
            resultsDiv.innerHTML = html;
        } else {
            resultsDiv.innerHTML = '<p class="no-data text-center mt-3">Keine Einträge mit Total > 0 gefunden</p>';
        }
    },
    
    displayAllPrograms(allPrograms) {
        const allProgramsDiv = document.getElementById('allPrograms');
        let html = '<div class="row">';
        
        // Sort programs by number
        const sortedPrograms = Array.from(allPrograms.entries()).sort((a, b) => {
            return parseInt(a[0]) - parseInt(b[0]);
        });
        
        sortedPrograms.forEach(([progNum, entries]) => {
            const totalSum = entries.reduce((sum, e) => sum + e.total, 0);
            const nonZeroEntries = entries.filter(e => e.total > 0);
            
            const badgeClass = nonZeroEntries.length > 0 ? 'bg-success' : 'bg-secondary';
            
            html += `
                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                    <div class="program-overview-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Programm ${progNum}</strong>
                            <span class="badge ${badgeClass}">${nonZeroEntries.length}/${entries.length}</span>
                        </div>
                        <div class="small">
                            <div><i class="bi bi-file-text me-1"></i> ${entries.length} Einträge total</div>
                            <div><i class="bi bi-check-circle me-1"></i> ${nonZeroEntries.length} mit Werten</div>
                            ${totalSum > 0 ? `<div><i class="bi bi-calculator me-1"></i> Summe: <strong>${totalSum}</strong></div>` : '<div class="text-muted"><i class="bi bi-x-circle me-1"></i> Keine Werte</div>'}
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        allProgramsDiv.innerHTML = html;
    },
    
    resetUpload() {
        this.uploadArea.style.display = 'block';
        this.resultsContainer.style.display = 'none';
        this.fileInput.value = '';
        ImportManager.reset();
        UIHelper.showToast('Bereit für neue Datei', 'info');
    }
};