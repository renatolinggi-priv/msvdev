/**
 * MSV UI Audit — Konfiguration
 *
 * Hier Login-Daten und Base-URL anpassen.
 */
module.exports = {
    // Base-URL der Applikation (ANPASSEN!)
    baseUrl: 'https://admin.msvwilen.ch',
    portalBaseUrl: 'https://mitglieder.msvwilen.ch',

    // Login-Daten (ANPASSEN!)
    login: {
        username: 'debug',
        password: 'Rl-040986!'
    },

    // Viewports
    viewports: {
        desktop: { width: 1920, height: 1080 },
        mobile:  { width: 375, height: 812 }
    },

    // Wartezeit nach Seitenladung (ms) — damit AJAX-Inhalte geladen werden
    waitAfterLoad: 1500,

    // Jahr das in Dropdowns ausgewählt wird (damit Daten sichtbar sind)
    dataYear: 2025,

    // Seiten die geprüft werden (gruppiert)
    // Admin-Seiten liegen direkt unter /inc/*.php (KEIN index.php?page= Routing!)
    pages: {
        // --- Auth (kein Login nötig) ---
        auth: [
            { id: 'login', path: '/login.php', label: 'Login', noAuth: true },
            { id: 'register', path: '/register.php', label: 'Registrierung', noAuth: true },
        ],

        // --- Admin Dashboard ---
        admin_dashboard: [
            { id: 'home', path: '/inc/home.php', label: 'Dashboard / Home' },
        ],

        // --- Resultate-Erfassung ---
        resultate: [
            { id: 'jmresultate', path: '/inc/jmresultate.php', label: 'JM Resultate' },
            { id: 'heimresultate', path: '/inc/heimresultate.php', label: 'Heim Resultate' },
            { id: 'kantiresultate', path: '/inc/kantiresultate.php', label: 'Kanti Resultate' },
            { id: 'endresultate', path: '/inc/endresultate.php', label: 'Endschiessen Resultate' },
            { id: 'jsendschresultate', path: '/inc/jsendschresultate.php', label: 'JS Endschiessen Resultate' },
            { id: 'cupresultat', path: '/inc/cupresultat.php', label: 'Cup Resultate' },
        ],

        // --- Rangierungen ---
        rangierungen: [
            { id: 'jmrang', path: '/inc/jmrang.php', label: 'JM Rangierung' },
            { id: 'heimrang', path: '/inc/heimrang.php', label: 'Heim Rangierung' },
            { id: 'kantirang', path: '/inc/kantirang.php', label: 'Kanti Rangierung' },
            { id: 'endschrang', path: '/inc/endschrang.php', label: 'Endschiessen Rangierung' },
            { id: 'cuprang', path: '/inc/cuprang.php', label: 'Cup Rangierung' },
            { id: 'einzelrangierung', path: '/inc/einzelrangierung.php', label: 'Einzelrangierungen' },
            { id: 'sektionsrangierungen', path: '/inc/sektionsrangierungen.php', label: 'Sektionsrangierungen' },
        ],

        // --- Verwaltung ---
        verwaltung: [
            { id: 'jmdefinition', path: '/inc/jmdefinition.php', label: 'JM Definition' },
            { id: 'mitgliederverwaltung', path: '/inc/mitgliederverwaltung.php', label: 'Mitgliederverwaltung' },
            { id: 'jsverwaltung', path: '/inc/jsverwaltung.php', label: 'JS Verwaltung' },
            { id: 'benutzerverwaltung', path: '/inc/benutzerverwaltung.php', label: 'Benutzerverwaltung' },
            { id: 'sieger', path: '/inc/sieger.php', label: 'Sieger' },
            { id: 'wanderpreise', path: '/inc/wanderpreise.php', label: 'Wanderpreise' },
        ],

        // --- Spezialseiten ---
        spezial: [
            { id: 'cup', path: '/inc/cup.php', label: 'Cup Turnierbaum' },
            { id: 'munitionskauf', path: '/inc/munitionskauf.php', label: 'Munitionskauf' },
            { id: 'standbelegung', path: '/inc/standbelegung.php', label: 'Standbelegung' },
            { id: 'wichtigetermine', path: '/inc/wichtigetermine.php', label: 'Wichtige Termine' },
            { id: 'monatsblatt', path: '/inc/monatsblatt.php', label: 'Monatsblatt' },
            { id: 'schuetzenabr', path: '/inc/schuetzenabr.php', label: 'Schützenabrechnung' },
            { id: 'endschloesen', path: '/inc/endschloesen.php', label: 'Endschiessen Lösen' },
            { id: 'kantiabr', path: '/inc/kantiabr.php', label: 'Kanti Abrechnung' },
        ],

        // --- Import ---
        import: [
            { id: 'result_import_csv', path: '/inc/result_import_csv.php', label: 'CSV Import' },
            { id: 'heimkanti_import', path: '/inc/heimkanti_import.php', label: 'Heim/Kanti Import' },
        ],

        // --- Portal (Mitglieder) ---
        portal: [
            { id: 'portal_dashboard', path: '/portal/dashboard.php', label: 'Portal Dashboard' },
            { id: 'portal_meine_jm', path: '/portal/meine_jm.php', label: 'Portal Meine JM' },
            { id: 'portal_meine_heim', path: '/portal/meine_heim.php', label: 'Portal Meine Heim' },
            { id: 'portal_meine_kanti', path: '/portal/meine_kanti.php', label: 'Portal Meine Kanti' },
            { id: 'portal_mein_fragebogen', path: '/portal/mein_fragebogen.php', label: 'Portal Fragebogen' },
            { id: 'portal_meine_einsaetze', path: '/portal/meine_einsaetze.php', label: 'Portal Einsätze' },
            { id: 'portal_protokolle', path: '/portal/protokolle.php', label: 'Portal Protokolle' },
            { id: 'portal_einsatzplaene', path: '/portal/einsatzplaene.php', label: 'Portal Einsatzpläne' },
            { id: 'portal_kalender_abo', path: '/portal/kalender_abo.php', label: 'Portal Kalender-Abo' },
        ],
    }
};
