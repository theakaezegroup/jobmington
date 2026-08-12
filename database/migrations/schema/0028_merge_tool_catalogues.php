<?php
/**
 * One catalogue instead of two.
 *
 * The resume optimizer was 'cv_roast' to the access gate and 'cv_optimizer' to
 * the paywall: the same tool with two names in two hand-written lists. The
 * paywall's name wins, because it is the one already written into
 * api/cv-roast.php, cv-builder/analyze.php and TOOL_COST_CV_OPTIMIZER.
 *
 * The four tools that were priced but never built get rows too, seeded off, so
 * they stop appearing on the pricing page as things people can buy.
 */
return function (PDO $pdo): void {

    // Carry across whatever the old key was set to rather than resetting it.
    $pdo->prepare("UPDATE IGNORE tool_flags  SET tool_key = 'cv_optimizer' WHERE tool_key = 'cv_roast'")->execute();
    $pdo->prepare("UPDATE IGNORE tool_grants SET tool_key = 'cv_optimizer' WHERE tool_key = 'cv_roast'")->execute();

    // UPDATE IGNORE leaves the old row behind if a cv_optimizer row already
    // existed, so clear any straggler.
    $pdo->prepare("DELETE FROM tool_flags  WHERE tool_key = 'cv_roast'")->execute();
    $pdo->prepare("DELETE FROM tool_grants WHERE tool_key = 'cv_roast'")->execute();

    require_once __DIR__ . '/../../../includes/tools.php';

    $insert = $pdo->prepare("INSERT IGNORE INTO tool_flags (tool_key, status, note) VALUES (?, ?, ?)");
    foreach (jm_tools() as $key => $tool) {
        $built = !empty($tool['built']);
        $insert->execute([
            $key,
            $built ? 'on' : 'off',
            $built ? null : 'No page built yet',
        ]);
    }
};
