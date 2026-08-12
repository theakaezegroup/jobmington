<?php
/**
 * Keep the tool registry and everything that reads it in agreement.
 *
 * The registry is the only place a tool is defined, but three other things
 * have to line up with it: the cards on the Tools page, the gate at the top of
 * each tool page, and the gate on each API endpoint the tool calls. A tool
 * locked in one of those and open in another is worse than not gating it at
 * all, because it looks handled.
 *
 * Returns a list of human-readable problems, empty when everything agrees.
 */

if (!defined('JOBMINGTON')) {
    http_response_code(403);
    exit('Not a standalone script');
}

function jm_tools_audit(string $root): array
{
    $problems = [];
    $tools = jm_tools();

    // 0. The priced view still covers the whole registry. These were two
    //    hand-written lists that had already drifted apart on one key.
    foreach (array_keys(jm_ai_tools()) as $key) {
        if (!isset($tools[$key])) {
            $problems[] = "jm_ai_tools() prices '{$key}', which is not in the registry";
        }
    }
    foreach ($tools as $key => $tool) {
        if (!array_key_exists('credit_cost', $tool) || !array_key_exists('is_free', $tool)) {
            $problems[] = "registry entry '{$key}' has no price, so the paywall cannot read it";
        }
    }

    // 1. Every card on the Tools page names a key the registry knows, and
    //    every built tool has a card. Unbuilt ones have no page to link to.
    $page = (string) @file_get_contents($root . '/tools/index.php');
    preg_match_all("/'key'\s*=>\s*'([a-z_]+)'/", $page, $m);
    $cardKeys = $m[1];

    foreach ($cardKeys as $key) {
        if (!isset($tools[$key])) {
            $problems[] = "tools/index.php offers a card for '{$key}', which is not in the registry";
        }
    }
    foreach ($tools as $key => $tool) {
        if (!empty($tool['built']) && !in_array($key, $cardKeys, true)) {
            $problems[] = "registry has '{$key}' but no card on tools/index.php names it";
        }
        if (empty($tool['built']) && in_array($key, $cardKeys, true)) {
            $problems[] = "tools/index.php shows '{$key}', which is not built yet";
        }
    }

    // 2. Every API the registry lists for a tool actually gates on that tool.
    foreach ($tools as $key => $tool) {
        foreach ($tool['api'] as $rel) {
            $path = $root . '/' . $rel;
            if (!is_file($path)) {
                $problems[] = "registry points '{$key}' at {$rel}, which does not exist";
                continue;
            }
            $src = (string) file_get_contents($path);
            if (strpos($src, 'jm_require_tool_api') === false) {
                $problems[] = "{$rel} does the work for '{$key}' but has no jm_require_tool_api gate";
            } elseif (strpos($src, "'{$key}'") === false) {
                $problems[] = "{$rel} is gated, but not on '{$key}'";
            }
        }
    }

    // 3. Every tool page named by the registry gates on its own key.
    foreach ($tools as $key => $tool) {
        if (empty($tool['built'])) {
            continue;   // priced, but there is no page to gate yet
        }
        $url = ltrim($tool['url'], '/');
        $path = $root . '/' . (substr($url, -1) === '/' ? $url . 'index.php' : $url);

        if (!is_file($path)) {
            $problems[] = "registry points '{$key}' at {$tool['url']}, which does not exist";
            continue;
        }

        // Job search is the public listing, deliberately not gated: only its
        // matching API is. Everything else must gate its own page.
        if ($key === 'job_match') {
            continue;
        }

        $src = (string) file_get_contents($path);
        if (strpos($src, "jm_require_tool('{$key}')") === false) {
            $problems[] = "{$tool['url']} is the page for '{$key}' but does not call jm_require_tool('{$key}')";
        }
    }

    return $problems;
}
