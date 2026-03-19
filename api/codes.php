<?php
/**
 * Lucky Codes API
 * 
 * GET:
 *   ?action=history - Get user's redeemed codes
 * 
 * POST:
 *   { action: "redeem", code: "..." }
 */

session_start();
header('Content-Type: application/json');

$dataDir = __DIR__ . '/../data';
$usersFile = $dataDir . '/users.json';
$codesFile = $dataDir . '/codes.json';

// Active codes - Add new codes here!
$CODES = [
    // Welcome & General
    'WELCOME2024' => ['reward' => 'coins', 'amount' => 500, 'maxUses' => null, 'expires' => null, 'desc' => '500 Coins'],
    'ARCADE100' => ['reward' => 'coins', 'amount' => 100, 'maxUses' => null, 'expires' => null, 'desc' => '100 Coins'],
    'FREESTUFF' => ['reward' => 'coins', 'amount' => 150, 'maxUses' => null, 'expires' => null, 'desc' => '150 Coins'],
    // St. Patricks Theme
    'LUCKYDAY' => ['reward' => 'coins', 'amount' => 250, 'maxUses' => 100, 'expires' => null, 'desc' => '250 Coins'],
    'POTOFGOLD' => ['reward' => 'coins', 'amount' => 777, 'maxUses' => null, 'expires' => null, 'desc' => '777 Coins'],
    'SHAMROCK' => ['reward' => 'xp', 'amount' => 300, 'maxUses' => null, 'expires' => null, 'desc' => '300 XP'],
    'LUCKY7' => ['reward' => 'coins', 'amount' => 777, 'maxUses' => 77, 'expires' => null, 'desc' => '777 Coins'],
    'RAINBOW' => ['reward' => 'lootbox', 'boxType' => 'premium', 'maxUses' => null, 'expires' => null, 'desc' => 'Premium Lootbox'],
    // Lootboxes and Keys
    'SECRETBOX' => ['reward' => 'lootbox', 'boxType' => 'premium', 'maxUses' => 50, 'expires' => null, 'desc' => 'Premium Lootbox'],
    'FREEBOX' => ['reward' => 'lootbox', 'boxType' => 'basic', 'maxUses' => null, 'expires' => null, 'desc' => 'Basic Lootbox'],
    'GOLDENKEY' => ['reward' => 'key', 'keyType' => 'gold_key', 'maxUses' => 25, 'expires' => null, 'desc' => 'Gold Key'],
    'BRONZE4U' => ['reward' => 'key', 'keyType' => 'bronze_key', 'maxUses' => null, 'expires' => null, 'desc' => 'Bronze Key'],
    // XP Codes
    'XPBOOST' => ['reward' => 'xp', 'amount' => 200, 'maxUses' => null, 'expires' => null, 'desc' => '200 XP'],
    'LEVELUP' => ['reward' => 'xp', 'amount' => 500, 'maxUses' => null, 'expires' => null, 'desc' => '500 XP'],
    'XPTIME' => ['reward' => 'xp', 'amount' => 100, 'maxUses' => null, 'expires' => null, 'desc' => '100 XP'],
    // Big Rewards Limited
    'JACKPOT' => ['reward' => 'coins', 'amount' => 2500, 'maxUses' => 20, 'expires' => null, 'desc' => '2500 Coins'],
    'MEGAPRIZE' => ['reward' => 'coins', 'amount' => 5000, 'maxUses' => 10, 'expires' => null, 'desc' => '5000 Coins'],
    'VIPACCESS' => ['reward' => 'coins', 'amount' => 1000, 'maxUses' => 50, 'expires' => null, 'desc' => '1000 Coins'],
    // Fun Codes
    'GGEZ' => ['reward' => 'coins', 'amount' => 69, 'maxUses' => null, 'expires' => null, 'desc' => '69 Coins'],
    'COOKIE' => ['reward' => 'coins', 'amount' => 200, 'maxUses' => null, 'expires' => null, 'desc' => '200 Coins'],
    'GAMER' => ['reward' => 'xp', 'amount' => 250, 'maxUses' => null, 'expires' => null, 'desc' => '250 XP'],
    'LETSGOO' => ['reward' => 'coins', 'amount' => 333, 'maxUses' => null, 'expires' => null, 'desc' => '333 Coins'],
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

function readCodes() {
    global $codesFile;
    if (!file_exists($codesFile)) return ['redeemed' => []];
    return json_decode(file_get_contents($codesFile), true) ?: ['redeemed' => []];
}

function writeCodes($data) {
    global $codesFile;
    file_put_contents($codesFile, json_encode($data, JSON_PRETTY_PRINT));
}

function ensureInventoryFields(&$user) {
    if (!isset($user['inventory'])) $user['inventory'] = [];
    if (!isset($user['inventory']['keys'])) {
        $user['inventory']['keys'] = ['bronze_key' => 0, 'silver_key' => 0, 'gold_key' => 0];
    }
    if (!isset($user['inventory']['lootboxes'])) {
        $user['inventory']['lootboxes'] = [];
    }
}

// Handle GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'history';
    
    if ($action === 'history') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit();
        }
        
        $users = readUsers();
        $username = $_SESSION['user'];
        $redeemedCodes = $users['users'][$username]['redeemedCodes'] ?? [];
        
        echo json_encode(['success' => true, 'redeemedCodes' => $redeemedCodes]);
        exit();
    }
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit();
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'redeem') {
        global $CODES;
        
        $code = strtoupper(trim($input['code'] ?? ''));
        
        if (empty($code)) {
            echo json_encode(['success' => false, 'error' => 'Please enter a code']);
            exit();
        }
        
        // Check if code exists
        if (!isset($CODES[$code])) {
            echo json_encode(['success' => false, 'error' => 'Invalid code']);
            exit();
        }
        
        $codeData = $CODES[$code];
        
        // Check expiration
        if ($codeData['expires'] && strtotime($codeData['expires']) < time()) {
            echo json_encode(['success' => false, 'error' => 'This code has expired']);
            exit();
        }
        
        $users = readUsers();
        $username = $_SESSION['user'];
        $user = &$users['users'][$username];
        
        // Check if already redeemed
        $redeemedCodes = $user['redeemedCodes'] ?? [];
        if (in_array($code, $redeemedCodes)) {
            echo json_encode(['success' => false, 'error' => 'You already redeemed this code']);
            exit();
        }
        
        // Check max uses
        if ($codeData['maxUses'] !== null) {
            $codesData = readCodes();
            $useCount = $codesData['redeemed'][$code] ?? 0;
            if ($useCount >= $codeData['maxUses']) {
                echo json_encode(['success' => false, 'error' => 'This code has reached its limit']);
                exit();
            }
            $codesData['redeemed'][$code] = $useCount + 1;
            writeCodes($codesData);
        }
        
        ensureInventoryFields($user);
        
        // Apply reward
        $rewardDesc = $codeData['desc'];
        
        switch ($codeData['reward']) {
            case 'coins':
                $user['coins'] = ($user['coins'] ?? 0) + $codeData['amount'];
                break;
            case 'xp':
                $user['xp'] = ($user['xp'] ?? 0) + $codeData['amount'];
                break;
            case 'lootbox':
                $user['inventory']['lootboxes'][] = $codeData['boxType'];
                break;
            case 'key':
                $keyType = $codeData['keyType'];
                $user['inventory']['keys'][$keyType] = ($user['inventory']['keys'][$keyType] ?? 0) + 1;
                break;
        }
        
        // Mark as redeemed
        if (!isset($user['redeemedCodes'])) $user['redeemedCodes'] = [];
        $user['redeemedCodes'][] = $code;
        
        writeUsers($users);
        
        echo json_encode([
            'success' => true,
            'reward' => $rewardDesc,
            'newCoins' => $user['coins'] ?? 0,
            'newXP' => $user['xp'] ?? 0
        ]);
        exit();
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>
