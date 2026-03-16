<?php
/**
 * User Authentication API
 * 
 * POST actions:
 *   register: { username, password, displayName }
 *   login: { username, password }
 *   logout: {}
 *   check: {} - Verify session
 *   update-profile: { displayName, avatar }
 *   add-xp: { amount, source }
 *   get-user: { username } - Get public profile
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

// Initialize data file if needed
if (!file_exists($usersFile)) {
    file_put_contents($usersFile, json_encode(['users' => []], JSON_PRETTY_PRINT));
    chmod($usersFile, 0666);
}

function readUsers() {
    global $usersFile;
    $data = json_decode(file_get_contents($usersFile), true);
    return $data ?: ['users' => []];
}

function writeUsers($data) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($data, JSON_PRETTY_PRINT));
}

function sanitize($input, $maxLength = 20) {
    $clean = strip_tags(trim($input));
    $clean = preg_replace('/[^\w\s\-]/', '', $clean);
    return substr($clean, 0, $maxLength);
}

function generateId() {
    return bin2hex(random_bytes(8));
}

function calculateLevel($xp) {
    return floor(sqrt($xp / 10)) + 1;
}

function xpForLevel($level) {
    return pow($level - 1, 2) * 10;
}

function getTitle($level) {
    if ($level >= 100) return 'Arcade God';
    if ($level >= 75) return 'Gaming Legend';
    if ($level >= 50) return 'Arcade Master';
    if ($level >= 30) return 'Pro Gamer';
    if ($level >= 20) return 'Arcade Regular';
    if ($level >= 10) return 'Game Enthusiast';
    if ($level >= 5) return 'Casual Gamer';
    return 'Arcade Newbie';
}

function updateLastActivity($username) {
    $data = readUsers();
    if (isset($data['users'][$username])) {
        $data['users'][$username]['lastActivity'] = date('c');
        writeUsers($data);
    }
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'check';
    
    if ($action === 'check') {
        if (isset($_SESSION['user'])) {
            $data = readUsers();
            $username = $_SESSION['user'];
            if (isset($data['users'][$username])) {
                $user = $data['users'][$username];
                updateLastActivity($username);
                echo json_encode([
                    'success' => true,
                    'loggedIn' => true,
                    'user' => [
                        'username' => $username,
                        'displayName' => $user['displayName'],
                        'avatar' => $user['avatar'],
                        'xp' => $user['xp'],
                        'level' => calculateLevel($user['xp']),
                        'title' => getTitle(calculateLevel($user['xp'])),
                        'achievements' => $user['achievements'] ?? [],
                        'friends' => $user['friends'] ?? [],
                        'stats' => $user['stats'] ?? [],
                        'coins' => $user['coins'] ?? 0
                    ]
                ]);
                exit();
            }
        }
        echo json_encode(['success' => true, 'loggedIn' => false]);
        exit();
    }
    
    if ($action === 'user') {
        $username = sanitize($_GET['username'] ?? '');
        $data = readUsers();
        if (isset($data['users'][$username])) {
            $user = $data['users'][$username];
            $level = calculateLevel($user['xp']);
            $isOnline = isset($user['lastActivity']) && 
                        (time() - strtotime($user['lastActivity'])) < 300;
            echo json_encode([
                'success' => true,
                'user' => [
                    'username' => $username,
                    'displayName' => $user['displayName'],
                    'avatar' => $user['avatar'],
                    'xp' => $user['xp'],
                    'level' => $level,
                    'title' => getTitle($level),
                    'isOnline' => $isOnline,
                    'achievements' => $user['achievements'] ?? [],
                    'stats' => $user['stats'] ?? [],
                        'coins' => $user['coins'] ?? 0,
                    'createdAt' => $user['createdAt'] ?? null
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'User not found']);
        }
        exit();
    }
    
    if ($action === 'leaderboard') {
        $data = readUsers();
        $users = [];
        foreach ($data['users'] as $username => $user) {
            $users[] = [
                'username' => $username,
                'displayName' => $user['displayName'],
                'avatar' => $user['avatar'],
                'xp' => $user['xp'],
                'level' => calculateLevel($user['xp'])
            ];
        }
        usort($users, function($a, $b) { return $b['xp'] - $a['xp']; });
        echo json_encode(['success' => true, 'users' => array_slice($users, 0, 50)]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'register') {
        $username = strtolower(sanitize($input['username'] ?? '', 20));
        $password = $input['password'] ?? '';
        $displayName = sanitize($input['displayName'] ?? $username, 20);
        
        if (strlen($username) < 3) {
            echo json_encode(['success' => false, 'error' => 'Username must be at least 3 characters']);
            exit();
        }
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
            exit();
        }
        
        $data = readUsers();
        if (isset($data['users'][$username])) {
            echo json_encode(['success' => false, 'error' => 'Username already taken']);
            exit();
        }
        
        $data['users'][$username] = [
            'id' => generateId(),
            'username' => $username,
            'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
            'displayName' => $displayName ?: $username,
            'avatar' => '😎',
            'xp' => 0,
            'coins' => 500,
            'friends' => [],
            'friendRequests' => ['incoming' => [], 'outgoing' => []],
            'achievements' => [],
            'secretGamesUnlocked' => [],
            'stats' => [
                'totalGamesPlayed' => 0,
                'totalTimePlayed' => 0,
                'tournamentsEntered' => 0,
                'tournamentWins' => 0,
                'tournamentPodiums' => 0,
                'messagesSent' => 0
            ],
            'createdAt' => date('c'),
            'lastActivity' => date('c')
        ];
        
        writeUsers($data);
        
        $_SESSION['user'] = $username;
        session_regenerate_id(true);
        
        echo json_encode([
            'success' => true,
            'message' => 'Account created!',
            'user' => [
                'username' => $username,
                'displayName' => $data['users'][$username]['displayName'],
                'avatar' => '😎',
                'xp' => 0,
            'coins' => 500,
                'level' => 1,
                'title' => 'Arcade Newbie'
            ]
        ]);
        exit();
    }
    
    if ($action === 'login') {
        $username = strtolower(sanitize($input['username'] ?? '', 20));
        $password = $input['password'] ?? '';
        
        $data = readUsers();
        if (!isset($data['users'][$username])) {
            echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
            exit();
        }
        
        if (!password_verify($password, $data['users'][$username]['passwordHash'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
            exit();
        }
        
        $data['users'][$username]['lastActivity'] = date('c');
        $data['users'][$username]['lastLogin'] = date('c');
        writeUsers($data);
        
        $_SESSION['user'] = $username;
        session_regenerate_id(true);
        
        $user = $data['users'][$username];
        $level = calculateLevel($user['xp']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Welcome back!',
            'user' => [
                'username' => $username,
                'displayName' => $user['displayName'],
                'avatar' => $user['avatar'],
                'xp' => $user['xp'],
                'level' => $level,
                'title' => getTitle($level),
                'achievements' => $user['achievements'] ?? [],
                'friends' => $user['friends'] ?? []
            ]
        ]);
        exit();
    }
    
    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out']);
        exit();
    }
    
    if ($action === 'update-profile') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit();
        }
        
        $data = readUsers();
        $username = $_SESSION['user'];
        
        if (isset($input['displayName'])) {
            $data['users'][$username]['displayName'] = sanitize($input['displayName'], 20);
        }
        if (isset($input['avatar'])) {
            $data['users'][$username]['avatar'] = mb_substr($input['avatar'], 0, 2);
        }
        
        writeUsers($data);
        echo json_encode(['success' => true, 'message' => 'Profile updated']);
        exit();
    }
    
    if ($action === 'add-xp') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit();
        }
        
        $amount = intval($input['amount'] ?? 0);
        $source = sanitize($input['source'] ?? 'unknown', 30);
        
        if ($amount <= 0 || $amount > 500) {
            echo json_encode(['success' => false, 'error' => 'Invalid XP amount']);
            exit();
        }
        
        $data = readUsers();
        $username = $_SESSION['user'];
        
        $oldXP = $data['users'][$username]['xp'];
        $oldLevel = calculateLevel($oldXP);
        
        $data['users'][$username]['xp'] += $amount;
        $newXP = $data['users'][$username]['xp'];
        $newLevel = calculateLevel($newXP);
        
        // Update stats based on source
        if ($source === 'game_played') {
            $data['users'][$username]['stats']['totalGamesPlayed']++;
        } elseif ($source === 'tournament_entry') {
            $data['users'][$username]['stats']['tournamentsEntered']++;
        } elseif ($source === 'tournament_win') {
            $data['users'][$username]['stats']['tournamentWins']++;
        } elseif ($source === 'tournament_podium') {
            $data['users'][$username]['stats']['tournamentPodiums']++;
        }
        
        $data['users'][$username]['lastActivity'] = date('c');
        writeUsers($data);
        
        $response = [
            'success' => true,
            'xpAdded' => $amount,
            'totalXP' => $newXP,
            'level' => $newLevel,
            'leveledUp' => $newLevel > $oldLevel
        ];
        
        if ($newLevel > $oldLevel) {
            $response['newLevel'] = $newLevel;
            $response['newTitle'] = getTitle($newLevel);
        }
        
        echo json_encode($response);
        exit();
    }
    
    if ($action === 'update-stats') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit();
        }
        
        $data = readUsers();
        $username = $_SESSION['user'];
        
        if (isset($input['timePlayed'])) {
            $data['users'][$username]['stats']['totalTimePlayed'] += intval($input['timePlayed']);
        }
        
        $data['users'][$username]['lastActivity'] = date('c');
        writeUsers($data);
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    if ($action === 'import-progress') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            exit();
        }
        
        $data = readUsers();
        $username = $_SESSION['user'];
        
        // Import stats from localStorage data sent by client
        if (isset($input['stats'])) {
            $stats = $input['stats'];
            $data['users'][$username]['stats']['totalGamesPlayed'] = max(
                $data['users'][$username]['stats']['totalGamesPlayed'],
                intval($stats['totalGamesPlayed'] ?? 0)
            );
            $data['users'][$username]['stats']['totalTimePlayed'] = max(
                $data['users'][$username]['stats']['totalTimePlayed'],
                intval($stats['totalTimePlayed'] ?? 0)
            );
            
            // Calculate XP from imported stats
            $importedXP = ($stats['totalGamesPlayed'] ?? 0) * 10 + 
                          floor(($stats['totalTimePlayed'] ?? 0) / 60);
            $data['users'][$username]['xp'] = max($data['users'][$username]['xp'], $importedXP);
        }
        
        writeUsers($data);
        echo json_encode(['success' => true, 'message' => 'Progress imported']);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
