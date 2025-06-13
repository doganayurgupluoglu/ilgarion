<?php
// /admin/users/api/get_users.php
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// BASE_PATH tanımı
if (!defined('BASE_PATH')) {
    define('BASE_PATH', realpath(__DIR__ . '/../../../'));
}

require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php';
require_once BASE_PATH . '/src/functions/enhanced_role_security.php';

// Yetki kontrolü - Süper admin bypass
if (!is_user_logged_in()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Süper admin her şeyi yapabilir
$is_super_admin = is_super_admin($pdo, $_SESSION['user_id']);

if (!$is_super_admin && !has_permission($pdo, 'admin.users.view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

try {
    // GET parametrelerini al ve sanitize et
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = max(1, min(100, intval($_GET['per_page'] ?? 20)));
    $search = trim($_GET['search'] ?? '');
    $status_filter = trim($_GET['status'] ?? 'all');
    $role_filter = trim($_GET['role'] ?? 'all');
    
    $offset = ($page - 1) * $per_page;
    
    // Base WHERE clause
    $where_conditions = [];
    $params = [];
    
    // Search filtresi
    if (!empty($search)) {
        $where_conditions[] = "(
            u.username LIKE ? OR 
            u.email LIKE ? OR 
            u.ingame_name LIKE ? OR
            u.discord_username LIKE ?
        )";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Status filtresi
    if ($status_filter !== 'all') {
        $where_conditions[] = "u.status = ?";
        $params[] = $status_filter;
    }
    
    // Role filtresi
    $role_join = '';
    if ($role_filter !== 'all' && is_numeric($role_filter)) {
        $role_join = "INNER JOIN user_roles ur_filter ON u.id = ur_filter.user_id";
        $where_conditions[] = "ur_filter.role_id = ?";
        $params[] = intval($role_filter);
    }
    
    $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Toplam kullanıcı sayısı
    $count_query = "
        SELECT COUNT(DISTINCT u.id) as total 
        FROM users u 
        $role_join
        $where_clause
    ";
    
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_users = $count_stmt->fetch()['total'];
    
    // Ana query - kullanıcıları getir  
    $users_query = "
        SELECT DISTINCT
            u.id,
            u.username,
            u.email,
            u.ingame_name,
            u.discord_username,
            u.avatar_path,
            u.status,
            u.profile_info,
            u.created_at,
            u.updated_at
        FROM users u 
        $role_join
        $where_clause
        ORDER BY u.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    
    $users_stmt = $pdo->prepare($users_query);
    $users_stmt->execute($params);
    $users = $users_stmt->fetchAll();
    
    // Her kullanıcı için roller ve skill tags bilgilerini getir
    foreach ($users as &$user) {
        try {
            // Roller
            $roles_query = "
                SELECT r.id, r.name, r.color, r.priority
                FROM user_roles ur
                JOIN roles r ON ur.role_id = r.id
                WHERE ur.user_id = :user_id
                ORDER BY r.priority ASC
            ";
            $roles_stmt = $pdo->prepare($roles_query);
            $roles_stmt->execute([':user_id' => $user['id']]);
            $user['roles'] = $roles_stmt->fetchAll();
            
            // Skill tags
            $skills_query = "
                SELECT st.id, st.tag_name
                FROM user_skill_tags ust
                JOIN skill_tags st ON ust.skill_tag_id = st.id
                WHERE ust.user_id = :user_id
                ORDER BY st.tag_name ASC
            ";
            $skills_stmt = $pdo->prepare($skills_query);
            $skills_stmt->execute([':user_id' => $user['id']]);
            $user['skills'] = $skills_stmt->fetchAll();
            
            // Kullanıcı silinebilir mi kontrolü
            $user['can_delete'] = $is_super_admin || can_user_manage_user_roles($pdo, $user['id'], $_SESSION['user_id']);
            
        } catch (Exception $e) {
            // Eğer rol/skill bilgileri alınamazsa, boş array'ler ata
            error_log("Error loading user details for user {$user['id']}: " . $e->getMessage());
            $user['roles'] = [];
            $user['skills'] = [];
            $user['can_delete'] = false;
        }
    }
    
    // Pagination bilgileri
    $total_pages = ceil($total_users / $per_page);
    $start_index = $total_users > 0 ? $offset + 1 : 0;
    $end_index = min($offset + $per_page, $total_users);
    
    $pagination = [
        'current_page' => $page,
        'per_page' => $per_page,
        'total_users' => $total_users,
        'total_pages' => $total_pages,
        'start_index' => $start_index,
        'end_index' => $end_index,
        'has_prev' => $page > 1,
        'has_next' => $page < $total_pages
    ];
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'pagination' => $pagination
    ]);

} catch (PDOException $e) {
    error_log("Get users PDO error: " . $e->getMessage());
    error_log("Query: " . ($users_query ?? 'N/A'));
    error_log("Params: " . print_r($params ?? [], true));
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Get users general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu: ' . $e->getMessage()
    ]);
}
?>