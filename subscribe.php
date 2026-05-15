<?php
/**
 * JOBMINGTON - Newsletter Subscription Handler
 * Secure subscription endpoint with validation
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

header('Content-Type: application/json');

// Handle subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = trim($_POST['email'] ?? $_GET['email'] ?? '');
        $name = trim($_POST['name'] ?? $_GET['name'] ?? '');
        $csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        
        // Validate CSRF if available
        if (!empty($csrf) && !Security::validateCSRFToken($csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security validation failed']);
            exit;
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            exit;
        }
        
        // Rate limiting
        $clientIP = $_SERVER['REMOTE_ADDR'];
        $rateKey = "newsletter_" . hash('sha256', $email . $clientIP);
        $attempts = Cache::get($rateKey, 0);
        
        if ($attempts >= 3) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Too many subscription attempts. Please try again later']);
            exit;
        }
        
        // Check if already subscribed
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM newsletters WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $existing = $stmt->fetch();
            
            if ($existing && $existing['subscribed'] == 1) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'You\'re already subscribed!', 'already' => true]);
                exit;
            }
            
            // Subscribe or update
            if ($existing) {
                $stmt = $db->prepare("UPDATE newsletters SET subscribed = 1, name = ?, updated_at = NOW() WHERE email = ?");
                $stmt->execute([$name ?: $existing['name'], $email]);
            } else {
                $stmt = $db->prepare("INSERT INTO newsletters (email, name, subscribed, created_at) VALUES (?, ?, 1, NOW())");
                $stmt->execute([$email, $name ?: 'Subscriber']);
            }
            
            // Send confirmation email
            $confirmBody = "
            <h2>Welcome to " . SITE_NAME . " Newsletter!</h2>
            <p>Hi " . Security::sanitizeOutput($name ?: 'there') . ",</p>
            <p>Thank you for subscribing to our newsletter. You'll now receive the latest job opportunities, course recommendations, and platform updates.</p>
            <p><strong>What to expect:</strong></p>
            <ul>
                <li>Weekly job recommendations tailored to your profile</li>
                <li>New course launches and learning opportunities</li>
                <li>Platform features and improvements</li>
                <li>Exclusive member offers and events</li>
            </ul>
            <p>Best regards,<br>" . SITE_NAME . " Team</p>
            ";
            
            Mailer::send($email, "Welcome to " . SITE_NAME . " Newsletter", $confirmBody);
            
            // Update rate limit
            Cache::set($rateKey, $attempts + 1, 3600);
            
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Successfully subscribed! Check your email for confirmation']);
            
        } catch (Exception $e) {
            error_log("Newsletter subscription error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Subscription failed. Please try again']);
        }
        
    } catch (Exception $e) {
        error_log("Subscription handler error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
