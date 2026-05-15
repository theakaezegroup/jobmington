<?php
/**
 * JOBMINGTON - Wallet Transaction History
 * Full transaction history with filtering and pagination
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
Session::requireLogin();

$pdo = db();
$userId = Session::userId();

// Pagination
$page = max(1, (int) get('page', 1));
$perPage = 20;
$filter = get('filter', 'all'); // all, earned, spent

// Build query based on filter
$whereClause = "WHERE user_id = ?";
$params = [$userId];

if ($filter === 'earned') {
    $whereClause .= " AND type IN ('earn', 'bonus', 'refund', 'purchase', 'transfer_in')";
} elseif ($filter === 'spent') {
    $whereClause .= " AND type NOT IN ('earn', 'bonus', 'refund', 'purchase', 'transfer_in')";
}

// Get total count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM seed_transactions {$whereClause}");
$stmt->execute($params);
$totalCount = $stmt->fetchColumn();

$pagination = paginate($totalCount, $perPage, $page);

// Get transactions
$stmt = $pdo->prepare("
    SELECT * FROM seed_transactions 
    {$whereClause}
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$params[] = $pagination['per_page'];
$params[] = $pagination['offset'];
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get wallet summary
$wallet = getWallet($userId);

$pageTitle = 'Transaction History | ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    body { background: #020617; color: #f8fafc; }
</style>

<div class="max-w-4xl mx-auto px-4 pt-20 pb-12">
    
    <div class="flex items-center justify-between mb-8">
        <div>
            <a href="/jobmington/wallet/" class="text-slate-400 hover:text-white text-xs font-bold uppercase tracking-widest mb-2 inline-block transition">
                <i class="fas fa-arrow-left mr-2"></i> Back to Wallet
            </a>
            <h1 class="text-3xl font-black text-white">Transaction History</h1>
        </div>
        <?php if ($wallet): ?>
        <div class="text-right">
            <div class="text-sm text-slate-400">Current Balance</div>
            <div class="text-2xl font-bold text-amber-400"><?= number_format($wallet['balance'], 2) ?> Seeds</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filter Tabs -->
    <div class="flex gap-2 mb-6">
        <a href="?filter=all" class="px-4 py-2 rounded-lg font-bold text-sm transition <?= $filter === 'all' ? 'bg-white text-black' : 'bg-white/10 text-slate-300 hover:bg-white/20' ?>">
            All
        </a>
        <a href="?filter=earned" class="px-4 py-2 rounded-lg font-bold text-sm transition <?= $filter === 'earned' ? 'bg-emerald-500 text-white' : 'bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20' ?>">
            <i class="fas fa-arrow-down mr-1"></i> Earned
        </a>
        <a href="?filter=spent" class="px-4 py-2 rounded-lg font-bold text-sm transition <?= $filter === 'spent' ? 'bg-amber-500 text-white' : 'bg-amber-500/10 text-amber-400 hover:bg-amber-500/20' ?>">
            <i class="fas fa-arrow-up mr-1"></i> Spent
        </a>
    </div>

    <!-- Transaction List -->
    <div class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
        <?php if (empty($transactions)): ?>
            <?= jm_empty_state_card('all_good', [
                'compact' => true,
                'class' => 'm-6',
            ]) ?>
        <?php else: ?>
        <div class="divide-y divide-white/5">
            <?php foreach ($transactions as $tx): 
                $isEarn = in_array($tx['type'], ['earn', 'bonus', 'refund', 'purchase', 'transfer_in']);
                $iconClass = match($tx['source'] ?? '') {
                    'signup_bonus', 'daily_login' => 'fa-gift',
                    'email_verify' => 'fa-envelope-circle-check',
                    'course_complete', 'course_enroll' => 'fa-graduation-cap',
                    'quiz_pass' => 'fa-check-circle',
                    'certificate_earn' => 'fa-certificate',
                    'job_apply' => 'fa-paper-plane',
                    'forum_post', 'forum_reply' => 'fa-comments',
                    'cv_create' => 'fa-file-lines',
                    'ai_chat_basic', 'ai_chat_premium' => 'fa-brain',
                    'cv_roast', 'cv_optimize' => 'fa-wand-magic-sparkles',
                    'purchase' => 'fa-shopping-cart',
                    'referral_signup' => 'fa-user-plus',
                    default => $isEarn ? 'fa-arrow-down' : 'fa-arrow-up'
                };
                $colorClass = $isEarn ? 'emerald' : 'amber';
            ?>
            <div class="flex items-center justify-between p-4 hover:bg-white/[0.02] transition">
                <div class="flex items-center gap-4">
                    <div class="h-10 w-10 rounded-xl bg-<?= $colorClass ?>-500/10 flex items-center justify-center text-<?= $colorClass ?>-400 border border-<?= $colorClass ?>-500/20">
                        <i class="fas <?= $iconClass ?>"></i>
                    </div>
                    <div>
                        <div class="text-white font-bold"><?= e($tx['description']) ?></div>
                        <div class="text-xs text-slate-500 mt-1">
                            <?= ucfirst(str_replace('_', ' ', $tx['source'] ?? $tx['type'])) ?>
                            • <?= date('M j, Y \a\t g:i A', strtotime($tx['created_at'])) ?>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-lg font-bold text-<?= $isEarn ? 'emerald' : 'slate' ?>-<?= $isEarn ? '400' : '300' ?>">
                        <?= $isEarn ? '+' : '-' ?><?= number_format($tx['amount'], 2) ?>
                    </div>
                    <div class="text-xs text-slate-600">Seeds</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalCount > $perPage): ?>
    <div class="mt-8 flex justify-center">
        <div class="inline-flex rounded-xl bg-white/5 border border-white/10 p-1">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&filter=<?= $filter ?>" class="px-4 py-2 text-slate-400 hover:text-white transition">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <span class="px-4 py-2 text-white font-bold">
                Page <?= $page ?> of <?= $pagination['total_pages'] ?>
            </span>
            
            <?php if ($page < $pagination['total_pages']): ?>
            <a href="?page=<?= $page + 1 ?>&filter=<?= $filter ?>" class="px-4 py-2 text-slate-400 hover:text-white transition">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
