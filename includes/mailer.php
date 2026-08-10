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
        // The transparent mark, not the badge: a blue tile on a blue header
        // reads as a mismatched square, the same problem the mobile header had.
        $mark    = 'https://jobmington.com/assets/images/badge-mark.png?v=logo-7';
        $year    = date('Y');
        $siteUrl = 'https://jobmington.com';

        $ff = "font-family:'Futura Cyrillic Book','Century Gothic','Trebuchet MS',Helvetica,Arial,sans-serif;";
        $ffd = "font-family:'Futura Cyrillic Demi','Century Gothic','Trebuchet MS',Helvetica,Arial,sans-serif;";

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
      font-weight: 700; font-style: normal;
    }
    @font-face {
      font-family: 'Futura Cyrillic Book';
      src: url('https://jobmington.com/assets/fonts/FuturaCyrillicBook.ttf') format('truetype');
      font-weight: 400; font-style: normal;
    }
  </style>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;-webkit-font-smoothing:antialiased;">

<div style="display:none;max-height:0;overflow:hidden;font-size:1px;color:#f4f4f4;">Jobmington &mdash; Africa's career platform&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f4;padding:48px 16px;">
<tr><td align="center">

  <!-- Card — sharp corners, no border-radius -->
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border-radius:0;">

    <!-- Brand-blue header, matching the site -->
    <tr>
      <td style="background:#0640a3;padding:24px 28px;">
        <table cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="padding-right:12px;vertical-align:middle;" width="68">
              <img src="{$mark}" alt="Jobmington" width="56" height="56" style="display:block;border:0;">
            </td>
            <td style="vertical-align:middle;">
              <span style="{$ffd}font-size:18px;font-weight:700;color:#ffffff;letter-spacing:-0.01em;white-space:nowrap;">Jobmington</span><br>
              <span style="{$ff}font-size:10px;color:#d6e4fa;letter-spacing:0.04em;text-transform:uppercase;white-space:nowrap;">Simple hiring for African talent</span>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- Orange accent line -->
    <tr><td style="height:4px;background:#f59f22;font-size:0;line-height:0;">&zwnj;</td></tr>

    <!-- Body -->
    <tr>
      <td style="padding:44px 40px 36px;{$ff}font-size:15px;line-height:1.8;color:#374151;">
        {$content}
      </td>
    </tr>

    <!-- Divider -->
    <tr>
      <td style="padding:0 40px;">
        <div style="height:1px;background:#e5e7eb;font-size:0;">&zwnj;</div>
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="padding:22px 40px;text-align:center;background:#f7f9fc;">
        <p style="margin:0 0 6px;{$ff}font-size:12px;color:#9ca3af;">
          <a href="{$siteUrl}" style="color:#0640a3;text-decoration:none;font-weight:600;">jobmington.com</a>
          &nbsp;&middot;&nbsp;
          <a href="{$siteUrl}/privacy-policy" style="color:#9ca3af;text-decoration:none;">Privacy</a>
          &nbsp;&middot;&nbsp;
          <a href="{$siteUrl}/terms-of-service" style="color:#9ca3af;text-decoration:none;">Terms</a>
        </p>
        <p style="margin:0;{$ff}font-size:11px;color:#c9d0da;">&copy; {$year} Jobmington. Simple hiring for African talent.</p>
      </td>
    </tr>

  </table>
  <!-- /card -->

</td></tr>
</table>

</body>
</html>
HTML;
    }

    // --- PREDEFINED MESSAGES ---

    public static function sendVerificationEmail(string $email, string $name, string $token): bool {
        $firstName = explode(' ', trim($name))[0];
        $url = SITE_URL . '/auth/verify-email?token=' . rawurlencode($token);
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
        $url = SITE_URL . '/jobs';
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

    /* ── Status label helper ───────────────────────────────────── */
    private static function statusLabel(string $status): string {
        return [
            'pending'     => 'Received',
            'reviewed'    => 'Under Review',
            'shortlisted' => 'Shortlisted',
            'interview'   => 'Interview Invited',
            'rejected'    => 'Unsuccessful',
            'hired'       => 'Hired',
        ][$status] ?? ucfirst($status);
    }

    /* ── 1. Application confirmation — seeker ──────────────────── */
    public static function sendApplicationConfirmation(string $email, string $name, string $jobTitle, string $company, string $jobUrl): bool {
        $firstName = explode(' ', trim($name))[0];
        $content = "
            <h2 style='font-weight:700;color:#06142a;margin:0 0 12px;font-size:22px;line-height:1.2;'>Application received, {$firstName}.</h2>
            <p style='color:#475569;margin:0 0 20px;line-height:1.75;'>Your application for <strong style='color:#06142a;'>{$jobTitle}</strong> at <strong style='color:#06142a;'>{$company}</strong> has been submitted. We'll let you know if there are any updates.</p>
            <table cellpadding='0' cellspacing='0' style='margin:0 0 24px;'>
              <tr><td style='background:#0640a3;border-radius:8px;'>
                <a href='{$jobUrl}' style='display:inline-block;padding:13px 28px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;border-radius:8px;'>View job</a>
              </td></tr>
            </table>
            <p style='color:#94a3b8;font-size:13px;margin:0;'>Track all your applications from your <a href='https://jobmington.com/seeker/applications' style='color:#0640a3;'>applications dashboard</a>.</p>
        ";
        return (new self())->send($email, "Application submitted — {$jobTitle}", $content);
    }

    /* ── 2. Application status update — seeker ─────────────────── */
    public static function sendApplicationStatusUpdate(string $email, string $name, string $jobTitle, string $company, string $status): bool {
        $firstName   = explode(' ', trim($name))[0];
        $label       = self::statusLabel($status);
        $isPositive  = in_array($status, ['shortlisted', 'interview', 'hired']);
        $isRejected  = $status === 'rejected';
        $accentColor = $isPositive ? '#059669' : ($isRejected ? '#6b7280' : '#0640a3');
        $url         = 'https://jobmington.com/seeker/applications';

        $statusNote = match($status) {
            'shortlisted' => 'Great news — the employer has shortlisted your profile. Stay ready.',
            'interview'   => 'The employer wants to interview you. Check your dashboard for next steps.',
            'hired'       => 'Congratulations! You have been hired. Best of luck in your new role.',
            'rejected'    => 'The employer has moved forward with other candidates. Keep applying — the right role is out there.',
            'reviewed'    => 'The employer is reviewing your application. We will update you as things progress.',
            default       => 'Your application status has been updated.',
        };

        $content = "
            <h2 style='font-weight:700;color:#06142a;margin:0 0 12px;font-size:22px;line-height:1.2;'>Application update, {$firstName}.</h2>
            <p style='color:#475569;margin:0 0 20px;line-height:1.75;'>Your application for <strong style='color:#06142a;'>{$jobTitle}</strong> at <strong style='color:#06142a;'>{$company}</strong> has been updated.</p>
            <table cellpadding='0' cellspacing='0' style='margin:0 0 20px;'>
              <tr>
                <td style='background:{$accentColor};padding:12px 20px;'>
                  <span style='color:#ffffff;font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;'>{$label}</span>
                </td>
              </tr>
            </table>
            <p style='color:#475569;margin:0 0 24px;line-height:1.75;'>{$statusNote}</p>
            <table cellpadding='0' cellspacing='0' style='margin:0 0 16px;'>
              <tr><td style='background:#0640a3;border-radius:8px;'>
                <a href='{$url}' style='display:inline-block;padding:13px 28px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;border-radius:8px;'>View applications</a>
              </td></tr>
            </table>
        ";
        return (new self())->send($email, "Application update — {$jobTitle}", $content);
    }

    /* ── 3. Job match alert — seeker ───────────────────────────── */

    /**
     * Build the subject + inner content for a job-match alert without sending.
     * Used by both sendJobMatchAlert() and the async queue (cron/send_job_match_alerts.php).
     *
     * @return array{subject:string,content:string}
     */
    public static function composeJobMatchAlert(string $name, int $matchCount, array $jobs = []): array {
        $firstName = explode(' ', trim($name))[0] ?: 'there';
        $url       = 'https://jobmington.com/jobs';
        $plural    = $matchCount === 1 ? 'role matches' : 'roles match';
        $jobsList  = '';
        foreach (array_slice($jobs, 0, 3) as $job) {
            $title   = htmlspecialchars((string) ($job['title'] ?? 'Role'), ENT_QUOTES);
            $company = htmlspecialchars((string) ($job['company'] ?? ''), ENT_QUOTES);
            $jobsList .= "<p style='margin:0 0 6px;color:#06142a;font-size:14px;'>&rsaquo; <strong>{$title}</strong> &mdash; {$company}</p>";
        }
        $content = "
            <h2 style='font-weight:700;color:#06142a;margin:0 0 12px;font-size:22px;line-height:1.2;'>{$matchCount} new {$plural} your profile, {$firstName}.</h2>
            <p style='color:#475569;margin:0 0 20px;line-height:1.75;'>Jobmington found roles that align with your skills, experience, and location. Here are a few to get you started:</p>
            <div style='border-left:3px solid #f59f22;padding-left:16px;margin:0 0 24px;'>{$jobsList}</div>
            <table cellpadding='0' cellspacing='0' style='margin:0 0 16px;'>
              <tr><td style='background:#0640a3;border-radius:8px;'>
                <a href='{$url}' style='display:inline-block;padding:13px 28px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;border-radius:8px;'>Browse all matches</a>
              </td></tr>
            </table>
        ";
        return ['subject' => "{$matchCount} new job {$plural} your profile", 'content' => $content];
    }

    public static function sendJobMatchAlert(string $email, string $name, int $matchCount, array $jobs = []): bool {
        $c = self::composeJobMatchAlert($name, $matchCount, $jobs);
        return (new self())->send($email, $c['subject'], $c['content']);
    }

    /* ── 4. Payment receipt — seeker ───────────────────────────── */
    public static function sendPaymentReceipt(string $email, string $name, string $planName, string $amount): bool {
        $firstName = explode(' ', trim($name))[0];
        $url       = 'https://jobmington.com/seeker/dashboard';
        $date      = date('d M Y');
        $content = "
            <h2 style='font-weight:700;color:#06142a;margin:0 0 12px;font-size:22px;line-height:1.2;'>Payment confirmed, {$firstName}.</h2>
            <p style='color:#475569;margin:0 0 20px;line-height:1.75;'>Your purchase was successful. Here's your receipt:</p>
            <table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid #e5e7eb;margin:0 0 24px;'>
              <tr style='background:#f8fafc;'>
                <td style='padding:12px 16px;font-size:13px;color:#64748b;'>Plan</td>
                <td style='padding:12px 16px;font-size:13px;color:#06142a;font-weight:700;text-align:right;'>{$planName}</td>
              </tr>
              <tr>
                <td style='padding:12px 16px;font-size:13px;color:#64748b;border-top:1px solid #e5e7eb;'>Amount</td>
                <td style='padding:12px 16px;font-size:13px;color:#06142a;font-weight:700;text-align:right;border-top:1px solid #e5e7eb;'>{$amount}</td>
              </tr>
              <tr style='background:#f8fafc;'>
                <td style='padding:12px 16px;font-size:13px;color:#64748b;border-top:1px solid #e5e7eb;'>Date</td>
                <td style='padding:12px 16px;font-size:13px;color:#06142a;font-weight:700;text-align:right;border-top:1px solid #e5e7eb;'>{$date}</td>
              </tr>
            </table>
            <p style='color:#475569;margin:0 0 24px;line-height:1.75;'>All AI tools and Premium features are now unlocked on your account.</p>
            <table cellpadding='0' cellspacing='0' style='margin:0 0 16px;'>
              <tr><td style='background:#0640a3;border-radius:8px;'>
                <a href='{$url}' style='display:inline-block;padding:13px 28px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;border-radius:8px;'>Go to dashboard</a>
              </td></tr>
            </table>
        ";
        return (new self())->send($email, "Payment confirmed — {$planName}", $content);
    }

    /* ── 5. New application alert — employer ───────────────────── */
    public static function sendNewApplicationAlert(string $email, string $companyName, string $jobTitle, string $applicantName, string $applicationsUrl): bool {
        $content = "
            <h2 style='font-weight:700;color:#06142a;margin:0 0 12px;font-size:22px;line-height:1.2;'>New application received.</h2>
            <p style='color:#475569;margin:0 0 20px;line-height:1.75;'><strong style='color:#06142a;'>{$applicantName}</strong> has applied for the <strong style='color:#06142a;'>{$jobTitle}</strong> role at {$companyName}.</p>
            <table cellpadding='0' cellspacing='0' style='margin:0 0 24px;'>
              <tr><td style='background:#0640a3;border-radius:8px;'>
                <a href='{$applicationsUrl}' style='display:inline-block;padding:13px 28px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;border-radius:8px;'>Review application</a>
              </td></tr>
            </table>
            <p style='color:#94a3b8;font-size:13px;margin:0;'>Manage all applications from your <a href='https://jobmington.com/employer/applications' style='color:#0640a3;'>employer dashboard</a>.</p>
        ";
        return (new self())->send($email, "New application — {$jobTitle}", $content);
    }

    /* ── 6. Job posting confirmed — employer ───────────────────── */
    public static function sendJobPostingConfirmed(string $email, string $companyName, string $jobTitle, string $jobUrl): bool {
        $content = "
            <h2 style='font-weight:700;color:#06142a;margin:0 0 12px;font-size:22px;line-height:1.2;'>Your role is live.</h2>
            <p style='color:#475569;margin:0 0 20px;line-height:1.75;'>The <strong style='color:#06142a;'>{$jobTitle}</strong> listing for <strong style='color:#06142a;'>{$companyName}</strong> is now live on Jobmington and visible to job seekers.</p>
            <table cellpadding='0' cellspacing='0' style='margin:0 0 24px;'>
              <tr><td style='background:#0640a3;border-radius:8px;'>
                <a href='{$jobUrl}' style='display:inline-block;padding:13px 28px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;border-radius:8px;'>View listing</a>
              </td></tr>
            </table>
            <p style='color:#94a3b8;font-size:13px;margin:0;'>Track applications from your <a href='https://jobmington.com/employer/applications' style='color:#0640a3;'>applications dashboard</a>.</p>
        ";
        return (new self())->send($email, "Your job is live — {$jobTitle}", $content);
    }

    /* ── 6b. Event registration confirmed — attendee ────────────── */
    public static function sendEventRegistration(string $email, string $name, array $event, string $eventUrl, string $calendarUrl = ''): bool {
        [$subject, $content] = self::composeEventRegistration($name, $event, $eventUrl, $calendarUrl);
        return (new self())->send($email, $subject, $content);
    }

    /** Split out so the message can be built and inspected without sending. */
    public static function composeEventRegistration(string $name, array $event, string $eventUrl, string $calendarUrl = ''): array {
        $firstName = explode(' ', trim($name))[0] ?: 'there';
        $start     = strtotime($event['starts_at']);
        $when      = date('l, j F Y', $start);
        $time      = date('g:i A', $start) . ($event['timezone'] ? ' ' . $event['timezone'] : '');
        $where     = !empty($event['is_online']) ? 'Online' : ($event['location'] ?: 'In person');
        $title     = htmlspecialchars((string) $event['title'], ENT_QUOTES, 'UTF-8');

        /*
         * Every style attribute below is delimited with double quotes.
         *
         * The font stack contains single quotes, so a single-quoted style
         * attribute terminates at the first one and every declaration after it
         * is thrown away. Buttons lost their padding, colour and weight and
         * rendered as bare washed-out links.
         */
        $ff  = "font-family:'Futura Cyrillic Book','Century Gothic','Trebuchet MS',Helvetica,Arial,sans-serif;";
        $ffd = "font-family:'Futura Cyrillic Demi','Century Gothic','Trebuchet MS',Helvetica,Arial,sans-serif;";

        // The poster carries the event better than any wording, so it leads.
        // Stored paths keep a legacy /jobmington prefix production does not
        // serve, and a mail client will not follow a redirect for an image.
        $poster = '';
        $cover  = trim((string) ($event['cover_image'] ?? ''));
        if ($cover !== '') {
            if (!preg_match('#^https?://#i', $cover)) {
                $cover = SITE_URL . '/' . ltrim(preg_replace('#^/?jobmington/#', '', ltrim($cover, '/')), '/');
            }
            $coverSafe = htmlspecialchars($cover, ENT_QUOTES, 'UTF-8');
            $poster = '<tr><td style="padding:0 0 22px;">'
                    . '<img src="' . $coverSafe . '" alt="' . $title . '" width="520" '
                    . 'style="display:block;width:100%;max-width:520px;height:auto;border:0;outline:none;text-decoration:none;">'
                    . '</td></tr>';
        }

        // PNG rather than inline SVG: Gmail strips <svg> and Outlook will not
        // render it, so a vector icon would simply vanish for most readers.
        $iconBase = SITE_URL . '/assets/images/email/';
        $row = static function (string $icon, string $label, string $value) use ($ff, $ffd, $iconBase): string {
            return '<tr>'
                 . '<td width="30" style="padding:0 12px 14px 0;vertical-align:top;">'
                 .   '<img src="' . $iconBase . $icon . '.png?v=5" alt="" width="20" height="20" style="display:block;border:0;">'
                 . '</td>'
                 . '<td style="padding:0 0 14px;vertical-align:top;">'
                 .   '<div style="' . $ff . 'font-size:11px;color:#94a3b8;letter-spacing:0.05em;text-transform:uppercase;padding-bottom:3px;">' . $label . '</div>'
                 .   '<div style="' . $ffd . 'font-size:15px;color:#06142a;font-weight:700;line-height:1.45;">' . $value . '</div>'
                 . '</td>'
                 . '</tr>';
        };

        $details = $row('when', 'When', htmlspecialchars($when, ENT_QUOTES, 'UTF-8')
                        . '<br><span style="' . $ff . 'font-weight:400;font-size:14px;color:#475569;">'
                        . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . '</span>')
                 . $row('where', 'Where', htmlspecialchars($where, ENT_QUOTES, 'UTF-8'));
        if (!empty($event['host_name'])) {
            $details .= $row('host', 'Host', htmlspecialchars((string) $event['host_name'], ENT_QUOTES, 'UTF-8'));
        }

        $joinUrl = (!empty($event['is_online']) && !empty($event['meeting_url']))
            ? htmlspecialchars((string) $event['meeting_url'], ENT_QUOTES, 'UTF-8') : '';
        $primaryUrl   = $joinUrl !== '' ? $joinUrl : htmlspecialchars($eventUrl, ENT_QUOTES, 'UTF-8');
        $primaryLabel = $joinUrl !== '' ? 'Join the session' : 'View event';

        $calendarBtn = $calendarUrl
            ? '<td width="18" style="width:18px;"><div style="width:18px;height:1px;line-height:1px;font-size:1px;">&nbsp;</div></td>'
              . '<td style="background:#eaf1fd;border:1px solid #cfe0f8;border-radius:8px;">'
              . '<a href="' . htmlspecialchars($calendarUrl, ENT_QUOTES, 'UTF-8') . '" '
              . 'style="' . $ffd . 'display:inline-block;padding:12px 18px;color:#0640a3;font-size:13px;'
              . 'font-weight:700;text-decoration:none;border-radius:8px;white-space:nowrap;">Add to calendar</a></td>'
            : '';

        $note = $joinUrl !== ''
            ? 'We will remind you the day before and again an hour before it starts.'
            : 'The joining details follow before it starts.';

        $content = '
            <h2 style="' . $ffd . 'font-weight:700;color:#06142a;margin:0 0 6px;font-size:22px;line-height:1.2;">You are registered, ' . $firstName . '.</h2>
            <p style="' . $ff . 'color:#475569;margin:0 0 22px;line-height:1.7;">Your place at <strong style="color:#06142a;">' . $title . '</strong> is confirmed.</p>

            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 4px;">
              ' . $poster . '
              <tr><td style="padding:0 0 22px;">
                <table cellpadding="0" cellspacing="0" border="0" width="100%" style="border:1px solid #e4eaf3;border-radius:8px;">
                  <tr><td style="padding:18px 20px;">
                    <table cellpadding="0" cellspacing="0" border="0">' . $details . '</table>
                  </td></tr>
                </table>
              </td></tr>
            </table>

            <p style="' . $ff . 'color:#475569;margin:0 0 22px;line-height:1.7;">' . $note . '</p>

            <table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
              <tr>
                <td style="background:#0640a3;border-radius:8px;">
                  <a href="' . $primaryUrl . '" style="' . $ffd . 'display:inline-block;padding:12px 20px;color:#ffffff;font-size:13px;font-weight:700;text-decoration:none;border-radius:8px;white-space:nowrap;">' . $primaryLabel . '</a>
                </td>
                ' . $calendarBtn . '
              </tr>
            </table>

            <p style="' . $ff . 'color:#94a3b8;font-size:13px;margin:0;">Cannot make it after all? Ignore this email, there is nothing to cancel.</p>
        ';

        return ["You are registered - {$event['title']}", $content];
    }

    /* ── 6c. Event reminder — 24 hours and 1 hour before ────────── */
    public static function sendEventReminder(string $email, string $name, array $event, string $eventUrl, string $window, string $calendarUrl = ''): bool {
        [$subject, $content] = self::composeEventReminder($name, $event, $eventUrl, $window, $calendarUrl);
        return (new self())->send($email, $subject, $content);
    }

    /** $window is '24h' or '1h'. Split out so it can be asserted without sending. */
    public static function composeEventReminder(string $name, array $event, string $eventUrl, string $window, string $calendarUrl = ''): array {
        $firstName = explode(' ', trim($name))[0] ?: 'there';
        $start     = strtotime($event['starts_at']);
        $time      = date('g:i A', $start) . ($event['timezone'] ? ' ' . $event['timezone'] : '');
        $title     = htmlspecialchars((string) $event['title'], ENT_QUOTES, 'UTF-8');
        $soon      = $window === '1h';

        // Double quotes throughout: the font stack contains single quotes and
        // would otherwise terminate the attribute and drop every declaration.
        $ff  = "font-family:'Futura Cyrillic Book','Century Gothic','Trebuchet MS',Helvetica,Arial,sans-serif;";
        $ffd = "font-family:'Futura Cyrillic Demi','Century Gothic','Trebuchet MS',Helvetica,Arial,sans-serif;";

        $poster = '';
        $cover  = trim((string) ($event['cover_image'] ?? ''));
        if ($cover !== '' && !$soon) {
            // The hour-before note stays short and link-first; the poster is for
            // the day-before mail, which is the one that has to sell attendance.
            if (!preg_match('#^https?://#i', $cover)) {
                $cover = SITE_URL . '/' . ltrim(preg_replace('#^/?jobmington/#', '', ltrim($cover, '/')), '/');
            }
            $poster = '<tr><td style="padding:0 0 22px;"><img src="' . htmlspecialchars($cover, ENT_QUOTES, 'UTF-8')
                    . '" alt="' . $title . '" width="520" style="display:block;width:100%;max-width:520px;height:auto;border:0;"></td></tr>';
        }

        $joinUrl = (!empty($event['is_online']) && !empty($event['meeting_url']))
            ? htmlspecialchars((string) $event['meeting_url'], ENT_QUOTES, 'UTF-8') : '';
        $primaryUrl   = $joinUrl !== '' ? $joinUrl : htmlspecialchars($eventUrl, ENT_QUOTES, 'UTF-8');
        $primaryLabel = $joinUrl !== '' ? 'Join the session' : 'View event';

        $heading = $soon
            ? 'Starting in about an hour, ' . $firstName . '.'
            : 'Tomorrow, ' . $firstName . '.';
        $lead = $soon
            ? '<strong style="color:#06142a;">' . $title . '</strong> starts at ' . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . '.'
            : '<strong style="color:#06142a;">' . $title . '</strong> runs tomorrow at ' . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . '.';

        $calendarBtn = (!$soon && $calendarUrl)
            ? '<td width="18" style="width:18px;"><div style="width:18px;height:1px;line-height:1px;font-size:1px;">&nbsp;</div></td>'
              . '<td style="background:#eaf1fd;border:1px solid #cfe0f8;border-radius:8px;">'
              . '<a href="' . htmlspecialchars($calendarUrl, ENT_QUOTES, 'UTF-8') . '" style="' . $ffd
              . 'display:inline-block;padding:12px 18px;color:#0640a3;font-size:13px;font-weight:700;'
              . 'text-decoration:none;border-radius:8px;white-space:nowrap;">Add to calendar</a></td>'
            : '';

        $content = '
            <h2 style="' . $ffd . 'font-weight:700;color:#06142a;margin:0 0 6px;font-size:22px;line-height:1.2;">' . $heading . '</h2>
            <p style="' . $ff . 'color:#475569;margin:0 0 22px;line-height:1.7;">' . $lead . '</p>

            <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 4px;">' . $poster . '</table>

            <table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
              <tr>
                <td style="background:#0640a3;border-radius:8px;">
                  <a href="' . $primaryUrl . '" style="' . $ffd . 'display:inline-block;padding:12px 20px;color:#ffffff;font-size:13px;font-weight:700;text-decoration:none;border-radius:8px;white-space:nowrap;">' . $primaryLabel . '</a>
                </td>
                ' . $calendarBtn . '
              </tr>
            </table>

            <p style="' . $ff . 'color:#94a3b8;font-size:13px;margin:0;">You are on the list, so there is nothing else to do.</p>
        ';

        $subject = $soon
            ? "Starting soon - {$event['title']}"
            : "Tomorrow - {$event['title']}";

        return [$subject, $content];
    }

    /* ── 7. Password changed — account security ─────────────────── */
    public static function sendPasswordChanged(string $email, string $name): bool {
        $firstName = explode(' ', trim($name))[0];
        $time      = date('d M Y, H:i') . ' (UTC)';
        $content = "
            <h2 style='font-weight:700;color:#06142a;margin:0 0 12px;font-size:22px;line-height:1.2;'>Password changed, {$firstName}.</h2>
            <p style='color:#475569;margin:0 0 20px;line-height:1.75;'>Your Jobmington password was successfully changed on <strong style='color:#06142a;'>{$time}</strong>.</p>
            <p style='color:#475569;margin:0 0 24px;line-height:1.75;'>If you made this change, no action is needed. If you did not change your password, please <a href='https://jobmington.com/auth/forgot-password' style='color:#0640a3;font-weight:700;'>reset it immediately</a> and contact us.</p>
            <table cellpadding='0' cellspacing='0' style='margin:0 0 16px;'>
              <tr><td style='background:#0640a3;border-radius:8px;'>
                <a href='https://jobmington.com/auth/login' style='display:inline-block;padding:13px 28px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;border-radius:8px;'>Sign in to your account</a>
              </td></tr>
            </table>
        ";
        return (new self())->send($email, 'Your password was changed', $content);
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

    /* ── Unsubscribe / suppression (marketing campaigns only) ───── */

    /** Signing secret for unsubscribe tokens. */
    private static function unsubscribeSecret(): string {
        return getenv('APP_KEY') ?: 'jm-default-app-key-change-me';
    }

    /** Deterministic token tying an unsubscribe link to one email address. */
    public static function unsubscribeToken(string $email): string {
        return hash_hmac('sha256', strtolower(trim($email)), self::unsubscribeSecret());
    }

    public static function verifyUnsubscribeToken(string $email, string $token): bool {
        return hash_equals(self::unsubscribeToken($email), $token);
    }

    /** Full, tokenised unsubscribe URL for an email address. */
    public static function unsubscribeUrl(string $email): string {
        return SITE_URL . '/unsubscribe?e=' . urlencode($email) . '&t=' . self::unsubscribeToken($email);
    }

    /** True if the address is on the suppression list. */
    public static function isSuppressed(string $email): bool {
        try {
            $stmt = db()->prepare("SELECT 1 FROM email_unsubscribes WHERE email = ? LIMIT 1");
            $stmt->execute([strtolower(trim($email))]);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('isSuppressed error: ' . $e->getMessage());
            return false;
        }
    }
}

// Global Helper
function sendMail(string $to, string $subj, string $body, array $opts = []): bool {
    return (new Mailer())->send($to, $subj, $body, $opts);
}
?>
