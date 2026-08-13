<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/_store.php';
require_once __DIR__ . '/_admin.php';

$ADMIN_FILE = __DIR__ . '/../data/space-invaders-admins.json';
$SETTINGS_FILE = __DIR__ . '/../data/space-invaders-settings.json';
$OWNER_USERS = ['billybuffalo15'];

// The admin and owner codes were plaintext literals here and were therefore
// publicly downloadable through the exposed .git directory. They now come from
// data/admin-codes.json under the "space-invaders" key and are compared in
// constant time via _admin.php. Treat the old values as compromised.

function loadAdmins() {
    global $ADMIN_FILE;
    if (!file_exists($ADMIN_FILE)) return [];
    return store_hold_read($ADMIN_FILE, []);
}

function saveAdmins($admins) {
    global $ADMIN_FILE;
    store_hold_write($ADMIN_FILE, $admins);
}

function loadSettings() {
    global $SETTINGS_FILE;
    if (!file_exists($SETTINGS_FILE)) {
        return ['adminCodeEnabled' => true];
    }
    return store_hold_read($SETTINGS_FILE, ['adminCodeEnabled' => true]);
}

function saveSettings($settings) {
    global $SETTINGS_FILE;
    store_hold_write($SETTINGS_FILE, $settings);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$username = strtolower($_SESSION['user']);
$isOwner = in_array($username, array_map('strtolower', $OWNER_USERS));

switch ($action) {
    case 'check':
        $admins = loadAdmins();
        $hasAdmin = isset($admins[$username]) || $isOwner;
        $settings = loadSettings();
        echo json_encode([
            'success' => true,
            'isAdmin' => $hasAdmin,
            'isOwner' => $isOwner,
            'username' => $_SESSION['user'],
            'codeEnabled' => $settings['adminCodeEnabled']
        ]);
        break;
        
    case 'unlock':
        $password = $_POST['password'] ?? '';
        $settings = loadSettings();
        
        if (game_code_matches('space-invaders', 'owner', (string) $password)) {
            $admins = loadAdmins();
            $admins[$username] = ['grantedAt' => date('Y-m-d H:i:s'), 'type' => 'owner'];
            saveAdmins($admins);
            echo json_encode(['success' => true, 'type' => 'owner', 'message' => 'Owner mode activated!']);
        } else if (game_code_matches('space-invaders', 'admin', (string) $password)) {
            if (!$settings['adminCodeEnabled']) {
                echo json_encode(['success' => false, 'error' => 'Admin code is currently disabled']);
                exit;
            }
            $admins = loadAdmins();
            $admins[$username] = ['grantedAt' => date('Y-m-d H:i:s'), 'type' => 'admin'];
            saveAdmins($admins);
            echo json_encode(['success' => true, 'type' => 'admin', 'message' => 'Admin unlocked!']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid password']);
        }
        break;
        
    case 'list':
        if (!$isOwner) {
            echo json_encode(['success' => false, 'error' => 'Owner access required']);
            exit;
        }
        $admins = loadAdmins();
        $settings = loadSettings();
        echo json_encode(['success' => true, 'admins' => $admins, 'codeEnabled' => $settings['adminCodeEnabled']]);
        break;
        
    case 'revoke':
        if (!$isOwner) {
            echo json_encode(['success' => false, 'error' => 'Owner access required']);
            exit;
        }
        $targetUser = strtolower($_POST['username'] ?? '');
        if (empty($targetUser)) {
            echo json_encode(['success' => false, 'error' => 'Username required']);
            exit;
        }
        $admins = loadAdmins();
        if (isset($admins[$targetUser])) {
            unset($admins[$targetUser]);
            saveAdmins($admins);
            echo json_encode(['success' => true, 'message' => "Revoked admin from $targetUser"]);
        } else {
            echo json_encode(['success' => false, 'error' => 'User not found in admin list']);
        }
        break;
        
    case 'toggleCode':
        if (!$isOwner) {
            echo json_encode(['success' => false, 'error' => 'Owner access required']);
            exit;
        }
        $settings = loadSettings();
        $settings['adminCodeEnabled'] = !($settings['adminCodeEnabled'] ?? true);
        saveSettings($settings);
        echo json_encode([
            'success' => true, 
            'codeEnabled' => $settings['adminCodeEnabled'],
            'message' => $settings['adminCodeEnabled'] ? 'Admin code ENABLED' : 'Admin code DISABLED'
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
