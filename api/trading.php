<?php
header("Content-Type: application/json");
session_start();

$tradesFile = __DIR__ . "/../data/trades.json";
$usersFile = __DIR__ . "/../data/users.json";

function loadTrades() {
    global $tradesFile;
    if (!file_exists($tradesFile)) return ["trades" => [], "nextId" => 1];
    return json_decode(file_get_contents($tradesFile), true) ?: ["trades" => [], "nextId" => 1];
}

function saveTrades($data) {
    global $tradesFile;
    file_put_contents($tradesFile, json_encode($data, JSON_PRETTY_PRINT));
}

function loadUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ["users" => []];
    return json_decode(file_get_contents($usersFile), true) ?: ["users" => []];
}

function saveUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

function getUserInventory($username) {
    $users = loadUsers();
    return $users["users"][$username]["inventory"] ?? [
        "lootboxes" => [],
        "boosters" => [],
        "tradingCards" => []
    ];
}

function removeItemFromInventory(&$inventory, $type, $item) {
    if ($type === "lootboxes" || $type === "boosters") {
        $idx = array_search($item, $inventory[$type] ?? []);
        if ($idx !== false) {
            array_splice($inventory[$type], $idx, 1);
            return true;
        }
    } elseif ($type === "tradingCards") {
        if (isset($inventory[$type][$item]) && $inventory[$type][$item] > 0) {
            $inventory[$type][$item]--;
            if ($inventory[$type][$item] <= 0) {
                unset($inventory[$type][$item]);
            }
            return true;
        }
    }
    return false;
}

function addItemToInventory(&$inventory, $type, $item) {
    if ($type === "lootboxes" || $type === "boosters") {
        $inventory[$type][] = $item;
    } elseif ($type === "tradingCards") {
        if (!isset($inventory[$type])) $inventory[$type] = [];
        $inventory[$type][$item] = ($inventory[$type][$item] ?? 0) + 1;
    }
}

// Handle GET requests
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $action = $_GET["action"] ?? "";
    
    if (!isset($_SESSION["user"])) {
        echo json_encode(["success" => false, "error" => "Not logged in"]);
        exit;
    }
    
    $username = $_SESSION["user"];
    
    // Get pending trades for user
    if ($action === "pending") {
        $data = loadTrades();
        $pending = [];
        
        foreach ($data["trades"] as $id => $trade) {
            if ($trade["status"] !== "pending") continue;
            if ($trade["from"] === $username || $trade["to"] === $username) {
                $trade["id"] = $id;
                $pending[] = $trade;
            }
        }
        
        echo json_encode(["success" => true, "trades" => $pending]);
        exit;
    }
    
    // Get trade history
    if ($action === "history") {
        $data = loadTrades();
        $history = [];
        
        foreach ($data["trades"] as $id => $trade) {
            if ($trade["from"] === $username || $trade["to"] === $username) {
                $trade["id"] = $id;
                $history[] = $trade;
            }
        }
        
        // Sort by date descending
        usort($history, fn($a, $b) => strtotime($b["created"]) - strtotime($a["created"]));
        $history = array_slice($history, 0, 20);
        
        echo json_encode(["success" => true, "trades" => $history]);
        exit;
    }
    
    // Get my inventory for trading
    if ($action === "myItems") {
        $inventory = getUserInventory($username);
        echo json_encode(["success" => true, "inventory" => $inventory]);
        exit;
    }
    
    // Get friends list
    if ($action === "friends") {
        $users = loadUsers();
        $friends = $users["users"][$username]["friends"] ?? [];
        echo json_encode(["success" => true, "friends" => $friends]);
        exit;
    }
}

// Handle POST requests
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);
    $action = $input["action"] ?? "";
    
    if (!isset($_SESSION["user"])) {
        echo json_encode(["success" => false, "error" => "Not logged in"]);
        exit;
    }
    
    $username = $_SESSION["user"];
    
    // Create trade offer
    if ($action === "create") {
        $toUser = $input["to"] ?? "";
        $offerItems = $input["offerItems"] ?? [];
        $requestItems = $input["requestItems"] ?? [];
        $offerCoins = intval($input["offerCoins"] ?? 0);
        $requestCoins = intval($input["requestCoins"] ?? 0);
        
        if ($toUser === $username) {
            echo json_encode(["success" => false, "error" => "Cannot trade with yourself"]);
            exit;
        }
        
        $users = loadUsers();
        
        if (!isset($users["users"][$toUser])) {
            echo json_encode(["success" => false, "error" => "User not found"]);
            exit;
        }
        
        // Check if friends
        $friends = $users["users"][$username]["friends"] ?? [];
        if (!in_array($toUser, $friends)) {
            echo json_encode(["success" => false, "error" => "You can only trade with friends"]);
            exit;
        }
        
        // Validate offer coins
        $myCoins = $users["users"][$username]["coins"] ?? 0;
        if ($offerCoins > $myCoins) {
            echo json_encode(["success" => false, "error" => "Not enough coins"]);
            exit;
        }
        
        // Validate offer items exist in inventory
        $myInventory = getUserInventory($username);
        foreach ($offerItems as $item) {
            $type = $item["type"];
            $name = $item["name"];
            
            if ($type === "lootboxes" || $type === "boosters") {
                if (!in_array($name, $myInventory[$type] ?? [])) {
                    echo json_encode(["success" => false, "error" => "You dont have that item"]);
                    exit;
                }
            } elseif ($type === "tradingCards") {
                if (($myInventory[$type][$name] ?? 0) < 1) {
                    echo json_encode(["success" => false, "error" => "You dont have that card"]);
                    exit;
                }
            }
        }
        
        // Create trade
        $data = loadTrades();
        $tradeId = "t" . $data["nextId"];
        $data["nextId"]++;
        
        $data["trades"][$tradeId] = [
            "from" => $username,
            "to" => $toUser,
            "offerItems" => $offerItems,
            "requestItems" => $requestItems,
            "offerCoins" => $offerCoins,
            "requestCoins" => $requestCoins,
            "status" => "pending",
            "created" => date("Y-m-d H:i:s")
        ];
        
        saveTrades($data);
        
        echo json_encode(["success" => true, "tradeId" => $tradeId]);
        exit;
    }
    
    // Accept trade
    if ($action === "accept") {
        $tradeId = $input["tradeId"] ?? "";
        
        $data = loadTrades();
        
        if (!isset($data["trades"][$tradeId])) {
            echo json_encode(["success" => false, "error" => "Trade not found"]);
            exit;
        }
        
        $trade = &$data["trades"][$tradeId];
        
        if ($trade["to"] !== $username) {
            echo json_encode(["success" => false, "error" => "This trade is not for you"]);
            exit;
        }
        
        if ($trade["status"] !== "pending") {
            echo json_encode(["success" => false, "error" => "Trade already processed"]);
            exit;
        }
        
        $users = loadUsers();
        $fromUser = $trade["from"];
        
        // Validate both parties still have items
        $fromInventory = &$users["users"][$fromUser]["inventory"];
        $toInventory = &$users["users"][$username]["inventory"];
        
        // Check sender still has offer items
        foreach ($trade["offerItems"] as $item) {
            $type = $item["type"];
            $name = $item["name"];
            if ($type === "tradingCards") {
                if (($fromInventory[$type][$name] ?? 0) < 1) {
                    $trade["status"] = "cancelled";
                    saveTrades($data);
                    echo json_encode(["success" => false, "error" => "Sender no longer has those items"]);
                    exit;
                }
            } else {
                if (!in_array($name, $fromInventory[$type] ?? [])) {
                    $trade["status"] = "cancelled";
                    saveTrades($data);
                    echo json_encode(["success" => false, "error" => "Sender no longer has those items"]);
                    exit;
                }
            }
        }
        
        // Check receiver has request items
        foreach ($trade["requestItems"] as $item) {
            $type = $item["type"];
            $name = $item["name"];
            if ($type === "tradingCards") {
                if (($toInventory[$type][$name] ?? 0) < 1) {
                    echo json_encode(["success" => false, "error" => "You no longer have the requested items"]);
                    exit;
                }
            } else {
                if (!in_array($name, $toInventory[$type] ?? [])) {
                    echo json_encode(["success" => false, "error" => "You no longer have the requested items"]);
                    exit;
                }
            }
        }
        
        // Check coins
        $fromCoins = $users["users"][$fromUser]["coins"] ?? 0;
        $toCoins = $users["users"][$username]["coins"] ?? 0;
        
        if ($trade["offerCoins"] > $fromCoins) {
            $trade["status"] = "cancelled";
            saveTrades($data);
            echo json_encode(["success" => false, "error" => "Sender doesnt have enough coins"]);
            exit;
        }
        
        if ($trade["requestCoins"] > $toCoins) {
            echo json_encode(["success" => false, "error" => "You dont have enough coins"]);
            exit;
        }
        
        // Execute trade - remove items from sender, add to receiver
        foreach ($trade["offerItems"] as $item) {
            removeItemFromInventory($fromInventory, $item["type"], $item["name"]);
            addItemToInventory($toInventory, $item["type"], $item["name"]);
        }
        
        // Remove items from receiver, add to sender
        foreach ($trade["requestItems"] as $item) {
            removeItemFromInventory($toInventory, $item["type"], $item["name"]);
            addItemToInventory($fromInventory, $item["type"], $item["name"]);
        }
        
        // Transfer coins
        $users["users"][$fromUser]["coins"] -= $trade["offerCoins"];
        $users["users"][$username]["coins"] += $trade["offerCoins"];
        $users["users"][$username]["coins"] -= $trade["requestCoins"];
        $users["users"][$fromUser]["coins"] += $trade["requestCoins"];
        
        $trade["status"] = "completed";
        $trade["completedAt"] = date("Y-m-d H:i:s");
        
        saveUsers($users);
        saveTrades($data);
        
        echo json_encode(["success" => true]);
        exit;
    }
    
    // Decline trade
    if ($action === "decline") {
        $tradeId = $input["tradeId"] ?? "";
        
        $data = loadTrades();
        
        if (!isset($data["trades"][$tradeId])) {
            echo json_encode(["success" => false, "error" => "Trade not found"]);
            exit;
        }
        
        $trade = &$data["trades"][$tradeId];
        
        if ($trade["to"] !== $username) {
            echo json_encode(["success" => false, "error" => "This trade is not for you"]);
            exit;
        }
        
        if ($trade["status"] !== "pending") {
            echo json_encode(["success" => false, "error" => "Trade already processed"]);
            exit;
        }
        
        $trade["status"] = "declined";
        saveTrades($data);
        
        echo json_encode(["success" => true]);
        exit;
    }
    
    // Cancel trade (by sender)
    if ($action === "cancel") {
        $tradeId = $input["tradeId"] ?? "";
        
        $data = loadTrades();
        
        if (!isset($data["trades"][$tradeId])) {
            echo json_encode(["success" => false, "error" => "Trade not found"]);
            exit;
        }
        
        $trade = &$data["trades"][$tradeId];
        
        if ($trade["from"] !== $username) {
            echo json_encode(["success" => false, "error" => "Not your trade"]);
            exit;
        }
        
        if ($trade["status"] !== "pending") {
            echo json_encode(["success" => false, "error" => "Trade already processed"]);
            exit;
        }
        
        $trade["status"] = "cancelled";
        saveTrades($data);
        
        echo json_encode(["success" => true]);
        exit;
    }
}

echo json_encode(["success" => false, "error" => "Invalid request"]);
