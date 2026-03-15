<?php
session_start();
header('Content-Type: application/json');

$DATA_DIR = __DIR__ . '/../data';
$USERS_FILE = $DATA_DIR . '/users.json';

// Helper functions
function loadUsers() {
    global $USERS_FILE;
    if (!file_exists($USERS_FILE)) return [];
    return json_decode(file_get_contents($USERS_FILE), true) ?: [];
}

function saveUsers($users) {
    global $USERS_FILE;
    file_put_contents($USERS_FILE, json_encode($users, JSON_PRETTY_PRINT));
}

function getCurrentUser(&$users) {
    if (!isset($_SESSION['user'])) return null;
    $username = $_SESSION['user'];
    foreach ($users as &$user) {
        if ($user['username'] === $username) return $user;
    }
    return null;
}

function updateUser(&$users, $username, $updates) {
    foreach ($users as &$user) {
        if ($user['username'] === $username) {
            foreach ($updates as $key => $value) {
                $user[$key] = $value;
            }
            return true;
        }
    }
    return false;
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    // No GET actions currently
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $users = loadUsers();
    $currentUser = getCurrentUser($users);
    
    if (!$currentUser) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    
    $coins = $currentUser['coins'] ?? 0;
    
    switch ($action) {
        case 'coinflip':
            $bet = intval($input['bet'] ?? 0);
            $choice = $input['choice'] ?? 'heads'; // heads or tails
            
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
            
            // 50/50 chance
            $result = rand(0, 1) === 0 ? 'heads' : 'tails';
            $won = ($result === $choice);
            
            if ($won) {
                $winnings = $bet; // 2x payout (get bet back + same amount)
                $newCoins = $coins + $winnings;
            } else {
                $winnings = -$bet;
                $newCoins = $coins - $bet;
            }
            
            updateUser($users, $currentUser['username'], ['coins' => $newCoins]);
            saveUsers($users);
            
            echo json_encode([
                'success' => true,
                'result' => $result,
                'won' => $won,
                'winnings' => $winnings,
                'coins' => $newCoins
            ]);
            break;
            
        case 'dice':
            $bet = intval($input['bet'] ?? 0);
            $target = $input['target'] ?? 'over'; // over, under, exact
            $number = intval($input['number'] ?? 50);
            
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
            $won = false;
            $multiplier = 1;
            
            if ($target === 'over') {
                $won = $roll > $number;
                // Payout based on probability
                $probability = (100 - $number) / 100;
                $multiplier = $probability > 0 ? round(0.95 / $probability, 2) : 1;
            } else if ($target === 'under') {
                $won = $roll < $number;
                $probability = ($number - 1) / 100;
                $multiplier = $probability > 0 ? round(0.95 / $probability, 2) : 1;
            } else if ($target === 'exact') {
                $won = $roll === $number;
                $multiplier = 95; // 95x for exact number
            }
            
            if ($won) {
                $winnings = floor($bet * $multiplier) - $bet;
                $newCoins = $coins + $winnings + $bet;
            } else {
                $winnings = -$bet;
                $newCoins = $coins - $bet;
            }
            
            updateUser($users, $currentUser['username'], ['coins' => $newCoins]);
            saveUsers($users);
            
            echo json_encode([
                'success' => true,
                'roll' => $roll,
                'won' => $won,
                'multiplier' => $multiplier,
                'winnings' => $winnings,
                'coins' => $newCoins
            ]);
            break;
            
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
            
            // Slot symbols with weights
            $symbols = ['🍒', '🍋', '🍊', '🍇', '⭐', '💎', '7️⃣'];
            $weights = [30, 25, 20, 15, 7, 2, 1]; // 7s and diamonds are rare
            
            function weightedRandom($symbols, $weights) {
                $total = array_sum($weights);
                $rand = rand(1, $total);
                $cumulative = 0;
                for ($i = 0; $i < count($weights); $i++) {
                    $cumulative += $weights[$i];
                    if ($rand <= $cumulative) {
                        return $symbols[$i];
                    }
                }
                return $symbols[0];
            }
            
            $reels = [
                weightedRandom($symbols, $weights),
                weightedRandom($symbols, $weights),
                weightedRandom($symbols, $weights)
            ];
            
            // Calculate winnings
            $multiplier = 0;
            if ($reels[0] === $reels[1] && $reels[1] === $reels[2]) {
                // Three of a kind
                switch ($reels[0]) {
                    case '🍒': $multiplier = 3; break;
                    case '🍋': $multiplier = 4; break;
                    case '🍊': $multiplier = 5; break;
                    case '🍇': $multiplier = 8; break;
                    case '⭐': $multiplier = 15; break;
                    case '💎': $multiplier = 50; break;
                    case '7️⃣': $multiplier = 100; break;
                }
            } else if ($reels[0] === $reels[1] || $reels[1] === $reels[2] || $reels[0] === $reels[2]) {
                // Two of a kind
                $multiplier = 1.5;
            }
            
            $won = $multiplier > 0;
            if ($won) {
                $winnings = floor($bet * $multiplier) - $bet;
                $newCoins = $coins + $winnings + $bet;
            } else {
                $winnings = -$bet;
                $newCoins = $coins - $bet;
            }
            
            updateUser($users, $currentUser['username'], ['coins' => $newCoins]);
            saveUsers($users);
            
            echo json_encode([
                'success' => true,
                'reels' => $reels,
                'won' => $won,
                'multiplier' => $multiplier,
                'winnings' => $winnings,
                'coins' => $newCoins
            ]);
            break;
            
        case 'blackjack-start':
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
            
            // Create deck
            $suits = ['♠', '♥', '♦', '♣'];
            $values = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
            $deck = [];
            foreach ($suits as $suit) {
                foreach ($values as $value) {
                    $deck[] = $value . $suit;
                }
            }
            shuffle($deck);
            
            // Deal cards
            $playerHand = [array_pop($deck), array_pop($deck)];
            $dealerHand = [array_pop($deck), array_pop($deck)];
            
            // Store game state in session
            $_SESSION['blackjack'] = [
                'deck' => $deck,
                'playerHand' => $playerHand,
                'dealerHand' => $dealerHand,
                'bet' => $bet,
                'status' => 'playing'
            ];
            
            // Deduct bet
            $newCoins = $coins - $bet;
            updateUser($users, $currentUser['username'], ['coins' => $newCoins]);
            saveUsers($users);
            
            $playerValue = calculateHandValue($playerHand);
            $dealerShowing = [$dealerHand[0]];
            
            // Check for blackjack
            if ($playerValue === 21) {
                $_SESSION['blackjack']['status'] = 'blackjack';
                $winnings = floor($bet * 2.5);
                $newCoins = $coins + $winnings;
                updateUser($users, $currentUser['username'], ['coins' => $newCoins]);
                saveUsers($users);
                
                echo json_encode([
                    'success' => true,
                    'playerHand' => $playerHand,
                    'dealerHand' => $dealerHand,
                    'playerValue' => $playerValue,
                    'dealerValue' => calculateHandValue($dealerHand),
                    'status' => 'blackjack',
                    'message' => 'Blackjack! You win!',
                    'winnings' => $winnings,
                    'coins' => $newCoins
                ]);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'playerHand' => $playerHand,
                'dealerHand' => $dealerShowing,
                'playerValue' => $playerValue,
                'status' => 'playing',
                'coins' => $newCoins
            ]);
            break;
            
        case 'blackjack-hit':
            if (!isset($_SESSION['blackjack']) || $_SESSION['blackjack']['status'] !== 'playing') {
                echo json_encode(['error' => 'No active game']);
                exit;
            }
            
            $game = &$_SESSION['blackjack'];
            $game['playerHand'][] = array_pop($game['deck']);
            $playerValue = calculateHandValue($game['playerHand']);
            
            if ($playerValue > 21) {
                $game['status'] = 'bust';
                echo json_encode([
                    'success' => true,
                    'playerHand' => $game['playerHand'],
                    'dealerHand' => $game['dealerHand'],
                    'playerValue' => $playerValue,
                    'dealerValue' => calculateHandValue($game['dealerHand']),
                    'status' => 'bust',
                    'message' => 'Bust! You lose.',
                    'winnings' => 0,
                    'coins' => $coins
                ]);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'playerHand' => $game['playerHand'],
                'dealerHand' => [$game['dealerHand'][0]],
                'playerValue' => $playerValue,
                'status' => 'playing'
            ]);
            break;
            
        case 'blackjack-stand':
            if (!isset($_SESSION['blackjack']) || $_SESSION['blackjack']['status'] !== 'playing') {
                echo json_encode(['error' => 'No active game']);
                exit;
            }
            
            $game = &$_SESSION['blackjack'];
            $playerValue = calculateHandValue($game['playerHand']);
            
            // Dealer plays
            while (calculateHandValue($game['dealerHand']) < 17) {
                $game['dealerHand'][] = array_pop($game['deck']);
            }
            
            $dealerValue = calculateHandValue($game['dealerHand']);
            $bet = $game['bet'];
            
            // Determine winner
            $status = '';
            $message = '';
            $winnings = 0;
            
            if ($dealerValue > 21) {
                $status = 'dealer_bust';
                $message = 'Dealer busts! You win!';
                $winnings = $bet * 2;
            } else if ($playerValue > $dealerValue) {
                $status = 'win';
                $message = 'You win!';
                $winnings = $bet * 2;
            } else if ($playerValue < $dealerValue) {
                $status = 'lose';
                $message = 'Dealer wins.';
                $winnings = 0;
            } else {
                $status = 'push';
                $message = 'Push! Bet returned.';
                $winnings = $bet;
            }
            
            $newCoins = $coins + $winnings;
            updateUser($users, $currentUser['username'], ['coins' => $newCoins]);
            saveUsers($users);
            
            $game['status'] = $status;
            
            echo json_encode([
                'success' => true,
                'playerHand' => $game['playerHand'],
                'dealerHand' => $game['dealerHand'],
                'playerValue' => $playerValue,
                'dealerValue' => $dealerValue,
                'status' => $status,
                'message' => $message,
                'winnings' => $winnings - $bet,
                'coins' => $newCoins
            ]);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
}

function calculateHandValue($hand) {
    $value = 0;
    $aces = 0;
    
    foreach ($hand as $card) {
        $cardValue = substr($card, 0, -1); // Remove suit
        if ($cardValue === 'A') {
            $aces++;
            $value += 11;
        } else if (in_array($cardValue, ['K', 'Q', 'J'])) {
            $value += 10;
        } else if ($cardValue === '10') {
            $value += 10;
        } else {
            $value += intval($cardValue);
        }
    }
    
    // Adjust for aces
    while ($value > 21 && $aces > 0) {
        $value -= 10;
        $aces--;
    }
    
    return $value;
}
