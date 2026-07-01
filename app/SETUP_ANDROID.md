# Android-App – Schritt für Schritt (Windows)

Ziel: Aus dem bestehenden Portal eine native Android-App bauen, die zuverlässige
Push-Benachrichtigungen (FCM) empfängt. iOS kommt später am Mac dazu.

> ## ✅ Bereits erledigt (von Claude, im Terminal)
> - Projektgerüst + `npm install` (alle Pakete inkl. Firebase-Messaging-Plugin)
> - `npx cap add android` → natives Android-Projekt unter `app/android/`
> - `google-services.json` an den richtigen Ort verschoben (`app/android/app/`)
> - Firebase/Gradle ist verdrahtet (Capacitor 6 macht das automatisch) + `cap sync`
> - App zeigt auf `https://mitglieder.msvwilen.ch`
>
> ## 🔲 Dein Rest (nur noch 3 Dinge)
> 1. **Android Studio installieren** (Schritt 0) – bringt JDK + Android SDK mit.
> 2. **App starten** (Schritt 5): `npx cap open android` → ▶ Run.
> 3. **Server scharf schalten** (Schritt 2): Service-Account-JSON + 2 DB-Settings –
>    nötig, damit der Server Pushes *senden* kann (kann auch nach dem ersten App-Start passieren).

Legende für den Ort jedes Schritts:
🌐 = im Browser · 💻 = Terminal (PowerShell) im Ordner `app/` · 🤖 = Android Studio · 🗄️ = Server/Datenbank

---

## 0) Einmalige Installation (Windows)

1. **Node.js LTS** – https://nodejs.org → installieren. Prüfen:
   ```powershell
   node -v
   npm -v
   ```
2. **Android Studio** – https://developer.android.com/studio → installieren.
   Beim ersten Start den **Setup-Wizard** durchlaufen (lädt Android SDK + Emulator).
   Das mitgelieferte JDK reicht – kein separates Java nötig.

---

## 1) 🌐 Firebase-Projekt + Android-App registrieren

1. https://console.firebase.google.com → **Projekt erstellen** → Name z.B. `MSV Wilen`.
   (Google Analytics kannst du deaktivieren – nicht nötig.)
2. Im Projekt auf das **Android-Symbol** („App hinzufügen" → Android).
3. Felder ausfüllen:
   - **Android-Paketname:** `ch.msvwilen.portal`
     ⚠️ **Muss exakt** mit `appId` in `capacitor.config.json` übereinstimmen.
   - **App-Spitzname:** `MSV Wilen` (frei)
   - **SHA-1:** für FCM **nicht** nötig → leer lassen.
4. **`google-services.json` herunterladen.** Datei merken – kommt in Schritt 3.
5. Restliche „SDK hinzufügen"-Schritte im Assistenten **überspringen** (macht Capacitor).
6. Cloud Messaging (FCM HTTP v1) ist im Projekt automatisch aktiv.

---

## 2) 🌐🗄️ Service-Account für den Server-Versand

Damit **dein Server** Pushes verschicken kann, braucht er einen Service-Account-Schlüssel.

1. 🌐 Firebase Console → ⚙️ **Projekteinstellungen** → Tab **Dienstkonten**
   → **Neuen privaten Schlüssel generieren** → JSON herunterladen.
2. 🗄️ Diese JSON auf den Webserver legen – **außerhalb** des Web-Roots
   (dort, wo auch `msvjm_config.php` liegt). Beispiel: `/var/www/secrets/msv-fcm.json`.
   ⚠️ Diese Datei ist **geheim** – niemals ins Git/öffentliche Verzeichnis.
3. 🗄️ Zwei Settings in der Datenbank eintragen (z.B. via phpMyAdmin):
   ```sql
   UPDATE settings SET setting_value = 'DEINE_FIREBASE_PROJECT_ID'
     WHERE setting_key = 'fcm_project_id';
   UPDATE settings SET setting_value = '/var/www/secrets/msv-fcm.json'
     WHERE setting_key = 'fcm_service_account_path';
   ```
   - **Project-ID** findest du in den Projekteinstellungen (Feld „Projekt-ID").
   - **Pfad** = der absolute Pfad aus Schritt 2.
4. Fertig: Sobald beide Werte gesetzt sind, sendet `inc/push_helper.php` automatisch
   auch nativ. Solange sie leer sind, passiert nativ nichts (Web-Push läuft normal weiter).

> Test (optional, ohne App): Erst sinnvoll, wenn ein Gerät registriert ist (Schritt 5).

---

## 3) ✅ Android-Projekt erzeugen — ERLEDIGT

Bereits ausgeführt (im Ordner `app/`):
- `npm install` (alle Pakete inkl. `@capacitor-firebase/messaging`)
- `npx cap add android` → natives Projekt unter `app/android/`
- `google-services.json` liegt in `app/android/app/`

Nichts mehr zu tun.

---

## 4) ✅ Firebase im Android-Projekt verdrahtet — ERLEDIGT (automatisch)

**Keine Gradle-Edits nötig.** Das Capacitor-6-Template enthält den `classpath` für das
google-services-Plugin bereits und wendet es automatisch an, sobald `google-services.json`
in `app/android/app/` liegt (ist der Fall). `npx cap sync` ist gelaufen.

> ⚠️ Falls dir die Firebase-Console Gradle-Snippets zeigt: **ignorieren.** Weder der
> moderne `plugins { … }`-Block noch der `firebase-bom`-Dependencies-Block werden hier
> gebraucht (das Plugin `@capacitor-firebase/messaging` bringt die Bibliothek mit). Die
> Notification-Berechtigung (Android 13+) fragt die App beim Start automatisch ab
> (`portal/js/native_bridge.js`).

---

## 5) 🤖 App starten & Push testen

```powershell
npx cap open android
```

Android Studio öffnet sich.

1. **Emulator/Gerät wählen:** Für Push brauchst du **Google Play Services**.
   - Emulator: einen mit **„Google APIs"/„Play Store"**-Image anlegen
     (Device Manager → Create Device → System Image mit Play Store), **oder**
   - echtes Android-Handy per USB (Entwickleroptionen + USB-Debugging an).
2. Oben auf den **▶ Run**-Button. Die App startet und lädt `mitglieder.msvwilen.ch`.
3. **Einloggen.** Beim ersten Start fragt die App nach Benachrichtigungs-Erlaubnis → **Erlauben**.
4. 🗄️ **Kontrolle:** In der DB sollte jetzt eine Zeile in `push_geraete_native` stehen
   (mit deiner `benutzer_id` und `platform = 'android'`).
5. **Test-Push:** Im Portal unter **Benachrichtigungen → Test senden** (oder Server
   `api/push.php?action=test`). Die Notification erscheint – auch wenn die App im
   Hintergrund/geschlossen ist. **Antippen** öffnet die richtige Seite.

---

## 6) 🌐 (Später) In den Google Play Store

1. **Google-Play-Entwicklerkonto** (einmalig 25 USD): https://play.google.com/console
2. In Android Studio einen **signierten Release-Build** erstellen:
   `Build → Generate Signed Bundle / APK → Android App Bundle (.aab)`,
   dabei einen **Keystore** anlegen und **sicher aufbewahren** (geht nicht ersetzbar verloren).
3. In der Play Console: App anlegen, **Data-Safety-Formular** ausfüllen, Screenshots +
   Beschreibung hochladen, `.aab` als internen Test/Produktion veröffentlichen.

---

## 7) (Optional, später) Biometrie-Login

Nach dem MVP: Plugin `@aparajita/capacitor-biometric-auth` installieren und beim
App-Start eine Face-ID/Fingerprint-Sperre über die WebView legen. Die Session bleibt
über den bestehenden Remember-Token erhalten – Details siehe Konzept-Datei.

---

## Troubleshooting

| Symptom | Ursache / Lösung |
|---|---|
| App zeigt nur den Lade-Spinner | `server.url` nicht erreichbar – Internet prüfen; Domain in `capacitor.config.json` korrekt? |
| Kein FCM-Token / `push_geraete_native` bleibt leer | Emulator **ohne** Google Play Services → Image mit „Play Store" nutzen, oder echtes Gerät. `google-services.json` am richtigen Ort? Gradle-Schritte (4) gemacht? |
| Build-Fehler „google-services plugin" | Schritt 4a/4b vergessen oder Tippfehler; danach `npx cap sync` + in Android Studio „Sync Project with Gradle Files". |
| Push kommt an, aber Tap öffnet nichts | `data.url` im Payload – wird vom Server gesetzt; `native_bridge.js` Listener `notificationActionPerformed` prüfen. |
| Test-Push meldet „kein Gerät" | `fcm_project_id` / `fcm_service_account_path` in `settings` gesetzt? JSON-Pfad lesbar für PHP? |
| Paketname-Konflikt | Firebase-Paketname **muss** `ch.msvwilen.portal` sein (= `appId`). |

---

## Was bei einem App-Update nötig ist

- **Inhaltliche Änderungen am Portal** (PHP/JS/CSS): wirken **sofort** – die App lädt ja
  die Live-Site, **kein** neuer Store-Build nötig.
- **Nur** Änderungen an der nativen Hülle/Plugins (z.B. neues Plugin, neue Berechtigung)
  brauchen einen neuen Build + Store-Update.
- Bei geänderten **gecachten Assets** (Offline): `RUNTIME_CACHE`-Version in `sw.js` hochzählen.
