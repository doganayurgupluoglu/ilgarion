<?php
// /src/functions/team_functions.php

require_once dirname(__DIR__) . '/config/database.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';

/**
 * Yeni takım oluşturur
 * @param PDO $pdo Veritabanı bağlantısı
 * @param array $team_data Takım verileri
 * @param int $user_id Oluşturan kullanıcı ID
 * @return int|false Oluşturulan takım ID'si veya false
 */
function create_team(PDO $pdo, array $team_data, int $user_id) {
    try {
        return execute_safe_transaction($pdo, function($pdo) use ($team_data, $user_id) {
            // Slug oluştur
            $slug = generate_team_slug($pdo, $team_data['name']);
            
            $query = "
                INSERT INTO teams (
                    name, slug, description, tag, color, max_members, 
                    is_recruitment_open, created_by_user_id
                ) VALUES (
                    :name, :slug, :description, :tag, :color, :max_members,
                    :is_recruitment_open, :created_by_user_id
                )
            ";
            
            $params = [
                ':name' => $team_data['name'],
                ':slug' => $slug,
                ':description' => $team_data['description'],
                ':tag' => $team_data['tag'],
                ':color' => $team_data['color'],
                ':max_members' => $team_data['max_members'],
                ':is_recruitment_open' => $team_data['is_recruitment_open'],
                ':created_by_user_id' => $user_id
            ];
            
            $stmt = execute_safe_query($pdo, $query, $params);
            $team_id = $pdo->lastInsertId();
            
            // Audit log
            audit_log($pdo, $user_id, 'team_created', 'team', $team_id, null, $team_data);
            
            return $team_id;
        });
    } catch (Exception $e) {
        error_log("Team creation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Takım bilgilerini günceller
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $team_id Takım ID
 * @param array $team_data Güncellenecek veriler
 * @param int $user_id Güncelleyen kullanıcı ID
 * @return bool Başarılı ise true
 */
function update_team(PDO $pdo, int $team_id, array $team_data, int $user_id): bool {
    try {
        return execute_safe_transaction($pdo, function($pdo) use ($team_id, $team_data, $user_id) {
            // Eski verileri al (audit için)
            $old_data = get_team_by_id($pdo, $team_id);
            if (!$old_data) {
                throw new Exception('Takım bulunamadı');
            }
            
            // Slug güncellenmişse yeni slug oluştur
            $slug = $old_data['slug'];
            if ($team_data['name'] !== $old_data['name']) {
                $slug = generate_team_slug($pdo, $team_data['name'], $team_id);
            }
            
            $query = "
                UPDATE teams SET 
                    name = :name,
                    slug = :slug,
                    description = :description,
                    tag = :tag,
                    color = :color,
                    max_members = :max_members,
                    is_recruitment_open = :is_recruitment_open,
                    updated_at = NOW()
                WHERE id = :team_id
            ";
            
            $params = [
                ':name' => $team_data['name'],
                ':slug' => $slug,
                ':description' => $team_data['description'],
                ':tag' => $team_data['tag'],
                ':color' => $team_data['color'],
                ':max_members' => $team_data['max_members'],
                ':is_recruitment_open' => $team_data['is_recruitment_open'],
                ':team_id' => $team_id
            ];
            
            execute_safe_query($pdo, $query, $params);
            
            // Audit log
            audit_log($pdo, $user_id, 'team_updated', 'team', $team_id, $old_data, $team_data);
            
            return true;
        });
    } catch (Exception $e) {
        error_log("Team update error: " . $e->getMessage());
        return false;
    }
}

/**
 * Takım bilgilerini ID ile getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $team_id Takım ID
 * @return array|null Takım verisi veya null
 */
function get_team_by_id(PDO $pdo, int $team_id): ?array {
    try {
        $query = "
            SELECT t.*, 
                   u.username as creator_username,
                   (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id AND tm.status = 'active') as member_count
            FROM teams t
            LEFT JOIN users u ON t.created_by_user_id = u.id
            WHERE t.id = :team_id
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':team_id' => $team_id]);
        $team = $stmt->fetch();
        
        return $team ?: null;
    } catch (Exception $e) {
        error_log("Get team by ID error: " . $e->getMessage());
        return null;
    }
}

/**
 * Takım bilgilerini slug ile getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $slug Takım slug
 * @return array|null Takım verisi veya null
 */
function get_team_by_slug(PDO $pdo, string $slug): ?array {
    try {
        $query = "
            SELECT t.*, 
                   u.username as creator_username,
                   (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id AND tm.status = 'active') as member_count
            FROM teams t
            LEFT JOIN users u ON t.created_by_user_id = u.id
            WHERE t.slug = :slug
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':slug' => $slug]);
        $team = $stmt->fetch();
        
        return $team ?: null;
    } catch (Exception $e) {
        error_log("Get team by slug error: " . $e->getMessage());
        return null;
    }
}

/**
 * Takım listesini getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param array $filters Filtreler
 * @param int $limit Limit
 * @param int $offset Offset
 * @return array Takım listesi
 */
function get_teams_list(PDO $pdo, array $filters = [], int $limit = 20, int $offset = 0): array {
    try {
        $where_conditions = ["t.status = 'active'"];
        $params = [];
        
        // Filters
        if (!empty($filters['tag'])) {
            $where_conditions[] = "t.tag = :tag";
            $params[':tag'] = $filters['tag'];
        }
        
        if (!empty($filters['search'])) {
            $where_conditions[] = "(t.name LIKE :search OR t.description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        if (isset($filters['recruitment_open'])) {
            $where_conditions[] = "t.is_recruitment_open = :recruitment_open";
            $params[':recruitment_open'] = $filters['recruitment_open'];
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $safe_limit = create_safe_limit($limit, $offset, 100);
        
        $query = "
            SELECT t.*, 
                   u.username as creator_username,
                   (SELECT COUNT(*) FROM team_members tm WHERE tm.team_id = t.id AND tm.status = 'active') as member_count
            FROM teams t
            LEFT JOIN users u ON t.created_by_user_id = u.id
            $where_clause
            ORDER BY t.created_at DESC
            $safe_limit
        ";
        
        $stmt = execute_safe_query($pdo, $query, $params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get teams list error: " . $e->getMessage());
        return [];
    }
}

/**
 * Kullanıcının takımlarını getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $user_id Kullanıcı ID
 * @return array Takım listesi
 */
function get_user_teams(PDO $pdo, int $user_id): array {
    try {
        $query = "
            SELECT t.*, tm.team_role_id, tm.status as member_status, tm.joined_at,
                   tr.name as role_name, tr.display_name as role_display_name, tr.color as role_color,
                   (SELECT COUNT(*) FROM team_members tm2 WHERE tm2.team_id = t.id AND tm2.status = 'active') as member_count
            FROM team_members tm
            JOIN teams t ON tm.team_id = t.id
            JOIN team_roles tr ON tm.team_role_id = tr.id
            WHERE tm.user_id = :user_id AND tm.status = 'active' AND t.status = 'active'
            ORDER BY tm.joined_at DESC
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':user_id' => $user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get user teams error: " . $e->getMessage());
        return [];
    }
}

/**
 * Takım üyelerini getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $team_id Takım ID
 * @param string $status Üye durumu (optional)
 * @return array Üye listesi
 */
function get_team_members(PDO $pdo, int $team_id, string $status = 'active'): array {
    try {
        $query = "
            SELECT tm.*, u.username, u.email, u.ingame_name, u.avatar_path,
                   tr.name as role_name, tr.display_name as role_display_name, 
                   tr.color as role_color, tr.priority as role_priority
            FROM team_members tm
            JOIN users u ON tm.user_id = u.id
            JOIN team_roles tr ON tm.team_role_id = tr.id
            WHERE tm.team_id = :team_id
        ";
        
        $params = [':team_id' => $team_id];
        
        if ($status !== 'all') {
            $query .= " AND tm.status = :status";
            $params[':status'] = $status;
        }
        
        $query .= " ORDER BY tr.priority ASC, tm.joined_at ASC";
        
        $stmt = execute_safe_query($pdo, $query, $params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get team members error: " . $e->getMessage());
        return [];
    }
}

/**
 * Takım başvurularını getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $team_id Takım ID
 * @param string $status Başvuru durumu (optional)
 * @return array Başvuru listesi
 */
function get_team_applications(PDO $pdo, int $team_id, string $status = 'pending'): array {
    try {
        $query = "
            SELECT ta.*, u.username, u.email, u.ingame_name, u.avatar_path,
                   reviewer.username as reviewer_username
            FROM team_applications ta
            JOIN users u ON ta.user_id = u.id
            LEFT JOIN users reviewer ON ta.reviewed_by_user_id = reviewer.id
            WHERE ta.team_id = :team_id
        ";
        
        $params = [':team_id' => $team_id];
        
        if ($status !== 'all') {
            $query .= " AND ta.status = :status";
            $params[':status'] = $status;
        }
        
        $query .= " ORDER BY ta.applied_at DESC";
        
        $stmt = execute_safe_query($pdo, $query, $params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get team applications error: " . $e->getMessage());
        return [];
    }
}

/**
 * Takıma başvuru yapar
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $team_id Takım ID
 * @param int $user_id Kullanıcı ID
 * @param string $message Başvuru mesajı
 * @return bool Başarılı ise true
 */
function apply_to_team(PDO $pdo, int $team_id, int $user_id, string $message = ''): bool {
    try {
        return execute_safe_transaction($pdo, function($pdo) use ($team_id, $user_id, $message) {
            // Zaten üye mi kontrolü
            if (is_team_member($pdo, $team_id, $user_id)) {
                throw new Exception('Bu takımın zaten üyesisiniz.');
            }
            
            // Bekleyen başvuru var mı kontrolü
            if (has_pending_application($pdo, $team_id, $user_id)) {
                throw new Exception('Bu takıma zaten bir başvurunuz bulunmaktadır.');
            }
            
            // Takım üye alımı açık mı kontrolü
            $team = get_team_by_id($pdo, $team_id);
            if (!$team || !$team['is_recruitment_open']) {
                throw new Exception('Bu takım şu anda üye alımı yapmıyor.');
            }
            
            $query = "
                INSERT INTO team_applications (team_id, user_id, message, status, applied_at)
                VALUES (:team_id, :user_id, :message, 'pending', NOW())
            ";
            
            $params = [
                ':team_id' => $team_id,
                ':user_id' => $user_id,
                ':message' => $message
            ];
            
            execute_safe_query($pdo, $query, $params);
            
            // Audit log
            audit_log($pdo, $user_id, 'team_application_submitted', 'team', $team_id, null, [
                'message' => $message
            ]);
            
            return true;
        });
    } catch (Exception $e) {
        error_log("Team application error: " . $e->getMessage());
        return false;
    }
}

/**
 * Takım başvurusunu değerlendirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $application_id Başvuru ID
 * @param string $action approve/reject
 * @param int $reviewer_id Değerlendiren kullanıcı ID
 * @param string $notes Yönetici notları
 * @return bool Başarılı ise true
 */
function review_team_application(PDO $pdo, int $application_id, string $action, int $reviewer_id, string $notes = ''): bool {
    try {
        return execute_safe_transaction($pdo, function($pdo) use ($application_id, $action, $reviewer_id, $notes) {
            // Başvuru bilgilerini al
            $app_query = "SELECT * FROM team_applications WHERE id = :id AND status = 'pending'";
            $stmt = execute_safe_query($pdo, $app_query, [':id' => $application_id]);
            $application = $stmt->fetch();
            
            if (!$application) {
                throw new Exception('Başvuru bulunamadı veya zaten değerlendirilmiş.');
            }
            
            // Başvuruyu güncelle
            $update_query = "
                UPDATE team_applications SET 
                    status = :status,
                    reviewed_by_user_id = :reviewer_id,
                    reviewed_at = NOW(),
                    admin_notes = :notes
                WHERE id = :id
            ";
            
            $params = [
                ':status' => $action,
                ':reviewer_id' => $reviewer_id,
                ':notes' => $notes,
                ':id' => $application_id
            ];
            
            execute_safe_query($pdo, $update_query, $params);
            
            // Eğer onaylandıysa üyeyi ekle
            if ($action === 'approved') {
                add_team_member($pdo, $application['team_id'], $application['user_id'], null, $reviewer_id);
            }
            
            // Audit log
            audit_log($pdo, $reviewer_id, 'team_application_reviewed', 'team', $application['team_id'], 
                $application, ['action' => $action, 'notes' => $notes]);
            
            return true;
        });
    } catch (Exception $e) {
        error_log("Team application review error: " . $e->getMessage());
        return false;
    }
}

/**
 * Takıma üye ekler
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $team_id Takım ID
 * @param int $user_id Kullanıcı ID
 * @param int|null $role_id Rol ID (null ise default rol)
 * @param int|null $invited_by Davet eden kullanıcı ID
 * @return bool Başarılı ise true
 */
function add_team_member(PDO $pdo, int $team_id, int $user_id, ?int $role_id = null, ?int $invited_by = null): bool {
    try {
        return execute_safe_transaction($pdo, function($pdo) use ($team_id, $user_id, $role_id, $invited_by) {
            // Zaten üye mi kontrolü
            if (is_team_member($pdo, $team_id, $user_id)) {
                throw new Exception('Kullanıcı zaten takım üyesi.');
            }
            
            // Default rol ID'sini al
            if ($role_id === null) {
                $role_id = get_default_team_role($pdo, $team_id);
                if (!$role_id) {
                    throw new Exception('Varsayılan rol bulunamadı.');
                }
            }
            
            $query = "
                INSERT INTO team_members (team_id, user_id, team_role_id, status, joined_at, invited_by_user_id)
                VALUES (:team_id, :user_id, :role_id, 'active', NOW(), :invited_by)
            ";
            
            $params = [
                ':team_id' => $team_id,
                ':user_id' => $user_id,
                ':role_id' => $role_id,
                ':invited_by' => $invited_by
            ];
            
            execute_safe_query($pdo, $query, $params);
            
            // Audit log
            audit_log($pdo, $invited_by, 'team_member_added', 'team', $team_id, null, [
                'user_id' => $user_id,
                'role_id' => $role_id
            ]);
            
            return true;
        });
    } catch (Exception $e) {
        error_log("Add team member error: " . $e->getMessage());
        return false;
    }
}

/**
 * Takımdan üye çıkarır
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $team_id Takım ID
 * @param int $user_id Kullanıcı ID
 * @param int $removed_by Çıkaran kullanıcı ID
 * @param string $reason Çıkarma sebebi
 * @return bool Başarılı ise true
 */
function remove_team_member(PDO $pdo, int $team_id, int $user_id, int $removed_by, string $reason = ''): bool {
    try {
        return execute_safe_transaction($pdo, function($pdo) use ($team_id, $user_id, $removed_by, $reason) {
            // Üye bilgilerini al
            $member_query = "
                SELECT tm.*, tr.name as role_name 
                FROM team_members tm 
                JOIN team_roles tr ON tm.team_role_id = tr.id
                WHERE tm.team_id = :team_id AND tm.user_id = :user_id AND tm.status = 'active'
            ";
            $stmt = execute_safe_query($pdo, $member_query, [':team_id' => $team_id, ':user_id' => $user_id]);
            $member = $stmt->fetch();
            
            if (!$member) {
                throw new Exception('Üye bulunamadı.');
            }
            
            // Owner çıkarılamaz
            if ($member['role_name'] === 'owner') {
                throw new Exception('Takım lideri çıkarılamaz.');
            }
            
            // Üyeyi çıkar
            $delete_query = "DELETE FROM team_members WHERE team_id = :team_id AND user_id = :user_id";
            execute_safe_query($pdo, $delete_query, [':team_id' => $team_id, ':user_id' => $user_id]);
            
            // Audit log
            audit_log($pdo, $removed_by, 'team_member_removed', 'team', $team_id, $member, [
                'reason' => $reason
            ]);
            
            return true;
        });
    } catch (Exception $e) {
        error_log("Remove team member error: " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcının takım üyesi olup olmadığını kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $team_id Takım ID
 * @param int $user_id Kullanıcı ID
 * @return bool Üye ise true
 */
function is_team_member(PDO $pdo, int $team_id, int $user_id): bool {
    try {
        $query = "
            SELECT COUNT(*) FROM team_members 
            WHERE team_id = :team_id AND user_id = :user_id AND status = 'active'
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':team_id' => $team_id, ':user_id' => $user_id]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Is team member check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcının bekleyen başvurusu olup olmadığını kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $team_id Takım ID
 * @param int $user_id Kullanıcı ID
 * @return bool Bekleyen başvuru varsa true
 */
function has_pending_application(PDO $pdo, int $team_id, int $user_id): bool {
    try {
        $query = "
            SELECT COUNT(*) FROM team_applications 
            WHERE team_id = :team_id AND user_id = :user_id AND status = 'pending'
        ";
        
        $stmt = execute_safe_query($pdo, $query, [':team_id' => $team_id, ':user_id' => $user_id]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Has pending application check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcının takımı düzenleme yetkisi olup olmadığını kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $team_id Takım ID
 * @param int $user_id Kullanıcı ID
 * @return bool Yetki varsa true
 */
function can_user_edit_team(PDO $pdo, int $team_id, int $user_id): bool {
    try {
        // Admin yetkisi kontrolü
        if (has_permission($pdo, 'admin.teams.manage_all', $user_id)) {
            return true;
        }
        
        // Takım sahibi mi kontrolü
        $team = get_team_by_id($pdo, $team_id);
        if ($team && $team['created_by_user_id'] == $user_id) {
            return true;
        }
        
        // Takım içi yetki kontrolü
        return has_team_permission($pdo, $team_id, $user_id, 'team.manage.settings');
    } catch (Exception $e) {
        error_log("Can user edit team check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcının takım içi yetkisini kontrol eder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $team_id Takım ID
 * @param int $user_id Kullanıcı ID
 * @param string $permission Yetki anahtarı
 * @return bool Yetki varsa true
 */
function has_team_permission(PDO $pdo, int $team_id, int $user_id, string $permission): bool {
    try {
        $query = "
            SELECT COUNT(*) FROM team_members tm
            JOIN team_role_permissions trp ON tm.team_role_id = trp.team_role_id
            JOIN team_permissions tp ON trp.team_permission_id = tp.id
            WHERE tm.team_id = :team_id 
            AND tm.user_id = :user_id 
            AND tm.status = 'active'
            AND tp.permission_key = :permission
            AND tp.is_active = 1
        ";
        
        $params = [
            ':team_id' => $team_id,
            ':user_id' => $user_id,
            ':permission' => $permission
        ];
        
        $stmt = execute_safe_query($pdo, $query, $params);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Has team permission check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Takımın varsayılan rol ID'sini getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $team_id Takım ID
 * @return int|null Varsayılan rol ID'si
 */
function get_default_team_role(PDO $pdo, int $team_id): ?int {
    try {
        $query = "SELECT id FROM team_roles WHERE team_id = :team_id AND is_default = 1 LIMIT 1";
        $stmt = execute_safe_query($pdo, $query, [':team_id' => $team_id]);
        $result = $stmt->fetchColumn();
        
        return $result ?: null;
    } catch (Exception $e) {
        error_log("Get default team role error: " . $e->getMessage());
        return null;
    }
}

/**
 * Takım slug'ı oluşturur
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $name Takım adı
 * @param int|null $exclude_team_id Hariç tutulacak takım ID (güncelleme için)
 * @return string Benzersiz slug
 */
function generate_team_slug(PDO $pdo, string $name, ?int $exclude_team_id = null): string {
    // Temel slug oluştur
    $base_slug = strtolower(trim($name));
    $base_slug = preg_replace('/[^a-z0-9\s-]/', '', $base_slug);
    $base_slug = preg_replace('/\s+/', '-', $base_slug);
    $base_slug = preg_replace('/-+/', '-', $base_slug);
    $base_slug = trim($base_slug, '-');
    
    if (empty($base_slug)) {
        $base_slug = 'team';
    }
    
    $slug = $base_slug;
    $counter = 1;
    
    // Benzersiz slug bul
    while (true) {
        $query = "SELECT COUNT(*) FROM teams WHERE slug = :slug";
        $params = [':slug' => $slug];
        
        if ($exclude_team_id !== null) {
            $query .= " AND id != :exclude_id";
            $params[':exclude_id'] = $exclude_team_id;
        }
        
        $stmt = execute_safe_query($pdo, $query, $params);
        
        if ($stmt->fetchColumn() == 0) {
            break;
        }
        
        $slug = $base_slug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

/**
 * Takım form verilerini doğrular
 * @param array $form_data Form verileri
 * @param int|null $team_id Güncelleme için takım ID (optional)
 * @return array Hata listesi
 */
function validate_team_form(array $form_data, ?int $team_id = null): array {
    $errors = [];
    
    // Takım adı kontrolü
    if (empty($form_data['name'])) {
        $errors['name'] = 'Takım adı zorunludur.';
    } elseif (strlen($form_data['name']) < 3) {
        $errors['name'] = 'Takım adı en az 3 karakter olmalıdır.';
    } elseif (strlen($form_data['name']) > 100) {
        $errors['name'] = 'Takım adı en fazla 100 karakter olmalıdır.';
    }
    // Sadece script injection'ı engelle
    elseif (stripos($form_data['name'], '<script') !== false || 
            stripos($form_data['name'], '<iframe') !== false || 
            stripos($form_data['name'], 'javascript:') !== false) {
        $errors['name'] = 'Takım adı güvenlik açısından geçersiz içerik içeriyor.';
    }
    
    // Açıklama kontrolü
    if (!empty($form_data['description']) && strlen($form_data['description']) > 1000) {
        $errors['description'] = 'Takım açıklaması en fazla 1000 karakter olmalıdır.';
    }
    
    // Renk kontrolü
    if (!empty($form_data['color']) && !preg_match('/^#[0-9a-fA-F]{6}$/', $form_data['color'])) {
        $errors['color'] = 'Geçersiz renk formatı.';
    }
    
    // Maksimum üye sayısı kontrolü
    if (!is_numeric($form_data['max_members']) || $form_data['max_members'] < 5 || $form_data['max_members'] > 500) {
        $errors['max_members'] = 'Maksimum üye sayısı 5-500 arasında olmalıdır.';
    }
    
    // Tag kontrolü (kısaltma)
    if (!empty($form_data['tag'])) {
        if (strlen($form_data['tag']) > 8) {
            $errors['tag'] = 'Takım kısaltması en fazla 8 karakter olmalıdır.';
        }
        if (strlen($form_data['tag']) < 2) {
            $errors['tag'] = 'Takım kısaltması en az 2 karakter olmalıdır.';
        }
        // Sadece script injection'ı engelle
        if (stripos($form_data['tag'], '<script') !== false || 
            stripos($form_data['tag'], '<iframe') !== false || 
            stripos($form_data['tag'], 'javascript:') !== false) {
            $errors['tag'] = 'Takım kısaltması güvenlik açısından geçersiz içerik içeriyor.';
        }
    }
    
    return $errors;
}

/**
 * Takım istatistiklerini getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $team_id Takım ID
 * @return array İstatistikler
 */
function get_team_statistics(PDO $pdo, int $team_id): array {
    try {
        $stats = [];
        
        // Toplam üye sayısı
        $query = "SELECT COUNT(*) FROM team_members WHERE team_id = :team_id AND status = 'active'";
        $stmt = execute_safe_query($pdo, $query, [':team_id' => $team_id]);
        $stats['total_members'] = $stmt->fetchColumn();
        
        // Bekleyen başvuru sayısı
        $query = "SELECT COUNT(*) FROM team_applications WHERE team_id = :team_id AND status = 'pending'";
        $stmt = execute_safe_query($pdo, $query, [':team_id' => $team_id]);
        $stats['pending_applications'] = $stmt->fetchColumn();
        
        // Rol dağılımı
        $query = "
            SELECT tr.display_name, tr.color, COUNT(*) as count
            FROM team_members tm
            JOIN team_roles tr ON tm.team_role_id = tr.id
            WHERE tm.team_id = :team_id AND tm.status = 'active'
            GROUP BY tr.id, tr.display_name, tr.color
            ORDER BY tr.priority ASC
        ";
        $stmt = execute_safe_query($pdo, $query, [':team_id' => $team_id]);
        $stats['role_distribution'] = $stmt->fetchAll();
        
        // Aylık üye katılım trendi
        $query = "
            SELECT DATE_FORMAT(joined_at, '%Y-%m') as month, COUNT(*) as count
            FROM team_members 
            WHERE team_id = :team_id AND status = 'active'
            AND joined_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(joined_at, '%Y-%m')
            ORDER BY month ASC
        ";
        $stmt = execute_safe_query($pdo, $query, [':team_id' => $team_id]);
        $stats['monthly_joins'] = $stmt->fetchAll();
        
        return $stats;
    } catch (Exception $e) {
        error_log("Get team statistics error: " . $e->getMessage());
        return [];
    }
}

/**
 * Takım silme işlemi
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $team_id Takım ID
 * @param int $deleted_by Silen kullanıcı ID
 * @return bool Başarılı ise true
 */
function delete_team(PDO $pdo, int $team_id, int $deleted_by): bool {
    try {
        return execute_safe_transaction($pdo, function($pdo) use ($team_id, $deleted_by) {
            // Takım bilgilerini al
            $team = get_team_by_id($pdo, $team_id);
            if (!$team) {
                throw new Exception('Takım bulunamadı.');
            }
            
            // Takımı sil (CASCADE ile ilişkili veriler de silinir)
            $query = "DELETE FROM teams WHERE id = :team_id";
            execute_safe_query($pdo, $query, [':team_id' => $team_id]);
            
            // Audit log
            audit_log($pdo, $deleted_by, 'team_deleted', 'team', $team_id, $team, null);
            
            return true;
        });
    } catch (Exception $e) {
        error_log("Delete team error: " . $e->getMessage());
        return false;
    }
}
?>