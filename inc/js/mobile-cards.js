/**
 * MSV Mobile Cards Helper
 * Generiert aus einer Tabelle eine mobile Card-Ansicht
 * Wiederverwendbar für alle Seiten mit Tabellen-Darstellung
 */
const MSVMobileCards = {

    /**
     * Tabelle in Cards umwandeln
     * @param {string} tableSelector - CSS Selector der Tabelle
     * @param {string} containerSelector - CSS Selector des Card-Containers
     * @param {object} options - Konfiguration
     *   - titleColumns: [0, 1] - Spalten-Indizes für Card-Titel
     *   - summaryColumns: [2] - Spalten-Indizes für Summary-Zeile
     *   - excludeColumns: [5] - Spalten ausblenden
     *   - badgeColumn: 3 - Spalte als Badge darstellen
     *   - rankColumn: 0 - Spalte mit Rang (für Top-3 Highlighting)
     *   - customCardClass: function(row, cells) - Custom CSS-Klasse für Card
     *   - customHtml: function(row, cells, headers) - Custom HTML-Generator
     */
    buildCards: function(tableSelector, containerSelector, options = {}) {
        const table = document.querySelector(tableSelector);
        const container = document.querySelector(containerSelector);

        if (!table || !container) {
            console.warn('MSVMobileCards: Table or container not found', {tableSelector, containerSelector});
            return;
        }

        const scrollContainer = container.querySelector('.mobile-cards-scroll');
        if (!scrollContainer) {
            console.warn('MSVMobileCards: .mobile-cards-scroll not found in container');
            return;
        }

        const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
        const rows = table.querySelectorAll('tbody tr');

        if (rows.length === 0) {
            scrollContainer.innerHTML = `
                <div class="mobile-cards-empty">
                    <i class="bi bi-inbox"></i>
                    <div>Keine Daten vorhanden</div>
                </div>`;
            return;
        }

        let html = '';
        rows.forEach((row, idx) => {
            const cells = Array.from(row.querySelectorAll('td'));
            if (cells.length === 0) return;

            // Custom HTML Generator verwenden falls vorhanden
            if (options.customHtml) {
                html += options.customHtml(row, cells, headers, idx);
                return;
            }

            // Standard Card-Generierung
            const titleColumns = options.titleColumns || [0, 1];
            const summaryColumns = options.summaryColumns || [];
            const excludeColumns = options.excludeColumns || [];

            // Title aus konfigurierten Spalten
            const titleParts = titleColumns
                .map(i => cells[i]?.textContent?.trim() || '')
                .filter(Boolean);

            // Summary
            const summaryParts = summaryColumns
                .map(i => {
                    const value = cells[i]?.textContent?.trim() || '-';
                    return `${headers[i]}: ${value}`;
                })
                .filter(Boolean);

            // Rank-basierte CSS-Klasse für Top 3
            let rankClass = '';
            if (options.rankColumn !== undefined) {
                const rank = parseInt(cells[options.rankColumn]?.textContent?.trim() || '0');
                if (rank >= 1 && rank <= 3) {
                    rankClass = ` rank-${rank}`;
                }
            }

            // Custom CSS-Klasse
            let customClass = '';
            if (options.customCardClass) {
                customClass = ' ' + options.customCardClass(row, cells);
            }

            // Details (alle anderen Spalten)
            let details = '';
            cells.forEach((cell, i) => {
                if (excludeColumns.includes(i)) return;
                if (titleColumns.includes(i)) return;
                if (summaryColumns.includes(i)) return;

                const label = headers[i] || '';
                let value = cell.innerHTML;

                // Badge-Spalte
                if (options.badgeColumn === i) {
                    value = `<span class="badge bg-secondary">${cell.textContent.trim()}</span>`;
                }

                details += `
                    <div class="mobile-card-detail-row">
                        <span class="mobile-card-detail-label">${label}</span>
                        <span class="mobile-card-detail-value">${value}</span>
                    </div>`;
            });

            html += `
            <div class="mobile-card${rankClass}${customClass}" data-index="${idx}">
                <div class="mobile-card-header" onclick="MSVMobileCards.toggle(this)">
                    <div>
                        <div class="fw-bold">${titleParts.join(' ')}</div>
                        ${summaryParts.length ? `<small class="text-muted">${summaryParts.join(' | ')}</small>` : ''}
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="mobile-card-body">${details}</div>
            </div>`;
        });

        scrollContainer.innerHTML = html;
    },

    /**
     * Toggle Card auf/zu
     * @param {HTMLElement} header - Card-Header Element
     */
    toggle: function(header) {
        const card = header.closest('.mobile-card');
        if (!card) return;

        card.classList.toggle('open');

        const icon = header.querySelector('i');
        if (icon) {
            icon.classList.toggle('bi-chevron-down');
            icon.classList.toggle('bi-chevron-up');
        }
    },

    /**
     * Filtert Cards basierend auf Suchbegriff
     * @param {HTMLInputElement} searchInput - Suchfeld
     * @param {string} containerSelector - CSS Selector des Card-Containers
     */
    filterCards: function(searchInput, containerSelector) {
        const query = searchInput.value.toLowerCase();
        const cards = document.querySelectorAll(`${containerSelector} .mobile-card`);

        let visibleCount = 0;
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            const isVisible = text.includes(query);
            card.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });

        // Empty State anzeigen wenn keine Resultate
        const scrollContainer = document.querySelector(`${containerSelector} .mobile-cards-scroll`);
        if (scrollContainer) {
            const emptyState = scrollContainer.querySelector('.mobile-cards-empty');
            if (visibleCount === 0 && !emptyState) {
                const lastCard = scrollContainer.lastElementChild;
                if (lastCard) {
                    lastCard.insertAdjacentHTML('afterend', `
                        <div class="mobile-cards-empty">
                            <i class="bi bi-search"></i>
                            <div>Keine Treffer gefunden</div>
                        </div>`);
                }
            } else if (visibleCount > 0 && emptyState) {
                emptyState.remove();
            }
        }
    },

    /**
     * Filtert Cards mit Debouncing (für bessere Performance)
     * @param {HTMLInputElement} searchInput - Suchfeld
     * @param {string} containerSelector - CSS Selector des Card-Containers
     * @param {number} delay - Delay in ms (default: 250)
     */
    filterCardsDebounced: function(searchInput, containerSelector, delay = 250) {
        if (this._debounceTimer) {
            clearTimeout(this._debounceTimer);
        }

        this._debounceTimer = setTimeout(() => {
            this.filterCards(searchInput, containerSelector);
        }, delay);
    },

    /**
     * Initialisiert Mobile-Ansicht nur auf Mobile-Breakpoint
     * @param {Function} buildFunction - Funktion die buildCards() aufruft
     * @returns {MediaQueryList} - MediaQueryList Objekt für weitere Verwendung
     */
    initResponsive: function(buildFunction) {
        const isMobile = window.matchMedia('(max-width: 767.98px)');

        function checkAndBuild() {
            if (isMobile.matches) {
                buildFunction();
            }
        }

        // Initial
        checkAndBuild();

        // Bei Resize
        isMobile.addEventListener('change', checkAndBuild);

        return isMobile;
    },

    /**
     * Zeigt Loading-State in Mobile-Container
     * @param {string} containerSelector - CSS Selector des Card-Containers
     */
    showLoading: function(containerSelector) {
        const container = document.querySelector(containerSelector);
        if (!container) return;

        const scrollContainer = container.querySelector('.mobile-cards-scroll');
        if (scrollContainer) {
            scrollContainer.innerHTML = `
                <div class="mobile-cards-loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Laden...</span>
                    </div>
                </div>`;
        }
    },

    /**
     * Zeigt Error-State in Mobile-Container
     * @param {string} containerSelector - CSS Selector des Card-Containers
     * @param {string} message - Fehlermeldung
     */
    showError: function(containerSelector, message = 'Fehler beim Laden der Daten') {
        const container = document.querySelector(containerSelector);
        if (!container) return;

        const scrollContainer = container.querySelector('.mobile-cards-scroll');
        if (scrollContainer) {
            scrollContainer.innerHTML = `
                <div class="mobile-cards-empty text-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    <div>${message}</div>
                </div>`;
        }
    }
};
