<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function scrapePriceCharting($cardName, $setName = '') {
    // Build search query
    $searchTerm = urlencode($cardName . ' ' . $setName . ' pokemon');
    $url = "https://www.pricecharting.com/search-products?q=" . $searchTerm . "&type=prices";
    
    // Fetch the page
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$html) {
        return null;
    }
    
    // Parse HTML to find the first matching product
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    
    // Find the first product link
    $productLinks = $xpath->query("//a[contains(@href, '/game/pokemon-')]");
    if ($productLinks->length === 0) {
        return null;
    }
    
    $productUrl = 'https://www.pricecharting.com' . $productLinks[0]->getAttribute('href');
    
    // Fetch the product page
    curl_setopt($ch = curl_init(), CURLOPT_URL, $productUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $productHtml = curl_exec($ch);
    curl_close($ch);
    
    if (!$productHtml) {
        return null;
    }
    
    // Parse prices from the product page
    $productDom = new DOMDocument();
    @$productDom->loadHTML($productHtml);
    $productXpath = new DOMXPath($productDom);
    
    $prices = [
        'ungraded' => null,
        'psa7' => null,
        'psa8' => null,
        'psa9' => null,
        'psa10' => null
    ];
    
    // Try to extract prices from the page
    // PriceCharting shows prices in spans with class 'price'
    $priceElements = $productXpath->query("//span[@class='price']");
    
    // Look for specific grading labels
    $rows = $productXpath->query("//tr[contains(@class, 'chart-row')]");
    foreach ($rows as $row) {
        $label = $row->textContent;
        $priceSpans = $row->getElementsByTagName('span');
        
        foreach ($priceSpans as $span) {
            if (strpos($span->getAttribute('class'), 'price') !== false) {
                $price = trim($span->textContent);
                $price = preg_replace('/[^0-9.]/', '', $price);
                
                if (stripos($label, 'ungraded') !== false || stripos($label, 'raw') !== false) {
                    $prices['ungraded'] = $price ? floatval($price) : null;
                } elseif (stripos($label, 'PSA 10') !== false || stripos($label, 'grade 10') !== false) {
                    $prices['psa10'] = $price ? floatval($price) : null;
                } elseif (stripos($label, 'PSA 9') !== false || stripos($label, 'grade 9') !== false) {
                    $prices['psa9'] = $price ? floatval($price) : null;
                } elseif (stripos($label, 'PSA 8') !== false || stripos($label, 'grade 8') !== false) {
                    $prices['psa8'] = $price ? floatval($price) : null;
                } elseif (stripos($label, 'PSA 7') !== false || stripos($label, 'grade 7') !== false) {
                    $prices['psa7'] = $price ? floatval($price) : null;
                }
            }
        }
    }
    
    return $prices;
}

function getPokemonPriceTrackerPrices($cardName, $setName = '') {
    // Fallback to PokemonPriceTracker API
    $searchQuery = $cardName;
    if ($setName) {
        $searchQuery .= ' ' . $setName;
    }
    
    $url = 'https://api.pokemonpricetracker.com/api/v2/cards?name=' . urlencode($searchQuery) . '&includeEbay=true';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    if (!isset($data['data']) || empty($data['data'])) {
        return null;
    }
    
    $card = $data['data'][0];
    $ebayData = $card['ebay'] ?? null;
    
    if (!$ebayData) {
        return null;
    }
    
    return [
        'ungraded' => $card['tcgPlayerPrice'] ?? null,
        'psa7' => $ebayData['psa7Price'] ?? null,
        'psa8' => $ebayData['psa8Price'] ?? null,
        'psa9' => $ebayData['psa9Price'] ?? null,
        'psa10' => $ebayData['psa10Price'] ?? null
    ];
}

// Get parameters
$cardName = $_GET['name'] ?? '';
$setName = $_GET['set'] ?? '';

if (empty($cardName)) {
    echo json_encode(['error' => 'Card name is required']);
    exit;
}

// Try PriceCharting first
$prices = scrapePriceCharting($cardName, $setName);

// If PriceCharting fails, try PokemonPriceTracker
if (!$prices || array_filter($prices) === []) {
    $prices = getPokemonPriceTrackerPrices($cardName, $setName);
}

// If both fail, return estimated prices
if (!$prices || array_filter($prices) === []) {
    $prices = [
        'ungraded' => null,
        'psa7' => null,
        'psa8' => null,
        'psa9' => null,
        'psa10' => null,
        'estimated' => true
    ];
}

echo json_encode([
    'success' => true,
    'prices' => $prices,
    'source' => ($prices['psa10'] !== null) ? 'real' : 'estimated'
]);
