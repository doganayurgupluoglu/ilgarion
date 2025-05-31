<?php
// src/functions/guide_functions.php

if (session_status() == PHP_SESSION_NONE) {
    // session_start(); 
}

// Parsedown.php dosyasının tam yolunu oluştur
$parsedown_path = __DIR__ . '/Parsedown.php'; 

if (!file_exists($parsedown_path)) {
    error_log("Parsedown HATA: Dosya bulunamadı - Beklenen yol: " . $parsedown_path);
} elseif (!is_readable($parsedown_path)) {
    error_log("Parsedown HATA: Dosya okunamıyor - Yol: " . $parsedown_path);
} else {
    require_once $parsedown_path; 
}

/**
 * Verilen başlığı URL dostu bir slug'a dönüştürür.
 */
function generate_slug(string $title): string {
    $slug = mb_strtolower($title, 'UTF-8');
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = str_replace(
        ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', '.', ',', '!', '?', '\'', '"', '(', ')', '[', ']', '{', '}', ':', ';'],
        ['i', 'g', 'u', 's', 'o', 'c', '', '', '', '', '', '', '', '', '', '', '', '', ''],
        $slug
    );
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    if (empty($slug)) {
        $slug = 'rehber-' . time(); // Benzersizlik için time() kullanılıyor, daha iyi bir yöntem düşünülebilir.
    }
    return $slug;
}

/**
 * Verilen başlık için veritabanında benzersiz bir slug üretir.
 * Eğer üretilen slug zaten varsa, sonuna -1, -2 gibi ekler yapar.
 */
function get_unique_slug(PDO $pdo, string $title, ?int $current_guide_id = null): string {
    $slug = generate_slug($title);
    $original_slug = $slug;
    $counter = 1;
    
    $sql = "SELECT COUNT(*) FROM guides WHERE slug = :slug";
    if ($current_guide_id !== null) {
        // Güncelleme durumunda, mevcut rehberin kendi slug'ını kontrol dışı bırak
        $sql .= " AND id != :current_guide_id";
    }
    $stmt = $pdo->prepare($sql);
    
    $params_check = [':slug' => $slug];
    if ($current_guide_id !== null) {
        $params_check[':current_guide_id'] = $current_guide_id;
    }
    $stmt->execute($params_check);

    while ($stmt->fetchColumn() > 0) {
        $slug = $original_slug . '-' . $counter;
        $counter++;
        $params_check[':slug'] = $slug; // Parametreyi güncelle
        $stmt->execute($params_check); // Güncellenmiş parametre ile tekrar çalıştır
    }
    return $slug;
}


/**
 * Verilen Markdown metnini HTML'e çevirir.
 * @param string $markdown_text İşlenecek Markdown metni.
 * @return string HTML'e dönüştürülmüş metin veya hata durumunda mesaj.
 */
function convert_markdown_to_html_guide(string $markdown_text): string {
    if (!class_exists('Parsedown')) { 
        $path_check = __DIR__ . '/Parsedown.php';
        error_log("Parsedown SINIF HATA: 'Parsedown' sınıfı bulunamadı. Parsedown.php dosyasının '$path_check' yolunda olduğundan ve doğru (namespaceli olmayan) bir sürüm olduğundan emin olun.");
        return "<p style='color:red; font-weight:bold;'>Markdown işleyici sınıfı yüklenemedi! Lütfen site yöneticisi ile iletişime geçin.</p><pre>Debug Info: Parsedown.php yolu: " . htmlspecialchars($path_check) . "</pre>";
    }

    try {
        $Parsedown = new Parsedown(); 
        $Parsedown->setSafeMode(true); // GÜVENLİK: Safe Modu etkinleştir
        $Parsedown->setBreaksEnabled(true); 
        return $Parsedown->text($markdown_text);
    } catch (Throwable $t) { 
        error_log("Parsedown METOD HATA (convert_markdown_to_html_guide): text() metodu çağrılırken hata oluştu: " . $t->getMessage() . " Markdown: " . substr($markdown_text, 0, 200));
        return "<p style='color:red; font-weight:bold;'>Markdown içeriği işlenirken bir hata oluştu.</p><pre>Markdown Başlangıcı: " . htmlspecialchars(substr($markdown_text, 0, 200)) . "\n...\nHata: " . htmlspecialchars($t->getMessage()) . "</pre>";
    }
}

/**
 * Markdown metninden belirli bir uzunlukta HTML tag'lerinden arındırılmış özet alır.
 */
function get_excerpt_from_markdown(string $markdown_text, int $length = 550): string {
    // ... (fonksiyonun geri kalanı aynı) ...
    if (empty(trim($markdown_text))) {
        return "";
    }
    $html_content = "";
    if (class_exists('Parsedown')) {
        $html_content = convert_markdown_to_html_guide($markdown_text);
        if (strpos($html_content, "Markdown işleyici") !== false) {
            $html_content = $markdown_text; 
        }
    } else {
        $html_content = $markdown_text; 
    }
    $text_content = strip_tags($html_content);
    $text_content = str_replace(["\n", "\r"], ' ', $text_content);
    $text_content = preg_replace('/\s+/', ' ', $text_content);
    $text_content = trim($text_content);
    if (mb_strlen($text_content, 'UTF-8') > $length) {
        return mb_substr($text_content, 0, $length, 'UTF-8') . '...';
    }
    return $text_content;
}
?>