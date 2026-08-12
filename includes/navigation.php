<?php
/**
 * JOBMINGTON - the site navigation, defined once.
 *
 * This class used to live inside includes/header.php, which meant the pages
 * built on ai-header.php could not reach it and hand-wrote their own list
 * instead. The two drifted: the main header carried Home and Post a Job, the
 * AI header carried Pricing and neither of those, one said "Find Jobs" and the
 * other "Find jobs", and Employers pointed at two different URLs.
 *
 * Anything that renders a nav reads it from here.
 */

if (!defined('JOBMINGTON')) {
    http_response_code(403);
    exit('Forbidden');
}

if (!class_exists('Navigation')) {
final class Navigation {
    /**
     * Every item. Used by the two full headers, which have the width and a
     * hamburger to fall back on.
     *
     * `compact` marks the ones that also belong in a narrow bar. The minimal
     * page family renders its nav as a flex row with a 26px gap beside the
     * logo, sized for about five items; putting the whole list in there wrapped
     * it onto a second row and scattered the header. One list, two densities.
     *
     * The compact set has a hard budget. The hamburger takes over at 900px, so
     * at 901px the bar has roughly 679px once the 48px shell padding, the logo
     * and the gap are taken out. Five items plus Dashboard, the bell and Sign
     * out come to about 664px. Adding a sixth costs another 79px and wraps.
     * Anything added to the compact set has to be measured, not assumed.
     */
    public static function getMainItems(): array {
        return [
            ['url' => '/jobmington/', 'label' => 'Home', 'icon' => 'home', 'compact' => false],   // the logo is the home link
            ['url' => '/jobmington/jobs', 'label' => 'Find Jobs', 'icon' => 'briefcase', 'compact' => true],
            ['url' => '/jobmington/cv-builder/', 'label' => 'CV Builder', 'icon' => 'document', 'compact' => true],
            ['url' => '/jobmington/tools/', 'label' => 'Tools', 'icon' => 'tools', 'compact' => true],
            ['url' => '/jobmington/learn/', 'label' => 'Learn', 'icon' => 'graduation', 'compact' => true],
            ['url' => '/jobmington/ai/andika.php', 'label' => 'Andika AI', 'icon' => 'sparkles', 'compact' => false],
            ['url' => '/jobmington/employer/post-job.php', 'label' => 'Post a Job', 'icon' => 'plus', 'compact' => false],
            ['url' => '/jobmington/employer/dashboard.php', 'label' => 'Employers', 'icon' => 'users', 'compact' => true],
            ['url' => '/jobmington/pricing.php', 'label' => 'Pricing', 'icon' => 'briefcase', 'compact' => false],
        ];
    }

    /** The subset that fits a narrow bar. Derived, never a second list. */
    public static function getCompactItems(): array {
        return array_values(array_filter(
            self::getMainItems(),
            static fn(array $i): bool => !empty($i['compact'])
        ));
    }

    public static function getIcon(string $name, string $class = 'w-5 h-5'): string {
        $icons = [
            'home' => '<path d="M3 10.5L12 3l9 7.5"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>',
            'briefcase' => '<path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M12 12v.01"/>',
            'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
            'sparkles' => '<path d="M12 3l1.9 5.8a2 2 0 001.3 1.3L21 12l-5.8 1.9a2 2 0 00-1.3 1.3L12 21l-1.9-5.8a2 2 0 00-1.3-1.3L3 12l5.8-1.9a2 2 0 001.3-1.3L12 3z"/><path d="M5 3v4"/><path d="M3 5h4"/>',
            'graduation' => '<path d="M22 10l-10-5L2 10l10 5 10-5z"/><path d="M6 12v5c0 2 3 3 6 3s6-1 6-3v-5"/><path d="M22 10v6"/>',
            'users' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>',
            'document' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/>',
            'blog' => '<path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/>',
            'tools' => '<path d="M14.7 6.3a4 4 0 00-5.4 5.4l-6 6a1.4 1.4 0 002 2l6-6a4 4 0 005.4-5.4l-2.3 2.3-2-2 2.3-2.3z"/>',
        ];
        return sprintf('<svg class="%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">%s</svg>', $class, $icons[$name] ?? $icons['briefcase']);
    }
}
}


/** Where a given role lands after signing in. */
if (!function_exists('jm_login_dashboard_for')) {
    function jm_login_dashboard_for(string $userType): string {
        if ($userType === USER_TYPE_ADMIN) {
            return '/jobmington/admin/';
        }
        if ($userType === USER_TYPE_EMPLOYER) {
            return '/jobmington/employer/dashboard.php';
        }
        return '/jobmington/seeker/dashboard.php';
    }
}


/**
 * Is this nav item the section the visitor is currently in?
 *
 * Was str_contains($item['url'], $activeUrl), which is a substring test. The
 * home page passes '/', every URL contains a slash, so every link came back
 * active and .jm-nav a.active is underlined. That is where the underlines came
 * from: not styling, a matcher that said yes to everything.
 *
 * Compares real path segments, and never treats the site root as a prefix
 * match, or it would claim every page again.
 */
if (!function_exists('jm_nav_is_active')) {
    function jm_nav_is_active(string $itemUrl, string $override = ''): bool {
        $strip = static fn(string $u): string
            => rtrim(preg_replace('#^/jobmington#', '', strtok($u, '?')) ?: '/', '/');

        $item = $strip($itemUrl);
        if ($item === '') {
            return false;   // the root is the logo's job, never a highlighted item
        }

        $here = $override !== '' ? $strip($override) : $strip((string) ($_SERVER['REQUEST_URI'] ?? '/'));
        if ($here === '') {
            return false;
        }

        return $here === $item || str_starts_with($here . '/', $item . '/');
    }
}

/**
 * Render the standard nav for the "minimal" page family.
 *
 * Roughly thirty pages hand-write their own <nav>, which is why the items
 * differ depending on where you are standing. This gives those pages the
 * shared list and, importantly, one correct answer for the signed-in state:
 * several of them decided what to show from a role rather than from whether
 * anybody was signed in at all, so a signed-in seeker was told to sign in.
 */
if (!function_exists('jm_site_nav')) {
    function jm_site_nav(string $activeUrl = '', string $ariaLabel = 'Main navigation', string $navId = ''): void {
        $loggedIn = class_exists('Session') && Session::isLoggedIn();
        $dash = '/jobmington/seeker/dashboard.php';
        if ($loggedIn && function_exists('jm_login_dashboard_for')) {
            $dash = jm_login_dashboard_for(Session::userType() ?? '');
        }
        ?>
        <nav class="jm-nav"<?= $navId !== '' ? ' id="' . e($navId) . '"' : '' ?> aria-label="<?= e($ariaLabel) ?>">
            <?php foreach (Navigation::getCompactItems() as $item): ?>
                <a class="<?= jm_nav_is_active($item['url'], $activeUrl) ? 'active' : '' ?>"
                   href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a>
            <?php endforeach; ?>

            <?php if ($loggedIn): ?>
                <a href="<?= e($dash) ?>">Dashboard</a>
                <?php
                if (is_file(__DIR__ . '/notification_bell.php')) {
                    require_once __DIR__ . '/notification_bell.php';
                    jm_notification_bell();
                }
                ?>
                <a class="jm-button secondary" href="/jobmington/auth/logout.php">Sign out</a>
            <?php else: ?>
                <a href="/jobmington/auth/login.php">Sign in</a>
                <a class="jm-button secondary" href="/jobmington/auth/register.php">Create account</a>
            <?php endif; ?>
        </nav>
        <?php
    }
}

/**
 * The nav for a workspace: the seeker area, the employer area, checkout.
 *
 * These legitimately differ from the marketing nav. Somebody managing
 * applications wants Applications and Saved jobs, not Pricing and Andika AI,
 * and flattening that would be consistency at the user's expense.
 *
 * What they must not each decide for themselves is who is signed in. That was
 * hardcoded in twelve separate files, and one of them got it wrong: Post a Job
 * offered a signed-in seeker an account they already had.
 *
 * @param array $links [key => [href, label]] for this workspace.
 */
if (!function_exists('jm_workspace_nav')) {
    function jm_workspace_nav(array $links, string $active = '', string $ariaLabel = 'Main navigation', string $navId = ''): void {
        $loggedIn = class_exists('Session') && Session::isLoggedIn();
        ?>
        <nav class="jm-nav"<?= $navId !== '' ? ' id="' . e($navId) . '"' : '' ?> aria-label="<?= e($ariaLabel) ?>">
            <a href="/jobmington/jobs/">Find Jobs</a>
            <?php foreach ($links as $key => [$href, $label]): ?>
                <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>

            <?php if ($loggedIn): ?>
                <?php
                if (is_file(__DIR__ . '/notification_bell.php')) {
                    require_once __DIR__ . '/notification_bell.php';
                    jm_notification_bell();
                }
                ?>
                <a class="jm-button secondary" href="/jobmington/auth/logout.php">Sign out</a>
            <?php else: ?>
                <a href="/jobmington/auth/login.php">Sign in</a>
                <a class="jm-button secondary" href="/jobmington/auth/register.php">Create account</a>
            <?php endif; ?>
        </nav>
        <?php
    }
}

/**
 * The nav for the sign-in, sign-up and password pages.
 *
 * Deliberately short. Someone here is trying to get into an account, and nine
 * competing links beside the form is not consistency, it is noise. They get a
 * way back to the site and the one action they might have meant instead.
 */
if (!function_exists('jm_auth_nav')) {
    function jm_auth_nav(string $opposite = 'register'): void {
        ?>
        <nav class="jm-nav" aria-label="Main navigation">
            <a href="/jobmington/jobs/">Find Jobs</a>
            <a href="/jobmington/employer/">Employers</a>
            <?php if ($opposite === 'login'): ?>
                <a class="jm-button secondary" href="/jobmington/auth/login.php">Sign in</a>
            <?php else: ?>
                <a class="jm-button secondary" href="/jobmington/auth/register.php">Create account</a>
            <?php endif; ?>
        </nav>
        <?php
    }
}
