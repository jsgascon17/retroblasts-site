<?php
header('Content-Type: application/json');
session_start();

$dataFile = __DIR__ . '/data/score-notifications.json';

function loadNotifications() {
    global $dataFile;
    if (!file_exists($dataFile)) return [];
    return json_decode(file_get_contents($dataFile), true) ?: [];
}

function saveNotifications($notifications) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($notifications, JSON_PRETTY_PRINT));
}

// Check auth
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user = $_SESSION['user'];
$notifications = loadNotifications();

// Handle GET - check for notifications
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'check';
    
    if ($action === 'check') {
        // Get unread notifications for this user
        $userNotifications = array_filter($notifications, function($n) use ($user) {
            return $n['victim'] === $user['username'] && !$n['read'];
        });
        
        echo json_encode([
            'success' => true,
            'notifications' => array_values($userNotifications)
        ]);
        exit;
    }
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'mark-read':
            $ids = $input['ids'] ?? [];
            foreach ($notifications as &$n) {
                if (in_array($n['id'], $ids)) {
                    $n['read'] = true;
                }
            }
            saveNotifications($notifications);
            echo json_encode(['success' => true]);
            break;
            
        case 'create':
            // Called when someone beats another player's score
            $notification = [
                'id' => uniqid('sn_'),
                'victim' => $input['victim'],
                'beater' => $user['username'],
                'beaterDisplay' => $user['displayName'] ?? $user['username'],
                'game' => $input['game'],
                'gameName' => $input['gameName'],
                'theirScore' => $input['theirScore'],
                'yourOldScore' => $input['yourOldScore'],
                'read' => false,
                'createdAt' => date('c')
            ];
            
            $notifications[] = $notification;
            
            // Keep only last 100 notifications total
            if (count($notifications) > 100) {
                $notifications = array_slice($notifications, -100);
            }
            
            saveNotifications($notifications);
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
