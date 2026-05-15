<?php
/**
 * JOBMINGTON API - Passport Verification
 * 
 * Handle verification actions for Talent Passport.
 */

define('JOBMINGTON', true);

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json');
Session::start();
$pdo = db();

if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = Session::get('user_id');
$action = $_POST['action'] ?? '';

// Get user's passport
$stmt = $pdo->prepare("SELECT * FROM talent_passports WHERE user_id = ?");
$stmt->execute([$userId]);
$passport = $stmt->fetch();

if (!$passport) {
    echo json_encode(['success' => false, 'message' => 'No passport found']);
    exit;
}

// Get pricing
function getPricing($pdo, $key) {
    $names = [
        'skill_verify' => 'Skill Verification',
        'cert_link' => 'Certificate Link',
        'ext_cert' => 'External Certificate',
    ];
    $fallbacks = [
        'skill_verify' => 100,
        'cert_link' => 50,
        'ext_cert' => 75,
    ];
    $itemName = $names[$key] ?? $key;

    $stmt = $pdo->prepare("
        SELECT price_seeds
        FROM passport_pricing
        WHERE is_active = 1
          AND item_type = 'verification'
          AND item_name = ?
        ORDER BY pricing_id DESC
        LIMIT 1
    ");
    $stmt->execute([$itemName]);
    $result = $stmt->fetch();
    return $result ? (int) $result['price_seeds'] : ($fallbacks[$key] ?? 100);
}

switch ($action) {
    case 'link_certificate':
        $certId = intval($_POST['certificate_id'] ?? 0);
        $cost = getPricing($pdo, 'cert_link');
        
        // Verify certificate ownership
        $stmt = $pdo->prepare("
            SELECT c.*, cr.title AS course_name
            FROM certificates c
            JOIN courses cr ON c.course_id = cr.course_id
            WHERE c.certificate_id = ? AND c.user_id = ?
        ");
        $stmt->execute([$certId, $userId]);
        $cert = $stmt->fetch();
        
        if (!$cert) {
            echo json_encode(['success' => false, 'message' => 'Certificate not found']);
            exit;
        }
        
        // Check if already linked
        $stmt = $pdo->prepare("
            SELECT verification_id FROM passport_verifications 
            WHERE passport_id = ? AND reference_id = ? AND verification_type = 'certificate'
        ");
        $stmt->execute([$passport['passport_id'], $certId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Certificate already linked']);
            exit;
        }
        
        // Check Seeds balance
        $balance = getSeeds($userId);
        if ($balance < $cost) {
            echo json_encode(['success' => false, 'message' => "Insufficient Seeds. Need $cost, have $balance"]);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Deduct Seeds
            deductSeeds($userId, $cost, "Passport: Link certificate - {$cert['course_name']}");
            
            // Create verification
            $stmt = $pdo->prepare("
                INSERT INTO passport_verifications 
                (passport_id, verification_type, reference_id, reference_name, verified_by, status, verified_at, created_at)
                VALUES (?, 'certificate', ?, ?, 'institution', 'verified', NOW(), NOW())
            ");
            $stmt->execute([$passport['passport_id'], $certId, $cert['course_name']]);
            
            // Check if user qualifies for level upgrade
            checkAndUpgradeLevel($pdo, $passport['passport_id']);
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Certificate linked successfully']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Certificate link error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to link certificate']);
        }
        break;
        
    case 'request_endorsement':
        $endorserName = trim($_POST['endorser_name'] ?? '');
        $endorserEmail = trim($_POST['endorser_email'] ?? '');
        $endorserTitle = trim($_POST['endorser_title'] ?? '');
        $endorserCompany = trim($_POST['endorser_company'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        if (!$endorserName || !$endorserEmail || !$endorserTitle || !$endorserCompany) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }
        
        if (!filter_var($endorserEmail, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address']);
            exit;
        }
        
        // Generate token
        $token = bin2hex(random_bytes(32));
        
        try {
            $pdo->beginTransaction();
            
            // Create pending endorsement
            $stmt = $pdo->prepare("
                INSERT INTO passport_endorsements 
                (passport_id, endorser_type, endorser_email, endorser_name, endorser_title, worked_together_at, relationship, endorsement_text, verification_token, is_verified, created_at)
                VALUES (?, 'colleague', ?, ?, ?, ?, 'Professional reference', ?, ?, 0, NOW())
            ");
            $stmt->execute([
                $passport['passport_id'],
                $endorserEmail,
                $endorserName,
                $endorserTitle,
                $endorserCompany,
                $message ?: 'Pending endorsement request.',
                $token
            ]);
            $endorsementId = $pdo->lastInsertId();
            
            // Get user info
            $stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            // Send email to endorser
            $endorseUrl = SITE_URL . '/wallet/passport/endorse.php?token=' . $token;
            
            $emailBody = "
            <h2>Endorsement Request from {$user['full_name']}</h2>
            <p>Hi {$endorserName},</p>
            <p>{$user['full_name']} is requesting your endorsement on their Jobmington Talent Passport.</p>
            " . ($message ? "<blockquote style='background: #f4f4f4; padding: 15px; border-radius: 8px;'>" . nl2br(e($message)) . "</blockquote>" : "") . "
            <p>A Talent Passport is a verified professional credential that showcases skills and experience. Your endorsement will help validate their expertise.</p>
            <p><a href='{$endorseUrl}' style='display: inline-block; background: #10b981; color: white; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-weight: bold;'>Write Endorsement</a></p>
            <p style='color: #666; font-size: 12px;'>This link expires in 30 days. If you don't know {$user['full_name']}, please ignore this email.</p>
            ";
            
            sendEmail($endorserEmail, "Endorsement Request from {$user['full_name']}", $emailBody);
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Endorsement request sent']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Endorsement request error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to send request']);
        }
        break;
        
    case 'external_cert':
        $certName = trim($_POST['cert_name'] ?? '');
        $platform = trim($_POST['platform'] ?? '');
        $certUrl = trim($_POST['cert_url'] ?? '');
        $cost = getPricing($pdo, 'ext_cert');
        
        if (!$certName || !$platform || !$certUrl) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }
        
        if (!filter_var($certUrl, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid URL']);
            exit;
        }
        
        // Check Seeds balance
        $balance = getSeeds($userId);
        if ($balance < $cost) {
            echo json_encode(['success' => false, 'message' => "Insufficient Seeds. Need $cost, have $balance"]);
            exit;
        }
        
        // Handle file upload
        $filePath = null;
        if (!empty($_FILES['cert_file']['name'])) {
            $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            $ext = strtolower(pathinfo($_FILES['cert_file']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type']);
                exit;
            }
            
            if ($_FILES['cert_file']['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'File too large (max 5MB)']);
                exit;
            }
            
            $uploadDir = __DIR__ . '/../../uploads/certificates/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filename = 'ext_cert_' . $userId . '_' . time() . '.' . $ext;
            $filePath = 'uploads/certificates/' . $filename;
            
            move_uploaded_file($_FILES['cert_file']['tmp_name'], $uploadDir . $filename);
        }
        
        try {
            $pdo->beginTransaction();
            
            // Deduct Seeds
            deductSeeds($userId, $cost, "Passport: External certificate - $certName");
            
            // Create pending verification
            $stmt = $pdo->prepare("
                INSERT INTO passport_verifications 
                (passport_id, verification_type, reference_name, verified_by, proof_type, proof_url, status, created_at)
                VALUES (?, 'certificate', ?, 'institution', 'external', ?, 'pending', NOW())
            ");
            $stmt->execute([
                $passport['passport_id'],
                $certName,
                $certUrl ?: $filePath
            ]);
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Certificate submitted for review']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("External cert error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to submit certificate']);
        }
        break;
        
    case 'ai_assessment_complete':
        $skill = trim($_POST['skill'] ?? '');
        $score = intval($_POST['score'] ?? 0);
        
        if (!$skill) {
            echo json_encode(['success' => false, 'message' => 'Missing skill']);
            exit;
        }
        
        // Score >= 70 passes
        $status = $score >= 70 ? 'verified' : 'rejected';
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO passport_verifications 
                (passport_id, verification_type, reference_name, verified_by, proof_type, status, verified_at, created_at)
                VALUES (?, 'skill', ?, 'ai', ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $passport['passport_id'],
                $skill,
                'score:' . $score,
                $status,
                $status === 'verified' ? date('Y-m-d H:i:s') : null
            ]);
            
            if ($status === 'verified') {
                checkAndUpgradeLevel($pdo, $passport['passport_id']);
            }
            
            echo json_encode([
                'success' => true, 
                'passed' => $status === 'verified',
                'score' => $score
            ]);
            
        } catch (Exception $e) {
            error_log("AI assessment complete error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to save result']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Check and upgrade passport level if eligible
 */
function checkAndUpgradeLevel($pdo, $passportId) {
    // Get passport
    $stmt = $pdo->prepare("SELECT * FROM talent_passports WHERE passport_id = ?");
    $stmt->execute([$passportId]);
    $passport = $stmt->fetch();
    
    if (!$passport) return;
    
    // Count verifications
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM passport_verifications 
        WHERE passport_id = ? AND status = 'verified'
    ");
    $stmt->execute([$passportId]);
    $verifiedCount = $stmt->fetchColumn();
    
    // Count endorsements
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM passport_endorsements 
        WHERE passport_id = ? AND is_verified = 1
    ");
    $stmt->execute([$passportId]);
    $endorsementCount = $stmt->fetchColumn();
    
    // Determine new level
    $newLevel = 'rising';
    
    if ($passport['times_featured'] >= 5) {
        $newLevel = 'elite';
    } elseif ($endorsementCount >= 2) {
        $newLevel = 'expert';
    } elseif ($verifiedCount >= 3) {
        $newLevel = 'verified';
    }
    
    // Upgrade if higher
    $levelOrder = ['rising' => 1, 'verified' => 2, 'expert' => 3, 'elite' => 4];
    
    if ($levelOrder[$newLevel] > $levelOrder[$passport['level']]) {
        $stmt = $pdo->prepare("
            UPDATE talent_passports 
            SET level = ?, updated_at = NOW()
            WHERE passport_id = ?
        ");
        $stmt->execute([$newLevel, $passportId]);
        
        // Notify user
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, link, created_at)
            VALUES (?, 'passport_upgrade', ?, ?, '/wallet/passport/', NOW())
        ");
        $levelNames = ['verified' => ' Verified', 'expert' => ' Expert', 'elite' => ' Elite'];
        $stmt->execute([
            $passport['user_id'],
            "Passport Upgraded to {$levelNames[$newLevel]}!",
            "Congratulations! Your Talent Passport has been upgraded based on your verified credentials."
        ]);
    }
}
