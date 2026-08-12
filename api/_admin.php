<?php
/**
 * Shared authorization helpers.
 *
 * Previously the site-admin list was a literal repeated in admin.php and
 * admin-reward.php, and the per-game admin/owner codes were plaintext literals
 * in game-admin.php and space-admin.php. Because the deploy checkout's .git
 * directory was being served over HTTP, that source -- and therefore every one
 * of those codes -- was publicly downloadable. Treat all pre-existing codes as
 * compromised and rotate them.
 *
 * Codes now live in data/admin-codes.json, which is gitignored and denied by
 * .htaccess, so they are neither committed nor fetchable.
 */

require_once __DIR__ . '/_store.php';

const SITE_ADMINS = ['billybuffalo15'];

function is_site_admin(): bool {
    return isset($_SESSION['user'])
        && in_array($_SESSION['user'], SITE_ADMINS, true);
}

/**
 * Gate an endpoint behind the site-admin list. Emits JSON and exits on failure,
 * so it is safe to call as the first statement of a handler.
 */
function require_site_admin(): void {
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    if (!is_site_admin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Not authorized']);
        exit;
    }
}

/**
 * Per-game admin/owner codes, keyed by game slug:
 *   { "space-invaders": { "admin": "...", "owner": "..." }, ... }
 *
 * Returns [] when the file is absent, which makes every code comparison fail
 * closed rather than falling back to a built-in default.
 */
function admin_codes_raw(): array {
    $codes = store_read(__DIR__ . '/../data/admin-codes.json', []);
    return is_array($codes) ? $codes : [];
}

/**
 * Game entries only. Keys beginning with "_" are reserved for settings such as
 * "_ownerMaster" and are filtered out here, because callers iterate this array
 * and surface its keys as the list of games.
 */
function game_codes(): array {
    $out = [];
    foreach (admin_codes_raw() as $key => $value) {
        if (is_string($key) && $key !== '' && $key[0] === '_') {
            continue;
        }
        $out[$key] = $value;
    }
    return $out;
}

function game_code_matches(string $game, string $role, string $supplied): bool {
    if ($supplied === '') {
        return false;
    }
    $codes = game_codes();
    if (!isset($codes[$game][$role]) || $codes[$game][$role] === '') {
        return false;
    }
    return hash_equals((string) $codes[$game][$role], $supplied);
}

/**
 * Master owner code, previously a hardcoded literal compared with === in
 * game-admin.php and space-admin.php. Supplying it granted full owner rights to
 * ANY logged-in account, and because it lived in source that was downloadable
 * through the exposed .git directory, it must be considered public. Rotate it.
 *
 * Fails closed: an absent or empty "_ownerMaster" key matches nothing.
 */
function owner_master_matches(string $supplied): bool {
    if ($supplied === '') {
        return false;
    }
    $codes = admin_codes_raw();
    $master = $codes['_ownerMaster'] ?? '';
    if (!is_string($master) || $master === '') {
        return false;
    }
    return hash_equals($master, $supplied);
}
