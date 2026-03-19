<?php
/**
 * Admin API - Only for authorized users
 */

session_start();
header('Content-Type: application/json');

$ADMIN_USERS = ['billybuffalo15'];

$usersFile = __DIR__ . '/../data/users.json';

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function writeUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

// Check if logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$username = $_SESSION['user'];

// Check if admin
if (!in_array($username, $ADMIN_USERS)) {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'add-coins') {
        $amount = intval($input['amount'] ?? 0);
        
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid amount']);
            exit();
        }
        
        $data = readUsers();
        
        if (!isset($data['users'][$username])) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit();
        }
        
        $data['users'][$username]['coins'] = ($data['users'][$username]['coins'] ?? 0) + $amount;
        $newBalance = $data['users'][$username]['coins'];
        
        writeUsers($data);
        
        echo json_encode([
            'success' => true,
            'added' => $amount,
            'newBalance' => $newBalance
        ]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
