<?php
/**
 * JOBMINGTON - Maintenance.
 *
 * Rendered by the guard in includes/maintenance.php, not routed to directly.
 * Self-contained on purpose: it has to render when the rest of the site is
 * deliberately unavailable, so it pulls in nothing but its own markup.
 *
 * Expects $jmMaintenanceMessage and $jmMaintenanceBack to already be set.
 */

if (!defined('JOBMINGTON')) {
    http_response_code(403);
    exit('Forbidden');
}

$base    = defined('SITE_URL') ? SITE_URL : '';
$message = $jmMaintenanceMessage ?? 'We are making Jobmington better. Back shortly.';
$back    = $jmMaintenanceBack ?? '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Back shortly &middot; Jobmington</title>
<link rel="icon" href="<?= htmlspecialchars($base) ?>/assets/images/favicon.png">
<style>
  @font-face {
    font-family: 'Futura Cyrillic Demi';
    src: url('<?= htmlspecialchars($base) ?>/assets/fonts/FuturaCyrillicDemi.ttf') format('truetype');
    font-weight: 700; font-style: normal; font-display: swap;
  }
  @font-face {
    font-family: 'Futura Cyrillic Book';
    src: url('<?= htmlspecialchars($base) ?>/assets/fonts/FuturaCyrillicBook.ttf') format('truetype');
    font-weight: 400; font-style: normal; font-display: swap;
  }

  *, *::before, *::after { box-sizing: border-box; }
  html, body { height: 100%; }
  body {
    margin: 0;
    font-family: 'Futura Cyrillic Book', 'Century Gothic', 'Trebuchet MS', Helvetica, Arial, sans-serif;
    background: #fbfcfe;
    color: #0b1b33;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 32px 20px;
    -webkit-font-smoothing: antialiased;
  }

  /* A single soft wash of brand blue, so the page has depth without ornament. */
  body::before {
    content: '';
    position: fixed;
    inset: -30vh 0 auto 0;
    height: 60vh;
    background: radial-gradient(ellipse at 50% 0%, rgba(6,64,163,.09), transparent 68%);
    pointer-events: none;
  }

  .m-card { position: relative; width: 100%; max-width: 460px; text-align: center; }

  .m-mark {
    width: 62px; height: 62px; display: block; margin: 0 auto 26px;
    border-radius: 15px;
  }

  h1 {
    font-family: 'Futura Cyrillic Demi', 'Century Gothic', Helvetica, Arial, sans-serif;
    font-weight: 700;
    font-size: clamp(27px, 6vw, 35px);
    letter-spacing: -.02em;
    line-height: 1.15;
    margin: 0 0 14px;
  }

  p.m-msg {
    font-size: 15.5px;
    line-height: 1.7;
    color: #56677f;
    margin: 0 auto;
    max-width: 380px;
  }

  /* The one moving element: a bar that fills, empties and repeats, so the
     page reads as in progress rather than broken. */
  .m-bar {
    position: relative;
    width: 168px; height: 3px;
    margin: 34px auto 0;
    border-radius: 99px;
    background: #e6ecf6;
    overflow: hidden;
  }
  .m-bar span {
    position: absolute; inset: 0;
    display: block;
    border-radius: 99px;
    background: linear-gradient(90deg, #0640a3, #f59f22);
    transform-origin: left center;
    animation: m-sweep 2.1s cubic-bezier(.6,.05,.3,1) infinite;
  }
  @keyframes m-sweep {
    0%   { transform: scaleX(0);   opacity: 1; }
    60%  { transform: scaleX(1);   opacity: 1; }
    100% { transform: scaleX(1);   opacity: 0; }
  }
  @media (prefers-reduced-motion: reduce) {
    .m-bar span { animation: none; transform: scaleX(.45); }
  }

  .m-back {
    margin: 26px 0 0;
    font-size: 13px;
    color: #8a99ad;
  }
  .m-back strong { color: #0b1b33; font-weight: 700; }

  .m-foot {
    position: relative;
    margin-top: 44px;
    font-size: 12.5px;
    color: #a4b0c0;
  }
  .m-foot a { color: #0640a3; text-decoration: none; font-weight: 700; }
  .m-foot a:hover { text-decoration: underline; }
</style>
</head>
<body>

  <main class="m-card">
    <img class="m-mark" src="<?= htmlspecialchars($base) ?>/assets/images/badge.png?v=logo-8" alt="Jobmington" width="62" height="62">

    <h1>We will be right back.</h1>
    <p class="m-msg"><?= htmlspecialchars($message) ?></p>

    <div class="m-bar" role="presentation"><span></span></div>

    <?php if ($back !== ''): ?>
      <p class="m-back">Expected back around <strong><?= htmlspecialchars($back) ?></strong></p>
    <?php endif; ?>
  </main>

  <p class="m-foot">
    Questions? <a href="mailto:hello@jobmington.com">hello@jobmington.com</a>
  </p>

</body>
</html>
