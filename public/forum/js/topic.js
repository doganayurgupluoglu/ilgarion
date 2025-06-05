// public/forum/js/topic.js - Tüm sorunları çözülmüş versiyon

let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

document.addEventListener('DOMContentLoaded', function() {
    // CSRF token'ı sayfadan al
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    if (csrfInput) {
        csrfToken = csrfInput.value;
    }

    const replyContent = document.getElementById('reply_content');
    const charCount = document.getElementById('char-count');
    
    if (replyContent && charCount) {
        replyContent.addEventListener('input', function() {
            charCount.textContent = this.value.length;
            
            if (this.value.length > 9500) {
                charCount.style.color = 'var(--red)';
            } else if (this.value.length > 8000) {
                charCount.style.color = 'var(--gold)';
            } else {
                charCount.style.color = 'var(--light-grey)';
            }
        });
    }

    // Color picker için input oluştur
    createColorPicker();
});

// Color picker oluştur
function createColorPicker() {
    const colorInput = document.createElement('input');
    colorInput.type = 'color';
    colorInput.id = 'color-picker';
    colorInput.style.display = 'none';
    colorInput.addEventListener('change', function() {
        insertBBCode('color', this.value);
    });
    document.body.appendChild(colorInput);
}

// BBCode editor functions - Geliştirilmiş versiyon
function insertBBCode(tag, value = null) {
    const textarea = document.getElementById('reply_content');
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    
    let replacement;
    
    switch(tag) {
        case 'url':
            const url = prompt('URL girin:');
            if (url) {
                replacement = selectedText ? `[url=${url}]${selectedText}[/url]` : `[url=${url}][/url]`;
            } else {
                return;
            }
            break;
        case 'img':
            const imgUrl = prompt('Resim URL\'sini girin:');
            if (imgUrl) {
                replacement = `[img]${imgUrl}[/img]`;
            } else {
                return;
            }
            break;
        case 'color':
            const color = value || document.getElementById('color-picker').value || '#bd912a';
            replacement = selectedText ? `[color=${color}]${selectedText}[/color]` : `[color=${color}][/color]`;
            break;
        default:
            replacement = selectedText ? `[${tag}]${selectedText}[/${tag}]` : `[${tag}][/${tag}]`;
    }
    
    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    
    // Cursor'u doğru pozisyona taşı
    let newPos;
    if (selectedText) {
        newPos = start + replacement.length;
    } else {
        const closingTag = tag === 'img' ? '' : `[/${tag}]`;
        newPos = start + replacement.length - closingTag.length;
    }
    
    textarea.setSelectionRange(newPos, newPos);
    textarea.focus();
    
    // Karakter sayısını güncelle
    const event = new Event('input');
    textarea.dispatchEvent(event);
}

// Renk seçici aç
function openColorPicker() {
    document.getElementById('color-picker').click();
}

// Quote post function - Tamamen düzeltilmiş
function quotePost(username, content, postElement) {
    const textarea = document.getElementById('reply_content');
    if (!textarea) return;
    
    let cleanContent = content;
    
    // Eğer element verilmişse, ondan temiz metin çıkar
    if (postElement) {
        const postItem = postElement.closest('.post-item');
        if (postItem) {
            const postBody = postItem.querySelector('.post-body');
            if (postBody) {
                // Clone yaparak orijinali bozmayalım
                const clone = postBody.cloneNode(true);
                
                // BBCode elementlerini temizle
                const quotes = clone.querySelectorAll('blockquote');
                quotes.forEach(quote => quote.remove());
                
                const codes = clone.querySelectorAll('pre');
                codes.forEach(code => code.remove());
                
                const images = clone.querySelectorAll('img');
                images.forEach(img => img.remove());
                
                // Temiz metin al
                cleanContent = clone.textContent || clone.innerText || '';
                cleanContent = cleanContent.replace(/\s+/g, ' ').trim();
            }
        }
    } else {
        // Element yoksa string'i temizle
        cleanContent = content.replace(/\[quote.*?\].*?\[\/quote\]/gi, '');
        cleanContent = cleanContent.replace(/\[code\].*?\[\/code\]/gi, '');
        cleanContent = cleanContent.replace(/\[img\].*?\[\/img\]/gi, '');
        cleanContent = cleanContent.replace(/\s+/g, ' ').trim();
    }
    
    // İçeriği kısalt
    const maxLength = 200;
    if (cleanContent.length > maxLength) {
        cleanContent = cleanContent.substring(0, maxLength) + '...';
    }
    
    const quote = `[quote=${username}]${cleanContent}[/quote]\n\n`;
    
    // Cursor pozisyonunu al
    const cursorPos = textarea.selectionStart || textarea.value.length;
    const beforeText = textarea.value.substring(0, cursorPos);
    const afterText = textarea.value.substring(cursorPos);
    
    // Quote'u ekle
    textarea.value = beforeText + quote + afterText;
    
    // Cursor'u quote'un sonuna taşı
    const newPos = cursorPos + quote.length;
    textarea.setSelectionRange(newPos, newPos);
    textarea.focus();
    
    // Reply form'a scroll yap
    document.getElementById('reply-form').scrollIntoView({ behavior: 'smooth' });
    
    // Karakter sayısını güncelle
    const event = new Event('input');
    textarea.dispatchEvent(event);
}

// Preview reply function - Geliştirilmiş
function previewReply() {
    const content = document.getElementById('reply_content').value;
    const previewDiv = document.getElementById('reply-preview');
    const previewContent = previewDiv.querySelector('.preview-content');
    
    if (!content.trim()) {
        showNotification('Önizlemek için içerik girin.', 'warning');
        return;
    }
    
    // Gelişmiş BBCode to HTML conversion
    let html = content
        .replace(/\[b\](.*?)\[\/b\]/gi, '<strong>$1</strong>')
        .replace(/\[i\](.*?)\[\/i\]/gi, '<em>$1</em>')
        .replace(/\[u\](.*?)\[\/u\]/gi, '<u>$1</u>')
        .replace(/\[s\](.*?)\[\/s\]/gi, '<del>$1</del>')
        .replace(/\[url=(.*?)\](.*?)\[\/url\]/gi, '<a href="$1" target="_blank" rel="noopener">$2</a>')
        .replace(/\[url\](.*?)\[\/url\]/gi, '<a href="$1" target="_blank" rel="noopener">$1</a>')
        .replace(/\[img\](.*?)\[\/img\]/gi, '<img src="$1" alt="User Image" style="max-width: 100%; height: auto; border-radius: 4px;">')
        .replace(/\[color=(#?[a-fA-F0-9]{3,6}|[a-zA-Z]+)\](.*?)\[\/color\]/gi, '<span style="color: $1;">$2</span>')
        .replace(/\[quote=(.*?)\](.*?)\[\/quote\]/gi, '<blockquote class="forum-quote"><cite>$1 yazdı:</cite>$2</blockquote>')
        .replace(/\[quote\](.*?)\[\/quote\]/gi, '<blockquote class="forum-quote">$1</blockquote>')
        .replace(/\[code\](.*?)\[\/code\]/gi, '<pre class="forum-code">$1</pre>')
        .replace(/\n/g, '<br>');
    
    previewContent.innerHTML = html;
    previewDiv.style.display = 'block';
    previewDiv.scrollIntoView({ behavior: 'smooth' });
}

// Toggle topic pin - Düzeltilmiş
async function toggleTopicPin(topicId, pinned) {
    if (!csrfToken) {
        showNotification('CSRF token bulunamadı. Sayfayı yenileyin.', 'error');
        return;
    }

    try {
        const response = await fetch('/public/forum/actions/toggle_topic_pin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `csrf_token=${encodeURIComponent(csrfToken)}&topic_id=${topicId}&pinned=${pinned}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            const btn = document.querySelector('.btn-pin');
            if (btn) {
                if (data.pinned) {
                    btn.classList.add('active');
                    btn.innerHTML = '<i class="fas fa-thumbtack"></i> Sabitlemeyi Kaldır';
                } else {
                    btn.classList.remove('active');
                    btn.innerHTML = '<i class="fas fa-thumbtack"></i> Sabitle';
                }
                btn.onclick = () => toggleTopicPin(topicId, !data.pinned);
            }
            
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Pin toggle error:', error);
        showNotification('Bir hata oluştu.', 'error');
    }
}

// Toggle topic lock - Düzeltilmiş
async function toggleTopicLock(topicId, locked) {
    if (!csrfToken) {
        showNotification('CSRF token bulunamadı. Sayfayı yenileyin.', 'error');
        return;
    }

    try {
        const response = await fetch('/public/forum/actions/toggle_topic_lock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `csrf_token=${encodeURIComponent(csrfToken)}&topic_id=${topicId}&locked=${locked}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            const btn = document.querySelector('.btn-lock');
            if (btn) {
                if (data.locked) {
                    btn.classList.add('active');
                    btn.innerHTML = '<i class="fas fa-lock"></i> Kilidi Aç';
                } else {
                    btn.classList.remove('active');
                    btn.innerHTML = '<i class="fas fa-lock"></i> Kilitle';
                }
                btn.onclick = () => toggleTopicLock(topicId, !data.locked);
            }
            
            showNotification(data.message, 'success');
            
            // Konu kilitliyse sayfayı yenile
            if (data.locked) {
                setTimeout(() => location.reload(), 1000);
            }
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Lock toggle error:', error);
        showNotification('Bir hata oluştu.', 'error');
    }
}

// Toggle post like - Tamamen düzeltilmiş
async function togglePostLike(postId) {
    if (!csrfToken) {
        showNotification('CSRF token bulunamadı. Sayfayı yenileyin.', 'error');
        return;
    }

    const btn = document.querySelector(`[data-post-id="${postId}"]`);
    if (!btn) {
        showNotification('Beğeni butonu bulunamadı.', 'error');
        return;
    }

    // Button'u geçici olarak devre dışı bırak
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span class="like-count">...</span>';

    try {
        const response = await fetch('/public/forum/actions/toggle_post_like.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `csrf_token=${encodeURIComponent(csrfToken)}&post_id=${postId}`
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            const likeCount = btn.querySelector('.like-count');
            
            if (data.liked) {
                btn.classList.add('liked');
            } else {
                btn.classList.remove('liked');
            }
            
            if (likeCount) {
                likeCount.textContent = data.like_count;
            }
            
            // Button'u tekrar etkinleştir
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-heart"></i> <span class="like-count">${data.like_count}</span>`;
            
            showNotification(data.message, 'success');
        } else {
            btn.disabled = false;
            btn.innerHTML = originalText;
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Like toggle error:', error);
        btn.disabled = false;
        btn.innerHTML = originalText;
        showNotification('Beğeni işlemi başarısız oldu: ' + error.message, 'error');
    }
}

// Yorum ekleme fonksiyonu - Dinamik ekleme için
function addPostToPage(postData) {
    const postsList = document.querySelector('.posts-list');
    if (!postsList) return;

    const postHtml = `
        <div class="post-item" id="post-${postData.id}">
            <div class="post-author">
                <div class="author-avatar">
                    <img src="${postData.avatar_path}" alt="${postData.username} Avatarı" class="avatar-img">
                </div>
                <div class="author-info">
                    <div class="author-name">
                        <span class="user-link" data-user-id="${postData.user_id}" style="color: ${postData.role_color}">
                            ${postData.username}
                        </span>
                    </div>
                    <div class="author-role" style="color: ${postData.role_color}">
                        ${postData.role_name}
                    </div>
                    <div class="author-join-date">
                        Üyelik: ${postData.join_date}
                    </div>
                </div>
            </div>
            <div class="post-content">
                <div class="post-body">
                    ${postData.content_html}
                </div>
                <div class="post-footer">
                    <div class="post-date">
                        <i class="fas fa-clock"></i>
                        Az önce
                    </div>
                    <div class="post-reactions">
                        <button class="post-like-btn" onclick="togglePostLike(${postData.id})" data-post-id="${postData.id}">
                            <i class="fas fa-heart"></i>
                            <span class="like-count">0</span>
                        </button>
                    </div>
                    <div class="post-actions">
                        <button class="post-action-btn" onclick="editPost(${postData.id}, 'post')">
                            <i class="fas fa-edit"></i> Düzenle
                        </button>
                        <button class="post-action-btn" onclick="quotePost('${postData.username}', '${postData.content_text}', this)">
                            <i class="fas fa-quote-left"></i> Alıntıla
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    postsList.insertAdjacentHTML('beforeend', postHtml);
    
    // Yeni yoruma scroll yap
    const newPost = document.getElementById(`post-${postData.id}`);
    if (newPost) {
        newPost.scrollIntoView({ behavior: 'smooth' });
    }
}

// Yorum gönderme - Tamamen yeniden yazılmış
document.addEventListener('DOMContentLoaded', function() {
    const replyForm = document.getElementById('replyForm');
    
    if (replyForm) {
        replyForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            const topicId = this.querySelector('input[name="topic_id"]').value;
            const currentPage = parseInt(new URLSearchParams(window.location.search).get('page')) || 1;
            const postsPerPage = 10;
            const currentPostCount = document.querySelectorAll('.post-item').length - 1; // İlk post hariç
            const totalPages = Math.ceil((currentPostCount + 1) / postsPerPage); // +1 yeni yorum için
            
            // Button'u devre dışı bırak
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';
            
            try {
                const formData = new FormData(this);
                
                // Pagination yoksa veya son sayfadaysak dinamik ekleme yap
                const pagination = document.querySelector('.forum-pagination');
                const isLastPage = !pagination || currentPage === totalPages;
                const canAddDynamic = isLastPage && currentPostCount < postsPerPage;
                
                if (canAddDynamic) {
                    formData.append('add_dynamic', 'true');
                }
                
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    
                    // Form'u temizle
                    document.getElementById('reply_content').value = '';
                    const charCount = document.getElementById('char-count');
                    if (charCount) {
                        charCount.textContent = '0';
                        charCount.style.color = 'var(--light-grey)';
                    }
                    
                    // Preview'u gizle
                    const preview = document.getElementById('reply-preview');
                    if (preview) {
                        preview.style.display = 'none';
                    }
                    
                    // Dinamik ekleme yapabiliyorsak ve veri varsa
                    if (canAddDynamic && data.post_data) {
                        addPostToPage(data.post_data);
                        
                        // Yanıt sayısını güncelle
                        const postsCount = document.querySelector('.posts-count');
                        if (postsCount) {
                            const currentCount = parseInt(postsCount.textContent.match(/\d+/)[0]);
                            postsCount.textContent = `${currentCount + 1} yanıt`;
                        }
                        
                        // Posts header'ı göster (eğer yoksa)
                        let postsHeader = document.querySelector('.posts-header');
                        if (!postsHeader && document.querySelector('.posts-list')) {
                            const postsList = document.querySelector('.posts-list');
                            postsHeader = document.createElement('div');
                            postsHeader.className = 'posts-header';
                            postsHeader.innerHTML = `
                                <h3><i class="fas fa-comments"></i> Yanıtlar</h3>
                                <div class="posts-count">1 yanıt</div>
                            `;
                            postsList.insertBefore(postsHeader, postsList.firstChild);
                        }
                        
                        // Eğer ilk yorum ise posts-list oluştur
                        if (!document.querySelector('.posts-list')) {
                            const postsListHtml = `
                                <div class="posts-list">
                                    <div class="posts-header">
                                        <h3><i class="fas fa-comments"></i> Yanıtlar</h3>
                                        <div class="posts-count">1 yanıt</div>
                                    </div>
                                </div>
                            `;
                            const topicContent = document.querySelector('.topic-content-wrapper');
                            topicContent.insertAdjacentHTML('afterend', postsListHtml);
                            addPostToPage(data.post_data);
                        }
                    } else {
                        // Son sayfaya git
                        setTimeout(() => {
                            window.location.href = data.redirect_url;
                        }, 1000);
                    }
                    
                } else {
                    showNotification(data.message, 'error');
                }
            } catch (error) {
                console.error('Reply submission error:', error);
                showNotification('Yanıt gönderilirken bir hata oluştu: ' + error.message, 'error');
            } finally {
                // Button'u tekrar aktif et
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
});

// Silme fonksiyonları - Gerçek implementasyon
function confirmTopicDelete(topicId) {
    if (confirm('Bu konuyu silmek istediğinizden emin misiniz? Bu işlem geri alınamaz ve tüm yanıtlar da silinecektir.')) {
        deleteTopicAction(topicId);
    }
}

function confirmPostDelete(postId) {
    if (confirm('Bu gönderiyi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
        deletePostAction(postId);
    }
}

// Konu silme action
async function deleteTopicAction(topicId) {
    if (!csrfToken) {
        showNotification('CSRF token bulunamadı. Sayfayı yenileyin.', 'error');
        return;
    }

    try {
        const response = await fetch('/public/forum/actions/delete_topic.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `csrf_token=${encodeURIComponent(csrfToken)}&topic_id=${topicId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Kategori sayfasına yönlendir
            setTimeout(() => {
                window.location.href = `/public/forum/category.php?slug=${data.category_slug}`;
            }, 2000);
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Topic delete error:', error);
        showNotification('Konu silinirken bir hata oluştu.', 'error');
    }
}

// Yorum silme action
async function deletePostAction(postId) {
    if (!csrfToken) {
        showNotification('CSRF token bulunamadı. Sayfayı yenileyin.', 'error');
        return;
    }

    const postElement = document.getElementById(`post-${postId}`);
    
    try {
        const response = await fetch('/public/forum/actions/delete_post.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `csrf_token=${encodeURIComponent(csrfToken)}&post_id=${postId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Gönderiyi sayfadan kaldır
            if (postElement) {
                postElement.style.opacity = '0.5';
                postElement.style.transform = 'translateX(-20px)';
                
                setTimeout(() => {
                    postElement.remove();
                    
                    // Yanıt sayısını güncelle
                    const postsCount = document.querySelector('.posts-count');
                    if (postsCount) {
                        const currentCount = parseInt(postsCount.textContent.match(/\d+/)[0]);
                        if (currentCount > 1) {
                            postsCount.textContent = `${currentCount - 1} yanıt`;
                        } else {
                            // Son yorum silindiyse yanıtlar bölümünü gizle
                            const postsList = document.querySelector('.posts-list');
                            if (postsList) {
                                postsList.remove();
                            }
                        }
                    }
                }, 500);
            }
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Post delete error:', error);
        showNotification('Gönderi silinirken bir hata oluştu.', 'error');
    }
}

function editPost(postId, type) {
    if (type === 'topic') {
        window.location.href = `/public/forum/edit_topic.php?id=${getTopicIdFromUrl()}`;
    } else {
        window.location.href = `/public/forum/edit_post.php?id=${postId}`;
    }
}

// URL'den topic ID'sini al
function getTopicIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

// Notification function - Geliştirilmiş
function showNotification(message, type = 'info') {
    // Mevcut notification sistemini kullan
    if (window.forumManager && typeof window.forumManager.showNotification === 'function') {
        window.forumManager.showNotification(message, type);
        return;
    }
    
    // Basit fallback notification sistemi
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 6px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
        cursor: pointer;
    `;
    
    // Tip'e göre renk ayarla
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    
    notification.style.backgroundColor = colors[type] || colors.info;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Animasyon
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Otomatik kaldır
    const timeoutId = setTimeout(() => {
        removeNotification(notification);
    }, 5000);
    
    // Tıklayınca kapat
    notification.addEventListener('click', () => {
        clearTimeout(timeoutId);
        removeNotification(notification);
    });
    
    function removeNotification(notif) {
        notif.style.opacity = '0';
        notif.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notif.parentNode) {
                notif.parentNode.removeChild(notif);
            }
        }, 300);
    }
}
// ============= TOPIC.JS'E EKLENECEK KODLAR =============
// Bu kodları mevcut topic.js dosyanızın sonuna ekleyin

// Edit modal HTML'ini oluştur
function createEditModal() {
    const modalHtml = `
        <div id="editModal" class="forum-modal" style="display: none;">
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="editModalTitle">Gönderiyi Düzenle</h3>
                    <button type="button" class="modal-close" onclick="closeEditModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" id="editPostId" name="post_id">
                        <input type="hidden" id="editType" name="type">
                        <input type="hidden" id="editTopicId" name="topic_id">
                        
                        <div class="form-group">
                            <label for="editContent">İçerik:</label>
                            <div class="editor-toolbar">
                                <button type="button" class="editor-btn" onclick="insertBBCodeEdit('b')" title="Kalın">
                                    <i class="fas fa-bold"></i>
                                </button>
                                <button type="button" class="editor-btn" onclick="insertBBCodeEdit('i')" title="İtalik">
                                    <i class="fas fa-italic"></i>
                                </button>
                                <button type="button" class="editor-btn" onclick="insertBBCodeEdit('u')" title="Altı Çizili">
                                    <i class="fas fa-underline"></i>
                                </button>
                                <button type="button" class="editor-btn" onclick="insertBBCodeEdit('url')" title="Link">
                                    <i class="fas fa-link"></i>
                                </button>
                                <button type="button" class="editor-btn" onclick="insertBBCodeEdit('img')" title="Resim">
                                    <i class="fas fa-image"></i>
                                </button>
                                <button type="button" class="editor-btn" onclick="insertBBCodeEdit('quote')" title="Alıntı">
                                    <i class="fas fa-quote-left"></i>
                                </button>
                                <button type="button" class="editor-btn" onclick="insertBBCodeEdit('code')" title="Kod">
                                    <i class="fas fa-code"></i>
                                </button>
                            </div>
                            <textarea id="editContent" name="content" rows="10" required 
                                      minlength="5" maxlength="50000"></textarea>
                            <div class="char-counter">
                                <span id="edit-char-count">0</span> / 50000 karakter
                            </div>
                        </div>
                        
                        <div class="modal-actions">
                            <button type="submit" class="btn-submit" id="editSubmitBtn">
                                <i class="fas fa-save"></i> Kaydet
                            </button>
                            <button type="button" class="btn-cancel" onclick="closeEditModal()">
                                <i class="fas fa-times"></i> İptal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Edit form event listener
    const editForm = document.getElementById('editForm');
    if (editForm) {
        editForm.addEventListener('submit', handleEditSubmit);
    }
    
    // Edit content karakter sayacı
    const editContent = document.getElementById('editContent');
    const editCharCount = document.getElementById('edit-char-count');
    
    if (editContent && editCharCount) {
        editContent.addEventListener('input', function() {
            editCharCount.textContent = this.value.length;
            
            if (this.value.length > 9500) {
                editCharCount.style.color = 'var(--red)';
            } else if (this.value.length > 8000) {
                editCharCount.style.color = 'var(--gold)';
            } else {
                editCharCount.style.color = 'var(--light-grey)';
            }
        });
    }
}

// Edit modal için BBCode fonksiyonları
function insertBBCodeEdit(tag, value = null) {
    const textarea = document.getElementById('editContent');
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);
    
    let replacement;
    
    switch(tag) {
        case 'url':
            const url = prompt('URL girin:');
            if (url) {
                replacement = selectedText ? `[url=${url}]${selectedText}[/url]` : `[url=${url}][/url]`;
            } else {
                return;
            }
            break;
        case 'img':
            const imgUrl = prompt('Resim URL\'sini girin:');
            if (imgUrl) {
                replacement = `[img]${imgUrl}[/img]`;
            } else {
                return;
            }
            break;
        default:
            replacement = selectedText ? `[${tag}]${selectedText}[/${tag}]` : `[${tag}][/${tag}]`;
    }
    
    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    
    let newPos;
    if (selectedText) {
        newPos = start + replacement.length;
    } else {
        const closingTag = tag === 'img' ? '' : `[/${tag}]`;
        newPos = start + replacement.length - closingTag.length;
    }
    
    textarea.setSelectionRange(newPos, newPos);
    textarea.focus();
    
    // Karakter sayısını güncelle
    const event = new Event('input');
    textarea.dispatchEvent(event);
}

// Edit modalını aç - DÜZELTİLMİŞ VERSİYON
async function editPost(postId, type) {
    try {
        let url;
        
        if (type === 'topic') {
            // Topic düzenleme için topic ID'yi al
            const topicId = getTopicIdFromUrl();
            if (!topicId) {
                showNotification('Topic ID bulunamadı.', 'error');
                return;
            }
            url = `/public/forum/actions/get_post_content.php?type=topic&topic_id=${topicId}`;
        } else {
            // Normal post düzenleme
            url = `/public/forum/actions/get_post_content.php?post_id=${postId}&type=post`;
        }
        
        console.log('Fetching from URL:', url); // Debug için
        
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        console.log('Response data:', data); // Debug için
        
        if (data.success) {
            // Modal verilerini set et
            document.getElementById('editPostId').value = postId || 0;
            document.getElementById('editType').value = type;
            document.getElementById('editTopicId').value = getTopicIdFromUrl() || 0;
            document.getElementById('editContent').value = data.content;
            document.getElementById('editModalTitle').textContent = type === 'topic' ? 'Konuyu Düzenle' : 'Gönderiyi Düzenle';
            
            // Karakter sayısını güncelle
            const editCharCount = document.getElementById('edit-char-count');
            if (editCharCount) {
                editCharCount.textContent = data.content.length;
            }
            
            // Modalı göster
            document.getElementById('editModal').style.display = 'block';
            document.body.style.overflow = 'hidden'; // Scroll'u kapat
            
            // Textarea'ya focus
            setTimeout(() => {
                document.getElementById('editContent').focus();
            }, 100);
        } else {
            showNotification(data.message || 'İçerik yüklenemedi.', 'error');
        }
    } catch (error) {
        console.error('Edit post error:', error);
        showNotification('İçerik yüklenirken hata oluştu: ' + error.message, 'error');
    }
}

// Edit modalını kapat
function closeEditModal() {
    const modal = document.getElementById('editModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = ''; // Scroll'u aç
        
        // Form'u temizle
        document.getElementById('editForm').reset();
    }
}

// Edit form submit handler
async function handleEditSubmit(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('editSubmitBtn');
    const originalText = submitBtn.innerHTML;
    
    // Button'u devre dışı bırak
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
    
    try {
        const formData = new FormData(e.target);
        formData.append('csrf_token', csrfToken);
        
        const response = await fetch('/public/forum/actions/edit_post.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            const type = formData.get('type');
            const postId = formData.get('post_id');
            
            // İçeriği güncelle
            if (type === 'topic') {
                // İlk post (konu içeriği)
                const topicPost = document.querySelector('.topic-first-post .post-body');
                if (topicPost) {
                    topicPost.innerHTML = data.content_html;
                }
            } else {
                // Normal post
                const postBody = document.querySelector(`#post-${postId} .post-body`);
                if (postBody) {
                    // Mevcut edited info'yu koru veya ekle
                    let editedInfo = postBody.querySelector('.post-edited-info');
                    if (!editedInfo) {
                        editedInfo = document.createElement('div');
                        editedInfo.className = 'post-edited-info';
                        postBody.appendChild(editedInfo);
                    }
                    
                    // İçeriği güncelle
                    postBody.innerHTML = data.content_html;
                    
                    // Edited info'yu güncelle
                    editedInfo.innerHTML = `
                        <i class="fas fa-edit"></i>
                        <em>Son düzenleme: Az önce</em>
                    `;
                    postBody.appendChild(editedInfo);
                }
            }
            
            showNotification(data.message, 'success');
            closeEditModal();
            
        } else {
            showNotification(data.message || 'Düzenleme başarısız oldu.', 'error');
        }
    } catch (error) {
        console.error('Edit submit error:', error);
        showNotification('Düzenleme sırasında hata oluştu.', 'error');
    } finally {
        // Button'u tekrar aktif et
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

// Gelişmiş silme onay modalı
function showDeleteConfirmation(title, message, onConfirm) {
    // Mevcut onay modalını kaldır
    const existingModal = document.querySelector('.delete-confirmation');
    if (existingModal) {
        existingModal.remove();
    }
    
    const confirmHtml = `
        <div class="delete-confirmation">
            <h4><i class="fas fa-exclamation-triangle"></i> ${title}</h4>
            <p>${message}</p>
            <div class="delete-confirmation-actions">
                <button class="btn-confirm-delete" onclick="handleDeleteConfirm()">
                    <i class="fas fa-trash"></i> Evet, Sil
                </button>
                <button class="btn-cancel-delete" onclick="closeDeleteConfirmation()">
                    <i class="fas fa-times"></i> İptal
                </button>
            </div>
        </div>
        <div class="modal-overlay" onclick="closeDeleteConfirmation()"></div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', confirmHtml);
    
    // Confirm callback'i sakla
    window.deleteConfirmCallback = onConfirm;
}

function handleDeleteConfirm() {
    if (window.deleteConfirmCallback) {
        window.deleteConfirmCallback();
        closeDeleteConfirmation();
    }
}

function closeDeleteConfirmation() {
    const modal = document.querySelector('.delete-confirmation');
    const overlay = document.querySelector('.modal-overlay:not(#editModal .modal-overlay)');
    
    if (modal) modal.remove();
    if (overlay) overlay.remove();
    
    window.deleteConfirmCallback = null;
}

// Yanıt sayısını güncelle
function updateReplyCount(change) {
    // Header'daki sayıyı güncelle
    const replyCountElement = document.querySelector('.topic-stats .stat-number');
    if (replyCountElement) {
        const currentCount = parseInt(replyCountElement.textContent.replace(/,/g, ''));
        const newCount = Math.max(0, currentCount + change);
        replyCountElement.textContent = newCount.toLocaleString('tr-TR');
    }
    
    // Posts header'daki sayıyı güncelle
    const postsCount = document.querySelector('.posts-count');
    if (postsCount) {
        const currentCount = parseInt(postsCount.textContent.match(/\d+/)[0]);
        const newCount = Math.max(0, currentCount + change);
        postsCount.textContent = `${newCount} yanıt`;
    }
}

// DOMContentLoaded'da edit modal'ı oluştur
document.addEventListener('DOMContentLoaded', function() {
    // Edit modal'ı oluştur
    createEditModal();
});

// Modal dışına tıklayınca kapat
document.addEventListener('click', function(e) {
    const modal = document.getElementById('editModal');
    if (modal && e.target.classList.contains('modal-overlay')) {
        closeEditModal();
    }
});

// ESC tuşu ile modal'ı kapat
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('editModal');
        if (modal && modal.style.display !== 'none') {
            closeEditModal();
        }
    }
});

// ============= SİLME FONKSİYONLARINI DEĞİŞTİR =============
// Mevcut confirmTopicDelete ve confirmPostDelete fonksiyonlarını bu versiyonlarla değiştirin

// Silme fonksiyonları - Gelişmiş implementasyon
function confirmTopicDelete(topicId) {
    showDeleteConfirmation(
        'Konuyu Sil',
        'Bu konuyu silmek istediğinizden emin misiniz? Bu işlem geri alınamaz ve tüm yanıtlar da silinecektir.',
        () => deleteTopicAction(topicId)
    );
}

function confirmPostDelete(postId) {
    showDeleteConfirmation(
        'Gönderiyi Sil',
        'Bu gönderiyi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.',
        () => deletePostAction(postId)
    );
}

// Yorum silme action - Gelişmiş versiyon
async function deletePostAction(postId) {
    if (!csrfToken) {
        showNotification('CSRF token bulunamadı. Sayfayı yenileyin.', 'error');
        return;
    }

    const postElement = document.getElementById(`post-${postId}`);
    
    // Loading overlay ekle
    if (postElement) {
        const loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'post-loading-overlay';
        loadingOverlay.innerHTML = '<i class="fas fa-spinner fa-spin spinner"></i>';
        postElement.style.position = 'relative';
        postElement.appendChild(loadingOverlay);
    }
    
    try {
        const response = await fetch('/public/forum/actions/delete_post.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `csrf_token=${encodeURIComponent(csrfToken)}&post_id=${postId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Gönderiyi animasyonlu şekilde kaldır
            if (postElement) {
                postElement.classList.add('deleting');
                
                setTimeout(() => {
                    postElement.remove();
                    
                    // Yanıt sayısını güncelle
                    updateReplyCount(-1);
                    
                    // Eğer son yorum silindiyse
                    const remainingPosts = document.querySelectorAll('.post-item');
                    if (remainingPosts.length === 0) {
                        const postsList = document.querySelector('.posts-list');
                        if (postsList) {
                            postsList.innerHTML = `
                                <div class="no-posts-message" style="text-align: center; padding: 3rem; color: var(--light-grey);">
                                    <i class="fas fa-comments" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                                    <p>Henüz yanıt yok. İlk yanıtı siz yazın!</p>
                                </div>
                            `;
                        }
                    }
                }, 500);
            }
        } else {
            showNotification(data.message, 'error');
            // Loading overlay'i kaldır
            if (postElement) {
                const overlay = postElement.querySelector('.post-loading-overlay');
                if (overlay) overlay.remove();
            }
        }
    } catch (error) {
        console.error('Post delete error:', error);
        showNotification('Gönderi silinirken bir hata oluştu.', 'error');
        // Loading overlay'i kaldır
        if (postElement) {
            const overlay = postElement.querySelector('.post-loading-overlay');
            if (overlay) overlay.remove();
        }
    }
}
// ============= TOPIC LIKE FUNCTIONS - DÜZELTME =============

// Konu beğeni toggle fonksiyonu - DÜZELTME
async function toggleTopicLike(topicId) {
    if (!csrfToken) {
        showNotification('CSRF token bulunamadı. Sayfayı yenileyin.', 'error');
        return;
    }

    const btn = document.querySelector('.topic-like-btn[data-topic-id="' + topicId + '"]');
    if (!btn) {
        showNotification('Beğeni butonu bulunamadı.', 'error');
        return;
    }

    // Button'u geçici olarak devre dışı bırak
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span class="like-count">...</span>';

    try {
        const response = await fetch('/public/forum/actions/toggle_topic_like.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `csrf_token=${encodeURIComponent(csrfToken)}&topic_id=${topicId}`
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            const likeCount = btn.querySelector('.like-count');
            
            // Beğeni durumunu güncelle
            if (data.liked) {
                btn.classList.add('liked');
            } else {
                btn.classList.remove('liked');
            }
            
            // Beğeni sayısını güncelle
            if (likeCount) {
                likeCount.textContent = data.like_count;
                likeCount.classList.add('updated');
                setTimeout(() => likeCount.classList.remove('updated'), 400);
            }
            
            // Button'u tekrar etkinleştir
            btn.disabled = false;
            btn.innerHTML = `<i class="fas fa-heart"></i> <span class="like-count">${data.like_count}</span>`;
            
            // Beğenen kullanıcılar listesini güncelle
            updateLikedUsersList(`topic-${topicId}`, data.liked_users || [], data.like_count);
            
            showNotification(data.message, 'success');
        } else {
            btn.disabled = false;
            btn.innerHTML = originalText;
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Topic like toggle error:', error);
        btn.disabled = false;
        btn.innerHTML = originalText;
        showNotification('Beğeni işlemi başarısız oldu: ' + error.message, 'error');
    }
}

// Beğenen kullanıcılar listesini güncelle
function updateLikedUsersList(elementId, likedUsers, totalCount) {
    let likedUsersSection = document.querySelector('.liked-users-section');
    
    if (totalCount === 0) {
        // Hiç beğeni yoksa bölümü gizle veya kaldır
        if (likedUsersSection) {
            likedUsersSection.style.display = 'none';
        }
        return;
    }
    
    if (!likedUsersSection) {
        // Eğer beğeni bölümü yoksa oluştur
        createLikedUsersSection(elementId, likedUsers, totalCount);
        return;
    }
    
    // Mevcut bölümü güncelle
    likedUsersSection.style.display = 'block';
    
    // Toggle butonunu güncelle
    const toggle = likedUsersSection.querySelector('.liked-users-toggle span');
    if (toggle) {
        toggle.textContent = `${totalCount} kişi beğendi`;
    }
    
    // Kullanıcı listesini güncelle
    const usersList = likedUsersSection.querySelector('.liked-users-list');
    if (usersList) {
        let usersHtml = '';
        
        likedUsers.forEach(user => {
            usersHtml += `
                <div class="liked-user-item">
                    <span class="user-link" data-user-id="${user.id}"
                        style="color: ${user.role_color || '#bd912a'}">
                        ${escapeHtml(user.username)}
                    </span>
                </div>
            `;
        });
        
        if (totalCount > 20) {
            usersHtml += `
                <div class="liked-users-more">
                    ve ${totalCount - 20} kişi daha...
                </div>
            `;
        }
        
        usersList.innerHTML = usersHtml;
    }
}

// Beğeni bölümü oluştur
function createLikedUsersSection(elementId, likedUsers, totalCount) {
    const topicContentWrapper = document.querySelector('.topic-content-wrapper');
    if (!topicContentWrapper) return;
    
    let usersHtml = '';
    likedUsers.forEach(user => {
        usersHtml += `
            <div class="liked-user-item">
                <span class="user-link" data-user-id="${user.id}"
                    style="color: ${user.role_color || '#bd912a'}">
                    ${escapeHtml(user.username)}
                </span>
            </div>
        `;
    });
    
    if (totalCount > 20) {
        usersHtml += `
            <div class="liked-users-more">
                ve ${totalCount - 20} kişi daha...
            </div>
        `;
    }
    
    const sectionHtml = `
        <div class="liked-users-section">
            <div class="liked-users-toggle" onclick="toggleLikedUsers('${elementId}')">
                <i class="fas fa-users"></i>
                <span>${totalCount} kişi beğendi</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="liked-users-list" id="liked-users-${elementId}" style="display: none;">
                ${usersHtml}
            </div>
        </div>
    `;
    
    topicContentWrapper.insertAdjacentHTML('beforeend', sectionHtml);
}

// Beğenen kullanıcılar listesini aç/kapat
function toggleLikedUsers(elementId) {
    const usersList = document.getElementById(`liked-users-${elementId}`);
    const toggle = document.querySelector(`[onclick="toggleLikedUsers('${elementId}')"]`);
    
    if (!usersList || !toggle) return;
    
    if (usersList.style.display === 'none') {
        usersList.style.display = 'block';
        toggle.classList.add('active');
    } else {
        usersList.style.display = 'none';
        toggle.classList.remove('active');
    }
}

// HTML escape fonksiyonu - GÜVENLİ VERSİYON
function escapeHtml(text) {
    if (!text) return '';
    
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    
    return String(text).replace(/[&<>"']/g, function(m) { 
        return map[m]; 
    });
}