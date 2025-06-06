<?php
// events/loadouts/api.php - Tip filtresi desteği ile güncellenmiş proxy

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Sadece POST istekleri kabul edilir']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$query = trim($input['query'] ?? '');
$filterType = trim($input['filter_type'] ?? '');

if (empty($query)) {
    echo json_encode(['error' => 'Query boş']);
    exit;
}

// Debug log
error_log("API Proxy - Query: $query, Filter: $filterType");

// API URL'ini oluştur
$apiUrl = 'https://api.star-citizen.wiki/api/v2/items/search';

// Eğer filtre varsa URL parametresi olarak ekle
if (!empty($filterType)) {
    $apiUrl .= '?filter[type]=' . urlencode($filterType);
    error_log("API URL with filter: $apiUrl");
}

// Request body
$requestBody = json_encode(['query' => $query]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'User-Agent: LoadoutBuilder/1.0'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Debug log
error_log("API Response Code: $httpCode");

if (!empty($curlError)) {
    error_log("cURL Error: $curlError");
    echo json_encode(['error' => 'Bağlantı hatası: ' . $curlError]);
    exit;
}

if ($httpCode === 200) {
    $responseData = json_decode($response, true);
    
    if ($responseData === null) {
        error_log("JSON decode error: " . json_last_error_msg());
        echo json_encode(['error' => 'Geçersiz JSON yanıtı']);
        exit;
    }
    
    // API yanıtını kontrol et
    if (isset($responseData['data']) && is_array($responseData['data'])) {
        $items = $responseData['data'];
        
        // Client-side filtreleme için ek filtreleme (backup)
        if (!empty($filterType) && is_array($items)) {
            $filteredItems = array_filter($items, function($item) use ($filterType) {
                $itemType = $item['type'] ?? '';
                $itemSubType = $item['sub_type'] ?? '';
                
                // Tip eşleşmesi kontrolü
                return stripos($itemType, $filterType) !== false || 
                       stripos($itemSubType, $filterType) !== false ||
                       $itemType === $filterType ||
                       $itemSubType === $filterType;
            });
            
            // Filtrelenmiş sonuçları döndür
            $items = array_values($filteredItems); // Array indexlerini sıfırla
        }
        
        error_log("Returning " . count($items) . " items");
        echo json_encode($items);
    } else {
        // Eğer 'data' anahtarı yoksa, doğrudan yanıtı döndür
        error_log("No 'data' key found, returning raw response");
        echo $response;
    }
} else {
    error_log("HTTP Error $httpCode: $response");
    
    // Hata mesajları
    $errorMessages = [
        400 => 'Geçersiz istek',
        401 => 'Yetkilendirme hatası',
        403 => 'Erişim reddedildi',
        404 => 'API endpoint bulunamadı',
        429 => 'Çok fazla istek - lütfen bekleyin',
        500 => 'Sunucu hatası',
        502 => 'API geçici olarak kullanılamıyor',
        503 => 'Servis kullanılamıyor'
    ];
    
    $errorMessage = $errorMessages[$httpCode] ?? "HTTP $httpCode hatası";
    
    echo json_encode([
        'error' => $errorMessage,
        'http_code' => $httpCode,
        'details' => 'API bağlantı sorunu'
    ]);
}
?>