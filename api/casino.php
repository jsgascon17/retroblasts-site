<?php
session_start();
header('Content-Type: application/json');

$DATA_DIR = __DIR__ . '/../data';
$USERS_FILE = $DATA_DIR . '/users.json';

// Helper functions
function loadUsers() {
    global $USERS_FILE;
    if (!file_exists($USERS_FILE)) return ['users' => []];
    return json_decode(file_get_contents($USERS_FILE), true) ?: ['users' => []];
}

function saveUsers($data) {
    global $USERS_FILE;
    file_put_contents($USERS_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

function getCurrentUser(&$data) {
    if (!isset($_SESSION['user'])) return null;
    $username = $_SESSION['user'];
    if (isset($data['users'][$username])) {
        return $data['users'][$username];
    }
    return null;
}

function updateUserCoins(&$data, $username, $newCoins) {
    if (isset($data['users'][$username])) {
        $data['users'][$username]['coins'] = $newCoins;
        return true;
    }
    return false;
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $data = loadUsers();
    $currentUser = getCurrentUser($data);
    
    if (!$currentUser) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    
    $username = $_SESSION['user'];
    $coins = $currentUser['coins'] ?? 0;
    
    switch ($action) {
        case 'coinflip':
            $bet = intval($input['bet'] ?? 0);
            $choice = $input['choice'] ?? 'heads';
            
            if ($bet < 10) {
                echo json_encode(['error' => 'Minimum bet is 10 coins']);
                exit;
            }
            if ($bet > $coins) {
                echo json_encode(['error' => 'Not enough coins']);
                exit;
            }
            if ($bet > 10000) {
                echo json_encode(['error' => 'Maximum bet is 10,000 coins']);
                exit;
            }
            
            $result = rand(0, 1) === 0 ? 'heads' : 'tails';
            $won = ($result === $choice);
            
            if ($won) {
                $winnings = $bet;
                $newCoins = $coins + $winnings;
            } else {
                $winnings = -$bet;
                $newCoins = $coins - $bet;
            }
            
            updateUserCoins($data, $username, $newCoins);
            saveUsers($data);
            
            echo json_encode([
                'success' => true,
                'result' => $result,
                'won' => $won,
                'winnings' => $winnings,
                'coins' => $newCoins
            ]);
            exit;
            
        case 'slots':
            $bet = intval($input['bet'] ?? 0);
            
            if ($bet < 10) {
                echo json_encode(['error' => 'Minimum bet is 10 coins']);
                exit;
            }
            if ($bet > $coins) {
                echo json_encode(['error' => 'Not enough coins']);
                exit;
            }
            if ($bet > 10000) {
                echo json_encode(['error' => 'Maximum bet is 10,000 coins']);
                exit;
            }
            
            $symbols = ['🍒', '🍋', '🍊', '🍇', '⭐', '💎', '7️⃣'];
            $weights = [25, 25, 20, 15, 10, 4, 1]; // Weighted probabilities
            
            function spinReel($symbols, $weights) {
                $total = array_sum($weights);
                $rand = rand(1, $total);
                $cumulative = 0;
                for ($i = 0; $i < count($symbols); $i++) {
                    $cumulative += $weights[$i];
                    if ($rand <= $cumulative) return $symbols[$i];
                }
                return $symbols[0];
            }
            
            $reel1 = spinReel($symbols, $weights);
            $reel2 = spinReel($symbols, $weights);
            $reel3 = spinReel($symbols, $weights);
            
            $multiplier = 0;
            if ($reel1 === $reel2 && $reel2 === $reel3) {
                // Three of a kind
                if ($reel1 === '7️⃣') $multiplier = 100;
                else if ($reel1 === '💎') $multiplier = 50;
                else if ($reel1 === '⭐') $multiplier = 15;
                else if ($reel1 === '🍇') $multiplier = 10;
                else if ($reel1 === '🍊') $multiplier = 8;
                else if ($reel1 === '🍋') $multiplier = 5;
                else $multiplier = 3;
            } else if ($reel1 === $reel2 || $reel2 === $reel3 || $reel1 === $reel3) {
                $multiplier = 1.5;
            }
            
            $winnings = floor($bet * $multiplier) - $bet;
            $newCoins = $coins + $winnings;
            
            updateUserCoins($data, $username, $newCoins);
            saveUsers($data);
            
            echo json_encode([
                'success' => true,
                'reels' => [$reel1, $reel2, $reel3],
                'multiplier' => $multiplier,
                'won' => $multiplier > 0,
                'winnings' => $winnings,
                'coins' => $newCoins
            ]);
            exit;
            
        case 'dice':
            $bet = intval($input['bet'] ?? 0);
            $target = intval($input['target'] ?? 50);
            $direction = $input['direction'] ?? 'over';
            
            if ($bet < 10) {
                echo json_encode(['error' => 'Minimum bet is 10 coins']);
                exit;
            }
            if ($bet > $coins) {
                echo json_encode(['error' => 'Not enough coins']);
                exit;
            }
            if ($bet > 10000) {
                echo json_encode(['error' => 'Maximum bet is 10,000 coins']);
                exit;
            }
            
            $roll = rand(1, 100);
            
            if ($direction === 'over') {
                $won = $roll > $target;
                $winChance = 100 - $target;
            } else {
                $won = $roll < $target;
                $winChance = $target - 1;
            }
            
            $multiplier = $winChance > 0 ? round(95 / $winChance, 2) : 0;
            
            if ($won) {
                $winnings = floor($bet * $multiplier) - $bet;
                $newCoins = $coins + $winnings;
            } else {
                $winnings = -$bet;
                $newCoins = $coins - $bet;
            }
            
            updateUserCoins($data, $username, $newCoins);
            saveUsers($data);
            
            echo json_encode([
                'success' => true,
                'roll' => $roll,
                'target' => $target,
                'direction' => $direction,
                'won' => $won,
                'multiplier' => $multiplier,
                'winnings' => $winnings,
                'coins' => $newCoins
            ]);
            exit;
            
        case 'blackjack':
            $subaction = $input['subaction'] ?? 'deal';
            $bet = intval($input['bet'] ?? 0);
            
            if ($subaction === 'deal') {
                if ($bet < 10) {
                    echo json_encode(['error' => 'Minimum bet is 10 coins']);
                    exit;
                }
                if ($bet > $coins) {
                    echo json_encode(['error' => 'Not enough coins']);
                    exit;
                }
                
                $deck = [];
                $suits = ['♠', '♥', '♦', '♣'];
                $values = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
                foreach ($suits as $suit) {
                    foreach ($values as $value) {
                        $deck[] = $value . $suit;
                    }
                }
                shuffle($deck);
                
                $playerHand = [array_pop($deck), array_pop($deck)];
                $dealerHand = [array_pop($deck), array_pop($deck)];
                
                $_SESSION['blackjack'] = [
                    'deck' => $deck,
                    'playerHand' => $playerHand,
                    'dealerHand' => $dealerHand,
                    'bet' => $bet,
                    'status' => 'playing'
                ];
                
                $newCoins = $coins - $bet;
                updateUserCoins($data, $username, $newCoins);
                saveUsers($data);
                
                echo json_encode([
                    'success' => true,
                    'playerHand' => $playerHand,
                    'dealerHand' => [$dealerHand[0], '?'],
                    'playerTotal' => calculateHand($playerHand),
                    'coins' => $newCoins
                ]);
                exit;
            }
            
            if (!isset($_SESSION['blackjack'])) {
                echo json_encode(['error' => 'No active game']);
                exit;
            }
            
            $game = &$_SESSION['blackjack'];
            
            if ($subaction === 'hit') {
                $game['playerHand'][] = array_pop($game['deck']);
                $playerTotal = calculateHand($game['playerHand']);
                
                if ($playerTotal > 21) {
                    unset($_SESSION['blackjack']);
                    echo json_encode([
                        'success' => true,
                        'playerHand' => $game['playerHand'],
                        'dealerHand' => $game['dealerHand'],
                        'playerTotal' => $playerTotal,
                        'dealerTotal' => calculateHand($game['dealerHand']),
                        'result' => 'bust',
                        'winnings' => -$game['bet'],
                        'coins' => $coins
                    ]);
                    exit;
                }
                
                echo json_encode([
                    'success' => true,
                    'playerHand' => $game['playerHand'],
                    'dealerHand' => [$game['dealerHand'][0], '?'],
                    'playerTotal' => $playerTotal,
                    'coins' => $coins
                ]);
                exit;
            }
            
            if ($subaction === 'stand') {
                $dealerHand = $game['dealerHand'];
                $deck = $game['deck'];
                
                while (calculateHand($dealerHand) < 17) {
                    $dealerHand[] = array_pop($deck);
                }
                
                $playerTotal = calculateHand($game['playerHand']);
                $dealerTotal = calculateHand($dealerHand);
                
                if ($dealerTotal > 21 || $playerTotal > $dealerTotal) {
                    $result = 'win';
                    $winnings = $game['bet'];
                    $newCoins = $coins + $game['bet'] * 2;
                } else if ($playerTotal < $dealerTotal) {
                    $result = 'lose';
                    $winnings = -$game['bet'];
                    $newCoins = $coins;
                } else {
                    $result = 'push';
                    $winnings = 0;
                    $newCoins = $coins + $game['bet'];
                }
                
                updateUserCoins($data, $username, $newCoins);
                saveUsers($data);
                unset($_SESSION['blackjack']);
                
                echo json_encode([
                    'success' => true,
                    'playerHand' => $game['playerHand'],
                    'dealerHand' => $dealerHand,
                    'playerTotal' => $playerTotal,
                    'dealerTotal' => $dealerTotal,
                    'result' => $result,
                    'winnings' => $winnings,
                    'coins' => $newCoins
                ]);
                exit;
            }
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
            exit;
    }
}

function calculateHand($hand) {
    $total = 0;
    $aces = 0;
    
    foreach ($hand as $card) {
        $value = substr($card, 0, -1);
        if ($value === 'A') {
            $aces++;
            $total += 11;
        } else if (in_array($value, ['K', 'Q', 'J'])) {
            $total += 10;
        } else {
            $total += intval($value);
        }
    }
    
    while ($total > 21 && $aces > 0) {
        $total -= 10;
        $aces--;
    }
    
    return $total;
}

echo json_encode(['error' => 'Invalid request']);
