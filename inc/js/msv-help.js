/**
 * msv-help.js – Globales Hilfesystem (Admin-Bereich).
 *
 * Bindet sich per Event-Delegation auf [data-help]-Elemente. Klick lädt den Eintrag über
 * inc/hilfetexte/api.php?action=lookup&key=… und zeigt ihn im Modal #help-modal (Markup in
 * inc/footer.inc.php). Hover zeigt nach kurzer Verzögerung eine Kurzansicht neben dem Button.
 * Inhalt ist serverseitig sanitisiertes HTML (inc/hilfetexte/html_sanitizer.inc.php).
 *
 * Verwendung im Markup:
 *   <button type="button" class="btn-help" data-help="jmdefinition.skalierung" aria-label="Hilfe"></button>
 *
 * API-Pfad kommt aus window.MSV_HELP_API (gesetzt in inc/header.inc.php über $incBase,
 * damit Seiten in inc/ und admin/ denselben Endpunkt treffen).
 *
 * Öffentlich: MsvHelp.show(key), MsvHelp.close(), MsvHelp.forget(key),
 *             MsvHelp.hoverCustom(el, titel, html), MsvHelp.hoverHide().
 * Portiert aus jungschuetzen.sksg.ch/js/help-modal.js (ohne Turbo-Sonderpfade).
 */
(function () {
    'use strict';

    var MsvHelp = {
        _cache: {},
        _modal: null,
        _overlay: null,
        _title: null,
        _body: null,
        _previousFocus: null,
        _bound: false,
        _isOpen: false,

        _hoverPopup: null,
        _hoverShowTimer: null,
        _hoverHideTimer: null,
        _hoverCurrentKey: null,
        _hoverSeq: 0,

        apiUrl: function () {
            return window.MSV_HELP_API || 'hilfetexte/api.php';
        },

        init: function () {
            this._modal   = document.getElementById('help-modal');
            this._overlay = document.getElementById('help-overlay');
            this._title   = document.getElementById('help-modal-title');
            this._body    = document.getElementById('help-modal-body');
            if (!this._modal || this._bound) return;
            this._bound = true;

            var self = this;

            // Capture-Phase: läuft vor Bootstraps delegierten Handlern (Collapse-Card-Köpfe,
            // data-bs-toggle), damit ein Klick aufs Fragezeichen die Karte nicht mit umschaltet.
            document.addEventListener('click', function (e) {
                var btn = e.target.closest && e.target.closest('[data-help]');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                var key = btn.getAttribute('data-help');
                if (key) self.show(key);
            }, true);

            this._overlay.addEventListener('click', function () { self.close(); });
            var closeBtn = this._modal.querySelector('.help-modal-close');
            if (closeBtn) closeBtn.addEventListener('click', function () { self.close(); });

            // Tab bleibt im offenen Hilfe-Fenster (Capture-Phase: auch über einem Slide-Panel mit eigener Tab-Schleife)
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Tab' || !self._isOpen) return;
                var f = Array.prototype.filter.call(self._modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'),
                    function (el) { return el.offsetParent !== null; });
                e.stopImmediatePropagation();
                if (!f.length) { e.preventDefault(); return; }
                var erstes = f[0], letztes = f[f.length - 1], aktiv = document.activeElement;
                if (!self._modal.contains(aktiv)) { e.preventDefault(); erstes.focus(); }
                else if (e.shiftKey && aktiv === erstes) { e.preventDefault(); letztes.focus(); }
                else if (!e.shiftKey && aktiv === letztes) { e.preventDefault(); erstes.focus(); }
            }, true);

            // ESC in der Capture-Phase, damit ein offenes Slide-Panel nicht gleichzeitig zugeht.
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && self._isOpen) {
                    self.close();
                    e.stopImmediatePropagation();
                }
            }, true);

            this._bindHover();
            this._initFirstVisitHint();
        },

        // ----- Erstbesuch-Hinweis -------------------------------------------------
        // Einmalig (LocalStorage) 2 s nach dem Laden ein kleiner Hinweis auf das erste
        // sichtbare Fragezeichen. Verschwindet nach 12 s, per X oder Klick auf ein «?».
        _initFirstVisitHint: function () {
            var KEY = 'msvjm.help_hint_seen';
            try { if (localStorage.getItem(KEY) === '1') return; } catch (e) { return; }

            setTimeout(function () {
                try { if (localStorage.getItem(KEY) === '1') return; } catch (e) { return; }
                var candidates = document.querySelectorAll('[data-help]');
                var target = null;
                for (var i = 0; i < candidates.length; i++) {
                    if (candidates[i].offsetParent !== null) { target = candidates[i]; break; }
                }
                if (!target) return;

                var hint = document.createElement('div');
                hint.id = 'help-firsthint';
                hint.innerHTML =
                    '<div class="help-firsthint-arrow"></div>'
                  + '<div class="help-firsthint-body"><strong>Tipp:</strong> Hinter dem Fragezeichen steht eine kurze Erklärung zu diesem Bereich.</div>'
                  + '<button type="button" class="help-firsthint-close" aria-label="Hinweis schliessen">&times;</button>';
                document.body.appendChild(hint);

                var r = target.getBoundingClientRect();
                var left = r.left + window.scrollX + (r.width / 2) - 18;
                if (left < 8) left = 8;
                var vw = document.documentElement.clientWidth;
                if (left + hint.offsetWidth > vw - 8) left = vw - hint.offsetWidth - 8;
                hint.style.top  = (r.bottom + window.scrollY + 10) + 'px';
                hint.style.left = left + 'px';

                try { localStorage.setItem(KEY, '1'); } catch (e) {}
                requestAnimationFrame(function () { hint.classList.add('show'); });

                var autoTimer;
                var dismiss = function () {
                    if (!hint.parentNode) return;
                    hint.classList.remove('show');
                    setTimeout(function () { if (hint.parentNode) hint.parentNode.removeChild(hint); }, 250);
                    document.removeEventListener('click', onAnyClick, true);
                    clearTimeout(autoTimer);
                };
                var onAnyClick = function (e) {
                    if (e.target.closest('[data-help]') || e.target.closest('#help-firsthint')) dismiss();
                };
                document.addEventListener('click', onAnyClick, true);
                hint.querySelector('.help-firsthint-close').addEventListener('click', dismiss);
                autoTimer = setTimeout(dismiss, 12000);
            }, 2000);
        },

        // ----- Hover-Kurzansicht ---------------------------------------------------
        _ensureHoverPopup: function () {
            if (this._hoverPopup && document.body.contains(this._hoverPopup)) return;
            var self = this;
            this._hoverPopup = document.createElement('div');
            this._hoverPopup.id = 'help-hover-popup';
            this._hoverPopup.setAttribute('role', 'tooltip');
            this._hoverPopup.hidden = true;
            this._hoverPopup.addEventListener('mouseenter', function () { self._cancelHide(); });
            this._hoverPopup.addEventListener('mouseleave', function () { self._scheduleHide(); });
            document.body.appendChild(this._hoverPopup);
        },

        _bindHover: function () {
            var self = this;
            this._ensureHoverPopup();

            document.addEventListener('mouseover', function (e) {
                var btn = e.target.closest && e.target.closest('[data-help]');
                if (!btn) return;
                var key = btn.getAttribute('data-help');
                if (!key) return;
                if (self._hoverCurrentKey === key && !self._hoverPopup.hidden) { self._cancelHide(); return; }
                self._cancelHide();
                clearTimeout(self._hoverShowTimer);
                self._hoverShowTimer = setTimeout(function () { self._showHover(btn, key); }, 180);
            });
            document.addEventListener('mouseout', function (e) {
                var btn = e.target.closest && e.target.closest('[data-help]');
                if (!btn) return;
                var to = e.relatedTarget;
                if (to && self._hoverPopup.contains(to)) return;
                clearTimeout(self._hoverShowTimer);
                self._scheduleHide();
            });
            // Tastatur: Fokus auf einem «?» zeigt dieselbe Kurzansicht (Enter öffnet weiterhin das Fenster)
            document.addEventListener('focusin', function (e) {
                var btn = e.target.closest && e.target.closest('[data-help]');
                if (!btn || !btn.matches(':focus-visible')) return;
                var key = btn.getAttribute('data-help');
                if (!key) return;
                self._cancelHide();
                clearTimeout(self._hoverShowTimer);
                self._hoverShowTimer = setTimeout(function () { self._showHover(btn, key); }, 180);
            });
            document.addEventListener('focusout', function (e) {
                if (!(e.target.closest && e.target.closest('[data-help]'))) return;
                clearTimeout(self._hoverShowTimer);
                self._scheduleHide();
            });
            document.addEventListener('click', function (e) {
                if (e.target.closest && e.target.closest('[data-help]')) self._hideHover();
            });
        },

        _cancelHide: function () { clearTimeout(this._hoverHideTimer); this._hoverHideTimer = null; },
        _scheduleHide: function () {
            var self = this;
            this._cancelHide();
            this._hoverHideTimer = setTimeout(function () { self._hideHover(); }, 180);
        },
        _hideHover: function () {
            this._cancelHide();
            this._hoverCurrentKey = null;
            if (this._hoverPopup) this._hoverPopup.hidden = true;
        },

        _showHover: function (btn, key) {
            var self = this;
            this._ensureHoverPopup();
            var seq = ++this._hoverSeq;
            this._hoverCurrentKey = key;
            this._fetch(key).then(function (data) {
                if (seq !== self._hoverSeq || self._hoverCurrentKey !== key) return;
                self._hoverPopup.innerHTML = ''
                    + '<div class="help-hover-title">' + (self._esc(data.titel) || 'Hilfe') + '</div>'
                    + '<div class="help-hover-body">' + (data.inhalt_html || '') + '</div>'
                    + '<div class="help-hover-footer">Klicken für die volle Ansicht</div>';
                self._hoverPopup.hidden = false;
                self._positionHover(btn);
            }).catch(function () { /* Weitergezogen oder kein Text: still bleiben. */ });
        },

        _esc: function (s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        },

        // Gleiche Optik mit frei übergebenem Inhalt (Aufrufer bindet mouseover/mouseout selbst).
        hoverCustom: function (el, titel, bodyHtml) {
            this._ensureHoverPopup();
            this._hoverSeq++;
            this._hoverCurrentKey = null;
            this._cancelHide();
            clearTimeout(this._hoverShowTimer);
            this._hoverPopup.innerHTML = ''
                + '<div class="help-hover-title">' + this._esc(titel) + '</div>'
                + '<div class="help-hover-body">' + bodyHtml + '</div>';
            this._hoverPopup.hidden = false;
            this._positionHover(el);
        },
        hoverHide: function () { this._scheduleHide(); },

        _positionHover: function (btn) {
            var r  = btn.getBoundingClientRect();
            var pp = this._hoverPopup;
            pp.style.visibility = 'hidden';
            pp.style.left = '0px';
            pp.style.top  = '0px';
            var w = pp.offsetWidth, h = pp.offsetHeight;
            var vw = document.documentElement.clientWidth;
            var vh = document.documentElement.clientHeight;
            var left = r.right + 10;
            if (left + w > vw - 8) left = Math.max(8, r.left - w - 10);
            var top = r.top + (r.height / 2) - 24;
            if (top + h > vh - 8) top = Math.max(8, vh - h - 8);
            if (top < 8) top = 8;
            pp.style.left = (left + window.scrollX) + 'px';
            pp.style.top  = (top + window.scrollY) + 'px';
            pp.style.visibility = '';
        },

        // ----- Modal ---------------------------------------------------------------
        show: function (key) {
            if (!this._modal) return;
            var self = this;
            this._fetch(key).then(function (data) {
                self._title.textContent = data.titel || 'Hilfe';
                self._body.innerHTML = data.inhalt_html || '';
                self._open();
            }).catch(function (err) {
                if (typeof msvToast === 'function') msvToast(err.message || 'Hilfetext nicht verfügbar', 'error');
            });
        },

        forget: function (key) { delete this._cache[key]; },

        _fetch: function (key) {
            var self = this;
            if (this._cache[key]) return Promise.resolve(this._cache[key]);
            return fetch(this.apiUrl() + '?action=lookup&key=' + encodeURIComponent(key), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            }).then(function (resp) {
                return resp.json().catch(function () { return {}; }).then(function (json) {
                    if (!resp.ok || !json.success) throw new Error(json.message || ('HTTP ' + resp.status));
                    self._cache[key] = json.item;
                    return json.item;
                });
            });
        },

        _open: function () {
            if (this._isOpen) return;
            this._previousFocus = document.activeElement;
            this._modal.removeAttribute('hidden');
            void this._modal.offsetWidth; // Reflow, damit die Transition greift.
            this._overlay.classList.add('active');
            this._modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            this._isOpen = true;
            var self = this;
            setTimeout(function () {
                var c = self._modal.querySelector('.help-modal-close');
                if (c) c.focus();
            }, 50);
        },

        close: function () {
            if (!this._isOpen) return;
            this._overlay.classList.remove('active');
            this._modal.classList.remove('open');
            document.body.style.overflow = '';
            this._isOpen = false;
            var self = this;
            setTimeout(function () { if (!self._isOpen) self._modal.setAttribute('hidden', ''); }, 220);
            if (this._previousFocus && typeof this._previousFocus.focus === 'function') {
                try { this._previousFocus.focus(); } catch (e) {}
            }
            this._previousFocus = null;
        }
    };

    window.MsvHelp = MsvHelp;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { MsvHelp.init(); });
    } else {
        MsvHelp.init();
    }
})();
