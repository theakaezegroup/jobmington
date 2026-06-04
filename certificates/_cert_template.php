<?php
/**
 * Shared certificate render — light, flat, minimal with a vector guilloché
 * security border (spirograph corners + interwoven lace), brand blue only.
 * Sizing uses container-query units (cqw) so it scales pixel-perfectly at any
 * width. A4 landscape (viewBox 1000 x 707). Print-ready.
 * Expects $cert with: full_name, course_title, cert_code, issued_at
 */
if (!defined('JOBMINGTON')) { exit; }

$certName   = trim((string) ($cert['full_name'] ?? '')) ?: 'Jobmington Learner';
$certCourse = (string) ($cert['course_title'] ?? 'Course');
$certCode   = (string) ($cert['cert_code'] ?? '');
$certDate   = formatDate($cert['issued_at'] ?? date('Y-m-d'), 'F j, Y');
$siteUrl    = defined('SITE_URL') ? SITE_URL : 'https://jobmington.com';
$verifyUrl  = $siteUrl . '/verify?code=' . urlencode($certCode);
$qrUrl      = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=0&qzone=1&color=0640a3&data=' . urlencode($verifyUrl);

/* ---------- vector guilloché border generator ---------- */
$GW = 1000; $GH = 707; $inset = 11; $band = 54;

$spiro = function ($cx, $cy, $R, $r, $d, $turns) {
    $steps = max(280, $turns * 150); $p = [];
    for ($i = 0; $i <= $steps; $i++) {
        $t = 2 * M_PI * $turns * $i / $steps;
        $x = ($R - $r) * cos($t) + $d * cos((($R - $r) / $r) * $t);
        $y = ($R - $r) * sin($t) - $d * sin((($R - $r) / $r) * $t);
        $p[] = round($cx + $x, 2) . ',' . round($cy + $y, 2);
    }
    return 'M' . implode(' L', $p);
};
// guilloché strip: $phases interwoven sine lines along an axis
$strip = function ($a0, $a1, $c, $amp, $lambda, $phases, $horizontal) {
    $out = ''; $len = $a1 - $a0; $steps = max(80, (int) ($len / 2.4));
    for ($k = 0; $k < $phases; $k++) {
        $ph = 2 * M_PI * $k / $phases; $p = [];
        for ($i = 0; $i <= $steps; $i++) {
            $a = $a0 + $len * $i / $steps;
            $o = $c + $amp * sin(2 * M_PI * ($a - $a0) / $lambda + $ph);
            $p[] = $horizontal ? round($a, 2) . ',' . round($o, 2) : round($o, 2) . ',' . round($a, 2);
        }
        $out .= '<polyline points="' . implode(' ', $p) . '"/>';
    }
    return $out;
};
$cTop = $inset + $band / 2; $cBot = $GH - $inset - $band / 2;
$cLft = $inset + $band / 2; $cRgt = $GW - $inset - $band / 2;
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Sacramento&display=swap');
@font-face {
    font-family:"Futura Cyrillic Demi";
    src:local("Futura Cyrillic Demi"), local("FuturaCyrillicDemi"),
        url("/jobmington/assets/fonts/FuturaCyrillicDemi.ttf") format("truetype");
    font-display:swap;
}
@font-face {
    font-family:"Futura Cyrillic Book";
    src:local("Futura Cyrillic Book"), local("FuturaCyrillicBook"),
        url("/jobmington/assets/fonts/FuturaCyrillicBook.ttf") format("truetype");
    font-display:swap;
}
.jm-cert-stage { container-type:inline-size; width:100%; max-width:1000px; margin:0 auto; }
.jm-cert {
    --navy:#0a1b3a; --blue:#0640a3; --muted:#6b7a92;
    position:relative; width:100%; aspect-ratio:1000 / 707; background:#ffffff;
    overflow:hidden; color:var(--navy);
    font-family:"Futura Cyrillic Book","Futura Cyrillic Demi",Arial,sans-serif;
}
.jm-cert-bg { position:absolute; inset:0; width:100%; height:100%; display:block; }
.jm-cert-inner {
    position:absolute; inset:0; padding:7.2cqw 11cqw 5.6cqw;
    display:flex; flex-direction:column; align-items:center; text-align:center;
}

.jm-cert-logo { width:5cqw; height:5cqw; object-fit:contain; display:block; }

.jm-cert-title {
    font-family:"Cormorant Garamond",Georgia,serif; font-weight:600;
    font-size:7.6cqw; line-height:.96; letter-spacing:.14em; color:var(--navy);
    margin:1.8cqw 0 0; text-transform:uppercase; text-indent:.14em;
}
.jm-cert-sub { font-size:1.5cqw; font-weight:700; letter-spacing:.52em; color:var(--muted); text-transform:uppercase; margin-top:1cqw; text-indent:.52em; }

.jm-cert-present { margin-top:3.4cqw; font-size:1.34cqw; color:var(--muted); }
.jm-cert-namerow { display:flex; align-items:center; justify-content:center; gap:2.2cqw; width:78%; margin-top:1.5cqw; }
.jm-cert-namerow .ln { flex:1; height:.08cqw; background:linear-gradient(90deg,transparent,#b9c7dd); }
.jm-cert-namerow .ln.r { background:linear-gradient(90deg,#b9c7dd,transparent); }
.jm-cert-name { font-family:"Futura Cyrillic Demi",Arial,sans-serif; font-size:2.7cqw; font-weight:800; letter-spacing:.13em; color:var(--blue); text-transform:uppercase; white-space:nowrap; }

.jm-cert-for { margin-top:2.4cqw; font-size:1.3cqw; color:var(--muted); }
.jm-cert-course { font-family:"Cormorant Garamond",Georgia,serif; font-size:3.2cqw; font-weight:600; color:var(--navy); margin-top:.6cqw; max-width:78%; line-height:1.16; }
.jm-cert-when { margin-top:1cqw; font-size:1.16cqw; color:var(--muted); }

/* signatures flanking the seal */
.jm-cert-foot { margin-top:auto; width:100%; display:flex; align-items:flex-end; justify-content:space-between; gap:3cqw; }
.jm-cert-sigcol { flex:1 1 0; min-width:0; text-align:center; }
.jm-cert-sigcol .sig { font-family:"Sacramento",cursive; font-weight:400; font-size:3.2cqw; letter-spacing:.01em; color:#1c3a63; line-height:.9; }
.jm-cert-sigcol .ln { height:.08cqw; background:#b9c7dd; margin:.4cqw 10% .8cqw; }
.jm-cert-sigcol .lbl { font-size:.9cqw; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:var(--muted); }
.jm-cert-seal { flex:0 0 auto; }
.jm-cert-seal svg { width:12.5cqw; height:12.5cqw; display:block; }
/* centered verification footer */
.jm-cert-footline { display:flex; align-items:center; justify-content:center; gap:1.1cqw; margin-top:2.2cqw; }
.jm-cert-footline img { width:5.4cqw; height:5.4cqw; background:#fff; display:block; }
.jm-cert-footline span { font-size:1cqw; color:var(--muted); letter-spacing:.02em; }
.jm-cert-footline b { color:var(--navy); font-family:"Courier New",monospace; font-weight:800; letter-spacing:.03em; }
</style>

<div class="jm-cert-stage">
<div class="jm-cert">
    <svg class="jm-cert-bg" viewBox="0 0 <?= $GW ?> <?= $GH ?>" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <!-- frames -->
        <rect x="<?= $inset ?>" y="<?= $inset ?>" width="<?= $GW - 2*$inset ?>" height="<?= $GH - 2*$inset ?>" fill="none" stroke="#0640a3" stroke-width="2.2"/>
        <rect x="<?= $inset + $band ?>" y="<?= $inset + $band ?>" width="<?= $GW - 2*($inset+$band) ?>" height="<?= $GH - 2*($inset+$band) ?>" fill="none" stroke="#0640a3" stroke-width="1"/>
        <rect x="<?= $inset + $band + 5 ?>" y="<?= $inset + $band + 5 ?>" width="<?= $GW - 2*($inset+$band+5) ?>" height="<?= $GH - 2*($inset+$band+5) ?>" fill="none" stroke="#9db4d8" stroke-width="0.5"/>

        <!-- guilloché lace bands (light, interwoven) -->
        <g stroke="#0640a3" stroke-width="0.5" fill="none" opacity="0.22">
            <?= $strip($inset + $band, $GW - $inset - $band, $cTop, 13, 27, 16, true) ?>
            <?= $strip($inset + $band, $GW - $inset - $band, $cBot, 13, 27, 16, true) ?>
            <?= $strip($inset + $band, $GH - $inset - $band, $cLft, 13, 27, 16, false) ?>
            <?= $strip($inset + $band, $GH - $inset - $band, $cRgt, 13, 27, 16, false) ?>
        </g>
        <!-- guilloché chain (antiphase eyes) -->
        <g stroke="#0640a3" stroke-width="0.8" fill="none" opacity="0.5">
            <?= $strip($inset + $band, $GW - $inset - $band, $cTop, 17, 54, 2, true) ?>
            <?= $strip($inset + $band, $GW - $inset - $band, $cBot, 17, 54, 2, true) ?>
            <?= $strip($inset + $band, $GH - $inset - $band, $cLft, 17, 54, 2, false) ?>
            <?= $strip($inset + $band, $GH - $inset - $band, $cRgt, 17, 54, 2, false) ?>
        </g>

        <!-- corner rosettes (spirograph) -->
        <?php
        $corners = [[$cLft, $cTop], [$cRgt, $cTop], [$cLft, $cBot], [$cRgt, $cBot]];
        foreach ($corners as $c):
            [$cx, $cy] = $c; ?>
            <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="30" fill="#ffffff"/>
            <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="30" fill="none" stroke="#0640a3" stroke-width="0.8" opacity="0.55"/>
            <path d="<?= $spiro($cx, $cy, 34, 9, 20, 9) ?>" fill="none" stroke="#0640a3" stroke-width="0.5" opacity="0.55"/>
            <path d="<?= $spiro($cx, $cy, 24, 7, 13, 7) ?>" fill="none" stroke="#0640a3" stroke-width="0.5" opacity="0.7"/>
            <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="4.4" fill="#0640a3"/>
        <?php endforeach; ?>
    </svg>

    <div class="jm-cert-inner">
        <img class="jm-cert-logo" src="/jobmington/assets/images/badge.png?v=logo-7" alt="">
        <h2 class="jm-cert-title">Certificate</h2>
        <div class="jm-cert-sub">of Completion</div>

        <div class="jm-cert-present">This certificate is proudly presented to</div>
        <div class="jm-cert-namerow">
            <span class="ln"></span>
            <span class="jm-cert-name"><?= e($certName) ?></span>
            <span class="ln r"></span>
        </div>

        <div class="jm-cert-for">has successfully completed the course</div>
        <div class="jm-cert-course"><?= e($certCourse) ?></div>
        <div class="jm-cert-when">Issued <?= e($certDate) ?></div>

        <div class="jm-cert-foot">
            <div class="jm-cert-sigcol">
                <div class="sig">Jobmington</div>
                <div class="ln"></div>
                <div class="lbl">Director, Jobmington</div>
            </div>

            <div class="jm-cert-seal" aria-label="Jobmington official seal">
                <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <path id="jmSealTop" d="M17,50 A33,33 0 0 1 83,50"/>
                        <path id="jmSealBot" d="M21,53 A29,29 0 0 0 79,53"/>
                    </defs>
                    <?php
                    $pts = []; $n = 28;
                    for ($i = 0; $i < $n * 2; $i++) {
                        $a = M_PI * $i / $n - M_PI / 2;
                        $r = ($i % 2 === 0) ? 49 : 44.5;
                        $pts[] = round(50 + cos($a) * $r, 2) . ',' . round(50 + sin($a) * $r, 2);
                    }
                    ?>
                    <polygon points="<?= implode(' ', $pts) ?>" fill="#0640a3"/>
                    <circle cx="50" cy="50" r="40.5" fill="#0640a3"/>
                    <circle cx="50" cy="50" r="37" fill="none" stroke="#fff" stroke-width="1" opacity="0.6"/>
                    <text fill="#dce8ff" font-size="5.4" font-family="Arial" font-weight="bold" letter-spacing="1.8"><textPath href="#jmSealTop" startOffset="50%" text-anchor="middle">JOBMINGTON</textPath></text>
                    <text fill="#dce8ff" font-size="4.4" font-family="Arial" font-weight="bold" letter-spacing="2.2"><textPath href="#jmSealBot" startOffset="50%" text-anchor="middle">OFFICIAL SEAL</textPath></text>
                    <circle cx="36" cy="50" r="1.1" fill="#dce8ff"/>
                    <circle cx="64" cy="50" r="1.1" fill="#dce8ff"/>
                    <circle cx="50" cy="48" r="14.5" fill="#fff"/>
                    <path d="M43.4 48.4l4.4 4.4 9.2-9.6" stroke="#0640a3" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                </svg>
            </div>

            <div class="jm-cert-sigcol">
                <div class="sig">Career Learning</div>
                <div class="ln"></div>
                <div class="lbl">Head, Career Learning</div>
            </div>
        </div>

        <div class="jm-cert-footline">
            <img src="<?= e($qrUrl) ?>" alt="Scan to verify" loading="lazy">
            <span>Certificate ID <b><?= e($certCode) ?></b> &middot; Verify at jobmington.com/verify</span>
        </div>
    </div>
</div>
</div>
