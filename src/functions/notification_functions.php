<?php
// src/functions/notification_functions.php

if (session_status() == PHP_SESSION_NONE) {
    // session_start(); 
}

// create_notification fonksiyonu bir önceki yanıttaki gibi kalacak.

function create_notification(PDO $pdo, int $user_id_to_notify, ?int $event_id, ?int $actor_user_id, string $message, string $base_link): bool {
    try {
        // error_log("create_notification: User: $user_id_to_notify, Event: $event_id, Actor: $actor_user_id, Msg: $message, BaseLink: $base_link");
        $stmt_insert_notification = $pdo->prepare(
            "INSERT INTO notifications (user_id, event_id, actor_user_id, message, link, is_read) 
             VALUES (:user_id, :event_id, :actor_user_id, :message, :link, 0)"
        );
        
        $params = [
            ':user_id' => $user_id_to_notify,
            ':event_id' => $event_id,
            ':actor_user_id' => $actor_user_id,
            ':message' => $message,
            ':link' => $base_link 
        ];

        if ($stmt_insert_notification->execute($params)) {
            $notification_id = $pdo->lastInsertId();
            if ($notification_id) {
                $final_link = $base_link . (strpos($base_link, '?') === false ? '?' : '&') . 'notif_id=' . $notification_id;
                $stmt_update_link = $pdo->prepare("UPDATE notifications SET link = :final_link WHERE id = :notification_id");
                if ($stmt_update_link->execute([':final_link' => $final_link, ':notification_id' => $notification_id])) {
                    // error_log("create_notification: Success. ID: $notification_id, Final Link: $final_link");
                    return true;
                } else {
                    error_log("create_notification: Link update FAILED. ID: $notification_id, Error: " . print_r($stmt_update_link->errorInfo(), true));
                    return true; 
                }
            } else {
                 error_log("create_notification: lastInsertId FAILED after insert.");
            }
        } else {
             error_log("create_notification: INSERT FAILED. Error: " . print_r($stmt_insert_notification->errorInfo(), true));
        }
        return false;
    } catch (PDOException $e) {
        error_log("create_notification: DB Exception: " . $e->getMessage() . " | Params: " . print_r($params ?? [], true));
        return false;
    }
}

function get_unread_notifications(PDO $pdo, int $user_id, int $limit = 5): array {
    // Bu fonksiyon loglamaya ihtiyaç duymayabilir, sorun okuma işleminde.
    try {
        $sql = "SELECT 
                    n.id, n.message, n.link, n.event_id, n.created_at,
                    n.actor_user_id, 
                    u_actor.username AS actor_username 
                FROM notifications n
                LEFT JOIN users u_actor ON n.actor_user_id = u_actor.id
                WHERE n.user_id = :user_id AND n.is_read = 0 
                ORDER BY n.created_at DESC 
                LIMIT :limit_val";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit_val', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("get_unread_notifications DB error (User ID: $user_id): " . $e->getMessage());
        return [];
    }
}

function count_unread_notifications(PDO $pdo, int $user_id): int {
    // Bu fonksiyon loglamaya ihtiyaç duymayabilir.
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("count_unread_notifications DB error (User ID: $user_id): " . $e->getMessage());
        return 0;
    }
}

function mark_notification_as_read(PDO $pdo, int $notification_id, int $user_id): bool {
    error_log("mark_notification_as_read (FUNCTION) CALLED with notif_id: $notification_id, user_id: $user_id. PDO: " . (isset($pdo) ? 'OK' : 'MISSING!'));
    
    if (!isset($pdo)) {
        error_log("mark_notification_as_read (FUNCTION): PDO object is not available.");
        return false;
    }

    try {
        // 1. Bildirimin varlığını ve mevcut durumunu kontrol et
        $stmt_check = $pdo->prepare("SELECT is_read FROM notifications WHERE id = :notification_id AND user_id = :user_id");
        $stmt_check->execute([':notification_id' => $notification_id, ':user_id' => $user_id]);
        $current_status_data = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_status_data) {
            error_log("mark_notification_as_read (FUNCTION): Bildirim (ID: $notification_id) kullanıcı (ID: $user_id) için bulunamadı veya ait değil.");
            return false; 
        }
        
        error_log("mark_notification_as_read (FUNCTION): Mevcut durum (ID: $notification_id): is_read = " . $current_status_data['is_read']);
        
        // 2. Zaten okunmuşsa, tekrar güncelleme yapma, başarılı say
        if ($current_status_data['is_read'] == 1) {
            error_log("mark_notification_as_read (FUNCTION): Bildirim (ID: $notification_id) zaten okunmuş.");
            return true; 
        }
        
        // 3. Okunmadıysa, okundu olarak işaretle
        $stmt_update = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :notification_id AND user_id = :user_id AND is_read = 0");
        // is_read = 0 koşulunu eklemek, rowCount'un sadece gerçekten değişiklik olduğunda > 0 olmasını sağlar.
        $stmt_update->bindParam(':notification_id', $notification_id, PDO::PARAM_INT);
        $stmt_update->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        
        $execute_result = $stmt_update->execute();
        
        if (!$execute_result) {
            $errorInfo = $stmt_update->errorInfo();
            error_log("mark_notification_as_read (FUNCTION): UPDATE sorgusu BAŞARISIZ oldu (ID: $notification_id). Hata: " . print_r($errorInfo, true));
            return false;
        }
        
        $affected_rows = $stmt_update->rowCount();
        error_log("mark_notification_as_read (FUNCTION): UPDATE sorgusu sonucu etkilenen satır sayısı (ID: $notification_id): " . $affected_rows);
        
        // Eğer en az bir satır güncellendiyse başarılıdır.
        return $affected_rows > 0;
        
    } catch (PDOException $e) {
        error_log("mark_notification_as_read (FUNCTION) DB Exception (ID: $notification_id, User: $user_id): " . $e->getMessage());
        return false;
    }
}

function mark_all_notifications_as_read(PDO $pdo, int $user_id): bool {
    error_log("mark_all_notifications_as_read (FUNCTION) CALLED for user_id: $user_id");
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        error_log("mark_all_notifications_as_read (FUNCTION): Etkilenen satır sayısı: " . $stmt->rowCount());
        return true; 
    } catch (PDOException $e) {
        error_log("mark_all_notifications_as_read (FUNCTION) DB error (User ID: $user_id): " . $e->getMessage());
        return false;
    }
}

function get_all_notifications(PDO $pdo, int $user_id): array {
    // Bu fonksiyon loglamaya ihtiyaç duymayabilir.
    try {
        $sql = "SELECT 
                    n.id, n.message, n.link, n.event_id, n.is_read, n.created_at,
                    n.actor_user_id,
                    u_actor.username AS actor_username
                FROM notifications n
                LEFT JOIN users u_actor ON n.actor_user_id = u_actor.id
                WHERE n.user_id = :user_id
                ORDER BY n.is_read ASC, n.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("get_all_notifications DB error (User ID: $user_id): " . $e->getMessage());
        return [];
    }
}
?>
