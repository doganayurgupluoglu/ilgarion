<?php
// src/actions/handle_edit_event.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Yetki fonksiyonları

$baseUrl = get_auth_base_url();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { /* ... */ }
if (!isset($_POST['event_id']) || !is_numeric($_POST['event_id'])) { /* ... */ }
$event_id = (int)$_POST['event_id'];

// Yetkilendirme (aynı kalacak, ama has_permission kullanılabilir)
// ... (require_approved_user ve event.edit_all/event.edit_own yetki kontrolü) ...
$stmt_check_edit = $pdo->prepare("SELECT created_by_user_id, image_path_1, image_path_2, image_path_3 FROM events WHERE id = ?");
$stmt_check_edit->execute([$event_id]);
$event_to_edit_auth = $stmt_check_edit->fetch(PDO::FETCH_ASSOC);
if (!$event_to_edit_auth) { /* Hata */ }

$can_edit_this_event_action = false;
if (has_permission($pdo, 'event.edit_all', $_SESSION['user_id'])) {
    $can_edit_this_event_action = true;
} elseif (has_permission($pdo, 'event.edit_own', $_SESSION['user_id']) && $event_to_edit_auth['created_by_user_id'] == $_SESSION['user_id']) {
    $can_edit_this_event_action = true;
}
if (!$can_edit_this_event_action) {
    $_SESSION['error_message'] = "Bu etkinliği düzenleme yetkiniz yok.";
    header('Location: ' . $baseUrl . '/event_detail.php?id=' . $event_id);
    exit;
}


// Form verilerini al (create_event'teki gibi)
$title = trim($_POST['title']);
// ... (diğer form alanları) ...

// Yeni görünürlük alanları
$is_public_no_auth_from_form_edit = isset($_POST['is_public_no_auth']) && $_POST['is_public_no_auth'] == '1';
$is_members_only_from_form_edit = isset($_POST['is_members_only']) && $_POST['is_members_only'] == '1';
$assigned_role_ids_from_form_edit = isset($_POST['assigned_role_ids']) && is_array($_POST['assigned_role_ids'])
                                  ? array_map('intval', $_POST['assigned_role_ids'])
                                  : [];

// `events` tablosu için `visibility` sütununu ve bayrakları belirle
$db_visibility_enum_edit = 'faction_only';
$db_is_public_no_auth_edit = 0;
$db_is_members_only_edit = 0;

if ($is_public_no_auth_from_form_edit) {
    $db_visibility_enum_edit = 'public';
    $db_is_public_no_auth_edit = 1;
    $assigned_role_ids_from_form_edit = [];
} elseif ($is_members_only_from_form_edit) {
    $db_visibility_enum_edit = 'members_only';
    $db_is_members_only_edit = 1;
    $assigned_role_ids_from_form_edit = [];
} elseif (empty($assigned_role_ids_from_form_edit)) {
    $db_visibility_enum_edit = 'members_only'; // Varsayılan
    $db_is_members_only_edit = 1;
}


// ... (temel doğrulamalar ve tarih formatlama aynı) ...

$update_fields_edit = [];
$params_edit = [];

$update_fields_edit[] = "title = :title"; $params_edit[':title'] = $title;
// ... (diğer temel alanlar için $update_fields_edit ve $params_edit'e eklemeler) ...
$update_fields_edit[] = "visibility = :visibility"; $params_edit[':visibility'] = $db_visibility_enum_edit;
$update_fields_edit[] = "is_public_no_auth = :is_public_no_auth"; $params_edit[':is_public_no_auth'] = $db_is_public_no_auth_edit;
$update_fields_edit[] = "is_members_only = :is_members_only"; $params_edit[':is_members_only'] = $db_is_members_only_edit;
// ... (max_participants, suggested_loadout_id) ...


// Fotoğraf İşlemleri (create_event'teki mantığa benzer, ancak mevcut resimleri de dikkate alarak)
// ... (resim silme ve yükleme mantığı burada olacak) ...


if (!empty($update_fields_edit)) {
    $sql_update = "UPDATE events SET " . implode(", ", $update_fields_edit) . " WHERE id = :event_id_update";
    $params_edit[':event_id_update'] = $event_id;

    try {
        $pdo->beginTransaction();
        $stmt_update_event = $pdo->prepare($sql_update);
        $stmt_update_event->execute($params_edit);

        // event_visibility_roles tablosunu güncelle
        // Önce mevcut tüm rolleri sil
        $stmt_delete_event_roles = $pdo->prepare("DELETE FROM event_visibility_roles WHERE event_id = ?");
        $stmt_delete_event_roles->execute([$event_id]);

        // Eğer yeni visibility 'faction_only' ise (yani spesifik roller seçilmişse) ve roller varsa, yenilerini ekle
        if ($db_visibility_enum_edit === 'faction_only' && !empty($assigned_role_ids_from_form_edit)) {
            $stmt_event_role_insert_edit = $pdo->prepare("INSERT INTO event_visibility_roles (event_id, role_id) VALUES (?, ?)");
            foreach ($assigned_role_ids_from_form_edit as $role_id_to_assign_edit) {
                $stmt_event_role_insert_edit->execute([$event_id, $role_id_to_assign_edit]);
            }
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Etkinlik başarıyla güncellendi.";
        header('Location: ' . $baseUrl . '/event_detail.php?id=' . $event_id);
        exit;
    } catch (PDOException $e) {
        // ... (hata işleme) ...
    }
} else {
    $_SESSION['info_message'] = "Güncellenecek bir değişiklik yapılmadı.";
    header('Location: ' . $baseUrl . '/edit_event.php?id=' . $event_id);
    exit;
}
// ... (hata durumunda geri yönlendirme) ...
