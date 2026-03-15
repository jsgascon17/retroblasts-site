<?php
/**
 * Spectate Mode API
 * 
 * GET:
 *   ?action=list                    - List active games being played
 *   ?action=watch&player=X&game=Y   - Get live game state
 * 
 * POST:
 *   { "action": "broadcast", "game": "snake", "state": {...} }
 *   { "action": "stop" }
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$spectateDir = __DIR__ . '/../data/spectate/';
$usersFile = __DIR__ . '/../data/users.json';

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function getSpectateFile($username, $game) {
    global $spectateDir;
    return $spectateDir . $username . '_' . $game . '.json';
}

function cleanOldSpectates() {
    global $spectateDir;
    $now = time();
    $files = glob($spectateDir . '*.json');
    
    foreach ($files as $file) {
        // Remove files older than 30 seconds
        if (filemtime($file) < $now - 30) {
            @unlink($file);
        }
    }
}

function getActiveGames() {
    global $spectateDir;
    $files = glob($spectateDir . '*.json');
    $games = [];
    $now = time();
    
    foreach ($files as $file) {
        // Only show games updated in last 10 seconds
        if (filemtime($file) < $now - 10) continue;
        
        $content = @file_get_contents($file);
        if (!$content) continue;
        
        $data = json_decode($content, true);
        if (!$data) continue;
        
        $games[] = [
            'player' => $data['player'],
            'playerName' => $data['playerName'],
            'avatar' => $data['avatar'] ?? '😎',
            'game' => $data['game'],
            'score' => $data['state']['score'] ?? 0,
            'gameState' => $data['state']['gameState'] ?? 'playing',
            'spectators' => $data['spectators'] ?? 0,
            'startedAt' => $data['startedAt'] ?? date('c')
        ];
    }
    
    // Sort by score descending
    usort($games, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return $games;
}

// Handle GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    cleanOldSpectates();
    
    if ($action === 'list') {
        $games = getActiveGames();
        echo json_encode(['success' => true, 'games' => $games]);
        exit();
    }
    
    if ($action === 'watch') {
        $player = strtolower($_GET['player'] ?? '');
        $game = $_GET['game'] ?? '';
        
        if (!$player || !$game) {
            echo json_encode(['success' => false, 'error' => 'Missing player or game']);
            exit();
        }
        
        $file = getSpectateFile($player, $game);
        
        if (!file_exists($file)) {
            echo json_encode(['success' => false, 'error' => 'Game not found or ended']);
            exit();
        }
        
        // Check if still active (updated in last 10 seconds)
        if (filemtime($file) < time() - 10) {
            echo json_encode(['success' => false, 'error' => 'Game ended']);
            exit();
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        // Increment spectator count
        $data['spectators'] = ($data['spectators'] ?? 0) + 1;
        // Don't write back, just track in memory
        
        echo json_encode([
            'success' => true,
            'player' => $data['player'],
            'playerName' => $data['playerName'],
            'game' => $data['game'],
            'state' => $data['state'],
            'timestamp' => $data['timestamp']
        ]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit();
    }
    
    $currentUser = $_SESSION['user'];
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $users = readUsers();
    $user = $users['users'][$currentUser] ?? null;
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    if ($action === 'broadcast') {
        $game = $input['game'] ?? '';
        $state = $input['state'] ?? [];
        
        if (!$game) {
            echo json_encode(['success' => false, 'error' => 'Missing game']);
            exit();
        }
        
        $file = getSpectateFile($currentUser, $game);
        
        // Read existing to preserve startedAt
        $existing = file_exists($file) ? json_decode(file_get_contents($file), true) : null;
        
        $data = [
            'player' => $currentUser,
            'playerName' => $user['displayName'],
            'avatar' => $user['avatar'] ?? '😎',
            'game' => $game,
            'state' => $state,
            'startedAt' => $existing['startedAt'] ?? date('c'),
            'timestamp' => date('c'),
            'spectators' => $existing['spectators'] ?? 0
        ];
        
        file_put_contents($file, json_encode($data));
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    if ($action === 'stop') {
        $game = $input['game'] ?? '';
        
        if ($game) {
            $file = getSpectateFile($currentUser, $game);
            if (file_exists($file)) {
                @unlink($file);
            }
        } else {
            // Stop all games for this user
            $files = glob($spectateDir . $currentUser . '_*.json');
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
