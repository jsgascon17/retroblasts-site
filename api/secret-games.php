<?php
/**
 * Secret Games API
 * 
 * GET:
 *   ?action=list - List all secret games with unlock status
 *   ?action=check&game=memory-match - Check if user can access
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
$usersFile = $dataDir . '/users.json';

// Secret game definitions
$SECRET_GAMES = [
    [
        'id' => 'memory-match',
        'name' => 'Memory Match',
        'icon' => '🧠',
        'description' => 'Test your memory by matching pairs!',
        'url' => 'games/secret/memory-match.html',
        'unlockType' => 'level',
        'unlockValue' => 25,
        'unlockText' => 'Reach Level 25'
    ],
    [
        'id' => 'reaction-test',
        'name' => 'Reaction Test',
        'icon' => '⚡',
        'description' => 'How fast are your reflexes?',
        'url' => 'games/secret/reaction-test.html',
        'unlockType' => 'achievements',
        'unlockValue' => 10,
        'unlockText' => 'Unlock 10 achievements'
    ],
    [
        'id' => 'typing-race',
        'name' => 'Typing Race',
        'icon' => '⌨️',
        'description' => 'Type at lightning speed!',
        'url' => 'games/secret/typing-race.html',
        'unlockType' => 'tournament_win',
        'unlockValue' => 1,
        'unlockText' => 'Win a tournament'
    ]
];

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function writeUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

function calculateLevel($xp) {
    return floor(sqrt($xp / 10)) + 1;
}

function checkUnlock($game, $user) {
    $type = $game['unlockType'];
    $value = $game['unlockValue'];
    
    switch ($type) {
        case 'level':
            return calculateLevel($user['xp'] ?? 0) >= $value;
        case 'achievements':
            return count($user['achievements'] ?? []) >= $value;
        case 'tournament_win':
            return ($user['stats']['tournamentWins'] ?? 0) >= $value;
        default:
            return false;
    }
}

function getProgress($game, $user) {
    $type = $game['unlockType'];
    $value = $game['unlockValue'];
    
    switch ($type) {
        case 'level':
            $current = calculateLevel($user['xp'] ?? 0);
            return ['current' => $current, 'required' => $value, 'percent' => min(100, ($current / $value) * 100)];
        case 'achievements':
            $current = count($user['achievements'] ?? []);
            return ['current' => $current, 'required' => $value, 'percent' => min(100, ($current / $value) * 100)];
        case 'tournament_win':
            $current = $user['stats']['tournamentWins'] ?? 0;
            return ['current' => $current, 'required' => $value, 'percent' => $current >= $value ? 100 : 0];
        default:
            return ['current' => 0, 'required' => $value, 'percent' => 0];
    }
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    global $SECRET_GAMES;
    $action = $_GET['action'] ?? 'list';
    
    // Get user data if logged in
    $user = null;
    if (isset($_SESSION['user'])) {
        $data = readUsers();
        $user = $data['users'][$_SESSION['user']] ?? null;
    }
    
    if ($action === 'list') {
        $games = [];
        
        foreach ($SECRET_GAMES as $game) {
            $unlocked = $user ? checkUnlock($game, $user) : false;
            $progress = $user ? getProgress($game, $user) : null;
            
            $games[] = [
                'id' => $game['id'],
                'name' => $game['name'],
                'icon' => $game['icon'],
                'description' => $game['description'],
                'url' => $unlocked ? $game['url'] : null,
                'unlocked' => $unlocked,
                'unlockText' => $game['unlockText'],
                'progress' => $progress
            ];
        }
        
        echo json_encode(['success' => true, 'games' => $games]);
        exit();
    }
    
    if ($action === 'check') {
        $gameId = $_GET['game'] ?? '';
        
        $game = null;
        foreach ($SECRET_GAMES as $g) {
            if ($g['id'] === $gameId) {
                $game = $g;
                break;
            }
        }
        
        if (!$game) {
            echo json_encode(['success' => false, 'error' => 'Game not found']);
            exit();
        }
        
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Not logged in', 'unlocked' => false]);
            exit();
        }
        
        $unlocked = checkUnlock($game, $user);
        
        if ($unlocked) {
            // Add to user's unlocked games if not already
            $data = readUsers();
            if (!in_array($gameId, $data['users'][$_SESSION['user']]['secretGamesUnlocked'] ?? [])) {
                $data['users'][$_SESSION['user']]['secretGamesUnlocked'][] = $gameId;
                writeUsers($data);
            }
        }
        
        echo json_encode([
            'success' => true,
            'unlocked' => $unlocked,
            'url' => $unlocked ? $game['url'] : null,
            'progress' => getProgress($game, $user)
        ]);
        exit();
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>
