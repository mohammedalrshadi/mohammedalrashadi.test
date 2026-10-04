<?php
// ============================================================
// SOCIAL MEDIA ACCOUNTS — Server-Side Protected Admin Route
// Consolidated into Control Center (admin/settings.php?tab=social).
// Maintains 302 redirect for backwards compatibility.
// ============================================================

require_once dirname(__DIR__) . '/api/auth/guard.php';

requireAdminPage('login.php');

header('Location: settings.php?tab=social', true, 302);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=settings.php?tab=social">
    <title>Redirecting to Control Center...</title>
</head>
<body>
    <p>Redirecting to <a href="settings.php?tab=social">Control Center &mdash; Social Channels</a>...</p>
</body>
</html>
