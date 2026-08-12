<?php
/**
 * JOBMINGTON - Shared helpers for AI tools (cover letter, cold pitch, ...).
 * Keeps LLM calling + candidate-profile loading in one place.
 */

if (!defined('JOBMINGTON')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Call the configured chat LLM (Groq first, OpenRouter fallback) expecting a
 * JSON object back. Returns the decoded array, or null if every provider fails.
 */
function jm_ai_chat_json(array $messages, int $maxTokens = 1200, float $temperature = 0.5): ?array {
    $providers = [];

    $groqKey = trim((string) getenv('GROQ_API_KEY'));
    if ($groqKey !== '' && stripos($groqKey, 'your_') === false) {
        $providers[] = [
            'url'     => 'https://api.groq.com/openai/v1/chat/completions',
            'model'   => 'llama-3.3-70b-versatile',
            'headers' => ['Authorization: Bearer ' . $groqKey],
        ];
    }

    $openRouterKey = trim((string) getenv('OPENROUTER_API_KEY'));
    if ($openRouterKey !== '' && stripos($openRouterKey, 'your_') === false) {
        $providers[] = [
            'url'     => 'https://openrouter.ai/api/v1/chat/completions',
            'model'   => defined('ANDIKA_MODEL') ? ANDIKA_MODEL : 'meta-llama/llama-3.2-3b-instruct:free',
            'headers' => [
                'Authorization: Bearer ' . $openRouterKey,
                'HTTP-Referer: ' . (defined('SITE_URL') ? SITE_URL : 'https://jobmington.com'),
                'X-Title: Jobmington',
            ],
        ];
    }

    foreach ($providers as $provider) {
        $ch = curl_init($provider['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $provider['headers']),
            CURLOPT_POSTFIELDS     => json_encode([
                'model'           => $provider['model'],
                'messages'        => $messages,
                'temperature'     => $temperature,
                'max_tokens'      => $maxTokens,
                'response_format' => ['type' => 'json_object'],
            ]),
            CURLOPT_TIMEOUT        => 28,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode < 200 || $httpCode >= 300) {
            continue;
        }

        $payload = json_decode((string) $response, true);
        $content = $payload['choices'][0]['message']['content'] ?? '';
        $parsed  = json_decode($content, true);

        if (is_array($parsed)) {
            return $parsed;
        }
    }

    return null;
}

/**
 * Build a compact candidate profile for prompting: name, headline, skills, and a
 * plain-text CV summary. Prefers a saved Jobmington CV; falls back to the users
 * row so the tools still work for people who have not built a CV yet.
 */
function jm_ai_candidate_profile(PDO $pdo, int $userId, int $cvId = 0): array {
    $out = ['name' => '', 'headline' => '', 'skills' => [], 'cv_text' => ''];

    // 1. Try a saved CV profile.
    try {
        if ($cvId <= 0) {
            $stmt = $pdo->prepare("SELECT cv_id FROM cv_profiles WHERE user_id = ? ORDER BY updated_at DESC, created_at DESC LIMIT 1");
            $stmt->execute([$userId]);
            $cvId = (int) ($stmt->fetchColumn() ?: 0);
        }

        if ($cvId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM cv_profiles WHERE cv_id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$cvId, $userId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($profile) {
                $out['name']     = trim((string) ($profile['full_name'] ?? ''));
                $out['headline'] = trim((string) ($profile['headline'] ?? ''));

                $skillRows = jm_ai_safe_rows($pdo, "SELECT skill_name FROM cv_skills WHERE cv_id = ? ORDER BY skill_name ASC", [$cvId]);
                $out['skills'] = array_values(array_filter(array_map(static fn($r) => trim((string) ($r['skill_name'] ?? '')), $skillRows)));

                $parts = [
                    'Summary: ' . trim((string) ($profile['summary'] ?? '')),
                ];
                if ($out['skills']) {
                    $parts[] = 'Skills: ' . implode(', ', $out['skills']);
                }
                foreach (jm_ai_safe_rows($pdo, "SELECT job_title, company, description, achievements FROM cv_experience WHERE cv_id = ? ORDER BY is_current DESC, start_date DESC LIMIT 6", [$cvId]) as $row) {
                    $parts[] = 'Experience: ' . trim(($row['job_title'] ?? '') . ' at ' . ($row['company'] ?? '') . '. ' . ($row['description'] ?? '') . ' ' . ($row['achievements'] ?? ''));
                }
                foreach (jm_ai_safe_rows($pdo, "SELECT degree, field_of_study, institution FROM cv_education WHERE cv_id = ? ORDER BY is_current DESC, start_date DESC LIMIT 3", [$cvId]) as $row) {
                    $parts[] = 'Education: ' . trim(($row['degree'] ?? '') . ' ' . ($row['field_of_study'] ?? '') . ' at ' . ($row['institution'] ?? ''));
                }
                $out['cv_text'] = trim(implode("\n", array_filter(array_map('trim', $parts))));
            }
        }
    } catch (Throwable $e) {
        error_log('jm_ai_candidate_profile cv error: ' . $e->getMessage());
    }

    // 2. Fall back to the users row for the name. Only the name: headline is a
    //    cv_profiles column and step 1 above is the only place it comes from.
    //    This used to ask users for it, fail, and retry without it on every
    //    single call.
    if ($out['name'] === '') {
        try {
            $stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $out['name'] = trim((string) ($stmt->fetchColumn() ?: ''));
        } catch (Throwable $e) {
            error_log('jm_ai_candidate_profile users error: ' . $e->getMessage());
        }
    }

    return $out;
}

function jm_ai_safe_rows(PDO $pdo, string $sql, array $params): array {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
