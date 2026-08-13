<?php
// Set session cookie params before session_start() for Safari compatibility
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// CORS headers for API access
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && strpos($origin, 'retroblasts.com') !== false) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://retroblasts.com');
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

require_once __DIR__ . '/_store.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$usersFile = __DIR__ . '/../data/users.json';
$bossFile = __DIR__ . '/../data/boss-events.json';
$bpFile = __DIR__ . '/../data/battlepass.json';

function loadJson($file) {
    if (!file_exists($file)) return [];
    return store_hold_read($file, []);
}

function saveJson($file, $data) {
    store_hold_write($file, $data);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$users = loadJson($usersFile);
$bossData = loadJson($bossFile);
$bpData = loadJson($bpFile);

// Admins who can spawn bosses
$ADMINS = ['BillyBuffalo15'];

// Boss definitions by difficulty
$BOSSES = [
    // ===== EASY BOSSES =====
    'slime_king' => [
        'name' => 'Slime King',
        'icon' => '🟢',
        'hp' => 50000,
        'difficulty' => 'easy',
        'duration' => 3600, // 1 hour
        'description' => 'A wobbly slime king bounces into the arcade!',
        'rewards' => [
            'participation' => ['coins' => 25, 'xp' => 15, 'bpXp' => 10],
            'milestones' => [
                500 => ['coins' => 50],
                2000 => ['coins' => 100, 'item' => ['type' => 'lootbox', 'rarity' => 'common']],
                5000 => ['coins' => 200, 'item' => ['type' => 'lootbox', 'rarity' => 'rare']]
            ],
            'topDamage' => [
                1 => ['coins' => 500, 'item' => ['type' => 'lootbox', 'rarity' => 'rare']],
                2 => ['coins' => 300],
                3 => ['coins' => 200]
            ]
        ]
    ],
    'pixel_bat' => [
        'name' => 'Pixel Bat',
        'icon' => '🦇',
        'hp' => 75000,
        'difficulty' => 'easy',
        'duration' => 7200, // 2 hours
        'description' => 'A pixelated bat flaps through the arcade!',
        'rewards' => [
            'participation' => ['coins' => 30, 'xp' => 20, 'bpXp' => 12],
            'milestones' => [
                750 => ['coins' => 75],
                3000 => ['coins' => 150, 'item' => ['type' => 'lootbox', 'rarity' => 'common']],
                7500 => ['coins' => 300, 'item' => ['type' => 'lootbox', 'rarity' => 'rare']]
            ],
            'topDamage' => [
                1 => ['coins' => 750, 'item' => ['type' => 'lootbox', 'rarity' => 'rare']],
                2 => ['coins' => 400],
                3 => ['coins' => 250]
            ]
        ]
    ],

    // ===== MEDIUM BOSSES =====
    'pixel_dragon' => [
        'name' => 'Pixel Dragon',
        'icon' => '🐉',
        'hp' => 500000,
        'difficulty' => 'medium',
        'duration' => 43200, // 12 hours
        'description' => 'A mighty dragon made of pure pixels threatens the arcade!',
        'rewards' => [
            'participation' => ['coins' => 75, 'xp' => 40, 'bpXp' => 20],
            'milestones' => [
                1000 => ['coins' => 150, 'item' => ['type' => 'lootbox', 'rarity' => 'rare']],
                5000 => ['coins' => 400, 'item' => ['type' => 'card', 'rarity' => 'epic']],
                15000 => ['coins' => 750, 'bpXp' => 75],
                30000 => ['coins' => 1500, 'title' => 'Dragon Slayer']
            ],
            'topDamage' => [
                1 => ['coins' => 3000, 'item' => ['type' => 'pet', 'id' => 'baby_dragon']],
                2 => ['coins' => 2000, 'item' => ['type' => 'lootbox', 'rarity' => 'epic']],
                3 => ['coins' => 1000, 'item' => ['type' => 'lootbox', 'rarity' => 'rare']]
            ]
        ]
    ],
    'arcade_golem' => [
        'name' => 'Arcade Golem',
        'icon' => '🗿',
        'hp' => 750000,
        'difficulty' => 'medium',
        'duration' => 64800, // 18 hours
        'description' => 'A massive golem of arcade cabinets has awakened!',
        'rewards' => [
            'participation' => ['coins' => 100, 'xp' => 50, 'bpXp' => 25],
            'milestones' => [
                2000 => ['coins' => 200, 'item' => ['type' => 'lootbox', 'rarity' => 'rare']],
                7500 => ['coins' => 500, 'item' => ['type' => 'card', 'rarity' => 'epic']],
                20000 => ['coins' => 1000, 'bpXp' => 100],
                40000 => ['coins' => 2000, 'title' => 'Golem Breaker']
            ],
            'topDamage' => [
                1 => ['coins' => 4000, 'item' => ['type' => 'pet', 'id' => 'mini_golem']],
                2 => ['coins' => 2500, 'item' => ['type' => 'lootbox', 'rarity' => 'epic']],
                3 => ['coins' => 1500, 'item' => ['type' => 'lootbox', 'rarity' => 'rare']]
            ]
        ]
    ],

    // ===== HARD BOSSES =====
    'glitch_hydra' => [
        'name' => 'Glitch Hydra',
        'icon' => '🐍',
        'hp' => 2000000,
        'difficulty' => 'hard',
        'duration' => 86400, // 24 hours
        'description' => 'A three-headed beast of corrupted code!',
        'rewards' => [
            'participation' => ['coins' => 150, 'xp' => 75, 'bpXp' => 40],
            'milestones' => [
                5000 => ['coins' => 400, 'item' => ['type' => 'lootbox', 'rarity' => 'epic']],
                20000 => ['coins' => 800, 'item' => ['type' => 'card', 'rarity' => 'legendary']],
                50000 => ['coins' => 1500, 'bpXp' => 150],
                100000 => ['coins' => 3000, 'title' => 'Hydra Hunter']
            ],
            'topDamage' => [
                1 => ['coins' => 7500, 'item' => ['type' => 'pet', 'id' => 'glitch_serpent']],
                2 => ['coins' => 5000, 'item' => ['type' => 'lootbox', 'rarity' => 'legendary']],
                3 => ['coins' => 3000, 'item' => ['type' => 'lootbox', 'rarity' => 'epic']]
            ]
        ]
    ],
    'shadow_phoenix' => [
        'name' => 'Shadow Phoenix',
        'icon' => '🔥',
        'hp' => 3000000,
        'difficulty' => 'hard',
        'duration' => 129600, // 36 hours
        'description' => 'A dark phoenix rises from corrupted save files!',
        'rewards' => [
            'participation' => ['coins' => 200, 'xp' => 100, 'bpXp' => 50],
            'milestones' => [
                10000 => ['coins' => 600, 'item' => ['type' => 'lootbox', 'rarity' => 'epic']],
                35000 => ['coins' => 1200, 'item' => ['type' => 'card', 'rarity' => 'legendary']],
                75000 => ['coins' => 2500, 'bpXp' => 200],
                150000 => ['coins' => 5000, 'title' => 'Phoenix Slayer', 'badge' => 'phoenix_slayer']
            ],
            'topDamage' => [
                1 => ['coins' => 10000, 'item' => ['type' => 'pet', 'id' => 'shadow_flame']],
                2 => ['coins' => 7000, 'item' => ['type' => 'lootbox', 'rarity' => 'legendary']],
                3 => ['coins' => 4000, 'item' => ['type' => 'lootbox', 'rarity' => 'legendary']]
            ]
        ]
    ],

    // ===== IMPOSSIBLE BOSSES =====
    'void_titan' => [
        'name' => 'Void Titan',
        'icon' => '👁️',
        'hp' => 10000000,
        'difficulty' => 'impossible',
        'duration' => 172800, // 48 hours
        'description' => 'An ancient titan from the void between games! IMPOSSIBLE DIFFICULTY!',
        'rewards' => [
            'participation' => ['coins' => 500, 'xp' => 250, 'bpXp' => 100],
            'milestones' => [
                25000 => ['coins' => 1500, 'item' => ['type' => 'lootbox', 'rarity' => 'legendary']],
                100000 => ['coins' => 4000, 'item' => ['type' => 'card', 'rarity' => 'legendary']],
                250000 => ['coins' => 8000, 'bpXp' => 500],
                500000 => ['coins' => 15000, 'title' => 'Void Walker', 'badge' => 'void_conqueror']
            ],
            'topDamage' => [
                1 => ['coins' => 25000, 'item' => ['type' => 'pet', 'id' => 'void_eye'], 'title' => 'Titan Slayer'],
                2 => ['coins' => 15000, 'item' => ['type' => 'lootbox', 'rarity' => 'legendary']],
                3 => ['coins' => 10000, 'item' => ['type' => 'lootbox', 'rarity' => 'legendary']]
            ]
        ]
    ],
    'omega_destroyer' => [
        'name' => 'OMEGA DESTROYER',
        'icon' => '💀',
        'hp' => 25000000,
        'difficulty' => 'impossible',
        'duration' => 259200, // 72 hours
        'description' => 'THE ULTIMATE BOSS. Can the entire community defeat it?!',
        'rewards' => [
            'participation' => ['coins' => 1000, 'xp' => 500, 'bpXp' => 200],
            'milestones' => [
                50000 => ['coins' => 3000, 'item' => ['type' => 'lootbox', 'rarity' => 'legendary']],
                200000 => ['coins' => 8000, 'item' => ['type' => 'card', 'rarity' => 'legendary']],
                500000 => ['coins' => 15000, 'bpXp' => 1000],
                1000000 => ['coins' => 30000, 'title' => 'OMEGA SLAYER', 'badge' => 'omega_destroyer']
            ],
            'topDamage' => [
                1 => ['coins' => 50000, 'item' => ['type' => 'pet', 'id' => 'omega_skull'], 'title' => 'THE DESTROYER'],
                2 => ['coins' => 30000, 'item' => ['type' => 'lootbox', 'rarity' => 'legendary']],
                3 => ['coins' => 20000, 'item' => ['type' => 'lootbox', 'rarity' => 'legendary']]
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
        'difficulty' => $boss['difficulty'],
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

function isAdmin($username, $ADMINS) {
    return in_array(strtolower($username), array_map('strtolower', $ADMINS));
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
    $user = &$users["users"][$username];
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
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit;
        }

        $username = $_SESSION['user'];
        if (!isAdmin($username, $ADMINS)) {
            echo json_encode(['success' => false, 'error' => 'Admin access required']);
            exit;
        }

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
            'event' => $event,
            'message' => 'Boss spawned by ' . $username
        ]);
        break;

    case 'isAdmin':
        // Check if current user is admin
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => true, 'isAdmin' => false]);
            exit;
        }
        echo json_encode(['success' => true, 'isAdmin' => isAdmin($_SESSION['user'], $ADMINS)]);
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
        // List available bosses grouped by difficulty
        $bossList = [];
        foreach ($BOSSES as $id => $boss) {
            $bossList[] = [
                'id' => $id,
                'name' => $boss['name'],
                'icon' => $boss['icon'],
                'hp' => $boss['hp'],
                'difficulty' => $boss['difficulty'],
                'duration' => $boss['duration'],
                'description' => $boss['description']
            ];
        }

        // Group by difficulty
        $grouped = [
            'easy' => [],
            'medium' => [],
            'hard' => [],
            'impossible' => []
        ];
        foreach ($bossList as $boss) {
            $grouped[$boss['difficulty']][] = $boss;
        }

        echo json_encode([
            'success' => true,
            'bosses' => $bossList,
            'byDifficulty' => $grouped
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
