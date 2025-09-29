<!DOCTYPE html>
<html>
<head>
    <title>Kanti Resultate</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
</head>
<body class="container-fluid">
    <h1>Kanti Resultate</h1>
    <button id="exportPDF" class="btn btn-primary mb-3">Export PDF</button>
    <div id="resultateTable">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>MitgliedID</th>
                    <th>KSchuss1</th>
                    <th>KSchuss2</th>
                    <th>KSchuss3</th>
                    <th>KSchuss4</th>
                    <th>KSchuss5</th>
                    <th>KSchuss6</th>
                </tr>
            </thead>
            <tbody>
                <?php
                include '../config.php';

                // Datenbankverbindung herstellen
                $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

                if ($conn->connect_error) {
                    die("Connection failed: " . $conn->connect_error);
                }

                // Daten aus der Tabelle kantiresultate abrufen
                $sql = "SELECT * FROM kantiresultate";
                $result = $conn->query($sql);

                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $row['MitgliedID'] . "</td>";
                        echo "<td>" . $row['Passe1'] . "</td>";
                        echo "<td>" . $row['Passe2'] . "</td>";
                        echo "<td>" . $row['Passe3'] . "</td>";
                        echo "<td>" . $row['Passe4'] . "</td>";
                        echo "<td>" . $row['Passe5'] . "</td>";
                        echo "<td>" . $row['Passe6'] . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='7'>Keine Einträge gefunden</td></tr>";
                }

                $conn->close();
                ?>
            </tbody>
        </table>
    </div>
    
    <script>
        $(document).ready(function() {
            // PDF-Export-Funktion
            $('#exportPDF').click(function() {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                doc.text("Kanti Resultate", 10, 10);

                const elem = document.getElementById("resultateTable");
                const res = doc.autoTableHtmlToJson(elem);
                doc.autoTable(res.columns, res.data, { startY: 20 });

                doc.save("kanti_resultate.pdf");
            });
        });
    </script>
</body>
</html>
