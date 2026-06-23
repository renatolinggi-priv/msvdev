# MSV UI Audit — Anleitung

## Voraussetzungen

- Node.js (bereits installiert: v22.18.0)
- Die MSV-Applikation muss lokal laufen (Apache/XAMPP)

---

## Schritt 1: Abhängigkeiten installieren

```bash
cd c:\TEMP\msvjm\msvdev\audit
npm install
```

Das installiert Playwright und lädt automatisch einen Chromium-Browser herunter (~150 MB).
Falls der Download blockiert wird, alternativ:
```bash
npx playwright install chromium
```

---

## Schritt 2: Konfiguration anpassen

Öffne `config.js` und passe an:

1. **`baseUrl`** — Die URL unter der die App läuft
   ```js
   baseUrl: 'http://localhost/msvjm/msvdev'
   ```

2. **`login.username`** und **`login.password`** — Dein Admin-Login
   ```js
   login: {
       username: 'admin',
       password: 'deinPasswort'
   }
   ```

---

## Schritt 3: Audit starten

### Alle Seiten (Desktop + Mobile)
```bash
npm run audit
```

### Nur Desktop
```bash
npm run audit:desktop
```

### Nur Mobile
```bash
npm run audit:mobile
```

### Einzelne Seite(n) testen
```bash
npm run audit:page -- jmrang
npm run audit:page -- jmrang,heimrang,sieger
```

### Nur eine Gruppe
```bash
node audit.js --group portal
node audit.js --group resultate
```

Verfügbare Gruppen: `auth`, `admin_dashboard`, `resultate`, `rangierungen`, `verwaltung`, `spezial`, `import`, `portal`

---

## Schritt 4: Ergebnisse anschauen

Nach dem Durchlauf findest du alles unter:
```
audit/results/YYYY-MM-DDTHH-MM-SS/
├── screenshots/
│   ├── desktop/        ← Screenshots jeder Seite (1920×1080)
│   │   ├── home.png
│   │   ├── jmrang.png
│   │   └── ...
│   └── mobile/         ← Screenshots jeder Seite (375×812)
│       ├── home.png
│       ├── jmrang.png
│       └── ...
├── report.json         ← Technische Daten (CSS, DOM, z-index, Console-Errors)
└── SUMMARY.md          ← Lesbare Zusammenfassung
```

---

## Schritt 5: Ergebnisse an Claude übergeben

### Option A: Screenshots direkt zeigen
Einfach Screenshots per Drag & Drop in den Chat ziehen. Claude kann Bilder analysieren.

### Option B: Report-Datei übergeben
Sag Claude:
> "Lies bitte `audit/results/.../SUMMARY.md` und `audit/results/.../report.json`"

### Option C: Einzelne Seite prüfen
> "Schau dir den Screenshot von jmrang an" (Screenshot reinkopieren)

---

## Tipps

- **Erster Durchlauf**: Starte mit `--group admin_dashboard` um zu testen ob Login funktioniert
- **Portal-Seiten**: Brauchen einen User mit Rolle `mitglied` oder `vorstand` — ggf. separaten Login in config.js
- **Langsame Seiten**: Falls Timeout-Fehler kommen, erhöhe `waitAfterLoad` in config.js
- **Login-Felder**: Das Script sucht nach `#username` und `#password` — falls deine IDs anders heissen, passe `audit.js` Zeile bei "Login" an

---

## Fehlerbehebung

| Problem | Lösung |
|---------|--------|
| "Login fehlgeschlagen" | Username/Passwort in config.js prüfen |
| "Seite nicht erreichbar" | Ist Apache/XAMPP gestartet? baseUrl korrekt? |
| "Chromium not found" | `npx playwright install chromium` ausführen |
| Timeout bei Seiten | `waitAfterLoad` in config.js erhöhen (z.B. 3000) |
| Portal-Seiten 403 | Admin-User hat evtl. keinen Portal-Zugang — separaten Login verwenden |
