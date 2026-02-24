<?
include 'dbconnect.inc.php';
include 'header.inc.php';
?>
    <style>
        .member-list,
        .match-list {
            list-style: none;
            padding: 0;
            margin: 0;
            min-height: 50px;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
        }

        .member-list li,
        .match-list li {
            margin: 5px 0;
            padding: 8px;
            background-color: #f8f9fa;
            border-radius: 4px;
            cursor: pointer;
        }

        .match {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
        }

        .match input[type="number"] {
            width: 50px;
            margin-left: 10px;
        }

        /* Mobile CUP Tournament Styles */
        @media (max-width: 767.98px) {
            .desktop-cup-container {
                display: none !important;
            }

            .mobile-cup-container {
                display: block !important;
            }

            .mobile-cup-round {
                background: white;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                margin-bottom: 1.5rem;
                overflow: hidden;
            }

            .mobile-cup-header {
                background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
                color: white;
                padding: 1rem;
                font-weight: 600;
                font-size: 1.1rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .mobile-member-select {
                padding: 1rem;
                border-bottom: 1px solid #e9ecef;
            }

            .mobile-member-btn {
                display: block;
                width: 100%;
                padding: 12px;
                margin-bottom: 8px;
                background: #f8f9fa;
                border: 2px solid #dee2e6;
                border-radius: 8px;
                font-size: 16px;
                text-align: left;
                cursor: pointer;
                transition: all 0.2s;
            }

            .mobile-member-btn:active {
                background: #e9ecef;
                transform: scale(0.98);
            }

            .mobile-member-btn.selected {
                background: #d4edda;
                border-color: #28a745;
                font-weight: 600;
            }

            .mobile-match-card {
                padding: 1rem;
                border-bottom: 1px solid #e9ecef;
            }

            .mobile-match-card:last-child {
                border-bottom: none;
            }

            .mobile-match-players {
                display: flex;
                flex-direction: column;
                gap: 12px;
                margin-bottom: 1rem;
            }

            .mobile-match-player {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px;
                background: #f8f9fa;
                border-radius: 8px;
                border: 2px solid #dee2e6;
            }

            .mobile-match-player.winner {
                background: #d4edda;
                border-color: #28a745;
            }

            .mobile-match-player-name {
                font-weight: 600;
                font-size: 16px;
            }

            .mobile-match-player-result {
                width: 70px;
                padding: 8px;
                border: 2px solid #dee2e6;
                border-radius: 8px;
                font-size: 18px;
                font-weight: 600;
                text-align: center;
            }

            .mobile-save-btn {
                width: 100%;
                padding: 14px;
                font-size: 16px;
                font-weight: 600;
                border-radius: 8px;
                margin-top: 1rem;
            }
        }

        @media (min-width: 768px) {
            .mobile-cup-container {
                display: none !important;
            }

            .desktop-cup-container {
                display: block !important;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>CUP Tournament Management</h2>

        <!-- Desktop Version -->
        <div class="desktop-cup-container">
            <div id="round1">
                <h4>Runde 1</h4>
                <div class="row">
                    <div class="col-md-6">
                        <h5>Mitgliederliste</h5>
                        <ul id="memberList" class="member-list">
                            <!-- Member items will be dynamically loaded here -->
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5>Paarungen</h5>
                        <div id="matchListRound1" class="match-list">
                            <!-- Pairs will be created here via drag and drop -->
                        </div>
                        <button id="saveRound1" class="btn btn-primary mt-3">Ergebnisse Runde 1 Speichern</button>
                    </div>
                </div>
            </div>

            <div id="round2" style="display:none;">
                <h4>Runde 2</h4>
                <div class="row">
                    <div class="col-md-6">
                        <h5>Gewinner Runde 1</h5>
                        <ul id="winnersRound1" class="member-list">
                            <!-- Winners from Round 1 will be dynamically loaded here -->
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5>Paarungen Runde 2</h5>
                        <div id="matchListRound2" class="match-list">
                            <!-- Pairs for Round 2 will be created here -->
                        </div>
                        <button id="saveRound2" class="btn btn-primary mt-3">Ergebnisse Runde 2 Speichern</button>
                    </div>
                </div>
            </div>

            <div id="final" style="display:none;">
                <h4>Final</h4>
                <div class="row">
                    <div class="col-md-6">
                        <h5>Gewinner Runde 2</h5>
                        <ul id="winnersRound2" class="member-list">
                            <!-- Winners from Round 2 will be dynamically loaded here -->
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5>Final Paarung</h5>
                        <div id="matchListFinal" class="match-list">
                            <!-- Final match pair will be created here -->
                        </div>
                        <button id="saveFinal" class="btn btn-primary mt-3">Final Ergebnisse Speichern</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Version -->
        <div class="mobile-cup-container" style="display:none;">
            <div id="mobileRound1">
                <div class="mobile-cup-round">
                    <div class="mobile-cup-header">
                        <span><i class="bi bi-trophy me-2"></i>Runde 1</span>
                    </div>
                    <div class="mobile-member-select" id="mobileMemberSelect1">
                        <p class="text-muted small mb-2">Wähle Spieler für Paarungen:</p>
                        <!-- Members will be loaded here -->
                    </div>
                    <div id="mobileMatchList1">
                        <!-- Mobile match cards -->
                    </div>
                    <div class="p-3">
                        <button id="mobileSaveRound1" class="btn btn-danger mobile-save-btn">
                            <i class="bi bi-save me-2"></i>Ergebnisse Runde 1 Speichern
                        </button>
                    </div>
                </div>
            </div>

            <div id="mobileRound2" style="display:none;">
                <div class="mobile-cup-round">
                    <div class="mobile-cup-header">
                        <span><i class="bi bi-trophy me-2"></i>Runde 2</span>
                    </div>
                    <div class="mobile-member-select" id="mobileMemberSelect2">
                        <p class="text-muted small mb-2">Gewinner Runde 1:</p>
                        <!-- Winners will be loaded here -->
                    </div>
                    <div id="mobileMatchList2">
                        <!-- Mobile match cards -->
                    </div>
                    <div class="p-3">
                        <button id="mobileSaveRound2" class="btn btn-danger mobile-save-btn">
                            <i class="bi bi-save me-2"></i>Ergebnisse Runde 2 Speichern
                        </button>
                    </div>
                </div>
            </div>

            <div id="mobileFinal" style="display:none;">
                <div class="mobile-cup-round">
                    <div class="mobile-cup-header">
                        <span><i class="bi bi-trophy-fill me-2"></i>Final</span>
                    </div>
                    <div class="mobile-member-select" id="mobileMemberSelectFinal">
                        <p class="text-muted small mb-2">Gewinner Runde 2:</p>
                        <!-- Winners will be loaded here -->
                    </div>
                    <div id="mobileMatchListFinal">
                        <!-- Mobile match cards -->
                    </div>
                    <div class="p-3">
                        <button id="mobileSaveFinal" class="btn btn-danger mobile-save-btn">
                            <i class="bi bi-trophy-fill me-2"></i>Final Ergebnisse Speichern
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script>
        $(document).ready(function () {
            var mobileMembers = [];
            var mobileSelectedPlayers = [];
            var isMobile = window.matchMedia('(max-width: 767.98px)').matches;

            loadMembers();

            function loadMembers() {
                $.ajax({
                    url: 'cup/load_members.php',
                    type: 'GET',
                    success: function (data) {
                        $('#memberList').html(data);

                        if (isMobile) {
                            // Extract member data for mobile
                            $('#memberList li').each(function() {
                                mobileMembers.push({
                                    id: $(this).data('id'),
                                    name: $(this).text().trim()
                                });
                            });
                            buildMobileMemberSelect('mobileMemberSelect1', mobileMembers);
                        } else {
                            initDragAndDrop();
                        }
                    }
                });
            }

            // Mobile member selection
            function buildMobileMemberSelect(containerId, members) {
                var container = $('#' + containerId);
                var html = '<p class="text-muted small mb-2">Wähle 2 Spieler für eine Paarung:</p>';

                members.forEach(function(member) {
                    html += '<button class="mobile-member-btn" data-id="' + member.id + '">';
                    html += '<i class="bi bi-person me-2"></i>' + member.name;
                    html += '</button>';
                });

                container.html(html);

                // Handle member selection
                container.find('.mobile-member-btn').on('click', function() {
                    var memberId = $(this).data('id');
                    var memberName = $(this).text().trim();

                    if ($(this).hasClass('selected')) {
                        // Deselect
                        $(this).removeClass('selected');
                        mobileSelectedPlayers = mobileSelectedPlayers.filter(function(p) {
                            return p.id != memberId;
                        });
                    } else {
                        // Select
                        if (mobileSelectedPlayers.length < 2) {
                            $(this).addClass('selected');
                            mobileSelectedPlayers.push({
                                id: memberId,
                                name: memberName
                            });
                        }
                    }

                    // If 2 players selected, create match
                    if (mobileSelectedPlayers.length === 2) {
                        createMobileMatch(containerId.replace('mobileMemberSelect', 'mobileMatchList'), mobileSelectedPlayers);
                        container.find('.mobile-member-btn.selected').remove();
                        mobileSelectedPlayers = [];
                    }
                });
            }

            function createMobileMatch(listId, players) {
                var html = '<div class="mobile-match-card">';
                html += '<div class="mobile-match-players">';

                players.forEach(function(player, index) {
                    html += '<div class="mobile-match-player" data-id="' + player.id + '">';
                    html += '<span class="mobile-match-player-name">' + player.name + '</span>';
                    html += '<input type="number" class="mobile-match-player-result" ';
                    html += 'placeholder="Resultat" min="0" inputmode="numeric">';
                    html += '</div>';
                });

                html += '</div>';
                html += '</div>';

                $('#' + listId).append(html);

                // Auto-highlight winner
                $('#' + listId + ' .mobile-match-player-result').on('input', function() {
                    var card = $(this).closest('.mobile-match-card');
                    var results = card.find('.mobile-match-player-result');
                    var val1 = parseInt($(results[0]).val()) || 0;
                    var val2 = parseInt($(results[1]).val()) || 0;

                    card.find('.mobile-match-player').removeClass('winner');

                    if (val1 > val2 && val1 > 0) {
                        $(results[0]).closest('.mobile-match-player').addClass('winner');
                    } else if (val2 > val1 && val2 > 0) {
                        $(results[1]).closest('.mobile-match-player').addClass('winner');
                    }
                });
            }

            function initDragAndDrop() {
                $(".member-list li").draggable({
                    helper: "clone",
                    revert: "invalid"
                });

                $(".match-list").droppable({
                    accept: ".member-list li",
                    drop: function (event, ui) {
                        const playerId = ui.draggable.data('id');
                        const playerName = ui.draggable.text();
                        $(this).append(createMatchElement(playerId, playerName));
                    }
                });
            }

            function createMatchElement(playerId, playerName) {
                return `<div class="match" data-id="${playerId}">
                    <span>${playerName}</span>
                    <input type="number" class="result-input" placeholder="Resultat" min="0">
                </div>`;
            }

            // Desktop save buttons
            $("#saveRound1").click(function () {
                saveMatches('round1', '#matchListRound1 .match');
            });

            $("#saveRound2").click(function () {
                saveMatches('round2', '#matchListRound2 .match');
            });

            $("#saveFinal").click(function () {
                saveMatches('final', '#matchListFinal .match');
            });

            // Mobile save buttons
            $("#mobileSaveRound1").click(function () {
                saveMobileMatches('round1', '#mobileMatchList1 .mobile-match-card');
            });

            $("#mobileSaveRound2").click(function () {
                saveMobileMatches('round2', '#mobileMatchList2 .mobile-match-card');
            });

            $("#mobileSaveFinal").click(function () {
                saveMobileMatches('final', '#mobileMatchListFinal .mobile-match-card');
            });

            function saveMatches(round, selector) {
                const matches = [];
                let match = {};
                $(selector).each(function (index) {
                    if (index % 2 === 0) {
                        match.player1Id = $(this).data('id');
                        match.player1Result = $(this).find('.result-input').val();
                    } else {
                        match.player2Id = $(this).data('id');
                        match.player2Result = $(this).find('.result-input').val();

                        const winnerId = match.player1Result > match.player2Result ? match.player1Id : match.player2Id;
                        match.winnerId = winnerId;

                        matches.push({
                            player1Id: match.player1Id,
                            player2Id: match.player2Id,
                            player1Result: match.player1Result,
                            player2Result: match.player2Result,
                            winnerId: match.winnerId
                        });

                        match = {};  // Reset match object for the next pairing
                    }
                });

                $.ajax({
                    url: 'cup/save_matches.php',
                    type: 'POST',
                    data: { round: round, matches: matches },
                    success: function (response) {
                        msvError(response);
                        if (round === 'round1') {
                            loadWinnersRound1();
                        } else if (round === 'round2') {
                            loadWinnersRound2();
                        }
                    }
                });
            }

            function saveMobileMatches(round, selector) {
                const matches = [];

                $(selector).each(function() {
                    const players = $(this).find('.mobile-match-player');
                    const results = $(this).find('.mobile-match-player-result');

                    if (players.length === 2 && results.length === 2) {
                        const player1Id = $(players[0]).data('id');
                        const player2Id = $(players[1]).data('id');
                        const player1Result = $(results[0]).val();
                        const player2Result = $(results[1]).val();

                        const winnerId = player1Result > player2Result ? player1Id : player2Id;

                        matches.push({
                            player1Id: player1Id,
                            player2Id: player2Id,
                            player1Result: player1Result,
                            player2Result: player2Result,
                            winnerId: winnerId
                        });
                    }
                });

                $.ajax({
                    url: 'cup/save_matches.php',
                    type: 'POST',
                    data: { round: round, matches: matches },
                    success: function (response) {
                        msvToast(response, 'success');
                        if (round === 'round1') {
                            loadWinnersRound1();
                        } else if (round === 'round2') {
                            loadWinnersRound2();
                        }
                    }
                });
            }

            function loadWinnersRound1() {
                $.ajax({
                    url: 'cup/load_winners.php',
                    type: 'GET',
                    data: { round: 'round1' },
                    success: function (data) {
                        if (isMobile) {
                            // Extract winners for mobile
                            var winners = [];
                            var tempDiv = $('<div>').html(data);
                            tempDiv.find('li').each(function() {
                                winners.push({
                                    id: $(this).data('id'),
                                    name: $(this).text().trim()
                                });
                            });
                            buildMobileMemberSelect('mobileMemberSelect2', winners);
                            $('#mobileRound2').show();
                        } else {
                            $('#winnersRound1').html(data);
                            $('#round2').show();
                            initDragAndDrop();
                        }
                    }
                });
            }

            function loadWinnersRound2() {
                $.ajax({
                    url: 'cup/load_winners.php',
                    type: 'GET',
                    data: { round: 'round2' },
                    success: function (data) {
                        if (isMobile) {
                            // Extract winners for mobile
                            var winners = [];
                            var tempDiv = $('<div>').html(data);
                            tempDiv.find('li').each(function() {
                                winners.push({
                                    id: $(this).data('id'),
                                    name: $(this).text().trim()
                                });
                            });
                            buildMobileMemberSelect('mobileMemberSelectFinal', winners);
                            $('#mobileFinal').show();
                        } else {
                            $('#winnersRound2').html(data);
                            $('#final').show();
                            initDragAndDrop();
                        }
                    }
                });
            }

            function loadExistingMatches(round, selector) {
                $.ajax({
                    url: 'cup/load_existing_matches.php',
                    type: 'GET',
                    data: { round: round, year: new Date().getFullYear() },
                    success: function (data) {
                        $(selector).html(data);
                    }
                });
            }

            loadExistingMatches('round1', '#matchListRound1');
            loadExistingMatches('round2', '#matchListRound2');
            loadExistingMatches('final', '#matchListFinal');
        });
    </script>
</body>

</html>