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
    private $useSmtp;
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    
    public function __construct() {
        // Load credentials from ENV or constants
        $this->fromEmail = getenv('MAIL_FROM_ADDRESS') ?: 'system@jobmington.com';
        $this->fromName = getenv('MAIL_FROM_NAME') ?: SITE_NAME;
        $this->useSmtp = !empty(getenv('MAIL_HOST'));
        $this->smtpHost = getenv('MAIL_HOST');
        $this->smtpPort = getenv('MAIL_PORT') ?: 587;
        $this->smtpUsername = getenv('MAIL_USERNAME');
        $this->smtpPassword = getenv('MAIL_PASSWORD');
    }
    
    /**
     * Send Message
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("Comm Error: Invalid Target Frequency - " . $to);
            return false;
        }
        
        $htmlBody = $this->buildTemplate($subject, $body);
        
        // Try High-Speed Relay (SMTP) first, then fallback to Standard Channel (PHP Mail)
        if ($this->useSmtp) {
            return $this->sendSmtp($to, $subject, $htmlBody, $options);
        } else {
            return $this->sendMail($to, $subject, $htmlBody, $options);
        }
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
     * Construct Holographic Template
     */
    private function buildTemplate(string $subject, string $content): string {
        $logo = SITE_URL . '/assets/images/logo.png'; // Ensure you have a logo here
        
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { background: #0f172a; color: #e2e8f0; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; }
                .wrapper { max-width: 600px; margin: 0 auto; background: #1e293b; border: 1px solid #334155; }
                .header { background: #0f172a; padding: 20px; text-align: center; border-bottom: 2px solid #a855f7; }
                .logo { height: 40px; }
                .body { padding: 30px; line-height: 1.6; }
                .btn { display: inline-block; background: #a855f7; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 20px 0; }
                .footer { background: #0f172a; padding: 20px; text-align: center; font-size: 12px; color: #64748b; }
                a { color: #a855f7; }
                strong { color: #fff; }
            </style>
        </head>
        <body>
            <div class="wrapper">
                <div class="header">
                    <img src="{$logo}" alt="Jobmington" class="logo">
                </div>
                <div class="body">
                    {$content}
                </div>
                <div class="footer">
                    &copy; date('Y') Jobmington Network. All rights reserved.
                </div>
            </div>
        </body>
        </html>
HTML;
    }
    
    // --- PREDEFINED MESSAGES ---
    
    public static function sendWelcome(string $email, string $name): bool {
        $url = SITE_URL . '/seeker/dashboard.php';
        $content = "<h2>Identity Verified. Welcome, {$name}.</h2>
        <p>Your access to the Jobmington Network has been granted. Prepare to upgrade your career trajectory.</p>
        <p><a href='{$url}' class='btn'>Enter Command Center</a></p>";
        return (new self())->send($email, 'Access Granted', $content);
    }
    
    public static function sendPasswordReset(string $email, string $name, string $link): bool {
        $content = "<h2>Security Alert</h2>
        <p>A request to reset your credentials was intercepted. If this was you, authorize below:</p>
        <p><a href='{$link}' class='btn'>Reset Credentials</a></p>
        <p>Link expires in 60 minutes.</p>";
        return (new self())->send($email, 'Credential Reset Protocol', $content);
    }
}

// Global Helper
function sendMail(string $to, string $subj, string $body, array $opts = []): bool {
    return (new Mailer())->send($to, $subj, $body, $opts);
}
?>