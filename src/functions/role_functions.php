<?php
// src/functions/role_functions.php

/**
 * Kullanıcının belirli bir yetkiye sahip olup olmadığını kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $permission Kontrol edilecek yetki
 * @param int|null $user_id Kullanıcı ID (opsiyonel, belirtilmezse session'dan alınır)
 * @return bool
 */
function has_permission(PDO $pdo, string $permission, ?int $user_id = null): bool {
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $user_id = $_SESSION['user_id'];
    }

    try {
        // Admin rolüne sahip kullanıcılar tüm yetkilere sahiptir varsayımı
        // Önce kullanıcının 'admin' rolüne sahip olup olmadığını kontrol edelim
        $stmt_is_admin = $pdo->prepare("
            SELECT COUNT(*) 
            FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            WHERE ur.user_id = :user_id AND r.name = 'admin'
        ");
        $stmt_is_admin->execute([':user_id' => $user_id]);
        if ($stmt_is_admin->fetchColumn() > 0) {
            return true; // Admin ise tüm yetkilere sahip
        }

        // Admin değilse, spesifik yetkiyi kontrol et
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            WHERE ur.user_id = :user_id 
            AND FIND_IN_SET(:permission, r.permissions) > 0
        ");
        
        $stmt->execute([
            ':user_id' => $user_id,
            ':permission' => $permission
        ]);
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Yetki kontrolü hatası (Kullanıcı ID: $user_id, Yetki: $permission): " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcının tüm rollerini (isim, açıklama, renk dahil) getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int|null $user_id Kullanıcı ID (opsiyonel, belirtilmezse session'dan alınır)
 * @return array Kullanıcının rollerini içeren dizi
 */
function get_user_roles(PDO $pdo, ?int $user_id = null): array {
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return [];
        }
        $user_id = $_SESSION['user_id'];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT r.id, r.name, r.description, r.color, r.permissions
            FROM roles r 
            JOIN user_roles ur ON r.id = ur.role_id 
            WHERE ur.user_id = :user_id
            ORDER BY r.name ASC
        ");
        
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Kullanıcı rolleri getirme hatası (Kullanıcı ID: $user_id): " . $e->getMessage());
        return [];
    }
}

/**
 * Kullanıcıya rol atar (Eğer zaten sahip değilse)
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $user_id Kullanıcı ID
 * @param int $role_id Rol ID
 * @return bool Başarılıysa true, aksi halde false
 */
function assign_role(PDO $pdo, int $user_id, int $role_id): bool {
    try {
        // Kullanıcının bu role zaten sahip olup olmadığını kontrol et
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE user_id = :user_id AND role_id = :role_id");
        $stmt_check->execute([':user_id' => $user_id, ':role_id' => $role_id]);
        if ($stmt_check->fetchColumn() > 0) {
            return true; // Zaten sahip, işlem başarılı sayılır
        }

        $stmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)");
        return $stmt->execute([':user_id' => $user_id, ':role_id' => $role_id]);
    } catch (PDOException $e) {
        error_log("Rol atama hatası (Kullanıcı ID: $user_id, Rol ID: $role_id): " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcıdan rol kaldırır
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $user_id Kullanıcı ID
 * @param int $role_id Rol ID
 * @return bool Başarılıysa true, aksi halde false
 */
function remove_role(PDO $pdo, int $user_id, int $role_id): bool {
    try {
        $stmt = $pdo->prepare("DELETE FROM user_roles WHERE user_id = :user_id AND role_id = :role_id");
        return $stmt->execute([':user_id' => $user_id, ':role_id' => $role_id]);
    } catch (PDOException $e) {
        error_log("Rol kaldırma hatası (Kullanıcı ID: $user_id, Rol ID: $role_id): " . $e->getMessage());
        return false;
    }
}

/**
 * Sistemdeki tüm rolleri (isim, açıklama, renk dahil) getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @return array Tüm rolleri içeren dizi
 */
function get_all_roles(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SELECT id, name, description, color, permissions FROM roles ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Tüm rolleri getirme hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Belirli bir yetkiye sahip olmayı zorunlu kılar, değilse yönlendirir.
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $permission Gerekli yetki anahtarı
 * @param string|null $redirect_url Başarısız olursa yönlendirilecek URL (opsiyonel)
 */
function require_permission(PDO $pdo, string $permission, ?string $redirect_url = null): void {
    if (!is_user_logged_in()) { // Önce giriş yapmış mı kontrol et
        $_SESSION['error_message'] = "Bu işlemi yapmak için giriş yapmalısınız.";
        header('Location: ' . (get_auth_base_url() . '/login.php?status=login_required_for_permission'));
        exit;
    }
    if (!has_permission($pdo, $permission)) {
        $_SESSION['error_message'] = "Bu işlem için gerekli yetkiye sahip değilsiniz. (Gereken: " . htmlspecialchars($permission) . ")";
        if ($redirect_url === null) {
            // Yetkisi yoksa, geldiği sayfaya veya ana sayfaya yönlendirebiliriz.
            // Şimdilik ana sayfaya yönlendirelim.
            $redirect_url = get_auth_base_url() . '/index.php?status=permission_denied';
        }
        header('Location: ' . $redirect_url);
        exit;
    }
}
