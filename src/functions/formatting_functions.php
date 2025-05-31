<?php
// src/functions/formatting_functions.php

if (session_status() == PHP_SESSION_NONE) {
    // session_start(); // Genellikle bu dosya zaten session başlatılmış bir yerden çağrılır.
}

// ... (parse_discussion_quotes_from_safe_html ve extract_raw_content_from_quotes fonksiyonları aynı kalacak) ...

/**
 * Kullanıcının avatarını, adını ve rollerini içeren,
 * tıklandığında popover açan bir HTML bloğu oluşturur.
 * Kullanıcı adı, en öncelikli rolünün rengiyle inline stil olarak renklendirilir.
 *
 * @param PDO $pdo Veritabanı bağlantısı (Rol rengini çekmek için eklendi)
 * @param array $user_data Kullanıcı bilgilerini içeren dizi.
 * @param string $link_class Kullanıcı adı linkine eklenecek ek CSS class'ı.
 * @param string $avatar_class Avatar görseline/placeholder'ına eklenecek ek CSS class'ı.
 * @param string $popover_trigger_wrapper_class Popover tetikleyici ana span'e eklenecek ek CSS class'ı.
 * @return string Oluşturulan HTML çıktısı.
 */
function render_user_info_with_popover(PDO $pdo, array $user_data, string $link_class = '', string $avatar_class = 'member-avatar-thumbnail', string $popover_trigger_wrapper_class = ''): string {
    if (empty($user_data['id']) || empty($user_data['username'])) {
        return '';
    }

    $user_id_safe = htmlspecialchars($user_data['id']);
    $username_safe = htmlspecialchars($user_data['username']);
    $avatar_path_full = !empty($user_data['avatar_path']) ? '/public/' . htmlspecialchars($user_data['avatar_path']) : '';
    
    $ingame_name_safe = htmlspecialchars($user_data['ingame_name'] ?? $user_data['creator_ingame_name'] ?? $user_data['author_ingame_name'] ?? $user_data['topic_starter_ingame'] ?? $user_data['last_replier_ingame'] ?? $user_data['participant_ingame_name'] ?? $user_data['uploader_ingame_name'] ?? 'N/A');
    $discord_safe = htmlspecialchars($user_data['discord_username'] ?? $user_data['creator_discord_username'] ?? $user_data['author_discord_username'] ?? $user_data['topic_starter_discord'] ?? $user_data['last_replier_discord'] ?? $user_data['participant_discord_username'] ?? $user_data['uploader_discord_username'] ?? 'N/A');
    $event_count_safe = htmlspecialchars($user_data['user_event_count'] ?? $user_data['creator_event_count'] ?? $user_data['author_event_count'] ?? $user_data['topic_starter_event_count'] ?? $user_data['last_replier_event_count'] ?? $user_data['participant_event_count'] ?? $user_data['uploader_event_count'] ?? '0');
    $gallery_count_safe = htmlspecialchars($user_data['user_gallery_count'] ?? $user_data['creator_gallery_count'] ?? $user_data['author_gallery_count'] ?? $user_data['topic_starter_gallery_count'] ?? $user_data['last_replier_gallery_count'] ?? $user_data['participant_gallery_count'] ?? $user_data['uploader_gallery_count'] ?? '0');
    $roles_list_safe = htmlspecialchars($user_data['user_roles_list'] ?? $user_data['creator_roles_list'] ?? $user_data['author_roles_list'] ?? $user_data['topic_starter_roles_list'] ?? $user_data['last_replier_roles_list'] ?? $user_data['participant_roles_list'] ?? $user_data['uploader_roles_list'] ?? '');

    global $role_priority; 
    $role_priority_local = $role_priority ?? ['admin', 'ilgarion_turanis', 'scg_uye', 'member', 'dis_uye'];

    $username_style = ''; // Inline stil için
    $user_roles_arr = !empty($roles_list_safe) ? explode(',', $roles_list_safe) : [];
    
    $primary_role_name_for_color = null;
    if (!empty($user_roles_arr)) {
        foreach ($role_priority_local as $p_role) {
            if (in_array($p_role, $user_roles_arr)) {
                $primary_role_name_for_color = $p_role;
                break;
            }
        }
        // Eğer öncelikli rollerden biri bulunamazsa ve roller varsa, ilk rolü al (veya hiçbirini)
        if (!$primary_role_name_for_color && !empty($user_roles_arr)) {
             $primary_role_name_for_color = $user_roles_arr[0];
        }
    }

    if ($primary_role_name_for_color) {
        try {
            $stmt_role_color = $pdo->prepare("SELECT color FROM roles WHERE name = ?");
            $stmt_role_color->execute([$primary_role_name_for_color]);
            $role_color_hex = $stmt_role_color->fetchColumn();
            if ($role_color_hex) {
                $username_style = 'style="color: ' . htmlspecialchars($role_color_hex) . ' !important;"';
            }
        } catch (PDOException $e) {
            error_log("Rol rengi çekme hatası (render_user_info_with_popover): " . $e->getMessage());
        }
    }


    $avatar_html = '';
    // ... (mevcut avatar HTML oluşturma kodunuz aynı kalacak) ...
    if (!empty($avatar_path_full)) {
        $avatar_html = '<img src="' . $avatar_path_full . '" alt="' . $username_safe . ' Avatar" class="' . htmlspecialchars($avatar_class) . '">';
    } else {
        $placeholder_class = 'avatar-placeholder';
        if (strpos($avatar_class, 'small') !== false || $avatar_class === 'member-avatar-thumbnail' || $avatar_class === 'creator-avatar-table' || $avatar_class === 'replier-avatar-micro' || $avatar_class === 'uploader-avatar-v2' || $avatar_class === 'author-avatar-guide-card' || $avatar_class === 'creator-avatar-small') {
            $placeholder_class .= ' small-placeholder';
        } elseif (strpos($avatar_class, 'large') !== false || $avatar_class === 'profile-main-avatar' || $avatar_class === 'avatar-placeholder-profile') {
            $placeholder_class .= ' large-placeholder';
        } elseif ($avatar_class === 'author-avatar-post' || $avatar_class === 'starter-avatar-disc-list' || $avatar_class === 'avatar-placeholder-disc-list' || $avatar_class === 'creator-avatar-evd' || $avatar_class === 'avatar-placeholder-evd' || $avatar_class === 'avatar-placeholder-participant-evd' || $avatar_class === 'author-avatar-gd' || $avatar_class === 'avatar-placeholder-gd' || $avatar_class === 'creator-avatar-ld' || $avatar_class === 'avatar-placeholder-ld') {
             $placeholder_class = 'avatar-placeholder-post';
        }
        if ($avatar_class === 'nav-user-avatar' || $avatar_class === 'avatar-placeholder-dropdown') {
            $placeholder_class = 'avatar-placeholder-nav';
        }
        $avatar_html = '<div class="' . htmlspecialchars($placeholder_class) . ' ' . htmlspecialchars($avatar_class) . '">' . strtoupper(substr($username_safe, 0, 1)) . '</div>';
    }


    $output = '<span class="user-info-trigger ' . htmlspecialchars($popover_trigger_wrapper_class) . '"
                     data-user-id="' . $user_id_safe . '"
                     data-username="' . $username_safe . '"
                     data-avatar="' . $avatar_path_full . '"
                     data-ingame="' . $ingame_name_safe . '"
                     data-discord="' . $discord_safe . '"
                     data-event-count="' . $event_count_safe . '"
                     data-gallery-count="' . $gallery_count_safe . '"
                     data-roles="' . $roles_list_safe . '">';
    $output .= $avatar_html;
    // Kullanıcı adı linkine inline stili ekle
    $output .= ' <a href="' . get_auth_base_url() . '/view_profile.php?user_id=' . $user_id_safe . '" class="' . htmlspecialchars($link_class) . '" ' . $username_style . '>' . $username_safe . '</a>';
    $output .= '</span>';

    return $output;
}
