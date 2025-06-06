<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Hashleme Aracı (PHP)</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: bold;
        }
        input[type="text"] {
            width: calc(100% - 20px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        button {
            background-color: #007bff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #0056b3;
        }
        .result {
            margin-top: 25px;
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 4px;
            text-align: left;
            word-wrap: break-word; /* Uzun hash'lerin taşmasını engeller */
        }
        .result strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Şifre Hashleme Aracı (PHP)</h2>
        <form action="" method="post"> <div class="form-group">
                <label for="password">Şifre Girin:</label>
                <input type="text" id="password" name="password" placeholder="Şifrenizi buraya girin..." required>
            </div>
            <button type="submit">Hashle</button>
        </form>

        <?php
        // Form gönderildiğinde (POST metodu ile)
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Şifre girilmiş mi ve boş değil mi kontrol et
            if (isset($_POST["password"]) && !empty($_POST["password"])) {
                $password = $_POST["password"];

                // Şifreyi PHP'nin güvenli password_hash() fonksiyonu ile hash'le
                // PASSWORD_DEFAULT şu anda en iyi algoritmayı (genellikle bcrypt) kullanır
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                echo '<div class="result">';
                echo '<strong>Girilen Şifre:</strong> ' . htmlspecialchars($password) . '<br>'; // XSS saldırılarına karşı koruma
                echo '<strong>Hashlenmiş Şifre:</strong> ' . $hashed_password;
                echo '</div>';
            } else {
                echo '<div class="result" style="background-color: #ffe0e0; color: #dc3545;">';
                echo 'Lütfen bir şifre girin.';
                echo '</div>';
            }
        }
        ?>
    </div>
</body>
</html>