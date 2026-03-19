<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$dataDir = __DIR__ . '/../data';
$tradesFile = $dataDir . '/trades.json';
$usersFile = $dataDir . '/users.json';
$friendsFile = $dataDir . '/friends.json';

function loadJson($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function areFriends($user1, $user2, $friends) {
    foreach ($friends as $f) {
        if ($f['status'] !== 'accepted') continue;
        if (($f['from'] === $user1 && $f['to'] === $user2) || 
            ($f['from'] === $user2 && $f['to'] === $user1)) {
            return true;
        }
    }
    return false;
}

$currentUser = $_SESSION['username'] ?? null;

if (!$currentUser) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$input = json_decode(file_get_contents('php://input'), true) ?: [];
if (isset($input['action'])) $action = $input['action'];

// List pending trades for current user
if ($action === 'list') {
    $trades = loadJson($tradesFile);
    
    $myTrades = array_filter($trades, function($t) use ($currentUser) {
        return ($t['from'] === $currentUser || $t['to'] === $currentUser) && 
               $t['status'] === 'pending';
    });
    
    echo json_encode([
        'success' => true,
        'trades' => array_values($myTrades)
    ]);
    exit;
}

// Create a new trade offer
if ($action === 'create') {
    $toUser = $input['to'] ?? '';
    $offerItems = $input['offer'] ?? []; // [{category: 'name_colors', id: 'red'}]
    $requestItems = $input['request'] ?? []; // Items you want
    $offerCoins = intval($input['offerCoins'] ?? 0);
    $requestCoins = intval($input['requestCoins'] ?? 0);
    
    if (!$toUser) {
        echo json_encode(['success' => false, 'error' => 'Missing recipient']);
        exit;
    }
    
    // Check they're friends
    $friends = loadJson($friendsFile);
    if (!areFriends($currentUser, $toUser, $friends)) {
        echo json_encode(['success' => false, 'error' => 'You can only trade with friends']);
        exit;
    }
    
    // Verify user owns offered items
    $users = loadJson($usersFile);
    $user = null;
    foreach ($users as &$u) {
        if ($u['username'] === $currentUser) {
            $user = &$u;
            break;
        }
    }
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    // Check coins
    if ($offerCoins > 0 && $user['coins'] < $offerCoins) {
        echo json_encode(['success' => false, 'error' => 'Not enough coins']);
        exit;
    }
    
    // Check items
    foreach ($offerItems as $item) {
        $cat = $item['category'] ?? '';
        $id = $item['id'] ?? '';
        if (!in_array($id, $user['inventory'][$cat] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'You don\'t own: ' . $id]);
            exit;
        }
    }
    
    $trades = loadJson($tradesFile);
    
    $trade = [
        'id' => uniqid('trade_'),
        'from' => $currentUser,
        'to' => $toUser,
        'offer' => $offerItems,
        'offerCoins' => $offerCoins,
        'request' => $requestItems,
        'requestCoins' => $requestCoins,
        'status' => 'pending',
        'created' => time()
    ];
    
    $trades[] = $trade;
    saveJson($tradesFile, $trades);
    
    echo json_encode(['success' => true, 'trade' => $trade]);
    exit;
}

// Accept a trade
if ($action === 'accept') {
    $tradeId = $input['tradeId'] ?? '';
    
    $trades = loadJson($tradesFile);
    $users = loadJson($usersFile);
    
    $tradeIndex = -1;
    foreach ($trades as $i => $t) {
        if ($t['id'] === $tradeId) {
            $tradeIndex = $i;
            break;
        }
    }
    
    if ($tradeIndex === -1) {
        echo json_encode(['success' => false, 'error' => 'Trade not found']);
        exit;
    }
    
    $trade = $trades[$tradeIndex];
    
    if ($trade['to'] !== $currentUser) {
        echo json_encode(['success' => false, 'error' => 'Not your trade']);
        exit;
    }
    
    if ($trade['status'] !== 'pending') {
        echo json_encode(['success' => false, 'error' => 'Trade already processed']);
        exit;
    }
    
    // Find both users
    $fromUser = null;
    $toUser = null;
    foreach ($users as &$u) {
        if ($u['username'] === $trade['from']) $fromUser = &$u;
        if ($u['username'] === $trade['to']) $toUser = &$u;
    }
    
    if (!$fromUser || !$toUser) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    // Verify both still have items/coins
    if ($trade['offerCoins'] > 0 && $fromUser['coins'] < $trade['offerCoins']) {
        $trades[$tradeIndex]['status'] = 'cancelled';
        saveJson($tradesFile, $trades);
        echo json_encode(['success' => false, 'error' => 'Sender no longer has enough coins']);
        exit;
    }
    
    if ($trade['requestCoins'] > 0 && $toUser['coins'] < $trade['requestCoins']) {
        echo json_encode(['success' => false, 'error' => 'You don\'t have enough coins']);
        exit;
    }
    
    // Check items
    foreach ($trade['offer'] as $item) {
        if (!in_array($item['id'], $fromUser['inventory'][$item['category']] ?? [])) {
            $trades[$tradeIndex]['status'] = 'cancelled';
            saveJson($tradesFile, $trades);
            echo json_encode(['success' => false, 'error' => 'Sender no longer has item']);
            exit;
        }
    }
    
    foreach ($trade['request'] as $item) {
        if (!in_array($item['id'], $toUser['inventory'][$item['category']] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'You no longer have item: ' . $item['id']]);
            exit;
        }
    }
    
    // Execute trade
    // Transfer coins
    $fromUser['coins'] -= $trade['offerCoins'];
    $fromUser['coins'] += $trade['requestCoins'];
    $toUser['coins'] += $trade['offerCoins'];
    $toUser['coins'] -= $trade['requestCoins'];
    
    // Transfer items from -> to
    foreach ($trade['offer'] as $item) {
        $idx = array_search($item['id'], $fromUser['inventory'][$item['category']]);
        if ($idx !== false) {
            array_splice($fromUser['inventory'][$item['category']], $idx, 1);
        }
        $toUser['inventory'][$item['category']][] = $item['id'];
    }
    
    // Transfer items to -> from
    foreach ($trade['request'] as $item) {
        $idx = array_search($item['id'], $toUser['inventory'][$item['category']]);
        if ($idx !== false) {
            array_splice($toUser['inventory'][$item['category']], $idx, 1);
        }
        $fromUser['inventory'][$item['category']][] = $item['id'];
    }
    
    $trades[$tradeIndex]['status'] = 'completed';
    $trades[$tradeIndex]['completedAt'] = time();
    
    saveJson($tradesFile, $trades);
    saveJson($usersFile, $users);
    
    echo json_encode(['success' => true, 'message' => 'Trade completed!']);
    exit;
}

// Decline a trade
if ($action === 'decline') {
    $tradeId = $input['tradeId'] ?? '';
    
    $trades = loadJson($tradesFile);
    
    foreach ($trades as &$t) {
        if ($t['id'] === $tradeId && $t['to'] === $currentUser && $t['status'] === 'pending') {
            $t['status'] = 'declined';
            saveJson($tradesFile, $trades);
            echo json_encode(['success' => true]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'error' => 'Trade not found']);
    exit;
}

// Cancel a trade you created
if ($action === 'cancel') {
    $tradeId = $input['tradeId'] ?? '';
    
    $trades = loadJson($tradesFile);
    
    foreach ($trades as &$t) {
        if ($t['id'] === $tradeId && $t['from'] === $currentUser && $t['status'] === 'pending') {
            $t['status'] = 'cancelled';
            saveJson($tradesFile, $trades);
            echo json_encode(['success' => true]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'error' => 'Trade not found']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
