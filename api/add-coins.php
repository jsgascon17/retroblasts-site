<?php
session_start();
header("Content-Type: application/json");

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

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "error" => "POST required"]);
    exit;
}

if (!isset($_SESSION["user"])) {
    echo json_encode(["success" => false, "error" => "Not logged in"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$coins = intval($input["coins"] ?? 0);
$game = $input["game"] ?? "unknown";
$score = intval($input["score"] ?? 0);

// Validate coins (cap at 2000 per request to prevent exploits)
if ($coins < 0 || $coins > 2000) {
    echo json_encode(["success" => false, "error" => "Invalid coin amount"]);
    exit;
}

$data = readUsers();
$username = $_SESSION["user"];

if (!isset($data["users"][$username])) {
    echo json_encode(["success" => false, "error" => "User not found"]);
    exit;
}

// Add coins
$data["users"][$username]["coins"] = ($data["users"][$username]["coins"] ?? 0) + $coins;
$newTotal = $data["users"][$username]["coins"];

// Calculate XP based on score (1 XP per 100 points, minimum 5 XP)
$xpEarned = 0;
if ($score > 0) {
    $xpEarned = max(5, floor($score / 100));
} else if ($coins > 0) {
    // Fallback to coins if no score provided
    $xpEarned = max(5, floor($coins / 2));
}

if ($xpEarned > 0) {
    $data["users"][$username]["xp"] = ($data["users"][$username]["xp"] ?? 0) + $xpEarned;
}

$newXP = $data["users"][$username]["xp"] ?? 0;

writeUsers($data);

echo json_encode([
    "success" => true,
    "coinsAdded" => $coins,
    "coinsEarned" => $coins,
    "newTotal" => $newTotal,
    "xpEarned" => $xpEarned,
    "newXP" => $newXP
]);
