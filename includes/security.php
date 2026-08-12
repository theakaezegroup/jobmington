<?php
/**
 * JOBMINGTON - The Vault Keeper (Security Core)
 * Protocols: Argon2id Hashing, Token Rotation, Bruteforce Defense
 */

// Prevent direct access
if (!defined('JOBMINGTON')) {
    header("HTTP/1.1 403 Forbidden");
    die('Access Denied: Security Protocol Initiated.');
}

class Security {
    
    // --- CRYPTOGRAPHY ---
    
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 3
        ]);
    }
    
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    public static function generateToken(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }
    
    // --- CSRF DEFENSE ---
    
    public static function generateCSRF(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateToken(32);
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function csrfField(): string {
        return '<input type="hidden" name="csrf_token" value="' . self::generateCSRF() . '">';
    }

    public static function csrfToken(): string {
        return self::generateCSRF();
    }
    
    public static function verifyCSRF(?string $token = null): bool {
        $token = $token ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        if (empty($_SESSION['csrf_token']) || empty($token)) return false;
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function validateCSRF(?string $token = null): bool {
        return self::verifyCSRF($token);
    }
    
    public static function regenerateCSRF(): void {
        $_SESSION['csrf_token'] = self::generateToken(32);
    }
    
    // --- SANITIZATION ---
    
    public static function escape(?string $string): string {
        return htmlspecialchars((string)$string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    public static function clean($input) {
        if (is_array($input)) {
            return array_map([self::class, 'clean'], $input);
        }
        return trim(strip_tags((string)$input));
    }
    
    // --- VALIDATION ---
    
    public static function validateEmail(?string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validatePasswordStrength(string $password): array {
        $errors = [];
        if (strlen($password) < 8) $errors[] = 'Password must be 8+ characters.';
        if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Include at least one uppercase letter.';
        if (!preg_match('/[0-9]/', $password)) $errors[] = 'Include at least one number.';
        return $errors;
    }
    
    // --- NETWORK SECURITY ---
    
    public static function getClientIP(): string {
        $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        // Handle multiple IPs (proxies)
        if (strpos($ip, ',') !== false) {
            $ip = explode(',', $ip)[0];
        }
        return filter_var(trim($ip), FILTER_VALIDATE_IP) ? trim($ip) : '0.0.0.0';
    }
    
    public static function isAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    // --- RATE LIMITING (Bruteforce Defense) ---
    
    public static function checkRateLimit(string $key, int $limit = 10, int $seconds = 60): bool {
        $pdo = db();
        $ip = self::getClientIP();
        $identifier = hash('sha256', $key . $ip);
        
        // Clear old records
        $pdo->prepare("DELETE FROM rate_limits WHERE created_at < NOW() - INTERVAL ? SECOND")->execute([$seconds]);
        
        // Check count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE identifier = ?");
        $stmt->execute([$identifier]);
        $count = $stmt->fetchColumn();
        
        if ($count >= $limit) return false;
        
        // Log attempt
        $pdo->prepare("INSERT INTO rate_limits (identifier, created_at) VALUES (?, NOW())")->execute([$identifier]);
        return true;
    }
    
    // --- ACTIVITY LOGGING ---
    
    /**
     * Record something a person did.
     *
     * Never throws. An audit trail that can take a page down with it is worse
     * than no audit trail, and for a long time this method did exactly that:
     * the activity_logs table did not exist, so every call site raised.
     */
    public static function logActivity(?int $userId, string $action, ?string $details = null): void {
        try {
            $route = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?');

            $stmt = db()->prepare("
                INSERT INTO activity_logs (user_id, action, details, route, method, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId ?: null,
                substr($action, 0, 60),
                $details !== null ? substr($details, 0, 500) : null,
                substr($route, 0, 255),
                substr((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 0, 10),
                self::getClientIP(),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
            ]);
        } catch (Throwable $e) {
            error_log('logActivity(' . $action . '): ' . $e->getMessage());
        }
    }
    
    // --- FILE UPLOAD SECURITY ---
    
    public static function uploadFile(array $file, string $dest, array $allowedMimes, int $maxBytes): array {
        if ($file['error'] !== UPLOAD_ERR_OK) return ['success' => false, 'errors' => ['Upload failed: Error code ' . $file['error']]];
        if ($file['size'] > $maxBytes) return ['success' => false, 'errors' => ['File too large. Max: ' . self::formatBytes($maxBytes)]];
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        
        if (!in_array($mime, $allowedMimes)) return ['success' => false, 'errors' => ['Invalid file type.']];
        
        // Sanitize extension
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeName = self::generateToken(16) . '.' . $ext;
        
        if (!is_dir($dest)) mkdir($dest, 0755, true);
        
        if (move_uploaded_file($file['tmp_name'], $dest . '/' . $safeName)) {
            return ['success' => true, 'filename' => $safeName];
        }
        
        return ['success' => false, 'errors' => ['Failed to save file.']];
    }
    
    public static function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return number_format($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}

// Global Short Helpers
function e(?string $s): string { return Security::escape($s); }
function csrf_field(): string { return Security::csrfField(); }
function csrf_token(): string { return Security::generateCSRF(); }
?>
