<?php
// public/admin/gallery.php

// Hata gösterimini aktif et (sorun çözülünce kaldırılabilir)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol ve yetki fonksiyonları
require_once BASE_PATH . '/src/functions/formatting_functions.php'; // render_user_info_with_popover için

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Oturum ve rol geçerliliğini kontrol et
if (is_user_logged_in()) {
    if (function_exists('check_user_session_validity')) {
        check_user_session_validity();
    }
}


$page_title = "Galeri Yönetimi (Admin)";
$all_gallery_photos = [];
$user_filter = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : null;

try {
    $sql = "SELECT
                gp.id AS photo_id,
                gp.image_path,
                gp.description AS photo_description,
                gp.uploaded_at,
                gp.user_id AS uploader_id,
                u.username AS uploader_username
            FROM gallery_photos gp
            JOIN users u ON gp.user_id = u.id";

    $params = [];
    if ($user_filter) {
        $sql .= " WHERE gp.user_id = :user_id";
        $params[':user_id'] = $user_filter;
    }
    $sql .= " ORDER BY gp.uploaded_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_gallery_photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Admin galeri listeleme hatası: " . $e->getMessage());
    $_SESSION['error_message'] = "Galeri fotoğrafları listelenirken bir veritabanı hatası oluştu.";
}

require_once BASE_PATH . '/src/includes/header.php'; // Bu header içinde <head> etiketi ve CSS dosyasının linki olmalı
require_once BASE_PATH . '/src/includes/navbar.php'; //
?>

<main class="main-content">
    <div class="container admin-container">
        <div class="page-header">
            <h2 class="page-title"><?php echo htmlspecialchars($page_title); ?> (<?php echo count($all_gallery_photos); ?> Fotoğraf)</h2>
            <a href="<?php echo get_auth_base_url(); ?>/upload_gallery_photo.php" class="btn btn-info btn-upload-gallery">+ Yeni Fotoğraf Yükle (Galeri)</a>
        </div>
           <?php
        // Hızlı Yönetim Linklerini Dahil Et
        require_once BASE_PATH . '/src/includes/admin_quick_navigation.php';
        ?>
        <?php if (empty($all_gallery_photos) && $user_filter): ?>
            <p class="info-message">Bu kullanıcıya ait fotoğraf bulunmamaktadır veya kullanıcı ID'si geçersiz.</p>
        <?php elseif (empty($all_gallery_photos)): ?>
             <p class="info-message">Galeride hiç fotoğraf bulunmamaktadır.</p>
        <?php else: ?>
            <table class="gallery-table">
                <thead class="table-header-row"> <tr>
                        <th class="th-preview">Önizleme</th>
                        <th>ID</th>
                        <th class="th-description">Açıklama</th>
                        <th>Yükleyen</th>
                        <th>Yüklenme Tarihi</th>
                        <th class="th-actions">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_gallery_photos as $photo): ?>
                        <tr>
                            <td class="td-preview">
                                <a href="/public/<?php echo htmlspecialchars($photo['image_path']); ?>" target="_blank">
                                    <img src="/public/<?php echo htmlspecialchars($photo['image_path']); ?>" alt="Galeri Fotoğrafı" class="gallery-thumbnail">
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($photo['photo_id']); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($photo['photo_description'] ?: '- Yok -')); ?></td>
                            <td>
                                <a href="users.php?search_term=<?php echo htmlspecialchars($photo['uploader_username']); ?>">
                                    <?php echo htmlspecialchars($photo['uploader_username']); ?>
                                </a> (ID: <?php echo $photo['uploader_id']; ?>)
                            </td>
                            <td><?php echo date('d M Y, H:i', strtotime($photo['uploaded_at'])); ?></td>
                            <td class="td-actions">
                                <a href="edit_gallery_photo.php?photo_id=<?php echo $photo['photo_id']; ?>" class="btn btn-sm btn-primary" style="margin-right: 5px;">Düzenle</a>
                                <form action="../../src/actions/handle_admin_gallery_actions.php" method="POST" class="inline-form" onsubmit="return confirm('Bu fotoğrafı KALICI OLARAK galeriden silmek istediğinizden emin misiniz?');">
                                    <input type="hidden" name="photo_id" value="<?php echo $photo['photo_id']; ?>">
                                    <input type="hidden" name="image_path" value="<?php echo htmlspecialchars($photo['image_path']); ?>">
                                    <input type="hidden" name="action" value="delete_photo">
                                    <button type="submit" class="btn btn-sm btn-danger">Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<?php
require_once BASE_PATH . '/src/includes/footer.php'; //
?>
