<?php
header('Content-Type: application/json');
session_start();

$dataFile = __DIR__ . '/../data/daily-rewards.json';

function loadRewards() {
    global $dataFile;
    if (!file_exists($dataFile)) return [];
    $data = json_decode(file_get_contents($dataFile), true);
    return $data ?: [];
}

function saveRewards($data) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
}

function loadUsers() {
    $userFile = __DIR__ . '/../data/users.json';
    if (!file_exists($userFile)) return [];
    $data = json_decode(file_get_contents($userFile), true);
    return $data ?: [];
}

function saveUsers($users) {
    $userFile = __DIR__ . '/../data/users.json';
    file_put_contents($userFile, json_encode($users, JSON_PRETTY_PRINT));
}

// Weekly rewards structure
$weeklyRewards = [
    1 => ['icon' => '🪙', 'amount' => 50, 'type' => 'coins'],
    2 => ['icon' => '🪙', 'amount' => 75, 'type' => 'coins'],
    3 => ['icon' => '🎁', 'amount' => 1, 'type' => 'mystery box'],
    4 => ['icon' => '🪙', 'amount' => 100, 'type' => 'coins'],
    5 => ['icon' => '💎', 'amount' => 5, 'type' => 'gems'],
    6 => ['icon' => '🪙', 'amount' => 150, 'type' => 'coins'],
    7 => ['icon' => '🏆', 'amount' => 1, 'type' => 'premium crate']
];

// Check auth
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$username = $_SESSION['user'];
$rewards = loadRewards();

// Initialize user if needed
if (!isset($rewards[$username])) {
    $rewards[$username] = [
        'streak' => 0,
        'lastClaim' => null,
        'totalDays' => 0,
        'weekProgress' => []
    ];
}

$userRewards = &$rewards[$username];

// Check if can claim today
function canClaimToday($lastClaim) {
    if (!$lastClaim) return true;

    $last = new DateTime($lastClaim);
    $last->setTime(0, 0, 0);

    $today = new DateTime();
    $today->setTime(0, 0, 0);

    return $today > $last;
}

// Check if streak is broken (missed more than 1 day)
function isStreakBroken($lastClaim) {
    if (!$lastClaim) return false;

    $last = new DateTime($lastClaim);
    $last->setTime(0, 0, 0);

    $today = new DateTime();
    $today->setTime(0, 0, 0);

    $diff = $today->diff($last)->days;

    return $diff > 1;
}

// Handle GET - status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'status';

    if ($action === 'status') {
        // Check if streak is broken
        if (isStreakBroken($userRewards['lastClaim'])) {
            $userRewards['streak'] = 0;
            $userRewards['weekProgress'] = [];
            saveRewards($rewards);
        }

        echo json_encode([
            'success' => true,
            'streak' => $userRewards['streak'],
            'lastClaim' => $userRewards['lastClaim'],
            'canClaim' => canClaimToday($userRewards['lastClaim']),
            'weekProgress' => $userRewards['weekProgress'],
            'totalDays' => $userRewards['totalDays'] ?? 0
        ]);
        exit;
    }
}

// Handle POST - claim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'claim') {
        // Check if can claim
        if (!canClaimToday($userRewards['lastClaim'])) {
            echo json_encode(['success' => false, 'error' => 'Already claimed today']);
            exit;
        }

        // Check if streak is broken
        if (isStreakBroken($userRewards['lastClaim'])) {
            $userRewards['streak'] = 0;
            $userRewards['weekProgress'] = [];
        }

        // Increment streak
        $userRewards['streak']++;
        $userRewards['totalDays'] = ($userRewards['totalDays'] ?? 0) + 1;
        $userRewards['lastClaim'] = date('c');

        // Get reward for current day
        $dayOfWeek = (($userRewards['streak'] - 1) % 7) + 1;
        $reward = $weeklyRewards[$dayOfWeek];

        // Track week progress
        $userRewards['weekProgress'][] = $dayOfWeek;
        if ($dayOfWeek === 7) {
            $userRewards['weekProgress'] = []; // Reset for new week
        }

        // Give reward to user
        $users = loadUsers();
        if (isset($users['users'][$username])) {
            if ($reward['type'] === 'coins') {
                $users['users'][$username]['coins'] = ($users['users'][$username]['coins'] ?? 0) + $reward['amount'];
            } elseif ($reward['type'] === 'gems') {
                $users['users'][$username]['gems'] = ($users['users'][$username]['gems'] ?? 0) + $reward['amount'];
            }
            // For mystery box and premium crate, add to inventory
            if ($reward['type'] === 'mystery box' || $reward['type'] === 'premium crate') {
                if (!isset($users['users'][$username]['inventory'])) {
                    $users['users'][$username]['inventory'] = [];
                }
                $users['users'][$username]['inventory'][] = [
                    'type' => $reward['type'],
                    'icon' => $reward['icon'],
                    'obtainedAt' => date('c')
                ];
            }
            saveUsers($users);
        }

        saveRewards($rewards);

        echo json_encode([
            'success' => true,
            'reward' => $reward,
            'newStreak' => $userRewards['streak'],
            'message' => 'Reward claimed!'
        ]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
