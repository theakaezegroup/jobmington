<?php
/**
 * JOBMINGTON - Admin: Certificate Branding
 * Upload a custom official seal and up to two signatures (image + name + title)
 * used on every issued certificate. Empty fields fall back to the built-in
 * vector seal / signature placeholders.
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

$msg = '';
$err = '';

$UPLOAD_REL = '/uploads/certificate';
$UPLOAD_DIR = dirname(__DIR__) . $UPLOAD_REL;

/** Handle a single image field: returns new stored path, or null to keep/clear. */
function jm_cert_upload(string $field, string $dir, string $rel): ?string {
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    if ($_FILES[$field]['size'] > 3 * 1024 * 1024) return null; // 3MB cap
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)) return null;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dir . '/' . $name)) {
        return $rel . '/' . $name;
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRF()) {
        $err = 'Security token expired. Please try again.';
    } else {
        // text fields
        jm_setting_set('cert_sig1_name', trim((string) post('sig1_name', '')));
        jm_setting_set('cert_sig1_title', trim((string) post('sig1_title', '')));
        jm_setting_set('cert_sig2_name', trim((string) post('sig2_name', '')));
        jm_setting_set('cert_sig2_title', trim((string) post('sig2_title', '')));
        jm_setting_set('cert_subtitle', trim((string) post('subtitle', '')));
        jm_setting_set('cert_brand_name', trim((string) post('brand_name', '')));

        // images: upload replaces; "remove" checkbox clears
        foreach ([
            'seal' => 'cert_seal_image',
            'sig1' => 'cert_sig1_image',
            'sig2' => 'cert_sig2_image',
        ] as $field => $key) {
            if (post('remove_' . $field)) {
                jm_setting_set($key, '');
            } else {
                $path = jm_cert_upload($field, $UPLOAD_DIR, $UPLOAD_REL);
                if ($path !== null) jm_setting_set($key, $path);
            }
        }

        Security::regenerateCSRF();
        $msg = 'Certificate branding saved.';
    }
}

// current values
$sealImg  = jm_setting_get('cert_seal_image', '');
$sig1Img  = jm_setting_get('cert_sig1_image', '');
$sig2Img  = jm_setting_get('cert_sig2_image', '');
$sig1Name = jm_setting_get('cert_sig1_name', '');
$sig1Ttl  = jm_setting_get('cert_sig1_title', '');
$sig2Name = jm_setting_get('cert_sig2_name', '');
$sig2Ttl  = jm_setting_get('cert_sig2_title', '');
$subtitle = jm_setting_get('cert_subtitle', '');
$brandName = jm_setting_get('cert_brand_name', '');

// most recent issued cert (for live preview)
$previewCode = $pdo->query("SELECT cert_code FROM certificates ORDER BY issued_at DESC LIMIT 1")->fetchColumn();

$pageTitle = 'Certificate Branding - Admin Panel';
require_once __DIR__ . '/../includes/header.php';

$imgTag = function (string $path): string {
    if ($path === '') return '<span class="text-slate-400 text-sm">None — using built-in vector</span>';
    return '<img src="/jobmington' . htmlspecialchars($path) . '" alt="" style="max-height:80px;max-width:160px;object-fit:contain;background:#f1f5f9;border-radius:8px;padding:6px;">';
};
?>
<div class="min-h-screen bg-slate-100 py-8">
    <div class="max-w-5xl mx-auto px-4">
        <div class="mb-6 flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Certificate Branding</h1>
                <p class="text-slate-600">Upload your official seal and signatures. Empty fields use the built-in vector design.</p>
            </div>
            <?php if ($previewCode): ?>
            <a href="/jobmington/certificates/view.php?code=<?= htmlspecialchars($previewCode) ?>" target="_blank"
               class="bg-white border border-slate-300 text-slate-700 font-semibold px-4 py-2 rounded-lg hover:bg-slate-50 transition text-sm">
                <i class="fas fa-external-link-alt mr-2"></i> Preview a real certificate
            </a>
            <?php endif; ?>
        </div>

        <?php if ($msg): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="space-y-6">
            <?= Security::csrfField() ?>

            <!-- Official Seal -->
            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="font-bold text-slate-900 mb-1 flex items-center gap-2"><i class="fas fa-stamp text-blue-600"></i> Official Seal</h3>
                <p class="text-sm text-slate-500 mb-4">Transparent PNG or SVG recommended (square). Leave empty to use the built-in vector seal.</p>
                <div class="flex items-center gap-6 flex-wrap">
                    <div class="flex-shrink-0"><?= $imgTag($sealImg) ?></div>
                    <div class="flex-1 min-w-[240px]">
                        <input type="file" name="seal" accept=".png,.jpg,.jpeg,.webp,.svg" class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 file:font-semibold hover:file:bg-blue-100">
                        <?php if ($sealImg): ?>
                        <label class="inline-flex items-center gap-2 mt-3 text-sm text-red-600"><input type="checkbox" name="remove_seal" value="1" class="rounded"> Remove seal (revert to vector)</label>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Signature 1 -->
            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2"><i class="fas fa-pen-nib text-blue-600"></i> Signature 1 (left)</h3>
                <div class="grid md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Signatory name</label>
                        <input type="text" name="sig1_name" value="<?= htmlspecialchars($sig1Name) ?>" placeholder="e.g. Jane Doe" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <label class="block text-sm font-medium text-slate-700 mb-2 mt-4">Title</label>
                        <input type="text" name="sig1_title" value="<?= htmlspecialchars($sig1Ttl) ?>" placeholder="e.g. Director, Jobmington" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Signature image</label>
                        <div class="mb-3"><?= $imgTag($sig1Img) ?></div>
                        <input type="file" name="sig1" accept=".png,.jpg,.jpeg,.webp,.svg" class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 file:font-semibold hover:file:bg-blue-100">
                        <?php if ($sig1Img): ?>
                        <label class="inline-flex items-center gap-2 mt-3 text-sm text-red-600"><input type="checkbox" name="remove_sig1" value="1" class="rounded"> Remove image</label>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Signature 2 -->
            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2"><i class="fas fa-pen-nib text-blue-600"></i> Signature 2 (right)</h3>
                <div class="grid md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Signatory name</label>
                        <input type="text" name="sig2_name" value="<?= htmlspecialchars($sig2Name) ?>" placeholder="e.g. John Smith" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <label class="block text-sm font-medium text-slate-700 mb-2 mt-4">Title</label>
                        <input type="text" name="sig2_title" value="<?= htmlspecialchars($sig2Ttl) ?>" placeholder="e.g. Head, Career Learning" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Signature image</label>
                        <div class="mb-3"><?= $imgTag($sig2Img) ?></div>
                        <input type="file" name="sig2" accept=".png,.jpg,.jpeg,.webp,.svg" class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 file:font-semibold hover:file:bg-blue-100">
                        <?php if ($sig2Img): ?>
                        <label class="inline-flex items-center gap-2 mt-3 text-sm text-red-600"><input type="checkbox" name="remove_sig2" value="1" class="rounded"> Remove image</label>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Wording -->
            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2"><i class="fas fa-font text-blue-600"></i> Wording</h3>
                <div class="grid md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Brand name (beside the logo)</label>
                        <input type="text" name="brand_name" value="<?= htmlspecialchars($brandName) ?>" placeholder="Jobmington" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-slate-500 mt-2">Defaults to "Jobmington" if left empty.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Subtitle under "Certificate"</label>
                        <input type="text" name="subtitle" value="<?= htmlspecialchars($subtitle) ?>" placeholder="of Completion" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-slate-500 mt-2">Defaults to "of Completion" if left empty.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow p-6">
                <button type="submit" class="bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                    <i class="fas fa-save"></i> Save branding
                </button>
            </div>
        </form>

        <?php if ($previewCode):
            $stmt = $pdo->prepare("SELECT cert.*, c.title AS course_title, u.full_name FROM certificates cert JOIN courses c ON cert.course_id=c.course_id LEFT JOIN users u ON cert.user_id=u.user_id WHERE cert.cert_code=? LIMIT 1");
            $stmt->execute([$previewCode]);
            $cert = $stmt->fetch();
            if ($cert): ?>
        <div class="bg-white rounded-xl shadow p-6 mt-8">
            <h3 class="font-bold text-slate-900 mb-4 flex items-center gap-2"><i class="fas fa-eye text-blue-600"></i> Live preview</h3>
            <?php require __DIR__ . '/../certificates/_cert_template.php'; ?>
        </div>
        <?php endif; endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
