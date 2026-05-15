<?php
/**
 * JOBMINGTON - Certificate Verification
 * Public page for employers to verify certificates
 */

define('JOBMINGTON', true);
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

Session::start();

$pdo = db();
$certCode = Security::clean(get('code', ''));
$searchPerformed = !empty($certCode);
$certificate = null;
$isValid = false;

if ($searchPerformed) {
    // Look up certificate
    $stmt = $pdo->prepare("
        SELECT cert.*, c.title as course_title, c.description as course_description,
               u.full_name, u.headline, u.city, u.country_id,
               co.name as country_name
        FROM certificates cert
        JOIN courses c ON cert.course_id = c.course_id
        LEFT JOIN users u ON cert.user_id = u.user_id
        LEFT JOIN countries co ON u.country_id = co.country_id
        WHERE cert.cert_code = ?
    ");
    $stmt->execute([$certCode]);
    $certificate = $stmt->fetch();
    $isValid = (bool) $certificate;
    
    // Log verification attempt
    Security::logActivity(Session::userId(), 'certificate_verification', 
        'Verified: ' . $certCode . ' - ' . ($isValid ? 'Valid' : 'Invalid'));
}

$pageTitle = 'Verify Certificate - ' . SITE_NAME;
$pageDescription = 'Verify the authenticity of Jobmington certificates. Enter the certificate ID to check if it\'s valid.';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Hero Section -->
<div class="min-h-screen bg-slate-900">
    <div class="absolute inset-0 opacity-5">
        <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.4\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
    </div>
    
    <div class="max-w-3xl mx-auto px-4 py-16 relative">
        <div class="text-center mb-12">
            <div class="w-20 h-20 bg-white/10 backdrop-blur-sm rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-shield-alt text-white text-4xl"></i>
            </div>
            <h1 class="text-5xl md:text-6xl font-heading font-black text-white mb-4">
                Verify Certificate<span class="text-slate-500">.</span>
            </h1>
            <p class="text-xl text-slate-300 mb-8">
                Check the authenticity of a Jobmington certificate
            </p>
            
            <!-- Search Form -->
            <form action="" method="GET" class="max-w-xl mx-auto">
                <div class="flex flex-col sm:flex-row gap-3 bg-white/5 border border-white/10 rounded-xl shadow-2xl overflow-hidden">
                    <div class="flex-1 relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-500">
                            <i class="fas fa-certificate text-xl"></i>
                        </span>
                        <input type="text" name="code" value="<?= e($certCode) ?>" 
                               placeholder="Enter Certificate ID (e.g., JMT-2025-A1B2C3D4)"
                               class="w-full pl-12 pr-4 py-4 bg-transparent text-white placeholder-slate-500 focus:outline-none text-lg"
                               required>
                    </div>
                    <button type="submit" class="bg-white hover:bg-slate-100 text-black font-bold px-8 py-4 transition">
                        <i class="fas fa-search mr-2"></i> Verify
                    </button>
                </div>
            </form>
        </div>

        <div class="space-y-6">
            <?php if ($searchPerformed): ?>
                <?php if ($isValid): ?>
                <!-- Valid Certificate -->
                <div class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden backdrop-blur-xl">
                    
                    <!-- Success Header -->
                    <div class="bg-gradient-to-r from-emerald-500/20 to-emerald-600/20 border-b border-emerald-500/30 p-8 text-center">
                        <div class="w-16 h-16 bg-emerald-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-check text-emerald-400 text-3xl"></i>
                        </div>
                        <h2 class="text-3xl font-heading font-bold text-white">Valid Certificate</h2>
                        <p class="text-emerald-200 mt-2">This certificate is authentic and verified.</p>
                    </div>
                    
                    <!-- Certificate Details -->
                    <div class="p-8">
                        <div class="grid md:grid-cols-2 gap-6">
                            
                            <!-- Recipient Info -->
                            <div class="space-y-4">
                                <h3 class="font-bold text-slate-300 text-sm uppercase tracking-wider">Certificate Holder</h3>
                                
                                <div class="flex items-center gap-4">
                                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center text-white font-bold text-xl">
                                        <?= e(strtoupper(substr($certificate['full_name'] ?: 'A', 0, 1))) ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-white text-lg"><?= e($certificate['full_name'] ?: 'Name Hidden') ?></p>
                                        <?php if ($certificate['headline']): ?>
                                            <p class="text-slate-400 text-sm"><?= e($certificate['headline']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($certificate['city'] || $certificate['country_name']): ?>
                                            <p class="text-slate-500 text-sm">
                                                <i class="fas fa-map-marker-alt mr-1"></i>
                                                <?= e(implode(', ', array_filter([$certificate['city'], $certificate['country_name']]))) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Certificate Info -->
                            <div class="space-y-4">
                                <h3 class="font-bold text-slate-300 text-sm uppercase tracking-wider">Certificate Details</h3>
                                
                                <div class="space-y-3">
                                    <div>
                                        <p class="text-xs text-slate-400">Certificate ID</p>
                                        <p class="font-mono font-bold text-white"><?= e($certificate['cert_code']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400">Issue Date</p>
                                        <p class="font-medium text-white"><?= formatDate($certificate['issued_at'], 'F d, Y') ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400">Status</p>
                                        <span class="inline-flex items-center bg-emerald-500/20 text-emerald-300 text-xs font-bold px-2 py-1 rounded-full">
                                            <i class="fas fa-check-circle mr-1"></i> Active
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Course Info -->
                        <div class="mt-8 pt-6 border-t border-white/10">
                            <h3 class="font-bold text-slate-300 text-sm uppercase tracking-wider mb-4">Course Completed</h3>
                            
                            <div class="bg-white/[0.02] rounded-xl p-5 border border-white/10">
                                <div class="flex items-start gap-4">
                                    <div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-graduation-cap text-blue-400 text-xl"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-white"><?= e($certificate['course_title']) ?></h4>
                                        <?php if ($certificate['course_description']): ?>
                                            <p class="text-slate-400 text-sm mt-1"><?= excerpt($certificate['course_description'], 150) ?></p>
                                        <?php endif; ?>
                                        <a href="/jobmington/jobs/" 
                                           class="text-blue-400 hover:text-blue-300 text-sm mt-2 inline-block transition">
                                            Browse Jobs <i class="fas fa-arrow-right ml-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- View Full Certificate -->
                        <div class="mt-6 text-center">
                            <a href="/jobmington/certificates/view.php?code=<?= e($certificate['cert_code']) ?>" 
                               class="inline-flex items-center bg-white hover:bg-slate-100 text-black font-bold px-6 py-3 rounded-xl transition">
                                <i class="fas fa-external-link-alt mr-2"></i> View Full Certificate
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php else: ?>
                <!-- Invalid Certificate -->
                <div class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden backdrop-blur-xl">
                    
                    <!-- Error Header -->
                    <div class="bg-gradient-to-r from-red-500/20 to-red-600/20 border-b border-red-500/30 p-8 text-center">
                        <div class="w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-times text-red-400 text-3xl"></i>
                        </div>
                        <h2 class="text-3xl font-heading font-bold text-white">Certificate Not Found</h2>
                        <p class="text-red-200 mt-2">We could not verify this certificate.</p>
                    </div>
                    
                    <!-- Details -->
                    <div class="p-8 text-center">
                        <p class="text-slate-300 mb-6">
                            The certificate ID <strong class="font-mono text-slate-100 text-lg"><?= e($certCode) ?></strong> 
                            was not found in our system.
                        </p>
                        
                        <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-6 text-left mb-8">
                            <h4 class="font-bold text-yellow-200 mb-3">Possible Reasons:</h4>
                            <ul class="text-sm text-yellow-100 space-y-2">
                                <li><i class="fas fa-check mr-2"></i> The certificate ID may be mistyped</li>
                                <li><i class="fas fa-check mr-2"></i> The certificate may have been revoked</li>
                                <li><i class="fas fa-check mr-2"></i> The certificate may not exist</li>
                            </ul>
                        </div>
                        
                        <a href="/jobmington/verify" class="inline-flex items-center text-slate-300 hover:text-white font-medium transition">
                            <i class="fas fa-redo mr-2"></i> Try Again
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
            <!-- Default State: How it works -->
            <div class="bg-white/5 border border-white/10 rounded-xl backdrop-blur-xl p-12">
                <h2 class="text-3xl font-heading font-bold text-white mb-12 text-center">How Verification Works</h2>
                
                <div class="grid md:grid-cols-3 gap-8">
                    <div class="text-center">
                        <div class="w-14 h-14 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="text-white font-bold text-xl">1</span>
                        </div>
                        <h3 class="font-bold text-white mb-2">Get Certificate ID</h3>
                        <p class="text-slate-400 text-sm">Ask the candidate for their Jobmington certificate ID (format: JMT-XXXX-XXXXXXXX)</p>
                    </div>
                    
                    <div class="text-center">
                        <div class="w-14 h-14 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="text-white font-bold text-xl">2</span>
                        </div>
                        <h3 class="font-bold text-white mb-2">Enter & Search</h3>
                        <p class="text-slate-400 text-sm">Enter the certificate ID in the search box above and click Verify</p>
                    </div>
                    
                    <div class="text-center">
                        <div class="w-14 h-14 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="text-white font-bold text-xl">3</span>
                        </div>
                        <h3 class="font-bold text-white mb-2">View Results</h3>
                        <p class="text-slate-400 text-sm">See the certificate holder's name, course completed, and issue date</p>
                    </div>
                </div>
            </div>
            
            <!-- Trust Indicators -->
            <div class="mt-8 grid md:grid-cols-3 gap-4">
                <div class="bg-white/5 border border-white/10 rounded-lg p-6 text-center backdrop-blur-xl">
                    <i class="fas fa-lock text-slate-300 text-3xl mb-3"></i>
                    <h4 class="font-bold text-white text-sm">Tamper-Proof</h4>
                    <p class="text-xs text-slate-400 mt-2">Each certificate has a unique, unguessable ID</p>
                </div>
                <div class="bg-white/5 border border-white/10 rounded-lg p-6 text-center backdrop-blur-xl">
                    <i class="fas fa-clock text-slate-300 text-3xl mb-3"></i>
                    <h4 class="font-bold text-white text-sm">Instant Verification</h4>
                    <p class="text-xs text-slate-400 mt-2">Verify any certificate in seconds</p>
                </div>
                <div class="bg-white/5 border border-white/10 rounded-lg p-6 text-center backdrop-blur-xl">
                    <i class="fas fa-globe text-slate-300 text-3xl mb-3"></i>
                    <h4 class="font-bold text-white text-sm">Globally Accessible</h4>
                    <p class="text-xs text-slate-400 mt-2">Verify from anywhere in the world</p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- For Employers -->
            <div class="mt-12 text-center bg-white/5 border border-white/10 rounded-xl p-8 backdrop-blur-xl">
                <h3 class="text-2xl font-bold text-white mb-2">Are you an employer?</h3>
                <p class="text-slate-300 mb-6">Find pre-verified candidates with certified skills.</p>
                <a href="/jobmington/auth/register.php?type=employer" class="inline-flex items-center bg-white hover:bg-slate-100 text-black font-bold px-6 py-3 rounded-xl transition">
                    <i class="fas fa-building mr-2"></i> Create Employer Account
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
