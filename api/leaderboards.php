<?php
session_start();
header('Content-Type: application/json');

$usersFile = __DIR__ . '/../data/users.json';
$leaderboardsDir = __DIR__ . '/../leaderboards/';

function loadJson($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

$action = $_GET['action'] ?? '';

$users = loadJson($usersFile);
$users = $users['users'] ?? [];

// Game display names
$GAME_NAMES = [
    '2048' => '2048',
    'asteroids' => 'Asteroids',
    'brick-breaker' => 'Brick Breaker',
    'crossy-road' => 'Crossy Road',
    'doodle-jump' => 'Doodle Jump',
    'dropper' => 'Dropper',
    'fishing' => 'Fishing',
    'flappy-bird' => 'Flappy Bird',
    'fruit-ninja' => 'Fruit Ninja',
    'geometry-dash' => 'Geometry Dash',
    'knife-hit' => 'Knife Hit',
    'minesweeper' => 'Minesweeper',
    'pac-man' => 'Pac-Man',
    'platformer' => 'Platformer',
    'pop-the-lock' => 'Pop the Lock',
    'retro-bowl' => 'Retro Bowl',
    'snake' => 'Snake',
    'space-invaders' => 'Space Invaders',
    'tetris' => 'Tetris',
    'tower-defense' => 'Tower Defense',
    'war-simulator' => 'War Simulator',
    'whack-a-mole' => 'Whack-a-Mole',
    'zombie-shooter' => 'Zombie Shooter'
];

// Build leaderboard data from users
function buildLeaderboards($users) {
    $leaderboards = [
        'coins' => [],
        'xp' => [],
        'gamesPlayed' => [],
        'timePlayed' => []
    ];

    foreach ($users as $username => $user) {
        if (!is_array($user)) continue;
        if (isset($user['banned']) && $user['banned']) continue;

        $stats = $user['stats'] ?? [];

        $leaderboards['coins'][] = [
            'username' => $user['displayName'] ?? $username,
            'realUsername' => $username,
            'avatar' => $user['avatar'] ?? '👤',
            'value' => $user['coins'] ?? 0,
            'rank' => $user['rank'] ?? 'Bronze'
        ];

        $leaderboards['xp'][] = [
            'username' => $user['displayName'] ?? $username,
            'realUsername' => $username,
            'avatar' => $user['avatar'] ?? '👤',
            'value' => $user['xp'] ?? 0,
            'level' => floor(sqrt(($user['xp'] ?? 0) / 10)) + 1,
            'rank' => $user['rank'] ?? 'Bronze'
        ];

        $leaderboards['gamesPlayed'][] = [
            'username' => $user['displayName'] ?? $username,
            'realUsername' => $username,
            'avatar' => $user['avatar'] ?? '👤',
            'value' => $stats['totalGamesPlayed'] ?? 0,
            'rank' => $user['rank'] ?? 'Bronze'
        ];

        $timePlayed = $stats['totalTimePlayed'] ?? 0;
        $leaderboards['timePlayed'][] = [
            'username' => $user['displayName'] ?? $username,
            'realUsername' => $username,
            'avatar' => $user['avatar'] ?? '👤',
            'value' => $timePlayed,
            'formatted' => formatTime($timePlayed),
            'rank' => $user['rank'] ?? 'Bronze'
        ];
    }

    // Sort each leaderboard by value descending
    foreach ($leaderboards as $type => &$board) {
        usort($board, fn($a, $b) => $b['value'] - $a['value']);
        // Filter out zero values
        $board = array_values(array_filter($board, fn($e) => $e['value'] > 0));
        // Add position
        foreach ($board as $i => &$entry) {
            $entry['position'] = $i + 1;
        }
    }

    return $leaderboards;
}

function formatTime($seconds) {
    if ($seconds < 60) return $seconds . 's';
    if ($seconds < 3600) return floor($seconds / 60) . 'm';
    return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
}

// Get per-game leaderboard
function getGameLeaderboard($game) {
    global $leaderboardsDir;
    $file = $leaderboardsDir . $game . '.json';
    if (!file_exists($file)) return [];
    
    $data = json_decode(file_get_contents($file), true);
    $scores = $data['scores'] ?? $data ?? [];
    
    if (empty($scores) || !is_array($scores)) return [];
    
    // Sort by score descending
    usort($scores, fn($a, $b) => ($b['score'] ?? 0) - ($a['score'] ?? 0));
    
    $result = [];
    foreach ($scores as $i => $entry) {
        $result[] = [
            'position' => $i + 1,
            'username' => $entry['name'] ?? 'Unknown',
            'avatar' => '🎮',
            'value' => $entry['score'] ?? 0,
            'date' => $entry['date'] ?? '',
            'nameColor' => $entry['nameColor'] ?? null,
            'title' => $entry['title'] ?? null
        ];
    }
    
    return $result;
}

// Get list of all games with scores
function getGamesWithScores() {
    global $leaderboardsDir, $GAME_NAMES;
    $games = [];
    
    foreach (glob($leaderboardsDir . '*.json') as $file) {
        $gameId = basename($file, '.json');
        $data = json_decode(file_get_contents($file), true);
        $scores = $data['scores'] ?? $data ?? [];
        
        if (!empty($scores) && is_array($scores)) {
            $games[] = [
                'id' => $gameId,
                'name' => $GAME_NAMES[$gameId] ?? ucwords(str_replace('-', ' ', $gameId)),
                'playerCount' => count($scores),
                'topScore' => $scores[0]['score'] ?? 0,
                'topPlayer' => $scores[0]['name'] ?? 'Unknown'
            ];
        }
    }
    
    // Sort by player count descending
    usort($games, fn($a, $b) => $b['playerCount'] - $a['playerCount']);
    
    return $games;
}

// Build combined high scores leaderboard from all game files
function buildHighScoresLeaderboard() {
    global $leaderboardsDir, $GAME_NAMES;
    $playerScores = [];
    
    // Read all game leaderboard files
    foreach (glob($leaderboardsDir . '*.json') as $file) {
        $gameId = basename($file, '.json');
        $data = json_decode(file_get_contents($file), true);
        $scores = $data['scores'] ?? $data ?? [];
        
        if (empty($scores) || !is_array($scores)) continue;
        
        foreach ($scores as $entry) {
            $name = strtolower($entry['name'] ?? 'unknown');
            $score = $entry['score'] ?? 0;
            
            if (!isset($playerScores[$name])) {
                $playerScores[$name] = [
                    'username' => $entry['name'] ?? 'Unknown',
                    'avatar' => '🎮',
                    'totalScore' => 0,
                    'gamesPlayed' => 0,
                    'bestGame' => '',
                    'bestScore' => 0,
                    'nameColor' => $entry['nameColor'] ?? null,
                    'title' => $entry['title'] ?? null
                ];
            }
            
            $playerScores[$name]['totalScore'] += $score;
            $playerScores[$name]['gamesPlayed']++;
            
            // Track best individual game score
            if ($score > $playerScores[$name]['bestScore']) {
                $playerScores[$name]['bestScore'] = $score;
                $playerScores[$name]['bestGame'] = $GAME_NAMES[$gameId] ?? $gameId;
            }
            
            // Keep latest name color/title
            if ($entry['nameColor'] ?? null) {
                $playerScores[$name]['nameColor'] = $entry['nameColor'];
            }
            if ($entry['title'] ?? null) {
                $playerScores[$name]['title'] = $entry['title'];
            }
        }
    }
    
    // Convert to array and sort by total score
    $leaderboard = array_values($playerScores);
    usort($leaderboard, fn($a, $b) => $b['totalScore'] - $a['totalScore']);
    
    // Add position and format
    $result = [];
    foreach ($leaderboard as $i => $entry) {
        $result[] = [
            'position' => $i + 1,
            'username' => $entry['username'],
            'avatar' => $entry['avatar'],
            'value' => $entry['totalScore'],
            'gamesPlayed' => $entry['gamesPlayed'],
            'bestGame' => $entry['bestGame'],
            'bestScore' => $entry['bestScore'],
            'nameColor' => $entry['nameColor'],
            'title' => $entry['title']
        ];
    }
    
    return $result;
}

switch ($action) {
    case 'coins':
    case 'xp':
    case 'gamesPlayed':
    case 'timePlayed':
        $leaderboards = buildLeaderboards($users);
        $limit = intval($_GET['limit'] ?? 100);
        $offset = intval($_GET['offset'] ?? 0);

        $board = array_slice($leaderboards[$action], $offset, $limit);

        // If logged in, find user's position
        $myPosition = null;
        if (isset($_SESSION['user'])) {
            $username = $_SESSION['user'];
            foreach ($leaderboards[$action] as $entry) {
                if ($entry['realUsername'] === $username) {
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

    case 'highScores':
        $board = buildHighScoresLeaderboard();
        $limit = intval($_GET['limit'] ?? 100);
        $offset = intval($_GET['offset'] ?? 0);
        
        $total = count($board);
        $board = array_slice($board, $offset, $limit);
        
        echo json_encode([
            'success' => true,
            'type' => 'highScores',
            'leaderboard' => $board,
            'total' => $total,
            'myPosition' => null,
            'updated' => date('c')
        ]);
        break;

    case 'games':
        // Return list of games with leaderboards
        $games = getGamesWithScores();
        echo json_encode([
            'success' => true,
            'games' => $games
        ]);
        break;

    case 'gameLeaderboard':
        $game = $_GET['game'] ?? '';
        if (!$game) {
            echo json_encode(['success' => false, 'error' => 'Game required']);
            exit;
        }

        $board = getGameLeaderboard($game);
        
        echo json_encode([
            'success' => true,
            'game' => $game,
            'gameName' => $GAME_NAMES[$game] ?? ucwords(str_replace('-', ' ', $game)),
            'leaderboard' => $board,
            'total' => count($board),
            'updated' => date('c')
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
