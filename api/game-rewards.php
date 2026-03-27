<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit(0);
}

$usersFile = __DIR__ . "/../data/users.json";

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ["users" => []];
    return json_decode(file_get_contents($usersFile), true) ?: ["users" => []];
}

function writeUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

// Valid games list
$validGames = [
    "snake", "tetris", "flappy-bird", "2048",
    "pong", "breakout", "space-invaders", "pac-man", "pacman",
    "asteroids", "doodle-jump", "crossy-road", "fruit-ninja",
    "geometry-dash", "cookie-clicker", "minesweeper", "sudoku",
    "wordle", "memory", "typing-test", "fishing",
    "capybara-clicker", "zombie-shooter", "piano-tiles", "whack-a-mole",
    "brick-breaker", "platformer", "pop-the-lock", "retro-bowl",
    "tower-defense", "war-simulator", "dropper", "knife-hit"
];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "error" => "POST required"]);
    exit;
}

// Check if logged in
if (!isset($_SESSION["user"])) {
    echo json_encode(["success" => false, "error" => "Not logged in", "coinsEarned" => 0]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$game = $input["game"] ?? "";
$score = intval($input["score"] ?? 0);

// Validate game
if (!in_array($game, $validGames)) {
    echo json_encode(["success" => false, "error" => "Invalid game"]);
    exit;
}

// Validate score
if ($score < 0 || $score > 999999999) {
    echo json_encode(["success" => false, "error" => "Invalid score"]);
    exit;
}

// Calculate coins: 1 coin per 50 points
$coinsEarned = floor($score / 50);

// Cap at reasonable amount per game (prevent exploits)
$coinsEarned = min($coinsEarned, 1000);

// Update user coins
$data = readUsers();
$username = $_SESSION["user"];

if (!isset($data["users"][$username])) {
    echo json_encode(["success" => false, "error" => "User not found"]);
    exit;
}

$data["users"][$username]["coins"] = ($data["users"][$username]["coins"] ?? 0) + $coinsEarned;
$newTotal = $data["users"][$username]["coins"];

// Track stats
if (!isset($data["users"][$username]["stats"]["totalCoinsFromGames"])) {
    $data["users"][$username]["stats"]["totalCoinsFromGames"] = 0;
}
$data["users"][$username]["stats"]["totalCoinsFromGames"] += $coinsEarned;

writeUsers($data);

// Update daily challenge progress
updateChallengeProgress($username, $game, $score, $coinsEarned);

echo json_encode([
    "success" => true,
    "coinsEarned" => $coinsEarned,
    "newTotal" => $newTotal,
    "game" => $game,
    "score" => $score
]);

function updateChallengeProgress($username, $game, $score, $coins) {
    $challengeFile = __DIR__ . "/../data/challenges.json";
    $today = date("Y-m-d");
    
    // Challenge definitions (must match challenges.php)
    $CHALLENGES = [
        ["type" => "play", "game" => "snake", "target" => 3, "reward" => 50],
        ["type" => "play", "game" => "tetris", "target" => 3, "reward" => 50],
        ["type" => "play", "game" => "pacman", "target" => 3, "reward" => 50],
        ["type" => "play", "game" => "flappy-bird", "target" => 5, "reward" => 60],
        ["type" => "play", "game" => "2048", "target" => 3, "reward" => 50],
        ["type" => "play", "game" => "brick-breaker", "target" => 3, "reward" => 50],
        ["type" => "score", "game" => "snake", "target" => 50, "reward" => 75],
        ["type" => "score", "game" => "tetris", "target" => 500, "reward" => 75],
        ["type" => "score", "game" => "flappy-bird", "target" => 10, "reward" => 100],
        ["type" => "score", "game" => "2048", "target" => 1000, "reward" => 80],
        ["type" => "score", "game" => "pacman", "target" => 500, "reward" => 75],
        ["type" => "score", "game" => "space-invaders", "target" => 300, "reward" => 75],
        ["type" => "score", "game" => "brick-breaker", "target" => 200, "reward" => 75],
        ["type" => "total_score", "target" => 500, "reward" => 100],
        ["type" => "total_score", "target" => 1000, "reward" => 150],
        ["type" => "play_any", "target" => 5, "reward" => 60],
        ["type" => "play_any", "target" => 10, "reward" => 120],
        ["type" => "variety", "target" => 3, "reward" => 100],
        ["type" => "variety", "target" => 5, "reward" => 150],
        ["type" => "earn_coins", "target" => 100, "reward" => 75],
        ["type" => "earn_coins", "target" => 200, "reward" => 125],
    ];
    
    // Generate same challenges as challenges.php
    $seed = crc32($today);
    srand($seed);
    
    $easy = array_values(array_filter($CHALLENGES, fn($c) => $c["reward"] <= 60));
    $medium = array_values(array_filter($CHALLENGES, fn($c) => $c["reward"] > 60 && $c["reward"] <= 100));
    $hard = array_values(array_filter($CHALLENGES, fn($c) => $c["reward"] > 100));
    
    $dailyChallenges = [];
    if (count($easy) > 0) $dailyChallenges[] = $easy[array_rand($easy)];
    if (count($medium) > 0) $dailyChallenges[] = $medium[array_rand($medium)];
    if (count($hard) > 0) $dailyChallenges[] = $hard[array_rand($hard)];
    
    // Load challenge data
    $challengeData = file_exists($challengeFile) ? json_decode(file_get_contents($challengeFile), true) : [];
    
    if (!isset($challengeData[$username]) || $challengeData[$username]["date"] !== $today) {
        $challengeData[$username] = [
            "date" => $today,
            "progress" => [0, 0, 0],
            "completed" => [false, false, false],
            "claimed" => [false, false, false],
            "gamesPlayed" => [],
            "gamesPlayedCount" => 0,
            "totalScore" => 0,
            "coinsEarned" => 0
        ];
    }
    
    $userChallenge = &$challengeData[$username];
    
    // Track games
    if (!in_array($game, $userChallenge["gamesPlayed"])) {
        $userChallenge["gamesPlayed"][] = $game;
    }
    $userChallenge["gamesPlayedCount"] = ($userChallenge["gamesPlayedCount"] ?? 0) + 1;
    $userChallenge["totalScore"] += $score;
    $userChallenge["coinsEarned"] += $coins;
    
    // Update each challenge progress
    foreach ($dailyChallenges as $i => $challenge) {
        if ($userChallenge["completed"][$i]) continue;
        
        switch ($challenge["type"]) {
            case "play":
                if ($challenge["game"] === $game) {
                    $userChallenge["progress"][$i]++;
                }
                break;
            case "score":
                if ($challenge["game"] === $game && $score >= $challenge["target"]) {
                    $userChallenge["progress"][$i] = 1;
                }
                break;
            case "play_any":
                $userChallenge["progress"][$i] = $userChallenge["gamesPlayedCount"];
                break;
            case "variety":
                $userChallenge["progress"][$i] = count($userChallenge["gamesPlayed"]);
                break;
            case "total_score":
                $userChallenge["progress"][$i] = $userChallenge["totalScore"];
                break;
            case "earn_coins":
                $userChallenge["progress"][$i] = $userChallenge["coinsEarned"];
                break;
        }
        
        // Check completion
        if ($userChallenge["progress"][$i] >= $challenge["target"]) {
            $userChallenge["completed"][$i] = true;
        }
    }
    
    file_put_contents($challengeFile, json_encode($challengeData, JSON_PRETTY_PRINT));
}
