<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Star Citizen Item API Arama Testi</title>
    <style>
        /* Sayfanın temel stilleri (senin verdiğin API test sayfasındaki stillere benzer) */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: #141414; /* Koyu tema */
            color: #e0e0e0;
            line-height: 1.6;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: #1E1E1E; /* Biraz daha açık koyu */
            padding: 20px;
            border-radius: 8px;
        }
        h1, h2 {
            color: #E50914; /* Netflix Kırmızısı */
            text-align: center;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
            margin-top:0;
        }
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            align-items: flex-end;
        }
        .search-form .form-group {
            flex-grow: 1;
        }
        .search-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .search-form input[type="text"], .search-form select {
            width: 100%;
            padding: 10px;
            background-color: #333;
            border: 1px solid #555;
            color: #fff;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em;
        }
        .search-form button {
            padding: 10px 20px;
            background-color: #E50914;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s;
        }
        .search-form button:hover {
            background-color: #B81D24;
        }
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .item-card {
            background-color: #252a31;
            border: 1px solid #3a3f46;
            border-radius: 6px;
            padding: 15px;
            display: flex;
            flex-direction: column;
        }
        .item-card h3 {
            margin-top: 0;
            margin-bottom: 8px;
            color: #d94f2b; /* Turuncuya yakın */
            font-size: 1.2em;
        }
        .item-card p {
            margin: 0 0 5px 0;
            font-size: 0.9em;
            color: #c0c0c0;
        }
        .item-card small {
            font-size: 0.8em;
            color: #888;
        }
        .api-message, .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 20px;
            color: #aaa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Star Citizen Item Arama Testi</h1>
        <form method="POST" action="" class="search-form">
            <div class="form-group">
                <label for="search_query">Item Adı veya Tipi:</label>
                <input type="text" id="search_query" name="search_query" value="<?php echo htmlspecialchars($_POST['search_query'] ?? ''); ?>" placeholder="örn: Morozov, WeaponPersonal, Char_Armor_Helmet">
            </div>
            <div class="form-group">
                <label for="filter_type">Filtrele (Type - Opsiyonel):</label>
                <input type="text" id="filter_type" name="filter_type" value="<?php echo htmlspecialchars($_POST['filter_type'] ?? ''); ?>" placeholder="örn: Char_Armor_Backpack">
            </div>
            <button type="submit" name="submit_search">Ara</button>
        </form>

        <div class="results-grid">
            <?php
            if (isset($_POST['submit_search'])) {
                $searchQuery = trim($_POST['search_query'] ?? '');
                $filterType = trim($_POST['filter_type'] ?? ''); // Opsiyonel filtre

                if (empty($searchQuery)) {
                    echo "<p class='api-message'>Lütfen bir arama terimi girin.</p>";
                } else {
                    // API Bilgileri
                    // Bu API anahtarı doğrudan koda gömülmemeli, bir config dosyasından alınmalı.
                    // Ancak bu test sayfası için şimdilik burada bırakıyorum.
                    // Gerçek API anahtarını kullanman gerekebilir. Star-Citizen.wiki API anahtarları genellikle herkese açık değildir.
                    // Eğer bir API anahtarına ihtiyacın yoksa veya farklı bir yöntemle erişiyorsan, bu kısmı düzenle.
                    // $apiKey = 'YOUR_API_KEY_HERE'; // Eğer gerekiyorsa
                    // $apiUrl = "https://api.star-citizen.wiki/api/v2/items/search"; 
                    // Dokümantasyona göre URL'de API key yok, parametre olarak gönderiliyor olabilir veya public.
                    // Şimdilik query parametrelerini URL'e ekleyeceğiz.

                    $apiUrlBase = "https://api.star-citizen.wiki/api/v2/items/search";
                    $queryParams = [];
                    if (!empty($filterType)) {
                        $queryParams['filter[type]'] = $filterType;
                    }
                    // Örnekte include=shops.items vardı, bunu da ekleyebiliriz
                    $queryParams['include'] = 'shops'; // 'shops.items' daha detaylı olabilir

                    $fullApiUrl = $apiUrlBase;
                    if (!empty($queryParams)) {
                        $fullApiUrl .= '?' . http_build_query($queryParams);
                    }

                    $requestBody = json_encode(['query' => $searchQuery]);

                    // echo "<p>API URL: " . htmlspecialchars($fullApiUrl) . "</p>";
                    // echo "<p>Request Body: " . htmlspecialchars($requestBody) . "</p>";

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $fullApiUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true); // POST isteği
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody); // JSON body
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Accept: application/json',
                        'Content-Type: application/json' // Önemli!
                    ]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Sadece yerel test için
                    // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  // Sadece yerel test için

                    $response = curl_exec($ch);
                    $curlError = null;
                    $items = [];

                    if (curl_errno($ch)) {
                        $curlError = 'cURL Hatası: ' . curl_error($ch);
                    } else {
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        if ($httpCode == 200) {
                            $responseData = json_decode($response, true);
                            if ($responseData && isset($responseData['data']) && is_array($responseData['data'])) {
                                $items = $responseData['data'];
                            } elseif ($responseData && isset($responseData['message'])) {
                                $curlError = "API Mesajı: " . htmlspecialchars($responseData['message']);
                            } else {
                                $curlError = "API yanıtı beklenmeyen formatta veya 'data' kısmı yok.";
                                // echo "<pre>Ham Yanıt:\n" . htmlspecialchars($response) . "\n</pre>";
                            }
                        } else {
                            $curlError = "API'den Hata Kodu Döndü: " . $httpCode;
                             // echo "<pre>Hata Yanıtı:\n" . htmlspecialchars($response) . "\n</pre>";
                        }
                    }
                    curl_close($ch);

                    if (!empty($items)) {
                        echo "<h2>Arama Sonuçları (" . count($items) . " bulundu)</h2>";
                        foreach ($items as $item) {
                            $itemName = htmlspecialchars($item['name'] ?? 'İsimsiz Item');
                            $itemType = htmlspecialchars($item['type'] ?? 'N/A');
                            $itemSubType = htmlspecialchars($item['sub_type'] ?? 'N/A');
                            $manufacturerName = htmlspecialchars($item['manufacturer']['name'] ?? 'Bilinmeyen');
                            $itemUuid = htmlspecialchars($item['uuid'] ?? '#');
                            // Resmi bulmak için bir mantık (API yanıtına göre)
                            // Bu API'de resim doğrudan item objesinde gelmiyor gibi, belki 'media' veya benzeri bir alanda.
                            // StarCitizen-API.com'daki gibi 'media[0].source_url' yok.
                            // Şimdilik placeholder kullanalım.
                            $imageUrl = 'https://via.placeholder.com/150x100.png?text=' . urlencode(substr($itemName,0,25));

                            echo "<div class='item-card'>";
                            echo "  <h3>" . $itemName . "</h3>";
                            echo "  <p><strong>UUID:</strong> " . $itemUuid . "</p>";
                            echo "  <p><strong>Tip:</strong> " . $itemType . ($itemSubType !== 'UNDEFINED' && $itemSubType !== 'N/A' ? " / " . $itemSubType : "") . "</p>";
                            echo "  <p><strong>Üretici:</strong> " . $manufacturerName . "</p>";
                            // Diğer bilgiler eklenebilir (shops, fiyat vb.)
                            // Örneğin, ilk dükkan ve fiyatı:
                            if (!empty($item['shops']) && !empty($item['shops'][0]['items'])) {
                                $shopItem = $item['shops'][0]['items'][0]; // Genellikle item kendisidir
                                if ($shopItem['uuid'] === $item['uuid'] && isset($shopItem['base_price'])) {
                                    echo "  <p><strong>Fiyat (Mağaza):</strong> " . htmlspecialchars($shopItem['base_price']) . " aUEC</p>";
                                }
                            }
                            echo "  <small>API Link: <a href='" . htmlspecialchars($item['link'] ?? '#') . "' target='_blank'>Detay</a></small>";
                            echo "</div>";
                        }
                    } elseif ($curlError) {
                        echo "<p class='api-message'>Veri çekilirken bir sorun oluştu: " . htmlspecialchars($curlError) . "</p>";
                    } else {
                        echo "<p class='no-results'>\"" . htmlspecialchars($searchQuery) . "\" araması için sonuç bulunamadı.</p>";
                    }
                }
            }
            ?>
        </div>
    </div>
</body>
</html>