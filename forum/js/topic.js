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
});

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
        const response = await fetch('/forum/actions/toggle_post_like.php', {
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
            
            // Button'u devre dışı bırak
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';
            
            try {
                const formData = new FormData(this);
                
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
                    
                    // Form'u temizle (WYSIWYG Editor)
                    const editorContent = this.querySelector('.wysiwyg-content');
                    const hiddenTextarea = this.querySelector('textarea[name="content"]');

                    if (editorContent) {
                        editorContent.innerHTML = '';
                    }
                    if (hiddenTextarea) {
                        hiddenTextarea.value = '';
                    }
                    
                    // Preview'u gizle
                    const preview = document.getElementById('reply-preview');
                    if (preview) {
                        preview.style.display = 'none';
                    }
                    
                    // If the new post belongs on the current page, add it dynamically.
                    // Otherwise, redirect to the correct page.
                    if (data.last_page === currentPage && data.post_data) {
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
                        // Redirect to the page with the new post
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
        const response = await fetch('/forum/actions/delete_topic.php', {
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
                window.location.href = `/forum/category.php?slug=${data.category_slug}`;
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
        const response = await fetch('/forum/actions/delete_post.php', {
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

let activeEditor = null; // To track the currently open editor

function showInlineEditor(type, id) {
    // If another editor is already open, don't open a new one.
    if (activeEditor) {
        showNotification('Lütfen önce açık olan düzenleyiciyi kapatın.', 'warning');
        return;
    }

    const contentArea = document.getElementById(`post-content-${type}-${id}`);
    const originalContent = contentArea.innerHTML;

    // Get the editor template and clone it
    const editorTemplate = document.getElementById('editor-template');
    if (!editorTemplate) {
        console.error('Editor template not found!');
        return;
    }
    const editorInstance = editorTemplate.cloneNode(true);
    editorInstance.id = `editor-instance-${type}-${id}`;
    editorInstance.style.display = 'block';

    // Set the editor's content to the current post content
    const wysiwygContent = editorInstance.querySelector('.wysiwyg-content');
    wysiwygContent.innerHTML = originalContent;

    // Hide the original content and show the editor
    contentArea.style.display = 'none';
    contentArea.parentNode.insertBefore(editorInstance, contentArea.nextSibling);

    // Initialize the new editor
    const wysiwygContainer = editorInstance.querySelector('.wysiwyg-editor-container');
    initializeWysiwygEditor(wysiwygContainer);

    activeEditor = {
        type,
        id,
        element: editorInstance,
        contentArea: contentArea
    };

    // --- Add event listeners for Save and Cancel ---
    const btnSave = editorInstance.querySelector('.btn-save-edit');
    const btnCancel = editorInstance.querySelector('.btn-cancel-edit');

    btnCancel.onclick = () => {
        closeInlineEditor();
    };

    btnSave.onclick = async () => {
        const newContent = editorInstance.querySelector('.wysiwyg-content').innerHTML;
        
        // Disable buttons
        btnSave.disabled = true;
        btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Kaydediliyor...';
        btnCancel.disabled = true;

        try {
            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('type', type);
            formData.append('id', id);
            formData.append('content', newContent);

            const response = await fetch('/forum/actions/update_post.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Update the original content area with the sanitized HTML from the server
                contentArea.innerHTML = data.clean_content;
                showNotification(data.message, 'success');
                closeInlineEditor();
            } else {
                showNotification(data.message || 'An error occurred.', 'error');
                btnSave.disabled = false;
                btnSave.innerHTML = 'Kaydet';
                btnCancel.disabled = false;
            }
        } catch (error) {
            console.error('Update error:', error);
            showNotification('An unexpected error occurred.', 'error');
            btnSave.disabled = false;
            btnSave.innerHTML = 'Kaydet';
            btnCancel.disabled = false;
        }
    };
}

function closeInlineEditor() {
    if (!activeEditor) return;
    activeEditor.contentArea.style.display = 'block'; // Show original content
    activeEditor.element.remove(); // Remove editor
    activeEditor = null;
}

function editPost(postId, type) {
    if (type === 'topic') {
        window.location.href = `/forum/edit_topic.php?id=${getTopicIdFromUrl()}`;
    } else {
        window.location.href = `/forum/edit_post.php?id=${postId}`;
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