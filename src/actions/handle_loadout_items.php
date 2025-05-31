<?php
// src/actions/handle_loadout_items.php
if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}

ini_set('display_errors', 1); // Debug için açık kalsın
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi

// require_admin();
if (isset($pdo)) { // $pdo'nun tanımlı olduğundan emin olalım
    require_permission($pdo, 'loadout.manage_items');
} else {
    $_SESSION['error_message'] = "Veritabanı bağlantısı yapılandırılamadı.";
    error_log("handle_loadout_items.php: PDO nesnesi bulunamadı.");
    header('Location: ' . get_auth_base_url() . '/admin/manage_loadout_sets.php');
    exit;
}

$baseUrl = get_auth_base_url();
$loggedInUserId = $_SESSION['user_id'];
$redirect_page = $baseUrl . '/admin/manage_loadout_sets.php'; // Varsayılan

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['loadout_set_id'])) {
    $action = $_POST['action'];
    $loadout_set_id = (int)$_POST['loadout_set_id'];
    $redirect_page = $baseUrl . '/admin/edit_loadout_items.php?set_id=' . $loadout_set_id; // Başarı/hata sonrası buraya dön

    if ($action === 'save_loadout_items') {
        $items_data_from_form = $_POST['items'] ?? []; 

        error_log("--- HANDLE LOADOUT ITEMS ---");
        error_log("Set ID: " . $loadout_set_id);
        error_log("Gelen Items Data RAW: " . print_r($items_data_from_form, true));

        if (empty($loadout_set_id)) {
            $_SESSION['error_message'] = "Geçersiz teçhizat seti ID'si.";
            header('Location: ' . $baseUrl . '/admin/manage_loadout_sets.php');
            exit;
        }

        try {
            $pdo->beginTransaction();

            // 1. Bu sete ait mevcut tüm item'ları sil
            $stmt_delete_old = $pdo->prepare("DELETE FROM loadout_set_items WHERE loadout_set_id = ?");
            $stmt_delete_old->execute([$loadout_set_id]);
            $deleted_rows = $stmt_delete_old->rowCount();
            error_log("Eski loadout item'ları silindi. Etkilenen satır: " . $deleted_rows);

            // 2. Formdan gelen yeni item'ları ekle
            $stmt_insert = $pdo->prepare(
                "INSERT INTO loadout_set_items 
                 (loadout_set_id, equipment_slot_id, custom_slot_name, item_name, item_api_uuid, item_type_api, item_manufacturer_api) 
                 VALUES (:loadout_set_id, :equipment_slot_id, :custom_slot_name, :item_name, :item_api_uuid, :item_type_api, :item_manufacturer_api)"
            );

            $inserted_count = 0;
            if (!empty($items_data_from_form)) {
                foreach ($items_data_from_form as $slot_key_from_js => $item_details) {
                    if (empty($item_details['item_name'])) {
                        error_log("Boş item_name geldi, atlanıyor. Slot Key (JS): " . $slot_key_from_js);
                        continue; 
                    }

                    $equipment_slot_id_to_db = null;
                    $custom_slot_name_to_db = null;

                    // slot_id ve custom_slot_name JS'den doğrudan gelmeli
                    if (isset($item_details['slot_id']) && !empty($item_details['slot_id'])) {
                        $equipment_slot_id_to_db = (int)$item_details['slot_id'];
                    } elseif (isset($item_details['custom_slot_name']) && !empty($item_details['custom_slot_name'])) {
                        $custom_slot_name_to_db = trim($item_details['custom_slot_name']);
                    } else {
                        error_log("Kritik: Slot ID veya Özel Slot Adı gelmedi! Slot Key (JS): " . $slot_key_from_js . " Item: " . ($item_details['item_name'] ?? 'İsimsiz'));
                        continue; 
                    }
                    
                    $params_to_insert = [
                        ':loadout_set_id' => $loadout_set_id,
                        ':equipment_slot_id' => $equipment_slot_id_to_db,
                        ':custom_slot_name' => $custom_slot_name_to_db,
                        ':item_name' => trim($item_details['item_name']),
                        ':item_api_uuid' => trim($item_details['item_api_uuid'] ?? null),
                        ':item_type_api' => trim($item_details['item_type_api'] ?? null),
                        ':item_manufacturer_api' => trim($item_details['item_manufacturer_api'] ?? null)
                    ];

                    error_log("INSERT denemesi - Slot Key (JS): " . $slot_key_from_js . " | Params: " . print_r($params_to_insert, true));
                    if ($stmt_insert->execute($params_to_insert)) {
                        $inserted_count++;
                    } else {
                         error_log("INSERT BAŞARISIZ! Slot Key (JS): " . $slot_key_from_js . " | ErrorInfo: " . print_r($stmt_insert->errorInfo(), true));
                    }
                }
            } else {
                error_log("Formdan gelen 'items' dizisi boş veya tanımsız.");
            }

            $pdo->commit();
            if ($inserted_count > 0) {
                $_SESSION['success_message'] = "$inserted_count adet item ataması teçhizat setine başarıyla kaydedildi.";
            } else if (empty($items_data_from_form) && $deleted_rows >= 0) { 
                $_SESSION['info_message'] = "Teçhizat setindeki tüm item'lar kaldırıldı (veya zaten boştu).";
            } else if ($inserted_count === 0 && !empty($items_data_from_form)) {
                $_SESSION['warning_message'] = "Item'lar gönderildi ancak hiçbiri veritabanına kaydedilemedi. Lütfen logları kontrol edin.";
            } else {
                $_SESSION['info_message'] = "Teçhizat setinde bir değişiklik yapılmadı veya kaydedilecek item yoktu.";
            }

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log("Loadout item kaydetme hatası (Set ID: $loadout_set_id): " . $e->getMessage() . " | Gelen Items Data: " . print_r($items_data_from_form, true));
            $_SESSION['error_message'] = "Item'lar kaydedilirken bir veritabanı hatası oluştu. Detaylar loglandı.";
        }
        header('Location: ' . $redirect_page);
        exit;
    } else { /* ... diğer action'lar ... */ }
} else { /* ... geçersiz istek ... */ }
?>
