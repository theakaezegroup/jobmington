<?php
/**
 * Shared certificate render — flat, sharp, brand-only, fully responsive.
 * Sizing uses container-query units (cqw) so the certificate scales
 * pixel-perfectly at any width (desktop, tablet, phone). A4 landscape.
 * Expects $cert with: full_name, course_title, cert_code, issued_at
 */
if (!defined('JOBMINGTON')) { exit; }

$certName   = trim((string) ($cert['full_name'] ?? '')) ?: 'Jobmington Learner';
$certCourse = (string) ($cert['course_title'] ?? 'Course');
$certCode   = (string) ($cert['cert_code'] ?? '');
$certDate   = formatDate($cert['issued_at'] ?? date('Y-m-d'), 'F j, Y');
$siteUrl    = defined('SITE_URL') ? SITE_URL : 'https://jobmington.com';
$verifyUrl  = $siteUrl . '/verify?code=' . urlencode($certCode);
$qrUrl      = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=0&qzone=1&color=061426&data=' . urlencode($verifyUrl);
?>
<style>
.jm-cert-stage { container-type:inline-size; width:100%; max-width:1000px; margin:0 auto; }
.jm-cert {
    --navy:#061426; --blue:#0640a3; --line:#d8e4f4; --muted:#5b6b82;
    position:relative; width:100%; aspect-ratio:1.414 / 1; background:#fbfdff;
    overflow:hidden; color:var(--navy);
    font-family:"Futura Cyrillic Demi", Arial, sans-serif;
    border:1px solid var(--line);
}
/* flat brand corner motif (vector, low opacity) */
.jm-cert-deco { position:absolute; inset:0; pointer-events:none; }
.jm-cert-deco svg { position:absolute; }
.jm-cert-deco .tl { top:0; left:0; width:34cqw; height:34cqw; }
.jm-cert-deco .br { bottom:0; right:0; width:34cqw; height:34cqw; transform:rotate(180deg); }
/* sharp double frame, brand only */
.jm-cert-frame { position:absolute; inset:2.4cqw; border:0.3cqw solid var(--navy); }
.jm-cert-frame::after { content:''; position:absolute; inset:0.9cqw; border:0.1cqw solid var(--blue); }

.jm-cert-inner {
    position:absolute; inset:2.4cqw; padding:4.6cqw 7cqw 3.4cqw;
    display:flex; flex-direction:column; align-items:center; text-align:center;
}

.jm-cert-top { display:flex; align-items:center; gap:1.1cqw; }
.jm-cert-top img { width:4cqw; height:4cqw; object-fit:contain; }
.jm-cert-brand { text-align:left; line-height:1; }
.jm-cert-brand b { display:block; font-size:2.1cqw; font-weight:800; letter-spacing:.02em; color:var(--navy); }
.jm-cert-brand span { display:block; font-size:.86cqw; font-weight:800; letter-spacing:.46em; color:var(--blue); margin-top:.5cqw; }

.jm-cert-kicker { margin-top:4.4cqw; font-size:1.5cqw; font-weight:800; letter-spacing:.5em; color:var(--blue); text-transform:uppercase; }
.jm-cert-rule { width:7cqw; height:.32cqw; background:var(--blue); margin:1.4cqw auto 0; }

.jm-cert-present { margin-top:3.6cqw; font-size:1.34cqw; color:var(--muted); }
.jm-cert-name {
    font-family:Georgia,"Times New Roman",serif; font-size:6.2cqw; font-weight:700;
    color:var(--navy); line-height:1.05; margin:1.6cqw 0 0; letter-spacing:.005em;
}
.jm-cert-namebar { width:46cqw; max-width:80%; height:.12cqw; background:var(--line); margin:2cqw auto 0; }

.jm-cert-for { margin-top:2.8cqw; font-size:1.3cqw; color:var(--muted); }
.jm-cert-course {
    font-family:Georgia,serif; font-size:3cqw; font-weight:700; color:var(--blue);
    margin-top:1cqw; max-width:78%; line-height:1.22;
}

.jm-cert-foot { margin-top:auto; width:100%; display:flex; align-items:flex-end; justify-content:space-between; gap:3cqw; }
/* left: QR + id + issue date (aligned together, no overlap) */
.jm-cert-vleft { flex:1 1 0; min-width:0; display:flex; align-items:flex-end; gap:1.3cqw; text-align:left; }
.jm-cert-vleft img { width:8.4cqw; height:8.4cqw; background:#fff; display:block; flex:0 0 auto; }
.jm-cert-vleft .vid { font-size:1.04cqw; font-weight:800; letter-spacing:.03em; color:var(--navy); font-family:"Courier New",monospace; }
.jm-cert-vleft .vmeta { font-size:1.02cqw; color:var(--navy); font-weight:700; margin-top:.55cqw; }
.jm-cert-vleft .vsub { font-size:.84cqw; color:#7c8aa0; margin-top:.45cqw; max-width:18cqw; line-height:1.4; }
/* center: vector seal */
.jm-cert-seal { flex:0 0 auto; }
.jm-cert-seal svg { width:13cqw; height:13cqw; display:block; }
/* right: signature */
.jm-cert-vright { flex:1 1 0; min-width:0; text-align:center; }
.jm-cert-vright .sig { font-family:"Segoe Script","Brush Script MT",cursive; font-size:2.6cqw; color:var(--navy); }
.jm-cert-vright .ln { height:.1cqw; background:#9fb0c6; margin:.8cqw 16% .9cqw; }
.jm-cert-vright .lbl { font-size:.92cqw; font-weight:800; letter-spacing:.18em; text-transform:uppercase; color:#7c8aa0; }
</style>

<div class="jm-cert-stage">
<div class="jm-cert">
    <div class="jm-cert-deco">
        <svg class="tl" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M0 0H100C44.77 0 0 44.77 0 100Z" fill="#0640a3" fill-opacity="0.05"/>
            <path d="M0 0H62C27.7 0 0 27.7 0 62Z" fill="#061426" fill-opacity="0.05"/>
        </svg>
        <svg class="br" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M0 0H100C44.77 0 0 44.77 0 100Z" fill="#0640a3" fill-opacity="0.05"/>
            <path d="M0 0H62C27.7 0 0 27.7 0 62Z" fill="#061426" fill-opacity="0.05"/>
        </svg>
    </div>
    <div class="jm-cert-frame"></div>

    <div class="jm-cert-inner">
        <div class="jm-cert-top">
            <img src="/jobmington/assets/images/badge.png?v=logo-7" alt="">
            <div class="jm-cert-brand"><b>Jobmington</b><span>ACADEMY</span></div>
        </div>

        <div class="jm-cert-kicker">Certificate of Completion</div>
        <div class="jm-cert-rule"></div>

        <div class="jm-cert-present">This certificate is proudly presented to</div>
        <div class="jm-cert-name"><?= e($certName) ?></div>
        <div class="jm-cert-namebar"></div>

        <div class="jm-cert-for">for successfully completing the course</div>
        <div class="jm-cert-course"><?= e($certCourse) ?></div>

        <div class="jm-cert-foot">
            <div class="jm-cert-vleft">
                <img src="<?= e($qrUrl) ?>" alt="Scan to verify" loading="lazy">
                <div>
                    <div class="vid"><?= e($certCode) ?></div>
                    <div class="vmeta">Issued <?= e($certDate) ?></div>
                    <div class="vsub">Scan to verify &middot; jobmington.com/verify</div>
                </div>
            </div>

            <div class="jm-cert-seal" aria-label="Verified seal">
                <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- scalloped medallion edge -->
                    <g fill="#061426">
                        <?php for ($i = 0; $i < 24; $i++) {
                            $a = deg2rad($i * 15);
                            $cx = 50 + cos($a) * 44; $cy = 50 + sin($a) * 44;
                            echo '<circle cx="' . round($cx, 2) . '" cy="' . round($cy, 2) . '" r="4.6"/>';
                        } ?>
                    </g>
                    <circle cx="50" cy="50" r="42" fill="#061426"/>
                    <circle cx="50" cy="50" r="38" fill="none" stroke="#0640a3" stroke-width="1.4"/>
                    <circle cx="50" cy="50" r="32" fill="none" stroke="#3f6fc0" stroke-width="0.8" stroke-dasharray="1.5 2.6"/>
                    <!-- center check -->
                    <circle cx="50" cy="47" r="18" fill="#0640a3"/>
                    <path d="M42 47.5l5 5 11-11.5" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <!-- VERIFIED label -->
                    <text x="50" y="74" text-anchor="middle" fill="#fff" font-size="6.4" font-family="Arial" font-weight="bold" letter-spacing="1.4">VERIFIED</text>
                    <!-- top star -->
                    <path d="M50 19l1.6 3.4 3.7.5-2.7 2.6.7 3.7L50 30.9l-3 1.8.7-3.7-2.7-2.6 3.7-.5z" fill="#0640a3"/>
                </svg>
            </div>

            <div class="jm-cert-vright">
                <div class="sig">Jobmington</div>
                <div class="ln"></div>
                <div class="lbl">Jobmington Academy</div>
            </div>
        </div>
    </div>
</div>
</div>
