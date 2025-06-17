<?php
// /teams/manage.php
session_start();

// Güvenlik ve gerekli dosyaları dahil et
require_once dirname(__DIR__) . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/team_functions.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/sql_security_functions.php';

// Login kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Veritabanı bağlantısı
if (!isset($pdo)) {
    die('Veritabanı bağlantı hatası');
}

// Team ID kontrolü
$team_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$team_id) {
    header('Location: /teams/?error=invalid_team');
    exit;
}

try {
    // Takım verilerini getir
    $stmt = $pdo->prepare("
        SELECT t.*, u.username as creator_username
        FROM teams t
        LEFT JOIN users u ON t.created_by_user_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$team_id]);
    $team_data = $stmt->fetch();
    
    if (!$team_data) {
        header('Location: /teams/?error=team_not_found');
        exit;
    }
    
    // Kullanıcı yetkilerini kontrol et
    $current_user_id = $_SESSION['user_id'];
    
    // Takım üyeliği ve yönetim yetkisi kontrolü
    $stmt = $pdo->prepare("
        SELECT tm.*, tr.name as role_name, tr.display_name as role_display_name, 
               tr.color as role_color, tr.is_management, tr.priority
        FROM team_members tm
        JOIN team_roles tr ON tm.team_role_id = tr.id
        WHERE tm.team_id = ? AND tm.user_id = ? AND tm.status = 'active'
    ");
    $stmt->execute([$team_id, $current_user_id]);
    $user_membership = $stmt->fetch();
    
    $is_team_member = !empty($user_membership);
    $is_team_manager = $user_membership['is_management'] ?? false;
    $is_team_owner = ($team_data['created_by_user_id'] == $current_user_id);
    
    // Yönetim yetkisi kontrolü
    $can_manage = $is_team_manager || $is_team_owner || has_permission($pdo, 'teams.edit_all', $current_user_id);
    
    if (!$can_manage) {
        header('Location: /teams/detail.php?id=' . $team_id . '&error=no_permission');
        exit;
    }
    
    // Aktif sekme
    $active_tab = $_GET['tab'] ?? 'overview';
    $valid_tabs = ['overview', 'members', 'applications', 'roles', 'settings'];
    if (!in_array($active_tab, $valid_tabs)) {
        $active_tab = 'overview';
    }
    
    // Takım istatistikleri
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_members,
            COUNT(CASE WHEN tm.last_activity >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as weekly_active,
            COUNT(CASE WHEN tm.last_activity >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as monthly_active
        FROM team_members tm
        WHERE tm.team_id = ? AND tm.status = 'active'
    ");
    $stmt->execute([$team_id]);
    $team_stats = $stmt->fetch();
    
    // Bekleyen başvuru sayısı
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_applications
        FROM team_applications
        WHERE team_id = ? AND status = 'pending'
    ");
    $stmt->execute([$team_id]);
    $team_stats['pending_applications'] = $stmt->fetchColumn();
    
    // Tab'a göre ek veriler
    switch ($active_tab) {
        case 'members':
            // Üyeleri getir
            $stmt = $pdo->prepare("
                SELECT tm.*, u.username, u.ingame_name, u.avatar_path,
                       tr.name as role_name, tr.display_name as role_display_name, 
                       tr.color as role_color, tr.priority as role_priority, tr.is_management
                FROM team_members tm
                JOIN users u ON tm.user_id = u.id
                JOIN team_roles tr ON tm.team_role_id = tr.id
                WHERE tm.team_id = ? AND tm.status = 'active'
                ORDER BY tr.priority ASC, tm.joined_at ASC
            ");
            $stmt->execute([$team_id]);
            $team_members = $stmt->fetchAll();
            
            // Roller listesi
            $stmt = $pdo->prepare("
                SELECT * FROM team_roles 
                WHERE team_id = ? 
                ORDER BY priority ASC
            ");
            $stmt->execute([$team_id]);
            $team_roles = $stmt->fetchAll();
            break;
            
        case 'applications':
            // Başvuruları getir
            $stmt = $pdo->prepare("
                SELECT ta.*, u.username, u.ingame_name, u.avatar_path
                FROM team_applications ta
                JOIN users u ON ta.user_id = u.id
                WHERE ta.team_id = ? AND ta.status = 'pending'
                ORDER BY ta.applied_at ASC
            ");
            $stmt->execute([$team_id]);
            $team_applications = $stmt->fetchAll();
            break;
            
        case 'roles':
            // Roller ve yetkileri getir
            $stmt = $pdo->prepare("
                SELECT tr.*, COUNT(tm.id) as member_count
                FROM team_roles tr
                LEFT JOIN team_members tm ON tr.id = tm.team_role_id AND tm.status = 'active'
                WHERE tr.team_id = ?
                GROUP BY tr.id
                ORDER BY tr.priority ASC
            ");
            $stmt->execute([$team_id]);
            $team_roles_detailed = $stmt->fetchAll();
            break;
            
        case 'overview':
            // Son aktiviteler
            $stmt = $pdo->prepare("
                SELECT 
                    'member_joined' as type,
                    tm.joined_at as date,
                    u.username,
                    tr.display_name as role_name
                FROM team_members tm
                JOIN users u ON tm.user_id = u.id
                JOIN team_roles tr ON tm.team_role_id = tr.id
                WHERE tm.team_id = ? AND tm.joined_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                
                UNION ALL
                
                SELECT 
                    'application_received' as type,
                    ta.applied_at as date,
                    u.username,
                    NULL as role_name
                FROM team_applications ta
                JOIN users u ON ta.user_id = u.id
                WHERE ta.team_id = ? 
                AND ta.applied_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                
                ORDER BY date DESC
                LIMIT 10
            ");
            $stmt->execute([$team_id, $team_id]);
            $recent_activities = $stmt->fetchAll();
            break;
    }
    
} catch (Exception $e) {
    error_log("Team manage error: " . $e->getMessage());
    header('Location: /teams/?error=database_error');
    exit;
}

// POST işlemleri
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Güvenlik hatası.';
    } else {
        try {
            // Hem 'action' hem de 'form_type' parametrelerini kontrol et
            $action = $_POST['action'] ?? $_POST['form_type'] ?? '';
            
            // Debug log
            error_log("Manage.php POST action: $action, POST keys: " . implode(', ', array_keys($_POST)));
            
            switch ($action) {
                // === SETTINGS TAB ACTIONS ===
                case 'basic_settings':
                    // Temel takım ayarları güncelleme
                    $name = trim($_POST['name'] ?? '');
                    $description = trim($_POST['description'] ?? '');
                    $tag = trim($_POST['tag'] ?? '');
                    $color = $_POST['color'] ?? '#007bff';
                    $max_members = max(1, min(100, (int)($_POST['max_members'] ?? 50)));
                    $is_recruitment_open = isset($_POST['is_recruitment_open']) ? 1 : 0;
                    
                    // Validasyon
                    if (strlen($name) < 3 || strlen($name) > 100) {
                        throw new Exception('Takım adı 3-100 karakter arasında olmalıdır.');
                    }
                    
                    if (strlen($description) > 1000) {
                        throw new Exception('Açıklama 1000 karakterden uzun olamaz.');
                    }
                    
                    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                        throw new Exception('Geçersiz renk kodu.');
                    }
                    
                    // Takım adı benzersizlik kontrolü
                    $stmt = $pdo->prepare("SELECT id FROM teams WHERE name = ? AND id != ?");
                    $stmt->execute([$name, $team_id]);
                    if ($stmt->rowCount() > 0) {
                        throw new Exception('Bu takım adı zaten kullanılıyor.');
                    }
                    
                    // Slug oluştur
                    $slug = strtolower(trim($name));
                    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
                    $slug = preg_replace('/\s+/', '-', $slug);
                    $slug = trim($slug, '-');
                    if (empty($slug)) $slug = 'team-' . $team_id;
                    
                    // Slug benzersizlik kontrolü
                    $stmt = $pdo->prepare("SELECT id FROM teams WHERE slug = ? AND id != ?");
                    $stmt->execute([$slug, $team_id]);
                    if ($stmt->rowCount() > 0) {
                        $slug = $slug . '-' . time();
                    }
                    
                    // Güncelleme
                    $stmt = $pdo->prepare("
                        UPDATE teams SET 
                            name = ?, slug = ?, description = ?, tag = ?, 
                            color = ?, max_members = ?, is_recruitment_open = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $name, $slug, $description, $tag, 
                        $color, $max_members, $is_recruitment_open, $team_id
                    ]);
                    
                    $success_message = 'Takım ayarları başarıyla güncellendi.';
                    
                    // Güncel verileri yeniden çek
                    $stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
                    $stmt->execute([$team_id]);
                    $team_data = $stmt->fetch();
                    break;
                    
                // === ROLES TAB ACTIONS ===
                case 'create_role':
                    // Yeni rol oluşturma
                    $role_name = trim($_POST['role_name'] ?? '');
                    $role_display_name = trim($_POST['role_display_name'] ?? '');
                    $role_color = $_POST['role_color'] ?? '#6c757d';
                    $role_priority = max(1, min(999, (int)($_POST['role_priority'] ?? 100)));
                    $is_management = isset($_POST['is_management']) ? 1 : 0;
                    $is_default = isset($_POST['is_default']) ? 1 : 0;
                    $role_description = trim($_POST['role_description'] ?? '');
                    
                    // Validasyon
                    if (strlen($role_name) < 2 || strlen($role_name) > 50) {
                        throw new Exception('Rol adı 2-50 karakter arasında olmalıdır.');
                    }
                    
                    if (strlen($role_display_name) < 2 || strlen($role_display_name) > 100) {
                        throw new Exception('Görünen ad 2-100 karakter arasında olmalıdır.');
                    }
                    
                    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $role_color)) {
                        throw new Exception('Geçersiz renk kodu.');
                    }
                    
                    // Rol adı benzersizlik kontrolü
                    $stmt = $pdo->prepare("SELECT id FROM team_roles WHERE team_id = ? AND name = ?");
                    $stmt->execute([$team_id, strtolower($role_name)]);
                    if ($stmt->rowCount() > 0) {
                        throw new Exception('Bu rol adı zaten kullanılıyor.');
                    }
                    
                    // Varsayılan rol kontrolü
                    if ($is_default) {
                        $stmt = $pdo->prepare("UPDATE team_roles SET is_default = 0 WHERE team_id = ?");
                        $stmt->execute([$team_id]);
                    }
                    
                    // Rol oluştur
                    $stmt = $pdo->prepare("
                        INSERT INTO team_roles (
                            team_id, name, display_name, color, priority, 
                            is_management, is_default, description
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $team_id, strtolower($role_name), $role_display_name, $role_color,
                        $role_priority, $is_management, $is_default, $role_description
                    ]);
                    
                    $success_message = 'Rol başarıyla oluşturuldu.';
                    break;
                    
                case 'update_role_permissions':
                    // Rol yetkilerini güncelleme
                    $role_id = (int)$_POST['role_id'];
                    $permissions = $_POST['permissions'] ?? [];
                    
                    // Rol kontrolü
                    $stmt = $pdo->prepare("SELECT * FROM team_roles WHERE id = ? AND team_id = ?");
                    $stmt->execute([$role_id, $team_id]);
                    $role = $stmt->fetch();
                    
                    if (!$role) {
                        throw new Exception('Rol bulunamadı.');
                    }
                    
                    $pdo->beginTransaction();
                    
                    // Mevcut yetkileri sil
                    $stmt = $pdo->prepare("DELETE FROM team_role_permissions WHERE team_role_id = ?");
                    $stmt->execute([$role_id]);
                    
                    // Yeni yetkileri ekle
                    if (!empty($permissions)) {
                        $stmt = $pdo->prepare("
                            INSERT INTO team_role_permissions (team_role_id, team_permission_id) 
                            VALUES (?, ?)
                        ");
                        
                        foreach ($permissions as $permission_id) {
                            $permission_id = (int)$permission_id;
                            if ($permission_id > 0) {
                                $stmt->execute([$role_id, $permission_id]);
                            }
                        }
                    }
                    
                    $pdo->commit();
                    $success_message = 'Rol yetkileri başarıyla güncellendi.';
                    break;
                    
                case 'delete_role':
                    // Rol silme
                    $role_id = (int)$_POST['role_id'];
                    
                    // Rol kontrolü
                    $stmt = $pdo->prepare("
                        SELECT tr.*, COUNT(tm.id) as member_count
                        FROM team_roles tr
                        LEFT JOIN team_members tm ON tr.id = tm.team_role_id AND tm.status = 'active'
                        WHERE tr.id = ? AND tr.team_id = ?
                        GROUP BY tr.id
                    ");
                    $stmt->execute([$role_id, $team_id]);
                    $role = $stmt->fetch();
                    
                    if (!$role) {
                        throw new Exception('Rol bulunamadı.');
                    }
                    
                    if ($role['name'] === 'owner') {
                        throw new Exception('Owner rolü silinemez.');
                    }
                    
                    if ($role['member_count'] > 0) {
                        throw new Exception('Bu role atanmış üyeler var. Önce üyeleri başka rollere taşıyın.');
                    }
                    
                    // Rolü sil
                    $stmt = $pdo->prepare("DELETE FROM team_roles WHERE id = ?");
                    $stmt->execute([$role_id]);
                    
                    $success_message = 'Rol başarıyla silindi.';
                    break;
                    
                case 'logo_upload':
                    // Logo yükleme
                    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('Lütfen bir logo dosyası seçin.');
                    }
                    
                    $file = $_FILES['logo'];
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $max_size = 2 * 1024 * 1024; // 2MB
                    
                    if (!in_array($file['type'], $allowed_types)) {
                        throw new Exception('Sadece JPEG, PNG, GIF veya WebP formatında resim yükleyebilirsiniz.');
                    }
                    
                    if ($file['size'] > $max_size) {
                        throw new Exception('Dosya boyutu 2MB\'dan büyük olamaz.');
                    }
                    
                    // Upload dizinini oluştur
                    $upload_dir = BASE_PATH . '/uploads/teams/logos/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Eski logo dosyasını sil
                    if ($team_data['logo_path']) {
                        $old_file = $upload_dir . $team_data['logo_path'];
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                    
                    // Dosya adı oluştur
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'team_' . $team_id . '_logo_' . time() . '.' . $extension;
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        // Veritabanını güncelle
                        $stmt = $pdo->prepare("UPDATE teams SET logo_path = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$filename, $team_id]);
                        
                        $success_message = 'Logo başarıyla yüklendi.';
                        $team_data['logo_path'] = $filename;
                    } else {
                        throw new Exception('Logo dosyası yüklenirken bir hata oluştu.');
                    }
                    break;
                    
                case 'banner_upload':
                    // Banner yükleme
                    if (!isset($_FILES['banner']) || $_FILES['banner']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('Lütfen bir banner dosyası seçin.');
                    }
                    
                    $file = $_FILES['banner'];
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    if (!in_array($file['type'], $allowed_types)) {
                        throw new Exception('Sadece JPEG, PNG, GIF veya WebP formatında resim yükleyebilirsiniz.');
                    }
                    
                    if ($file['size'] > $max_size) {
                        throw new Exception('Dosya boyutu 5MB\'dan büyük olamaz.');
                    }
                    
                    // Upload dizinini oluştur
                    $upload_dir = BASE_PATH . '/uploads/teams/banners/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Eski banner dosyasını sil
                    if ($team_data['banner_path']) {
                        $old_file = BASE_PATH . $team_data['banner_path'];
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                    
                    // Dosya adı oluştur
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'team_' . $team_id . '_banner_' . time() . '.' . $extension;
                    $filepath = $upload_dir . $filename;
                    $relative_path = '/uploads/teams/banners/' . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        // Veritabanını güncelle
                        $stmt = $pdo->prepare("UPDATE teams SET banner_path = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$relative_path, $team_id]);
                        
                        $success_message = 'Banner başarıyla yüklendi.';
                        $team_data['banner_path'] = $relative_path;
                    } else {
                        throw new Exception('Banner dosyası yüklenirken bir hata oluştu.');
                    }
                    break;
                    
                case 'delete_team':
                    // Takım silme
                    $confirm_name = trim($_POST['confirm_name'] ?? '');
                    if ($confirm_name !== $team_data['name']) {
                        throw new Exception('Takım adını doğru yazmadınız.');
                    }
                    
                    // Logo ve banner dosyalarını sil
                    if ($team_data['logo_path']) {
                        $logo_file = BASE_PATH . '/uploads/teams/logos/' . $team_data['logo_path'];
                        if (file_exists($logo_file)) {
                            unlink($logo_file);
                        }
                    }
                    
                    if ($team_data['banner_path']) {
                        $banner_file = BASE_PATH . $team_data['banner_path'];
                        if (file_exists($banner_file)) {
                            unlink($banner_file);
                        }
                    }
                    
                    // Takımı sil (CASCADE ile ilişkili veriler otomatik silinir)
                    $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
                    $stmt->execute([$team_id]);
                    
                    // Başarılı silme sonrası yönlendir
                    header('Location: /teams/?success=' . urlencode('Takım başarıyla silindi.'));
                    exit;
                    
                // === MEMBERS TAB ACTIONS ===
                case 'approve_application':
                    $application_id = (int)$_POST['application_id'];
                    
                    // Başvuruyu getir
                    $stmt = $pdo->prepare("
                        SELECT * FROM team_applications 
                        WHERE id = ? AND team_id = ? AND status = 'pending'
                    ");
                    $stmt->execute([$application_id, $team_id]);
                    $application = $stmt->fetch();
                    
                    if (!$application) {
                        throw new Exception('Başvuru bulunamadı.');
                    }
                    
                    // Takım dolu mu kontrol et
                    if ($team_stats['total_members'] >= $team_data['max_members']) {
                        throw new Exception('Takım dolu.');
                    }
                    
                    $pdo->beginTransaction();
                    
                    // Başvuruyu onayla
                    $stmt = $pdo->prepare("
                        UPDATE team_applications 
                        SET status = 'approved', reviewed_by_user_id = ?, reviewed_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$current_user_id, $application_id]);
                    
                    // Varsayılan rolü bul
                    $stmt = $pdo->prepare("
                        SELECT id FROM team_roles 
                        WHERE team_id = ? AND is_default = 1 
                        LIMIT 1
                    ");
                    $stmt->execute([$team_id]);
                    $default_role_id = $stmt->fetchColumn();
                    
                    if (!$default_role_id) {
                        throw new Exception('Varsayılan rol bulunamadı.');
                    }
                    
                    // Üyeyi ekle
                    $stmt = $pdo->prepare("
                        INSERT INTO team_members (team_id, user_id, team_role_id, status, joined_at, invited_by_user_id)
                        VALUES (?, ?, ?, 'active', NOW(), ?)
                    ");
                    $stmt->execute([$team_id, $application['user_id'], $default_role_id, $current_user_id]);
                    
                    $pdo->commit();
                    $success_message = 'Başvuru onaylandı ve üye eklendi.';
                    break;
                    
                case 'reject_application':
                    $application_id = (int)$_POST['application_id'];
                    $admin_notes = trim($_POST['admin_notes'] ?? '');
                    
                    $stmt = $pdo->prepare("
                        UPDATE team_applications 
                        SET status = 'rejected', reviewed_by_user_id = ?, reviewed_at = NOW(), admin_notes = ?
                        WHERE id = ? AND team_id = ? AND status = 'pending'
                    ");
                    $result = $stmt->execute([$current_user_id, $admin_notes, $application_id, $team_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success_message = 'Başvuru reddedildi.';
                    } else {
                        $error_message = 'Başvuru bulunamadı veya zaten işlenmiş.';
                    }
                    break;
                    
                case 'kick_member':
                    $member_id = (int)$_POST['member_id'];
                    
                    // Üyeyi getir
                    $stmt = $pdo->prepare("
                        SELECT tm.*, u.username FROM team_members tm
                        JOIN users u ON tm.user_id = u.id
                        WHERE tm.id = ? AND tm.team_id = ?
                    ");
                    $stmt->execute([$member_id, $team_id]);
                    $member = $stmt->fetch();
                    
                    if (!$member) {
                        throw new Exception('Üye bulunamadı.');
                    }
                    
                    // Kendini atamaz
                    if ($member['user_id'] == $current_user_id) {
                        throw new Exception('Kendinizi atamazsınız.');
                    }
                    
                    // Takım sahibini atamaz
                    if ($member['user_id'] == $team_data['created_by_user_id']) {
                        throw new Exception('Takım sahibini atamazsınız.');
                    }
                    
                    $stmt = $pdo->prepare("
                        UPDATE team_members 
                        SET status = 'inactive'
                        WHERE id = ?
                    ");
                    $stmt->execute([$member_id]);
                    
                    $success_message = $member['username'] . ' takımdan atıldı.';
                    break;
                    
                case 'change_member_role':
                    $member_id = (int)$_POST['member_id'];
                    $new_role_id = (int)$_POST['new_role_id'];
                    
                    // Rol kontrolü
                    $stmt = $pdo->prepare("
                        SELECT * FROM team_roles 
                        WHERE id = ? AND team_id = ?
                    ");
                    $stmt->execute([$new_role_id, $team_id]);
                    $role = $stmt->fetch();
                    
                    if (!$role) {
                        throw new Exception('Geçersiz rol.');
                    }
                    
                    $stmt = $pdo->prepare("
                        UPDATE team_members 
                        SET team_role_id = ?
                        WHERE id = ? AND team_id = ?
                    ");
                    $result = $stmt->execute([$new_role_id, $member_id, $team_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success_message = 'Üye rolü güncellendi.';
                    } else {
                        $error_message = 'Rol güncellenemedi.';
                    }
                    break;
                    
                default:
                    $error_message = 'Geçersiz işlem: ' . htmlspecialchars($action);
                    error_log("Invalid action in manage.php: $action - POST data: " . json_encode($_POST));
            }
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = $e->getMessage();
            error_log("Manage.php exception: " . $e->getMessage());
        }
    }
    
    // Sayfayı yenile (PRG pattern)
    $redirect_url = '/teams/manage.php?id=' . $team_id . '&tab=' . $active_tab;
    if ($success_message) {
        $redirect_url .= '&success=' . urlencode($success_message);
    }
    if ($error_message) {
        $redirect_url .= '&error=' . urlencode($error_message);
    }
    header('Location: ' . $redirect_url);
    exit;
}

// URL'den mesajları al
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error_message = $_GET['error'];
}

// CSRF token oluştur
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Sayfa başlığı
$page_title = htmlspecialchars($team_data['name']) . ' - Takım Yönetimi';

include BASE_PATH . '/src/includes/header.php';
include BASE_PATH . '/src/includes/navbar.php';
?>

<!-- Teams CSS -->
<link rel="stylesheet" href="/teams/css/manage.css">

<div class="site-container">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
        <a href="/" class="breadcrumb-item">
            <i class="fas fa-home"></i> Ana Sayfa
        </a>
        <span class="breadcrumb-item">
            <a href="/teams/">Takımlar</a>
        </span>
        <span class="breadcrumb-item">
            <a href="/teams/detail.php?id=<?= $team_id ?>"><?= htmlspecialchars($team_data['name']) ?></a>
        </span>
        <span class="breadcrumb-item active">
            <i class="fas fa-cog"></i> Yönetim
        </span>
    </nav>

    <div class="team-manage-container">
        <!-- Page Header -->
        <div class="manage-header">
            <div class="header-info">
                <div class="team-info">
                    <?php if ($team_data['logo_path']): ?>
                        <img src="/uploads/teams/logos/<?= htmlspecialchars($team_data['logo_path']) ?>" 
                             alt="<?= htmlspecialchars($team_data['name']) ?>" 
                             class="team-logo-small"
                             style="border-color: <?= htmlspecialchars($team_data['color']) ?>;">
                    <?php else: ?>
                        <div class="team-logo-small" style="border-color: <?= htmlspecialchars($team_data['color']) ?>; color: <?= htmlspecialchars($team_data['color']) ?>;">
                            <?= strtoupper(substr($team_data['name'], 0, 2)) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="team-basic-info">
                        <h1 style="color: <?= htmlspecialchars($team_data['color']) ?>">
                            <?= htmlspecialchars($team_data['name']) ?> - Yönetim
                        </h1>
                        <p>Takım üyelerini, başvuruları ve ayarları yönetin</p>
                    </div>
                </div>
                
                <div class="quick-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?= $team_stats['total_members'] ?>/<?= $team_data['max_members'] ?></div>
                        <div class="stat-label">Üye</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $team_stats['pending_applications'] ?></div>
                        <div class="stat-label">Bekleyen Başvuru</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= $team_stats['weekly_active'] ?></div>
                        <div class="stat-label">Haftalık Aktif</div>
                    </div>
                </div>
            </div>
            
            <div class="header-actions">
                <a href="/teams/detail.php?id=<?= $team_id ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-eye"></i> Takımı Görüntüle
                </a>
                <?php if ($is_team_owner): ?>
                    <a href="/teams/create.php?id=<?= $team_id ?>" class="btn btn-outline-primary">
                        <i class="fas fa-edit"></i> Takım Ayarları
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Navigation Tabs -->
        <div class="manage-tabs">
            <nav class="tab-nav">
                <a href="?id=<?= $team_id ?>&tab=overview" class="tab-link <?= $active_tab === 'overview' ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i> Genel Bakış
                </a>
                <a href="?id=<?= $team_id ?>&tab=members" class="tab-link <?= $active_tab === 'members' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> Üyeler (<?= $team_stats['total_members'] ?>)
                </a>
                <a href="?id=<?= $team_id ?>&tab=applications" class="tab-link <?= $active_tab === 'applications' ? 'active' : '' ?>">
                    <i class="fas fa-clipboard-list"></i> Başvurular 
                    <?php if ($team_stats['pending_applications'] > 0): ?>
                        <span class="badge"><?= $team_stats['pending_applications'] ?></span>
                    <?php endif; ?>
                </a>
                <?php if ($is_team_owner): ?>
                    <a href="?id=<?= $team_id ?>&tab=roles" class="tab-link <?= $active_tab === 'roles' ? 'active' : '' ?>">
                        <i class="fas fa-user-tag"></i> Roller
                    </a>
                    <a href="?id=<?= $team_id ?>&tab=settings" class="tab-link <?= $active_tab === 'settings' ? 'active' : '' ?>">
                        <i class="fas fa-cog"></i> Ayarlar
                    </a>
                <?php endif; ?>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="tab-content">
            <?php include __DIR__ . '/manage_tabs/' . $active_tab . '.php'; ?>
        </div>
    </div>
</div>

<script>
// Confirm dialogs for dangerous actions
document.addEventListener('DOMContentLoaded', function() {
    // Kick member confirmation
    document.querySelectorAll('[data-action="kick"]').forEach(button => {
        button.addEventListener('click', function(e) {
            const memberName = this.dataset.memberName;
            if (!confirm(`${memberName} adlı üyeyi takımdan atmak istediğinizden emin misiniz?`)) {
                e.preventDefault();
            }
        });
    });
    
    // Reject application confirmation
    document.querySelectorAll('[data-action="reject"]').forEach(button => {
        button.addEventListener('click', function(e) {
            const applicantName = this.dataset.applicantName;
            if (!confirm(`${applicantName} adlı kullanıcının başvurusunu reddetmek istediğinizden emin misiniz?`)) {
                e.preventDefault();
            }
        });
    });
    
    // Role change confirmation
    document.querySelectorAll('.role-select').forEach(select => {
        select.addEventListener('change', function() {
            const memberName = this.dataset.memberName;
            const roleName = this.options[this.selectedIndex].text;
            if (confirm(`${memberName} adlı üyenin rolünü ${roleName} olarak değiştirmek istediğinizden emin misiniz?`)) {
                this.closest('form').submit();
            } else {
                this.selectedIndex = this.dataset.originalIndex;
            }
        });
        
        // Store original index
        select.dataset.originalIndex = select.selectedIndex;
    });

    // Form submission debug
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const action = this.querySelector('input[name="action"]')?.value || 
                          this.querySelector('input[name="form_type"]')?.value || 
                          'unknown';
            console.log('Form submitting with action:', action);
        });
    });
});
</script>

<?php include BASE_PATH . '/src/includes/footer.php'; ?>

<!-- User Popover Component -->
<?php include BASE_PATH . '/src/includes/user_popover.php'; ?>