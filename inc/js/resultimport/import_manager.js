// import_manager.js - Import-Logik und Server-Kommunikation
const ImportManager = {
    programs133: [],
    programs134: [],
    selectedPrograms: [],
    
    init() {
        this.setupEventListeners();
    },
    
    setupEventListeners() {
        $('#mitgliedSelect').on('change', () => this.checkExistingData());
        $('#jahrSelect').on('change', () => this.checkExistingData());
    },
    
    preselectMemberByLicense(lizenzNr) {
        console.log('Trying to preselect member with license:', lizenzNr);
        
        // Cache-Busting durch Timestamp
        const timestamp = new Date().getTime();
        
        // Versuche Mitglied anhand der Lizenznummer zu finden
        $.ajax({
            url: 'resultimport/import_handler.php?t=' + timestamp,
            type: 'GET',
            cache: false, // Explizit Cache deaktivieren
            data: {
                action: 'find_member_by_license',
                license: lizenzNr
            },
            dataType: 'json',
            success: (response) => {
                console.log('Member search response:', response);
                
                if (response.success && response.member_id) {
                    // Warte kurz, damit das Dropdown bereit ist
                    setTimeout(() => {
                        $('#mitgliedSelect').val(response.member_id).trigger('change');
                        console.log('Selected member ID:', response.member_id);
                        UIHelper.showToast(`Mitglied ${response.member_name} wurde anhand der Lizenznummer vorselektiert`, 'success');
                        this.checkExistingData();
                    }, 500);
                } else {
                    console.log('No member found for license:', lizenzNr);
                    console.log('Server message:', response.message);
                    UIHelper.showToast(`Kein Mitglied mit Lizenznummer ${lizenzNr} gefunden`, 'warning');
                }
            },
            error: (xhr, status, error) => {
                console.error('Error finding member:', error);
                console.error('Response:', xhr.responseText);
                // Zeige den Response Text für Debugging
                if (xhr.responseText) {
                    console.error('Server response:', xhr.responseText);
                }
            }
        });
    },
    
    setPrograms(p133, p134) {
        this.programs133 = p133;
        this.programs134 = p134;
    },
    
    reset() {
        this.programs133 = [];
        this.programs134 = [];
        this.selectedPrograms = [];
        $('#prepareImportBtn').prop('disabled', true);
    },
    
    prepareImport() {
        $('#importSection').slideDown();
        
        // Warnungen prüfen
        let warningMessage = '';
        if (this.programs133.length > 4) {
            warningMessage += `Es wurden ${this.programs133.length} Programme 133 gefunden (erwartet: max. 4). `;
        }
        if (this.programs134.length > 4) {
            warningMessage += `Es wurden ${this.programs134.length} Programme 134 gefunden (erwartet: max. 4). `;
        }
        
        if (warningMessage) {
            $('#warningSection').show();
            $('#warningMessage').text(warningMessage + 'Bitte wähle die zu importierenden Programme aus.');
        } else {
            $('#warningSection').hide();
        }
        
        // Programme zur Auswahl anzeigen
        this.displayProgramSelection();
        
        // Scroll zu Import-Section
        $('html, body').animate({
            scrollTop: $('#importSection').offset().top - 100
        }, 500);
    },
    
    displayProgramSelection() {
        // Programm 133
        this.displayProgramGroup(this.programs133, '133', 'program133Selection', ['Passe 1', 'Passe 3', 'Passe 5', 'Passe 7']);
        
        // Programm 134
        this.displayProgramGroup(this.programs134, '134', 'program134Selection', ['Passe 2', 'Passe 4', 'Passe 6', 'Passe 8']);
        
        // Initial die ersten 4 auswählen
        this.updateImportPreview();
    },
    
    displayProgramGroup(programs, programNumber, containerId, passeMapping) {
        let html = '';
        
        programs.forEach((prog, idx) => {
            const disabled = idx >= 4 ? 'disabled' : '';
            const selected = idx < 4 ? 'selected' : '';
            const passeText = idx < 4 ? passeMapping[idx] : 'Nicht zugeordnet';
            
            html += `
                <div class="program-selection-card ${selected} ${disabled}" 
                     data-program="${programNumber}" data-index="${idx}" 
                     onclick="ImportManager.toggleProgramSelection(this)">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${prog.datetime}</strong><br>
                            <small class="text-muted">${prog.title}</small>
                        </div>
                        <div class="text-end">
                            <div class="total-value" style="font-size: 1.5rem;">${prog.total}</div>
                            <span class="passe-assignment">${passeText}</span>
                        </div>
                    </div>
                </div>
            `;
        });
        
        $(`#${containerId}`).html(html || '<p class="no-data">Keine Programme ' + programNumber + ' gefunden</p>');
    },
    
    toggleProgramSelection(element) {
        if ($(element).hasClass('disabled')) return;
        
        const programNumber = $(element).data('program');
        const index = $(element).data('index');
        
        // Anzahl der bereits ausgewählten Programme dieses Typs prüfen
        const selectedCount = $(`.program-selection-card[data-program="${programNumber}"].selected`).length;
        
        if ($(element).hasClass('selected')) {
            $(element).removeClass('selected');
        } else {
            if (selectedCount >= 4) {
                UIHelper.showToast(`Maximal 4 Programme ${programNumber} können importiert werden`, 'warning');
                return;
            }
            $(element).addClass('selected');
        }
        
        this.updateImportPreview();
    },
    
    updateImportPreview() {
        const mitgliedId = $('#mitgliedSelect').val();
        const jahr = $('#jahrSelect').val();
        
        if (!mitgliedId) {
            $('#importPreview').hide();
            return;
        }
        
        this.selectedPrograms = [];
        
        // Programme 133 sammeln
        $('.program-selection-card[data-program="133"].selected').each((selIndex, elem) => {
            const idx = $(elem).data('index');
            this.selectedPrograms.push({
                number: '133',
                index: selIndex + 1, // 1-basiert für Passe-Zuordnung
                total: this.programs133[idx].total,
                datetime: this.programs133[idx].datetime
            });
        });
        
        // Programme 134 sammeln
        $('.program-selection-card[data-program="134"].selected').each((selIndex, elem) => {
            const idx = $(elem).data('index');
            this.selectedPrograms.push({
                number: '134',
                index: selIndex + 1, // 1-basiert für Passe-Zuordnung
                total: this.programs134[idx].total,
                datetime: this.programs134[idx].datetime
            });
        });
        
        // Vorschau erstellen
        if (this.selectedPrograms.length > 0) {
            let previewHtml = '<table class="table table-sm">';
            previewHtml += '<thead><tr><th>Passe</th><th>Programm</th><th>Datum</th><th>Total</th></tr></thead>';
            previewHtml += '<tbody>';
            
            // Sortiere nach Passe
            const passes = [];
            this.selectedPrograms.forEach(prog => {
                let passeNr;
                if (prog.number === '133') {
                    passeNr = (prog.index * 2) - 1; // 1, 3, 5, 7
                } else {
                    passeNr = prog.index * 2; // 2, 4, 6, 8
                }
                passes.push({
                    passeNr: passeNr,
                    program: prog.number,
                    datetime: prog.datetime,
                    total: prog.total
                });
            });
            
            passes.sort((a, b) => a.passeNr - b.passeNr);
            
            passes.forEach(pass => {
                previewHtml += `
                    <tr>
                        <td><strong>Passe ${pass.passeNr}</strong></td>
                        <td>Programm ${pass.program}</td>
                        <td>${pass.datetime}</td>
                        <td class="text-success"><strong>${pass.total}</strong></td>
                    </tr>
                `;
            });
            
            previewHtml += '</tbody></table>';
            
            $('#previewContent').html(previewHtml);
            $('#importPreview').show();
        } else {
            $('#importPreview').hide();
        }
    },
    
    checkExistingData() {
        const mitgliedId = $('#mitgliedSelect').val();
        const jahr = $('#jahrSelect').val();
        
        if (!mitgliedId) return;
        
        $.ajax({
            url: 'resultimport/import_handler.php',
            type: 'GET',
            data: {
                action: 'check_existing',
                mitglied_id: mitgliedId,
                jahr: jahr
            },
            dataType: 'json',
            success: (response) => {
                if (response.success && response.exists) {
                    const existingPasses = response.existing_passes;
                    if (existingPasses.length > 0) {
                        $('#existingDataWarning').show();
                        $('#existingDataMessage').text(
                            `Es existieren bereits Daten für Passe: ${existingPasses.join(', ')}. Diese werden überschrieben!`
                        );
                    } else {
                        $('#existingDataWarning').hide();
                    }
                } else {
                    $('#existingDataWarning').hide();
                }
            }
        });
        
        this.updateImportPreview();
    },
    
    executeImport() {
        const mitgliedId = $('#mitgliedSelect').val();
        const jahr = $('#jahrSelect').val();
        
        if (!mitgliedId) {
            UIHelper.showToast('Bitte wähle ein Mitglied aus', 'warning');
            return;
        }
        
        if (this.selectedPrograms.length === 0) {
            UIHelper.showToast('Keine Programme zum Import ausgewählt', 'warning');
            return;
        }
        
        // Bestätigung
        if (!confirm('Möchtest du die ausgewählten Programme wirklich importieren?')) {
            return;
        }
        
        // Debug: Log was gesendet wird
        console.log('Sending import data:', {
            mitglied_id: mitgliedId,
            jahr: jahr,
            selected_programs: this.selectedPrograms
        });
        
        // AJAX Request
        $.ajax({
            url: 'resultimport/import_handler.php',
            type: 'POST',
            data: {
                action: 'import_results',
                csrf_token: CSRF_TOKEN,
                mitglied_id: mitgliedId,
                jahr: jahr,
                selected_programs: JSON.stringify(this.selectedPrograms)
            },
            dataType: 'json',
            success: (response) => {
                if (response.success) {
                    UIHelper.showToast('Import erfolgreich durchgeführt!', 'success');
                    setTimeout(() => {
                        window.location.href = 'heimresultate.php';
                    }, 2000);
                } else {
                    UIHelper.showToast('Fehler: ' + response.message, 'error');
                }
            },
            error: (xhr, status, error) => {
                UIHelper.showToast('Fehler beim Import: ' + error, 'error');
                console.error('Import error:', xhr.responseText);
            }
        });
    },
    
    resetImport() {
        $('#importSection').slideUp();
        this.selectedPrograms = [];
    }
};