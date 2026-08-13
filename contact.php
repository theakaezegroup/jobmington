<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

Session::start();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!Security::verifyCSRF($_POST['csrf_token'])) {
        $message = 'Security validation failed. Please try again.';
        $messageType = 'error';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $content = trim($_POST['message'] ?? '');
        $category = trim($_POST['category'] ?? 'general');

        if (!$name || !$email || !$subject || !$content) {
            $message = 'Please fill in all fields.';
            $messageType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'error';
        } elseif (strlen($content) < 10) {
            $message = 'Message must be at least 10 characters long.';
            $messageType = 'error';
        } else {
            try {
                $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@' . ($_SERVER['HTTP_HOST'] ?? 'jobmington.com');
                $safeSubject = Security::escape($subject);
                $body = '<h2>New Contact Message</h2>'
                    . '<p><strong>Name:</strong> ' . Security::escape($name) . '</p>'
                    . '<p><strong>Email:</strong> ' . Security::escape($email) . '</p>'
                    . '<p><strong>Category:</strong> ' . Security::escape($category) . '</p>'
                    . '<p><strong>Subject:</strong> ' . $safeSubject . '</p>'
                    . '<p>' . nl2br(Security::escape($content)) . '</p>';
                Mailer::send($adminEmail, '[' . SITE_NAME . '] ' . $safeSubject, $body);
                $message = 'Thank you. Your message has been sent.';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error sending message. Please try again later.';
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact | Jobmington</title>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicDemi.ttf" crossorigin>
    <link rel="preload" as="font" type="font/ttf" href="/jobmington/assets/fonts/FuturaCyrillicBook.ttf" crossorigin>
    <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-26">
</head>
<body class="jm-minimal">
    <div class="jm-shell">
        <header class="jm-header">
            <a href="/jobmington/" class="jm-logo"><img src="/jobmington/assets/images/badge.png?v=logo-8" alt=""><span>Jobmington</span></a>
            <?php require_once __DIR__ . '/includes/navigation.php'; jm_site_nav(); ?>
        </header>

        <section class="jm-hero">
            <div>
                <p class="jm-kicker">Contact</p>
                <h1>Send us a message.</h1>
                <p>Questions about jobs, applications, employer accounts, or support? Send a short note and we will help.</p>
            </div>
            <aside class="jm-panel">
                <h2>Email</h2>
                <p><?= e(defined('SITE_EMAIL') ? SITE_EMAIL : 'support@jobmington.com') ?></p>
                <a class="jm-muted-link" href="/jobmington/faq.php">Read FAQ</a>
            </aside>
        </section>

        <section class="jm-section">
            <form method="POST" class="jm-panel">
                <?= Security::csrfField() ?>
                <?php if ($message): ?>
                    <div class="<?= $messageType === 'success' ? 'jm-success' : 'jm-alert' ?>"><?= e($message) ?></div>
                <?php endif; ?>
                <div class="jm-form-grid">
                    <div class="jm-field"><label for="name">Name</label><input class="jm-input" id="name" type="text" name="name" required></div>
                    <div class="jm-field"><label for="email">Email</label><input class="jm-input" id="email" type="email" name="email" required></div>
                    <div class="jm-field">
                        <label for="category">Category</label>
                        <select class="jm-select" id="category" name="category">
                            <option value="general">General</option>
                            <option value="jobs">Jobs</option>
                            <option value="employer">Employer</option>
                            <option value="support">Support</option>
                        </select>
                    </div>
                    <div class="jm-field"><label for="subject">Subject</label><input class="jm-input" id="subject" type="text" name="subject" required></div>
                </div>
                <div class="jm-field" style="margin-top:16px;"><label for="message">Message</label><textarea class="jm-textarea" id="message" name="message" required></textarea></div>
                <div class="jm-form-actions"><button class="jm-button" type="submit">Send message</button></div>
            </form>
        </section>

        <?php jm_minimal_footer(); ?>
    </div>
</body>
</html>
