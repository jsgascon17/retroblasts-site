<?php
/**
 * Seasonal Events API
 * 
 * GET:
 *   ?action=current - Get current active event
 *   ?action=achievements - Get seasonal achievements
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Event definitions
$EVENTS = [
    'stpatricks' => [
        'id' => 'stpatricks',
        'name' => "St. Patrick's Day",
        'icon' => '☘️',
        'startMonth' => 3, 'startDay' => 1,
        'endMonth' => 3, 'endDay' => 31,
        'theme' => 'stpatricks',
        'xpMultiplier' => 1.5,
        'coinMultiplier' => 2,
        'achievements' => [
            ['id' => 'lucky_streak', 'name' => 'Lucky Streak', 'desc' => 'Win 3 games in a row', 'xp' => 100, 'icon' => '🍀'],
            ['id' => 'pot_of_gold', 'name' => 'Pot of Gold', 'desc' => 'Earn 1000 coins this month', 'xp' => 75, 'icon' => '🌈']
        ]
    ],
    'easter' => [
        'id' => 'easter',
        'name' => 'Easter',
        'icon' => '🐰',
        'startMonth' => 4, 'startDay' => 1,
        'endMonth' => 4, 'endDay' => 30,
        'theme' => 'easter',
        'xpMultiplier' => 1.25,
        'coinMultiplier' => 1.5,
        'achievements' => [
            ['id' => 'egg_hunter', 'name' => 'Egg Hunter', 'desc' => 'Play 10 different games', 'xp' => 100, 'icon' => '🥚'],
            ['id' => 'spring_player', 'name' => 'Spring Player', 'desc' => 'Play 25 games this month', 'xp' => 75, 'icon' => '🌸']
        ]
    ],
    'summer' => [
        'id' => 'summer',
        'name' => 'Summer Games',
        'icon' => '☀️',
        'startMonth' => 6, 'startDay' => 1,
        'endMonth' => 8, 'endDay' => 31,
        'theme' => 'summer',
        'xpMultiplier' => 1.25,
        'coinMultiplier' => 1.5,
        'achievements' => [
            ['id' => 'beach_bum', 'name' => 'Beach Bum', 'desc' => 'Play for 2 hours total', 'xp' => 100, 'icon' => '🏖️'],
            ['id' => 'summer_champion', 'name' => 'Summer Champion', 'desc' => 'Win a tournament', 'xp' => 150, 'icon' => '🏆']
        ]
    ],
    'halloween' => [
        'id' => 'halloween',
        'name' => 'Halloween',
        'icon' => '🎃',
        'startMonth' => 10, 'startDay' => 1,
        'endMonth' => 10, 'endDay' => 31,
        'theme' => 'halloween',
        'xpMultiplier' => 1.5,
        'coinMultiplier' => 2,
        'achievements' => [
            ['id' => 'spooky_player', 'name' => 'Spooky Player', 'desc' => 'Play 13 games', 'xp' => 100, 'icon' => '👻'],
            ['id' => 'monster_mash', 'name' => 'Monster Mash', 'desc' => 'Play between midnight and 3 AM', 'xp' => 150, 'icon' => '🧟']
        ]
    ],
    'christmas' => [
        'id' => 'christmas',
        'name' => 'Christmas',
        'icon' => '🎄',
        'startMonth' => 12, 'startDay' => 1,
        'endMonth' => 12, 'endDay' => 31,
        'theme' => 'christmas',
        'xpMultiplier' => 2,
        'coinMultiplier' => 2,
        'achievements' => [
            ['id' => 'gift_giver', 'name' => 'Gift Giver', 'desc' => 'Add 3 new friends', 'xp' => 100, 'icon' => '🎁'],
            ['id' => 'holiday_spirit', 'name' => 'Holiday Spirit', 'desc' => 'Play every day for 7 days', 'xp' => 200, 'icon' => '⭐']
        ]
    ],
    'valentines' => [
        'id' => 'valentines',
        'name' => "Valentine's Day",
        'icon' => '💝',
        'startMonth' => 2, 'startDay' => 1,
        'endMonth' => 2, 'endDay' => 28,
        'theme' => 'valentines',
        'xpMultiplier' => 1.25,
        'coinMultiplier' => 1.5,
        'achievements' => [
            ['id' => 'love_games', 'name' => 'Love Games', 'desc' => 'Play with a friend', 'xp' => 75, 'icon' => '💕'],
            ['id' => 'heartbreaker', 'name' => 'Heartbreaker', 'desc' => 'Score in the top 10', 'xp' => 100, 'icon' => '💔']
        ]
    ],
    'july4' => [
        'id' => 'july4',
        'name' => '4th of July',
        'icon' => '🎆',
        'startMonth' => 7, 'startDay' => 1,
        'endMonth' => 7, 'endDay' => 7,
        'theme' => 'july4',
        'xpMultiplier' => 1.5,
        'coinMultiplier' => 2,
        'achievements' => [
            ['id' => 'firework_player', 'name' => 'Firework Player', 'desc' => 'Play 5 games on July 4th', 'xp' => 100, 'icon' => '🎇'],
            ['id' => 'patriot', 'name' => 'Patriot', 'desc' => 'Win 3 games', 'xp' => 75, 'icon' => '🦅']
        ]
    ]
];

function getCurrentEvent() {
    global $EVENTS;
    
    $month = (int)date('n');
    $day = (int)date('j');
    
    foreach ($EVENTS as $event) {
        $startMonth = $event['startMonth'];
        $startDay = $event['startDay'];
        $endMonth = $event['endMonth'];
        $endDay = $event['endDay'];
        
        // Handle same-month events
        if ($startMonth === $endMonth) {
            if ($month === $startMonth && $day >= $startDay && $day <= $endDay) {
                return $event;
            }
        }
        // Handle multi-month events
        else {
            if (($month === $startMonth && $day >= $startDay) ||
                ($month > $startMonth && $month < $endMonth) ||
                ($month === $endMonth && $day <= $endDay)) {
                return $event;
            }
        }
    }
    
    return null;
}

$action = $_GET['action'] ?? 'current';

if ($action === 'current') {
    $event = getCurrentEvent();
    
    if ($event) {
        echo json_encode([
            'success' => true,
            'hasEvent' => true,
            'event' => [
                'id' => $event['id'],
                'name' => $event['name'],
                'icon' => $event['icon'],
                'theme' => $event['theme'],
                'xpMultiplier' => $event['xpMultiplier'],
                'coinMultiplier' => $event['coinMultiplier'],
                'endMonth' => $event['endMonth'],
                'endDay' => $event['endDay']
            ]
        ]);
    } else {
        echo json_encode(['success' => true, 'hasEvent' => false]);
    }
    exit();
}

if ($action === 'achievements') {
    $event = getCurrentEvent();
    
    if ($event) {
        echo json_encode([
            'success' => true,
            'eventName' => $event['name'],
            'achievements' => $event['achievements']
        ]);
    } else {
        echo json_encode(['success' => true, 'achievements' => []]);
    }
    exit();
}

if ($action === 'all') {
    global $EVENTS;
    echo json_encode(['success' => true, 'events' => array_values($EVENTS)]);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>
