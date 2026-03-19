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

$data["users"][$username]["coins"] = ($data["users"][$username]["coins"] ?? 0) + $coins;
$newTotal = $data["users"][$username]["coins"];

writeUsers($data);

echo json_encode([
    "success" => true,
    "coinsAdded" => $coins,
    "newTotal" => $newTotal
]);
