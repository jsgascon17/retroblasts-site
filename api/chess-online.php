<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/_store.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$dataFile = __DIR__ . '/data/chess-games.json';

// Ensure data directory exists
if (!file_exists(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}

// Load games data
function loadGames() {
    global $dataFile;
    if (!file_exists($dataFile)) {
        return ['games' => []];
    }
    $content = file_get_contents($dataFile);
    return json_decode($content, true) ?: ['games' => []];
}

// Save games data
function saveGames($data) {
    global $dataFile;
    store_write($dataFile, $data);
}

// Generate random game code
function generateCode() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// Clean up old games (older than 24 hours)
function cleanupOldGames(&$data) {
    $cutoff = time() - (24 * 60 * 60);
    foreach ($data['games'] as $code => $game) {
        if ($game['lastUpdate'] < $cutoff) {
            unset($data['games'][$code]);
        }
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        // Create a new game room
        $data = loadGames();
        cleanupOldGames($data);

        $playerName = $_POST['playerName'] ?? 'Player 1';
        $timeControl = intval($_POST['timeControl'] ?? 0);

        // Generate unique code
        do {
            $code = generateCode();
        } while (isset($data['games'][$code]));

        $data['games'][$code] = [
            'code' => $code,
            'status' => 'waiting', // waiting, playing, finished
            'white' => [
                'name' => $playerName,
                'id' => uniqid('p', true),
                'connected' => time()
            ],
            'black' => null,
            'fen' => 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1',
            'moves' => [],
            'turn' => 'w',
            'timeControl' => $timeControl,
            'whiteTime' => $timeControl,
            'blackTime' => $timeControl,
            'lastMoveTime' => null,
            'result' => null,
            'created' => time(),
            'lastUpdate' => time()
        ];

        saveGames($data);

        echo json_encode([
            'success' => true,
            'code' => $code,
            'playerId' => $data['games'][$code]['white']['id'],
            'color' => 'white'
        ]);
        break;

    case 'join':
        // Join an existing game
        $data = loadGames();
        $code = strtoupper($_POST['code'] ?? '');
        $playerName = $_POST['playerName'] ?? 'Player 2';

        if (!isset($data['games'][$code])) {
            echo json_encode(['success' => false, 'error' => 'Game not found']);
            break;
        }

        $game = &$data['games'][$code];

        if ($game['status'] !== 'waiting') {
            echo json_encode(['success' => false, 'error' => 'Game already in progress']);
            break;
        }

        $game['black'] = [
            'name' => $playerName,
            'id' => uniqid('p', true),
            'connected' => time()
        ];
        $game['status'] = 'playing';
        $game['lastMoveTime'] = time() * 1000; // milliseconds
        $game['lastUpdate'] = time();

        saveGames($data);

        echo json_encode([
            'success' => true,
            'code' => $code,
            'playerId' => $game['black']['id'],
            'color' => 'black',
            'game' => $game
        ]);
        break;

    case 'move':
        // Make a move
        $data = loadGames();
        $code = strtoupper($_POST['code'] ?? '');
        $playerId = $_POST['playerId'] ?? '';
        $move = json_decode($_POST['move'] ?? '{}', true);
        $fen = $_POST['fen'] ?? '';
        $san = $_POST['san'] ?? '';

        if (!isset($data['games'][$code])) {
            echo json_encode(['success' => false, 'error' => 'Game not found']);
            break;
        }

        $game = &$data['games'][$code];

        // Verify it's this player's turn
        $isWhite = $game['white'] && $game['white']['id'] === $playerId;
        $isBlack = $game['black'] && $game['black']['id'] === $playerId;

        if (!$isWhite && !$isBlack) {
            echo json_encode(['success' => false, 'error' => 'Invalid player']);
            break;
        }

        $playerColor = $isWhite ? 'w' : 'b';
        if ($game['turn'] !== $playerColor) {
            echo json_encode(['success' => false, 'error' => 'Not your turn']);
            break;
        }

        // Update time if time control is active
        if ($game['timeControl'] > 0 && $game['lastMoveTime']) {
            $elapsed = (time() * 1000 - $game['lastMoveTime']) / 1000;
            if ($playerColor === 'w') {
                $game['whiteTime'] = max(0, $game['whiteTime'] - $elapsed);
            } else {
                $game['blackTime'] = max(0, $game['blackTime'] - $elapsed);
            }
        }

        // Record the move
        $game['moves'][] = [
            'san' => $san,
            'fen' => $fen,
            'move' => $move,
            'time' => time() * 1000
        ];

        $game['fen'] = $fen;
        $game['turn'] = $playerColor === 'w' ? 'b' : 'w';
        $game['lastMoveTime'] = time() * 1000;
        $game['lastUpdate'] = time();

        saveGames($data);

        echo json_encode([
            'success' => true,
            'game' => $game
        ]);
        break;

    case 'poll':
        // Poll for game updates
        $data = loadGames();
        $code = strtoupper($_GET['code'] ?? '');
        $playerId = $_GET['playerId'] ?? '';
        $lastMove = intval($_GET['lastMove'] ?? 0);

        if (!isset($data['games'][$code])) {
            echo json_encode(['success' => false, 'error' => 'Game not found']);
            break;
        }

        $game = $data['games'][$code];

        // Update connected time
        $isWhite = $game['white'] && $game['white']['id'] === $playerId;
        $isBlack = $game['black'] && $game['black']['id'] === $playerId;

        if ($isWhite) {
            $data['games'][$code]['white']['connected'] = time();
            saveGames($data);
        } else if ($isBlack) {
            $data['games'][$code]['black']['connected'] = time();
            saveGames($data);
        }

        // Check if there are new moves
        $newMoves = [];
        foreach ($game['moves'] as $i => $move) {
            if ($i >= $lastMove) {
                $newMoves[] = $move;
            }
        }

        echo json_encode([
            'success' => true,
            'game' => $game,
            'newMoves' => $newMoves,
            'moveCount' => count($game['moves'])
        ]);
        break;

    case 'end':
        // End the game
        $data = loadGames();
        $code = strtoupper($_POST['code'] ?? '');
        $playerId = $_POST['playerId'] ?? '';
        $result = $_POST['result'] ?? '';
        $reason = $_POST['reason'] ?? '';

        if (!isset($data['games'][$code])) {
            echo json_encode(['success' => false, 'error' => 'Game not found']);
            break;
        }

        $game = &$data['games'][$code];
        $game['status'] = 'finished';
        $game['result'] = $result;
        $game['resultReason'] = $reason;
        $game['lastUpdate'] = time();

        saveGames($data);

        echo json_encode([
            'success' => true,
            'game' => $game
        ]);
        break;

    case 'resign':
        // Resign from the game
        $data = loadGames();
        $code = strtoupper($_POST['code'] ?? '');
        $playerId = $_POST['playerId'] ?? '';

        if (!isset($data['games'][$code])) {
            echo json_encode(['success' => false, 'error' => 'Game not found']);
            break;
        }

        $game = &$data['games'][$code];

        $isWhite = $game['white'] && $game['white']['id'] === $playerId;
        $winner = $isWhite ? 'black' : 'white';

        $game['status'] = 'finished';
        $game['result'] = $winner;
        $game['resultReason'] = 'resignation';
        $game['lastUpdate'] = time();

        saveGames($data);

        echo json_encode([
            'success' => true,
            'game' => $game
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
