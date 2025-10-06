// targetprint_handler.js - Helper functions for Zielscheiben-Ausdruck

/**
 * Formatiert eine Zahl mit führenden Nullen
 */
function padNumber(num, length = 2) {
    return String(num).padStart(length, '0');
}

/**
 * Formatiert ein Datum im deutschen Format
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return `${padNumber(date.getDate())}.${padNumber(date.getMonth() + 1)}.${date.getFullYear()}`;
}

/**
 * Validiert CSV-Inhalt
 */
function validateCSVContent(content) {
    if (!content || content.trim().length === 0) {
        return { valid: false, error: 'CSV-Datei ist leer' };
    }
    
    // Prüfe auf Semikolon-Trennung
    if (!content.includes(';')) {
        return { valid: false, error: 'CSV muss Semikolon-getrennt sein' };
    }
    
    // Prüfe auf mindestens eine Programmnummer
    const relevanteProgramme = ['522', '526', '525'];
    let hasRelevantProgram = false;
    
    relevanteProgramme.forEach(prog => {
        if (content.includes(prog + ';')) {
            hasRelevantProgram = true;
        }
    });
    
    if (!hasRelevantProgram) {
        return { 
            valid: false, 
            error: 'Keine relevanten Stiche gefunden (Endstich 522, Schwini 526, Programm 519)' 
        };
    }
    
    return { valid: true };
}

/**
 * Berechnet Statistiken für einen Stich
 */
function calculateStatistics(schuesse) {
    if (!schuesse || schuesse.length === 0) {
        return {
            total: 0,
            durchschnitt: 0,
            max: 0,
            min: 0,
            max100er: 0
        };
    }
    
    let total = 0;
    let max = 0;
    let min = 10;
    let max100er = 0;
    
    schuesse.forEach(schuss => {
        total += schuss.wert;
        if (schuss.wert > max) max = schuss.wert;
        if (schuss.wert < min) min = schuss.wert;
        if (schuss.hunderter > max100er) max100er = schuss.hunderter;
    });
    
    const durchschnitt = (total / schuesse.length).toFixed(2);
    
    return {
        total,
        durchschnitt,
        max,
        min,
        max100er
    };
}

/**
 * Erstellt eine formatierte Vorschau-Tabelle
 */
function createPreviewTable(schuesse, limit = 5) {
    if (!schuesse || schuesse.length === 0) {
        return '<p class="text-muted">Keine Schüsse vorhanden</p>';
    }
    
    let html = '<table class="table table-sm preview-table">';
    html += '<thead><tr>';
    html += '<th>Nr</th><th>Wertung</th><th>100er</th>';
    html += '</tr></thead><tbody>';
    
    const displaySchuesse = schuesse.slice(0, limit);
    
    displaySchuesse.forEach(schuss => {
        html += '<tr>';
        html += `<td>${schuss.schuss_nr}</td>`;
        html += `<td>${schuss.wert}</td>`;
        html += `<td>${schuss.hunderter}</td>`;
        html += '</tr>';
    });
    
    if (schuesse.length > limit) {
        html += '<tr><td colspan="3" class="text-center text-muted">';
        html += `... und ${schuesse.length - limit} weitere Schüsse`;
        html += '</td></tr>';
    }
    
    html += '</tbody></table>';
    
    return html;
}

/**
 * Download-Link für PDF erstellen
 */
function createDownloadLink(pdfPath, filename) {
    const link = document.createElement('a');
    link.href = pdfPath;
    link.download = filename;
    link.target = '_blank';
    link.textContent = filename;
    return link.outerHTML;
}

/**
 * Farbe für Wertung ermitteln
 */
function getColorForWertung(wert) {
    if (wert === 10) return '#FF0000'; // Rot
    if (wert === 9) return '#0000FF';  // Blau
    if (wert >= 7) return '#FF6400';   // Orange
    return '#000000';                   // Schwarz
}

/**
 * Debug-Ausgabe für Entwicklung
 */
function debugLog(message, data = null) {
    if (console && console.log) {
        const timestamp = new Date().toISOString();
        if (data) {
            console.log(`[${timestamp}] ${message}`, data);
        } else {
            console.log(`[${timestamp}] ${message}`);
        }
    }
}

/**
 * Exportiere Funktionen für globalen Zugriff
 */
window.TargetPrintHelper = {
    padNumber,
    formatDate,
    validateCSVContent,
    calculateStatistics,
    createPreviewTable,
    createDownloadLink,
    getColorForWertung,
    debugLog
};
