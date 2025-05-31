<?php
// src/actions/search_ships_api.php

header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/functions/auth_functions.php'; // auth_functions.php'yi dahil et

// Temel yetkilendirme (AJAX için)
if (!is_user_logged_in()) { // is_user_logged_in() fonksiyonunu kullan
    echo json_encode(['success' => false, 'error' => 'Bu işlem için giriş yapmalısınız.']);
    exit;
}
require_approved_user(); // Sadece onaylanmış kullanıcılar API araması yapabilsin

$search_term = trim($_GET['query'] ?? '');

if (empty($search_term)) {
    echo json_encode(['success' => true, 'data' => [], 'message' => 'Lütfen bir arama terimi girin.']);
    exit;
}

$apiKey = 'Ne4UEe9MrI1FhEiTQ9ENxyA7kYIlSN6I'; // API Anahtarın
$mode = 'cache'; // Arama için 'cache' modu genellikle daha hızlı ve önerilen moddur.
                 // 'live' çok yavaş olabilir ve API limitlerini etkileyebilir.

// API URL'sini arama parametresiyle oluştur (?name=ARAMA_TERIMI)
$apiUrl = "https://api.starcitizen-api.com/{$apiKey}/v1/{$mode}/ships?name=" . urlencode($search_term);

$headers = ['Accept: application/json'];
$found_ships = [];
$api_call_error = null;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout süresini biraz düşürebiliriz
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Test için gerekirse
// curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  // Test için gerekirse

$response_body = curl_exec($ch);

if (curl_errno($ch)) {
    $api_call_error = 'API Bağlantı Hatası: ' . curl_error($ch);
    error_log("cURL Error for ships API search ($search_term): " . curl_error($ch));
} else {
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode == 200) {
        $shipDataContainer = json_decode($response_body, true);
        if ($shipDataContainer && isset($shipDataContainer['success']) && $shipDataContainer['success'] == 1) {
            if (isset($shipDataContainer['data']) && is_array($shipDataContainer['data']) && !empty($shipDataContainer['data'])) {
                $found_ships = $shipDataContainer['data'];
            } else {
                // Başarılı yanıt ama data boş veya array değil (eşleşme yok)
                // $api_call_error = "Aramanızla eşleşen gemi bulunamadı."; // Bunu JS tarafında ele alalım
            }
        } elseif ($shipDataContainer && isset($shipDataContainer['message'])) {
            $api_call_error = "API Mesajı: " . htmlspecialchars($shipDataContainer['message']);
        } else {
            $api_call_error = "API yanıtı beklenmeyen formatta.";
            error_log("Beklenmeyen API yanıtı (search_ships_api): " . $response_body);
        }
    } else {
        $api_call_error = "API'den Hata Kodu Döndü: " . $httpCode;
        error_log("API Hata Kodu (search_ships_api): " . $httpCode . " - Yanıt: " . $response_body);
    }
}
curl_close($ch);

if ($api_call_error) {
    echo json_encode(['success' => false, 'error' => $api_call_error, 'data' => []]);
} else {
    // Başarılı ama gemi bulunamadıysa boş data göndermek JS tarafında kontrol edilecek.
    echo json_encode(['success' => true, 'data' => $found_ships, 'message' => empty($found_ships) ? 'Aramanızla eşleşen gemi bulunamadı.' : 'Gemiler başarıyla bulundu.']);
}
exit;
?>
