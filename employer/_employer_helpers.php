<?php
if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

function jm_employer_company(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ? ORDER BY company_id ASC LIMIT 1");
    $stmt->execute([$userId]);
    $company = $stmt->fetch();

    return $company ?: null;
}

function jm_employer_company_or_redirect(PDO $pdo, int $userId): array {
    $company = jm_employer_company($pdo, $userId);
    if (!$company) {
        redirect('/jobmington/employer/company-profile.php?setup=1');
    }

    return $company;
}

function jm_employer_slug(string $value): string {
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $value), '-'));

    return $slug !== '' ? $slug : 'listing';
}

function jm_employer_unique_slug(PDO $pdo, string $table, string $column, string $value, ?int $ignoreId = null, string $idColumn = 'id'): string {
    $base = jm_employer_slug($value);
    $slug = $base;
    $i = 2;

    while (true) {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
        $params = [$slug];

        if ($ignoreId !== null) {
            $sql .= " AND {$idColumn} != ?";
            $params[] = $ignoreId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }

        $slug = $base . '-' . $i;
        $i++;
    }
}

function jm_employer_company_logo(?string $logo): string {
    if (!$logo) {
        return asset('images/default-company.png');
    }
    if (strpos($logo, 'http') === 0) {
        return $logo;
    }

    return upload(strpos($logo, '/') !== false ? $logo : 'company-logos/' . $logo);
}

function jm_employer_header(string $pageTitle, string $active = ''): void {
    $links = [
        'dashboard' => ['/jobmington/employer/dashboard.php', 'Dashboard'],
        'company' => ['/jobmington/employer/company-profile.php', 'Company'],
        'jobs' => ['/jobmington/employer/manage-jobs.php', 'Jobs'],
        'applications' => ['/jobmington/employer/applications.php', 'Applications'],
        'talent' => ['/jobmington/employer/talent-pool.php', 'Talent'],
        'pricing' => ['/jobmington/pricing.php', 'Pricing'],
        'post' => ['/jobmington/employer/post-job.php', 'Post a job'],
    ];
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($pageTitle) ?></title>
    <link rel="preload" as="font" type="font/woff2" href="/jobmington/assets/fonts/FuturaCyrillicDemi.woff2" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="/jobmington/assets/fonts/FuturaCyrillicBook.woff2" crossorigin>
        <link rel="stylesheet" href="/jobmington/assets/css/minimal-jobmington.css?v=brand-30">
    </head>
    <body class="jm-minimal">
        <div class="jm-shell">
            <header class="jm-header">
                <a class="jm-logo" href="/jobmington/">
                    <img src="/jobmington/assets/images/badge.png?v=logo-8" alt="">
                    <span>Jobmington</span>
                </a>
                <?php require_once __DIR__ . '/../includes/navigation.php'; jm_workspace_nav($links, $active, 'Employer navigation'); ?>
            </header>
    <?php
}

function jm_employer_footer(string $linkHref = '/jobmington/jobs/', string $linkLabel = 'View public jobs'): void {
    ?>
            <?php jm_minimal_footer(); ?>
        </div>
    </body>
    </html>
    <?php
}

function jm_employer_status_text(?string $status): string {
    $status = $status ?: 'pending';

    return ucfirst(str_replace('_', ' ', $status));
}

function jm_employer_flash(): void {
    if (!Session::hasFlash()) {
        return;
    }

    foreach (Session::getFlash() as $type => $messages) {
        $class = $type === 'success' ? 'jm-success' : 'jm-alert';
        foreach ($messages as $message) {
            echo '<div class="' . e($class) . '">' . e($message) . '</div>';
        }
    }
}
