<?php
/**
 * Streaks API
 * Tracks consecutive days played and awards bonuses
 * 
 * GET:
 *   ?action=get - Get current streak info
 * 
 * POST:
 *   { action: "checkin" } - Check in for today
 */

session_start();
header('Content-Type: application/json');

$usersFile = __DIR__ . '/../data/users.json';

// Streak milestone rewards
$MILESTONES = [
    3 => ['coins' => 100, 'name' => '3 Day Streak'],
    7 => ['coins' => 300, 'xp' => 100, 'name' => 'Week Warrior'],
    14 => ['coins' => 750, 'xp' => 250, 'name' => 'Two Week Champion'],
    30 => ['coins' => 2000, 'xp' => 500, 'name' => 'Monthly Master'],
    60 => ['coins' => 5000, 'xp' => 1000, 'name' => 'Legendary Dedication'],
    100 => ['coins' => 10000, 'xp' => 2000, 'name' => 'Century Club'],
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

function getToday() {
    return date('Y-m-d');
}

function getYesterday() {
    return date('Y-m-d', strtotime('-1 day'));
}

function getNextMilestone($currentStreak) {
    global $MILESTONES;
    foreach ($MILESTONES as $days => $reward) {
        if ($days > $currentStreak) {
            return ['days' => $days, 'reward' => $reward];
        }
    }
    return null;
}

// Handle GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'get';
    
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit();
    }
    
    $users = readUsers();
    $username = $_SESSION['user'];
    $user = $users['users'][$username] ?? null;
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit();
    }
    
    $streak = $user['streak'] ?? ['current' => 0, 'best' => 0, 'lastCheckin' => null];
    $today = getToday();
    $checkedInToday = ($streak['lastCheckin'] === $today);
    
    // Check if streak should be reset (missed a day)
    if ($streak['lastCheckin'] && $streak['lastCheckin'] !== $today && $streak['lastCheckin'] !== getYesterday()) {
        $streak['current'] = 0;
    }
    
    $nextMilestone = getNextMilestone($streak['current']);
    
    echo json_encode([
        'success' => true,
        'streak' => [
            'current' => $streak['current'],
            'best' => $streak['best'],
            'lastCheckin' => $streak['lastCheckin'],
            'checkedInToday' => $checkedInToday
        ],
        'nextMilestone' => $nextMilestone
    ]);
    exit();
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit();
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'checkin') {
        global $MILESTONES;
        
        $users = readUsers();
        $username = $_SESSION['user'];
        $user = &$users['users'][$username];
        
        $today = getToday();
        $yesterday = getYesterday();
        
        // Initialize streak if needed
        if (!isset($user['streak'])) {
            $user['streak'] = ['current' => 0, 'best' => 0, 'lastCheckin' => null];
        }
        
        $streak = &$user['streak'];
        
        // Already checked in today
        if ($streak['lastCheckin'] === $today) {
            echo json_encode([
                'success' => true,
                'alreadyCheckedIn' => true,
                'streak' => $streak,
                'nextMilestone' => getNextMilestone($streak['current'])
            ]);
            exit();
        }
        
        // Check if continuing streak or starting new
        if ($streak['lastCheckin'] === $yesterday) {
            // Continue streak
            $streak['current']++;
        } else {
            // Start new streak
            $streak['current'] = 1;
        }
        
        $streak['lastCheckin'] = $today;
        
        // Update best streak
        if ($streak['current'] > $streak['best']) {
            $streak['best'] = $streak['current'];
        }
        
        // Check for milestone rewards
        $earnedMilestone = null;
        if (isset($MILESTONES[$streak['current']])) {
            $earnedMilestone = $MILESTONES[$streak['current']];
            $earnedMilestone['days'] = $streak['current'];
            
            // Award coins
            if (isset($earnedMilestone['coins'])) {
                $user['coins'] = ($user['coins'] ?? 0) + $earnedMilestone['coins'];
            }
            // Award XP
            if (isset($earnedMilestone['xp'])) {
                $user['xp'] = ($user['xp'] ?? 0) + $earnedMilestone['xp'];
            }
        }
        
        writeUsers($users);
        
        echo json_encode([
            'success' => true,
            'streak' => $streak,
            'earnedMilestone' => $earnedMilestone,
            'nextMilestone' => getNextMilestone($streak['current'])
        ]);
        exit();
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>
