<?php
/**
 * JOBMINGTON - My Certificates
 * Display all earned certificates
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();
Session::requireLogin();

$pdo = db();
$userId = Session::userId();

// Get User's Certificates
$stmt = $pdo->prepare("
    SELECT cert.*, c.title as course_title, c.thumbnail, c.description
    FROM certificates cert
    JOIN courses c ON cert.course_id = c.course_id
    WHERE cert.user_id = ?
    ORDER BY cert.issued_at DESC
");
$stmt->execute([$userId]);
$certificates = $stmt->fetchAll();

$pageTitle = 'My Certificates - ' . SITE_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="bg-gradient-to-br from-primary via-blue-800 to-primary py-12 relative overflow-hidden">
    <!-- Pattern -->
    <div class="absolute inset-0 opacity-10">
        <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.4\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
    </div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative text-center">
        <div class="inline-block bg-secondary text-white text-sm font-bold px-4 py-1 rounded-full mb-4">
            <i class="fas fa-certificate mr-2"></i> VERIFIED CREDENTIALS
        </div>
        <h1 class="text-3xl md:text-4xl font-heading font-bold text-white mb-4">
            My Certificates
        </h1>
        <p class="text-white/80 max-w-2xl mx-auto">
            Your verified achievements and credentials. Share these with employers to showcase your skills.
        </p>
    </div>
</div>

<div class="bg-gray-50 min-h-screen py-12">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <?php if (empty($certificates)): ?>
            <!-- No Certificates -->
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-certificate text-gray-300 text-5xl"></i>
                </div>
                <h3 class="text-xl font-heading font-bold text-gray-900 mb-2">No certificates yet</h3>
                <p class="text-gray-500 mb-6 max-w-md mx-auto">
                    Certificates are being simplified while Jobmington focuses on jobs, applications, and hiring.
                </p>
                <a href="/jobmington/jobs/" class="inline-flex items-center bg-primary hover:bg-blue-800 text-white font-bold px-6 py-3 rounded-lg transition shadow-lg">
                    <i class="fas fa-briefcase mr-2"></i> Browse Jobs
                </a>
            </div>
        <?php else: ?>
            
            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-xl shadow-sm p-6 text-center">
                    <p class="text-3xl font-bold text-primary"><?= count($certificates) ?></p>
                    <p class="text-gray-500 text-sm">Certificates Earned</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 text-center">
                    <p class="text-3xl font-bold text-green-600"><?= count($certificates) ?></p>
                    <p class="text-gray-500 text-sm">Courses Completed</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 text-center">
                    <p class="text-3xl font-bold text-secondary">100%</p>
                    <p class="text-gray-500 text-sm">Verified</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 text-center">
                    <p class="text-3xl font-bold text-slate-600">∞</p>
                    <p class="text-gray-500 text-sm">Never Expires</p>
                </div>
            </div>
            
            <!-- Certificates Grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($certificates as $cert): ?>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden hover:shadow-xl transition group border border-gray-100">
                    <!-- Certificate Preview -->
                    <div class="aspect-[4/3] bg-gradient-to-br from-primary/10 via-white to-secondary/10 relative p-6 flex items-center justify-center border-b border-gray-100">
                        <!-- Certificate Design Preview -->
                        <div class="w-full max-w-xs bg-white rounded-lg shadow-lg p-4 border-2 border-primary/20 transform group-hover:scale-105 transition duration-300">
                            <div class="text-center">
                                <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-award text-primary text-xl"></i>
                                </div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Certificate of Completion</p>
                                <p class="font-bold text-gray-900 text-sm mt-1 line-clamp-1"><?= e($cert['course_title']) ?></p>
                                <div class="mt-2 pt-2 border-t border-gray-100">
                                    <p class="text-xs text-gray-500"><?= e(Session::get('full_name')) ?></p>
                                    <p class="text-xs text-gray-400 mt-1"><?= formatDate($cert['issued_at'], 'M d, Y') ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Verification Badge -->
                        <div class="absolute top-3 right-3 bg-green-500 text-white text-xs font-bold px-2 py-1 rounded-full flex items-center gap-1">
                            <i class="fas fa-check-circle"></i>
                            <span>Verified</span>
                        </div>
                    </div>
                    
                    <!-- Certificate Details -->
                    <div class="p-5">
                        <h3 class="font-heading font-bold text-gray-900 mb-1 line-clamp-2">
                            <?= e($cert['course_title']) ?>
                        </h3>
                        
                        <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
                            <i class="fas fa-calendar-check"></i>
                            <span>Issued <?= formatDate($cert['issued_at'], 'M d, Y') ?></span>
                        </div>
                        
                        <!-- Certificate Code -->
                        <div class="bg-gray-50 rounded-lg p-3 mb-4">
                            <p class="text-xs text-gray-500 mb-1">Certificate ID</p>
                            <p class="font-mono font-bold text-primary"><?= e($cert['cert_code']) ?></p>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex gap-2">
                            <a href="/jobmington/certificates/view.php?code=<?= e($cert['cert_code']) ?>" 
                               class="flex-1 bg-primary/10 hover:bg-primary text-primary hover:text-white text-center py-2 rounded-lg text-sm font-medium transition">
                                <i class="fas fa-eye mr-1"></i> View
                            </a>
                            <a href="/jobmington/certificates/download.php?code=<?= e($cert['cert_code']) ?>" 
                               class="flex-1 bg-secondary hover:bg-orange-600 text-white text-center py-2 rounded-lg text-sm font-medium transition">
                                <i class="fas fa-download mr-1"></i> Download
                            </a>
                            <button onclick="shareCertificate('<?= e($cert['cert_code']) ?>')" 
                                    class="px-3 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg transition" title="Share">
                                <i class="fas fa-share-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Public Verification Note -->
            <div class="mt-8 bg-gradient-to-r from-primary/5 to-secondary/5 rounded-xl p-6 flex flex-col md:flex-row items-center gap-6">
                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center shadow-md flex-shrink-0">
                    <i class="fas fa-shield-alt text-primary text-2xl"></i>
                </div>
                <div class="flex-1 text-center md:text-left">
                    <h4 class="font-bold text-gray-900 mb-1">Employer Verification</h4>
                    <p class="text-gray-600 text-sm">
                        Employers can verify your certificates at <strong class="text-primary"><?= SITE_URL ?>/verify</strong> 
                        using the Certificate ID above.
                    </p>
                </div>
                <a href="/jobmington/verify" target="_blank" class="bg-white hover:bg-gray-50 text-gray-700 font-medium px-4 py-2 rounded-lg border border-gray-200 transition whitespace-nowrap">
                    <i class="fas fa-external-link-alt mr-1"></i> View Verification Page
                </a>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
function shareCertificate(code) {
    const url = '<?= SITE_URL ?>/verify?code=' + code;
    
    if (navigator.share) {
        navigator.share({
            title: 'My Jobmington Certificate',
            text: 'Check out my verified certificate from Jobmington!',
            url: url
        });
    } else {
        navigator.clipboard.writeText(url).then(() => {
            JM.toast('Verification link copied!', 'success');
        });
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
