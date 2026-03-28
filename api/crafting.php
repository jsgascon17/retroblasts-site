<?php
session_start();
header('Content-Type: application/json');

$usersFile = __DIR__ . '/../data/users.json';

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

// Crafting recipes
$RECIPES = [
    // Lootbox upgrades
    'rare_lootbox' => [
        'name' => 'Rare Lootbox',
        'icon' => '📦',
        'ingredients' => [
            ['type' => 'lootbox', 'rarity' => 'common', 'count' => 3]
        ],
        'result' => ['type' => 'lootbox', 'rarity' => 'rare', 'count' => 1]
    ],
    'epic_lootbox' => [
        'name' => 'Epic Lootbox',
        'icon' => '📦',
        'ingredients' => [
            ['type' => 'lootbox', 'rarity' => 'rare', 'count' => 3]
        ],
        'result' => ['type' => 'lootbox', 'rarity' => 'epic', 'count' => 1]
    ],
    'legendary_lootbox' => [
        'name' => 'Legendary Lootbox',
        'icon' => '📦',
        'ingredients' => [
            ['type' => 'lootbox', 'rarity' => 'epic', 'count' => 3]
        ],
        'result' => ['type' => 'lootbox', 'rarity' => 'legendary', 'count' => 1]
    ],

    // Card combinations
    'rare_card' => [
        'name' => 'Rare Trading Card',
        'icon' => '🃏',
        'ingredients' => [
            ['type' => 'card', 'rarity' => 'common', 'count' => 5]
        ],
        'result' => ['type' => 'card', 'rarity' => 'rare', 'count' => 1]
    ],
    'epic_card' => [
        'name' => 'Epic Trading Card',
        'icon' => '🃏',
        'ingredients' => [
            ['type' => 'card', 'rarity' => 'rare', 'count' => 4]
        ],
        'result' => ['type' => 'card', 'rarity' => 'epic', 'count' => 1]
    ],
    'legendary_card' => [
        'name' => 'Legendary Trading Card',
        'icon' => '🃏',
        'ingredients' => [
            ['type' => 'card', 'rarity' => 'epic', 'count' => 3]
        ],
        'result' => ['type' => 'card', 'rarity' => 'legendary', 'count' => 1]
    ],

    // Booster crafting
    'xp_booster' => [
        'name' => 'XP Booster (2x, 1hr)',
        'icon' => '⚡',
        'ingredients' => [
            ['type' => 'card', 'rarity' => 'common', 'count' => 3],
            ['type' => 'coins', 'count' => 100]
        ],
        'result' => ['type' => 'booster', 'id' => 'xp_boost_2x_1h', 'count' => 1]
    ],
    'coin_booster' => [
        'name' => 'Coin Booster (2x, 1hr)',
        'icon' => '🪙',
        'ingredients' => [
            ['type' => 'card', 'rarity' => 'common', 'count' => 3],
            ['type' => 'coins', 'count' => 100]
        ],
        'result' => ['type' => 'booster', 'id' => 'coin_boost_2x_1h', 'count' => 1]
    ],
    'mega_xp_booster' => [
        'name' => 'Mega XP Booster (3x, 2hr)',
        'icon' => '⚡',
        'ingredients' => [
            ['type' => 'booster', 'id' => 'xp_boost_2x_1h', 'count' => 2],
            ['type' => 'card', 'rarity' => 'rare', 'count' => 2]
        ],
        'result' => ['type' => 'booster', 'id' => 'xp_boost_3x_2h', 'count' => 1]
    ],

    // Pet egg crafting
    'rare_pet_egg' => [
        'name' => 'Rare Pet Egg',
        'icon' => '🥚',
        'ingredients' => [
            ['type' => 'lootbox', 'rarity' => 'rare', 'count' => 2],
            ['type' => 'card', 'rarity' => 'rare', 'count' => 3]
        ],
        'result' => ['type' => 'petEgg', 'rarity' => 'rare', 'count' => 1]
    ],
    'epic_pet_egg' => [
        'name' => 'Epic Pet Egg',
        'icon' => '🥚',
        'ingredients' => [
            ['type' => 'lootbox', 'rarity' => 'epic', 'count' => 2],
            ['type' => 'card', 'rarity' => 'epic', 'count' => 2]
        ],
        'result' => ['type' => 'petEgg', 'rarity' => 'epic', 'count' => 1]
    ],

    // Special items
    'lucky_charm' => [
        'name' => 'Lucky Charm',
        'icon' => '🍀',
        'description' => '+10% loot drop chance for 24 hours',
        'ingredients' => [
            ['type' => 'card', 'rarity' => 'rare', 'count' => 5],
            ['type' => 'coins', 'count' => 500]
        ],
        'result' => ['type' => 'charm', 'id' => 'lucky_charm', 'duration' => 86400, 'count' => 1]
    ],
    'xp_charm' => [
        'name' => 'XP Charm',
        'icon' => '✨',
        'description' => '+25% XP for 24 hours',
        'ingredients' => [
            ['type' => 'card', 'rarity' => 'epic', 'count' => 3],
            ['type' => 'coins', 'count' => 750]
        ],
        'result' => ['type' => 'charm', 'id' => 'xp_charm', 'duration' => 86400, 'count' => 1]
    ],

    // Mystery box
    'mystery_box' => [
        'name' => 'Mystery Box',
        'icon' => '🎁',
        'description' => 'Contains random rare+ items',
        'ingredients' => [
            ['type' => 'lootbox', 'rarity' => 'common', 'count' => 2],
            ['type' => 'lootbox', 'rarity' => 'rare', 'count' => 1],
            ['type' => 'card', 'rarity' => 'uncommon', 'count' => 5]
        ],
        'result' => ['type' => 'mysteryBox', 'count' => 1]
    ]
];

$users = loadJson($usersFile);
$user = &$users[$username];

// Ensure inventory structure
if (!isset($user['inventory'])) $user['inventory'] = [];
if (!isset($user['inventory']['lootboxes'])) $user['inventory']['lootboxes'] = [];
if (!isset($user['inventory']['tradingCards'])) $user['inventory']['tradingCards'] = [];
if (!isset($user['inventory']['boosters'])) $user['inventory']['boosters'] = [];
if (!isset($user['inventory']['petEggs'])) $user['inventory']['petEggs'] = [];
if (!isset($user['inventory']['charms'])) $user['inventory']['charms'] = [];
if (!isset($user['inventory']['mysteryBoxes'])) $user['inventory']['mysteryBoxes'] = [];

function countItems(&$user, $type, $subtype = null) {
    switch ($type) {
        case 'lootbox':
            return count(array_filter($user['inventory']['lootboxes'], fn($l) => $l['type'] === $subtype));
        case 'card':
            return count(array_filter($user['inventory']['tradingCards'], fn($c) => $c['rarity'] === $subtype));
        case 'booster':
            return count(array_filter($user['inventory']['boosters'], fn($b) => $b['type'] === $subtype));
        case 'coins':
            return $user['coins'] ?? 0;
        default:
            return 0;
    }
}

function removeItems(&$user, $type, $subtype, $count) {
    switch ($type) {
        case 'lootbox':
            $removed = 0;
            $user['inventory']['lootboxes'] = array_values(array_filter(
                $user['inventory']['lootboxes'],
                function($l) use ($subtype, &$removed, $count) {
                    if ($l['type'] === $subtype && $removed < $count) {
                        $removed++;
                        return false;
                    }
                    return true;
                }
            ));
            break;
        case 'card':
            $removed = 0;
            $user['inventory']['tradingCards'] = array_values(array_filter(
                $user['inventory']['tradingCards'],
                function($c) use ($subtype, &$removed, $count) {
                    if ($c['rarity'] === $subtype && $removed < $count) {
                        $removed++;
                        return false;
                    }
                    return true;
                }
            ));
            break;
        case 'booster':
            $removed = 0;
            $user['inventory']['boosters'] = array_values(array_filter(
                $user['inventory']['boosters'],
                function($b) use ($subtype, &$removed, $count) {
                    if ($b['type'] === $subtype && $removed < $count) {
                        $removed++;
                        return false;
                    }
                    return true;
                }
            ));
            break;
        case 'coins':
            $user['coins'] = ($user['coins'] ?? 0) - $count;
            break;
    }
}

switch ($action) {
    case 'recipes':
        // Return all recipes with availability
        $recipesWithAvail = [];
        foreach ($RECIPES as $id => $recipe) {
            $canCraft = true;
            $ingredientsStatus = [];

            foreach ($recipe['ingredients'] as $ing) {
                $have = countItems($user, $ing['type'], $ing['rarity'] ?? $ing['id'] ?? null);
                $need = $ing['count'];
                $ingredientsStatus[] = [
                    'type' => $ing['type'],
                    'subtype' => $ing['rarity'] ?? $ing['id'] ?? null,
                    'have' => $have,
                    'need' => $need,
                    'enough' => $have >= $need
                ];
                if ($have < $need) $canCraft = false;
            }

            $recipesWithAvail[$id] = array_merge($recipe, [
                'id' => $id,
                'canCraft' => $canCraft,
                'ingredientsStatus' => $ingredientsStatus
            ]);
        }

        echo json_encode([
            'success' => true,
            'recipes' => $recipesWithAvail
        ]);
        break;

    case 'craft':
        $input = json_decode(file_get_contents('php://input'), true);
        $recipeId = $input['recipe'] ?? '';

        if (!isset($RECIPES[$recipeId])) {
            echo json_encode(['success' => false, 'error' => 'Invalid recipe']);
            exit;
        }

        $recipe = $RECIPES[$recipeId];

        // Check all ingredients
        foreach ($recipe['ingredients'] as $ing) {
            $have = countItems($user, $ing['type'], $ing['rarity'] ?? $ing['id'] ?? null);
            if ($have < $ing['count']) {
                echo json_encode(['success' => false, 'error' => 'Not enough materials']);
                exit;
            }
        }

        // Remove ingredients
        foreach ($recipe['ingredients'] as $ing) {
            removeItems($user, $ing['type'], $ing['rarity'] ?? $ing['id'] ?? null, $ing['count']);
        }

        // Add result
        $result = $recipe['result'];
        for ($i = 0; $i < $result['count']; $i++) {
            switch ($result['type']) {
                case 'lootbox':
                    $user['inventory']['lootboxes'][] = ['type' => $result['rarity'], 'obtained' => date('c')];
                    break;
                case 'card':
                    $cardIds = ['arcade_hero', 'pixel_warrior', 'retro_master', 'game_legend', 'digital_knight'];
                    $user['inventory']['tradingCards'][] = [
                        'id' => $cardIds[array_rand($cardIds)] . '_' . rand(1, 100),
                        'rarity' => $result['rarity'],
                        'obtained' => date('c')
                    ];
                    break;
                case 'booster':
                    $user['inventory']['boosters'][] = ['type' => $result['id'], 'obtained' => date('c')];
                    break;
                case 'petEgg':
                    $user['inventory']['petEggs'][] = ['rarity' => $result['rarity'], 'obtained' => date('c')];
                    break;
                case 'charm':
                    $user['inventory']['charms'][] = [
                        'id' => $result['id'],
                        'duration' => $result['duration'],
                        'obtained' => date('c')
                    ];
                    break;
                case 'mysteryBox':
                    $user['inventory']['mysteryBoxes'][] = ['obtained' => date('c')];
                    break;
            }
        }

        saveJson($usersFile, $users);

        echo json_encode([
            'success' => true,
            'crafted' => $recipe['name'],
            'result' => $result
        ]);
        break;

    case 'inventory':
        // Return crafting-relevant inventory counts
        $counts = [
            'lootboxes' => [
                'common' => countItems($user, 'lootbox', 'common'),
                'rare' => countItems($user, 'lootbox', 'rare'),
                'epic' => countItems($user, 'lootbox', 'epic'),
                'legendary' => countItems($user, 'lootbox', 'legendary')
            ],
            'cards' => [
                'common' => countItems($user, 'card', 'common'),
                'uncommon' => countItems($user, 'card', 'uncommon'),
                'rare' => countItems($user, 'card', 'rare'),
                'epic' => countItems($user, 'card', 'epic'),
                'legendary' => countItems($user, 'card', 'legendary')
            ],
            'coins' => $user['coins'] ?? 0,
            'boosters' => count($user['inventory']['boosters'] ?? []),
            'petEggs' => count($user['inventory']['petEggs'] ?? []),
            'charms' => count($user['inventory']['charms'] ?? []),
            'mysteryBoxes' => count($user['inventory']['mysteryBoxes'] ?? [])
        ];

        echo json_encode([
            'success' => true,
            'inventory' => $counts
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
