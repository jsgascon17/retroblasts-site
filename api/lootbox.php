<?php
/**
 * Loot Box System API
 */

session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

$usersFile = __DIR__ . "/../data/users.json";

// Crate definitions (unlocked with keys)
$CRATES = [
    "bronze_crate" => [
        "key" => "bronze_key",
        "drops" => [
            ["weight" => 30, "type" => "coins", "min" => 200, "max" => 500],
            ["weight" => 20, "type" => "consumable", "pool" => ["extra_life", "reroll_token"]],
            ["weight" => 20, "type" => "card", "pool" => ["cookie_bronze", "snake_bronze", "arcade_bronze"]],
            ["weight" => 15, "type" => "emote", "pool" => ["gg", "nice", "rip", "sad"]],
            ["weight" => 10, "type" => "name_color", "pool" => ["neon_green", "purple"]],
            ["weight" => 5, "type" => "key", "pool" => ["silver_key"]]
        ]
    ],
    "silver_crate" => [
        "key" => "silver_key",
        "drops" => [
            ["weight" => 20, "type" => "coins", "min" => 500, "max" => 1500],
            ["weight" => 20, "type" => "card", "pool" => ["cookie_silver", "snake_silver", "arcade_silver"]],
            ["weight" => 20, "type" => "consumable", "pool" => ["extra_life", "score_2x", "luck_boost"]],
            ["weight" => 15, "type" => "name_color", "pool" => ["gold", "fire", "ice"]],
            ["weight" => 10, "type" => "border", "pool" => ["silver", "bronze"]],
            ["weight" => 10, "type" => "booster", "pool" => ["xp_2x", "coins_2x"]],
            ["weight" => 5, "type" => "key", "pool" => ["gold_key"]]
        ]
    ],
    "gold_crate" => [
        "key" => "gold_key",
        "drops" => [
            ["weight" => 15, "type" => "coins", "min" => 2000, "max" => 5000],
            ["weight" => 20, "type" => "card", "pool" => ["cookie_gold", "snake_gold", "arcade_gold"]],
            ["weight" => 15, "type" => "consumable", "pool" => ["tournament_ticket", "gift_box", "luck_boost"]],
            ["weight" => 15, "type" => "name_color", "pool" => ["rainbow", "fire", "ice"]],
            ["weight" => 15, "type" => "border", "pool" => ["gold", "diamond"]],
            ["weight" => 10, "type" => "avatar_effect", "pool" => ["sparkle", "glow", "pulse"]],
            ["weight" => 5, "type" => "card", "pool" => ["legendary_dragon", "legendary_unicorn", "legendary_phoenix"]],
            ["weight" => 5, "type" => "border", "pool" => ["rainbow"]]
        ]
    ]
];

// Loot box definitions
$LOOT_BOXES = [
    "basic" => [
        "name" => "Basic Box",
        "cost" => 500,
        "icon" => "📦",
        "description" => "Common items with a chance for something special",
        "drops" => [
            ["weight" => 30, "type" => "coins", "min" => 100, "max" => 300],
            ["weight" => 18, "type" => "emote", "pool" => ["gg", "nice", "rip", "sad"]],
            ["weight" => 15, "type" => "card", "pool" => ["cookie_bronze", "snake_bronze", "arcade_bronze"]],
            ["weight" => 12, "type" => "name_color", "pool" => ["neon_green", "purple"]],
            ["weight" => 10, "type" => "key", "pool" => ["bronze_key"]],
            ["weight" => 8, "type" => "border", "pool" => ["bronze"]],
            ["weight" => 4, "type" => "crate", "pool" => ["bronze_crate"]],
            ["weight" => 3, "type" => "coins", "min" => 500, "max" => 1000]
        ]
    ],
    "premium" => [
        "name" => "Premium Box",
        "cost" => 1500,
        "icon" => "🎁",
        "description" => "Better odds for rare items",
        "drops" => [
            ["weight" => 18, "type" => "coins", "min" => 300, "max" => 800],
            ["weight" => 14, "type" => "card", "pool" => ["cookie_bronze", "cookie_silver", "snake_bronze", "snake_silver", "arcade_bronze", "arcade_silver"]],
            ["weight" => 12, "type" => "emote", "pool" => ["pog", "ez", "hype", "love", "laugh"]],
            ["weight" => 12, "type" => "name_color", "pool" => ["gold", "fire", "ice"]],
            ["weight" => 10, "type" => "border", "pool" => ["silver", "bronze"]],
            ["weight" => 10, "type" => "key", "pool" => ["bronze_key", "silver_key"]],
            ["weight" => 8, "type" => "crate", "pool" => ["bronze_crate", "silver_crate"]],
            ["weight" => 6, "type" => "avatar_effect", "pool" => ["sparkle", "bounce"]],
            ["weight" => 6, "type" => "consumable", "pool" => ["extra_life", "reroll_token"]],
            ["weight" => 4, "type" => "coins", "min" => 2000, "max" => 5000]
        ]
    ],
    "legendary" => [
        "name" => "Legendary Box",
        "cost" => 5000,
        "icon" => "👑",
        "description" => "Guaranteed rare or better!",
        "drops" => [
            ["weight" => 15, "type" => "coins", "min" => 1000, "max" => 3000],
            ["weight" => 14, "type" => "card", "pool" => ["cookie_silver", "cookie_gold", "snake_silver", "snake_gold", "arcade_silver", "arcade_gold"]],
            ["weight" => 12, "type" => "name_color", "pool" => ["rainbow", "fire", "ice"]],
            ["weight" => 12, "type" => "border", "pool" => ["gold", "diamond"]],
            ["weight" => 10, "type" => "avatar_effect", "pool" => ["sparkle", "glow", "pulse"]],
            ["weight" => 10, "type" => "key", "pool" => ["silver_key", "gold_key"]],
            ["weight" => 8, "type" => "crate", "pool" => ["silver_crate", "gold_crate"]],
            ["weight" => 7, "type" => "consumable", "pool" => ["score_2x", "luck_boost", "tournament_ticket"]],
            ["weight" => 5, "type" => "booster", "pool" => ["xp_2x", "coins_2x"]],
            ["weight" => 4, "type" => "card", "pool" => ["legendary_dragon", "legendary_unicorn", "legendary_phoenix"]],
            ["weight" => 3, "type" => "border", "pool" => ["rainbow"]]
        ]
    ]
];

// Item display names
$ITEM_NAMES = [
    "name_colors" => [
        "gold" => "Gold Name", "rainbow" => "Rainbow Name", "neon_green" => "Neon Green Name",
        "fire" => "Fire Name", "ice" => "Ice Name", "purple" => "Purple Name"
    ],
    "borders" => [
        "bronze" => "Bronze Border", "silver" => "Silver Border", "gold" => "Gold Border",
        "diamond" => "Diamond Border", "rainbow" => "Rainbow Border"
    ],
    "avatar_effects" => [
        "sparkle" => "Sparkle Effect", "bounce" => "Bounce Effect", "glow" => "Glow Effect",
        "shake" => "Shake Effect", "pulse" => "Pulse Effect"
    ],
    "emotes" => [
        "gg" => ":gg: Emote", "nice" => ":nice: Emote", "rip" => ":rip: Emote", "pog" => ":pog: Emote",
        "ez" => ":ez: Emote", "sad" => ":sad: Emote", "hype" => ":hype: Emote", "love" => ":love: Emote",
        "laugh" => ":laugh: Emote", "think" => ":think: Emote"
    ],
    "boosters" => [
        "xp_2x" => "2x XP Booster", "coins_2x" => "2x Coins Booster"
    ],
    "keys" => [
        "bronze_key" => "Bronze Key", "silver_key" => "Silver Key", "gold_key" => "Gold Key"
    ],
    "crates" => [
        "bronze_crate" => "Bronze Crate", "silver_crate" => "Silver Crate", "gold_crate" => "Gold Crate"
    ],
    "consumables" => [
        "reroll_token" => "Reroll Token", "extra_life" => "Extra Life", "score_2x" => "2x Score",
        "luck_boost" => "Luck Boost", "tournament_ticket" => "Tournament Ticket", "gift_box" => "Gift Box"
    ],
    "cards" => [
        "cookie_bronze" => "Cookie Bronze", "cookie_silver" => "Cookie Silver", "cookie_gold" => "Cookie Gold",
        "snake_bronze" => "Snake Bronze", "snake_silver" => "Snake Silver", "snake_gold" => "Snake Gold",
        "arcade_bronze" => "Arcade Bronze", "arcade_silver" => "Arcade Silver", "arcade_gold" => "Arcade Gold",
        "legendary_dragon" => "Dragon Card", "legendary_unicorn" => "Unicorn Card", "legendary_phoenix" => "Phoenix Card"
    ]
];

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ["users" => []];
    return json_decode(file_get_contents($usersFile), true) ?: ["users" => []];
}

function writeUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

function ensureUserHasFields(&$user) {
    if (!isset($user["coins"])) $user["coins"] = 0;
    if (!isset($user["inventory"])) {
        $user["inventory"] = [];
    }
    $fields = ["name_colors", "borders", "avatar_effects", "emotes", "boosters", "social", "lootboxes", "lockedCrates"];
    foreach ($fields as $f) {
        if (!isset($user["inventory"][$f])) $user["inventory"][$f] = [];
    }
    if (!isset($user["inventory"]["keys"])) {
        $user["inventory"]["keys"] = ["bronze_key" => 0, "silver_key" => 0, "gold_key" => 0];
    }
    if (!isset($user["inventory"]["consumables"])) {
        $user["inventory"]["consumables"] = [];
    }
    if (!isset($user["inventory"]["tradingCards"])) {
        $user["inventory"]["tradingCards"] = [];
    }
}

function rollDrop($drops) {
    $totalWeight = array_sum(array_column($drops, "weight"));
    $roll = mt_rand(1, $totalWeight);
    $cumulative = 0;
    
    foreach ($drops as $drop) {
        $cumulative += $drop["weight"];
        if ($roll <= $cumulative) {
            return $drop;
        }
    }
    
    return $drops[0];
}

function processDrop($drop, &$user, $boxCost = 0) {
    global $ITEM_NAMES;
    $reward = [];
    
    if ($drop["type"] === "coins") {
        $amount = mt_rand($drop["min"], $drop["max"]);
        $user["coins"] += $amount;
        $reward = [
            "type" => "coins",
            "amount" => $amount,
            "name" => $amount . " Coins",
            "icon" => "🪙",
            "rarity" => $amount >= 1000 ? "rare" : ($amount >= 500 ? "uncommon" : "common")
        ];
    } elseif ($drop["type"] === "key") {
        $keyType = $drop["pool"][array_rand($drop["pool"])];
        if (!isset($user["inventory"]["keys"])) {
            $user["inventory"]["keys"] = ["bronze_key" => 0, "silver_key" => 0, "gold_key" => 0];
        }
        $user["inventory"]["keys"][$keyType] = ($user["inventory"]["keys"][$keyType] ?? 0) + 1;
        
        $icons = ["bronze_key" => "🗝️", "silver_key" => "🔑", "gold_key" => "🔐"];
        $rarities = ["bronze_key" => "uncommon", "silver_key" => "rare", "gold_key" => "legendary"];
        $reward = [
            "type" => "key",
            "itemId" => $keyType,
            "name" => $ITEM_NAMES["keys"][$keyType] ?? $keyType,
            "icon" => $icons[$keyType] ?? "🔑",
            "rarity" => $rarities[$keyType] ?? "uncommon"
        ];
    } elseif ($drop["type"] === "crate") {
        $crateType = $drop["pool"][array_rand($drop["pool"])];
        $user["inventory"]["lockedCrates"][] = $crateType;
        
        $icons = ["bronze_crate" => "📦", "silver_crate" => "🎁", "gold_crate" => "👑"];
        $rarities = ["bronze_crate" => "uncommon", "silver_crate" => "rare", "gold_crate" => "legendary"];
        $reward = [
            "type" => "crate",
            "itemId" => $crateType,
            "name" => $ITEM_NAMES["crates"][$crateType] ?? $crateType,
            "icon" => $icons[$crateType] ?? "📦",
            "rarity" => $rarities[$crateType] ?? "uncommon"
        ];
    } elseif ($drop["type"] === "consumable") {
        $itemId = $drop["pool"][array_rand($drop["pool"])];
        if (!isset($user["inventory"]["consumables"][$itemId])) {
            $user["inventory"]["consumables"][$itemId] = 0;
        }
        $user["inventory"]["consumables"][$itemId]++;
        
        $icons = [
            "reroll_token" => "🔄", "extra_life" => "❤️", "score_2x" => "✖️",
            "luck_boost" => "🍀", "tournament_ticket" => "🎫", "gift_box" => "🎁"
        ];
        $reward = [
            "type" => "consumable",
            "itemId" => $itemId,
            "name" => $ITEM_NAMES["consumables"][$itemId] ?? $itemId,
            "icon" => $icons[$itemId] ?? "🎟️",
            "rarity" => in_array($itemId, ["tournament_ticket", "luck_boost"]) ? "rare" : "uncommon"
        ];
    } elseif ($drop["type"] === "card") {
        $cardId = $drop["pool"][array_rand($drop["pool"])];
        if (!isset($user["inventory"]["tradingCards"][$cardId])) {
            $user["inventory"]["tradingCards"][$cardId] = 0;
        }
        $user["inventory"]["tradingCards"][$cardId]++;
        
        $icons = [
            "cookie_bronze" => "🍪", "cookie_silver" => "🍪", "cookie_gold" => "🍪",
            "snake_bronze" => "🐍", "snake_silver" => "🐍", "snake_gold" => "🐍",
            "arcade_bronze" => "🕹️", "arcade_silver" => "🕹️", "arcade_gold" => "🕹️",
            "legendary_dragon" => "🐉", "legendary_unicorn" => "🦄", "legendary_phoenix" => "🔥"
        ];
        $rarities = [];
        foreach (["cookie_bronze", "snake_bronze", "arcade_bronze"] as $c) $rarities[$c] = "common";
        foreach (["cookie_silver", "snake_silver", "arcade_silver"] as $c) $rarities[$c] = "uncommon";
        foreach (["cookie_gold", "snake_gold", "arcade_gold"] as $c) $rarities[$c] = "rare";
        foreach (["legendary_dragon", "legendary_unicorn", "legendary_phoenix"] as $c) $rarities[$c] = "legendary";
        
        $reward = [
            "type" => "card",
            "itemId" => $cardId,
            "name" => $ITEM_NAMES["cards"][$cardId] ?? $cardId,
            "icon" => $icons[$cardId] ?? "🃏",
            "rarity" => $rarities[$cardId] ?? "common"
        ];
    } else {
        $itemId = $drop["pool"][array_rand($drop["pool"])];
        $category = $drop["type"] === "emote" ? "emotes" : 
                    ($drop["type"] === "name_color" ? "name_colors" : 
                    ($drop["type"] === "booster" ? "boosters" :
                    ($drop["type"] === "avatar_effect" ? "avatar_effects" : 
                    $drop["type"] . "s")));
        
        // Check if already owned (except boosters)
        if ($category !== "boosters" && in_array($itemId, $user["inventory"][$category] ?? [])) {
            $coinValue = max(100, $boxCost / 2);
            $user["coins"] += $coinValue;
            $reward = [
                "type" => "coins",
                "amount" => $coinValue,
                "name" => intval($coinValue) . " Coins (duplicate)",
                "icon" => "🪙",
                "rarity" => "common",
                "duplicate" => true
            ];
        } else {
            if (!isset($user["inventory"][$category])) $user["inventory"][$category] = [];
            $user["inventory"][$category][] = $itemId;
            
            $rarity = "common";
            if (in_array($itemId, ["rainbow", "diamond", "glow", "pulse"])) $rarity = "legendary";
            else if (in_array($itemId, ["gold", "fire", "ice", "sparkle"])) $rarity = "rare";
            else if (in_array($itemId, ["silver", "purple", "bounce"])) $rarity = "uncommon";
            
            $reward = [
                "type" => "item",
                "category" => $category,
                "itemId" => $itemId,
                "name" => $ITEM_NAMES[$category][$itemId] ?? $itemId,
                "icon" => $drop["type"] === "emote" ? "😀" : ($drop["type"] === "border" ? "🖼️" : ($drop["type"] === "avatar_effect" ? "✨" : ($drop["type"] === "booster" ? "⚡" : "🎨"))),
                "rarity" => $rarity
            ];
        }
    }
    
    return $reward;
}

// GET requests
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    global $LOOT_BOXES;
    $action = $_GET["action"] ?? "boxes";
    
    if ($action === "boxes") {
        $boxes = [];
        foreach ($LOOT_BOXES as $id => $box) {
            $boxes[] = [
                "id" => $id,
                "name" => $box["name"],
                "cost" => $box["cost"],
                "icon" => $box["icon"],
                "description" => $box["description"]
            ];
        }
        echo json_encode(["success" => true, "boxes" => $boxes]);
        exit();
    }
    
    echo json_encode(["success" => false, "error" => "Invalid action"]);
    exit();
}

// POST requests
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    global $LOOT_BOXES, $ITEM_NAMES, $CRATES;
    
    if (!isset($_SESSION["user"])) {
        echo json_encode(["success" => false, "error" => "Not logged in"]);
        exit();
    }
    
    $currentUser = $_SESSION["user"];
    $input = json_decode(file_get_contents("php://input"), true);
    $action = $input["action"] ?? "";
    
    $usersData = readUsers();
    
    if (!isset($usersData["users"][$currentUser])) {
        echo json_encode(["success" => false, "error" => "User not found"]);
        exit();
    }
    
    $user = &$usersData["users"][$currentUser];
    ensureUserHasFields($user);
    
    if ($action === "open") {
        $boxType = $input["boxType"] ?? "basic";
        
        if (!isset($LOOT_BOXES[$boxType])) {
            echo json_encode(["success" => false, "error" => "Invalid box type"]);
            exit();
        }
        
        $box = $LOOT_BOXES[$boxType];
        
        if ($user["coins"] < $box["cost"]) {
            echo json_encode(["success" => false, "error" => "Not enough coins"]);
            exit();
        }
        
        $user["coins"] -= $box["cost"];
        $drop = rollDrop($box["drops"]);
        $reward = processDrop($drop, $user, $box["cost"]);
        
        writeUsers($usersData);
        
        echo json_encode([
            "success" => true,
            "reward" => $reward,
            "coins" => $user["coins"]
        ]);
        exit();
    }
    
    if ($action === "openFromInventory") {
        $boxType = $input["boxType"] ?? "basic";
        
        if (!isset($user["inventory"]["lootboxes"]) || !is_array($user["inventory"]["lootboxes"])) {
            echo json_encode(["success" => false, "error" => "No boxes in inventory"]);
            exit();
        }
        
        $index = array_search($boxType, $user["inventory"]["lootboxes"]);
        if ($index === false) {
            echo json_encode(["success" => false, "error" => "No " . $boxType . " boxes in inventory"]);
            exit();
        }
        
        array_splice($user["inventory"]["lootboxes"], $index, 1);
        
        $box = $LOOT_BOXES[$boxType];
        $drop = rollDrop($box["drops"]);
        $reward = processDrop($drop, $user, $box["cost"]);
        
        writeUsers($usersData);
        
        echo json_encode([
            "success" => true,
            "reward" => $reward,
            "coins" => $user["coins"],
            "inventory" => $user["inventory"]
        ]);
        exit();
    }
    
    if ($action === "openCrate") {
        $crateType = $input["crateType"] ?? "";
        
        if (!isset($CRATES[$crateType])) {
            echo json_encode(["success" => false, "error" => "Invalid crate type"]);
            exit();
        }
        
        $crate = $CRATES[$crateType];
        $keyType = $crate["key"];
        
        // Check if user has the crate
        $crateIndex = array_search($crateType, $user["inventory"]["lockedCrates"] ?? []);
        if ($crateIndex === false) {
            echo json_encode(["success" => false, "error" => "You don't have this crate"]);
            exit();
        }
        
        // Check if user has the key
        if (($user["inventory"]["keys"][$keyType] ?? 0) < 1) {
            echo json_encode(["success" => false, "error" => "You need a " . $ITEM_NAMES["keys"][$keyType] . " to open this"]);
            exit();
        }
        
        // Remove crate and key
        array_splice($user["inventory"]["lockedCrates"], $crateIndex, 1);
        $user["inventory"]["keys"][$keyType]--;
        
        // Roll for reward
        $drop = rollDrop($crate["drops"]);
        $reward = processDrop($drop, $user, 1000);
        
        writeUsers($usersData);
        
        echo json_encode([
            "success" => true,
            "reward" => $reward,
            "coins" => $user["coins"],
            "inventory" => $user["inventory"]
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
    
    echo json_encode(["success" => false, "error" => "Invalid action"]);
    exit();
}

http_response_code(405);
echo json_encode(["success" => false, "error" => "Method not allowed"]);
