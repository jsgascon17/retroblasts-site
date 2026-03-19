<?php
header('Content-Type: application/json');
session_start();

$dataFile = __DIR__ . '/../data/live-games.json';

function loadLiveGames() {
    global $dataFile;
    if (!file_exists($dataFile)) return [];
    $data = json_decode(file_get_contents($dataFile), true);
    return $data ?: [];
}

function saveLiveGames($data) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
}

function loadUsers() {
    $userFile = __DIR__ . '/../data/users.json';
    if (!file_exists($userFile)) return [];
    $data = json_decode(file_get_contents($userFile), true);
    return $data ?: [];
}

function loadFriends($username) {
    $friendsFile = __DIR__ . '/../data/friends.json';
    if (!file_exists($friendsFile)) return [];
    $data = json_decode(file_get_contents($friendsFile), true);
    return $data[$username] ?? [];
}

// Clean up stale games (older than 5 minutes without update)
function cleanupStaleGames(&$games) {
    $cutoff = time() - 300; // 5 minutes
    foreach ($games as $username => $game) {
        if (isset($game['lastUpdate']) && strtotime($game['lastUpdate']) < $cutoff) {
            unset($games[$username]);
        }
    }
}

$games = loadLiveGames();
cleanupStaleGames($games);

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    // Get live streams (friends only)
    if ($action === 'live') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => true, 'streams' => []]);
            exit;
        }

        $username = $_SESSION['user'];
        $friends = loadFriends($username);
        $users = loadUsers();

        $streams = [];
        foreach ($games as $player => $game) {
            // Only show friends
            if (in_array($player, $friends) || $player === $username) {
                $userInfo = $users['users'][$player] ?? [];
                $streams[] = [
                    'username' => $player,
                    'displayName' => $userInfo['displayName'] ?? $player,
                    'game' => $game['game'],
                    'score' => $game['score'] ?? 0,
                    'viewers' => count($game['viewers'] ?? []),
                    'startedAt' => $game['startedAt']
                ];
            }
        }

        echo json_encode(['success' => true, 'streams' => $streams]);
        exit;
    }

    // Watch a specific player
    if ($action === 'watch') {
        $player = $_GET['player'] ?? '';

        if (!isset($games[$player])) {
            echo json_encode(['success' => false, 'error' => 'Player not streaming']);
            exit;
        }

        $game = $games[$player];
        $users = loadUsers();
        $userInfo = $users['users'][$player] ?? [];

        echo json_encode([
            'success' => true,
            'state' => [
                'username' => $player,
                'displayName' => $userInfo['displayName'] ?? $player,
                'game' => $game['game'],
                'score' => $game['score'] ?? 0,
                'highScore' => $game['highScore'] ?? 0,
                'level' => $game['level'] ?? 1,
                'viewers' => count($game['viewers'] ?? []),
                'isLive' => true,
                'gameState' => $game['gameState'] ?? null
            ]
        ]);
        exit;
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    // Start broadcasting (called by game)
    if ($action === 'start') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $username = $_SESSION['user'];
        $game = $input['game'] ?? 'unknown';

        $games[$username] = [
            'game' => $game,
            'score' => 0,
            'highScore' => $input['highScore'] ?? 0,
            'level' => 1,
            'startedAt' => date('c'),
            'lastUpdate' => date('c'),
            'viewers' => [],
            'gameState' => null
        ];

        saveLiveGames($games);
        echo json_encode(['success' => true]);
        exit;
    }

    // Update game state (called by game during play)
    if ($action === 'update') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $username = $_SESSION['user'];

        if (!isset($games[$username])) {
            echo json_encode(['success' => false, 'error' => 'Not broadcasting']);
            exit;
        }

        $games[$username]['score'] = $input['score'] ?? $games[$username]['score'];
        $games[$username]['level'] = $input['level'] ?? $games[$username]['level'];
        $games[$username]['gameState'] = $input['gameState'] ?? null;
        $games[$username]['lastUpdate'] = date('c');

        saveLiveGames($games);
        echo json_encode(['success' => true, 'viewers' => count($games[$username]['viewers'])]);
        exit;
    }

    // Stop broadcasting
    if ($action === 'stop') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $username = $_SESSION['user'];
        unset($games[$username]);
        saveLiveGames($games);

        echo json_encode(['success' => true]);
        exit;
    }

    // Join as viewer
    if ($action === 'join') {
        $player = $input['player'] ?? '';
        $viewer = $_SESSION['user'] ?? 'anonymous_' . uniqid();

        if (isset($games[$player])) {
            if (!in_array($viewer, $games[$player]['viewers'])) {
                $games[$player]['viewers'][] = $viewer;
                saveLiveGames($games);
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // Leave as viewer
    if ($action === 'leave') {
        $player = $input['player'] ?? '';
        $viewer = $_SESSION['user'] ?? '';

        if (isset($games[$player]) && $viewer) {
            $games[$player]['viewers'] = array_filter(
                $games[$player]['viewers'],
                fn($v) => $v !== $viewer
            );
            saveLiveGames($games);
        }

        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
