<?php
// src/functions/forum_functions.php

/**
 * Forum sistemi için temel fonksiyonlar
 */

/**
 * Kullanıcının erişebileceği forum kategorilerini getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int|null $user_id Kullanıcı ID'si
 * @return array Kategori listesi
 */
function get_accessible_forum_categories(PDO $pdo, ?int $user_id = null): array {
    try {
        $query = "
            SELECT fc.*,
                   COUNT(DISTINCT ft.id) as topic_count,
                   COUNT(DISTINCT fp.id) as post_count,
                   lt.id as last_topic_id,
                   lt.title as last_topic_title,
                   lt.last_post_at,
                   lpu.id as last_post_user_id,
                   lpu.username as last_post_username,
                   lpr.color as last_post_user_role_color
            FROM forum_categories fc
            LEFT JOIN forum_topics ft ON fc.id = ft.category_id
            LEFT JOIN forum_posts fp ON ft.id = fp.topic_id
            LEFT JOIN (
                SELECT ft2.category_id, ft2.id, ft2.title, ft2.last_post_at, ft2.last_post_user_id,
                       ROW_NUMBER() OVER (PARTITION BY ft2.category_id ORDER BY ft2.last_post_at DESC, ft2.updated_at DESC) as rn
                FROM forum_topics ft2
                WHERE ft2.last_post_at IS NOT NULL
            ) lt ON fc.id = lt.category_id AND lt.rn = 1
            LEFT JOIN users lpu ON lt.last_post_user_id = lpu.id
            LEFT JOIN user_roles lpur ON lpu.id = lpur.user_id
            LEFT JOIN roles lpr ON lpur.role_id = lpr.id
            WHERE fc.is_active = 1
            GROUP BY fc.id, fc.name, fc.description, fc.slug, fc.icon, fc.color, fc.display_order, fc.visibility,
                     lt.id, lt.title, lt.last_post_at, lpu.id, lpu.username, lpr.color
            ORDER BY fc.display_order ASC, fc.name ASC
        ";
        
        $stmt = execute_safe_query($pdo, $query);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $accessible_categories = [];
        
        foreach ($categories as $category) {
            if (can_user_access_forum_category($pdo, $category, $user_id)) {
                $accessible_categories[] = $category;
            }
        }
        
        return $accessible_categories;
        
    } catch (Exception $e) {
        error_log("Forum kategorileri getirme hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Kullanıcının forum kategorisine erişip erişemeyeceğini kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param array $category Kategori verisi
 * @param int|null $user_id Kullanıcı ID'si
 * @return bool Erişebilirse true
 */
function can_user_access_forum_category(PDO $pdo, array $category, ?int $user_id = null): bool {
    // Admin her şeyi görebilir
    if ($user_id && has_permission($pdo, 'admin.panel.access', $user_id)) {
        return true;
    }
    
    switch ($category['visibility']) {
        case 'public':
            return true;
            
        case 'members_only':
            return $user_id && is_user_approved();
            
        case 'faction_only':
            if (!$user_id || !is_user_approved()) {
                return false;
            }
            
            // Kategoriye atanmış rolleri kontrol et
            $roles_query = "
                SELECT role_id FROM forum_category_visibility_roles 
                WHERE category_id = :category_id
            ";
            $stmt = execute_safe_query($pdo, $roles_query, [':category_id' => $category['id']]);
            $required_role_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($required_role_ids)) {
                return true; // Rol atanmamışsa tüm üyeler görebilir
            }
            
            $user_roles = get_user_roles($pdo, $user_id);
            $user_role_ids = array_column($user_roles, 'id');
            
            return !empty(array_intersect($user_role_ids, $required_role_ids));
            
        default:
            return false;
    }
}

/**
 * Forum kategorisinin detaylarını slug ile getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $slug Kategori slug'ı
 * @param int|null $user_id Kullanıcı ID'si
 * @return array|false Kategori detayları veya false
 */
function get_forum_category_details_by_slug(PDO $pdo, string $slug, ?int $user_id = null) {
    try {
        $query = "
            SELECT fc.*,
                   COUNT(DISTINCT ft.id) as topic_count,
                   COUNT(DISTINCT fp.id) as post_count
            FROM forum_categories fc
            LEFT JOIN forum_topics ft ON fc.id = ft.category_id
            LEFT JOIN forum_posts fp ON ft.id = fp.topic_id
            WHERE fc.slug = :slug AND fc.is_active = 1
            GROUP BY fc.id, fc.name, fc.description, fc.slug, fc.icon, fc.color, fc.display_order, fc.visibility
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':slug' => $slug]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$category) {
            return false;
        }
        
        // Erişim kontrolü
        if (!can_user_access_forum_category($pdo, $category, $user_id)) {
            return false;
        }
        
        // Kullanıcının bu kategoride konu oluşturabileceğini kontrol et
        $category['can_create_topic'] = can_user_create_forum_topic($pdo, $category['id'], $user_id);
        
        return $category;
        
    } catch (Exception $e) {
        error_log("Forum kategori detayları getirme hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcının belirli kategoride konu oluşturabileceğini kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $category_id Kategori ID'si
 * @param int|null $user_id Kullanıcı ID'si
 * @return bool Oluşturabilirse true
 */
function can_user_create_forum_topic(PDO $pdo, int $category_id, ?int $user_id = null): bool {
    if (!$user_id || !is_user_approved()) {
        return false;
    }
    
    if (!has_permission($pdo, 'forum.topic.create', $user_id)) {
        return false;
    }
    
    // Kategori erişim kontrolü
    $category_query = "SELECT * FROM forum_categories WHERE id = :id AND is_active = 1";
    $stmt = execute_safe_query($pdo, $category_query, [':id' => $category_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        return false;
    }
    
    return can_user_access_forum_category($pdo, $category, $user_id);
}

/**
 * Belirli kategorideki konuları getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $category_id Kategori ID'si
 * @param int|null $user_id Kullanıcı ID'si
 * @param int $limit Sayfa başına konu sayısı
 * @param int $offset Başlangıç noktası
 * @param string $sort Sıralama kriteri
 * @param string $order Sıralama yönü
 * @return array Konular ve toplam sayı
 */
function get_forum_topics_in_category(PDO $pdo, int $category_id, ?int $user_id = null, int $limit = 20, int $offset = 0, string $sort = 'updated', string $order = 'desc'): array {
    try {
        // Sıralama güvenlik kontrolü
        $allowed_sort_columns = ['updated_at', 'created_at', 'title', 'reply_count', 'view_count'];
        $sort_column = in_array($sort, ['updated', 'created', 'title', 'replies', 'views']) 
            ? str_replace(['updated', 'created', 'replies', 'views'], ['updated_at', 'created_at', 'reply_count', 'view_count'], $sort)
            : 'updated_at';
        
        $order_direction = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        
        // Toplam konu sayısını al
        $count_query = "
            SELECT COUNT(*) 
            FROM forum_topics ft
            JOIN forum_categories fc ON ft.category_id = fc.id
            WHERE ft.category_id = :category_id AND fc.is_active = 1
        ";
        $stmt = execute_safe_query($pdo, $count_query, [':category_id' => $category_id]);
        $total = (int)$stmt->fetchColumn();
        
        // Konuları getir
        $topics_query = "
            SELECT ft.*,
                   u.username as author_username,
                   ur.color as author_role_color,
                   lpu.username as last_post_username,
                   lpu.id as last_post_user_id,
                   lpur.color as last_post_role_color
            FROM forum_topics ft
            JOIN forum_categories fc ON ft.category_id = fc.id
            JOIN users u ON ft.user_id = u.id
            LEFT JOIN user_roles uur ON u.id = uur.user_id
            LEFT JOIN roles ur ON uur.role_id = ur.id AND ur.priority = (
                SELECT MIN(r2.priority) FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id 
                WHERE ur2.user_id = u.id
            )
            LEFT JOIN users lpu ON ft.last_post_user_id = lpu.id
            LEFT JOIN user_roles lpuur ON lpu.id = lpuur.user_id
            LEFT JOIN roles lpur ON lpuur.role_id = lpur.id AND lpur.priority = (
                SELECT MIN(r3.priority) FROM user_roles ur3
                JOIN roles r3 ON ur3.role_id = r3.id
                WHERE ur3.user_id = lpu.id
            )
            WHERE ft.category_id = :category_id AND fc.is_active = 1
            ORDER BY ft.is_pinned DESC, ft.{$sort_column} {$order_direction}
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($topics_query);
        $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'topics' => $topics,
            'total' => $total
        ];
        
    } catch (Exception $e) {
        error_log("Forum konuları getirme hatası: " . $e->getMessage());
        return ['topics' => [], 'total' => 0];
    }
}

/**
 * Forum konusunu ID ile getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $topic_id Konu ID'si
 * @param int|null $user_id Kullanıcı ID'si
 * @return array|false Konu detayları veya false
 */
function get_forum_topic_by_id(PDO $pdo, int $topic_id, ?int $user_id = null) {
    try {
        $query = "
            SELECT ft.*,
                   fc.name as category_name,
                   fc.slug as category_slug,
                   fc.color as category_color,
                   fc.visibility as category_visibility,
                   u.username as author_username,
                   u.avatar_path as author_avatar,
                   ur.color as author_role_color,
                   ur.name as author_role_name
            FROM forum_topics ft
            JOIN forum_categories fc ON ft.category_id = fc.id
            JOIN users u ON ft.user_id = u.id
            LEFT JOIN user_roles uur ON u.id = uur.user_id
            LEFT JOIN roles ur ON uur.role_id = ur.id AND ur.priority = (
                SELECT MIN(r2.priority) FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id 
                WHERE ur2.user_id = u.id
            )
            WHERE ft.id = :topic_id AND fc.is_active = 1
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':topic_id' => $topic_id]);
        $topic = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$topic) {
            return false;
        }
        
        // Kategori erişim kontrolü
        $category = [
            'id' => $topic['category_id'],
            'visibility' => $topic['category_visibility']
        ];
        
        if (!can_user_access_forum_category($pdo, $category, $user_id)) {
            return false;
        }
        
        // Kullanıcının yetki bilgilerini ekle
        if ($user_id) {
            $topic['can_reply'] = !$topic['is_locked'] && has_permission($pdo, 'forum.topic.reply', $user_id);
            $topic['can_edit'] = can_user_edit_forum_topic($pdo, $topic_id, $user_id);
            $topic['can_delete'] = can_user_delete_forum_topic($pdo, $topic_id, $user_id);
            $topic['can_pin'] = has_permission($pdo, 'forum.topic.pin', $user_id);
            $topic['can_lock'] = has_permission($pdo, 'forum.topic.lock', $user_id);
        } else {
            $topic['can_reply'] = false;
            $topic['can_edit'] = false;
            $topic['can_delete'] = false;
            $topic['can_pin'] = false;
            $topic['can_lock'] = false;
        }
        
        return $topic;
        
    } catch (Exception $e) {
        error_log("Forum konusu getirme hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Forum konusunun gönderilerini getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $topic_id Konu ID'si
 * @param int|null $user_id Kullanıcı ID'si
 * @param int $limit Sayfa başına gönderi sayısı
 * @param int $offset Başlangıç noktası
 * @return array Gönderiler ve toplam sayı
 */
function get_forum_topic_posts(PDO $pdo, int $topic_id, ?int $user_id = null, int $limit = 10, int $offset = 0): array {
    try {
        // Toplam gönderi sayısını al
        $count_query = "SELECT COUNT(*) FROM forum_posts WHERE topic_id = :topic_id";
        $stmt = execute_safe_query($pdo, $count_query, [':topic_id' => $topic_id]);
        $total = (int)$stmt->fetchColumn();
        
        // Gönderileri getir
        $posts_query = "
            SELECT fp.*,
                   u.username,
                   u.avatar_path,
                   u.created_at as user_join_date,
                   ur.color as user_role_color,
                   ur.name as user_role_name,
                   (SELECT COUNT(*) FROM forum_post_likes WHERE post_id = fp.id) as like_count,
                   " . ($user_id ? "(SELECT COUNT(*) FROM forum_post_likes WHERE post_id = fp.id AND user_id = :user_id) as user_liked" : "0 as user_liked") . "
            FROM forum_posts fp
            JOIN users u ON fp.user_id = u.id
            LEFT JOIN user_roles uur ON u.id = uur.user_id
            LEFT JOIN roles ur ON uur.role_id = ur.id AND ur.priority = (
                SELECT MIN(r2.priority) FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id 
                WHERE ur2.user_id = u.id
            )
            WHERE fp.topic_id = :topic_id
            ORDER BY fp.created_at ASC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($posts_query);
        $stmt->bindValue(':topic_id', $topic_id, PDO::PARAM_INT);
        if ($user_id) {
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Her gönderi için kullanıcı yetkilerini ekle
        foreach ($posts as &$post) {
            if ($user_id) {
                $post['can_edit'] = can_user_edit_forum_post($pdo, $post['id'], $user_id);
                $post['can_delete'] = can_user_delete_forum_post($pdo, $post['id'], $user_id);
                $post['can_like'] = $post['user_id'] != $user_id && has_permission($pdo, 'forum.post.like', $user_id);
            } else {
                $post['can_edit'] = false;
                $post['can_delete'] = false;
                $post['can_like'] = false;
            }
        }
        
        return [
            'posts' => $posts,
            'total' => $total
        ];
        
    } catch (Exception $e) {
        error_log("Forum gönderileri getirme hatası: " . $e->getMessage());
        return ['posts' => [], 'total' => 0];
    }
}

/**
 * Kullanıcının forum konusunu düzenleyebileceğini kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $topic_id Konu ID'si
 * @param int $user_id Kullanıcı ID'si
 * @return bool Düzenleyebilirse true
 */
function can_user_edit_forum_topic(PDO $pdo, int $topic_id, int $user_id): bool {
    try {
        $query = "SELECT user_id FROM forum_topics WHERE id = :topic_id";
        $stmt = execute_safe_query($pdo, $query, [':topic_id' => $topic_id]);
        $topic_owner_id = $stmt->fetchColumn();
        
        if (!$topic_owner_id) {
            return false;
        }
        
        // Admin veya moderatör her şeyi düzenleyebilir
        if (has_permission($pdo, 'forum.topic.edit_all', $user_id)) {
            return true;
        }
        
        // Kendi konusunu düzenleyebilir
        if ($topic_owner_id == $user_id && has_permission($pdo, 'forum.topic.edit_own', $user_id)) {
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Forum konu düzenleme yetkisi kontrolü hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcının forum konusunu silebileceğini kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $topic_id Konu ID'si
 * @param int $user_id Kullanıcı ID'si
 * @return bool Silebilirse true
 */
function can_user_delete_forum_topic(PDO $pdo, int $topic_id, int $user_id): bool {
    try {
        $query = "SELECT user_id FROM forum_topics WHERE id = :topic_id";
        $stmt = execute_safe_query($pdo, $query, [':topic_id' => $topic_id]);
        $topic_owner_id = $stmt->fetchColumn();
        
        if (!$topic_owner_id) {
            return false;
        }
        
        // Admin veya moderatör her şeyi silebilir
        if (has_permission($pdo, 'forum.topic.delete_all', $user_id)) {
            return true;
        }
        
        // Kendi konusunu silebilir
        if ($topic_owner_id == $user_id && has_permission($pdo, 'forum.topic.delete_own', $user_id)) {
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Forum konu silme yetkisi kontrolü hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcının forum gönderisini düzenleyebileceğini kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $post_id Gönderi ID'si
 * @param int $user_id Kullanıcı ID'si
 * @return bool Düzenleyebilirse true
 */
function can_user_edit_forum_post(PDO $pdo, int $post_id, int $user_id): bool {
    try {
        $query = "SELECT user_id FROM forum_posts WHERE id = :post_id";
        $stmt = execute_safe_query($pdo, $query, [':post_id' => $post_id]);
        $post_owner_id = $stmt->fetchColumn();
        
        if (!$post_owner_id) {
            return false;
        }
        
        // Admin veya moderatör her şeyi düzenleyebilir
        if (has_permission($pdo, 'forum.post.edit_all', $user_id)) {
            return true;
        }
        
        // Kendi gönderisini düzenleyebilir
        if ($post_owner_id == $user_id && has_permission($pdo, 'forum.post.edit_own', $user_id)) {
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Forum gönderi düzenleme yetkisi kontrolü hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcının forum gönderisini silebileceğini kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $post_id Gönderi ID'si
 * @param int $user_id Kullanıcı ID'si
 * @return bool Silebilirse true
 */
function can_user_delete_forum_post(PDO $pdo, int $post_id, int $user_id): bool {
    try {
        $query = "SELECT user_id FROM forum_posts WHERE id = :post_id";
        $stmt = execute_safe_query($pdo, $query, [':post_id' => $post_id]);
        $post_owner_id = $stmt->fetchColumn();
        
        if (!$post_owner_id) {
            return false;
        }
        
        // Admin veya moderatör her şeyi silebilir
        if (has_permission($pdo, 'forum.post.delete_all', $user_id)) {
            return true;
        }
        
        // Kendi gönderisini silebilir
        if ($post_owner_id == $user_id && has_permission($pdo, 'forum.post.delete_own', $user_id)) {
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Forum gönderi silme yetkisi kontrolü hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Son forum konularını getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $limit Kaç konu getirileceği
 * @param int|null $user_id Kullanıcı ID'si
 * @return array Konu listesi
 */
function get_recent_forum_topics(PDO $pdo, int $limit = 5, ?int $user_id = null): array {
    try {
        $query = "
            SELECT ft.*,
                   fc.name as category_name,
                   fc.color as category_color,
                   u.username as author_username,
                   ur.color as author_role_color
            FROM forum_topics ft
            JOIN forum_categories fc ON ft.category_id = fc.id
            JOIN users u ON ft.user_id = u.id
            LEFT JOIN user_roles uur ON u.id = uur.user_id
            LEFT JOIN roles ur ON uur.role_id = ur.id AND ur.priority = (
                SELECT MIN(r2.priority) FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id 
                WHERE ur2.user_id = u.id
            )
            WHERE fc.is_active = 1
            ORDER BY ft.updated_at DESC
            LIMIT :limit
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Erişim kontrolü
        $accessible_topics = [];
        foreach ($topics as $topic) {
            $category = [
                'id' => $topic['category_id'],
                'visibility' => $topic['visibility'] ?? 'public'
            ];
            
            if (can_user_access_forum_category($pdo, $category, $user_id)) {
                $accessible_topics[] = $topic;
            }
        }
        
        return $accessible_topics;
        
    } catch (Exception $e) {
        error_log("Son forum konuları getirme hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Forum istatistiklerini getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int|null $user_id Kullanıcı ID'si
 * @return array İstatistik verileri
 */
function get_forum_statistics(PDO $pdo, ?int $user_id = null): array {
    try {
        // Kategorileri kontrol ederek erişilebilir kategorilerin ID'lerini al
        $accessible_categories = get_accessible_forum_categories($pdo, $user_id);
        $accessible_category_ids = array_column($accessible_categories, 'id');
        
        if (empty($accessible_category_ids)) {
            return [
                'total_topics' => 0,
                'total_posts' => 0,
                'total_members' => 0,
                'newest_member' => null
            ];
        }
        
        $category_ids_placeholder = implode(',', array_fill(0, count($accessible_category_ids), '?'));
        
        // Toplam konu sayısı
        $topics_query = "
            SELECT COUNT(*) FROM forum_topics ft
            JOIN forum_categories fc ON ft.category_id = fc.id
            WHERE ft.category_id IN ($category_ids_placeholder) AND fc.is_active = 1
        ";
        $stmt = $pdo->prepare($topics_query);
        $stmt->execute($accessible_category_ids);
        $total_topics = (int)$stmt->fetchColumn();
        
        // Toplam gönderi sayısı
        $posts_query = "
            SELECT COUNT(*) FROM forum_posts fp
            JOIN forum_topics ft ON fp.topic_id = ft.id
            JOIN forum_categories fc ON ft.category_id = fc.id
            WHERE ft.category_id IN ($category_ids_placeholder) AND fc.is_active = 1
        ";
        $stmt = $pdo->prepare($posts_query);
        $stmt->execute($accessible_category_ids);
        $total_posts = (int)$stmt->fetchColumn();
        
        // Toplam üye sayısı
        $members_query = "SELECT COUNT(*) FROM users WHERE status = 'approved'";
        $stmt = execute_safe_query($pdo, $members_query);
        $total_members = (int)$stmt->fetchColumn();
        
        // En yeni üye
        $newest_member_query = "
            SELECT username FROM users 
            WHERE status = 'approved' 
            ORDER BY created_at DESC 
            LIMIT 1
        ";
        $stmt = execute_safe_query($pdo, $newest_member_query);
        $newest_member = $stmt->fetchColumn();
        
        return [
            'total_topics' => $total_topics,
            'total_posts' => $total_posts,
            'total_members' => $total_members,
            'newest_member' => $newest_member ?: null
        ];
        
    } catch (Exception $e) {
        error_log("Forum istatistikleri getirme hatası: " . $e->getMessage());
        return [
            'total_topics' => 0,
            'total_posts' => 0,
            'total_members' => 0,
            'newest_member' => null
        ];
    }
}

/**
 * Forum içeriğinde arama yapar
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $search_query Arama sorgusu
 * @param int|null $user_id Kullanıcı ID'si
 * @param int $limit Sonuç limiti
 * @return array Arama sonuçları
 */
function search_forum_content(PDO $pdo, string $search_query, ?int $user_id = null, int $limit = 20): array {
    try {
        if (strlen($search_query) < 2) {
            return [];
        }
        
        $search_term = '%' . $search_query . '%';
        $results = [];
        
        // Kategorilerde ara
        $category_query = "
            SELECT 'category' as type, 'Kategori' as type_label,
                   id, name as title, description as content,
                   CONCAT('/public/forum/category.php?slug=', slug) as url
            FROM forum_categories
            WHERE is_active = 1 AND (name LIKE :search OR description LIKE :search)
            ORDER BY name ASC
            LIMIT 5
        ";
        $stmt = execute_safe_query($pdo, $category_query, [':search' => $search_term]);
        $category_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($category_results as $result) {
            $category = ['id' => $result['id'], 'visibility' => 'public']; // Basit kontrol
            if (can_user_access_forum_category($pdo, $category, $user_id)) {
                $results[] = $result;
            }
        }
        
        // Konularda ara
        $topic_query = "
            SELECT 'topic' as type, 'Konu' as type_label,
                   ft.id, ft.title, LEFT(ft.content, 200) as content,
                   CONCAT('/public/forum/topic.php?id=', ft.id) as url,
                   u.username, ur.color as user_role_color, ft.user_id, ft.created_at
            FROM forum_topics ft
            JOIN forum_categories fc ON ft.category_id = fc.id
            JOIN users u ON ft.user_id = u.id
            LEFT JOIN user_roles uur ON u.id = uur.user_id
            LEFT JOIN roles ur ON uur.role_id = ur.id AND ur.priority = (
                SELECT MIN(r2.priority) FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id 
                WHERE ur2.user_id = u.id
            )
            WHERE fc.is_active = 1 AND (ft.title LIKE :search OR ft.content LIKE :search)
            ORDER BY ft.updated_at DESC
            LIMIT 10
        ";
        $stmt = execute_safe_query($pdo, $topic_query, [':search' => $search_term]);
        $topic_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($topic_results as $result) {
            $results[] = $result;
        }
        
        // Gönderilerde ara
        $post_query = "
            SELECT 'post' as type, 'Gönderi' as type_label,
                   fp.id, ft.title, LEFT(fp.content, 200) as content,
                   CONCAT('/public/forum/topic.php?id=', ft.id, '#post-', fp.id) as url,
                   u.username, ur.color as user_role_color, fp.user_id, fp.created_at
            FROM forum_posts fp
            JOIN forum_topics ft ON fp.topic_id = ft.id
            JOIN forum_categories fc ON ft.category_id = fc.id
            JOIN users u ON fp.user_id = u.id
            LEFT JOIN user_roles uur ON u.id = uur.user_id
            LEFT JOIN roles ur ON uur.role_id = ur.id AND ur.priority = (
                SELECT MIN(r2.priority) FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id 
                WHERE ur2.user_id = u.id
            )
            WHERE fc.is_active = 1 AND fp.content LIKE :search
            ORDER BY fp.created_at DESC
            LIMIT 10
        ";
        $stmt = execute_safe_query($pdo, $post_query, [':search' => $search_term]);
        $post_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($post_results as $result) {
            $results[] = $result;
        }
        
        // Kullanıcılarda ara
        if ($user_id) { // Sadece giriş yapmış kullanıcılar kullanıcı arayabilir
            $user_query = "
                SELECT 'user' as type, 'Kullanıcı' as type_label,
                       id, username as title, '' as content,
                       CONCAT('/public/view_profile.php?user_id=', id) as url,
                       username, '#bd912a' as user_role_color, id as user_id, created_at
                FROM users
                WHERE status = 'approved' AND (username LIKE :search OR ingame_name LIKE :search)
                ORDER BY username ASC
                LIMIT 5
            ";
            $stmt = execute_safe_query($pdo, $user_query, [':search' => $search_term]);
            $user_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($user_results as $result) {
                $results[] = $result;
            }
        }
        
        return array_slice($results, 0, $limit);
        
    } catch (Exception $e) {
        error_log("Forum arama hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Forum konusu oluşturur
 * @param PDO $pdo Veritabanı bağlantısı
 * @param array $topic_data Konu verileri
 * @param int $user_id Kullanıcı ID'si
 * @return int|false Oluşturulan konu ID'si veya false
 */
function create_forum_topic(PDO $pdo, array $topic_data, int $user_id) {
    try {
        // Input validation
        if (empty($topic_data['title']) || empty($topic_data['content']) || empty($topic_data['category_id'])) {
            return false;
        }
        
        // Kategori erişim kontrolü
        $category_query = "SELECT * FROM forum_categories WHERE id = :id AND is_active = 1";
        $stmt = execute_safe_query($pdo, $category_query, [':id' => $topic_data['category_id']]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$category || !can_user_access_forum_category($pdo, $category, $user_id)) {
            return false;
        }
        
        // Slug oluştur
        $slug = create_topic_slug($pdo, $topic_data['title']);
        
        // Visibility ayarla
        $visibility = $topic_data['visibility'] ?? 'public';
        if (!in_array($visibility, ['public', 'members_only', 'faction_only'])) {
            $visibility = 'public';
        }
        
        // Transaction başlat
        $pdo->beginTransaction();
        
        try {
            // Konuyu oluştur
            $insert_query = "
                INSERT INTO forum_topics (category_id, user_id, title, slug, content, visibility, created_at, updated_at)
                VALUES (:category_id, :user_id, :title, :slug, :content, :visibility, NOW(), NOW())
            ";
            
            $insert_params = [
                ':category_id' => $topic_data['category_id'],
                ':user_id' => $user_id,
                ':title' => $topic_data['title'],
                ':slug' => $slug,
                ':content' => $topic_data['content'],
                ':visibility' => $visibility
            ];
            
            $stmt = execute_safe_query($pdo, $insert_query, $insert_params);
            $topic_id = $pdo->lastInsertId();
            
            // Visibility rolleri ata (eğer faction_only ise)
            if ($visibility === 'faction_only' && !empty($topic_data['visible_roles'])) {
                foreach ($topic_data['visible_roles'] as $role_id) {
                    $role_query = "INSERT INTO forum_topic_visibility_roles (topic_id, role_id) VALUES (:topic_id, :role_id)";
                    execute_safe_query($pdo, $role_query, [':topic_id' => $topic_id, ':role_id' => $role_id]);
                }
            }
            
            $pdo->commit();
            
            // Audit log
            audit_log($pdo, $user_id, 'forum_topic_created', 'forum_topic', $topic_id, null, [
                'category_id' => $topic_data['category_id'],
                'title' => $topic_data['title'],
                'visibility' => $visibility
            ]);
            
            return $topic_id;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("Forum konusu oluşturma hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Konu slug'ı oluşturur (benzersiz)
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $title Konu başlığı
 * @return string Benzersiz slug
 */
function create_topic_slug(PDO $pdo, string $title): string {
    // Türkçe karakterleri değiştir ve slug oluştur
    $slug = mb_strtolower($title, 'UTF-8');
    $slug = str_replace(
        ['ç', 'ğ', 'ı', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'I', 'İ', 'Ö', 'Ş', 'Ü'],
        ['c', 'g', 'i', 'o', 's', 'u', 'c', 'g', 'i', 'i', 'o', 's', 'u'],
        $slug
    );
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/\s+/', '-', trim($slug));
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = substr($slug, 0, 100);
    
    // Benzersizlik kontrolü
    $original_slug = $slug;
    $counter = 1;
    
    while (true) {
        $check_query = "SELECT COUNT(*) FROM forum_topics WHERE slug = :slug";
        $stmt = execute_safe_query($pdo, $check_query, [':slug' => $slug]);
        
        if ($stmt->fetchColumn() == 0) {
            break;
        }
        
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

/**
 * Konu görüntüleme sayısını artırır
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $topic_id Konu ID'si
 * @param int|null $user_id Kullanıcı ID'si (session kontrolü için)
 */
function increment_topic_view_count(PDO $pdo, int $topic_id, ?int $user_id = null): void {
    try {
        // Session bazlı view tracking (aynı kullanıcının çoklu view'ını önlemek için)
        $view_key = "topic_view_$topic_id";
        
        if (!isset($_SESSION[$view_key]) || (time() - $_SESSION[$view_key]) > 300) { // 5 dakika
            $update_query = "UPDATE forum_topics SET view_count = view_count + 1 WHERE id = :topic_id";
            execute_safe_query($pdo, $update_query, [':topic_id' => $topic_id]);
            
            $_SESSION[$view_key] = time();
        }
        
    } catch (Exception $e) {
        error_log("Konu görüntüleme sayısı artırma hatası: " . $e->getMessage());
    }
}

/**
 * BBCode'u HTML'e çevirir (basit versiyon)
 * @param string $text BBCode içeren metin
 * @return string HTML'e çevrilmiş metin
 */
function parse_bbcode(string $text): string {
    // Güvenlik için önce HTML'i temizle
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
    // BBCode dönüşümleri
    $bbcode_patterns = [
        '/\[b\](.*?)\[\/b\]/is' => '<strong>$1</strong>',
        '/\[i\](.*?)\[\/i\]/is' => '<em>$1</em>',
        '/\[u\](.*?)\[\/u\]/is' => '<u>$1</u>',
        '/\[s\](.*?)\[\/s\]/is' => '<del>$1</del>',
        '/\[url=(.*?)\](.*?)\[\/url\]/is' => '<a href="$1" target="_blank" rel="noopener">$2</a>',
        '/\[url\](.*?)\[\/url\]/is' => '<a href="$1" target="_blank" rel="noopener">$1</a>',
        '/\[img\](.*?)\[\/img\]/is' => '<img src="$1" alt="User Image" style="max-width: 100%; height: auto;">',
        '/\[quote\](.*?)\[\/quote\]/is' => '<blockquote class="forum-quote">$1</blockquote>',
        '/\[quote=(.*?)\](.*?)\[\/quote\]/is' => '<blockquote class="forum-quote"><cite>$1 yazdı:</cite>$2</blockquote>',
        '/\[code\](.*?)\[\/code\]/is' => '<pre class="forum-code">$1</pre>',
        '/\[color=(.*?)\](.*?)\[\/color\]/is' => '<span style="color: $1;">$2</span>',
        '/\[size=(.*?)\](.*?)\[\/size\]/is' => '<span style="font-size: $1;">$2</span>',
    ];
    
    foreach ($bbcode_patterns as $pattern => $replacement) {
        $text = preg_replace($pattern, $replacement, $text);
    }
    
    // Satır sonlarını <br> ile değiştir
    $text = nl2br($text);
    
    return $text;
}

/**
 * Forum breadcrumb'ını oluşturur
 * @param array $items Breadcrumb öğeleri
 * @return string HTML breadcrumb
 */
function generate_forum_breadcrumb(array $items): string {
    $breadcrumb = '<nav class="forum-breadcrumb"><ol class="breadcrumb">';
    
    foreach ($items as $index => $item) {
        $is_last = ($index === count($items) - 1);
        
        if ($is_last) {
            $breadcrumb .= '<li class="breadcrumb-item active">';
            if (!empty($item['icon'])) {
                $breadcrumb .= '<i class="' . htmlspecialchars($item['icon']) . '"></i> ';
            }
            $breadcrumb .= htmlspecialchars($item['text']);
            $breadcrumb .= '</li>';
        } else {
            $breadcrumb .= '<li class="breadcrumb-item">';
            $breadcrumb .= '<a href="' . htmlspecialchars($item['url']) . '">';
            if (!empty($item['icon'])) {
                $breadcrumb .= '<i class="' . htmlspecialchars($item['icon']) . '"></i> ';
            }
            $breadcrumb .= htmlspecialchars($item['text']);
            $breadcrumb .= '</a>';
            $breadcrumb .= '</li>';
        }
    }
    
    $breadcrumb .= '</ol></nav>';
    return $breadcrumb;
}
?>