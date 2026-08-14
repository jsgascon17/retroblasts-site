<?php
session_start();
header("Content-Type: application/json");

require_once __DIR__ . '/_store.php';

$usersFile = __DIR__ . "/../data/users.json";

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ["users" => []];
    return store_hold_read($usersFile, ["users" => []]);
}

function writeUsers($data) {
    global $usersFile;
    store_hold_write($usersFile, $data);
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
$claimed = intval($input["coins"] ?? 0);
$game = $input["game"] ?? "unknown";
$score = intval($input["score"] ?? 0);

/*
 * The browser reports how many coins it thinks it earned, and the server has no
 * way to recompute that: coins are in-game collectibles whose rules live in each
 * game's JavaScript. So the number cannot be trusted, only bounded.
 *
 * The old per-request cap of 2000 bounded a single call and nothing else, which
 * meant unlimited coins for anyone willing to send the request twice.
 *
 * MAX_PER_REQUEST  keeps one absurd claim from landing.
 * MAX_PER_DAY      is the control that actually matters: it caps the total a
 *                  player can gain from game rewards in a UTC day, however many
 *                  requests they send.
 *
 * 5000/day was chosen against real balances rather than picked from the air: the
 * median account holds ~1,300 coins and the most active player has earned 64,680
 * in total. A day's allowance is therefore several times a typical player's
 * entire balance, which no honest session will reach.
 *
 * Both are tunable; nothing else depends on the numbers.
 */
const MAX_PER_REQUEST = 2000;
const MAX_PER_DAY     = 5000;

if ($claimed < 0 || $claimed > MAX_PER_REQUEST) {
    echo json_encode(["success" => false, "error" => "Invalid coin amount"]);
    exit;
}

$username = $_SESSION["user"];
$today = gmdate("Y-m-d");

// One exclusive lock across read, decide, and write, so two tabs finishing a
// game at the same moment cannot both spend the same daily allowance.
$result = store_mutate($usersFile, function (&$data) use ($username, $claimed, $score, $today) {
    if (!isset($data["users"][$username])) {
        return false;
    }
    $user = &$data["users"][$username];

    $earnedToday = (($user["coinDay"] ?? null) === $today)
        ? intval($user["coinDayTotal"] ?? 0)
        : 0;

    // Grant whatever is left of today's allowance rather than rejecting
    // outright, so hitting the cap mid-session does not look like an error.
    $granted = max(0, min($claimed, MAX_PER_DAY - $earnedToday));

    $user["coins"]        = ($user["coins"] ?? 0) + $granted;
    $user["coinDay"]      = $today;
    $user["coinDayTotal"] = $earnedToday + $granted;

    // XP tracks real play, so it follows the score when there is one and the
    // coins actually granted otherwise - never the unbounded claim.
    $xpEarned = 0;
    if ($score > 0) {
        $xpEarned = max(5, intdiv($score, 100));
    } elseif ($granted > 0) {
        $xpEarned = max(5, intdiv($granted, 2));
    }
    if ($xpEarned > 0) {
        $user["xp"] = ($user["xp"] ?? 0) + $xpEarned;
    }

    return [
        "granted"   => $granted,
        "capped"    => $granted < $claimed,
        "remaining" => MAX_PER_DAY - $user["coinDayTotal"],
        "newTotal"  => $user["coins"],
        "xpEarned"  => $xpEarned,
        "newXP"     => $user["xp"] ?? 0,
    ];
}, ["users" => []]);

if ($result === false) {
    echo json_encode(["success" => false, "error" => "User not found"]);
    exit;
}
if ($result === null) {
    echo json_encode(["success" => false, "error" => "Could not update coins"]);
    exit;
}

// success stays true even when capped: the games only read .success and
// .coinsEarned, and a capped round is not an error the player should see as one.
echo json_encode([
    "success"        => true,
    "coinsAdded"     => $result["granted"],
    "coinsEarned"    => $result["granted"],
    "newTotal"       => $result["newTotal"],
    "xpEarned"       => $result["xpEarned"],
    "newXP"          => $result["newXP"],
    "dailyCapped"    => $result["capped"],
    "dailyRemaining" => $result["remaining"],
]);
