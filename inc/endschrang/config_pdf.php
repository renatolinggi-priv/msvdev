<?

// Funktion zum Konvertieren eines Bildes in Base64
function imgToBase64($imgPath) {
    $imageData = base64_encode(file_get_contents($imgPath));
    $src = 'data:' . mime_content_type($imgPath) . ';base64,' . $imageData;
    return $src;
}
$header = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }
        .container {
            margin: 5px;
            padding: 1px;
        }
        h2 {
            text-align: left;
            margin-bottom: 20px;
        }
        .header {
            display: flex;
            align-items: center;
        }
        .header img {
            max-width: 100px;
            margin-right: 20px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td {
            vertical-align: middle;
            padding: 4px;
            border: 0px solid #000;
        }
        .table th {
            background-color: #343a40;
            color: #fff;
        }
        .bold{
            font-weight: bold;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 8px;
            padding: 10px 0;
            border-top: 1px solid #000;
        }
    </style>';


$footer = '</div>
    <div class="footer">
        <p>© ' . date("Y") . ' MSV Wilen. Alle Rechte vorbehalten.</p>
    </div>
    </body>
    </html>';
?>