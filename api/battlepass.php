<?php
session_start();
header('Content-Type: application/json');

$usersFile = __DIR__ . '/../data/users.json';
$bpFile = __DIR__ . '/../data/battlepass.json';

function loadJson($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$username = $_SESSION['user'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Season config
$SEASON = [
    'id' => 1,
    'name' => 'Arcade Legends',
    'start' => '2026-03-01',
    'end' => '2026-04-30',
    'xpPerLevel' => 100,
    'maxLevel' => 30,
    'premiumCost' => 500
];

// Rewards definition
$REWARDS = [
    1 => ['free' => ['icon' => '🪙', 'name' => '50 Coins', 'type' => 'coins', 'value' => 50], 'premium' => ['icon' => '💎', 'name' => '100 Coins', 'type' => 'coins', 'value' => 100]],
    2 => ['free' => ['icon' => '📦', 'name' => 'Common Lootbox', 'type' => 'lootbox', 'value' => 'common'], 'premium' => ['icon' => '📦', 'name' => 'Rare Lootbox', 'type' => 'lootbox', 'value' => 'rare']],
    3 => ['free' => ['icon' => '🪙', 'name' => '75 Coins', 'type' => 'coins', 'value' => 75], 'premium' => ['icon' => '🎨', 'name' => 'Neon Theme', 'type' => 'theme', 'value' => 'neon']],
    4 => ['free' => ['icon' => '🃏', 'name' => 'Trading Card', 'type' => 'card', 'value' => 'random'], 'premium' => ['icon' => '🃏', 'name' => 'Rare Card', 'type' => 'card', 'value' => 'rare']],
    5 => ['free' => ['icon' => '🪙', 'name' => '100 Coins', 'type' => 'coins', 'value' => 100], 'premium' => ['icon' => '📦', 'name' => 'Epic Lootbox', 'type' => 'lootbox', 'value' => 'epic']],
    6 => ['free' => ['icon' => '⚡', 'name' => 'XP Booster', 'type' => 'booster', 'value' => 'xp_boost_small'], 'premium' => ['icon' => '💎', 'name' => '200 Coins', 'type' => 'coins', 'value' => 200]],
    7 => ['free' => ['icon' => '🪙', 'name' => '100 Coins', 'type' => 'coins', 'value' => 100], 'premium' => ['icon' => '🐾', 'name' => 'Rare Pet Egg', 'type' => 'pet', 'value' => 'rare_egg']],
    8 => ['free' => ['icon' => '📦', 'name' => 'Rare Lootbox', 'type' => 'lootbox', 'value' => 'rare'], 'premium' => ['icon' => '👑', 'name' => 'Crown Badge', 'type' => 'badge', 'value' => 'crown']],
    9 => ['free' => ['icon' => '🪙', 'name' => '150 Coins', 'type' => 'coins', 'value' => 150], 'premium' => ['icon' => '🎆', 'name' => 'Galaxy Background', 'type' => 'background', 'value' => 'galaxy']],
    10 => ['free' => ['icon' => '🏅', 'name' => 'Bronze Frame', 'type' => 'frame', 'value' => 'bronze'], 'premium' => ['icon' => '🥇', 'name' => 'Gold Frame', 'type' => 'frame', 'value' => 'gold']],
    11 => ['free' => ['icon' => '🪙', 'name' => '150 Coins', 'type' => 'coins', 'value' => 150], 'premium' => ['icon' => '💎', 'name' => '300 Coins', 'type' => 'coins', 'value' => 300]],
    12 => ['free' => ['icon' => '🃏', 'name' => '2 Trading Cards', 'type' => 'card', 'value' => 'random_2'], 'premium' => ['icon' => '📦', 'name' => 'Legendary Lootbox', 'type' => 'lootbox', 'value' => 'legendary']],
    13 => ['free' => ['icon' => '🪙', 'name' => '200 Coins', 'type' => 'coins', 'value' => 200], 'premium' => ['icon' => '🎨', 'name' => 'Synthwave Theme', 'type' => 'theme', 'value' => 'synthwave']],
    14 => ['free' => ['icon' => '⚡', 'name' => 'Coin Booster', 'type' => 'booster', 'value' => 'coin_boost_small'], 'premium' => ['icon' => '💫', 'name' => 'Comet Trail', 'type' => 'trail', 'value' => 'comet']],
    15 => ['free' => ['icon' => '🎖️', 'name' => 'Silver Badge', 'type' => 'badge', 'value' => 'silver'], 'premium' => ['icon' => '💎', 'name' => '500 Coins', 'type' => 'coins', 'value' => 500]],
    16 => ['free' => ['icon' => '🪙', 'name' => '200 Coins', 'type' => 'coins', 'value' => 200], 'premium' => ['icon' => '🐾', 'name' => 'Epic Pet Egg', 'type' => 'pet', 'value' => 'epic_egg']],
    17 => ['free' => ['icon' => '📦', 'name' => 'Epic Lootbox', 'type' => 'lootbox', 'value' => 'epic'], 'premium' => ['icon' => '❄️', 'name' => 'Frost Trail', 'type' => 'trail', 'value' => 'frost']],
    18 => ['free' => ['icon' => '🪙', 'name' => '250 Coins', 'type' => 'coins', 'value' => 250], 'premium' => ['icon' => '🎭', 'name' => 'Arcade King Avatar', 'type' => 'avatar', 'value' => 'arcade_king']],
    19 => ['free' => ['icon' => '🎨', 'name' => 'Cyber Theme', 'type' => 'theme', 'value' => 'cyber'], 'premium' => ['icon' => '💎', 'name' => '750 Coins', 'type' => 'coins', 'value' => 750]],
    20 => ['free' => ['icon' => '🥈', 'name' => 'Silver Frame', 'type' => 'frame', 'value' => 'silver'], 'premium' => ['icon' => '💠', 'name' => 'Diamond Frame', 'type' => 'frame', 'value' => 'diamond']],
    21 => ['free' => ['icon' => '🪙', 'name' => '300 Coins', 'type' => 'coins', 'value' => 300], 'premium' => ['icon' => '📦', 'name' => '2 Legendary Lootboxes', 'type' => 'lootbox', 'value' => 'legendary_2']],
    22 => ['free' => ['icon' => '🃏', 'name' => '3 Trading Cards', 'type' => 'card', 'value' => 'random_3'], 'premium' => ['icon' => '💎', 'name' => '1000 Coins', 'type' => 'coins', 'value' => 1000]],
    23 => ['free' => ['icon' => '🪙', 'name' => '350 Coins', 'type' => 'coins', 'value' => 350], 'premium' => ['icon' => '🌀', 'name' => 'Vortex Trail', 'type' => 'trail', 'value' => 'vortex']],
    24 => ['free' => ['icon' => '⭐', 'name' => 'Gold Badge', 'type' => 'badge', 'value' => 'gold'], 'premium' => ['icon' => '💎', 'name' => 'Diamond Badge', 'type' => 'badge', 'value' => 'diamond']],
    25 => ['free' => ['icon' => '🪙', 'name' => '500 Coins', 'type' => 'coins', 'value' => 500], 'premium' => ['icon' => '🌈', 'name' => 'Rainbow Aura', 'type' => 'aura', 'value' => 'rainbow']],
    26 => ['free' => ['icon' => '📦', 'name' => 'Legendary Lootbox', 'type' => 'lootbox', 'value' => 'legendary'], 'premium' => ['icon' => '💎', 'name' => '1500 Coins', 'type' => 'coins', 'value' => 1500]],
    27 => ['free' => ['icon' => '🪙', 'name' => '500 Coins', 'type' => 'coins', 'value' => 500], 'premium' => ['icon' => '🐾', 'name' => 'Legendary Pet Egg', 'type' => 'pet', 'value' => 'legendary_egg']],
    28 => ['free' => ['icon' => '🃏', 'name' => 'Legendary Card', 'type' => 'card', 'value' => 'legendary'], 'premium' => ['icon' => '🔮', 'name' => 'Mystic Effect', 'type' => 'effect', 'value' => 'mystic']],
    29 => ['free' => ['icon' => '🪙', 'name' => '750 Coins', 'type' => 'coins', 'value' => 750], 'premium' => ['icon' => '💎', 'name' => '2000 Coins', 'type' => 'coins', 'value' => 2000]],
    30 => ['free' => ['icon' => '🏆', 'name' => 'Season 1 Trophy', 'type' => 'trophy', 'value' => 'season1'], 'premium' => ['icon' => '👑', 'name' => 'Arcade Master Title', 'type' => 'title', 'value' => 'arcade_master']]
];

// Challenge definitions
$CHALLENGES = [
    'daily' => [
        ['id' => 'd1', 'title' => 'Play 3 Games', 'desc' => 'Play any 3 games', 'xp' => 25, 'target' => 3, 'track' => 'games_played'],
        ['id' => 'd2', 'title' => 'Score 1000 Points', 'desc' => 'Earn 1000 points total', 'xp' => 30, 'target' => 1000, 'track' => 'score'],
        ['id' => 'd3', 'title' => 'Win 2 Games', 'desc' => 'Win 2 games', 'xp' => 40, 'target' => 2, 'track' => 'wins']
    ],
    'weekly' => [
        ['id' => 'w1', 'title' => 'Play 20 Games', 'desc' => 'Play 20 games this week', 'xp' => 100, 'target' => 20, 'track' => 'games_played'],
        ['id' => 'w2', 'title' => 'High Scorer', 'desc' => 'Score 10,000 points total', 'xp' => 150, 'target' => 10000, 'track' => 'score'],
        ['id' => 'w3', 'title' => 'Win Streak', 'desc' => 'Win 10 games', 'xp' => 200, 'target' => 10, 'track' => 'wins']
    ],
    'seasonal' => [
        ['id' => 's1', 'title' => 'Dedicated Player', 'desc' => 'Play 100 games this season', 'xp' => 500, 'target' => 100, 'track' => 'games_played'],
        ['id' => 's2', 'title' => 'Point Master', 'desc' => 'Score 100,000 points total', 'xp' => 750, 'target' => 100000, 'track' => 'score'],
        ['id' => 's3', 'title' => 'Champion', 'desc' => 'Win 50 games', 'xp' => 1000, 'target' => 50, 'track' => 'wins']
    ]
];

$bpData = loadJson($bpFile);
$users = loadJson($usersFile);

// Initialize user battlepass if needed
if (!isset($bpData[$username])) {
    $bpData[$username] = [
        'season' => $SEASON['id'],
        'xp' => 0,
        'premium' => false,
        'claimed' => ['free' => [], 'premium' => []],
        'challenges' => [
            'daily' => ['date' => date('Y-m-d'), 'progress' => []],
            'weekly' => ['week' => date('W'), 'progress' => []],
            'seasonal' => ['season' => $SEASON['id'], 'progress' => []]
        ]
    ];
    saveJson($bpFile, $bpData);
}

$userBp = &$bpData[$username];

// Reset challenges if needed
$today = date('Y-m-d');
$week = date('W');
if ($userBp['challenges']['daily']['date'] !== $today) {
    $userBp['challenges']['daily'] = ['date' => $today, 'progress' => []];
}
if ($userBp['challenges']['weekly']['week'] !== $week) {
    $userBp['challenges']['weekly'] = ['week' => $week, 'progress' => []];
}

function calculateLevel($xp, $xpPerLevel, $maxLevel) {
    $level = floor($xp / $xpPerLevel) + 1;
    return min($level, $maxLevel);
}

switch ($action) {
    case 'status':
        $level = calculateLevel($userBp['xp'], $SEASON['xpPerLevel'], $SEASON['maxLevel']);
        $currentLevelXp = $userBp['xp'] % $SEASON['xpPerLevel'];

        // Build challenges with progress
        $challengesWithProgress = [];
        foreach (['daily', 'weekly', 'seasonal'] as $type) {
            $challengesWithProgress[$type] = [];
            foreach ($CHALLENGES[$type] as $c) {
                $progress = $userBp['challenges'][$type]['progress'][$c['id']] ?? 0;
                $challengesWithProgress[$type][] = array_merge($c, [
                    'progress' => $progress,
                    'completed' => $progress >= $c['target'],
                    'claimed' => isset($userBp['challenges'][$type]['claimed'][$c['id']])
                ]);
            }
        }

        echo json_encode([
            'success' => true,
            'season' => $SEASON,
            'xp' => $userBp['xp'],
            'level' => $level,
            'currentLevelXp' => $currentLevelXp,
            'premium' => $userBp['premium'],
            'claimed' => $userBp['claimed'],
            'challenges' => $challengesWithProgress,
            'rewards' => $REWARDS,
            'daysLeft' => max(0, (strtotime($SEASON['end']) - time()) / 86400)
        ]);
        break;

    case 'addXp':
        $input = json_decode(file_get_contents('php://input'), true);
        $xpAmount = intval($input['xp'] ?? 0);
        $source = $input['source'] ?? 'game';

        if ($xpAmount <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid XP']);
            exit;
        }

        // Premium gets 2x XP
        if ($userBp['premium']) {
            $xpAmount *= 2;
        }

        $oldLevel = calculateLevel($userBp['xp'], $SEASON['xpPerLevel'], $SEASON['maxLevel']);
        $userBp['xp'] += $xpAmount;
        $newLevel = calculateLevel($userBp['xp'], $SEASON['xpPerLevel'], $SEASON['maxLevel']);

        saveJson($bpFile, $bpData);

        echo json_encode([
            'success' => true,
            'xpAdded' => $xpAmount,
            'totalXp' => $userBp['xp'],
            'level' => $newLevel,
            'leveledUp' => $newLevel > $oldLevel
        ]);
        break;

    case 'updateChallenge':
        $input = json_decode(file_get_contents('php://input'), true);
        $track = $input['track'] ?? '';
        $amount = intval($input['amount'] ?? 1);

        $xpEarned = 0;
        $completed = [];

        foreach (['daily', 'weekly', 'seasonal'] as $type) {
            foreach ($CHALLENGES[$type] as $c) {
                if ($c['track'] === $track) {
                    $current = $userBp['challenges'][$type]['progress'][$c['id']] ?? 0;
                    $new = $current + $amount;
                    $userBp['challenges'][$type]['progress'][$c['id']] = $new;

                    // Check if just completed
                    if ($current < $c['target'] && $new >= $c['target']) {
                        if (!isset($userBp['challenges'][$type]['claimed'][$c['id']])) {
                            $userBp['challenges'][$type]['claimed'][$c['id']] = true;
                            $xpEarned += $c['xp'];
                            $completed[] = $c;
                        }
                    }
                }
            }
        }

        // Add earned XP
        if ($xpEarned > 0) {
            if ($userBp['premium']) $xpEarned *= 2;
            $userBp['xp'] += $xpEarned;
        }

        saveJson($bpFile, $bpData);

        echo json_encode([
            'success' => true,
            'xpEarned' => $xpEarned,
            'completed' => $completed
        ]);
        break;

    case 'claimReward':
        $input = json_decode(file_get_contents('php://input'), true);
        $level = intval($input['level'] ?? 0);
        $type = $input['type'] ?? 'free'; // 'free' or 'premium'

        if ($level < 1 || $level > $SEASON['maxLevel']) {
            echo json_encode(['success' => false, 'error' => 'Invalid level']);
            exit;
        }

        $userLevel = calculateLevel($userBp['xp'], $SEASON['xpPerLevel'], $SEASON['maxLevel']);

        if ($userLevel < $level) {
            echo json_encode(['success' => false, 'error' => 'Level not reached']);
            exit;
        }

        if ($type === 'premium' && !$userBp['premium']) {
            echo json_encode(['success' => false, 'error' => 'Premium required']);
            exit;
        }

        if (in_array($level, $userBp['claimed'][$type])) {
            echo json_encode(['success' => false, 'error' => 'Already claimed']);
            exit;
        }

        $reward = $REWARDS[$level][$type];
        $userBp['claimed'][$type][] = $level;

        // Give the reward
        $user = &$users[$username];
        switch ($reward['type']) {
            case 'coins':
                $user['coins'] = ($user['coins'] ?? 0) + $reward['value'];
                break;
            case 'lootbox':
                $rarity = str_replace('_2', '', $reward['value']);
                $count = strpos($reward['value'], '_2') !== false ? 2 : 1;
                for ($i = 0; $i < $count; $i++) {
                    $user['inventory']['lootboxes'][] = ['type' => $rarity, 'obtained' => date('c')];
                }
                break;
            case 'booster':
                $user['inventory']['boosters'][] = ['type' => $reward['value'], 'obtained' => date('c')];
                break;
            case 'card':
                $cardRarities = ['common', 'uncommon', 'rare', 'epic', 'legendary'];
                $cardRarity = $reward['value'] === 'legendary' ? 'legendary' :
                             ($reward['value'] === 'rare' ? 'rare' : $cardRarities[array_rand(['common', 'uncommon', 'rare'])]);
                $count = preg_match('/random_(\d)/', $reward['value'], $m) ? intval($m[1]) : 1;
                for ($i = 0; $i < $count; $i++) {
                    $user['inventory']['tradingCards'][] = [
                        'id' => 'card_' . rand(1, 50),
                        'rarity' => $cardRarity,
                        'obtained' => date('c')
                    ];
                }
                break;
            case 'pet':
                $user['inventory']['petEggs'][] = ['type' => $reward['value'], 'obtained' => date('c')];
                break;
            default:
                // Cosmetics go to unlocks
                $user['unlocks'][$reward['type']][] = $reward['value'];
                break;
        }

        saveJson($usersFile, $users);
        saveJson($bpFile, $bpData);

        echo json_encode([
            'success' => true,
            'reward' => $reward,
            'message' => 'Claimed ' . $reward['name']
        ]);
        break;

    case 'buyPremium':
        if ($userBp['premium']) {
            echo json_encode(['success' => false, 'error' => 'Already have premium']);
            exit;
        }

        $user = &$users[$username];
        if (($user['coins'] ?? 0) < $SEASON['premiumCost']) {
            echo json_encode(['success' => false, 'error' => 'Not enough coins']);
            exit;
        }

        $user['coins'] -= $SEASON['premiumCost'];
        $userBp['premium'] = true;

        saveJson($usersFile, $users);
        saveJson($bpFile, $bpData);

        echo json_encode([
            'success' => true,
            'message' => 'Premium Battle Pass unlocked!'
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
