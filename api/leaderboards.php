<?php
session_start();
header('Content-Type: application/json');

$usersFile = __DIR__ . '/../data/users.json';
$gamesFile = __DIR__ . '/../data/game-stats.json';

function loadJson($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

$action = $_GET['action'] ?? '';

$users = loadJson($usersFile);
$gameStats = loadJson($gamesFile);

// Build leaderboard data from users
function buildLeaderboards($users, $gameStats) {
    $leaderboards = [
        'coins' => [],
        'xp' => [],
        'gamesPlayed' => [],
        'winStreak' => [],
        'totalWins' => [],
        'highScores' => []
    ];

    foreach ($users as $username => $user) {
        if (!isset($user['username'])) continue;

        // Skip inactive or banned users
        if (isset($user['banned']) && $user['banned']) continue;

        $stats = $user['stats'] ?? [];

        $leaderboards['coins'][] = [
            'username' => $username,
            'avatar' => $user['avatar'] ?? '👤',
            'value' => $user['coins'] ?? 0,
            'rank' => $user['rank'] ?? 'Bronze'
        ];

        $leaderboards['xp'][] = [
            'username' => $username,
            'avatar' => $user['avatar'] ?? '👤',
            'value' => $user['xp'] ?? 0,
            'level' => $user['level'] ?? 1,
            'rank' => $user['rank'] ?? 'Bronze'
        ];

        $leaderboards['gamesPlayed'][] = [
            'username' => $username,
            'avatar' => $user['avatar'] ?? '👤',
            'value' => $stats['gamesPlayed'] ?? 0,
            'rank' => $user['rank'] ?? 'Bronze'
        ];

        $leaderboards['winStreak'][] = [
            'username' => $username,
            'avatar' => $user['avatar'] ?? '👤',
            'value' => $stats['bestWinStreak'] ?? 0,
            'currentStreak' => $stats['currentWinStreak'] ?? 0,
            'rank' => $user['rank'] ?? 'Bronze'
        ];

        $leaderboards['totalWins'][] = [
            'username' => $username,
            'avatar' => $user['avatar'] ?? '👤',
            'value' => $stats['wins'] ?? 0,
            'rank' => $user['rank'] ?? 'Bronze'
        ];

        // Calculate total high score across all games
        $totalHighScore = 0;
        if (isset($stats['gameHighScores'])) {
            foreach ($stats['gameHighScores'] as $game => $score) {
                $totalHighScore += $score;
            }
        }
        $leaderboards['highScores'][] = [
            'username' => $username,
            'avatar' => $user['avatar'] ?? '👤',
            'value' => $totalHighScore,
            'rank' => $user['rank'] ?? 'Bronze'
        ];
    }

    // Sort each leaderboard
    foreach ($leaderboards as $type => &$board) {
        usort($board, fn($a, $b) => $b['value'] - $a['value']);

        // Add position
        foreach ($board as $i => &$entry) {
            $entry['position'] = $i + 1;
        }
    }

    return $leaderboards;
}

switch ($action) {
    case 'all':
        $leaderboards = buildLeaderboards($users, $gameStats);

        // Return top 100 for each
        foreach ($leaderboards as $type => &$board) {
            $board = array_slice($board, 0, 100);
        }

        echo json_encode([
            'success' => true,
            'leaderboards' => $leaderboards,
            'updated' => date('c')
        ]);
        break;

    case 'coins':
    case 'xp':
    case 'gamesPlayed':
    case 'winStreak':
    case 'totalWins':
    case 'highScores':
        $leaderboards = buildLeaderboards($users, $gameStats);
        $limit = intval($_GET['limit'] ?? 100);
        $offset = intval($_GET['offset'] ?? 0);

        $board = array_slice($leaderboards[$action], $offset, $limit);

        // If logged in, also get user's position
        $myPosition = null;
        if (isset($_SESSION['user'])) {
            $username = $_SESSION['user'];
            foreach ($leaderboards[$action] as $entry) {
                if ($entry['username'] === $username) {
                    $myPosition = $entry;
                    break;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'type' => $action,
            'leaderboard' => $board,
            'total' => count($leaderboards[$action]),
            'myPosition' => $myPosition,
            'updated' => date('c')
        ]);
        break;

    case 'myRanks':
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $username = $_SESSION['user'];
        $leaderboards = buildLeaderboards($users, $gameStats);

        $myRanks = [];
        foreach ($leaderboards as $type => $board) {
            foreach ($board as $entry) {
                if ($entry['username'] === $username) {
                    $myRanks[$type] = $entry;
                    break;
                }
            }
        }

        echo json_encode([
            'success' => true,
            'ranks' => $myRanks
        ]);
        break;

    case 'gameLeaderboard':
        $game = $_GET['game'] ?? '';
        if (!$game) {
            echo json_encode(['success' => false, 'error' => 'Game required']);
            exit;
        }

        $gameBoard = [];
        foreach ($users as $username => $user) {
            if (!isset($user['stats']['gameHighScores'][$game])) continue;

            $gameBoard[] = [
                'username' => $username,
                'avatar' => $user['avatar'] ?? '👤',
                'value' => $user['stats']['gameHighScores'][$game],
                'rank' => $user['rank'] ?? 'Bronze'
            ];
        }

        usort($gameBoard, fn($a, $b) => $b['value'] - $a['value']);
        foreach ($gameBoard as $i => &$entry) {
            $entry['position'] = $i + 1;
        }

        echo json_encode([
            'success' => true,
            'game' => $game,
            'leaderboard' => array_slice($gameBoard, 0, 100),
            'total' => count($gameBoard)
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
