<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>API Test - Generate PDF</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <h1>API Test - Zielscheiben PDF</h1>
    <button onclick="testAPI()">Test API Call</button>
    <pre id="output"></pre>
    
    <script>
    function testAPI() {
        const testData = {
            csrf_token: '<?php session_start(); echo $_SESSION["csrf_token"] ?? "test"; ?>',
            alleStiche: [
                {
                    programmNummer: '522',
                    stichName: 'Endstich',
                    passe: 1,
                    schuesse: [
                        {schuss_nr: 1, wert: 10, hunderter: 93, x: -24.71, y: 34.44},
                        {schuss_nr: 2, wert: 9, hunderter: 90, x: 28.9, y: 46.77},
                        {schuss_nr: 3, wert: 10, hunderter: 93, x: -3.53, y: -42.4}
                    ]
                }
            ],
            schuetzenName: 'Test Schütze',
            jahr: 2025
        };
        
        $('#output').text('Sending request...');
        
        $.ajax({
            url: 'inc/endsch_targetprint/generate_pdf.php',
            method: 'POST',
            data: JSON.stringify(testData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                $('#output').text(JSON.stringify(response, null, 2));
                console.log('Success:', response);
                
                if (response.pdf_link) {
                    alert('PDF Link: ' + response.pdf_link);
                }
            },
            error: function(xhr, status, error) {
                $('#output').text('ERROR:\n' + xhr.responseText);
                console.error('Error:', error);
            }
        });
    }
    </script>
</body>
</html>
