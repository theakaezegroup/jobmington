<?php
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireAdmin();

$pdo = db();
$countries = [];
try {
    $countries = $pdo->query("
        SELECT c.*,
               COUNT(DISTINCT j.job_id) AS job_count,
               COUNT(DISTINCT co.company_id) AS company_count,
               COUNT(DISTINCT u.user_id) AS user_count
        FROM countries c
        LEFT JOIN jobs j ON c.country_id = j.country_id
        LEFT JOIN companies co ON c.country_id = co.country_id
        LEFT JOIN users u ON c.country_id = u.country_id
        GROUP BY c.country_id
        ORDER BY c.is_active DESC, c.name ASC
    ")->fetchAll();
} catch (Throwable $e) {
    $countries = [];
}

$pageTitle = 'Countries - Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-100 py-8">
    <div class="max-w-7xl mx-auto px-4">
        <div class="mb-8">
            <a href="/jobmington/admin/" class="text-slate-500 hover:text-slate-900 text-xs font-bold uppercase tracking-widest">Admin Dashboard</a>
            <h1 class="text-3xl font-bold text-slate-900 mt-2">Countries</h1>
            <p class="text-slate-600">Supported locations, currencies, jobs, companies, and users.</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <div class="jm-tablewrap"><table class="w-full text-sm jm-stacktable">
                <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                    <tr>
                        <th class="text-left p-4">Country</th>
                        <th class="text-left p-4">Region</th>
                        <th class="text-left p-4">Currency</th>
                        <th class="text-left p-4">Jobs</th>
                        <th class="text-left p-4">Companies</th>
                        <th class="text-left p-4">Users</th>
                        <th class="text-left p-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($countries as $country): ?>
                        <tr class="border-t border-slate-100">
                            <td class="p-4 font-bold text-slate-900"><?= e($country['name']) ?> <span class="text-slate-400 uppercase"><?= e($country['iso_code']) ?></span></td>
                            <td class="p-4 text-slate-700"><?= e($country['region']) ?></td>
                            <td class="p-4 text-slate-700"><?= e(trim(($country['currency_code'] ?? '') . ' ' . ($country['currency_symbol'] ?? ''))) ?></td>
                            <td class="p-4 text-slate-700"><?= number_format((int) $country['job_count']) ?></td>
                            <td class="p-4 text-slate-700"><?= number_format((int) $country['company_count']) ?></td>
                            <td class="p-4 text-slate-700"><?= number_format((int) $country['user_count']) ?></td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded-full text-xs font-bold <?= (int) $country['is_active'] === 1 ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' ?>">
                                    <?= (int) $country['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
