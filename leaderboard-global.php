<?php
/**
 * Global Leaderboard API for Game Arcade
 *
 * GET: Returns top scores for a game
 *   ?game=snake           - Get top 10 for snake
 *   ?game=snake&limit=20  - Get top 20 for snake
 *   ?game=all             - Get top 5 from each game
 *
 * POST: Submit a new score
 *   { "game": "snake", "name": "Player", "score": 100 }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Valid games list
$validGames = [
    'flappy-bird' => ['name' => 'Flappy Bird', 'icon' => '🐤', 'maxScore' => 1000],
    'snake' => ['name' => 'Snake', 'icon' => '🐍', 'maxScore' => 100000],
    'space-invaders' => ['name' => 'Space Invaders', 'icon' => '👾', 'maxScore' => 1000000],
    'pac-man' => ['name' => 'Pac-Man', 'icon' => '🟡', 'maxScore' => 1000000],
    'whack-a-mole' => ['name' => 'Whack-a-Mole', 'icon' => '🐹', 'maxScore' => 500],
    'cookie-clicker' => ['name' => 'Cookie Clicker', 'icon' => '🍪', 'maxScore' => 999999999999],
    'zombie-shooter' => ['name' => 'Zombie Shooter', 'icon' => '🧟', 'maxScore' => 10000000],
    'geometry-dash' => ['name' => 'Geometry Dash', 'icon' => '🔷', 'maxScore' => 1000],
    'doodle-jump' => ['name' => 'Doodle Jump', 'icon' => '🦘', 'maxScore' => 100000],
    'knife-hit' => ['name' => 'Knife Hit', 'icon' => '🔪', 'maxScore' => 10000],
    'tower-defense' => ['name' => 'Tower Defense', 'icon' => '🏰', 'maxScore' => 1000000],
    'asteroids' => ['name' => 'Asteroids', 'icon' => '🪨', 'maxScore' => 100000],
    'fruit-ninja' => ['name' => 'Fruit Ninja', 'icon' => '🍉', 'maxScore' => 10000],
    'crossy-road' => ['name' => 'Crossy Road', 'icon' => '🐔', 'maxScore' => 10000],
    'platformer' => ['name' => 'Platformer', 'icon' => '🏃', 'maxScore' => 100000],
    'fishing' => ['name' => 'Fishing', 'icon' => '🎣', 'maxScore' => 100000],
    'capybara-clicker' => ['name' => 'Capybara Clicker', 'icon' => '🦫', 'maxScore' => 999999999999],
    'retro-bowl' => ['name' => 'Retro Bowl', 'icon' => '🏈', 'maxScore' => 1000],
    'pop-the-lock' => ['name' => 'Pop the Lock', 'icon' => '🔓', 'maxScore' => 1000],
    'dropper' => ['name' => 'The Dropper', 'icon' => '⬇️', 'maxScore' => 100],
    'war-simulator' => ['name' => 'War Simulator', 'icon' => '⚔️', 'maxScore' => 1000000],
];

$dataDir = __DIR__ . '/leaderboards/';

// Create data directory if it doesn't exist
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

function getDataFile($game) {
    global $dataDir;
    return $dataDir . $game . '.json';
}

function readData($file) {
    if (!file_exists($file)) {
        return ['scores' => []];
    }
    $content = file_get_contents($file);
    return json_decode($content, true) ?: ['scores' => []];
}

function writeData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function sanitize($input, $maxLength = 15) {
    $clean = strip_tags(trim($input));
    $clean = preg_replace('/[^\w\s\-]/', '', $clean);
    return substr($clean, 0, $maxLength);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $game = isset($_GET['game']) ? strtolower(trim($_GET['game'])) : '';
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 50) : 10;

    // Get all leaderboards summary
    if ($game === 'all' || $game === '') {
        $allScores = [];
        foreach ($validGames as $gameId => $gameInfo) {
            $data = readData(getDataFile($gameId));
            $scores = $data['scores'];
            usort($scores, function($a, $b) {
                return $b['score'] - $a['score'];
            });
            $topScores = array_slice($scores, 0, 5);
            if (!empty($topScores)) {
                $allScores[$gameId] = [
                    'name' => $gameInfo['name'],
                    'icon' => $gameInfo['icon'],
                    'scores' => $topScores
                ];
            }
        }
        echo json_encode([
            'success' => true,
            'games' => $validGames,
            'leaderboards' => $allScores
        ]);
        exit();
    }

    // Get specific game leaderboard
    if (!isset($validGames[$game])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid game']);
        exit();
    }

    $data = readData(getDataFile($game));
    $scores = $data['scores'];

    usort($scores, function($a, $b) {
        return $b['score'] - $a['score'];
    });

    $topScores = array_slice($scores, 0, $limit);

    echo json_encode([
        'success' => true,
        'game' => $game,
        'gameInfo' => $validGames[$game],
        'scores' => $topScores
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['game']) || !isset($input['name']) || !isset($input['score'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing game, name, or score']);
        exit();
    }

    $game = strtolower(trim($input['game']));
    $name = sanitize($input['name']);
    $score = intval($input['score']);

    if (!isset($validGames[$game])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid game']);
        exit();
    }

    if (empty($name)) {
        $name = 'Anonymous';
    }

    $maxScore = $validGames[$game]['maxScore'];
    if ($score < 0 || $score > $maxScore) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid score']);
        exit();
    }

    $dataFile = getDataFile($game);
    $data = readData($dataFile);

    // Add new score
    $data['scores'][] = [
        'name' => $name,
        'score' => $score,
        'date' => date('Y-m-d H:i:s')
    ];

    // Sort and keep top 100
    usort($data['scores'], function($a, $b) {
        return $b['score'] - $a['score'];
    });
    $data['scores'] = array_slice($data['scores'], 0, 100);

    writeData($dataFile, $data);

    // Find rank
    $rank = 1;
    foreach ($data['scores'] as $entry) {
        if ($entry['score'] === $score && $entry['name'] === $name) {
            break;
        }
        $rank++;
    }

    echo json_encode([
        'success' => true,
        'rank' => $rank,
        'totalPlayers' => count($data['scores']),
        'isTopTen' => $rank <= 10,
        'message' => $rank <= 10 ? 'You made the top 10!' : 'Score submitted!'
    ]);

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>
