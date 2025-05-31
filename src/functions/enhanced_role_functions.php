<?php
// src/functions/enhanced_role_functions.php

/**
 * İçeriğin görünürlük ayarlarına göre kullanıcının erişim yetkisini kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param array $content İçerik bilgileri (visibility, is_public_no_auth, is_members_only vb.)
 * @param array $content_role_ids İçeriğe atanmış rol ID'leri (eğer faction_only ise)
 * @param int|null $user_id Kullanıcı ID (null ise session'dan alınır)
 * @return bool Erişim yetkisi var mı?
 */
function can_user_access_content(PDO $pdo, array $content, array $content_role_ids = [], ?int $user_id = null): bool {
    // Kullanıcı ID'sini belirle
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            $user_id = null; // Misafir kullanıcı
        } else {
            $user_id = $_SESSION['user_id'];
        }
    }

    // Admin kontrolü - adminler her şeyi görebilir
    if ($user_id && has_permission($pdo, 'admin.panel.access', $user_id)) {
        return true;
    }

    // Herkese açık içerik
    if (isset($content['is_public_no_auth']) && $content['is_public_no_auth']) {
        return true;
    }

    // Eğer visibility sütunu varsa onu kullan, yoksa bayrakları kontrol et
    $visibility = $content['visibility'] ?? null;
    
    if ($visibility === 'public' || (isset($content['is_public_no_auth']) && $content['is_public_no_auth'])) {
        return true;
    }

    // Giriş yapmamış kullanıcılar için
    if (!$user_id) {
        return false;
    }

    // Onaylanmış kullanıcı kontrolü
    if (!is_user_approved()) {
        return false;
    }

    // Private içerik - sadece oluşturan ve adminler
    if ($visibility === 'private') {
        return isset($content['user_id']) && $content['user_id'] == $user_id;
    }

    // Members only - tüm onaylı üyeler
    if ($visibility === 'members_only' || (isset($content['is_members_only']) && $content['is_members_only'])) {
        return true;
    }

    // Faction only - belirli rollere sahip olanlar
    if ($visibility === 'faction_only' || (!empty($content_role_ids))) {
        if (empty($content_role_ids)) {
            return true; // Rol atanmamışsa tüm üyeler görebilir
        }

        // Kullanıcının rollerini kontrol et
        $user_roles = get_user_roles($pdo, $user_id);
        $user_role_ids = array_column($user_roles, 'id');
        
        return !empty(array_intersect($user_role_ids, $content_role_ids));
    }

    // Varsayılan olarak erişim ver (members_only gibi davran)
    return true;
}

/**
 * Sayfa düzeyinde yetki kontrolü yapar ve gerekirse yönlendirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string|array $required_permissions Gerekli yetki(ler)
 * @param string $error_message Hata mesajı
 * @param string|null $redirect_url Yönlendirme URL'si
 */
function require_page_permission(PDO $pdo, $required_permissions, string $error_message = null, ?string $redirect_url = null): void {
    if (!is_user_logged_in()) {
        $_SESSION['error_message'] = "Bu sayfayı görüntülemek için giriş yapmalısınız.";
        header('Location: ' . get_auth_base_url() . '/login.php');
        exit;
    }

    if (!is_user_approved()) {
        $_SESSION['error_message'] = "Bu sayfayı görüntülemek için hesabınızın onaylanmış olması gerekmektedir.";
        header('Location: ' . get_auth_base_url() . '/index.php');
        exit;
    }

    $permissions = is_array($required_permissions) ? $required_permissions : [$required_permissions];
    $has_permission = false;

    foreach ($permissions as $permission) {
        if (has_permission($pdo, $permission)) {
            $has_permission = true;
            break;
        }
    }

    if (!$has_permission) {
        $_SESSION['error_message'] = $error_message ?? "Bu sayfaya erişim yetkiniz bulunmamaktadır.";
        header('Location: ' . $redirect_url ?? get_auth_base_url() . '/index.php');
        exit;
    }
}

/**
 * İçerik oluşturma yetkisini kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $content_type İçerik türü (event, guide, gallery, discussion)
 * @return bool
 */
function can_user_create_content(PDO $pdo, string $content_type): bool {
    if (!is_user_approved()) {
        return false;
    }

    $permission_map = [
        'event' => 'event.create',
        'guide' => 'guide.create',
        'gallery' => 'gallery.upload',
        'discussion_topic' => 'discussion.topic.create',
        'discussion_post' => 'discussion.post.create',
        'loadout' => 'loadout.manage_sets'
    ];

    $permission = $permission_map[$content_type] ?? null;
    if (!$permission) {
        return false;
    }

    return has_permission($pdo, $permission);
}

/**
 * İçerik düzenleme yetkisini kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $content_type İçerik türü
 * @param int $content_owner_id İçeriğin sahibinin ID'si
 * @param int|null $user_id Kontrol edilecek kullanıcı ID'si
 * @return bool
 */
function can_user_edit_content(PDO $pdo, string $content_type, int $content_owner_id, ?int $user_id = null): bool {
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $user_id = $_SESSION['user_id'];
    }

    // Kendi içeriğini düzenleyebilir mi?
    $own_permission_map = [
        'event' => 'event.edit_own',
        'guide' => 'guide.edit_own',
        'discussion_topic' => 'discussion.topic.edit_own',
        'discussion_post' => 'discussion.post.edit_own'
    ];

    // Herhangi bir içeriği düzenleyebilir mi? (admin/moderatör)
    $all_permission_map = [
        'event' => 'event.edit_all',
        'guide' => 'guide.edit_all',
        'discussion_topic' => 'discussion.topic.edit_all',
        'discussion_post' => 'discussion.post.edit_all',
        'gallery' => 'gallery.delete_any' // Galeri için delete_any kullanılıyor
    ];

    // Önce "tümünü düzenleme" yetkisini kontrol et
    $all_permission = $all_permission_map[$content_type] ?? null;
    if ($all_permission && has_permission($pdo, $all_permission, $user_id)) {
        return true;
    }

    // Sonra "kendi içeriğini düzenleme" yetkisini kontrol et
    $own_permission = $own_permission_map[$content_type] ?? null;
    if ($own_permission && $content_owner_id == $user_id && has_permission($pdo, $own_permission, $user_id)) {
        return true;
    }

    return false;
}

/**
 * İçerik silme yetkisini kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $content_type İçerik türü
 * @param int $content_owner_id İçeriğin sahibinin ID'si
 * @param int|null $user_id Kontrol edilecek kullanıcı ID'si
 * @return bool
 */
function can_user_delete_content(PDO $pdo, string $content_type, int $content_owner_id, ?int $user_id = null): bool {
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $user_id = $_SESSION['user_id'];
    }

    // Kendi içeriğini silebilir mi?
    $own_permission_map = [
        'event' => 'event.delete_own',
        'guide' => 'guide.delete_own',
        'gallery' => 'gallery.delete_own',
        'discussion_topic' => 'discussion.topic.delete_own',
        'discussion_post' => 'discussion.post.delete_own'
    ];

    // Herhangi bir içeriği silebilir mi? (admin/moderatör)
    $all_permission_map = [
        'event' => 'event.delete_all',
        'guide' => 'guide.delete_all',
        'gallery' => 'gallery.delete_any',
        'discussion_topic' => 'discussion.topic.delete_all',
        'discussion_post' => 'discussion.post.delete_all'
    ];

    // Önce "tümünü silme" yetkisini kontrol et
    $all_permission = $all_permission_map[$content_type] ?? null;
    if ($all_permission && has_permission($pdo, $all_permission, $user_id)) {
        return true;
    }

    // Sonra "kendi içeriğini silme" yetkisini kontrol et
    $own_permission = $own_permission_map[$content_type] ?? null;
    if ($own_permission && $content_owner_id == $user_id && has_permission($pdo, $own_permission, $user_id)) {
        return true;
    }

    return false;
}

/**
 * Belirli bir içeriğin görünürlük rollerini çeker
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $content_type İçerik türü
 * @param int $content_id İçerik ID'si
 * @return array Rol ID'leri
 */
function get_content_visibility_roles(PDO $pdo, string $content_type, int $content_id): array {
    $table_map = [
        'event' => 'event_visibility_roles',
        'guide' => 'guide_visibility_roles',
        'gallery' => 'gallery_photo_visibility_roles',
        'discussion' => 'discussion_topic_visibility_roles',
        'loadout' => 'loadout_set_visibility_roles'
    ];

    $table = $table_map[$content_type] ?? null;
    if (!$table) {
        return [];
    }

    $id_column_map = [
        'event' => 'event_id',
        'guide' => 'guide_id',
        'gallery' => 'photo_id',
        'discussion' => 'topic_id',
        'loadout' => 'set_id'
    ];

    $id_column = $id_column_map[$content_type] ?? 'content_id';

    try {
        $stmt = $pdo->prepare("SELECT role_id FROM {$table} WHERE {$id_column} = ?");
        $stmt->execute([$content_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Görünürlük rolleri çekme hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Kullanıcının belirli bir içerik türünde hangi eylemleri yapabileceğini döndürür
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $content_type İçerik türü
 * @param int|null $content_owner_id İçerik sahibi (düzenleme/silme için)
 * @return array Yapılabilir eylemler
 */
function get_user_content_actions(PDO $pdo, string $content_type, ?int $content_owner_id = null): array {
    if (!is_user_approved()) {
        return [];
    }

    $actions = [];

    // Oluşturma yetkisi
    if (can_user_create_content($pdo, $content_type)) {
        $actions[] = 'create';
    }

    // Düzenleme yetkisi (içerik sahibi gerekli)
    if ($content_owner_id && can_user_edit_content($pdo, $content_type, $content_owner_id)) {
        $actions[] = 'edit';
    }

    // Silme yetkisi (içerik sahibi gerekli)
    if ($content_owner_id && can_user_delete_content($pdo, $content_type, $content_owner_id)) {
        $actions[] = 'delete';
    }

    // Özel eylemler
    switch ($content_type) {
        case 'gallery':
            if (has_permission($pdo, 'gallery.like')) {
                $actions[] = 'like';
            }
            break;
        case 'guide':
            if (has_permission($pdo, 'guide.like')) {
                $actions[] = 'like';
            }
            break;
        case 'event':
            if (has_permission($pdo, 'event.participate')) {
                $actions[] = 'participate';
            }
            break;
        case 'discussion_topic':
            if (has_permission($pdo, 'discussion.topic.lock')) {
                $actions[] = 'lock';
            }
            if (has_permission($pdo, 'discussion.topic.pin')) {
                $actions[] = 'pin';
            }
            break;
    }

    return $actions;
}