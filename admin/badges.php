<?php
/**
 * JOBMINGTON - Admin Badges Management
 * Manage achievement badges, distribute seeds, and configure rewards
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/seeds.php';

Session::start();
Session::requireAdmin();

$pdo = db();
$success = '';
$error = '';
$activeTab = get('tab', 'badges');

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = Security::clean(post('action', ''));
    
    switch ($action) {
        case 'award_badge':
        case 'revoke_badge':
            require_once __DIR__ . '/../includes/badges.php';
            $badgeUser = (int) post('badge_user_id', 0);
            $badgeType = Security::clean(post('badge_type', ''));
            if ($badgeUser <= 0 || $badgeType === '') {
                $error = 'Pick a user and a badge.';
            } elseif ($action === 'award_badge') {
                $error = awardBadge($badgeUser, $badgeType)
                    ? '' : 'Could not award that badge. They may already hold it.';
                if (!$error) { $success = 'Badge awarded.'; }
            } else {
                $error = revokeBadge($badgeUser, $badgeType) ? '' : 'They do not hold that badge.';
                if (!$error) { $success = 'Badge revoked.'; }
            }
            break;

        case 'distribute_seeds':
            $userId = (int) post('user_id', 0);
            $amount = (float) post('amount', 0);
            $description = Security::clean(post('description', 'Admin Bonus'));
            
            if ($userId > 0 && $amount > 0) {
                $result = awardSeeds($userId, 'admin_bonus', null, $description, $amount);
                if ($result['success']) {
                    $success = "Successfully awarded {$amount} Seeds to user ID {$userId}!";
                } else {
                    $error = $result['message'];
                }
            } else {
                $error = 'Invalid user ID or amount.';
            }
            break;
            
        case 'bulk_distribute':
            $amount = (float) post('bulk_amount', 0);
            $userType = Security::clean(post('user_type', 'all'));
            $description = Security::clean(post('bulk_description', 'Promotional Bonus'));
            
            if ($amount > 0) {
                $whereClause = '';
                if ($userType === 'seeker') {
                    $whereClause = "WHERE user_type = 'seeker'";
                } elseif ($userType === 'employer') {
                    $whereClause = "WHERE user_type = 'employer'";
                }
                
                $stmt = $pdo->query("SELECT user_id FROM users {$whereClause}");
                $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $successCount = 0;
                
                foreach ($users as $uid) {
                    $result = awardSeeds($uid, 'admin_bulk_bonus', null, $description, $amount);
                    if ($result['success']) $successCount++;
                }
                
                $success = "Successfully awarded {$amount} Seeds to {$successCount} users!";
            } else {
                $error = 'Invalid amount.';
            }
            break;
            
        case 'create_badge':
            $name = Security::clean(post('badge_name', ''));
            $description = Security::clean(post('badge_description', ''));
            $icon = Security::clean(post('badge_icon', 'fa-medal'));
            $color = Security::clean(post('badge_color', '#fbbf24'));
            $seedReward = (float) post('seed_reward', 0);
            
            if ($name) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO badges (name, description, icon) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $description, $icon]);
                    $success = "Badge '{$name}' created successfully!";
                } catch (Exception $e) {
                    $error = 'Error creating badge: ' . $e->getMessage();
                }
            }
            break;
    }
}

// Fetch data
try {
    $badges = $pdo->query("SELECT * FROM badges ORDER BY created_at DESC")->fetchAll();
} catch (Exception $e) { $badges = []; }

try {
    $seedRates = $pdo->query("SELECT * FROM seed_rates ORDER BY type, action")->fetchAll();
} catch (Exception $e) { $seedRates = []; }

try {
    $recentTransactions = $pdo->query("SELECT st.*, u.first_name, u.last_name, u.email FROM seed_transactions st JOIN users u ON st.user_id = u.user_id ORDER BY st.created_at DESC LIMIT 50")->fetchAll();
} catch (Exception $e) { $recentTransactions = []; }

try {
    $users = $pdo->query("SELECT user_id, first_name, last_name, email FROM users ORDER BY first_name LIMIT 500")->fetchAll();
} catch (Exception $e) { $users = []; }

require_once __DIR__ . '/../includes/badges.php';
$badgeCatalogue = getBadgeTypes();
$badgeHolders = [];
try {
    $bs = $pdo->query("SELECT ub.badge_type, ub.earned_at, u.user_id, u.full_name, u.email
                       FROM user_badges ub JOIN users u ON u.user_id = ub.user_id
                       ORDER BY ub.earned_at DESC");
    foreach ($bs as $row) { $badgeHolders[$row['badge_type']][] = $row; }
} catch (Throwable $e) { $badgeHolders = []; }
$allUsers = [];
try { $allUsers = $pdo->query("SELECT user_id, full_name, email FROM users ORDER BY full_name")->fetchAll(); }
catch (Throwable $e) { $allUsers = []; }

$pageTitle = 'Badges & Seeds Management - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

        <!-- Badges: the catalogue, who holds what, and manual award/revoke -->
        <div class="bg-white border border-slate-200 rounded-xl p-6 mb-8">
            <h2 class="text-xl font-bold text-slate-900 mb-1">Badges</h2>
            <p class="text-slate-500 text-sm mb-6">Three badges, each tied to something the platform can actually verify. Awarded automatically; use this to correct a case by hand.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <?php foreach ($badgeCatalogue as $type => $b): $holders = $badgeHolders[$type] ?? []; ?>
                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <img src="/jobmington/assets/images/badges/<?= e($b['icon']) ?>" alt="" class="w-6 h-6">
                            <span class="font-bold text-slate-900"><?= e($b['name']) ?></span>
                        </div>
                        <p class="text-xs text-slate-500 mb-2"><?= e($b['how'] ?? $b['description']) ?></p>
                        <p class="text-sm font-bold text-blue-400"><?= count($holders) ?> holder<?= count($holders) === 1 ? '' : 's' ?></p>
                        <?php if ($holders): ?>
                            <ul class="mt-2 space-y-1">
                                <?php foreach (array_slice($holders, 0, 5) as $h): ?>
                                    <li class="text-xs text-slate-500 truncate"><?= e($h['full_name'] ?: $h['email']) ?></li>
                                <?php endforeach; ?>
                                <?php if (count($holders) > 5): ?><li class="text-xs text-slate-500">and <?= count($holders) - 5 ?> more</li><?php endif; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="post" class="flex flex-wrap items-end gap-3">
                <?= Security::csrfField() ?>
                <div class="flex-1 min-w-[220px]">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">User</label>
                    <select name="badge_user_id" class="w-full rounded-lg bg-slate-50 border border-slate-200 text-slate-900 px-3 py-2 text-sm">
                        <option value="">Select a user</option>
                        <?php foreach ($allUsers as $u): ?>
                            <option value="<?= (int) $u['user_id'] ?>"><?= e($u['full_name'] ?: $u['email']) ?> (#<?= (int) $u['user_id'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Badge</label>
                    <select name="badge_type" class="w-full rounded-lg bg-slate-50 border border-slate-200 text-slate-900 px-3 py-2 text-sm">
                        <?php foreach ($badgeCatalogue as $type => $b): ?>
                            <option value="<?= e($type) ?>"><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button name="action" value="award_badge" class="rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-bold px-5 py-2 text-sm">Award</button>
                <button name="action" value="revoke_badge" class="rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold px-5 py-2 text-sm">Revoke</button>
            </form>
        </div>

        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <div>
                <a href="/jobmington/admin/" class="text-slate-500 hover:text-slate-900 text-xs font-bold uppercase tracking-widest mb-2 inline-block transition">
                    <i class="fas fa-arrow-left mr-1"></i> Admin Dashboard
                </a>
                <h1 class="text-4xl font-heading font-black text-slate-900">Badges & Seeds</h1>
                <p class="text-slate-500 mt-2">Manage rewards, badges, and distribute seeds to users</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 p-4 rounded-xl mb-6 flex items-center gap-3">
                <i class="fas fa-check-circle"></i> <?= e($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl mb-6 flex items-center gap-3">
                <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="flex gap-2 mb-8 border-b border-slate-200 pb-4">
            <a href="?tab=badges" class="px-4 py-2 rounded-lg text-sm font-bold <?= $activeTab === 'badges' ? 'bg-blue-700 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100' ?> transition"><i class="fas fa-medal mr-2"></i> Badges</a>
            <a href="?tab=distribute" class="px-4 py-2 rounded-lg text-sm font-bold <?= $activeTab === 'distribute' ? 'bg-blue-700 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100' ?> transition"><i class="fas fa-seedling mr-2"></i> Distribute Seeds</a>
            <a href="?tab=rates" class="px-4 py-2 rounded-lg text-sm font-bold <?= $activeTab === 'rates' ? 'bg-blue-700 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100' ?> transition"><i class="fas fa-sliders-h mr-2"></i> Seed Rates</a>
            <a href="?tab=transactions" class="px-4 py-2 rounded-lg text-sm font-bold <?= $activeTab === 'transactions' ? 'bg-blue-700 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100' ?> transition"><i class="fas fa-history mr-2"></i> Transactions</a>
        </div>

        <?php if ($activeTab === 'badges'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="bg-white rounded-xl p-6">
                <h3 class="text-lg font-bold text-slate-900 mb-4">Create New Badge</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="create_badge">
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Badge Name</label>
                        <input type="text" name="badge_name" required class="w-full rounded-lg px-4 py-3 text-slate-900">
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Description</label>
                        <textarea name="badge_description" rows="2" class="w-full rounded-lg px-4 py-3 text-slate-900"></textarea>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Icon</label>
                            <select name="badge_icon" class="w-full rounded-lg px-4 py-3 text-slate-900">
                                <option value="fa-medal">Medal</option>
                                <option value="fa-trophy">Trophy</option>
                                <option value="fa-star">Star</option>
                                <option value="fa-crown">Crown</option>
                                <option value="fa-gem">Gem</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Color</label>
                            <input type="color" name="badge_color" value="#fbbf24" class="w-full h-12 rounded-lg cursor-pointer">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Seed Reward</label>
                        <input type="number" name="seed_reward" value="50" min="0" class="w-full rounded-lg px-4 py-3 text-slate-900">
                    </div>
                    <button type="submit" class="w-full bg-amber-500 hover:bg-amber-400 text-black font-bold py-3 rounded-lg transition">Create Badge</button>
                </form>
            </div>
            
            <div class="lg:col-span-2">
                <h3 class="text-lg font-bold text-slate-900 mb-4">Existing Badges</h3>
                <?php if (empty($badges)): ?>
                    <div class="bg-white  rounded-xl p-8 text-center">
                        <i class="fas fa-medal text-4xl text-slate-600 mb-4"></i>
                        <p class="text-slate-500">No badges created yet.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($badges as $badge): ?>
                        <div class="bg-white  rounded-xl p-4 hover:bg-slate-50 transition">
                            <div class="flex items-center gap-4">
                                <?php $badgeColor = $badge['color'] ?? '#fbbf24'; ?>
                                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background: <?= e($badgeColor) ?>20; color: <?= e($badgeColor) ?>">
                                    <i class="fas <?= e($badge['icon']) ?> text-xl"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-slate-900"><?= e($badge['name']) ?></h4>
                                    <p class="text-xs text-slate-500"><?= e($badge['description']) ?></p>
                                    <?php if (isset($badge['seed_reward'])): ?>
                                        <span class="text-xs text-amber-400">+<?= number_format($badge['seed_reward']) ?> Seeds</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'distribute'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-white rounded-xl p-6">
                <h3 class="text-lg font-bold text-slate-900 mb-4"><i class="fas fa-user mr-2"></i> Distribute to User</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="distribute_seeds">
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Select User</label>
                        <select name="user_id" required class="w-full rounded-lg px-4 py-3 text-slate-900">
                            <option value="">Choose a user...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['user_id'] ?>"><?= e($user['first_name'] . ' ' . $user['last_name']) ?> (<?= e($user['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Seeds Amount</label>
                        <input type="number" name="amount" required min="1" max="10000" placeholder="100" class="w-full rounded-lg px-4 py-3 text-slate-900">
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Description</label>
                        <input type="text" name="description" value="Admin Bonus" class="w-full rounded-lg px-4 py-3 text-slate-900">
                    </div>
                    <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-bold py-3 rounded-lg transition"><i class="fas fa-seedling mr-2"></i> Award Seeds</button>
                </form>
            </div>
            
            <div class="bg-white rounded-xl p-6">
                <h3 class="text-lg font-bold text-slate-900 mb-4"><i class="fas fa-users mr-2"></i> Bulk Distribution</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="bulk_distribute">
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">User Type</label>
                        <select name="user_type" class="w-full rounded-lg px-4 py-3 text-slate-900">
                            <option value="all">All Users</option>
                            <option value="seeker">Job Seekers Only</option>
                            <option value="employer">Employers Only</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Seeds Amount (Per User)</label>
                        <input type="number" name="bulk_amount" required min="1" max="1000" placeholder="50" class="w-full rounded-lg px-4 py-3 text-slate-900">
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Description</label>
                        <input type="text" name="bulk_description" value="Promotional Bonus" class="w-full rounded-lg px-4 py-3 text-slate-900">
                    </div>
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-400 text-white font-bold py-3 rounded-lg transition" onclick="return confirm('This will award seeds to all selected users. Continue?')"><i class="fas fa-paper-plane mr-2"></i> Distribute to All</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'rates'): ?>
        <div class="bg-white rounded-xl p-6">
            <h3 class="text-lg font-bold text-slate-900 mb-4">Configure Seed Rates</h3>
            <?php if (empty($seedRates)): ?>
                <p class="text-slate-500 text-center py-8">No seed rates configured.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full jm-stacktable">
                        <thead><tr class="border-b border-slate-200"><th class="text-left py-3 px-4 text-slate-500 text-xs font-bold uppercase">Action</th><th class="text-left py-3 px-4 text-slate-500 text-xs font-bold uppercase">Type</th><th class="text-left py-3 px-4 text-slate-500 text-xs font-bold uppercase">Seeds</th><th class="text-left py-3 px-4 text-slate-500 text-xs font-bold uppercase">Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($seedRates as $rate): ?>
                            <tr class="border-b border-white/5 hover:bg-white">
                                <td class="py-3 px-4 text-slate-900"><?= e($rate['action']) ?></td>
                                <td class="py-3 px-4 text-slate-500"><?= e($rate['type']) ?></td>
                                <td class="py-3 px-4"><span class="text-amber-400 font-bold"><?= number_format($rate['seeds_amount']) ?></span></td>
                                <td class="py-3 px-4"><?= $rate['is_active'] ? '<span class="px-2 py-1 bg-emerald-500/20 text-emerald-400 rounded-full text-xs">Active</span>' : '<span class="px-2 py-1 bg-red-500/20 text-red-400 rounded-full text-xs">Inactive</span>' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($activeTab === 'transactions'): ?>
        <div class="bg-white rounded-xl p-6">
            <h3 class="text-lg font-bold text-slate-900 mb-4">Recent Transactions</h3>
            <?php if (empty($recentTransactions)): ?>
                <p class="text-slate-500 text-center py-8">No transactions recorded yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full jm-stacktable">
                        <thead><tr class="border-b border-slate-200"><th class="text-left py-3 px-4 text-slate-500 text-xs font-bold uppercase">User</th><th class="text-left py-3 px-4 text-slate-500 text-xs font-bold uppercase">Type</th><th class="text-left py-3 px-4 text-slate-500 text-xs font-bold uppercase">Amount</th><th class="text-left py-3 px-4 text-slate-500 text-xs font-bold uppercase">Source</th><th class="text-left py-3 px-4 text-slate-500 text-xs font-bold uppercase">Date</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentTransactions as $tx): ?>
                            <tr class="border-b border-white/5 hover:bg-white">
                                <td class="py-3 px-4 text-slate-900 text-sm"><?= e($tx['first_name'] . ' ' . $tx['last_name']) ?></td>
                                <td class="py-3 px-4"><span class="px-2 py-1 rounded-full text-xs <?= $tx['type'] === 'earn' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400' ?>"><?= ucfirst($tx['type']) ?></span></td>
                                <td class="py-3 px-4 font-bold <?= $tx['type'] === 'earn' ? 'text-emerald-400' : 'text-amber-400' ?>"><?= $tx['type'] === 'earn' ? '+' : '-' ?><?= number_format($tx['amount'], 2) ?></td>
                                <td class="py-3 px-4 text-slate-500 text-sm"><?= e($tx['source']) ?></td>
                                <td class="py-3 px-4 text-slate-500 text-sm"><?= formatDate($tx['created_at'], 'M d, Y H:i') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
