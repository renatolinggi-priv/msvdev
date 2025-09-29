// import_manager.js - Vereinfachter Manager für 3-Phasen-Workflow
const ImportManager = {
    selectedHeimPrograms: [], // Finale Auswahl Heim-Programme
    selectedKantiPrograms: [], // Finale Auswahl Kanti-Programme
    
    init() {
        this.setupEventListeners();
    },
    
    setupEventListeners() {
        // Simplified event listeners
        $('#mitgliedSelect').on('change', () => this.onMemberChange());
        $('#jahrSelect').on('change', () => this.onYearChange());
    },
    
    onMemberChange() {
        // Optional: Check existing data when member changes
        console.log('Member changed:', $('#mitgliedSelect').val());
    },
    
    onYearChange() {
        // Optional: Check existing data when year changes
        console.log('Year changed:', $('#jahrSelect').val());
    },
    
    // Anpassung für import_manager.js - preselectMemberByLicense Funktion
// Ersetze die existierende preselectMemberByLicense Funktion mit dieser Version:

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

    // FIX 1: Verwende POST statt GET um HTTP2 Probleme zu vermeiden
    $.ajax({
        url: 'heimkanti_import/import_handler.php',
        type: 'POST',  // Ändere zu POST
        cache: false,
        data: {
            action: 'find_member_by_license',
            license: lizenzNr
        },
        dataType: 'json',
        timeout: 10000,  // FIX 2: Füge Timeout hinzu
        headers: {
            'X-Requested-With': 'XMLHttpRequest'  // FIX 3: Expliziter AJAX Header
        },
        beforeSend: function(xhr) {
            // FIX 4: Setze zusätzliche Header um HTTP2 Probleme zu vermeiden
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        },
        success: (response) => {
            console.log('[ENDSCH-DEBUG] Member search response:', response);
            
            if (response && response.success && response.member_id) {
                console.log('[ENDSCH-DEBUG] Found member:', response.member_name, 'ID:', response.member_id);
                
                setTimeout(() => {
                    const $select = $('#mitgliedSelect');
                    console.log('[ENDSCH-DEBUG] Select element found:', $select.length > 0);
                    console.log('[ENDSCH-DEBUG] Current select options count:', $select.find('option').length);
                    
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
            console.error('[ENDSCH-DEBUG] Status:', status);
            console.error('[ENDSCH-DEBUG] XHR Status:', xhr.status);
            console.error('[ENDSCH-DEBUG] Response Text:', xhr.responseText);
            
            // FIX 5: Detailliertere Fehlerbehandlung
            if (status === 'timeout') {
                UIHelper.showToast('Zeitüberschreitung bei der Suche nach Lizenznummer.', 'error');
            } else if (xhr.status === 0) {
                // HTTP2_PROTOCOL_ERROR führt oft zu Status 0
                console.log('[ENDSCH-DEBUG] Possible HTTP2 error, retrying with fallback...');
                // Fallback: Versuche es nochmal mit einem vereinfachten Request
                ImportManagerSingle.preselectMemberByLicenseFallback(lizenzNr);
            } else {
                UIHelper.showToast('Suche nach Lizenznummer fehlgeschlagen.', 'error');
            }
        }
    });
},

// FIX 6: Fallback-Methode mit nativem fetch API statt jQuery
preselectMemberByLicenseFallback(lizenzNr) {
    console.log('[ENDSCH-DEBUG] Using fallback method with fetch API');
    
    const formData = new FormData();
    formData.append('action', 'find_member_by_license');
    formData.append('license', lizenzNr);
    
    fetch('heimkanti_import/import_handler.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('[ENDSCH-DEBUG] Fallback response:', data);
        
        if (data && data.success && data.member_id) {
            const $select = $('#mitgliedSelect');
            
            setTimeout(() => {
                $select.val(data.member_id);
                if ($select.val() === String(data.member_id)) {
                    $select.trigger('change');
                    UIHelper.showToast(`Mitglied ${data.member_name} wurde anhand der Lizenznummer vorselektiert`, 'success');
                } else {
                    // Option hinzufügen falls nicht vorhanden
                    $select.append(`<option value="${data.member_id}">${data.member_name}</option>`);
                    $select.val(data.member_id).trigger('change');
                    UIHelper.showToast(`Mitglied ${data.member_name} wurde anhand der Lizenznummer vorselektiert`, 'success');
                }
            }, 500);
        } else {
            UIHelper.showToast(`Kein Mitglied mit Lizenznummer ${lizenzNr} gefunden`, 'warning');
        }
    })
    .catch(error => {
        console.error('[ENDSCH-DEBUG] Fallback error:', error);
        UIHelper.showToast('Suche nach Lizenznummer fehlgeschlagen.', 'error');
    });
},
    
    reset() {
        this.selectedHeimPrograms = [];
        this.selectedKantiPrograms = [];
        console.log('ImportManager reset');
    },
    
    // Simplified - no complex program selection needed, handled by FileHandler
    
    async executeImport(forceOverwrite = false) {
        const mitgliedId = $('#mitgliedSelect').val();
        const jahr = $('#jahrSelect').val();
        
        if (!mitgliedId) {
            UIHelper.showToast('Bitte wähle ein Mitglied aus', 'warning');
            return;
        }
        
        const totalSelected = this.selectedHeimPrograms.length + this.selectedKantiPrograms.length;
        if (totalSelected === 0) {
            UIHelper.showToast('Keine Programme zum Import ausgewählt', 'warning');
            return;
        }
        
        console.log('Starting import for Heim:', this.selectedHeimPrograms.length, 'Kanti:', this.selectedKantiPrograms.length);
        
        // Zeige Loading
        this.showLoadingOverlay('Import wird durchgeführt...');
        
        try {
            // Importiere beide Typen parallel
            const importPromises = [];
            
            if (this.selectedHeimPrograms.length > 0) {
                const heimData = this.selectedHeimPrograms.map(prog => ({
                    number: prog.number,
                    index: prog.passeNr,
                    total: prog.total,
                    datetime: prog.datetime
                }));
                
                importPromises.push(
                    $.ajax({
                        url: 'heimkanti_import/import_handler.php',
                        type: 'POST',
                        data: {
                            action: 'import_results',
                            csrf_token: CSRF_TOKEN,
                            mitglied_id: mitgliedId,
                            jahr: jahr,
                            stich_type: 'Heimmeisterschaft',
                            selected_programs: JSON.stringify(heimData)
                        },
                        dataType: 'json'
                    }).then(result => ({ ...result, type: 'heim' }))
                );
            }
            
            if (this.selectedKantiPrograms.length > 0) {
                const kantiData = this.selectedKantiPrograms.map(prog => ({
                    number: prog.number,
                    index: prog.passeNr,
                    total: prog.total,
                    datetime: prog.datetime
                }));
                
                importPromises.push(
                    $.ajax({
                        url: 'heimkanti_import/import_handler.php',
                        type: 'POST',
                        data: {
                            action: 'import_results',
                            csrf_token: CSRF_TOKEN,
                            mitglied_id: mitgliedId,
                            jahr: jahr,
                            stich_type: 'Kantonalstich',
                            selected_programs: JSON.stringify(kantiData)
                        },
                        dataType: 'json'
                    }).then(result => ({ ...result, type: 'kanti' }))
                );
            }
            
            const results = await Promise.all(importPromises);
            this.hideLoadingOverlay();
            
            const successCount = results.filter(r => r.success).length;
            const failCount = results.length - successCount;
            
            if (failCount === 0) {
                UIHelper.showToast('Import erfolgreich durchgeführt!', 'success');
                setTimeout(() => {
                    // Gehe zur ersten erfolgreichen Seite
                    const heimSuccess = results.find(r => r.type === 'heim' && r.success);
                    const kantiSuccess = results.find(r => r.type === 'kanti' && r.success);
                    
                    if (heimSuccess) {
                        window.location.href = 'heimkanti_import.php';
                    } else if (kantiSuccess) {
                        window.location.href = 'heimkanti_import.php';
                    }
                }, 2000);
            } else {
                const errors = results.filter(r => !r.success).map(r => r.message || 'Unbekannter Fehler');
                UIHelper.showToast('Teilweise Fehler beim Import: ' + errors.join(', '), 'warning');
            }
            
        } catch (error) {
            this.hideLoadingOverlay();
            UIHelper.showToast('Fehler beim Import: ' + error, 'error');
            console.error('Import error:', error);
        }
    },
    
    showLoadingOverlay(message) {
        const overlay = $(`
            <div class="loading-overlay">
                <div class="loading-spinner">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <div>${message}</div>
                </div>
            </div>
        `);
        $('body').append(overlay);
    },
    
    hideLoadingOverlay() {
        $('.loading-overlay').remove();
    }
};