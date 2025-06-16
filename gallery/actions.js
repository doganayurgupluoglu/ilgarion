/**
 * Toggles a comment between view and edit mode.
 * @param {number} commentId - The ID of the comment to edit.
 */
function toggleCommentEdit(commentId) {
    const commentDiv = document.getElementById(`comment-${commentId}`);
    if (!commentDiv) return;

    const textP = commentDiv.querySelector('.comment-text');
    const editContainer = commentDiv.querySelector('.comment-edit-container');

    // If already in edit mode, cancel
    if (editContainer.style.display !== 'none') {
        editContainer.style.display = 'none';
        textP.style.display = 'block';
        editContainer.innerHTML = ''; // Clear the edit form
        return;
    }

    // Switch to edit mode
    textP.style.display = 'none';
    editContainer.style.display = 'block';

    const currentText = textP.innerHTML.replace(/<br\s*[\/]?>/gi, "\n"); // br2nl

    editContainer.innerHTML = `
        <textarea class="comment-edit-textarea" rows="3">${currentText}</textarea>
        <div class="comment-edit-actions">
            <button class="btn-primary btn-sm" onclick="saveCommentEdit(${commentId})">
                <i class="fas fa-save"></i> Kaydet
            </button>
            <button class="btn-secondary btn-sm" onclick="toggleCommentEdit(${commentId})">
                <i class="fas fa-times"></i> İptal
            </button>
        </div>
    `;

    const textarea = editContainer.querySelector('textarea');
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = textarea.value.length;
}

/**
 * Saves the edited comment content via an API call.
 * @param {number} commentId - The ID of the comment to save.
 */
async function saveCommentEdit(commentId) {
    const editContainer = document.getElementById(`edit-container-${commentId}`);
    const textarea = editContainer.querySelector('.comment-edit-textarea');
    const newContent = textarea.value;

    try {
        const response = await fetch('/gallery/actions/edit_comment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                comment_id: commentId,
                content: newContent,
                csrf_token: window.galleryConfig.csrfToken
            })
        });

        const result = await response.json();

        if (result.success) {
            const commentDiv = document.getElementById(`comment-${commentId}`);
            const textP = commentDiv.querySelector('.comment-text');
            textP.innerHTML = result.new_content.replace(/\n/g, '<br>');
            toggleCommentEdit(commentId); // Switch back to view mode
            showModalNotification('Yorum başarıyla güncellendi.', 'success');
        } else {
            showModalNotification(result.message || 'Yorum güncellenemedi.', 'error');
        }
    } catch (error) {
        console.error('Error saving comment:', error);
        showModalNotification('Bir ağ hatası oluştu.', 'error');
    }
} 