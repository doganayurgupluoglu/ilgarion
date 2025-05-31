// public/js/notifications.js
document.addEventListener('DOMContentLoaded', function() {
    // const notificationItems = document.querySelectorAll('.notification-item'); // Bu satır artık kullanılmayacak
    const notificationIcon = document.getElementById('notifications-icon'); // Navbar'daki ikon (Bu ID navbar.php'de yok, notificationsTriggerBtn olmalı)
    const notificationBadge = document.querySelector('#notificationsTriggerBtn .notification-badge'); // Güncellenmiş seçici

    // .notification-item öğelerine tıklama olayı artık notifications_ajax.js tarafından yönetiliyor.
    // Bu nedenle aşağıdaki blok kaldırıldı veya yorum satırına alındı.
    /*
    notificationItems.forEach(item => {
        item.addEventListener('click', function(event) {
            event.preventDefault(); // Linkin varsayılan davranışını engelle

            const notificationId = this.dataset.notificationId;
            const targetUrl = this.href; // notif_id parametresini zaten içeriyor

            fetch('/public/actions/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notification_id=' + notificationId + '&action=mark_read'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Bildirim ' + notificationId + ' okundu olarak işaretlendi (notifications.js).');
                    if (notificationBadge) {
                        let currentCount = parseInt(notificationBadge.textContent);
                        if (currentCount > 1) {
                            notificationBadge.textContent = currentCount - 1;
                        } else {
                            notificationBadge.style.display = 'none';
                            const dropdown = document.getElementById('notifications-dropdown-list'); // veya 'notificationsDropdown'
                            if(dropdown) {
                                // ... (dropdown içeriğini güncelleme mantığı) ...
                            }
                        }
                    }
                    this.style.backgroundColor = '#e9ecef'; 
                    this.style.opacity = '0.7'; 
                    setTimeout(() => { window.location.href = targetUrl; }, 200);

                } else {
                    console.error('Bildirim okundu işaretlenemedi (notifications.js):', data.error);
                    window.location.href = targetUrl; 
                }
            })
            .catch(error => {
                console.error('Fetch hatası (notifications.js):', error);
                window.location.href = targetUrl; 
            });
        });
    });
    */

    // Dropdown açıldığında tüm görünen bildirimleri okundu olarak işaretleme (isteğe bağlı özellik)
    // Bu özellik şu anda yorum satırında. Eğer kullanılmak istenirse,
    // notificationsTriggerBtn (navbar.php'de tanımlı olan ID) ile etkileşime geçmeli.
    /*
    const notificationsTriggerButton = document.getElementById('notificationsTriggerBtn'); // Navbar'daki asıl butonun ID'si
    const notificationsDropdownPanel = document.getElementById('notificationsDropdown'); // Açılan panelin ID'si

    if (notificationsTriggerButton && notificationsDropdownPanel && notificationBadge && parseInt(notificationBadge.textContent) > 0) {
        notificationsTriggerButton.addEventListener('click', function() {
            // Bu, dropdown açıldığında tetiklenir.
            // Sadece dropdown gerçekten açılıyorsa (CSS class'ı ile kontrol edilebilir) işlem yapılmalı.
            // Örneğin, dropdown paneli 'open' class'ını alıyorsa:
            // setTimeout(() => { // Dropdown'ın açılmasını beklemek için küçük bir gecikme
            //     if (notificationsDropdownPanel.classList.contains('open')) {
            //         fetch('/public/actions/mark_notification_read.php', {
            //             method: 'POST',
            //             headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            //             body: 'action=mark_all_read' // Sunucu tarafında bu action'ı ele almalısınız
            //         })
            //         .then(response => response.json())
            //         .then(data => {
            //             if (data.success) {
            //                 if (notificationBadge) notificationBadge.style.display = 'none';
            //                 // Dropdown içindeki tüm item'ları okundu olarak işaretle (görsel olarak)
            //                 notificationsDropdownPanel.querySelectorAll('.notification-item.unread').forEach(item => {
            //                     item.style.opacity = '0.6';
            //                     item.classList.remove('unread');
            //                     item.classList.add('read');
            //                 });
            //                 console.log("Dropdown açıldığında tüm bildirimler okundu olarak işaretlendi (AJAX).");
            //             } else {
            //                 console.error("Tüm bildirimler okundu işaretlenemedi:", data.error);
            //             }
            //         })
            //         .catch(error => console.error("Tümünü okundu işaretleme fetch hatası:", error));
            //     }
            // }, 100);
        });
    }
    */
});
