<?php
/**
 * Notifications API
 * 
 * GET:
 *   ?action=list - Get user notifications
 *   ?action=unread-count - Get count of unread notifications
 * 
 * POST:
 *   { action: "mark-read", id: "..." } - Mark notification as read
 *   { action: "mark-all-read" } - Mark all as read
 *   { action: "delete", id: "..." } - Delete notification
 */

session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
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

// Create notification helper function
function createNotification($username, $type, $message, $data = []) {
    $usersData = readUsers();
    if (!isset($usersData["users"][$username])) return false;
    
    if (!isset($usersData["users"][$username]["notifications"])) {
        $usersData["users"][$username]["notifications"] = [];
    }
    
    $notification = [
        "id" => uniqid(),
        "type" => $type,
        "message" => $message,
        "data" => $data,
        "read" => false,
        "createdAt" => date("c")
    ];
    
    array_unshift($usersData["users"][$username]["notifications"], $notification);
    
    // Keep only last 50 notifications
    $usersData["users"][$username]["notifications"] = array_slice(
        $usersData["users"][$username]["notifications"], 0, 50
    );
    
    writeUsers($usersData);
    return true;
}

// Check if logged in
if (!isset($_SESSION["user"])) {
    echo json_encode(["success" => false, "error" => "Not logged in"]);
    exit();
}

$currentUser = $_SESSION["user"];
$data = readUsers();

if (!isset($data["users"][$currentUser])) {
    echo json_encode(["success" => false, "error" => "User not found"]);
    exit();
}

$user = &$data["users"][$currentUser];
if (!isset($user["notifications"])) {
    $user["notifications"] = [];
}

// Handle GET requests
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $action = $_GET["action"] ?? "list";
    
    if ($action === "list") {
        echo json_encode([
            "success" => true,
            "notifications" => $user["notifications"]
        ]);
        exit();
    }
    
    if ($action === "unread-count") {
        $count = count(array_filter($user["notifications"], function($n) {
            return !$n["read"];
        }));
        echo json_encode(["success" => true, "count" => $count]);
        exit();
    }
    
    echo json_encode(["success" => false, "error" => "Invalid action"]);
    exit();
}

// Handle POST requests
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);
    $action = $input["action"] ?? "";
    
    if ($action === "mark-read") {
        $id = $input["id"] ?? "";
        foreach ($user["notifications"] as &$notif) {
            if ($notif["id"] === $id) {
                $notif["read"] = true;
                break;
            }
        }
        writeUsers($data);
        echo json_encode(["success" => true]);
        exit();
    }
    
    if ($action === "mark-all-read") {
        foreach ($user["notifications"] as &$notif) {
            $notif["read"] = true;
        }
        writeUsers($data);
        echo json_encode(["success" => true]);
        exit();
    }
    
    if ($action === "delete") {
        $id = $input["id"] ?? "";
        $user["notifications"] = array_values(array_filter(
            $user["notifications"],
            function($n) use ($id) { return $n["id"] !== $id; }
        ));
        writeUsers($data);
        echo json_encode(["success" => true]);
        exit();
    }
    
    echo json_encode(["success" => false, "error" => "Invalid action"]);
    exit();
}

http_response_code(405);
echo json_encode(["success" => false, "error" => "Method not allowed"]);
