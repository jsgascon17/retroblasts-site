<?php
/**
 * Replay System API
 * 
 * GET:
 *   ?action=list                     - List replays (optionally filter by game/user)
 *   ?action=list&game=snake          - List replays for a specific game
 *   ?action=list&user=username       - List replays for a specific user
 *   ?action=my                       - List my replays
 *   ?action=get&id=replay_id         - Get specific replay
 * 
 * POST:
 *   { "action": "save", "game": "snake", "score": 150, "duration": 120, "frames": [...] }
 *   { "action": "delete", "id": "replay_id" }
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

$replaysDir = __DIR__ . '/../data/replays/';
$usersFile = __DIR__ . '/../data/users.json';
$indexFile = __DIR__ . '/../data/replays/index.json';

$MAX_REPLAYS_PER_USER_PER_GAME = 3;
$MAX_REPLAY_SIZE = 500000; // 500KB
$REPLAY_RETENTION_DAYS = 30;

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function readIndex() {
    global $indexFile;
    if (!file_exists($indexFile)) return ['replays' => []];
    return json_decode(file_get_contents($indexFile), true) ?: ['replays' => []];
}

function writeIndex($data) {
    global $indexFile;
    file_put_contents($indexFile, json_encode($data, JSON_PRETTY_PRINT));
}

function getReplayFile($id) {
    global $replaysDir;
    return $replaysDir . $id . '.json';
}

function cleanOldReplays() {
    global $indexFile, $replaysDir, $REPLAY_RETENTION_DAYS;
    
    $index = readIndex();
    $cutoff = time() - ($REPLAY_RETENTION_DAYS * 86400);
    $cleaned = false;
    
    $index['replays'] = array_filter($index['replays'], function($r) use ($replaysDir, $cutoff, &$cleaned) {
        if (strtotime($r['createdAt']) < $cutoff) {
            $file = $replaysDir . $r['id'] . '.json';
            if (file_exists($file)) @unlink($file);
            $cleaned = true;
            return false;
        }
        return true;
    });
    
    if ($cleaned) {
        $index['replays'] = array_values($index['replays']);
        writeIndex($index);
    }
}

// Handle GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    cleanOldReplays();
    $index = readIndex();
    
    if ($action === 'list') {
        $game = $_GET['game'] ?? null;
        $user = isset($_GET['user']) ? strtolower($_GET['user']) : null;
        
        $replays = $index['replays'];
        
        if ($game) {
            $replays = array_filter($replays, function($r) use ($game) {
                return $r['game'] === $game;
            });
        }
        
        if ($user) {
            $replays = array_filter($replays, function($r) use ($user) {
                return $r['player'] === $user;
            });
        }
        
        // Sort by score descending
        usort($replays, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Limit to 50
        $replays = array_slice($replays, 0, 50);
        
        echo json_encode(['success' => true, 'replays' => array_values($replays)]);
        exit();
    }
    
    if ($action === 'my') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit();
        }
        
        $currentUser = $_SESSION['user'];
        $replays = array_filter($index['replays'], function($r) use ($currentUser) {
            return $r['player'] === $currentUser;
        });
        
        // Sort by date descending
        usort($replays, function($a, $b) {
            return strtotime($b['createdAt']) - strtotime($a['createdAt']);
        });
        
        echo json_encode(['success' => true, 'replays' => array_values($replays)]);
        exit();
    }
    
    if ($action === 'get') {
        $id = $_GET['id'] ?? '';
        
        if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
            echo json_encode(['success' => false, 'error' => 'Invalid replay ID']);
            exit();
        }
        
        $file = getReplayFile($id);
        if (!file_exists($file)) {
            echo json_encode(['success' => false, 'error' => 'Replay not found']);
            exit();
        }
        
        $replay = json_decode(file_get_contents($file), true);
        echo json_encode(['success' => true, 'replay' => $replay]);
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
    
    if ($action === 'save') {
        global $MAX_REPLAYS_PER_USER_PER_GAME, $MAX_REPLAY_SIZE;
        
        $game = $input['game'] ?? '';
        $score = intval($input['score'] ?? 0);
        $duration = intval($input['duration'] ?? 0);
        $frames = $input['frames'] ?? [];
        
        if (!$game) {
            echo json_encode(['success' => false, 'error' => 'Missing game']);
            exit();
        }
        
        if (empty($frames)) {
            echo json_encode(['success' => false, 'error' => 'No frames to save']);
            exit();
        }
        
        // Check size
        $replayData = json_encode($frames);
        if (strlen($replayData) > $MAX_REPLAY_SIZE) {
            echo json_encode(['success' => false, 'error' => 'Replay too large']);
            exit();
        }
        
        $index = readIndex();
        
        // Check how many replays user has for this game
        $userGameReplays = array_filter($index['replays'], function($r) use ($currentUser, $game) {
            return $r['player'] === $currentUser && $r['game'] === $game;
        });
        
        // If at limit, remove oldest
        if (count($userGameReplays) >= $MAX_REPLAYS_PER_USER_PER_GAME) {
            usort($userGameReplays, function($a, $b) {
                return strtotime($a['createdAt']) - strtotime($b['createdAt']);
            });
            
            $toRemove = array_slice($userGameReplays, 0, count($userGameReplays) - $MAX_REPLAYS_PER_USER_PER_GAME + 1);
            foreach ($toRemove as $old) {
                $oldFile = getReplayFile($old['id']);
                if (file_exists($oldFile)) @unlink($oldFile);
                $index['replays'] = array_filter($index['replays'], function($r) use ($old) {
                    return $r['id'] !== $old['id'];
                });
            }
            $index['replays'] = array_values($index['replays']);
        }
        
        // Create new replay
        $replayId = bin2hex(random_bytes(8));
        
        $replayMeta = [
            'id' => $replayId,
            'game' => $game,
            'player' => $currentUser,
            'playerName' => $user['displayName'],
            'avatar' => $user['avatar'] ?? '😎',
            'score' => $score,
            'duration' => $duration,
            'frameCount' => count($frames),
            'createdAt' => date('c')
        ];
        
        $replayFull = array_merge($replayMeta, ['frames' => $frames]);
        
        // Save full replay
        file_put_contents(getReplayFile($replayId), json_encode($replayFull));
        
        // Update index
        $index['replays'][] = $replayMeta;
        writeIndex($index);
        
        echo json_encode([
            'success' => true,
            'message' => 'Replay saved!',
            'replay' => $replayMeta
        ]);
        exit();
    }
    
    if ($action === 'delete') {
        $id = $input['id'] ?? '';
        
        if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
            echo json_encode(['success' => false, 'error' => 'Invalid replay ID']);
            exit();
        }
        
        $index = readIndex();
        
        // Find and verify ownership
        $found = null;
        foreach ($index['replays'] as $r) {
            if ($r['id'] === $id) {
                $found = $r;
                break;
            }
        }
        
        if (!$found) {
            echo json_encode(['success' => false, 'error' => 'Replay not found']);
            exit();
        }
        
        if ($found['player'] !== $currentUser) {
            echo json_encode(['success' => false, 'error' => 'Not your replay']);
            exit();
        }
        
        // Delete
        $file = getReplayFile($id);
        if (file_exists($file)) @unlink($file);
        
        $index['replays'] = array_filter($index['replays'], function($r) use ($id) {
            return $r['id'] !== $id;
        });
        $index['replays'] = array_values($index['replays']);
        writeIndex($index);
        
        echo json_encode(['success' => true, 'message' => 'Replay deleted']);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
