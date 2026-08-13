<?php
/**
 * JOBMINGTON - Professional CV Export
 * Redirects to the complete export with all sections
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/tools.php';

Session::start();
Session::requireLogin();
jm_require_tool('cv_builder');

$cvId = (int)($_GET['id'] ?? 0);

// Redirect to complete export
if ($cvId > 0) {
    header('Location: ' . SITE_URL . '/cv-builder/export-complete.php?id=' . $cvId);
} else {
    header('Location: ' . SITE_URL . '/cv-builder/');
}
exit;

// Legacy code below - kept for reference
require_once __DIR__ . '/../config/database.php';
$pdo = db();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= Security::escape($cv['full_name'] ?? 'My CV') ?> - Resume</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page { 
            margin: 0; 
            size: A4; 
        }
        
        * { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0;
        }
        
        body { 
            font-family: 'Futura Cyrillic Demi';
            background: #f3f2ef;
            color: #000000;
            line-height: 1.5;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .cv-container {
            max-width: 800px;
            margin: 20px auto;
            background: #ffffff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        /* Header Section - LinkedIn Style */
        .cv-header {
            background: linear-gradient(135deg, #0077b5 0%, #005885 100%);
            color: white;
            padding: 40px;
            position: relative;
        }
        
        .cv-name {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .cv-headline {
            font-size: 18px;
            font-weight: 500;
            opacity: 0.95;
            margin-bottom: 20px;
        }
        
        .cv-contact {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .cv-contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Main Content */
        .cv-body {
            padding: 40px;
        }
        
        .cv-section {
            margin-bottom: 35px;
        }
        
        .cv-section:last-child {
            margin-bottom: 0;
        }
        
        .cv-section-title {
            font-size: 20px;
            font-weight: 700;
            color: #000000;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #0077b5;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .cv-section-icon {
            width: 24px;
            height: 24px;
            color: #0077b5;
        }
        
        /* Summary */
        .cv-summary {
            font-size: 15px;
            color: #333;
            line-height: 1.7;
        }
        
        /* Experience & Education Items */
        .cv-item {
            margin-bottom: 25px;
            padding-left: 0;
        }
        
        .cv-item:last-child {
            margin-bottom: 0;
        }
        
        .cv-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        
        .cv-item-title {
            font-size: 16px;
            font-weight: 600;
            color: #000000;
        }
        
        .cv-item-subtitle {
            font-size: 15px;
            color: #0077b5;
            font-weight: 500;
            margin-top: 2px;
        }
        
        .cv-item-date {
            font-size: 14px;
            color: #666;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .cv-item-description {
            font-size: 14px;
            color: #444;
            line-height: 1.6;
            margin-top: 10px;
        }
        
        /* Skills */
        .cv-skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .cv-skill-tag {
            background: #e8f4f8;
            color: #0077b5;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid #0077b5;
        }
        
        /* Print Controls */
        .print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        
        .print-btn {
            background: #0077b5;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 24px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .print-btn:hover {
            background: #005885;
        }
        
        .print-btn-secondary {
            background: #666;
        }
        
        .print-btn-secondary:hover {
            background: #444;
        }
        
        /* Print Styles */
        @media print {
            body {
                background: white;
            }
            
            .cv-container {
                margin: 0;
                box-shadow: none;
                max-width: 100%;
            }
            
            .print-controls {
                display: none !important;
            }
            
            .cv-header {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

    <div class="print-controls">
        <a href="<?= SITE_URL ?>/cv-builder/editor-complete.php?id=<?= $cvId ?>" class="print-btn print-btn-secondary">
            ← Back to Editor
        </a>
        <button onclick="window.print()" class="print-btn">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
            </svg>
            Download PDF
        </button>
    </div>

    <div class="cv-container">
        
        <!-- HEADER -->
        <div class="cv-header">
            <div class="cv-name"><?= Security::escape($cv['full_name'] ?? '') ?></div>
            <div class="cv-headline"><?= Security::escape($cv['headline'] ?? '') ?></div>
            <div class="cv-contact">
                <?php if (!empty($cv['email'])): ?>
                <div class="cv-contact-item">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    <?= Security::escape($cv['email']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($cv['phone'])): ?>
                <div class="cv-contact-item">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                    <?= Security::escape($cv['phone']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($cv['city'])): ?>
                <div class="cv-contact-item">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                    <?= Security::escape($cv['city']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- BODY -->
        <div class="cv-body">
            
            <!-- SUMMARY -->
            <?php if (!empty($cv['summary'])): ?>
            <div class="cv-section">
                <div class="cv-section-title">
                    <svg class="cv-section-icon" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    About
                </div>
                <div class="cv-summary"><?= nl2br(Security::escape($cv['summary'])) ?></div>
            </div>
            <?php endif; ?>
            
            <!-- EXPERIENCE -->
            <?php if (!empty($experience)): ?>
            <div class="cv-section">
                <div class="cv-section-title">
                    <svg class="cv-section-icon" fill="currentColor" viewBox="0 0 24 24"><path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/></svg>
                    Experience
                </div>
                <?php foreach ($experience as $exp): ?>
                <div class="cv-item">
                    <div class="cv-item-header">
                        <div>
                            <div class="cv-item-title"><?= Security::escape($exp['job_title'] ?? '') ?></div>
                            <div class="cv-item-subtitle"><?= Security::escape($exp['company'] ?? '') ?></div>
                        </div>
                        <div class="cv-item-date">
                            <?php 
                            $startDate = !empty($exp['start_date']) ? date('M Y', strtotime($exp['start_date'])) : '';
                            $endDate = !empty($exp['is_current']) ? 'Present' : (!empty($exp['end_date']) ? date('M Y', strtotime($exp['end_date'])) : '');
                            echo $startDate . ($startDate && $endDate ? ' - ' : '') . $endDate;
                            ?>
                        </div>
                    </div>
                    <?php if (!empty($exp['description'])): ?>
                    <div class="cv-item-description"><?= nl2br(Security::escape($exp['description'])) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- EDUCATION -->
            <?php if (!empty($education)): ?>
            <div class="cv-section">
                <div class="cv-section-title">
                    <svg class="cv-section-icon" fill="currentColor" viewBox="0 0 24 24"><path d="M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82zM12 3L1 9l11 6 9-4.91V17h2V9L12 3z"/></svg>
                    Education
                </div>
                <?php foreach ($education as $edu): ?>
                <div class="cv-item">
                    <div class="cv-item-header">
                        <div>
                            <div class="cv-item-title"><?= Security::escape($edu['degree'] ?? '') ?></div>
                            <div class="cv-item-subtitle"><?= Security::escape($edu['institution'] ?? '') ?></div>
                        </div>
                        <div class="cv-item-date">
                            <?php 
                            $startDate = !empty($edu['start_date']) ? date('Y', strtotime($edu['start_date'])) : '';
                            $endDate = !empty($edu['end_date']) ? date('Y', strtotime($edu['end_date'])) : '';
                            echo $startDate . ($startDate && $endDate ? ' - ' : '') . $endDate;
                            ?>
                        </div>
                    </div>
                    <?php if (!empty($edu['description'])): ?>
                    <div class="cv-item-description"><?= nl2br(Security::escape($edu['description'])) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- SKILLS -->
            <?php if (!empty($skills)): ?>
            <div class="cv-section">
                <div class="cv-section-title">
                    <svg class="cv-section-icon" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l-5.5 9h11L12 2zm0 3.84L13.93 9h-3.87L12 5.84zM17.5 13c-2.49 0-4.5 2.01-4.5 4.5s2.01 4.5 4.5 4.5 4.5-2.01 4.5-4.5-2.01-4.5-4.5-4.5zm0 7c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5zM3 21.5h8v-8H3v8zm2-6h4v4H5v-4z"/></svg>
                    Skills
                </div>
                <div class="cv-skills-list">
                    <?php foreach ($skills as $skill): ?>
                    <span class="cv-skill-tag"><?= Security::escape($skill['skill_name'] ?? '') ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </div>

<?php if (isset($_GET['print'])): ?>
<script>
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 500);
    };
</script>
<?php endif; ?>

</body>
</html>
