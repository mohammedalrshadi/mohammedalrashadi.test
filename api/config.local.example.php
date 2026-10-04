<?php
// ============================================================
// LOCAL DATABASE CREDENTIALS — EXAMPLE / TEMPLATE
// ============================================================
// This is a TEMPLATE. It contains placeholders only.
//
// Setup:
//   1. Copy this file to api/config.local.php
//      (on the server, via Hostinger File Manager or FTP —
//      do NOT do this through Git)
//   2. Replace every placeholder below with the real Hostinger
//      MySQL credentials.
//   3. Never commit the resulting api/config.local.php file.
//      It is already excluded via .gitignore.
// ============================================================
define('DB_HOST',    'localhost');
define('DB_NAME',    'your_database_name');
define('DB_USER',    'your_database_user');
define('DB_PASS',    'your_database_password');

define('DB_CHARSET', 'utf8mb4');

// IMP-033: Analytics HMAC secret key for hashing persistent visitor cookies.
// Must be a strong, cryptographically random string (min 32 chars).
// Generate via: bin2hex(random_bytes(32))
define('TELEMETRY_SECRET', 'your_telemetry_secret');

// BACKUP_CRON_KEY — optional secret for HTTP-triggered cron access.
// Only needed if you trigger cron/backup.php via HTTP instead of CLI.
// Generate via: bin2hex(random_bytes(24))
// Leave empty or omit to disable HTTP trigger (CLI cron always works without it).
// define('BACKUP_CRON_KEY', 'your_backup_cron_key_here');

// TRUSTED_PROXIES — optional array of trusted reverse proxy / CDN addresses.
// Entries may be exact IPs or CIDR ranges (IPv4 or IPv6).
// - Direct hosting (no proxy/CDN in front): leave unset. Default is loopback only and
//   X-Forwarded-For is ignored, so the client IP cannot be spoofed.
// - Behind Cloudflare / another CDN: set this to that provider's published IP ranges.
//   If you don't, every visitor appears as the proxy's IP and the login rate limiter
//   (which keys on getClientIp()) can lock out all users sharing it.
// X-Forwarded-For is only read when the TCP peer is in this list, and it is read from
// the right (rightmost non-trusted hop), never from the client-controlled leftmost value.
// Default if not specified: ['127.0.0.1', '::1']
// define('TRUSTED_PROXIES', ['127.0.0.1', '::1', '203.0.113.0/24']);  // example ranges only

// SMTP — Outgoing Transactional Email (Hostinger Mail)
// Uncomment all lines and set SMTP_PASS to the real Hostinger email password.
// Never commit the real password; this file is a safe template only.
// define('SMTP_HOST',      'smtp.hostinger.com');
// define('SMTP_PORT',      465);                          // SSL/TLS — or 587 for STARTTLS
// define('SMTP_USER',      'alrashadi@mohammedalrashadi.com');
// define('SMTP_PASS',      'your_smtp_password');         // ← replace on the server
// define('SMTP_FROM',      'alrashadi@mohammedalrashadi.com');
// define('SMTP_FROM_NAME', 'Mohammed Alrashadi');

// DNS records to verify in Hostinger hPanel → Domains → DNS:
//   SPF  : TXT on @      → v=spf1 include:_spf.mail.hostinger.com ~all
//   DKIM : CNAME  hostingermail-a._domainkey → hostingermail-a.dkim.mail.hostinger.com.
//   DKIM : CNAME  hostingermail-b._domainkey → hostingermail-b.dkim.mail.hostinger.com.
//   DKIM : CNAME  hostingermail-c._domainkey → hostingermail-c.dkim.mail.hostinger.com.
//   DMARC: TXT on _dmarc  → v=DMARC1; p=none; rua=mailto:alrashadi@mohammedalrashadi.com
//          (change p=none → p=quarantine after testing passes)
