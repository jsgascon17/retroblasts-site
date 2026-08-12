<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/_store.php';

$usersFile = __DIR__ . '/../data/users.json';
$ADMIN_PASSWORD = 'retroadmin2026'; // Change this!

function loadUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function saveUsers($data) {
    global $usersFile;
    store_write($usersFile, $data);
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// Check if admin (no auth required)
if ($action === "check" || $_GET["action"] === "check") {
    echo json_encode(["success" => true, "isAdmin" => isset($_SESSION["isAdmin"]) && $_SESSION["isAdmin"] === true]);
    exit;
}

// Admin login
if ($action === 'login') {
    $password = $input['password'] ?? '';
    if ($password === $ADMIN_PASSWORD) {
        $_SESSION['isAdmin'] = true;
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Wrong password']);
    }
    exit;
}

// Check admin session
if (!isset($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit;
}

// List all users
if ($action === 'listUsers') {
    $data = loadUsers();
    $userList = [];
    
    foreach ($data['users'] as $username => $user) {
        $userList[] = [
            'username' => $username,
            'displayName' => $user['displayName'] ?? $username,
            'coins' => $user['coins'] ?? 0,
            'xp' => $user['xp'] ?? 0,
            'lastLogin' => $user['lastLogin'] ?? 'Never',
            'createdAt' => $user['createdAt'] ?? 'Unknown'
        ];
    }
    
    echo json_encode(['success' => true, 'users' => $userList]);
    exit;
}

// Reset password
if ($action === 'resetPassword') {
    $username = strtolower($input['username'] ?? '');
    $newPassword = $input['newPassword'] ?? '';
    
    if (strlen($newPassword) < 4) {
        echo json_encode(['success' => false, 'error' => 'Password must be at least 4 characters']);
        exit;
    }
    
    $data = loadUsers();
    
    if (!isset($data['users'][$username])) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    $data['users'][$username]['passwordHash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    saveUsers($data);
    
    echo json_encode(['success' => true, 'message' => 'Password reset for ' . $username]);
    exit;
}

// Get user details
if ($action === 'getUser') {
    $username = strtolower($input['username'] ?? '');
    $data = loadUsers();

    if (!isset($data['users'][$username])) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    $user = $data['users'][$username];
    unset($user['passwordHash']); // Don't send password hash

    echo json_encode(['success' => true, 'user' => $user]);
    exit;
}

// Add coins to current user (owner only)
if ($action === 'add-coins') {
    // Must be logged in as site user
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $username = $_SESSION['user'];

    // Only owner can add coins
    if (strtolower($username) !== 'billybuffalo15') {
        echo json_encode(['success' => false, 'error' => 'Only owner can add coins']);
        exit;
    }

    $amount = intval($input['amount'] ?? 0);
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid amount']);
        exit;
    }

    $data = loadUsers();

    if (!isset($data['users'][$username])) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    $data['users'][$username]['coins'] = ($data['users'][$username]['coins'] ?? 0) + $amount;
    $newBalance = $data['users'][$username]['coins'];

    saveUsers($data);

    echo json_encode(['success' => true, 'newBalance' => $newBalance]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
