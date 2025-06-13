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

// Yetki kontrolü
if (!is_user_logged_in() || !has_permission($pdo, 'admin.users.view')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

try {
    // GET parametrelerini al
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = max(1, min(100, intval($_GET['per_page'] ?? 20)));
    $search = trim($_GET['search'] ?? '');
    $status_filter = $_GET['status'] ?? 'all';
    $role_filter = $_GET['role'] ?? 'all';
    
    $offset = ($page - 1) * $per_page;
    
    // Base query
    $base_query = "
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Search filtresi
    if (!empty($search)) {
        $base_query .= " AND (
            u.username LIKE :search OR 
            u.email LIKE :search OR 
            u.ingame_name LIKE :search OR
            u.discord_username LIKE :search
        )";
        $params[':search'] = "%$search%";
    }
    
    // Status filtresi
    if ($status_filter !== 'all') {
        $base_query .= " AND u.status = :status";
        $params[':status'] = $status_filter;
    }
    
    // Role filtresi
    if ($role_filter !== 'all') {
        $base_query .= " AND ur.role_id = :role_id";
        $params[':role_id'] = $role_filter;
    }
    
    // Toplam sayı
    $count_query = "SELECT COUNT(DISTINCT u.id) as total " . $base_query;
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
        " . $base_query . "
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $users_stmt = $pdo->prepare($users_query);
    
    // Parametreleri bind et
    foreach ($params as $key => $value) {
        $users_stmt->bindValue($key, $value);
    }
    $users_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $users_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $users_stmt->execute();
    $users = $users_stmt->fetchAll();
    
    // Her kullanıcı için roller ve skill tags bilgilerini getir
    foreach ($users as &$user) {
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
        $user['can_delete'] = can_user_manage_user_roles($pdo, $user['id'], $_SESSION['user_id']);
    }
    
    // Pagination bilgileri
    $total_pages = ceil($total_users / $per_page);
    $start_index = $offset + 1;
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
    error_log("Get users error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası oluştu'
    ]);
} catch (Exception $e) {
    error_log("Get users general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu'
    ]);
}
?>