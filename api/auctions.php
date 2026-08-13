<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/_store.php';

$AUCTIONS_FILE = __DIR__ . '/../data/auctions.json';
$USERS_FILE = __DIR__ . '/../data/users.json';
$OWNERS = ['billybuffalo15'];

function loadAuctions() {
    global $AUCTIONS_FILE;
    if (!file_exists($AUCTIONS_FILE)) return [];
    return store_hold_read($AUCTIONS_FILE, []);
}

function saveAuctions($auctions) {
    global $AUCTIONS_FILE;
    store_hold_write($AUCTIONS_FILE, $auctions);
}

function loadUsers() {
    global $USERS_FILE;
    if (!file_exists($USERS_FILE)) return ['users' => []];
    return store_hold_read($USERS_FILE, ['users' => []]);
}

function saveUsers($data) {
    global $USERS_FILE;
    store_hold_write($USERS_FILE, $data);
}

function getUserCoins($username) {
    $data = loadUsers();
    $userLower = strtolower($username);
    foreach ($data['users'] as $uname => $u) {
        if (strtolower($uname) === $userLower) {
            return $u['coins'] ?? 0;
        }
    }
    return 0;
}

function updateUserCoins($username, $amount) {
    $data = loadUsers();
    $userLower = strtolower($username);
    foreach ($data['users'] as $uname => &$u) {
        if (strtolower($uname) === $userLower) {
            $u['coins'] = ($u['coins'] ?? 0) + $amount;
            saveUsers($data);
            return true;
        }
    }
    return false;
}

function checkEndedAuctions() {
    $auctions = loadAuctions();
    $now = time();
    $changed = false;
    
    foreach ($auctions as $id => &$auction) {
        if ($auction['status'] === 'active' && $auction['endsAt'] <= $now) {
            $auction['status'] = 'ended';
            
            if ($auction['highestBidder']) {
                // Winner gets the item (handled elsewhere)
                $auction['winner'] = $auction['highestBidder'];
            } else {
                // No bids - auction failed
                $auction['winner'] = null;
            }
            $changed = true;
        }
    }
    
    if ($changed) saveAuctions($auctions);
    return $auctions;
}

function isOwner($username) {
    global $OWNERS;
    return in_array(strtolower($username), array_map('strtolower', $OWNERS));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Public actions
if ($action === 'list') {
    $auctions = checkEndedAuctions();
    $active = array_filter($auctions, fn($a) => $a['status'] === 'active');
    
    // Sort by ending soon first
    usort($active, fn($a, $b) => $a['endsAt'] - $b['endsAt']);
    
    echo json_encode(['success' => true, 'auctions' => array_values($active)]);
    exit;
}

if ($action === 'history') {
    $auctions = loadAuctions();
    $ended = array_filter($auctions, fn($a) => $a['status'] === 'ended');
    usort($ended, fn($a, $b) => $b['endsAt'] - $a['endsAt']);
    echo json_encode(['success' => true, 'auctions' => array_values(array_slice($ended, 0, 20))]);
    exit;
}

// Protected actions
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$username = $_SESSION['user'];

switch ($action) {
    case 'create':
        if (!isOwner($username)) {
            echo json_encode(['success' => false, 'error' => 'Only owners can create auctions']);
            exit;
        }
        
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $startingBid = intval($_POST['startingBid'] ?? 100);
        $buyNow = intval($_POST['buyNow'] ?? 0);
        $duration = intval($_POST['duration'] ?? 24); // hours
        $category = trim($_POST['category'] ?? 'misc');
        $itemType = trim($_POST['itemType'] ?? '');
        $itemId = trim($_POST['itemId'] ?? '');
        
        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Title required']);
            exit;
        }
        
        $auctions = loadAuctions();
        $auctionId = 'auction_' . time() . '_' . rand(1000, 9999);
        
        $auctions[$auctionId] = [
            'id' => $auctionId,
            'title' => $title,
            'description' => $description,
            'image' => $image ?: null,
            'category' => $category,
            'itemType' => $itemType,
            'itemId' => $itemId,
            'startingBid' => $startingBid,
            'currentBid' => 0,
            'buyNow' => $buyNow > 0 ? $buyNow : null,
            'highestBidder' => null,
            'bidHistory' => [],
            'status' => 'active',
            'createdBy' => $username,
            'createdAt' => date('Y-m-d H:i:s'),
            'endsAt' => time() + ($duration * 3600),
            'winner' => null
        ];
        
        saveAuctions($auctions);
        echo json_encode(['success' => true, 'auction' => $auctions[$auctionId], 'message' => 'Auction created!']);
        break;
        
    case 'bid':
        $auctionId = $_POST['auctionId'] ?? '';
        $amount = intval($_POST['amount'] ?? 0);
        
        $auctions = checkEndedAuctions();
        
        if (!isset($auctions[$auctionId])) {
            echo json_encode(['success' => false, 'error' => 'Auction not found']);
            exit;
        }
        
        $auction = &$auctions[$auctionId];
        
        if ($auction['status'] !== 'active') {
            echo json_encode(['success' => false, 'error' => 'Auction has ended']);
            exit;
        }
        
        $minBid = $auction['currentBid'] > 0 ? $auction['currentBid'] + 1 : $auction['startingBid'];
        
        if ($amount < $minBid) {
            echo json_encode(['success' => false, 'error' => 'Bid must be at least ' . number_format($minBid) . ' coins']);
            exit;
        }
        
        $userCoins = getUserCoins($username);
        if ($userCoins < $amount) {
            echo json_encode(['success' => false, 'error' => 'Not enough coins']);
            exit;
        }
        
        // Refund previous bidder
        if ($auction['highestBidder'] && $auction['currentBid'] > 0) {
            updateUserCoins($auction['highestBidder'], $auction['currentBid']);
        }
        
        // Deduct from new bidder
        updateUserCoins($username, -$amount);
        
        // Update auction
        $auction['currentBid'] = $amount;
        $auction['highestBidder'] = $username;
        $auction['bidHistory'][] = [
            'bidder' => $username,
            'amount' => $amount,
            'time' => date('Y-m-d H:i:s')
        ];
        
        // Extend auction by 2 minutes if ending soon (anti-snipe)
        if ($auction['endsAt'] - time() < 120) {
            $auction['endsAt'] = time() + 120;
        }
        
        saveAuctions($auctions);
        
        echo json_encode([
            'success' => true,
            'message' => 'Bid placed! You are now the highest bidder.',
            'currentBid' => $amount,
            'endsAt' => $auction['endsAt']
        ]);
        break;
        
    case 'buyNow':
        $auctionId = $_POST['auctionId'] ?? '';
        
        $auctions = checkEndedAuctions();
        
        if (!isset($auctions[$auctionId])) {
            echo json_encode(['success' => false, 'error' => 'Auction not found']);
            exit;
        }
        
        $auction = &$auctions[$auctionId];
        
        if ($auction['status'] !== 'active') {
            echo json_encode(['success' => false, 'error' => 'Auction has ended']);
            exit;
        }
        
        if (!$auction['buyNow']) {
            echo json_encode(['success' => false, 'error' => 'No Buy Now option']);
            exit;
        }
        
        $userCoins = getUserCoins($username);
        if ($userCoins < $auction['buyNow']) {
            echo json_encode(['success' => false, 'error' => 'Not enough coins']);
            exit;
        }
        
        // Refund previous bidder
        if ($auction['highestBidder'] && $auction['currentBid'] > 0) {
            updateUserCoins($auction['highestBidder'], $auction['currentBid']);
        }
        
        // Deduct buy now price
        updateUserCoins($username, -$auction['buyNow']);
        
        // End auction
        $auction['status'] = 'ended';
        $auction['winner'] = $username;
        $auction['currentBid'] = $auction['buyNow'];
        $auction['highestBidder'] = $username;
        $auction['bidHistory'][] = [
            'bidder' => $username,
            'amount' => $auction['buyNow'],
            'time' => date('Y-m-d H:i:s'),
            'type' => 'buyNow'
        ];
        
        saveAuctions($auctions);
        
        echo json_encode([
            'success' => true,
            'message' => 'Congratulations! You bought the item for ' . number_format($auction['buyNow']) . ' coins!'
        ]);
        break;
        
    case 'myBids':
        $auctions = loadAuctions();
        $myBids = [];
        
        foreach ($auctions as $auction) {
            if (strtolower($auction['highestBidder'] ?? '') === strtolower($username)) {
                $myBids[] = $auction;
            }
        }
        
        echo json_encode(['success' => true, 'auctions' => $myBids]);
        break;
        
    case 'myWins':
        $auctions = loadAuctions();
        $myWins = array_filter($auctions, fn($a) => 
            $a['status'] === 'ended' && 
            strtolower($a['winner'] ?? '') === strtolower($username)
        );
        echo json_encode(['success' => true, 'auctions' => array_values($myWins)]);
        break;
        
    case 'cancel':
        if (!isOwner($username)) {
            echo json_encode(['success' => false, 'error' => 'Only owners can cancel auctions']);
            exit;
        }
        
        $auctionId = $_POST['auctionId'] ?? '';
        $auctions = loadAuctions();
        
        if (!isset($auctions[$auctionId])) {
            echo json_encode(['success' => false, 'error' => 'Auction not found']);
            exit;
        }
        
        $auction = &$auctions[$auctionId];
        
        // Refund current bidder
        if ($auction['highestBidder'] && $auction['currentBid'] > 0) {
            updateUserCoins($auction['highestBidder'], $auction['currentBid']);
        }
        
        $auction['status'] = 'cancelled';
        saveAuctions($auctions);
        
        echo json_encode(['success' => true, 'message' => 'Auction cancelled']);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
