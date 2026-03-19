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
    "pong", "breakout", "space-invaders", "pac-man",
    "asteroids", "doodle-jump", "crossy-road", "fruit-ninja",
    "geometry-dash", "cookie-clicker", "minesweeper", "sudoku",
    "wordle", "memory", "typing-test", "fishing",
    "capybara-clicker", "zombie-shooter", "piano-tiles", "whack-a-mole"
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

echo json_encode([
    "success" => true,
    "coinsEarned" => $coinsEarned,
    "newTotal" => $newTotal,
    "game" => $game,
    "score" => $score
]);
