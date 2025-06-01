<?php
// src/config/database.php
define('BASE_PATH', dirname(dirname(__DIR__))); // Proje kök dizini (örn: /path/to/your/ilgarion_turanis_website)
define('DB_HOST', 'localhost'); // Genellikle localhost'tur
define('DB_USER', 'root');      // Verdiğin kullanıcı adı
define('DB_PASS', '');      // Verdiğin şifre
define('DB_NAME', 'ilgarion_turanis_db'); // Verdiğin veritabanı adı
define('DB_CHARSET', 'utf8mb4');

// PDO ile bağlantı için DSN (Data Source Name)
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Hataları Exception olarak yakala
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Sonuçları associativedizi olarak al
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Gerçek prepared statement'ları kullan
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // Geliştirme aşamasında detaylı hata, canlıda daha genel bir mesaj verilebilir
    error_log("Veritabanı Bağlantı Hatası: " . $e->getMessage()); // Hata loglama
    // Kullanıcıya gösterilecek hata mesajı (canlı ortam için)
    // die("Siteye şu anda erişilemiyor. Lütfen daha sonra tekrar deneyin.");
    // Geliştirme için detaylı hata:
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// $pdo değişkenini diğer dosyalarda kullanmak için burada bırakıyoruz.
// Örneğin: require_once __DIR__ . '/../config/database.php';
// ve sonra $pdo->query(...) gibi.

?>