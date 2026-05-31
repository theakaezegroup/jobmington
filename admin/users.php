<?php
/**
 * JOBMINGTON - Admin User Operations
 */

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
$currentAdminId = (int) (Session::userId() ?? 0);

function jm_admin_users_ensure_columns(PDO $pdo): void {
    static $ready = false;

    if ($ready) {
        return;
    }

    $columnExists = static function (string $column) use ($pdo): bool {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$column]);
        return (int) $stmt->fetchColumn() > 0;
    };

    $columns = [
        'failed_login_attempts' => "ALTER TABLE users ADD COLUMN failed_login_attempts INT NOT NULL DEFAULT 0",
        'locked_until' => "ALTER TABLE users ADD COLUMN locked_until DATETIME DEFAULT NULL",
        'last_login' => "ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL",
    ];

    foreach ($columns as $column => $sql) {
        if (!$columnExists($column)) {
            $pdo->exec($sql);
        }
    }

    $ready = true;
}

function jm_admin_user_scalar(PDO $pdo, string $sql, array $params = [], $fallback = 0) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false ? $fallback : $value;
    } catch (Throwable $e) {
        error_log('Admin users scalar error: ' . $e->getMessage());
        return $fallback;
    }
}

function jm_admin_user_rows(PDO $pdo, string $sql, array $params = []): array {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Admin users rows error: ' . $e->getMessage());
        return [];
    }
}

function jm_admin_user_when(?string $datetime): string {
    if (!$datetime) {
        return 'Never';
    }
    return formatDateTime($datetime, 'M d, Y h:i A');
}

function jm_admin_user_name(array $user): string {
    $name = trim((string) ($user['full_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
    return $name !== '' ? $name : 'Unnamed user';
}

function jm_admin_temp_password(): string {
    return 'Jobmington-' . random_int(100000, 999999) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

jm_admin_users_ensure_columns($pdo);

$allowedRoles = [USER_TYPE_ADMIN, USER_TYPE_EMPLOYER, USER_TYPE_SEEKER];

if (isPost()) {
    if (!Security::verifyCSRF()) {
        Session::flash('error', 'Invalid security token. Please try again.');
        redirect('/jobmington/admin/users.php');
    }

    $action = (string) post('action', '');
    $userId = (int) post('user_id', 0);

    if ($userId <= 0) {
        Session::flash('error', 'Choose a valid user first.');
        redirect('/jobmington/admin/users.php');
    }

    try {
        if ($action === 'change_role') {
            $role = (string) post('role', USER_TYPE_SEEKER);
            if (!in_array($role, $allowedRoles, true)) {
                throw new RuntimeException('Unsupported role.');
            }
            if ($userId === $currentAdminId && $role !== USER_TYPE_ADMIN) {
                throw new RuntimeException('You cannot remove your own admin role.');
            }
            $stmt = $pdo->prepare('UPDATE users SET user_type = ? WHERE user_id = ?');
            $stmt->execute([$role, $userId]);
            Session::flash('success', 'User role updated.');
        } elseif ($action === 'toggle_active') {
            if ($userId === $currentAdminId) {
                throw new RuntimeException('You cannot deactivate your own account.');
            }
            $value = post('value', '0') === '1' ? 1 : 0;
            $stmt = $pdo->prepare('UPDATE users SET is_active = ? WHERE user_id = ?');
            $stmt->execute([$value, $userId]);
            Session::flash('success', $value ? 'User activated.' : 'User suspended.');
        } elseif ($action === 'verify') {
            $stmt = $pdo->prepare('UPDATE users SET is_verified = 1 WHERE user_id = ?');
            $stmt->execute([$userId]);
            Session::flash('success', 'User marked as verified.');
        } elseif ($action === 'unlock') {
            $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE user_id = ?');
            $stmt->execute([$userId]);
            Session::flash('success', 'Login lockout cleared.');
        } elseif ($action === 'reset_password') {
            $tempPassword = jm_admin_temp_password();
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL WHERE user_id = ?');
            $stmt->execute([password_hash($tempPassword, PASSWORD_DEFAULT), $userId]);
            Session::flash('success', 'Temporary password generated: ' . $tempPassword);
        } else {
            throw new RuntimeException('Unsupported user action.');
        }
    } catch (Throwable $e) {
        error_log('Admin user action error: ' . $e->getMessage());
        Session::flash('error', $e->getMessage());
    }

    $returnQuery = $_SERVER['QUERY_STRING'] ?? '';
    redirect('/jobmington/admin/users.php' . ($returnQuery ? '?' . $returnQuery : ''));
}

$query = trim((string) get('q', ''));
$roleFilter = (string) get('role', 'all');
$statusFilter = (string) get('status', 'all');
$sort = (string) get('sort', 'newest');

$where = [];
$params = [];

if ($query !== '') {
    $where[] = "(u.email LIKE ? OR u.full_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $needle = '%' . $query . '%';
    array_push($params, $needle, $needle, $needle, $needle);
}

if (in_array($roleFilter, $allowedRoles, true)) {
    $where[] = 'u.user_type = ?';
    $params[] = $roleFilter;
}

if ($statusFilter === 'active') {
    $where[] = 'u.is_active = 1';
} elseif ($statusFilter === 'suspended') {
    $where[] = 'COALESCE(u.is_active, 0) = 0';
} elseif ($statusFilter === 'unverified') {
    $where[] = 'COALESCE(u.is_verified, 0) = 0';
} elseif ($statusFilter === 'locked') {
    $where[] = 'u.locked_until IS NOT NULL AND u.locked_until > NOW()';
}

$orderSql = match ($sort) {
    'oldest' => 'u.created_at ASC',
    'last_login' => 'u.last_login DESC, u.created_at DESC',
    'role' => 'u.user_type ASC, u.created_at DESC',
    default => 'u.created_at DESC',
};

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$users = jm_admin_user_rows($pdo, "
    SELECT u.user_id, u.full_name, u.first_name, u.last_name, u.email, u.user_type,
           u.is_active, u.is_verified, u.created_at, u.last_login,
           u.failed_login_attempts, u.locked_until,
           (SELECT COUNT(*) FROM job_applications ja WHERE ja.user_id = u.user_id) AS application_count,
           (SELECT COUNT(*) FROM companies c WHERE c.user_id = u.user_id) AS company_count,
           (SELECT COALESCE(w.balance, 0) FROM wallets w WHERE w.user_id = u.user_id LIMIT 1) AS seed_balance
    FROM users u
    {$whereSql}
    ORDER BY {$orderSql}
    LIMIT 120
", $params);

$totalMatching = (int) jm_admin_user_scalar($pdo, "SELECT COUNT(*) FROM users u {$whereSql}", $params);

$stats = [
    ['label' => 'Total users', 'value' => number_format((int) jm_admin_user_scalar($pdo, 'SELECT COUNT(*) FROM users')), 'detail' => number_format($totalMatching) . ' in this view', 'tone' => 'blue'],
    ['label' => 'Active', 'value' => number_format((int) jm_admin_user_scalar($pdo, 'SELECT COUNT(*) FROM users WHERE is_active = 1')), 'detail' => 'Can access the platform', 'tone' => 'good'],
    ['label' => 'Unverified', 'value' => number_format((int) jm_admin_user_scalar($pdo, 'SELECT COUNT(*) FROM users WHERE COALESCE(is_verified, 0) = 0')), 'detail' => 'Need identity/email review', 'tone' => 'warning'],
    ['label' => 'Locked', 'value' => number_format((int) jm_admin_user_scalar($pdo, 'SELECT COUNT(*) FROM users WHERE locked_until IS NOT NULL AND locked_until > NOW()')), 'detail' => 'Login lockout active', 'tone' => 'danger'],
];

$roleStats = [
    'Admins' => (int) jm_admin_user_scalar($pdo, "SELECT COUNT(*) FROM users WHERE user_type = ?", [USER_TYPE_ADMIN]),
    'Employers' => (int) jm_admin_user_scalar($pdo, "SELECT COUNT(*) FROM users WHERE user_type = ?", [USER_TYPE_EMPLOYER]),
    'Seekers' => (int) jm_admin_user_scalar($pdo, "SELECT COUNT(*) FROM users WHERE user_type = ?", [USER_TYPE_SEEKER]),
];

$pageTitle = 'Users Command Center | ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .admin-users-hero,
    .admin-users-card,
    .admin-users-table {
        background: #ffffff;
        border: 1px solid #d8e4f4;
        border-radius: 8px;
    }
    .admin-users-hero {
        display: grid;
        gap: 20px;
        grid-template-columns: minmax(0, 1.4fr) minmax(280px, 0.6fr);
        margin-bottom: 18px;
        padding: 22px;
    }
    .admin-users-kicker {
        color: #0640a3;
        font-size: 12px;
        font-weight: 700;
        margin: 0 0 8px;
        text-transform: uppercase;
    }
    .admin-users-hero h1 {
        color: #061426;
        font-size: clamp(30px, 5vw, 48px);
        line-height: 1;
        margin: 0 0 10px;
    }
    .admin-users-hero p {
        color: #53667f;
        margin: 0;
        max-width: 720px;
    }
    .admin-users-actions {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
    }
    .admin-users-btn,
    .admin-users-action {
        align-items: center;
        border: 1px solid #cfe0f7;
        border-radius: 8px;
        display: inline-flex;
        font-size: 13px;
        gap: 8px;
        min-height: 38px;
        padding: 8px 11px;
        text-decoration: none;
    }
    .admin-users-btn {
        background: #0640a3;
        color: #ffffff;
    }
    .admin-users-action {
        background: #f7faff;
        color: #061426;
    }
    .admin-users-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 18px;
    }
    .admin-users-card {
        padding: 16px;
    }
    .admin-users-card span {
        color: #53667f;
        display: block;
        font-size: 12px;
    }
    .admin-users-card strong {
        color: #061426;
        display: block;
        font-size: 30px;
        line-height: 1;
        margin: 7px 0;
    }
    .admin-users-card.tone-good { border-color: #bfe9d9; }
    .admin-users-card.tone-warning { border-color: #ffe2b5; }
    .admin-users-card.tone-danger { border-color: #ffc9c9; }
    .admin-users-filters {
        align-items: end;
        display: grid;
        gap: 12px;
        grid-template-columns: minmax(240px, 1fr) 170px 170px 170px auto;
        margin-bottom: 18px;
        padding: 16px;
    }
    .admin-users-field label {
        color: #53667f;
        display: block;
        font-size: 12px;
        margin-bottom: 6px;
    }
    .admin-users-field input,
    .admin-users-field select {
        background: #ffffff;
        border: 1px solid #cfe0f7;
        border-radius: 8px;
        color: #061426;
        min-height: 42px;
        padding: 9px 11px;
        width: 100%;
    }
    .admin-users-table {
        overflow-x: auto;
    }
    .admin-users-table table {
        border-collapse: collapse;
        min-width: 1120px;
        width: 100%;
    }
    .admin-users-table th,
    .admin-users-table td {
        border-bottom: 1px solid #e6eef9;
        padding: 13px 14px;
        text-align: left;
        vertical-align: top;
    }
    .admin-users-table th {
        color: #53667f;
        font-size: 11px;
        text-transform: uppercase;
    }
    .admin-users-name {
        color: #061426;
        font-weight: 700;
    }
    .admin-users-meta {
        color: #53667f;
        display: block;
        font-size: 12px;
        margin-top: 3px;
    }
    .admin-users-pill {
        border: 1px solid #d8e4f4;
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        gap: 6px;
        padding: 5px 8px;
        white-space: nowrap;
    }
    .admin-users-pill.good { background: #effaf5; border-color: #bfe9d9; color: #0f766e; }
    .admin-users-pill.warning { background: #fff8ec; border-color: #ffe2b5; color: #9a5b00; }
    .admin-users-pill.danger { background: #fff5f5; border-color: #ffc9c9; color: #b42318; }
    .admin-users-controls {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        min-width: 280px;
    }
    .admin-users-controls form {
        display: inline-flex;
    }
    .admin-users-controls button,
    .admin-users-controls select {
        background: #ffffff;
        border: 1px solid #cfe0f7;
        border-radius: 8px;
        color: #061426;
        font-size: 12px;
        min-height: 34px;
        padding: 7px 9px;
    }
    .admin-users-controls button.danger {
        background: #fff5f5;
        border-color: #ffc9c9;
        color: #b42318;
    }
    .admin-users-flash {
        border: 1px solid #d8e4f4;
        border-radius: 8px;
        margin-bottom: 12px;
        padding: 12px 14px;
    }
    .admin-users-flash.success { background: #effaf5; border-color: #bfe9d9; color: #0f766e; }
    .admin-users-flash.error { background: #fff5f5; border-color: #ffc9c9; color: #b42318; }
    .admin-users-rolebar {
        color: #53667f;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 14px;
    }
    .admin-users-rolebar span {
        background: #f7faff;
        border: 1px solid #d8e4f4;
        border-radius: 999px;
        padding: 7px 10px;
    }
    @media (max-width: 1040px) {
        .admin-users-hero,
        .admin-users-filters {
            grid-template-columns: 1fr;
        }
        .admin-users-actions {
            justify-content: flex-start;
        }
        .admin-users-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 560px) {
        .admin-users-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php foreach (Session::getFlash('success') as $message): ?>
    <div class="admin-users-flash success"><?= e($message) ?></div>
<?php endforeach; ?>
<?php foreach (Session::getFlash('error') as $message): ?>
    <div class="admin-users-flash error"><?= e($message) ?></div>
<?php endforeach; ?>

<section class="admin-users-hero">
    <div>
        <p class="admin-users-kicker">Account operations</p>
        <h1>Users Command Center.</h1>
        <p>Search accounts, review verification and lockout signals, change roles, suspend access, unlock accounts, and generate temporary passwords from one controlled surface.</p>
        <div class="admin-users-rolebar">
            <?php foreach ($roleStats as $label => $count): ?>
                <span><?= e($label) ?>: <strong><?= number_format($count) ?></strong></span>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="admin-users-actions">
        <a class="admin-users-action" href="/jobmington/admin/"><i class="fas fa-gauge-high"></i> Dashboard</a>
        <a class="admin-users-action" href="/jobmington/admin/operations.php"><i class="fas fa-heartbeat"></i> Operations</a>
        <a class="admin-users-btn" href="/jobmington/auth/register.php"><i class="fas fa-user-plus"></i> Create account</a>
    </div>
</section>

<section class="admin-users-grid" aria-label="User metrics">
    <?php foreach ($stats as $stat): ?>
        <article class="admin-users-card tone-<?= e($stat['tone']) ?>">
            <span><?= e($stat['label']) ?></span>
            <strong><?= e($stat['value']) ?></strong>
            <span><?= e($stat['detail']) ?></span>
        </article>
    <?php endforeach; ?>
</section>

<form class="admin-users-card admin-users-filters" method="GET">
    <div class="admin-users-field">
        <label for="q">Search users</label>
        <input id="q" type="search" name="q" value="<?= e($query) ?>" placeholder="Name or email">
    </div>
    <div class="admin-users-field">
        <label for="role">Role</label>
        <select id="role" name="role">
            <option value="all">All roles</option>
            <?php foreach ($allowedRoles as $role): ?>
                <option value="<?= e($role) ?>" <?= $roleFilter === $role ? 'selected' : '' ?>><?= e(ucfirst($role)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="admin-users-field">
        <label for="status">Status</label>
        <select id="status" name="status">
            <?php foreach (['all' => 'All statuses', 'active' => 'Active', 'suspended' => 'Suspended', 'unverified' => 'Unverified', 'locked' => 'Locked'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="admin-users-field">
        <label for="sort">Sort</label>
        <select id="sort" name="sort">
            <?php foreach (['newest' => 'Newest', 'oldest' => 'Oldest', 'last_login' => 'Last login', 'role' => 'Role'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="admin-users-btn" type="submit"><i class="fas fa-filter"></i> Apply</button>
</form>

<section class="admin-users-table">
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Role</th>
                <th>Status</th>
                <th>Activity</th>
                <th>Platform value</th>
                <th>Controls</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$users): ?>
                <tr><td colspan="6">No users match this view.</td></tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <?php
                        $isLocked = !empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time();
                        $isActive = (int) $user['is_active'] === 1;
                        $isVerified = (int) $user['is_verified'] === 1;
                    ?>
                    <tr>
                        <td>
                            <span class="admin-users-name"><?= e(jm_admin_user_name($user)) ?></span>
                            <span class="admin-users-meta"><?= e($user['email']) ?></span>
                            <span class="admin-users-meta">ID <?= (int) $user['user_id'] ?> / joined <?= e(jm_admin_user_when($user['created_at'])) ?></span>
                        </td>
                        <td>
                            <span class="admin-users-pill"><?= e(ucfirst((string) $user['user_type'])) ?></span>
                        </td>
                        <td>
                            <span class="admin-users-pill <?= $isActive ? 'good' : 'danger' ?>"><?= $isActive ? 'Active' : 'Suspended' ?></span>
                            <span class="admin-users-pill <?= $isVerified ? 'good' : 'warning' ?>"><?= $isVerified ? 'Verified' : 'Unverified' ?></span>
                            <?php if ($isLocked): ?>
                                <span class="admin-users-pill danger">Locked</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="admin-users-meta">Last login: <?= e(jm_admin_user_when($user['last_login'])) ?></span>
                            <span class="admin-users-meta">Failed attempts: <?= number_format((int) $user['failed_login_attempts']) ?></span>
                            <?php if ($isLocked): ?>
                                <span class="admin-users-meta">Until <?= e(jm_admin_user_when($user['locked_until'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="admin-users-meta"><?= number_format((int) $user['application_count']) ?> applications</span>
                            <span class="admin-users-meta"><?= number_format((int) $user['company_count']) ?> companies</span>
                            <span class="admin-users-meta"><?= number_format((float) $user['seed_balance']) ?> Seeds</span>
                        </td>
                        <td>
                            <div class="admin-users-controls">
                                <form method="POST">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="change_role">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                    <select name="role" onchange="this.form.submit()" aria-label="Change role">
                                        <?php foreach ($allowedRoles as $role): ?>
                                            <option value="<?= e($role) ?>" <?= $user['user_type'] === $role ? 'selected' : '' ?>><?= e(ucfirst($role)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                                <form method="POST">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                    <input type="hidden" name="value" value="<?= $isActive ? '0' : '1' ?>">
                                    <button class="<?= $isActive ? 'danger' : '' ?>" type="submit"><?= $isActive ? 'Suspend' : 'Activate' ?></button>
                                </form>
                                <?php if (!$isVerified): ?>
                                    <form method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="verify">
                                        <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                        <button type="submit">Verify</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($isLocked || (int) $user['failed_login_attempts'] > 0): ?>
                                    <form method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="unlock">
                                        <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                        <button type="submit">Unlock</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" onsubmit="return confirm('Generate a temporary password for this user? The new password will be shown once in the admin flash message.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                    <button type="submit">Temp password</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
