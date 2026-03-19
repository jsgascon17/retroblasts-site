<?php
header('Content-Type: application/json');
session_start();

$dataFile = __DIR__ . '/../data/rooms.json';

function loadRooms() {
    global $dataFile;
    if (!file_exists($dataFile)) return [];
    $data = json_decode(file_get_contents($dataFile), true);
    return $data ?: [];
}

function saveRooms($data) {
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

function generateCode() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// Clean up stale rooms (older than 30 minutes without activity)
function cleanupStaleRooms(&$rooms) {
    $cutoff = time() - 1800;
    foreach ($rooms as $code => $room) {
        if (isset($room['lastActivity']) && strtotime($room['lastActivity']) < $cutoff) {
            unset($rooms[$code]);
        }
    }
}

$rooms = loadRooms();
cleanupStaleRooms($rooms);
saveRooms($rooms);

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    // List rooms
    if ($action === 'list') {
        $username = $_SESSION['user'] ?? null;
        $friends = $username ? loadFriends($username) : [];

        $publicRooms = [];
        $friendsRooms = [];

        foreach ($rooms as $code => $room) {
            if ($room['status'] !== 'waiting') continue;

            $roomData = [
                'code' => $code,
                'name' => $room['name'],
                'game' => $room['game'],
                'maxPlayers' => $room['maxPlayers'],
                'timeLimit' => $room['timeLimit'],
                'private' => $room['private'],
                'players' => array_map(function($p) {
                    return ['username' => $p['username'], 'avatar' => $p['avatar'] ?? '👤'];
                }, $room['players'])
            ];

            // Check if any player is a friend
            $hasFriend = false;
            foreach ($room['players'] as $player) {
                if (in_array($player['username'], $friends)) {
                    $hasFriend = true;
                    break;
                }
            }

            if ($hasFriend) {
                $friendsRooms[] = $roomData;
            } elseif (!$room['private']) {
                $publicRooms[] = $roomData;
            }
        }

        echo json_encode(['success' => true, 'public' => $publicRooms, 'friends' => $friendsRooms]);
        exit;
    }

    // Get specific room
    if ($action === 'get') {
        $code = $_GET['code'] ?? '';

        if (!isset($rooms[$code])) {
            echo json_encode(['success' => false, 'error' => 'Room not found']);
            exit;
        }

        echo json_encode(['success' => true, 'room' => $rooms[$code]]);
        exit;
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    // Create room
    if ($action === 'create') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $username = $_SESSION['user'];
        $users = loadUsers();
        $userInfo = $users['users'][$username] ?? [];

        $code = generateCode();
        while (isset($rooms[$code])) {
            $code = generateCode();
        }

        $room = [
            'code' => $code,
            'name' => $input['name'] ?? 'Game Room',
            'game' => $input['game'] ?? 'snake',
            'maxPlayers' => min(8, max(2, $input['maxPlayers'] ?? 4)),
            'timeLimit' => $input['timeLimit'] ?? 0,
            'private' => $input['private'] ?? false,
            'allowSpectators' => $input['allowSpectators'] ?? true,
            'status' => 'waiting',
            'host' => $username,
            'players' => [
                [
                    'username' => $username,
                    'displayName' => $userInfo['displayName'] ?? $username,
                    'avatar' => $userInfo['avatar'] ?? '👤',
                    'ready' => true
                ]
            ],
            'spectators' => [],
            'createdAt' => date('c'),
            'lastActivity' => date('c')
        ];

        $rooms[$code] = $room;
        saveRooms($rooms);

        echo json_encode(['success' => true, 'room' => $room]);
        exit;
    }

    // Join room
    if ($action === 'join') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $code = strtoupper($input['code'] ?? '');
        $username = $_SESSION['user'];

        if (!isset($rooms[$code])) {
            echo json_encode(['success' => false, 'error' => 'Room not found']);
            exit;
        }

        $room = &$rooms[$code];

        if ($room['status'] !== 'waiting') {
            echo json_encode(['success' => false, 'error' => 'Game already started']);
            exit;
        }

        if (count($room['players']) >= $room['maxPlayers']) {
            echo json_encode(['success' => false, 'error' => 'Room is full']);
            exit;
        }

        // Check if already in room
        foreach ($room['players'] as $player) {
            if ($player['username'] === $username) {
                echo json_encode(['success' => true, 'room' => $room]);
                exit;
            }
        }

        $users = loadUsers();
        $userInfo = $users['users'][$username] ?? [];

        $room['players'][] = [
            'username' => $username,
            'displayName' => $userInfo['displayName'] ?? $username,
            'avatar' => $userInfo['avatar'] ?? '👤',
            'ready' => false
        ];
        $room['lastActivity'] = date('c');

        saveRooms($rooms);
        echo json_encode(['success' => true, 'room' => $room]);
        exit;
    }

    // Leave room
    if ($action === 'leave') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $code = $input['code'] ?? '';
        $username = $_SESSION['user'];

        if (!isset($rooms[$code])) {
            echo json_encode(['success' => true]);
            exit;
        }

        $room = &$rooms[$code];

        // Remove player
        $room['players'] = array_filter($room['players'], function($p) use ($username) {
            return $p['username'] !== $username;
        });
        $room['players'] = array_values($room['players']);

        // If room is empty or host left, delete room
        if (count($room['players']) === 0 || $room['host'] === $username) {
            unset($rooms[$code]);
        } else {
            // Assign new host
            $room['host'] = $room['players'][0]['username'];
            $room['players'][0]['ready'] = true;
        }

        saveRooms($rooms);
        echo json_encode(['success' => true]);
        exit;
    }

    // Toggle ready
    if ($action === 'ready') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $code = $input['code'] ?? '';
        $username = $_SESSION['user'];
        $ready = $input['ready'] ?? false;

        if (!isset($rooms[$code])) {
            echo json_encode(['success' => false, 'error' => 'Room not found']);
            exit;
        }

        foreach ($rooms[$code]['players'] as &$player) {
            if ($player['username'] === $username) {
                $player['ready'] = $ready;
                break;
            }
        }

        $rooms[$code]['lastActivity'] = date('c');
        saveRooms($rooms);

        echo json_encode(['success' => true]);
        exit;
    }

    // Start game
    if ($action === 'start') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $code = $input['code'] ?? '';
        $username = $_SESSION['user'];

        if (!isset($rooms[$code])) {
            echo json_encode(['success' => false, 'error' => 'Room not found']);
            exit;
        }

        $room = &$rooms[$code];

        if ($room['host'] !== $username) {
            echo json_encode(['success' => false, 'error' => 'Only host can start']);
            exit;
        }

        $room['status'] = 'playing';
        $room['startedAt'] = date('c');
        $room['lastActivity'] = date('c');

        saveRooms($rooms);
        echo json_encode(['success' => true]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
