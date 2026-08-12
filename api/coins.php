<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/_store.php';

// Server-decided ad reward. shop.html shows AD_REWARD = 100; keep these in step.
const AD_REWARD_COINS   = 100;
const AD_REWARDS_PER_DAY = 20;   // ceiling: 2000 coins/day, matching add-coins.php

$usersFile = __DIR__ . '/../data/users.json';

function loadUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function saveUsers($data) {
    global $usersFile;
    store_write($usersFile, $data);
}

$username = $_SESSION['user'] ?? null;

if (!$username) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

$users = loadUsers();

if (!isset($users['users'][$username])) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

switch ($action) {
    case 'get':
        echo json_encode([
            'success' => true,
            'coins' => $users['users'][$username]['coins'] ?? 0
        ]);
        break;
        
    case 'deduct':
        $amount = intval($input['amount'] ?? 0);
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid amount']);
            exit;
        }

        // The balance check and the write must happen under one lock, or two
        // concurrent purchases can both pass "can afford" against the same
        // snapshot and overdraw the account.
        $result = store_mutate($usersFile, function (&$data) use ($username, $amount) {
            if (!isset($data['users'][$username])) {
                return false;
            }
            $current = $data['users'][$username]['coins'] ?? 0;
            if ($current < $amount) {
                return ['ok' => false, 'coins' => $current];
            }
            $data['users'][$username]['coins'] = $current - $amount;
            return ['ok' => true, 'coins' => $data['users'][$username]['coins']];
        }, ['users' => []]);

        if ($result === false || $result === null) {
            echo json_encode(['success' => false, 'error' => 'Could not update coins']);
            exit;
        }
        if (!$result['ok']) {
            echo json_encode([
                'success' => false,
                'error'   => 'Not enough coins',
                'coins'   => $result['coins'],
            ]);
            exit;
        }

        echo json_encode(['success' => true, 'coins' => $result['coins']]);
        break;
        
    case 'add':
        // This backs the "Watch Ad" button in shop.html, which is the only
        // legitimate caller. It used to trust the client's `amount` up to
        // 10000 with no cap on how often it could be called, so any logged-in
        // user could mint unlimited coins with a single repeated request.
        //
        // The server now decides the amount, and a daily ceiling bounds the
        // total. There is no server-side proof an ad was actually watched --
        // that would need the ad network's callback -- so the ceiling is the
        // control, not the reward value.
        $granted = AD_REWARD_COINS;
        $today = gmdate('Y-m-d');

        $result = store_mutate($usersFile, function (&$data) use ($username, $granted, $today) {
            if (!isset($data['users'][$username])) {
                return false;
            }
            $user = &$data['users'][$username];

            $claimed = 0;
            if (($user['adRewardDate'] ?? null) === $today) {
                $claimed = intval($user['adRewardCount'] ?? 0);
            }
            if ($claimed >= AD_REWARDS_PER_DAY) {
                return ['capped' => true, 'coins' => $user['coins'] ?? 0];
            }

            $user['coins'] = ($user['coins'] ?? 0) + $granted;
            $user['adRewardDate'] = $today;
            $user['adRewardCount'] = $claimed + 1;

            return [
                'capped'    => false,
                'coins'     => $user['coins'],
                'remaining' => AD_REWARDS_PER_DAY - ($claimed + 1),
            ];
        }, ['users' => []]);

        if ($result === false || $result === null) {
            echo json_encode(['success' => false, 'error' => 'Could not update coins']);
            exit;
        }
        if ($result['capped']) {
            echo json_encode([
                'success' => false,
                'error'   => 'Daily ad reward limit reached',
                'coins'   => $result['coins'],
            ]);
            exit;
        }

        echo json_encode([
            'success'   => true,
            'coins'     => $result['coins'],
            'awarded'   => $granted,
            'remaining' => $result['remaining'],
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
