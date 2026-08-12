<?php
/**
 * JOBMINGTON - Admin Email Outreach (Brevo-powered)
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email_queue.php';

Session::start();
Session::requireAdmin();

$pdo = db();

/*
 * Schema (email_campaigns, email_unsubscribes) is managed by the migration
 * runner — see database/migrate.php. Run `php database/migrate.php` on deploy.
 */

/* Reconcile: finalise any campaign whose queue has drained (covers a worker
   that finished between cron ticks, or a send that never enqueued). */
jm_reconcile_sending_campaigns($pdo);

/* Ensure upload directory exists and is writable by the web server */
$posterUploadDir = rtrim(UPLOADS_PATH, '/') . '/campaign-posters';
if (!is_dir($posterUploadDir)) {
    mkdir($posterUploadDir, 0755, true);
}
if (!is_writable($posterUploadDir)) {
    error_log('email-campaigns: poster upload dir not writable — ' . $posterUploadDir);
}

/* ── Helpers ─────────────────────────────────────────────────────── */
function ec_segment_label(string $seg): string {
    return [
        'all'        => 'All verified users',
        'seekers'    => 'Job seekers only',
        'employers'  => 'Employers only',
        'unverified' => 'Unverified accounts',
        'new7'       => 'Joined last 7 days',
        'new30'      => 'Joined last 30 days',
    ][$seg] ?? ucfirst($seg);
}

function ec_segment_query(PDO $pdo, string $seg): array {
    $base  = 'SELECT user_id, full_name, email FROM users WHERE is_active = 1 AND email IS NOT NULL AND email != \'\'';
    $sql   = match ($seg) {
        'seekers'    => $base . " AND user_type = 'seeker'   AND is_verified = 1",
        'employers'  => $base . " AND user_type = 'employer' AND is_verified = 1",
        'unverified' => $base . ' AND is_verified = 0',
        'new7'       => $base . ' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
        'new30'      => $base . ' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
        default      => $base . ' AND is_verified = 1',
    };
    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('ec_segment_query: ' . $e->getMessage());
        return [];
    }
}

function ec_count(PDO $pdo, string $seg): int {
    return count(ec_segment_query($pdo, $seg));
}

/* ── AJAX: recipient count ───────────────────────────────────────── */
if (($_GET['ajax'] ?? '') === 'count' && isset($_GET['segment'])) {
    header('Content-Type: application/json');
    echo json_encode(['count' => ec_count($pdo, Security::clean($_GET['segment']))]);
    exit;
}

/* ── POST handlers ───────────────────────────────────────────────── */
$flash = Session::getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        Session::flash('error', 'Security token invalid. Please try again.');
        redirect('/jobmington/admin/email-campaigns.php');
    }

    $postAction = Security::clean($_POST['action'] ?? '');

    /* Delete campaign */
    if ($postAction === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM email_campaigns WHERE id = ? AND status != 'sending'")->execute([$id]);
        Session::flash('success', 'Campaign deleted.');
        redirect('/jobmington/admin/email-campaigns.php');
    }

    /* Duplicate campaign → new draft */
    if ($postAction === 'duplicate') {
        $id   = (int) ($_POST['id'] ?? 0);
        $orig = $pdo->prepare("SELECT * FROM email_campaigns WHERE id = ? LIMIT 1");
        $orig->execute([$id]);
        $orig = $orig->fetch(PDO::FETCH_ASSOC);
        if ($orig) {
            $pdo->prepare("
                INSERT INTO email_campaigns
                    (subject, preview_text, body_html, poster_url, custom_emails, segment, recipient_count, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?)
            ")->execute([
                'Copy of ' . $orig['subject'],
                $orig['preview_text'],
                $orig['body_html'],
                $orig['poster_url'],
                $orig['custom_emails'],
                $orig['segment'],
                $orig['recipient_count'],
                (int) Session::userId(),
            ]);
            $newId = (int) $pdo->lastInsertId();
            Session::flash('success', 'Campaign duplicated as a new draft.');
            redirect('/jobmington/admin/email-campaigns.php?view=compose&id=' . $newId);
        }
        redirect('/jobmington/admin/email-campaigns.php');
    }

    /* Save draft */
    if (in_array($postAction, ['save_draft', 'send'], true)) {
        $subject      = trim($_POST['subject']       ?? '');
        $previewText  = trim($_POST['preview_text']  ?? '');
        $bodyHtml     = trim($_POST['body_html']     ?? '');
        $segment      = Security::clean($_POST['segment'] ?? 'all');
        $editId       = (int) ($_POST['edit_id'] ?? 0);
        $keepPoster   = Security::clean($_POST['keep_poster'] ?? '');
        $rawCustom    = trim($_POST['custom_emails'] ?? '');

        /* Parse & validate custom emails */
        $customEmails = [];
        if ($rawCustom !== '') {
            $tokens = preg_split('/[\r\n,;]+/', $rawCustom);
            foreach ($tokens as $tok) {
                $tok = strtolower(trim($tok));
                if ($tok !== '' && filter_var($tok, FILTER_VALIDATE_EMAIL)) {
                    $customEmails[] = $tok;
                }
            }
            $customEmails = array_unique($customEmails);
        }
        $customEmailsStored = implode("\n", $customEmails);

        if ($subject === '' || $bodyHtml === '') {
            Session::flash('error', 'Subject and body are required.');
            redirect('/jobmington/admin/email-campaigns.php?view=compose' . ($editId ? '&id=' . $editId : ''));
        }

        /* Handle poster upload */
        $posterUrl = $keepPoster ?: null;
        if (!empty($_FILES['poster']['name'])) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $ext     = strtolower(pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION));
            $mime    = mime_content_type($_FILES['poster']['tmp_name']);
            if (in_array($mime, $allowed, true) && $_FILES['poster']['size'] <= 8 * 1024 * 1024) {
                $filename = 'poster_' . uniqid() . '.' . $ext;
                $dest     = $posterUploadDir . '/' . $filename;
                if (move_uploaded_file($_FILES['poster']['tmp_name'], $dest)) {
                    $posterUrl = UPLOADS_URL . '/campaign-posters/' . $filename;
                }
            } else {
                Session::flash('error', 'Poster must be JPG, PNG, GIF or WebP and under 8 MB.');
                redirect('/jobmington/admin/email-campaigns.php?view=compose' . ($editId ? '&id=' . $editId : ''));
            }
        }

        /* Merge segment + custom, deduplicate by email */
        $segmentRecipients = ec_segment_query($pdo, $segment);
        $segmentEmails     = array_column($segmentRecipients, 'email', 'email');
        $allRecipients     = $segmentRecipients;
        foreach ($customEmails as $ce) {
            if (!isset($segmentEmails[$ce])) {
                $allRecipients[] = ['email' => $ce, 'full_name' => ''];
            }
        }
        $recipientCount = count($allRecipients);

        if ($editId > 0) {
            $pdo->prepare("
                UPDATE email_campaigns
                SET subject=?, preview_text=?, body_html=?, poster_url=?, custom_emails=?, segment=?, recipient_count=?, status='draft'
                WHERE id=? AND status='draft'
            ")->execute([$subject, $previewText, $bodyHtml, $posterUrl, $customEmailsStored, $segment, $recipientCount, $editId]);
            $campaignId = $editId;
        } else {
            $pdo->prepare("
                INSERT INTO email_campaigns (subject, preview_text, body_html, poster_url, custom_emails, segment, recipient_count, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?)
            ")->execute([$subject, $previewText, $bodyHtml, $posterUrl, $customEmailsStored, $segment, $recipientCount, (int) Session::userId()]);
            $campaignId = (int) $pdo->lastInsertId();
        }

        if ($postAction === 'save_draft') {
            Session::flash('success', 'Campaign saved as draft.');
            redirect('/jobmington/admin/email-campaigns.php');
        }

        /* Send now */
        if ($recipientCount === 0) {
            Session::flash('error', 'No recipients match the selected segment or custom list.');
            redirect('/jobmington/admin/email-campaigns.php?view=compose&id=' . $campaignId);
        }

        /* Prepend poster to body if present — table markup required for email clients */
        $posterHtml = '';
        if ($posterUrl) {
            $safeUrl    = htmlspecialchars($posterUrl, ENT_QUOTES | ENT_HTML5);
            $posterHtml = "<table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin:0 0 28px;'>"
                        . "<tr><td align='center'>"
                        . "<img src='{$safeUrl}' alt='Campaign image' width='520'"
                        . " style='display:block;width:100%;max-width:520px;height:auto;border:0;' />"
                        . "</td></tr></table>\n";
        }

        /* Enqueue one fully-rendered email per recipient. Actual delivery is
           handled asynchronously by cron/process_email_queue.php so this
           request returns immediately even for large lists. */
        $queuedCount  = 0;
        $skippedCount = 0;

        foreach ($allRecipients as $user) {
            $to = trim($user['email']);

            // Honour the suppression list — never queue an unsubscribed address.
            if (Mailer::isSuppressed($to)) {
                $skippedCount++;
                continue;
            }

            $name = trim($user['full_name'] ?: 'there');

            // Per-recipient unsubscribe footer (CAN-SPAM / deliverability).
            $unsubUrl = Mailer::unsubscribeUrl($to);
            $footer   = "<p style='color:#94a3b8;font-size:12px;line-height:1.6;margin:28px 0 0;text-align:center;'>"
                      . "You're receiving this because you have a Jobmington account.<br>"
                      . "<a href='" . htmlspecialchars($unsubUrl, ENT_QUOTES | ENT_HTML5) . "' style='color:#94a3b8;text-decoration:underline;'>Unsubscribe from marketing emails</a>."
                      . "</p>";

            $personalised = str_replace(
                ['{{name}}', '{{first_name}}', '{{email}}'],
                [e($name), e(explode(' ', $name)[0]), e($to)],
                $posterHtml . $bodyHtml . $footer
            );

            // Strip any unsupported/unreplaced {{tokens}} so raw tokens never ship.
            $personalised = preg_replace('/\{\{\s*[a-zA-Z0-9_]+\s*\}\}/', '', $personalised);

            if (jm_enqueue_email($pdo, $to, $name, $subject, $personalised, 'campaign', $campaignId)) {
                $queuedCount++;
            }
        }

        if ($queuedCount === 0) {
            // Everyone was suppressed (or nothing enqueued) — nothing to send.
            $pdo->prepare("UPDATE email_campaigns SET status='sent', skipped_count=?, sent_at=NOW() WHERE id=?")
                ->execute([$skippedCount, $campaignId]);
            Session::flash('error', 'No emails queued — all recipients are unsubscribed.');
            redirect('/jobmington/admin/email-campaigns.php');
        }

        $pdo->prepare("
            UPDATE email_campaigns
            SET status='sending', started_at=NOW(), recipient_count=?, skipped_count=?, sent_count=0, failed_count=0
            WHERE id=?
        ")->execute([$queuedCount, $skippedCount, $campaignId]);

        $msg = "Campaign queued — {$queuedCount} email" . ($queuedCount === 1 ? '' : 's') . " will be delivered shortly";
        if ($skippedCount) { $msg .= " ({$skippedCount} skipped — unsubscribed)"; }
        Session::flash('success', $msg . '.');
        redirect('/jobmington/admin/email-campaigns.php');
    }

    redirect('/jobmington/admin/email-campaigns.php');
}

/* ── Page data ───────────────────────────────────────────────────── */
$view   = Security::clean($_GET['view'] ?? 'list');
$editId = (int) ($_GET['id'] ?? 0);

$campaigns = [];
try {
    $campaigns = $pdo->query("SELECT * FROM email_campaigns ORDER BY created_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable) {}

$totalCampaigns = count($campaigns);
$totalSent      = array_reduce($campaigns, fn($c, $r) => $c + (int)$r['sent_count'], 0);
$lastSentAt     = null;
foreach ($campaigns as $c) { if ($c['sent_at']) { $lastSentAt = $c['sent_at']; break; } }

$editCampaign = null;
if ($editId > 0 && $view === 'compose') {
    foreach ($campaigns as $c) {
        if ((int)$c['id'] === $editId && $c['status'] === 'draft') { $editCampaign = $c; break; }
    }
}

$segments = ['all', 'seekers', 'employers', 'unverified', 'new7', 'new30'];

$pageTitle = 'Email Outreach | ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>
<style>
    .ec-shell   { max-width:1100px; margin:0 auto; padding:0 6px 48px; }
    .ec-hero    { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; flex-wrap:wrap; padding:28px 0 24px; border-bottom:1px solid var(--admin-line); margin-bottom:28px; }
    .ec-hero h1 { margin:0 0 6px; font-size:26px; font-weight:700; color:var(--admin-ink); }
    .ec-hero p  { margin:0; font-size:14px; color:var(--admin-muted); }
    .ec-kicker  { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--admin-blue); margin:0 0 8px; }
    .ec-stats   { display:flex; gap:32px; flex-wrap:wrap; }
    .ec-stat    { text-align:center; }
    .ec-stat strong { display:block; font-size:28px; font-weight:800; color:var(--admin-ink); line-height:1; }
    .ec-stat span   { font-size:12px; color:var(--admin-muted); }

    /* Compose button */
    .ec-btn-primary { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; background:var(--admin-blue); color:#fff; border:none; border-radius:8px; font-size:14px; font-weight:700; cursor:pointer; text-decoration:none; }
    .ec-btn-primary:hover { background:#0530a3; }
    .ec-btn-secondary { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; background:#fff; color:var(--admin-ink); border:1px solid var(--admin-line); border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; }
    .ec-btn-secondary:hover { border-color:#b6cceb; }
    .ec-btn-danger { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; background:#fff5f5; color:var(--admin-red); border:1px solid #ffd7d7; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; }
    .ec-btn-danger:hover { background:#ffecec; }

    /* Table */
    .ec-table-wrap { background:#fff; border:1px solid var(--admin-line); border-radius:10px; overflow:hidden; }
    .ec-table { width:100%; border-collapse:collapse; font-size:14px; }
    .ec-table th { background:#f5f8fc; padding:11px 16px; text-align:left; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--admin-muted); border-bottom:1px solid var(--admin-line); white-space:nowrap; }
    .ec-table td { padding:14px 16px; border-bottom:1px solid #f0f4fb; vertical-align:middle; color:var(--admin-ink); }
    .ec-table tr:last-child td { border-bottom:none; }
    .ec-table tr:hover td { background:#fafcff; }
    .ec-table .ec-td-sub { font-size:12px; color:var(--admin-muted); margin-top:3px; }
    .ec-badge { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:99px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
    .ec-badge.draft    { background:#f0f4fb; color:#53667f; }
    .ec-badge.sent     { background:#ecfdf5; color:#065f46; }
    .ec-badge.sending  { background:#fffbeb; color:#92400e; }
    .ec-badge.failed   { background:#fff5f5; color:var(--admin-red); }
    .ec-empty { padding:56px 24px; text-align:center; }
    .ec-empty svg { width:48px; height:48px; stroke:var(--admin-line); margin:0 auto 16px; display:block; }
    .ec-empty h3 { margin:0 0 6px; font-size:18px; color:var(--admin-ink); }
    .ec-empty p  { margin:0; font-size:14px; color:var(--admin-muted); }

    /* Compose layout */
    .ec-compose { display:grid; grid-template-columns:1fr 380px; gap:28px; align-items:start; }
    @media(max-width:900px){ .ec-compose{ grid-template-columns:1fr; } }
    .ec-card    { background:#fff; border:1px solid var(--admin-line); border-radius:10px; padding:28px; }
    .ec-card h2 { margin:0 0 20px; font-size:17px; font-weight:700; color:var(--admin-ink); }
    .ec-field   { margin-bottom:18px; }
    .ec-label   { display:block; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--admin-muted); margin-bottom:6px; }
    .ec-input, .ec-select, .ec-textarea {
        width:100%; box-sizing:border-box; padding:10px 12px;
        border:1px solid var(--admin-line); border-radius:7px;
        font-size:14px; color:var(--admin-ink); background:#fff;
        font-family:inherit;
    }
    .ec-input:focus, .ec-select:focus, .ec-textarea:focus {
        outline:none; border-color:var(--admin-blue); box-shadow:0 0 0 3px rgba(6,64,163,.08);
    }
    .ec-textarea { min-height:340px; font-family:'Courier New',monospace; font-size:13px; line-height:1.6; resize:vertical; }
    .ec-help { font-size:12px; color:var(--admin-muted); margin-top:5px; }
    .ec-segment-row { display:flex; align-items:center; gap:10px; }
    .ec-segment-count { background:#eef5ff; color:var(--admin-blue); font-size:13px; font-weight:700; padding:4px 12px; border-radius:99px; white-space:nowrap; }
    .ec-actions { display:flex; gap:12px; flex-wrap:wrap; margin-top:24px; padding-top:20px; border-top:1px solid var(--admin-line); }

    /* Preview pane */
    .ec-preview-label { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--admin-muted); margin:0 0 10px; }
    .ec-preview-meta  { font-size:13px; color:var(--admin-muted); margin-bottom:16px; line-height:1.7; }
    .ec-preview-meta strong { color:var(--admin-ink); }
    .ec-preview-frame { border:1px solid var(--admin-line); border-radius:8px; overflow:hidden; height:360px; }
    .ec-preview-frame iframe { width:100%; height:100%; border:none; display:block; }
    .ec-personalise { background:#f5f8fc; border:1px solid var(--admin-line); border-radius:8px; padding:14px; margin-top:16px; }
    .ec-personalise h4 { margin:0 0 8px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--admin-muted); }
    .ec-personalise code { display:inline-block; background:#eef5ff; color:var(--admin-blue); padding:2px 7px; border-radius:4px; font-size:12px; margin:2px 3px; }

    /* Toolbar */
    .ec-toolbar { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:8px; }
    .ec-tool-btn { padding:5px 11px; background:#f5f8fc; border:1px solid var(--admin-line); border-radius:5px; font-size:12px; font-weight:600; color:var(--admin-ink); cursor:pointer; }
    .ec-tool-btn:hover { background:#eef5ff; border-color:#b6cceb; color:var(--admin-blue); }

    /* Flash */
    .ec-flash { padding:14px 18px; border-radius:8px; font-size:14px; font-weight:600; margin-bottom:20px; }
    .ec-flash.success { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
    .ec-flash.error   { background:#fff5f5; color:var(--admin-red); border:1px solid #ffd7d7; }
</style>

<div class="ec-shell">

<?php if (!empty($flash['success'])): ?>
    <div class="ec-flash success"><i class="fas fa-check-circle" style="margin-right:8px;"></i><?= e($flash['success']) ?></div>
<?php elseif (!empty($flash['error'])): ?>
    <div class="ec-flash error"><i class="fas fa-exclamation-circle" style="margin-right:8px;"></i><?= e($flash['error']) ?></div>
<?php endif; ?>

<?php if ($view === 'compose'): /* ═══════════════ COMPOSE ════════════════ */ ?>

<div class="ec-hero">
    <div>
        <p class="ec-kicker">Email Outreach</p>
        <h1><?= $editCampaign ? 'Edit draft' : 'New campaign' ?></h1>
        <p>Write your message, pick your audience, then send or save a draft.</p>
    </div>
    <a class="ec-btn-secondary" href="/jobmington/admin/email-campaigns.php">
        <i class="fas fa-arrow-left"></i> All campaigns
    </a>
</div>

<form method="POST" id="ec-form" enctype="multipart/form-data">
    <?= Security::csrfField() ?>
    <input type="hidden" name="edit_id" value="<?= $editId ?>">
    <input type="hidden" name="keep_poster" id="ec-keep-poster"
           value="<?= e($editCampaign['poster_url'] ?? '') ?>"
           data-orig="<?= e($editCampaign['poster_url'] ?? '') ?>">

    <div class="ec-compose">

        <!-- LEFT: Form -->
        <div>
            <div class="ec-card">
                <h2>Campaign details</h2>

                <div class="ec-field">
                    <label class="ec-label" for="ec-subject">Subject line <span style="color:var(--admin-red)">*</span></label>
                    <input class="ec-input" id="ec-subject" name="subject" required maxlength="255"
                           placeholder="e.g. 5 new remote jobs that match your profile"
                           value="<?= e($editCampaign['subject'] ?? '') ?>">
                </div>

                <div class="ec-field">
                    <label class="ec-label" for="ec-preview">Preview text</label>
                    <input class="ec-input" id="ec-preview" name="preview_text" maxlength="255"
                           placeholder="Short summary shown in inbox before opening"
                           value="<?= e($editCampaign['preview_text'] ?? '') ?>">
                    <p class="ec-help">Displayed by Gmail, Apple Mail, etc. before the email is opened.</p>
                </div>

                <!-- Poster / Flier upload -->
                <div class="ec-field">
                    <label class="ec-label">Poster / Flier image</label>
                    <?php if (!empty($editCampaign['poster_url'])): ?>
                        <div id="ec-poster-current" style="margin-bottom:10px;">
                            <img src="<?= e($editCampaign['poster_url']) ?>" alt="Current poster"
                                 style="max-width:100%;max-height:180px;border-radius:6px;border:1px solid var(--admin-line);display:block;margin-bottom:8px;">
                            <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--admin-muted);cursor:pointer;">
                                <input type="checkbox" id="ec-remove-poster" onchange="togglePosterRemove(this)"> Remove current poster
                            </label>
                        </div>
                    <?php endif; ?>
                    <div id="ec-poster-upload" <?= !empty($editCampaign['poster_url']) ? 'style="display:none;"' : '' ?>>
                        <div id="ec-drop-zone" style="border:2px dashed var(--admin-line);border-radius:8px;padding:28px 20px;text-align:center;cursor:pointer;transition:border-color .15s;"
                             onclick="document.getElementById('ec-poster-file').click()"
                             ondragover="event.preventDefault();this.style.borderColor='var(--admin-blue)'"
                             ondragleave="this.style.borderColor='var(--admin-line)'"
                             ondrop="handleDrop(event)">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--admin-muted)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 10px;display:block;"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            <p style="margin:0 0 4px;font-size:14px;font-weight:600;color:var(--admin-ink);">Drop your poster here or click to browse</p>
                            <p style="margin:0;font-size:12px;color:var(--admin-muted);">JPG, PNG, GIF, WebP — max 8 MB</p>
                        </div>
                        <input type="file" id="ec-poster-file" name="poster" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;" onchange="previewPoster(this)">
                        <div id="ec-poster-preview" style="display:none;margin-top:10px;position:relative;">
                            <img id="ec-poster-img" src="" alt="" style="max-width:100%;max-height:200px;border-radius:6px;border:1px solid var(--admin-line);display:block;">
                            <button type="button" onclick="clearPoster()" style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:14px;line-height:1;display:flex;align-items:center;justify-content:center;">&times;</button>
                        </div>
                    </div>
                    <p class="ec-help">Appears full-width at the top of the email before your body text.</p>
                </div>

                <div class="ec-field">
                    <label class="ec-label" for="ec-segment">Recipient segment</label>
                    <div class="ec-segment-row">
                        <select class="ec-select" id="ec-segment" name="segment" style="flex:1;" onchange="updateCount(this.value)">
                            <?php foreach ($segments as $seg): ?>
                                <option value="<?= e($seg) ?>" <?= ($editCampaign['segment'] ?? 'all') === $seg ? 'selected' : '' ?>>
                                    <?= e(ec_segment_label($seg)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="ec-segment-count" id="ec-count">
                            <?= number_format(ec_count($pdo, $editCampaign['segment'] ?? 'all')) ?> recipients
                        </span>
                    </div>
                    <p class="ec-help">Choose a group of registered users, or leave on "All verified users".</p>
                </div>

                <div class="ec-field">
                    <label class="ec-label" for="ec-custom-emails">
                        Additional recipients
                        <span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--admin-muted);font-size:11px;"> — emails not in the system</span>
                    </label>
                    <textarea class="ec-textarea" id="ec-custom-emails" name="custom_emails"
                              style="min-height:100px;font-family:inherit;font-size:13px;"
                              placeholder="Paste one email per line, or separate by commas:&#10;alice@example.com&#10;bob@company.org, carol@brand.io"
                              oninput="countCustomEmails()"><?= e($editCampaign['custom_emails'] ?? '') ?></textarea>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:5px;">
                        <p class="ec-help" style="margin:0;">Valid addresses are merged with the segment above. Duplicates are skipped.</p>
                        <span id="ec-custom-count" style="font-size:12px;font-weight:700;color:var(--admin-blue);white-space:nowrap;"></span>
                    </div>
                </div>
            </div>

            <div class="ec-card" style="margin-top:20px;">
                <h2>Email body <span style="font-weight:400;font-size:13px;color:var(--admin-muted);">(HTML)</span></h2>

                <div class="ec-toolbar" id="ec-toolbar">
                    <button type="button" class="ec-tool-btn" onclick="insert('&lt;h2 style=&quot;font-weight:700;color:#06142a;margin:0 0 12px;font-size:20px;&quot;&gt;Heading&lt;/h2&gt;\n')"><i class="fas fa-heading"></i> H2</button>
                    <button type="button" class="ec-tool-btn" onclick="insert('&lt;p style=&quot;color:#475569;margin:0 0 16px;line-height:1.75;&quot;&gt;Your paragraph text here.&lt;/p&gt;\n')"><i class="fas fa-paragraph"></i> Para</button>
                    <button type="button" class="ec-tool-btn" onclick="insert(ctaSnippet())"><i class="fas fa-hand-pointer"></i> CTA Button</button>
                    <button type="button" class="ec-tool-btn" onclick="insert('&lt;hr style=&quot;border:none;border-top:1px solid #e5e7eb;margin:24px 0;&quot;&gt;\n')"><i class="fas fa-minus"></i> Divider</button>
                    <button type="button" class="ec-tool-btn" onclick="insert('&lt;p style=&quot;color:#94a3b8;font-size:13px;margin:0;&quot;&gt;Footer note here.&lt;/p&gt;\n')"><i class="fas fa-info-circle"></i> Footer note</button>
                    <button type="button" class="ec-tool-btn" onclick="insertJobsList()"><i class="fas fa-list"></i> Jobs list</button>
                    <button type="button" class="ec-tool-btn" onclick="livePreview()" style="margin-left:auto;background:#eef5ff;border-color:#b6cceb;color:var(--admin-blue);"><i class="fas fa-eye"></i> Preview</button>
                </div>

                <textarea class="ec-textarea" id="ec-body" name="body_html" required
                          oninput="livePreview()"
                          placeholder="Write your email HTML here. Use {{name}}, {{first_name}}, {{email}} for personalisation."><?= e($editCampaign['body_html'] ?? '') ?></textarea>

                <div class="ec-actions">
                    <button class="ec-btn-secondary" type="submit" name="action" value="save_draft">
                        <i class="fas fa-save"></i> Save draft
                    </button>
                    <button class="ec-btn-primary" type="button"
                            onclick="if(confirm('Send this campaign to all selected recipients now?')) { document.getElementById('ec-send-action').value='send'; document.getElementById('ec-form').submit(); }">
                        <i class="fas fa-paper-plane"></i> Send now
                    </button>
                    <input type="hidden" id="ec-send-action" name="action" value="save_draft">
                </div>
            </div>
        </div>

        <!-- RIGHT: Preview + info -->
        <div>
            <div class="ec-card">
                <p class="ec-preview-label">Live preview</p>
                <div class="ec-preview-frame">
                    <iframe id="ec-preview-frame" srcdoc="<p style='padding:16px;color:#94a3b8;font-size:13px;'>Start typing to see a preview.</p>"></iframe>
                </div>
                <p class="ec-help" style="margin-top:8px;">Preview shows your content wrapped in the Jobmington email template.</p>
            </div>

            <div class="ec-card" style="margin-top:20px;">
                <p class="ec-preview-label">Recipient summary</p>
                <div class="ec-preview-meta" id="ec-segment-summary">
                    Segment: <strong id="ec-seg-label"><?= e(ec_segment_label($editCampaign['segment'] ?? 'all')) ?></strong><br>
                    Recipients: <strong id="ec-seg-count"><?= number_format(ec_count($pdo, $editCampaign['segment'] ?? 'all')) ?></strong>
                </div>
            </div>

            <div class="ec-personalise">
                <h4>Personalisation tokens</h4>
                <div>
                    <code>{{name}}</code> Full name<br>
                    <code>{{first_name}}</code> First name<br>
                    <code>{{email}}</code> Email address
                </div>
            </div>
        </div>

    </div>
</form>

<?php else: /* ═══════════════════ CAMPAIGN LIST ═══════════════════ */ ?>

<div class="ec-hero">
    <div>
        <p class="ec-kicker">Email Outreach</p>
        <h1>Campaigns.</h1>
        <p>Send targeted email campaigns to your users via Brevo.</p>
        <div class="ec-stats" style="margin-top:16px;">
            <div class="ec-stat">
                <strong><?= number_format($totalCampaigns) ?></strong>
                <span>Total campaigns</span>
            </div>
            <div class="ec-stat">
                <strong><?= number_format($totalSent) ?></strong>
                <span>Emails delivered</span>
            </div>
            <div class="ec-stat">
                <strong><?= $lastSentAt ? date('M d', strtotime($lastSentAt)) : '—' ?></strong>
                <span>Last campaign</span>
            </div>
            <div class="ec-stat">
                <strong><?= number_format(ec_count($pdo, 'all')) ?></strong>
                <span>Verified users</span>
            </div>
        </div>
    </div>
    <a class="ec-btn-primary" href="/jobmington/admin/email-campaigns.php?view=compose">
        <i class="fas fa-plus"></i> New campaign
    </a>
</div>

<div class="ec-table-wrap">
    <?php if (empty($campaigns)): ?>
        <div class="ec-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="4" width="20" height="16" rx="2"/>
                <polyline points="22,6 12,13 2,6"/>
            </svg>
            <h3>No campaigns yet</h3>
            <p>Create your first campaign to reach your users directly.</p>
        </div>
    <?php else: ?>
        <div class="jm-tablewrap">
        <table class="ec-table jm-stacktable">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Segment</th>
                    <th>Recipients</th>
                    <th>Delivered</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $campaign): ?>
                    <tr>
                        <td>
                            <strong style="font-size:14px;"><?= e($campaign['subject']) ?></strong>
                            <?php if ($campaign['preview_text']): ?>
                                <div class="ec-td-sub"><?= e($campaign['preview_text']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e(ec_segment_label($campaign['segment'])) ?></td>
                        <td><?= number_format($campaign['recipient_count']) ?></td>
                        <td>
                            <?php if ($campaign['status'] === 'sent' || $campaign['status'] === 'failed'): ?>
                                <span style="color:<?= $campaign['failed_count'] > 0 ? 'var(--admin-amber)' : 'var(--admin-green)' ?>; font-weight:700;">
                                    <?= number_format($campaign['sent_count']) ?>
                                </span>
                                <?php if ($campaign['failed_count'] > 0): ?>
                                    <span style="color:var(--admin-red);font-size:12px;"> / <?= number_format($campaign['failed_count']) ?> failed</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:var(--admin-muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="ec-badge <?= e($campaign['status']) ?>">
                                <?php if ($campaign['status'] === 'sent'): ?><i class="fas fa-check" style="font-size:9px;"></i><?php endif; ?>
                                <?php if ($campaign['status'] === 'sending'): ?><i class="fas fa-spinner fa-spin" style="font-size:9px;"></i><?php endif; ?>
                                <?php if ($campaign['status'] === 'draft'): ?><i class="fas fa-pencil" style="font-size:9px;"></i><?php endif; ?>
                                <?php if ($campaign['status'] === 'failed'): ?><i class="fas fa-exclamation" style="font-size:9px;"></i><?php endif; ?>
                                <?= ucfirst($campaign['status']) ?>
                            </span>
                        </td>
                        <td style="white-space:nowrap;font-size:13px;color:var(--admin-muted);">
                            <?= $campaign['sent_at']
                                ? date('M d, Y', strtotime($campaign['sent_at']))
                                : date('M d, Y', strtotime($campaign['created_at'])) ?>
                        </td>
                        <td style="white-space:nowrap;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                            <?php if ($campaign['status'] === 'draft'): ?>
                                <a class="ec-btn-secondary" style="padding:6px 12px;font-size:12px;"
                                   href="/jobmington/admin/email-campaigns.php?view=compose&id=<?= (int)$campaign['id'] ?>">
                                    <i class="fas fa-pencil"></i> Edit
                                </a>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="action" value="duplicate">
                                <input type="hidden" name="id" value="<?= (int)$campaign['id'] ?>">
                                <button class="ec-btn-secondary" type="submit" style="padding:6px 12px;font-size:12px;" title="Duplicate as new draft">
                                    <i class="fas fa-copy"></i> Reuse
                                </button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this campaign?')">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$campaign['id'] ?>">
                                <button class="ec-btn-danger" type="submit" style="padding:6px 12px;font-size:12px;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php endif; /* end view */ ?>
</div>

<script>
/* ── Custom email counter ────────────────────────────────────── */
function countCustomEmails() {
    const ta  = document.getElementById('ec-custom-emails');
    const out = document.getElementById('ec-custom-count');
    if (!ta || !out) return;
    const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const tokens  = ta.value.split(/[\r\n,;]+/).map(s => s.trim().toLowerCase()).filter(Boolean);
    const valid   = [...new Set(tokens)].filter(t => emailRe.test(t));
    out.textContent = valid.length ? valid.length + ' valid address' + (valid.length > 1 ? 'es' : '') : '';
}

/* ── Recipient count AJAX ────────────────────────────────────── */
function updateCount(seg) {
    fetch('/jobmington/admin/email-campaigns.php?ajax=count&segment=' + encodeURIComponent(seg))
        .then(r => r.json())
        .then(d => {
            const n = d.count.toLocaleString();
            const el = document.getElementById('ec-count');
            if (el) el.textContent = n + ' recipients';
            const sc = document.getElementById('ec-seg-count');
            if (sc) sc.textContent = n;
            const sl = document.getElementById('ec-seg-label');
            const labels = {
                all:'All verified users', seekers:'Job seekers only', employers:'Employers only',
                unverified:'Unverified accounts', new7:'Joined last 7 days', new30:'Joined last 30 days'
            };
            if (sl) sl.textContent = labels[seg] || seg;
        });
}

/* ── Poster upload handlers ──────────────────────────────────── */
function previewPoster(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('ec-poster-img').src = e.target.result;
        document.getElementById('ec-poster-preview').style.display = 'block';
        document.getElementById('ec-drop-zone').style.display = 'none';
        livePreview();
    };
    reader.readAsDataURL(input.files[0]);
}

function clearPoster() {
    document.getElementById('ec-poster-file').value = '';
    document.getElementById('ec-poster-preview').style.display = 'none';
    document.getElementById('ec-drop-zone').style.display = 'block';
    livePreview();
}

function handleDrop(event) {
    event.preventDefault();
    event.currentTarget.style.borderColor = 'var(--admin-line)';
    const file = event.dataTransfer.files[0];
    if (!file || !file.type.startsWith('image/')) return;
    const dt = new DataTransfer();
    dt.items.add(file);
    const input = document.getElementById('ec-poster-file');
    input.files = dt.files;
    previewPoster(input);
}

function togglePosterRemove(cb) {
    const uploadZone = document.getElementById('ec-poster-upload');
    const keepInput  = document.getElementById('ec-keep-poster');
    if (cb.checked) {
        uploadZone.style.display = 'block';
        keepInput.value = '';
    } else {
        uploadZone.style.display = 'none';
        keepInput.value = keepInput.dataset.orig || keepInput.value;
    }
    livePreview();
}

/* ── Live preview ────────────────────────────────────────────── */
function livePreview() {
    const body  = document.getElementById('ec-body');
    const frame = document.getElementById('ec-preview-frame');
    if (!body || !frame) return;

    const logo = 'https://jobmington.com/assets/images/badge.png';
    const ff   = "font-family:'Futura Cyrillic Book','Century Gothic','Trebuchet MS',Helvetica,Arial,sans-serif;";
    const ffd  = "font-family:'Futura Cyrillic Demi','Century Gothic','Trebuchet MS',Helvetica,Arial,sans-serif;";

    /* Poster: use uploaded preview OR existing URL */
    let posterHtml = '';
    const previewImg = document.getElementById('ec-poster-img');
    const keepPoster = document.getElementById('ec-keep-poster');
    const removeChk  = document.getElementById('ec-remove-poster');
    const posterSrc  = (previewImg && previewImg.src && previewImg.closest('#ec-poster-preview')?.style.display !== 'none')
        ? previewImg.src
        : (!removeChk?.checked && keepPoster?.value ? keepPoster.value : null);
    if (posterSrc) {
        posterHtml = `<tr><td style="padding:0;"><img src="${posterSrc}" alt="Poster" style="width:100%;display:block;"></td></tr>`;
    }

    const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 16px;">
<tr><td align="center">
  <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;">
    <tr><td style="background:#fff;padding:20px 28px;">
      <table cellpadding="0" cellspacing="0"><tr>
        <td style="padding-right:10px;vertical-align:middle;"><img src="${logo}" width="32" height="32"></td>
        <td style="vertical-align:middle;"><span style="${ffd}font-size:18px;font-weight:700;color:#06142a;">Jobmington</span><br>
          <span style="${ff}font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;">Simple hiring for African talent</span></td>
      </tr></table>
    </td></tr>
    <tr><td style="height:3px;background:#f59f22;"></td></tr>
    ${posterHtml}
    <tr><td style="${ff}padding:28px;font-size:14px;line-height:1.8;color:#374151;">${body.value || '<p style="color:#94a3b8;">Start typing your email body...</p>'}</td></tr>
    <tr><td style="padding:16px 28px;text-align:center;background:#f7f9fc;font-size:11px;color:#9ca3af;${ff}">
      &copy; ${new Date().getFullYear()} Jobmington &middot; <a href="#" style="color:#9ca3af;">Unsubscribe</a>
    </td></tr>
  </table>
</td></tr></table>
</body></html>`;

    frame.srcdoc = html;
}

/* ── Insert helpers ──────────────────────────────────────────── */
function insert(snippet) {
    const ta = document.getElementById('ec-body');
    if (!ta) return;
    const start = ta.selectionStart, end = ta.selectionEnd;
    ta.value = ta.value.substring(0, start) + snippet + ta.value.substring(end);
    ta.selectionStart = ta.selectionEnd = start + snippet.length;
    ta.focus();
    livePreview();
}

function ctaSnippet() {
    const url = prompt('CTA URL:', 'https://jobmington.com/jobs/');
    const lbl = prompt('Button label:', 'Browse open jobs');
    if (!url || !lbl) return '';
    return `<table cellpadding="0" cellspacing="0" style="margin:0 0 20px;">\n  <tr><td style="background:#0640a3;">\n    <a href="${url}" style="display:inline-block;padding:12px 24px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">${lbl}</a>\n  </td></tr>\n</table>\n`;
}

function insertJobsList() {
    insert(`<p style="font-weight:700;color:#06142a;margin:0 0 10px;">Featured roles this week:</p>\n<ul style="margin:0 0 20px;padding-left:18px;color:#475569;">\n  <li>Product Designer &mdash; Lagos, Remote</li>\n  <li>Backend Engineer &mdash; Nairobi, Remote</li>\n  <li>Marketing Lead &mdash; Accra</li>\n</ul>\n`);
}

/* init preview and custom count if editing a draft */
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('ec-body')?.value) livePreview();
    countCustomEmails();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
