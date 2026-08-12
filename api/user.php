<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: https://retroblasts.com");
header("Access-Control-Allow-Credentials: true");

$usersFile = __DIR__ . "/../data/users.json";

function loadUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ["users" => []];
    return json_decode(file_get_contents($usersFile), true) ?: ["users" => []];
}

$action = $_GET["action"] ?? "profile";

if ($action === "profile") {
    if (!isset($_SESSION["user"])) {
        echo json_encode(["success" => false, "error" => "Not logged in"]);
        exit;
    }
    
    $username = $_SESSION["user"];
    $users = loadUsers();
    
    if (!isset($users["users"][$username])) {
        echo json_encode(["success" => false, "error" => "User not found"]);
        exit;
    }
    
    $user = $users["users"][$username];
    
    echo json_encode([
        "success" => true,
        "username" => $username,
        "displayName" => $user["displayName"] ?? $username,
        "coins" => $user["coins"] ?? 0,
        "xp" => $user["xp"] ?? 0,
        "level" => floor(($user["xp"] ?? 0) / 100) + 1,
        "avatar" => $user["avatar"] ?? "default"
    ]);
    exit;
}

echo json_encode(["success" => false, "error" => "Invalid action"]);
