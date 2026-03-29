<?php
session_start();
header('Content-Type: application/json');

$usersFile = __DIR__ . '/../data/users.json';

function loadJson($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$username = $_SESSION['user'];
$action = $_GET['action'] ?? '';

$users = loadJson($usersFile);
$user = $users['users'][$username] ?? null;

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

// Ensure inventory structure exists
if (!isset($user['inventory'])) $user['inventory'] = [];
if (!isset($user['inventory']['lootboxes'])) $user['inventory']['lootboxes'] = [];
if (!isset($user['inventory']['tradingCards'])) $user['inventory']['tradingCards'] = [];
if (!isset($user['inventory']['boosters'])) $user['inventory']['boosters'] = [];
if (!isset($user['inventory']['petEggs'])) $user['inventory']['petEggs'] = [];
if (!isset($user['inventory']['charms'])) $user['inventory']['charms'] = [];
if (!isset($user['inventory']['mysteryBoxes'])) $user['inventory']['mysteryBoxes'] = [];

switch ($action) {
    case 'all':
        echo json_encode([
            'success' => true,
            'inventory' => $user['inventory'],
            'coins' => $user['coins'] ?? 0
        ]);
        break;

    case 'lootboxes':
        echo json_encode([
            'success' => true,
            'lootboxes' => $user['inventory']['lootboxes']
        ]);
        break;

    case 'cards':
        echo json_encode([
            'success' => true,
            'tradingCards' => $user['inventory']['tradingCards']
        ]);
        break;

    case 'boosters':
        echo json_encode([
            'success' => true,
            'boosters' => $user['inventory']['boosters']
        ]);
        break;

    case 'summary':
        $summary = [
            'lootboxes' => count($user['inventory']['lootboxes']),
            'tradingCards' => count($user['inventory']['tradingCards']),
            'boosters' => count($user['inventory']['boosters']),
            'petEggs' => count($user['inventory']['petEggs']),
            'charms' => count($user['inventory']['charms']),
            'mysteryBoxes' => count($user['inventory']['mysteryBoxes']),
            'coins' => $user['coins'] ?? 0
        ];

        // Count by rarity
        $lootboxesByRarity = [];
        foreach ($user['inventory']['lootboxes'] as $lb) {
            $type = $lb['type'] ?? 'common';
            $lootboxesByRarity[$type] = ($lootboxesByRarity[$type] ?? 0) + 1;
        }

        $cardsByRarity = [];
        foreach ($user['inventory']['tradingCards'] as $card) {
            $rarity = $card['rarity'] ?? 'common';
            $cardsByRarity[$rarity] = ($cardsByRarity[$rarity] ?? 0) + 1;
        }

        $summary['lootboxesByRarity'] = $lootboxesByRarity;
        $summary['cardsByRarity'] = $cardsByRarity;

        echo json_encode([
            'success' => true,
            'summary' => $summary
        ]);
        break;

    default:
        // Return everything by default
        echo json_encode([
            'success' => true,
            'inventory' => $user['inventory'],
            'coins' => $user['coins'] ?? 0
        ]);
}
?>
