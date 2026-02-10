# MSV Wilen API Dokumentation

## Übersicht
Zentrale API für externe Zugriffe auf MSV Wilen Daten (z.B. WordPress-Integration).

## Base URL
```
https://jahresmeisterschaft.msvwilen.ch/api/api.php
```

---

## Verfügbare Endpoints

### 1. Jahresprogramm
Liefert alle Schiessanlässe für ein bestimmtes Jahr.

**Endpoint:** `jahresprogramm`

**Parameter:**
- `year` (optional) - Jahr (Standard: aktuelles Jahr)

**Beispiel-Aufruf:**
```
https://jahresmeisterschaft.msvwilen.ch/api/api.php?endpoint=jahresprogramm&year=2024
```

**Response:**
```json
{
  "success": true,
  "message": "",
  "data": {
    "year": 2024,
    "programm": [
      {
        "Reihenfolge": 1,
        "Bezeichnung": "Neujahrschiessen",
        "Schiesstage": "1. Januar\n2. Januar",
        "Maxpunkte": 100,
        "Streicher": 0,
        "Erweitert": 1,
        "Info": 0,
        "tage": "1. / 2.",
        "monate": "Januar",
        "jm_status": "X"
      }
    ],
    "zusatztext": "Bitte beachten Sie...",
    "pdf_url": "https://jahresmeisterschaft.msvwilen.ch/inc/jmdefinition/export_jmdefinition_pdf.php?year=2024"
  },
  "timestamp": "2024-01-15 10:30:45"
}
```

**Feld-Beschreibungen:**
- `tage`: Extrahierte Tage (z.B. "1. / 2.")
- `monate`: Extrahierte Monate (z.B. "Januar / Februar")
- `jm_status`: Status für Jahresmeisterschaft ("X", "Bonus" oder leer)
- `pdf_url`: Direkt-Link zum PDF-Download

---

## Neue Endpoints hinzufügen

Um einen neuen Endpoint hinzuzufügen, füge einen neuen Case im Switch-Statement ein:

```php
case 'mein_neuer_endpoint':
    // Parameter holen
    $param = $_GET['param'] ?? 'default';
    
    try {
        // SQL-Abfrage
        $sql = "SELECT * FROM Tabelle WHERE ...";
        $result = $conn->query($sql);
        $data = $result->fetch_all(MYSQLI_ASSOC);
        
        // Response senden
        sendResponse(true, [
            'ergebnis' => $data
        ]);
        
    } catch (Exception $e) {
        handleError('Fehler', $e->getMessage());
    }
    break;
```

---

## Sicherheit

### CORS
Aktuell erlaubt für: `https://www.msvwilen.ch`

Um weitere Domains zu erlauben, passe die CORS-Header an:
```php
header('Access-Control-Allow-Origin: https://deine-domain.ch');
```

### Authentifizierung
Für geschützte Endpoints kannst du API-Keys verwenden:
```php
$api_key = $_GET['api_key'] ?? '';
if ($api_key !== 'DEIN_GEHEIMER_KEY') {
    handleError('Nicht autorisiert');
}
```

---

## Geplante Endpoints
- [ ] Rangliste Jahresmeisterschaft
- [ ] Einzelresultate
- [ ] Statistiken
- [ ] Mitgliederliste (mit Auth)

---

## Fehlerbehandlung

Alle Fehler werden im JSON-Format zurückgegeben:
```json
{
  "success": false,
  "message": "Fehler beim Laden: Connection timeout",
  "data": [],
  "timestamp": "2024-01-15 10:30:45"
}
```

---

## Testing

Teste die API direkt im Browser oder mit curl:
```bash
curl "https://jahresmeisterschaft.msvwilen.ch/api/api.php?endpoint=jahresprogramm&year=2024"
```
