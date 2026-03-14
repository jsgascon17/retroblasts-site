<?php
/**
 * Friends System API
 * 
 * GET:
 *   ?action=list - Get friends list with online status
 *   ?action=requests - Get pending friend requests
 *   ?action=search&q=name - Search for users
 * 
 * POST:
 *   { action: "send-request", username: "..." }
 *   { action: "accept", username: "..." }
 *   { action: "decline", username: "..." }
 *   { action: "remove", username: "..." }
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

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function writeUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

function sanitize($input, $maxLength = 20) {
    $clean = strip_tags(trim($input));
    $clean = preg_replace('/[^\w\s\-]/', '', $clean);
    return strtolower(substr($clean, 0, $maxLength));
}

function calculateLevel($xp) {
    return floor(sqrt($xp / 10)) + 1;
}

function isOnline($user) {
    if (!isset($user['lastActivity'])) return false;
    return (time() - strtotime($user['lastActivity'])) < 300; // 5 minutes
}

function getUserPublicData($user, $username) {
    return [
        'username' => $username,
        'displayName' => $user['displayName'] ?? $username,
        'avatar' => $user['avatar'] ?? '😎',
        'level' => calculateLevel($user['xp'] ?? 0),
        'isOnline' => isOnline($user),
        'lastActivity' => $user['lastActivity'] ?? null
    ];
}

// Check if logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$currentUser = $_SESSION['user'];

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    $data = readUsers();
    
    if (!isset($data['users'][$currentUser])) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    $user = $data['users'][$currentUser];
    
    if ($action === 'list') {
        $friends = [];
        foreach ($user['friends'] ?? [] as $friendUsername) {
            if (isset($data['users'][$friendUsername])) {
                $friend = $data['users'][$friendUsername];
                $friends[] = getUserPublicData($friend, $friendUsername);
            }
        }
        
        // Sort by online status, then by level
        usort($friends, function($a, $b) {
            if ($a['isOnline'] !== $b['isOnline']) {
                return $b['isOnline'] - $a['isOnline'];
            }
            return $b['level'] - $a['level'];
        });
        
        echo json_encode(['success' => true, 'friends' => $friends]);
        exit();
    }
    
    if ($action === 'requests') {
        $incoming = [];
        $outgoing = [];
        
        foreach ($user['friendRequests']['incoming'] ?? [] as $request) {
            $fromUsername = $request['from'];
            if (isset($data['users'][$fromUsername])) {
                $fromUser = $data['users'][$fromUsername];
                $incoming[] = [
                    'username' => $fromUsername,
                    'displayName' => $fromUser['displayName'] ?? $fromUsername,
                    'avatar' => $fromUser['avatar'] ?? '😎',
                    'level' => calculateLevel($fromUser['xp'] ?? 0),
                    'sentAt' => $request['sentAt']
                ];
            }
        }
        
        foreach ($user['friendRequests']['outgoing'] ?? [] as $request) {
            $toUsername = $request['to'];
            if (isset($data['users'][$toUsername])) {
                $toUser = $data['users'][$toUsername];
                $outgoing[] = [
                    'username' => $toUsername,
                    'displayName' => $toUser['displayName'] ?? $toUsername,
                    'avatar' => $toUser['avatar'] ?? '😎',
                    'level' => calculateLevel($toUser['xp'] ?? 0),
                    'sentAt' => $request['sentAt']
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'incoming' => $incoming,
            'outgoing' => $outgoing
        ]);
        exit();
    }
    
    if ($action === 'search') {
        $query = sanitize($_GET['q'] ?? '');
        if (strlen($query) < 2) {
            echo json_encode(['success' => false, 'error' => 'Search query too short']);
            exit();
        }
        
        $results = [];
        foreach ($data['users'] as $username => $userData) {
            if ($username === $currentUser) continue;
            if (in_array($username, $user['friends'] ?? [])) continue;
            
            if (strpos($username, $query) !== false || 
                strpos(strtolower($userData['displayName'] ?? ''), $query) !== false) {
                
                $isPending = false;
                foreach ($user['friendRequests']['outgoing'] ?? [] as $req) {
                    if ($req['to'] === $username) {
                        $isPending = true;
                        break;
                    }
                }
                
                $results[] = [
                    'username' => $username,
                    'displayName' => $userData['displayName'] ?? $username,
                    'avatar' => $userData['avatar'] ?? '😎',
                    'level' => calculateLevel($userData['xp'] ?? 0),
                    'isPending' => $isPending
                ];
            }
        }
        
        echo json_encode(['success' => true, 'users' => array_slice($results, 0, 20)]);
        exit();
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $targetUsername = sanitize($input['username'] ?? '');
    
    if (empty($targetUsername)) {
        echo json_encode(['success' => false, 'error' => 'Username required']);
        exit();
    }
    
    $data = readUsers();
    
    if (!isset($data['users'][$targetUsername])) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    if ($targetUsername === $currentUser) {
        echo json_encode(['success' => false, 'error' => 'Cannot add yourself']);
        exit();
    }
    
    $user = &$data['users'][$currentUser];
    $target = &$data['users'][$targetUsername];
    
    // Initialize arrays if needed
    if (!isset($user['friends'])) $user['friends'] = [];
    if (!isset($user['friendRequests'])) $user['friendRequests'] = ['incoming' => [], 'outgoing' => []];
    if (!isset($target['friends'])) $target['friends'] = [];
    if (!isset($target['friendRequests'])) $target['friendRequests'] = ['incoming' => [], 'outgoing' => []];
    
    if ($action === 'send-request') {
        // Check if already friends
        if (in_array($targetUsername, $user['friends'])) {
            echo json_encode(['success' => false, 'error' => 'Already friends']);
            exit();
        }
        
        // Check if request already sent
        foreach ($user['friendRequests']['outgoing'] as $req) {
            if ($req['to'] === $targetUsername) {
                echo json_encode(['success' => false, 'error' => 'Request already sent']);
                exit();
            }
        }
        
        // Check if they already sent us a request (auto-accept)
        foreach ($user['friendRequests']['incoming'] as $i => $req) {
            if ($req['from'] === $targetUsername) {
                // Auto-accept
                $user['friends'][] = $targetUsername;
                $target['friends'][] = $currentUser;
                
                // Remove requests
                array_splice($user['friendRequests']['incoming'], $i, 1);
                foreach ($target['friendRequests']['outgoing'] as $j => $outReq) {
                    if ($outReq['to'] === $currentUser) {
                        array_splice($target['friendRequests']['outgoing'], $j, 1);
                        break;
                    }
                }
                
                writeUsers($data);
                echo json_encode(['success' => true, 'message' => 'You are now friends!', 'autoAccepted' => true]);
                exit();
            }
        }
        
        // Send request
        $user['friendRequests']['outgoing'][] = ['to' => $targetUsername, 'sentAt' => date('c')];
        $target['friendRequests']['incoming'][] = ['from' => $currentUser, 'sentAt' => date('c')];
        
        writeUsers($data);
        echo json_encode(['success' => true, 'message' => 'Friend request sent!']);
        exit();
    }
    
    if ($action === 'accept') {
        // Find and remove incoming request
        $found = false;
        foreach ($user['friendRequests']['incoming'] as $i => $req) {
            if ($req['from'] === $targetUsername) {
                array_splice($user['friendRequests']['incoming'], $i, 1);
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            echo json_encode(['success' => false, 'error' => 'No request from this user']);
            exit();
        }
        
        // Remove from their outgoing
        foreach ($target['friendRequests']['outgoing'] as $i => $req) {
            if ($req['to'] === $currentUser) {
                array_splice($target['friendRequests']['outgoing'], $i, 1);
                break;
            }
        }
        
        // Add as friends
        if (!in_array($targetUsername, $user['friends'])) {
            $user['friends'][] = $targetUsername;
        }
        if (!in_array($currentUser, $target['friends'])) {
            $target['friends'][] = $currentUser;
        }
        
        writeUsers($data);
        echo json_encode(['success' => true, 'message' => 'Friend request accepted!']);
        exit();
    }
    
    if ($action === 'decline') {
        // Remove incoming request
        foreach ($user['friendRequests']['incoming'] as $i => $req) {
            if ($req['from'] === $targetUsername) {
                array_splice($user['friendRequests']['incoming'], $i, 1);
                break;
            }
        }
        
        // Remove from their outgoing
        foreach ($target['friendRequests']['outgoing'] as $i => $req) {
            if ($req['to'] === $currentUser) {
                array_splice($target['friendRequests']['outgoing'], $i, 1);
                break;
            }
        }
        
        writeUsers($data);
        echo json_encode(['success' => true, 'message' => 'Request declined']);
        exit();
    }
    
    if ($action === 'remove') {
        // Remove from both friend lists
        $user['friends'] = array_values(array_filter($user['friends'], fn($f) => $f !== $targetUsername));
        $target['friends'] = array_values(array_filter($target['friends'], fn($f) => $f !== $currentUser));
        
        writeUsers($data);
        echo json_encode(['success' => true, 'message' => 'Friend removed']);
        exit();
    }
    
    if ($action === 'cancel') {
        // Cancel outgoing request
        foreach ($user['friendRequests']['outgoing'] as $i => $req) {
            if ($req['to'] === $targetUsername) {
                array_splice($user['friendRequests']['outgoing'], $i, 1);
                break;
            }
        }
        
        foreach ($target['friendRequests']['incoming'] as $i => $req) {
            if ($req['from'] === $currentUser) {
                array_splice($target['friendRequests']['incoming'], $i, 1);
                break;
            }
        }
        
        writeUsers($data);
        echo json_encode(['success' => true, 'message' => 'Request cancelled']);
        exit();
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>
