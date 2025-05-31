// public/js/guides.js
document.addEventListener('DOMContentLoaded', function() {
    // Hem rehber listeleme sayfasındaki (.btn-like-guide) 
    // hem de rehber detay sayfasındaki (.btn-like-guide-detail) beğeni butonlarını seç
    const allLikeButtons = document.querySelectorAll('.btn-like-guide, .btn-like-guide-detail');
    
    // Ana beğeni sayısı göstergesi (rehber detay sayfasındaki meta bilgisi için)
    // Bu ID'nin guide_detail.php'deki render_guide_meta_and_actions_detail fonksiyonunda 
    // <span id="likeCountDisplay_top">...</span> şeklinde tanımlandığından emin olun.
    const mainLikeCountDisplayTop = document.getElementById('likeCountDisplay_top');

    allLikeButtons.forEach(likeButton => {
        likeButton.addEventListener('click', function() {
            const currentClickedButton = this;
            const guideId = currentClickedButton.dataset.guideId;

            // Tıklanan butona göre ilgili durum mesajı span'ını ve beğeni sayısı göstergesini bul
            let likeActionStatusSpan = null;
            let likeCountTargetSpan = null; // Kart içindeki veya buton yanındaki sayaç

            if (currentClickedButton.classList.contains('btn-like-guide-detail')) {
                // Rehber Detay Sayfası
                likeActionStatusSpan = document.getElementById('likeActionStatus_top'); // Detay sayfasındaki ID
                // Ana meta bilgisi (mainLikeCountDisplayTop) zaten yukarıda seçildi.
                // Butonun kendi içindeki metni ve ikonu ayrıca güncelleyeceğiz.
            } else if (currentClickedButton.classList.contains('btn-like-guide')) {
                // Rehber Listeleme Sayfası (guides.php) - Kart içindeki yapıya göre
                // Eğer kartlarda ayrı bir durum mesajı span'ı varsa, ID'si veya class'ı ile seçilebilir.
                // Şimdilik, kart içindeki beğeni sayısını güncellemeyi hedefleyelim.
                const cardFooter = currentClickedButton.closest('.guide-card-footer-v2'); // Kart footer'ını bul
                if (cardFooter) {
                    // Karttaki beğeni sayısını gösteren span'i bulmak için daha spesifik bir yol gerekebilir.
                    // Örneğin, '.guide-stats-v2 .stat-item[title="Beğeni"]' gibi.
                    // Şimdilik, butonun kendi metnini güncelleyeceğiz.
                }
            }


            if (!guideId) {
                console.error("Rehber ID bulunamadı (data-guide-id eksik veya boş).");
                if (likeActionStatusSpan) likeActionStatusSpan.textContent = 'Hata: ID eksik.';
                return;
            }

            if (likeActionStatusSpan) likeActionStatusSpan.textContent = 'İşleniyor...';

            fetch('../src/actions/handle_guide_like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'guide_id=' + encodeURIComponent(guideId)
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(errData => { throw errData; })
                                     .catch(() => { throw new Error('Network response was not ok. Status: ' + response.status); });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const newLikeCount = data.like_count;
                    const isNowLiked = data.action_taken === 'liked';

                    // 1. Ana beğeni sayısını güncelle (guide_detail.php'deki meta için)
                    if (mainLikeCountDisplayTop) {
                        // ID'si "likeCountDisplay_top" olan span'in içindeki <strong> etiketini bulup güncelle
                        const strongTag = mainLikeCountDisplayTop.querySelector('strong');
                        if (strongTag) {
                            strongTag.textContent = newLikeCount;
                        } else {
                            // Fallback, eğer strong yoksa doğrudan span'i güncelle (ama HTML yapısına uymalı)
                            mainLikeCountDisplayTop.innerHTML = `<i class="fas fa-thumbs-up meta-icon"></i>Beğeni: <strong>${newLikeCount}</strong>`;
                        }
                    }

                    // 2. Tıklanan butonun görünümünü güncelle
                    const likeIconSpan = currentClickedButton.querySelector('.like-icon'); // İkonu içeren span
                    const likeTextSpan = currentClickedButton.querySelector('.like-text'); // Metni içeren span (guide_detail için)
                    
                    if (isNowLiked) {
                        currentClickedButton.classList.remove('btn-success'); // Eğer btn-success varsa kaldır
                        currentClickedButton.classList.add('liked', 'btn-danger'); // 'liked' ve 'btn-danger' ekle
                        currentClickedButton.title = 'Beğenmekten Vazgeç';
                        if (likeIconSpan) likeIconSpan.innerHTML = `<i class="fas fa-heart-crack"></i>`;
                        if (likeTextSpan) likeTextSpan.textContent = ' Vazgeç';
                    } else { // unliked
                        currentClickedButton.classList.remove('liked', 'btn-danger');
                        currentClickedButton.classList.add('btn-success'); // Beğen butonu için varsayılan class
                        currentClickedButton.title = 'Beğen';
                        if (likeIconSpan) likeIconSpan.innerHTML = `<i class="fas fa-heart"></i>`;
                        if (likeTextSpan) likeTextSpan.textContent = ' Beğen';
                    }
                    
                    // 3. Tıklanan butona özel durum mesajını ayarla (guide_detail için)
                    if (likeActionStatusSpan) {
                        likeActionStatusSpan.textContent = isNowLiked ? 'Beğenildi!' : 'Beğeni geri alındı.';
                        setTimeout(() => { likeActionStatusSpan.textContent = ''; }, 3000);
                    }

                } else {
                    if (likeActionStatusSpan) likeActionStatusSpan.textContent = 'Hata: ' + (data.error || 'Bilinmeyen sorun.');
                    console.error("Beğeni işlemi başarısız:", data.error);
                    if (likeActionStatusSpan) setTimeout(() => { likeActionStatusSpan.textContent = ''; }, 3000);
                }
            })
            .catch(error => {
                let errorMessage = "Bir ağ hatası oluştu.";
                if (error && typeof error === 'object' && error.error) { errorMessage = error.error; } 
                else if (error instanceof Error) { errorMessage = error.message; }
                
                if (likeActionStatusSpan) likeActionStatusSpan.textContent = 'Hata: ' + errorMessage;
                console.error('Fetch Hatası (Beğeni):', error);
                if (likeActionStatusSpan) setTimeout(() => { likeActionStatusSpan.textContent = ''; }, 3000);
            });
        });
    });
});
