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
@import url('https://fonts.googleapis.com/css2?family=Alex+Brush&display=swap');
@font-face {
    font-family:"Futura Cyrillic Demi";
    src:local("Futura Cyrillic Demi"), local("FuturaCyrillicDemi"),
        url("/jobmington/assets/fonts/FuturaCyrillicDemi.ttf") format("truetype");
    font-display:swap;
}
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

.jm-cert-top { display:flex; align-items:center; justify-content:center; gap:1.3cqw; }
.jm-cert-top img { width:4.8cqw; height:4.8cqw; object-fit:contain; display:block; }
.jm-cert-brand { font-family:"Futura Cyrillic Demi", Arial, sans-serif; font-size:2.7cqw; font-weight:800; letter-spacing:.015em; color:var(--navy); line-height:1; }

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
.jm-cert-vright .sig { font-family:"Alex Brush","Segoe Script",cursive; font-size:4cqw; color:var(--navy); line-height:1; padding-bottom:.3cqw; }
.jm-cert-vright .ln { height:.1cqw; background:#9fb0c6; margin:.6cqw 14% .9cqw; }
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
            <div class="jm-cert-brand">Jobmington</div>
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
                    <?php
                    // sleek sunburst rosette (vector, brand navy)
                    $pts = []; $n = 28;
                    for ($i = 0; $i < $n * 2; $i++) {
                        $a = M_PI * $i / $n - M_PI / 2;
                        $r = ($i % 2 === 0) ? 49 : 44.5;
                        $pts[] = round(50 + cos($a) * $r, 2) . ',' . round(50 + sin($a) * $r, 2);
                    }
                    ?>
                    <polygon points="<?= implode(' ', $pts) ?>" fill="#061426"/>
                    <circle cx="50" cy="50" r="40.5" fill="#061426"/>
                    <circle cx="50" cy="50" r="36.5" fill="none" stroke="#0640a3" stroke-width="1.3"/>
                    <circle cx="50" cy="50" r="31.5" fill="none" stroke="#3f6fc0" stroke-width="0.7" stroke-dasharray="0.4 3"/>
                    <!-- clean center -->
                    <circle cx="50" cy="44" r="15.5" fill="#fff"/>
                    <path d="M43 44.4l4.6 4.6 9.6-10" stroke="#0640a3" stroke-width="3.1" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                    <text x="50" y="71.5" text-anchor="middle" fill="#fff" font-size="5.4" font-family="Arial" font-weight="bold" letter-spacing="2.4">VERIFIED</text>
                </svg>
            </div>

            <div class="jm-cert-vright">
                <div class="sig">Jobmington</div>
                <div class="ln"></div>
                <div class="lbl">Authorised Signature</div>
            </div>
        </div>
    </div>
</div>
</div>
