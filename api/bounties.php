<?php
session_start();
header('Content-Type: application/json');

$BOUNTY_FILE = __DIR__ . '/../data/bounties.json';
$USERS_FILE = __DIR__ . '/../data/users.json';

function loadBounties() {
    global $BOUNTY_FILE;
    if (!file_exists($BOUNTY_FILE)) return [];
    return json_decode(file_get_contents($BOUNTY_FILE), true) ?: [];
}

function saveBounties($bounties) {
    global $BOUNTY_FILE;
    file_put_contents($BOUNTY_FILE, json_encode($bounties, JSON_PRETTY_PRINT));
}

function loadUsers() {
    global $USERS_FILE;
    if (!file_exists($USERS_FILE)) return ['users' => []];
    $data = json_decode(file_get_contents($USERS_FILE), true);
    return $data ?: ['users' => []];
}

function saveUsers($data) {
    global $USERS_FILE;
    file_put_contents($USERS_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

function getUserCoins($username) {
    $data = loadUsers();
    $userLower = strtolower($username);
    foreach ($data['users'] as $uname => $u) {
        if (strtolower($uname) === $userLower) {
            return $u['coins'] ?? 0;
        }
    }
    return 0;
}

function updateUserCoins($username, $amount) {
    $data = loadUsers();
    $userLower = strtolower($username);
    foreach ($data['users'] as $uname => &$u) {
        if (strtolower($uname) === $userLower) {
            $u['coins'] = ($u['coins'] ?? 0) + $amount;
            saveUsers($data);
            return true;
        }
    }
    return false;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Public actions (no login required)
if ($action === 'list') {
    $game = $_GET['game'] ?? '';
    $bounties = loadBounties();
    
    // Filter active bounties
    $active = array_filter($bounties, function($b) use ($game) {
        if ($b['status'] !== 'active') return false;
        if ($game && $b['game'] !== $game) return false;
        return true;
    });
    
    // Sort by reward descending
    usort($active, function($a, $b) {
        return $b['reward'] - $a['reward'];
    });
    
    echo json_encode(['success' => true, 'bounties' => array_values($active)]);
    exit;
}

// Protected actions
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$username = $_SESSION['user'];

switch ($action) {
    case 'create':
        $game = $_POST['game'] ?? '';
        $targetScore = intval($_POST['targetScore'] ?? 0);
        $reward = intval($_POST['reward'] ?? 0);
        
        if (empty($game) || $targetScore <= 0 || $reward < 100) {
            echo json_encode(['success' => false, 'error' => 'Invalid bounty: need game, score > 0, reward >= 100']);
            exit;
        }
        
        // Check user has enough coins
        $userCoins = getUserCoins($username);
        if ($userCoins < $reward) {
            echo json_encode(['success' => false, 'error' => 'Not enough coins']);
            exit;
        }
        
        // Deduct coins
        updateUserCoins($username, -$reward);
        
        // Create bounty
        $bounties = loadBounties();
        $bountyId = 'bounty_' . time() . '_' . rand(1000, 9999);
        $bounties[$bountyId] = [
            'id' => $bountyId,
            'creator' => $username,
            'game' => $game,
            'targetScore' => $targetScore,
            'reward' => $reward,
            'status' => 'active',
            'createdAt' => date('Y-m-d H:i:s'),
            'claimedBy' => null,
            'claimedAt' => null
        ];
        saveBounties($bounties);
        
        echo json_encode(['success' => true, 'bounty' => $bounties[$bountyId], 'message' => 'Bounty created!']);
        break;
        
    case 'claim':
        $bountyId = $_POST['bountyId'] ?? '';
        $score = intval($_POST['score'] ?? 0);
        
        $bounties = loadBounties();
        
        if (!isset($bounties[$bountyId])) {
            echo json_encode(['success' => false, 'error' => 'Bounty not found']);
            exit;
        }
        
        $bounty = $bounties[$bountyId];
        
        if ($bounty['status'] !== 'active') {
            echo json_encode(['success' => false, 'error' => 'Bounty no longer active']);
            exit;
        }
        
        if ($score < $bounty['targetScore']) {
            echo json_encode(['success' => false, 'error' => 'Score not high enough']);
            exit;
        }
        
        // Claim the bounty!
        $bounties[$bountyId]['status'] = 'claimed';
        $bounties[$bountyId]['claimedBy'] = $username;
        $bounties[$bountyId]['claimedAt'] = date('Y-m-d H:i:s');
        $bounties[$bountyId]['claimedScore'] = $score;
        saveBounties($bounties);
        
        // Award coins
        updateUserCoins($username, $bounty['reward']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Bounty claimed! You won ' . number_format($bounty['reward']) . ' coins!',
            'reward' => $bounty['reward']
        ]);
        break;
        
    case 'cancel':
        $bountyId = $_POST['bountyId'] ?? '';
        
        $bounties = loadBounties();
        
        if (!isset($bounties[$bountyId])) {
            echo json_encode(['success' => false, 'error' => 'Bounty not found']);
            exit;
        }
        
        $bounty = $bounties[$bountyId];
        
        if (strtolower($bounty['creator']) !== strtolower($username)) {
            echo json_encode(['success' => false, 'error' => 'Not your bounty']);
            exit;
        }
        
        if ($bounty['status'] !== 'active') {
            echo json_encode(['success' => false, 'error' => 'Bounty not active']);
            exit;
        }
        
        // Cancel and refund 90%
        $refund = intval($bounty['reward'] * 0.9);
        $bounties[$bountyId]['status'] = 'cancelled';
        saveBounties($bounties);
        
        updateUserCoins($username, $refund);
        
        echo json_encode([
            'success' => true,
            'message' => 'Bounty cancelled. Refunded ' . number_format($refund) . ' coins (10% fee)',
            'refund' => $refund
        ]);
        break;
        
    case 'my':
        $bounties = loadBounties();
        $myBounties = array_filter($bounties, function($b) use ($username) {
            return strtolower($b['creator']) === strtolower($username);
        });
        echo json_encode(['success' => true, 'bounties' => array_values($myBounties)]);
        break;
        
    case 'check':
        // Check if a score beats any active bounties for a game
        $game = $_POST['game'] ?? '';
        $score = intval($_POST['score'] ?? 0);
        
        $bounties = loadBounties();
        $claimable = [];
        
        foreach ($bounties as $b) {
            if ($b['status'] === 'active' && $b['game'] === $game && $score >= $b['targetScore']) {
                $claimable[] = $b;
            }
        }
        
        echo json_encode(['success' => true, 'claimable' => $claimable]);
        break;
        
    case "owner-delete":
        // Owner can delete any bounty
        if (!isset($_SESSION["isAdmin"]) || $_SESSION["isAdmin"] !== true) {
            echo json_encode(["success" => false, "error" => "Owner access required"]);
            exit;
        }
        
        $bountyId = $_POST["bountyId"] ?? "";
        $bounties = loadBounties();
        
        if (!isset($bounties[$bountyId])) {
            echo json_encode(["success" => false, "error" => "Bounty not found"]);
            exit;
        }
        
        // Delete the bounty (no refund for ridiculous bounties)
        unset($bounties[$bountyId]);
        saveBounties($bounties);
        
        echo json_encode(["success" => true, "message" => "Bounty deleted by owner"]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
