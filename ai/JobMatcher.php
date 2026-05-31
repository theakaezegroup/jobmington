<?php
/**
 * JOBMINGTON - Andika AI Job Matcher
 * Intelligent job matching engine using weighted criteria
 */

class JobMatcher {
    
    private PDO $pdo;
    
    // Matching weights (must sum to 100)
    private const WEIGHTS = [
        'skills'     => 35,  // Skills overlap
        'title'      => 20,  // Headline vs job title
        'location'   => 15,  // Country match
        'experience' => 15,  // Experience level
        'category'   => 10,  // Job category
        'type'       => 5    // Job type preference
    ];
    
    // Common skill synonyms for fuzzy matching
    private const SKILL_SYNONYMS = [
        'javascript' => ['js', 'ecmascript', 'es6', 'es2015'],
        'typescript' => ['ts'],
        'python' => ['py', 'django', 'flask', 'fastapi'],
        'php' => ['laravel', 'symfony', 'wordpress'],
        'react' => ['reactjs', 'react.js'],
        'vue' => ['vuejs', 'vue.js'],
        'angular' => ['angularjs', 'angular.js'],
        'node' => ['nodejs', 'node.js', 'express'],
        'sql' => ['mysql', 'postgresql', 'postgres', 'mssql', 'sqlite'],
        'aws' => ['amazon web services', 'ec2', 's3', 'lambda'],
        'azure' => ['microsoft azure'],
        'gcp' => ['google cloud', 'google cloud platform'],
        'docker' => ['containerization', 'kubernetes', 'k8s'],
        'git' => ['github', 'gitlab', 'bitbucket', 'version control'],
        'machine learning' => ['ml', 'ai', 'deep learning', 'tensorflow', 'pytorch'],
        'data science' => ['data analysis', 'analytics', 'pandas', 'numpy'],
        'devops' => ['ci/cd', 'jenkins', 'github actions', 'deployment'],
        'frontend' => ['front-end', 'ui', 'ux', 'css', 'html'],
        'backend' => ['back-end', 'server-side', 'api'],
        'fullstack' => ['full-stack', 'full stack'],
        'mobile' => ['ios', 'android', 'react native', 'flutter', 'swift', 'kotlin'],
        'database' => ['db', 'mongodb', 'redis', 'nosql'],
        'agile' => ['scrum', 'kanban', 'sprint'],
        'project management' => ['pm', 'jira', 'trello', 'asana'],
        'marketing' => ['seo', 'sem', 'digital marketing', 'content marketing'],
        'design' => ['figma', 'sketch', 'adobe', 'photoshop', 'illustrator'],
        'accounting' => ['bookkeeping', 'quickbooks', 'xero', 'financial'],
        'sales' => ['crm', 'salesforce', 'hubspot', 'business development'],
    ];
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get top matching jobs for a user
     */
    public function getTopMatches(int $userId, int $limit = 20): array {
        // Get user profile data
        $profile = $this->getUserProfile($userId);
        if (!$profile) {
            return [];
        }
        
        // Get active jobs
        $jobs = $this->getActiveJobs();
        if (empty($jobs)) {
            return [];
        }
        
        // Calculate match scores
        $matches = [];
        foreach ($jobs as $job) {
            $score = $this->calculateMatchScore($profile, $job);
            if ($score['total'] >= 20) { // Minimum 20% match
                $matches[] = [
                    'job_id'       => $job['job_id'],
                    'title'        => $job['title'],
                    'company'      => $job['company_name'] ?? 'Company',
                    'location'     => $job['country_name'] ?? 'Remote',
                    'job_type'     => $job['job_type'],
                    'salary_range' => $this->formatSalary($job),
                    'posted_at'    => $job['posted_at'] ?? $job['created_at'] ?? null,
                    'score'        => $score['total'],
                    'reasons'      => $score['reasons'],
                    'breakdown'    => $score['breakdown']
                ];
            }
        }
        
        // Sort by score descending
        usort($matches, fn($a, $b) => $b['score'] - $a['score']);
        
        return array_slice($matches, 0, $limit);
    }
    
    /**
     * Get match score for a single job
     */
    public function getMatchScore(int $userId, int $jobId): ?array {
        $profile = $this->getUserProfile($userId);
        if (!$profile) return null;
        
        $stmt = $this->pdo->prepare("
            SELECT j.*, jc.name as category_name, jc.slug as category_slug,
                   c.name as country_name, c.iso_code as country_code, c.currency_symbol,
                   co.name as company_name
            FROM jobs j
            LEFT JOIN job_categories jc ON j.category_id = jc.category_id
            LEFT JOIN countries c ON j.country_id = c.country_id
            LEFT JOIN companies co ON j.company_id = co.company_id
            WHERE j.job_id = ? AND j.is_active = 1
        ");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) return null;
        
        return $this->calculateMatchScore($profile, $job);
    }
    
    /**
     * Core matching algorithm
     */
    private function calculateMatchScore(array $profile, array $job): array {
        $breakdown = [];
        $reasons = [];
        
        // 1. Skills Match (35%)
        $skillScore = $this->calculateSkillMatch($profile['skills'], $job);
        $breakdown['skills'] = $skillScore['score'];
        if ($skillScore['score'] > 50) {
            $reasons[] = $skillScore['count'] . ' skill' . ($skillScore['count'] > 1 ? 's' : '') . ' match';
        }
        
        // 2. Title Match (20%)
        $titleScore = $this->calculateTitleMatch($profile['headline'], $job['title']);
        $breakdown['title'] = $titleScore;
        if ($titleScore > 60) {
            $reasons[] = 'Role alignment';
        }
        
        // 3. Location Match (15%)
        $locationScore = $this->calculateLocationMatch($profile['country_id'], $job);
        $breakdown['location'] = $locationScore['score'];
        if ($locationScore['score'] > 80) {
            $reasons[] = $locationScore['reason'];
        }
        
        // 4. Experience Match (15%)
        $expScore = $this->calculateExperienceMatch($profile['experience_years'], $job);
        $breakdown['experience'] = $expScore;
        if ($expScore > 70) {
            $reasons[] = 'Experience fit';
        }
        
        // 5. Category Match (10%)
        $catScore = $this->calculateCategoryMatch($profile, $job);
        $breakdown['category'] = $catScore;
        
        // 6. Job Type Match (5%)
        $typeScore = $this->calculateTypeMatch($profile['job_type_pref'] ?? '', $job['job_type'] ?? '');
        $breakdown['type'] = $typeScore;
        
        // Calculate weighted total
        $total = 0;
        foreach (self::WEIGHTS as $key => $weight) {
            $total += ($breakdown[$key] / 100) * $weight;
        }
        
        return [
            'total'     => round($total),
            'breakdown' => $breakdown,
            'reasons'   => array_slice($reasons, 0, 3) // Top 3 reasons
        ];
    }
    
    /**
     * Skills matching with fuzzy logic
     */
    private function calculateSkillMatch(array $userSkills, array $job): array {
        if (empty($userSkills)) {
            return ['score' => 0, 'count' => 0];
        }
        
        // Extract keywords from job
        $jobText = strtolower(
            ($job['title'] ?? '') . ' ' .
            ($job['description'] ?? '') . ' ' .
            ($job['requirements'] ?? '')
        );
        
        $jobKeywords = $this->extractKeywords($jobText);
        
        // Normalize user skills
        $normalizedSkills = array_map('strtolower', array_column($userSkills, 'skill_name'));
        
        // Count matches including synonyms
        $matchCount = 0;
        foreach ($normalizedSkills as $skill) {
            if ($this->skillMatchesJob($skill, $jobKeywords)) {
                $matchCount++;
            }
        }
        
        // Score based on overlap percentage and absolute count
        $skillCount = count($normalizedSkills);
        $overlapPct = ($matchCount / max($skillCount, 1)) * 100;
        
        // Bonus for multiple matches
        $bonus = min($matchCount * 5, 30);
        $score = min(100, $overlapPct + $bonus);
        
        return ['score' => round($score), 'count' => $matchCount];
    }
    
    /**
     * Check if skill matches job keywords (including synonyms)
     */
    private function skillMatchesJob(string $skill, array $jobKeywords): bool {
        // Direct match
        if (in_array($skill, $jobKeywords)) {
            return true;
        }
        
        // Check synonyms
        foreach (self::SKILL_SYNONYMS as $primary => $synonyms) {
            if ($skill === $primary || in_array($skill, $synonyms)) {
                // Check if any synonym matches job
                if (in_array($primary, $jobKeywords)) {
                    return true;
                }
                foreach ($synonyms as $syn) {
                    if (in_array($syn, $jobKeywords)) {
                        return true;
                    }
                }
            }
        }
        
        // Partial match (skill contained in job keyword)
        foreach ($jobKeywords as $keyword) {
            if (strlen($skill) >= 3 && str_contains($keyword, $skill)) {
                return true;
            }
            if (strlen($keyword) >= 3 && str_contains($skill, $keyword)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract relevant keywords from text
     */
    private function extractKeywords(string $text): array {
        // Remove common words
        $stopwords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 
                      'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been',
                      'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
                      'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'need',
                      'our', 'your', 'we', 'you', 'they', 'them', 'their', 'this', 'that',
                      'these', 'those', 'work', 'working', 'job', 'role', 'position', 'team',
                      'company', 'experience', 'years', 'ability', 'skills', 'required',
                      'looking', 'seeking', 'join', 'opportunity'];
        
        // Split and clean
        $words = preg_split('/[\s,;:.\-\/\\\\()\[\]{}]+/', $text);
        $keywords = [];
        
        foreach ($words as $word) {
            $word = trim(strtolower($word));
            if (strlen($word) >= 2 && !in_array($word, $stopwords) && !is_numeric($word)) {
                $keywords[] = $word;
            }
        }
        
        return array_unique($keywords);
    }
    
    /**
     * Title/headline matching
     */
    private function calculateTitleMatch(string $headline, string $jobTitle): int {
        if (empty($headline) || empty($jobTitle)) {
            return 50; // Neutral score
        }
        
        $headline = strtolower($headline);
        $jobTitle = strtolower($jobTitle);
        
        // Extract key words
        $headlineWords = $this->extractKeywords($headline);
        $titleWords = $this->extractKeywords($jobTitle);
        
        // Calculate overlap
        $overlap = array_intersect($headlineWords, $titleWords);
        $overlapCount = count($overlap);
        
        if ($overlapCount >= 3) return 100;
        if ($overlapCount >= 2) return 80;
        if ($overlapCount >= 1) return 60;
        
        // Check for similar roles
        $roles = ['engineer', 'developer', 'designer', 'manager', 'analyst', 'specialist', 
                  'coordinator', 'lead', 'senior', 'junior', 'associate', 'director'];
        foreach ($roles as $role) {
            if (str_contains($headline, $role) && str_contains($jobTitle, $role)) {
                return 70;
            }
        }
        
        return 30;
    }
    
    /**
     * Location matching
     */
    private function calculateLocationMatch(?int $userCountryId, array $job): array {
        $jobCountryId = $job['country_id'] ?? null;
        $isRemote = strtolower($job['job_type'] ?? '') === 'remote';
        
        // Remote jobs are accessible anywhere
        if ($isRemote) {
            return ['score' => 90, 'reason' => 'Remote friendly'];
        }
        
        // Same country
        if ($userCountryId && $jobCountryId && $userCountryId === $jobCountryId) {
            return ['score' => 100, 'reason' => 'Same location'];
        }
        
        // No country specified = worldwide
        if (!$jobCountryId) {
            return ['score' => 80, 'reason' => 'Worldwide'];
        }
        
        // Different country
        return ['score' => 30, 'reason' => ''];
    }
    
    /**
     * Experience level matching
     */
    private function calculateExperienceMatch(int $userYears, array $job): int {
        // Try to infer required experience from job
        $text = strtolower(($job['title'] ?? '') . ' ' . ($job['description'] ?? '') . ' ' . ($job['requirements'] ?? ''));
        
        // Detect experience level keywords
        $isSenior = preg_match('/\b(senior|sr\.?|lead|principal|staff|architect)\b/', $text);
        $isMid = preg_match('/\b(mid|intermediate|regular)\b/', $text);
        $isJunior = preg_match('/\b(junior|jr\.?|entry|intern|graduate|trainee)\b/', $text);
        
        // Extract specific year requirements
        $requiredYears = 0;
        if (preg_match('/(\d+)\+?\s*years?/', $text, $matches)) {
            $requiredYears = (int) $matches[1];
        }
        
        // Calculate fit
        if ($requiredYears > 0) {
            $diff = abs($userYears - $requiredYears);
            if ($diff <= 1) return 100;
            if ($diff <= 2) return 80;
            if ($diff <= 3) return 60;
            if ($userYears > $requiredYears) return 70; // Overqualified
            return 40; // Underqualified
        }
        
        // Level-based matching
        if ($isSenior && $userYears >= 5) return 90;
        if ($isSenior && $userYears >= 3) return 70;
        if ($isMid && $userYears >= 2 && $userYears <= 6) return 90;
        if ($isJunior && $userYears <= 2) return 90;
        if ($isJunior && $userYears > 5) return 40; // Senior applying to junior
        
        // Default neutral
        return 60;
    }
    
    /**
     * Category matching
     */
    private function calculateCategoryMatch(array $profile, array $job): int {
        // Check if user's experience matches job category
        $jobCategory = strtolower($job['category_slug'] ?? '');
        
        if (empty($jobCategory)) {
            return 50;
        }
        
        // Check user's experience titles
        foreach ($profile['experience'] ?? [] as $exp) {
            $expTitle = strtolower($exp['job_title'] ?? '');
            if (str_contains($expTitle, $jobCategory) || str_contains($jobCategory, $expTitle)) {
                return 90;
            }
        }
        
        // Check headline
        $headline = strtolower($profile['headline'] ?? '');
        if (str_contains($headline, $jobCategory)) {
            return 80;
        }
        
        return 40;
    }
    
    /**
     * Job type preference matching
     */
    private function calculateTypeMatch(string $userPref, string $jobType): int {
        if (empty($userPref) || empty($jobType)) {
            return 50;
        }
        
        $userPref = strtolower($userPref);
        $jobType = strtolower($jobType);
        
        if ($userPref === $jobType) {
            return 100;
        }
        
        // Partial matches
        if ($userPref === 'remote' && str_contains($jobType, 'remote')) return 90;
        if ($userPref === 'full-time' && $jobType === 'full-time') return 100;
        
        return 40;
    }
    
    /**
     * Get user profile with all CV data
     */
    private function getUserProfile(int $userId): ?array {
        // Basic user info
        $stmt = $this->pdo->prepare("
            SELECT u.user_id, u.country_id, u.city,
                   cv.headline, cv.summary
            FROM users u
            LEFT JOIN cv_profiles cv ON u.user_id = cv.user_id
            WHERE u.user_id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return null;
        }
        
        // Get CV ID
        $stmt = $this->pdo->prepare("SELECT cv_id FROM cv_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $cvRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $cvId = $cvRow['cv_id'] ?? null;
        
        // Get skills
        $skills = [];
        if ($cvId) {
            $stmt = $this->pdo->prepare("SELECT skill_name FROM cv_skills WHERE cv_id = ?");
            $stmt->execute([$cvId]);
            $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Get experience
        $experience = [];
        $totalYears = 0;
        if ($cvId) {
            $stmt = $this->pdo->prepare("
                SELECT job_title, company, start_date, end_date, is_current
                FROM cv_experience WHERE cv_id = ? ORDER BY start_date DESC
            ");
            $stmt->execute([$cvId]);
            $experience = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate total years
            foreach ($experience as $exp) {
                $start = new DateTime($exp['start_date'] ?? 'now');
                $end = $exp['is_current'] ? new DateTime() : new DateTime($exp['end_date'] ?? 'now');
                $totalYears += max(0, $end->diff($start)->y);
            }
        }
        
        return [
            'user_id'          => $user['user_id'],
            'country_id'       => $user['country_id'],
            'city'             => $user['city'],
            'headline'         => $user['headline'] ?? '',
            'summary'          => $user['summary'] ?? '',
            'skills'           => $skills,
            'experience'       => $experience,
            'experience_years' => $totalYears,
            'job_type_pref'    => '' // Can be added to user preferences later
        ];
    }
    
    /**
     * Get all active jobs
     */
    private function getActiveJobs(): array {
        $stmt = $this->pdo->query("
            SELECT j.*, jc.name as category_name, jc.slug as category_slug,
                   c.name as country_name, c.iso_code as country_code, c.currency_symbol,
                   co.name as company_name
            FROM jobs j
            LEFT JOIN job_categories jc ON j.category_id = jc.category_id
            LEFT JOIN countries c ON j.country_id = c.country_id
            LEFT JOIN companies co ON j.company_id = co.company_id
            WHERE j.is_active = 1 
              AND (j.expires_at IS NULL OR j.expires_at >= CURDATE())
            ORDER BY j.posted_at DESC
            LIMIT 500
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Format salary for display
     */
    private function formatSalary(array $job): string {
        $min = $job['salary_min'] ?? null;
        $max = $job['salary_max'] ?? null;
        $currency = $job['salary_currency'] ?? $job['currency_symbol'] ?? 'USD';
        
        if (!$min && !$max) {
            return 'Competitive';
        }
        
        if ($min && $max) {
            return $currency . ' ' . number_format($min) . ' - ' . number_format($max);
        }
        
        return $currency . ' ' . number_format($min ?: $max);
    }
}
