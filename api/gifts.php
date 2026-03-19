<?php
/**
 * Gifting System API
 * 
 * GET:
 *   ?action=history    - Get gift history (sent and received)
 * 
 * POST:
 *   { "action": "send-coins", "to": "username", "amount": 100, "message": "optional" }
 *   { "action": "send-item", "to": "username", "category": "emotes", "itemId": "gg" }
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

$usersFile = __DIR__ . '/../data/users.json';
$giftsFile = __DIR__ . '/../data/gifts.json';
$rateLimitFile = __DIR__ . '/../data/gift-ratelimit.json';

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function writeUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

function readGifts() {
    global $giftsFile;
    if (!file_exists($giftsFile)) return ['gifts' => []];
    return json_decode(file_get_contents($giftsFile), true) ?: ['gifts' => []];
}

function writeGifts($data) {
    global $giftsFile;
    file_put_contents($giftsFile, json_encode($data, JSON_PRETTY_PRINT));
}

function checkRateLimit($username) {
    global $rateLimitFile;
    $data = file_exists($rateLimitFile) ? json_decode(file_get_contents($rateLimitFile), true) : [];
    $now = time();
    
    // Clean old entries
    foreach ($data as $user => $timestamp) {
        if ($timestamp < $now - 60) unset($data[$user]);
    }
    
    if (isset($data[$username]) && $data[$username] > $now - 60) {
        $remaining = 60 - ($now - $data[$username]);
        return $remaining;
    }
    
    $data[$username] = $now;
    file_put_contents($rateLimitFile, json_encode($data));
    return 0;
}

function sanitize($input, $maxLength = 256) {
    return substr(strip_tags(trim($input)), 0, $maxLength);
}

function ensureUserHasFields(&$user) {
    if (!isset($user['coins'])) $user['coins'] = 0;
    if (!isset($user['inventory'])) {
        $user['inventory'] = [
            'name_colors' => [],
            'borders' => [],
            'avatar_effects' => [],
            'emotes' => [],
            'boosters' => [],
            'social' => []
        ];
    }
    if (!isset($user['giftHistory'])) $user['giftHistory'] = [];
}

// Check login
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$currentUser = $_SESSION['user'];

// Handle GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'history';
    
    if ($action === 'history') {
        $giftsData = readGifts();
        
        // Filter gifts for current user
        $myGifts = array_filter($giftsData['gifts'], function($g) use ($currentUser) {
            return $g['from'] === $currentUser || $g['to'] === $currentUser;
        });
        
        // Sort by date descending
        usort($myGifts, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // Limit to last 50
        $myGifts = array_slice($myGifts, 0, 50);
        
        echo json_encode(['success' => true, 'gifts' => array_values($myGifts)]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    // Rate limit check
    $waitTime = checkRateLimit($currentUser);
    if ($waitTime > 0) {
        echo json_encode(['success' => false, 'error' => "Wait $waitTime seconds before sending another gift"]);
        exit();
    }
    
    $data = readUsers();
    
    if (!isset($data['users'][$currentUser])) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    $sender = &$data['users'][$currentUser];
    ensureUserHasFields($sender);
    
    $toUsername = strtolower(sanitize($input['to'] ?? '', 20));
    
    if ($toUsername === $currentUser) {
        echo json_encode(['success' => false, 'error' => 'Cannot gift to yourself']);
        exit();
    }
    
    if (!isset($data['users'][$toUsername])) {
        echo json_encode(['success' => false, 'error' => 'Recipient not found']);
        exit();
    }
    
    // Check if friends
    if (!in_array($toUsername, $sender['friends'] ?? [])) {
        echo json_encode(['success' => false, 'error' => 'You can only gift to friends']);
        exit();
    }
    
    $recipient = &$data['users'][$toUsername];
    ensureUserHasFields($recipient);
    
    if ($action === 'send-coins') {
        $amount = intval($input['amount'] ?? 0);
        $message = sanitize($input['message'] ?? '', 100);
        
        if ($amount < 10) {
            echo json_encode(['success' => false, 'error' => 'Minimum gift is 10 coins']);
            exit();
        }
        
        if ($amount > 10000) {
            echo json_encode(['success' => false, 'error' => 'Maximum gift is 10,000 coins']);
            exit();
        }
        
        if ($sender['coins'] < $amount) {
            echo json_encode(['success' => false, 'error' => 'Not enough coins']);
            exit();
        }
        
        // Transfer coins
        $sender['coins'] -= $amount;
        $recipient['coins'] += $amount;
        
        // Record gift
        $gift = [
            'id' => bin2hex(random_bytes(8)),
            'type' => 'coins',
            'from' => $currentUser,
            'fromName' => $sender['displayName'],
            'to' => $toUsername,
            'toName' => $recipient['displayName'],
            'amount' => $amount,
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        $giftsData = readGifts();
        $giftsData['gifts'][] = $gift;
        
        // Keep only last 1000 gifts
        if (count($giftsData['gifts']) > 1000) {
            $giftsData['gifts'] = array_slice($giftsData['gifts'], -1000);
        }
        
        writeGifts($giftsData);
        writeUsers($data);
        
        echo json_encode([
            'success' => true,
            'message' => "Sent $amount coins to " . $recipient['displayName'],
            'coins' => $sender['coins']
        ]);
        exit();
    }
    
    if ($action === 'send-item') {
        $category = $input['category'] ?? '';
        $itemId = $input['itemId'] ?? '';
        $message = sanitize($input['message'] ?? '', 100);
        
        // Check if sender owns the item
        if (!in_array($itemId, $sender['inventory'][$category] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'You do not own this item']);
            exit();
        }
        
        // Check if recipient already has it (except boosters)
        if ($category !== 'boosters' && in_array($itemId, $recipient['inventory'][$category] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'Recipient already owns this item']);
            exit();
        }
        
        // Transfer item
        $key = array_search($itemId, $sender['inventory'][$category]);
        array_splice($sender['inventory'][$category], $key, 1);
        
        if (!isset($recipient['inventory'][$category])) {
            $recipient['inventory'][$category] = [];
        }
        $recipient['inventory'][$category][] = $itemId;
        
        // Unequip if sender had it equipped
        $equipKey = ($category === 'borders') ? 'border' : (
                    ($category === 'avatar_effects') ? 'avatar_effect' :
                    str_replace('s', '', $category));
        if (isset($sender['equipped'][$equipKey]) && $sender['equipped'][$equipKey] === $itemId) {
            $sender['equipped'][$equipKey] = null;
        }
        
        // Record gift
        $gift = [
            'id' => bin2hex(random_bytes(8)),
            'type' => 'item',
            'from' => $currentUser,
            'fromName' => $sender['displayName'],
            'to' => $toUsername,
            'toName' => $recipient['displayName'],
            'category' => $category,
            'itemId' => $itemId,
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        $giftsData = readGifts();
        $giftsData['gifts'][] = $gift;
        writeGifts($giftsData);
        writeUsers($data);
        
        echo json_encode([
            'success' => true,
            'message' => "Sent $itemId to " . $recipient['displayName'],
            'inventory' => $sender['inventory']
        ]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
