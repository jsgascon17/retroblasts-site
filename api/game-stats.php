<?php
/**
 * Game Stats API
 * Syncs game-specific stats (high scores, times, etc.) to user profiles
 *
 * POST actions:
 *   save: { game, data } - Save game stats for logged-in user
 *   load: { game } - Load game stats for logged-in user
 *   loadAll: {} - Load all game stats for logged-in user
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dataDir = __DIR__ . '/../data';
$gameStatsFile = $dataDir . '/game-stats.json';

// Initialize data files if needed
if (!file_exists($dataDir)) {
    mkdir($dataDir, 0755, true);
}
if (!file_exists($gameStatsFile)) {
    file_put_contents($gameStatsFile, json_encode(['users' => []], JSON_PRETTY_PRINT));
    chmod($gameStatsFile, 0666);
}

function readGameStats() {
    global $gameStatsFile;
    $data = json_decode(file_get_contents($gameStatsFile), true);
    return $data ?: ['users' => []];
}

function writeGameStats($data) {
    global $gameStatsFile;
    file_put_contents($gameStatsFile, json_encode($data, JSON_PRETTY_PRINT));
}

function sanitizeGameName($game) {
    return preg_replace('/[^a-z0-9\-_]/i', '', substr($game, 0, 50));
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'load';

    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit();
    }

    $username = $_SESSION['user'];
    $data = readGameStats();

    if ($action === 'load') {
        $game = sanitizeGameName($_GET['game'] ?? '');
        if (!$game) {
            echo json_encode(['success' => false, 'error' => 'Game name required']);
            exit();
        }

        $gameData = $data['users'][$username]['games'][$game] ?? null;
        echo json_encode([
            'success' => true,
            'data' => $gameData
        ]);
        exit();
    }

    if ($action === 'loadAll') {
        $allGames = $data['users'][$username]['games'] ?? [];
        echo json_encode([
            'success' => true,
            'games' => $allGames
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit();
    }

    $username = $_SESSION['user'];

    if ($action === 'save') {
        $game = sanitizeGameName($input['game'] ?? '');
        $gameData = $input['data'] ?? [];

        if (!$game) {
            echo json_encode(['success' => false, 'error' => 'Game name required']);
            exit();
        }

        $data = readGameStats();

        // Initialize user if needed
        if (!isset($data['users'][$username])) {
            $data['users'][$username] = [
                'games' => [],
                'lastUpdated' => date('c')
            ];
        }

        // Initialize game if needed
        if (!isset($data['users'][$username]['games'][$game])) {
            $data['users'][$username]['games'][$game] = [];
        }

        // Merge stats (keep higher values for scores, sum for totals)
        $existing = $data['users'][$username]['games'][$game];
        $merged = [];

        foreach ($gameData as $key => $value) {
            if (is_numeric($value)) {
                // For high scores, keep the maximum
                if (strpos(strtolower($key), 'high') !== false ||
                    strpos(strtolower($key), 'best') !== false ||
                    strpos(strtolower($key), 'max') !== false) {
                    $merged[$key] = max($existing[$key] ?? 0, $value);
                }
                // For totals and counts, keep the maximum (client tracks cumulative)
                else {
                    $merged[$key] = max($existing[$key] ?? 0, $value);
                }
            } else {
                // For non-numeric values, just use the new value
                $merged[$key] = $value;
            }
        }

        $data['users'][$username]['games'][$game] = $merged;
        $data['users'][$username]['lastUpdated'] = date('c');

        writeGameStats($data);

        echo json_encode([
            'success' => true,
            'message' => 'Stats saved',
            'data' => $merged
        ]);
        exit();
    }

    if ($action === 'load') {
        $game = sanitizeGameName($input['game'] ?? '');
        if (!$game) {
            echo json_encode(['success' => false, 'error' => 'Game name required']);
            exit();
        }

        $data = readGameStats();
        $gameData = $data['users'][$username]['games'][$game] ?? null;

        echo json_encode([
            'success' => true,
            'data' => $gameData
        ]);
        exit();
    }

    if ($action === 'loadAll') {
        $data = readGameStats();
        $allGames = $data['users'][$username]['games'] ?? [];

        echo json_encode([
            'success' => true,
            'games' => $allGames
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
