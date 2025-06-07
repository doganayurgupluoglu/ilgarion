<?php
// /src/functions/enhanced_role_functions.php - Gelişmiş rol fonksiyonları

/**
 * Kullanıcının etkinlik rolü yönetimi yetkilerini kontrol eder
 */
function can_user_manage_event_role($pdo, $user_id, $role_data, $action = 'view') {
    // Temel yetki kontrolleri
    if (!is_user_approved($user_id)) {
        return false;
    }
    
    switch ($action) {
        case 'create':
            return has_permission($pdo, 'event_role.create', $user_id);
            
        case 'edit':
            // Kendi rolünü düzenleyebilir mi?
            if ($role_data && $role_data['created_by_user_id'] == $user_id && 
                has_permission($pdo, 'event_role.edit_own', $user_id)) {
                return true;
            }
            // Tüm rolleri düzenleyebilir mi?
            return has_permission($pdo, 'event_role.edit_all', $user_id);
            
        case 'delete':
            // Kendi rolünü silebilir mi?
            if ($role_data && $role_data['created_by_user_id'] == $user_id && 
                has_permission($pdo, 'event_role.delete_own', $user_id)) {
                return true;
            }
            // Tüm rolleri silebilir mi?
            return has_permission($pdo, 'event_role.delete_all', $user_id);
            
        case 'view':
            // Görünürlük kontrolü
            if (!$role_data) return true; // Genel liste için
            
            if ($role_data['visibility'] === 'public') {
                return has_permission($pdo, 'event_role.view_public', $user_id);
            } elseif ($role_data['visibility'] === 'members_only') {
                return has_permission($pdo, 'event_role.view_members_only', $user_id);
            }
            return has_permission($pdo, 'event_role.view_all', $user_id);
            
        case 'assign_to_event':
            return has_permission($pdo, 'event_role.assign_to_events', $user_id);
            
        case 'manage_participants':
            return has_permission($pdo, 'event_role.manage_participants', $user_id);
            
        default:
            return false;
    }
}

/**
 * Kullanıcının skill tag yönetimi yetkilerini kontrol eder
 */
function can_user_manage_skill_tags($pdo, $user_id, $target_user_id = null, $action = 'view') {
    if (!is_user_approved($user_id)) {
        return false;
    }
    
    switch ($action) {
        case 'view_own':
            return has_permission($pdo, 'skill_tag.view_own', $user_id);
            
        case 'view_all':
            return has_permission($pdo, 'skill_tag.view_all', $user_id);
            
        case 'add_own':
            return has_permission($pdo, 'skill_tag.add_own', $user_id);
            
        case 'verify_others':
            return has_permission($pdo, 'skill_tag.verify_others', $user_id);
            
        case 'manage_all':
            return has_permission($pdo, 'skill_tag.manage_all', $user_id);
            
        default:
            return false;
    }
}
function can_user_join_event_role($pdo, $user_id, $event_role_id, $event_id = null) {
    try {
        // Rol bilgilerini al
        $role_stmt = $pdo->prepare("
            SELECT er.*, 
                   COUNT(err.id) as requirement_count,
                   COUNT(CASE WHEN err.is_required = 1 THEN 1 END) as required_count
            FROM event_roles er
            LEFT JOIN event_role_requirements err ON er.id = err.event_role_id
            WHERE er.id = :role_id AND er.is_active = 1
            GROUP BY er.id
        ");
        $role_stmt->execute([':role_id' => $event_role_id]);
        $role = $role_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$role) {
            return ['success' => false, 'message' => 'Rol bulunamadı.'];
        }
        
        // Görünürlük kontrolü
        if ($role['visibility'] === 'members_only') {
            if (!is_user_approved($user_id)) {
                return ['success' => false, 'message' => 'Bu rol sadece onaylı üyeler içindir.'];
            }
        }
        
        // Kullanıcı bilgilerini al
        $user_stmt = $pdo->prepare("
            SELECT u.*, ur.role_name as user_role
            FROM users u
            LEFT JOIN user_roles ur ON u.role_id = ur.id
            WHERE u.id = :user_id
        ");
        $user_stmt->execute([':user_id' => $user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Kullanıcı bulunamadı.'];
        }
        
        // Kullanıcının skill tag'lerini al
        $tags_stmt = $pdo->prepare("
            SELECT tag_name, skill_level, is_verified
            FROM user_skill_tags
            WHERE user_id = :user_id
        ");
        $tags_stmt->execute([':user_id' => $user_id]);
        $user_tags = $tags_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Rol gereksinimlerini kontrol et
        if ($role['requirement_count'] > 0) {
            $req_stmt = $pdo->prepare("
                SELECT * FROM event_role_requirements
                WHERE event_role_id = :role_id
                ORDER BY is_required DESC, requirement_type
            ");
            $req_stmt->execute([':role_id' => $event_role_id]);
            $requirements = $req_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $required_met = 0;
            $required_total = 0;
            $failed_requirements = [];
            
            foreach ($requirements as $req) {
                if ($req['is_required']) {
                    $required_total++;
                }
                
                $met = false;
                
                switch ($req['requirement_type']) {
                    case 'user_role':
                        if (strtolower($user['user_role']) === strtolower($req['requirement_value'])) {
                            $met = true;
                        }
                        break;
                        
                    case 'skill_tag':
                        foreach ($user_tags as $tag) {
                            if (strtolower($tag['tag_name']) === strtolower($req['requirement_value'])) {
                                $met = true;
                                break;
                            }
                        }
                        break;
                        
                    case 'experience_level':
                        // Basit deneyim seviyesi kontrolü (gelecekte geliştirilebilir)
                        $user_level = calculate_user_experience_level($pdo, $user_id);
                        if (strtolower($user_level) === strtolower($req['requirement_value'])) {
                            $met = true;
                        }
                        break;
                }
                
                if ($req['is_required'] && $met) {
                    $required_met++;
                } elseif ($req['is_required'] && !$met) {
                    $failed_requirements[] = $req['requirement_value'];
                }
            }
            
            // Zorunlu gereksinimler karşılanmış mı?
            if ($required_total > 0 && $required_met < $required_total) {
                return [
                    'success' => false, 
                    'message' => 'Gerekli yetenek/roller eksik: ' . implode(', ', $failed_requirements)
                ];
            }
        }
        
        // Etkinlik bazlı kontroller (eğer etkinlik ID verilmişse)
        if ($event_id) {
            // Kullanıcı zaten bu etkinliğe katılmış mı?
            $participant_stmt = $pdo->prepare("
                SELECT erp.id 
                FROM event_role_participants erp
                JOIN event_role_slots ers ON erp.event_role_slot_id = ers.id
                WHERE ers.event_id = :event_id AND erp.user_id = :user_id AND erp.status = 'active'
            ");
            $participant_stmt->execute([':event_id' => $event_id, ':user_id' => $user_id]);
            
            if ($participant_stmt->fetch()) {
                return ['success' => false, 'message' => 'Zaten bu etkinliğe katılmışsınız.'];
            }
            
            // Rol slotu dolu mu?
            $slot_stmt = $pdo->prepare("
                SELECT ers.*, (ers.slot_count - ers.filled_count) as available_slots
                FROM event_role_slots ers
                WHERE ers.event_id = :event_id AND ers.event_role_id = :role_id
            ");
            $slot_stmt->execute([':event_id' => $event_id, ':role_id' => $event_role_id]);
            $slot = $slot_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$slot) {
                return ['success' => false, 'message' => 'Bu rol bu etkinlikte mevcut değil.'];
            }
            
            if ($slot['available_slots'] <= 0) {
                return ['success' => false, 'message' => 'Bu rol için yer kalmamış.'];
            }
        }
        
        return ['success' => true, 'message' => 'Katılabilirsiniz.'];
        
    } catch (PDOException $e) {
        error_log("Role eligibility check error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Kontrol yapılırken bir hata oluştu.'];
    }
}

/**
 * Kullanıcının deneyim seviyesini hesapla
 */
function calculate_user_experience_level($pdo, $user_id) {
    try {
        // Katıldığı etkinlik sayısı
        $events_stmt = $pdo->prepare("
            SELECT COUNT(*) as event_count
            FROM event_participants ep
            JOIN events e ON ep.event_id = e.id
            WHERE ep.user_id = :user_id AND e.status = 'completed'
        ");
        $events_stmt->execute([':user_id' => $user_id]);
        $event_count = $events_stmt->fetch(PDO::FETCH_ASSOC)['event_count'];
        
        // Sahip olduğu onaylı tag sayısı
        $tags_stmt = $pdo->prepare("
            SELECT COUNT(*) as tag_count
            FROM user_skill_tags
            WHERE user_id = :user_id AND is_verified = 1
        ");
        $tags_stmt->execute([':user_id' => $user_id]);
        $tag_count = $tags_stmt->fetch(PDO::FETCH_ASSOC)['tag_count'];
        
        // Basit seviye hesaplama
        $total_score = $event_count + ($tag_count * 2);
        
        if ($total_score >= 20) return 'expert';
        if ($total_score >= 10) return 'advanced';
        if ($total_score >= 5) return 'intermediate';
        return 'beginner';
        
    } catch (PDOException $e) {
        error_log("Experience level calculation error: " . $e->getMessage());
        return 'beginner';
    }
}

/**
 * Etkinlik rolüne katılım
 */
function join_event_role($pdo, $user_id, $event_role_slot_id) {
    try {
        $pdo->beginTransaction();
        
        // Slot bilgilerini al
        $slot_stmt = $pdo->prepare("
            SELECT ers.*, e.title as event_title, er.role_name
            FROM event_role_slots ers
            JOIN events e ON ers.event_id = e.id
            JOIN event_roles er ON ers.event_role_id = er.id
            WHERE ers.id = :slot_id
        ");
        $slot_stmt->execute([':slot_id' => $event_role_slot_id]);
        $slot = $slot_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$slot) {
            throw new Exception('Rol slotu bulunamadı.');
        }
        
        // Uygunluk kontrolü
        $eligibility = can_user_join_event_role($pdo, $user_id, $slot['event_role_id'], $slot['event_id']);
        if (!$eligibility['success']) {
            throw new Exception($eligibility['message']);
        }
        
        // Kullanıcıyı role ekle
        $join_stmt = $pdo->prepare("
            INSERT INTO event_role_participants (event_role_slot_id, user_id, status)
            VALUES (:slot_id, :user_id, 'active')
        ");
        $join_stmt->execute([':slot_id' => $event_role_slot_id, ':user_id' => $user_id]);
        
        // Slot dolu sayısını artır
        $update_stmt = $pdo->prepare("
            UPDATE event_role_slots 
            SET filled_count = filled_count + 1
            WHERE id = :slot_id
        ");
        $update_stmt->execute([':slot_id' => $event_role_slot_id]);
        
        // Event participants tablosuna da ekle (uyumluluk için)
        $event_participant_stmt = $pdo->prepare("
            INSERT IGNORE INTO event_participants (event_id, user_id, status, event_role_slot_id)
            VALUES (:event_id, :user_id, 'joined', :slot_id)
        ");
        $event_participant_stmt->execute([
            ':event_id' => $slot['event_id'],
            ':user_id' => $user_id,
            ':slot_id' => $event_role_slot_id
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => "'{$slot['role_name']}' rolü için '{$slot['event_title']}' etkinliğine başarıyla katıldınız."
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Join event role error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Join event role DB error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Katılım işlemi sırasında bir hata oluştu.'];
    }
}

/**
 * Etkinlik rolünden ayrılma
 */
function leave_event_role($pdo, $user_id, $event_role_slot_id) {
    try {
        $pdo->beginTransaction();
        
        // Katılım kontrolü
        $participant_stmt = $pdo->prepare("
            SELECT erp.*, ers.event_id, er.role_name, e.title as event_title
            FROM event_role_participants erp
            JOIN event_role_slots ers ON erp.event_role_slot_id = ers.id
            JOIN event_roles er ON ers.event_role_id = er.id
            JOIN events e ON ers.event_id = e.id
            WHERE erp.event_role_slot_id = :slot_id AND erp.user_id = :user_id AND erp.status = 'active'
        ");
        $participant_stmt->execute([':slot_id' => $event_role_slot_id, ':user_id' => $user_id]);
        $participant = $participant_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$participant) {
            throw new Exception('Bu role katılımınız bulunamadı.');
        }
        
        // Katılımı güncelle
        $leave_stmt = $pdo->prepare("
            UPDATE event_role_participants 
            SET status = 'left'
            WHERE event_role_slot_id = :slot_id AND user_id = :user_id
        ");
        $leave_stmt->execute([':slot_id' => $event_role_slot_id, ':user_id' => $user_id]);
        
        // Slot dolu sayısını azalt
        $update_stmt = $pdo->prepare("
            UPDATE event_role_slots 
            SET filled_count = GREATEST(0, filled_count - 1)
            WHERE id = :slot_id
        ");
        $update_stmt->execute([':slot_id' => $event_role_slot_id]);
        
        // Event participants tablosunu güncelle
        $event_participant_stmt = $pdo->prepare("
            UPDATE event_participants 
            SET status = 'left'
            WHERE event_id = :event_id AND user_id = :user_id AND event_role_slot_id = :slot_id
        ");
        $event_participant_stmt->execute([
            ':event_id' => $participant['event_id'],
            ':user_id' => $user_id,
            ':slot_id' => $event_role_slot_id
        ]);
        
        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => "'{$participant['role_name']}' rolünden '{$participant['event_title']}' etkinliğinden başarıyla ayrıldınız."
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Leave event role error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Leave event role DB error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Ayrılma işlemi sırasında bir hata oluştu.'];
    }
}

/**
 * Kullanıcının etkinlik rollerini getir
 */
function get_user_event_roles($pdo, $user_id, $event_id = null) {
    try {
        $where_clause = "WHERE erp.user_id = :user_id AND erp.status = 'active'";
        $params = [':user_id' => $user_id];
        
        if ($event_id) {
            $where_clause .= " AND ers.event_id = :event_id";
            $params[':event_id'] = $event_id;
        }
        
        $stmt = $pdo->prepare("
            SELECT erp.*, ers.event_id, ers.slot_count, ers.filled_count,
                   er.role_name, er.icon_class, er.role_description,
                   e.title as event_title, e.start_date, e.status as event_status
            FROM event_role_participants erp
            JOIN event_role_slots ers ON erp.event_role_slot_id = ers.id
            JOIN event_roles er ON ers.event_role_id = er.id
            JOIN events e ON ers.event_id = e.id
            $where_clause
            ORDER BY e.start_date DESC, er.role_name ASC
        ");
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Get user event roles error: " . $e->getMessage());
        return [];
    }
}

/**
 * Etkinlik için mevcut rol slotlarını getir
 */
function get_event_role_slots($pdo, $event_id, $user_id = null) {
    try {
        $stmt = $pdo->prepare("
            SELECT ers.*, er.role_name, er.icon_class, er.role_description, er.visibility,
                   (ers.slot_count - ers.filled_count) as available_slots,
                   ls.set_name as suggested_loadout_name,
                   CASE WHEN erp.id IS NOT NULL THEN 1 ELSE 0 END as user_joined
            FROM event_role_slots ers
            JOIN event_roles er ON ers.event_role_id = er.id
            LEFT JOIN loadout_sets ls ON er.suggested_loadout_id = ls.id
            " . ($user_id ? "LEFT JOIN event_role_participants erp ON ers.id = erp.event_role_slot_id AND erp.user_id = :user_id AND erp.status = 'active'" : "") . "
            WHERE ers.event_id = :event_id AND er.is_active = 1
            ORDER BY ers.display_order ASC, er.role_name ASC
        ");
        
        $params = [':event_id' => $event_id];
        if ($user_id) {
            $params[':user_id'] = $user_id;
        }
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Get event role slots error: " . $e->getMessage());
        return [];
    }
}

/**
 * Rol için katılımcıları getir
 */
function get_role_participants($pdo, $event_role_slot_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT erp.*, u.username, u.avatar_url, u.discord_username,
                   ur.role_name as user_role, ur.color as role_color
            FROM event_role_participants erp
            JOIN users u ON erp.user_id = u.id
            LEFT JOIN user_roles ur ON u.role_id = ur.id
            WHERE erp.event_role_slot_id = :slot_id AND erp.status = 'active'
            ORDER BY erp.joined_at ASC
        ");
        $stmt->execute([':slot_id' => $event_role_slot_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Get role participants error: " . $e->getMessage());
        return [];
    }
}

/**
 * Kullanıcı skill tag'ı ekle
 */
function add_user_skill_tag($pdo, $user_id, $tag_name, $skill_level = 'beginner', $verified_by = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_skill_tags (user_id, tag_name, skill_level, verified_by_user_id, is_verified)
            VALUES (:user_id, :tag_name, :skill_level, :verified_by, :is_verified)
            ON DUPLICATE KEY UPDATE 
                skill_level = VALUES(skill_level),
                verified_by_user_id = VALUES(verified_by_user_id),
                is_verified = VALUES(is_verified)
        ");
        
        return $stmt->execute([
            ':user_id' => $user_id,
            ':tag_name' => $tag_name,
            ':skill_level' => $skill_level,
            ':verified_by' => $verified_by,
            ':is_verified' => $verified_by ? 1 : 0
        ]);
        
    } catch (PDOException $e) {
        error_log("Add user skill tag error: " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcının skill tag'lerini getir
 */
function get_user_skill_tags($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT ust.*, u.username as verified_by_username
            FROM user_skill_tags ust
            LEFT JOIN users u ON ust.verified_by_user_id = u.id
            WHERE ust.user_id = :user_id
            ORDER BY ust.is_verified DESC, ust.skill_level DESC, ust.tag_name ASC
        ");
        $stmt->execute([':user_id' => $user_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Get user skill tags error: " . $e->getMessage());
        return [];
    }
}

/**
 * Etkinlik rolü istatistiklerini getir
 */
function get_event_role_stats($pdo, $event_role_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT ers.event_id) as events_used,
                SUM(ers.slot_count) as total_slots,
                SUM(ers.filled_count) as filled_slots,
                AVG(ers.filled_count / ers.slot_count) * 100 as fill_rate
            FROM event_role_slots ers
            JOIN events e ON ers.event_id = e.id
            WHERE ers.event_role_id = :role_id AND e.status != 'cancelled'
        ");
        $stmt->execute([':role_id' => $event_role_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Get event role stats error: " . $e->getMessage());
        return [
            'events_used' => 0,
            'total_slots' => 0,
            'filled_slots' => 0,
            'fill_rate' => 0
        ];
    }
}

/**
 * Popüler rolleri getir
 */
function get_popular_event_roles($pdo, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT er.*, 
                   COUNT(DISTINCT ers.event_id) as events_used,
                   SUM(ers.filled_count) as total_participants,
                   AVG(ers.filled_count / ers.slot_count) * 100 as avg_fill_rate
            FROM event_roles er
            LEFT JOIN event_role_slots ers ON er.id = ers.event_role_id
            LEFT JOIN events e ON ers.event_id = e.id AND e.status != 'cancelled'
            WHERE er.is_active = 1
            GROUP BY er.id
            ORDER BY total_participants DESC, events_used DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Get popular event roles error: " . $e->getMessage());
        return [];
    }
}

/**
 * Rol gereksinimlerini kontrol eden yardımcı fonksiyon
 */
function check_role_requirements($pdo, $user_id, $event_role_id) {
    try {
        $requirements = [];
        $met_requirements = [];
        $failed_requirements = [];
        
        // Gereksinimleri al
        $req_stmt = $pdo->prepare("
            SELECT * FROM event_role_requirements
            WHERE event_role_id = :role_id
            ORDER BY is_required DESC, requirement_type
        ");
        $req_stmt->execute([':role_id' => $event_role_id]);
        $role_requirements = $req_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($role_requirements)) {
            return [
                'success' => true,
                'requirements' => [],
                'met' => [],
                'failed' => []
            ];
        }
        
        // Kullanıcı bilgilerini al
        $user_stmt = $pdo->prepare("
            SELECT u.*, ur.role_name as user_role
            FROM users u
            LEFT JOIN user_roles ur ON u.role_id = ur.id
            WHERE u.id = :user_id
        ");
        $user_stmt->execute([':user_id' => $user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Kullanıcı tag'lerini al
        $user_tags = get_user_skill_tags($pdo, $user_id);
        $user_tag_names = array_column($user_tags, 'tag_name');
        
        foreach ($role_requirements as $req) {
            $requirement_info = [
                'type' => $req['requirement_type'],
                'value' => $req['requirement_value'],
                'required' => (bool)$req['is_required'],
                'met' => false
            ];
            
            switch ($req['requirement_type']) {
                case 'user_role':
                    if (strtolower($user['user_role']) === strtolower($req['requirement_value'])) {
                        $requirement_info['met'] = true;
                        $met_requirements[] = $requirement_info;
                    } else {
                        $failed_requirements[] = $requirement_info;
                    }
                    break;
                    
                case 'skill_tag':
                    if (in_array(strtolower($req['requirement_value']), array_map('strtolower', $user_tag_names))) {
                        $requirement_info['met'] = true;
                        $met_requirements[] = $requirement_info;
                    } else {
                        $failed_requirements[] = $requirement_info;
                    }
                    break;
                    
                case 'experience_level':
                    $user_level = calculate_user_experience_level($pdo, $user_id);
                    if (strtolower($user_level) === strtolower($req['requirement_value'])) {
                        $requirement_info['met'] = true;
                        $met_requirements[] = $requirement_info;
                    } else {
                        $failed_requirements[] = $requirement_info;
                    }
                    break;
            }
            
            $requirements[] = $requirement_info;
        }
        
        // Zorunlu gereksinimler karşılandı mı?
        $required_failed = array_filter($failed_requirements, function($req) {
            return $req['required'];
        });
        
        return [
            'success' => empty($required_failed),
            'requirements' => $requirements,
            'met' => $met_requirements,
            'failed' => $failed_requirements,
            'required_failed' => $required_failed
        ];
        
    } catch (PDOException $e) {
        error_log("Check role requirements error: " . $e->getMessage());
        return [
            'success' => false,
            'requirements' => [],
            'met' => [],
            'failed' => [],
            'error' => 'Gereksinimler kontrol edilemedi.'
        ];
    }
}
?>