<?php
session_start();
header('Content-Type: application/json');

$usersFile = __DIR__ . '/../data/users.json';
$marketFile = __DIR__ . '/../data/marketplace.json';

function loadJson($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$users = loadJson($usersFile);
$market = loadJson($marketFile);
if (!isset($market['listings'])) $market['listings'] = [];
if (!isset($market['history'])) $market['history'] = [];

// Market fee (5%)
$MARKET_FEE = 0.05;

// Min/max prices by rarity
$PRICE_RANGES = [
    'common' => ['min' => 10, 'max' => 100],
    'uncommon' => ['min' => 25, 'max' => 250],
    'rare' => ['min' => 50, 'max' => 500],
    'epic' => ['min' => 100, 'max' => 1000],
    'legendary' => ['min' => 250, 'max' => 5000]
];

switch ($action) {
    case 'listings':
        // Get all active listings
        $type = $_GET['type'] ?? 'all';
        $rarity = $_GET['rarity'] ?? 'all';
        $sort = $_GET['sort'] ?? 'newest';
        $page = intval($_GET['page'] ?? 1);
        $perPage = 20;

        $filtered = array_filter($market['listings'], function($l) use ($type, $rarity) {
            if ($l['status'] !== 'active') return false;
            if ($type !== 'all' && $l['itemType'] !== $type) return false;
            if ($rarity !== 'all' && $l['rarity'] !== $rarity) return false;
            return true;
        });

        // Sort
        usort($filtered, function($a, $b) use ($sort) {
            switch ($sort) {
                case 'price_low': return $a['price'] - $b['price'];
                case 'price_high': return $b['price'] - $a['price'];
                case 'oldest': return strtotime($a['listed']) - strtotime($b['listed']);
                default: return strtotime($b['listed']) - strtotime($a['listed']);
            }
        });

        $total = count($filtered);
        $filtered = array_slice($filtered, ($page - 1) * $perPage, $perPage);

        echo json_encode([
            'success' => true,
            'listings' => array_values($filtered),
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $perPage)
        ]);
        break;

    case 'myListings':
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }
        $username = $_SESSION['user'];

        $myListings = array_filter($market['listings'], fn($l) => $l['seller'] === $username);

        echo json_encode([
            'success' => true,
            'listings' => array_values($myListings)
        ]);
        break;

    case 'list':
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }
        $username = $_SESSION['user'];

        $input = json_decode(file_get_contents('php://input'), true);
        $itemType = $input['itemType'] ?? '';
        $itemIndex = intval($input['itemIndex'] ?? -1);
        $price = intval($input['price'] ?? 0);

        if ($price < 1) {
            echo json_encode(['success' => false, 'error' => 'Invalid price']);
            exit;
        }

        $user = &$users['users'][$username];

        // Get item from inventory
        $item = null;
        $rarity = 'common';

        switch ($itemType) {
            case 'lootbox':
                if (!isset($user['inventory']['lootboxes'][$itemIndex])) {
                    echo json_encode(['success' => false, 'error' => 'Item not found']);
                    exit;
                }
                $item = $user['inventory']['lootboxes'][$itemIndex];
                $rarity = $item['type'];
                array_splice($user['inventory']['lootboxes'], $itemIndex, 1);
                break;

            case 'card':
                if (!isset($user['inventory']['tradingCards'][$itemIndex])) {
                    echo json_encode(['success' => false, 'error' => 'Item not found']);
                    exit;
                }
                $item = $user['inventory']['tradingCards'][$itemIndex];
                $rarity = $item['rarity'];
                array_splice($user['inventory']['tradingCards'], $itemIndex, 1);
                break;

            case 'booster':
                if (!isset($user['inventory']['boosters'][$itemIndex])) {
                    echo json_encode(['success' => false, 'error' => 'Item not found']);
                    exit;
                }
                $item = $user['inventory']['boosters'][$itemIndex];
                $rarity = 'rare';
                array_splice($user['inventory']['boosters'], $itemIndex, 1);
                break;

            case 'petEgg':
                if (!isset($user['inventory']['petEggs'][$itemIndex])) {
                    echo json_encode(['success' => false, 'error' => 'Item not found']);
                    exit;
                }
                $item = $user['inventory']['petEggs'][$itemIndex];
                $rarity = $item['rarity'] ?? 'rare';
                array_splice($user['inventory']['petEggs'], $itemIndex, 1);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Invalid item type']);
                exit;
        }

        // Check price range
        $range = $PRICE_RANGES[$rarity] ?? $PRICE_RANGES['common'];
        if ($price < $range['min'] || $price > $range['max']) {
            // Restore item
            switch ($itemType) {
                case 'lootbox': $user['inventory']['lootboxes'][] = $item; break;
                case 'card': $user['inventory']['tradingCards'][] = $item; break;
                case 'booster': $user['inventory']['boosters'][] = $item; break;
                case 'petEgg': $user['inventory']['petEggs'][] = $item; break;
            }
            echo json_encode(['success' => false, 'error' => "Price must be {$range['min']}-{$range['max']} for {$rarity} items"]);
            exit;
        }

        $listingId = uniqid('list_');
        $market['listings'][] = [
            'id' => $listingId,
            'seller' => $username,
            'itemType' => $itemType,
            'item' => $item,
            'rarity' => $rarity,
            'price' => $price,
            'listed' => date('c'),
            'status' => 'active'
        ];

        saveJson($usersFile, $users);
        saveJson($marketFile, $market);

        echo json_encode([
            'success' => true,
            'listingId' => $listingId,
            'message' => 'Item listed for ' . $price . ' coins'
        ]);
        break;

    case 'buy':
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }
        $username = $_SESSION['user'];

        $input = json_decode(file_get_contents('php://input'), true);
        $listingId = $input['listingId'] ?? '';

        $listingIndex = null;
        foreach ($market['listings'] as $i => $l) {
            if ($l['id'] === $listingId) {
                $listingIndex = $i;
                break;
            }
        }

        if ($listingIndex === null) {
            echo json_encode(['success' => false, 'error' => 'Listing not found']);
            exit;
        }

        $listing = &$market['listings'][$listingIndex];

        if ($listing['status'] !== 'active') {
            echo json_encode(['success' => false, 'error' => 'Listing no longer available']);
            exit;
        }

        if ($listing['seller'] === $username) {
            echo json_encode(['success' => false, 'error' => 'Cannot buy your own listing']);
            exit;
        }

        $buyer = &$users['users'][$username];
        if (($buyer['coins'] ?? 0) < $listing['price']) {
            echo json_encode(['success' => false, 'error' => 'Not enough coins']);
            exit;
        }

        // Process purchase
        $buyer['coins'] -= $listing['price'];

        // Give item to buyer
        switch ($listing['itemType']) {
            case 'lootbox':
                $buyer['inventory']['lootboxes'][] = $listing['item'];
                break;
            case 'card':
                $buyer['inventory']['tradingCards'][] = $listing['item'];
                break;
            case 'booster':
                $buyer['inventory']['boosters'][] = $listing['item'];
                break;
            case 'petEgg':
                $buyer['inventory']['petEggs'][] = $listing['item'];
                break;
        }

        // Pay seller (minus fee)
        $seller = &$users['users'][$listing['seller']];
        $fee = floor($listing['price'] * $MARKET_FEE);
        $sellerReceives = $listing['price'] - $fee;
        $seller['coins'] = ($seller['coins'] ?? 0) + $sellerReceives;

        // Update listing
        $listing['status'] = 'sold';
        $listing['buyer'] = $username;
        $listing['soldAt'] => date('c');
        $listing['fee'] = $fee;

        // Add to history
        $market['history'][] = [
            'listing' => $listing,
            'date' => date('c')
        ];

        saveJson($usersFile, $users);
        saveJson($marketFile, $market);

        echo json_encode([
            'success' => true,
            'message' => 'Item purchased!',
            'paid' => $listing['price'],
            'newBalance' => $buyer['coins']
        ]);
        break;

    case 'cancel':
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }
        $username = $_SESSION['user'];

        $input = json_decode(file_get_contents('php://input'), true);
        $listingId = $input['listingId'] ?? '';

        $listingIndex = null;
        foreach ($market['listings'] as $i => $l) {
            if ($l['id'] === $listingId) {
                $listingIndex = $i;
                break;
            }
        }

        if ($listingIndex === null) {
            echo json_encode(['success' => false, 'error' => 'Listing not found']);
            exit;
        }

        $listing = $market['listings'][$listingIndex];

        if ($listing['seller'] !== $username) {
            echo json_encode(['success' => false, 'error' => 'Not your listing']);
            exit;
        }

        if ($listing['status'] !== 'active') {
            echo json_encode(['success' => false, 'error' => 'Listing already closed']);
            exit;
        }

        // Return item to seller
        $user = &$users['users'][$username];
        switch ($listing['itemType']) {
            case 'lootbox':
                $user['inventory']['lootboxes'][] = $listing['item'];
                break;
            case 'card':
                $user['inventory']['tradingCards'][] = $listing['item'];
                break;
            case 'booster':
                $user['inventory']['boosters'][] = $listing['item'];
                break;
            case 'petEgg':
                $user['inventory']['petEggs'][] = $listing['item'];
                break;
        }

        // Remove listing
        array_splice($market['listings'], $listingIndex, 1);

        saveJson($usersFile, $users);
        saveJson($marketFile, $market);

        echo json_encode([
            'success' => true,
            'message' => 'Listing cancelled, item returned'
        ]);
        break;

    case 'history':
        $limit = intval($_GET['limit'] ?? 50);
        $history = array_slice(array_reverse($market['history']), 0, $limit);

        echo json_encode([
            'success' => true,
            'history' => $history
        ]);
        break;

    case 'priceRanges':
        echo json_encode([
            'success' => true,
            'priceRanges' => $PRICE_RANGES,
            'fee' => $MARKET_FEE * 100 . '%'
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
