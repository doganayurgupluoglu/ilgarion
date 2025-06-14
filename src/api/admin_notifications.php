<?php
// /src/api/admin_notifications.php
header('Content-Type: application/json');

// Yetkilendirme veya diğer kontroller burada yapılabilir.
// Bu dosya eksik olduğu için geçici olarak oluşturuldu.

// Boş bir bildirim dizisi döndürerek 404 hatasını önleyelim.
echo json_encode(['success' => true, 'notifications' => []]); 