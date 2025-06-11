<?php
// api/search_ships.php - Star Citizen API Proxy (Düzeltilmiş)

// Output buffering başlat
ob_start();

// Header'ları en başta set et
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

// Error reporting'i kapat (JSON response için)
error_reporting(0);
ini_set('display_errors', 0);

// Method kontrolü
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

// API Ayarları
define('API_BASE_URL', 'https://api.starcitizen-api.com');
define('API_VERSION', 'v1');
define('API_MODE', 'cache');
define('API_KEY', 'Ne4UEe9MrI1FhEiTQ9ENxyA7kYIlSN6I');

try {
    // Buffer'ı temizle
    ob_clean();
    
    $url = API_BASE_URL . "/" . API_KEY . "/" . API_VERSION . "/" . API_MODE . "/ships";
    $params = [];
    
    // Gelen parametreleri işle ve validate et
    if (isset($_GET['name']) && !empty(trim($_GET['name']))) {
        $name = trim($_GET['name']);
        // Güvenlik kontrolü - sadece alfanümerik ve bazı özel karakterler
        if (preg_match('/^[a-zA-Z0-9\s\-_.]{1,50}$/', $name)) {
            $params['name'] = $name;
        }
    }
    
    if (isset($_GET['classification']) && !empty($_GET['classification'])) {
        $classification = $_GET['classification'];
        // İzin verilen sınıflandırmalar
        $allowed_classifications = [
            'combat', 'transport', 'exploration', 'industrial', 
            'support', 'competition', 'ground', 'multi'
        ];
        if (in_array($classification, $allowed_classifications, true)) {
            $params['classification'] = $classification;
        }
    }

    
    // URL oluştur
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    // HTTP context oluştur
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Accept: application/json',
                'User-Agent: IlgarionTuranis-Hangar/1.0'
            ],
            'timeout' => 30,
            'ignore_errors' => true
        ]
    ]);
    
    // API çağrısı yap
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception("API isteği başarısız oldu");
    }
    
    // HTTP status code kontrol et
    $http_response_header = $http_response_header ?? [];
    $status_line = isset($http_response_header[0]) ? $http_response_header[0] : '';
    
    if (strpos($status_line, '200') === false && strpos($status_line, '304') === false) {
        throw new Exception("API hatası: " . $status_line);
    }
    
    $data = @json_decode($response, true);
    
    if (!$data) {
        throw new Exception("API'den geçersiz JSON response");
    }
    
    if (!isset($data['success']) || $data['success'] !== 1) {
        throw new Exception($data['message'] ?? 'API hatası');
    }
    
    // Veri temizleme ve güvenlik
    $ships = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
    $cleaned_ships = [];
    
    foreach ($ships as $ship) {
        if (!is_array($ship)) continue;
        
        // Sadece gerekli alanları al ve temizle
        $cleaned_ship = [
            'id' => isset($ship['id']) ? (int)$ship['id'] : 0,
            'name' => isset($ship['name']) ? htmlspecialchars(trim($ship['name']), ENT_QUOTES, 'UTF-8') : 'Unknown Ship',
            'size' => isset($ship['size']) ? htmlspecialchars(trim($ship['size']), ENT_QUOTES, 'UTF-8') : '',
            'focus' => isset($ship['focus']) ? htmlspecialchars(trim($ship['focus']), ENT_QUOTES, 'UTF-8') : '',
            'price' => isset($ship['price']) ? (float)$ship['price'] : 0,
            'max_crew' => isset($ship['max_crew']) ? (int)$ship['max_crew'] : 0,
            'manufacturer' => [
                'name' => isset($ship['manufacturer']['name']) ? htmlspecialchars(trim($ship['manufacturer']['name']), ENT_QUOTES, 'UTF-8') : ''
            ],
            'media' => []
        ];
        
        // Medya dosyalarını temizle
        if (isset($ship['media']) && is_array($ship['media'])) {
            foreach ($ship['media'] as $media) {
                if (isset($media['source_url']) && filter_var($media['source_url'], FILTER_VALIDATE_URL)) {
                    $cleaned_ship['media'][] = [
                        'source_url' => $media['source_url']
                    ];
                    // Sadece ilk resmi al
                    break;
                }
            }
        }
        
        if ($cleaned_ship['id'] > 0) {
            $cleaned_ships[] = $cleaned_ship;
        }
    }
    
    // Buffer'ı temizle ve başarılı response
    ob_clean();
    echo json_encode([
        'success' => true,
        'data' => $cleaned_ships,
        'message' => 'success',
        'count' => count($cleaned_ships)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Buffer'ı temizle
    ob_clean();
    
    // Log the error
    error_log("Ship search API error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => [],
        'message' => 'API servisinde bir hata oluştu: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // Buffer'ı temizle
    ob_clean();
    
    // Log the error
    error_log("Ship search critical error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => [],
        'message' => 'Kritik bir hata oluştu'
    ], JSON_UNESCAPED_UNICODE);
}

// Buffer'ı kapat
ob_end_flush();
?>