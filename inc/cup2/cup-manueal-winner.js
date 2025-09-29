/**
 * cup-manual-winner.js
 * JavaScript-Funktionen für die Integration der manuellen Gewinner-Funktion
 */

// Globale Variablen
let currentPairData = null;
let currentRound = 1;

// ===== HAUPTFUNKTIONEN =====

/**
 * Lädt die Paarungen für eine bestimmte Runde
 */
function loadPairs(round, year = null) {
    if (!year) year = new Date().getFullYear();
    
    $.ajax({
        url: 'fetch_pairs.php',
        type: 'GET',
        data: { round: round, year: year },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayPairs(response.data, round);
                updateStatistics(response.stats);
            } else {
                showError('Fehler beim Laden der Paarungen: ' + response.error);
            }
        },
        error: function(xhr, status, error) {
            showError('Verbindungsfehler: ' + error);
        }
    });
}

/**
 * Zeigt die Paarungen in der UI an
 */
function displayPairs(pairs, round) {
    const container = $('#pairs-container');
    container.empty();
    
    if (pairs.length === 0) {
        container.html('<p class="no-data">Keine Paarungen für Runde ' + round + ' vorhanden.</p>');
        return;
    }
    
    pairs.forEach(function(pair) {
        const pairElement = createPairElement(pair, round);
        container.append(pairElement);
    });
}

/**
 * Erstellt ein HTML-Element für eine Paarung
 */
function createPairElement(pair, round) {
    const isThreePair = pair.Participant3 !== null;
    const hasManualWinner = pair.is_manual_winner;
    
    let html = '<div class="pair-card" data-pair-id="' + pair.ID + '">';
    html += '<div class="pair-header">';
    html += '<span class="pair-number">Paarung #' + pair.ID + '</span>';
    
    // Markierung für manuelle Gewinner
    if (hasManualWinner) {
        html += '<span class="manual-winner-badge" title="' + (pair.manual_winner_reason || 'Manuell gesetzt') + '">Manueller Gewinner</span>';
    }
    
    html += '</div>';
    html += '<div class="pair-content">';
    
    // Teilnehmer anzeigen
    const participants = [
        { id: pair.Participant1, name: pair.full_name_1, result: pair.Result1, lowshot: pair.LowShot1 },
        { id: pair.Participant2, name: pair.full_name_2, result: pair.Result2, lowshot: pair.LowShot2 }
    ];
    
    if (isThreePair) {
        participants.push({ 
            id: pair.Participant3, 
            name: pair.full_name_3, 
            result: pair.Result3, 
            lowshot: pair.LowShot3 
        });
    }
    
    // Gewinner ermitteln
    let winners = [];
    if (hasManualWinner) {
        winners = [pair.manual_winner_id];
    } else if (pair.winners) {
        winners = pair.winners;
    } else if (pair.winner_id) {
        winners = [pair.winner_id];
    }
    
    // Teilnehmer-Tabelle
    html += '<table class="participants-table">';
    html += '<thead><tr><th>Teilnehmer</th><th>Resultat</th><th>Tiefschuss</th><th>Status</th></tr></thead>';
    html += '<tbody>';
    
    participants.forEach(function(p) {
        const isWinner = winners.includes(p.id);
        const rowClass = isWinner ? 'winner-row' : 'loser-row';
        
        html += '<tr class="' + rowClass + '">';
        html += '<td>' + p.name + '</td>';
        html += '<td class="result">' + (p.result !== null ? p.result : '-') + '</td>';
        html += '<td class="lowshot">' + (p.lowshot !== null ? p.lowshot : '-') + '</td>';
        html += '<td>';
        
        if (isWinner) {
            html += '<span class="status-winner">Gewinner</span>';
        } else {
            html += '<span class="status-loser">Verlierer</span>';
        }
        
        html += '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    
    // Aktionen
    html += '<div class="pair-actions">';
    
    // Button für manuellen Gewinner (nur für Runde 1 und 2)
    if (round < 3) {
        html += '<button class="btn btn-manual-winner" onclick="showManualWinnerDialog(' + pair.ID + ')">';
        html += hasManualWinner ? 'Manuellen Gewinner ändern' : 'Manuellen Gewinner setzen';
        html += '</button>';
    }
    
    // Löschen-Button
    html += '<button class="btn btn-delete" onclick="deletePair(' + pair.ID + ')">Paarung löschen</button>';
    
    html += '</div>';
    html += '</div>';
    html += '</div>';
    
    return html;
}

/**
 * Zeigt den Dialog zum Setzen eines manuellen Gewinners
 */
function showManualWinnerDialog(pairId) {
    // Paarungsdaten abrufen
    $.ajax({
        url: 'fetch_pairs.php',
        type: 'GET',
        data: { round: currentRound, year: new Date().getFullYear() },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const pair = response.data.find(p => p.ID === pairId);
                if (pair) {
                    openManualWinnerModal(pair);
                }
            }
        }
    });
}

/**
 * Öffnet das Modal für manuelle Gewinner-Auswahl
 */
function openManualWinnerModal(pair) {
    currentPairData = pair;
    
    let modalHtml = `
        <div id="manual-winner-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Manuellen Gewinner setzen</h2>
                    <span class="close" onclick="closeManualWinnerModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <p>Wähle einen Teilnehmer aus, der manuell als Gewinner markiert werden soll:</p>
                    
                    <div class="participant-selection">
    `;
    
    // Teilnehmer-Optionen
    const participants = [
        { id: pair.Participant1, name: pair.full_name_1 },
        { id: pair.Participant2, name: pair.full_name_2 }
    ];
    
    if (pair.Participant3) {
        participants.push({ id: pair.Participant3, name: pair.full_name_3 });
    }
    
    participants.forEach(function(p) {
        const isCurrentManual = pair.manual_winner_id === p.id;
        modalHtml += `
            <label class="participant-option ${isCurrentManual ? 'current-manual' : ''}">
                <input type="radio" name="manual_winner" value="${p.id}" ${isCurrentManual ? 'checked' : ''}>
                <span>${p.name}</span>
            </label>
        `;
    });
    
    // Option zum Entfernen des manuellen Gewinners
    if (pair.manual_winner_id) {
        modalHtml += `
            <label class="participant-option remove-option">
                <input type="radio" name="manual_winner" value="">
                <span>Manuellen Gewinner entfernen (automatische Ermittlung)</span>
            </label>
        `;
    }
    
    modalHtml += `
                    </div>
                    
                    <div class="reason-input">
                        <label for="manual_reason">Grund für manuelle Auswahl:</label>
                        <input type="text" id="manual_reason" placeholder="z.B. Nachrücker, Wildcard, etc." 
                               value="${pair.manual_winner_reason || ''}" maxlength="255">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" onclick="saveManualWinner()">Speichern</button>
                    <button class="btn btn-secondary" onclick="closeManualWinnerModal()">Abbrechen</button>
                </div>
            </div>
        </div>
    `;
    
    // Modal zum Body hinzufügen
    $('body').append(modalHtml);
    $('#manual-winner-modal').fadeIn();
}

/**
 * Schließt das Modal
 */
function closeManualWinnerModal() {
    $('#manual-winner-modal').fadeOut(function() {
        $(this).remove();
    });
    currentPairData = null;
}

/**
 * Speichert den manuellen Gewinner
 */
function saveManualWinner() {
    const selectedWinner = $('input[name="manual_winner"]:checked').val();
    const reason = $('#manual_reason').val().trim();
    
    if (selectedWinner === undefined) {
        alert('Bitte wähle einen Teilnehmer aus.');
        return;
    }
    
    // Ladeanzeige
    showLoading();
    
    $.ajax({
        url: 'set_manual_winner.php',
        type: 'POST',
        data: {
            pair_id: currentPairData.ID,
            winner_id: selectedWinner || null,
            reason: reason
        },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                showSuccess(response.message);
                closeManualWinnerModal();
                // Paarungen neu laden
                loadPairs(currentRound);
            } else {
                showError('Fehler: ' + response.error);
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            showError('Verbindungsfehler: ' + error);
        }
    });
}

/**
 * Löscht eine Paarung
 */
function deletePair(pairId) {
    if (!confirm('Möchtest du diese Paarung wirklich löschen?\n\nAchtung: Alle abhängigen Einträge (Runde 2, Finale) werden ebenfalls gelöscht!')) {
        return;
    }
    
    showLoading();
    
    $.ajax({
        url: 'delete_pair.php',
        type: 'POST',
        data: { pair_id: pairId },
        dataType: 'json',
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                showSuccess('Paarung erfolgreich gelöscht');
                // Element aus der UI entfernen
                $('.pair-card[data-pair-id="' + pairId + '"]').fadeOut(function() {
                    $(this).remove();
                });
            } else {
                showError('Fehler beim Löschen: ' + response.error);
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            showError('Verbindungsfehler: ' + error);
        }
    });
}

/**
 * Aktualisiert die Statistik-Anzeige
 */
function updateStatistics(stats) {
    if (!stats) return;
    
    $('#stat-total-pairs').text(stats.total_pairs || 0);
    $('#stat-two-pairs').text(stats.two_person_pairs || 0);
    $('#stat-three-pairs').text(stats.three_person_pairs || 0);
    $('#stat-manual-winners').text(stats.manual_winners || 0);
    $('#stat-completed').text(stats.completed_pairs || 0);
}

// ===== HILFSFUNKTIONEN =====

/**
 * Zeigt eine Ladeanzeige
 */
function showLoading() {
    if ($('#loading-overlay').length === 0) {
        $('body').append('<div id="loading-overlay"><div class="spinner"></div></div>');
    }
    $('#loading-overlay').fadeIn();
}

/**
 * Versteckt die Ladeanzeige
 */
function hideLoading() {
    $('#loading-overlay').fadeOut();
}

/**
 * Zeigt eine Erfolgsmeldung
 */
function showSuccess(message) {
    showNotification(message, 'success');
}

/**
 * Zeigt eine Fehlermeldung
 */
function showError(message) {
    showNotification(message, 'error');
}

/**
 * Zeigt eine Benachrichtigung
 */
function showNotification(message, type) {
    const notification = $('<div class="notification ' + type + '">' + message + '</div>');
    $('body').append(notification);
    
    notification.fadeIn(function() {
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    });
}

// ===== EVENT HANDLER =====

$(document).ready(function() {
    // Runden-Tabs
    $('.round-tab').on('click', function() {
        $('.round-tab').removeClass('active');
        $(this).addClass('active');
        
        currentRound = $(this).data('round');
        loadPairs(currentRound);
    });
    
    // Modal schließen bei Klick außerhalb
    $(document).on('click', '.modal', function(e) {
        if (e.target === this) {
            $(this).find('.close').click();
        }
    });
    
    // Initial laden
    if ($('#pairs-container').length > 0) {
        loadPairs(currentRound);
    }
});