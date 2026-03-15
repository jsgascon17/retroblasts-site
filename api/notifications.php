<?php
/**
 * Notifications API
 * 
 * GET:
 *   ?action=list       - Get unread notifications
 *   ?action=count      - Get unread count
 * 
 * POST:
 *   { "action": "mark-read", "id": "notif_id" }
 *   { "action": "mark-all-read" }
 *   { "action": "score-beaten", "game": "snake", "beatenBy": "user", "oldScore": 100, "newScore": 150 }
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$notificationsFile = __DIR__ . '/../data/notifications.json';
$usersFile = __DIR__ . '/../data/users.json';

function readNotifications() {
    global $notificationsFile;
    if (!file_exists($notificationsFile)) return ['notifications' => []];
    return json_decode(file_get_contents($notificationsFile), true) ?: ['notifications' => []];
}

function writeNotifications($data) {
    global $notificationsFile;
    file_put_contents($notificationsFile, json_encode($data, JSON_PRETTY_PRINT));
}

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function addNotification($toUser, $type, $title, $message, $data = []) {
    $notifs = readNotifications();
    
    $notif = [
        'id' => bin2hex(random_bytes(8)),
        'to' => $toUser,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'data' => $data,
        'read' => false,
        'timestamp' => date('c')
    ];
    
    $notifs['notifications'][] = $notif;
    
    // Keep only last 500 notifications per user, clean old ones
    $userNotifs = array_filter($notifs['notifications'], function($n) use ($toUser) {
        return $n['to'] === $toUser;
    });
    
    if (count($userNotifs) > 50) {
        // Remove oldest for this user
        $notifs['notifications'] = array_filter($notifs['notifications'], function($n) use ($toUser, $userNotifs) {
            if ($n['to'] !== $toUser) return true;
            $oldest = array_slice($userNotifs, 0, count($userNotifs) - 50);
            return !in_array($n['id'], array_column($oldest, 'id'));
        });
    }
    
    // Global cleanup - keep last 2000
    if (count($notifs['notifications']) > 2000) {
        $notifs['notifications'] = array_slice($notifs['notifications'], -2000);
    }
    
    writeNotifications($notifs);
    return $notif;
}

// Handle GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit();
    }
    
    $currentUser = $_SESSION['user'];
    $action = $_GET['action'] ?? 'list';
    
    $notifs = readNotifications();
    
    // Filter for current user
    $userNotifs = array_filter($notifs['notifications'], function($n) use ($currentUser) {
        return $n['to'] === $currentUser;
    });
    
    // Sort by date descending
    usort($userNotifs, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    if ($action === 'count') {
        $unread = count(array_filter($userNotifs, function($n) { return !$n['read']; }));
        echo json_encode(['success' => true, 'unread' => $unread]);
        exit();
    }
    
    if ($action === 'list') {
        // Return last 30 notifications
        $userNotifs = array_slice($userNotifs, 0, 30);
        echo json_encode(['success' => true, 'notifications' => array_values($userNotifs)]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    // Some actions don't require login (system notifications)
    if ($action === 'score-beaten') {
        $targetUser = strtolower($input['targetUser'] ?? '');
        $game = $input['game'] ?? '';
        $beatenBy = $input['beatenBy'] ?? '';
        $beatenByName = $input['beatenByName'] ?? $beatenBy;
        $oldScore = intval($input['oldScore'] ?? 0);
        $newScore = intval($input['newScore'] ?? 0);
        
        if (!$targetUser || !$game || !$beatenBy) {
            echo json_encode(['success' => false, 'error' => 'Missing parameters']);
            exit();
        }
        
        $users = readUsers();
        if (!isset($users['users'][$targetUser])) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit();
        }
        
        addNotification(
            $targetUser,
            'score_beaten',
            'Score Beaten! 😱',
            "$beatenByName beat your $game score! ($newScore > $oldScore)",
            [
                'game' => $game,
                'beatenBy' => $beatenBy,
                'oldScore' => $oldScore,
                'newScore' => $newScore
            ]
        );
        
        echo json_encode(['success' => true, 'message' => 'Notification sent']);
        exit();
    }
    
    if ($action === 'gift-received') {
        $targetUser = strtolower($input['targetUser'] ?? '');
        $fromUser = $input['fromUser'] ?? '';
        $fromName = $input['fromName'] ?? $fromUser;
        $giftType = $input['giftType'] ?? 'coins';
        $giftDetails = $input['giftDetails'] ?? '';
        
        addNotification(
            $targetUser,
            'gift',
            'Gift Received! 🎁',
            "$fromName sent you $giftDetails",
            ['from' => $fromUser, 'type' => $giftType]
        );
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    if ($action === 'friend-activity') {
        $targetUser = strtolower($input['targetUser'] ?? '');
        $friendName = $input['friendName'] ?? '';
        $activity = $input['activity'] ?? '';
        
        addNotification(
            $targetUser,
            'friend_activity',
            'Friend Activity 👥',
            $activity,
            ['friend' => $friendName]
        );
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    // Login required for user actions
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit();
    }
    
    $currentUser = $_SESSION['user'];
    $notifs = readNotifications();
    
    if ($action === 'mark-read') {
        $notifId = $input['id'] ?? '';
        
        foreach ($notifs['notifications'] as &$n) {
            if ($n['id'] === $notifId && $n['to'] === $currentUser) {
                $n['read'] = true;
                break;
            }
        }
        
        writeNotifications($notifs);
        echo json_encode(['success' => true]);
        exit();
    }
    
    if ($action === 'mark-all-read') {
        foreach ($notifs['notifications'] as &$n) {
            if ($n['to'] === $currentUser) {
                $n['read'] = true;
            }
        }
        
        writeNotifications($notifs);
        echo json_encode(['success' => true]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
