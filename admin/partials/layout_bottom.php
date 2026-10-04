<?php
// ============================================================
// SHARED PARTIAL — close </main>, output page scripts, close page
// ============================================================
// Expects the including page to define, before requiring this
// file:
//   $pageScripts (array of strings) — script src paths, in load order.
//   js/common.js is added automatically and always loads first.
// ============================================================

if (!isset($pageScripts) || !is_array($pageScripts)) {
    $pageScripts = [];
}

array_unshift($pageScripts, 'js/common.js');
?>

    </main>


    <!-- =====================================================
         ADMIN JAVASCRIPT
    ====================================================== -->

<?php foreach ($pageScripts as $scriptSrc): ?>
<?php
    $scriptPath = __DIR__ . '/../' . $scriptSrc;

    // Cache-bust by CONTENT HASH, not filesystem mtime. mtime depends on
    // the upload/deploy tool actually stamping "now" on the file — some
    // FTP clients, zip-extract-in-place flows, and sync tools preserve an
    // older mtime instead. A content hash changes if and only if the
    // file's bytes actually change, regardless of how it got onto the
    // server, so a stale-cache-after-deploy can't happen here.
    $scriptVersion = file_exists($scriptPath)
        ? substr(md5_file($scriptPath), 0, 10)
        : '1';
?>
    <script src="/admin/<?php echo htmlspecialchars(ltrim($scriptSrc, '/'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo htmlspecialchars($scriptVersion, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endforeach; ?>


</body>

</html>
