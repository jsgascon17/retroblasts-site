<?php
session_start();
header('Content-Type: application/json');

// Check admin
if (!isset($_SESSION['user']) || $_SESSION['user'] !== 'billybuffalo15') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$game = $input['game'] ?? '';
$rewards = $input['rewards'] ?? [500, 250, 100];

if (empty($game)) {
    echo json_encode(['success' => false, 'error' => 'No game specified']);
    exit;
}

// Load leaderboard
$leaderboardFile = __DIR__ . '/../leaderboards/' . $game . '.json';
if (!file_exists($leaderboardFile)) {
    echo json_encode(['success' => false, 'error' => 'Leaderboard not found']);
    exit;
}

$leaderboard = json_decode(file_get_contents($leaderboardFile), true);
$scores = $leaderboard['scores'] ?? [];

if (count($scores) === 0) {
    echo json_encode(['success' => false, 'error' => 'No scores on leaderboard']);
    exit;
}

// Load users
$usersFile = __DIR__ . '/../data/users.json';
$usersData = json_decode(file_get_contents($usersFile), true);

$messages = [];

// Reward top 3
for ($i = 0; $i < min(3, count($scores)); $i++) {
    $playerName = strtolower($scores[$i]['name']);
    $rewardAmount = $rewards[$i];
    
    if (isset($usersData['users'][$playerName])) {
        $usersData['users'][$playerName]['coins'] = ($usersData['users'][$playerName]['coins'] ?? 0) + $rewardAmount;
        $messages[] = "#" . ($i + 1) . " " . $scores[$i]['name'] . ": +" . $rewardAmount . " coins";
    } else {
        $messages[] = "#" . ($i + 1) . " " . $scores[$i]['name'] . ": Not a registered user";
    }
}

file_put_contents($usersFile, json_encode($usersData, JSON_PRETTY_PRINT));

echo json_encode([
    'success' => true,
    'message' => implode("\n", $messages)
]);
