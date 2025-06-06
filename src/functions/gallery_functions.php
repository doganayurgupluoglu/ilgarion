<?php
// src/functions/gallery_functions.php

/**
 * Galeri fotoğraflarını getirir (erişim kontrolü ile)
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int|null $user_id Kullanıcı ID'si
 * @param int $limit Sayfa başına fotoğraf sayısı
 * @param int $offset Başlangıç noktası
 * @param string $sort Sıralama türü
 * @param string $user_filter Kullanıcı filtresi
 * @return array Fotoğraflar ve toplam sayı
 */
function get_gallery_photos(PDO $pdo, ?int $user_id = null, int $limit = 20, int $offset = 0, string $sort = 'newest', string $user_filter = ''): array {
    try {
        // Sıralama kontrolü
        $order_clause = match($sort) {
            'oldest' => 'gp.uploaded_at ASC',
            'most_liked' => 'like_count DESC, gp.uploaded_at DESC',
            'most_commented' => 'comment_count DESC, gp.uploaded_at DESC',
            default => 'gp.uploaded_at DESC' // newest
        };
        
        // WHERE koşulları
        $where_conditions = [];
        $params = [];
        
        // Kullanıcı filtresi
        if (!empty($user_filter)) {
            $where_conditions[] = "u.username LIKE :user_filter";
            $params[':user_filter'] = '%' . $user_filter . '%';
        }
        
        // Erişim kontrolü
        if (!$user_id) {
            // Giriş yapmamış kullanıcılar sadece herkese açık fotoğrafları görebilir
            $where_conditions[] = "gp.is_public_no_auth = 1";
        } elseif (!is_user_approved()) {
            // Onaylanmamış kullanıcılar sadece herkese açık fotoğrafları görebilir
            $where_conditions[] = "gp.is_public_no_auth = 1";
        } else {
            // Onaylı kullanıcılar için erişim kontrolü
            if (!has_permission($pdo, 'gallery.manage_all', $user_id)) {
                $where_conditions[] = "(gp.is_public_no_auth = 1 OR gp.is_members_only = 1 OR gp.user_id = :current_user_id)";
                $params[':current_user_id'] = $user_id;
            }
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Toplam sayı sorgusu
        $count_query = "
            SELECT COUNT(DISTINCT gp.id)
            FROM gallery_photos gp
            JOIN users u ON gp.user_id = u.id
            $where_clause
        ";
        
        $stmt = $pdo->prepare($count_query);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        
        // Fotoğraflar sorgusu
        $photos_query = "
            SELECT gp.*,
                   u.username,
                   ur.color as user_role_color,
                   ur.name as user_role_name,
                   COUNT(DISTINCT gpl.id) as like_count,
                   COUNT(DISTINCT gpc.id) as comment_count,
                   " . ($user_id ? "(SELECT COUNT(*) FROM gallery_photo_likes WHERE photo_id = gp.id AND user_id = :like_user_id) as user_liked" : "0 as user_liked") . "
            FROM gallery_photos gp
            JOIN users u ON gp.user_id = u.id
            LEFT JOIN user_roles uur ON u.id = uur.user_id
            LEFT JOIN roles ur ON uur.role_id = ur.id AND ur.priority = (
                SELECT MIN(r2.priority) FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id 
                WHERE ur2.user_id = u.id
            )
            LEFT JOIN gallery_photo_likes gpl ON gp.id = gpl.photo_id
            LEFT JOIN gallery_photo_comments gpc ON gp.id = gpc.photo_id
            $where_clause
            GROUP BY gp.id
            ORDER BY $order_clause
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($photos_query);
        
        if ($user_id) {
            $params[':like_user_id'] = $user_id;
        }
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        $stmt->execute($params);
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'photos' => $photos,
            'total' => $total
        ];
        
    } catch (Exception $e) {
        error_log("Gallery photos fetch error: " . $e->getMessage());
        return ['photos' => [], 'total' => 0];
    }
}

/**
 * Galeri istatistiklerini getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int|null $user_id Kullanıcı ID'si (erişim kontrolü için)
 * @return array İstatistikler
 */
function get_gallery_statistics(PDO $pdo, ?int $user_id = null): array {
    try {
        // Erişim kontrolü koşulları
        $where_conditions = [];
        $params = [];
        
        if (!$user_id || !is_user_approved()) {
            $where_conditions[] = "gp.is_public_no_auth = 1";
        } elseif (!has_permission($pdo, 'gallery.manage_all', $user_id)) {
            $where_conditions[] = "(gp.is_public_no_auth = 1 OR gp.is_members_only = 1)";
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Toplam fotoğraf sayısı
        $photos_query = "SELECT COUNT(*) FROM gallery_photos gp $where_clause";
        $stmt = $pdo->prepare($photos_query);
        $stmt->execute($params);
        $total_photos = (int)$stmt->fetchColumn();
        
        // Toplam katkıda bulunan sayısı
        $contributors_query = "
            SELECT COUNT(DISTINCT gp.user_id) 
            FROM gallery_photos gp 
            $where_clause
        ";
        $stmt = $pdo->prepare($contributors_query);
        $stmt->execute($params);
        $total_contributors = (int)$stmt->fetchColumn();
        
        // Toplam beğeni sayısı
        $likes_query = "
            SELECT COUNT(gpl.id)
            FROM gallery_photo_likes gpl
            JOIN gallery_photos gp ON gpl.photo_id = gp.id
            $where_clause
        ";
        $stmt = $pdo->prepare($likes_query);
        $stmt->execute($params);
        $total_likes = (int)$stmt->fetchColumn();
        
        return [
            'total_photos' => $total_photos,
            'total_contributors' => $total_contributors,
            'total_likes' => $total_likes
        ];
        
    } catch (Exception $e) {
        error_log("Gallery statistics error: " . $e->getMessage());
        return [
            'total_photos' => 0,
            'total_contributors' => 0,
            'total_likes' => 0
        ];
    }
}

/**
 * Fotoğraf detaylarını getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $photo_id Fotoğraf ID'si
 * @param int|null $user_id Kullanıcı ID'si
 * @return array|false Fotoğraf detayları
 */
function get_photo_details(PDO $pdo, int $photo_id, ?int $user_id = null) {
    try {
        $query = "
            SELECT gp.*,
                   u.username,
                   u.avatar_path,
                   ur.color as user_role_color,
                   ur.name as user_role_name,
                   COUNT(DISTINCT gpl.id) as like_count,
                   COUNT(DISTINCT gpc.id) as comment_count,
                   " . ($user_id ? "(SELECT COUNT(*) FROM gallery_photo_likes WHERE photo_id = gp.id AND user_id = :like_user_id) as user_liked" : "0 as user_liked") . "
            FROM gallery_photos gp
            JOIN users u ON gp.user_id = u.id
            LEFT JOIN user_roles uur ON u.id = uur.user_id
            LEFT JOIN roles ur ON uur.role_id = ur.id AND ur.priority = (
                SELECT MIN(r2.priority) FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id 
                WHERE ur2.user_id = u.id
            )
            LEFT JOIN gallery_photo_likes gpl ON gp.id = gpl.photo_id
            LEFT JOIN gallery_photo_comments gpc ON gp.id = gpc.photo_id
            WHERE gp.id = :photo_id
            GROUP BY gp.id
        ";
        
        $params = [':photo_id' => $photo_id];
        if ($user_id) {
            $params[':like_user_id'] = $user_id;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$photo) {
            return false;
        }
        
        // Erişim kontrolü
        if (!can_user_access_photo($pdo, $photo, $user_id)) {
            return false;
        }
        
        // Kullanıcı yetkilerini ekle
        if ($user_id) {
            $photo['can_like'] = $photo['user_id'] != $user_id && has_permission($pdo, 'gallery.like', $user_id);
            $photo['can_comment'] = has_permission($pdo, 'gallery.comment.create', $user_id);
            $photo['can_delete'] = ($photo['user_id'] == $user_id && has_permission($pdo, 'gallery.delete_own', $user_id)) || 
                                   has_permission($pdo, 'gallery.delete_any', $user_id);
        } else {
            $photo['can_like'] = false;
            $photo['can_comment'] = false;
            $photo['can_delete'] = false;
        }
        
        return $photo;
        
    } catch (Exception $e) {
        error_log("Photo details fetch error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fotoğrafa erişim kontrolü
 * @param PDO $pdo Veritabanı bağlantısı
 * @param array $photo Fotoğraf verileri
 * @param int|null $user_id Kullanıcı ID'si
 * @return bool Erişebilirse true
 */
function can_user_access_photo(PDO $pdo, array $photo, ?int $user_id = null): bool {
    // Admin her şeyi görebilir
    if ($user_id && has_permission($pdo, 'gallery.manage_all', $user_id)) {
        return true;
    }
    
    // Herkese açık fotoğraf
    if ($photo['is_public_no_auth']) {
        return true;
    }
    
    // Giriş yapmamış kullanıcılar sadece herkese açık fotoğrafları görebilir
    if (!$user_id) {
        return false;
    }
    
    // Onaylanmamış kullanıcılar sadece herkese açık fotoğrafları görebilir
    if (!is_user_approved()) {
        return false;
    }
    
    // Sadece üyelere açık fotoğraf
    if ($photo['is_members_only']) {
        return true;
    }
    
    // Kendi fotoğrafı
    if ($photo['user_id'] == $user_id) {
        return true;
    }
    
    return false;
}

/**
 * Fotoğraf yorumlarını getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $photo_id Fotoğraf ID'si
 * @param int|null $user_id Kullanıcı ID'si
 * @return array Yorumlar
 */
function get_photo_comments(PDO $pdo, int $photo_id, ?int $user_id = null): array {
    try {
        $query = "
            SELECT gpc.*,
                   u.username,
                   u.avatar_path,
                   ur.color as user_role_color,
                   ur.name as user_role_name,
                   COUNT(DISTINCT gcl.id) as like_count,
                   " . ($user_id ? "(SELECT COUNT(*) FROM gallery_comment_likes WHERE comment_id = gpc.id AND user_id = :like_user_id) as user_liked" : "0 as user_liked") . "
            FROM gallery_photo_comments gpc
            JOIN users u ON gpc.user_id = u.id
            LEFT JOIN user_roles uur ON u.id = uur.user_id
            LEFT JOIN roles ur ON uur.role_id = ur.id AND ur.priority = (
                SELECT MIN(r2.priority) FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id 
                WHERE ur2.user_id = u.id
            )
            LEFT JOIN gallery_comment_likes gcl ON gpc.id = gcl.comment_id
            WHERE gpc.photo_id = :photo_id
            GROUP BY gpc.id
            ORDER BY gpc.created_at ASC
        ";
        
        $params = [':photo_id' => $photo_id];
        if ($user_id) {
            $params[':like_user_id'] = $user_id;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Her yorum için kullanıcı yetkilerini ekle
        foreach ($comments as &$comment) {
            if ($user_id) {
                $comment['can_like'] = $comment['user_id'] != $user_id && has_permission($pdo, 'gallery.comment.like', $user_id);
                $comment['can_edit'] = ($comment['user_id'] == $user_id && has_permission($pdo, 'gallery.comment.edit_own', $user_id)) || 
                                      has_permission($pdo, 'gallery.comment.edit_all', $user_id);
                $comment['can_delete'] = ($comment['user_id'] == $user_id && has_permission($pdo, 'gallery.comment.delete_own', $user_id)) || 
                                        has_permission($pdo, 'gallery.comment.delete_all', $user_id);
            } else {
                $comment['can_like'] = false;
                $comment['can_edit'] = false;
                $comment['can_delete'] = false;
            }
        }
        
        return $comments;
        
    } catch (Exception $e) {
        error_log("Photo comments fetch error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fotoğraf yükler
 * @param PDO $pdo Veritabanı bağlantısı
 * @param array $file_data $_FILES array'i
 * @param array $form_data Form verileri
 * @param int $user_id Kullanıcı ID'si
 * @return array Sonuç
 */

/**
 * Fotoğraf beğeni durumunu değiştirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $photo_id Fotoğraf ID'si
 * @param int $user_id Kullanıcı ID'si
 * @return array Sonuç
 */
function toggle_photo_like(PDO $pdo, int $photo_id, int $user_id): array {
    try {
        // Yetki kontrolü
        if (!has_permission($pdo, 'gallery.like', $user_id)) {
            return ['success' => false, 'message' => 'Beğeni yetkiniz bulunmamaktadır.'];
        }
        
        // Fotoğraf kontrolü
        $photo_query = "SELECT user_id FROM gallery_photos WHERE id = :photo_id";
        $stmt = execute_safe_query($pdo, $photo_query, [':photo_id' => $photo_id]);
        $photo_owner_id = $stmt->fetchColumn();
        
        if (!$photo_owner_id) {
            return ['success' => false, 'message' => 'Fotoğraf bulunamadı.'];
        }
        
        // Kendi fotoğrafını beğenemez
        if ($photo_owner_id == $user_id) {
            return ['success' => false, 'message' => 'Kendi fotoğrafınızı beğenemezsiniz.'];
        }
        
        // Mevcut beğeni durumunu kontrol et
        $like_check_query = "SELECT id FROM gallery_photo_likes WHERE photo_id = :photo_id AND user_id = :user_id";
        $stmt = execute_safe_query($pdo, $like_check_query, [':photo_id' => $photo_id, ':user_id' => $user_id]);
        $existing_like = $stmt->fetchColumn();
        
        if ($existing_like) {
            // Beğeniyi kaldır
            $delete_query = "DELETE FROM gallery_photo_likes WHERE photo_id = :photo_id AND user_id = :user_id";
            execute_safe_query($pdo, $delete_query, [':photo_id' => $photo_id, ':user_id' => $user_id]);
            $liked = false;
            $message = 'Beğeni kaldırıldı.';
        } else {
            // Beğeni ekle
            $insert_query = "INSERT INTO gallery_photo_likes (photo_id, user_id) VALUES (:photo_id, :user_id)";
            execute_safe_query($pdo, $insert_query, [':photo_id' => $photo_id, ':user_id' => $user_id]);
            $liked = true;
            $message = 'Fotoğraf beğenildi.';
        }
        
        // Güncel beğeni sayısını al
        $count_query = "SELECT COUNT(*) FROM gallery_photo_likes WHERE photo_id = :photo_id";
        $stmt = execute_safe_query($pdo, $count_query, [':photo_id' => $photo_id]);
        $like_count = (int)$stmt->fetchColumn();
        
        return [
            'success' => true,
            'message' => $message,
            'liked' => $liked,
            'like_count' => $like_count
        ];
        
    } catch (Exception $e) {
        error_log("Photo like toggle error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Beğeni işlemi sırasında bir hata oluştu.'];
    }
}

/**
 * Fotoğraf yorumu ekler
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $photo_id Fotoğraf ID'si
 * @param string $comment_text Yorum metni
 * @param int $user_id Kullanıcı ID'si
 * @return array Sonuç
 */
function add_photo_comment(PDO $pdo, int $photo_id, string $comment_text, int $user_id): array {
    try {
        // Yetki kontrolü
        if (!has_permission($pdo, 'gallery.comment.create', $user_id)) {
            return ['success' => false, 'message' => 'Yorum yazma yetkiniz bulunmamaktadır.'];
        }
        
        // Yorum uzunluk kontrolü
        $comment_text = trim($comment_text);
        if (strlen($comment_text) < 1) {
            return ['success' => false, 'message' => 'Yorum boş olamaz.'];
        }
        
        if (strlen($comment_text) > 1000) {
            return ['success' => false, 'message' => 'Yorum çok uzun. Maksimum 1000 karakter olabilir.'];
        }
        
        // Fotoğraf kontrolü ve erişim
        $photo_query = "SELECT * FROM gallery_photos WHERE id = :photo_id";
        $stmt = execute_safe_query($pdo, $photo_query, [':photo_id' => $photo_id]);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$photo) {
            return ['success' => false, 'message' => 'Fotoğraf bulunamadı.'];
        }
        
        if (!can_user_access_photo($pdo, $photo, $user_id)) {
            return ['success' => false, 'message' => 'Bu fotoğrafa erişim yetkiniz bulunmamaktadır.'];
        }
        
        // Yorumu ekle
        $insert_query = "
            INSERT INTO gallery_photo_comments (photo_id, user_id, comment_text)
            VALUES (:photo_id, :user_id, :comment_text)
        ";
        
        $insert_params = [
            ':photo_id' => $photo_id,
            ':user_id' => $user_id,
            ':comment_text' => $comment_text
        ];
        
        $stmt = execute_safe_query($pdo, $insert_query, $insert_params);
        $comment_id = $pdo->lastInsertId();
        
        // Yorum verilerini getir
        $comment_data_query = "
            SELECT gpc.*,
                   u.username,
                   u.avatar_path,
                   ur.color as user_role_color,
                   ur.name as user_role_name
            FROM gallery_photo_comments gpc
            JOIN users u ON gpc.user_id = u.id
            LEFT JOIN user_roles uur ON u.id = uur.user_id
            LEFT JOIN roles ur ON uur.role_id = ur.id AND ur.priority = (
                SELECT MIN(r2.priority) FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id 
                WHERE ur2.user_id = u.id
            )
            WHERE gpc.id = :comment_id
        ";
        
        $stmt = execute_safe_query($pdo, $comment_data_query, [':comment_id' => $comment_id]);
        $comment_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Yetkileri ekle
        $comment_data['can_like'] = has_permission($pdo, 'gallery.comment.like', $user_id);
        $comment_data['can_edit'] = has_permission($pdo, 'gallery.comment.edit_own', $user_id);
        $comment_data['can_delete'] = has_permission($pdo, 'gallery.comment.delete_own', $user_id);
        $comment_data['like_count'] = 0;
        $comment_data['user_liked'] = 0;
        
        return [
            'success' => true,
            'message' => 'Yorum eklendi.',
            'comment_data' => $comment_data
        ];
        
    } catch (Exception $e) {
        error_log("Photo comment add error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Yorum eklenirken bir hata oluştu.'];
    }
}

/**
 * Fotoğraf siler
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $photo_id Fotoğraf ID'si
 * @param int $user_id Kullanıcı ID'si
 * @return array Sonuç
 */
function delete_photo(PDO $pdo, int $photo_id, int $user_id): array {
    try {
        // Fotoğraf bilgilerini al
        $photo_query = "SELECT * FROM gallery_photos WHERE id = :photo_id";
        $stmt = execute_safe_query($pdo, $photo_query, [':photo_id' => $photo_id]);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$photo) {
            return ['success' => false, 'message' => 'Fotoğraf bulunamadı.'];
        }
        
        // Yetki kontrolü
        $can_delete = ($photo['user_id'] == $user_id && has_permission($pdo, 'gallery.delete_own', $user_id)) || 
                      has_permission($pdo, 'gallery.delete_any', $user_id);
        
        if (!$can_delete) {
            return ['success' => false, 'message' => 'Bu fotoğrafı silme yetkiniz bulunmamaktadır.'];
        }
        
        return execute_safe_transaction($pdo, function($pdo) use ($photo, $photo_id, $user_id) {
            // Fotoğrafı veritabanından sil (CASCADE ile yorumlar ve beğeniler de silinir)
            $delete_query = "DELETE FROM gallery_photos WHERE id = :photo_id";
            execute_safe_query($pdo, $delete_query, [':photo_id' => $photo_id]);
            
            // Dosyayı sil
            $file_path = BASE_PATH . '' . $photo['image_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Audit log
            audit_log($pdo, $user_id, 'gallery_photo_deleted', 'gallery_photo', $photo_id, 
                ['image_path' => $photo['image_path']], null);
            
            return [
                'success' => true,
                'message' => 'Fotoğraf başarıyla silindi.'
            ];
        });
        
    } catch (Exception $e) {
        error_log("Photo delete error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Fotoğraf silinirken bir hata oluştu.'];
    }
}
?>