<?php
/**
 * Coin Shop API
 * 
 * GET:
 *   ?action=items     - Get all shop items
 *   ?action=inventory - Get user inventory
 * 
 * POST:
 *   { "action": "purchase", "category": "name_colors", "itemId": "gold" }
 *   { "action": "equip", "category": "name_colors", "itemId": "gold" }
 *   { "action": "unequip", "category": "name_colors" }
 *   { "action": "activate-boost", "boostType": "xp_2x" }
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

// Shop items definition
$SHOP_ITEMS = [
    'name_colors' => [
        'name' => 'Name Colors',
        'icon' => '🎨',
        'items' => [
            ['id' => 'gold', 'name' => 'Gold', 'cost' => 500, 'css' => 'color: #ffd700; text-shadow: 0 0 5px rgba(255,215,0,0.5)'],
            ['id' => 'rainbow', 'name' => 'Rainbow', 'cost' => 2000, 'css' => 'background: linear-gradient(90deg, #ff0000, #ff7f00, #ffff00, #00ff00, #0000ff, #4b0082, #8f00ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text'],
            ['id' => 'neon_green', 'name' => 'Neon Green', 'cost' => 750, 'css' => 'color: #39ff14; text-shadow: 0 0 10px #39ff14'],
            ['id' => 'fire', 'name' => 'Fire', 'cost' => 1500, 'css' => 'background: linear-gradient(180deg, #ff0, #f00); -webkit-background-clip: text; -webkit-text-fill-color: transparent'],
            ['id' => 'ice', 'name' => 'Ice', 'cost' => 1500, 'css' => 'color: #00ffff; text-shadow: 0 0 10px #00ffff, 0 0 20px #0080ff'],
            ['id' => 'purple', 'name' => 'Royal Purple', 'cost' => 750, 'css' => 'color: #9b59b6; text-shadow: 0 0 8px rgba(155,89,182,0.6)']
        ]
    ],
    'borders' => [
        'name' => 'Profile Borders',
        'icon' => '🖼️',
        'items' => [
            ['id' => 'bronze', 'name' => 'Bronze', 'cost' => 1000, 'css' => 'border: 3px solid #cd7f32; box-shadow: 0 0 10px rgba(205,127,50,0.5)'],
            ['id' => 'silver', 'name' => 'Silver', 'cost' => 2000, 'css' => 'border: 3px solid #c0c0c0; box-shadow: 0 0 10px rgba(192,192,192,0.5)'],
            ['id' => 'gold', 'name' => 'Gold', 'cost' => 3500, 'css' => 'border: 3px solid #ffd700; box-shadow: 0 0 15px rgba(255,215,0,0.6)'],
            ['id' => 'diamond', 'name' => 'Diamond', 'cost' => 5000, 'css' => 'border: 3px solid #b9f2ff; box-shadow: 0 0 20px #b9f2ff, inset 0 0 10px rgba(185,242,255,0.3)'],
            ['id' => 'rainbow', 'name' => 'Rainbow', 'cost' => 7500, 'css' => 'border: 3px solid transparent; background: linear-gradient(#1a1a2e, #1a1a2e) padding-box, linear-gradient(90deg, #ff0000, #ff7f00, #ffff00, #00ff00, #0000ff, #8f00ff) border-box; animation: rainbow-border 3s linear infinite']
        ]
    ],
    'avatar_effects' => [
        'name' => 'Avatar Effects',
        'icon' => '✨',
        'items' => [
            ['id' => 'sparkle', 'name' => 'Sparkle', 'cost' => 2000, 'animation' => 'sparkle'],
            ['id' => 'bounce', 'name' => 'Bounce', 'cost' => 2000, 'animation' => 'bounce'],
            ['id' => 'glow', 'name' => 'Glow', 'cost' => 2000, 'animation' => 'glow'],
            ['id' => 'shake', 'name' => 'Shake', 'cost' => 2000, 'animation' => 'shake'],
            ['id' => 'pulse', 'name' => 'Pulse', 'cost' => 2000, 'animation' => 'pulse']
        ]
    ],
    'emotes' => [
        'name' => 'Chat Emotes',
        'icon' => '😀',
        'items' => [
            ['id' => 'gg', 'name' => ':gg:', 'cost' => 300, 'emoji' => '🎮', 'display' => 'GG'],
            ['id' => 'nice', 'name' => ':nice:', 'cost' => 300, 'emoji' => '👍', 'display' => 'Nice!'],
            ['id' => 'rip', 'name' => ':rip:', 'cost' => 300, 'emoji' => '💀', 'display' => 'RIP'],
            ['id' => 'pog', 'name' => ':pog:', 'cost' => 300, 'emoji' => '😲', 'display' => 'POG'],
            ['id' => 'ez', 'name' => ':ez:', 'cost' => 300, 'emoji' => '😎', 'display' => 'EZ'],
            ['id' => 'sad', 'name' => ':sad:', 'cost' => 300, 'emoji' => '😢', 'display' => 'Sad'],
            ['id' => 'hype', 'name' => ':hype:', 'cost' => 300, 'emoji' => '🔥', 'display' => 'HYPE'],
            ['id' => 'love', 'name' => ':love:', 'cost' => 300, 'emoji' => '❤️', 'display' => 'Love'],
            ['id' => 'laugh', 'name' => ':laugh:', 'cost' => 300, 'emoji' => '😂', 'display' => 'LOL'],
            ['id' => 'think', 'name' => ':think:', 'cost' => 300, 'emoji' => '🤔', 'display' => 'Hmm']
        ]
    ],
    'boosters' => [
        'name' => 'Boosters',
        'icon' => '⚡',
        'items' => [
            ['id' => 'xp_2x', 'name' => '2x XP (1 hour)', 'cost' => 1000, 'duration' => 3600, 'multiplier' => 2, 'type' => 'xp'],
            ['id' => 'coins_2x', 'name' => '2x Coins (1 hour)', 'cost' => 1500, 'duration' => 3600, 'multiplier' => 2, 'type' => 'coins']
        ]
    ],
    'social' => [
        'name' => 'Social Items',
        'icon' => '🏆',
        'items' => [
            ['id' => 'tournament_banner', 'name' => 'Tournament Banner', 'cost' => 3000, 'desc' => 'Custom gold banner on tournaments you create'],
            ['id' => 'spotlight', 'name' => 'Profile Spotlight (24h)', 'cost' => 5000, 'duration' => 86400, 'desc' => 'Featured on homepage for 24 hours']
        ]
    ]
];

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function writeUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

function ensureUserHasShopFields(&$user) {
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
    if (!isset($user['equipped'])) {
        $user['equipped'] = [
            'name_color' => null,
            'border' => null,
            'avatar_effect' => null
        ];
    }
    if (!isset($user['activeBoosts'])) $user['activeBoosts'] = [];
}

function getItemFromShop($category, $itemId) {
    global $SHOP_ITEMS;
    if (!isset($SHOP_ITEMS[$category])) return null;
    foreach ($SHOP_ITEMS[$category]['items'] as $item) {
        if ($item['id'] === $itemId) return $item;
    }
    return null;
}

function cleanExpiredBoosts(&$user) {
    $now = time();
    $user['activeBoosts'] = array_filter($user['activeBoosts'], function($boost) use ($now) {
        return strtotime($boost['expiresAt']) > $now;
    });
    $user['activeBoosts'] = array_values($user['activeBoosts']);
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'items';
    
    if ($action === 'items') {
        echo json_encode(['success' => true, 'shop' => $SHOP_ITEMS]);
        exit();
    }
    
    if ($action === 'inventory') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit();
        }
        
        $data = readUsers();
        $username = $_SESSION['user'];
        
        if (!isset($data['users'][$username])) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit();
        }
        
        $user = &$data['users'][$username];
        ensureUserHasShopFields($user);
        cleanExpiredBoosts($user);
        writeUsers($data);
        
        echo json_encode([
            'success' => true,
            'coins' => $user['coins'],
            'inventory' => $user['inventory'],
            'equipped' => $user['equipped'],
            'activeBoosts' => $user['activeBoosts']
        ]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit();
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $data = readUsers();
    $username = $_SESSION['user'];
    
    if (!isset($data['users'][$username])) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    $user = &$data['users'][$username];
    ensureUserHasShopFields($user);
    cleanExpiredBoosts($user);
    
    if ($action === 'purchase') {
        $category = $input['category'] ?? '';
        $itemId = $input['itemId'] ?? '';
        
        $item = getItemFromShop($category, $itemId);
        if (!$item) {
            echo json_encode(['success' => false, 'error' => 'Item not found']);
            exit();
        }
        
        // Check if already owned (except boosters which can be bought multiple times)
        if ($category !== 'boosters' && in_array($itemId, $user['inventory'][$category] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'You already own this item']);
            exit();
        }
        
        // Check coins
        if ($user['coins'] < $item['cost']) {
            echo json_encode(['success' => false, 'error' => 'Not enough coins']);
            exit();
        }
        
        // Purchase
        $user['coins'] -= $item['cost'];
        
        if ($category === 'boosters') {
            // Add to boosters inventory (consumable)
            $user['inventory']['boosters'][] = $itemId;
        } else {
            // Add to inventory
            if (!isset($user['inventory'][$category])) {
                $user['inventory'][$category] = [];
            }
            $user['inventory'][$category][] = $itemId;
        }
        
        writeUsers($data);
        
        echo json_encode([
            'success' => true,
            'message' => 'Purchased ' . $item['name'],
            'coins' => $user['coins'],
            'inventory' => $user['inventory']
        ]);
        exit();
    }
    
    if ($action === 'equip') {
        $category = $input['category'] ?? '';
        $itemId = $input['itemId'] ?? '';
        
        // Map category to equipped key
        $equipKey = str_replace('s', '', $category); // name_colors -> name_color
        if ($category === 'borders') $equipKey = 'border';
        if ($category === 'avatar_effects') $equipKey = 'avatar_effect';
        
        // Check ownership
        if (!in_array($itemId, $user['inventory'][$category] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'You do not own this item']);
            exit();
        }
        
        $user['equipped'][$equipKey] = $itemId;
        writeUsers($data);
        
        echo json_encode([
            'success' => true,
            'equipped' => $user['equipped']
        ]);
        exit();
    }
    
    if ($action === 'unequip') {
        $category = $input['category'] ?? '';
        
        $equipKey = str_replace('s', '', $category);
        if ($category === 'borders') $equipKey = 'border';
        if ($category === 'avatar_effects') $equipKey = 'avatar_effect';
        
        $user['equipped'][$equipKey] = null;
        writeUsers($data);
        
        echo json_encode([
            'success' => true,
            'equipped' => $user['equipped']
        ]);
        exit();
    }
    
    if ($action === 'activate-boost') {
        $boostType = $input['boostType'] ?? '';
        
        // Check if user has this boost in inventory
        $boostIndex = array_search($boostType, $user['inventory']['boosters'] ?? []);
        if ($boostIndex === false) {
            echo json_encode(['success' => false, 'error' => 'You do not have this boost']);
            exit();
        }
        
        // Get boost info
        $boostItem = getItemFromShop('boosters', $boostType);
        if (!$boostItem) {
            echo json_encode(['success' => false, 'error' => 'Invalid boost']);
            exit();
        }
        
        // Check if already have active boost of same type
        foreach ($user['activeBoosts'] as $active) {
            if ($active['type'] === $boostType) {
                echo json_encode(['success' => false, 'error' => 'You already have this boost active']);
                exit();
            }
        }
        
        // Remove from inventory
        array_splice($user['inventory']['boosters'], $boostIndex, 1);
        
        // Add to active boosts
        $user['activeBoosts'][] = [
            'type' => $boostType,
            'multiplier' => $boostItem['multiplier'],
            'boostFor' => $boostItem['type'],
            'expiresAt' => date('c', time() + $boostItem['duration'])
        ];
        
        writeUsers($data);
        
        echo json_encode([
            'success' => true,
            'message' => 'Activated ' . $boostItem['name'],
            'activeBoosts' => $user['activeBoosts'],
            'inventory' => $user['inventory']
        ]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
