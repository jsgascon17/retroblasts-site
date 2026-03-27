<?php
header("Content-Type: application/json");
session_start();

$dataFile = __DIR__ . "/../data/challenges.json";
$usersFile = __DIR__ . "/../data/users.json";

// Challenge definitions
$CHALLENGES = [
    // Play X games
    ["type" => "play", "game" => "snake", "target" => 3, "reward" => 50, "desc" => "Play Snake 3 times"],
    ["type" => "play", "game" => "tetris", "target" => 3, "reward" => 50, "desc" => "Play Tetris 3 times"],
    ["type" => "play", "game" => "pacman", "target" => 3, "reward" => 50, "desc" => "Play Pac-Man 3 times"],
    ["type" => "play", "game" => "flappy-bird", "target" => 5, "reward" => 60, "desc" => "Play Flappy Bird 5 times"],
    ["type" => "play", "game" => "2048", "target" => 3, "reward" => 50, "desc" => "Play 2048 3 times"],
    ["type" => "play", "game" => "brick-breaker", "target" => 3, "reward" => 50, "desc" => "Play Brick Breaker 3 times"],
    
    // Score challenges
    ["type" => "score", "game" => "snake", "target" => 50, "reward" => 75, "desc" => "Score 50+ in Snake"],
    ["type" => "score", "game" => "tetris", "target" => 500, "reward" => 75, "desc" => "Score 500+ in Tetris"],
    ["type" => "score", "game" => "flappy-bird", "target" => 10, "reward" => 100, "desc" => "Score 10+ in Flappy Bird"],
    ["type" => "score", "game" => "2048", "target" => 1000, "reward" => 80, "desc" => "Score 1000+ in 2048"],
    ["type" => "score", "game" => "pacman", "target" => 500, "reward" => 75, "desc" => "Score 500+ in Pac-Man"],
    ["type" => "score", "game" => "space-invaders", "target" => 300, "reward" => 75, "desc" => "Score 300+ in Space Invaders"],
    ["type" => "score", "game" => "brick-breaker", "target" => 200, "reward" => 75, "desc" => "Score 200+ in Brick Breaker"],
    
    // Total score across games
    ["type" => "total_score", "target" => 500, "reward" => 100, "desc" => "Earn 500 total points today"],
    ["type" => "total_score", "target" => 1000, "reward" => 150, "desc" => "Earn 1000 total points today"],
    
    // Play any games
    ["type" => "play_any", "target" => 5, "reward" => 60, "desc" => "Play any 5 games"],
    ["type" => "play_any", "target" => 10, "reward" => 120, "desc" => "Play any 10 games"],
    
    // Variety challenge
    ["type" => "variety", "target" => 3, "reward" => 100, "desc" => "Play 3 different games"],
    ["type" => "variety", "target" => 5, "reward" => 150, "desc" => "Play 5 different games"],
    
    // Coin earning
    ["type" => "earn_coins", "target" => 100, "reward" => 75, "desc" => "Earn 100 coins from games"],
    ["type" => "earn_coins", "target" => 200, "reward" => 125, "desc" => "Earn 200 coins from games"],
];

function loadData() {
    global $dataFile;
    if (!file_exists($dataFile)) return [];
    return json_decode(file_get_contents($dataFile), true) ?: [];
}

function saveData($data) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
}

function loadUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ["users" => []];
    return json_decode(file_get_contents($usersFile), true) ?: ["users" => []];
}

function saveUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

function getToday() {
    return date("Y-m-d");
}

function generateDailyChallenges() {
    global $CHALLENGES;
    $today = getToday();
    $seed = crc32($today);
    srand($seed);
    
    // Pick 3 random challenges (easy, medium, hard based on reward)
    $easy = array_filter($CHALLENGES, fn($c) => $c["reward"] <= 60);
    $medium = array_filter($CHALLENGES, fn($c) => $c["reward"] > 60 && $c["reward"] <= 100);
    $hard = array_filter($CHALLENGES, fn($c) => $c["reward"] > 100);
    
    $easy = array_values($easy);
    $medium = array_values($medium);
    $hard = array_values($hard);
    
    $daily = [];
    if (count($easy) > 0) $daily[] = $easy[array_rand($easy)];
    if (count($medium) > 0) $daily[] = $medium[array_rand($medium)];
    if (count($hard) > 0) $daily[] = $hard[array_rand($hard)];
    
    // Add IDs
    foreach ($daily as $i => &$c) {
        $c["id"] = $i;
    }
    
    return $daily;
}

function getUserProgress($username) {
    $data = loadData();
    $today = getToday();
    
    if (!isset($data[$username]) || $data[$username]["date"] !== $today) {
        // Reset for new day
        $data[$username] = [
            "date" => $today,
            "progress" => [0, 0, 0],
            "completed" => [false, false, false],
            "claimed" => [false, false, false],
            "gamesPlayed" => [],
            "totalScore" => 0,
            "coinsEarned" => 0
        ];
        saveData($data);
    }
    
    return $data[$username];
}

function updateProgress($username, $game, $score, $coins) {
    $data = loadData();
    $today = getToday();
    $challenges = generateDailyChallenges();
    
    // Init if needed
    if (!isset($data[$username]) || $data[$username]["date"] !== $today) {
        $data[$username] = [
            "date" => $today,
            "progress" => [0, 0, 0],
            "completed" => [false, false, false],
            "claimed" => [false, false, false],
            "gamesPlayed" => [],
            "totalScore" => 0,
            "coinsEarned" => 0
        ];
    }
    
    $userProgress = &$data[$username];
    
    // Track games played
    if (!in_array($game, $userProgress["gamesPlayed"])) {
        $userProgress["gamesPlayed"][] = $game;
    }
    $userProgress["totalScore"] += $score;
    $userProgress["coinsEarned"] += $coins;
    
    // Update each challenge progress
    foreach ($challenges as $i => $challenge) {
        if ($userProgress["completed"][$i]) continue;
        
        switch ($challenge["type"]) {
            case "play":
                if ($challenge["game"] === $game) {
                    $userProgress["progress"][$i]++;
                }
                break;
            case "score":
                if ($challenge["game"] === $game && $score >= $challenge["target"]) {
                    $userProgress["progress"][$i] = 1;
                }
                break;
            case "play_any":
                $userProgress["progress"][$i]++;
                break;
            case "variety":
                $userProgress["progress"][$i] = count($userProgress["gamesPlayed"]);
                break;
            case "total_score":
                $userProgress["progress"][$i] = $userProgress["totalScore"];
                break;
            case "earn_coins":
                $userProgress["progress"][$i] = $userProgress["coinsEarned"];
                break;
        }
        
        // Check completion
        if ($userProgress["progress"][$i] >= $challenge["target"]) {
            $userProgress["completed"][$i] = true;
        }
    }
    
    saveData($data);
    return $userProgress;
}

// Handle requests
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $action = $_GET["action"] ?? "";
    
    if ($action === "get") {
        if (!isset($_SESSION["user"])) {
            echo json_encode(["success" => false, "error" => "Not logged in"]);
            exit;
        }
        
        $username = $_SESSION["user"];
        $challenges = generateDailyChallenges();
        $progress = getUserProgress($username);
        
        echo json_encode([
            "success" => true,
            "challenges" => $challenges,
            "progress" => $progress["progress"],
            "completed" => $progress["completed"],
            "claimed" => $progress["claimed"]
        ]);
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);
    $action = $input["action"] ?? "";
    
    if (!isset($_SESSION["user"])) {
        echo json_encode(["success" => false, "error" => "Not logged in"]);
        exit;
    }
    
    $username = $_SESSION["user"];
    
    // Track game completion (called by games)
    if ($action === "track") {
        $game = $input["game"] ?? "";
        $score = intval($input["score"] ?? 0);
        $coins = intval($input["coins"] ?? 0);
        
        $progress = updateProgress($username, $game, $score, $coins);
        $challenges = generateDailyChallenges();
        
        echo json_encode([
            "success" => true,
            "progress" => $progress["progress"],
            "completed" => $progress["completed"]
        ]);
        exit;
    }
    
    // Claim reward
    if ($action === "claim") {
        $challengeId = intval($input["id"] ?? -1);
        
        if ($challengeId < 0 || $challengeId > 2) {
            echo json_encode(["success" => false, "error" => "Invalid challenge"]);
            exit;
        }
        
        $data = loadData();
        $today = getToday();
        $challenges = generateDailyChallenges();
        
        if (!isset($data[$username]) || $data[$username]["date"] !== $today) {
            echo json_encode(["success" => false, "error" => "No progress today"]);
            exit;
        }
        
        $userProgress = &$data[$username];
        
        if (!$userProgress["completed"][$challengeId]) {
            echo json_encode(["success" => false, "error" => "Challenge not completed"]);
            exit;
        }
        
        if ($userProgress["claimed"][$challengeId]) {
            echo json_encode(["success" => false, "error" => "Already claimed"]);
            exit;
        }
        
        // Award coins
        $reward = $challenges[$challengeId]["reward"];
        $users = loadUsers();
        $users["users"][$username]["coins"] = ($users["users"][$username]["coins"] ?? 0) + $reward;
        saveUsers($users);
        
        $userProgress["claimed"][$challengeId] = true;
        saveData($data);
        
        echo json_encode([
            "success" => true,
            "reward" => $reward,
            "newBalance" => $users["users"][$username]["coins"],
            "claimed" => $userProgress["claimed"]
        ]);
        exit;
    }
}

echo json_encode(["success" => false, "error" => "Invalid request"]);
