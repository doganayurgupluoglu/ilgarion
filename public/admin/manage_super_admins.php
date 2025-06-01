<?php
// public/admin/manage_super_admins.php

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Yetki kontrolü - hem süper admin olmak hem de manage yetkisi gerekli
if (!is_super_admin($pdo)) {
    $_SESSION['error_message'] = "Bu sayfaya sadece süper adminler erişebilir.";
    header('Location: ' . get_auth_base_url() . '/admin/manage_roles.php');
    exit;
}

// Ek yetki kontrolü - manage yetkisi
require_permission($pdo, 'admin.super_admin.manage');

$page_title = "Süper Admin Yönetimi";

// CSRF token kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = "Güvenlik hatası. Lütfen tekrar deneyin.";
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// POST işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        if ($action === 'add_super_admin') {
            $user_id_to_add = (int)$_POST['user_id'];
            
            // Kullanıcının var olup olmadığını kontrol et
            $check_user_query = "SELECT username FROM users WHERE id = :user_id AND status = 'approved'";
            $check_user_params = [':user_id' => $user_id_to_add];
            $stmt_check = execute_safe_query($pdo, $check_user_query, $check_user_params);
            $user = $stmt_check->fetch();
            
            if (!$user) {
                $_SESSION['error_message'] = "Geçersiz kullanıcı ID'si veya kullanıcı onaylanmamış.";
            } else {
                // Mevcut super admin listesini al
                $current_list = get_system_setting($pdo, 'super_admin_users', []);
                if (!in_array($user_id_to_add, $current_list)) {
                    $current_list[] = $user_id_to_add;
                    
                    if (set_system_setting($pdo, 'super_admin_users', $current_list, 'json', 
                                          'Süper admin kullanıcı ID listesi', $_SESSION['user_id'])) {
                        $_SESSION['success_message'] = "Kullanıcı '{$user['username']}' süper admin olarak eklendi.";
                        
                        // Audit log
                        audit_log($pdo, $_SESSION['user_id'], 'super_admin_added', 'user', $user_id_to_add, null, [
                            'added_user_id' => $user_id_to_add,
                            'added_username' => $user['username'],
                            'action_user_id' => $_SESSION['user_id']
                        ]);
                    } else {
                        $_SESSION['error_message'] = "Süper admin eklenirken hata oluştu.";
                    }
                } else {
                    $_SESSION['info_message'] = "Bu kullanıcı zaten süper admin.";
                }
            }
            
        } elseif ($action === 'remove_super_admin') {
            $user_id_to_remove = (int)$_POST['user_id'];
            
            // Kendini çıkaramaz
            if ($user_id_to_remove == $_SESSION['user_id']) {
                $_SESSION['error_message'] = "Kendinizi süper admin listesinden çıkaramazsınız.";
            } else {
                // Kullanıcı adını audit için al
                $user_query = "SELECT username FROM users WHERE id = :user_id";
                $stmt_user = execute_safe_query($pdo, $user_query, [':user_id' => $user_id_to_remove]);
                $removed_user = $stmt_user->fetch();
                
                // Mevcut super admin listesini al
                $current_list = get_system_setting($pdo, 'super_admin_users', []);
                $new_list = array_values(array_filter($current_list, function($id) use ($user_id_to_remove) {
                    return $id != $user_id_to_remove;
                }));
                
                if (count($new_list) < count($current_list)) {
                    if (set_system_setting($pdo, 'super_admin_users', $new_list, 'json', 
                                          'Süper admin kullanıcı ID listesi', $_SESSION['user_id'])) {
                        $_SESSION['success_message'] = "Kullanıcı süper admin listesinden çıkarıldı.";
                        
                        // Audit log
                        audit_log($pdo, $_SESSION['user_id'], 'super_admin_removed', 'user', $user_id_to_remove, null, [
                            'removed_user_id' => $user_id_to_remove,
                            'removed_username' => $removed_user['username'] ?? 'Unknown',
                            'action_user_id' => $_SESSION['user_id']
                        ]);
                    } else {
                        $_SESSION['error_message'] = "Süper admin çıkarılırken hata oluştu.";
                    }
                } else {
                    $_SESSION['info_message'] = "Bu kullanıcı zaten süper admin değil.";
                }
            }
        }
    } catch (Exception $e) {
        error_log("Süper admin yönetimi hatası: " . $e->getMessage());
        $_SESSION['error_message'] = "İşlem sırasında bir hata oluştu.";
        
        // Güvenlik ihlali audit log
        audit_log($pdo, $_SESSION['user_id'], 'super_admin_management_error', 'system', null, null, [
            'action' => $action,
            'error' => $e->getMessage(),
            'user_id' => $_SESSION['user_id']
        ]);
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Mevcut süper adminleri getir
$super_admin_ids = get_system_setting($pdo, 'super_admin_users', []);
$super_admins = [];

if (!empty($super_admin_ids)) {
    $placeholders = str_repeat('?,', count($super_admin_ids) - 1) . '?';
    $query = "SELECT id, username, email, ingame_name, status FROM users WHERE id IN ($placeholders) ORDER BY username ASC";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($super_admin_ids);
        $super_admins = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Süper admin listesi getirme hatası: " . $e->getMessage());
    }
}

// Tüm onaylı kullanıcıları getir (dropdown için)
$all_users = [];
try {
    $query = "SELECT id, username, email, ingame_name FROM users WHERE status = 'approved' ORDER BY username ASC";
    $stmt = execute_safe_query($pdo, $query);
    $all_users = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Kullanıcı listesi getirme hatası: " . $e->getMessage());
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
.super-admin-container {
    width: 100%;
    max-width: 1600px;
    margin: 30px auto;
    padding: 25px;
    font-family: var(--font);
}

.page-header-super-admin {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--darker-gold-1);
}

.page-title-super-admin {
    color: var(--gold);
    font-size: 2rem;
    margin: 0;
}

.super-admin-warning {
    background: linear-gradient(135deg, #ff6b6b, #ee5a24);
    color: white;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    border-left: 5px solid #c0392b;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
}

.super-admin-warning i {
    font-size: 1.2rem;
}

.current-super-admins {
    background-color: var(--charcoal);
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 30px;
    border: 1px solid var(--darker-gold-1);
}

.current-super-admins h3 {
    color: var(--light-gold);
    margin: 0 0 20px 0;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.super-admin-list {
    display: grid;
    gap: 15px;
}

.super-admin-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background-color: var(--darker-gold-2);
    border-radius: 6px;
    border-left: 4px solid var(--gold);
}

.super-admin-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.super-admin-username {
    font-weight: 600;
    color: var(--gold);
    font-size: 1.1rem;
}

.super-admin-details {
    font-size: 0.9rem;
    color: var(--lighter-grey);
}

.super-admin-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.current-user-badge {
    background-color: var(--turquase);
    color: var(--black);
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: bold;
}

.add-super-admin-form {
    background-color: var(--charcoal);
    padding: 25px;
    border-radius: 8px;
    border: 1px solid var(--darker-gold-1);
}

.add-super-admin-form h3 {
    color: var(--light-gold);
    margin: 0 0 20px 0;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-group-super-admin {
    margin-bottom: 20px;
}

.form-group-super-admin label {
    display: block;
    color: var(--lighter-grey);
    margin-bottom: 8px;
    font-weight: 500;
}

.form-control-super-admin {
    width: 100%;
    padding: 12px 15px;
    background-color: var(--grey);
    border: 1px solid var(--darker-gold-1);
    border-radius: 6px;
    color: var(--white);
    font-size: 1rem;
    font-family: var(--font);
}

.form-control-super-admin:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px var(--transparent-gold);
}

.form-actions-super-admin {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 20px;
}

.no-super-admins {
    text-align: center;
    padding: 40px 20px;
    color: var(--light-grey);
    font-style: italic;
}

.permission-info {
    background-color: var(--transparent-gold);
    color: var(--light-gold);
    padding: 12px 15px;
    border-radius: 5px;
    font-size: 0.9rem;
    margin-bottom: 20px;
    border: 1px solid var(--gold);
    display: flex;
    align-items: center;
    gap: 10px;
}

.permission-info i {
    color: var(--gold);
}

/* Responsive design */
@media (max-width: 768px) {
    .super-admin-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .super-admin-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>

<main class="main-content">
    <div class="container admin-container super-admin-container">
        <div class="page-header-super-admin">
            <h1 class="page-title-super-admin"><?php echo htmlspecialchars($page_title); ?></h1>
            <div>
                <a href="audit_log.php" class="btn btn-sm btn-info" style="margin-right: 10px;">
                    <i class="fas fa-clipboard-list"></i> Audit Log
                </a>
                <a href="manage_roles.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Rol Yönetimine Dön
                </a>
            </div>
        </div>

        <?php require BASE_PATH . '/src/includes/admin_quick_navigation.php'; ?>

        <div class="permission-info">
            <i class="fas fa-info-circle"></i>
            <span>Bu sayfayı görüntülemek için 'admin.super_admin.manage' yetkisine sahip olmanız gerekmektedir.</span>
        </div>

        <div class="super-admin-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>DİKKAT:</strong> Süper adminler tüm sistem yetkilerine sahiptir ve rol kısıtlamalarını bypass ederler. 
                Bu listeyi sadece tamamen güvendiğiniz kişilerle paylaşın. Tüm işlemler audit log'a kaydedilir.
            </div>
        </div>

        <!-- Mevcut Süper Adminler -->
        <div class="current-super-admins">
            <h3><i class="fas fa-crown"></i>Mevcut Süper Adminler (<?php echo count($super_admins); ?>)</h3>
            
            <?php if (empty($super_admins)): ?>
                <div class="no-super-admins">
                    <i class="fas fa-info-circle"></i> Henüz tanımlanmış süper admin bulunmuyor.
                </div>
            <?php else: ?>
                <div class="super-admin-list">
                    <?php foreach ($super_admins as $admin): ?>
                        <div class="super-admin-item">
                            <div class="super-admin-info">
                                <div class="super-admin-username">
                                    <?php echo htmlspecialchars($admin['username']); ?>
                                    <?php if ($admin['id'] == $_SESSION['user_id']): ?>
                                        <span class="current-user-badge">SİZ</span>
                                    <?php endif; ?>
                                </div>
                                <div class="super-admin-details">
                                    <?php echo htmlspecialchars($admin['email']); ?> | 
                                    Oyun İçi: <?php echo htmlspecialchars($admin['ingame_name'] ?: 'Belirtilmemiş'); ?> |
                                    ID: <?php echo $admin['id']; ?>
                                </div>
                            </div>
                            <div class="super-admin-actions">
                                <?php if ($admin['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('<?php echo htmlspecialchars($admin['username']); ?> kullanıcısını süper admin listesinden çıkarmak istediğinizden emin misiniz?\n\nBu işlem audit log\'a kaydedilecektir.');">
                                        <input type="hidden" name="action" value="remove_super_admin">
                                        <input type="hidden" name="user_id" value="<?php echo $admin['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-user-minus"></i> Çıkar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-secondary" disabled 
                                            title="Kendinizi süper admin listesinden çıkaramazsınız">
                                        <i class="fas fa-lock"></i> Korumalı
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Yeni Süper Admin Ekleme -->
        <div class="add-super-admin-form">
            <h3><i class="fas fa-user-plus"></i>Yeni Süper Admin Ekle</h3>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_super_admin">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="form-group-super-admin">
                    <label for="user_id">Kullanıcı Seçin:</label>
                    <select name="user_id" id="user_id" class="form-control-super-admin" required>
                        <option value="">-- Kullanıcı Seçin --</option>
                        <?php foreach ($all_users as $user): ?>
                            <?php if (!in_array($user['id'], $super_admin_ids)): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?> 
                                    (<?php echo htmlspecialchars($user['ingame_name'] ?: 'Oyun adı yok'); ?>) - 
                                    ID: <?php echo $user['id']; ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--light-grey); font-size: 0.85rem; margin-top: 5px; display: block;">
                        Sadece onaylanmış kullanıcılar listelenir. Zaten süper admin olanlar gösterilmez.
                    </small>
                </div>
                
                <div class="form-actions-super-admin">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-crown"></i> Süper Admin Yap
                    </button>
                </div>
            </form>
        </div>

        <!-- Bilgi Kutusu -->
        <div style="margin-top: 30px; padding: 20px; background-color: var(--darker-gold-2); border-radius: 8px;">
            <h4 style="color: var(--gold); margin: 0 0 15px 0;">Süper Admin Yetkileri ve Güvenlik:</h4>
            <ul style="margin: 0; padding-left: 20px; color: var(--lighter-grey);">
                <li><strong>Tüm sistem yetkilerine sınırsız erişim</strong> - Normal rol kısıtlamalarını bypass eder</li>
                <li><strong>Rol yönetimi:</strong> Diğer süper adminleri yönetme, tüm rolleri düzenleme</li>
                <li><strong>Sistem ayarları:</strong> Kritik sistem ayarlarını değiştirme</li>
                <li><strong>Audit log erişimi:</strong> Tüm sistem işlemlerini görüntüleme ve analiz etme</li>
                <li><strong>Güvenlik yönetimi:</strong> Güvenlik ayarları ve kullanıcı erişim kontrolü</li>
                <li><strong>Tam veritabanı erişimi:</strong> SQL injection koruması olmaksızın veri yönetimi</li>
            </ul>
            
            <div style="margin-top: 15px; padding: 10px; background-color: var(--transparent-red); border-radius: 4px;">
                <strong style="color: var(--red);">⚠️ Güvenlik Uyarısı:</strong>
                <span style="color: var(--lighter-grey); font-size: 0.9rem;">
                    Süper admin yetkisi sadece sistem yöneticileri ve tamamen güvenilir kişilere verilmelidir. 
                    Tüm süper admin işlemleri audit log'a kaydedilir ve izlenir.
                </span>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form submission loading state
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> İşleniyor...';
                
                // Re-enable after timeout (safety net)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 10000);
            }
        });
    });
    
    // Enhanced security check
    const sensitiveActions = document.querySelectorAll('form[onsubmit*="confirm"]');
    sensitiveActions.forEach(form => {
        form.addEventListener('submit', function(e) {
            const username = this.querySelector('input[name="user_id"]').value;
            const userText = this.closest('.super-admin-item')?.querySelector('.super-admin-username')?.textContent.trim();
            
            if (userText && !confirm(`UYARI: ${userText} kullanıcısını süper admin listesinden çıkarmak istediğinizden EMİN misiniz?\n\nBu işlem:\n- Kullanıcının tüm süper admin yetkilerini kaldırır\n- Audit log'a kaydedilir\n- Geri alınamaz\n\nDevam etmek için OK'e tıklayın.`)) {
                e.preventDefault();
                return false;
            }
        });
    });
});
</script>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>