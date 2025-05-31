<?php
// src/actions/handle_guide_like.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları

$response = ['success' => false, 'action_taken' => '', 'like_count' => 0, 'error' => 'Bilinmeyen bir hata oluştu.'];

// Yetki kontrolü: Onaylı kullanıcı ve 'guide.like' yetkisi
if (!is_user_approved()) {
    $response['error'] = 'Beğeni yapmak için giriş yapmış ve onaylanmış olmalısınız.';
    echo json_encode($response);
    exit;
}
// $pdo global scope'ta olmalı veya bu fonksiyona parametre olarak geçilmeli.
// require_permission fonksiyonu $pdo'ya ihtiyaç duyar.
if (!isset($pdo)) { // $pdo'nun varlığını kontrol et
    $response['error'] = 'Veritabanı bağlantı hatası.';
    error_log("handle_guide_like.php: PDO bağlantısı bulunamadı.");
    echo json_encode($response);
    exit;
}
if (!has_permission($pdo, 'guide.like', $_SESSION['user_id'])) {
    $response['error'] = 'Rehberleri beğenme yetkiniz bulunmamaktadır.';
    echo json_encode($response);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['guide_id'])) {
    $response['error'] = 'Geçersiz istek.';
    echo json_encode($response);
    exit;
}

$guide_id = (int)$_POST['guide_id'];
$user_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    $stmt_check = $pdo->prepare("SELECT id FROM guide_likes WHERE guide_id = ? AND user_id = ?");
    $stmt_check->execute([$guide_id, $user_id]);
    $existing_like = $stmt_check->fetch();

    if ($existing_like) {
        $stmt_delete = $pdo->prepare("DELETE FROM guide_likes WHERE id = ?");
        $stmt_delete->execute([$existing_like['id']]);
        $stmt_update_count = $pdo->prepare("UPDATE guides SET like_count = GREATEST(0, like_count - 1) WHERE id = ?");
        $stmt_update_count->execute([$guide_id]);
        $response['action_taken'] = 'unliked';
    } else {
        $stmt_insert = $pdo->prepare("INSERT INTO guide_likes (guide_id, user_id) VALUES (?, ?)");
        $stmt_insert->execute([$guide_id, $user_id]);
        $stmt_update_count = $pdo->prepare("UPDATE guides SET like_count = like_count + 1 WHERE id = ?");
        $stmt_update_count->execute([$guide_id]);
        $response['action_taken'] = 'liked';
    }

    $stmt_new_count = $pdo->prepare("SELECT like_count FROM guides WHERE id = ?");
    $stmt_new_count->execute([$guide_id]);
    $response['like_count'] = (int)$stmt_new_count->fetchColumn();

    $pdo->commit();
    $response['success'] = true;
    unset($response['error']);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Rehber beğeni hatası: " . $e->getMessage());
    $response['error'] = 'İşlem sırasında bir veritabanı hatası oluştu.';
}

echo json_encode($response);
exit;
