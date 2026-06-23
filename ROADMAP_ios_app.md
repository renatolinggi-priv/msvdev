# Theorie: Tagebuch.inato.io als native iOS-App

## Context
Gedankenspiel — was würde sich ändern, wenn die PHP/PWA-Tagebuch-App zusätzlich als native iOS-App existierte? Schwerpunkt: Architektur-Konsequenzen und Push-Benachrichtigungen.

**User-Vorgaben (geklärt):**
- PWA bleibt parallel zur App bestehen (Desktop weiterhin Web, Familien-Mitglieder auf Android nutzen weiterhin PWA)
- **Vorerst nur iOS** — Android wird nicht mitgedacht
- Apple Developer Account ist nicht vorhanden ($99/Jahr, muss angeschafft werden)
- **Status: reines Roadmap-Dokument, keine Umsetzung jetzt.** iOS-Entwicklung erfordert Xcode (Mac-only), aktuelle Arbeitsumgebung ist Windows. Backend-Vorarbeiten (siehe Abschnitt 5) wären auf Windows machbar, werden aber bewusst zurückgestellt.

---

## 1. Technologiewahl

| Ansatz | Aufwand | E2E-Notif-Decrypt | UI-Qualität | Empfehlung |
|---|---|---|---|---|
| **WKWebView-Wrapper** (PWA in Container) | 1-2 Wo | ❌ Web Push bleibt unzuverlässig | mäßig | nur als Brückenlösung |
| **Capacitor** | 2-3 Wo | ⚠️ Plugin-Ebene, fragil | mittelmäßig | nicht empfohlen |
| **Flutter** (für späteren Android-Pfad) | 8-10 Wo | ✅ via Plattform-Channels | gut | nur wenn Android sicher kommt |
| **Native SwiftUI + CryptoKit** | 10-12 Wo | ✅ sauber, Notification Service Extension mit Keychain-Group | maximal | **empfohlen** |

### Empfehlung: Native SwiftUI
Begründung:
- iOS-only erlaubt maximale Plattform-Tiefe ohne Cross-Platform-Reibung
- Notification Service Extension für E2E-Decrypt ist auf SwiftUI-Pfad am saubersten
- Keychain + Secure Enclave + Passkeys via `AuthenticationServices` direkt nutzbar
- Live Activities, Widgets, Shortcuts, Siri später trivial nachrüstbar
- Kein Plugin-Risiko bei Apple-API-Updates (kommt jeden Herbst)

Sollte später Android dazukommen, ist die Migration auf Flutter oder eine zweite native App nicht günstig — aber jetzt nicht zu lösen. Wichtig: Backend-Erweiterungen so designen, dass FCM später ohne Schema-Wechsel ergänzt werden kann.

---

## 2. Was bleibt, was ändert sich

### Bleibt (Source of Truth)
- PHP-Backend, MySQL, Cron-Logik
- E2E-Schlüssel-Wrapping-Konzept (DEK + Keypair)
- API-Schema `/api/<resource>.php?action=...`
- Bild-Storage außerhalb Webroot
- Recovery-Konzept (BIP39 / Datei / Passkey)
- PWA für Desktop und Android-User in der Familie

### Backend-Erweiterungen
- **Auth**: Bearer-Token-Pfad zusätzlich zu Cookie-Sessions. Token in iOS-Keychain (`kSecAttrAccessibleAfterFirstUnlock`)
- **APNS-Sender**: `edamov/pushok` (HTTP/2 + JWT-Auth bei Apple, Token-basiert statt Zertifikat)
- **Tabelle `push_abos`** bekommt:
  - `art ENUM('web','apns','fcm')` — `fcm` schon jetzt vorsehen, auch wenn ungenutzt
  - `device_token` (NULL für Web, gefüllt für APNS)
  - `bundle_id`
  - `env ENUM('sandbox','prod')` für TestFlight/Prod-Trennung
- **Cron-Loop** in `cron/check_ablauf.php` wählt pro Subscription den Sender, ein Switch genügt
- **CSRF**: für Bearer-Calls deaktivieren (CSRF schützt nur Cookie-Auth)

### Frontend in SwiftUI

| Web (heute) | iOS Native |
|---|---|
| Vanilla-JS-Views in `js/views/` | SwiftUI-Views mit `@Observable`-Models |
| Hash-Routing in `app.js` | `NavigationStack` + Deep Links über `URL`-Scheme |
| IndexedDB Offline-Queue (`pending_mutations`, `cache_responses`) | **SwiftData** mit `syncStatus`-Feld + lokaler Mutation-Queue |
| Service-Worker + `online`-Event | `BGAppRefreshTask` + `URLSession` Background-Session |
| Web Crypto API | **CryptoKit** (`AES.GCM`, `P256.KeyAgreement`, `HKDF`) |
| IndexedDB-CryptoKey | **Keychain** (Secure Enclave wo möglich für Wrap-Keys) |
| WebAuthn-PRF | **Passkeys** über `AuthenticationServices` |
| `URL.createObjectURL` Foto-Buffer | `PhotosPicker` / `PHPickerViewController` + ggf. HEIC→JPEG |
| `confirmDialog` | `.alert()` + `.confirmationDialog()` |
| `localStorage` Theme | `UserDefaults` + Adaptive Dynamic Colors |
| Toast mit Aktion | Eigener SwiftUI-Overlay (Pattern existiert) |
| Karten-Swipe (eigene Lib) | `.swipeActions { }` (out of the box) |

### Krypto-Kompatibilität — kritisch
Damit derselbe User Web + App parallel nutzen kann, müssen Schlüsselformate identisch bleiben:
- **AES-GCM-256, 96-bit IV**: identisch in Web Crypto und CryptoKit
- **PBKDF2-SHA-256 600k**: identisch
- **RSA-OAEP-2048** (heutige Wahl): in CryptoKit über `SecKey` möglich, aber sperrig
- **Empfehlung**: bei Etappe 4 der E2E-Initiative auf **P-256 ECDH (HPKE)** wechseln — in CryptoKit elegant (`HPKE.Sender`/`HPKE.Recipient`), Web Crypto unterstützt P-256 ebenfalls nativ. Reduziert Plattform-Eigenheiten und Payload-Größe

---

## 3. Benachrichtigungen — der Kern

### Heute (Web Push via VAPID)
- Cron triggert PHP, das via `minishlink/web-push` an Browser-Push-Service sendet
- Auf iOS Safari nur ab 16.4 und nur bei installierter PWA — fragil
- Wegen E2E aktuell **bewusst generisch**: "1 Eintrag fällig"

### Native: APNS — qualitativ neue Möglichkeiten

**Standard-Vorteile gegenüber Web Push:**
- Sub-Sekunden-Zustellung, hochpriorisiert
- **Time-Sensitive Notifications** (iOS 15+) — durchbricht Fokus-Modus, ideal für Medikamenten-Erinnerung
- **Critical Alerts** mit Apple-Sonderfreigabe — durchbricht auch Stummschaltung
- Rich Notifications (Bilder direkt im Banner)
- Action-Buttons im Banner: "Quittieren" / "+1 Tag verschieben" → API-Call ohne App-Öffnung
- Notification Threads (z.B. pro Familie/Gruppe)
- Provisional Authorization: leise Push ohne Initial-Prompt — User entscheidet später

**Der Game-Changer für E2E:**
1. Server verschlüsselt Titel/Body mit User-DEK, packt verschlüsselten Payload als Custom-Field in Push
2. Push trägt `mutable-content: 1` + generischen Default-Body als Fallback
3. iOS startet **Notification Service Extension** (eigenes Target im Xcode-Projekt mit Suffix-Bundle-ID)
4. Extension hat über **App Group** Zugriff auf Keychain → liest DEK
5. Entschlüsselt → ersetzt Notification-Body **vor Anzeige**
6. Server hat *nie* Klartext gesehen, User sieht "Linsen wechseln"
7. Wenn Decrypt scheitert (Reinstall, Keys weg): generischer Default bleibt sichtbar

### Empfohlenes Push-Modell
Pro Gerät, nicht pro User:
- Desktop-Browser → Web Push (VAPID, bleibt)
- iPhone-App → APNS
- Android-Geräte → Web Push (PWA bleibt der Pfad, kein App-Pfad vorerst)

`push_abos` als Source-of-Truth für alle Pfade. Cron-Loop iteriert und delegiert.

### Lokale Notifications zusätzlich
- Wiederholungs-Einträge mit festem Tag-Rhythmus per `UNCalendarNotificationTrigger` lokal schedulen
- Vorteil: kein Server-Roundtrip, robust offline
- Bei Edit: Re-Schedule im SwiftData-Save-Hook
- Server-Push bleibt für Familien-/Gruppen-Events und ad-hoc Erinnerungen zuständig

### Permissions-Flow
- Onboarding: provisional auth direkt nach Login (leise Push), Hard-Prompt erst bei erstem Banner-Wunsch in Einstellungen
- DB-Flag `push_aktiv` bleibt führend, App kennt zusätzlich `UNAuthorizationStatus`

---

## 4. Aufwandseinschätzung (Solo, SwiftUI-Pfad)

| Block | Aufwand |
|---|---|
| Apple Dev Account Setup, Bundle-ID, Zertifikate, Push-Key | 0.5 Wochen |
| Backend: Bearer-Auth + APNS-Sender + Cron-Erweiterung | 1 Woche |
| iOS-Projekt, Auth-Flow, API-Client, Keychain | 1 Woche |
| SwiftData-Schema + Sync-Layer + Offline-Queue | 1.5 Wochen |
| **E2E-Crypto-Port** (CryptoKit) inkl. Recovery-Pfade (BIP39, Datei, Passkey) | 2 Wochen |
| Views (Heute, Liste, Detail, Editor, Einstellungen, Gruppen, Setup-Wizard) | 2-3 Wochen |
| **Push-Stack**: APNS-Token-Handling + Notification Service Extension mit E2E-Decrypt | 1 Woche |
| Foto-Pipeline (PhotosPicker, HEIC, Background-Upload) | 4-5 Tage |
| Polish (Theme adaptiv, Swipe, Haptics, optional: Live Activities, Widgets) | 1 Woche |
| TestFlight-Prozess + App-Store-Review | 1-2 Wochen Kalenderzeit |
| **Summe** | **~10-12 Wochen Vollzeit** |

---

## 5. Was *jetzt* schon Backend-seitig vorbereitet werden kann
Verlustfreie Vorarbeit, die auch ohne App-Start sofort Wert bringt:
1. **`push_abos.art`-Spalte** + Migration (Web Push wird unter `art='web'` weitergeführt; `apns`/`fcm` schon vorgesehen)
2. **Bearer-Auth-Endpoint** in `api/auth.php` (`action=token_login`) — auch von Web parallel nutzbar
3. **E2E-Etappe-4-Wechsel auf P-256 ECDH/HPKE** statt RSA-OAEP — vereinfacht spätere App-Krypto, Web profitiert (kleinere Payloads)

Diese drei Schritte senken den späteren App-Start-Aufwand um ~2 Wochen.

---

## 6. Empfehlung in einem Satz
**Native SwiftUI mit Notification Service Extension** — Backend bleibt PHP, kriegt einen Bearer-Auth-Pfad und einen APNS-Sender. PWA bleibt für Desktop und Android-Familien-Mitglieder unverändert. `push_abos` schon jetzt mit `art`-Spalte (`web|apns|fcm`) erweitern, damit ein späterer Android-App-Pfad ohne Schema-Bruch möglich bleibt.
