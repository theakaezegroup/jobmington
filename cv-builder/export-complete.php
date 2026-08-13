<?php
/**
 * JOBMINGTON - World-Class Professional CV Export
 * ATS-Friendly, Beautifully Designed CV Template
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/tools.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireLogin();
jm_require_tool('cv_builder');

$pdo = db();
$userId = Session::userId();
$cvId = (int)($_GET['id'] ?? 0);

if (!$cvId) { header('Location: ' . SITE_URL . '/cv-builder/'); exit; }

// Fetch CV profile
$stmt = $pdo->prepare("SELECT * FROM cv_profiles WHERE cv_id = ? AND user_id = ?");
$stmt->execute([$cvId, $userId]);
$cv = $stmt->fetch();

if (!$cv) { header('Location: ' . SITE_URL . '/cv-builder/'); exit; }

// Fetch all sections
$experience = $pdo->prepare("SELECT * FROM cv_experience WHERE cv_id = ? ORDER BY is_current DESC, start_date DESC");
$experience->execute([$cvId]);
$experience = $experience->fetchAll();

$education = $pdo->prepare("SELECT * FROM cv_education WHERE cv_id = ? ORDER BY start_date DESC");
$education->execute([$cvId]);
$education = $education->fetchAll();

// Skills - handle missing sort_order column
try {
    $skills = $pdo->prepare("SELECT * FROM cv_skills WHERE cv_id = ? ORDER BY skill_name");
    $skills->execute([$cvId]);
    $skills = $skills->fetchAll();
} catch (Exception $e) { $skills = []; }

// Optional tables
try {
    $certifications = $pdo->prepare("SELECT * FROM cv_certifications WHERE cv_id = ? ORDER BY issue_date DESC");
    $certifications->execute([$cvId]);
    $certifications = $certifications->fetchAll();
} catch (Exception $e) { $certifications = []; }

try {
    $languages = $pdo->prepare("SELECT * FROM cv_languages WHERE cv_id = ?");
    $languages->execute([$cvId]);
    $languages = $languages->fetchAll();
} catch (Exception $e) { $languages = []; }

try {
    $projects = $pdo->prepare("SELECT * FROM cv_projects WHERE cv_id = ? ORDER BY start_date DESC");
    $projects->execute([$cvId]);
    $projects = $projects->fetchAll();
} catch (Exception $e) { $projects = []; }

// Use unique function names to avoid conflicts with functions.php
function cvFormatDate($date, $format = 'M Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

function cvFormatDateRange($start, $end, $isCurrent = false) {
    $startStr = cvFormatDate($start);
    if ($isCurrent) return $startStr . ' - Present';
    $endStr = cvFormatDate($end);
    return $startStr . ($endStr ? ' - ' . $endStr : '');
}

$proficiencyLabels = [
    'basic' => 'Basic',
    'conversational' => 'Conversational',
    'professional' => 'Professional',
    'fluent' => 'Fluent',
    'native' => 'Native'
];

// Get template (default to Obsidian)
$template = strtolower($cv['template'] ?? 'obsidian');
if (!in_array($template, ['obsidian', 'cybernetic', 'blueprint'])) {
    $template = 'obsidian';
}
$cvLocation = $cv['location'] ?? $cv['city'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-template="<?= $template ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    /*
     * This is the name the browser suggests when someone saves the print as a
     * PDF, so it is the name that lands in a recruiter's folder: "Ada Obi -
     * CV.pdf". CV, not Resume: the ad they are answering asked for a CV.
     */
    ?>
    <title><?= e($cv['full_name'] ?? 'My CV') ?> - CV</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /* ============================================
           TEMPLATE: EXECUTIVE
           ============================================ */
        [data-template="obsidian"] {
            --cv-header-bg: #ffffff;
            --cv-header-text: #061426;
            --cv-header-subtitle: #53667f;
            --cv-accent: #e88712;
            --cv-accent-light: rgba(232, 135, 18, 0.12);
            --cv-section-title: #061426;
            /* A tint, not the accent. A rule under every section title is
               structure, and structure should recede; the accent stays on the
               dot beside the title, where one spot of colour reads as a
               choice instead of a stripe. */
            --cv-section-border: #f3d4a3;
            --cv-company: #0640a3;
            --cv-skill-bg: #fff8ec;
            --cv-skill-border: #f3d4a3;
            --cv-skill-text: #7a4300;
            --cv-font: 'Inter', sans-serif;
        }
        
        /* ============================================
           TEMPLATE: MODERN
           ============================================ */
        [data-template="cybernetic"] {
            --cv-header-bg: linear-gradient(135deg, #0640a3 0%, #0f766e 100%);
            --cv-header-text: #ffffff;
            --cv-header-subtitle: #d7fff7;
            --cv-accent: #5eead4;
            --cv-accent-light: rgba(94, 234, 212, 0.13);
            --cv-section-title: #0640a3;
            /* Same reasoning as executive: a tint of the family, not the
               full-strength teal that also colours the company names. */
            --cv-section-border: #bde9df;
            --cv-company: #0f766e;
            --cv-skill-bg: #ecfdf8;
            --cv-skill-border: #bde9df;
            --cv-skill-text: #0f766e;
            --cv-font: 'Inter', sans-serif;
        }
        
        /* ============================================
           TEMPLATE: TECHNICAL
           ============================================ */
        [data-template="blueprint"] {
            --cv-header-bg: #f7faff;
            --cv-header-text: #061426;
            --cv-header-subtitle: #53667f;
            --cv-accent: #0640a3;
            --cv-accent-light: rgba(6, 64, 163, 0.1);
            --cv-section-title: #0640a3;
            --cv-section-border: #bfd0e5;
            --cv-company: #0640a3;
            --cv-skill-bg: #eff6ff;
            --cv-skill-border: #bfd0e5;
            --cv-skill-text: #0640a3;
            --cv-font: 'Inter', sans-serif;
        }
        
        body {
            font-family: var(--cv-font);
            font-size: 10pt;
            line-height: 1.5;
            color: #1f2937;
            background: #64748b;
            padding: 40px 20px;
        }
        
        .cv-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        /* Header */
        .cv-header {
            background: var(--cv-header-bg);
            color: var(--cv-header-text);
            padding: 40px 50px;
            position: relative;
            overflow: hidden;
        }
        
        .cv-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 120px;
            height: 100%;
            background: linear-gradient(135deg, transparent 40%, var(--cv-accent) 100%);
            opacity: 0.15;
        }
        
        /* Cybernetic template: add grid pattern */
        [data-template="cybernetic"] .cv-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 20px 20px;
            pointer-events: none;
        }
        
        /* Blueprint template: add technical corner marks */
        [data-template="blueprint"] .cv-header::after {
            content: '';
            position: absolute;
            top: 15px;
            right: 15px;
            width: 30px;
            height: 30px;
            border-top: 2px solid var(--cv-accent);
            border-right: 2px solid var(--cv-accent);
            opacity: 0.5;
        }
        
        .cv-name {
            font-size: 28pt;
            font-weight: 800;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
            position: relative;
        }
        
        [data-template="blueprint"] .cv-name {
            font-size: 24pt;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        .cv-headline {
            font-size: 12pt;
            font-weight: 500;
            margin-bottom: 20px;
            color: var(--cv-header-subtitle);
        }
        
        [data-template="cybernetic"] .cv-headline {
            font-size: 11pt;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        
        .contact-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 9pt;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0.95;
        }
        
        .contact-icon {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            color: var(--cv-accent);
        }
        
        /* Main Content */
        /*
         * The body's table wrapper, switched off. It earns its keep only in
         * the print block, where a table footer group is what repeats and
         * reserves the running strip on every sheet. Here it is flattened to
         * blocks so the preview lays out as if the table were not there.
         */
        .cv-sheet,
        .cv-sheet > tbody,
        .cv-sheet > tbody > tr,
        .cv-sheet > tbody > tr > td {
            display: block;
            width: auto;
            padding: 0;
            border: 0;
        }

        .cv-sheet-head,
        .cv-sheet-foot {
            display: none;
        }

        .cv-body {
            padding: 35px 50px;
        }

        /* Blueprint: add subtle grid background */
        [data-template="blueprint"] .cv-body {
            background-image: 
                linear-gradient(rgba(59, 130, 246, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59, 130, 246, 0.03) 1px, transparent 1px);
            background-size: 10px 10px;
        }
        
        .cv-section {
            margin-bottom: 28px;
        }
        
        .cv-section:last-child {
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 10pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--cv-section-title);
            border-bottom: 2px solid var(--cv-section-border);
            padding-bottom: 6px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-title::before {
            content: '';
            width: 4px;
            height: 4px;
            background: var(--cv-accent);
            border-radius: 50%;
        }
        
        /* Cybernetic: glowing section titles */
        [data-template="cybernetic"] .section-title {
            border-bottom: 1px solid var(--cv-section-border);
            padding-bottom: 8px;
            text-shadow: 0 0 20px var(--cv-accent-light);
        }
        
        [data-template="cybernetic"] .section-title::before {
            width: 6px;
            height: 6px;
            box-shadow: 0 0 8px var(--cv-accent);
        }
        
        /* Blueprint: technical section markers */
        [data-template="blueprint"] .section-title {
            font-family: 'Futura Cyrillic Demi';
            letter-spacing: 3px;
            font-size: 9pt;
        }
        
        [data-template="blueprint"] .section-title::before {
            content: '//';
            width: auto;
            height: auto;
            background: none;
            border-radius: 0;
            color: var(--cv-accent);
            font-weight: 700;
        }
        
        /* Entry Items */
        .entry {
            margin-bottom: 18px;
            page-break-inside: avoid;
        }
        
        .entry:last-child {
            margin-bottom: 0;
        }
        
        .entry-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
        }
        
        .entry-title {
            font-size: 11pt;
            font-weight: 700;
            color: #111827;
        }
        
        .entry-company {
            font-size: 10pt;
            font-weight: 600;
            color: var(--cv-company);
        }
        
        .entry-meta {
            font-size: 9pt;
            color: #6b7280;
            text-align: right;
            flex-shrink: 0;
        }
        
        [data-template="cybernetic"] .entry-meta {
            color: var(--cv-accent);
            font-weight: 500;
        }
        
        .entry-location {
            font-size: 9pt;
            color: #6b7280;
        }
        
        .entry-description {
            font-size: 9.5pt;
            color: #374151;
            line-height: 1.6;
            margin-top: 8px;
            white-space: pre-line;
        }
        
        /* Skills */
        .skills-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .skill-pill {
            background: var(--cv-skill-bg);
            border: 1px solid var(--cv-skill-border);
            color: var(--cv-skill-text);
            padding: 5px 12px;
            border-radius: 4px;
            font-size: 9pt;
            font-weight: 500;
        }
        
        [data-template="cybernetic"] .skill-pill {
            border-radius: 0;
            border: none;
            border-left: 2px solid var(--cv-accent);
        }
        
        [data-template="blueprint"] .skill-pill {
            border-radius: 0;
            font-family: 'Futura Cyrillic Demi';
            font-size: 8pt;
        }
        
        /* Languages */
        .lang-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        
        .lang-item {
            background: #f8fafc;
            border-left: 3px solid var(--cv-section-border);
            padding: 10px 14px;
        }
        
        [data-template="cybernetic"] .lang-item {
            background: var(--cv-accent-light);
            border-left-color: var(--cv-accent);
        }
        
        .lang-name {
            font-weight: 600;
            color: #111827;
        }
        
        .lang-level {
            font-size: 8.5pt;
            color: #6b7280;
        }
        
        /* Projects */
        .project-tech {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }
        
        .tech-tag {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            color: #92400e;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: 500;
        }
        
        /* Certifications */
        .cert-issuer {
            color: var(--cv-company);
            font-weight: 500;
            font-size: 9pt;
        }
        
        /* Summary */
        .summary-text {
            font-size: 10pt;
            color: #374151;
            line-height: 1.7;
        }

        /* Refined template polish */
        body {
            background: #e8eef6;
        }

        .cv-container {
            overflow: hidden;
            border: 1px solid rgba(15, 23, 42, 0.12);
            box-shadow: 0 18px 60px rgba(6, 20, 38, 0.18);
        }

        .cv-header {
            padding: 42px 52px 34px;
            border-bottom: 5px solid var(--cv-accent);
        }

        [data-template="obsidian"] .cv-header,
        [data-template="blueprint"] .cv-header {
            border-bottom-width: 4px;
        }

        /*
         * Executive has no wedge.
         *
         * A 180px diagonal gradient across the top-right corner, at 8% on a
         * white header, does not read as a tint. It reads as a chipped corner:
         * too faint to be a deliberate shape, too visible to ignore, and the
         * diagonal edge looks like damage rather than design.
         *
         * This is the template sold as refined, single-column, boardroom. A
         * clean white header with the accent rule beneath it is that. The
         * decoration was working against the only thing this template is for.
         */
        [data-template="obsidian"] .cv-header::before {
            display: none;
        }

        [data-template="blueprint"] .cv-header::before {
            width: 180px;
            background: linear-gradient(135deg, transparent 30%, var(--cv-accent) 100%);
            opacity: 0.08;
        }

        [data-template="cybernetic"] .cv-header::before {
            width: 190px;
            opacity: 0.22;
        }

        .cv-name {
            max-width: 760px;
            letter-spacing: -0.7px;
        }

        [data-template="blueprint"] .cv-name {
            font-family: 'Futura Cyrillic Demi';
            font-size: 25pt;
            letter-spacing: 0.5px;
        }

        .cv-headline {
            max-width: 640px;
        }

        [data-template="cybernetic"] .cv-headline {
            letter-spacing: 0.8px;
        }

        .contact-grid {
            gap: 10px;
        }

        .contact-item {
            min-height: 28px;
            padding: 5px 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
        }

        [data-template="obsidian"] .contact-item,
        [data-template="blueprint"] .contact-item {
            border-color: #d8e4f4;
            background: #f7faff;
        }

        .contact-icon {
            color: var(--cv-accent);
        }

        .cv-body {
            padding: 36px 52px 40px;
        }

        .section-title {
            border-bottom-width: 1px;
            padding-bottom: 8px;
            margin-bottom: 18px;
        }

        [data-template="obsidian"] .section-title {
            letter-spacing: 1px;
        }

        [data-template="cybernetic"] .section-title {
            text-shadow: none;
        }

        [data-template="blueprint"] .section-title {
            font-family: 'Futura Cyrillic Demi';
            letter-spacing: 1.6px;
        }

        .entry {
            padding-left: 14px;
            border-left: 2px solid #e5edf7;
        }

        [data-template="cybernetic"] .entry {
            border-left-color: rgba(15, 118, 110, 0.35);
        }

        [data-template="blueprint"] .entry {
            border-left-style: dashed;
        }

        .entry-title {
            letter-spacing: -0.1px;
        }

        .entry-description {
            color: #26384f;
        }

        .skill-pill {
            border-radius: 999px;
            padding: 6px 11px;
            font-size: 8.8pt;
        }

        [data-template="cybernetic"] .skill-pill {
            border: 1px solid var(--cv-skill-border);
            border-left: 3px solid var(--cv-accent);
            border-radius: 6px;
        }

        [data-template="blueprint"] .skill-pill {
            border-radius: 4px;
            font-family: 'Futura Cyrillic Demi';
        }

        .tech-tag {
            background: var(--cv-accent-light);
            border-color: var(--cv-skill-border);
            color: var(--cv-skill-text);
        }

        .lang-item {
            border-radius: 6px;
            background: #f7faff;
        }

        .cv-footer {
            background: #fbfdff;
        }

        /* The verification chip used to be declared twice, here and again 160
           lines further down, and the two disagreed. Whichever came last won,
           which is a coin toss every time either is edited, and is how it came
           to be white text on an invalid background. One declaration now, and
           it is the later one. */

        /* Print Styles - A4 exact */
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            
            .cv-container {
                box-shadow: none !important;
                border: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
            }
            
            .cv-header {
                background: var(--cv-header-bg) !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .cv-header::before {
                background: linear-gradient(135deg, transparent 40%, var(--cv-accent) 100%) !important;
                -webkit-print-color-adjust: exact !important;
            }
            
            .section-title {
                color: var(--cv-section-title) !important;
                border-color: var(--cv-section-border) !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            /*
             * No page margin, on purpose. The margin is the only place a
             * browser can draw its own header and footer, so a page with none
             * cannot be given the document URL and the date. Reserving the
             * running strip is the table's job instead, below.
             */
            @page {
                margin: 0;
                size: A4;
            }

            /*
             * Fixed, which is what makes the print engine paint it once per
             * sheet. It positions against the page rather than the text, so it
             * holds the foot of every page instead of drifting up to wherever
             * the writing stopped.
             */
            .cv-footer {
                position: fixed;
                bottom: 7mm;
                left: 0;
                right: 0;
                background: transparent;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /*
             * On paper the wrapper becomes a real table again, because a
             * table footer group is the one box a browser repeats at the foot
             * of every sheet and reserves height for. The strip is empty: it
             * exists to keep the body copy from running underneath the fixed
             * footer that gets painted into it. 16mm clears the 7mm offset
             * and the line itself with room to spare.
             */
            .cv-sheet {
                display: table;
                width: 100%;
                border-collapse: collapse;
            }

            .cv-sheet > tbody {
                display: table-row-group;
            }

            .cv-sheet > tbody > tr {
                display: table-row;
            }

            .cv-sheet > tbody > tr > td {
                display: table-cell;
                padding: 0;
                border: 0;
            }

            .cv-sheet-head {
                display: table-header-group;
            }

            .cv-sheet-foot {
                display: table-footer-group;
            }

            .cv-sheet-head td,
            .cv-sheet-foot td {
                padding: 0;
                border: 0;
            }

            /*
             * 14mm at the top, 16mm at the bottom. Slightly more at the foot
             * because a page with equal margins reads as bottom-heavy, and
             * because the running line lives down there.
             */
            .cv-head-space {
                height: 14mm;
            }

            .cv-foot-space {
                height: 16mm;
            }

            /*
             * The body's own top padding has to go, or page one would carry it
             * on top of the reserved strip while later pages carry only the
             * strip. Moving that space into the header group is what makes
             * every page start at the same height.
             */
            .cv-body {
                padding-top: 0;
            }
        }

        /*
         * Where the paper is allowed to break.
         *
         * There was one rule for this in the whole stylesheet, so a two-page CV
         * broke wherever the text happened to run out. A role could split with
         * the employer on one sheet and the dates on the next, and a section
         * heading could land as the final line of a page with nothing under it,
         * which reads as though the section is empty.
         *
         * These apply on screen as well as print. The screen preview is meant to
         * show what will come out of the printer, and a preview that paginates
         * differently is a preview of nothing.
         */
        .entry,
        .skill-pill,
        .cv-footer-brand {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        /* A heading stays with what it introduces. */
        .section-title {
            break-after: avoid;
            page-break-after: avoid;
        }

        /* And a section never starts on the last line of a sheet. */
        .cv-section {
            break-inside: auto;
            page-break-inside: auto;
        }

        /* Never one line of a paragraph alone at a page edge. */
        .entry-description,
        .cv-summary {
            orphans: 3;
            widows: 3;
        }
        
        /* Screen preview padding */
        @media screen {
            body {
                padding: 40px 20px;
            }
        }
        
        /* Action Bar */
        .action-bar {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 12px;
            z-index: 100;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
        }
        
        .btn-print {
            background: #0f172a;
            color: white;
        }
        
        .btn-print:hover {
            background: #1e293b;
            box-shadow: 0 0 0 2px var(--cv-accent);
        }
        
        .btn-back {
            background: white;
            color: #0f172a;
            border: 1px solid #d1d5db;
        }
        
        .btn-back:hover {
            background: #f9fafb;
            border-color: #0f172a;
        }
        
        /* Two Column Layout for small sections */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 768px) {
            .two-col {
                grid-template-columns: 1fr;
            }
            
            .lang-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        /*
         * The footer, on every printed sheet.
         *
         * Reduced to two things: whose CV this is, and where it came from. No
         * panel, no rule above it, no chip, no date. Something that repeats on
         * every page has to be quiet or it becomes the loudest element in a
         * document that belongs to somebody else, which is exactly why the blue
         * chip could not simply be duplicated.
         *
         * position: fixed is what makes it repeat: a fixed element is painted
         * once per sheet by the print engine. The same property is why it stays
         * at the foot of the page instead of drifting up to wherever the text
         * happened to stop.
         */
        .cv-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 0 50px;
            font-size: 7pt;
            color: #94a3b8;
            background: transparent;
        }

        .cv-footer-who {
            color: #94a3b8;
            letter-spacing: 0.1px;
        }

        .cv-footer-brand {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .cv-footer-logo {
            width: 11px;
            height: 11px;
            object-fit: contain;
            /* Printers drop images that look decorative unless told otherwise. */
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .cv-footer-brand a {
            color: #94a3b8;
            text-decoration: none;
        }

        
        @media print {
            .cv-footer {
                background: #f8fafc !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            /* Brand blue on paper too, not the template's accent. This mark
               says where the document came from, so it looks the same whichever
               of the three templates the CV is wearing. Forced, because a
               printer drops backgrounds by default and a white tick on a
               dropped blue would vanish into the page. */
            /* The mark and the address are the only things left down there, and
               a printer drops both by default because they read as decoration. */
            .cv-footer-who,
            .cv-footer-brand a {
                color: #94a3b8 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

<!-- Action Bar (hidden in print) -->
<div class="action-bar no-print">
    <a href="<?= SITE_URL ?>/cv-builder/editor-complete.php?id=<?= $cvId ?>" class="action-btn btn-back">
        &larr; Edit CV
    </a>
    <button onclick="window.print()" class="action-btn btn-print">
        <svg width="18" height="18" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd"/>
        </svg>
        Download / Print
    </button>
</div>

<div class="cv-container">
    
    <!-- Header -->
    <header class="cv-header">
        <h1 class="cv-name"><?= e($cv['full_name'] ?? 'Your Name') ?></h1>
        <?php if (!empty($cv['headline'])): ?>
        <p class="cv-headline"><?= e($cv['headline']) ?></p>
        <?php endif; ?>
        
        <div class="contact-grid">
            <?php if (!empty($cv['email'])): ?>
            <span class="contact-item">
                <svg class="contact-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                </svg>
                <?= e($cv['email']) ?>
            </span>
            <?php endif; ?>
            
            <?php if (!empty($cv['phone'])): ?>
            <span class="contact-item">
                <svg class="contact-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                </svg>
                <?= e($cv['phone']) ?>
            </span>
            <?php endif; ?>
            
            <?php if (!empty($cvLocation)): ?>
            <span class="contact-item">
                <svg class="contact-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                </svg>
                <?= e($cvLocation) ?>
            </span>
            <?php endif; ?>
            
            <?php if (!empty($cv['linkedin_url'])): ?>
            <span class="contact-item">
                <svg class="contact-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M4.98 3.5C4.98 4.88 3.87 6 2.5 6S0 4.88 0 3.5 1.12 1 2.5 1s2.48 1.12 2.48 2.5zM.22 8.02h4.56V20H.22V8.02zM7.12 8.02h4.37v1.64h.06c.61-1.15 2.1-2.36 4.32-2.36 4.62 0 5.47 3.04 5.47 6.99V20h-4.55v-5.06c0-1.21-.02-2.76-1.68-2.76-1.69 0-1.95 1.32-1.95 2.67V20H7.12V8.02z"/>
                </svg>
                <?= e($cv['linkedin_url']) ?>
            </span>
            <?php endif; ?>

            <?php if (!empty($cv['portfolio_url'])): ?>
            <span class="contact-item">
                <svg class="contact-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 10-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5.172 5.172a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd"/>
                </svg>
                <?= e($cv['portfolio_url']) ?>
            </span>
            <?php endif; ?>

            <?php if (!empty($cv['github_url'])): ?>
            <span class="contact-item">
                <svg class="contact-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 .5a10 10 0 00-3.16 19.49c.5.09.68-.22.68-.48v-1.7c-2.78.6-3.37-1.18-3.37-1.18-.45-1.14-1.1-1.44-1.1-1.44-.9-.62.07-.61.07-.61 1 .07 1.52 1.03 1.52 1.03.89 1.52 2.33 1.08 2.9.83.09-.64.35-1.08.63-1.33-2.22-.25-4.56-1.11-4.56-4.95 0-1.09.39-1.98 1.03-2.68-.1-.25-.45-1.27.1-2.64 0 0 .84-.27 2.75 1.02A9.56 9.56 0 0110 5.53c.85 0 1.7.11 2.5.34 1.9-1.29 2.74-1.02 2.74-1.02.55 1.37.2 2.39.1 2.64.64.7 1.03 1.59 1.03 2.68 0 3.85-2.34 4.7-4.57 4.95.36.31.68.92.68 1.86v2.76c0 .26.18.57.69.48A10 10 0 0010 .5z" clip-rule="evenodd"/>
                </svg>
                <?= e($cv['github_url']) ?>
            </span>
            <?php endif; ?>
        </div>
    </header>
    
    <?php
    /*
     * The body is wrapped in a table for one reason: a table footer group is
     * the only thing a browser repeats at the foot of every printed sheet AND
     * reserves room for, and room is what the running strip needs.
     *
     * The obvious way to reserve it would be a bottom page margin, but the
     * page margin is the one place a browser draws its own header and footer,
     * so giving the page a margin puts the document URL back on the paper.
     * That is the whole reason for the table: it buys a per-page strip
     * without buying the URL along with it.
     *
     * The table exists only on paper. On screen every part of it is set back
     * to display: block, so the preview is laid out exactly as it was.
     */
    ?>
    <table class="cv-sheet">
    <?php /* The reserved top margin. Same trick as the foot: a header group
             repeats on every sheet and holds height, which is what stops page
             two from starting hard against the paper edge. */ ?>
    <thead class="cv-sheet-head"><tr><td><div class="cv-head-space"></div></td></tr></thead>
    <tbody><tr><td>
    <main class="cv-body">

        <!-- Summary -->
        <?php if (!empty($cv['summary'])): ?>
        <section class="cv-section">
            <h2 class="section-title">Professional Summary</h2>
            <p class="summary-text"><?= nl2br(e($cv['summary'])) ?></p>
        </section>
        <?php endif; ?>
        
        <!-- Experience -->
        <?php if (!empty($experience)): ?>
        <section class="cv-section">
            <h2 class="section-title">Work Experience</h2>
            <?php foreach ($experience as $exp): ?>
            <article class="entry">
                <div class="entry-header">
                    <div>
                        <h3 class="entry-title"><?= e($exp['job_title']) ?></h3>
                        <p class="entry-company"><?= e($exp['company']) ?></p>
                    </div>
                    <div class="entry-meta">
                        <div><?= cvFormatDateRange($exp['start_date'], $exp['end_date'], $exp['is_current']) ?></div>
                    </div>
                </div>
                <?php if (!empty($exp['description'])): ?>
                <p class="entry-description"><?= e($exp['description']) ?></p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
        
        <!-- Education -->
        <?php if (!empty($education)): ?>
        <section class="cv-section">
            <h2 class="section-title">Education</h2>
            <?php foreach ($education as $edu): ?>
            <article class="entry">
                <div class="entry-header">
                    <div>
                        <h3 class="entry-title"><?= e($edu['degree']) ?></h3>
                        <p class="entry-company"><?= e($edu['institution']) ?></p>
                    </div>
                    <div class="entry-meta">
                        <div><?= cvFormatDateRange($edu['start_date'], $edu['end_date']) ?></div>
                    </div>
                </div>
                <?php if (!empty($edu['description'])): ?>
                <p class="entry-description"><?= e($edu['description']) ?></p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
        
        <!-- Projects -->
        <?php if (!empty($projects)): ?>
        <section class="cv-section">
            <h2 class="section-title">Projects</h2>
            <?php foreach ($projects as $proj): ?>
            <article class="entry">
                <div class="entry-header">
                    <div>
                        <h3 class="entry-title"><?= e($proj['name']) ?></h3>
                        <?php if (!empty($proj['role'])): ?>
                        <p class="entry-company"><?= e($proj['role']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($proj['url'])): ?>
                    <div class="entry-meta"><?= e($proj['url']) ?></div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($proj['description'])): ?>
                <p class="entry-description"><?= e($proj['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($proj['technologies'])): ?>
                <div class="project-tech">
                    <?php foreach (explode(',', $proj['technologies']) as $tech): ?>
                    <span class="tech-tag"><?= e(trim($tech)) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
        
        <!-- Skills -->
        <?php if (!empty($skills)): ?>
        <section class="cv-section">
            <h2 class="section-title">Skills</h2>
            <div class="skills-grid">
                <?php foreach ($skills as $skill): ?>
                <span class="skill-pill"><?= e($skill['skill_name']) ?></span>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- Two Column: Certifications & Languages -->
        <?php if (!empty($certifications) || !empty($languages)): ?>
        <div class="two-col">
            
            <?php if (!empty($certifications)): ?>
            <section class="cv-section">
                <h2 class="section-title">Certifications</h2>
                <?php foreach ($certifications as $cert): ?>
                <article class="entry">
                    <h3 class="entry-title"><?= e($cert['name']) ?></h3>
                    <?php if (!empty($cert['issuing_organization'])): ?>
                    <p class="cert-issuer"><?= e($cert['issuing_organization']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($cert['issue_date'])): ?>
                    <p class="entry-location">Issued <?= cvFormatDate($cert['issue_date']) ?></p>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>
            
            <?php if (!empty($languages)): ?>
            <section class="cv-section">
                <h2 class="section-title">Languages</h2>
                <div class="lang-grid" style="grid-template-columns: 1fr;">
                    <?php foreach ($languages as $lang): ?>
                    <div class="lang-item">
                        <div class="lang-name"><?= e($lang['language']) ?></div>
                        <div class="lang-level"><?= $proficiencyLabels[$lang['proficiency']] ?? 'Professional' ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            
        </div>
        <?php endif; ?>
        
    </main>
    </td></tr></tbody>
    <?php /* The reserved strip. Empty: the visible footer is painted into it. */ ?>
    <tfoot class="cv-sheet-foot"><tr><td><div class="cv-foot-space"></div></td></tr></tfoot>
    </table>

    <!-- Jobmington Branding Footer -->
    <?php
    /*
     * One strip at the foot of every printed sheet.
     *
     * It carries the person's name on the left, because recruiters print CVs
     * and the pages get separated on a desk, and a page two that does not say
     * whose it is gets binned. On the right it carries the mark and the
     * address, and nothing else: no panel, no rule, no chip, no date.
     *
     * A repeating element has to be quiet or it becomes the loudest thing in a
     * document that belongs to somebody else. That is the whole reason the
     * blue chip could not simply be duplicated.
     */
    $runningName = trim((string) ($cv['full_name'] ?? ''));
    ?>
    <footer class="cv-footer" aria-hidden="true">
        <span class="cv-footer-who"><?= e($runningName) ?></span>
        <span class="cv-footer-brand">
            <img src="<?= SITE_URL ?>/assets/images/badge.png?v=logo-8" alt="" class="cv-footer-logo">
            <a href="<?= SITE_URL ?>" target="_blank">jobmington.com</a>
        </span>
    </footer>
    <!-- JOBMINGTON_CV_BUILDER_EXPORT cv_id:<?= $cvId ?> user:<?= $userId ?> generated:<?= date('Y-m-d') ?> -->
    
</div>

</body>
</html>
