<?php
// /articles/download.php

require_once '../src/config/database.php';
require_once '../src/functions/auth_functions.php';
require_once '../src/functions/role_functions.php';

// Session kontrolü
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Login kontrolü
if (!is_user_logged_in()) {
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Download yetkisi kontrolü
if (!has_permission($pdo, 'article.download')) {
    header('Location: /articles/?error=no_permission');
    exit;
}

// Article ID kontrolü
$article_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($article_id <= 0) {
    header('Location: /articles/?error=invalid_id');
    exit;
}

try {
    // Makale bilgilerini al
    $article_query = "
        SELECT id, title, file_name, file_path, file_size, mime_type, upload_type, status, visibility
        FROM articles 
        WHERE id = ?
    ";
    $stmt = $pdo->prepare($article_query);
    $stmt->execute([$article_id]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$article) {
        header('Location: /articles/?error=not_found');
        exit;
    }

    // Status kontrolü
    if ($article['status'] !== 'approved') {
        header('Location: /articles/?error=not_approved');
        exit;
    }

    // Upload type kontrolü
    if ($article['upload_type'] !== 'file_upload') {
        header('Location: /articles/view.php?id=' . $article_id . '&error=not_downloadable');
        exit;
    }

    // Görünürlük kontrolü
    $can_view = false;
    
    if ($article['visibility'] === 'public') {
        $can_view = true;
    } elseif ($article['visibility'] === 'members_only') {
        $can_view = true; // Zaten login kontrolü yapıldı
    } elseif ($article['visibility'] === 'specific_roles') {
        // Kullanıcının bu makaleyi görebileceği rolleri kontrol et
        $role_check_query = "
            SELECT COUNT(*) 
            FROM article_visibility_roles avr
            JOIN user_roles ur ON avr.role_id = ur.role_id
            WHERE avr.article_id = ? AND ur.user_id = ?
        ";
        $stmt = $pdo->prepare($role_check_query);
        $stmt->execute([$article_id, $_SESSION['user_id']]);
        $can_view = $stmt->fetchColumn() > 0;
    }

    // Admin/moderator her zaman indirebilir
    if (has_permission($pdo, 'article.view_all')) {
        $can_view = true;
    }

    if (!$can_view) {
        header('Location: /articles/?error=access_denied');
        exit;
    }

    // Dosya yolu kontrolü
    $file_path = '../' . ltrim($article['file_path'], '/');
    
    if (!file_exists($file_path)) {
        header('Location: /articles/view.php?id=' . $article_id . '&error=file_not_found');
        exit;
    }

    // Download count artır (AJAX ile de arttırılıyor ama güvenlik için burada da)
    $update_query = "UPDATE articles SET download_count = download_count + 1 WHERE id = ?";
    $stmt = $pdo->prepare($update_query);
    $stmt->execute([$article_id]);

    // Audit log
    audit_log($pdo, $_SESSION['user_id'], 'article_downloaded', 'article', $article_id, null, [
        'file_name' => $article['file_name'],
        'file_size' => $article['file_size']
    ]);

    // Dosya indirme
    $safe_filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $article['file_name']);
    
    // Headers ayarla
    header('Content-Type: ' . $article['mime_type']);
    header('Content-Disposition: attachment; filename="' . $safe_filename . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Dosyayı chunk'lar halinde oku (büyük dosyalar için)
    $chunk_size = 8192; // 8KB chunks
    $handle = fopen($file_path, 'rb');
    
    if ($handle === false) {
        header('HTTP/1.1 500 Internal Server Error');
        echo "Dosya okunamadı.";
        exit;
    }

    // Output buffering'i temizle
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Dosyayı chunk'lar halinde gönder
    while (!feof($handle)) {
        $chunk = fread($handle, $chunk_size);
        echo $chunk;
        flush();
    }
    
    fclose($handle);
    exit;

} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    header('Location: /articles/view.php?id=' . $article_id . '&error=download_failed');
    exit;
}
?>