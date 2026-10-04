<?php
/**
 * Shared Email Template Layout Helper
 * Provides a responsive, email-safe HTML shell and a plain-text formatter.
 */

/**
 * Renders the full email HTML and Text.
 * 
 * @param string $title Main title of the email
 * @param string $preheader Hidden preview text for inbox
 * @param string $contentHtml The main HTML content of the email body
 * @param string $contentText The plain text version of the content
 * @param string $footerReason Optional custom footer reason
 * @return array ['html' => string, 'text' => string]
 */
function renderEmail(string $title, string $preheader, string $contentHtml, string $contentText = '', string $footerReason = ''): array
{
    $esc = fn($s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    
    // Fallback logo URL
    $logoUrl = 'https://mohammedalrashadi.com/assets/logo/email-logo.png';
    $localEmailLogo = dirname(__DIR__) . '/assets/logo/email-logo.png';
    if (!file_exists($localEmailLogo)) {
        $logoUrl = 'https://mohammedalrashadi.com/assets/logo/logo.png';
    }

    $year = date('Y');
    
    if (empty($footerReason)) {
        $footerReason = 'You received this email because it concerns your account at mohammedalrashadi.com.';
    }

    // Hidden preheader padding to avoid body text showing in preview
    $spacer = str_repeat('&#160;&#8203;', 60);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{$esc($title)}</title>
  <!--[if mso]>
  <noscript>
    <xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
  </noscript>
  <![endif]-->
  <style type="text/css">
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
    @media only screen and (max-width: 600px) {
      .email-container { width: 100% !important; padding: 16px !important; }
      .email-card { width: 100% !important; border-radius: 8px !important; }
      .email-card-body { padding: 24px 20px !important; }
      .button-cell { display: block !important; width: 100% !important; }
    }
  </style>
</head>
<body style="margin: 0; padding: 0; background-color: #F4F6F8;">

  <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all; font-size: 1px; color: #F4F6F8; line-height: 1px;">
    {$esc($preheader)}{$spacer}
  </div>

  <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #F4F6F8; min-width: 100%;">
    <tr>
      <td align="center" class="email-container" style="padding: 40px 16px;">
        
        <!-- Main Card -->
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="560" class="email-card" style="background-color: #FFFFFF; border: 1px solid #E3E7EC; border-radius: 12px; max-width: 560px; width: 100%;">
          <tr>
            <td class="email-card-body" style="padding: 32px; font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 16px; line-height: 1.6; color: #4B5563;">
              
              <!-- Header -->
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                <tr>
                  <td style="padding-bottom: 24px;">
                    <img src="{$logoUrl}" alt="Mohammed Alrashadi" height="40" style="display: block; height: 40px; width: auto;" border="0">
                  </td>
                </tr>
                <tr>
                  <td style="border-bottom: 1px solid #E3E7EC; height: 1px; line-height: 1px;">&nbsp;</td>
                </tr>
              </table>

              <!-- Content -->
              <div style="padding-top: 32px; color: #111827;">
                {$contentHtml}
              </div>
              
              <!-- Footer -->
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 48px;">
                <tr>
                  <td align="center" style="font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 13px; line-height: 1.5; color: #6B7280;">
                    <p style="margin: 0 0 8px 0;">{$esc($footerReason)}</p>
                    <p style="margin: 0;">&copy; {$year} Mohammed Alrashadi</p>
                  </td>
                </tr>
              </table>

            </td>
          </tr>
        </table>

      </td>
    </tr>
  </table>

</body>
</html>
HTML;

    $text = '';
    if ($preheader) {
        $text .= $preheader . "\n\n";
    }
    if ($contentText) {
        $text .= $contentText . "\n\n";
    }
    $text .= $footerReason . "\n";
    $text .= "© {$year} Mohammed Alrashadi\n";

    return ['html' => $html, 'text' => $text];
}
