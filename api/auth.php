<?php
/**
 * JOBMINGTON - Authentication API
 * Handles: Login, Register, OAuth Providers
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$provider = $_GET['provider'] ?? null;

// Handle OAuth redirects
if ($provider) {
    // Get OAuth credentials from environment
    $googleClientId = getenv('GOOGLE_CLIENT_ID') ?: '';
    $googleRedirectUri = 'http://localhost/jobmington/api/auth.php?callback=google';
    
    $linkedinClientId = getenv('LINKEDIN_CLIENT_ID') ?: '';
    $linkedinRedirectUri = 'http://localhost/jobmington/api/auth.php?callback=linkedin';
    
    if ($provider === 'google') {
        if (empty($googleClientId)) {
            // Redirect to login with error message
            header('Location: /jobmington/auth/login.php?error=' . urlencode('Google OAuth not configured. Please contact administrator.'));
            exit;
        }
        $scope = urlencode('openid email profile');
        $authUrl = "https://accounts.google.com/o/oauth2/v2/auth?client_id={$googleClientId}&redirect_uri=" . urlencode($googleRedirectUri) . "&response_type=code&scope={$scope}";
        header("Location: $authUrl");
        exit;
    }
    
    if ($provider === 'linkedin') {
        if (empty($linkedinClientId)) {
            header('Location: /jobmington/auth/login.php?error=' . urlencode('LinkedIn OAuth not configured. Please contact administrator.'));
            exit;
        }
        $scope = urlencode('r_liteprofile r_emailaddress');
        $authUrl = "https://www.linkedin.com/oauth/v2/authorization?response_type=code&client_id={$linkedinClientId}&redirect_uri=" . urlencode($linkedinRedirectUri) . "&scope={$scope}";
        header("Location: $authUrl");
        exit;
    }
}

// Handle OAuth callbacks
$callback = $_GET['callback'] ?? null;
if ($callback) {
    $code = $_GET['code'] ?? null;
    
    if (!$code) {
        header('Location: /jobmington/auth/login.php?error=' . urlencode('Authentication cancelled'));
        exit;
    }
    
    // In production, you would exchange the code for tokens and get user info
    // For now, redirect with message
    header('Location: /jobmington/auth/login.php?error=' . urlencode('OAuth callback received. Complete OAuth setup in env.php'));
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

switch ($method) {
    case 'POST':
        $action = $_GET['action'] ?? null;
        
        if ($action === 'register') {
            $name = $input['full_name'] ?? '';
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            
            // Validate
            if (empty($name) || empty($email) || empty($password)) {
                echo json_encode(["success" => false, "message" => "All fields required"]);
                exit;
            }
            
            // Check if email exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => false, "message" => "Email already exists"]);
                exit;
            }
            
            // Create user
            $hash = password_hash($password, PASSWORD_DEFAULT);
            [$firstName, $lastName] = array_pad(explode(' ', trim($name), 2), 2, '');
            $stmt = $pdo->prepare("
                INSERT INTO users (first_name, last_name, full_name, email, password_hash, user_type, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, 'seeker', 1, NOW())
            ");
            $stmt->execute([$firstName, $lastName, $name, $email, $hash]);
            
            echo json_encode([
                "success" => true,
                "message" => "Registration successful",
                "user_id" => $pdo->lastInsertId()
            ]);
        }
        
        elseif ($action === 'login') {
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                unset($user['password_hash']); // Don't send password
                echo json_encode([
                    "success" => true,
                    "message" => "Login successful",
                    "user" => $user
                ]);
            } else {
                echo json_encode(["success" => false, "message" => "Invalid credentials"]);
            }
        }
        break;
        
    default:
        echo json_encode(["error" => "Method not allowed"]);
        break;
}
?>
