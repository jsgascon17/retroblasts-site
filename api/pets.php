<?php
header('Content-Type: application/json');
session_start();

$dataFile = __DIR__ . '/../data/pets.json';
$usersFile = __DIR__ . '/../data/users.json';

$PETS = [
    'puppy' => ['price' => 500],
    'kitten' => ['price' => 500],
    'bunny' => ['price' => 750],
    'hamster' => ['price' => 1000],
    'fox' => ['price' => 1500],
    'owl' => ['price' => 1500],
    'penguin' => ['price' => 3000],
    'panda' => ['price' => 3500],
    'tiger' => ['price' => 4000],
    'unicorn' => ['price' => 7500],
    'dragon' => ['price' => 10000],
    'phoenix' => ['price' => 25000],
    'alien' => ['price' => 30000],
];

function loadPets() {
    global $dataFile;
    if (!file_exists($dataFile)) return [];
    $data = json_decode(file_get_contents($dataFile), true);
    return $data ?: [];
}

function savePets($data) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
}

function loadUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function saveUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

function getUserPets($username) {
    $allPets = loadPets();
    return $allPets[$username] ?? [
        'owned' => [],
        'active' => null,
        'petLevels' => []
    ];
}

function saveUserPets($username, $pets) {
    $allPets = loadPets();
    $allPets[$username] = $pets;
    savePets($allPets);
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'get') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $username = $_SESSION['user'];
        $pets = getUserPets($username);

        echo json_encode(['success' => true, 'pets' => $pets]);
        exit;
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $username = $_SESSION['user'];

    // Buy pet
    if ($action === 'buy') {
        global $PETS;

        $petId = $input['petId'] ?? '';

        if (!isset($PETS[$petId])) {
            echo json_encode(['success' => false, 'error' => 'Invalid pet']);
            exit;
        }

        $pets = getUserPets($username);
        $users = loadUsers();

        if (in_array($petId, $pets['owned'])) {
            echo json_encode(['success' => false, 'error' => 'Already owned']);
            exit;
        }

        $price = $PETS[$petId]['price'];
        $userCoins = $users['users'][$username]['coins'] ?? 0;

        if ($userCoins < $price) {
            echo json_encode(['success' => false, 'error' => 'Not enough coins']);
            exit;
        }

        // Deduct coins
        $users['users'][$username]['coins'] = $userCoins - $price;
        saveUsers($users);

        // Add pet
        $pets['owned'][] = $petId;
        $pets['petLevels'][$petId] = ['level' => 1, 'xp' => 0];

        // Auto-equip if no active pet
        if ($pets['active'] === null) {
            $pets['active'] = $petId;
        }

        saveUserPets($username, $pets);

        echo json_encode([
            'success' => true,
            'pets' => $pets,
            'newBalance' => $users['users'][$username]['coins']
        ]);
        exit;
    }

    // Equip pet
    if ($action === 'equip') {
        $petId = $input['petId'] ?? '';
        $pets = getUserPets($username);

        if (!in_array($petId, $pets['owned'])) {
            echo json_encode(['success' => false, 'error' => 'Pet not owned']);
            exit;
        }

        $pets['active'] = $petId;
        saveUserPets($username, $pets);

        echo json_encode(['success' => true, 'pets' => $pets]);
        exit;
    }

    // Add XP to pet (called by games)
    if ($action === 'addXP') {
        $xp = intval($input['xp'] ?? 0);
        if ($xp <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid XP']);
            exit;
        }

        $pets = getUserPets($username);

        if (!$pets['active']) {
            echo json_encode(['success' => false, 'error' => 'No active pet']);
            exit;
        }

        $petId = $pets['active'];
        if (!isset($pets['petLevels'][$petId])) {
            $pets['petLevels'][$petId] = ['level' => 1, 'xp' => 0];
        }

        $petLevel = &$pets['petLevels'][$petId];
        $petLevel['xp'] += $xp;

        // Check for level up
        $leveledUp = false;
        while ($petLevel['xp'] >= $petLevel['level'] * 100) {
            $petLevel['xp'] -= $petLevel['level'] * 100;
            $petLevel['level']++;
            $leveledUp = true;
        }

        saveUserPets($username, $pets);

        echo json_encode([
            'success' => true,
            'pets' => $pets,
            'leveledUp' => $leveledUp,
            'newLevel' => $petLevel['level']
        ]);
        exit;
    }

    // Get active pet bonus (for games to apply)
    if ($action === 'getBonus') {
        $pets = getUserPets($username);

        if (!$pets['active']) {
            echo json_encode(['success' => true, 'coinBonus' => 0, 'xpBonus' => 0]);
            exit;
        }

        $petId = $pets['active'];
        $level = $pets['petLevels'][$petId]['level'] ?? 1;

        // Pet bonuses by ID (must match frontend)
        $petBonuses = [
            'puppy' => ['type' => 'coin', 'amount' => 5],
            'kitten' => ['type' => 'xp', 'amount' => 5],
            'bunny' => ['type' => 'coin', 'amount' => 7],
            'hamster' => ['type' => 'xp', 'amount' => 8],
            'fox' => ['type' => 'coin', 'amount' => 10],
            'owl' => ['type' => 'xp', 'amount' => 10],
            'penguin' => ['type' => 'coin', 'amount' => 15],
            'panda' => ['type' => 'xp', 'amount' => 15],
            'tiger' => ['type' => 'coin', 'amount' => 18],
            'unicorn' => ['type' => 'both', 'amount' => 12],
            'dragon' => ['type' => 'coin', 'amount' => 25],
            'phoenix' => ['type' => 'both', 'amount' => 20],
            'alien' => ['type' => 'xp', 'amount' => 35],
        ];

        $petBonus = $petBonuses[$petId] ?? ['type' => 'coin', 'amount' => 0];
        $levelMultiplier = 1 + (($level - 1) * 0.02);
        $amount = round($petBonus['amount'] * $levelMultiplier);

        $coinBonus = 0;
        $xpBonus = 0;

        if ($petBonus['type'] === 'coin' || $petBonus['type'] === 'both') {
            $coinBonus = $amount;
        }
        if ($petBonus['type'] === 'xp' || $petBonus['type'] === 'both') {
            $xpBonus = $amount;
        }

        echo json_encode([
            'success' => true,
            'coinBonus' => $coinBonus,
            'xpBonus' => $xpBonus,
            'petId' => $petId,
            'petLevel' => $level
        ]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
