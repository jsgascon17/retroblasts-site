<?php
/**
 * Game Ratings API
 * 
 * GET:
 *   ?action=get&game=snake     - Get ratings for a game
 *   ?action=all                - Get all game ratings
 * 
 * POST:
 *   { "action": "rate", "game": "snake", "rating": 5 }
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

$ratingsFile = __DIR__ . '/../data/ratings.json';

function readRatings() {
    global $ratingsFile;
    if (!file_exists($ratingsFile)) return ['games' => []];
    return json_decode(file_get_contents($ratingsFile), true) ?: ['games' => []];
}

function writeRatings($data) {
    global $ratingsFile;
    file_put_contents($ratingsFile, json_encode($data, JSON_PRETTY_PRINT));
}

// Handle GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'all';
    $data = readRatings();
    
    if ($action === 'get') {
        $game = $_GET['game'] ?? '';
        if (!$game) {
            echo json_encode(['success' => false, 'error' => 'Game not specified']);
            exit();
        }
        
        $gameData = $data['games'][$game] ?? ['ratings' => [], 'average' => 0, 'count' => 0];
        
        // Check if current user has rated
        $userRating = null;
        if (isset($_SESSION['user'])) {
            $userRating = $gameData['ratings'][$_SESSION['user']] ?? null;
        }
        
        echo json_encode([
            'success' => true,
            'game' => $game,
            'average' => round($gameData['average'], 1),
            'count' => $gameData['count'],
            'userRating' => $userRating
        ]);
        exit();
    }
    
    if ($action === 'all') {
        $result = [];
        foreach ($data['games'] as $game => $info) {
            $result[$game] = [
                'average' => round($info['average'], 1),
                'count' => $info['count']
            ];
        }
        echo json_encode(['success' => true, 'ratings' => $result]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'rate') {
        if (!isset($_SESSION['user'])) {
            echo json_encode(['success' => false, 'error' => 'Must be logged in to rate']);
            exit();
        }
        
        $game = $input['game'] ?? '';
        $rating = intval($input['rating'] ?? 0);
        
        if (!$game) {
            echo json_encode(['success' => false, 'error' => 'Game not specified']);
            exit();
        }
        
        if ($rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'error' => 'Rating must be 1-5']);
            exit();
        }
        
        $data = readRatings();
        $username = $_SESSION['user'];
        
        if (!isset($data['games'][$game])) {
            $data['games'][$game] = ['ratings' => [], 'average' => 0, 'count' => 0];
        }
        
        // Add or update user's rating
        $data['games'][$game]['ratings'][$username] = $rating;
        
        // Recalculate average
        $ratings = array_values($data['games'][$game]['ratings']);
        $data['games'][$game]['average'] = array_sum($ratings) / count($ratings);
        $data['games'][$game]['count'] = count($ratings);
        
        writeRatings($data);
        
        echo json_encode([
            'success' => true,
            'message' => 'Rating saved!',
            'average' => round($data['games'][$game]['average'], 1),
            'count' => $data['games'][$game]['count']
        ]);
        exit();
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
