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
$shopDataFile = __DIR__ . '/../data/shop-data.json';

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
            ['id' => 'purple', 'name' => 'Royal Purple', 'cost' => 750, 'css' => 'color: #9b59b6; text-shadow: 0 0 8px rgba(155,89,182,0.6)'],
            ['id' => 'neon_pink', 'name' => 'Neon Pink', 'cost' => 750, 'css' => 'color: #ff1493; text-shadow: 0 0 10px #ff1493'],
            ['id' => 'cyan', 'name' => 'Cyan', 'cost' => 500, 'css' => 'color: #00bcd4; text-shadow: 0 0 8px rgba(0,188,212,0.6)'],
            ['id' => 'crimson', 'name' => 'Crimson', 'cost' => 750, 'css' => 'color: #dc143c; text-shadow: 0 0 8px rgba(220,20,60,0.6)'],
            ['id' => 'silver', 'name' => 'Silver', 'cost' => 500, 'css' => 'color: #c0c0c0; text-shadow: 0 0 5px rgba(192,192,192,0.5)'],
            ['id' => 'lime', 'name' => 'Lime', 'cost' => 500, 'css' => 'color: #00ff00; text-shadow: 0 0 8px rgba(0,255,0,0.5)'],
            ['id' => 'animated_rainbow', 'name' => 'Animated Rainbow', 'cost' => 5000, 'css' => 'background: linear-gradient(90deg, #ff0000, #ff7f00, #ffff00, #00ff00, #0000ff, #4b0082, #8f00ff, #ff0000); background-size: 200% 100%; -webkit-background-clip: text; -webkit-text-fill-color: transparent; animation: rainbow-scroll 2s linear infinite']
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
            ['id' => 'rainbow', 'name' => 'Rainbow', 'cost' => 7500, 'css' => 'border: 3px solid transparent; background: linear-gradient(#1a1a2e, #1a1a2e) padding-box, linear-gradient(90deg, #ff0000, #ff7f00, #ffff00, #00ff00, #0000ff, #8f00ff) border-box; animation: rainbow-border 3s linear infinite'],
            ['id' => 'animated_fire', 'name' => 'Animated Fire', 'cost' => 8000, 'css' => 'border: 3px solid #ff4500; box-shadow: 0 0 15px #ff4500, 0 0 30px #ff6600; animation: fire-pulse 1s ease-in-out infinite'],
            ['id' => 'animated_ice', 'name' => 'Animated Ice', 'cost' => 8000, 'css' => 'border: 3px solid #00bfff; box-shadow: 0 0 15px #00bfff, 0 0 30px #87ceeb; animation: ice-shimmer 2s ease-in-out infinite'],
            ['id' => 'pixel', 'name' => 'Pixel Art', 'cost' => 4000, 'css' => 'border: 4px dashed #00ff00; box-shadow: 0 0 0 2px #000, 0 0 10px #00ff00'],
            ['id' => 'neon_glow', 'name' => 'Neon Glow', 'cost' => 6000, 'css' => 'border: 2px solid #fff; box-shadow: 0 0 10px #ff00ff, 0 0 20px #ff00ff, 0 0 30px #ff00ff, 0 0 40px #ff00ff']
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
            ['id' => 'pulse', 'name' => 'Pulse', 'cost' => 2000, 'animation' => 'pulse'],
            ['id' => 'ghost', 'name' => 'Ghost', 'cost' => 3000, 'animation' => 'ghost'],
            ['id' => 'fire_aura', 'name' => 'Fire Aura', 'cost' => 4000, 'animation' => 'fire-aura'],
            ['id' => 'rainbow_trail', 'name' => 'Rainbow Trail', 'cost' => 4500, 'animation' => 'rainbow-trail'],
            ['id' => 'glitch', 'name' => 'Glitch', 'cost' => 3500, 'animation' => 'glitch']
        ]
    ],
    'avatar_rings' => [
        'name' => 'Avatar Rings',
        'icon' => '💍',
        'items' => [
            ['id' => 'gold', 'name' => 'Gold', 'cost' => 500],
            ['id' => 'rainbow', 'name' => 'Rainbow', 'cost' => 2000],
            ['id' => 'neon-pink', 'name' => 'Neon Pink', 'cost' => 750],
            ['id' => 'neon-blue', 'name' => 'Neon Blue', 'cost' => 750],
            ['id' => 'neon-green', 'name' => 'Neon Green', 'cost' => 750],
            ['id' => 'fire', 'name' => 'Fire', 'cost' => 1500],
            ['id' => 'ice', 'name' => 'Ice', 'cost' => 1500]
        ]
    ],
    'titles' => [
        'name' => 'Titles',
        'icon' => '🏷️',
        'items' => [
            ['id' => 'champion', 'name' => 'Champion 👑', 'cost' => 5000, 'display' => 'Champion 👑'],
            ['id' => 'legend', 'name' => 'Legend ⭐', 'cost' => 7500, 'display' => 'Legend ⭐'],
            ['id' => 'whale', 'name' => 'Whale 🐋', 'cost' => 10000, 'display' => 'Whale 🐋'],
            ['id' => 'og', 'name' => 'OG 🔥', 'cost' => 15000, 'display' => 'OG 🔥', 'limited' => 10]
        ]
    ],
    'banners' => [
        'name' => 'Profile Banners',
        'icon' => '🖼️',
        'items' => [
            ['id' => 'sunset', 'name' => 'Sunset', 'cost' => 2000, 'gradient' => 'linear-gradient(135deg, #ff6b6b, #feca57, #ff9f43)'],
            ['id' => 'ocean', 'name' => 'Ocean Wave', 'cost' => 2000, 'gradient' => 'linear-gradient(135deg, #667eea, #764ba2, #f953c6)'],
            ['id' => 'forest', 'name' => 'Forest', 'cost' => 2000, 'gradient' => 'linear-gradient(135deg, #11998e, #38ef7d)'],
            ['id' => 'galaxy', 'name' => 'Galaxy', 'cost' => 3500, 'gradient' => 'linear-gradient(135deg, #0f0c29, #302b63, #24243e)'],
            ['id' => 'fire', 'name' => 'Inferno', 'cost' => 3500, 'gradient' => 'linear-gradient(135deg, #f12711, #f5af19)'],
            ['id' => 'neon', 'name' => 'Neon City', 'cost' => 4000, 'gradient' => 'linear-gradient(135deg, #00d2ff, #3a7bd5, #f953c6, #f5576c)'],
            ['id' => 'rainbow', 'name' => 'Rainbow', 'cost' => 5000, 'gradient' => 'linear-gradient(135deg, #ff0000, #ff7f00, #ffff00, #00ff00, #0000ff, #8b00ff)'],
            ['id' => 'gold', 'name' => 'Golden', 'cost' => 7500, 'gradient' => 'linear-gradient(135deg, #f7971e, #ffd200, #b38728)'],
            ['id' => 'diamond', 'name' => 'Diamond', 'cost' => 10000, 'gradient' => 'linear-gradient(135deg, #00CED1, #7FFFD4, #E0FFFF, #00CED1)'],
            ['id' => 'animated_fire', 'name' => 'Animated Fire', 'cost' => 8000, 'gradient' => 'linear-gradient(135deg, #f12711, #f5af19)', 'animated' => true],
            ['id' => 'animated_water', 'name' => 'Animated Water', 'cost' => 8000, 'gradient' => 'linear-gradient(135deg, #667eea, #764ba2)', 'animated' => true]
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
            ['id' => 'think', 'name' => ':think:', 'cost' => 300, 'emoji' => '🤔', 'display' => 'Hmm'],
            ['id' => 'wow', 'name' => ':wow:', 'cost' => 300, 'emoji' => '😮', 'display' => 'WOW'],
            ['id' => 'angry', 'name' => ':angry:', 'cost' => 300, 'emoji' => '😡', 'display' => 'Angry'],
            ['id' => 'clap', 'name' => ':clap:', 'cost' => 300, 'emoji' => '👏', 'display' => 'GG WP'],
            ['id' => 'skull', 'name' => ':skull:', 'cost' => 300, 'emoji' => '💀', 'display' => 'Dead']
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

function readShopData() {
    global $shopDataFile;
    if (!file_exists($shopDataFile)) return ['limitedItems' => []];
    return json_decode(file_get_contents($shopDataFile), true) ?: ['limitedItems' => []];
}

function writeShopData($data) {
    global $shopDataFile;
    file_put_contents($shopDataFile, json_encode($data, JSON_PRETTY_PRINT));
}

function ensureUserHasShopFields(&$user) {
    if (!isset($user['coins'])) $user['coins'] = 0;
    if (!isset($user['inventory'])) {
        $user['inventory'] = [
            'name_colors' => [],
            'borders' => [],
            'avatar_effects' => [],
            'avatar_rings' => [],
            'titles' => [],
            'banners' => [],
            'emotes' => [],
            'boosters' => [],
            'social' => []
        ];
    }
    // Ensure titles category exists for existing users
    if (!isset($user['inventory']['banners'])) {
        $user['inventory']['banners'] = [];
    }
    if (!isset($user['inventory']['titles'])) {
    if (!isset($user['inventory']['avatar_rings'])) {
        $user['inventory']['avatar_rings'] = [];
    }
        $user['inventory']['titles'] = [];
    }
    if (!isset($user['equipped'])) {
        $user['equipped'] = [
            'name_color' => null,
            'border' => null,
            'avatar_effect' => null,
            'avatar_ring' => null,
            'title' => null,
            'banner' => null
        ];
    }
    // Ensure title equip slot exists
    if (!isset($user['equipped']['title'])) {
    if (!isset($user['equipped']['avatar_ring'])) {
        $user['equipped']['avatar_ring'] = null;
    }
        $user['equipped']['title'] = null;
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

function getLimitedItemPurchaseCount($itemId) {
    $shopData = readShopData();
    return $shopData['limitedItems'][$itemId]['count'] ?? 0;
}

function incrementLimitedItemCount($itemId) {
    $shopData = readShopData();
    if (!isset($shopData['limitedItems'][$itemId])) {
        $shopData['limitedItems'][$itemId] = ['count' => 0, 'buyers' => []];
    }
    $shopData['limitedItems'][$itemId]['count']++;
    writeShopData($shopData);
}

function addLimitedItemBuyer($itemId, $username) {
    $shopData = readShopData();
    if (!isset($shopData['limitedItems'][$itemId])) {
        $shopData['limitedItems'][$itemId] = ['count' => 0, 'buyers' => []];
    }
    $shopData['limitedItems'][$itemId]['buyers'][] = $username;
    $shopData['limitedItems'][$itemId]['count'] = count($shopData['limitedItems'][$itemId]['buyers']);
    writeShopData($shopData);
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'items';
    
    if ($action === 'items') {
        // Add limited item info to shop data
        $shopData = readShopData();
        $shopWithLimits = $SHOP_ITEMS;
        
        // Add purchase counts for limited items
        foreach ($shopWithLimits as $catKey => &$category) {
            foreach ($category['items'] as &$item) {
                if (isset($item['limited'])) {
                    $item['purchaseCount'] = $shopData['limitedItems'][$item['id']]['count'] ?? 0;
                    $item['soldOut'] = $item['purchaseCount'] >= $item['limited'];
                }
            }
        }
        
        echo json_encode(['success' => true, 'shop' => $shopWithLimits]);
        exit();
    }
    
    if ($action === 'status') {
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
        
        $user = $data['users'][$username];
        
        echo json_encode([
            'success' => true,
            'equipped' => $user['equipped'] ?? []
        ]);
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
        
        // Check if limited item is sold out
        if (isset($item['limited'])) {
            $purchaseCount = getLimitedItemPurchaseCount($itemId);
            if ($purchaseCount >= $item['limited']) {
                echo json_encode(['success' => false, 'error' => 'This limited item is sold out!']);
                exit();
            }
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
            
            // Track limited item purchase
            if (isset($item['limited'])) {
                addLimitedItemBuyer($itemId, $username);
            }
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
        if ($category === 'titles') $equipKey = 'title';
        if ($category === 'banners') $equipKey = 'banner';
        if ($category === 'avatar_rings') $equipKey = 'avatar_ring';
        
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
        if ($category === 'titles') $equipKey = 'title';
        if ($category === 'banners') $equipKey = 'banner';
        if ($category === 'avatar_rings') $equipKey = 'avatar_ring';
        
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
