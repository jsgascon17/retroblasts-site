<?php
session_start();
header('Content-Type: application/json');

$DATA_DIR = __DIR__ . '/../data/';
$ADMINS_FILE = $DATA_DIR . 'game-admins.json';
$SETTINGS_FILE = $DATA_DIR . 'game-admin-settings.json';
$OWNER_USERS = ['billybuffalo15'];

// Game configs: game_id => [password, owner_password]
$GAME_CONFIGS = [
    'space-invaders' => ['admin' => 'jsg1708admin', 'owner' => 'JsgOwner2024X'],
    'flappy-bird' => ['admin' => 'flappy123', 'owner' => 'jsgowner2008'],
    'rhythm' => ['admin' => 'rhythm123', 'owner' => 'JsgOwner2024X'],
    'cookie-clicker' => ['admin' => 'cookie123', 'owner' => 'JsgOwner2024X'],
    'doodle-jump' => ['admin' => 'doodle123', 'owner' => 'JsgOwner2024X'],
    'knife-hit' => ['admin' => 'knife123', 'owner' => 'JsgOwner2024X']
];

function loadData($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function getSettings() {
    global $SETTINGS_FILE, $GAME_CONFIGS;
    $settings = loadData($SETTINGS_FILE);
    // Default all games to code enabled
    foreach ($GAME_CONFIGS as $game => $config) {
        if (!isset($settings[$game])) {
            $settings[$game] = ['codeEnabled' => true];
        }
    }
    return $settings;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$game = $_GET['game'] ?? $_POST['game'] ?? '';

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$username = strtolower($_SESSION['user']);
$isOwner = in_array($username, array_map('strtolower', $OWNER_USERS));

// Also allow owner password authentication
$ownerPassword = $_GET['ownerPw'] ?? $_POST['ownerPw'] ?? '';
if ($ownerPassword === 'JsgOwner2024X') {
    $isOwner = true;
}

switch ($action) {
    case 'check':
        if (empty($game)) {
            echo json_encode(['success' => false, 'error' => 'Game required']);
            exit;
        }
        $admins = loadData($ADMINS_FILE);
        $settings = getSettings();
        $hasAdmin = isset($admins[$game][$username]) || $isOwner;
        echo json_encode([
            'success' => true,
            'isAdmin' => $hasAdmin,
            'isOwner' => $isOwner,
            'username' => $_SESSION['user'],
            'codeEnabled' => $settings[$game]['codeEnabled'] ?? true
        ]);
        break;
        
    case 'unlock':
        if (empty($game)) {
            echo json_encode(['success' => false, 'error' => 'Game required']);
            exit;
        }
        $password = $_POST['password'] ?? '';
        $settings = getSettings();
        $config = $GAME_CONFIGS[$game] ?? null;
        
        if (!$config) {
            echo json_encode(['success' => false, 'error' => 'Unknown game']);
            exit;
        }
        
        if ($password === $config['owner']) {
            $admins = loadData($ADMINS_FILE);
            if (!isset($admins[$game])) $admins[$game] = [];
            $admins[$game][$username] = ['grantedAt' => date('Y-m-d H:i:s'), 'type' => 'owner'];
            saveData($ADMINS_FILE, $admins);
            echo json_encode(['success' => true, 'type' => 'owner', 'message' => 'Owner mode activated!']);
        } else if ($password === $config['admin']) {
            if (!($settings[$game]['codeEnabled'] ?? true)) {
                echo json_encode(['success' => false, 'error' => 'Admin code is currently disabled for this game']);
                exit;
            }
            $admins = loadData($ADMINS_FILE);
            if (!isset($admins[$game])) $admins[$game] = [];
            $admins[$game][$username] = ['grantedAt' => date('Y-m-d H:i:s'), 'type' => 'admin'];
            saveData($ADMINS_FILE, $admins);
            echo json_encode(['success' => true, 'type' => 'admin', 'message' => 'Admin unlocked!']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid password']);
        }
        break;
        
    case 'listAll':
        // List all admins across all games (owner only)
        if (!$isOwner) {
            echo json_encode(['success' => false, 'error' => 'Owner access required']);
            exit;
        }
        $admins = loadData($ADMINS_FILE);
        $settings = getSettings();
        echo json_encode(['success' => true, 'admins' => $admins, 'settings' => $settings, 'games' => array_keys($GAME_CONFIGS)]);
        break;
        
    case 'list':
        // List admins for specific game (owner only)
        if (!$isOwner) {
            echo json_encode(['success' => false, 'error' => 'Owner access required']);
            exit;
        }
        if (empty($game)) {
            echo json_encode(['success' => false, 'error' => 'Game required']);
            exit;
        }
        $admins = loadData($ADMINS_FILE);
        $settings = getSettings();
        echo json_encode([
            'success' => true, 
            'admins' => $admins[$game] ?? [],
            'codeEnabled' => $settings[$game]['codeEnabled'] ?? true
        ]);
        break;
        
    case 'revoke':
        if (!$isOwner) {
            echo json_encode(['success' => false, 'error' => 'Owner access required']);
            exit;
        }
        if (empty($game)) {
            echo json_encode(['success' => false, 'error' => 'Game required']);
            exit;
        }
        $targetUser = strtolower($_POST['username'] ?? '');
        if (empty($targetUser)) {
            echo json_encode(['success' => false, 'error' => 'Username required']);
            exit;
        }
        $admins = loadData($ADMINS_FILE);
        if (isset($admins[$game][$targetUser])) {
            unset($admins[$game][$targetUser]);
            saveData($ADMINS_FILE, $admins);
            echo json_encode(['success' => true, 'message' => "Revoked admin from $targetUser in $game"]);
        } else {
            echo json_encode(['success' => false, 'error' => 'User not found in admin list for this game']);
        }
        break;
        
    case 'revokeAll':
        // Revoke user from ALL games
        if (!$isOwner) {
            echo json_encode(['success' => false, 'error' => 'Owner access required']);
            exit;
        }
        $targetUser = strtolower($_POST['username'] ?? '');
        if (empty($targetUser)) {
            echo json_encode(['success' => false, 'error' => 'Username required']);
            exit;
        }
        $admins = loadData($ADMINS_FILE);
        $removed = [];
        foreach ($admins as $g => &$users) {
            if (isset($users[$targetUser])) {
                unset($users[$targetUser]);
                $removed[] = $g;
            }
        }
        saveData($ADMINS_FILE, $admins);
        if (count($removed) > 0) {
            echo json_encode(['success' => true, 'message' => "Revoked $targetUser from: " . implode(', ', $removed)]);
        } else {
            echo json_encode(['success' => false, 'error' => 'User not found in any game']);
        }
        break;
        
    case 'toggleCode':
        if (!$isOwner) {
            echo json_encode(['success' => false, 'error' => 'Owner access required']);
            exit;
        }
        if (empty($game)) {
            echo json_encode(['success' => false, 'error' => 'Game required']);
            exit;
        }
        $settings = getSettings();
        $settings[$game]['codeEnabled'] = !($settings[$game]['codeEnabled'] ?? true);
        saveData($SETTINGS_FILE, $settings);
        echo json_encode([
            'success' => true, 
            'codeEnabled' => $settings[$game]['codeEnabled'],
            'message' => $settings[$game]['codeEnabled'] ? "Admin code ENABLED for $game" : "Admin code DISABLED for $game"
        ]);
        break;
        
    case 'disableAll':
        // Disable admin codes for ALL games
        if (!$isOwner) {
            echo json_encode(['success' => false, 'error' => 'Owner access required']);
            exit;
        }
        $settings = getSettings();
        foreach ($settings as $g => &$s) {
            $s['codeEnabled'] = false;
        }
        saveData($SETTINGS_FILE, $settings);
        echo json_encode(['success' => true, 'message' => 'Disabled admin codes for ALL games']);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
