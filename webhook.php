<?php

// n8n Webhook URL'nizi buraya yapıştırın
$n8nWebhookUrl = 'https://ilgarionturanis.app.n8n.cloud/webhook-test/a7491ddc-3b59-462d-aa3d-7c641a85ce97'; // Örneğin: https://your.n8n.instance/webhook/abcdefg12345

// Göndermek istediğiniz veri (örneğin bir JSON nesnesi)
$data = array(
    'message' => 'Merhaba SCG Ben ilgarion!',
    'timestamp' => date('Y-m-d H:i:s'),
    'user_id' => 123,
    'event_name' => 'Yarrağımın başı etkinliği',
    'Calendar' => date('Y-m-d H:i:s')
);

// Veriyi JSON formatına dönüştürün
$json_data = json_encode($data);

// cURL başlatma
$ch = curl_init($n8nWebhookUrl);

// POST isteği için seçenekleri ayarlama
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); // HTTP metodu POST olarak ayarla
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data); // Gönderilecek veriyi ayarla
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Yanıtı string olarak döndür
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json', // İçerik tipini JSON olarak ayarla
    'Content-Length: ' . strlen($json_data) // İçerik uzunluğunu ayarla
));

// İsteği yürütme ve yanıtı alma
$response = curl_exec($ch);

// Hata kontrolü
if (curl_errno($ch)) {
    echo 'cURL hatası: ' . curl_error($ch);
} else {
    echo 'n8n\'e webhook başarıyla gönderildi. Yanıt: ' . $response;
}

// cURL oturumunu kapatma
curl_close($ch);

?>