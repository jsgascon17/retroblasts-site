<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/_store.php';

$STOCKS_FILE = __DIR__ . '/../data/stocks.json';
$PORTFOLIOS_FILE = __DIR__ . '/../data/portfolios.json';
$USERS_FILE = __DIR__ . '/../data/users.json';

// Fake companies
$COMPANIES = [
    'FLAP' => ['name' => 'Flappy Industries', 'sector' => 'Gaming'],
    'COIN' => ['name' => 'CoinStack Corp', 'sector' => 'Finance'],
    'RETRO' => ['name' => 'RetroBlasts Inc', 'sector' => 'Entertainment'],
    'PIXEL' => ['name' => 'Pixel Perfect Ltd', 'sector' => 'Technology'],
    'NEON' => ['name' => 'Neon Dreams', 'sector' => 'Entertainment'],
    'BOSS' => ['name' => 'Boss Battle Co', 'sector' => 'Gaming'],
    'LOOT' => ['name' => 'LootBox Enterprises', 'sector' => 'Gaming'],
    'MEGA' => ['name' => 'MegaCorp Global', 'sector' => 'Conglomerate'],
    'CAFE' => ['name' => 'Cyber Cafe Chain', 'sector' => 'Food'],
    'ROCK' => ['name' => 'Rocket Fuel Energy', 'sector' => 'Beverages']
];

function loadStocks() {
    global $STOCKS_FILE, $COMPANIES;
    if (!file_exists($STOCKS_FILE)) {
        // Initialize with starting prices
        $stocks = [];
        foreach ($COMPANIES as $symbol => $info) {
            $basePrice = rand(50, 500);
            $stocks[$symbol] = [
                'symbol' => $symbol,
                'name' => $info['name'],
                'sector' => $info['sector'],
                'price' => $basePrice,
                'previousPrice' => $basePrice,
                'history' => [$basePrice],
                'lastUpdate' => time()
            ];
        }
        saveStocks($stocks);
        return $stocks;
    }
    return json_decode(file_get_contents($STOCKS_FILE), true) ?: [];
}

function saveStocks($stocks) {
    global $STOCKS_FILE;
    store_write($STOCKS_FILE, $stocks);
}

function loadPortfolios() {
    global $PORTFOLIOS_FILE;
    if (!file_exists($PORTFOLIOS_FILE)) return [];
    return json_decode(file_get_contents($PORTFOLIOS_FILE), true) ?: [];
}

function savePortfolios($portfolios) {
    global $PORTFOLIOS_FILE;
    store_write($PORTFOLIOS_FILE, $portfolios);
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

function updatePrices() {
    $stocks = loadStocks();
    $now = time();
    
    // Update every 10 minutes
    $firstStock = reset($stocks);
    if ($firstStock && ($now - $firstStock['lastUpdate']) < 600) {
        return $stocks; // No update needed
    }
    
    foreach ($stocks as $symbol => &$stock) {
        $stock['previousPrice'] = $stock['price'];
        
        // Random price change: -15% to +15%
        $change = (rand(-150, 150) / 1000);
        $newPrice = round($stock['price'] * (1 + $change), 2);
        
        // Keep price between 10 and 10000
        $newPrice = max(10, min(10000, $newPrice));
        
        $stock['price'] = $newPrice;
        $stock['lastUpdate'] = $now;
        
        // Keep last 24 price points for chart
        $stock['history'][] = $newPrice;
        if (count($stock['history']) > 24) {
            array_shift($stock['history']);
        }
    }
    
    saveStocks($stocks);
    return $stocks;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Public actions
if ($action === 'prices') {
    $stocks = updatePrices();
    $output = [];
    foreach ($stocks as $symbol => $stock) {
        $change = $stock['price'] - $stock['previousPrice'];
        $changePercent = $stock['previousPrice'] > 0 ? round(($change / $stock['previousPrice']) * 100, 2) : 0;
        $output[$symbol] = [
            'symbol' => $symbol,
            'name' => $stock['name'],
            'sector' => $stock['sector'],
            'price' => $stock['price'],
            'change' => round($change, 2),
            'changePercent' => $changePercent,
            'history' => $stock['history']
        ];
    }
    echo json_encode(['success' => true, 'stocks' => $output, 'nextUpdate' => 600 - (time() - $stocks['FLAP']['lastUpdate'])]);
    exit;
}

// Protected actions
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$username = $_SESSION['user'];

switch ($action) {
    case 'portfolio':
        $portfolios = loadPortfolios();
        $userLower = strtolower($username);
        $portfolio = $portfolios[$userLower] ?? [];
        
        $stocks = loadStocks();
        $holdings = [];
        $totalValue = 0;
        
        foreach ($portfolio as $symbol => $data) {
            if ($data['shares'] > 0 && isset($stocks[$symbol])) {
                $currentValue = $data['shares'] * $stocks[$symbol]['price'];
                $costBasis = $data['shares'] * $data['avgPrice'];
                $profit = $currentValue - $costBasis;
                
                $holdings[$symbol] = [
                    'symbol' => $symbol,
                    'name' => $stocks[$symbol]['name'],
                    'shares' => $data['shares'],
                    'avgPrice' => round($data['avgPrice'], 2),
                    'currentPrice' => $stocks[$symbol]['price'],
                    'value' => round($currentValue, 2),
                    'profit' => round($profit, 2),
                    'profitPercent' => $costBasis > 0 ? round(($profit / $costBasis) * 100, 2) : 0
                ];
                $totalValue += $currentValue;
            }
        }
        
        echo json_encode([
            'success' => true,
            'holdings' => $holdings,
            'totalValue' => round($totalValue, 2),
            'coins' => getUserCoins($username)
        ]);
        break;
        
    case 'buy':
        $symbol = strtoupper($_POST['symbol'] ?? '');
        $shares = intval($_POST['shares'] ?? 0);
        
        $stocks = loadStocks();
        
        if (!isset($stocks[$symbol])) {
            echo json_encode(['success' => false, 'error' => 'Invalid stock']);
            exit;
        }
        
        if ($shares <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid share amount']);
            exit;
        }
        
        $totalCost = $stocks[$symbol]['price'] * $shares;
        $userCoins = getUserCoins($username);
        
        if ($userCoins < $totalCost) {
            echo json_encode(['success' => false, 'error' => 'Not enough coins. Need ' . number_format($totalCost)]);
            exit;
        }
        
        // Deduct coins
        updateUserCoins($username, -$totalCost);
        
        // Add to portfolio
        $portfolios = loadPortfolios();
        $userLower = strtolower($username);
        
        if (!isset($portfolios[$userLower])) {
            $portfolios[$userLower] = [];
        }
        
        if (!isset($portfolios[$userLower][$symbol])) {
            $portfolios[$userLower][$symbol] = ['shares' => 0, 'avgPrice' => 0, 'totalInvested' => 0];
        }
        
        $current = $portfolios[$userLower][$symbol];
        $newTotalShares = $current['shares'] + $shares;
        $newTotalInvested = $current['totalInvested'] + $totalCost;
        
        $portfolios[$userLower][$symbol] = [
            'shares' => $newTotalShares,
            'avgPrice' => $newTotalInvested / $newTotalShares,
            'totalInvested' => $newTotalInvested
        ];
        
        savePortfolios($portfolios);
        
        echo json_encode([
            'success' => true,
            'message' => "Bought $shares shares of $symbol for " . number_format($totalCost) . " coins"
        ]);
        break;
        
    case 'sell':
        $symbol = strtoupper($_POST['symbol'] ?? '');
        $shares = intval($_POST['shares'] ?? 0);
        
        $stocks = loadStocks();
        $portfolios = loadPortfolios();
        $userLower = strtolower($username);
        
        if (!isset($stocks[$symbol])) {
            echo json_encode(['success' => false, 'error' => 'Invalid stock']);
            exit;
        }
        
        if (!isset($portfolios[$userLower][$symbol]) || $portfolios[$userLower][$symbol]['shares'] < $shares) {
            echo json_encode(['success' => false, 'error' => 'Not enough shares']);
            exit;
        }
        
        if ($shares <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid share amount']);
            exit;
        }
        
        $saleValue = $stocks[$symbol]['price'] * $shares;
        
        // Add coins
        updateUserCoins($username, $saleValue);
        
        // Update portfolio
        $portfolios[$userLower][$symbol]['shares'] -= $shares;
        if ($portfolios[$userLower][$symbol]['shares'] <= 0) {
            unset($portfolios[$userLower][$symbol]);
        } else {
            $portfolios[$userLower][$symbol]['totalInvested'] = 
                $portfolios[$userLower][$symbol]['avgPrice'] * $portfolios[$userLower][$symbol]['shares'];
        }
        
        savePortfolios($portfolios);
        
        echo json_encode([
            'success' => true,
            'message' => "Sold $shares shares of $symbol for " . number_format($saleValue) . " coins"
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
