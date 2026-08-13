<?php
/**
 * Chat System API (Polling-based)
 *
 * GET:
 *   ?action=global&since=timestamp - Get global chat messages
 *   ?action=dm&with=username&since=timestamp - Get DMs with user
 *   ?action=unread - Get unread message counts
 *   ?action=conversations - Get list of DM conversations
 *   ?action=typing&channel=global|dm:username - Get who's typing
 *   ?action=online - Get online users for @mention autocomplete
 *
 * POST:
 *   { action: "send-global", message: "...", replyTo?: messageId }
 *   { action: "send-dm", to: "username", message: "...", replyTo?: messageId }
 *   { action: "react", messageId: "...", emoji: "...", channel: "global|dm:username" }
 *   { action: "typing", channel: "global|dm:username" }
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/_store.php';

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
$typingFile = $dataDir . '/chat-typing.json';

// Allowed reaction emojis
$ALLOWED_REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🔥', '💀', '👏'];

// Initialize files
if (!file_exists($globalChatFile)) {
    store_hold_write($globalChatFile, ['messages' => []]);
    chmod($globalChatFile, 0666);
}
if (!is_dir($dmDir)) {
    mkdir($dmDir, 0777, true);
}

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return store_hold_read($usersFile, ['users' => []]);
}

function writeUsers($data) {
    global $usersFile;
    store_hold_write($usersFile, $data);
}

function readGlobalChat() {
    global $globalChatFile;
    return store_hold_read($globalChatFile, ['messages' => []]);
}

function writeGlobalChat($data) {
    global $globalChatFile;
    store_hold_write($globalChatFile, $data);
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
    return store_hold_read($file, ['messages' => [], 'lastRead' => []]);
}

function writeDM($user1, $user2, $data) {
    $file = getDMFile($user1, $user2);
    store_hold_write($file, $data);
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
        $limits = store_hold_read($rateLimitFile, []);
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

    store_hold_write($rateLimitFile, $limits);
    return true;
}

function readTyping() {
    global $typingFile;
    if (!file_exists($typingFile)) return [];
    return store_hold_read($typingFile, []);
}

function writeTyping($data) {
    global $typingFile;
    store_hold_write($typingFile, $data);
}

function updateTyping($username, $displayName, $avatar, $channel) {
    $typing = readTyping();
    $now = time();

    // Clean expired (older than 5 seconds)
    foreach ($typing as $chan => $users) {
        foreach ($users as $user => $info) {
            if ($now - $info['time'] > 5) {
                unset($typing[$chan][$user]);
            }
        }
        if (empty($typing[$chan])) unset($typing[$chan]);
    }

    // Add current user typing
    if (!isset($typing[$channel])) $typing[$channel] = [];
    $typing[$channel][$username] = [
        'displayName' => $displayName,
        'avatar' => $avatar,
        'time' => $now
    ];

    writeTyping($typing);
}

function getTypingUsers($channel, $excludeUser) {
    $typing = readTyping();
    $now = time();
    $users = [];

    if (isset($typing[$channel])) {
        foreach ($typing[$channel] as $user => $info) {
            if ($user !== $excludeUser && $now - $info['time'] <= 5) {
                $users[] = [
                    'username' => $user,
                    'displayName' => $info['displayName'],
                    'avatar' => $info['avatar']
                ];
            }
        }
    }

    return $users;
}

function clearTyping($username, $channel) {
    $typing = readTyping();
    if (isset($typing[$channel][$username])) {
        unset($typing[$channel][$username]);
        if (empty($typing[$channel])) unset($typing[$channel]);
        writeTyping($typing);
    }
}

function findMessageInGlobal($messageId) {
    $chat = readGlobalChat();
    foreach ($chat['messages'] as &$msg) {
        if ($msg['id'] === $messageId) {
            return $msg;
        }
    }
    return null;
}

function addReactionToMessage(&$messages, $messageId, $emoji, $username) {
    foreach ($messages as &$msg) {
        if ($msg['id'] === $messageId) {
            if (!isset($msg['reactions'])) $msg['reactions'] = [];
            if (!isset($msg['reactions'][$emoji])) $msg['reactions'][$emoji] = [];

            // Toggle reaction
            $key = array_search($username, $msg['reactions'][$emoji]);
            if ($key !== false) {
                array_splice($msg['reactions'][$emoji], $key, 1);
                if (empty($msg['reactions'][$emoji])) {
                    unset($msg['reactions'][$emoji]);
                }
            } else {
                $msg['reactions'][$emoji][] = $username;
            }
            return true;
        }
    }
    return false;
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

    if ($action === 'typing') {
        $channel = $_GET['channel'] ?? 'global';
        $typingUsers = getTypingUsers($channel, $currentUser);
        echo json_encode(['success' => true, 'typing' => $typingUsers]);
        exit();
    }

    if ($action === 'online') {
        // Get recently active users for @mention autocomplete
        $onlineUsers = [];
        $now = time();

        foreach ($usersData['users'] as $username => $user) {
            if ($username === $currentUser) continue;

            $lastActivity = strtotime($user['lastActivity'] ?? '1970-01-01');
            if ($now - $lastActivity < 300) { // Active in last 5 minutes
                $onlineUsers[] = [
                    'username' => $username,
                    'displayName' => $user['displayName'] ?? $username,
                    'avatar' => $user['avatar'] ?? '😎'
                ];
            }
        }

        echo json_encode(['success' => true, 'users' => $onlineUsers]);
        exit();
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    // Handle typing indicator (no rate limit needed)
    if ($action === 'typing') {
        $channel = $input['channel'] ?? 'global';
        updateTyping($currentUser, $userData['displayName'] ?? $currentUser, $userData['avatar'] ?? '😎', $channel);
        echo json_encode(['success' => true]);
        exit();
    }

    // Handle reactions (no rate limit needed)
    if ($action === 'react') {
        global $ALLOWED_REACTIONS;
        $messageId = $input['messageId'] ?? '';
        $emoji = $input['emoji'] ?? '';
        $channel = $input['channel'] ?? 'global';

        if (empty($messageId) || empty($emoji)) {
            echo json_encode(['success' => false, 'error' => 'Message ID and emoji required']);
            exit();
        }

        if (!in_array($emoji, $ALLOWED_REACTIONS)) {
            echo json_encode(['success' => false, 'error' => 'Invalid reaction']);
            exit();
        }

        if ($channel === 'global') {
            $chat = readGlobalChat();
            if (addReactionToMessage($chat['messages'], $messageId, $emoji, $currentUser)) {
                writeGlobalChat($chat);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Message not found']);
            }
        } else if (strpos($channel, 'dm:') === 0) {
            $with = substr($channel, 3);
            $dm = readDM($currentUser, $with);
            if (addReactionToMessage($dm['messages'], $messageId, $emoji, $currentUser)) {
                writeDM($currentUser, $with, $dm);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Message not found']);
            }
        }
        exit();
    }

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

        // Handle reply-to
        $replyTo = null;
        if (!empty($input['replyTo'])) {
            foreach ($chat['messages'] as $msg) {
                if ($msg['id'] === $input['replyTo']) {
                    $replyTo = [
                        'id' => $msg['id'],
                        'from' => $msg['from'],
                        'fromName' => $msg['fromName'],
                        'message' => mb_substr($msg['message'], 0, 100)
                    ];
                    break;
                }
            }
        }

        $newMessage = [
            'id' => generateId(),
            'from' => $currentUser,
            'fromName' => $userData['displayName'] ?? $currentUser,
            'avatar' => $userData['avatar'] ?? '😎',
            'message' => $message,
            'timestamp' => date('c')
        ];

        if ($replyTo) {
            $newMessage['replyTo'] = $replyTo;
        }

        $chat['messages'][] = $newMessage;

        // Keep only last 200 messages
        if (count($chat['messages']) > 200) {
            $chat['messages'] = array_slice($chat['messages'], -200);
        }

        writeGlobalChat($chat);

        // Clear typing indicator
        clearTyping($currentUser, 'global');

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

        // Handle reply-to
        $replyTo = null;
        if (!empty($input['replyTo'])) {
            foreach ($dm['messages'] as $msg) {
                if ($msg['id'] === $input['replyTo']) {
                    $replyTo = [
                        'id' => $msg['id'],
                        'from' => $msg['from'],
                        'fromName' => $msg['fromName'],
                        'message' => mb_substr($msg['message'], 0, 100)
                    ];
                    break;
                }
            }
        }

        $newMessage = [
            'id' => generateId(),
            'from' => $currentUser,
            'fromName' => $userData['displayName'] ?? $currentUser,
            'message' => $message,
            'timestamp' => date('c')
        ];

        if ($replyTo) {
            $newMessage['replyTo'] = $replyTo;
        }

        $dm['messages'][] = $newMessage;

        // Keep only last 500 messages
        if (count($dm['messages']) > 500) {
            $dm['messages'] = array_slice($dm['messages'], -500);
        }

        // Mark as read for sender
        $dm['lastRead'][$currentUser] = date('c');

        writeDM($currentUser, $to, $dm);

        // Clear typing indicator
        clearTyping($currentUser, 'dm:' . $to);

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
