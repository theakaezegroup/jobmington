<?php
/**
 * JOBMINGTON - The Courier Service (Mailer)
 * Capabilities: SMTP Relay, HTML Templating, Fail-Safe Delivery
 */

// Prevent direct access
if (!defined('JOBMINGTON')) {
    die('Direct access not permitted: Comm Link Severed');
}

class Mailer {

    private $fromEmail;
    private $fromName;
    private $apiKey;
    private $useSmtp;
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;

    public function __construct() {
        $this->fromEmail    = getenv('MAIL_FROM_ADDRESS') ?: 'noreply@jobmington.com';
        $this->fromName     = getenv('MAIL_FROM_NAME')    ?: SITE_NAME;
        $this->apiKey       = getenv('BREVO_API_KEY')     ?: '';
        $this->useSmtp      = !empty(getenv('MAIL_HOST'));
        $this->smtpHost     = getenv('MAIL_HOST');
        $this->smtpPort     = getenv('MAIL_PORT') ?: 587;
        $this->smtpUsername = getenv('MAIL_USERNAME');
        $this->smtpPassword = getenv('MAIL_PASSWORD');
    }

    /**
     * Send — prefers Brevo HTTP API, falls back to SMTP, then PHP mail()
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("Mailer: invalid address — " . $to);
            return false;
        }

        $htmlBody = $this->buildTemplate($subject, $body);

        if ($this->apiKey !== '') {
            return $this->sendViaBrevoApi($to, $subject, $htmlBody);
        }
        if ($this->useSmtp) {
            return $this->sendSmtp($to, $subject, $htmlBody, $options);
        }
        return $this->sendMail($to, $subject, $htmlBody, $options);
    }

    /**
     * Brevo Transactional Email API (v3) — works over HTTPS, no IP whitelist needed
     */
    private function sendViaBrevoApi(string $to, string $subject, string $html): bool {
        $payload = json_encode([
            'sender'      => ['name' => $this->fromName, 'email' => $this->fromEmail],
            'to'          => [['email' => $to]],
            'subject'     => $subject,
            'htmlContent' => $html,
        ]);

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'content-type: application/json',
                'api-key: ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS     => $payload,
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            error_log("Brevo API error {$status}: {$response} | curl: {$err}");
            return false;
        }
        return true;
    }
    
    /**
     * Standard Channel (PHP mail)
     */
    private function sendMail(string $to, string $subject, string $body, array $options = []): bool {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $this->fromName . ' <' . $this->fromEmail . '>';
        $headers[] = 'Reply-To: ' . ($options['replyTo'] ?? $this->fromEmail);
        $headers[] = 'X-Mailer: Jobmington Relay/1.0';
        
        if (!empty($options['cc'])) $headers[] = 'Cc: ' . $options['cc'];
        if (!empty($options['bcc'])) $headers[] = 'Bcc: ' . $options['bcc'];
        
        $result = @mail($to, $subject, $body, implode("\r\n", $headers));
        
        if (!$result) error_log("Comm Error: Standard Channel Failed for " . $to);
        
        return $result;
    }
    
    /**
     * High-Speed Relay (SMTP)
     */
    private function sendSmtp(string $to, string $subject, string $body, array $options = []): bool {
        try {
            $socket = @fsockopen($this->smtpHost, $this->smtpPort, $errno, $errstr, 10);
            if (!$socket) throw new Exception("Connection Refused: $errstr ($errno)");
            
            $this->smtpRead($socket); // Greeting
            
            $this->smtpWrite($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            $this->smtpRead($socket);
            
            if ($this->smtpPort == 587) {
                $this->smtpWrite($socket, "STARTTLS");
                $this->smtpRead($socket);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->smtpWrite($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
                $this->smtpRead($socket);
            }
            
            $this->smtpWrite($socket, "AUTH LOGIN");
            $this->smtpRead($socket);
            $this->smtpWrite($socket, base64_encode($this->smtpUsername));
            $this->smtpRead($socket);
            $this->smtpWrite($socket, base64_encode($this->smtpPassword));
            $resp = $this->smtpRead($socket);
            
            if (strpos($resp, '235') === false) throw new Exception("Auth Failed");
            
            $this->smtpWrite($socket, "MAIL FROM:<{$this->fromEmail}>");
            $this->smtpRead($socket);
            $this->smtpWrite($socket, "RCPT TO:<{$to}>");
            $this->smtpRead($socket);
            $this->smtpWrite($socket, "DATA");
            $this->smtpRead($socket);
            
            $message = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            $message .= "To: {$to}\r\n";
            $message .= "Subject: {$subject}\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $message .= $body . "\r\n.";
            
            $this->smtpWrite($socket, $message);
            $this->smtpRead($socket);
            $this->smtpWrite($socket, "QUIT");
            fclose($socket);
            return true;
            
        } catch (Exception $e) {
            error_log("SMTP Relay Failed: " . $e->getMessage() . " -> Switching to Standard Channel.");
            return $this->sendMail($to, $subject, $body, $options);
        }
    }
    
    private function smtpWrite($s, $d) { fwrite($s, $d . "\r\n"); }
    private function smtpRead($s) { 
        $r = ''; while($l = fgets($s, 515)) { $r .= $l; if(substr($l, 3, 1) == ' ') break; } return $r; 
    }
    
    /**
     * Build branded email template
     */
    private function buildTemplate(string $subject, string $content): string {
        $logo    = 'https://jobmington.com/assets/images/badge.png';
        $year    = date('Y');
        $siteUrl = 'https://jobmington.com';

        return <<<HTML
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{$subject}</title>
  <style>
    @font-face {
      font-family: 'Futura Cyrillic Demi';
      src: url('https://jobmington.com/assets/fonts/FuturaCyrillicDemi.ttf') format('truetype');
      font-weight: 700;
      font-style: normal;
    }
    @font-face {
      font-family: 'Futura Cyrillic Book';
      src: url('https://jobmington.com/assets/fonts/FuturaCyrillicBook.ttf') format('truetype');
      font-weight: 400;
      font-style: normal;
    }
  </style>
</head>
<body style="margin:0;padding:0;background:#eef2f7;-webkit-font-smoothing:antialiased;">

<!-- Pre-header preview text -->
<div style="display:none;max-height:0;overflow:hidden;font-size:1px;color:#eef2f7;">Jobmington &mdash; Africa's career platform&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2f7;padding:40px 16px;">
<tr><td align="center">

  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">

    <!-- Brand strip above card -->
    <tr>
      <td align="center" style="padding-bottom:20px;">
        <table cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="padding-right:10px;vertical-align:middle;">
              <img src="{$logo}" alt="" width="28" height="28" style="display:block;border-radius:6px;">
            </td>
            <td style="vertical-align:middle;">
              <span style="font-family:Futura,Century Gothic,Trebuchet MS,Helvetica,Arial,sans-serif;font-size:16px;font-weight:700;color:#0640a3;letter-spacing:-0.01em;">Jobmington</span>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Main card -->
    <tr>
      <td style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 2px 16px rgba(6,20,38,.07),0 0 0 1px rgba(6,20,38,.05);">

        <!-- Hero banner -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="background:linear-gradient(135deg,#0533a0 0%,#0a5fcc 100%);padding:36px 44px 32px;border-radius:16px 16px 0 0;">
              <p style="margin:0 0 4px;font-family:'Futura Cyrillic Book','Futura Cyrillic Demi','Century Gothic','Trebuchet MS',Helvetica,Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.55);">Jobmington</p>
              <p style="margin:0;font-family:'Futura Cyrillic Book','Futura Cyrillic Demi','Century Gothic','Trebuchet MS',Helvetica,Arial,sans-serif;font-size:13px;color:rgba(255,255,255,.7);">Africa's career platform</p>
            </td>
          </tr>
          <!-- Orange accent bar -->
          <tr><td style="height:3px;background:linear-gradient(90deg,#f59f22,#f7b944);font-size:0;">&zwnj;</td></tr>
        </table>

        <!-- Body content -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="padding:40px 44px 36px;font-family:'Futura Cyrillic Book','Futura Cyrillic Demi','Century Gothic','Trebuchet MS',Helvetica,Arial,sans-serif;font-size:15px;line-height:1.75;color:#374151;">
              {$content}
            </td>
          </tr>
        </table>

        <!-- Divider -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr><td style="padding:0 44px;"><div style="height:1px;background:#e5e7eb;font-size:0;">&zwnj;</div></td></tr>
        </table>

        <!-- Footer inside card -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="padding:24px 44px;text-align:center;">
              <p style="margin:0 0 8px;font-family:'Futura Cyrillic Book','Futura Cyrillic Demi','Century Gothic','Trebuchet MS',Helvetica,Arial,sans-serif;font-size:12px;color:#9ca3af;">
                <a href="{$siteUrl}" style="color:#0640a3;text-decoration:none;font-weight:600;">jobmington.com</a>
                &nbsp;&bull;&nbsp;
                <a href="{$siteUrl}/privacy-policy.php" style="color:#9ca3af;text-decoration:none;">Privacy</a>
                &nbsp;&bull;&nbsp;
                <a href="{$siteUrl}/terms-of-service.php" style="color:#9ca3af;text-decoration:none;">Terms</a>
              </p>
              <p style="margin:0;font-family:'Futura Cyrillic Book','Futura Cyrillic Demi','Century Gothic','Trebuchet MS',Helvetica,Arial,sans-serif;font-size:11px;color:#d1d5db;">
                &copy; {$year} Jobmington &mdash; Simple hiring for African talent.
              </p>
            </td>
          </tr>
        </table>

      </td>
    </tr>
    <!-- /card -->

  </table>

</td></tr>
</table>

</body>
</html>
HTML;
    }

    // --- PREDEFINED MESSAGES ---

    public static function sendVerificationEmail(string $email, string $name, string $token): bool {
        $firstName = explode(' ', trim($name))[0];
        $url = SITE_URL . '/auth/verify-email.php?token=' . rawurlencode($token);
        $content = "
            <h2 style='font-weight:700;color:#06142a;margin:0 0 12px;font-size:22px;line-height:1.2;'>Verify your email, {$firstName}.</h2>
            <p style='color:#475569;margin:0 0 28px;line-height:1.75;'>You are one step away. Click the button below to confirm your email address and unlock full access to Jobmington &mdash; job listings, AI tools, and more.</p>
            <table cellpadding='0' cellspacing='0' style='margin:0 0 28px;'>
              <tr><td style='border-radius:8px;background:#0640a3;'>
                <a href='{$url}' style='display:inline-block;padding:14px 32px;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;border-radius:8px;'>Verify my email</a>
              </td></tr>
            </table>
            <p style='color:#94a3b8;font-size:13px;margin:0 0 6px;'>If you didn't create a Jobmington account, you can safely ignore this email.</p>
            <p style='color:#94a3b8;font-size:12px;margin:0;'>Or copy this link into your browser:<br><a href='{$url}' style='color:#0640a3;word-break:break-all;'>{$url}</a></p>
        ";
        return (new self())->send($email, 'Verify your Jobmington email', $content);
    }

    public static function sendWelcome(string $email, string $name): bool {
        $firstName = explode(' ', trim($name))[0];
        $url = SITE_URL . '/jobs/';
        $content = "
            <h2 style='font-weight:700;color:#06142a;margin:0 0 12px;font-size:22px;line-height:1.2;'>Welcome to Jobmington, {$firstName}.</h2>
            <p style='color:#475569;margin:0 0 24px;line-height:1.7;'>Your account is verified. Start browsing open roles, use AI tools to sharpen your CV, and apply to jobs that match your skills.</p>
            <table cellpadding='0' cellspacing='0' style='margin:0 0 24px;'>
              <tr><td style='border-radius:8px;background:#0640a3;'>
                <a href='{$url}' style='display:inline-block;padding:14px 32px;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;border-radius:8px;'>Browse open jobs</a>
              </td></tr>
            </table>
        ";
        return (new self())->send($email, 'Welcome to Jobmington', $content);
    }

    public static function sendPasswordReset(string $email, string $name, string $link): bool {
        $firstName = explode(' ', trim($name))[0];
        $content = "
            <h2 style='font-weight:700;color:#06142a;margin:0 0 12px;font-size:22px;line-height:1.2;'>Reset your password, {$firstName}.</h2>
            <p style='color:#475569;margin:0 0 24px;line-height:1.7;'>We received a request to reset your Jobmington password. Click below to choose a new one. This link expires in 60 minutes.</p>
            <table cellpadding='0' cellspacing='0' style='margin:0 0 24px;'>
              <tr><td style='border-radius:8px;background:#0640a3;'>
                <a href='{$link}' style='display:inline-block;padding:14px 32px;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;border-radius:8px;'>Reset password</a>
              </td></tr>
            </table>
            <p style='color:#94a3b8;font-size:13px;margin:0;'>If you didn't request this, you can safely ignore this email. Your password won't change.</p>
        ";
        return (new self())->send($email, 'Reset your Jobmington password', $content);
    }
}

// Global Helper
function sendMail(string $to, string $subj, string $body, array $opts = []): bool {
    return (new Mailer())->send($to, $subj, $body, $opts);
}
?>
