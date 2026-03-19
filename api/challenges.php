<?php
header('Content-Type: application/json');
session_start();

$dataFile = __DIR__ . '/../data/challenges.json';

function loadChallenges() {
    global $dataFile;
    if (!file_exists($dataFile)) return [];
    $data = json_decode(file_get_contents($dataFile), true);
    return $data ?: [];
}

function saveChallenges($challenges) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($challenges, JSON_PRETTY_PRINT));
}

function loadUsers() {
    $userFile = __DIR__ . '/../data/users.json';
    if (!file_exists($userFile)) return [];
    $data = json_decode(file_get_contents($userFile), true);
    return $data["users"] ?? [];
}

// Check auth
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$username = $_SESSION['user'];
$challenges = loadChallenges();
$users = loadUsers();

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'list') {
        $userChallenges = array_filter($challenges, function($c) use ($username) {
            return $c['from'] === $username || $c['to'] === $username;
        });
        
        foreach ($userChallenges as &$c) {
            $fromUser = $users[$c['from']] ?? null;
            $toUser = $users[$c['to']] ?? null;
            $c['fromDisplay'] = $fromUser ? $fromUser['displayName'] : $c['from'];
            $c['toDisplay'] = $toUser ? $toUser['displayName'] : $c['to'];
        }
        
        usort($userChallenges, function($a, $b) {
            return strtotime($b['createdAt']) - strtotime($a['createdAt']);
        });
        
        echo json_encode(['success' => true, 'challenges' => array_values($userChallenges)]);
        exit;
    }
    
    // Get single challenge by ID
    if ($action === 'get') {
        $id = $_GET['id'] ?? '';
        foreach ($challenges as $c) {
            if ($c['id'] === $id) {
                $fromUser = $users[$c['from']] ?? null;
                $toUser = $users[$c['to']] ?? null;
                $c['fromDisplay'] = $fromUser ? $fromUser['displayName'] : $c['from'];
                $c['toDisplay'] = $toUser ? $toUser['displayName'] : $c['to'];
                echo json_encode(['success' => true, 'challenge' => $c]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => 'Challenge not found']);
        exit;
    }
    
    // Check for active accepted challenges (for polling)
    if ($action === 'active') {
        foreach ($challenges as $c) {
            if ($c['status'] === 'accepted' && 
                ($c['from'] === $username || $c['to'] === $username)) {
                $fromUser = $users[$c['from']] ?? null;
                $toUser = $users[$c['to']] ?? null;
                $c['fromDisplay'] = $fromUser ? $fromUser['displayName'] : $c['from'];
                $c['toDisplay'] = $toUser ? $toUser['displayName'] : $c['to'];
                echo json_encode(['success' => true, 'challenge' => $c]);
                exit;
            }
        }
        echo json_encode(['success' => true, 'challenge' => null]);
        exit;
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $to = $input['to'] ?? '';
            $game = $input['game'] ?? '';
            $wager = intval($input['wager'] ?? 0);
            
            if (!$to || !$game) {
                echo json_encode(['success' => false, 'error' => 'Missing friend or game']);
                exit;
            }
            
            if ($to === $username) {
                echo json_encode(['success' => false, 'error' => "Can't challenge yourself!"]);
                exit;
            }
            
            foreach ($challenges as $c) {
                if (($c['status'] === 'pending' || $c['status'] === 'accepted') && 
                    (($c['from'] === $username && $c['to'] === $to) ||
                     ($c['from'] === $to && $c['to'] === $username))) {
                    echo json_encode(['success' => false, 'error' => 'You already have an active challenge with this player']);
                    exit;
                }
            }
            
            $challenge = [
                'id' => uniqid('chal_'),
                'from' => $username,
                'to' => $to,
                'game' => $game,
                'wager' => max(0, min(1000, $wager)),
                'status' => 'pending',
                'createdAt' => date('c'),
                'scores' => []
            ];
            
            $challenges[] = $challenge;
            saveChallenges($challenges);
            
            echo json_encode(['success' => true, 'challenge' => $challenge]);
            break;
            
        case 'accept':
            $id = $input['id'] ?? '';
            
            foreach ($challenges as &$c) {
                if ($c['id'] === $id && $c['to'] === $username && $c['status'] === 'pending') {
                    $c['status'] = 'accepted';
                    $c['acceptedAt'] = date('c');
                    saveChallenges($challenges);
                    
                    // Return challenge details so we can redirect to game
                    $fromUser = $users[$c['from']] ?? null;
                    $toUser = $users[$c['to']] ?? null;
                    $c['fromDisplay'] = $fromUser ? $fromUser['displayName'] : $c['from'];
                    $c['toDisplay'] = $toUser ? $toUser['displayName'] : $c['to'];
                    
                    echo json_encode(['success' => true, 'challenge' => $c]);
                    exit;
                }
            }
            
            echo json_encode(['success' => false, 'error' => 'Challenge not found']);
            break;
            
        case 'decline':
            $id = $input['id'] ?? '';
            
            foreach ($challenges as &$c) {
                if ($c['id'] === $id && $c['to'] === $username && $c['status'] === 'pending') {
                    $c['status'] = 'declined';
                    saveChallenges($challenges);
                    echo json_encode(['success' => true]);
                    exit;
                }
            }
            
            echo json_encode(['success' => false, 'error' => 'Challenge not found']);
            break;
            
        case 'cancel':
            $id = $input['id'] ?? '';
            
            foreach ($challenges as &$c) {
                if ($c['id'] === $id && $c['from'] === $username && 
                    ($c['status'] === 'pending' || $c['status'] === 'accepted')) {
                    $c['status'] = 'cancelled';
                    saveChallenges($challenges);
                    echo json_encode(['success' => true]);
                    exit;
                }
            }
            
            echo json_encode(['success' => false, 'error' => 'Challenge not found']);
            break;
            
        case 'submit-score':
            $id = $input['id'] ?? '';
            $score = intval($input['score'] ?? 0);
            
            foreach ($challenges as &$c) {
                if ($c['id'] === $id && $c['status'] === 'accepted' &&
                    ($c['from'] === $username || $c['to'] === $username)) {
                    
                    $c['scores'][$username] = $score;
                    
                    // Check if both players have submitted
                    if (isset($c['scores'][$c['from']]) && isset($c['scores'][$c['to']])) {
                        $c['status'] = 'completed';
                        $c['completedAt'] = date('c');
                        
                        if ($c['scores'][$c['from']] > $c['scores'][$c['to']]) {
                            $c['winner'] = $c['from'];
                        } elseif ($c['scores'][$c['to']] > $c['scores'][$c['from']]) {
                            $c['winner'] = $c['to'];
                        } else {
                            $c['winner'] = 'tie';
                        }
                    }
                    
                    saveChallenges($challenges);
                    
                    $fromUser = $users[$c['from']] ?? null;
                    $toUser = $users[$c['to']] ?? null;
                    $c['fromDisplay'] = $fromUser ? $fromUser['displayName'] : $c['from'];
                    $c['toDisplay'] = $toUser ? $toUser['displayName'] : $c['to'];
                    
                    echo json_encode(['success' => true, 'challenge' => $c]);
                    exit;
                }
            }
            
            echo json_encode(['success' => false, 'error' => 'Challenge not found']);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
