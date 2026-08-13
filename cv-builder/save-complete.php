<?php
/**
 * JOBMINGTON - CV Save Handler
 * FIXED: Matches actual database schema
 * 
 * ACTUAL TABLE SCHEMAS:
 * cv_profiles: cv_id, user_id, title, template, full_name, email, phone, city, headline, summary, created_at, updated_at
 * cv_experience: id, cv_id, company, job_title, start_date, end_date, is_current, description
 * cv_education: id, cv_id, institution, degree, start_date, end_date, is_current, description
 * cv_skills: id, cv_id, skill_name, level (enum: Beginner, Intermediate, Advanced, Expert)
 */

header('Content-Type: application/json');
define('JOBMINGTON', true);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/tools.php';

Session::start();

if (!Session::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// The tool gate, after the sign-in check: a signed-out caller should be told
// they are signed out, not that a tool they cannot even see is locked.
jm_require_tool_api('cv_builder');

$userId = Session::userId();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('Invalid request data');
    }
    
    $cvId = (int)($data['cv_id'] ?? 0);
    if (!$cvId) {
        throw new Exception('CV ID required');
    }
    
    $pdo = db();
    $profileColumns = $pdo->query("SHOW COLUMNS FROM cv_profiles")->fetchAll(PDO::FETCH_COLUMN);
    
    // Verify ownership
    $stmt = $pdo->prepare("SELECT cv_id FROM cv_profiles WHERE cv_id = ? AND user_id = ?");
    $stmt->execute([$cvId, $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('CV not found or access denied');
    }
    
    // Create optional tables BEFORE starting transaction (DDL causes implicit commit)
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cv_certifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cv_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                issuing_organization VARCHAR(255),
                issue_date DATE,
                expiry_date DATE,
                credential_url VARCHAR(500),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cv_id (cv_id)
            ) ENGINE=InnoDB
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cv_languages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cv_id INT NOT NULL,
                language VARCHAR(100) NOT NULL,
                proficiency VARCHAR(50) DEFAULT 'professional',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cv_id (cv_id)
            ) ENGINE=InnoDB
        ");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS cv_projects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cv_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                role VARCHAR(255),
                url VARCHAR(500),
                technologies TEXT,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cv_id (cv_id)
            ) ENGINE=InnoDB
        ");
    } catch (Exception $e) {
        // Tables might already exist or fail - that's okay
    }
    
    $pdo->beginTransaction();
    
    // 1. Update personal info, only writing columns that exist in the current install.
    $personal = $data['personal'] ?? [];
    
    $updateFields = [];
    $updateValues = [];
    
    // Map form fields to actual database columns
    $fieldMap = [
        'full_name' => 'full_name',
        'headline' => 'headline',
        'email' => 'email',
        'phone' => 'phone',
        'summary' => 'summary',
        'linkedin_url' => 'linkedin_url',
        'portfolio_url' => 'portfolio_url',
        'github_url' => 'github_url'
    ];
    
    if (isset($personal['location'])) {
        if (in_array('location', $profileColumns, true)) {
            $updateFields[] = "location = ?";
            $updateValues[] = $personal['location'];
        } elseif (in_array('city', $profileColumns, true)) {
            $updateFields[] = "city = ?";
            $updateValues[] = $personal['location'];
        }
    }
    
    foreach ($fieldMap as $formKey => $dbColumn) {
        if (isset($personal[$formKey]) && in_array($dbColumn, $profileColumns, true)) {
            $updateFields[] = "$dbColumn = ?";
            $updateValues[] = $personal[$formKey];
        }
    }

    /*
     * The CV's own name, which had nowhere to be changed.
     *
     * It could only be set once, on the create form, and the editor showed it
     * as a plain heading. A builder whose whole point is keeping several CVs
     * for several kinds of role, where none of them can ever be renamed, makes
     * the list unreadable the moment there are three.
     */
    if (isset($data['title']) && in_array('title', $profileColumns, true)) {
        $title = trim((string) $data['title']);
        $updateFields[] = 'title = ?';
        // Not mb_strimwidth: no mbstring on this server. The /u regex counts
        // characters without it and will not cut through a multi-byte one.
        $updateValues[] = $title !== ''
            ? (preg_replace('/^(.{0,100}).*$/us', '$1', $title) ?? substr($title, 0, 100))
            : 'Untitled CV';
    }
    
    if (!empty($updateFields)) {
        $updateValues[] = $cvId;
        $sql = "UPDATE cv_profiles SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE cv_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);
    }
    
    // 2. Update Experience
    // Columns: id, cv_id, company, job_title, start_date, end_date, is_current, description
    $pdo->prepare("DELETE FROM cv_experience WHERE cv_id = ?")->execute([$cvId]);
    
    $experiences = $data['experience'] ?? [];
    foreach ($experiences as $exp) {
        if (empty($exp['job_title']) && empty($exp['company'])) continue;
        
        $stmt = $pdo->prepare("
            INSERT INTO cv_experience (cv_id, job_title, company, start_date, end_date, is_current, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $cvId,
            $exp['job_title'] ?? '',
            $exp['company'] ?? '',
            !empty($exp['start_date']) ? $exp['start_date'] . '-01' : null,
            !empty($exp['end_date']) ? $exp['end_date'] . '-01' : null,
            !empty($exp['is_current']) ? 1 : 0,
            $exp['description'] ?? ''
        ]);
    }
    
    // 3. Update Education
    // Columns: id, cv_id, institution, degree, start_date, end_date, is_current, description
    $pdo->prepare("DELETE FROM cv_education WHERE cv_id = ?")->execute([$cvId]);
    
    $education = $data['education'] ?? [];
    foreach ($education as $edu) {
        if (empty($edu['institution'])) continue;
        
        // Combine degree and field_of_study into description since those columns don't exist
        $description = '';
        if (!empty($edu['field_of_study'])) {
            $description .= "Field: " . $edu['field_of_study'];
        }
        if (!empty($edu['grade'])) {
            $description .= ($description ? " | " : "") . "Grade: " . $edu['grade'];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO cv_education (cv_id, institution, degree, start_date, end_date, is_current, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $cvId,
            $edu['institution'] ?? '',
            $edu['degree'] ?? '',
            !empty($edu['start_date']) ? $edu['start_date'] . '-01' : null,
            !empty($edu['end_date']) ? $edu['end_date'] . '-01' : null,
            0, // is_current
            $description
        ]);
    }
    
    // 4. Update Skills
    // Columns: id, cv_id, skill_name, level (enum: Beginner, Intermediate, Advanced, Expert)
    $pdo->prepare("DELETE FROM cv_skills WHERE cv_id = ?")->execute([$cvId]);
    
    $skills = $data['skills'] ?? [];
    foreach ($skills as $skill) {
        if (empty($skill['name'])) continue;
        
        $stmt = $pdo->prepare("
            INSERT INTO cv_skills (cv_id, skill_name)
            VALUES (?, ?)
        ");
        $stmt->execute([
            $cvId,
            $skill['name'],
        ]);
    }
    
    // 5. Certifications
    try {
        $pdo->prepare("DELETE FROM cv_certifications WHERE cv_id = ?")->execute([$cvId]);
        
        $certs = $data['certifications'] ?? [];
        foreach ($certs as $cert) {
            if (empty($cert['name'])) continue;
            
            $stmt = $pdo->prepare("
                INSERT INTO cv_certifications (cv_id, name, issuing_organization, issue_date, expiry_date, credential_url)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $cvId,
                $cert['name'] ?? '',
                $cert['issuing_organization'] ?? '',
                !empty($cert['issue_date']) ? $cert['issue_date'] . '-01' : null,
                !empty($cert['expiry_date']) ? $cert['expiry_date'] . '-01' : null,
                $cert['credential_url'] ?? ''
            ]);
        }
    } catch (Exception $e) {
        // Certifications table operation failed, skip
    }
    
    // 6. Languages
    try {
        $pdo->prepare("DELETE FROM cv_languages WHERE cv_id = ?")->execute([$cvId]);
        
        $languages = $data['languages'] ?? [];
        foreach ($languages as $lang) {
            if (empty($lang['language'])) continue;
            
            $stmt = $pdo->prepare("
                INSERT INTO cv_languages (cv_id, language, proficiency)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $cvId,
                $lang['language'],
                $lang['proficiency'] ?? 'professional'
            ]);
        }
    } catch (Exception $e) {
        // Languages table operation failed, skip
    }
    
    // 7. Projects
    try {
        $pdo->prepare("DELETE FROM cv_projects WHERE cv_id = ?")->execute([$cvId]);
        
        $projects = $data['projects'] ?? [];
        foreach ($projects as $proj) {
            if (empty($proj['name'])) continue;
            
            $stmt = $pdo->prepare("
                INSERT INTO cv_projects (cv_id, name, role, url, technologies, description)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $cvId,
                $proj['name'] ?? '',
                $proj['role'] ?? '',
                $proj['url'] ?? '',
                $proj['technologies'] ?? '',
                $proj['description'] ?? ''
            ]);
        }
    } catch (Exception $e) {
        // Projects table operation failed, skip
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'CV saved successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Save failed: ' . $e->getMessage()
    ]);
}
