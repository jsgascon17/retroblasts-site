<?php
session_start();
header('Content-Type: application/json');

$usersFile = __DIR__ . '/../data/users.json';

function loadUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function saveUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

$username = $_SESSION['user'] ?? null;

if (!$username) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

$users = loadUsers();

if (!isset($users['users'][$username])) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

switch ($action) {
    case 'get':
        echo json_encode([
            'success' => true,
            'coins' => $users['users'][$username]['coins'] ?? 0
        ]);
        break;
        
    case 'deduct':
        $amount = intval($input['amount'] ?? 0);
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid amount']);
            exit;
        }
        
        $currentCoins = $users['users'][$username]['coins'] ?? 0;
        if ($currentCoins < $amount) {
            echo json_encode(['success' => false, 'error' => 'Not enough coins']);
            exit;
        }
        
        $users['users'][$username]['coins'] = $currentCoins - $amount;
        saveUsers($users);
        
        echo json_encode([
            'success' => true,
            'coins' => $users['users'][$username]['coins']
        ]);
        break;
        
    case 'add':
        $amount = intval($input['amount'] ?? 0);
        if ($amount <= 0 || $amount > 10000) {
            echo json_encode(['success' => false, 'error' => 'Invalid amount']);
            exit;
        }
        
        $users['users'][$username]['coins'] = ($users['users'][$username]['coins'] ?? 0) + $amount;
        saveUsers($users);
        
        echo json_encode([
            'success' => true,
            'coins' => $users['users'][$username]['coins']
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
