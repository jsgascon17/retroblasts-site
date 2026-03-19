<?php
/**
 * Team Wars API
 * 
 * GET:
 *   ?action=list              - List all active wars
 *   ?action=my                - Get my team's current war
 *   ?action=history           - Get war history
 *   ?action=leaderboard       - Top teams by wars won
 * 
 * POST:
 *   { "action": "challenge", "teamId": "..." }       - Challenge another team
 *   { "action": "accept", "warId": "..." }           - Accept war challenge
 *   { "action": "decline", "warId": "..." }          - Decline war challenge
 *   { "action": "submit-score", "warId": "...", "game": "...", "score": 123 }
 */

header('Content-Type: application/json');
session_start();

$warsFile = __DIR__ . '/../data/wars.json';
$teamsFile = __DIR__ . '/../data/teams.json';
$usersFile = __DIR__ . '/../data/users.json';

function readWars() {
    global $warsFile;
    if (!file_exists($warsFile)) return [];
    return json_decode(file_get_contents($warsFile), true) ?: [];
}

function writeWars($data) {
    global $warsFile;
    file_put_contents($warsFile, json_encode($data, JSON_PRETTY_PRINT));
}

function readTeams() {
    global $teamsFile;
    if (!file_exists($teamsFile)) return [];
    return json_decode(file_get_contents($teamsFile), true) ?: [];
}

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function getUserTeam($username) {
    $teams = readTeams();
    foreach ($teams as $team) {
        if (in_array($username, $team['members'] ?? [])) {
            return $team;
        }
    }
    return null;
}

function isTeamOfficer($username, $team) {
    return in_array($username, $team['officers'] ?? []) || $team['leader'] === $username;
}

// Check auth
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$currentUser = $_SESSION['user'];
$wars = readWars();
$teams = readTeams();

// GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        // List all active wars
        $activeWars = array_filter($wars, function($w) {
            return $w['status'] === 'active' || $w['status'] === 'pending';
        });
        
        // Add team names
        foreach ($activeWars as &$war) {
            foreach ($teams as $t) {
                if ($t['id'] === $war['team1']) $war['team1Name'] = $t['name'];
                if ($t['id'] === $war['team2']) $war['team2Name'] = $t['name'];
            }
        }
        
        echo json_encode(['success' => true, 'wars' => array_values($activeWars)]);
        exit;
    }

    if ($action === 'my') {
        $myTeam = getUserTeam($currentUser);
        if (!$myTeam) {
            echo json_encode(['success' => true, 'war' => null, 'message' => 'Not in a team']);
            exit;
        }

        // Find active war for my team
        $myWar = null;
        foreach ($wars as $war) {
            if (($war['team1'] === $myTeam['id'] || $war['team2'] === $myTeam['id']) 
                && ($war['status'] === 'active' || $war['status'] === 'pending')) {
                $myWar = $war;
                // Add team names
                foreach ($teams as $t) {
                    if ($t['id'] === $myWar['team1']) $myWar['team1Name'] = $t['name'];
                    if ($t['id'] === $myWar['team2']) $myWar['team2Name'] = $t['name'];
                }
                break;
            }
        }

        echo json_encode(['success' => true, 'war' => $myWar, 'myTeamId' => $myTeam['id']]);
        exit;
    }

    if ($action === 'history') {
        $myTeam = getUserTeam($currentUser);
        $history = [];
        
        foreach ($wars as $war) {
            if ($war['status'] === 'completed') {
                if (!$myTeam || $war['team1'] === $myTeam['id'] || $war['team2'] === $myTeam['id']) {
                    // Add team names
                    foreach ($teams as $t) {
                        if ($t['id'] === $war['team1']) $war['team1Name'] = $t['name'];
                        if ($t['id'] === $war['team2']) $war['team2Name'] = $t['name'];
                    }
                    $history[] = $war;
                }
            }
        }
        
        // Sort by completion date
        usort($history, function($a, $b) {
            return strtotime($b['completedAt'] ?? $b['createdAt']) - strtotime($a['completedAt'] ?? $a['createdAt']);
        });
        
        echo json_encode(['success' => true, 'history' => array_slice($history, 0, 20)]);
        exit;
    }

    if ($action === 'leaderboard') {
        // Calculate wins per team
        $teamWins = [];
        foreach ($wars as $war) {
            if ($war['status'] === 'completed' && isset($war['winner'])) {
                if (!isset($teamWins[$war['winner']])) {
                    $teamWins[$war['winner']] = ['wins' => 0, 'losses' => 0];
                }
                $teamWins[$war['winner']]['wins']++;
                
                $loser = $war['winner'] === $war['team1'] ? $war['team2'] : $war['team1'];
                if (!isset($teamWins[$loser])) {
                    $teamWins[$loser] = ['wins' => 0, 'losses' => 0];
                }
                $teamWins[$loser]['losses']++;
            }
        }

        $leaderboard = [];
        foreach ($teams as $team) {
            $stats = $teamWins[$team['id']] ?? ['wins' => 0, 'losses' => 0];
            $leaderboard[] = [
                'id' => $team['id'],
                'name' => $team['name'],
                'tag' => $team['tag'],
                'color' => $team['color'] ?? '#f59e0b',
                'wins' => $stats['wins'],
                'losses' => $stats['losses'],
                'winRate' => $stats['wins'] + $stats['losses'] > 0 
                    ? round($stats['wins'] / ($stats['wins'] + $stats['losses']) * 100) 
                    : 0
            ];
        }

        // Sort by wins
        usort($leaderboard, function($a, $b) {
            if ($b['wins'] !== $a['wins']) return $b['wins'] - $a['wins'];
            return $b['winRate'] - $a['winRate'];
        });

        echo json_encode(['success' => true, 'leaderboard' => array_slice($leaderboard, 0, 20)]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    $myTeam = getUserTeam($currentUser);
    if (!$myTeam && $action !== '') {
        echo json_encode(['success' => false, 'error' => 'You must be in a team']);
        exit;
    }

    if ($action === 'challenge') {
        $targetTeamId = $input['teamId'] ?? '';
        
        if (!isTeamOfficer($currentUser, $myTeam)) {
            echo json_encode(['success' => false, 'error' => 'Only team officers can start wars']);
            exit;
        }

        if ($targetTeamId === $myTeam['id']) {
            echo json_encode(['success' => false, 'error' => "Can't challenge your own team"]);
            exit;
        }

        // Check if either team is already in a war
        foreach ($wars as $war) {
            if ($war['status'] === 'active' || $war['status'] === 'pending') {
                if ($war['team1'] === $myTeam['id'] || $war['team2'] === $myTeam['id']) {
                    echo json_encode(['success' => false, 'error' => 'Your team is already in a war']);
                    exit;
                }
                if ($war['team1'] === $targetTeamId || $war['team2'] === $targetTeamId) {
                    echo json_encode(['success' => false, 'error' => 'That team is already in a war']);
                    exit;
                }
            }
        }

        // Find target team
        $targetTeam = null;
        foreach ($teams as $t) {
            if ($t['id'] === $targetTeamId) {
                $targetTeam = $t;
                break;
            }
        }

        if (!$targetTeam) {
            echo json_encode(['success' => false, 'error' => 'Team not found']);
            exit;
        }

        // Create war challenge
        $war = [
            'id' => 'war_' . uniqid(),
            'team1' => $myTeam['id'],
            'team2' => $targetTeamId,
            'status' => 'pending',
            'createdAt' => date('c'),
            'createdBy' => $currentUser,
            'duration' => 24 * 60 * 60, // 24 hours
            'scores' => [
                $myTeam['id'] => 0,
                $targetTeamId => 0
            ],
            'contributions' => [],
            'games' => ['snake', 'flappy-bird', 'space-invaders', 'knife-hit', 'pacman']
        ];

        $wars[] = $war;
        writeWars($wars);

        echo json_encode(['success' => true, 'war' => $war]);
        exit;
    }

    if ($action === 'accept') {
        $warId = $input['warId'] ?? '';
        
        if (!isTeamOfficer($currentUser, $myTeam)) {
            echo json_encode(['success' => false, 'error' => 'Only team officers can accept wars']);
            exit;
        }

        foreach ($wars as &$war) {
            if ($war['id'] === $warId && $war['status'] === 'pending' && $war['team2'] === $myTeam['id']) {
                $war['status'] = 'active';
                $war['acceptedAt'] = date('c');
                $war['endsAt'] = date('c', time() + $war['duration']);
                writeWars($wars);
                echo json_encode(['success' => true, 'war' => $war]);
                exit;
            }
        }

        echo json_encode(['success' => false, 'error' => 'War not found or cannot accept']);
        exit;
    }

    if ($action === 'decline') {
        $warId = $input['warId'] ?? '';
        
        if (!isTeamOfficer($currentUser, $myTeam)) {
            echo json_encode(['success' => false, 'error' => 'Only team officers can decline wars']);
            exit;
        }

        foreach ($wars as &$war) {
            if ($war['id'] === $warId && $war['status'] === 'pending' && $war['team2'] === $myTeam['id']) {
                $war['status'] = 'declined';
                $war['declinedAt'] = date('c');
                writeWars($wars);
                echo json_encode(['success' => true]);
                exit;
            }
        }

        echo json_encode(['success' => false, 'error' => 'War not found']);
        exit;
    }

    if ($action === 'submit-score') {
        $warId = $input['warId'] ?? '';
        $game = $input['game'] ?? '';
        $score = intval($input['score'] ?? 0);

        foreach ($wars as &$war) {
            if ($war['id'] === $warId && $war['status'] === 'active') {
                // Check if user's team is in this war
                if ($war['team1'] !== $myTeam['id'] && $war['team2'] !== $myTeam['id']) {
                    echo json_encode(['success' => false, 'error' => 'Your team is not in this war']);
                    exit;
                }

                // Check if war has ended
                if (strtotime($war['endsAt']) < time()) {
                    // End the war
                    $war['status'] = 'completed';
                    $war['completedAt'] = date('c');
                    if ($war['scores'][$war['team1']] > $war['scores'][$war['team2']]) {
                        $war['winner'] = $war['team1'];
                    } elseif ($war['scores'][$war['team2']] > $war['scores'][$war['team1']]) {
                        $war['winner'] = $war['team2'];
                    } else {
                        $war['winner'] = 'tie';
                    }
                    writeWars($wars);
                    echo json_encode(['success' => false, 'error' => 'War has ended', 'war' => $war]);
                    exit;
                }

                // Check if valid game
                if (!in_array($game, $war['games'])) {
                    echo json_encode(['success' => false, 'error' => 'Invalid game for this war']);
                    exit;
                }

                // Add contribution
                $contribution = [
                    'user' => $currentUser,
                    'team' => $myTeam['id'],
                    'game' => $game,
                    'score' => $score,
                    'time' => date('c')
                ];
                $war['contributions'][] = $contribution;

                // Add to team score
                $war['scores'][$myTeam['id']] += $score;

                writeWars($wars);
                echo json_encode(['success' => true, 'war' => $war, 'contribution' => $contribution]);
                exit;
            }
        }

        echo json_encode(['success' => false, 'error' => 'War not found or not active']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request method']);
