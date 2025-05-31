<?php
// src/actions/search_items_star_citizen_wiki_api.php

header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hata gösterimini açalım (DEBUG için)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/functions/auth_functions.php'; // auth_functions.php'yi dahil et

if (!is_user_logged_in()) { // is_user_logged_in() fonksiyonunu kullan
    echo json_encode(['success' => false, 'error' => 'Bu işlem için giriş yapmalısınız.']);
    exit;
}
require_approved_user(); // Sadece onaylanmış kullanıcılar API araması yapabilsin

$search_term_from_js = trim($_GET['query'] ?? '');
$filter_type_from_js = trim($_GET['filter_type'] ?? ''); // Bunu da API'ye gönderebiliriz

if (empty($search_term_from_js)) {
    echo json_encode(['success' => true, 'data' => [], 'message' => 'Lütfen bir arama terimi girin.']);
    exit;
}

$apiUrlBase = "https://api.star-citizen.wiki/api/v2/items/search";
$queryParams = [];

if (!empty($filter_type_from_js)) {
    $queryParams['filter[type]'] = $filter_type_from_js;
}
// $queryParams['include'] = 'shops'; // İsteğe bağlı

$fullApiUrl = $apiUrlBase;
if (!empty($queryParams)) {
    $fullApiUrl .= '?' . http_build_query($queryParams);
}

// API'ye gönderilecek arama terimi: Kullanıcının girdiği ham terim.
// API'nin kendisi "morozov legs" gibi çoklu kelime aramalarını nasıl ele alıyor görmek önemli.
$requestBodyArray = ['query' => $search_term_from_js];
$requestBodyJson = json_encode($requestBodyArray);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fullApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBodyJson);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
// curl_setopt($ch, CURLOPT_USERAGENT, 'IlgarionTuranisWebsite/1.0');

$response_body = curl_exec($ch);
$curl_error_msg = null;
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$items_from_api = [];

if (curl_errno($ch)) {
    $curl_error_msg = 'API Bağlantı Hatası (cURL): ' . curl_error($ch);
    error_log("cURL Error (search_items): " . curl_error($ch) . " | URL: " . $fullApiUrl . " | Body: " . $requestBodyJson);
} else {
    if ($httpCode == 200) {
        $responseData = json_decode($response_body, true);
        if ($responseData && isset($responseData['data']) && is_array($responseData['data'])) {
            $items_from_api = $responseData['data'];
        } elseif ($responseData && isset($responseData['message']) && stripos($responseData['message'], 'no results') !== false) {
            // API "no results" mesajı dönerse, $items_from_api boş kalacak.
        } elseif ($responseData && isset($responseData['message'])) {
            $curl_error_msg = "API Mesajı: " . htmlspecialchars($responseData['message']);
        } elseif ($responseData === null && json_last_error() !== JSON_ERROR_NONE) {
            $curl_error_msg = "API yanıtı geçerli bir JSON değil. Hata: " . json_last_error_msg();
            error_log("Invalid JSON (search_items): " . $response_body);
        } else {
            // $curl_error_msg = "API yanıtı 'data' dizisi içermiyor veya format beklenmedik.";
            // error_log("Unexpected API response (search_items): " . $response_body);
        }
    } else {
        $curl_error_msg = "API'den Hata Kodu Döndü: " . $httpCode;
        error_log("API HTTP Error (search_items): " . $httpCode . " - Yanıt: " . $response_body);
    }
}
curl_close($ch);

if ($curl_error_msg) {
    echo json_encode(['success' => false, 'error' => $curl_error_msg, 'data' => []]);
    exit;
}

// API'den gelen sonuçlar üzerinde ek PHP filtrelemesi (kullanıcının girdiği tüm kelimeler geçmeli)
$filtered_results = [];
if (!empty($items_from_api)) {
    $search_keywords = preg_split('/\s+/', strtolower($search_term_from_js));
    $search_keywords = array_filter($search_keywords); // Boş elemanları ve null'ları kaldır

    if (empty($search_keywords)) { // Eğer arama terimi sadece boşluklardan oluşuyorsa
        $filtered_results = $items_from_api; // API ne döndürdüyse onu kullan
    } else {
        foreach ($items_from_api as $item) {
            $item_name_lower = strtolower($item['name'] ?? '');
            $all_keywords_found = true;
            foreach ($search_keywords as $keyword) {
                if (strpos($item_name_lower, $keyword) === false) {
                    $all_keywords_found = false;
                    break;
                }
            }
            if ($all_keywords_found) {
                $filtered_results[] = $item;
            }
        }
    }
}

// Sonuçları limitleme
$limit_results = 20; // İstediğin bir limit
$final_results_to_send = array_slice($filtered_results, 0, $limit_results);

if (empty($final_results_to_send)) {
    echo json_encode(['success' => true, 'data' => [], 'message' => '"' . htmlspecialchars($search_term_from_js) . '" ile eşleşen item bulunamadı (PHP filtresi sonrası).']);
} else {
    echo json_encode([
        'success' => true, 
        'data' => $final_results_to_send,
        'message' => count($final_results_to_send) . ' item bulundu.' . (count($filtered_results) > $limit_results ? ' (Daha fazla sonuç olabilir, aramanızı daraltın)' : '')
    ]);
}
exit;
?>
