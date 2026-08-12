<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/_store.php';

$PREDICTIONS_FILE = __DIR__ . '/../data/predictions.json';
$USERS_FILE = __DIR__ . '/../data/users.json';
$OWNERS = ['billybuffalo15'];

function loadPredictions() {
    global $PREDICTIONS_FILE;
    if (!file_exists($PREDICTIONS_FILE)) return [];
    return json_decode(file_get_contents($PREDICTIONS_FILE), true) ?: [];
}

function savePredictions($predictions) {
    global $PREDICTIONS_FILE;
    store_write($PREDICTIONS_FILE, $predictions);
}

function loadUsers() {
    global $USERS_FILE;
    if (!file_exists($USERS_FILE)) return ['users' => []];
    $data = json_decode(file_get_contents($USERS_FILE), true);
    return $data ?: ['users' => []];
}

function saveUsers($data) {
    global $USERS_FILE;
    store_write($USERS_FILE, $data);
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

function isOwner($username) {
    global $OWNERS;
    return in_array(strtolower($username), array_map('strtolower', $OWNERS));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Public actions
if ($action === 'list') {
    $predictions = loadPredictions();
    $status = $_GET['status'] ?? 'active';
    
    $filtered = array_filter($predictions, fn($p) => $p['status'] === $status);
    usort($filtered, fn($a, $b) => $b['createdAt'] <=> $a['createdAt']);
    
    echo json_encode(['success' => true, 'predictions' => array_values($filtered)]);
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
        if (!isOwner($username)) {
            echo json_encode(['success' => false, 'error' => 'Only owners can create predictions']);
            exit;
        }
        
        $question = trim($_POST['question'] ?? '');
        $category = trim($_POST['category'] ?? 'general');
        $endsAt = intval($_POST['endsAt'] ?? 0);
        
        if (empty($question)) {
            echo json_encode(['success' => false, 'error' => 'Question required']);
            exit;
        }
        
        if ($endsAt <= time()) {
            $endsAt = time() + 86400; // Default 24 hours
        }
        
        $predictions = loadPredictions();
        $predId = 'pred_' . time() . '_' . rand(1000, 9999);
        
        $predictions[$predId] = [
            'id' => $predId,
            'question' => $question,
            'category' => $category,
            'status' => 'active',
            'createdBy' => $username,
            'createdAt' => time(),
            'endsAt' => $endsAt,
            'yesPool' => 0,
            'noPool' => 0,
            'yesBets' => [],
            'noBets' => [],
            'result' => null,
            'resolvedAt' => null
        ];
        
        savePredictions($predictions);
        echo json_encode(['success' => true, 'prediction' => $predictions[$predId], 'message' => 'Prediction created!']);
        break;
        
    case 'bet':
        $predId = $_POST['predictionId'] ?? '';
        $side = strtolower($_POST['side'] ?? '');
        $amount = intval($_POST['amount'] ?? 0);
        
        if (!$predId || !in_array($side, ['yes', 'no']) || $amount < 10) {
            echo json_encode(['success' => false, 'error' => 'Invalid bet (min 10 coins)']);
            exit;
        }
        
        $predictions = loadPredictions();
        
        if (!isset($predictions[$predId])) {
            echo json_encode(['success' => false, 'error' => 'Prediction not found']);
            exit;
        }
        
        $pred = &$predictions[$predId];
        
        if ($pred['status'] !== 'active') {
            echo json_encode(['success' => false, 'error' => 'Prediction is closed']);
            exit;
        }
        
        if ($pred['endsAt'] <= time()) {
            echo json_encode(['success' => false, 'error' => 'Betting has ended']);
            exit;
        }
        
        $userCoins = getUserCoins($username);
        if ($userCoins < $amount) {
            echo json_encode(['success' => false, 'error' => 'Not enough coins']);
            exit;
        }
        
        // Deduct coins
        updateUserCoins($username, -$amount);
        
        // Add bet
        $userLower = strtolower($username);
        if ($side === 'yes') {
            $pred['yesPool'] += $amount;
            if (!isset($pred['yesBets'][$userLower])) {
                $pred['yesBets'][$userLower] = 0;
            }
            $pred['yesBets'][$userLower] += $amount;
        } else {
            $pred['noPool'] += $amount;
            if (!isset($pred['noBets'][$userLower])) {
                $pred['noBets'][$userLower] = 0;
            }
            $pred['noBets'][$userLower] += $amount;
        }
        
        savePredictions($predictions);
        
        echo json_encode([
            'success' => true,
            'message' => 'Bet placed! ' . $amount . ' coins on ' . strtoupper($side),
            'yesPool' => $pred['yesPool'],
            'noPool' => $pred['noPool']
        ]);
        break;
        
    case 'resolve':
        if (!isOwner($username)) {
            echo json_encode(['success' => false, 'error' => 'Only owners can resolve predictions']);
            exit;
        }
        
        $predId = $_POST['predictionId'] ?? '';
        $result = strtolower($_POST['result'] ?? '');
        
        if (!in_array($result, ['yes', 'no', 'cancel'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid result']);
            exit;
        }
        
        $predictions = loadPredictions();
        
        if (!isset($predictions[$predId])) {
            echo json_encode(['success' => false, 'error' => 'Prediction not found']);
            exit;
        }
        
        $pred = &$predictions[$predId];
        
        if ($pred['status'] !== 'active') {
            echo json_encode(['success' => false, 'error' => 'Already resolved']);
            exit;
        }
        
        $pred['status'] = 'resolved';
        $pred['result'] = $result;
        $pred['resolvedAt'] = time();
        
        $totalPool = $pred['yesPool'] + $pred['noPool'];
        $payouts = [];
        
        if ($result === 'cancel') {
            // Refund everyone
            foreach ($pred['yesBets'] as $user => $amt) {
                updateUserCoins($user, $amt);
                $payouts[$user] = $amt;
            }
            foreach ($pred['noBets'] as $user => $amt) {
                updateUserCoins($user, $amt);
                $payouts[$user] = ($payouts[$user] ?? 0) + $amt;
            }
        } else {
            // Pay winners proportionally
            $winningBets = $result === 'yes' ? $pred['yesBets'] : $pred['noBets'];
            $winningPool = $result === 'yes' ? $pred['yesPool'] : $pred['noPool'];
            
            if ($winningPool > 0) {
                foreach ($winningBets as $user => $amt) {
                    $share = $amt / $winningPool;
                    $payout = intval($totalPool * $share);
                    updateUserCoins($user, $payout);
                    $payouts[$user] = $payout;
                }
            }
        }
        
        $pred['payouts'] = $payouts;
        savePredictions($predictions);
        
        echo json_encode([
            'success' => true,
            'message' => 'Prediction resolved as ' . strtoupper($result),
            'payouts' => $payouts
        ]);
        break;
        
    case 'myBets':
        $predictions = loadPredictions();
        $userLower = strtolower($username);
        $myBets = [];
        
        foreach ($predictions as $pred) {
            $yesBet = $pred['yesBets'][$userLower] ?? 0;
            $noBet = $pred['noBets'][$userLower] ?? 0;
            
            if ($yesBet > 0 || $noBet > 0) {
                $myBets[] = [
                    'prediction' => $pred,
                    'yesBet' => $yesBet,
                    'noBet' => $noBet,
                    'payout' => $pred['payouts'][$userLower] ?? null
                ];
            }
        }
        
        echo json_encode(['success' => true, 'bets' => $myBets]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
