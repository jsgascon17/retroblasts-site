<?php
/**
 * Teams/Clans System API
 * 
 * GET:
 *   ?action=list              - List all teams
 *   ?action=get&id=X          - Get team details
 *   ?action=my                - Get my team
 *   ?action=leaderboard       - Team leaderboard by total XP
 * 
 * POST:
 *   { "action": "create", "name": "Team Name", "tag": "TAG", "description": "...", "color": "#ff0000" }
 *   { "action": "join", "teamId": "..." }
 *   { "action": "leave" }
 *   { "action": "kick", "username": "..." }
 *   { "action": "promote", "username": "..." }
 *   { "action": "update", "description": "...", "color": "..." }
 *   { "action": "disband" }
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$teamsFile = __DIR__ . '/../data/teams.json';
$usersFile = __DIR__ . '/../data/users.json';

function readTeams() {
    global $teamsFile;
    if (!file_exists($teamsFile)) return ['teams' => []];
    return json_decode(file_get_contents($teamsFile), true) ?: ['teams' => []];
}

function writeTeams($data) {
    global $teamsFile;
    file_put_contents($teamsFile, json_encode($data, JSON_PRETTY_PRINT));
}

function readUsers() {
    global $usersFile;
    if (!file_exists($usersFile)) return ['users' => []];
    return json_decode(file_get_contents($usersFile), true) ?: ['users' => []];
}

function writeUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

function sanitize($input, $maxLength = 50) {
    return substr(strip_tags(trim($input)), 0, $maxLength);
}

function getTeamStats($team, $users) {
    $totalXp = 0;
    $memberCount = count($team['members']);
    $onlineCount = 0;
    $now = time();
    
    foreach ($team['members'] as $username) {
        if (isset($users['users'][$username])) {
            $user = $users['users'][$username];
            $totalXp += $user['xp'] ?? 0;
            if (isset($user['lastActivity']) && strtotime($user['lastActivity']) > $now - 300) {
                $onlineCount++;
            }
        }
    }
    
    return [
        'totalXp' => $totalXp,
        'memberCount' => $memberCount,
        'onlineCount' => $onlineCount,
        'avgXp' => $memberCount > 0 ? round($totalXp / $memberCount) : 0
    ];
}

// GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    $teamsData = readTeams();
    $usersData = readUsers();
    
    if ($action === 'list') {
        $teams = [];
        foreach ($teamsData['teams'] as $team) {
            $stats = getTeamStats($team, $usersData);
            $teams[] = [
                'id' => $team['id'],
                'name' => $team['name'],
                'tag' => $team['tag'],
                'color' => $team['color'],
                'leader' => $team['leader'],
                'memberCount' => $stats['memberCount'],
                'totalXp' => $stats['totalXp'],
                'createdAt' => $team['createdAt']
            ];
        }
        
        // Sort by total XP
        usort($teams, function($a, $b) {
            return $b['totalXp'] - $a['totalXp'];
        });
        
        echo json_encode(['success' => true, 'teams' => $teams]);
        exit();
    }
    
    if ($action === 'get') {
        $teamId = $_GET['id'] ?? '';
        $team = null;
        
        foreach ($teamsData['teams'] as $t) {
            if ($t['id'] === $teamId) {
                $team = $t;
                break;
            }
        }
        
        if (!$team) {
            echo json_encode(['success' => false, 'error' => 'Team not found']);
            exit();
        }
        
        $stats = getTeamStats($team, $usersData);
        
        // Get member details
        $members = [];
        foreach ($team['members'] as $username) {
            if (isset($usersData['users'][$username])) {
                $u = $usersData['users'][$username];
                $members[] = [
                    'username' => $username,
                    'displayName' => $u['displayName'] ?? $username,
                    'avatar' => $u['avatar'] ?? '😎',
                    'xp' => $u['xp'] ?? 0,
                    'level' => floor(sqrt(($u['xp'] ?? 0) / 10)) + 1,
                    'role' => $username === $team['leader'] ? 'leader' : (in_array($username, $team['officers'] ?? []) ? 'officer' : 'member'),
                    'isOnline' => isset($u['lastActivity']) && strtotime($u['lastActivity']) > time() - 300
                ];
            }
        }
        
        // Sort by XP
        usort($members, function($a, $b) {
            return $b['xp'] - $a['xp'];
        });
        
        echo json_encode([
            'success' => true,
            'team' => [
                'id' => $team['id'],
                'name' => $team['name'],
                'tag' => $team['tag'],
                'description' => $team['description'] ?? '',
                'color' => $team['color'],
                'leader' => $team['leader'],
                'officers' => $team['officers'] ?? [],
                'members' => $members,
                'stats' => $stats,
                'createdAt' => $team['createdAt']
            ]
        ]);
        exit();
    }
    
    if ($action === 'my') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit();
        }
        
        $currentUser = $_SESSION['user'];
        $myTeam = null;
        
        foreach ($teamsData['teams'] as $team) {
            if (in_array($currentUser, $team['members'])) {
                $myTeam = $team;
                break;
            }
        }
        
        if (!$myTeam) {
            echo json_encode(['success' => true, 'team' => null]);
            exit();
        }
        
        $stats = getTeamStats($myTeam, $usersData);
        echo json_encode([
            'success' => true,
            'team' => [
                'id' => $myTeam['id'],
                'name' => $myTeam['name'],
                'tag' => $myTeam['tag'],
                'color' => $myTeam['color'],
                'role' => $currentUser === $myTeam['leader'] ? 'leader' : (in_array($currentUser, $myTeam['officers'] ?? []) ? 'officer' : 'member'),
                'stats' => $stats
            ]
        ]);
        exit();
    }
    
    if ($action === 'leaderboard') {
        $teams = [];
        foreach ($teamsData['teams'] as $team) {
            $stats = getTeamStats($team, $usersData);
            $teams[] = [
                'id' => $team['id'],
                'name' => $team['name'],
                'tag' => $team['tag'],
                'color' => $team['color'],
                'totalXp' => $stats['totalXp'],
                'memberCount' => $stats['memberCount'],
                'avgXp' => $stats['avgXp']
            ];
        }
        
        usort($teams, function($a, $b) {
            return $b['totalXp'] - $a['totalXp'];
        });
        
        echo json_encode(['success' => true, 'leaderboard' => array_slice($teams, 0, 20)]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

// POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit();
    }
    
    $currentUser = $_SESSION['user'];
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $teamsData = readTeams();
    $usersData = readUsers();
    
    // Find user's current team
    $currentTeam = null;
    $currentTeamIndex = -1;
    foreach ($teamsData['teams'] as $i => $team) {
        if (in_array($currentUser, $team['members'])) {
            $currentTeam = $team;
            $currentTeamIndex = $i;
            break;
        }
    }
    
    if ($action === 'create') {
        if ($currentTeam) {
            echo json_encode(['success' => false, 'error' => 'You are already in a team']);
            exit();
        }
        
        $name = sanitize($input['name'] ?? '', 30);
        $tag = strtoupper(sanitize($input['tag'] ?? '', 5));
        $description = sanitize($input['description'] ?? '', 200);
        $color = $input['color'] ?? '#ffd700';
        
        if (strlen($name) < 3) {
            echo json_encode(['success' => false, 'error' => 'Team name must be at least 3 characters']);
            exit();
        }
        
        if (strlen($tag) < 2 || strlen($tag) > 5) {
            echo json_encode(['success' => false, 'error' => 'Tag must be 2-5 characters']);
            exit();
        }
        
        // Check for duplicate name/tag
        foreach ($teamsData['teams'] as $t) {
            if (strtolower($t['name']) === strtolower($name)) {
                echo json_encode(['success' => false, 'error' => 'Team name already taken']);
                exit();
            }
            if (strtolower($t['tag']) === strtolower($tag)) {
                echo json_encode(['success' => false, 'error' => 'Tag already taken']);
                exit();
            }
        }
        
        $newTeam = [
            'id' => bin2hex(random_bytes(8)),
            'name' => $name,
            'tag' => $tag,
            'description' => $description,
            'color' => $color,
            'leader' => $currentUser,
            'officers' => [],
            'members' => [$currentUser],
            'createdAt' => date('c')
        ];
        
        $teamsData['teams'][] = $newTeam;
        writeTeams($teamsData);
        
        echo json_encode(['success' => true, 'message' => 'Team created!', 'team' => $newTeam]);
        exit();
    }
    
    if ($action === 'join') {
        if ($currentTeam) {
            echo json_encode(['success' => false, 'error' => 'You are already in a team']);
            exit();
        }
        
        $teamId = $input['teamId'] ?? '';
        $teamIndex = -1;
        
        foreach ($teamsData['teams'] as $i => $t) {
            if ($t['id'] === $teamId) {
                $teamIndex = $i;
                break;
            }
        }
        
        if ($teamIndex === -1) {
            echo json_encode(['success' => false, 'error' => 'Team not found']);
            exit();
        }
        
        if (count($teamsData['teams'][$teamIndex]['members']) >= 50) {
            echo json_encode(['success' => false, 'error' => 'Team is full (max 50 members)']);
            exit();
        }
        
        $teamsData['teams'][$teamIndex]['members'][] = $currentUser;
        writeTeams($teamsData);
        
        echo json_encode(['success' => true, 'message' => 'Joined team!']);
        exit();
    }
    
    if ($action === 'leave') {
        if (!$currentTeam) {
            echo json_encode(['success' => false, 'error' => 'You are not in a team']);
            exit();
        }
        
        if ($currentTeam['leader'] === $currentUser) {
            echo json_encode(['success' => false, 'error' => 'Leader cannot leave. Transfer leadership or disband the team.']);
            exit();
        }
        
        $teamsData['teams'][$currentTeamIndex]['members'] = array_values(array_filter(
            $teamsData['teams'][$currentTeamIndex]['members'],
            function($m) use ($currentUser) { return $m !== $currentUser; }
        ));
        $teamsData['teams'][$currentTeamIndex]['officers'] = array_values(array_filter(
            $teamsData['teams'][$currentTeamIndex]['officers'] ?? [],
            function($o) use ($currentUser) { return $o !== $currentUser; }
        ));
        
        writeTeams($teamsData);
        echo json_encode(['success' => true, 'message' => 'Left team']);
        exit();
    }
    
    if ($action === 'kick') {
        if (!$currentTeam) {
            echo json_encode(['success' => false, 'error' => 'You are not in a team']);
            exit();
        }
        
        $isLeader = $currentTeam['leader'] === $currentUser;
        $isOfficer = in_array($currentUser, $currentTeam['officers'] ?? []);
        
        if (!$isLeader && !$isOfficer) {
            echo json_encode(['success' => false, 'error' => 'Only leaders and officers can kick members']);
            exit();
        }
        
        $targetUser = strtolower($input['username'] ?? '');
        
        if ($targetUser === $currentTeam['leader']) {
            echo json_encode(['success' => false, 'error' => 'Cannot kick the leader']);
            exit();
        }
        
        if (!$isLeader && in_array($targetUser, $currentTeam['officers'] ?? [])) {
            echo json_encode(['success' => false, 'error' => 'Officers cannot kick other officers']);
            exit();
        }
        
        $teamsData['teams'][$currentTeamIndex]['members'] = array_values(array_filter(
            $teamsData['teams'][$currentTeamIndex]['members'],
            function($m) use ($targetUser) { return $m !== $targetUser; }
        ));
        $teamsData['teams'][$currentTeamIndex]['officers'] = array_values(array_filter(
            $teamsData['teams'][$currentTeamIndex]['officers'] ?? [],
            function($o) use ($targetUser) { return $o !== $targetUser; }
        ));
        
        writeTeams($teamsData);
        echo json_encode(['success' => true, 'message' => 'Member kicked']);
        exit();
    }
    
    if ($action === 'promote') {
        if (!$currentTeam || $currentTeam['leader'] !== $currentUser) {
            echo json_encode(['success' => false, 'error' => 'Only the leader can promote members']);
            exit();
        }
        
        $targetUser = strtolower($input['username'] ?? '');
        $toRole = $input['role'] ?? 'officer';
        
        if (!in_array($targetUser, $currentTeam['members'])) {
            echo json_encode(['success' => false, 'error' => 'User is not in your team']);
            exit();
        }
        
        if ($toRole === 'leader') {
            $teamsData['teams'][$currentTeamIndex]['leader'] = $targetUser;
            if (!in_array($currentUser, $teamsData['teams'][$currentTeamIndex]['officers'] ?? [])) {
                $teamsData['teams'][$currentTeamIndex]['officers'][] = $currentUser;
            }
        } else if ($toRole === 'officer') {
            if (!isset($teamsData['teams'][$currentTeamIndex]['officers'])) {
                $teamsData['teams'][$currentTeamIndex]['officers'] = [];
            }
            if (!in_array($targetUser, $teamsData['teams'][$currentTeamIndex]['officers'])) {
                $teamsData['teams'][$currentTeamIndex]['officers'][] = $targetUser;
            }
        } else {
            $teamsData['teams'][$currentTeamIndex]['officers'] = array_values(array_filter(
                $teamsData['teams'][$currentTeamIndex]['officers'] ?? [],
                function($o) use ($targetUser) { return $o !== $targetUser; }
            ));
        }
        
        writeTeams($teamsData);
        echo json_encode(['success' => true, 'message' => 'Role updated']);
        exit();
    }
    
    if ($action === 'disband') {
        if (!$currentTeam || $currentTeam['leader'] !== $currentUser) {
            echo json_encode(['success' => false, 'error' => 'Only the leader can disband the team']);
            exit();
        }
        
        $teamsData['teams'] = array_values(array_filter(
            $teamsData['teams'],
            function($t) use ($currentTeam) { return $t['id'] !== $currentTeam['id']; }
        ));
        
        writeTeams($teamsData);
        echo json_encode(['success' => true, 'message' => 'Team disbanded']);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
