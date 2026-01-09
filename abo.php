<?php
// Kalender-Abo-Seite für einfaches Einrichten
$pageTitle = "Kalender abonnieren";
$calendarUrl = "https://jahresmeisterschaft.msvwilen.ch/termine";
$webcalUrl = "webcal://jahresmeisterschaft.msvwilen.ch/termine";
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - MSV Wilen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --primary-light: #34495e;
            --accent: #3498db;
            --text: #333;
            --text-muted: #6c757d;
            --bg: #f5f7fa;
            --white: #fff;
            --border: #e0e4e8;
        }
        
        body {
            background: var(--bg);
            min-height: 100vh;
            padding: 2rem 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--text);
        }
        
        .calendar-card {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            max-width: 560px;
            margin: 0 auto;
            overflow: hidden;
        }
        
        .card-header-custom {
            background: var(--primary);
            color: var(--white);
            padding: 1.5rem 2rem;
        }
        
        .card-header-custom h1 {
            font-size: 1.4rem;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        
        .card-header-custom p {
            margin: 0;
            opacity: 0.85;
            font-size: 0.95rem;
        }
        
        .card-body-custom {
            padding: 2rem;
        }
        
        .subscribe-button {
            background: var(--accent);
            border: none;
            color: var(--white);
            padding: 0.875rem 1.5rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            width: 100%;
            transition: background 0.2s;
            margin-bottom: 1.5rem;
        }
        
        .subscribe-button:hover {
            background: #2980b9;
            color: var(--white);
        }
        
        .subscribe-button i {
            margin-right: 0.5rem;
        }
        
        .device-icons {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            color: var(--text-muted);
        }
        
        .qr-section {
            text-align: center;
            padding: 1.5rem 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            margin: 1.5rem 0;
        }
        
        .qr-section h3 {
            color: var(--primary);
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        
        .qr-code {
            display: inline-block;
        }
        
        .qr-code img {
            border: 1px solid var(--border);
            border-radius: 4px;
        }
        
        .info-section {
            background: var(--bg);
            border-radius: 6px;
            padding: 1.25rem;
            margin-top: 1.5rem;
        }
        
        .info-section h4 {
            color: var(--primary);
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        
        .info-section ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .info-section li {
            padding: 0.35rem 0;
            padding-left: 1.5rem;
            position: relative;
            font-size: 0.9rem;
        }
        
        .info-section li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: var(--accent);
            font-weight: 600;
        }
        
        .manual-link {
            text-align: center;
            margin-top: 1.5rem;
            padding: 1rem;
            background: #fef9e7;
            border-radius: 6px;
            border: 1px solid #f0e6c8;
            font-size: 0.9rem;
        }
        
        .manual-link strong {
            color: var(--primary);
        }
        
        .manual-link code {
            background: var(--white);
            padding: 0.4rem 0.75rem;
            border-radius: 4px;
            display: inline-block;
            margin-top: 0.5rem;
            font-size: 0.8rem;
            word-break: break-all;
            border: 1px solid var(--border);
        }
        
        .accordion-item {
            border: 1px solid var(--border);
            margin-bottom: -1px;
        }
        
        .accordion-item:first-child {
            border-radius: 6px 6px 0 0;
        }
        
        .accordion-item:last-child {
            border-radius: 0 0 6px 6px;
        }
        
        .accordion-button {
            font-size: 0.9rem;
            font-weight: 500;
            padding: 0.875rem 1rem;
        }
        
        .accordion-button:not(.collapsed) {
            background: var(--bg);
            color: var(--primary);
        }
        
        .accordion-button:focus {
            box-shadow: none;
            border-color: var(--border);
        }
        
        .accordion-body {
            font-size: 0.875rem;
            padding: 1rem;
        }
        
        .accordion-body ol {
            padding-left: 1.25rem;
            margin-bottom: 0;
        }
        
        .accordion-body li {
            margin-bottom: 0.35rem;
        }
        
        .btn-back {
            color: var(--text-muted);
            font-size: 0.9rem;
            text-decoration: none;
        }
        
        .btn-back:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="calendar-card">
            <div class="card-header-custom">
                <h1><i class="fas fa-calendar-alt me-2"></i>MSV Jahreskalender</h1>
                <p>Alle Termine automatisch in deinem Kalender</p>
            </div>
            
            <div class="card-body-custom">
                
                <!-- Hauptbutton für Sofort-Abo -->
                <a href="<?php echo $webcalUrl; ?>" class="btn subscribe-button">
                    <i class="fas fa-calendar-plus"></i> Kalender abonnieren
                </a>
                
                <div class="device-icons">
                    <i class="fab fa-apple" title="iOS"></i>
                    <i class="fab fa-android" title="Android"></i>
                    <i class="fas fa-desktop" title="Desktop"></i>
                </div>

                <!-- QR-Code Sektion -->
                <div class="qr-section">
                    <h3><i class="fas fa-qrcode me-1"></i> QR-Code scannen</h3>
                    <p class="text-muted small mb-3">Mit der Kamera-App scannen</p>
                    <div class="qr-code">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=<?php echo urlencode($webcalUrl); ?>" 
                             alt="QR Code für Kalender-Abo" 
                             width="160" 
                             height="160">
                    </div>
                </div>

                <!-- Info-Sektion -->
                <div class="info-section">
                    <h4><i class="fas fa-info-circle me-1"></i> Das ist enthalten:</h4>
                    <ul>
                        <li>Alle Schiesstage und Wettkämpfe</li>
                        <li>Wichtige Vereinstermine</li>
                        <li>Automatische Updates bei Änderungen</li>
                        <li>Erinnerungen vor den Terminen</li>
                    </ul>
                </div>

                <!-- Manuelle Eingabe als Fallback -->
                <div class="manual-link">
                    <strong><i class="fas fa-link me-1"></i> Manuell hinzufügen</strong>
                    <p class="mb-1 mt-2">Diese URL als Kalender-Abo einfügen:</p>
                    <code><?php echo $calendarUrl; ?></code>
                </div>

                <!-- Anleitung für verschiedene Geräte -->
                <div class="accordion mt-4" id="anleitungAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ios">
                                <i class="fab fa-apple me-2"></i> iPhone / iPad
                            </button>
                        </h2>
                        <div id="ios" class="accordion-collapse collapse" data-bs-parent="#anleitungAccordion">
                            <div class="accordion-body">
                                <ol>
                                    <li>Auf "Kalender abonnieren" tippen</li>
                                    <li>"Abonnieren" bestätigen</li>
                                    <li>Fertig!</li>
                                </ol>
                                <p class="text-muted small mb-0 mt-2"><strong>Alternativ:</strong> Einstellungen → Kalender → Accounts → Account hinzufügen → Kalender-Abo</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#android">
                                <i class="fab fa-android me-2"></i> Android
                            </button>
                        </h2>
                        <div id="android" class="accordion-collapse collapse" data-bs-parent="#anleitungAccordion">
                            <div class="accordion-body">
                                <ol>
                                    <li>Google Kalender öffnen</li>
                                    <li>Einstellungen → Kalender hinzufügen → Über URL</li>
                                    <li>URL einfügen und bestätigen</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#desktop">
                                <i class="fas fa-desktop me-2"></i> Computer
                            </button>
                        </h2>
                        <div id="desktop" class="accordion-collapse collapse" data-bs-parent="#anleitungAccordion">
                            <div class="accordion-body">
                                <p class="mb-2"><strong>Google Calendar:</strong></p>
                                <ol>
                                    <li><a href="https://calendar.google.com" target="_blank">calendar.google.com</a> öffnen</li>
                                    <li>"+" bei "Weitere Kalender" → "Über URL"</li>
                                    <li>URL einfügen</li>
                                </ol>
                                
                                <p class="mb-2 mt-3"><strong>Outlook:</strong></p>
                                <ol>
                                    <li>Kalender hinzufügen → Aus dem Internet</li>
                                    <li>URL einfügen</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="text-center mt-3">
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left me-1"></i> Zurück
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
