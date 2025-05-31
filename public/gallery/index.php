<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/functions/auth_functions.php';
require_once __DIR__ . '/../../src/functions/permission_functions.php';

// Oturum kontrolü
session_start();

// Galeri görüntüleme izni kontrolü
require_page_permission($pdo, 'gallery.view');

// Kullanıcının diğer izinlerini kontrol et
$can_upload = can_perform_action($pdo, 'gallery.upload');
$can_delete = can_perform_action($pdo, 'gallery.delete');

// Galeri resimlerini getir
try {
    $stmt = $pdo->query("
        SELECT g.*, u.username as uploader_name 
        FROM gallery g 
        LEFT JOIN users u ON g.uploaded_by = u.id 
        ORDER BY g.upload_date DESC
    ");
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Galeri resimleri getirme hatası: " . $e->getMessage());
    $images = [];
}

$page_title = "Galeri";
require_once __DIR__ . '/../../src/includes/header.php';
require_once __DIR__ . '/../../src/includes/navbar.php';
?>

<style>
.gallery-container {
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
}

.gallery-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.gallery-title {
    color: var(--gold);
    font-size: 2rem;
    margin: 0;
}

.upload-btn {
    background-color: var(--gold);
    color: var(--darker-gold-2);
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.upload-btn:hover {
    background-color: var(--light-gold);
    transform: translateY(-2px);
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.gallery-item {
    position: relative;
    background-color: var(--darker-gold-1);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.gallery-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

.gallery-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.gallery-info {
    padding: 15px;
}

.gallery-title {
    color: var(--gold);
    font-size: 1.1rem;
    margin: 0 0 10px 0;
}

.gallery-meta {
    color: var(--light-grey);
    font-size: 0.9rem;
}

.gallery-actions {
    position: absolute;
    top: 10px;
    right: 10px;
    display: flex;
    gap: 10px;
}

.delete-btn {
    background-color: rgba(220, 53, 69, 0.9);
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.delete-btn:hover {
    background-color: #dc3545;
}

.no-images {
    text-align: center;
    color: var(--light-grey);
    padding: 40px;
    background-color: var(--darker-gold-1);
    border-radius: 8px;
    margin-top: 20px;
}
</style>

<div class="gallery-container">
    <div class="gallery-header">
        <h1 class="gallery-title">Galeri</h1>
        <?php if ($can_upload): ?>
            <a href="upload.php" class="upload-btn">
                <i class="fas fa-upload"></i> Resim Yükle
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($images)): ?>
        <div class="no-images">
            <i class="fas fa-images fa-3x"></i>
            <p>Henüz hiç resim yüklenmemiş.</p>
        </div>
    <?php else: ?>
        <div class="gallery-grid">
            <?php foreach ($images as $image): ?>
                <div class="gallery-item">
                    <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                         alt="<?php echo htmlspecialchars($image['title']); ?>" 
                         class="gallery-image">
                    
                    <?php if ($can_delete): ?>
                        <div class="gallery-actions">
                            <button class="delete-btn" 
                                    onclick="deleteImage(<?php echo $image['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="gallery-info">
                        <h3 class="gallery-title"><?php echo htmlspecialchars($image['title']); ?></h3>
                        <div class="gallery-meta">
                            <p>Yükleyen: <?php echo htmlspecialchars($image['uploader_name']); ?></p>
                            <p>Tarih: <?php echo date('d.m.Y H:i', strtotime($image['upload_date'])); ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($can_delete): ?>
<script>
function deleteImage(imageId) {
    if (confirm('Bu resmi silmek istediğinizden emin misiniz?')) {
        fetch('/public/actions/handle_gallery.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=delete&id=' + imageId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Resim silinirken bir hata oluştu');
            }
        })
        .catch(error => {
            console.error('Hata:', error);
            alert('Resim silinirken bir hata oluştu');
        });
    }
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../src/includes/footer.php'; ?> 