<?php
if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

function jm_job_location(array $job): string {
    $parts = [];
    if (!empty($job['city'])) {
        $parts[] = $job['city'];
    }
    if (!empty($job['country_name'])) {
        $parts[] = $job['country_name'];
    }
    if (empty($parts) && !empty($job['original_location'])) {
        $parts[] = $job['original_location'];
    }
    return $parts ? implode(', ', $parts) : 'Remote';
}

function jm_job_salary(array $job): string {
    if (isset($job['show_salary']) && (int) $job['show_salary'] === 0) {
        return 'Salary not listed';
    }

    $min = $job['salary_min'] !== null && $job['salary_min'] !== '' ? (float) $job['salary_min'] : null;
    $max = $job['salary_max'] !== null && $job['salary_max'] !== '' ? (float) $job['salary_max'] : null;

    if ($min === null && $max === null) {
        return 'Salary not listed';
    }

    return formatSalaryRange($min, $max, $job['currency_symbol'] ?? null);
}

function jm_job_add_tag(array &$tags, string $label, string $tone = 'neutral'): void {
    $label = trim($label);
    if ($label === '') {
        return;
    }

    foreach ($tags as $tag) {
        if (strcasecmp($tag['label'], $label) === 0) {
            return;
        }
    }

    $tags[] = ['label' => $label, 'tone' => $tone];
}

function jm_job_tags(array $job, int $limit = 0): array {
    $tags = [];

    if (!empty($job['is_featured'])) {
        jm_job_add_tag($tags, 'Featured', 'orange');
    }

    if (!empty($job['job_type'])) {
        $tone = $job['job_type'] === 'Remote' ? 'green' : 'blue';
        jm_job_add_tag($tags, $job['job_type'], $tone);
    }

    if (!empty($job['experience_level'])) {
        jm_job_add_tag($tags, $job['experience_level'] . ' level', 'neutral');
    }

    if (!empty($job['category_name'])) {
        jm_job_add_tag($tags, $job['category_name'], 'blue');
    }

    if (jm_job_salary($job) !== 'Salary not listed') {
        jm_job_add_tag($tags, 'Salary listed', 'green');
    }

    if (!empty($job['source']) && strcasecmp($job['source'], 'Jobmington') !== 0) {
        jm_job_add_tag($tags, 'Curated', 'neutral');
    }

    return $limit > 0 ? array_slice($tags, 0, $limit) : $tags;
}

function jm_job_plain_text(?string $content): string {
    $text = html_entity_decode((string) $content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text);
    $text = preg_replace('/<\/(p|div|li|h[1-6])>/i', "\n", $text);
    $text = strip_tags($text);
    $text = preg_replace("/\r\n?/", "\n", $text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", trim($text));

    return trim($text);
}

function jm_job_content_html(?string $content): string {
    $text = jm_job_plain_text($content);
    if ($text === '') {
        return '<div class="jm-rich-text"><p>Details will be shared by the employer during application.</p></div>';
    }

    $blocks = preg_split("/\n{2,}/", $text);
    $html = '';

    foreach ($blocks as $block) {
        $lines = array_values(array_filter(array_map('trim', preg_split("/\n+/", $block)), static function ($line) {
            return $line !== '';
        }));

        if (!$lines) {
            continue;
        }

        $listItems = [];
        $allListItems = count($lines) > 1;
        foreach ($lines as $line) {
            if (preg_match('/^(?:[-*]|\d+[.)]|\x{2022})\s+(.+)/u', $line, $matches)) {
                $listItems[] = $matches[1];
            } else {
                $allListItems = false;
                break;
            }
        }

        if ($allListItems) {
            $html .= '<ul>';
            foreach ($listItems as $item) {
                $html .= '<li>' . e($item) . '</li>';
            }
            $html .= '</ul>';
            continue;
        }

        $html .= '<p>' . implode('<br>', array_map('e', $lines)) . '</p>';
    }

    return '<div class="jm-rich-text">' . $html . '</div>';
}

function jm_job_match_alias_map(): array {
    return [
        'accounting' => ['accounting', 'bookkeeping', 'payroll', 'quickbooks', 'xero'],
        'administration' => ['administration', 'admin assistant', 'office management', 'clerical'],
        'api integration' => ['api', 'rest api', 'graphql', 'integration'],
        'business development' => ['business development', 'partnerships', 'lead generation'],
        'content writing' => ['content writing', 'copywriting', 'blog writing', 'storytelling'],
        'crm' => ['crm', 'hubspot', 'salesforce', 'zoho'],
        'creative strategy' => ['creative strategy', 'creative direction', 'ad creative', 'paid social creative', 'creative lead'],
        'css' => ['css', 'tailwind', 'bootstrap'],
        'customer service' => ['customer service', 'customer support', 'client support'],
        'data analysis' => ['data analysis', 'analytics', 'power bi', 'tableau', 'reporting'],
        'digital marketing' => ['digital marketing', 'paid ads', 'paid social', 'paid media', 'media buying', 'performance marketing', 'social media marketing', 'meta ads', 'facebook ads', 'tiktok ads', 'campaigns'],
        'excel' => ['excel', 'spreadsheet', 'google sheets'],
        'figma' => ['figma', 'product design', 'wireframe', 'prototype'],
        'finance' => ['finance', 'financial analysis', 'budgeting'],
        'html' => ['html'],
        'human resources' => ['human resources', 'hr', 'recruitment', 'talent acquisition'],
        'javascript' => ['javascript', 'typescript', 'react', 'vue', 'node.js', 'node js'],
        'mysql' => ['mysql', 'sql', 'database', 'mariadb', 'postgresql'],
        'operations' => ['operations', 'process improvement', 'logistics'],
        'php' => ['php', 'laravel', 'symfony', 'wordpress'],
        'project management' => ['project management', 'scrum', 'agile', 'kanban'],
        'python' => ['python', 'django', 'flask'],
        'remote collaboration' => ['remote', 'distributed team', 'slack', 'zoom'],
        'sales' => ['sales', 'account executive', 'closing', 'pipeline'],
        'seo' => ['seo', 'search engine optimization'],
        'sql' => ['sql', 'queries', 'database reporting'],
        'ui design' => ['ui design', 'interface design', 'design system'],
        'ux research' => ['ux research', 'user research', 'usability testing'],
        'writing' => ['writing', 'editing', 'documentation'],
    ];
}

function jm_job_match_stopwords(): array {
    return [
        'about' => true, 'after' => true, 'also' => true, 'and' => true, 'are' => true, 'can' => true,
        'company' => true, 'candidate' => true, 'daily' => true, 'for' => true, 'from' => true,
        'have' => true, 'job' => true, 'jobs' => true, 'join' => true, 'lead' => true, 'looking' => true, 'must' => true,
        'our' => true, 'paid' => true, 'role' => true, 'social' => true, 'team' => true, 'that' => true, 'the' => true, 'this' => true,
        'to' => true, 'with' => true, 'work' => true, 'will' => true, 'you' => true, 'your' => true,
    ];
}

function jm_job_match_text_has(string $text, string $needle): bool {
    $needle = strtolower(trim($needle));
    if ($needle === '') {
        return false;
    }

    $pattern = '/(^|[^a-z0-9+#.])' . preg_quote($needle, '/') . '([^a-z0-9+#.]|$)/i';
    return (bool) preg_match($pattern, $text);
}

function jm_job_match_skill_label(string $skill): string {
    $special = [
        'api integration' => 'API integration',
        'crm' => 'CRM',
        'css' => 'CSS',
        'html' => 'HTML',
        'mysql' => 'MySQL',
        'php' => 'PHP',
        'seo' => 'SEO',
        'sql' => 'SQL',
        'ui design' => 'UI design',
        'ux research' => 'UX research',
    ];

    $key = strtolower(trim($skill));
    return $special[$key] ?? ucwords($key);
}

function jm_job_match_keywords(array $job, int $limit = 12): array {
    $pieces = [
        $job['title'] ?? '',
        $job['category_name'] ?? '',
        $job['description'] ?? '',
        $job['requirements'] ?? '',
        $job['benefits'] ?? '',
    ];
    $text = strtolower(jm_job_plain_text(implode("\n", $pieces)));
    $found = [];

    foreach (jm_job_match_alias_map() as $skill => $aliases) {
        foreach (array_unique(array_merge([$skill], $aliases)) as $alias) {
            if (jm_job_match_text_has($text, $alias)) {
                $found[$skill] = jm_job_match_skill_label($skill);
                break;
            }
        }
    }

    if (count($found) < 4) {
        $fallbackText = strtolower((string) (($job['title'] ?? '') . ' ' . ($job['category_name'] ?? '')));
        $words = preg_split('/[^a-z0-9+#.]+/i', $fallbackText) ?: [];
        $stopwords = jm_job_match_stopwords();

        foreach ($words as $word) {
            $word = strtolower(trim($word));
            if (strlen($word) < 3 || isset($stopwords[$word]) || is_numeric($word)) {
                continue;
            }
            $found[$word] = jm_job_match_skill_label($word);
            if (count($found) >= $limit) {
                break;
            }
        }
    }

    return array_slice($found, 0, $limit, true);
}

function jm_job_cv_snapshot(PDO $pdo, ?int $userId): array {
    $empty = [
        'has_cv' => false,
        'cv_id' => null,
        'title' => '',
        'headline' => '',
        'summary' => '',
        'city' => '',
        'country_id' => null,
        'skills' => [],
        'experience' => [],
        'experience_years' => 0,
        'text' => '',
    ];

    if (!$userId) {
        return $empty;
    }

    try {
        $stmt = $pdo->prepare("SELECT user_id, country_id, city FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch() ?: [];
    } catch (Throwable $e) {
        $user = [];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM cv_profiles
            WHERE user_id = ?
            ORDER BY updated_at DESC, created_at DESC, cv_id DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $cv = $stmt->fetch();
    } catch (Throwable $e) {
        $cv = false;
    }

    if (!$cv) {
        $empty['country_id'] = $user['country_id'] ?? null;
        $empty['city'] = $user['city'] ?? '';
        return $empty;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM cv_skills WHERE cv_id = ? ORDER BY skill_name");
        $stmt->execute([$cv['cv_id']]);
        $skillRows = $stmt->fetchAll();
    } catch (Throwable $e) {
        $skillRows = [];
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM cv_experience WHERE cv_id = ? ORDER BY is_current DESC, start_date DESC");
        $stmt->execute([$cv['cv_id']]);
        $experienceRows = $stmt->fetchAll();
    } catch (Throwable $e) {
        $experienceRows = [];
    }

    $skills = [];
    foreach ($skillRows as $row) {
        $name = trim((string) ($row['skill_name'] ?? ''));
        if ($name !== '') {
            $skills[] = $name;
        }
    }

    $experienceYears = 0.0;
    $textParts = [
        $cv['title'] ?? '',
        $cv['headline'] ?? '',
        $cv['summary'] ?? '',
        implode(' ', $skills),
    ];

    foreach ($experienceRows as $row) {
        $textParts[] = $row['job_title'] ?? '';
        $textParts[] = $row['company'] ?? '';
        $textParts[] = $row['description'] ?? '';

        if (!empty($row['start_date'])) {
            try {
                $start = new DateTimeImmutable($row['start_date']);
                $end = !empty($row['end_date']) && empty($row['is_current'])
                    ? new DateTimeImmutable($row['end_date'])
                    : new DateTimeImmutable('now');
                $months = (($end->format('Y') - $start->format('Y')) * 12) + ($end->format('n') - $start->format('n'));
                if ($months > 0) {
                    $experienceYears += $months / 12;
                }
            } catch (Throwable $e) {
                $experienceYears += 0.5;
            }
        } else {
            $experienceYears += 0.5;
        }
    }

    return [
        'has_cv' => true,
        'cv_id' => (int) $cv['cv_id'],
        'title' => (string) ($cv['title'] ?? ''),
        'headline' => (string) ($cv['headline'] ?? ''),
        'summary' => (string) ($cv['summary'] ?? ''),
        'city' => (string) (($cv['city'] ?? '') ?: ($user['city'] ?? '')),
        'country_id' => $user['country_id'] ?? null,
        'skills' => $skills,
        'experience' => $experienceRows,
        'experience_years' => round($experienceYears, 1),
        'text' => strtolower(jm_job_plain_text(implode("\n", $textParts))),
    ];
}

function jm_job_match_role_terms(array $job): array {
    $text = strtolower((string) (($job['title'] ?? '') . ' ' . ($job['category_name'] ?? '')));
    $words = preg_split('/[^a-z0-9+#.]+/i', $text) ?: [];
    $stopwords = jm_job_match_stopwords();
    $terms = [];

    foreach ($words as $word) {
        $word = strtolower(trim($word));
        if (strlen($word) < 3 || isset($stopwords[$word]) || is_numeric($word)) {
            continue;
        }
        $terms[$word] = true;
    }

    return array_keys($terms);
}

function jm_job_match_coach(PDO $pdo, array $job, ?int $userId, bool $isLoggedIn = false): array {
    $profile = jm_job_cv_snapshot($pdo, $userId);
    $jobSkills = jm_job_match_keywords($job);
    $jobTitle = trim((string) ($job['title'] ?? 'this role'));
    $company = trim((string) ($job['company_name'] ?? 'the employer'));

    if (!$userId) {
        $isSignedInNonSeeker = $isLoggedIn;
        return [
            'score' => null,
            'label' => $isSignedInNonSeeker ? 'Candidate guidance' : 'See your fit',
            'tone' => 'empty',
            'has_cv' => false,
            'matched_skills' => [],
            'missing_skills' => array_slice(array_values($jobSkills), 0, 5),
            'coaching' => $isSignedInNonSeeker ? [
                'Candidates with a CV see simple guidance before they apply.',
                'It helps them decide what to mention and what to improve.',
            ] : [
                'Sign in and we will show how this role lines up with your CV.',
                'Add your CV once, then use this guidance on every role.',
            ],
            'cover_note' => "I am interested in the {$jobTitle} role at {$company} and would like to show how my experience can support the team.",
            'cta_url' => $isSignedInNonSeeker ? '/jobmington/jobs/' : '/jobmington/auth/login.php?redirect=' . rawurlencode('/jobmington/jobs/view.php?id=' . (int) ($job['job_id'] ?? 0)),
            'cta_label' => $isSignedInNonSeeker ? 'Browse jobs' : 'Sign in',
        ];
    }

    if (!$profile['has_cv']) {
        return [
            'score' => null,
            'label' => 'Create your CV first',
            'tone' => 'empty',
            'has_cv' => false,
            'matched_skills' => [],
            'missing_skills' => array_slice(array_values($jobSkills), 0, 5),
            'coaching' => [
                'Create a CV and we will show how well this role suits you.',
                'Add your headline, short summary, recent work, and key skills.',
            ],
            'cover_note' => "I am interested in the {$jobTitle} role at {$company} and would like to show how my experience can support the team.",
            'cta_url' => '/jobmington/cv-builder/',
            'cta_label' => 'Create CV',
        ];
    }

    $aliasMap = jm_job_match_alias_map();
    $matched = [];
    $missing = [];

    foreach ($jobSkills as $skillKey => $skillLabel) {
        $aliases = array_unique(array_merge([$skillKey], $aliasMap[$skillKey] ?? []));
        $hasSkill = false;
        foreach ($aliases as $alias) {
            if (jm_job_match_text_has($profile['text'], $alias)) {
                $hasSkill = true;
                break;
            }
        }

        if ($hasSkill) {
            $matched[] = $skillLabel;
        } else {
            $missing[] = $skillLabel;
        }
    }

    $skillCount = max(1, count($jobSkills));
    $skillScore = $jobSkills ? (count($matched) / $skillCount) * 45 : 22;
    $profileScore = 0;
    $profileScore += trim($profile['headline']) !== '' ? 5 : 0;
    $profileScore += trim($profile['summary']) !== '' ? 5 : 0;
    $profileScore += count($profile['skills']) >= 4 ? 5 : min(4, count($profile['skills']));
    $profileScore += count($profile['experience']) > 0 ? 5 : 0;

    $roleHits = 0;
    $roleTerms = jm_job_match_role_terms($job);
    foreach ($roleTerms as $term) {
        if (jm_job_match_text_has($profile['text'], $term)) {
            $roleHits++;
        }
    }
    $roleScore = $roleTerms ? min(20, ($roleHits / max(1, count($roleTerms))) * 20) : 10;

    $locationScore = 9;
    if (!empty($job['job_type']) && strcasecmp((string) $job['job_type'], 'Remote') === 0) {
        $locationScore = 15;
    } elseif (!empty($profile['country_id']) && !empty($job['country_id']) && (int) $profile['country_id'] === (int) $job['country_id']) {
        $locationScore = 14;
    } elseif (!empty($profile['city']) && !empty($job['city']) && strcasecmp($profile['city'], (string) $job['city']) === 0) {
        $locationScore = 14;
    }

    $score = (int) round(min(100, max(0, $skillScore + $profileScore + $roleScore + $locationScore)));
    $label = $score >= 75 ? 'Looks promising' : ($score >= 55 ? 'Worth a closer look' : 'Tailor your CV first');
    $tone = $score >= 75 ? 'strong' : ($score >= 55 ? 'possible' : 'weak');

    $coaching = [];
    if ($matched) {
        $coaching[] = 'Your CV already shows ' . implode(', ', array_slice($matched, 0, 3)) . '.';
    }
    if ($missing) {
        $coaching[] = 'Mention ' . implode(', ', array_slice($missing, 0, 3)) . ' only if it truly reflects your experience.';
    }
    if (trim($profile['headline']) === '') {
        $coaching[] = 'Add a headline that mirrors the kind of role you want next.';
    }
    if (trim($profile['summary']) === '') {
        $coaching[] = 'Write a short summary with your role, strengths, and industry focus.';
    }
    if (count($profile['skills']) < 6) {
        $coaching[] = 'Add a few more focused skills to make this guidance more useful.';
    }
    if (!$coaching) {
        $coaching[] = 'Tailor the first two CV lines to this role before applying.';
    }

    $coverSkill = $matched[0] ?? ($jobSkills ? reset($jobSkills) : ($job['category_name'] ?? 'relevant work'));

    return [
        'score' => $score,
        'label' => $label,
        'tone' => $tone,
        'has_cv' => true,
        'matched_skills' => array_slice($matched, 0, 5),
        'missing_skills' => array_slice($missing, 0, 5),
        'coaching' => array_slice($coaching, 0, 4),
        'cover_note' => "I am interested in the {$jobTitle} role at {$company} because my background in {$coverSkill} aligns with the work described.",
        'cta_url' => '/jobmington/cv-builder/editor-complete.php?id=' . (int) $profile['cv_id'],
        'cta_label' => 'Improve CV',
    ];
}

function jm_job_join_words(array $items): string {
    $items = array_values(array_filter(array_map('trim', $items), static function ($item) {
        return $item !== '';
    }));

    if (!$items) {
        return '';
    }
    if (count($items) === 1) {
        return $items[0];
    }

    $last = array_pop($items);
    return implode(', ', $items) . ' and ' . $last;
}

function jm_job_interview_kit(array $job): array {
    $role = trim((string) ($job['title'] ?? 'this role'));
    $company = trim((string) ($job['company_name'] ?? 'the company'));
    $category = trim((string) ($job['category_name'] ?? ''));
    $level = trim((string) ($job['experience_level'] ?? ''));
    $type = trim((string) ($job['job_type'] ?? ''));
    $skills = array_values(jm_job_match_keywords($job, 6));
    $skillPhrase = jm_job_join_words(array_slice($skills, 0, 3));
    $primaryFocus = $skillPhrase ?: ($category ?: 'the work in this role');

    $focus = array_slice($skills, 0, 5);
    if ($category && !in_array($category, $focus, true)) {
        array_unshift($focus, $category);
    }
    if ($level) {
        $focus[] = $level . ' level';
    }
    if ($type) {
        $focus[] = $type;
    }
    $focus = array_values(array_unique(array_slice($focus, 0, 7)));

    $questions = [
        "Tell us about work you have done that is close to the {$role} role.",
        "How would you approach your first 30 days at {$company}?",
    ];

    if ($skillPhrase) {
        $questions[] = "Which of {$skillPhrase} have you used recently, and what did it help you achieve?";
    } elseif ($category) {
        $questions[] = "What part of {$category} work do you handle best, and why?";
    }

    $levelLower = strtolower($level);
    if (strpos($levelLower, 'senior') !== false || strpos($levelLower, 'executive') !== false) {
        $questions[] = 'How have you led people, improved a process, or made a hard decision in a previous role?';
    } elseif (strpos($levelLower, 'entry') !== false) {
        $questions[] = 'What have you learned quickly in a past role, project, or training experience?';
    } else {
        $questions[] = 'Describe a time you solved a problem without waiting to be told exactly what to do.';
    }

    if (strcasecmp($type, 'Remote') === 0) {
        $questions[] = 'How do you stay organised and communicate clearly when working remotely?';
    } else {
        $questions[] = 'How do you handle busy days, changing priorities, or pressure at work?';
    }

    $prepare = [
        'One short story with a problem, your action, and the result.',
        'Two examples that show the strengths listed on your CV.',
        'A clear reason why this role and company interest you.',
        'Your availability, preferred work style, and salary expectations.',
    ];

    if ($skillPhrase) {
        array_unshift($prepare, "A recent example that proves your experience with {$skillPhrase}.");
    }

    $ask = [
        'What would success look like in the first 90 days?',
        'What are the main problems this hire should help solve?',
        'How does the team give feedback and measure good work?',
    ];

    if ($type) {
        $ask[] = 'What does a normal working week look like for this role?';
    }

    return [
        'focus' => $focus,
        'questions' => array_slice($questions, 0, 5),
        'prepare' => array_slice($prepare, 0, 5),
        'ask' => array_slice($ask, 0, 4),
        'pitch' => "I am interested in the {$role} role because I can bring practical experience in {$primaryFocus}, learn the team quickly, and contribute to the outcomes {$company} needs from this hire.",
    ];
}

function jm_job_find(PDO $pdo, int $jobId, bool $activeOnly = true): ?array {
    $sql = "
        SELECT j.*, co.name AS company_name, co.logo AS company_logo, co.description AS company_description,
               co.website AS company_website, co.industry AS company_industry, co.user_id AS employer_user_id,
               c.name AS country_name, c.currency_symbol, jc.name AS category_name, jc.slug AS category_slug
        FROM jobs j
        JOIN companies co ON j.company_id = co.company_id
        LEFT JOIN countries c ON j.country_id = c.country_id
        LEFT JOIN job_categories jc ON j.category_id = jc.category_id
        WHERE j.job_id = ?
    ";

    if ($activeOnly) {
        $sql .= " AND j.is_active = 1 AND (j.expires_at IS NULL OR j.expires_at >= CURDATE())";
    }

    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();

    return $job ?: null;
}

/**
 * Extract a clean company domain from the company website. Returns null when no
 * usable domain exists. We deliberately ignore apply_link, since for scraped
 * jobs that points at the job board (LinkedIn, Jooble, ...), not the employer.
 */
function jm_company_domain(array $job): ?string {
    $website = trim((string) ($job['company_website'] ?? ''));
    if ($website === '') {
        return null;
    }
    if (!preg_match('#^https?://#i', $website)) {
        $website = 'https://' . $website;
    }
    $host = parse_url($website, PHP_URL_HOST) ?: '';
    $host = preg_replace('#^www\.#i', '', strtolower($host));
    return $host !== '' && str_contains($host, '.') ? $host : null;
}

/**
 * A logo URL for a known company domain (Google's favicon service, which returns
 * a clean PNG and follows redirects in the browser).
 */
function jm_company_logo_from_domain(string $domain, int $size = 128): string {
    return 'https://www.google.com/s2/favicons?domain=' . rawurlencode($domain) . '&sz=' . $size;
}

/**
 * Best available logo URL for a company: an uploaded logo if present, otherwise
 * a fetched logo from the company website domain. Returns '' when neither is
 * available — the view then resolves it from the company name client-side, and
 * renders nothing if that fails too (no initials placeholder).
 */
function jm_company_logo_url(array $job): string {
    $uploaded = trim((string) ($job['company_logo'] ?? ''));
    if ($uploaded !== '') {
        return $uploaded;
    }
    $domain = jm_company_domain($job);
    return $domain !== null ? jm_company_logo_from_domain($domain) : '';
}

function jm_jobs_header(string $pageTitle, string $active = 'jobs'): void {
    $isLoggedIn = Session::isLoggedIn();
    $dashboardUrl = Session::isAdmin()
        ? '/jobmington/admin/'
        : (Session::isEmployer() ? '/jobmington/employer/dashboard.php' : '/jobmington/seeker/dashboard.php');
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($pageTitle) ?></title>
        <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-13">
    </head>
    <body class="jm-minimal">
        <div class="jm-shell">
            <header class="jm-header">
                <a class="jm-logo" href="/jobmington/">
                    <img src="/jobmington/assets/images/badge.png?v=logo-7" alt="">
                    <span>Jobmington</span>
                </a>
                <nav class="jm-nav" aria-label="Main navigation">
                    <a class="<?= $active === 'jobs' ? 'active' : '' ?>" href="/jobmington/jobs/">Find jobs</a>
                    <a href="/jobmington/cv-builder/">CV Builder</a>
                    <a href="/jobmington/ai/andika.php">Andika AI</a>
                    <a href="/jobmington/employer/">Employers</a>
                    <a href="/jobmington/pricing.php">Pricing</a>
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= e($dashboardUrl) ?>">Dashboard</a>
                        <a class="jm-button secondary" href="/jobmington/auth/logout.php">Sign out</a>
                    <?php else: ?>
                        <a href="/jobmington/auth/login.php">Sign in</a>
                        <a class="jm-button secondary" href="/jobmington/auth/register.php">Create account</a>
                    <?php endif; ?>
                </nav>
            </header>
    <?php
}

function jm_jobs_footer(string $secondaryUrl = '/jobmington/', string $secondaryLabel = 'Home'): void {
    ?>
            <?php jm_minimal_footer(); ?>
        </div>
    </body>
    </html>
    <?php
}
