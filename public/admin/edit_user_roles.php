<?php
// public/admin/edit_user_roles.php

require_once '../../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi

// require_admin(); // Genel admin kontrolü yerine spesifik yetki
require_permission($pdo, 'admin.users.assign_roles'); // Kullanıcılara rol atama/kaldırma yetkisi

$user_id_to_edit = null;
$user_to_edit = null;
$all_system_roles = [];
$user_current_role_ids = [];

if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $user_id_to_edit = (int)$_GET['user_id'];
} else {
    $_SESSION['error_message'] = "Geçersiz kullanıcı ID'si.";
    header('Location: ' . get_auth_base_url() . '/admin/users.php');
    exit;
}

try {
    $stmt_user = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt_user->execute([$user_id_to_edit]);
    $user_to_edit = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user_to_edit) {
        $_SESSION['error_message'] = "Rolleri düzenlenecek kullanıcı bulunamadı.";
        header('Location: ' . get_auth_base_url() . '/admin/users.php');
        exit;
    }

    // Sistemdeki tüm rolleri çek (açıklama ve renk ile birlikte)
    $stmt_all_roles = $pdo->query("SELECT id, name, description, color FROM roles ORDER BY name ASC");
    $all_system_roles = $stmt_all_roles->fetchAll(PDO::FETCH_ASSOC);

    $stmt_user_roles = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
    $stmt_user_roles->execute([$user_id_to_edit]);
    $user_current_role_ids = $stmt_user_roles->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log("Kullanıcı rolleri düzenleme sayfası yükleme hatası: " . $e->getMessage());
    $_SESSION['error_message'] = "Veriler yüklenirken bir hata oluştu.";
}

$page_title = htmlspecialchars($user_to_edit['username']) . " Kullanıcısının Rollerİnİ Düzenle";

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* edit_user_roles.php için özel stiller */
.edit-roles-container {
    width: 100%;
    max-width: 800px; /* Form için daha geniş alan */
    margin: 30px auto;
    padding: 25px 30px;
    background-color: var(--charcoal);
    border: 1px solid var(--darker-gold-1);
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    color: var(--lighter-grey);
}

.edit-roles-container .page-header-form {
    text-align: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--darker-gold-2);
}
.edit-roles-container .page-header-form h1 {
    color: var(--gold);
    font-size: 1.9rem;
    margin: 0;
}
.edit-roles-container .page-header-form p {
    font-size: 1rem;
    color: var(--light-gold);
    margin-top: 5px;
}

.roles-checkbox-group-user {
    margin-bottom: 25px;
    padding: 20px;
    background-color: var(--darker-gold-2);
    border-radius: 8px;
}
.roles-checkbox-group-user legend {
    font-size: 1.2rem;
    color: var(--light-gold);
    font-weight: 600;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--darker-gold-1);
    width: 100%;
}

.role-item-user {
    display: flex; /* Checkbox, renk, ad ve açıklamayı hizalamak için */
    align-items: flex-start; /* Dikeyde üste hizala (uzun açıklamalar için) */
    margin-bottom: 12px;
    padding: 8px;
    border-radius: 4px;
    transition: background-color 0.2s ease;
}
.role-item-user:hover {
    background-color: var(--darker-gold-1);
}
.role-item-user input[type="checkbox"] {
    width: auto;
    margin-right: 12px;
    accent-color: var(--gold);
    transform: scale(1.2);
    margin-top: 3px; /* Metinle daha iyi hizalamak için */
    flex-shrink: 0;
}
.role-item-user .role-details-wrapper {
    display: flex;
    flex-direction: column;
}
.role-item-user label {
    font-size: 0.95rem;
    color: var(--lighter-grey);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    font-weight: 500; /* Rol adını biraz daha belirgin yap */
}
.role-color-indicator-user {
    display: inline-block;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    margin-right: 8px;
    vertical-align: middle;
    border: 1px solid rgba(255,255,255,0.1);
}
.role-item-user .role-description-user {
    font-size: 0.8em;
    color: var(--light-grey);
    margin-left: 0; /* Checkbox'tan sonra değil, rol adının altında */
    display: block;
    padding-left: 34px; /* Checkbox + renk + boşluk kadar */
    opacity: 0.9;
    line-height: 1.3;
}

.form-actions-user-roles {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--darker-gold-2);
    text-align: right;
    display: flex;
    justify-content: flex-end;
    gap: 15px;
}
/* .btn-primary ve .btn-secondary stilleri style.css'den */
</style>

<main class="main-content">
    <div class="container admin-container edit-roles-container">
        <div class="page-header-form">
            <h1>Kullanıcı Rollerini Düzenle</h1>
            <?php if ($user_to_edit): ?>
                <p>Kullanıcı: <strong><?php echo htmlspecialchars($user_to_edit['username']); ?></strong> (ID: <?php echo $user_to_edit['id']; ?>)</p>
            <?php endif; ?>
        </div>

        <?php require BASE_PATH . '/src/includes/admin_quick_navigation.php'; ?>

        <?php if ($user_to_edit && !empty($all_system_roles)): ?>
            <form action="/src/actions/handle_user_roles_update.php" method="POST" style="margin-top:20px;">
                <input type="hidden" name="user_id_to_update" value="<?php echo $user_to_edit['id']; ?>">
                
                <fieldset class="roles-checkbox-group-user">
                    <legend>Atanacak Roller:</legend>
                    <?php foreach ($all_system_roles as $role): ?>
                        <div class="role-item-user">
                            <input type="checkbox" 
                                   name="assigned_roles[]" 
                                   id="role_<?php echo $role['id']; ?>" 
                                   value="<?php echo $role['id']; ?>"
                                   <?php echo in_array($role['id'], $user_current_role_ids) ? 'checked' : ''; ?>
                                   <?php // Admin kendi admin rolünü kaldıramasın
                                   if ($user_id_to_edit == $_SESSION['user_id'] && $role['name'] === 'admin' && in_array($role['id'], $user_current_role_ids)) {
                                       // Eğer admin kendi admin rolünü düzenliyorsa ve bu rol zaten atanmışsa, kaldırmasını engelle
                                       // Ancak, başka bir admin, başka bir adminin rolünü kaldırabilmeli.
                                       // Bu kontrolü handle_user_roles_update.php'de daha detaylı yapmak daha iyi olabilir.
                                       // Şimdilik basit bir disabled koyabiliriz ama bu JS ile bypass edilebilir.
                                       // echo ' disabled title="Kendi admin rolünüzü kaldıramazsınız."';
                                   }
                                   ?>>
                            <div class="role-details-wrapper">
                                <label for="role_<?php echo $role['id']; ?>">
                                    <span class="role-color-indicator-user" style="background-color: <?php echo htmlspecialchars($role['color']); ?>;"></span>
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $role['name']))); // Açıklama yerine direkt adı gösterelim, daha okunaklı olur ?>
                                </label>
                                <?php if (!empty($role['description'])): ?>
                                    <small class="role-description-user"><?php echo htmlspecialchars($role['description']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </fieldset>

                <div class="form-actions-user-roles">
                    <a href="users.php" class="btn btn-secondary">İptal / Kullanıcı Listesine Dön</a>
                    <button type="submit" class="btn btn-primary">Rolleri Güncelle</button>
                </div>
            </form>
        <?php elseif (!$user_to_edit): ?>
            <p class="message error-message">Kullanıcı bilgileri yüklenemedi.</p>
        <?php else: ?>
            <p class="message info-message">Sistemde tanımlı rol bulunmamaktadır. Lütfen önce <a href="manage_roles.php">Rol Yönetimi</a> sayfasından rol oluşturun.</p>
        <?php endif; ?>
    </div>
</main>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>
