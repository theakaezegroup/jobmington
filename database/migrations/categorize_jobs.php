http://localhost/jobmington/database/migrations/create_seeds_system.phphttp://localhost/jobmington/database/migrations/create_seeds_system.phphttp://localhost/jobmington/database/migrations/create_seeds_system.php<?php
/**
 * Auto-Categorize Jobs
 * Analyzes job titles and assigns appropriate categories
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = db();

echo "<div style='font-family: monospace; padding: 20px; background: #1e293b; color: #f8fafc;'>";
echo "<h2> Auto-Categorizing Jobs...</h2><hr>";

// Category keywords mapping
$categoryKeywords = [
    'technology-it' => [
        'developer', 'engineer', 'software', 'programmer', 'web', 'mobile', 'devops', 'backend', 
        'frontend', 'fullstack', 'full-stack', 'java', 'python', 'php', 'javascript', 'react', 
        'node', 'data scientist', 'data analyst', 'database', 'cloud', 'aws', 'azure', 
        'cybersecurity', 'security', 'network', 'it support', 'tech', 'system admin', 
        'sysadmin', 'linux', 'windows server', 'qa', 'quality assurance', 'tester', 'testing',
        'ui/ux', 'machine learning', 'ai', 'artificial intelligence', 'blockchain', 'crypto'
    ],
    'finance-accounting' => [
        'accountant', 'accounting', 'finance', 'financial', 'auditor', 'audit', 'banker', 
        'banking', 'tax', 'treasury', 'controller', 'bookkeeper', 'payroll', 'budget', 
        'investment', 'analyst', 'cfo', 'actuary', 'credit', 'loan', 'risk'
    ],
    'marketing-sales' => [
        'marketing', 'sales', 'salesperson', 'business development', 'brand', 'advertising',
        'digital marketing', 'seo', 'sem', 'social media', 'content marketing', 'growth',
        'account manager', 'account executive', 'b2b', 'b2c', 'retail', 'merchandiser',
        'trade marketing', 'campaign', 'pr', 'public relations'
    ],
    'healthcare' => [
        'doctor', 'nurse', 'nursing', 'medical', 'health', 'hospital', 'clinic', 'pharmacy',
        'pharmacist', 'optometrist', 'dentist', 'dental', 'surgeon', 'physician', 'therapist',
        'physiotherapy', 'lab technician', 'radiologist', 'cardiologist', 'pediatric',
        'mental health', 'counselor', 'caregiver', 'midwife', 'healthcare'
    ],
    'engineering' => [
        'civil engineer', 'mechanical', 'electrical', 'chemical engineer', 'structural',
        'project engineer', 'site engineer', 'maintenance', 'hvac', 'plumber', 'welder',
        'technician', 'draughtsman', 'cad', 'autocad', 'surveyor', 'quantity surveyor',
        'construction', 'architect', 'industrial engineer'
    ],
    'education-training' => [
        'teacher', 'instructor', 'professor', 'lecturer', 'tutor', 'trainer', 'training',
        'education', 'school', 'university', 'academic', 'curriculum', 'learning', 
        'coach', 'facilitator', 'e-learning', 'teaching'
    ],
    'customer-service' => [
        'customer service', 'customer support', 'call center', 'helpdesk', 'support agent',
        'client service', 'customer care', 'customer success', 'client relations',
        'service desk', 'front desk', 'receptionist'
    ],
    'human-resources' => [
        'hr', 'human resource', 'recruiter', 'recruiting', 'recruitment', 'talent',
        'people operations', 'hrbp', 'compensation', 'benefits', 'employee relations',
        'onboarding', 'workforce', 'staffing'
    ],
    'design-creative' => [
        'designer', 'graphic', 'ui', 'ux', 'visual', 'creative', 'art director', 
        'illustrator', 'animator', 'video', 'photographer', 'videographer', 
        'content creator', 'copywriter', 'editor', 'motion graphics'
    ],
    'legal' => [
        'lawyer', 'attorney', 'legal', 'counsel', 'paralegal', 'compliance', 
        'regulatory', 'litigation', 'contract', 'corporate law', 'solicitor'
    ],
    'operations-logistics' => [
        'operations', 'logistics', 'supply chain', 'warehouse', 'inventory', 
        'procurement', 'purchasing', 'shipping', 'driver', 'dispatch', 
        'fleet', 'distribution', 'import', 'export'
    ],
    'administrative' => [
        'admin', 'administrative', 'assistant', 'secretary', 'office manager',
        'executive assistant', 'clerk', 'data entry', 'receptionist', 'coordinator'
    ],
    'real-estate' => [
        'real estate', 'property', 'estate agent', 'realtor', 'facility',
        'building manager', 'leasing', 'land', 'construction manager'
    ],
    'media-communications' => [
        'journalist', 'reporter', 'editor', 'media', 'communications', 'broadcast',
        'radio', 'television', 'news', 'writer', 'correspondent', 'press'
    ],
    'hospitality-tourism' => [
        'hotel', 'hospitality', 'restaurant', 'chef', 'cook', 'waiter', 'waitress',
        'bartender', 'front office', 'housekeeping', 'concierge', 'travel', 
        'tourism', 'event', 'catering'
    ]
];

// Get all categories from database
$categories = $pdo->query("SELECT category_id, slug, name FROM job_categories")->fetchAll(PDO::FETCH_ASSOC);
$categoryMap = [];
foreach ($categories as $cat) {
    $categoryMap[$cat['slug']] = $cat['category_id'];
}

if (empty($categoryMap)) {
    echo " No categories found! Please run setup_full_schema.php first.<br>";
    echo "<a href='setup_full_schema.php' style='color: #60a5fa;'>Run Setup →</a>";
    echo "</div>";
    exit;
}

// Get all jobs
$jobs = $pdo->query("SELECT * FROM jobs")->fetchAll(PDO::FETCH_ASSOC);

if (empty($jobs)) {
    echo " No jobs found in database.<br>";
    echo "</div>";
    exit;
}

echo " Found " . count($jobs) . " jobs to categorize<br><hr>";

$updated = 0;
$uncategorized = [];

// Determine ID column name
$idColumn = isset($jobs[0]['job_id']) ? 'job_id' : 'id';

foreach ($jobs as $job) {
    $jobId = $job[$idColumn];
    $title = strtolower($job['title'] ?? '');
    $description = strtolower($job['description'] ?? '');
    $searchText = $title . ' ' . $description;
    
    $matchedCategory = null;
    $highestScore = 0;
    
    // Score each category based on keyword matches
    foreach ($categoryKeywords as $categorySlug => $keywords) {
        if (!isset($categoryMap[$categorySlug])) continue;
        
        $score = 0;
        foreach ($keywords as $keyword) {
            // Title matches are worth more
            if (strpos($title, $keyword) !== false) {
                $score += 10;
            }
            // Description matches
            if (strpos($searchText, $keyword) !== false) {
                $score += 3;
            }
        }
        
        if ($score > $highestScore) {
            $highestScore = $score;
            $matchedCategory = $categorySlug;
        }
    }
    
    if ($matchedCategory && $highestScore >= 5) {
        $categoryId = $categoryMap[$matchedCategory];
        
        try {
            $stmt = $pdo->prepare("UPDATE jobs SET category_id = ? WHERE {$idColumn} = ?");
            $stmt->execute([$categoryId, $jobId]);
            $updated++;
            
            $catName = '';
            foreach ($categories as $c) {
                if ($c['slug'] === $matchedCategory) {
                    $catName = $c['name'];
                    break;
                }
            }
            
            echo " <strong>{$job['title']}</strong> → <span style='color: #22c55e;'>{$catName}</span> (score: {$highestScore})<br>";
        } catch (Exception $e) {
            echo " Failed to update job #{$jobId}: {$e->getMessage()}<br>";
        }
    } else {
        // Default to Technology & IT if no match
        $defaultCatId = $categoryMap['technology-it'] ?? 1;
        
        try {
            $stmt = $pdo->prepare("UPDATE jobs SET category_id = ? WHERE {$idColumn} = ?");
            $stmt->execute([$defaultCatId, $jobId]);
            $uncategorized[] = $job['title'];
            echo " <strong>{$job['title']}</strong> → <span style='color: #94a3b8;'>Technology & IT (default)</span><br>";
        } catch (Exception $e) {
            echo " Failed to update job #{$jobId}<br>";
        }
    }
}

// Update category job counts
$pdo->exec("
    UPDATE job_categories jc 
    SET job_count = (
        SELECT COUNT(*) FROM jobs j WHERE j.category_id = jc.category_id AND j.is_active = 1
    )
");

echo "<hr>";
echo "<h3> Categorization Summary</h3>";
echo " Updated: <strong>{$updated}</strong> jobs<br>";
echo " Default category: <strong>" . count($uncategorized) . "</strong> jobs<br>";

// Show category distribution
echo "<hr><h3> Category Distribution</h3>";
$distribution = $pdo->query("
    SELECT jc.name, COUNT(j.job_id) as count 
    FROM job_categories jc 
    LEFT JOIN jobs j ON jc.category_id = j.category_id 
    GROUP BY jc.category_id 
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

echo "<table style='width: 100%; border-collapse: collapse; margin-top: 10px;'>";
echo "<tr style='border-bottom: 1px solid #334155;'><th style='text-align: left; padding: 8px;'>Category</th><th style='text-align: right; padding: 8px;'>Jobs</th></tr>";
foreach ($distribution as $row) {
    $bar = str_repeat('█', min($row['count'] * 2, 30));
    echo "<tr style='border-bottom: 1px solid #1e293b;'>";
    echo "<td style='padding: 8px;'>{$row['name']}</td>";
    echo "<td style='text-align: right; padding: 8px;'><span style='color: #60a5fa;'>{$bar}</span> {$row['count']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr><h3> Categorization Complete!</h3>";
echo "<a href='/jobmington/' style='background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; display: inline-block;'>View Site →</a>";
echo "</div>";
