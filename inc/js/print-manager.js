/**
 * PrintManager — Zentrale QZ Tray Wrapper-Klasse
 *
 * Kapselt die Verbindung zu QZ Tray, Druckerverwaltung und Druckaufträge.
 * Die Signierung laeuft serverseitig ueber pages/drucksteuerung/sign_api.php.
 */

// Machine-ID fuer Mehrplatz-Druckertrennung (localStorage-basiert)
function getMachineId() {
    let id = localStorage.getItem('msv_machine_id');
    if (!id) {
        id = crypto.randomUUID();
        localStorage.setItem('msv_machine_id', id);
    }
    return id;
}

// Machine-ID Header automatisch an alle Drucksteuerungs-Calls anhaengen
// jQuery AJAX
if (typeof $ !== 'undefined' && $.ajaxPrefilter) {
    $.ajaxPrefilter(function(options) {
        if (options.url && options.url.indexOf('drucksteuerung/') !== -1) {
            options.headers = options.headers || {};
            options.headers['X-Machine-Id'] = getMachineId();
        }
    });
}
// Native fetch
(function() {
    const _fetch = window.fetch;
    window.fetch = function(url, opts) {
        if (typeof url === 'string' && url.indexOf('drucksteuerung/') !== -1) {
            opts = opts || {};
            opts.headers = new Headers(opts.headers || {});
            opts.headers.set('X-Machine-Id', getMachineId());
        }
        return _fetch.call(this, url, opts);
    };
})();

class PrintManager {
    constructor() {
        this.connected = false;
        this.onStatusChange = null; // Callback: (connected: boolean) => void
    }

    /**
     * Verbindung zu QZ Tray herstellen
     */
    async connect() {
        if (this.connected) return true;

        // Falls bereits eine WebSocket-Verbindung besteht (z.B. von anderer Seite/Instanz)
        if (qz.websocket.isActive()) {
            this.connected = true;
            this._notifyStatus();
            console.log('[PrintManager] Bestehende QZ Tray Verbindung wiederverwendet');
            return true;
        }

        try {
            // Zertifikat setzen (öffentliches Zertifikat für Vertrauensstellung)
            qz.security.setCertificatePromise((resolve) => {
                fetch('../certs/digital-certificate.txt')
                    .then(r => {
                        if (!r.ok) throw new Error('HTTP ' + r.status);
                        return r.text();
                    })
                    .then(cert => {
                        console.log('[PrintManager] Zertifikat geladen (' + cert.length + ' Bytes)');
                        resolve(cert);
                    })
                    .catch(err => {
                        console.error('[PrintManager] Zertifikat nicht gefunden:', err);
                        resolve('');
                    });
            });

            // Signierung ueber Backend
            qz.security.setSignatureAlgorithm('SHA512');
            qz.security.setSignaturePromise((toSign) => {
                return (resolve, reject) => {
                    $.ajax({
                        url: 'drucksteuerung/sign_api.php',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ request: toSign }),
                        dataType: 'json',
                        success: (res) => {
                            if (res.success) {
                                console.log('[PrintManager] Signierung erfolgreich');
                                resolve(res.signature);
                            } else {
                                console.error('[PrintManager] Signierung fehlgeschlagen:', res.message);
                                reject(res.message || 'Signierung fehlgeschlagen');
                            }
                        },
                        error: (xhr) => {
                            console.error('[PrintManager] Signierung HTTP-Fehler:', xhr.status, xhr.responseText);
                            reject('Signierung fehlgeschlagen: ' + xhr.statusText);
                        }
                    });
                };
            });

            // Verbinden
            await qz.websocket.connect();
            this.connected = true;
            this._notifyStatus();
            console.log('[PrintManager] Verbunden mit QZ Tray');
            return true;
        } catch (err) {
            this.connected = false;
            this._notifyStatus();
            console.error('[PrintManager] Verbindungsfehler:', err);
            throw err;
        }
    }

    /**
     * Verbindung trennen
     */
    async disconnect() {
        if (!this.connected) return;
        try {
            await qz.websocket.disconnect();
        } catch (err) {
            console.warn('[PrintManager] Disconnect-Fehler:', err);
        }
        this.connected = false;
        this._notifyStatus();
    }

    /**
     * Verbindungsstatus prüfen
     */
    getStatus() {
        this.connected = qz.websocket.isActive();
        return this.connected;
    }

    /**
     * Alle Systemdrucker auflisten
     */
    async listPrinters() {
        if (!this.connected) throw new Error('Nicht verbunden mit QZ Tray');
        return await qz.printers.find();
    }

    /**
     * Druckerstatus abfragen (nicht von allen Druckern unterstuetzt)
     */
    async getPrinterStatus(printerName) {
        if (!this.connected) throw new Error('Nicht verbunden mit QZ Tray');
        try {
            return await qz.printers.getStatus(printerName);
        } catch {
            return null;
        }
    }

    /**
     * Pixel-Druck (PDF, HTML, Bild)
     *
     * @param {string} printerName - Systemname des Druckers
     * @param {Array} data - QZ Tray Daten-Array [{type: 'pdf', data: 'base64...'}]
     * @param {Object} options - {copies, orientation, colorType, duplex, paperSize, ...}
     */
    async printPixel(printerName, data, options = {}) {
        if (!this.connected) throw new Error('Nicht verbunden mit QZ Tray');

        const config = qz.configs.create(printerName, {
            jobName:     options.jobName || null,
            copies:      options.copies || 1,
            orientation: options.orientation || null,
            colorType:   options.colorType || null,
            duplex:      options.duplex || false,
            size:        options.size || null,       // {width, height}
            units:       options.units || 'mm',
            margins:     options.margins || null,
            density:     options.density || null,     // DPI fuer Rasterisierung
            rasterize:   options.rasterize ?? false,
            scaleContent: options.scaleContent ?? true,
        });

        return await qz.print(config, data);
    }

    /**
     * RAW-Druck (ZPL, ESC/POS, etc.)
     *
     * @param {string} printerName - Systemname des Druckers
     * @param {Array} commands - Array mit RAW-Befehlen
     */
    async printRaw(printerName, commands) {
        if (!this.connected) throw new Error('Nicht verbunden mit QZ Tray');

        const config = qz.configs.create(printerName);
        return await qz.print(config, commands);
    }

    /**
     * Druckauftrag serverseitig loggen
     */
    async logJob(docType, printerName, dateiname, status, copies = 1, fehlerText = null) {
        try {
            const res = await $.ajax({
                url: 'drucksteuerung/print_job_api.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    doc_type: docType,
                    printer_name: printerName,
                    dateiname: dateiname,
                    status: status,
                    copies: copies,
                    fehler_text: fehlerText
                }),
                dataType: 'json'
            });
            return res.id || null;
        } catch (err) {
            console.error('[PrintManager] Log-Fehler:', err);
            return null;
        }
    }

    /**
     * Job-Status aktualisieren
     */
    async updateJobStatus(jobId, status, fehlerText = null) {
        try {
            await $.ajax({
                url: 'drucksteuerung/print_job_api.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ id: jobId, status: status, fehler_text: fehlerText }),
                dataType: 'json'
            });
        } catch (err) {
            console.error('[PrintManager] Status-Update-Fehler:', err);
        }
    }

    /**
     * Status-Callback aufrufen
     */
    _notifyStatus() {
        if (typeof this.onStatusChange === 'function') {
            this.onStatusChange(this.connected);
        }
    }
}
