<?php
/**
 * JOBMINGTON - Course System Setup
 * Creates courses tables and seeds with essential career courses
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/database.php';

$pdo = db();

echo "<!DOCTYPE html><html><head><title>Setup Courses</title></head><body>";
echo "<div style='font-family: monospace; padding: 20px; background: #0f172a; color: #f8fafc; min-height: 100vh;'>";
echo "<h1 style='color: #fbbf24;'> Setting Up Course System...</h1><hr style='border-color: #334155;'>";

try {
    // Disable foreign key checks for clean table recreation
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Drop existing tables if they exist (in reverse dependency order)
    $pdo->exec("DROP TABLE IF EXISTS course_reviews");
    $pdo->exec("DROP TABLE IF EXISTS certificates");
    $pdo->exec("DROP TABLE IF EXISTS module_progress");
    $pdo->exec("DROP TABLE IF EXISTS course_enrollments");
    $pdo->exec("DROP TABLE IF EXISTS course_modules");
    $pdo->exec("DROP TABLE IF EXISTS courses");
    $pdo->exec("DROP TABLE IF EXISTS course_categories");
    echo " Cleaned up existing tables<br>";

    // --- COURSE CATEGORIES TABLE ---
    $pdo->exec("
    CREATE TABLE course_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        icon VARCHAR(50) DEFAULT 'fa-book',
        description TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " course_categories table created<br>";

    // --- COURSES TABLE ---
    $pdo->exec("
    CREATE TABLE courses (
        course_id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        description TEXT,
        short_description VARCHAR(500),
        thumbnail VARCHAR(500),
        instructor_name VARCHAR(100),
        instructor_bio TEXT,
        course_type ENUM('video', 'article', 'certification', 'full-course', 'guide') DEFAULT 'video',
        external_url VARCHAR(500),
        is_external TINYINT(1) DEFAULT 0,
        duration_hours DECIMAL(5,1) DEFAULT 0,
        difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
        is_free TINYINT(1) DEFAULT 1,
        price DECIMAL(10,2) DEFAULT 0,
        seed_price INT DEFAULT 0,
        enrollment_count INT DEFAULT 0,
        completion_count INT DEFAULT 0,
        rating_avg DECIMAL(2,1) DEFAULT 0,
        rating_count INT DEFAULT 0,
        has_certificate TINYINT(1) DEFAULT 0,
        certificate_provider VARCHAR(100),
        is_published TINYINT(1) DEFAULT 1,
        is_featured TINYINT(1) DEFAULT 0,
        tags VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category_id),
        INDEX idx_published (is_published),
        INDEX idx_featured (is_featured),
        INDEX idx_external (is_external)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " courses table created<br>";

    // --- COURSE MODULES TABLE ---
    $pdo->exec("
    CREATE TABLE course_modules (
        module_id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        content TEXT,
        video_url VARCHAR(500),
        duration_minutes INT DEFAULT 0,
        sort_order INT DEFAULT 0,
        is_free_preview TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_course (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " course_modules table created<br>";

    // --- COURSE ENROLLMENTS TABLE ---
    $pdo->exec("
    CREATE TABLE course_enrollments (
        enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        progress INT DEFAULT 0,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_enrollment (user_id, course_id),
        INDEX idx_user (user_id),
        INDEX idx_course (course_id),
        INDEX idx_progress (progress)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " course_enrollments table created<br>";

    // --- MODULE PROGRESS TABLE ---
    $pdo->exec("
    CREATE TABLE module_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        module_id INT NOT NULL,
        is_completed TINYINT(1) DEFAULT 0,
        completed_at TIMESTAMP NULL,
        UNIQUE KEY unique_progress (user_id, module_id),
        INDEX idx_user (user_id),
        INDEX idx_module (module_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " module_progress table created<br>";

    // --- CERTIFICATES TABLE ---
    $pdo->exec("
    CREATE TABLE certificates (
        certificate_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        verification_code VARCHAR(50) NOT NULL UNIQUE,
        issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        pdf_path VARCHAR(500),
        INDEX idx_user (user_id),
        INDEX idx_course (course_id),
        INDEX idx_code (verification_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " certificates table created<br>";

    // --- COURSE REVIEWS TABLE ---
    $pdo->exec("
    CREATE TABLE course_reviews (
        review_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        rating TINYINT NOT NULL,
        review TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_review (user_id, course_id),
        INDEX idx_course (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo " course_reviews table created<br>";

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "<br><hr style='border-color: #334155;'>";
    echo "<h2 style='color: #fbbf24;'> Creating Course Categories...</h2>";

    // --- INSERT COURSE CATEGORIES ---
    $categories = [
        ['Career Development', 'career-development', 'fa-rocket', 'CV writing, interview skills, and career planning'],
        ['Office Skills', 'office-skills', 'fa-file-excel', 'Microsoft Office, Excel, Word, and productivity tools'],
        ['Digital Marketing', 'digital-marketing', 'fa-bullhorn', 'SEO, social media, Google ads, and online marketing'],
        ['Customer Service', 'customer-service', 'fa-headset', 'Customer support, communication, and service excellence'],
        ['Business & Entrepreneurship', 'business-entrepreneurship', 'fa-briefcase', 'Start and grow your own business'],
        ['Technology', 'technology', 'fa-laptop-code', 'Programming, web development, and tech skills'],
        ['Communication', 'communication', 'fa-comments', 'Public speaking, writing, and professional communication'],
    ];

    $catStmt = $pdo->prepare("INSERT IGNORE INTO course_categories (name, slug, icon, description) VALUES (?, ?, ?, ?)");
    foreach ($categories as $cat) {
        $catStmt->execute($cat);
    }
    echo " " . count($categories) . " course categories inserted<br>";

    // Get category IDs
    $catIds = $pdo->query("SELECT slug, id FROM course_categories")->fetchAll(PDO::FETCH_KEY_PAIR);

    echo "<br><hr style='border-color: #334155;'>";
    echo "<h2 style='color: #fbbf24;'> Seeding Essential Career Courses...</h2>";

    // --- INSERT THE 5 ESSENTIAL COURSES ---
    $courses = [
        [
            'category_id' => $catIds['career-development'],
            'title' => 'How to Write a Professional CV (Nigerian Standard)',
            'slug' => 'nigerian-professional-cv-writing',
            'description' => '<p>Learn how to write a CV that stands out to Nigerian employers and HR managers.</p>
<p>This comprehensive guide from Jobberman Nigeria covers:</p>
<ul>
<li>The Nigerian CV format that recruiters expect</li>
<li>How to structure your work experience</li>
<li>Writing a compelling professional summary</li>
<li>Cover letter templates that work</li>
<li>Common mistakes to avoid</li>
</ul>
<p><strong>Why this matters:</strong> Using advice from Jobberman, the leading Nigerian job platform, gives you the best chance with local HR managers who know exactly what they\'re looking for.</p>',
            'short_description' => 'Master the Nigerian CV format with expert guidance from Jobberman. Learn what local HR managers really want to see.',
            'thumbnail' => 'https://images.unsplash.com/photo-1586281380349-632531db7ed4?w=800',
            'instructor_name' => 'Jobberman Nigeria',
            'course_type' => 'guide',
            'external_url' => 'https://www.jobberman.com/discover/cv-writing',
            'is_external' => 1,
            'duration_hours' => 1,
            'difficulty' => 'beginner',
            'is_free' => 1,
            'has_certificate' => 0,
            'is_featured' => 1,
            'tags' => 'cv,resume,cover letter,job application,nigerian jobs,career'
        ],
        [
            'category_id' => $catIds['career-development'],
            'title' => 'Top 21 Interview Questions and Answers',
            'slug' => 'top-interview-questions-answers',
            'description' => '<p>Ace your next interview with this comprehensive guide to the most common interview questions.</p>
<p>Career expert Richard McMunn walks you through:</p>
<ul>
<li>"Tell me about yourself" - the perfect opening</li>
<li>Behavioral questions and the STAR method</li>
<li>"Why do you want this job?" - how to show genuine interest</li>
<li>Salary negotiation questions</li>
<li>"Do you have any questions for us?" - what to ask</li>
<li>Body language and confidence tips</li>
</ul>
<p><strong>Why this matters:</strong> This single video covers 90% of what fresh graduates face in interviews. Richard McMunn\'s techniques are used by candidates worldwide.</p>',
            'short_description' => 'Master the 21 most common interview questions with expert Richard McMunn. Perfect for fresh graduates and career changers.',
            'thumbnail' => 'https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?w=800',
            'instructor_name' => 'Richard McMunn (CareerVidz)',
            'course_type' => 'video',
            'external_url' => 'https://www.youtube.com/watch?v=5h-XvgSPSTc',
            'is_external' => 1,
            'duration_hours' => 0.5,
            'difficulty' => 'beginner',
            'is_free' => 1,
            'has_certificate' => 0,
            'is_featured' => 1,
            'tags' => 'interview,job interview,career,hiring,questions,preparation'
        ],
        [
            'category_id' => $catIds['office-skills'],
            'title' => 'Excel Tutorial for Beginners (Zero to Hero)',
            'slug' => 'excel-tutorial-beginners',
            'description' => '<p>Master the essential Excel skills every employer expects you to have.</p>
<p>Kevin Stratvert\'s beginner-friendly crash course covers:</p>
<ul>
<li>Navigating the Excel interface</li>
<li>Creating and formatting spreadsheets</li>
<li>Essential formulas (SUM, AVERAGE, IF)</li>
<li>Sorting and filtering data</li>
<li>Creating basic charts and graphs</li>
<li>Time-saving keyboard shortcuts</li>
</ul>
<p><strong>Why this matters:</strong> Almost every office job requires basic Excel skills. This 12-minute video is short enough not to scare beginners but detailed enough to be genuinely useful.</p>',
            'short_description' => 'Learn essential Excel skills in just 12 minutes. Perfect crash course for job seekers who need office skills fast.',
            'thumbnail' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=800',
            'instructor_name' => 'Kevin Stratvert',
            'course_type' => 'video',
            'external_url' => 'https://www.youtube.com/watch?v=LgXzzu68j7M',
            'is_external' => 1,
            'duration_hours' => 0.2,
            'difficulty' => 'beginner',
            'is_free' => 1,
            'has_certificate' => 0,
            'is_featured' => 1,
            'tags' => 'excel,microsoft,office,spreadsheet,data,office skills'
        ],
        [
            'category_id' => $catIds['customer-service'],
            'title' => 'Delivering Exceptional Customer Support',
            'slug' => 'hubspot-customer-support-certification',
            'description' => '<p>Get certified in customer service excellence with this free HubSpot Academy course.</p>
<p>This comprehensive certification covers:</p>
<ul>
<li>Customer service fundamentals</li>
<li>Building customer empathy</li>
<li>Communication best practices</li>
<li>Handling difficult situations</li>
<li>Creating customer delight</li>
<li>Using support tools effectively</li>
</ul>
<p><strong>Why this matters:</strong> This is a full free certification. After completion, you can legitimately put "HubSpot Certified in Customer Service" on your CV - a credential recognized by employers worldwide.</p>
<p><em> Includes official HubSpot certification badge</em></p>',
            'short_description' => 'Free HubSpot certification in customer service. Add "HubSpot Certified" to your CV upon completion!',
            'thumbnail' => 'https://images.unsplash.com/photo-1553775927-a071d5a6a39a?w=800',
            'instructor_name' => 'HubSpot Academy',
            'course_type' => 'certification',
            'external_url' => 'https://academy.hubspot.com/courses/delivering-exceptional-customer-support',
            'is_external' => 1,
            'duration_hours' => 3,
            'difficulty' => 'beginner',
            'is_free' => 1,
            'has_certificate' => 1,
            'certificate_provider' => 'HubSpot Academy',
            'is_featured' => 1,
            'tags' => 'customer service,support,hubspot,certification,communication,career'
        ],
        [
            'category_id' => $catIds['digital-marketing'],
            'title' => 'Fundamentals of Digital Marketing (Google)',
            'slug' => 'google-digital-marketing-fundamentals',
            'description' => '<p>Master digital marketing with this prestigious 40-hour certification from Google Digital Skills for Africa.</p>
<p>This comprehensive course covers all 26 modules:</p>
<ul>
<li>The online opportunity</li>
<li>Building your web presence</li>
<li>Search engine optimization (SEO)</li>
<li>Search engine marketing (SEM)</li>
<li>Social media marketing</li>
<li>Content marketing</li>
<li>Email marketing</li>
<li>Display advertising</li>
<li>Mobile marketing</li>
<li>Analytics and data insights</li>
<li>E-commerce fundamentals</li>
<li>International expansion</li>
</ul>
<p><strong>Why this matters:</strong> This is the most prestigious free digital marketing certificate in Africa right now. The Google Digital Skills certification is recognized by employers across the continent and opens doors to remote work opportunities globally.</p>
<p><em> Includes official Google certification</em></p>',
            'short_description' => 'The most prestigious free digital certificate in Africa. 40-hour Google certification covering all aspects of digital marketing.',
            'thumbnail' => 'https://images.unsplash.com/photo-1432888622747-4eb9a8f2c1d9?w=800',
            'instructor_name' => 'Google Digital Skills for Africa',
            'course_type' => 'full-course',
            'external_url' => 'https://skillshop.exceedlms.com/student/collection/1830706-fundamentals-of-digital-marketing',
            'is_external' => 1,
            'duration_hours' => 40,
            'difficulty' => 'beginner',
            'is_free' => 1,
            'has_certificate' => 1,
            'certificate_provider' => 'Google',
            'is_featured' => 1,
            'tags' => 'digital marketing,google,seo,social media,certification,side hustle,freelance'
        ],
    ];

    $courseStmt = $pdo->prepare("
        INSERT INTO courses (
            category_id, title, slug, description, short_description, thumbnail,
            instructor_name, course_type, external_url, is_external, duration_hours,
            difficulty, is_free, has_certificate, certificate_provider, is_featured, tags
        ) VALUES (
            :category_id, :title, :slug, :description, :short_description, :thumbnail,
            :instructor_name, :course_type, :external_url, :is_external, :duration_hours,
            :difficulty, :is_free, :has_certificate, :certificate_provider, :is_featured, :tags
        )
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            description = VALUES(description),
            short_description = VALUES(short_description),
            thumbnail = VALUES(thumbnail),
            external_url = VALUES(external_url),
            duration_hours = VALUES(duration_hours),
            is_featured = VALUES(is_featured)
    ");

    foreach ($courses as $course) {
        $courseStmt->execute([
            ':category_id' => $course['category_id'],
            ':title' => $course['title'],
            ':slug' => $course['slug'],
            ':description' => $course['description'],
            ':short_description' => $course['short_description'],
            ':thumbnail' => $course['thumbnail'],
            ':instructor_name' => $course['instructor_name'],
            ':course_type' => $course['course_type'],
            ':external_url' => $course['external_url'],
            ':is_external' => $course['is_external'],
            ':duration_hours' => $course['duration_hours'],
            ':difficulty' => $course['difficulty'],
            ':is_free' => $course['is_free'],
            ':has_certificate' => $course['has_certificate'],
            ':certificate_provider' => $course['certificate_provider'] ?? null,
            ':is_featured' => $course['is_featured'],
            ':tags' => $course['tags']
        ]);
        echo " Added: <strong>{$course['title']}</strong><br>";
    }

    echo "<br><hr style='border-color: #334155;'>";
    echo "<h2 style='color: #22c55e;'> Course System Setup Complete!</h2>";
    echo "<div style='background: #1e293b; padding: 20px; border-radius: 8px; margin-top: 20px;'>";
    echo "<h3 style='color: #fbbf24; margin-top: 0;'> Summary</h3>";
    echo "<ul style='color: #94a3b8; line-height: 2;'>";
    echo "<li> 7 database tables created</li>";
    echo "<li> " . count($categories) . " course categories added</li>";
    echo "<li> " . count($courses) . " essential career courses seeded</li>";
    echo "</ul>";
    echo "<h3 style='color: #fbbf24;'> Courses Added:</h3>";
    echo "<ol style='color: #e2e8f0; line-height: 2;'>";
    echo "<li><strong>CV Writing (Nigerian Standard)</strong> - Jobberman Guide</li>";
    echo "<li><strong>Interview Questions</strong> - CareerVidz Video</li>";
    echo "<li><strong>Excel Basics</strong> - Kevin Stratvert Video</li>";
    echo "<li><strong>Customer Service</strong> - HubSpot Certification </li>";
    echo "<li><strong>Digital Marketing</strong> - Google Certification </li>";
    echo "</ol>";
    echo "</div>";

    echo "<div style='margin-top: 30px;'>";
    echo "<a href='/Jobmington/learn/' style='display: inline-block; background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #0f172a; padding: 15px 30px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 16px;'> View Learning Academy →</a>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='background: #7f1d1d; padding: 20px; border-radius: 8px; color: #fecaca;'>";
    echo " Error: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
