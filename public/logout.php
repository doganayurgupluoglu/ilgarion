<?php
// public/logout.php
require_once '../src/config/database.php'; // BASE_PATH için
require_once BASE_PATH . '/src/functions/auth_functions.php'; // logout_user fonksiyonu için

logout_user("Başarıyla çıkış yaptınız."); // Mesajla çıkış yap
?>