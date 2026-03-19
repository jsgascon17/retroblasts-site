<?php
/**
 * Friend Activity Feed API
 * 
 * GET:
 *   ?action=feed           - Get activity from friends
 *   ?action=my             - Get my activity
 * 
 * POST:
 *   { "action": "log", "type": "...", "game": "...", "data": {...} }
 */

header('Content-Type: application/json');
session_start();

$activityFile = __DIR__ . '/../data/activity.json';
$friendsFile = __DIR__ . '/../data/friends.json';
$usersFile = __DIR__ . '/../data/users.json';

function readActivity() {
    global $activityFile;
    if (!file_exists($activityFile)) return [];
    return json_decode(file_get_contents($activityFile), true) ?: [];
}

function writeActivity($data) {
    global $activityFile;
    // Keep only last 1000 activities
    $data = array_slice($data, -1000);
    file_put_contents($activityFile, json_encode($data, JSON_PRETTY_PRINT));
}

function readFriends() {
    global $friendsFile;
    if (!file_exists($friendsFile)) return [];
    return json_decode(file_get_contents($friendsFile), true) ?: [];
}

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function getUserFriends($username) {
    $friends = readFriends();
    $userFriends = [];
    foreach ($friends as $f) {
        if ($f['status'] === 'accepted') {
            if ($f['from'] === $username) $userFriends[] = $f['to'];
            if ($f['to'] === $username) $userFriends[] = $f['from'];
        }
    }
    return $userFriends;
}

function formatActivity($activity, $users) {
    $user = $users[$activity['user']] ?? null;
    $displayName = $user ? $user['displayName'] : $activity['user'];
    
    $messages = [
        'high_score' => "🏆 {$displayName} got a new high score of {$activity['data']['score']} in {$activity['data']['game']}!",
        'achievement' => "⭐ {$displayName} unlocked '{$activity['data']['name']}' in {$activity['data']['game']}!",
        'level_up' => "📈 {$displayName} reached level {$activity['data']['level']} in {$activity['data']['game']}!",
        'purchase' => "🛍️ {$displayName} bought '{$activity['data']['item']}' in {$activity['data']['game']}!",
        'duel_win' => "⚔️ {$displayName} won a duel against {$activity['data']['opponent']} in {$activity['data']['game']}!",
        'duel_loss' => "😢 {$displayName} lost a duel to {$activity['data']['opponent']} in {$activity['data']['game']}",
        'war_contribution' => "🔥 {$displayName} scored {$activity['data']['score']} points in team war!",
        'joined_team' => "👥 {$displayName} joined team {$activity['data']['team']}!",
        'playing' => "🎮 {$displayName} is playing {$activity['data']['game']}",
    ];
    
    $activity['message'] = $messages[$activity['type']] ?? "{$displayName} did something in {$activity['data']['game'] ?? 'arcade'}";
    $activity['displayName'] = $displayName;
    $activity['timeAgo'] = getTimeAgo($activity['time']);
    
    return $activity;
}

function getTimeAgo($timestamp) {
    $diff = time() - strtotime($timestamp);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($timestamp));
}

// Check auth
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$currentUser = $_SESSION['user'];
$activities = readActivity();
$users = readUsers()['users'] ?? [];

// GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'feed';

    if ($action === 'feed') {
        $friends = getUserFriends($currentUser);
        $friends[] = $currentUser; // Include own activity
        
        // Filter to friend activity
        $feed = array_filter($activities, function($a) use ($friends) {
            return in_array($a['user'], $friends);
        });
        
        // Sort by time descending
        usort($feed, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });
        
        // Take last 50 and format
        $feed = array_slice($feed, 0, 50);
        $feed = array_map(function($a) use ($users) {
            return formatActivity($a, $users);
        }, $feed);
        
        echo json_encode(['success' => true, 'feed' => array_values($feed)]);
        exit;
    }

    if ($action === 'my') {
        $myActivity = array_filter($activities, function($a) use ($currentUser) {
            return $a['user'] === $currentUser;
        });
        
        usort($myActivity, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });
        
        $myActivity = array_slice($myActivity, 0, 20);
        $myActivity = array_map(function($a) use ($users) {
            return formatActivity($a, $users);
        }, $myActivity);
        
        echo json_encode(['success' => true, 'activity' => array_values($myActivity)]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'log') {
        $type = $input['type'] ?? '';
        $data = $input['data'] ?? [];
        
        if (!$type) {
            echo json_encode(['success' => false, 'error' => 'Missing activity type']);
            exit;
        }
        
        $activity = [
            'id' => uniqid('act_'),
            'user' => $currentUser,
            'type' => $type,
            'data' => $data,
            'time' => date('c')
        ];
        
        $activities[] = $activity;
        writeActivity($activities);
        
        echo json_encode(['success' => true, 'activity' => $activity]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request method']);
