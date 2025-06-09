<?php
// src/functions/event_functions.php

/**
 * Kullanıcının rol gereksinimlerini kontrol et
 */
function check_user_role_requirements($pdo, $user_id, $role_id) {
    // Rol gereksinimleri
    $stmt = $pdo->prepare("
        SELECT skill_tag_id FROM event_role_requirements 
        WHERE role_id = ?
    ");
    $stmt->execute([$role_id]);
    $required_tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($required_tags)) {
        return true; // Gereksinim yok, herkes katılabilir
    }
    
    // Kullanıcının skill tag'leri
    $stmt = $pdo->prepare("
        SELECT skill_tag_id FROM user_skill_tags 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user_tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Tüm gereksinimleri karşılıyor mu?
    return count(array_intersect($required_tags, $user_tags)) === count($required_tags);
}

/**
 * Event listesini getir
 */
function get_events_list($pdo, $user_id = null, $limit = 20, $offset = 0, $filters = []) {
    $where_conditions = ["1=1"];
    $params = [];
    
    // Status filtresi
    if (!empty($filters['status'])) {
        $where_conditions[] = "e.status = ?";
        $params[] = $filters['status'];
    }
    
    // Tarih filtresi
    if (!empty($filters['date_from'])) {
        $where_conditions[] = "e.event_date >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where_conditions[] = "e.event_date <= ?";
        $params[] = $filters['date_to'];
    }
    
    // Visibility filtresi - kullanıcıya göre
    if ($user_id) {
        $visibility_sql = "(e.visibility = 'public' OR 
                          (e.visibility = 'members_only' AND ? IN (SELECT id FROM users WHERE status = 'approved')) OR
                          (e.visibility = 'faction_only' AND EXISTS (
                              SELECT 1 FROM event_visibility_roles evr 
                              JOIN user_roles ur ON evr.role_id = ur.role_id 
                              WHERE evr.event_id = e.id AND ur.user_id = ?
                          )))";
        $where_conditions[] = $visibility_sql;
        array_push($params, $user_id, $user_id);
    } else {
        $where_conditions[] = "e.visibility = 'public'";
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    
    // Toplam sayıyı al
    $count_sql = "SELECT COUNT(*) FROM events e WHERE $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Event listesini al
    $sql = "
        SELECT e.*, u.username as creator_name, u.avatar_path as creator_avatar,
               (SELECT COUNT(*) FROM event_participations WHERE event_id = e.id AND participation_status = 'confirmed') as confirmed_count,
               (SELECT COUNT(*) FROM event_participations WHERE event_id = e.id AND participation_status = 'maybe') as maybe_count,
               (SELECT COUNT(*) FROM event_participations WHERE event_id = e.id AND participation_status = 'declined') as declined_count,
               (SELECT SUM(slot_count) FROM event_role_slots WHERE event_id = e.id) as total_slots
        FROM events e
        JOIN users u ON e.created_by_user_id = u.id
        WHERE $where_clause
        ORDER BY e.event_date DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll();
    
    return [
        'events' => $events,
        'total' => $total
    ];
}

/**
 * Event detaylarını getir
 */
function get_event_details($pdo, $event_id) {
    $stmt = $pdo->prepare("
        SELECT e.*, u.username as creator_name, u.avatar_path as creator_avatar
        FROM events e
        JOIN users u ON e.created_by_user_id = u.id
        WHERE e.id = ?
    ");
    $stmt->execute([$event_id]);
    return $stmt->fetch();
}

/**
 * Event rol slotlarını getir
 */
function get_event_role_slots($pdo, $event_id) {
    $stmt = $pdo->prepare("
        SELECT ers.*, er.role_name, er.role_description, er.role_icon,
               er.suggested_loadout_id, ls.set_name as suggested_loadout_name,
               (SELECT COUNT(*) FROM event_participations 
                WHERE role_slot_id = ers.id AND participation_status = 'confirmed') as filled_slots
        FROM event_role_slots ers
        JOIN event_roles er ON ers.role_id = er.id
        LEFT JOIN loadout_sets ls ON er.suggested_loadout_id = ls.id
        WHERE ers.event_id = ?
        ORDER BY er.role_name
    ");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll();
}

/**
 * Event katılımcılarını getir
 */
function get_event_participants($pdo, $event_id) {
    $stmt = $pdo->prepare("
        SELECT ep.*, u.username, u.avatar_path, u.ingame_name,
               ers.role_id, er.role_name, er.role_icon
        FROM event_participations ep
        JOIN users u ON ep.user_id = u.id
        LEFT JOIN event_role_slots ers ON ep.role_slot_id = ers.id
        LEFT JOIN event_roles er ON ers.role_id = er.id
        WHERE ep.event_id = ?
        ORDER BY ep.participation_status DESC, er.role_name, u.username
    ");
    $stmt->execute([$event_id]);
    
    $participants = $stmt->fetchAll();
    
    // Duruma göre grupla
    $grouped = [
        'confirmed' => [],
        'maybe' => [],
        'declined' => []
    ];
    
    foreach ($participants as $participant) {
        $grouped[$participant['participation_status']][] = $participant;
    }
    
    return $grouped;
}

/**
 * Kullanıcının event katılım durumunu getir
 */
function get_user_event_participation($pdo, $event_id, $user_id) {
    $stmt = $pdo->prepare("
        SELECT ep.*, ers.role_id, er.role_name
        FROM event_participations ep
        LEFT JOIN event_role_slots ers ON ep.role_slot_id = ers.id
        LEFT JOIN event_roles er ON ers.role_id = er.id
        WHERE ep.event_id = ? AND ep.user_id = ?
    ");
    $stmt->execute([$event_id, $user_id]);
    return $stmt->fetch();
}

/**
 * Event istatistiklerini getir
 */
function get_event_statistics($pdo, $user_id = null) {
    $stats = [];
    
    // Toplam event sayısı
    $where = $user_id ? "AND (visibility = 'public' OR created_by_user_id = ?)" : "AND visibility = 'public'";
    $params = $user_id ? [$user_id] : [];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE status = 'published' $where");
    $stmt->execute($params);
    $stats['total_events'] = $stmt->fetchColumn();
    
    // Yaklaşan eventler
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM events 
        WHERE status = 'published' AND event_date > NOW() $where
    ");
    $stmt->execute($params);
    $stats['upcoming_events'] = $stmt->fetchColumn();
    
    // Aktif katılımcı sayısı
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT user_id) 
        FROM event_participations 
        WHERE participation_status = 'confirmed'
    ");
    $stats['active_participants'] = $stmt->fetchColumn();
    
    return $stats;
}

/**
 * Zaman formatlama yardımcı fonksiyonu
 */
function format_event_date($datetime) {
    $timestamp = strtotime($datetime);
    $now = time();
    $diff = $timestamp - $now;
    
    // Geçmiş
    if ($diff < 0) {
        return '<span class="text-muted">Tamamlandı</span>';
    }
    
    // 24 saat içinde
    if ($diff < 86400) {
        return '<span class="text-danger">Bugün ' . date('H:i', $timestamp) . '</span>';
    }
    
    // 7 gün içinde
    if ($diff < 604800) {
        return '<span class="text-warning">' . date('d.m.Y H:i', $timestamp) . '</span>';
    }
    
    // Normal
    return date('d.m.Y H:i', $timestamp);
}
?>