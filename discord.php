<?php
// Discord Bot Tetikleyici
// Bu scripti kullanmadan önce aşağıdaki bilgileri doldurun:

// Discord Bot Ayarları - Buraya kendi bilgilerinizi girin
$bot_token = "MTM3OTE3MTUxNjExNzQyMjEzMA.G6i3wH.Y1Vxi69l2qbXffD_9h_qT5BTrFgOOcoT7Q6PQo";  // Bot token'ınızı buraya yazın
$channel_id = "1338556345233833994";  // Mesaj gönderilecek kanal ID'sini buraya yazın
$role_id = "1379175302294409306";       // Etiketlenecek rol ID'sini buraya yazın (isteğe bağlı)

// Mesaj gönderme fonksiyonu
function sendDiscordMessage($token, $channelId, $message, $roleId = null) {
    $url = "https://discord.com/api/v10/channels/{$channelId}/messages";
    
    // Rol etiketleme varsa mesaja ekle
    $finalMessage = $message;
    if ($roleId && $roleId !== "ROL_ID_BURAYA") {
        $finalMessage = "<@&{$roleId}> " . $message;
    }
    
    $data = json_encode([
        'content' => $finalMessage
    ]);
    
    $options = [
        'http' => [
            'header' => [
                "Content-Type: application/json",
                "Authorization: Bot {$token}",
                "User-Agent: DiscordBot (https://github.com/discord/discord-api-docs, 1.0)"
            ],
            'method' => 'POST',
            'content' => $data
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result === false) {
        return ['success' => false, 'error' => 'Mesaj gönderilemedi'];
    }
    
    $response = json_decode($result, true);
    
    if (isset($response['id'])) {
        return ['success' => true, 'message' => 'Mesaj başarıyla gönderildi!'];
    } else {
        return ['success' => false, 'error' => 'Discord API hatası: ' . json_encode($response)];
    }
}

// POST isteği kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    
    // Bot token ve kanal ID kontrolü
    if ($bot_token === "BOT_TOKEN_BURAYA" || $channel_id === "KANAL_ID_BURAYA") {
        $error_message = "Lütfen önce bot token'ı ve kanal ID'sini ayarlayın!";
    } else {
        // Discord'a mesaj gönder
        $result = sendDiscordMessage($bot_token, $channel_id, "Merhaba dünya!", $role_id);
        
        if ($result['success']) {
            $success_message = $result['message'];
        } else {
            $error_message = $result['error'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discord Bot Tetikleyici</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #7289da;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .info-box {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .info-box h3 {
            margin-top: 0;
            color: #0c5460;
        }
        
        .info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .send-button {
            background: #7289da;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            display: block;
            margin: 20px auto;
            transition: background 0.3s;
        }
        
        .send-button:hover {
            background: #5b6eae;
        }
        
        .send-button:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
        
        .success {
            color: #28a745;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px;
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .error {
            color: #dc3545;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .current-config {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .current-config h3 {
            margin-top: 0;
            color: #856404;
        }
        
        .config-item {
            margin: 8px 0;
            font-family: monospace;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 Discord Bot Tetikleyici</h1>
        
        <div class="info-box">
            <h3>📋 Kurulum Talimatları:</h3>
            <ul>
                <li>Discord Developer Portal'dan bot token'ınızı alın</li>
                <li>Mesaj göndermek istediğiniz kanalın ID'sini bulun</li>
                <li>Bu PHP dosyasının başındaki değişkenleri doldurun</li>
                <li>Bot'unuzun kanalda mesaj gönderme yetkisi olduğundan emin olun</li>
            <p><strong>Rol ID:</strong></p>
            <ul>
                <li>Discord'da Developer Mode'u aktifleştirin (Ayarlar > Gelişmiş > Developer Mode)</li>
                <li>Sunucu ayarları > Roller > Etiketlemek istediğiniz role sağ tıklayın > "Copy ID"</li>
                <li>Bu alan isteğe bağlıdır - boş bırakırsanız sadece mesaj gönderilir</li>
            </ul>
        </div>
        
        <div class="current-config">
            <h3>⚙️ Mevcut Yapılandırma:</h3>
            <div class="config-item">
                <strong>Bot Token:</strong> 
                <?php echo $bot_token === "BOT_TOKEN_BURAYA" ? "❌ Ayarlanmamış" : "✅ Ayarlanmış"; ?>
            </div>
            <div class="config-item">
                <strong>Kanal ID:</strong> 
                <?php echo $channel_id === "KANAL_ID_BURAYA" ? "❌ Ayarlanmamış" : "✅ Ayarlanmış (" . $channel_id . ")"; ?>
            </div>
            <div class="config-item">
                <strong>Rol ID:</strong> 
                <?php echo $role_id === "ROL_ID_BURAYA" ? "⚠️ Ayarlanmamış (İsteğe bağlı)" : "✅ Ayarlanmış (" . $role_id . ")"; ?>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="success">
                ✅ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="error">
                ❌ <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <button type="submit" name="send_message" class="send-button" 
                    <?php echo ($bot_token === "BOT_TOKEN_BURAYA" || $channel_id === "KANAL_ID_BURAYA") ? 'disabled' : ''; ?>>
                📤 Discord'a "Merhaba dünya!" Gönder
                <?php if ($role_id !== "ROL_ID_BURAYA"): ?>
                    <br><small style="font-size: 12px; opacity: 0.8;">(Rol etiketlemesi ile)</small>
                <?php endif; ?>
            </button>
        </form>
        
        <div class="info-box">
            <h3>🔧 Bot Token ve Kanal ID Nasıl Bulunur?</h3>
            <p><strong>Bot Token:</strong></p>
            <ul>
                <li>Discord Developer Portal'a gidin (https://discord.com/developers/applications)</li>
                <li>Uygulamanızı seçin > Bot sekmesi > Token bölümünden kopyalayın</li>
            </ul>
            <p><strong>Kanal ID:</strong></p>
            <ul>
                <li>Discord'da Developer Mode'u aktifleştirin (Ayarlar > Gelişmiş > Developer Mode)</li>
                <li>Kanala sağ tıklayın > "Copy ID" seçeneğini tıklayın</li>
            </ul>
        </div>
    </div>
</body>
</html>