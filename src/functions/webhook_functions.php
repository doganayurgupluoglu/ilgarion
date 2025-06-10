<?php
// src/functions/webhook_functions.php - Webhook işlemleri

/**
 * Etkinlik oluşturma webhook'unu gönderir
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $event_id Oluşturulan etkinlik ID'si
 * @param string $action İşlem türü (created, updated, deleted)
 * @return bool Başarılıysa true
 */
function send_event_webhook(PDO $pdo, int $event_id, string $action = 'created'): bool {
    try {
        // Webhook URL'ini sistem ayarlarından al
        $webhook_url = get_system_setting($pdo, 'event_webhook_url', null);
        
        if (empty($webhook_url)) {
            error_log("Event webhook URL not configured");
            return false;
        }
        
        // Etkinlik verilerini çek
        $event_data = getEventWebhookData($pdo, $event_id);
        
        if (!$event_data) {
            error_log("Could not fetch event data for webhook: Event ID $event_id");
            return false;
        }
        
        // Webhook payload'ını oluştur
        $payload = createEventWebhookPayload($event_data, $action);
        
        // Webhook'u gönder
        $result = sendWebhookRequest($webhook_url, $payload);
        
        // Sonucu logla
        if ($result['success']) {
            audit_log($pdo, $_SESSION['user_id'] ?? null, 'webhook_sent', 'event', $event_id, null, [
                'webhook_url' => $webhook_url,
                'action' => $action,
                'response_code' => $result['http_code'],
                'response_time' => $result['response_time'] ?? 0
            ]);
        } else {
            error_log("Webhook failed for event $event_id: " . $result['error']);
            audit_log($pdo, $_SESSION['user_id'] ?? null, 'webhook_failed', 'event', $event_id, null, [
                'webhook_url' => $webhook_url,
                'action' => $action,
                'error' => $result['error'],
                'response_code' => $result['http_code'] ?? 0
            ]);
        }
        
        return $result['success'];
        
    } catch (Exception $e) {
        error_log("Webhook exception for event $event_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Etkinlik verilerini webhook için çeker
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $event_id Etkinlik ID'si
 * @return array|null Etkinlik verileri
 */
function getEventWebhookData(PDO $pdo, int $event_id): ?array {
    try {
        // Ana etkinlik verileri
        $event_query = "
            SELECT e.*, u.username, u.ingame_name, u.discord_username, u.email, u.avatar_path,
                   r.name as primary_role_name, r.color as primary_role_color, r.priority
            FROM events e
            JOIN users u ON e.created_by_user_id = u.id
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE e.id = :event_id
            ORDER BY r.priority ASC
            LIMIT 1
        ";
        
        $stmt = execute_safe_query($pdo, $event_query, [':event_id' => $event_id]);
        $event = $stmt->fetch();
        
        if (!$event) {
            return null;
        }
        
        // Etkinlik rollerini çek
        $roles_query = "
            SELECT ers.id as slot_id, ers.slot_count,
                   er.id as role_id, er.role_name, er.role_description, er.role_icon,
                   GROUP_CONCAT(DISTINCT st.tag_name SEPARATOR ',') as requirements,
                   COUNT(ep.id) as confirmed_participants
            FROM event_role_slots ers
            JOIN event_roles er ON ers.role_id = er.id
            LEFT JOIN event_role_requirements err ON er.id = err.role_id
            LEFT JOIN skill_tags st ON err.skill_tag_id = st.id
            LEFT JOIN event_participations ep ON ers.id = ep.role_slot_id 
                AND ep.participation_status = 'confirmed'
            WHERE ers.event_id = :event_id
            GROUP BY ers.id, ers.slot_count, er.id, er.role_name, er.role_description, er.role_icon
            ORDER BY er.role_name ASC
        ";
        
        $stmt = execute_safe_query($pdo, $roles_query, [':event_id' => $event_id]);
        $event_roles = $stmt->fetchAll();
        
        // Katılımcıları çek (eğer varsa)
        $participants_query = "
            SELECT ep.participation_status, u.username, u.ingame_name, u.discord_username,
                   ers.id as slot_id, er.role_name
            FROM event_participations ep
            JOIN users u ON ep.user_id = u.id
            JOIN event_role_slots ers ON ep.role_slot_id = ers.id
            JOIN event_roles er ON ers.role_id = er.id
            WHERE ep.event_id = :event_id
            ORDER BY ep.registered_at ASC
        ";
        
        $stmt = execute_safe_query($pdo, $participants_query, [':event_id' => $event_id]);
        $participants = $stmt->fetchAll();
        
        return [
            'event' => $event,
            'event_roles' => $event_roles,
            'participants' => $participants
        ];
        
    } catch (Exception $e) {
        error_log("Error fetching event webhook data: " . $e->getMessage());
        return null;
    }
}

/**
 * Webhook payload'ını oluşturur
 * @param array $event_data Etkinlik verileri
 * @param string $action İşlem türü
 * @return array Webhook payload
 */
function createEventWebhookPayload(array $event_data, string $action): array {
    $event = $event_data['event'];
    $event_roles = $event_data['event_roles'];
    $participants = $event_data['participants'];
    
    $base_url = get_auth_base_url();
    $current_time = date('c'); // ISO 8601 format
    
    // Avatar path'i düzelt
    $avatar_url = null;
    if (!empty($event['avatar_path'])) {
        if (strpos($event['avatar_path'], 'http') === 0) {
            $avatar_url = $event['avatar_path'];
        } elseif (strpos($event['avatar_path'], '../assets/') === 0) {
            $avatar_url = $base_url . str_replace('../assets/', '/assets/', $event['avatar_path']);
        } elseif (strpos($event['avatar_path'], 'uploads/') === 0) {
            $avatar_url = $base_url . '/' . $event['avatar_path'];
        } else {
            $avatar_url = $base_url . '/assets/logo.png';
        }
    } else {
        $avatar_url = $base_url . '/assets/logo.png';
    }
    
    // Thumbnail URL'i düzelt
    $thumbnail_url = null;
    if (!empty($event['event_thumbnail_path'])) {
        if (strpos($event['event_thumbnail_path'], 'http') === 0) {
            $thumbnail_url = $event['event_thumbnail_path'];
        } else {
            $thumbnail_url = $base_url . '/' . $event['event_thumbnail_path'];
        }
    }
    
    // Rol verilerini düzenle
    $formatted_roles = [];
    $total_slots = 0;
    $available_slots = 0;
    $confirmed_participants = 0;
    
    foreach ($event_roles as $role) {
        $slot_participants = array_filter($participants, function($p) use ($role) {
            return $p['slot_id'] == $role['slot_id'] && $p['participation_status'] === 'confirmed';
        });
        
        $requirements = !empty($role['requirements']) ? explode(',', $role['requirements']) : [];
        
        $formatted_roles[] = [
            'slot_id' => (int)$role['slot_id'],
            'role' => [
                'id' => (int)$role['role_id'],
                'name' => $role['role_name'],
                'description' => $role['role_description'] ?: '',
                'icon' => $role['role_icon'] ?: 'fas fa-user',
                'requirements' => $requirements
            ],
            'slot_count' => (int)$role['slot_count'],
            'available_slots' => (int)$role['slot_count'] - (int)$role['confirmed_participants'],
            'participants' => array_map(function($p) {
                return [
                    'username' => $p['username'],
                    'ingame_name' => $p['ingame_name'] ?: '',
                    'discord_username' => $p['discord_username'] ?: '',
                    'status' => $p['participation_status']
                ];
            }, $slot_participants)
        ];
        
        $total_slots += (int)$role['slot_count'];
        $available_slots += ((int)$role['slot_count'] - (int)$role['confirmed_participants']);
        $confirmed_participants += (int)$role['confirmed_participants'];
    }
    
    // Pending participants sayısı
    $pending_participants = count(array_filter($participants, function($p) {
        return $p['participation_status'] === 'pending';
    }));
    
    $payload = [
        'webhook_id' => 'event_creation_webhook',
        'webhook_name' => 'Event Creation Notification',
        'webhook_notification_id' => uniqid('webhook_', true),
        'webhook_url' => get_system_setting(get_pdo(), 'event_webhook_url', ''),
        'event_type' => 'event.' . $action,
        'timestamp' => $current_time,
        'server_info' => [
            'host' => $_SERVER['HTTP_HOST'] ?? 'localhost',
            'user_agent' => 'IlgarionTuranis-Webhook/1.0',
            'ip_address' => get_client_ip()
        ],
        'data' => [
            'event' => [
                'id' => (int)$event['id'],
                'title' => $event['event_title'],
                'description' => $event['event_description'],
                'location' => $event['event_location'] ?: '',
                'event_date' => date('c', strtotime($event['event_date'])),
                'visibility' => $event['visibility'],
                'status' => $event['status'],
                'thumbnail_url' => $thumbnail_url,
                'created_at' => date('c', strtotime($event['created_at'])),
                'updated_at' => $event['updated_at'] ? date('c', strtotime($event['updated_at'])) : null
            ],
            'creator' => [
                'id' => (int)$event['created_by_user_id'],
                'username' => $event['username'],
                'ingame_name' => $event['ingame_name'] ?: '',
                'discord_username' => $event['discord_username'] ?: '',
                'email' => $event['email'],
                'avatar_url' => $avatar_url,
                'primary_role' => [
                    'name' => $event['primary_role_name'] ?: 'member',
                    'display_name' => ucfirst($event['primary_role_name'] ?: 'member'),
                    'color' => $event['primary_role_color'] ?: '#bd912a',
                    'priority' => (int)($event['priority'] ?: 999)
                ]
            ],
            'event_roles' => $formatted_roles,
            'statistics' => [
                'total_slots' => $total_slots,
                'available_slots' => $available_slots,
                'confirmed_participants' => $confirmed_participants,
                'pending_participants' => $pending_participants,
                'roles_count' => count($formatted_roles)
            ],
            'urls' => [
                'event_detail' => $base_url . '/events/detail.php?id=' . $event['id'],
                'event_edit' => $base_url . '/events/create.php?edit=' . $event['id'],
                'participation' => $base_url . '/events/actions/participate.php'
            ]
        ],
        'action' => $action,
        'metadata' => [
            'user_ip' => get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id(),
            'csrf_token_valid' => true,
            'form_source' => 'web_interface'
        ]
    ];
    
    return $payload;
}

/**
 * Webhook isteğini gönderir
 * @param string $webhook_url Webhook URL'i
 * @param array $payload Gönderilecek veri
 * @return array Sonuç bilgileri
 */
function sendWebhookRequest(string $webhook_url, array $payload): array {
    $start_time = microtime(true);
    
    try {
        // cURL ile webhook gönder
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $webhook_url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: IlgarionTuranis-Webhook/1.0',
                'X-Webhook-Event: event.created',
                'X-Webhook-Delivery: ' . uniqid('delivery_', true),
                'X-Webhook-Timestamp: ' . date('c')
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_USERAGENT => 'IlgarionTuranis-Webhook/1.0'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $response_time = microtime(true) - $start_time;
        
        curl_close($ch);
        
        if ($response === false || !empty($error)) {
            return [
                'success' => false,
                'error' => $error ?: 'Unknown cURL error',
                'http_code' => $http_code,
                'response_time' => $response_time
            ];
        }
        
        // 2xx status code'ları başarılı kabul et
        $success = $http_code >= 200 && $http_code < 300;
        
        return [
            'success' => $success,
            'response' => $response,
            'http_code' => $http_code,
            'response_time' => $response_time,
            'error' => $success ? null : "HTTP $http_code response"
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Exception: ' . $e->getMessage(),
            'http_code' => 0,
            'response_time' => microtime(true) - $start_time
        ];
    }
}

/**
 * Webhook URL'ini sistem ayarlarına kaydeder
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $webhook_url Webhook URL'i
 * @return bool Başarılıysa true
 */
function set_event_webhook_url(PDO $pdo, string $webhook_url): bool {
    return set_system_setting($pdo, 'event_webhook_url', $webhook_url, 'string', 'Event creation webhook URL');
}

/**
 * Webhook URL'ini sistem ayarlarından getirir
 * @param PDO $pdo Veritabanı bağlantısı
 * @return string|null Webhook URL'i
 */
function get_event_webhook_url(PDO $pdo): ?string {
    return get_system_setting($pdo, 'event_webhook_url', null);
}

/**
 * Webhook'u test etmek için test verisi gönderir
 * @param PDO $pdo Veritabanı bağlantısı
 * @return array Test sonucu
 */
function test_event_webhook(PDO $pdo): array {
    $webhook_url = get_event_webhook_url($pdo);
    
    if (empty($webhook_url)) {
        return [
            'success' => false,
            'message' => 'Webhook URL tanımlanmamış'
        ];
    }
    
    $test_payload = [
        'webhook_id' => 'event_webhook_test',
        'webhook_name' => 'Event Webhook Test',
        'webhook_notification_id' => uniqid('test_', true),
        'event_type' => 'webhook.test',
        'timestamp' => date('c'),
        'data' => [
            'message' => 'Bu bir test webhook mesajıdır',
            'test_time' => date('Y-m-d H:i:s'),
            'server' => $_SERVER['HTTP_HOST'] ?? 'localhost'
        ]
    ];
    
    $result = sendWebhookRequest($webhook_url, $test_payload);
    
    return [
        'success' => $result['success'],
        'message' => $result['success'] ? 'Test webhook başarıyla gönderildi' : 'Test webhook başarısız: ' . $result['error'],
        'details' => $result
    ];
}

/**
 * PDO instance'ı getirir (global $pdo erişimi için)
 * @return PDO
 */
function get_pdo(): PDO {
    global $pdo;
    return $pdo;
}
?>