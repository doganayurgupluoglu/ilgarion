<?php
// /articles/actions.php

// PHP hatalarını logla ama gösterme
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../src/config/database.php';
require_once '../src/functions/auth_functions.php';
require_once '../src/functions/role_functions.php';

// Session kontrolü
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// JSON header
header('Content-Type: application/json');

// Test için debug
if (!function_exists('is_user_logged_in')) {
    echo json_encode(['success' => false, 'message' => 'is_user_logged_in function not found']);
    exit;
}

// Login kontrolü
if (!is_user_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Giriş yapmanız gerekiyor.']);
    exit;
}

// POST kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek yöntemi.']);
    exit;
}

$action = $_POST['action'] ?? '';
$article_id = isset($_POST['article_id']) ? (int)$_POST['article_id'] : 0;

if ($article_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz makale ID.']);
    exit;
}

// Debug için
error_log("Actions.php - Action: $action, Article ID: $article_id, User ID: " . $_SESSION['user_id']);

try {
    switch ($action) {
        case 'toggle_like':
            handleToggleLike($pdo, $article_id);
            break;
            
        case 'record_download':
            handleRecordDownload($pdo, $article_id);
            break;
            
        case 'delete_article':
            handleDeleteArticle($pdo, $article_id);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Geçersiz aksiyon: ' . $action]);
            break;
    }
} catch (Exception $e) {
    error_log("Articles action error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu: ' . $e->getMessage()]);
}

function handleToggleLike($pdo, $article_id) {
    try {
        // Fonksiyon varlık kontrolü
        if (!function_exists('has_permission')) {
            echo json_encode(['success' => false, 'message' => 'has_permission function not found']);
            return;
        }

        // Yetki kontrolü
        if (!has_permission($pdo, 'article.like')) {
            echo json_encode(['success' => false, 'message' => 'Beğeni yetkiniz yok.']);
            return;
        }

        // Makale var mı?
        $article_check_query = "SELECT id, status FROM articles WHERE id = ?";
        $stmt = $pdo->prepare($article_check_query);
        $stmt->execute([$article_id]);
        $article = $stmt->fetch();

        if (!$article) {
            echo json_encode(['success' => false, 'message' => 'Makale bulunamadı.']);
            return;
        }

        if ($article['status'] !== 'approved') {
            echo json_encode(['success' => false, 'message' => 'Bu makale henüz onaylanmamış.']);
            return;
        }

        $user_id = $_SESSION['user_id'];
        
        // Mevcut beğeni durumunu kontrol et
        $like_check_query = "SELECT id FROM article_likes WHERE article_id = ? AND user_id = ?";
        $stmt = $pdo->prepare($like_check_query);
        $stmt->execute([$article_id, $user_id]);
        $existing_like = $stmt->fetch();

        $pdo->beginTransaction();

        if ($existing_like) {
            // Beğeniyi kaldır
            $delete_like_query = "DELETE FROM article_likes WHERE article_id = ? AND user_id = ?";
            $stmt = $pdo->prepare($delete_like_query);
            $stmt->execute([$article_id, $user_id]);
            $liked = false;
        } else {
            // Beğeni ekle
            $insert_like_query = "INSERT INTO article_likes (article_id, user_id) VALUES (?, ?)";
            $stmt = $pdo->prepare($insert_like_query);
            $stmt->execute([$article_id, $user_id]);
            $liked = true;
        }

        // Toplam beğeni sayısını al ve articles tablosunu güncelle
        $count_query = "SELECT COUNT(*) FROM article_likes WHERE article_id = ?";
        $stmt = $pdo->prepare($count_query);
        $stmt->execute([$article_id]);
        $like_count = $stmt->fetchColumn();

        // Articles tablosundaki like_count'u güncelle
        $update_count_query = "UPDATE articles SET like_count = ? WHERE id = ?";
        $stmt = $pdo->prepare($update_count_query);
        $stmt->execute([$like_count, $article_id]);

        // Audit log - sadece fonksiyon varsa
        if (function_exists('audit_log')) {
            audit_log($pdo, $user_id, $liked ? 'article_liked' : 'article_unliked', 'article', $article_id);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'liked' => $liked,
            'like_count' => (int)$like_count,
            'message' => $liked ? 'Makale beğenildi.' : 'Beğeni kaldırıldı.'
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        error_log("Like toggle error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Bir hata oluştu: ' . $e->getMessage()]);
    }
}

function handleRecordDownload($pdo, $article_id) {
    // Yetki kontrolü
    if (!has_permission($pdo, 'article.download')) {
        echo json_encode(['success' => false, 'message' => 'İndirme yetkiniz yok.']);
        return;
    }

    // Makale var mı ve dosya upload türünde mi?
    $article_check_query = "SELECT id, upload_type, status FROM articles WHERE id = ?";
    $stmt = $pdo->prepare($article_check_query);
    $stmt->execute([$article_id]);
    $article = $stmt->fetch();

    if (!$article) {
        echo json_encode(['success' => false, 'message' => 'Makale bulunamadı.']);
        return;
    }

    if ($article['status'] !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Bu makale henüz onaylanmamış.']);
        return;
    }

    if ($article['upload_type'] !== 'file_upload') {
        echo json_encode(['success' => false, 'message' => 'Bu makale dosya indirilebilir değil.']);
        return;
    }

    // Download count'u artır
    $update_query = "UPDATE articles SET download_count = download_count + 1 WHERE id = ?";
    $stmt = $pdo->prepare($update_query);
    $stmt->execute([$article_id]);

    // Audit log
    audit_log($pdo, $_SESSION['user_id'], 'article_downloaded', 'article', $article_id);

    echo json_encode(['success' => true, 'message' => 'İndirme kaydedildi.']);
}

function handleDeleteArticle($pdo, $article_id) {
    $user_id = $_SESSION['user_id'];

    // Makale bilgilerini al
    $article_query = "SELECT id, uploaded_by, title, file_path, thumbnail_path FROM articles WHERE id = ?";
    $stmt = $pdo->prepare($article_query);
    $stmt->execute([$article_id]);
    $article = $stmt->fetch();

    if (!$article) {
        echo json_encode(['success' => false, 'message' => 'Makale bulunamadı.']);
        return;
    }

    // Yetki kontrolü
    $is_owner = ($article['uploaded_by'] == $user_id);
    $can_delete = ($is_owner && has_permission($pdo, 'article.delete_own')) || 
                  has_permission($pdo, 'article.delete_all');

    if (!$can_delete) {
        echo json_encode(['success' => false, 'message' => 'Bu makaleyi silme yetkiniz yok.']);
        return;
    }

    $pdo->beginTransaction();

    try {
        // İlişkili kayıtları sil
        $delete_likes_query = "DELETE FROM article_likes WHERE article_id = ?";
        $stmt = $pdo->prepare($delete_likes_query);
        $stmt->execute([$article_id]);

        $delete_views_query = "DELETE FROM article_views WHERE article_id = ?";
        $stmt = $pdo->prepare($delete_views_query);
        $stmt->execute([$article_id]);

        $delete_visibility_query = "DELETE FROM article_visibility_roles WHERE article_id = ?";
        $stmt = $pdo->prepare($delete_visibility_query);
        $stmt->execute([$article_id]);

        // Makaleyi sil
        $delete_article_query = "DELETE FROM articles WHERE id = ?";
        $stmt = $pdo->prepare($delete_article_query);
        $stmt->execute([$article_id]);

        // Dosyaları sil
        if ($article['file_path'] && file_exists('../' . ltrim($article['file_path'], '/'))) {
            unlink('../' . ltrim($article['file_path'], '/'));
        }

        if ($article['thumbnail_path'] && file_exists('../' . ltrim($article['thumbnail_path'], '/'))) {
            unlink('../' . ltrim($article['thumbnail_path'], '/'));
        }

        // Audit log
        audit_log($pdo, $user_id, 'article_deleted', 'article', $article_id, null, [
            'title' => $article['title'],
            'was_owner' => $is_owner
        ]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Makale başarıyla silindi.']);

    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Article delete error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Silme işlemi sırasında hata oluştu.']);
    }
}
?>