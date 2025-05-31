// public/js/notifications_ajax.js
document.addEventListener('DOMContentLoaded', function() {
    const notificationDropdownList = document.getElementById('notificationsDropdown'); 

    if (notificationDropdownList) {
        notificationDropdownList.addEventListener('click', function(event) {
            const notificationItemLink = event.target.closest('.notification-item');
            
            if (notificationItemLink && notificationItemLink.dataset.notificationId) {
                event.preventDefault(); 

                const notificationId = notificationItemLink.dataset.notificationId;
                const targetUrl = notificationItemLink.href; 

                console.log(`[AJAX Debug] Bildirim ID ${notificationId} için okundu işaretleniyor. URL: ${targetUrl}`);

                fetch('/public/actions/mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest' 
                    },
                    body: 'action=mark_read&notification_id=' + encodeURIComponent(notificationId)
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('[AJAX Debug] Sunucu Hata Yanıtı (Text):', text);
                            try {
                                const errData = JSON.parse(text);
                                throw errData; 
                            } catch (e) {
                                throw new Error('Ağ yanıtı başarısız oldu. Durum: ' + response.status + '. Yanıt: ' + text.substring(0, 100));
                            }
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("[AJAX Debug] Sunucudan gelen yanıt:", JSON.stringify(data));
                    if (data.success) {
                        console.log("[AJAX Debug] data.success true.");
                        if (data.marked_as_read_now) {
                            console.log('[AJAX Debug] data.marked_as_read_now true. Badge güncellenecek.');
                            const badge = document.querySelector('#notificationsTriggerBtn .notification-badge');
                            if (badge) {
                                console.log('[AJAX Debug] Badge elementi bulundu. Mevcut değer:', badge.textContent);
                                let count = parseInt(badge.textContent);
                                if (!isNaN(count) && count > 0) {
                                    if (count > 1) {
                                        badge.textContent = count - 1;
                                        console.log('[AJAX Debug] Badge yeni değer:', badge.textContent);
                                    } else {
                                        badge.style.display = 'none'; 
                                        console.log('[AJAX Debug] Badge gizlendi.');
                                    }
                                } else {
                                    console.log('[AJAX Debug] Badge değeri sayı değil veya 0.');
                                }
                            } else {
                                console.log('[AJAX Debug] Badge elementi bulunamadı.');
                            }
                        } else if (data.already_read) {
                            console.log('[AJAX Debug] Bildirim ' + notificationId + ' zaten okunmuştu. Badge güncellenmeyecek.');
                        } else {
                            console.log('[AJAX Debug] data.marked_as_read_now false ve data.already_read false. Badge güncellenmeyecek.');
                        }
                        
                        notificationItemLink.style.opacity = '0.6';
                        notificationItemLink.classList.remove('unread'); 
                        notificationItemLink.classList.add('read'); 
                        console.log("[AJAX Debug] Bildirim öğesi güncellendi. Yönlendiriliyor...");

                        // Test için küçük bir gecikmeyle yönlendirme
                        setTimeout(function() {
                            window.location.href = targetUrl;
                        }, 50); // 50ms gecikme

                    } else {
                        console.error('[AJAX Debug] Bildirim okundu işaretlenemedi (data.success false):', data.error || 'Bilinmeyen sunucu hatası.');
                        window.location.href = targetUrl;
                    }
                })
                .catch(error => {
                    console.error('[AJAX Debug] Fetch hatası (mark_notification_read):', error);
                    window.location.href = targetUrl;
                });
            }
        });
    } else {
        // console.warn("[AJAX Debug] ID'si 'notificationsDropdown' olan bildirim dropdown paneli bulunamadı.");
    }
});
