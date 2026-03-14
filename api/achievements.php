<?php
/**
 * Global Achievements API
 * 
 * GET:
 *   ?action=list - Get all achievements with unlock status
 *   ?action=check - Check for new achievements to unlock
 * 
 * POST:
 *   { action: "unlock", achievementId: "..." }
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dataDir = __DIR__ . '/../data';
$usersFile = $dataDir . '/users.json';

// Achievement definitions
$ACHIEVEMENTS = [
    // Milestone - Games Played
    ['id' => 'first_game', 'name' => 'First Steps', 'desc' => 'Play your first game', 'xp' => 25, 'icon' => '👶', 'category' => 'milestone', 'check' => ['stat' => 'totalGamesPlayed', 'min' => 1]],
    ['id' => 'games_10', 'name' => 'Getting Started', 'desc' => 'Play 10 games', 'xp' => 50, 'icon' => '🎮', 'category' => 'milestone', 'check' => ['stat' => 'totalGamesPlayed', 'min' => 10]],
    ['id' => 'games_50', 'name' => 'Dedicated Player', 'desc' => 'Play 50 games', 'xp' => 75, 'icon' => '🎯', 'category' => 'milestone', 'check' => ['stat' => 'totalGamesPlayed', 'min' => 50]],
    ['id' => 'games_100', 'name' => 'Arcade Regular', 'desc' => 'Play 100 games', 'xp' => 100, 'icon' => '⭐', 'category' => 'milestone', 'check' => ['stat' => 'totalGamesPlayed', 'min' => 100]],
    ['id' => 'games_500', 'name' => 'Arcade Veteran', 'desc' => 'Play 500 games', 'xp' => 200, 'icon' => '🏅', 'category' => 'milestone', 'check' => ['stat' => 'totalGamesPlayed', 'min' => 500]],
    
    // Time-based
    ['id' => 'time_1h', 'name' => 'Time Flies', 'desc' => 'Play for 1 hour total', 'xp' => 50, 'icon' => '⏰', 'category' => 'time', 'check' => ['stat' => 'totalTimePlayed', 'min' => 3600]],
    ['id' => 'time_5h', 'name' => 'Marathon Gamer', 'desc' => 'Play for 5 hours total', 'xp' => 100, 'icon' => '🏃', 'category' => 'time', 'check' => ['stat' => 'totalTimePlayed', 'min' => 18000]],
    ['id' => 'time_24h', 'name' => 'No Life Mode', 'desc' => 'Play for 24 hours total', 'xp' => 200, 'icon' => '🌙', 'category' => 'time', 'check' => ['stat' => 'totalTimePlayed', 'min' => 86400]],
    
    // Tournament
    ['id' => 'tournament_first', 'name' => 'Competitor', 'desc' => 'Enter your first tournament', 'xp' => 50, 'icon' => '🎪', 'category' => 'tournament', 'check' => ['stat' => 'tournamentsEntered', 'min' => 1]],
    ['id' => 'tournament_5', 'name' => 'Tournament Regular', 'desc' => 'Enter 5 tournaments', 'xp' => 100, 'icon' => '🏟️', 'category' => 'tournament', 'check' => ['stat' => 'tournamentsEntered', 'min' => 5]],
    ['id' => 'tournament_win', 'name' => 'Champion', 'desc' => 'Win a tournament', 'xp' => 150, 'icon' => '🥇', 'category' => 'tournament', 'check' => ['stat' => 'tournamentWins', 'min' => 1]],
    ['id' => 'tournament_podium', 'name' => 'Podium Regular', 'desc' => 'Finish top 3 in 3 tournaments', 'xp' => 100, 'icon' => '🏆', 'category' => 'tournament', 'check' => ['stat' => 'tournamentPodiums', 'min' => 3]],
    
    // Social
    ['id' => 'friend_first', 'name' => 'Friendly', 'desc' => 'Add your first friend', 'xp' => 50, 'icon' => '🤝', 'category' => 'social', 'check' => ['stat' => 'friendsCount', 'min' => 1]],
    ['id' => 'friend_5', 'name' => 'Social Butterfly', 'desc' => 'Have 5 friends', 'xp' => 75, 'icon' => '🦋', 'category' => 'social', 'check' => ['stat' => 'friendsCount', 'min' => 5]],
    ['id' => 'friend_10', 'name' => 'Popular', 'desc' => 'Have 10 friends', 'xp' => 100, 'icon' => '🌟', 'category' => 'social', 'check' => ['stat' => 'friendsCount', 'min' => 10]],
    ['id' => 'chat_first', 'name' => 'Chatty', 'desc' => 'Send your first message', 'xp' => 25, 'icon' => '💬', 'category' => 'social', 'check' => ['stat' => 'messagesSent', 'min' => 1]],
    ['id' => 'chat_100', 'name' => 'Talkative', 'desc' => 'Send 100 messages', 'xp' => 75, 'icon' => '🗣️', 'category' => 'social', 'check' => ['stat' => 'messagesSent', 'min' => 100]],
    
    // Level-based
    ['id' => 'level_10', 'name' => 'Rising Star', 'desc' => 'Reach level 10', 'xp' => 100, 'icon' => '📈', 'category' => 'level', 'check' => ['stat' => 'level', 'min' => 10]],
    ['id' => 'level_25', 'name' => 'Experienced', 'desc' => 'Reach level 25', 'xp' => 150, 'icon' => '🎖️', 'category' => 'level', 'check' => ['stat' => 'level', 'min' => 25]],
    ['id' => 'level_50', 'name' => 'Halfway There', 'desc' => 'Reach level 50', 'xp' => 200, 'icon' => '💪', 'category' => 'level', 'check' => ['stat' => 'level', 'min' => 50]],
    ['id' => 'level_100', 'name' => 'Centurion', 'desc' => 'Reach level 100', 'xp' => 500, 'icon' => '👑', 'category' => 'level', 'check' => ['stat' => 'level', 'min' => 100]],
    
    // Secret
    ['id' => 'night_owl', 'name' => 'Night Owl', 'desc' => 'Play between 2-5 AM', 'xp' => 75, 'icon' => '🦉', 'category' => 'secret', 'check' => ['type' => 'time_of_day', 'start' => 2, 'end' => 5]],
    ['id' => 'early_bird', 'name' => 'Early Bird', 'desc' => 'Play between 5-7 AM', 'xp' => 75, 'icon' => '🐦', 'category' => 'secret', 'check' => ['type' => 'time_of_day', 'start' => 5, 'end' => 7]],
    ['id' => 'secret_finder', 'name' => 'Secret Finder', 'desc' => 'Unlock a secret game', 'xp' => 100, 'icon' => '🔮', 'category' => 'secret', 'check' => ['stat' => 'secretGamesUnlocked', 'min' => 1]],
];

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function writeUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

function calculateLevel($xp) {
    return floor(sqrt($xp / 10)) + 1;
}

function getUserStats($user) {
    return [
        'totalGamesPlayed' => $user['stats']['totalGamesPlayed'] ?? 0,
        'totalTimePlayed' => $user['stats']['totalTimePlayed'] ?? 0,
        'tournamentsEntered' => $user['stats']['tournamentsEntered'] ?? 0,
        'tournamentWins' => $user['stats']['tournamentWins'] ?? 0,
        'tournamentPodiums' => $user['stats']['tournamentPodiums'] ?? 0,
        'friendsCount' => count($user['friends'] ?? []),
        'messagesSent' => $user['stats']['messagesSent'] ?? 0,
        'level' => calculateLevel($user['xp'] ?? 0),
        'secretGamesUnlocked' => count($user['secretGamesUnlocked'] ?? [])
    ];
}

function checkAchievement($achievement, $stats) {
    $check = $achievement['check'];
    
    if (isset($check['stat'])) {
        return ($stats[$check['stat']] ?? 0) >= $check['min'];
    }
    
    if (isset($check['type']) && $check['type'] === 'time_of_day') {
        $hour = (int)date('G');
        return $hour >= $check['start'] && $hour < $check['end'];
    }
    
    return false;
}

// Handle requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    global $ACHIEVEMENTS;
    $action = $_GET['action'] ?? 'list';
    
    $userAchievements = [];
    if (isset($_SESSION['user'])) {
        $data = readUsers();
        $username = $_SESSION['user'];
        if (isset($data['users'][$username])) {
            $userAchievements = $data['users'][$username]['achievements'] ?? [];
        }
    }
    
    if ($action === 'list') {
        $result = [];
        foreach ($ACHIEVEMENTS as $ach) {
            $result[] = [
                'id' => $ach['id'],
                'name' => $ach['name'],
                'desc' => $ach['desc'],
                'xp' => $ach['xp'],
                'icon' => $ach['icon'],
                'category' => $ach['category'],
                'unlocked' => in_array($ach['id'], $userAchievements)
            ];
        }
        echo json_encode(['success' => true, 'achievements' => $result]);
        exit();
    }
    
    if ($action === 'check') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit();
        }
        
        $data = readUsers();
        $username = $_SESSION['user'];
        $user = $data['users'][$username];
        $stats = getUserStats($user);
        $currentAchievements = $user['achievements'] ?? [];
        $newlyUnlocked = [];
        $totalXP = 0;
        
        foreach ($ACHIEVEMENTS as $ach) {
            if (!in_array($ach['id'], $currentAchievements)) {
                if (checkAchievement($ach, $stats)) {
                    $currentAchievements[] = $ach['id'];
                    $newlyUnlocked[] = [
                        'id' => $ach['id'],
                        'name' => $ach['name'],
                        'desc' => $ach['desc'],
                        'xp' => $ach['xp'],
                        'icon' => $ach['icon']
                    ];
                    $totalXP += $ach['xp'];
                }
            }
        }
        
        if (count($newlyUnlocked) > 0) {
            $data['users'][$username]['achievements'] = $currentAchievements;
            $data['users'][$username]['xp'] += $totalXP;
            writeUsers($data);
        }
        
        echo json_encode([
            'success' => true,
            'newlyUnlocked' => $newlyUnlocked,
            'totalXPEarned' => $totalXP
        ]);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'unlock') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit();
        }
        
        $achievementId = $input['achievementId'] ?? '';
        global $ACHIEVEMENTS;
        
        $achievement = null;
        foreach ($ACHIEVEMENTS as $ach) {
            if ($ach['id'] === $achievementId) {
                $achievement = $ach;
                break;
            }
        }
        
        if (!$achievement) {
            echo json_encode(['success' => false, 'error' => 'Achievement not found']);
            exit();
        }
        
        $data = readUsers();
        $username = $_SESSION['user'];
        
        if (in_array($achievementId, $data['users'][$username]['achievements'] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'Already unlocked']);
            exit();
        }
        
        $data['users'][$username]['achievements'][] = $achievementId;
        $data['users'][$username]['xp'] += $achievement['xp'];
        writeUsers($data);
        
        echo json_encode([
            'success' => true,
            'achievement' => $achievement,
            'xpEarned' => $achievement['xp']
        ]);
        exit();
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>
