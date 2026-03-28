<?php
session_start();
header('Content-Type: application/json');

$usersFile = __DIR__ . '/../data/users.json';
$bossFile = __DIR__ . '/../data/boss-events.json';
$bpFile = __DIR__ . '/../data/battlepass.json';

function loadJson($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$users = loadJson($usersFile);
$bossData = loadJson($bossFile);
$bpData = loadJson($bpFile);

// Boss definitions
$BOSSES = [
    'pixel_dragon' => [
        'name' => 'Pixel Dragon',
        'icon' => '🐉',
        'hp' => 1000000,
        'duration' => 86400, // 24 hours
        'description' => 'A mighty dragon made of pure pixels threatens the arcade!',
        'rewards' => [
            'participation' => ['coins' => 100, 'xp' => 50, 'bpXp' => 25],
            'milestones' => [
                1000 => ['coins' => 200, 'item' => ['type' => 'lootbox', 'rarity' => 'rare']],
                5000 => ['coins' => 500, 'item' => ['type' => 'card', 'rarity' => 'epic']],
                10000 => ['coins' => 1000, 'bpXp' => 100],
                25000 => ['item' => ['type' => 'lootbox', 'rarity' => 'legendary']],
                50000 => ['coins' => 2000, 'title' => 'Dragon Slayer']
            ],
            'topDamage' => [
                1 => ['coins' => 5000, 'item' => ['type' => 'pet', 'id' => 'baby_dragon']],
                2 => ['coins' => 3000, 'item' => ['type' => 'lootbox', 'rarity' => 'legendary']],
                3 => ['coins' => 2000, 'item' => ['type' => 'lootbox', 'rarity' => 'legendary']],
                10 => ['coins' => 1000, 'item' => ['type' => 'lootbox', 'rarity' => 'epic']]
            ]
        ]
    ],
    'arcade_golem' => [
        'name' => 'Arcade Golem',
        'icon' => '🗿',
        'hp' => 2000000,
        'duration' => 172800, // 48 hours
        'description' => 'A massive golem of arcade cabinets has awakened!',
        'rewards' => [
            'participation' => ['coins' => 150, 'xp' => 75, 'bpXp' => 35],
            'milestones' => [
                2000 => ['coins' => 300, 'item' => ['type' => 'lootbox', 'rarity' => 'rare']],
                10000 => ['coins' => 750, 'item' => ['type' => 'card', 'rarity' => 'epic']],
                25000 => ['coins' => 1500, 'bpXp' => 150],
                50000 => ['item' => ['type' => 'lootbox', 'rarity' => 'legendary']],
                100000 => ['coins' => 3000, 'title' => 'Golem Breaker']
            ],
            'topDamage' => [
                1 => ['coins' => 7500, 'item' => ['type' => 'pet', 'id' => 'mini_golem']],
                2 => ['coins' => 5000, 'item' => ['type' => 'lootbox', 'rarity' => 'legendary']],
                3 => ['coins' => 3000, 'item' => ['type' => 'lootbox', 'rarity' => 'legendary']],
                10 => ['coins' => 1500, 'item' => ['type' => 'lootbox', 'rarity' => 'epic']]
            ]
        ]
    ],
    'glitch_hydra' => [
        'name' => 'Glitch Hydra',
        'icon' => '🐍',
        'hp' => 3000000,
        'duration' => 259200, // 72 hours
        'description' => 'A three-headed beast of corrupted code!',
        'rewards' => [
            'participation' => ['coins' => 200, 'xp' => 100, 'bpXp' => 50],
            'milestones' => [
                5000 => ['coins' => 500, 'item' => ['type' => 'lootbox', 'rarity' => 'epic']],
                20000 => ['coins' => 1000, 'item' => ['type' => 'card', 'rarity' => 'legendary']],
                50000 => ['coins' => 2000, 'bpXp' => 200],
                100000 => ['item' => ['type' => 'lootbox', 'rarity' => 'legendary'], 'item2' => ['type' => 'lootbox', 'rarity' => 'legendary']],
                200000 => ['coins' => 5000, 'title' => 'Hydra Hunter', 'badge' => 'hydra_slayer']
            ],
            'topDamage' => [
                1 => ['coins' => 10000, 'item' => ['type' => 'pet', 'id' => 'glitch_serpent']],
                2 => ['coins' => 7500, 'item' => ['type' => 'lootbox', 'rarity' => 'legendary']],
                3 => ['coins' => 5000, 'item' => ['type' => 'lootbox', 'rarity' => 'legendary']],
                10 => ['coins' => 2500, 'item' => ['type' => 'lootbox', 'rarity' => 'epic']]
            ]
        ]
    ]
];

// Initialize current event if none
if (!isset($bossData['current'])) {
    $bossData['current'] = null;
}
if (!isset($bossData['history'])) {
    $bossData['history'] = [];
}

function startNewBoss($bossId, &$bossData, $BOSSES) {
    $boss = $BOSSES[$bossId];
    $bossData['current'] = [
        'id' => $bossId,
        'name' => $boss['name'],
        'icon' => $boss['icon'],
        'description' => $boss['description'],
        'maxHp' => $boss['hp'],
        'currentHp' => $boss['hp'],
        'startTime' => date('c'),
        'endTime' => date('c', time() + $boss['duration']),
        'participants' => [],
        'totalDamage' => 0,
        'defeated' => false
    ];
    return $bossData['current'];
}

function addDamage($username, $damage, &$bossData, &$users, &$bpData, $BOSSES) {
    if (!$bossData['current'] || $bossData['current']['defeated']) {
        return ['success' => false, 'error' => 'No active boss'];
    }

    $event = &$bossData['current'];
    $bossId = $event['id'];
    $boss = $BOSSES[$bossId];

    // Check if event ended
    if (time() > strtotime($event['endTime'])) {
        return ['success' => false, 'error' => 'Event ended'];
    }

    // Add participant if new
    if (!isset($event['participants'][$username])) {
        $event['participants'][$username] = [
            'damage' => 0,
            'attacks' => 0,
            'joined' => date('c'),
            'milestonesClaimed' => []
        ];
    }

    $participant = &$event['participants'][$username];
    $oldDamage = $participant['damage'];
    $participant['damage'] += $damage;
    $participant['attacks']++;
    $event['totalDamage'] += $damage;

    // Deal damage to boss
    $event['currentHp'] = max(0, $event['currentHp'] - $damage);

    // Give participation rewards
    $user = &$users[$username];
    $rewards = $boss['rewards']['participation'];
    $user['coins'] = ($user['coins'] ?? 0) + $rewards['coins'];
    $user['xp'] = ($user['xp'] ?? 0) + $rewards['xp'];

    // Battle pass XP
    if (isset($bpData[$username])) {
        $bpData[$username]['xp'] += $rewards['bpXp'];
    }

    // Check milestones
    $newMilestones = [];
    foreach ($boss['rewards']['milestones'] as $threshold => $reward) {
        if ($participant['damage'] >= $threshold && $oldDamage < $threshold) {
            if (!in_array($threshold, $participant['milestonesClaimed'])) {
                $participant['milestonesClaimed'][] = $threshold;
                $newMilestones[] = ['threshold' => $threshold, 'reward' => $reward];

                // Give milestone rewards
                if (isset($reward['coins'])) {
                    $user['coins'] += $reward['coins'];
                }
                if (isset($reward['bpXp']) && isset($bpData[$username])) {
                    $bpData[$username]['xp'] += $reward['bpXp'];
                }
                if (isset($reward['item'])) {
                    $item = $reward['item'];
                    switch ($item['type']) {
                        case 'lootbox':
                            $user['inventory']['lootboxes'][] = ['type' => $item['rarity'], 'obtained' => date('c')];
                            break;
                        case 'card':
                            $user['inventory']['tradingCards'][] = ['id' => 'boss_card_' . rand(1,100), 'rarity' => $item['rarity'], 'obtained' => date('c')];
                            break;
                    }
                }
                if (isset($reward['title'])) {
                    $user['titles'][] = $reward['title'];
                }
                if (isset($reward['badge'])) {
                    $user['badges'][] = $reward['badge'];
                }
            }
        }
    }

    // Check if boss defeated
    $bossDefeated = false;
    if ($event['currentHp'] <= 0 && !$event['defeated']) {
        $event['defeated'] = true;
        $event['defeatedAt'] = date('c');
        $event['defeatedBy'] = $username;
        $bossDefeated = true;
    }

    return [
        'success' => true,
        'damage' => $damage,
        'totalDamage' => $participant['damage'],
        'bossHp' => $event['currentHp'],
        'bossMaxHp' => $event['maxHp'],
        'coinsEarned' => $rewards['coins'],
        'xpEarned' => $rewards['xp'],
        'bpXpEarned' => $rewards['bpXp'],
        'newMilestones' => $newMilestones,
        'bossDefeated' => $bossDefeated
    ];
}

switch ($action) {
    case 'current':
        if (!$bossData['current']) {
            echo json_encode(['success' => true, 'event' => null]);
            exit;
        }

        $event = $bossData['current'];
        $bossId = $event['id'];

        // Check if ended
        if (time() > strtotime($event['endTime']) && !$event['defeated']) {
            // Event ended without defeating boss
            $bossData['history'][] = $event;
            $bossData['current'] = null;
            saveJson($bossFile, $bossData);
            echo json_encode(['success' => true, 'event' => null]);
            exit;
        }

        // Get top participants
        $participants = $event['participants'];
        uasort($participants, fn($a, $b) => $b['damage'] - $a['damage']);
        $topParticipants = array_slice($participants, 0, 10, true);

        // Format for response
        $topList = [];
        foreach ($topParticipants as $user => $data) {
            $topList[] = [
                'username' => $user,
                'damage' => $data['damage'],
                'attacks' => $data['attacks']
            ];
        }

        $response = [
            'success' => true,
            'event' => [
                'id' => $event['id'],
                'name' => $event['name'],
                'icon' => $event['icon'],
                'description' => $event['description'],
                'currentHp' => $event['currentHp'],
                'maxHp' => $event['maxHp'],
                'hpPercent' => round(($event['currentHp'] / $event['maxHp']) * 100, 2),
                'startTime' => $event['startTime'],
                'endTime' => $event['endTime'],
                'timeLeft' => max(0, strtotime($event['endTime']) - time()),
                'participantCount' => count($event['participants']),
                'totalDamage' => $event['totalDamage'],
                'defeated' => $event['defeated'],
                'topParticipants' => $topList,
                'rewards' => $BOSSES[$event['id']]['rewards']
            ]
        ];

        // Add user's stats if logged in
        if (isset($_SESSION['user'])) {
            $username = $_SESSION['user'];
            if (isset($event['participants'][$username])) {
                $response['myStats'] = $event['participants'][$username];

                // Calculate rank
                $rank = 1;
                foreach ($participants as $user => $data) {
                    if ($user === $username) break;
                    $rank++;
                }
                $response['myStats']['rank'] = $rank;
            }
        }

        echo json_encode($response);
        break;

    case 'attack':
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $username = $_SESSION['user'];
        $input = json_decode(file_get_contents('php://input'), true);
        $damage = intval($input['damage'] ?? 0);

        if ($damage < 1 || $damage > 10000) {
            echo json_encode(['success' => false, 'error' => 'Invalid damage']);
            exit;
        }

        $result = addDamage($username, $damage, $bossData, $users, $bpData, $BOSSES);

        saveJson($bossFile, $bossData);
        saveJson($usersFile, $users);
        saveJson($bpFile, $bpData);

        echo json_encode($result);
        break;

    case 'contribute':
        // Called automatically when playing games
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $username = $_SESSION['user'];
        $input = json_decode(file_get_contents('php://input'), true);
        $score = intval($input['score'] ?? 0);
        $game = $input['game'] ?? '';

        // Convert game score to boss damage (10% of score)
        $damage = max(1, floor($score * 0.1));

        $result = addDamage($username, $damage, $bossData, $users, $bpData, $BOSSES);

        saveJson($bossFile, $bossData);
        saveJson($usersFile, $users);
        saveJson($bpFile, $bpData);

        echo json_encode($result);
        break;

    case 'start':
        // Admin only - start a new boss event
        $input = json_decode(file_get_contents('php://input'), true);
        $bossId = $input['bossId'] ?? 'pixel_dragon';

        if (!isset($BOSSES[$bossId])) {
            echo json_encode(['success' => false, 'error' => 'Invalid boss']);
            exit;
        }

        // Archive current if exists
        if ($bossData['current']) {
            $bossData['history'][] = $bossData['current'];
        }

        $event = startNewBoss($bossId, $bossData, $BOSSES);
        saveJson($bossFile, $bossData);

        echo json_encode([
            'success' => true,
            'event' => $event
        ]);
        break;

    case 'history':
        $limit = intval($_GET['limit'] ?? 10);
        $history = array_slice(array_reverse($bossData['history']), 0, $limit);

        echo json_encode([
            'success' => true,
            'history' => $history
        ]);
        break;

    case 'bosses':
        // List available bosses
        $bossList = [];
        foreach ($BOSSES as $id => $boss) {
            $bossList[] = [
                'id' => $id,
                'name' => $boss['name'],
                'icon' => $boss['icon'],
                'hp' => $boss['hp'],
                'duration' => $boss['duration']
            ];
        }

        echo json_encode([
            'success' => true,
            'bosses' => $bossList
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
