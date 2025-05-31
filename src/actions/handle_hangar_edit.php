<?php
// src/actions/handle_hangar_edit.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hata gösterimini geliştirme için açabilirsin, canlıda kapat veya logla
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once dirname(__DIR__) . '/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php'; // Auth fonksiyonları
require_once BASE_PATH . '/src/functions/role_functions.php'; // Rol fonksiyonları eklendi

require_approved_user(); // Sadece onaylanmış kullanıcılar hangarını düzenleyebilir
// Ek olarak hangar.edit_own yetkisini de kontrol et
if (isset($pdo)) {
    require_permission($pdo, 'hangar.edit_own');
} else {
    $_SESSION['error_message'] = "Veritabanı bağlantısı yapılandırılamadı (hangar yetki kontrolü).";
    error_log("handle_hangar_edit.php: PDO nesnesi bulunamadı (yetki kontrolü).");
    header('Location: ' . get_auth_base_url() . '/index.php'); // Genel bir sayfaya yönlendir
    exit;
}

$baseUrl = get_auth_base_url(); // Yönlendirmeler için /public (auth_functions.php'den)
$user_id = $_SESSION['user_id'];
$redirect_page = $baseUrl . '/edit_hangar.php'; // Varsayılan dönüş sayfası

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_hangar_quantities') { // Sadece adet güncelleniyor, notlar kaldırıldı
        if (isset($_POST['hangar_item_ids']) && is_array($_POST['hangar_item_ids'])) {
            $item_ids = $_POST['hangar_item_ids'];
            $quantities = $_POST['quantities'] ?? [];
            $updated_count = 0;
            $deleted_count = 0;

            try {
                $pdo->beginTransaction();

                for ($i = 0; $i < count($item_ids); $i++) {
                    $hangar_item_id = (int)$item_ids[$i];
                    $quantity = isset($quantities[$i]) ? (int)$quantities[$i] : 0;

                    if ($quantity <= 0) { // Adet 0 veya daha az ise sil
                        $stmt_delete = $pdo->prepare("DELETE FROM user_hangar WHERE id = ? AND user_id = ?");
                        $stmt_delete->execute([$hangar_item_id, $user_id]);
                        if ($stmt_delete->rowCount() > 0) $deleted_count++;
                    } else { // Adet 0'dan büyükse güncelle (sadece quantity)
                        $stmt_update = $pdo->prepare("UPDATE user_hangar SET quantity = ? WHERE id = ? AND user_id = ?");
                        $stmt_update->execute([$quantity, $hangar_item_id, $user_id]);
                        if ($stmt_update->rowCount() > 0) $updated_count++;
                    }
                }
                $pdo->commit();
                
                $messages = [];
                if ($updated_count > 0) $messages[] = "$updated_count geminin adedi güncellendi";
                if ($deleted_count > 0) $messages[] = "$deleted_count gemi hangardan silindi";

                if (!empty($messages)) {
                    $_SESSION['success_message'] = "Hangar başarıyla güncellendi: " . implode(', ', $messages) . ".";
                } else {
                    $_SESSION['info_message'] = "Hangarda herhangi bir değişiklik yapılmadı.";
                }

            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Hangar (mevcut) güncelleme hatası: " . $e->getMessage());
                $_SESSION['error_message'] = "Hangar güncellenirken bir veritabanı hatası oluştu.";
            }
        } else {
            $_SESSION['info_message'] = "Güncellenecek hangar öğesi bulunamadı.";
        }
        header('Location: ' . $redirect_page);
        exit;

    } elseif ($action === 'add_basket_to_hangar') {
        if (isset($_POST['selected_ships']) && is_array($_POST['selected_ships'])) {
            $basket_ships_data = $_POST['selected_ships'];
            $processed_count = 0; // Hem eklenen hem güncellenenler
            $error_ships_log = [];

            if (empty($basket_ships_data)) {
                $_SESSION['info_message'] = "Sepette eklenecek gemi bulunmuyor.";
                header('Location: ' . $redirect_page);
                exit;
            }

            try {
                $pdo->beginTransaction();
                
                // Gemi ekleme/güncelleme için hazırlıklı sorgu (user_notes kaldırıldı)
                $stmt_upsert = $pdo->prepare(
                    "INSERT INTO user_hangar 
                        (user_id, ship_api_id, ship_name, ship_manufacturer, ship_focus, ship_size, ship_image_url, quantity) 
                     VALUES 
                        (:user_id, :ship_api_id, :ship_name, :ship_manufacturer, :ship_focus, :ship_size, :ship_image_url, :quantity)
                     ON DUPLICATE KEY UPDATE 
                        quantity = quantity + VALUES(quantity)" // Sadece adedi artır
                );

                foreach ($basket_ships_data as $ship_api_id_from_key => $ship_details) {
                    // JavaScript'ten gelen veride geminin ana ID'si 'id' anahtarında olmalı
                    $api_id = $ship_details['id'] ?? $ship_api_id_from_key; 
                    $name = $ship_details['name'] ?? 'Bilinmiyor';
                    $manufacturer = $ship_details['manufacturer'] ?? null;
                    $focus = $ship_details['focus'] ?? null;
                    $size = $ship_details['size'] ?? null;
                    $image_url = $ship_details['image_url'] ?? null;
                    $quantity = isset($ship_details['quantity']) ? (int)$ship_details['quantity'] : 1;
                    // $notes kaldırıldı

                    if (empty($api_id) || empty($name) || $quantity < 1) {
                        $error_ships_log[] = $name . " (geçersiz veri, atlandı)";
                        continue; 
                    }

                    $params = [
                        ':user_id' => $user_id,
                        ':ship_api_id' => $api_id,
                        ':ship_name' => $name,
                        ':ship_manufacturer' => $manufacturer,
                        ':ship_focus' => $focus,
                        ':ship_size' => $size,
                        ':ship_image_url' => $image_url,
                        ':quantity' => $quantity
                        // ':user_notes' kaldırıldı
                    ];
                    
                    if ($stmt_upsert->execute($params)) {
                        if ($stmt_upsert->rowCount() > 0) {
                            $processed_count++;
                        }
                    } else {
                        $error_ships_log[] = $name . " (DB hatası)";
                        error_log("Hangar sepet ekleme hatası (execute false): API ID " . $api_id . " - Error: " . print_r($stmt_upsert->errorInfo(), true));
                    }
                }
                $pdo->commit();

                $messages = [];
                if ($processed_count > 0) $messages[] = "$processed_count gemi hangara eklendi/adedi güncellendi";
                if (!empty($error_ships_log)) $messages[] = "Bazı gemiler işlenemedi: " . implode(', ', $error_ships_log);
                
                if ($processed_count > 0 && empty($error_ships_log)) {
                    $_SESSION['success_message'] = implode('. ', $messages) . ".";
                } elseif ($processed_count > 0 && !empty($error_ships_log)) {
                    $_SESSION['warning_message'] = implode('. ', $messages) . ". Lütfen kontrol edin.";
                } elseif (empty($processed_count) && !empty($error_ships_log)) {
                     $_SESSION['error_message'] = implode('. ', $messages) . ".";
                } else { 
                    $_SESSION['info_message'] = "Sepetten hangara eklenecek/güncellenecek gemi bulunamadı veya bir değişiklik yapılmadı.";
                }

            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Sepetten hangar ekleme - genel DB hatası: " . $e->getMessage());
                $_SESSION['error_message'] = "Gemiler hangara eklenirken bir veritabanı hatası oluştu.";
            }
        } else {
            $_SESSION['info_message'] = "Hangara eklenecek gemi seçilmedi (sepet boş).";
        }
        header('Location: ' . $redirect_page);
        exit;
    } else {
        $_SESSION['error_message'] = "Geçersiz hangar işlemi: " . htmlspecialchars($action);
        header('Location: ' . $redirect_page);
        exit;
    }
} else {
    $_SESSION['error_message'] = "Geçersiz erişim denemesi.";
    header('Location: ' . $baseUrl . '/index.php'); //
    exit;
}
?>
