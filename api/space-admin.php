<?php
session_start();
header('Content-Type: application/json');

$ADMIN_FILE = __DIR__ . '/../data/space-invaders-admins.json';
$SETTINGS_FILE = __DIR__ . '/../data/space-invaders-settings.json';
$OWNER_USERS = ['billybuffalo15'];
$ADMIN_PASSWORD = 'jsg1708admin';
$OWNER_PASSWORD = 'jsgowner2008';

function loadAdmins() {
    global $ADMIN_FILE;
    if (!file_exists($ADMIN_FILE)) return [];
    return json_decode(file_get_contents($ADMIN_FILE), true) ?: [];
}

function saveAdmins($admins) {
    global $ADMIN_FILE;
    file_put_contents($ADMIN_FILE, json_encode($admins, JSON_PRETTY_PRINT));
}

function loadSettings() {
    global $SETTINGS_FILE;
    if (!file_exists($SETTINGS_FILE)) {
        return ['adminCodeEnabled' => true];
    }
    return json_decode(file_get_contents($SETTINGS_FILE), true) ?: ['adminCodeEnabled' => true];
}

function saveSettings($settings) {
    global $SETTINGS_FILE;
    file_put_contents($SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT));
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
        
        if ($password === $OWNER_PASSWORD) {
            $admins = loadAdmins();
            $admins[$username] = ['grantedAt' => date('Y-m-d H:i:s'), 'type' => 'owner'];
            saveAdmins($admins);
            echo json_encode(['success' => true, 'type' => 'owner', 'message' => 'Owner mode activated!']);
        } else if ($password === $ADMIN_PASSWORD) {
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
