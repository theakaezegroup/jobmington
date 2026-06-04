<?php
/**
 * Shared premium certificate render. Expects $cert with:
 *   full_name, course_title, cert_code, issued_at
 * Outputs the certificate markup + scoped styles. A4 landscape, print-ready.
 */
if (!defined('JOBMINGTON')) { exit; }

$certName   = trim((string) ($cert['full_name'] ?? '')) ?: 'Jobmington Learner';
$certCourse = (string) ($cert['course_title'] ?? 'Course');
$certCode   = (string) ($cert['cert_code'] ?? '');
$certDate   = formatDate($cert['issued_at'] ?? date('Y-m-d'), 'F j, Y');
$siteUrl    = defined('SITE_URL') ? SITE_URL : 'https://jobmington.com';
$verifyUrl  = $siteUrl . '/verify?code=' . urlencode($certCode);
$qrUrl      = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=0&qzone=1&color=061426&data=' . urlencode($verifyUrl);
?>
<style>
.jm-cert-wrap { --navy:#061426; --blue:#0640a3; --gold:#c79a3a; --ink:#1f2d3d; }
.jm-cert {
    position:relative; width:100%; max-width:1000px; margin:0 auto; aspect-ratio:1.414/1;
    background:
        radial-gradient(120% 140% at 0% 0%, #fbfcff 0%, #f4f7fd 60%, #eef3fb 100%);
    border-radius:6px; overflow:hidden;
    box-shadow:0 24px 60px -24px rgba(6,20,38,.45);
    color:var(--ink); font-family:"Futura Cyrillic Demi", Arial, sans-serif;
}
.jm-cert::before { /* outer navy frame */
    content:''; position:absolute; inset:18px; border:2px solid var(--navy); border-radius:3px; pointer-events:none;
}
.jm-cert::after { /* inner gold hairline */
    content:''; position:absolute; inset:25px; border:1px solid var(--gold); border-radius:2px; pointer-events:none;
}
.jm-cert-bg { position:absolute; inset:0; pointer-events:none; opacity:.06; }
.jm-cert-bg .ring { position:absolute; border-radius:50%; border:40px solid var(--blue); }
.jm-cert-bg .r1 { width:520px; height:520px; right:-220px; top:-220px; }
.jm-cert-bg .r2 { width:360px; height:360px; left:-200px; bottom:-160px; border-color:var(--gold); }
.jm-cert-inner { position:absolute; inset:25px; padding:4.2% 7% 3.5%; display:flex; flex-direction:column; align-items:center; text-align:center; }

.jm-cert-top { display:flex; align-items:center; gap:12px; margin-bottom:2.4%; }
.jm-cert-top img { width:46px; height:46px; object-fit:contain; }
.jm-cert-brand { text-align:left; line-height:1; }
.jm-cert-brand b { display:block; font-size:1.45rem; font-weight:800; letter-spacing:.02em; color:var(--navy); }
.jm-cert-brand span { display:block; font-size:.62rem; font-weight:800; letter-spacing:.42em; color:var(--blue); margin-top:4px; }

.jm-cert-kicker { font-size:.92rem; font-weight:800; letter-spacing:.5em; color:var(--blue); text-transform:uppercase; }
.jm-cert-kicker-line { width:64px; height:3px; background:var(--gold); margin:12px auto 0; border-radius:2px; }

.jm-cert-present { margin-top:3.4%; font-size:.95rem; color:#5b6b82; font-style:italic; }
.jm-cert-name {
    font-family:Georgia, "Times New Roman", serif; font-size:clamp(2rem,5.4vw,3.4rem); font-weight:700;
    color:var(--navy); line-height:1.1; margin:1.6% 0 0; letter-spacing:.01em;
}
.jm-cert-flourish { display:flex; align-items:center; gap:10px; margin:1.4% auto 0; }
.jm-cert-flourish .ln { width:120px; height:1px; background:linear-gradient(90deg,transparent,var(--gold)); }
.jm-cert-flourish .ln.r { background:linear-gradient(90deg,var(--gold),transparent); }
.jm-cert-flourish .dot { width:7px; height:7px; border:1.5px solid var(--gold); transform:rotate(45deg); }

.jm-cert-for { margin-top:3%; font-size:.92rem; color:#5b6b82; }
.jm-cert-course {
    font-family:Georgia, serif; font-size:clamp(1.1rem,2.6vw,1.7rem); font-weight:700; color:var(--blue);
    margin-top:.8%; max-width:80%; line-height:1.25;
}

.jm-cert-foot { margin-top:auto; width:100%; display:flex; align-items:flex-end; justify-content:space-between; gap:20px; }
.jm-cert-foot-col { flex:1; text-align:center; min-width:0; }
.jm-cert-sig-val { font-family:Georgia, serif; font-size:1.05rem; color:var(--navy); font-weight:700; }
.jm-cert-sig-script { font-family:"Brush Script MT","Segoe Script",cursive; font-size:1.5rem; color:var(--navy); }
.jm-cert-sig-line { height:1px; background:#9fb0c6; margin:6px 18% 7px; }
.jm-cert-sig-label { font-size:.62rem; font-weight:800; letter-spacing:.16em; text-transform:uppercase; color:#7c8aa0; }

.jm-cert-seal { flex:0 0 auto; }
.jm-cert-seal svg { width:88px; height:88px; }

.jm-cert-verify {
    position:absolute; left:48px; bottom:42px; display:flex; align-items:center; gap:11px; text-align:left;
}
.jm-cert-verify img { width:66px; height:66px; border:3px solid #fff; box-shadow:0 2px 8px rgba(6,20,38,.18); border-radius:4px; background:#fff; }
.jm-cert-verify .v-id { font-size:.6rem; font-weight:800; letter-spacing:.04em; color:var(--navy); font-family:Arial, monospace; }
.jm-cert-verify .v-sub { font-size:.56rem; color:#7c8aa0; margin-top:3px; max-width:130px; line-height:1.4; }

@media (max-width:680px){
    .jm-cert-inner { padding:5% 6%; }
    .jm-cert-verify { left:34px; bottom:32px; }
    .jm-cert-verify img { width:52px; height:52px; }
    .jm-cert-foot { flex-direction:row; }
}
</style>

<div class="jm-cert-wrap">
<div class="jm-cert">
    <div class="jm-cert-bg"><span class="ring r1"></span><span class="ring r2"></span></div>
    <div class="jm-cert-inner">
        <div class="jm-cert-top">
            <img src="/jobmington/assets/images/badge.png?v=logo-7" alt="">
            <div class="jm-cert-brand"><b>Jobmington</b><span>Academy</span></div>
        </div>

        <div class="jm-cert-kicker">Certificate of Completion</div>
        <div class="jm-cert-kicker-line"></div>

        <div class="jm-cert-present">This certificate is proudly presented to</div>
        <div class="jm-cert-name"><?= e($certName) ?></div>
        <div class="jm-cert-flourish"><span class="ln"></span><span class="dot"></span><span class="ln r"></span></div>

        <div class="jm-cert-for">for successfully completing the course</div>
        <div class="jm-cert-course"><?= e($certCourse) ?></div>

        <div class="jm-cert-foot">
            <div class="jm-cert-foot-col">
                <div class="jm-cert-sig-val"><?= e($certDate) ?></div>
                <div class="jm-cert-sig-line"></div>
                <div class="jm-cert-sig-label">Date Issued</div>
            </div>
            <div class="jm-cert-seal">
                <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="50" cy="50" r="46" fill="#061426"/>
                    <circle cx="50" cy="50" r="46" fill="none" stroke="#c79a3a" stroke-width="2"/>
                    <circle cx="50" cy="50" r="36" fill="none" stroke="#c79a3a" stroke-width="1" stroke-dasharray="2 3"/>
                    <path d="M50 30l4.6 9.3 10.3 1.5-7.4 7.2 1.7 10.2L50 63.6 40.8 67.4l1.7-10.2-7.4-7.2 10.3-1.5z" fill="#c79a3a"/>
                    <text x="50" y="80" text-anchor="middle" fill="#c79a3a" font-size="7" font-family="Arial" font-weight="bold" letter-spacing="1">VERIFIED</text>
                </svg>
            </div>
            <div class="jm-cert-foot-col">
                <div class="jm-cert-sig-script">Jobmington</div>
                <div class="jm-cert-sig-line"></div>
                <div class="jm-cert-sig-label">Jobmington Academy</div>
            </div>
        </div>

        <div class="jm-cert-verify">
            <img src="<?= e($qrUrl) ?>" alt="Verify QR" loading="lazy">
            <div>
                <div class="v-id"><?= e($certCode) ?></div>
                <div class="v-sub">Scan to verify at jobmington.com/verify</div>
            </div>
        </div>
    </div>
</div>
</div>
