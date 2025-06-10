<?php
// api/search_ships.php - Page_max sorunu düzeltilmiş versiyon

error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('display_errors', 0);

ob_start();

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'OPTIONS OK']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    ob_clean();
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

// Session başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Authentication required"]);
    exit;
}

try {
    // Star Citizen API ayarları
    $api_base_url = "https://api.starcitizen-api.com";
    $api_key = "Ne4UEe9MrI1FhEiTQ9ENxyA7kYIlSN6I";
    $api_version = "v1";
    $api_mode = "cache";
    
    $api_url = "$api_base_url/$api_key/$api_version/$api_mode/ships";
    
    // Input parametrelerini al ve validate et
    $params = [];
    
    $search_name = $_GET['name'] ?? null;
    if ($search_name) {
        $search_name = trim($search_name);
        if (preg_match('/^[a-zA-Z0-9\s\-_.]{1,50}$/', $search_name)) {
            $params['name'] = $search_name;
        }
    }
    
    $search_classification = $_GET['classification'] ?? null;
    if ($search_classification) {
        $allowed = ['combat', 'transport', 'exploration', 'industrial', 'support', 'competition', 'ground', 'multi'];
        if (in_array(strtolower($search_classification), $allowed)) {
            $params['classification'] = strtolower($search_classification);
        }
    }
    
    // Page_max parametresini kaldırdık - API'nin kendi varsayılan değerini kullanacak
    // Sadece name veya classification varsa ekleyelim
    
    // URL'yi oluştur
    if (!empty($params)) {
        $api_url .= '?' . http_build_query($params);
    }
    
    // Debug için log
    error_log("API Request URL: " . $api_url);
    
    // API isteği
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Accept: application/json',
                'User-Agent: IlgarionTuranis-Hangar/1.0'
            ],
            'timeout' => 45 // Timeout'u artırdık çünkü page_max yok
        ]
    ]);
    
    $api_response = @file_get_contents($api_url, false, $context);
    
    if ($api_response === false) {
        throw new Exception('Star Citizen API isteği başarısız');
    }
    
    $api_data = json_decode($api_response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('API yanıtı geçersiz JSON: ' . json_last_error_msg());
    }
    
    if (!$api_data || $api_data['success'] !== 1) {
        $error_msg = isset($api_data['message']) ? $api_data['message'] : 'API hatası';
        throw new Exception('Star Citizen API Hatası: ' . $error_msg);
    }
    
    // Gemi verilerini işle
    $ships = $api_data['data'] ?? [];
    $cleaned_ships = [];
    
    // Eğer çok fazla gemi varsa (arama yapılmamışsa), ilk 30'unu al
    if (empty($params) && count($ships) > 30) {
        $ships = array_slice($ships, 0, 30);
    }
    
    foreach ($ships as $ship) {
        try {
            $cleaned_ship = [
                'id' => isset($ship['id']) ? (int)$ship['id'] : 0,
                'name' => isset($ship['name']) ? htmlspecialchars(trim($ship['name']), ENT_QUOTES, 'UTF-8') : 'Unknown Ship',
                'size' => isset($ship['size']) ? htmlspecialchars(trim($ship['size']), ENT_QUOTES, 'UTF-8') : '',
                'focus' => isset($ship['focus']) ? htmlspecialchars(trim($ship['focus']), ENT_QUOTES, 'UTF-8') : '',
                'price' => isset($ship['price']) ? (float)$ship['price'] : 0,
                'max_crew' => isset($ship['max_crew']) ? (int)$ship['max_crew'] : 0,
                'manufacturer' => [
                    'name' => isset($ship['manufacturer']['name']) ? 
                        htmlspecialchars(trim($ship['manufacturer']['name']), ENT_QUOTES, 'UTF-8') : ''
                ],
                'media' => []
            ];
            
            // Medya dosyalarını güvenli şekilde işle
            if (isset($ship['media']) && is_array($ship['media'])) {
                foreach ($ship['media'] as $media) {
                    if (isset($media['source_url']) && filter_var($media['source_url'], FILTER_VALIDATE_URL)) {
                        // HTTPS URL'leri tercih et
                        $url = $media['source_url'];
                        if (strpos($url, 'http://') === 0) {
                            $url = str_replace('http://', 'https://', $url);
                        }
                        $cleaned_ship['media'][] = ['source_url' => $url];
                    }
                }
            }
            
            if ($cleaned_ship['id'] > 0) {
                $cleaned_ships[] = $cleaned_ship;
            }
        } catch (Exception $e) {
            // Bu gemiyi atla, diğerleriyle devam et
            error_log("Gemi işleme hatası: " . $e->getMessage());
            continue;
        }
    }
    
    // Başarılı yanıt
    $response = [
        'success' => true,
        'data' => $cleaned_ships,
        'message' => 'Arama başarılı',
        'count' => count($cleaned_ships),
        'total_from_api' => count($ships),
        'api_url' => $api_url, // Debug için
        'search_params' => $params
    ];
    
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Ship search API error: " . $e->getMessage());
    
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => [],
        'message' => 'Gemi arama hatası: ' . $e->getMessage(),
        'error_details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'api_url' => $api_url ?? 'Oluşturulamadı'
        ]
    ], JSON_UNESCAPED_UNICODE);
}

ob_end_flush();
?>