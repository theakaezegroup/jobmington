<?php
/**
 * JOBMINGTON - Community reactions.
 *
 * Evidence rather than applause. A like tells you a post was popular; these say
 * what it was worth, which is the only thing that matters when the subject is
 * advice about getting hired.
 */
if (!defined('JOBMINGTON')) { exit; }

/** The three kinds, in display order. Labels are shown, not just counts. */
function jm_reaction_kinds(): array {
    return [
        'helpful' => [
            'label' => 'Helpful',
            'done'  => 'Helpful',
            'title' => 'This taught me something',
            'icon'  => '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/>',
        ],
        'worked' => [
            'label' => 'Worked for me',
            'done'  => 'Worked for me',
            'title' => 'I tried this and it worked',
            'icon'  => '<path d="M20 6 9 17l-5-5"/>',
        ],
        'same' => [
            'label' => 'Same here',
            'done'  => 'Same here',
            'title' => 'This happens to me too',
            'icon'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        ],
    ];
}

/**
 * Counts per kind for a set of targets, plus which ones the viewer reacted to.
 * Batched deliberately: a thread renders one topic and many replies, and this
 * must not become a query per post.
 */
function jm_reactions_for(PDO $pdo, string $type, array $ids, ?int $viewerId): array {
    $out = [];
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) { return $out; }

    $in = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $pdo->prepare("SELECT target_id, kind, COUNT(*) AS n
                               FROM forum_reactions
                               WHERE target_type = ? AND target_id IN ($in)
                               GROUP BY target_id, kind");
        $stmt->execute(array_merge([$type], $ids));
        foreach ($stmt as $r) {
            $out[(int) $r['target_id']]['counts'][$r['kind']] = (int) $r['n'];
        }

        if ($viewerId) {
            $stmt = $pdo->prepare("SELECT target_id, kind FROM forum_reactions
                                   WHERE target_type = ? AND target_id IN ($in) AND user_id = ?");
            $stmt->execute(array_merge([$type], $ids, [$viewerId]));
            foreach ($stmt as $r) {
                $out[(int) $r['target_id']]['mine'][$r['kind']] = true;
            }
        }
    } catch (Throwable $e) {
        error_log('Reaction lookup failed: ' . $e->getMessage());
    }
    return $out;
}

/**
 * Toggle one reaction. Returns [ok, message].
 *
 * Refuses self-reactions: 'worked' pays Seeds, so without this an author could
 * simply confirm their own advice.
 */
function jm_reaction_toggle(PDO $pdo, string $type, int $targetId, int $userId, string $kind): array {
    if (!isset(jm_reaction_kinds()[$kind]) || !in_array($type, ['topic', 'reply'], true) || $targetId <= 0) {
        return [false, 'Unknown reaction.'];
    }

    try {
        $authorId = (int) jm_reaction_author($pdo, $type, $targetId);
        if (!$authorId) { return [false, 'That post no longer exists.']; }
        if ($authorId === $userId) { return [false, 'You cannot react to your own post.']; }

        $find = $pdo->prepare("SELECT reaction_id FROM forum_reactions
                               WHERE target_type = ? AND target_id = ? AND user_id = ? AND kind = ? LIMIT 1");
        $find->execute([$type, $targetId, $userId, $kind]);
        $existing = $find->fetchColumn();

        if ($existing) {
            $pdo->prepare("DELETE FROM forum_reactions WHERE reaction_id = ?")->execute([$existing]);
            return [true, 'removed'];
        }

        $pdo->prepare("INSERT INTO forum_reactions (target_type, target_id, user_id, kind) VALUES (?,?,?,?)")
            ->execute([$type, $targetId, $userId, $kind]);

        // Only the outcome reaction pays, and only on the way in. Seeds failing
        // must not undo a reaction that was recorded successfully.
        if ($kind === 'worked') {
            try {
                require_once __DIR__ . '/seeds.php';
                awardSeeds($authorId, 'forum_worked_for_me', $targetId,
                           'Someone confirmed your advice worked');
            } catch (Throwable $e) {
                error_log('Reaction seed award failed: ' . $e->getMessage());
            }
        }
        return [true, 'added'];
    } catch (Throwable $e) {
        error_log('Reaction toggle failed: ' . $e->getMessage());
        return [false, 'Could not save that just now.'];
    }
}

/** Author of a topic or reply, or 0. */
function jm_reaction_author(PDO $pdo, string $type, int $targetId): int {
    $sql = $type === 'topic'
        ? "SELECT user_id FROM forum_topics WHERE topic_id = ? LIMIT 1"
        : "SELECT user_id FROM forum_replies WHERE reply_id = ? LIMIT 1";
    $s = $pdo->prepare($sql);
    $s->execute([$targetId]);
    return (int) $s->fetchColumn();
}

/**
 * Render the reaction row. Buttons post to the current page, so this works on
 * the topic view without a separate endpoint or any JavaScript.
 */
function jm_reaction_bar(string $type, int $targetId, array $data, bool $canReact, bool $isOwn = false): string {
    $counts = $data['counts'] ?? [];
    $mine   = $data['mine'] ?? [];

    // Its own form, not one shared with the reply box: the page already has a
    // form, and nesting them is invalid HTML that browsers resolve unpredictably.
    $open  = $canReact && !$isOwn
        ? '<form method="post" class="jm-react">' . Security::csrfField()
        : '<div class="jm-react">';
    $close = $canReact && !$isOwn ? '</form>' : '</div>';

    $html = $open;

    foreach (jm_reaction_kinds() as $kind => $k) {
        $n    = (int) ($counts[$kind] ?? 0);
        $on   = !empty($mine[$kind]);
        $cls  = 'jm-react-btn' . ($on ? ' is-on' : '');
        $icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $k['icon'] . '</svg>';

        // A label with a count reads as meaningful at low volume; a bare number
        // next to an icon does not.
        $text = e($k['label']) . ($n > 0 ? ' <b>' . $n . '</b>' : '');

        if ($canReact && !$isOwn) {
            $html .= '<button class="' . $cls . '" type="submit" name="react" '
                   . 'value="' . e($type . ':' . $targetId . ':' . $kind) . '" '
                   . 'title="' . e($k['title']) . '">' . $icon . $text . '</button>';
        } elseif ($n > 0 || $isOwn) {
            $html .= '<span class="' . $cls . ' is-static" title="' . e($k['title']) . '">' . $icon . $text . '</span>';
        }
    }

    return $html . $close;
}

/** Styles, printed once per page. */
function jm_reaction_styles(): string {
    static $done = false;
    if ($done) { return ''; }
    $done = true;
    return '<style>'
        . '.jm-react{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;}'
        . '.jm-react-btn{display:inline-flex;align-items:center;gap:6px;padding:6px 11px;border:1px solid #e4eaf3;border-radius:99px;background:#fff;color:#53667f;font:inherit;font-size:12.5px;font-weight:700;cursor:pointer;transition:border-color .14s,color .14s,background .14s;}'
        . '.jm-react-btn svg{width:14px;height:14px;}'
        . '.jm-react-btn b{font-weight:800;color:#0b1b33;}'
        . '.jm-react-btn:hover{border-color:#0640a3;color:#0640a3;}'
        . '.jm-react-btn.is-on{background:#eaf1fd;border-color:#c3d8f7;color:#0640a3;}'
        . '.jm-react-btn.is-on b{color:#0640a3;}'
        . '.jm-react-btn.is-static{cursor:default;}'
        . '.jm-react-btn.is-static:hover{border-color:#e4eaf3;color:#53667f;}'
        . '.jm-verified-answer{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#0640a3;background:#eaf1fd;border-radius:6px;padding:4px 9px;margin-bottom:10px;}'
        . '.jm-verified-answer img{width:14px;height:14px;border-radius:3px;}'
        . '@media(max-width:560px){.jm-react-btn{padding:6px 9px;font-size:12px;}}'
        . '</style>';
}
