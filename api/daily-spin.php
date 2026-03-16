<?php
session_start();
header("Content-Type: application/json");

$usersFile = __DIR__ . "/../data/users.json";

$PRIZES = [
    ["name" => "100 Coins", "type" => "coins", "amount" => 100, "weight" => 20, "color" => "#ffd700"],
    ["name" => "250 Coins", "type" => "coins", "amount" => 250, "weight" => 15, "color" => "#ffd700"],
    ["name" => "500 Coins", "type" => "coins", "amount" => 500, "weight" => 10, "color" => "#ffd700"],
    ["name" => "1000 Coins", "type" => "coins", "amount" => 1000, "weight" => 5, "color" => "#ffd700"],
    ["name" => "Basic Box", "type" => "lootbox", "boxType" => "basic", "weight" => 10, "color" => "#60a5fa"],
    ["name" => "Premium Box", "type" => "lootbox", "boxType" => "premium", "weight" => 4, "color" => "#a855f7"],
    ["name" => "2x XP (1hr)", "type" => "boost", "boostType" => "xp_2x", "weight" => 8, "color" => "#4ade80"],
    ["name" => "Bronze Key", "type" => "key", "keyType" => "bronze_key", "weight" => 10, "color" => "#cd7f32"],
    ["name" => "Silver Key", "type" => "key", "keyType" => "silver_key", "weight" => 5, "color" => "#c0c0c0"],
    ["name" => "Reroll Token", "type" => "consumable", "item" => "reroll_token", "weight" => 8, "color" => "#3b82f6"],
    ["name" => "Try Again", "type" => "nothing", "weight" => 5, "color" => "#666"]
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

function rollPrize() {
    global $PRIZES;
    $totalWeight = array_sum(array_column($PRIZES, "weight"));
    $roll = mt_rand(1, $totalWeight);
    $cumulative = 0;
    foreach ($PRIZES as $index => $prize) {
        $cumulative += $prize["weight"];
        if ($roll <= $cumulative) {
            return ["index" => $index, "prize" => $prize];
        }
    }
    return ["index" => 0, "prize" => $PRIZES[0]];
}

function ensureInventoryFields(&$user) {
    if (!isset($user["inventory"])) $user["inventory"] = [];
    if (!isset($user["inventory"]["keys"])) {
        $user["inventory"]["keys"] = ["bronze_key" => 0, "silver_key" => 0, "gold_key" => 0];
    }
    if (!isset($user["inventory"]["consumables"])) {
        $user["inventory"]["consumables"] = [];
    }
    if (!isset($user["inventory"]["lootboxes"])) {
        $user["inventory"]["lootboxes"] = [];
    }
    if (!isset($user["inventory"]["boosters"])) {
        $user["inventory"]["boosters"] = [];
    }
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    global $PRIZES;
    
    $input = json_decode(file_get_contents("php://input"), true) ?? [];
    $action = $input["action"] ?? $_GET["action"] ?? "";
    
    $canSpin = false;
    $nextSpin = null;
    $rerollTokens = 0;
    
    if (isset($_SESSION["user"])) {
        $users = readUsers();
        $user = $users["users"][$_SESSION["user"]] ?? null;
        if ($user) {
            $lastSpin = $user["lastDailySpin"] ?? 0;
            $now = time();
            $nextSpinTime = strtotime("tomorrow", $lastSpin);
            $canSpin = ($now >= $nextSpinTime) || ($lastSpin === 0);
            if (!$canSpin) {
                $nextSpin = $nextSpinTime - $now;
            }
            $rerollTokens = $user["inventory"]["consumables"]["reroll_token"] ?? 0;
        }
    }
    
    $prizes = array_map(function($p) {
        return ["name" => $p["name"], "color" => $p["color"]];
    }, $PRIZES);
    
    echo json_encode([
        "success" => true,
        "prizes" => $prizes,
        "canSpin" => $canSpin,
        "nextSpinIn" => $nextSpin,
        "rerollTokens" => $rerollTokens
    ]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_SESSION["user"])) {
        echo json_encode(["success" => false, "error" => "Not logged in"]);
        exit();
    }
    
    $input = json_decode(file_get_contents("php://input"), true) ?? [];
    $action = $input["action"] ?? "spin";
    
    $users = readUsers();
    $username = $_SESSION["user"];
    
    if (!isset($users["users"][$username])) {
        echo json_encode(["success" => false, "error" => "User not found"]);
        exit();
    }
    
    $user = &$users["users"][$username];
    ensureInventoryFields($user);
    
    // Handle reroll
    if ($action === "reroll") {
        $rerollTokens = $user["inventory"]["consumables"]["reroll_token"] ?? 0;
        if ($rerollTokens < 1) {
            echo json_encode(["success" => false, "error" => "No reroll tokens"]);
            exit();
        }
        
        // Use reroll token
        $user["inventory"]["consumables"]["reroll_token"]--;
        
        $result = rollPrize();
        $prize = $result["prize"];
        
        // Apply prize
        applyPrize($prize, $user);
        
        writeUsers($users);
        
        echo json_encode([
            "success" => true,
            "prizeIndex" => $result["index"],
            "prize" => $prize,
            "newCoins" => $user["coins"] ?? 0,
            "rerollTokens" => $user["inventory"]["consumables"]["reroll_token"] ?? 0,
            "isReroll" => true
        ]);
        exit();
    }
    
    // Normal daily spin
    $lastSpin = $user["lastDailySpin"] ?? 0;
    $now = time();
    $nextSpinTime = strtotime("tomorrow", $lastSpin);
    
    if ($now < $nextSpinTime && $lastSpin !== 0) {
        echo json_encode(["success" => false, "error" => "Already spun today", "nextSpinIn" => $nextSpinTime - $now]);
        exit();
    }
    
    $result = rollPrize();
    $prize = $result["prize"];
    
    // Apply prize
    applyPrize($prize, $user);
    
    $user["lastDailySpin"] = $now;
    writeUsers($users);
    
    echo json_encode([
        "success" => true,
        "prizeIndex" => $result["index"],
        "prize" => $prize,
        "newCoins" => $user["coins"] ?? 0,
        "rerollTokens" => $user["inventory"]["consumables"]["reroll_token"] ?? 0
    ]);
    exit();
}

function applyPrize($prize, &$user) {
    if ($prize["type"] === "coins") {
        $user["coins"] = ($user["coins"] ?? 0) + $prize["amount"];
    } elseif ($prize["type"] === "lootbox") {
        $user["inventory"]["lootboxes"][] = $prize["boxType"];
    } elseif ($prize["type"] === "boost") {
        if (!isset($user["inventory"]["boosters"])) $user["inventory"]["boosters"] = [];
        $user["inventory"]["boosters"][] = $prize["boostType"];
    } elseif ($prize["type"] === "key") {
        $keyType = $prize["keyType"];
        $user["inventory"]["keys"][$keyType] = ($user["inventory"]["keys"][$keyType] ?? 0) + 1;
    } elseif ($prize["type"] === "consumable") {
        $item = $prize["item"];
        $user["inventory"]["consumables"][$item] = ($user["inventory"]["consumables"][$item] ?? 0) + 1;
    }
    // "nothing" type does nothing
}
