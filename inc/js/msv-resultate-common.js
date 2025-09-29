/**
 * MSV Resultate Common Library
 * Einheitliche Funktionen für Heim-, Kanti- und Endresultate
 * Version: 1.0.0
 */

(function(window, $) {
    'use strict';

    // Namespace für MSV Funktionen
    window.MSV = window.MSV || {};

    /**
     * Konfiguration für verschiedene Tabellentypen
     */
    const TABLE_CONFIGS = {
        heim: {
            tableId: 'heimresultateTabelle',
            columns: 9,
            loadUrl: 'heimresultate/load_heimresultate_form.php',
            saveUrl: 'heimresultate/save_heimresultate.php',
            deleteUrl: 'heimresultate/delete_heim.php',
            redirectUrl: 'heimrang.php',
            title: 'Heimmeisterschaft'
        },
        kanti: {
            tableId: 'kantiresultateTabelle',
            columns: 5,
            loadUrl: 'kantiresultate/load_kantiresultate_form.php',
            saveUrl: 'kantiresultate/save_kantiresultate.php',
            deleteUrl: 'kantiresultate/delete_kanti.php',
            redirectUrl: 'kantirang.php',
            title: 'Kantonalstich'
        },
        end: {
            tableId: 'mitgliederTabelle',
            columns: 8,
            loadUrl: 'endschresultate/load_endschresultate.php',
            saveUrl: 'endschresultate/save_schuss.php',
            deleteUrl: 'endschresultate/delete_endschresultate.php',
            deleteOneUrl: 'endschresultate/delete_endschresultat.php',
            redirectUrl: 'endschrang.php',
            title: 'Endschiessen',
            hasModal: true
        }
    };

    /**
     * Layout Manager - Einheitliches Höhen-Management
     */
    class LayoutManager {
        constructor() {
            this.resizeTimeout = null;
            this.RESIZE_DEBOUNCE = 120;
            this.initialized = false;
        }

        init() {
            if (this.initialized) return;
            
            this.applyViewportVars();
            this.bindEvents();
            this.initialized = true;
        }

        applyViewportVars() {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', vh + 'px');
            
            const headerEl = document.querySelector('.navbar, header, .site-header');
            const footerEl = document.querySelector('footer, .site-footer');
            
            const headerH = headerEl ? Math.round(headerEl.getBoundingClientRect().height) : 0;
            const footerH = footerEl ? Math.round(footerEl.getBoundingClientRect().height) : 0;
            
            document.documentElement.style.setProperty('--app-header', headerH + 'px');
            document.documentElement.style.setProperty('--app-footer', footerH + 'px');
        }

        calculateTableHeight(tableId) {
            const table = document.getElementById(tableId);
            if (!table) return;
            
            const tableResp = table.closest('.table-responsive');
            if (!tableResp) return;

            // Einfache Höhenberechnung basierend auf Viewport
            const navbar = document.querySelector('.navbar');
            const navbarH = navbar ? navbar.offsetHeight : 76;
            
            // Berechne Position der Tabelle
            const rect = tableResp.getBoundingClientRect();
            const topPosition = rect.top;
            
            // Padding unten
            const bottomPadding = 30;
            
            // Berechne verfügbare Höhe
            const viewportHeight = window.innerHeight;
            const availableHeight = viewportHeight - topPosition - bottomPadding;
            const maxHeight = Math.max(300, availableHeight); // Minimum 300px
            
            // Setze Höhe für table-responsive
            tableResp.style.maxHeight = maxHeight + 'px';
            tableResp.style.overflowY = 'auto';
            tableResp.style.overflowX = 'auto';
            
            // Debug Info
            console.log('Table Height Calculation:', {
                viewport: viewportHeight,
                tableTop: topPosition,
                available: availableHeight,
                applied: maxHeight
            });
        }

        bindEvents() {
            const self = this;
            
            window.addEventListener('load', () => self.refresh());
            
            window.addEventListener('resize', function() {
                clearTimeout(self.resizeTimeout);
                self.resizeTimeout = setTimeout(() => self.refresh(), self.RESIZE_DEBOUNCE);
            });
        }

        refresh() {
            this.applyViewportVars();
            
            // Aktualisiere alle bekannten Tabellen
            Object.values(TABLE_CONFIGS).forEach(config => {
                requestAnimationFrame(() => this.calculateTableHeight(config.tableId));
            });
        }
    }

    /**
     * Toast Notification System - Einheitlich für alle Seiten
     */
    class ToastManager {
        constructor() {
            this.containerId = 'toast-container';
            this.ensureContainer();
        }

        ensureContainer() {
            if (!document.getElementById(this.containerId)) {
                const container = document.createElement('div');
                container.id = this.containerId;
                container.style.cssText = 'position:fixed;top:80px;right:20px;z-index:9999;';
                document.body.appendChild(container);
            }
        }

        show(message, type = 'info') {
            this.ensureContainer();
            
            const iconMap = {
                'success': 'check-circle',
                'error': 'exclamation-circle',
                'warning': 'exclamation-triangle',
                'info': 'info-circle'
            };
            
            const icon = iconMap[type] || iconMap['info'];
            const toast = $('<div>')
                .addClass(`toast-message toast-${type}`)
                .html(`<i class="bi bi-${icon} me-2"></i>${message}`);
            
            $('#' + this.containerId).append(toast);
            
            setTimeout(() => toast.addClass('show'), 50);
            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        // Alias für Kompatibilität
        showMessage(message, type) {
            const typeMap = {
                'danger': 'error',
                'success': 'success',
                'warning': 'warning',
                'info': 'info'
            };
            this.show(message, typeMap[type] || 'info');
        }
    }

    /**
     * Input Handler - Einheitliche Input-Verwaltung
     */
    class InputHandler {
        constructor(tableId) {
            this.tableId = tableId;
        }

        bindInputs() {
            const tableSelector = '#' + this.tableId;
            const $inputs = $(tableSelector + ' input[type="number"], ' + tableSelector + ' input[type="text"]');
            
            // Enter/Tab Navigation
            $inputs.off('keydown.msv').on('keydown.msv', function(e) {
                if (e.key === 'Enter' || e.key === 'Tab') {
                    e.preventDefault();
                    const inputs = $(tableSelector + ' input');
                    const currentIndex = inputs.index(this);
                    const nextIndex = e.shiftKey ? currentIndex - 1 : currentIndex + 1;
                    const nextInput = inputs.eq(nextIndex);
                    
                    if (nextInput.length) {
                        nextInput.focus().select();
                    }
                }
            });
            
            // Focus Verhalten
            $inputs.off('focus.msv').on('focus.msv', function() {
                const $this = $(this);
                if ($this.val() === '0') {
                    $this.val('').select();
                } else if ($this.val() !== '') {
                    $this.select();
                }
            });
            
            // Blur Verhalten
            $inputs.off('blur.msv').on('blur.msv', function() {
                if ($(this).val().trim() === '') {
                    $(this).val('0');
                }
            });
            
            // Input Validierung (nur Zahlen)
            $inputs.filter('[type="number"]').off('input.msv').on('input.msv', function() {
                let value = $(this).val().replace(/[^0-9]/g, '');
                
                // Max 2 Zeichen für Schüsse (0-10), 3 für andere Werte
                const maxLength = $(this).attr('max') === '10' ? 2 : 3;
                if (value.length > maxLength) {
                    value = value.substring(0, maxLength);
                }
                
                $(this).val(value);
            });
        }

        fillEmptyWithZero() {
            $('#' + this.tableId + ' tbody tr').each(function() {
                const inputs = $(this).find('input[name*="passe"], input[name*="Schuss"]');
                let hasLaterValue = false;
                
                // Von hinten nach vorne durchgehen
                for (let i = inputs.length - 1; i >= 0; i--) {
                    const $input = $(inputs[i]);
                    const val = $input.val().trim();
                    
                    if (val !== '' && val !== '0') {
                        hasLaterValue = true;
                    } else if (hasLaterValue && val === '') {
                        $input.val('0');
                    }
                }
            });
        }
    }

    /**
     * Resultate Manager - Hauptklasse für Resultatverwaltung
     */
    class ResultateManager {
        constructor(type) {
            if (!TABLE_CONFIGS[type]) {
                throw new Error('Unbekannter Tabellentyp: ' + type);
            }
            
            this.type = type;
            this.config = TABLE_CONFIGS[type];
            this.layoutManager = new LayoutManager();
            this.toastManager = new ToastManager();
            this.inputHandler = new InputHandler(this.config.tableId);
            
            this.currentYear = new Date().getFullYear();
            this.basePath = '';
            this.deleteType = '';
            this.itemToDelete = null;
            
            this.init();
        }

        init() {
            const self = this;
            
            // Layout initialisieren
            this.layoutManager.init();
            
            // Jahr-Dropdown initialisieren
            this.initializeYearDropdown();
            
            // Event-Handler binden
            this.bindEvents();
            
            // Initiale Daten laden
            this.loadData(this.currentYear);
        }

        initializeYearDropdown() {
            const $yearSelect = $('#yearSelect');
            $yearSelect.empty();
            
            for (let year = 2024; year <= this.currentYear + 1; year++) {
                const $option = $('<option></option>')
                    .val(year)
                    .text(year);
                
                if (year === this.currentYear) {
                    $option.prop('selected', true);
                }
                
                $yearSelect.append($option);
            }
        }

        bindEvents() {
            const self = this;
            
            // Jahr-Auswahl
            $('#yearSelect').off('change.msv').on('change.msv', function() {
                self.loadData($(this).val());
            });
            
            // Redirect Button
            $('#redirect-btn').off('click.msv').on('click.msv', function() {
                window.location.href = self.basePath + self.config.redirectUrl;
            });
            
            // Delete All Button
            $('#delete-btn, #delall-btn').off('click.msv').on('click.msv', function(e) {
                e.preventDefault();
                self.deleteType = 'all';
                self.showDeleteConfirmation('all');
            });
            
            // Delete Single Button (dynamisch)
            $(document).off('click.msv', '.delete-btn').on('click.msv', '.delete-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.deleteType = 'single';
                self.itemToDelete = $(this).data('id');
                const name = $(this).closest('tr').find('td:first').text();
                self.showDeleteConfirmation('single', name);
            });
            
            // Form Submit (für Heim/Kanti)
            $('#heimresultateForm, #kantiresultateForm').off('submit.msv').on('submit.msv', function(e) {
                e.preventDefault();
                self.saveData();
            });
            
            // Confirm Action
            $('#confirmAction').off('click.msv').on('click.msv', function() {
                self.executeDelete();
            });
            
            // Modal verstecken - Reset
            $('#confirmModal').on('hidden.bs.modal', function() {
                self.deleteType = '';
                self.itemToDelete = null;
            });
            
            // Spezielle End-Resultate Events
            if (this.type === 'end') {
                this.bindEndResultateEvents();
            }
        }

        bindEndResultateEvents() {
            const self = this;
            
            // Edit Button
            $(document).off('click.msv', '.edit-btn').on('click.msv', '.edit-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.openEditModal($(this).data('id'));
            });
            
            // Schuss Form Submit
            $('#schussForm').off('submit.msv').on('submit.msv', function(e) {
                e.preventDefault();
                self.saveSchussData();
            });
        }

        loadData(year) {
            const self = this;
            const $tbody = $('#' + this.config.tableId + ' tbody');
            
            // Loading indicator
            $tbody.html(
                `<tr><td colspan="${this.config.columns}" class="text-center py-4">` +
                `<div class="spinner-border spinner-border-sm text-primary me-2"></div>` +
                `Lade Daten...</td></tr>`
            );
            
            $.ajax({
                url: this.basePath + this.config.loadUrl,
                type: 'GET',
                data: { year: year },
                success: function(response) {
                    $tbody.html(response);
                    self.inputHandler.bindInputs();
                    self.layoutManager.refresh();
                    self.toastManager.show('Daten erfolgreich geladen', 'success');
                    
                    // Mobile Enhancement für Kanti
                    if (self.type === 'kanti') {
                        self.enhanceMobileRows();
                    }
                },
                error: function() {
                    $tbody.html(
                        `<tr><td colspan="${self.config.columns}" class="text-center text-danger py-4">` +
                        `<i class="bi bi-exclamation-triangle me-2"></i>` +
                        `Fehler beim Laden der Daten</td></tr>`
                    );
                    self.toastManager.show('Fehler beim Laden der Daten', 'error');
                }
            });
        }

        saveData() {
            const self = this;
            const $submitBtn = $('button[type="submit"]');
            const originalText = $submitBtn.html();
            
            $submitBtn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Speichere...');
            
            // Leere Felder mit 0 füllen
            this.inputHandler.fillEmptyWithZero();
            
            const selectedYear = $('#yearSelect').val();
            const formSelector = this.type === 'heim' ? '#heimresultateForm' : '#kantiresultateForm';
            const formData = $(formSelector).serialize() + '&year=' + selectedYear + '&jahr=' + selectedYear;
            
            $.ajax({
                url: this.basePath + this.config.saveUrl,
                type: 'POST',
                data: formData,
                success: function() {
                    self.toastManager.show('Ergebnisse erfolgreich gespeichert!', 'success');
                    setTimeout(() => self.loadData(selectedYear), 1000);
                },
                error: function() {
                    self.toastManager.show('Fehler beim Speichern der Ergebnisse', 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).html(originalText);
                }
            });
        }

        showDeleteConfirmation(type, name = '') {
            let message = '';
            
            if (type === 'all') {
                message = `
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle text-danger me-3" style="font-size: 2rem;"></i>
                        <div>
                            <strong>Möchten Sie wirklich ALLE Resultate des aktuellen Jahres löschen?</strong>
                            <br><small class="text-muted">Diese Aktion kann nicht rückgängig gemacht werden!</small>
                        </div>
                    </div>
                `;
            } else {
                message = `
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle text-warning me-3" style="font-size: 2rem;"></i>
                        <div>
                            <strong>Möchten Sie die Resultate von "${name}" wirklich löschen?</strong>
                            <br><small class="text-muted">Diese Aktion kann nicht rückgängig gemacht werden.</small>
                        </div>
                    </div>
                `;
            }
            
            $('#confirmModal .modal-body').html(message);
            $('#confirmModal').modal('show');
        }

        executeDelete() {
            const self = this;
            const $btn = $('#confirmAction');
            const selectedYear = $('#yearSelect').val();
            
            $btn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2"></span>Verarbeite...');
            
            let ajaxConfig = {
                method: 'POST',
                data: {
                    jahr: selectedYear,
                    year: selectedYear,
                    csrf_token: $('input[name="csrf_token"]').val()
                },
                complete: function() {
                    $btn.prop('disabled', false)
                        .html('<i class="bi bi-check-circle me-1"></i>Bestätigen');
                    $('#confirmModal').modal('hide');
                }
            };
            
            if (this.deleteType === 'all') {
                ajaxConfig.url = this.basePath + this.config.deleteUrl;
                ajaxConfig.success = function() {
                    self.toastManager.show('Alle Resultate erfolgreich gelöscht', 'success');
                    setTimeout(() => self.loadData(selectedYear), 800);
                };
                ajaxConfig.error = function() {
                    self.toastManager.show('Fehler beim Löschen', 'error');
                };
            } else if (this.deleteType === 'single' && this.itemToDelete !== null) {
                ajaxConfig.url = this.basePath + (this.config.deleteOneUrl || this.config.deleteUrl);
                ajaxConfig.data.mitgliedID = this.itemToDelete;
                ajaxConfig.success = function() {
                    self.toastManager.show('Resultate erfolgreich gelöscht', 'success');
                    setTimeout(() => self.loadData(selectedYear), 800);
                };
                ajaxConfig.error = function() {
                    self.toastManager.show('Fehler beim Löschen', 'error');
                };
            }
            
            $.ajax(ajaxConfig);
        }

        enhanceMobileRows() {
            if (!window.matchMedia('(max-width: 768px)').matches) return;
            
            const $tbody = $('#' + this.config.tableId + ' tbody');
            $tbody.find('.kanti-row-more, .kanti-more-btn').remove();
            
            $('#' + this.config.tableId + ' tbody tr').each(function() {
                const $tr = $(this);
                const $cells = $tr.children('td');
                
                if ($cells.length < 5) return;
                
                const more = {
                    passe3: $cells.eq(2).html(),
                    passe4: $cells.eq(3).html(),
                    passe5: $cells.eq(4).html()
                };
                
                const $firstCell = $cells.first();
                const btn = $('<button type="button" class="btn btn-outline-secondary btn-sm kanti-more-btn" aria-expanded="false">' +
                           '<i class="bi bi-three-dots"></i> Mehr</button>');
                
                $firstCell.append('<br>').append(btn);
                
                const $moreRow = $(`
                    <tr class="kanti-row-more" style="display:none;">
                        <td colspan="${$cells.length}">
                            <div>
                                <strong>Passe 3:</strong> ${more.passe3 || '-'}<br>
                                <strong>Passe 4:</strong> ${more.passe4 || '-'}<br>
                                <strong>Passe 5:</strong> ${more.passe5 || '-'}
                            </div>
                        </td>
                    </tr>
                `);
                
                $tr.after($moreRow);
            });
            
            $(document).off('click.msvMobile', '.kanti-more-btn').on('click.msvMobile', '.kanti-more-btn', function() {
                const $tr = $(this).closest('tr');
                const $moreRow = $tr.next('.kanti-row-more');
                $moreRow.toggle();
                $(this).attr('aria-expanded', $moreRow.is(':visible'));
            });
        }

        // Spezielle Methoden für End-Resultate
        openEditModal(mitgliedID) {
            const self = this;
            const selectedYear = $('#yearSelect').val();
            const name = $(`[data-id="${mitgliedID}"]`).closest('tr').find('td:first').text();
            
            $('#schussModalLabel').html(`<i class="bi bi-target me-2"></i> Erfassen - ${name}`);
            $('#mitgliedID').val(mitgliedID);
            $('#schussForm')[0].reset();
            $('.total-display').text('0');
            
            $('#schussModal').modal('show');
            
            // Daten laden
            $.get(this.basePath + 'endschresultate/load_schussdaten.php', {
                mitgliedID: mitgliedID,
                year: selectedYear,
                csrf_token: $('input[name="csrf_token"]').val()
            }).done(function(response) {
                try {
                    const data = JSON.parse(response);
                    Object.keys(data).forEach(key => {
                        const $field = $('#' + key);
                        if ($field.length) {
                            $field.val(data[key]);
                        }
                    });
                    self.calculateAllSums();
                    self.toastManager.show('Daten erfolgreich geladen', 'success');
                } catch(e) {
                    self.toastManager.show('Fehler beim Parsen der Daten', 'error');
                }
            }).fail(function() {
                self.toastManager.show('Fehler beim Laden der Schussdaten', 'error');
            });
        }

        saveSchussData() {
            const self = this;
            const $btn = $('#schussModal button[type="submit"]');
            const originalText = $btn.html();
            
            $btn.prop('disabled', true)
                .html('<i class="bi bi-hourglass-split me-2"></i>Speichere...');
            
            const selectedYear = $('#yearSelect').val();
            const formData = $('#schussForm').serialize() + '&year=' + selectedYear;
            
            $.post(this.basePath + 'endschresultate/save_schuss.php', formData)
                .done(function(response) {
                    self.toastManager.show('Resultate erfolgreich gespeichert!', 'success');
                    $('#schussModal').modal('hide');
                    setTimeout(() => self.loadData(selectedYear), 800);
                })
                .fail(function() {
                    self.toastManager.show('Fehler beim Speichern', 'error');
                })
                .always(function() {
                    $btn.prop('disabled', false).html(originalText);
                });
        }

        calculateAllSums() {
            this.calculateSum('.endschuss', 'endstichSumme');
            this.calculateSum('.schwini-schuss1', 'schwiniSumme1');
            this.calculateSum('.schwini-schuss2', 'schwiniSumme2');
            this.calculateSum('.kunst', 'kunstSum');
            this.calculateSum('.zabig', 'zabigsum');
            this.calculateSum('.sieunder', 'sieunderSum');
        }

        calculateSum(selector, sumId) {
            let sum = 0;
            $(selector).each(function() {
                sum += parseInt($(this).val()) || 0;
            });
            $('#' + sumId).text(sum);
        }
    }

    /**
     * Global Scroll Delegation - Scroll-Events an Tabelle weiterleiten
     */
    function enableGlobalScroll() {
        // Scroll-Events an Tabelle weiterleiten wenn Fokus auf der Seite
        // Verwende native addEventListener mit { passive: false } für preventDefault()
        document.addEventListener('wheel', function(e) {
            const $tableContainer = $('.table-responsive');
            
            if ($tableContainer.length) {
                const container = $tableContainer[0];
                const deltaY = e.deltaY;
                
                // Prüfe ob die Tabelle scrollbar ist
                if (container.scrollHeight > container.clientHeight) {
                    container.scrollTop += deltaY;
                    e.preventDefault();
                }
            }
        }, { passive: false });
    }

    // Public API
    window.MSV.ResultateManager = ResultateManager;
    window.MSV.LayoutManager = LayoutManager;
    window.MSV.ToastManager = ToastManager;
    window.MSV.InputHandler = InputHandler;
    window.MSV.enableGlobalScroll = enableGlobalScroll;
    
    // Helper für Kompatibilität
    window.MSV.init = function(type) {
        return new ResultateManager(type);
    };

})(window, jQuery);
