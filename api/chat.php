<?php
/**
 * Chat System API (Polling-based)
 * 
 * GET:
 *   ?action=global&since=timestamp - Get global chat messages
 *   ?action=dm&with=username&since=timestamp - Get DMs with user
 *   ?action=unread - Get unread message counts
 *   ?action=conversations - Get list of DM conversations
 * 
 * POST:
 *   { action: "send-global", message: "..." }
 *   { action: "send-dm", to: "username", message: "..." }
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
$globalChatFile = $dataDir . '/chat-global.json';
$dmDir = $dataDir . '/chat-dms';
$usersFile = $dataDir . '/users.json';

// Rate limiting
$rateLimitFile = $dataDir . '/chat-ratelimit.json';

// Initialize files
if (!file_exists($globalChatFile)) {
    file_put_contents($globalChatFile, json_encode(['messages' => []], JSON_PRETTY_PRINT));
    chmod($globalChatFile, 0666);
}
if (!is_dir($dmDir)) {
    mkdir($dmDir, 0777, true);
}

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function writeUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

function readGlobalChat() {
    global $globalChatFile;
    return json_decode(file_get_contents($globalChatFile), true) ?: ['messages' => []];
}

function writeGlobalChat($data) {
    global $globalChatFile;
    file_put_contents($globalChatFile, json_encode($data, JSON_PRETTY_PRINT));
}

function getDMFile($user1, $user2) {
    global $dmDir;
    $users = [$user1, $user2];
    sort($users);
    return $dmDir . '/' . implode('_', $users) . '.json';
}

function readDM($user1, $user2) {
    $file = getDMFile($user1, $user2);
    if (!file_exists($file)) {
        return ['messages' => [], 'lastRead' => []];
    }
    return json_decode(file_get_contents($file), true) ?: ['messages' => [], 'lastRead' => []];
}

function writeDM($user1, $user2, $data) {
    $file = getDMFile($user1, $user2);
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    chmod($file, 0666);
}

function sanitizeMessage($msg) {
    $msg = strip_tags(trim($msg));
    $msg = preg_replace('/\s+/', ' ', $msg);
    return mb_substr($msg, 0, 500);
}

function generateId() {
    return bin2hex(random_bytes(8));
}

function checkRateLimit($username) {
    global $rateLimitFile;
    $limits = [];
    if (file_exists($rateLimitFile)) {
        $limits = json_decode(file_get_contents($rateLimitFile), true) ?: [];
    }
    
    $now = time();
    $lastMessage = $limits[$username] ?? 0;
    
    if ($now - $lastMessage < 2) {
        return false; // Rate limited
    }
    
    $limits[$username] = $now;
    
    // Clean old entries
    foreach ($limits as $user => $time) {
        if ($now - $time > 60) unset($limits[$user]);
    }
    
    file_put_contents($rateLimitFile, json_encode($limits));
    return true;
}

// Check if logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$currentUser = $_SESSION['user'];
$usersData = readUsers();

if (!isset($usersData['users'][$currentUser])) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit();
}

$userData = $usersData['users'][$currentUser];

// Update last activity
$usersData['users'][$currentUser]['lastActivity'] = date('c');
writeUsers($usersData);

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'global';
    
    if ($action === 'global') {
        $since = $_GET['since'] ?? '1970-01-01T00:00:00Z';
        $chat = readGlobalChat();
        
        $messages = array_filter($chat['messages'], function($msg) use ($since) {
            return $msg['timestamp'] > $since;
        });
        
        // Return last 50 messages max
        $messages = array_slice(array_values($messages), -50);
        
        echo json_encode([
            'success' => true,
            'messages' => $messages,
            'serverTime' => date('c')
        ]);
        exit();
    }
    
    if ($action === 'dm') {
        $with = strtolower(trim($_GET['with'] ?? ''));
        $since = $_GET['since'] ?? '1970-01-01T00:00:00Z';
        
        if (empty($with)) {
            echo json_encode(['success' => false, 'error' => 'Username required']);
            exit();
        }
        
        // Check if friends
        if (!in_array($with, $userData['friends'] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'Can only message friends']);
            exit();
        }
        
        $dm = readDM($currentUser, $with);
        
        // Mark as read
        $dm['lastRead'][$currentUser] = date('c');
        writeDM($currentUser, $with, $dm);
        
        $messages = array_filter($dm['messages'], function($msg) use ($since) {
            return $msg['timestamp'] > $since;
        });
        
        echo json_encode([
            'success' => true,
            'messages' => array_values($messages),
            'serverTime' => date('c')
        ]);
        exit();
    }
    
    if ($action === 'conversations') {
        global $dmDir;
        $conversations = [];
        
        foreach ($userData['friends'] ?? [] as $friend) {
            $dm = readDM($currentUser, $friend);
            $lastMessage = end($dm['messages']);
            $lastRead = $dm['lastRead'][$currentUser] ?? '1970-01-01T00:00:00Z';
            
            $unreadCount = 0;
            foreach ($dm['messages'] as $msg) {
                if ($msg['from'] !== $currentUser && $msg['timestamp'] > $lastRead) {
                    $unreadCount++;
                }
            }
            
            if ($lastMessage || $unreadCount > 0) {
                $friendData = $usersData['users'][$friend] ?? [];
                $conversations[] = [
                    'username' => $friend,
                    'displayName' => $friendData['displayName'] ?? $friend,
                    'avatar' => $friendData['avatar'] ?? '😎',
                    'lastMessage' => $lastMessage ? [
                        'text' => mb_substr($lastMessage['message'], 0, 50),
                        'timestamp' => $lastMessage['timestamp'],
                        'fromMe' => $lastMessage['from'] === $currentUser
                    ] : null,
                    'unreadCount' => $unreadCount
                ];
            }
        }
        
        // Sort by last message time
        usort($conversations, function($a, $b) {
            $aTime = $a['lastMessage']['timestamp'] ?? '1970-01-01';
            $bTime = $b['lastMessage']['timestamp'] ?? '1970-01-01';
            return strcmp($bTime, $aTime);
        });
        
        echo json_encode(['success' => true, 'conversations' => $conversations]);
        exit();
    }
    
    if ($action === 'unread') {
        $totalUnread = 0;
        
        foreach ($userData['friends'] ?? [] as $friend) {
            $dm = readDM($currentUser, $friend);
            $lastRead = $dm['lastRead'][$currentUser] ?? '1970-01-01T00:00:00Z';
            
            foreach ($dm['messages'] as $msg) {
                if ($msg['from'] !== $currentUser && $msg['timestamp'] > $lastRead) {
                    $totalUnread++;
                }
            }
        }
        
        echo json_encode(['success' => true, 'unreadCount' => $totalUnread]);
        exit();
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $message = sanitizeMessage($input['message'] ?? '');
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message required']);
        exit();
    }
    
    if (!checkRateLimit($currentUser)) {
        echo json_encode(['success' => false, 'error' => 'Slow down! Wait 2 seconds between messages.']);
        exit();
    }
    
    if ($action === 'send-global') {
        $chat = readGlobalChat();
        
        $newMessage = [
            'id' => generateId(),
            'from' => $currentUser,
            'fromName' => $userData['displayName'] ?? $currentUser,
            'avatar' => $userData['avatar'] ?? '😎',
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        $chat['messages'][] = $newMessage;
        
        // Keep only last 200 messages
        if (count($chat['messages']) > 200) {
            $chat['messages'] = array_slice($chat['messages'], -200);
        }
        
        writeGlobalChat($chat);
        
        // Update stats
        $usersData['users'][$currentUser]['stats']['messagesSent'] = 
            ($usersData['users'][$currentUser]['stats']['messagesSent'] ?? 0) + 1;
        writeUsers($usersData);
        
        echo json_encode(['success' => true, 'message' => $newMessage]);
        exit();
    }
    
    if ($action === 'send-dm') {
        $to = strtolower(trim($input['to'] ?? ''));
        
        if (empty($to)) {
            echo json_encode(['success' => false, 'error' => 'Recipient required']);
            exit();
        }
        
        if (!in_array($to, $userData['friends'] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'Can only message friends']);
            exit();
        }
        
        $dm = readDM($currentUser, $to);
        
        $newMessage = [
            'id' => generateId(),
            'from' => $currentUser,
            'fromName' => $userData['displayName'] ?? $currentUser,
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        $dm['messages'][] = $newMessage;
        
        // Keep only last 500 messages
        if (count($dm['messages']) > 500) {
            $dm['messages'] = array_slice($dm['messages'], -500);
        }
        
        // Mark as read for sender
        $dm['lastRead'][$currentUser] = date('c');
        
        writeDM($currentUser, $to, $dm);
        
        // Update stats
        $usersData['users'][$currentUser]['stats']['messagesSent'] = 
            ($usersData['users'][$currentUser]['stats']['messagesSent'] ?? 0) + 1;
        writeUsers($usersData);
        
        echo json_encode(['success' => true, 'message' => $newMessage]);
        exit();
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>
