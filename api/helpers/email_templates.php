<?php
// ============================================================
// EMAIL DESIGN SYSTEM
// api/helpers/email_templates.php
//
// renderEmail(array $opts): array   → ['html' => ..., 'text' => ...]
//
// Named builders (thin wrappers):
//   buildPasswordResetEmail(array $data): array
//   buildPasswordChangedEmail(array $data): array
//   buildSupportConfirmationEmail(array $data): array
//   buildSupportOwnerNotificationEmail(array $data): array
//   buildWelcomeEmail(array $data): array
//
// Design: dark, table-based, all CSS inline.
// Compatible with Gmail, Apple Mail, Outlook desktop/web, mobile.
// ============================================================

/**
 * Core layout renderer.
 *
 * Options (all optional unless noted):
 *   preheader       string   Hidden inbox preview text
 *   eyebrow         string   Monospace eyebrow label, e.g. "// SECURITY & ACCESS"
 *   title           string   Main heading (REQUIRED)
 *   greeting        string   Greeting line
 *   intro           string[] Body paragraphs (plain strings, HTML-escaped internally)
 *   intro_html      string[] Body paragraphs that are already safe HTML (NOT escaped again)
 *   button          array    ['label' => ..., 'url' => ...]
 *   raw_link        string   URL shown below button as "Or copy this link"
 *   info_box        array    ['rows' => [['label'=>..., 'value'=>...]]]
 *   security_notice string   Amber left-border callout
 *   footer_note     string   Extra note above standard footer
 *
 * @return array{html: string, text: string}
 */
function renderEmail(array $opts): array
{
    // ---- Helpers ---------------------------------------------------------
    $e   = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $esc = fn(mixed $s): string  => htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // ---- Extract options -------------------------------------------------
    $preheader      = $opts['preheader']      ?? '';
    $eyebrow        = $opts['eyebrow']        ?? '';
    $title          = $opts['title']          ?? '';
    $greeting       = $opts['greeting']       ?? '';
    $introParagraphs= $opts['intro']          ?? [];   // escaped by us
    $introHtml      = $opts['intro_html']     ?? [];   // caller-trusted HTML
    $button         = $opts['button']         ?? null; // ['label'=>..., 'url'=>...]
    $rawLink        = $opts['raw_link']       ?? '';
    $infoBox        = $opts['info_box']       ?? null; // ['rows'=>[...]]
    $securityNotice = $opts['security_notice']?? '';
    $footerNote     = $opts['footer_note']    ?? '';

    $siteUrl  = 'https://mohammedalrashadi.com';
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Mohammed Alrashadi';
    $fromAddr = defined('SMTP_FROM')      ? SMTP_FROM      : 'alrashadi@mohammedalrashadi.com';

    // ---- Hidden preheader with spacer chars (prevents body bleed) --------
    $preheaderHtml = '';
    if ($preheader !== '') {
        $spacer = str_repeat('&#160;&#8203;', 60);
        $preheaderHtml = '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;color:#131922;line-height:1px;">'
            . $esc($preheader) . $spacer . '</div>';
    }

    // ---- Button (bulletproof VML+table) ----------------------------------
    $buttonHtml = '';
    if ($button && !empty($button['url'])) {
        $btnLabel = $esc($button['label'] ?? 'Click Here');
        $btnUrl   = $esc($button['url']);
        // VML fallback for Outlook + standard <a> for all others
        $buttonHtml = <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:28px 0 12px;">
  <tr>
    <td align="center">
      <!--[if mso]>
      <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word"
        href="{$btnUrl}"
        style="height:50px;v-text-anchor:middle;width:520px;"
        arcsize="20%"
        stroke="f"
        fillcolor="#4CC9F0">
        <w:anchorlock/>
        <center style="color:#04202B;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;font-weight:700;">
          {$btnLabel}
        </center>
      </v:roundrect>
      <![endif]-->
      <!--[if !mso]><!-->
      <a href="{$btnUrl}"
         target="_blank"
         rel="noopener noreferrer"
         style="background-color:#4CC9F0;border-radius:10px;color:#04202B;display:inline-block;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;font-weight:700;line-height:1;min-width:200px;padding:16px 32px;text-align:center;text-decoration:none;mso-hide:all;">
        {$btnLabel}
      </a>
      <!--<![endif]-->
    </td>
  </tr>
</table>
HTML;
    }

    // ---- "Or copy this link" block ---------------------------------------
    $rawLinkHtml = '';
    if ($rawLink !== '') {
        $safeUrl = $esc($rawLink);
        $rawLinkHtml = <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:24px;">
  <tr>
    <td>
      <p style="margin:0 0 6px;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12px;color:#64748B;">Or copy this link into your browser:</p>
      <div style="background:#0F141B;border:1px solid rgba(148,163,184,0.14);border-radius:6px;padding:10px 14px;word-break:break-all;">
        <span style="font-family:'SF Mono',Menlo,Consolas,monospace;font-size:12px;color:#94A3B8;">{$safeUrl}</span>
      </div>
    </td>
  </tr>
</table>
HTML;
    }

    // ---- Info box --------------------------------------------------------
    $infoBoxHtml = '';
    if ($infoBox && !empty($infoBox['rows'])) {
        $rows = '';
        foreach ($infoBox['rows'] as $row) {
            $label = $esc($row['label'] ?? '');
            $value = $esc($row['value'] ?? '');
            $rows .= <<<HTML
<tr>
  <td style="padding:6px 12px;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12.5px;color:#64748B;white-space:nowrap;vertical-align:top;">{$label}</td>
  <td style="padding:6px 12px;font-family:'SF Mono',Menlo,Consolas,monospace;font-size:12px;color:#94A3B8;word-break:break-all;vertical-align:top;">{$value}</td>
</tr>
HTML;
        }
        $infoBoxHtml = <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
  style="margin:20px 0;background:#0F141B;border:1px solid rgba(148,163,184,0.14);border-radius:8px;overflow:hidden;">
  <tbody>
    {$rows}
  </tbody>
</table>
HTML;
    }

    // ---- Security notice (amber callout) ---------------------------------
    $securityHtml = '';
    if ($securityNotice !== '') {
        $securityHtml = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:20px 0;">'
            . '<tr><td style="border-left:3px solid #F59E0B;padding:10px 16px;background:#1A1506;border-radius:0 6px 6px 0;">'
            . '<p style="margin:0;font-family:-apple-system,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:13.5px;color:#FCD34D;line-height:1.5;">'
            . $esc($securityNotice) . '</p></td></tr></table>';
    }

    // ---- Body paragraphs -------------------------------------------------
    $bodyHtml = '';
    if ($greeting !== '') {
        $bodyHtml .= '<p style="margin:0 0 14px;font-family:-apple-system,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.65;color:#B7C3D3;font-weight:600;">'
            . $esc($greeting) . '</p>';
    }
    foreach ($introParagraphs as $para) {
        $bodyHtml .= '<p style="margin:0 0 14px;font-family:-apple-system,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.65;color:#B7C3D3;">'
            . $esc($para) . '</p>';
    }
    foreach ($introHtml as $para) {
        // Caller-supplied safe HTML (nl2br output etc.)
        $bodyHtml .= '<p style="margin:0 0 14px;font-family:-apple-system,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.65;color:#B7C3D3;">'
            . $para . '</p>';
    }

    // ---- Footer note -----------------------------------------------------
    $footerNoteHtml = '';
    if ($footerNote !== '') {
        $footerNoteHtml = '<p style="margin:0 0 10px;font-family:-apple-system,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:12px;color:#475569;">'
            . $esc($footerNote) . '</p>';
    }

    // ---- Eyebrow label ---------------------------------------------------
    $eyebrowHtml = '';
    if ($eyebrow !== '') {
        $eyebrowHtml = '<p style="margin:0 0 10px;font-family:\'SF Mono\',Menlo,Consolas,monospace;font-size:11.5px;color:#4CC9F0;letter-spacing:0.04em;">'
            . $esc($eyebrow) . '</p>';
    }

    // ---- Title -----------------------------------------------------------
    $titleHtml = '';
    if ($title !== '') {
        $titleHtml = '<h1 style="margin:0 0 20px;font-family:-apple-system,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:23px;font-weight:700;color:#F1F5F9;line-height:1.25;">'
            . $esc($title) . '</h1>';
    }

    // ---- Assemble HTML ---------------------------------------------------
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="color-scheme" content="dark light">
  <meta name="supported-color-schemes" content="dark light">
  <title>{$esc($title)}</title>
  <!--[if mso]>
  <noscript>
    <xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
  </noscript>
  <![endif]-->
  <style type="text/css">
    /* Reset */
    body, table, td, a { -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }
    table, td { mso-table-lspace:0pt; mso-table-rspace:0pt; }
    img { -ms-interpolation-mode:bicubic; border:0; height:auto; line-height:100%; outline:none; text-decoration:none; }
    /* Mobile */
    @media only screen and (max-width:620px) {
      .email-card { width:100% !important; border-radius:0 !important; }
      .email-body-cell { padding:28px 20px !important; }
      .btn-full { display:block !important; text-align:center !important; }
    }
    /* Dark-mode overrides — cosmetic only, light text is already set inline */
    @media (prefers-color-scheme:dark) {
      .email-outer { background-color:#0B0F14 !important; }
    }
  </style>
</head>
<body bgcolor="#0B0F14" style="margin:0;padding:0;background-color:#0B0F14;" class="email-outer">

{$preheaderHtml}

<!-- Outer wrapper -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" bgcolor="#0B0F14"
  style="background-color:#0B0F14;min-width:100%;table-layout:fixed;">
  <tr>
    <td align="center" style="padding:32px 16px 40px;">

      <!-- Email card (600px wide) -->
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-card"
        style="background-color:#131922;border:1px solid rgba(148,163,184,0.16);border-radius:14px;max-width:600px;width:100%;">
        <tr>
          <td class="email-body-cell" style="padding:36px 40px 32px;">

            <!-- ====== HEADER ====== -->
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:0;">
              <tr>
                <!-- Monogram badge -->
                <td width="52" valign="middle" style="padding-right:14px;">
                  <div style="background-color:#0F141B;border:1px solid rgba(76,201,240,0.35);border-radius:8px;width:46px;height:46px;text-align:center;line-height:46px;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;font-weight:800;color:#4CC9F0;letter-spacing:-0.5px;">MA</div>
                </td>
                <!-- Name + tagline -->
                <td valign="middle">
                  <div style="font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;font-weight:700;color:#F1F5F9;letter-spacing:0.02em;margin-bottom:2px;">MOHAMMED ALRASHADI</div>
                  <div style="font-family:'SF Mono',Menlo,Consolas,monospace;font-size:10.5px;color:#64748B;letter-spacing:0.06em;text-transform:uppercase;">ADMIN STUDIO / PLATFORM</div>
                </td>
              </tr>
            </table>

            <!-- Accent rule -->
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:16px 0 24px;">
              <tr><td height="1" bgcolor="#4CC9F0" style="background-color:#4CC9F0;line-height:1px;font-size:1px;">&nbsp;</td></tr>
            </table>

            <!-- ====== CONTENT ====== -->
            {$eyebrowHtml}
            {$titleHtml}
            {$bodyHtml}
            {$buttonHtml}
            {$rawLinkHtml}
            {$infoBoxHtml}
            {$securityHtml}

            <!-- ====== FOOTER ====== -->
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top:28px;border-top:1px solid rgba(148,163,184,0.1);padding-top:20px;">
              <tr>
                <td>
                  {$footerNoteHtml}
                  <p style="margin:0 0 4px;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12px;color:#475569;">
                    <a href="{$esc($siteUrl)}" target="_blank" rel="noopener noreferrer" style="color:#4CC9F0;text-decoration:none;">mohammedalrashadi.com</a>
                    &nbsp;&middot;&nbsp;
                    <a href="mailto:{$esc($fromAddr)}" style="color:#64748B;text-decoration:none;">{$esc($fromAddr)}</a>
                  </p>
                  <p style="margin:0;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11.5px;color:#334155;">
                    This is an automated message — please do not reply directly to this email.
                  </p>
                </td>
              </tr>
            </table>

          </td>
        </tr>
      </table>
      <!-- /email card -->

    </td>
  </tr>
</table>

</body>
</html>
HTML;

    // ---- Assemble plain-text version ------------------------------------
    $lines = [];
    if ($preheader !== '') {
        $lines[] = $preheader;
        $lines[] = '';
    }
    $lines[] = strtoupper('MOHAMMED ALRASHADI — ADMIN STUDIO / PLATFORM');
    $lines[] = str_repeat('─', 56);
    if ($eyebrow !== '')   { $lines[] = $eyebrow; $lines[] = ''; }
    if ($title !== '')     { $lines[] = strtoupper($title); $lines[] = ''; }
    if ($greeting !== '')  { $lines[] = $greeting; $lines[] = ''; }
    foreach ($introParagraphs as $para) { $lines[] = $para; $lines[] = ''; }
    foreach ($introHtml as $para) {
        $lines[] = strip_tags(str_replace(['<br>', '<br/>','<br />'], "\n", $para));
        $lines[] = '';
    }
    if ($button && !empty($button['url'])) {
        $lines[] = '>> ' . ($button['label'] ?? 'Click Here');
        $lines[] = $button['url'];
        $lines[] = '';
    }
    if ($rawLink !== '') {
        $lines[] = 'Or copy this link:';
        $lines[] = $rawLink;
        $lines[] = '';
    }
    if ($infoBox && !empty($infoBox['rows'])) {
        $lines[] = str_repeat('─', 40);
        foreach ($infoBox['rows'] as $row) {
            $lines[] = ($row['label'] ?? '') . ': ' . ($row['value'] ?? '');
        }
        $lines[] = str_repeat('─', 40);
        $lines[] = '';
    }
    if ($securityNotice !== '') {
        $lines[] = '⚠ ' . $securityNotice;
        $lines[] = '';
    }
    if ($footerNote !== '') { $lines[] = $footerNote; $lines[] = ''; }
    $lines[] = str_repeat('─', 56);
    $lines[] = $siteUrl . '  |  ' . $fromAddr;
    $lines[] = 'This is an automated message — please do not reply directly.';

    $text = implode("\n", $lines);

    return ['html' => $html, 'text' => $text];
}


// ============================================================
// Named Template Builders
// ============================================================

/**
 * 1. Password reset request email.
 *
 * Required: name, reset_url, requested_time, requested_ip
 */
function buildPasswordResetEmail(array $data): array
{
    $name   = $data['name']           ?? 'there';
    $url    = $data['reset_url']      ?? '';
    $time   = $data['requested_time'] ?? date('Y-m-d H:i:s T');
    $ip     = $data['requested_ip']   ?? 'unknown';

    return renderEmail([
        'preheader'       => 'You requested a password reset for your Mohammed Alrashadi Platform account.',
        'eyebrow'         => '// SECURITY & ACCESS',
        'title'           => 'Reset Your Password',
        'greeting'        => 'Hello ' . $name . ',',
        'intro'           => [
            'A password reset was requested for your account. Use the button below to choose a new password.',
            'This link is valid for 60 minutes and can only be used once. If it expires, you can request a new one.',
        ],
        'button'          => ['label' => 'Reset My Password', 'url' => $url],
        'raw_link'        => $url,
        'info_box'        => [
            'rows' => [
                ['label' => 'Requested at', 'value' => $time],
                ['label' => 'Requested from', 'value' => $ip],
                ['label' => 'Valid for', 'value' => '60 minutes, single use'],
            ],
        ],
        'security_notice' => 'If you didn\'t request this, you can safely ignore this email. Your password won\'t change.',
        'footer_note'     => 'For security questions, contact ' . (defined('SMTP_FROM') ? SMTP_FROM : 'alrashadi@mohammedalrashadi.com') . '.',
    ]);
}

/**
 * 2. Password successfully changed confirmation.
 *
 * Required: name, changed_time, changed_ip, reply_address
 */
function buildPasswordChangedEmail(array $data): array
{
    $name    = $data['name']          ?? 'there';
    $time    = $data['changed_time']  ?? date('Y-m-d H:i:s T');
    $ip      = $data['changed_ip']    ?? 'unknown';
    $replyTo = $data['reply_address'] ?? (defined('SMTP_FROM') ? SMTP_FROM : 'alrashadi@mohammedalrashadi.com');

    return renderEmail([
        'preheader'       => 'Your Mohammed Alrashadi Platform password was just changed.',
        'eyebrow'         => '// SECURITY ALERT',
        'title'           => 'Your Password Was Changed',
        'greeting'        => 'Hello ' . $name . ',',
        'intro'           => [
            'Your account password was successfully updated.',
            'If this was you, no further action is needed.',
        ],
        'info_box'        => [
            'rows' => [
                ['label' => 'Changed at',   'value' => $time],
                ['label' => 'Changed from', 'value' => $ip],
            ],
        ],
        'security_notice' => 'If you did NOT make this change, contact me immediately by replying to this email or writing to ' . $replyTo . '.',
    ]);
}

/**
 * 3. Support message received — visitor confirmation.
 *
 * Required: name, ticket_id, subject, category
 * (No full message echo — minimal by design)
 */
function buildSupportConfirmationEmail(array $data): array
{
    $name     = $data['name']      ?? 'there';
    $ticketId = $data['ticket_id'] ?? '—';
    $subject  = $data['subject']   ?? '';
    $category = $data['category']  ?? 'general';

    return renderEmail([
        'preheader'   => 'Your message has been received. Ticket #' . $ticketId . '.',
        'eyebrow'     => '// SUPPORT',
        'title'       => 'Message Received',
        'greeting'    => 'Hello ' . $name . ',',
        'intro'       => [
            'Thank you for reaching out. Your message has been received and will be reviewed shortly.',
            'Here is a summary of your ticket:',
        ],
        'info_box'    => [
            'rows' => [
                ['label' => 'Ticket #',  'value' => (string)$ticketId],
                ['label' => 'Subject',   'value' => $subject],
                ['label' => 'Category',  'value' => ucfirst($category)],
                ['label' => 'Submitted', 'value' => date('Y-m-d H:i:s T')],
            ],
        ],
        'footer_note' => 'I typically respond within 1–2 business days.',
    ]);
}

/**
 * 4. Support message received — owner notification.
 *
 * Required: ticket_id, name, email, category, subject, message, ip
 */
function buildSupportOwnerNotificationEmail(array $data): array
{
    $ticketId = $data['ticket_id'] ?? '—';
    $name     = $data['name']      ?? '';
    $email    = $data['email']     ?? '';
    $category = $data['category']  ?? 'general';
    $subject  = $data['subject']   ?? '';
    $message  = $data['message']   ?? '';
    $ip       = $data['ip']        ?? 'unknown';

    // Truncate message preview to 800 chars to avoid Gmail clipping
    $msgPreview = mb_strlen($message) > 800
        ? mb_substr($message, 0, 800) . '… [truncated]'
        : $message;

    return renderEmail([
        'preheader'  => 'New support ticket #' . $ticketId . ' from ' . $name . '.',
        'eyebrow'    => '// ADMIN NOTIFICATION',
        'title'      => 'New Support Ticket #' . $ticketId,
        'greeting'   => 'A new support message was submitted.',
        'info_box'   => [
            'rows' => [
                ['label' => 'Ticket #',  'value' => (string)$ticketId],
                ['label' => 'From',      'value' => $name . ' <' . $email . '>'],
                ['label' => 'Category',  'value' => ucfirst($category)],
                ['label' => 'Subject',   'value' => $subject],
                ['label' => 'IP',        'value' => $ip],
                ['label' => 'Received',  'value' => date('Y-m-d H:i:s T')],
            ],
        ],
        'intro_html' => [
            '<strong style="color:#94A3B8;">Message preview:</strong>',
            nl2br(htmlspecialchars($msgPreview, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
        ],
        'footer_note'=> 'Reply directly to ' . $email . ' or manage in Admin Studio.',
    ]);
}

/**
 * 5. Welcome email after registration.
 *
 * Required: name, dashboard_url
 */
function buildWelcomeEmail(array $data): array
{
    $name    = $data['name']          ?? 'there';
    $dashUrl = $data['dashboard_url'] ?? 'https://mohammedalrashadi.com/dashboard/';

    return renderEmail([
        'preheader'  => 'Welcome to Mohammed Alrashadi Platform — your account is ready.',
        'eyebrow'    => '// WELCOME',
        'title'      => 'Your Account Is Ready',
        'greeting'   => 'Welcome, ' . $name . '!',
        'intro'      => [
            'Your account on Mohammed Alrashadi Platform has been created. You can now access your personal dashboard, bookmark content, and engage with articles and projects.',
            'Everything is set up — head to your dashboard to get started.',
        ],
        'button'     => ['label' => 'Go to Your Dashboard', 'url' => $dashUrl],
        'footer_note'=> 'If you didn\'t create this account, please contact ' . (defined('SMTP_FROM') ? SMTP_FROM : 'alrashadi@mohammedalrashadi.com') . ' immediately.',
    ]);
}

/**
 * 6. Email address verification request.
 *
 * Required: name, verify_url, requested_time, requested_ip
 */
function buildVerifyEmailEmail(array $data): array
{
    $name    = $data['name']           ?? 'there';
    $url     = $data['verify_url']     ?? '';
    $time    = $data['requested_time'] ?? date('Y-m-d H:i:s T');
    $ip      = $data['requested_ip']   ?? 'unknown';
    $replyTo = defined('SMTP_FROM') ? SMTP_FROM : 'alrashadi@mohammedalrashadi.com';

    return renderEmail([
        'preheader'       => 'Verify your email address for your Mohammed Alrashadi Platform account.',
        'eyebrow'         => '// ACCOUNT VERIFICATION',
        'title'           => 'Verify Your Email Address',
        'greeting'        => 'Hello ' . $name . ',',
        'intro'           => [
            'Thank you for joining Mohammed Alrashadi Platform. Please verify your email address to confirm ownership and activate full account privileges.',
            'This verification link is valid for 24 hours and can only be used once.',
        ],
        'button'          => ['label' => 'Verify My Email', 'url' => $url],
        'raw_link'        => $url,
        'info_box'        => [
            'rows' => [
                ['label' => 'Requested at', 'value' => $time],
                ['label' => 'Requested from', 'value' => $ip],
                ['label' => 'Valid for', 'value' => '24 hours, single use'],
            ],
        ],
        'security_notice' => 'If you did not create an account on Mohammed Alrashadi Platform, you can safely ignore this email. No account will be activated without verification.',
        'footer_note'     => 'Questions? Contact ' . $replyTo . '.',
    ]);
}

/**
 * 7. Notice sent to old email address when account email is changed.
 *
 * Required: name, old_email, new_email, changed_time, changed_ip
 */
function buildEmailChangedNoticeEmail(array $data): array
{
    $name     = $data['name']         ?? 'there';
    $oldEmail = $data['old_email']    ?? '';
    $newEmail = $data['new_email']    ?? '';
    $time     = $data['changed_time'] ?? date('Y-m-d H:i:s T');
    $ip       = $data['changed_ip']   ?? 'unknown';
    $replyTo  = defined('SMTP_FROM')  ? SMTP_FROM : 'alrashadi@mohammedalrashadi.com';

    return renderEmail([
        'preheader'       => 'Your Mohammed Alrashadi Platform account email was changed.',
        'eyebrow'         => '// SECURITY ALERT',
        'title'           => 'Account Email Changed',
        'greeting'        => 'Hello ' . $name . ',',
        'intro'           => [
            'The primary email address associated with your Mohammed Alrashadi Platform account was recently updated.',
        ],
        'info_box'        => [
            'rows' => [
                ['label' => 'Previous email', 'value' => $oldEmail],
                ['label' => 'New email',      'value' => $newEmail],
                ['label' => 'Changed at',     'value' => $time],
                ['label' => 'Changed from',   'value' => $ip],
            ],
        ],
        'security_notice' => 'If you did NOT authorize this change, please contact me immediately at ' . $replyTo . ' to secure your account.',
        'footer_note'     => 'A verification link has also been sent to the new email address.',
    ]);
}


