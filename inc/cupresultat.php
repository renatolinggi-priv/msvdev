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
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>CUP Tournament Management</h2>

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

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script>
        $(document).ready(function () {
            loadMembers();

            function loadMembers() {
                $.ajax({
                    url: 'cup/load_members.php',
                    type: 'GET',
                    success: function (data) {
                        $('#memberList').html(data);
                        initDragAndDrop();
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

            $("#saveRound1").click(function () {
                saveMatches('round1', '#matchListRound1 .match');
            });

            $("#saveRound2").click(function () {
                saveMatches('round2', '#matchListRound2 .match');
            });

            $("#saveFinal").click(function () {
                saveMatches('final', '#matchListFinal .match');
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

            function loadWinnersRound1() {
                $.ajax({
                    url: 'cup/load_winners.php',
                    type: 'GET',
                    data: { round: 'round1' },
                    success: function (data) {
                        $('#winnersRound1').html(data);
                        $('#round2').show();
                        initDragAndDrop();
                    }
                });
            }

            function loadWinnersRound2() {
                $.ajax({
                    url: 'cup/load_winners.php',
                    type: 'GET',
                    data: { round: 'round2' },
                    success: function (data) {
                        $('#winnersRound2').html(data);
                        $('#final').show();
                        initDragAndDrop();
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