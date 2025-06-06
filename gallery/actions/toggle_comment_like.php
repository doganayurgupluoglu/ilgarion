<?php
// gallery/actions/toggle_comment_like.php - Basitleştirilmiş versiyon

// Tüm çıktıları yakala
ob_start();

// Hata gösterimini kapat
error_reporting(0);
ini_set('display_errors', 0);

// JSON header'ı ayarla
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Session başlat
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Basit test yanıtı
    if (isset($_GET['test'])) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Test successful',
            'timestamp' => time()
        ]);
        exit;
    }

    // Include'ları yükle
    require_once '../../src/config/database.php';
    require_once BASE_PATH . '/src/functions/auth_functions.php';
    require_once BASE_PATH . '/src/functions/role_functions.php';
    require_once BASE_PATH . '/src/functions/sql_security_functions.php';

    // POST metodu kontrolü
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Sadece POST metodu kabul edilir.');
    }

    // User kontrolü
    if (!isset($_SESSION['user_id']) || !is_user_approved()) {
        throw new Exception('Giriş yapmanız ve onaylı olmanız gerekiyor.');
    }

    // CSRF kontrolü
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        throw new Exception('CSRF token hatası.');
    }

    // Comment ID kontrolü
    $comment_id = (int)($_POST['comment_id'] ?? 0);
    if ($comment_id <= 0) {
        throw new Exception('Geçersiz yorum ID.');
    }

    // Rate limiting
    if (!check_rate_limit('comment_like', 30, 60)) {
        throw new Exception('Çok fazla istek. Bekleyin.');
    }

    // Comment'i bul
    $stmt = $pdo->prepare("
        SELECT gc.*, gp.user_id as photo_owner_id
        FROM gallery_photo_comments gc
        JOIN gallery_photos gp ON gc.photo_id = gp.id
        WHERE gc.id = ?
    ");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$comment) {
        throw new Exception('Yorum bulunamadı.');
    }

    // Kendi yorumunu beğenemez
    if ($comment['user_id'] == $_SESSION['user_id']) {
        throw new Exception('Kendi yorumunuzu beğenemezsiniz.');
    }

    // Mevcut beğeni kontrolü
    $stmt = $pdo->prepare("
        SELECT id FROM gallery_comment_likes 
        WHERE comment_id = ? AND user_id = ?
    ");
    $stmt->execute([$comment_id, $_SESSION['user_id']]);
    $existing_like = $stmt->fetch();

    $pdo->beginTransaction();

    if ($existing_like) {
        // Beğeniyi kaldır
        $stmt = $pdo->prepare("
            DELETE FROM gallery_comment_likes 
            WHERE comment_id = ? AND user_id = ?
        ");
        $stmt->execute([$comment_id, $_SESSION['user_id']]);
        $liked = false;
        $message = 'Beğeni kaldırıldı.';
    } else {
        // Beğeni ekle
        $stmt = $pdo->prepare("
            INSERT INTO gallery_comment_likes (comment_id, user_id, liked_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$comment_id, $_SESSION['user_id']]);
        $liked = true;
        $message = 'Beğenildi.';
    }

    // Toplam beğeni sayısı
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM gallery_comment_likes WHERE comment_id = ?");
    $stmt->execute([$comment_id]);
    $like_count = (int)$stmt->fetchColumn();

    $pdo->commit();

    // Buffer'ı temizle ve JSON gönder
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => $message,
        'liked' => $liked,
        'like_count' => $like_count
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Hata logla
    error_log("Comment like error: " . $e->getMessage());

    // Buffer'ı temizle ve hata gönder
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Buffer'ı sonlandır
ob_end_flush();
?>