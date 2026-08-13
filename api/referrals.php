<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/_store.php';

$usersFile = __DIR__ . '/../data/users.json';
$referralsFile = __DIR__ . '/../data/referrals.json';

function loadJson($file) {
    if (!file_exists($file)) return [];
    return store_hold_read($file, []);
}

function saveJson($file, $data) {
    store_hold_write($file, $data);
}

function generateCode($username) {
    // Create a short, unique code based on username
    $hash = strtoupper(substr(md5($username . 'retroblasts'), 0, 6));
    return $hash;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Actions that require login
$requiresLogin = ['getMyCode', 'getStats'];

if (in_array($action, $requiresLogin) && !isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$username = $_SESSION['user'] ?? '';

switch ($action) {
    case 'getMyCode':
        $users = loadJson($usersFile);
        $user = &$users['users'][$username];
        
        // Generate code if user doesn't have one
        if (!isset($user['referralCode'])) {
            $user['referralCode'] = generateCode($username);
            saveJson($usersFile, $users);
        }
        
        $code = $user['referralCode'];
        $link = 'https://retroblasts.com/register.html?ref=' . $code;
        
        echo json_encode([
            'success' => true,
            'code' => $code,
            'link' => $link
        ]);
        break;
        
    case 'getStats':
        $referrals = loadJson($referralsFile);
        $userReferrals = $referrals[$username] ?? ['referred' => [], 'totalRewards' => 0];
        
        echo json_encode([
            'success' => true,
            'totalReferred' => count($userReferrals['referred']),
            'referred' => $userReferrals['referred'],
            'totalCoinsEarned' => $userReferrals['totalRewards']
        ]);
        break;
        
    case 'useCode':
        // Called during registration - new user uses a referral code
        $input = json_decode(file_get_contents('php://input'), true);
        $code = strtoupper(trim($input['code'] ?? ''));
        $newUsername = $input['newUsername'] ?? '';
        
        if (empty($code) || empty($newUsername)) {
            echo json_encode(['success' => false, 'error' => 'Missing code or username']);
            exit;
        }
        
        $users = loadJson($usersFile);
        $referrals = loadJson($referralsFile);
        
        // Find who owns this referral code
        $referrer = null;
        foreach ($users['users'] as $uname => &$udata) {
            if (isset($udata['referralCode']) && $udata['referralCode'] === $code) {
                $referrer = $uname;
                break;
            }
        }
        
        if (!$referrer) {
            echo json_encode(['success' => false, 'error' => 'Invalid referral code']);
            exit;
        }
        
        if ($referrer === $newUsername) {
            echo json_encode(['success' => false, 'error' => 'Cannot use your own code']);
            exit;
        }
        
        // Check if new user already used a code
        if (isset($users['users'][$newUsername]['referredBy'])) {
            echo json_encode(['success' => false, 'error' => 'Already used a referral code']);
            exit;
        }
        
        // Give rewards to referrer
        $users['users'][$referrer]['coins'] = ($users['users'][$referrer]['coins'] ?? 0) + 500;
        if (!isset($users['users'][$referrer]['inventory']['lootboxes'])) {
            $users['users'][$referrer]['inventory']['lootboxes'] = [];
        }
        $users['users'][$referrer]['inventory']['lootboxes'][] = [
            'type' => 'rare',
            'obtained' => date('c'),
            'source' => 'referral'
        ];
        
        // Give rewards to new user
        $users['users'][$newUsername]['coins'] = ($users['users'][$newUsername]['coins'] ?? 0) + 250;
        if (!isset($users['users'][$newUsername]['inventory']['lootboxes'])) {
            $users['users'][$newUsername]['inventory']['lootboxes'] = [];
        }
        $users['users'][$newUsername]['inventory']['lootboxes'][] = [
            'type' => 'common',
            'obtained' => date('c'),
            'source' => 'referral_bonus'
        ];
        $users['users'][$newUsername]['referredBy'] = $referrer;
        
        // Track the referral
        if (!isset($referrals[$referrer])) {
            $referrals[$referrer] = ['referred' => [], 'totalRewards' => 0];
        }
        $referrals[$referrer]['referred'][] = [
            'username' => $newUsername,
            'date' => date('c')
        ];
        $referrals[$referrer]['totalRewards'] += 500;
        
        saveJson($usersFile, $users);
        saveJson($referralsFile, $referrals);
        
        echo json_encode([
            'success' => true,
            'message' => 'Referral code applied! You got 250 coins and a lootbox!'
        ]);
        break;
        
    case 'validateCode':
        // Check if a code is valid (for showing feedback on signup form)
        $code = strtoupper(trim($_GET['code'] ?? ''));
        
        if (empty($code)) {
            echo json_encode(['success' => false, 'valid' => false]);
            exit;
        }
        
        $users = loadJson($usersFile);
        
        foreach ($users['users'] as $uname => $udata) {
            if (isset($udata['referralCode']) && $udata['referralCode'] === $code) {
                echo json_encode([
                    'success' => true,
                    'valid' => true,
                    'referrer' => $uname
                ]);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'valid' => false]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
