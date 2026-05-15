<?php
/**
 * JOBMINGTON API - Contact Talent
 * 
 * Allows employers to contact talent through the passport system.
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

// Must be logged in as employer
if (!Session::isLoggedIn() || !Session::isEmployer()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = Session::userId();

$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ? ORDER BY company_id ASC LIMIT 1");
$stmt->execute([$userId]);
$company = $stmt->fetch();

if (!$company) {
    echo json_encode(['success' => false, 'message' => 'Complete your company profile first.']);
    exit;
}

$companyId = (int) $company['company_id'];

// Get POST data
$passportId = intval($_POST['passport_id'] ?? 0);
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$passportId || !$subject || !$message) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Verify employer has access
$stmt = $pdo->prepare("
    SELECT * FROM employer_talent_access 
    WHERE company_id = ? AND user_id = ? AND status = 'active' AND expires_at > NOW()
    ORDER BY access_tier DESC
    LIMIT 1
");
$stmt->execute([$companyId, $userId]);
$access = $stmt->fetch();

if (!$access || $access['contacts_used'] >= $access['contacts_allowed']) {
    echo json_encode(['success' => false, 'message' => 'No contacts remaining. Please upgrade your plan.']);
    exit;
}

// Get passport and talent info
$stmt = $pdo->prepare("
    SELECT tp.*, u.email, u.full_name, u.user_id as talent_user_id
    FROM talent_passports tp
    JOIN users u ON tp.user_id = u.user_id
    WHERE tp.passport_id = ? AND tp.is_public = 1
");
$stmt->execute([$passportId]);
$passport = $stmt->fetch();

if (!$passport) {
    echo json_encode(['success' => false, 'message' => 'Passport not found']);
    exit;
}

// Check if they can access this level
$level = $passport['level'];
$canAccess = ($level === 'rising') ||
             ($level === 'verified' && ($access['can_access_verified'] || $access['access_tier'] !== 'basic')) ||
             ($level === 'expert' && $access['can_access_expert']) ||
             ($level === 'elite' && $access['can_access_elite']);

if (!$canAccess) {
    echo json_encode(['success' => false, 'message' => 'You don\'t have access to this talent level']);
    exit;
}

$companyName = $company['name'] ?? 'A company';

try {
    $pdo->beginTransaction();
    
    // Record the contact
    $stmt = $pdo->prepare("
        INSERT INTO passport_contacts
        (passport_id, employer_user_id, company_id, contact_type, created_at)
        VALUES (?, ?, ?, 'message', NOW())
    ");
    $stmt->execute([$passportId, $userId, $companyId]);
    
    // Update contact usage
    $stmt = $pdo->prepare("
        UPDATE employer_talent_access
        SET contacts_used = contacts_used + 1
        WHERE access_id = ?
    ");
    $stmt->execute([$access['access_id']]);
    
    // Notify talent by email
    $emailBody = "
    <h2>New Message from $companyName</h2>
    <p>A potential employer has reached out to you through your Talent Passport!</p>
    
    <div style='background: #f4f4f4; padding: 20px; border-radius: 8px; margin: 20px 0;'>
        <h3 style='margin-top: 0;'>$subject</h3>
        <p>" . nl2br(e($message)) . "</p>
    </div>
    
    <p><a href='" . SITE_URL . "/user/messages.php' style='display: inline-block; background: #f59e0b; color: #000; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;'>View & Reply</a></p>
    
    <p style='color: #666; font-size: 12px;'>This message was sent through your Jobmington Talent Passport. Companies pay to contact you, so this is a serious inquiry.</p>
    ";
    
    sendEmail($passport['email'], "Message from $companyName: $subject", $emailBody);
    
    // Also create an internal notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, message, link, created_at)
        VALUES (?, 'talent_contact', ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $passport['talent_user_id'],
        "New contact from $companyName",
        substr($message, 0, 100) . (strlen($message) > 100 ? '...' : ''),
        '/user/messages.php'
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'contacts_remaining' => $access['contacts_allowed'] - $access['contacts_used'] - 1
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Contact talent error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
}
