<?php
// src/functions/forum_functions.php

require_once BASE_PATH . '/src/functions/enhanced_role_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

/**
 * Kullanıcının erişebileceği forum kategorilerini getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int|null $user_id Kullanıcı ID'si
 * @return array Kategori listesi
 */
function get_accessible_forum_categories(PDO $pdo, ?int $user_id = null): array {
    try {
        $base_query = "
            SELECT fc.*, 
                   COUNT(DISTINCT ft.id) as topic_count,
                   COUNT(DISTINCT fp.id) as post_count,
                   MAX(ft.updated_at) as last_activity,
                   lp_topic.title as last_topic_title,
                   lp_topic.id as last_topic_id,
                   lp_user.username as last_post_username,
                   lp_user.id as last_post_user_id,
                   ft_last.last_post_at as last_post_at
            FROM forum_categories fc
            LEFT JOIN forum_topics ft ON fc.id = ft.category_id
            LEFT JOIN forum_posts fp ON ft.id = fp.topic_id
            LEFT JOIN (
                SELECT category_id, MAX(updated_at) as max_updated
                FROM forum_topics 
                GROUP BY category_id
            ) latest ON fc.id = latest.category_id
            LEFT JOIN forum_topics ft_last ON fc.id = ft_last.category_id 
                AND ft_last.updated_at = latest.max_updated
            LEFT JOIN forum_topics lp_topic ON ft_last.id = lp_topic.id
            LEFT JOIN users lp_user ON ft_last.last_post_user_id = lp_user.id
            WHERE fc.is_active = 1
        ";

        // Kullanıcının erişim yetkilerine göre filtreleme
        if ($user_id === null || !is_user_logged_in()) {
            // Misafir kullanıcı - sadece public kategoriler
            $base_query .= " AND fc.visibility = 'public'";
            $params = [];
        } else {
            // Giriş yapmış kullanıcı
            if (is_user_approved()) {
                if (has_permission($pdo, 'forum.view_faction_only', $user_id)) {
                    // Faction yetkisi var - tüm kategorileri görebilir
                    $params = [];
                } else if (has_permission($pdo, 'forum.view_members_only', $user_id)) {
                    // Sadece members_only yetkisi var
                    $base_query .= " AND fc.visibility IN ('public', 'members_only')";
                    $params = [];
                } else {
                    // Sadece public yetkisi var
                    $base_query .= " AND fc.visibility = 'public'";
                    $params = [];
                }
            } else {
                // Onaylanmamış kullanıcı - sadece public
                $base_query .= " AND fc.visibility = 'public'";
                $params = [];
            }
        }

        $base_query .= "
            GROUP BY fc.id, fc.name, fc.description, fc.icon, fc.color, fc.display_order, 
                     fc.is_active, fc.visibility, fc.created_at, fc.updated_at,
                     lp_topic.title, lp_topic.id, lp_user.username, lp_user.id, ft_last.last_post_at
            ORDER BY fc.display_order ASC, fc.name ASC
        ";

        $stmt = execute_safe_query($pdo, $base_query, $params);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Her kategori için roller kontrolü (faction_only kategoriler için)
        foreach ($categories as &$category) {
            $category['can_access'] = can_user_access_forum_category($pdo, $category, $user_id);
            $category['can_create_topic'] = can_user_create_forum_topic($pdo, $category['id'], $user_id);
            
            // Sayıları integer'a çevir
            $category['topic_count'] = (int)$category['topic_count'];
            $category['post_count'] = (int)$category['post_count'];
        }

        return array_filter($categories, function($cat) {
            return $cat['can_access'];
        });

    } catch (Exception $e) {
        error_log("Forum kategorileri getirme hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Kullanıcının belirli bir forum kategorisine erişip erişemeyeceğini kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param array $category Kategori bilgileri
 * @param int|null $user_id Kullanıcı ID'si
 * @return bool
 */
function can_user_access_forum_category(PDO $pdo, array $category, ?int $user_id = null): bool {
    // Admin kontrolü
    if ($user_id && has_permission($pdo, 'admin.panel.access', $user_id)) {
        return true;
    }

    // Görünürlük kontrolü
    switch ($category['visibility']) {
        case 'public':
            return true;
        case 'members_only':
            return $user_id && is_user_approved() && has_permission($pdo, 'forum.view_members_only', $user_id);
        case 'faction_only':
            if (!$user_id || !is_user_approved()) {
                return false;
            }
            
            // Faction permission kontrolü
            if (has_permission($pdo, 'forum.view_faction_only', $user_id)) {
                // Eğer bu kategori için özel roller tanımlanmışsa kontrol et
                $role_ids = get_forum_category_visibility_roles($pdo, $category['id']);
                if (!empty($role_ids)) {
                    $user_roles = get_user_roles($pdo, $user_id);
                    $user_role_ids = array_column($user_roles, 'id');
                    return !empty(array_intersect($user_role_ids, $role_ids));
                }
                return true;
            }
            return false;
        default:
            return false;
    }
}

/**
 * Kullanıcının belirli bir kategoride konu oluşturup oluşturamayacağını kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $category_id Kategori ID'si
 * @param int|null $user_id Kullanıcı ID'si
 * @return bool
 */
function can_user_create_forum_topic(PDO $pdo, int $category_id, ?int $user_id = null): bool {
    if (!$user_id || !is_user_approved()) {
        return false;
    }

    // Kategori erişim kontrolü
    $category_query = "SELECT * FROM forum_categories WHERE id = :category_id AND is_active = 1";
    $stmt = execute_safe_query($pdo, $category_query, [':category_id' => $category_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category || !can_user_access_forum_category($pdo, $category, $user_id)) {
        return false;
    }

    // Forum konu oluşturma yetkisi kontrolü
    return has_permission($pdo, 'forum.topic.create', $user_id);
}

/**
 * Forum kategorisinin görünürlük rollerini getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $category_id Kategori ID'si
 * @return array Rol ID'leri
 */
function get_forum_category_visibility_roles(PDO $pdo, int $category_id): array {
    try {
        $query = "SELECT role_id FROM forum_category_visibility_roles WHERE category_id = :category_id";
        $stmt = execute_safe_query($pdo, $query, [':category_id' => $category_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Forum kategori rolleri getirme hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Forum kategorisinin detaylarını getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $category_id Kategori ID'si
 * @param int|null $user_id Kullanıcı ID'si
 * @return array|null Kategori detayları
 */
function get_forum_category_details(PDO $pdo, int $category_id, ?int $user_id = null): ?array {
    try {
        $query = "
            SELECT fc.*,
                   COUNT(DISTINCT ft.id) as topic_count,
                   COUNT(DISTINCT fp.id) as post_count
            FROM forum_categories fc
            LEFT JOIN forum_topics ft ON fc.id = ft.category_id
            LEFT JOIN forum_posts fp ON ft.id = fp.topic_id
            WHERE fc.id = :category_id AND fc.is_active = 1
            GROUP BY fc.id
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':category_id' => $category_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            return null;
        }

        // Erişim kontrolü
        if (!can_user_access_forum_category($pdo, $category, $user_id)) {
            return null;
        }

        $category['topic_count'] = (int)$category['topic_count'];
        $category['post_count'] = (int)$category['post_count'];
        $category['can_create_topic'] = can_user_create_forum_topic($pdo, $category_id, $user_id);

        return $category;

    } catch (Exception $e) {
        error_log("Forum kategorisi detay getirme hatası: " . $e->getMessage());
        return null;
    }
}

/**
 * Forum istatistiklerini getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int|null $user_id Kullanıcı ID'si
 * @return array İstatistikler
 */
function get_forum_statistics(PDO $pdo, ?int $user_id = null): array {
    try {
        $stats = [
            'total_topics' => 0,
            'total_posts' => 0,
            'total_members' => 0,
            'newest_member' => null,
            'online_members' => 0
        ];

        // Erişilebilir kategorileri al
        $accessible_categories = get_accessible_forum_categories($pdo, $user_id);
        $category_ids = array_column($accessible_categories, 'id');

        if (!empty($category_ids)) {
            // IN clause için güvenli parametreler oluştur
            $in_clause = create_safe_in_clause($pdo, $category_ids, 'int');
            
            // Toplam konu sayısı
            $topics_query = "SELECT COUNT(*) FROM forum_topics WHERE category_id " . $in_clause['placeholders'];
            $stmt = execute_safe_query($pdo, $topics_query, $in_clause['params']);
            $stats['total_topics'] = (int)$stmt->fetchColumn();

            // Toplam gönderi sayısı
            $posts_query = "
                SELECT COUNT(*) FROM forum_posts fp 
                JOIN forum_topics ft ON fp.topic_id = ft.id 
                WHERE ft.category_id " . $in_clause['placeholders'];
            $stmt = execute_safe_query($pdo, $posts_query, $in_clause['params']);
            $stats['total_posts'] = (int)$stmt->fetchColumn();
        }

        // Toplam üye sayısı
        $members_query = "SELECT COUNT(*) FROM users WHERE status = 'approved'";
        $stmt = execute_safe_query($pdo, $members_query);
        $stats['total_members'] = (int)$stmt->fetchColumn();

        // En yeni üye
        $newest_query = "SELECT username FROM users WHERE status = 'approved' ORDER BY created_at DESC LIMIT 1";
        $stmt = execute_safe_query($pdo, $newest_query);
        $stats['newest_member'] = $stmt->fetchColumn();

        return $stats;

    } catch (Exception $e) {
        error_log("Forum istatistikleri getirme hatası: " . $e->getMessage());
        return [
            'total_topics' => 0,
            'total_posts' => 0,
            'total_members' => 0,
            'newest_member' => null,
            'online_members' => 0
        ];
    }
}

/**
 * Son aktivite olan konuları getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $limit Limit
 * @param int|null $user_id Kullanıcı ID'si
 * @return array Konu listesi
 */
function get_recent_forum_topics(PDO $pdo, int $limit = 5, ?int $user_id = null): array {
    try {
        // Erişilebilir kategorileri al
        $accessible_categories = get_accessible_forum_categories($pdo, $user_id);
        $category_ids = array_column($accessible_categories, 'id');

        if (empty($category_ids)) {
            return [];
        }

        $in_clause = create_safe_in_clause($pdo, $category_ids, 'int');
        $safe_limit = create_safe_limit($limit, 0, 20);

        $query = "
            SELECT ft.*, fc.name as category_name, fc.color as category_color,
                   u.username as author_username,
                   lu.username as last_post_username,
                   ft.last_post_at
            FROM forum_topics ft
            JOIN forum_categories fc ON ft.category_id = fc.id
            JOIN users u ON ft.user_id = u.id
            LEFT JOIN users lu ON ft.last_post_user_id = lu.id
            WHERE ft.category_id " . $in_clause['placeholders'] . "
            ORDER BY ft.updated_at DESC
            $safe_limit
        ";

        $stmt = execute_safe_query($pdo, $query, $in_clause['params']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Son forum konuları getirme hatası: " . $e->getMessage());
        return [];
    }
}
?>
<?php
// Bu kodları mevcut src/functions/forum_functions.php dosyanızın sonuna ekleyin

/**
 * Kategori detaylarını slug ile getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $category_slug Kategori slug'ı
 * @param int|null $user_id Kullanıcı ID'si
 * @return array|null Kategori detayları
 */
function get_forum_category_details_by_slug(PDO $pdo, string $category_slug, ?int $user_id = null): ?array {
    try {
        $query = "
            SELECT fc.*,
                   COUNT(DISTINCT ft.id) as topic_count,
                   COUNT(DISTINCT fp.id) as post_count
            FROM forum_categories fc
            LEFT JOIN forum_topics ft ON fc.id = ft.category_id
            LEFT JOIN forum_posts fp ON ft.id = fp.topic_id
            WHERE fc.slug = :slug AND fc.is_active = 1
            GROUP BY fc.id
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':slug' => $category_slug]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            return null;
        }

        // Erişim kontrolü
        if (!can_user_access_forum_category($pdo, $category, $user_id)) {
            return null;
        }

        $category['topic_count'] = (int)$category['topic_count'];
        $category['post_count'] = (int)$category['post_count'];
        $category['can_create_topic'] = can_user_create_forum_topic($pdo, $category['id'], $user_id);

        return $category;

    } catch (Exception $e) {
        error_log("Forum kategorisi detay getirme hatası: " . $e->getMessage());
        return null;
    }
}

/**
 * Kategorinin etiketlerini getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $category_id Kategori ID'si
 * @return array Etiket listesi
 */
function get_category_tags(PDO $pdo, int $category_id): array {
    try {
        $query = "
            SELECT ft.*, COUNT(ftt.topic_id) as usage_count
            FROM forum_tags ft
            JOIN forum_topic_tags ftt ON ft.id = ftt.tag_id
            JOIN forum_topics t ON ftt.topic_id = t.id
            WHERE t.category_id = :category_id
            GROUP BY ft.id
            ORDER BY usage_count DESC, ft.name ASC
            LIMIT 20
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':category_id' => $category_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Kategori etiketleri getirme hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Forum içerik arama
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $search_query Arama sorgusu
 * @param int|null $user_id Kullanıcı ID'si
 * @param int $limit Limit
 * @return array Arama sonuçları
 */
function search_forum_content(PDO $pdo, string $search_query, ?int $user_id = null, int $limit = 50): array {
    try {
        $accessible_categories = get_accessible_forum_categories($pdo, $user_id);
        $category_ids = array_column($accessible_categories, 'id');

        if (empty($category_ids)) {
            return [];
        }

        $in_clause = create_safe_in_clause($pdo, $category_ids, 'int');
        $search_like = create_safe_like_pattern($search_query, 'contains');
        $safe_limit = create_safe_limit($limit, 0, 100);

        // Konu arama
        $topic_query = "
            SELECT 'topic' as type, ft.id, ft.title, ft.content, ft.created_at,
                   u.username, u.id as user_id, r.color as user_role_color,
                   CONCAT('/public/forum/topic.php?id=', ft.id) as url
            FROM forum_topics ft
            JOIN users u ON ft.user_id = u.id
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE ft.category_id " . $in_clause['placeholders'] . "
            AND (ft.title LIKE :search OR ft.content LIKE :search)
            ORDER BY ft.updated_at DESC
            " . $safe_limit;

        $topic_params = array_merge($in_clause['params'], [':search' => $search_like]);
        $stmt = execute_safe_query($pdo, $topic_query, $topic_params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Gönderi arama (daha az sonuç için)
        if (count($results) < $limit) {
            $post_query = "
                SELECT 'post' as type, fp.id, ft.title, fp.content, fp.created_at,
                       u.username, u.id as user_id, r.color as user_role_color,
                       CONCAT('/public/forum/topic.php?id=', ft.id, '#post-', fp.id) as url
                FROM forum_posts fp
                JOIN forum_topics ft ON fp.topic_id = ft.id
                JOIN users u ON fp.user_id = u.id
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                WHERE ft.category_id " . $in_clause['placeholders'] . "
                AND fp.content LIKE :search
                ORDER BY fp.created_at DESC
                LIMIT " . ($limit - count($results));

            $post_params = array_merge($in_clause['params'], [':search' => $search_like]);
            $stmt = execute_safe_query($pdo, $post_query, $post_params);
            $post_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = array_merge($results, $post_results);
        }

        return $results;

    } catch (Exception $e) {
        error_log("Forum arama hatası: " . $e->getMessage());
        return [];
    }
}

/**

/**
 * Belirli bir kategorideki konuları getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $category_id Kategori ID'si
 * @param int|null $user_id Kullanıcı ID'si
 * @param int $limit Limit
 * @param int $offset Offset
 * @return array Konular ve toplam sayı
 */
function get_forum_topics_in_category(PDO $pdo, int $category_id, ?int $user_id = null, int $limit = 20, int $offset = 0): array {
    try {
        // Kategoriye erişim kontrolü - basit kontrol
        $category_check = "SELECT id FROM forum_categories WHERE id = :category_id AND is_active = 1";
        $stmt_check = execute_safe_query($pdo, $category_check, [':category_id' => $category_id]);
        if (!$stmt_check->fetch()) {
            return ['topics' => [], 'total' => 0];
        }

        // Kullanıcının görebileceği konuları belirle
        $visibility_clause = "";
        $params = [':category_id' => $category_id];

        if ($user_id === null || !is_user_logged_in()) {
            $visibility_clause = " AND ft.visibility = 'public'";
        } else {
            if (is_user_approved()) {
                if (has_permission($pdo, 'forum.view_faction_only', $user_id)) {
                    // Tüm konuları görebilir
                } else if (has_permission($pdo, 'forum.view_members_only', $user_id)) {
                    $visibility_clause = " AND ft.visibility IN ('public', 'members_only')";
                } else {
                    $visibility_clause = " AND ft.visibility = 'public'";
                }
            } else {
                $visibility_clause = " AND ft.visibility = 'public'";
            }
        }

        // Toplam konu sayısı
        $count_query = "
            SELECT COUNT(*) 
            FROM forum_topics ft 
            WHERE ft.category_id = :category_id" . $visibility_clause;
        
        $stmt = execute_safe_query($pdo, $count_query, $params);
        $total = (int)$stmt->fetchColumn();

        // Konuları getir
        $safe_limit = create_safe_limit($limit, $offset, 100);
        
        $topics_query = "
            SELECT ft.*, 
                   u.username as author_username,
                   lpu.username as last_post_username,
                   lpu.id as last_post_user_id,
                   ar.color as author_role_color,
                   lpr.color as last_post_role_color
            FROM forum_topics ft
            JOIN users u ON ft.user_id = u.id
            LEFT JOIN users lpu ON ft.last_post_user_id = lpu.id
            LEFT JOIN (
                SELECT ur.user_id, r.color
                FROM user_roles ur 
                JOIN roles r ON ur.role_id = r.id 
                WHERE r.priority = (
                    SELECT MIN(r2.priority) 
                    FROM user_roles ur2 
                    JOIN roles r2 ON ur2.role_id = r2.id 
                    WHERE ur2.user_id = ur.user_id
                )
            ) ar ON u.id = ar.user_id
            LEFT JOIN (
                SELECT ur.user_id, r.color
                FROM user_roles ur 
                JOIN roles r ON ur.role_id = r.id 
                WHERE r.priority = (
                    SELECT MIN(r2.priority) 
                    FROM user_roles ur2 
                    JOIN roles r2 ON ur2.role_id = r2.id 
                    WHERE ur2.user_id = ur.user_id
                )
            ) lpr ON lpu.id = lpr.user_id
            WHERE ft.category_id = :category_id" . $visibility_clause . "
            ORDER BY ft.is_pinned DESC, ft.updated_at DESC
            " . $safe_limit;

        $stmt = execute_safe_query($pdo, $topics_query, $params);
        $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'topics' => $topics,
            'total' => $total
        ];

    } catch (Exception $e) {
        error_log("Forum kategori konuları getirme hatası: " . $e->getMessage());
        return ['topics' => [], 'total' => 0];
    }
}

/**
 * Forum kategori detaylarını ID ile getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $category_id Kategori ID'si
 * @param int|null $user_id Kullanıcı ID'si
 * @return array|null Kategori detayları
 */
function get_forum_category_details_by_id(PDO $pdo, int $category_id, ?int $user_id = null): ?array {
    try {
        $query = "
            SELECT fc.*,
                   COUNT(DISTINCT ft.id) as topic_count,
                   COUNT(DISTINCT fp.id) as post_count
            FROM forum_categories fc
            LEFT JOIN forum_topics ft ON fc.id = ft.category_id
            LEFT JOIN forum_posts fp ON ft.id = fp.topic_id
            WHERE fc.id = :category_id AND fc.is_active = 1
            GROUP BY fc.id
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':category_id' => $category_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$category) {
            return null;
        }

        // Erişim kontrolü
        if (!can_user_access_forum_category($pdo, $category, $user_id)) {
            return null;
        }

        $category['topic_count'] = (int)$category['topic_count'];
        $category['post_count'] = (int)$category['post_count'];
        $category['can_create_topic'] = can_user_create_forum_topic($pdo, $category_id, $user_id);

        return $category;

    } catch (Exception $e) {
        error_log("Forum kategorisi detay getirme hatası: " . $e->getMessage());
        return null;
    }
}
?>