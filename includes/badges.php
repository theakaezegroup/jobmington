<?php
/**
 * Verification Badges Helper Functions
 * Provides functions to display and manage profile verification badges
 */

/**
 * Available badge types with their properties
 */
function getBadgeTypes(): array {
    return [
        'verified-blue' => [
            'name' => 'Verified',
            'description' => 'Basic profile verification completed',
            'icon' => 'verified-blue.svg',
            'color' => '#3b82f6',
            'tier' => 1
        ],
        'verified-email' => [
            'name' => 'Email Verified',
            'description' => 'Email address has been confirmed',
            'icon' => 'verified-email.svg',
            'color' => '#10b981',
            'tier' => 1
        ],
        'verified-id' => [
            'name' => 'ID Verified',
            'description' => 'Government ID has been verified',
            'icon' => 'verified-id.svg',
            'color' => '#6366f1',
            'tier' => 2
        ],
        'verified-skills' => [
            'name' => 'Skills Certified',
            'description' => 'Professional skills have been assessed and certified',
            'icon' => 'verified-skills.svg',
            'color' => '#f59e0b',
            'tier' => 2
        ],
        'verified-company' => [
            'name' => 'Verified Company',
            'description' => 'Business registration has been verified',
            'icon' => 'verified-company.svg',
            'color' => '#6366f1',
            'tier' => 2
        ],
        'verified-gold' => [
            'name' => 'Gold Member',
            'description' => 'Premium gold tier membership',
            'icon' => 'verified-gold.svg',
            'color' => '#f59e0b',
            'tier' => 3
        ],
        'verified-platinum' => [
            'name' => 'Platinum Member',
            'description' => 'Elite platinum tier membership',
            'icon' => 'verified-platinum.svg',
            'color' => '#94a3b8',
            'tier' => 4
        ],
        'verified-pro' => [
            'name' => 'Professional',
            'description' => 'Recognized as a verified professional',
            'icon' => 'verified-pro.svg',
            'color' => '#8b5cf6',
            'tier' => 3
        ],
        'verified-elite' => [
            'name' => 'Elite',
            'description' => 'Top performer in the community',
            'icon' => 'verified-elite.svg',
            'color' => '#f59e0b',
            'tier' => 4
        ],
        'verified-top-rated' => [
            'name' => 'Top Rated',
            'description' => 'Consistently high ratings from employers',
            'icon' => 'verified-top-rated.svg',
            'color' => '#ef4444',
            'tier' => 4
        ],
        'verified-new-member' => [
            'name' => 'New Member',
            'description' => 'Recently joined the platform',
            'icon' => 'verified-new-member.svg',
            'color' => '#06b6d4',
            'tier' => 1
        ]
    ];
}

/**
 * Render a single badge with tooltip
 * 
 * @param string $badgeType The type of badge to render
 * @param string $size Size class (sm, md, lg)
 * @param bool $showTooltip Whether to show tooltip on hover
 * @return string HTML for the badge
 */
function renderBadge(string $badgeType, string $size = 'md', bool $showTooltip = true): string {
    $badges = getBadgeTypes();
    
    if (!isset($badges[$badgeType])) {
        return '';
    }
    
    $badge = $badges[$badgeType];
    $sizeClasses = [
        'xs' => 'w-4 h-4',
        'sm' => 'w-5 h-5',
        'md' => 'w-6 h-6',
        'lg' => 'w-8 h-8',
        'xl' => 'w-10 h-10'
    ];
    
    $sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];
    $tooltipAttr = $showTooltip ? "data-tooltip=\"{$badge['name']}: {$badge['description']}\"" : '';
    
    $badgePath = "/jobmington/assets/images/badges/{$badge['icon']}";
    
    return <<<HTML
    <span class="verification-badge inline-flex items-center justify-center {$sizeClass} transition-transform hover:scale-110" {$tooltipAttr}>
        <img src="{$badgePath}" alt="{$badge['name']}" class="w-full h-full object-contain">
    </span>
    HTML;
}

/**
 * Render multiple badges in a row
 * 
 * @param array $badgeTypes Array of badge type strings
 * @param string $size Size class (sm, md, lg)
 * @param int $maxShow Maximum badges to show before +N indicator
 * @return string HTML for the badge row
 */
function renderBadgeRow(array $badgeTypes, string $size = 'md', int $maxShow = 5): string {
    if (empty($badgeTypes)) {
        return '';
    }
    
    $badges = getBadgeTypes();
    $validBadges = array_filter($badgeTypes, fn($type) => isset($badges[$type]));
    
    // Sort by tier (highest first)
    usort($validBadges, fn($a, $b) => ($badges[$b]['tier'] ?? 0) - ($badges[$a]['tier'] ?? 0));
    
    $visible = array_slice($validBadges, 0, $maxShow);
    $remaining = count($validBadges) - $maxShow;
    
    $html = '<div class="badge-row flex items-center gap-1">';
    
    foreach ($visible as $badgeType) {
        $html .= renderBadge($badgeType, $size);
    }
    
    if ($remaining > 0) {
        $html .= "<span class=\"text-xs text-slate-500 ml-1\">+{$remaining}</span>";
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render badge with name label
 * 
 * @param string $badgeType The type of badge to render
 * @param string $size Size class (sm, md, lg)
 * @return string HTML for the badge with label
 */
function renderBadgeWithLabel(string $badgeType, string $size = 'md'): string {
    $badges = getBadgeTypes();
    
    if (!isset($badges[$badgeType])) {
        return '';
    }
    
    $badge = $badges[$badgeType];
    
    return <<<HTML
    <div class="badge-with-label flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-100 hover:bg-slate-200 transition-colors">
        {renderBadge($badgeType, $size, false)}
        <span class="text-sm font-medium text-slate-700">{$badge['name']}</span>
    </div>
    HTML;
}

/**
 * Render a badge card for showcase/profile pages
 * 
 * @param string $badgeType The type of badge to render
 * @param bool $earned Whether the user has earned this badge
 * @return string HTML for the badge card
 */
function renderBadgeCard(string $badgeType, bool $earned = true): string {
    $badges = getBadgeTypes();
    
    if (!isset($badges[$badgeType])) {
        return '';
    }
    
    $badge = $badges[$badgeType];
    $earnedClass = $earned ? '' : 'opacity-40 grayscale';
    $badgePath = "/jobmington/assets/images/badges/{$badge['icon']}";
    
    return <<<HTML
    <div class="badge-card flex flex-col items-center p-4 rounded-xl bg-white border border-slate-200 hover:border-slate-300 hover:shadow-lg transition-all {$earnedClass}">
        <div class="w-16 h-16 mb-3">
            <img src="{$badgePath}" alt="{$badge['name']}" class="w-full h-full object-contain">
        </div>
        <h4 class="font-semibold text-slate-900 text-sm text-center">{$badge['name']}</h4>
        <p class="text-xs text-slate-500 text-center mt-1">{$badge['description']}</p>
        <span class="mt-2 px-2 py-0.5 text-xs rounded-full" style="background-color: {$badge['color']}20; color: {$badge['color']}">
            Tier {$badge['tier']}
        </span>
    </div>
    HTML;
}

/**
 * Get user's badges from database
 * 
 * @param int $userId The user ID
 * @return array Array of badge type strings
 */
function getUserBadges(int $userId): array {
    $pdo = db();
    
    // Check if badges table exists
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'user_badges'");
        if ($tableCheck->rowCount() === 0) {
            // Return default badges for demo
            return ['verified-email'];
        }
        
        $stmt = $pdo->prepare("SELECT badge_type FROM user_badges WHERE user_id = ? AND is_active = 1 ORDER BY earned_at DESC");
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // Table doesn't exist yet, return demo badge
        return ['verified-email'];
    }
}

/**
 * Award a badge to a user
 * 
 * @param int $userId The user ID
 * @param string $badgeType The badge type to award
 * @return bool Success status
 */
function awardBadge(int $userId, string $badgeType): bool {
    $pdo = db();
    
    $badges = getBadgeTypes();
    if (!isset($badges[$badgeType])) {
        return false;
    }
    
    try {
        // Check if badges table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'user_badges'");
        if ($tableCheck->rowCount() === 0) {
            return false;
        }
        
        // Check if already has badge
        $stmt = $pdo->prepare("SELECT id FROM user_badges WHERE user_id = ? AND badge_type = ?");
        $stmt->execute([$userId, $badgeType]);
        if ($stmt->rowCount() > 0) {
            return false; // Already has badge
        }
        
        // Award the badge
        $stmt = $pdo->prepare("INSERT INTO user_badges (user_id, badge_type, earned_at) VALUES (?, ?, NOW())");
        return $stmt->execute([$userId, $badgeType]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Revoke a badge from a user
 * 
 * @param int $userId The user ID
 * @param string $badgeType The badge type to revoke
 * @return bool Success status
 */
function revokeBadge(int $userId, string $badgeType): bool {
    $pdo = db();
    
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'user_badges'");
        if ($tableCheck->rowCount() === 0) {
            return false;
        }
        
        $stmt = $pdo->prepare("UPDATE user_badges SET is_active = 0 WHERE user_id = ? AND badge_type = ?");
        return $stmt->execute([$userId, $badgeType]);
    } catch (Exception $e) {
        return false;
    }
}
