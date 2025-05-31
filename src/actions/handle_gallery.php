<?php
// src/actions/handle_gallery.php
// Bu dosya, public/actions/handle_gallery.php olarak adlandırılmıştı, src/actions altına taşımak daha mantıklı.
// Eğer eski yolu kullanıyorsanız, form action'larını ona göre güncelleyin.

require_once dirname(__DIR__) . '/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json'); // Tüm yanıtlar JSON olacak

if (!is_user_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Bu işlem için giriş yapmalısınız.']);
    exit;
}
if (!is_user_approved()) {
    echo json_encode(['success' => false, 'message' => 'Bu işlem için hesabınızın onaylanmış olması gerekmektedir.']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'upload': // Bu action artık handle_gallery_upload.php tarafından yönetiliyor.
                     // Eğer hala buradan yönetilecekse, yetki kontrolü eklenmeli.
                     // Şimdilik handle_gallery_upload.php'ye yönlendirdiğimizi varsayıyorum.
            echo json_encode(['success' => false, 'message' => 'Yükleme işlemi için geçersiz action. Lütfen handle_gallery_upload.php kullanın.']);
            exit;
            // break; // Gerekli değil, exit sonrası

        case 'delete':
            if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
                throw new Exception("Geçersiz fotoğraf ID'si.");
            }
            $image_id = (int)$_POST['id'];

            // Fotoğraf bilgilerini ve sahibini çek
            $stmt_img = $pdo->prepare("SELECT user_id, image_path FROM gallery_photos WHERE id = ?");
            $stmt_img->execute([$image_id]);
            $image_to_delete = $stmt_img->fetch(PDO::FETCH_ASSOC);

            if (!$image_to_delete) {
                throw new Exception("Silinecek fotoğraf bulunamadı.");
            }

            // Yetki Kontrolü: Ya admin ya da fotoğrafın sahibi olmalı
            $can_delete_this_specific_photo = false;
            if (has_permission($pdo, 'gallery.delete_any', $current_user_id)) { // Admin her şeyi silebilir
                $can_delete_this_specific_photo = true;
            } elseif (has_permission($pdo, 'gallery.delete_own', $current_user_id) && $image_to_delete['user_id'] == $current_user_id) { // Kullanıcı kendi fotoğrafını silebilir
                $can_delete_this_specific_photo = true;
            }

            if (!$can_delete_this_specific_photo) {
                throw new Exception("Bu fotoğrafı silme yetkiniz bulunmamaktadır.");
            }

            // Dosyayı sunucudan sil
            $filepath_to_delete = BASE_PATH . '/public/' . $image_to_delete['image_path'];
            if (strpos($image_to_delete['image_path'], 'uploads/gallery/') === 0 && file_exists($filepath_to_delete)) {
                if (!unlink($filepath_to_delete)) {
                    error_log("Galeri silme (handle_gallery.php): Dosya sunucudan silinemedi - " . $filepath_to_delete);
                    // Hata mesajı verilebilir ama DB'den silmeye devam edilebilir.
                }
            }

            // Veritabanından sil
            $stmt_delete_db = $pdo->prepare("DELETE FROM gallery_photos WHERE id = ?");
            $stmt_delete_db->execute([$image_id]);

            if ($stmt_delete_db->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Fotoğraf başarıyla silindi.']);
            } else {
                // Dosya sunucudan silinmiş olabilir ama DB'de bulunamadı (nadiren olur)
                echo json_encode(['success' => true, 'message' => 'Fotoğraf veritabanında bulunamadı ancak sunucudan silinmiş olabilir.']);
            }
            break;

        default:
            throw new Exception("Geçersiz galeri işlemi: " . htmlspecialchars($action));
    }
} catch (Exception $e) {
    error_log("Galeri işlemi hatası (handle_gallery.php): " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit; // Her zaman çıkış yap
