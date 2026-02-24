    </div><!-- /.portal-content -->

    <footer class="text-center py-3 mt-4" style="color: #adb5bd; font-size: 0.8rem;">
        &copy; <?php echo date('Y'); ?> MSV Wilen
    </footer>

    <!-- ============================================================
         In-App Dokument-Viewer (PDF.js + iframe Fallback)
         Feature-Flag: USE_PDFJS_VIEWER (siehe JS unten)
         ============================================================ -->

    <!-- Viewer Overlay -->
    <div id="docViewerOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:99999; flex-direction:column; background:#111;">

        <!-- Header Bar -->
        <div id="docViewerHeader" style="display:flex; align-items:center; gap:0.5rem; padding:0.5rem 0.75rem; background:#1a1a2e; color:white; flex-shrink:0; min-height:52px; user-select:none;">

            <!-- SCHLIESSEN-BUTTON: Prominent, grosses Touch-Target -->
            <button onclick="closePortalDoc()" id="docViewerCloseBtn" style="
                background: rgba(255,255,255,0.12);
                border: none;
                color: white;
                font-size: 1.3rem;
                width: 44px;
                height: 44px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                -webkit-tap-highlight-color: transparent;
                transition: background 0.15s;
            " aria-label="Schliessen">
                <i class="bi bi-x-lg"></i>
            </button>

            <!-- Dokumenttitel -->
            <span id="docViewerTitle" style="font-size:0.85rem; font-weight:600; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding:0 0.25rem;"></span>

            <!-- PDF-Seitennavigation (nur bei PDF sichtbar) -->
            <div id="pdfNavControls" style="display:none; align-items:center; gap:0.2rem; font-size:0.8rem; flex-shrink:0;">
                <button onclick="pdfGoPage(-1)" style="background:transparent; border:none; color:white; font-size:1.3rem; padding:0.2rem 0.5rem; line-height:1; min-width:36px; min-height:36px;" aria-label="Vorherige Seite">
                    <i class="bi bi-chevron-left"></i>
                </button>
                <span id="pdfPageInfo" style="min-width:50px; text-align:center; font-variant-numeric:tabular-nums;">1 / 1</span>
                <button onclick="pdfGoPage(1)" style="background:transparent; border:none; color:white; font-size:1.3rem; padding:0.2rem 0.5rem; line-height:1; min-width:36px; min-height:36px;" aria-label="Nächste Seite">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>

            <!-- PDF Zoom Controls (nur bei PDF sichtbar) -->
            <div id="pdfZoomControls" style="display:none; align-items:center; gap:0.1rem; flex-shrink:0;">
                <button onclick="pdfZoom(-1)" style="background:transparent; border:none; color:white; font-size:1.1rem; padding:0.2rem 0.4rem; min-width:36px; min-height:36px;" aria-label="Verkleinern">
                    <i class="bi bi-dash-lg"></i>
                </button>
                <button onclick="pdfZoom(0)" style="background:transparent; border:none; color:white; font-size:0.7rem; padding:0.2rem 0.3rem; min-width:36px; min-height:36px;" aria-label="Zoom zurücksetzen" id="pdfZoomLevel">100%</button>
                <button onclick="pdfZoom(1)" style="background:transparent; border:none; color:white; font-size:1.1rem; padding:0.2rem 0.4rem; min-width:36px; min-height:36px;" aria-label="Vergrössern">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>

            <!-- Download / Share Button -->
            <button id="docViewerDownload" onclick="downloadPortalDoc(currentDocId, currentDocFilename, this)" style="
                background: transparent;
                border: none;
                color: white;
                font-size: 1.15rem;
                padding: 0.25rem 0.5rem;
                flex-shrink: 0;
                line-height: 1;
                min-width: 36px;
                min-height: 36px;
            " title="Speichern / Teilen">
                <i class="bi bi-share"></i>
            </button>
        </div>

        <!-- Loading Spinner -->
        <div id="pdfLoading" style="display:none; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); z-index:2; text-align:center; color:white;">
            <div class="spinner-border text-light" style="width:3rem; height:3rem;" role="status"></div>
            <div style="margin-top:0.75rem; font-size:0.9rem;">Dokument wird geladen…</div>
        </div>

        <!-- PDF Canvas Container (scrollbar) -->
        <div id="pdfCanvasContainer" style="display:none; flex:1; overflow:auto; background:#333; -webkit-overflow-scrolling:touch; position:relative;">
            <canvas id="pdfCanvas" style="display:block; margin:0 auto;"></canvas>
        </div>

        <!-- iframe Fallback für Nicht-PDF-Dateien -->
        <iframe id="docViewerFrame" src="" style="display:none; flex:1; border:none; width:100%; background:white;" allowfullscreen></iframe>
    </div>

    <style>
    /* Schliessen-Button Hover-Effekt */
    #docViewerCloseBtn:hover,
    #docViewerCloseBtn:active {
        background: rgba(255,255,255,0.25) !important;
    }
    /* Scrollbar im PDF-Container dezenter */
    #pdfCanvasContainer::-webkit-scrollbar { width: 6px; }
    #pdfCanvasContainer::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 3px; }
    /* PDF-Fehlermeldung */
    .pdf-error-msg {
        color: #ff6b6b;
        text-align: center;
        padding: 2rem 1rem;
        font-size: 0.95rem;
    }
    .pdf-error-msg i { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; }
    </style>

    <script>
    // ================================================================
    // Feature-Flag: Auf false setzen um sofort zum alten iframe-Viewer zurückzukehren
    // ================================================================
    var USE_PDFJS_VIEWER = true;

    // PDF.js Worker konfigurieren
    if (typeof pdfjsLib !== 'undefined') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }

    var currentDocId = 0;
    var currentDocFilename = '';

    // PDF.js State
    var pdfDoc = null;
    var pdfCurrentPage = 1;
    var pdfTotalPages = 0;
    var pdfBaseScale = 0;
    var pdfZoomFactor = 1.0;
    var pdfRendering = false;
    var pdfPendingPage = null;

    function isPdfFile(filename) {
        return filename && filename.toLowerCase().endsWith('.pdf');
    }

    // ================================================================
    // Dokument öffnen (Signatur bleibt identisch!)
    // ================================================================
    function openPortalDoc(id, filename) {
        currentDocId = id;
        currentDocFilename = filename;

        var overlay  = document.getElementById('docViewerOverlay');
        var title    = document.getElementById('docViewerTitle');
        var frame    = document.getElementById('docViewerFrame');
        var pdfCont  = document.getElementById('pdfCanvasContainer');
        var pdfNav   = document.getElementById('pdfNavControls');
        var pdfZoomC = document.getElementById('pdfZoomControls');
        var loading  = document.getElementById('pdfLoading');

        title.textContent = filename;
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Reset
        frame.style.display    = 'none';
        frame.src              = '';
        pdfCont.style.display  = 'none';
        pdfNav.style.display   = 'none';
        pdfZoomC.style.display = 'none';
        loading.style.display  = 'none';

        var docUrl = '../api/dokument_download.php?id=' + id;

        if (USE_PDFJS_VIEWER && isPdfFile(filename) && typeof pdfjsLib !== 'undefined') {
            // === PDF.js Modus ===
            loading.style.display = 'block';
            pdfCont.style.display = 'flex';

            if (pdfDoc) { pdfDoc.destroy(); pdfDoc = null; }
            pdfCurrentPage = 1;
            pdfZoomFactor  = 1.0;
            pdfBaseScale   = 0;

            pdfjsLib.getDocument(docUrl).promise.then(function(doc) {
                pdfDoc = doc;
                pdfTotalPages = doc.numPages;
                loading.style.display = 'none';
                pdfNav.style.display   = 'flex';
                pdfZoomC.style.display = 'flex';
                updatePdfPageInfo();
                renderPdfPage(pdfCurrentPage);
            }).catch(function(err) {
                console.error('PDF.js Fehler:', err);
                loading.style.display = 'none';
                pdfCont.innerHTML = '<div class="pdf-error-msg"><i class="bi bi-exclamation-triangle"></i>PDF konnte nicht geladen werden.<br><small>Versuche es über den Download-Button.</small></div>';
            });
        } else {
            // === Fallback: iframe ===
            frame.style.display = 'block';
            frame.src = docUrl;
        }
    }

    // ================================================================
    // PDF-Seite rendern
    // ================================================================
    function renderPdfPage(pageNum) {
        if (!pdfDoc || pdfRendering) { pdfPendingPage = pageNum; return; }
        pdfRendering = true;

        pdfDoc.getPage(pageNum).then(function(page) {
            var container = document.getElementById('pdfCanvasContainer');
            var canvas    = document.getElementById('pdfCanvas');
            var ctx       = canvas.getContext('2d');

            var viewport = page.getViewport({ scale: 1.0 });
            if (pdfBaseScale === 0) {
                pdfBaseScale = (container.clientWidth - 8) / viewport.width;
            }

            var scale = pdfBaseScale * pdfZoomFactor;
            var scaledViewport = page.getViewport({ scale: scale });

            var dpr = window.devicePixelRatio || 1;
            canvas.width        = Math.floor(scaledViewport.width * dpr);
            canvas.height       = Math.floor(scaledViewport.height * dpr);
            canvas.style.width  = Math.floor(scaledViewport.width) + 'px';
            canvas.style.height = Math.floor(scaledViewport.height) + 'px';
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

            page.render({ canvasContext: ctx, viewport: scaledViewport }).promise.then(function() {
                pdfRendering = false;
                if (pdfPendingPage !== null) {
                    var pending = pdfPendingPage;
                    pdfPendingPage = null;
                    renderPdfPage(pending);
                }
            });
        });
    }

    // ================================================================
    // PDF-Navigation
    // ================================================================
    function pdfGoPage(delta) {
        if (!pdfDoc) return;
        var newPage = pdfCurrentPage + delta;
        if (newPage < 1 || newPage > pdfTotalPages) return;
        pdfCurrentPage = newPage;
        updatePdfPageInfo();
        document.getElementById('pdfCanvasContainer').scrollTop = 0;
        renderPdfPage(pdfCurrentPage);
    }

    function updatePdfPageInfo() {
        document.getElementById('pdfPageInfo').textContent = pdfCurrentPage + ' / ' + pdfTotalPages;
    }

    // ================================================================
    // PDF-Zoom
    // ================================================================
    function pdfZoom(direction) {
        if (!pdfDoc) return;
        if (direction === 0) {
            pdfZoomFactor = 1.0;
        } else if (direction > 0) {
            pdfZoomFactor = Math.min(pdfZoomFactor + 0.25, 4.0);
        } else {
            pdfZoomFactor = Math.max(pdfZoomFactor - 0.25, 0.5);
        }
        document.getElementById('pdfZoomLevel').textContent = Math.round(pdfZoomFactor * 100) + '%';
        renderPdfPage(pdfCurrentPage);
    }

    // ================================================================
    // Dokument schliessen
    // ================================================================
    function closePortalDoc() {
        var overlay = document.getElementById('docViewerOverlay');
        var frame   = document.getElementById('docViewerFrame');
        var pdfCont = document.getElementById('pdfCanvasContainer');

        frame.src = '';
        frame.style.display = 'none';

        if (pdfDoc) { pdfDoc.destroy(); pdfDoc = null; }
        pdfCont.innerHTML = '<canvas id="pdfCanvas" style="display:block; margin:0 auto;"></canvas>';
        pdfCont.style.display = 'none';

        overlay.style.display = 'none';
        document.body.style.overflow = '';
    }

    // ================================================================
    // Download / Teilen (identisch zum Original)
    // ================================================================
    async function downloadPortalDoc(id, filename, btnEl) {
        var origHtml = btnEl ? btnEl.innerHTML : null;
        if (btnEl) { btnEl.disabled = true; btnEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
        try {
            var resp = await fetch('../api/dokument_download.php?id=' + id + '&force_download=1');
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            var blob = await resp.blob();
            var file = new File([blob], filename, { type: blob.type });
            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                await navigator.share({ files: [file], title: filename });
            } else {
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url; a.download = filename;
                document.body.appendChild(a); a.click(); document.body.removeChild(a);
                setTimeout(function() { URL.revokeObjectURL(url); }, 1000);
            }
        } catch(e) {
            if (e.name !== 'AbortError' && typeof msvToast === 'function') {
                msvToast('Download fehlgeschlagen', 'error');
            }
        } finally {
            if (btnEl && origHtml) { btnEl.disabled = false; btnEl.innerHTML = origHtml; }
        }
    }

    // ================================================================
    // Swipe-Down zum Schliessen (Mobile PWA)
    // ================================================================
    (function() {
        var startY = 0, startX = 0;
        var header = document.getElementById('docViewerHeader');
        if (!header) return;

        header.addEventListener('touchstart', function(e) {
            startY = e.touches[0].clientY;
            startX = e.touches[0].clientX;
        }, { passive: true });

        header.addEventListener('touchend', function(e) {
            var deltaY = e.changedTouches[0].clientY - startY;
            var deltaX = Math.abs(e.changedTouches[0].clientX - startX);
            if (deltaY > 60 && deltaX < deltaY) { closePortalDoc(); }
        }, { passive: true });
    })();

    // ================================================================
    // Keyboard Navigation
    // ================================================================
    document.addEventListener('keydown', function(e) {
        var overlay = document.getElementById('docViewerOverlay');
        if (!overlay || overlay.style.display === 'none') return;

        if (e.key === 'Escape') { closePortalDoc(); }
        if (!pdfDoc) return;
        if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')   { e.preventDefault(); pdfGoPage(-1); }
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown')  { e.preventDefault(); pdfGoPage(1); }
        if (e.key === '+' || e.key === '=') { e.preventDefault(); pdfZoom(1); }
        if (e.key === '-')                  { e.preventDefault(); pdfZoom(-1); }
        if (e.key === '0')                  { e.preventDefault(); pdfZoom(0); }
    });

    // ================================================================
    // Fenster-Resize: PDF neu rendern
    // ================================================================
    var pdfResizeTimer = null;
    window.addEventListener('resize', function() {
        if (!pdfDoc) return;
        clearTimeout(pdfResizeTimer);
        pdfResizeTimer = setTimeout(function() {
            pdfBaseScale = 0;
            renderPdfPage(pdfCurrentPage);
        }, 200);
    });
    </script>

    <?php if (!empty($portal_page_js)): ?>
    <script><?php echo $portal_page_js; ?></script>
    <?php endif; ?>
</body>
</html>
