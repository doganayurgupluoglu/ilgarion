<?php
// src/actions/handle_gallery_like.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi

$response = ['success' => false, 'action_taken' => '', 'like_count' => 0, 'error' => 'Bilinmeyen bir hata oluştu.'];

if (!is_user_approved()) { // Sadece onaylanmış kullanıcılar beğenebilsin
    $response['error'] = 'Beğeni yapmak için giriş yapmış ve onaylanmış olmalısınız.';
    echo json_encode($response);
    exit;
}
// Ek olarak gallery.like yetkisini de kontrol et
if (isset($pdo)) {
    require_permission($pdo, 'gallery.like');
} else {
    $response['error'] = 'Veritabanı bağlantısı yapılandırılamadı (beğeni yetki kontrolü).';
    error_log("handle_gallery_like.php: PDO nesnesi bulunamadı (yetki kontrolü).");
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['photo_id'])) {
    $response['error'] = 'Geçersiz istek.';
    echo json_encode($response);
    exit;
}

$photo_id = (int)$_POST['photo_id'];
$user_id = $_SESSION['user_id'];

// Fotoğrafın var olup olmadığını kontrol et (opsiyonel ama iyi bir pratik)
try {
    $stmt_photo_check = $pdo->prepare("SELECT id FROM gallery_photos WHERE id = ?");
    $stmt_photo_check->execute([$photo_id]);
    if (!$stmt_photo_check->fetch()) {
        $response['error'] = 'Beğenilecek fotoğraf bulunamadı.';
        echo json_encode($response);
        exit;
    }
} catch (PDOException $e) {
    error_log("Galeri beğeni - fotoğraf kontrol hatası: " . $e->getMessage());
    $response['error'] = 'İşlem sırasında bir veritabanı hatası oluştu.';
    echo json_encode($response);
    exit;
}


try {
    $pdo->beginTransaction();

    // Kullanıcı bu fotoğrafı daha önce beğenmiş mi?
    $stmt_check = $pdo->prepare("SELECT id FROM gallery_photo_likes WHERE photo_id = ? AND user_id = ?");
    $stmt_check->execute([$photo_id, $user_id]);
    $existing_like = $stmt_check->fetch();

    // Güncel beğeni sayısını almak için ayrı bir sorgu
    // (UPDATE sonrası SELECT yerine, trigger veya stored procedure de düşünülebilir ama bu daha basit)
    $stmt_update_like_count_in_gallery = null;

    if ($existing_like) {
        // Zaten beğenmiş, beğeniyi geri al
        $stmt_delete = $pdo->prepare("DELETE FROM gallery_photo_likes WHERE id = ?");
        $stmt_delete->execute([$existing_like['id']]);
        
        // gallery_photos tablosundaki like_count'u azalt (eğer böyle bir sütun eklemeyi düşünürsen)
        // Şimdilik, like_count'u gallery_photo_likes'tan sayarak alacağız, gallery_photos'a ek bir sütun eklemiyoruz.
        // Eğer gallery_photos tablosuna like_count sütunu eklerseniz, aşağıdaki gibi güncelleyebilirsiniz:
        // $stmt_update_like_count_in_gallery = $pdo->prepare("UPDATE gallery_photos SET like_count = GREATEST(0, like_count - 1) WHERE id = ?");
        // $stmt_update_like_count_in_gallery->execute([$photo_id]);

        $response['action_taken'] = 'unliked';
    } else {
        // Beğenmemiş, beğeniyi ekle
        $stmt_insert = $pdo->prepare("INSERT INTO gallery_photo_likes (photo_id, user_id) VALUES (?, ?)");
        $stmt_insert->execute([$photo_id, $user_id]);

        // gallery_photos tablosundaki like_count'u artır (eğer böyle bir sütun eklemeyi düşünürsen)
        // $stmt_update_like_count_in_gallery = $pdo->prepare("UPDATE gallery_photos SET like_count = like_count + 1 WHERE id = ?");
        // $stmt_update_like_count_in_gallery->execute([$photo_id]);

        $response['action_taken'] = 'liked';
    }

    // Güncel beğeni sayısını gallery_photo_likes tablosundan sayarak al
    $stmt_new_count = $pdo->prepare("SELECT COUNT(*) FROM gallery_photo_likes WHERE photo_id = ?");
    $stmt_new_count->execute([$photo_id]);
    $response['like_count'] = (int)$stmt_new_count->fetchColumn();
    
    $pdo->commit();
    $response['success'] = true;
    unset($response['error']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Galeri fotoğrafı beğeni hatası: " . $e->getMessage());
    $response['error'] = 'İşlem sırasında bir veritabanı hatası oluştu.';
}

echo json_encode($response);
exit;
?>
