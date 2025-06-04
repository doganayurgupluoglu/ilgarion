
let csrfToken = '<?= generate_csrf_token() ?>';
document.addEventListener('DOMContentLoaded', function() {
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

// BBCode editor functions
function insertBBCode(tag) {
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
        default:
            replacement = selectedText ? `[${tag}]${selectedText}[/${tag}]` : `[${tag}][/${tag}]`;
    }
    
    textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
    
    // Move cursor
    const newPos = start + replacement.length - (selectedText ? 0 : `[/${tag}]`.length);
    textarea.setSelectionRange(newPos, newPos);
    textarea.focus();
    
    // Update character count
    const event = new Event('input');
    textarea.dispatchEvent(event);
}

// Quote post function
function quotePost(username, content) {
    const textarea = document.getElementById('reply_content');
    if (!textarea) return;
    
    // Clean content for quoting
    const cleanContent = content.substring(0, 200) + (content.length > 200 ? '...' : '');
    const quote = `[quote=${username}]${cleanContent}[/quote]\n\n`;
    
    const currentPos = textarea.value.length;
    textarea.value += quote;
    textarea.setSelectionRange(currentPos + quote.length, currentPos + quote.length);
    textarea.focus();
    
    // Scroll to reply form
    document.getElementById('reply-form').scrollIntoView({ behavior: 'smooth' });
    
    // Update character count
    const event = new Event('input');
    textarea.dispatchEvent(event);
}

// Preview reply function
function previewReply() {
    const content = document.getElementById('reply_content').value;
    const previewDiv = document.getElementById('reply-preview');
    const previewContent = previewDiv.querySelector('.preview-content');
    
    if (!content.trim()) {
        alert('Önizlemek için içerik girin.');
        return;
    }
    
    // Simple BBCode to HTML conversion for preview
    let html = content
        .replace(/\[b\](.*?)\[\/b\]/g, '<strong>$1</strong>')
        .replace(/\[i\](.*?)\[\/i\]/g, '<em>$1</em>')
        .replace(/\[u\](.*?)\[\/u\]/g, '<u>$1</u>')
        .replace(/\[s\](.*?)\[\/s\]/g, '<del>$1</del>')
        .replace(/\[url=(.*?)\](.*?)\[\/url\]/g, '<a href="$1" target="_blank">$2</a>')
        .replace(/\[url\](.*?)\[\/url\]/g, '<a href="$1" target="_blank">$1</a>')
        .replace(/\[quote=(.*?)\](.*?)\[\/quote\]/g, '<blockquote class="forum-quote"><cite>$1 yazdı:</cite>$2</blockquote>')
        .replace(/\[quote\](.*?)\[\/quote\]/g, '<blockquote class="forum-quote">$1</blockquote>')
        .replace(/\[code\](.*?)\[\/code\]/g, '<pre class="forum-code">$1</pre>')
        .replace(/\n/g, '<br>');
    
    previewContent.innerHTML = html;
    previewDiv.style.display = 'block';
    previewDiv.scrollIntoView({ behavior: 'smooth' });
}

// Toggle topic pin
async function toggleTopicPin(topicId, pinned) {
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

// Toggle topic lock
async function toggleTopicLock(topicId, locked) {
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
            
            // Refresh page if topic was locked to update reply form
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

// Toggle post like
async function togglePostLike(postId) {
    try {
        const response = await fetch('/public/forum/actions/toggle_post_like.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `csrf_token=${encodeURIComponent(csrfToken)}&post_id=${postId}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            const btn = document.querySelector(`[data-post-id="${postId}"]`);
            const likeCount = btn.querySelector('.like-count');
            
            if (data.liked) {
                btn.classList.add('liked');
            } else {
                btn.classList.remove('liked');
            }
            
            likeCount.textContent = data.like_count;
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
        }
    } catch (error) {
        console.error('Like toggle error:', error);
        showNotification('Bir hata oluştu.', 'error');
    }
}

// Confirm topic delete
function confirmTopicDelete(topicId) {
    if (confirm('Bu konuyu silmek istediğinizden emin misiniz? Bu işlem geri alınamaz ve tüm yanıtlar da silinecektir.')) {
        // Implement topic deletion
        showNotification('Konu silme özelliği henüz aktif değil.', 'info');
    }
}

// Confirm post delete
function confirmPostDelete(postId) {
    if (confirm('Bu gönderiyi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
        // Implement post deletion
        showNotification('Gönderi silme özelliği henüz aktif değil.', 'info');
    }
}

// Edit post function
function editPost(postId, type) {
    showNotification('Düzenleme özelliği henüz aktif değil.', 'info');
}

// Form submission with AJAX
document.addEventListener('DOMContentLoaded', function() {
    const replyForm = document.getElementById('replyForm');
    
    if (replyForm) {
        replyForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gönderiliyor...';
            
            try {
                const formData = new FormData(this);
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    
                    // Redirect to the new post
                    if (data.redirect_url) {
                        setTimeout(() => {
                            window.location.href = data.redirect_url;
                        }, 1000);
                    }
                } else {
                    showNotification(data.message, 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Reply submission error:', error);
                showNotification('Yanıt gönderilirken bir hata oluştu.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
});

// Notification function
function showNotification(message, type) {
    // Use the forum manager's notification system if available
    if (window.forumManager) {
        window.forumManager.showNotification(message, type);
    } else {
        // Fallback notification
        alert(message);
    }
}