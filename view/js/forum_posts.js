/* view/js/forum_posts.js - Forum Posts Specific JavaScript */

document.addEventListener('DOMContentLoaded', function() {
    // Forum posts specific functionality
    initializeForumPosts();
});

function initializeForumPosts() {
    // Post content truncation
    const postContents = document.querySelectorAll('.post-content');
    postContents.forEach(content => {
        if (content.textContent.length > 200) {
            const originalText = content.textContent;
            const truncatedText = originalText.substring(0, 200) + '...';
            content.textContent = truncatedText;
            
            // Add expand functionality if needed
            const expandBtn = document.createElement('span');
            expandBtn.textContent = ' [Devamını Gör]';
            expandBtn.style.color = 'var(--gold)';
            expandBtn.style.cursor = 'pointer';
            expandBtn.onclick = function() {
                content.textContent = originalText;
                expandBtn.remove();
            };
            content.appendChild(expandBtn);
        }
    });
    
    // Post actions tooltips
    const postActions = document.querySelectorAll('.post-actions .btn');
    postActions.forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            showTooltip(this, this.title || 'Mesajı Görüntüle');
        });
    });
}

// Forum specific utilities
function highlightSearchTerm(text, term) {
    if (!term) return text;
    const regex = new RegExp(`(${term})`, 'gi');
    return text.replace(regex, '<mark style="background: var(--gold); color: var(--body-bg); padding: 0.1rem;">$1</mark>');
}

function formatPostDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 1) return 'Dün';
    if (diffDays <= 7) return `${diffDays} gün önce`;
    return date.toLocaleDateString('tr-TR');
} 