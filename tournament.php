<?php
/**
 * Tournament System API
 *
 * GET:
 *   ?action=list              - Get all tournaments
 *   ?action=active            - Get active tournaments only
 *   ?action=scores&id=X       - Get scores for tournament X
 *
 * POST:
 *   { "action": "submit", "tournamentId": 1, "name": "Player", "score": 100 }
 *   { "action": "create", "game": "snake", "name": "Snake Sprint", "duration": 86400 }  (admin only)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dataFile = __DIR__ . '/tournaments.json';

// Game info
$games = [
    'flappy-bird' => ['name' => 'Flappy Bird', 'icon' => '🐤'],
    'snake' => ['name' => 'Snake', 'icon' => '🐍'],
    'space-invaders' => ['name' => 'Space Invaders', 'icon' => '👾'],
    'pac-man' => ['name' => 'Pac-Man', 'icon' => '🟡'],
    'whack-a-mole' => ['name' => 'Whack-a-Mole', 'icon' => '🐹'],
    'geometry-dash' => ['name' => 'Geometry Dash', 'icon' => '🔷'],
    'doodle-jump' => ['name' => 'Doodle Jump', 'icon' => '🦘'],
    'knife-hit' => ['name' => 'Knife Hit', 'icon' => '🔪'],
    'pop-the-lock' => ['name' => 'Pop the Lock', 'icon' => '🔓'],
    'crossy-road' => ['name' => 'Crossy Road', 'icon' => '🐔'],
    'fruit-ninja' => ['name' => 'Fruit Ninja', 'icon' => '🍉'],
    'asteroids' => ['name' => 'Asteroids', 'icon' => '🪨'],
    '2048' => ['name' => '2048', 'icon' => '🔢'],
    'tetris' => ['name' => 'Tetris', 'icon' => '🧱'],
    'minesweeper' => ['name' => 'Minesweeper', 'icon' => '💣'],
];

function readData() {
    global $dataFile;
    if (!file_exists($dataFile)) {
        return ['tournaments' => [], 'nextId' => 1];
    }
    return json_decode(file_get_contents($dataFile), true) ?: ['tournaments' => [], 'nextId' => 1];
}

function writeData($data) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
}

function sanitize($input, $maxLength = 15) {
    $clean = strip_tags(trim($input));
    $clean = preg_replace('/[^\w\s\-]/', '', $clean);
    return substr($clean, 0, $maxLength);
}

function getTournamentStatus($tournament) {
    $now = time();
    $start = strtotime($tournament['startTime']);
    $end = strtotime($tournament['endTime']);

    if ($now < $start) return 'upcoming';
    if ($now > $end) return 'ended';
    return 'active';
}

function getTimeRemaining($endTime) {
    $now = time();
    $end = strtotime($endTime);
    $diff = $end - $now;

    if ($diff <= 0) return 'Ended';

    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);

    if ($days > 0) return "{$days}d {$hours}h left";
    if ($hours > 0) return "{$hours}h {$minutes}m left";
    return "{$minutes}m left";
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $data = readData();

    // Update statuses
    foreach ($data['tournaments'] as &$t) {
        $t['status'] = getTournamentStatus($t);
        $t['timeRemaining'] = getTimeRemaining($t['endTime']);
    }

    if ($action === 'active') {
        $active = array_filter($data['tournaments'], function($t) {
            return $t['status'] === 'active';
        });
        echo json_encode(['success' => true, 'tournaments' => array_values($active)]);
    } elseif ($action === 'scores') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $tournament = null;
        foreach ($data['tournaments'] as $t) {
            if ($t['id'] === $id) {
                $tournament = $t;
                break;
            }
        }
        if (!$tournament) {
            echo json_encode(['success' => false, 'error' => 'Tournament not found']);
            exit();
        }

        // Sort scores
        usort($tournament['scores'], function($a, $b) {
            return $b['score'] - $a['score'];
        });

        echo json_encode(['success' => true, 'tournament' => $tournament]);
    } else {
        // Sort by status (active first) then by end time
        usort($data['tournaments'], function($a, $b) {
            $statusOrder = ['active' => 0, 'upcoming' => 1, 'ended' => 2];
            $aOrder = $statusOrder[$a['status']] ?? 3;
            $bOrder = $statusOrder[$b['status']] ?? 3;
            if ($aOrder !== $bOrder) return $aOrder - $bOrder;
            return strtotime($b['endTime']) - strtotime($a['endTime']);
        });

        echo json_encode(['success' => true, 'tournaments' => $data['tournaments'], 'games' => $games]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($input['action']) ? $input['action'] : '';

    if ($action === 'submit') {
        // Submit score to tournament
        $tournamentId = isset($input['tournamentId']) ? intval($input['tournamentId']) : 0;
        $name = sanitize($input['name'] ?? 'Anonymous');
        $score = intval($input['score'] ?? 0);

        if (empty($name)) $name = 'Anonymous';

        $data = readData();
        $tournamentIndex = -1;

        foreach ($data['tournaments'] as $i => $t) {
            if ($t['id'] === $tournamentId) {
                $tournamentIndex = $i;
                break;
            }
        }

        if ($tournamentIndex === -1) {
            echo json_encode(['success' => false, 'error' => 'Tournament not found']);
            exit();
        }

        $tournament = &$data['tournaments'][$tournamentIndex];

        // Check if tournament is active
        if (getTournamentStatus($tournament) !== 'active') {
            echo json_encode(['success' => false, 'error' => 'Tournament is not active']);
            exit();
        }

        // Check for existing score by same player
        $existingIndex = -1;
        foreach ($tournament['scores'] as $i => $s) {
            if (strtolower($s['name']) === strtolower($name)) {
                $existingIndex = $i;
                break;
            }
        }

        if ($existingIndex >= 0) {
            // Only update if higher
            if ($score > $tournament['scores'][$existingIndex]['score']) {
                $tournament['scores'][$existingIndex] = [
                    'name' => $name,
                    'score' => $score,
                    'date' => date('Y-m-d H:i:s')
                ];
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'Your best tournament score is still ' . $tournament['scores'][$existingIndex]['score']
                ]);
                exit();
            }
        } else {
            $tournament['scores'][] = [
                'name' => $name,
                'score' => $score,
                'date' => date('Y-m-d H:i:s')
            ];
        }

        // Sort and find rank
        usort($tournament['scores'], function($a, $b) {
            return $b['score'] - $a['score'];
        });

        $rank = 1;
        foreach ($tournament['scores'] as $s) {
            if (strtolower($s['name']) === strtolower($name)) break;
            $rank++;
        }

        writeData($data);

        echo json_encode([
            'success' => true,
            'rank' => $rank,
            'totalPlayers' => count($tournament['scores']),
            'message' => $rank <= 3 ? "You're in the top 3!" : "Rank #$rank in tournament"
        ]);

    } elseif ($action === 'create') {
        // Create new tournament (simple admin - no auth for now)
        $game = strtolower(trim($input['game'] ?? ''));
        $name = trim($input['name'] ?? '');
        $duration = intval($input['duration'] ?? 86400); // Default 24 hours

        if (!isset($games[$game])) {
            echo json_encode(['success' => false, 'error' => 'Invalid game']);
            exit();
        }

        if (empty($name)) {
            $name = $games[$game]['name'] . ' Tournament';
        }

        $data = readData();

        $tournament = [
            'id' => $data['nextId']++,
            'game' => $game,
            'gameName' => $games[$game]['name'],
            'gameIcon' => $games[$game]['icon'],
            'name' => $name,
            'startTime' => date('Y-m-d H:i:s'),
            'endTime' => date('Y-m-d H:i:s', time() + $duration),
            'duration' => $duration,
            'scores' => [],
            'status' => 'active'
        ];

        $data['tournaments'][] = $tournament;
        writeData($data);

        echo json_encode(['success' => true, 'tournament' => $tournament]);

    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>
