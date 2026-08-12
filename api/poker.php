<?php
/**
 * Poker API for RetroBlasts
 * Handles multiplayer tables and tournaments
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

$dataDir = __DIR__ . '/../data/';
$tablesFile = $dataDir . 'poker-tables.json';
$tournamentsFile = $dataDir . 'poker-tournaments.json';
$usersFile = $dataDir . 'users.json';

// Get action from query or POST
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Check authentication
function getUser() {
    if (!isset($_SESSION['user'])) {
        return null;
    }
    return $_SESSION['user'];
}

function isOwner() {
    return getUser() === 'billybuffalo15';
}

// Load JSON file
function loadJson($file) {
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    return json_decode($content, true) ?: [];
}

// Save JSON file
function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Get user coins
function getUserCoins($username) {
    global $usersFile;
    $users = loadJson($usersFile);
    return $users['users'][$username]['coins'] ?? 0;
}

// Update user coins
function updateUserCoins($username, $amount) {
    global $usersFile;
    $users = loadJson($usersFile);
    if (!isset($users['users'][$username])) {
        $users['users'][$username] = ['coins' => 0, 'inventory' => []];
    }
    $users['users'][$username]['coins'] += $amount;
    saveJson($usersFile, $users);
    return $users['users'][$username]['coins'];
}

// Generate unique table code
function generateTableCode() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// Clean up stale tables (inactive for 30 minutes)
function cleanupTables() {
    global $tablesFile;
    $tables = loadJson($tablesFile);
    $now = time();
    $cleaned = false;

    foreach ($tables as $code => $table) {
        if ($now - ($table['lastActivity'] ?? 0) > 1800) {
            // Refund players still at table
            foreach ($table['players'] as $player) {
                if (!$player['isBot'] && $player['chips'] > 0) {
                    updateUserCoins($player['name'], $player['chips']);
                }
            }
            unset($tables[$code]);
            $cleaned = true;
        }
    }

    if ($cleaned) {
        saveJson($tablesFile, $tables);
    }

    return $tables;
}

// Main action handler
switch ($action) {
    case 'list-tables':
        $tables = cleanupTables();
        $result = [];

        foreach ($tables as $code => $table) {
            $result[] = [
                'code' => $code,
                'stakes' => $table['stakes'],
                'players' => count($table['players']),
                'maxPlayers' => $table['maxPlayers'],
                'status' => $table['status'],
                'minBuyin' => $table['minBuyin']
            ];
        }

        echo json_encode(['success' => true, 'tables' => $result]);
        break;

    case 'create-table':
        $user = getUser();
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $stakes = $_POST['stakes'] ?? 'low';
        $maxPlayers = min(6, max(2, intval($_POST['maxPlayers'] ?? 6)));
        $buyin = intval($_POST['buyin'] ?? 500);

        $stakeConfig = [
            'low' => ['sb' => 5, 'bb' => 10, 'min' => 100, 'max' => 1000],
            'medium' => ['sb' => 50, 'bb' => 100, 'min' => 1000, 'max' => 10000],
            'high' => ['sb' => 500, 'bb' => 1000, 'min' => 10000, 'max' => 100000]
        ];

        $config = $stakeConfig[$stakes] ?? $stakeConfig['low'];

        if ($buyin < $config['min'] || $buyin > $config['max']) {
            echo json_encode(['success' => false, 'error' => 'Invalid buy-in amount']);
            exit;
        }

        $userCoins = getUserCoins($user);
        if ($buyin > $userCoins) {
            echo json_encode(['success' => false, 'error' => 'Not enough coins']);
            exit;
        }

        // Deduct buy-in
        updateUserCoins($user, -$buyin);

        $tables = loadJson($tablesFile);

        // Generate unique code
        do {
            $code = generateTableCode();
        } while (isset($tables[$code]));

        $tables[$code] = [
            'code' => $code,
            'host' => $user,
            'stakes' => $stakes,
            'stakesConfig' => $config,
            'maxPlayers' => $maxPlayers,
            'minBuyin' => $config['min'],
            'status' => 'waiting',
            'players' => [
                [
                    'id' => 0,
                    'name' => $user,
                    'chips' => $buyin,
                    'seat' => 0,
                    'isBot' => false,
                    'cards' => [],
                    'bet' => 0,
                    'folded' => false,
                    'isAllIn' => false,
                    'lastAction' => time()
                ]
            ],
            'deck' => [],
            'communityCards' => [],
            'pot' => 0,
            'currentBet' => 0,
            'dealerIndex' => 0,
            'currentPlayerIndex' => 0,
            'round' => 'waiting',
            'handNumber' => 0,
            'lastActivity' => time(),
            'createdAt' => time()
        ];

        saveJson($tablesFile, $tables);

        echo json_encode([
            'success' => true,
            'code' => $code,
            'table' => $tables[$code]
        ]);
        break;

    case 'join-table':
        $user = getUser();
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $code = strtoupper($_POST['code'] ?? '');
        $buyin = intval($_POST['buyin'] ?? 0);

        $tables = loadJson($tablesFile);

        if (!isset($tables[$code])) {
            echo json_encode(['success' => false, 'error' => 'Table not found']);
            exit;
        }

        $table = &$tables[$code];

        // Check if already at table
        foreach ($table['players'] as $player) {
            if ($player['name'] === $user) {
                echo json_encode(['success' => false, 'error' => 'Already at this table']);
                exit;
            }
        }

        if (count($table['players']) >= $table['maxPlayers']) {
            echo json_encode(['success' => false, 'error' => 'Table is full']);
            exit;
        }

        if ($buyin < $table['minBuyin']) {
            echo json_encode(['success' => false, 'error' => 'Buy-in too low']);
            exit;
        }

        $userCoins = getUserCoins($user);
        if ($buyin > $userCoins) {
            echo json_encode(['success' => false, 'error' => 'Not enough coins']);
            exit;
        }

        // Deduct buy-in
        updateUserCoins($user, -$buyin);

        // Find available seat
        $usedSeats = array_column($table['players'], 'seat');
        $seat = 0;
        for ($i = 0; $i < 6; $i++) {
            if (!in_array($i, $usedSeats)) {
                $seat = $i;
                break;
            }
        }

        $table['players'][] = [
            'id' => count($table['players']),
            'name' => $user,
            'chips' => $buyin,
            'seat' => $seat,
            'isBot' => false,
            'cards' => [],
            'bet' => 0,
            'folded' => false,
            'isAllIn' => false,
            'lastAction' => time()
        ];

        $table['lastActivity'] = time();

        saveJson($tablesFile, $tables);

        echo json_encode([
            'success' => true,
            'table' => $table
        ]);
        break;

    case 'leave-table':
        $user = getUser();
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $code = strtoupper($_POST['code'] ?? '');

        $tables = loadJson($tablesFile);

        if (!isset($tables[$code])) {
            echo json_encode(['success' => false, 'error' => 'Table not found']);
            exit;
        }

        $table = &$tables[$code];

        // Find player
        $playerIndex = -1;
        $chips = 0;
        foreach ($table['players'] as $i => $player) {
            if ($player['name'] === $user) {
                $playerIndex = $i;
                $chips = $player['chips'];
                break;
            }
        }

        if ($playerIndex === -1) {
            echo json_encode(['success' => false, 'error' => 'Not at this table']);
            exit;
        }

        // Refund chips
        if ($chips > 0) {
            updateUserCoins($user, $chips);
        }

        // Remove player
        array_splice($table['players'], $playerIndex, 1);

        // Delete table if empty
        if (count($table['players']) === 0) {
            unset($tables[$code]);
        } else {
            // Reassign host if needed
            if ($table['host'] === $user) {
                $table['host'] = $table['players'][0]['name'];
            }
        }

        saveJson($tablesFile, $tables);

        echo json_encode([
            'success' => true,
            'refund' => $chips
        ]);
        break;

    case 'poll':
        $user = getUser();
        $code = strtoupper($_GET['code'] ?? '');
        $lastUpdate = intval($_GET['lastUpdate'] ?? 0);

        $tables = loadJson($tablesFile);

        if (!isset($tables[$code])) {
            echo json_encode(['success' => false, 'error' => 'Table not found']);
            exit;
        }

        $table = $tables[$code];

        // Find player index
        $myIndex = -1;
        foreach ($table['players'] as $i => $player) {
            if ($player['name'] === $user) {
                $myIndex = $i;
                break;
            }
        }

        // Hide other players' cards unless showdown
        if ($table['round'] !== 'showdown') {
            foreach ($table['players'] as $i => &$player) {
                if ($i !== $myIndex) {
                    $player['cards'] = count($player['cards']) > 0 ? ['hidden', 'hidden'] : [];
                }
            }
        }

        echo json_encode([
            'success' => true,
            'table' => $table,
            'myIndex' => $myIndex,
            'isMyTurn' => $myIndex === $table['currentPlayerIndex'] && $table['status'] === 'playing'
        ]);
        break;

    case 'action':
        $user = getUser();
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $code = strtoupper($_POST['code'] ?? '');
        $actionType = $_POST['actionType'] ?? '';
        $amount = intval($_POST['amount'] ?? 0);

        $tables = loadJson($tablesFile);

        if (!isset($tables[$code])) {
            echo json_encode(['success' => false, 'error' => 'Table not found']);
            exit;
        }

        $table = &$tables[$code];

        // Find player
        $playerIndex = -1;
        foreach ($table['players'] as $i => $player) {
            if ($player['name'] === $user) {
                $playerIndex = $i;
                break;
            }
        }

        if ($playerIndex === -1) {
            echo json_encode(['success' => false, 'error' => 'Not at this table']);
            exit;
        }

        if ($table['currentPlayerIndex'] !== $playerIndex) {
            echo json_encode(['success' => false, 'error' => 'Not your turn']);
            exit;
        }

        $player = &$table['players'][$playerIndex];
        $toCall = $table['currentBet'] - $player['bet'];

        switch ($actionType) {
            case 'fold':
                $player['folded'] = true;
                break;

            case 'check':
                if ($toCall > 0) {
                    echo json_encode(['success' => false, 'error' => 'Cannot check, must call']);
                    exit;
                }
                break;

            case 'call':
                $callAmount = min($toCall, $player['chips']);
                $player['chips'] -= $callAmount;
                $player['bet'] += $callAmount;
                $table['pot'] += $callAmount;
                if ($player['chips'] === 0) $player['isAllIn'] = true;
                break;

            case 'raise':
                if ($amount <= $table['currentBet']) {
                    echo json_encode(['success' => false, 'error' => 'Raise must be higher than current bet']);
                    exit;
                }
                $raiseAmount = $amount - $player['bet'];
                if ($raiseAmount > $player['chips']) {
                    $raiseAmount = $player['chips'];
                }
                $player['chips'] -= $raiseAmount;
                $player['bet'] += $raiseAmount;
                $table['pot'] += $raiseAmount;
                $table['currentBet'] = $player['bet'];
                if ($player['chips'] === 0) $player['isAllIn'] = true;
                break;

            case 'allin':
                $allInAmount = $player['chips'];
                $player['bet'] += $allInAmount;
                $player['chips'] = 0;
                $player['isAllIn'] = true;
                $table['pot'] += $allInAmount;
                if ($player['bet'] > $table['currentBet']) {
                    $table['currentBet'] = $player['bet'];
                }
                break;
        }

        $player['lastAction'] = time();
        $table['lastActivity'] = time();

        // Move to next player
        $table['currentPlayerIndex'] = ($table['currentPlayerIndex'] + 1) % count($table['players']);

        // TODO: Implement full game flow (betting rounds, showdown, etc.)

        saveJson($tablesFile, $tables);

        echo json_encode([
            'success' => true,
            'table' => $table
        ]);
        break;

    case 'start-game':
        $user = getUser();
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $code = strtoupper($_POST['code'] ?? '');

        $tables = loadJson($tablesFile);

        if (!isset($tables[$code])) {
            echo json_encode(['success' => false, 'error' => 'Table not found']);
            exit;
        }

        $table = &$tables[$code];

        if ($table['host'] !== $user && !isOwner()) {
            echo json_encode(['success' => false, 'error' => 'Only the host can start the game']);
            exit;
        }

        if (count($table['players']) < 2) {
            echo json_encode(['success' => false, 'error' => 'Need at least 2 players']);
            exit;
        }

        $table['status'] = 'playing';
        $table['round'] = 'preflop';
        $table['handNumber'] = 1;

        // Create and shuffle deck
        $suits = ['s', 'h', 'd', 'c'];
        $ranks = ['2', '3', '4', '5', '6', '7', '8', '9', 'T', 'J', 'Q', 'K', 'A'];
        $deck = [];
        foreach ($suits as $suit) {
            foreach ($ranks as $rank) {
                $deck[] = $rank . $suit;
            }
        }
        shuffle($deck);
        $table['deck'] = $deck;

        // Deal cards
        foreach ($table['players'] as &$player) {
            $player['cards'] = [array_pop($table['deck']), array_pop($table['deck'])];
            $player['folded'] = false;
            $player['bet'] = 0;
            $player['isAllIn'] = false;
        }

        // Post blinds
        $config = $table['stakesConfig'];
        $sbIndex = ($table['dealerIndex'] + 1) % count($table['players']);
        $bbIndex = ($table['dealerIndex'] + 2) % count($table['players']);

        $sbAmount = min($config['sb'], $table['players'][$sbIndex]['chips']);
        $table['players'][$sbIndex]['chips'] -= $sbAmount;
        $table['players'][$sbIndex]['bet'] = $sbAmount;

        $bbAmount = min($config['bb'], $table['players'][$bbIndex]['chips']);
        $table['players'][$bbIndex]['chips'] -= $bbAmount;
        $table['players'][$bbIndex]['bet'] = $bbAmount;

        $table['pot'] = $sbAmount + $bbAmount;
        $table['currentBet'] = $bbAmount;

        // UTG is first to act
        $table['currentPlayerIndex'] = ($bbIndex + 1) % count($table['players']);

        $table['lastActivity'] = time();

        saveJson($tablesFile, $tables);

        echo json_encode([
            'success' => true,
            'table' => $table
        ]);
        break;

    // Tournament endpoints
    case 'list-tournaments':
        $tournaments = loadJson($tournamentsFile);
        echo json_encode(['success' => true, 'tournaments' => array_values($tournaments)]);
        break;

    case 'create-tournament':
        if (!isOwner()) {
            echo json_encode(['success' => false, 'error' => 'Only owner can create tournaments']);
            exit;
        }

        $name = $_POST['name'] ?? 'Tournament';
        $buyin = intval($_POST['buyin'] ?? 500);
        $maxPlayers = intval($_POST['maxPlayers'] ?? 16);
        $startTime = intval($_POST['startTime'] ?? time() + 3600);

        $tournaments = loadJson($tournamentsFile);

        $id = uniqid('t_');
        $tournaments[$id] = [
            'id' => $id,
            'name' => $name,
            'buyin' => $buyin,
            'maxPlayers' => $maxPlayers,
            'players' => [],
            'status' => 'registering',
            'startTime' => $startTime,
            'prizePool' => 0,
            'createdAt' => time()
        ];

        saveJson($tournamentsFile, $tournaments);

        echo json_encode(['success' => true, 'tournament' => $tournaments[$id]]);
        break;

    case 'join-tournament':
        $user = getUser();
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $id = $_POST['id'] ?? '';

        $tournaments = loadJson($tournamentsFile);

        if (!isset($tournaments[$id])) {
            echo json_encode(['success' => false, 'error' => 'Tournament not found']);
            exit;
        }

        $tournament = &$tournaments[$id];

        if ($tournament['status'] !== 'registering') {
            echo json_encode(['success' => false, 'error' => 'Registration closed']);
            exit;
        }

        if (in_array($user, $tournament['players'])) {
            echo json_encode(['success' => false, 'error' => 'Already registered']);
            exit;
        }

        if (count($tournament['players']) >= $tournament['maxPlayers']) {
            echo json_encode(['success' => false, 'error' => 'Tournament is full']);
            exit;
        }

        $userCoins = getUserCoins($user);
        if ($tournament['buyin'] > $userCoins) {
            echo json_encode(['success' => false, 'error' => 'Not enough coins']);
            exit;
        }

        // Deduct buy-in
        updateUserCoins($user, -$tournament['buyin']);

        $tournament['players'][] = $user;
        $tournament['prizePool'] += $tournament['buyin'];

        saveJson($tournamentsFile, $tournaments);

        echo json_encode(['success' => true, 'tournament' => $tournament]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}
?>
