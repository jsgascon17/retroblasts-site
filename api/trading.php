<?php
/**
 * Trading System API
 * 
 * GET:
 *   ?action=pending     - Get pending trade offers (incoming/outgoing)
 *   ?action=history     - Get trade history
 * 
 * POST:
 *   { "action": "offer", "to": "username", "offering": {...}, "requesting": {...} }
 *   { "action": "accept", "tradeId": "..." }
 *   { "action": "decline", "tradeId": "..." }
 *   { "action": "cancel", "tradeId": "..." }
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

$tradesFile = __DIR__ . '/../data/trades.json';
$usersFile = __DIR__ . '/../data/users.json';

function readTrades() {
    global $tradesFile;
    if (!file_exists($tradesFile)) return ['trades' => []];
    return json_decode(file_get_contents($tradesFile), true) ?: ['trades' => []];
}

function writeTrades($data) {
    global $tradesFile;
    file_put_contents($tradesFile, json_encode($data, JSON_PRETTY_PRINT));
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
}

function userHasItems($user, $items) {
    if (isset($items['coins']) && $items['coins'] > 0) {
        if (($user['coins'] ?? 0) < $items['coins']) return false;
    }
    
    foreach (['name_colors', 'borders', 'avatar_effects', 'emotes', 'boosters', 'social'] as $cat) {
        if (isset($items[$cat]) && is_array($items[$cat])) {
            foreach ($items[$cat] as $itemId) {
                $userItems = $user['inventory'][$cat] ?? [];
                $count = array_count_values($userItems)[$itemId] ?? 0;
                $needed = array_count_values($items[$cat])[$itemId] ?? 0;
                if ($count < $needed) return false;
            }
        }
    }
    
    return true;
}

function transferItems(&$from, &$to, $items) {
    if (isset($items['coins']) && $items['coins'] > 0) {
        $from['coins'] -= $items['coins'];
        $to['coins'] = ($to['coins'] ?? 0) + $items['coins'];
    }
    
    foreach (['name_colors', 'borders', 'avatar_effects', 'emotes', 'boosters', 'social'] as $cat) {
        if (isset($items[$cat]) && is_array($items[$cat])) {
            foreach ($items[$cat] as $itemId) {
                // Remove from sender
                $key = array_search($itemId, $from['inventory'][$cat] ?? []);
                if ($key !== false) {
                    array_splice($from['inventory'][$cat], $key, 1);
                }
                // Add to receiver
                if (!isset($to['inventory'][$cat])) $to['inventory'][$cat] = [];
                $to['inventory'][$cat][] = $itemId;
            }
        }
    }
}

// Check login
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$currentUser = $_SESSION['user'];

// GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'pending';
    $tradesData = readTrades();
    
    if ($action === 'pending') {
        $incoming = [];
        $outgoing = [];
        
        foreach ($tradesData['trades'] as $trade) {
            if ($trade['status'] !== 'pending') continue;
            
            if ($trade['to'] === $currentUser) {
                $incoming[] = $trade;
            } else if ($trade['from'] === $currentUser) {
                $outgoing[] = $trade;
            }
        }
        
        echo json_encode(['success' => true, 'incoming' => $incoming, 'outgoing' => $outgoing]);
        exit();
    }
    
    if ($action === 'history') {
        $history = array_filter($tradesData['trades'], function($t) use ($currentUser) {
            return ($t['from'] === $currentUser || $t['to'] === $currentUser) && $t['status'] !== 'pending';
        });
        
        usort($history, function($a, $b) {
            return strtotime($b['updatedAt'] ?? $b['createdAt']) - strtotime($a['updatedAt'] ?? $a['createdAt']);
        });
        
        echo json_encode(['success' => true, 'history' => array_values(array_slice($history, 0, 20))]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

// POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $tradesData = readTrades();
    $usersData = readUsers();
    
    if (!isset($usersData['users'][$currentUser])) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    $currentUserData = &$usersData['users'][$currentUser];
    ensureUserHasFields($currentUserData);
    
    if ($action === 'offer') {
        $toUser = strtolower($input['to'] ?? '');
        $offering = $input['offering'] ?? [];
        $requesting = $input['requesting'] ?? [];
        
        if ($toUser === $currentUser) {
            echo json_encode(['success' => false, 'error' => 'Cannot trade with yourself']);
            exit();
        }
        
        if (!isset($usersData['users'][$toUser])) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit();
        }
        
        // Check if friends
        if (!in_array($toUser, $currentUserData['friends'] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'You can only trade with friends']);
            exit();
        }
        
        // Verify sender has the items
        if (!userHasItems($currentUserData, $offering)) {
            echo json_encode(['success' => false, 'error' => 'You do not have all the items you are offering']);
            exit();
        }
        
        // Check for empty trade
        $offeringCount = ($offering['coins'] ?? 0) + count($offering['name_colors'] ?? []) + count($offering['borders'] ?? []) + count($offering['emotes'] ?? []) + count($offering['boosters'] ?? []);
        $requestingCount = ($requesting['coins'] ?? 0) + count($requesting['name_colors'] ?? []) + count($requesting['borders'] ?? []) + count($requesting['emotes'] ?? []) + count($requesting['boosters'] ?? []);
        
        if ($offeringCount === 0 && $requestingCount === 0) {
            echo json_encode(['success' => false, 'error' => 'Trade cannot be empty']);
            exit();
        }
        
        $trade = [
            'id' => bin2hex(random_bytes(8)),
            'from' => $currentUser,
            'fromName' => $currentUserData['displayName'],
            'to' => $toUser,
            'toName' => $usersData['users'][$toUser]['displayName'],
            'offering' => $offering,
            'requesting' => $requesting,
            'status' => 'pending',
            'createdAt' => date('c')
        ];
        
        $tradesData['trades'][] = $trade;
        writeTrades($tradesData);
        
        echo json_encode(['success' => true, 'message' => 'Trade offer sent!', 'trade' => $trade]);
        exit();
    }
    
    if ($action === 'accept') {
        $tradeId = $input['tradeId'] ?? '';
        $tradeIndex = -1;
        
        foreach ($tradesData['trades'] as $i => $t) {
            if ($t['id'] === $tradeId) {
                $tradeIndex = $i;
                break;
            }
        }
        
        if ($tradeIndex === -1) {
            echo json_encode(['success' => false, 'error' => 'Trade not found']);
            exit();
        }
        
        $trade = &$tradesData['trades'][$tradeIndex];
        
        if ($trade['to'] !== $currentUser) {
            echo json_encode(['success' => false, 'error' => 'This trade is not for you']);
            exit();
        }
        
        if ($trade['status'] !== 'pending') {
            echo json_encode(['success' => false, 'error' => 'Trade is no longer pending']);
            exit();
        }
        
        $fromUser = &$usersData['users'][$trade['from']];
        ensureUserHasFields($fromUser);
        
        // Verify both parties still have items
        if (!userHasItems($fromUser, $trade['offering'])) {
            $trade['status'] = 'cancelled';
            $trade['updatedAt'] = date('c');
            writeTrades($tradesData);
            echo json_encode(['success' => false, 'error' => 'Sender no longer has the offered items']);
            exit();
        }
        
        if (!userHasItems($currentUserData, $trade['requesting'])) {
            echo json_encode(['success' => false, 'error' => 'You do not have the requested items']);
            exit();
        }
        
        // Execute trade
        transferItems($fromUser, $currentUserData, $trade['offering']);
        transferItems($currentUserData, $fromUser, $trade['requesting']);
        
        $trade['status'] = 'completed';
        $trade['updatedAt'] = date('c');
        
        writeTrades($tradesData);
        writeUsers($usersData);
        
        echo json_encode(['success' => true, 'message' => 'Trade completed!']);
        exit();
    }
    
    if ($action === 'decline') {
        $tradeId = $input['tradeId'] ?? '';
        
        foreach ($tradesData['trades'] as &$trade) {
            if ($trade['id'] === $tradeId && $trade['to'] === $currentUser && $trade['status'] === 'pending') {
                $trade['status'] = 'declined';
                $trade['updatedAt'] = date('c');
                writeTrades($tradesData);
                echo json_encode(['success' => true, 'message' => 'Trade declined']);
                exit();
            }
        }
        
        echo json_encode(['success' => false, 'error' => 'Trade not found']);
        exit();
    }
    
    if ($action === 'cancel') {
        $tradeId = $input['tradeId'] ?? '';
        
        foreach ($tradesData['trades'] as &$trade) {
            if ($trade['id'] === $tradeId && $trade['from'] === $currentUser && $trade['status'] === 'pending') {
                $trade['status'] = 'cancelled';
                $trade['updatedAt'] = date('c');
                writeTrades($tradesData);
                echo json_encode(['success' => true, 'message' => 'Trade cancelled']);
                exit();
            }
        }
        
        echo json_encode(['success' => false, 'error' => 'Trade not found']);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
