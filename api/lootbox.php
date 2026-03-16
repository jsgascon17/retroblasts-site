<?php
/**
 * Loot Box System API
 * 
 * GET:
 *   ?action=boxes      - Get available loot boxes
 * 
 * POST:
 *   { "action": "open", "boxType": "basic" }
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

// Loot box definitions
$LOOT_BOXES = [
    'basic' => [
        'name' => 'Basic Box',
        'cost' => 500,
        'icon' => '📦',
        'description' => 'Common items with a chance for something special',
        'drops' => [
            ['weight' => 40, 'type' => 'coins', 'min' => 100, 'max' => 300],
            ['weight' => 25, 'type' => 'emote', 'pool' => ['gg', 'nice', 'rip', 'sad']],
            ['weight' => 20, 'type' => 'name_color', 'pool' => ['neon_green', 'purple']],
            ['weight' => 10, 'type' => 'border', 'pool' => ['bronze']],
            ['weight' => 5, 'type' => 'coins', 'min' => 500, 'max' => 1000]
        ]
    ],
    'premium' => [
        'name' => 'Premium Box',
        'cost' => 1500,
        'icon' => '🎁',
        'description' => 'Better odds for rare items',
        'drops' => [
            ['weight' => 25, 'type' => 'coins', 'min' => 300, 'max' => 800],
            ['weight' => 20, 'type' => 'emote', 'pool' => ['pog', 'ez', 'hype', 'love', 'laugh']],
            ['weight' => 20, 'type' => 'name_color', 'pool' => ['gold', 'fire', 'ice']],
            ['weight' => 15, 'type' => 'border', 'pool' => ['silver', 'bronze']],
            ['weight' => 10, 'type' => 'avatar_effect', 'pool' => ['sparkle', 'bounce']],
            ['weight' => 7, 'type' => 'border', 'pool' => ['gold']],
            ['weight' => 3, 'type' => 'coins', 'min' => 2000, 'max' => 5000]
        ]
    ],
    'legendary' => [
        'name' => 'Legendary Box',
        'cost' => 5000,
        'icon' => '👑',
        'description' => 'Guaranteed rare or better!',
        'drops' => [
            ['weight' => 25, 'type' => 'coins', 'min' => 1000, 'max' => 3000],
            ['weight' => 20, 'type' => 'name_color', 'pool' => ['rainbow', 'fire', 'ice']],
            ['weight' => 20, 'type' => 'border', 'pool' => ['gold', 'diamond']],
            ['weight' => 15, 'type' => 'avatar_effect', 'pool' => ['sparkle', 'glow', 'pulse']],
            ['weight' => 10, 'type' => 'booster', 'pool' => ['xp_2x', 'coins_2x']],
            ['weight' => 7, 'type' => 'border', 'pool' => ['rainbow']],
            ['weight' => 3, 'type' => 'coins', 'min' => 10000, 'max' => 20000]
        ]
    ]
];

// Item display names
$ITEM_NAMES = [
    'name_colors' => [
        'gold' => 'Gold Name', 'rainbow' => 'Rainbow Name', 'neon_green' => 'Neon Green Name',
        'fire' => 'Fire Name', 'ice' => 'Ice Name', 'purple' => 'Purple Name'
    ],
    'borders' => [
        'bronze' => 'Bronze Border', 'silver' => 'Silver Border', 'gold' => 'Gold Border',
        'diamond' => 'Diamond Border', 'rainbow' => 'Rainbow Border'
    ],
    'avatar_effects' => [
        'sparkle' => 'Sparkle Effect', 'bounce' => 'Bounce Effect', 'glow' => 'Glow Effect',
        'shake' => 'Shake Effect', 'pulse' => 'Pulse Effect'
    ],
    'emotes' => [
        'gg' => ':gg: Emote', 'nice' => ':nice: Emote', 'rip' => ':rip: Emote', 'pog' => ':pog: Emote',
        'ez' => ':ez: Emote', 'sad' => ':sad: Emote', 'hype' => ':hype: Emote', 'love' => ':love: Emote',
        'laugh' => ':laugh: Emote', 'think' => ':think: Emote'
    ],
    'boosters' => [
        'xp_2x' => '2x XP Booster', 'coins_2x' => '2x Coins Booster'
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

function rollDrop($drops) {
    $totalWeight = array_sum(array_column($drops, 'weight'));
    $roll = mt_rand(1, $totalWeight);
    $cumulative = 0;
    
    foreach ($drops as $drop) {
        $cumulative += $drop['weight'];
        if ($roll <= $cumulative) {
            return $drop;
        }
    }
    
    return $drops[0];
}

// GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    global $LOOT_BOXES;
    $action = $_GET['action'] ?? 'boxes';
    
    if ($action === 'boxes') {
        $boxes = [];
        foreach ($LOOT_BOXES as $id => $box) {
            $boxes[] = [
                'id' => $id,
                'name' => $box['name'],
                'cost' => $box['cost'],
                'icon' => $box['icon'],
                'description' => $box['description']
            ];
        }
        echo json_encode(['success' => true, 'boxes' => $boxes]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

// POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $LOOT_BOXES, $ITEM_NAMES;
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit();
    }
    
    $currentUser = $_SESSION['user'];
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $usersData = readUsers();
    
    if (!isset($usersData['users'][$currentUser])) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    $user = &$usersData['users'][$currentUser];
    ensureUserHasFields($user);
    
    if ($action === 'open') {
        $boxType = $input['boxType'] ?? 'basic';
        
        if (!isset($LOOT_BOXES[$boxType])) {
            echo json_encode(['success' => false, 'error' => 'Invalid box type']);
            exit();
        }
        
        $box = $LOOT_BOXES[$boxType];
        
        if ($user['coins'] < $box['cost']) {
            echo json_encode(['success' => false, 'error' => 'Not enough coins']);
            exit();
        }
        
        // Deduct cost
        $user['coins'] -= $box['cost'];
        
        // Roll for reward
        $drop = rollDrop($box['drops']);
        $reward = [];
        
        if ($drop['type'] === 'coins') {
            $amount = mt_rand($drop['min'], $drop['max']);
            $user['coins'] += $amount;
            $reward = [
                'type' => 'coins',
                'amount' => $amount,
                'name' => $amount . ' Coins',
                'icon' => '🪙',
                'rarity' => $amount >= 1000 ? 'rare' : ($amount >= 500 ? 'uncommon' : 'common')
            ];
        } else {
            $itemId = $drop['pool'][array_rand($drop['pool'])];
            $category = $drop['type'] === 'emote' ? 'emotes' : 
                        ($drop['type'] === 'name_color' ? 'name_colors' : 
                        ($drop['type'] === 'booster' ? 'boosters' :
                        ($drop['type'] === 'avatar_effect' ? 'avatar_effects' : 
                        $drop['type'] . 's')));
            
            // Check if already owned (except boosters)
            $alreadyOwned = false;
            if ($category !== 'boosters' && in_array($itemId, $user['inventory'][$category] ?? [])) {
                // Give coins instead
                $coinValue = $box['cost'] / 2;
                $user['coins'] += $coinValue;
                $reward = [
                    'type' => 'coins',
                    'amount' => $coinValue,
                    'name' => intval($coinValue) . ' Coins (duplicate)',
                    'icon' => '🪙',
                    'rarity' => 'common',
                    'duplicate' => true
                ];
                $alreadyOwned = true;
            }
            
            if (!$alreadyOwned) {
                $user['inventory'][$category][] = $itemId;
                
                $rarity = 'common';
                if (in_array($itemId, ['rainbow', 'diamond', 'glow', 'pulse'])) $rarity = 'legendary';
                else if (in_array($itemId, ['gold', 'fire', 'ice', 'sparkle'])) $rarity = 'rare';
                else if (in_array($itemId, ['silver', 'purple', 'bounce'])) $rarity = 'uncommon';
                
                $reward = [
                    'type' => 'item',
                    'category' => $category,
                    'itemId' => $itemId,
                    'name' => $ITEM_NAMES[$category][$itemId] ?? $itemId,
                    'icon' => $drop['type'] === 'emote' ? '😀' : ($drop['type'] === 'border' ? '🖼️' : ($drop['type'] === 'avatar_effect' ? '✨' : '🎨')),
                    'rarity' => $rarity
                ];
            }
        }
        
        writeUsers($usersData);
        
        echo json_encode([
            'success' => true,
            'reward' => $reward,
            'coins' => $user['coins']
        ]);
        exit();
    }
    
    if ($action === "buyBundle") {
        $boxType = $input["boxType"] ?? "basic";
        $count = intval($input["count"] ?? 1);
        $price = intval($input["price"] ?? 0);
        
        $validBundles = [
            "basic_3" => 1250, "basic_10" => 3750,
            "premium_3" => 3750, "legendary_3" => 12000
        ];
        
        $bundleKey = $boxType . "_" . $count;
        if (!isset($validBundles[$bundleKey]) || $validBundles[$bundleKey] !== $price) {
            echo json_encode(["success" => false, "error" => "Invalid bundle"]);
            exit();
        }
        
        if ($user["coins"] < $price) {
            echo json_encode(["success" => false, "error" => "Not enough coins"]);
            exit();
        }
        
        $user["coins"] -= $price;
        
        if (!isset($user["inventory"]["lootboxes"])) $user["inventory"]["lootboxes"] = [];
        for ($i = 0; $i < $count; $i++) {
            $user["inventory"]["lootboxes"][] = $boxType;
        }
        
        writeUsers($usersData);
        
        echo json_encode([
            "success" => true,
            "coins" => $user["coins"],
            "boxesAdded" => $count
        ]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
