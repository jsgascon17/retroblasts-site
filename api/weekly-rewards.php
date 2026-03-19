<?php
session_start();
header("Content-Type: application/json");

$usersFile = __DIR__ . "/../data/users.json";
$rewardsFile = __DIR__ . "/../data/weekly-rewards.json";
$leaderboardDir = __DIR__ . "/../leaderboards/";

$PRIZES = [1000, 500, 250];

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ["users" => []];
    return json_decode(file_get_contents($usersFile), true) ?: ["users" => []];
}

function writeUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

function readRewardsHistory() {
    global $rewardsFile;
    if (!file_exists($rewardsFile)) return ["lastProcessedWeek" => null, "history" => []];
    return json_decode(file_get_contents($rewardsFile), true) ?: ["lastProcessedWeek" => null, "history" => []];
}

function writeRewardsHistory($data) {
    global $rewardsFile;
    file_put_contents($rewardsFile, json_encode($data, JSON_PRETTY_PRINT));
}

function getCurrentWeek() { return date("Y-\WW"); }
function getPreviousWeek() { return date("Y-\WW", strtotime("-1 week")); }

function getTop3FromLeaderboard($file) {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    if (!isset($data["scores"])) return [];
    usort($data["scores"], function($a, $b) { return $b["score"] - $a["score"]; });
    return array_slice($data["scores"], 0, 3);
}

$action = $_GET["action"] ?? "";
$input = json_decode(file_get_contents("php://input"), true) ?: [];
if (isset($input["action"])) $action = $input["action"];

switch ($action) {
    case "check":
        $history = readRewardsHistory();
        $lastWeek = getPreviousWeek();
        $pending = $history["lastProcessedWeek"] !== $lastWeek;
        echo json_encode([
            "success" => true,
            "currentWeek" => getCurrentWeek(),
            "lastProcessedWeek" => $history["lastProcessedWeek"],
            "rewardsPending" => $pending,
            "pendingWeek" => $pending ? $lastWeek : null
        ]);
        break;
        
    case "distribute":
        $history = readRewardsHistory();
        $lastWeek = getPreviousWeek();
        
        if ($history["lastProcessedWeek"] === $lastWeek) {
            echo json_encode(["success" => false, "error" => "Already distributed for " . $lastWeek]);
            exit;
        }
        
        $users = readUsers();
        $files = glob($leaderboardDir . "*.json") ?: [];
        $winners = [];
        $totalCoins = 0;
        
        foreach ($files as $file) {
            $game = basename($file, ".json");
            $top3 = getTop3FromLeaderboard($file);
            $gameWinners = [];
            
            foreach ($top3 as $rank => $entry) {
                $name = strtolower($entry["name"] ?? "");
                $prize = $PRIZES[$rank] ?? 0;
                if ($prize <= 0) continue;
                
                $foundUser = null;
                foreach ($users["users"] as $uname => &$u) {
                    $dname = strtolower($u["displayName"] ?? $uname);
                    if ($dname === $name || strtolower($uname) === $name) {
                        $foundUser = $uname;
                        break;
                    }
                }
                
                $awarded = false;
                if ($foundUser) {
                    $users["users"][$foundUser]["coins"] = ($users["users"][$foundUser]["coins"] ?? 0) + $prize;
                    $awarded = true;
                    $totalCoins += $prize;
                }
                
                $gameWinners[] = ["rank" => $rank + 1, "name" => $entry["name"], "score" => $entry["score"], "prize" => $prize, "awarded" => $awarded];
            }
            
            if (!empty($gameWinners)) $winners[$game] = $gameWinners;
        }
        
        writeUsers($users);
        
        $history["lastProcessedWeek"] = $lastWeek;
        $history["history"][] = ["week" => $lastWeek, "processedAt" => date("c"), "totalCoins" => $totalCoins, "winners" => $winners];
        if (count($history["history"]) > 10) $history["history"] = array_slice($history["history"], -10);
        writeRewardsHistory($history);
        
        echo json_encode(["success" => true, "week" => $lastWeek, "totalCoinsAwarded" => $totalCoins, "winners" => $winners]);
        break;
        
    case "history":
        $history = readRewardsHistory();
        echo json_encode(["success" => true, "history" => array_slice($history["history"], -5)]);
        break;
        
    default:
        echo json_encode(["success" => false, "error" => "Use: check, distribute, or history"]);
}
