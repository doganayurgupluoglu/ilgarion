<?php
// api/search_ships.php - Star Citizen API Proxy

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

// API Ayarları
const API_BASE_URL = "https://api.starcitizen-api.com";
const API_VERSION = "v1";
const API_MODE = "cache";
const API_KEY = "Ne4UEe9MrI1FhEiTQ9ENxyA7kYIlSN6I";

try {
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
        if (in_array($classification, $allowed_classifications)) {
            $params['classification'] = $classification;
        }
    }
    
    // Sayfa limiti - maksimum 3 sayfa (30 gemi)
    $params['page_max'] = 3;
    
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
            'timeout' => 45
        ]
    ]);
    
    // API çağrısı yap
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception("API request failed");
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['success']) || $data['success'] !== 1) {
        throw new Exception($data['message'] ?? 'API error');
    }
    
    // Veri temizleme ve güvenlik
    $ships = $data['data'] ?? [];
    $cleaned_ships = [];
    
    foreach ($ships as $ship) {
        // Sadece gerekli alanları al ve temizle
        $cleaned_ship = [
            'id' => isset($ship['id']) ? (int)$ship['id'] : 0,
            'name' => isset($ship['name']) ? htmlspecialchars(trim($ship['name'])) : 'Unknown Ship',
            'size' => isset($ship['size']) ? htmlspecialchars(trim($ship['size'])) : '',
            'focus' => isset($ship['focus']) ? htmlspecialchars(trim($ship['focus'])) : '',
            'price' => isset($ship['price']) ? (float)$ship['price'] : 0,
            'max_crew' => isset($ship['max_crew']) ? (int)$ship['max_crew'] : 0,
            'manufacturer' => [
                'name' => isset($ship['manufacturer']['name']) ? htmlspecialchars(trim($ship['manufacturer']['name'])) : ''
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
                }
            }
        }
        
        $cleaned_ships[] = $cleaned_ship;
    }
    
    // Başarılı response
    echo json_encode([
        'success' => true,
        'data' => $cleaned_ships,
        'message' => 'success',
        'count' => count($cleaned_ships)
    ]);
    
} catch (Exception $e) {
    error_log("Ship search API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'data' => [],
        'message' => 'API servisinde bir hata oluştu. Lütfen daha sonra tekrar deneyin.'
    ]);
}
?>