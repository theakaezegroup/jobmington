<?php
/**
 * JOBMINGTON - Admin: delete an account.
 *
 * Its own page rather than another button in the row, because this is the one
 * action on the users table that cannot be undone. A row of small buttons is
 * the wrong place to put something irreversible: the mis-click costs a person's
 * account, and there is no reading of the list that shows what the click would
 * actually take with it.
 *
 * So it shows the count for every table first, and asks for the email address
 * to be typed. That is not ceremony. It is the only thing that catches
 * deleting the row above the one you meant.
 *
 * Page styling deliberately matches the rest of this admin panel.
 */
define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/account_deletion.php';

Session::start();
Session::requireAdmin();

$adminId = (int) Session::userId();
$userId  = (int) (post('user_id', 0) ?: get('id', 0));
$error   = '';

if (isPost()) {
    if (!Security::verifyCSRF()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $preview = jm_account_deletion_preview($userId, $adminId);
        $typed   = strtolower(trim((string) post('confirm_email', '')));
        $actual  = strtolower(trim((string) ($preview['user']['email'] ?? '')));

        if (!$preview['user']) {
            Session::flash('error', 'That account no longer exists.');
            redirect('/jobmington/admin/users.php');
        } elseif ($preview['blockers']) {
            $error = $preview['blockers'][0];
        } elseif ($typed === '' || $typed !== $actual) {
            $error = 'That did not match the account email, so nothing was deleted.';
        } else {
            $result = jm_delete_account($userId, $adminId);
            Session::flash($result['ok'] ? 'success' : 'error', $result['message']);
            redirect('/jobmington/admin/users.php');
        }
    }
}

$preview = jm_account_deletion_preview($userId, $adminId);

if (!$preview['user']) {
    Session::flash('error', 'That account could not be found.');
    redirect('/jobmington/admin/users.php');
}

$user      = $preview['user'];
$blocked   = $preview['blockers'] !== [];
$pageTitle = 'Delete ' . $user['full_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-100 py-8">
    <div class="max-w-3xl mx-auto px-4">

        <div class="mb-6">
            <a href="/jobmington/admin/users.php" class="text-sm text-slate-500 hover:text-slate-800">&larr; Back to users</a>
            <h1 class="text-2xl font-bold text-slate-900 mt-2">Delete this account</h1>
            <p class="text-sm text-slate-500 mt-1">This cannot be undone.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 mb-6 text-sm font-semibold">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white border border-slate-200 rounded-xl p-5 mb-6">
            <p class="text-lg font-bold text-slate-900"><?= e($user['full_name']) ?></p>
            <p class="text-sm text-slate-600"><?= e($user['email']) ?></p>
            <p class="text-xs text-slate-400 mt-2">
                <?= e(ucfirst($user['user_type'])) ?> &middot; joined <?= e(date('j M Y', strtotime($user['created_at']))) ?>
            </p>
        </div>

        <?php if ($blocked): ?>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-6">
                <p class="font-bold text-amber-900 mb-1">This account cannot be deleted.</p>
                <?php foreach ($preview['blockers'] as $b): ?>
                    <p class="text-sm text-amber-800"><?= e($b) ?></p>
                <?php endforeach; ?>
            </div>
            <a href="/jobmington/admin/users.php" class="inline-block bg-slate-800 text-white rounded-lg px-4 py-2 text-sm font-semibold">Back to users</a>

        <?php else: ?>

            <div class="grid md:grid-cols-2 gap-4 mb-6">
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-slate-200">
                        <p class="font-bold text-slate-900 text-sm">Deleted with the account</p>
                        <p class="text-xs text-slate-500">Personal to this person.</p>
                    </div>
                    <?php if (!$preview['remove']): ?>
                        <p class="px-4 py-4 text-sm text-slate-400">Nothing. This account has no activity.</p>
                    <?php else: ?>
                        <ul class="divide-y divide-slate-100">
                            <?php foreach ($preview['remove'] as $table => $count): ?>
                                <li class="flex items-baseline justify-between gap-3 px-4 py-2 text-sm">
                                    <span class="text-slate-600"><?= e(str_replace('_', ' ', $table)) ?></span>
                                    <span class="font-bold text-slate-900 tabular-nums"><?= number_format($count) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-slate-200">
                        <p class="font-bold text-slate-900 text-sm">Kept, no longer named</p>
                        <p class="text-xs text-slate-500">Stays on the site, pointing at nobody.</p>
                    </div>
                    <?php if (!$preview['detach']): ?>
                        <p class="px-4 py-4 text-sm text-slate-400">Nothing. They created no public content and made no payments.</p>
                    <?php else: ?>
                        <ul class="divide-y divide-slate-100">
                            <?php foreach ($preview['detach'] as $table => $info): ?>
                                <li class="px-4 py-2 text-sm">
                                    <div class="flex items-baseline justify-between gap-3">
                                        <span class="text-slate-600"><?= e(str_replace('_', ' ', $table)) ?></span>
                                        <span class="font-bold text-slate-900 tabular-nums"><?= number_format($info['count']) ?></span>
                                    </div>
                                    <p class="text-xs text-slate-400"><?= e($info['why']) ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST" class="bg-white border border-red-200 rounded-xl p-5">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">

                <label class="block text-sm font-semibold text-slate-800 mb-2">
                    Type <span class="font-mono text-red-700"><?= e($user['email']) ?></span> to confirm
                </label>
                <input type="text" name="confirm_email" autocomplete="off" spellcheck="false" required
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm mb-4"
                       placeholder="<?= e($user['email']) ?>">

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white rounded-lg px-4 py-2 text-sm font-semibold">
                        Delete this account permanently
                    </button>
                    <a href="/jobmington/admin/users.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg px-4 py-2 text-sm font-semibold">
                        Cancel
                    </a>
                </div>
            </form>

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
