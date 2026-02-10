<?php
// footer.inc.php
// Optional: seitenbezogene JS-Snippets einbinden (im jeweiligen PHP vor dem include setzen: $page_specific_js = '...';)
if (!empty($page_specific_js)) {
    echo "<script>{$page_specific_js}</script>";
}

?>

<!-- Back-to-top Button
<button id="backToTop" class="btn btn-light border position-fixed" style="right:1rem; bottom:1rem; display:none; z-index:1080;" aria-label="Nach oben">
    <i class="bi bi-arrow-up"></i>
</button>

<script>
(function () {
    const btn = document.getElementById('backToTop');
    if (!btn) return;
    window.addEventListener('scroll', function () {
        if (window.scrollY > 300) {
            btn.style.display = 'block';
        } else {
            btn.style.display = 'none';
        }
    });
    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();
</script>

 -->
<!-- WICHTIG: Keine erneute Ausgabe von render_logout_modal() hier! Das Modal wird bereits im Header gerendert. -->
</div> <!-- /.col-12 -->
</div> <!-- /.row -->
</div> <!-- /.container-fluid -->
</body>
</html>
