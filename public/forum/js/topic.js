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