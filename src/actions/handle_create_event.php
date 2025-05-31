<?php
// src/actions/handle_create_event.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları
require_once BASE_PATH . '/src/functions/notification_functions.php';

require_approved_user();
require_permission($pdo, 'event.create'); // Yetki kontrolü

$baseUrl = get_auth_base_url();
$loggedInUserId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $event_datetime_str = trim($_POST['event_datetime']);
    $description = trim($_POST['description']);
    $location = isset($_POST['location']) ? trim($_POST['location']) : null;
    $event_type = $_POST['event_type'] ?? 'Genel';
    $max_participants = isset($_POST['max_participants']) && is_numeric($_POST['max_participants']) && $_POST['max_participants'] > 0 ? (int)$_POST['max_participants'] : null;
    $suggested_loadout_id = isset($_POST['suggested_loadout_id']) && is_numeric($_POST['suggested_loadout_id']) && $_POST['suggested_loadout_id'] > 0 ? (int)$_POST['suggested_loadout_id'] : null;

    $is_public_no_auth_from_form = isset($_POST['is_public_no_auth']) && $_POST['is_public_no_auth'] == '1';
    $is_members_only_from_form = isset($_POST['is_members_only']) && $_POST['is_members_only'] == '1';
    $assigned_role_ids_from_form = isset($_POST['assigned_role_ids']) && is_array($_POST['assigned_role_ids'])
                                  ? array_map('intval', $_POST['assigned_role_ids'])
                                  : [];

    $db_visibility_enum = 'faction_only';
    $db_is_public_no_auth = 0;
    $db_is_members_only = 0;

    if ($is_public_no_auth_from_form) {
        $db_visibility_enum = 'public';
        $db_is_public_no_auth = 1;
        $assigned_role_ids_from_form = [];
    } elseif ($is_members_only_from_form) {
        $db_visibility_enum = 'members_only';
        $db_is_members_only = 1;
        $assigned_role_ids_from_form = [];
    } elseif (empty($assigned_role_ids_from_form)) {
        $db_visibility_enum = 'members_only';
        $db_is_members_only = 1;
    }

    if (empty($title) || empty($event_datetime_str) || empty($description)) {
        $_SESSION['error_message'] = "Başlık, tarih/saat ve açıklama alanları boş bırakılamaz.";
        $_SESSION['form_input'] = $_POST;
        header('Location: ' . $baseUrl . '/create_event.php');
        exit;
    }

    $event_datetime = '';
    try {
        $dt = new DateTime($event_datetime_str);
        $event_datetime = $dt->format('Y-m-d H:i:s');
        // Etkinlik tarihinin geçmiş bir tarih olup olmadığını kontrol et
        if ($dt < new DateTime()) {
            $_SESSION['error_message'] = "Etkinlik tarihi geçmiş bir tarih olamaz.";
            $_SESSION['form_input'] = $_POST;
            header('Location: ' . $baseUrl . '/create_event.php');
            exit;
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Geçersiz tarih/saat formatı.";
        $_SESSION['form_input'] = $_POST;
        header('Location: ' . $baseUrl . '/create_event.php');
        exit;
    }

    $image_paths_for_db = [null, null, null];
    $uploaded_temp_files = []; // Yüklenen geçici dosyaların yollarını tutmak için

    $upload_dir_base = BASE_PATH . '/public/uploads/event_images/';
    if (!is_dir($upload_dir_base)) {
        if (!mkdir($upload_dir_base, 0775, true)) {
            $_SESSION['error_message'] = "Etkinlik resimleri için yükleme klasörü oluşturulamadı.";
            error_log("Etkinlik resimleri yükleme klasörü oluşturulamadı: " . $upload_dir_base);
            $_SESSION['form_input'] = $_POST;
            header('Location: ' . $baseUrl . '/create_event.php');
            exit;
        }
    }
    if (!is_writable($upload_dir_base)) {
        $_SESSION['error_message'] = "Sunucu yapılandırma hatası: Etkinlik resimleri yükleme klasörü yazılabilir değil.";
        error_log("Etkinlik resimleri yükleme klasörü yazılabilir değil: " . $upload_dir_base);
        $_SESSION['form_input'] = $_POST;
        header('Location: ' . $baseUrl . '/create_event.php');
        exit;
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 20 * 1024 * 1024; // 20MB

    if (isset($_FILES['event_images'])) {
        for ($i = 0; $i < count($_FILES['event_images']['name']); $i++) {
            if ($i >= 3) break;

            if ($_FILES['event_images']['error'][$i] === UPLOAD_ERR_OK) {
                $file_tmp_name = $_FILES['event_images']['tmp_name'][$i];
                $file_name_original = basename($_FILES['event_images']['name'][$i]); // Güvenlik için basename
                $file_size = $_FILES['event_images']['size'][$i];

                if (!file_exists($file_tmp_name) || !is_uploaded_file($file_tmp_name)) {
                    $_SESSION['error_message'] = "Geçici resim dosyası bulunamadı ($file_name_original).";
                    // Başarılı yüklenmiş diğer dosyaları sil (eğer varsa)
                    foreach ($uploaded_temp_files as $temp_file_to_delete) { if (file_exists($temp_file_to_delete)) unlink($temp_file_to_delete); }
                    $_SESSION['form_input'] = $_POST;
                    header('Location: ' . $baseUrl . '/create_event.php');
                    exit;
                }
                $file_type = mime_content_type($file_tmp_name);

                if (!in_array($file_type, $allowed_types)) {
                    $_SESSION['error_message'] = "Geçersiz dosya tipi ($file_name_original). Sadece JPG, PNG, GIF.";
                    foreach ($uploaded_temp_files as $temp_file_to_delete) { if (file_exists($temp_file_to_delete)) unlink($temp_file_to_delete); }
                    $_SESSION['form_input'] = $_POST;
                    header('Location: ' . $baseUrl . '/create_event.php');
                    exit;
                }
                if ($file_size > $max_size) {
                    $_SESSION['error_message'] = "Dosya boyutu çok büyük ($file_name_original). Maksimum " . ($max_size / 1024 / 1024) . "MB.";
                    foreach ($uploaded_temp_files as $temp_file_to_delete) { if (file_exists($temp_file_to_delete)) unlink($temp_file_to_delete); }
                    $_SESSION['form_input'] = $_POST;
                    header('Location: ' . $baseUrl . '/create_event.php');
                    exit;
                }

                $file_extension = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
                // Geçici bir isimle kaydet, DB'ye eklendikten sonra event ID ile yeniden adlandırılacak
                $temp_filename = 'event_temp_' . $loggedInUserId . '_' . time() . '_img' . ($i + 1) . '_' . bin2hex(random_bytes(4)) . '.' . $file_extension;
                $destination = $upload_dir_base . $temp_filename;

                if (move_uploaded_file($file_tmp_name, $destination)) {
                    $image_paths_for_db[$i] = 'uploads/event_images/' . $temp_filename; // Veritabanına geçici yolu kaydet
                    $uploaded_temp_files[$i] = $destination; // Yeniden adlandırma için tam yolu sakla
                } else {
                    $_SESSION['error_message'] = "Fotoğraf ($file_name_original) yüklenirken bir hata oluştu.";
                    error_log("move_uploaded_file (etkinlik oluşturma) başarısız: " . $file_name_original . " Hedef: " . $destination);
                    foreach ($uploaded_temp_files as $temp_file_to_delete) { if (file_exists($temp_file_to_delete)) unlink($temp_file_to_delete); }
                    $_SESSION['form_input'] = $_POST;
                    header('Location: ' . $baseUrl . '/create_event.php');
                    exit;
                }
            } elseif ($_FILES['event_images']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                $_SESSION['error_message'] = "Fotoğraf ($i+1) yüklenirken hata oluştu: Kod " . $_FILES['event_images']['error'][$i];
                foreach ($uploaded_temp_files as $temp_file_to_delete) { if (file_exists($temp_file_to_delete)) unlink($temp_file_to_delete); }
                $_SESSION['form_input'] = $_POST;
                header('Location: ' . $baseUrl . '/create_event.php');
                exit;
            }
        }
    }

    try {
        $pdo->beginTransaction();

        $sql = "INSERT INTO events (title, description, location, event_type, event_datetime, created_by_user_id, image_path_1, image_path_2, image_path_3, status, visibility, is_public_no_auth, is_members_only, max_participants, suggested_loadout_id)
                VALUES (:title, :description, :location, :event_type, :event_datetime, :created_by_user_id, :image_path_1, :image_path_2, :image_path_3, 'active', :visibility, :is_public_no_auth, :is_members_only, :max_participants, :suggested_loadout_id)";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':location', $location);
        $stmt->bindParam(':event_type', $event_type);
        $stmt->bindParam(':event_datetime', $event_datetime);
        $stmt->bindParam(':created_by_user_id', $loggedInUserId, PDO::PARAM_INT);
        $stmt->bindParam(':image_path_1', $image_paths_for_db[0]); // Geçici yol veya null
        $stmt->bindParam(':image_path_2', $image_paths_for_db[1]); // Geçici yol veya null
        $stmt->bindParam(':image_path_3', $image_paths_for_db[2]); // Geçici yol veya null
        $stmt->bindParam(':visibility', $db_visibility_enum);
        $stmt->bindParam(':is_public_no_auth', $db_is_public_no_auth, PDO::PARAM_INT);
        $stmt->bindParam(':is_members_only', $db_is_members_only, PDO::PARAM_INT);
        $stmt->bindParam(':max_participants', $max_participants, PDO::PARAM_INT); // PDO::PARAM_NULL if null
        $stmt->bindParam(':suggested_loadout_id', $suggested_loadout_id, PDO::PARAM_INT); // PDO::PARAM_NULL if null

        if ($stmt->execute()) {
            $newEventId = $pdo->lastInsertId();
            $final_image_paths_for_update = [null, null, null];

            // Resimleri event ID ile yeniden adlandır ve DB'yi güncelle
            for ($j = 0; $j < 3; $j++) {
                if ($image_paths_for_db[$j] !== null && isset($uploaded_temp_files[$j])) {
                    $old_temp_path_full = $uploaded_temp_files[$j]; //örn: /basepath/public/uploads/event_images/event_temp_...
                    $path_parts = pathinfo($old_temp_path_full);
                    $final_filename_with_id = 'event_' . $newEventId . '_img' . ($j + 1) . '_' . time() . '.' . $path_parts['extension'];
                    $new_final_path_full = $upload_dir_base . $final_filename_with_id;

                    if (rename($old_temp_path_full, $new_final_path_full)) {
                        $final_image_paths_for_update[$j] = 'uploads/event_images/' . $final_filename_with_id;
                    } else {
                        error_log("Etkinlik resmi yeniden adlandırılamadı: Eski: $old_temp_path_full Yeni: $new_final_path_full");
                        // Hata durumunda geçici dosyayı silmeye çalışabiliriz veya loglayabiliriz
                        // Şimdilik DB'ye null yazılacak (çünkü $final_image_paths_for_update[$j] null kalacak)
                        if(file_exists($old_temp_path_full)) unlink($old_temp_path_full);
                    }
                }
            }

            // DB'deki resim yollarını son halleriyle güncelle
            $stmt_update_images = $pdo->prepare("UPDATE events SET image_path_1 = :img1, image_path_2 = :img2, image_path_3 = :img3 WHERE id = :event_id");
            $stmt_update_images->execute([
                ':img1' => $final_image_paths_for_update[0],
                ':img2' => $final_image_paths_for_update[1],
                ':img3' => $final_image_paths_for_update[2],
                ':event_id' => $newEventId
            ]);

            if ($db_visibility_enum === 'faction_only' && !empty($assigned_role_ids_from_form)) {
                $stmt_event_role_insert = $pdo->prepare("INSERT INTO event_visibility_roles (event_id, role_id) VALUES (?, ?)");
                foreach ($assigned_role_ids_from_form as $role_id_to_assign) {
                    $stmt_event_role_insert->execute([$newEventId, $role_id_to_assign]);
                }
            }

            // Etkinliği oluşturanı otomatik olarak katılımcı yap
            $stmt_add_creator_participant = $pdo->prepare("INSERT INTO event_participants (event_id, user_id, participation_status) VALUES (?, ?, 'attending')");
            $stmt_add_creator_participant->execute([$newEventId, $loggedInUserId]);


            // Bildirim gönderme
            $stmt_users_to_notify = $pdo->prepare("SELECT id FROM users WHERE status = 'approved' AND id != :creator_id");
            $stmt_users_to_notify->execute([':creator_id' => $loggedInUserId]);
            $users_to_notify_ids = $stmt_users_to_notify->fetchAll(PDO::FETCH_COLUMN);

            if ($users_to_notify_ids) {
                $notification_message = "Yeni bir etkinlik oluşturuldu: \"" . htmlspecialchars($title) . "\"";
                $notification_link_base = $baseUrl . '/event_detail.php?id=' . $newEventId;
                // create_notification fonksiyonu her kullanıcı için ayrı bildirim oluşturur.
                foreach ($users_to_notify_ids as $notify_user_id) {
                    create_notification($pdo, $notify_user_id, $newEventId, $loggedInUserId, $notification_message, $notification_link_base);
                }
            }

            $pdo->commit();
            $_SESSION['success_message'] = "Etkinlik başarıyla oluşturuldu!";
            header('Location: ' . $baseUrl . '/event_detail.php?id=' . $newEventId);
            exit;
        } else {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Etkinlik oluşturulurken bir veritabanı hatası oluştu (execute false).";
            // Yüklenmiş geçici dosyaları sil
            foreach ($uploaded_temp_files as $temp_file_to_delete) { if (file_exists($temp_file_to_delete)) unlink($temp_file_to_delete); }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Etkinlik oluşturma veritabanı hatası: " . $e->getMessage());
        $_SESSION['error_message'] = "Etkinlik oluşturulurken kritik bir veritabanı hatası oluştu.";
        // Yüklenmiş geçici dosyaları sil
        foreach ($uploaded_temp_files as $temp_file_to_delete) { if (file_exists($temp_file_to_delete)) unlink($temp_file_to_delete); }
    }

    // Hata durumunda formu tekrar doldurmak için
    $_SESSION['form_input'] = $_POST;
    header('Location: ' . $baseUrl . '/create_event.php');
    exit;

} else {
    // POST isteği değilse
    header('Location: ' . $baseUrl . '/events.php');
    exit;
}
