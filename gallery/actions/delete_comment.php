<?php
// gallery/actions/delete_comment.php

// Hata raporlamayı kapat ve sadece JSON çıktısı ver
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/gallery_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Output buffering başlat
ob_start();

try {
    // Güvenlik kontrolleri
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Geçersiz istek metodu.');
    }
    
    if (!isset($_SESSION['user_id']) || !is_user_approved()) {
        throw new Exception('Bu işlem için giriş yapmanız ve hesabınızın onaylanmış olması gerekmektedir.');
    }
    
    // CSRF kontrolü
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        throw new Exception('Güvenlik token hatası. Sayfayı yenileyin ve tekrar deneyin.');
    }
    
    // Parametreleri al
    $comment_id = (int)($_POST['comment_id'] ?? 0);
    
    if ($comment_id <= 0) {
        throw new Exception('Geçersiz yorum ID\'si.');
    }
    
    // Rate limiting
    if (!is_super_admin($pdo, $_SESSION['user_id']) && !check_rate_limit('comment_delete', 10, 300)) {
        throw new Exception('Çok fazla silme girişimi. Lütfen biraz bekleyin.');
    }
    
    // Yorum var mı ve erişim kontrolü
    $comment_query = "
        SELECT gc.*, gp.user_id as photo_owner_id, gp.is_public_no_auth, gp.is_members_only, gp.id as photo_id
        FROM gallery_photo_comments gc
        JOIN gallery_photos gp ON gc.photo_id = gp.id
        WHERE gc.id = :comment_id
    ";
    $stmt = execute_safe_query($pdo, $comment_query, [':comment_id' => $comment_id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comment) {
        throw new Exception('Yorum bulunamadı.');
    }
    
    // Silme yetkisi kontrolü
    $can_delete = false;
    
    // Kendi yorumunu silebilir
    if ($comment['user_id'] == $_SESSION['user_id'] && has_permission($pdo, 'gallery.comment.delete_own', $_SESSION['user_id'])) {
        $can_delete = true;
    }
    
    // Admin/moderatör her yorumu silebilir
    if (has_permission($pdo, 'gallery.comment.delete_all', $_SESSION['user_id'])) {
        $can_delete = true;
    }
    
    if (!$can_delete) {
        throw new Exception('Bu yorumu silme yetkiniz bulunmamaktadır.');
    }
    
    // Transaction başlat
    $pdo->beginTransaction();
    
    try {
        // Yorumun beğenilerini sil
        $delete_likes_query = "DELETE FROM gallery_comment_likes WHERE comment_id = :comment_id";
        execute_safe_query($pdo, $delete_likes_query, [':comment_id' => $comment_id]);
        
        // Yorumu sil
        $delete_comment_query = "DELETE FROM gallery_photo_comments WHERE id = :comment_id";
        execute_safe_query($pdo, $delete_comment_query, [':comment_id' => $comment_id]);
        
        // Transaction'ı commit et
        $pdo->commit();
        
        // Audit log
        audit_log($pdo, $_SESSION['user_id'], 'gallery_comment_deleted', 'gallery_comment', $comment_id, 
            ['comment_text' => $comment['comment_text']], [
                'photo_id' => $comment['photo_id'],
                'comment_owner_id' => $comment['user_id']
            ]
        );
        
        // Output buffer'ı temizle
        ob_clean();
        
        // Başarılı yanıt
        echo json_encode([
            'success' => true,
            'message' => 'Yorum başarıyla silindi.',
            'photo_id' => $comment['photo_id']
        ]);
        
    } catch (Exception $db_error) {
        $pdo->rollBack();
        throw $db_error;
    }
    
} catch (Exception $e) {
    // Output buffer'ı temizle
    ob_clean();
    
    // Hata logla
    error_log("Gallery comment delete error for user " . ($_SESSION['user_id'] ?? 'unknown') . ": " . $e->getMessage());
    
    // Hata yanıtı
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Output buffer'ı sonlandır
ob_end_flush();
?>