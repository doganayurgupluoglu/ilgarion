// public/js/discussion.js
document.addEventListener('DOMContentLoaded', function() {
    const commentFormContainer = document.getElementById('newCommentForm');
    if (!commentFormContainer) {
        return;
    }

    const replyLinks = document.querySelectorAll('.reply-to-post-link-dd');
    const commentTextarea = document.getElementById('comment_content_textarea');
    const parentPostIdField = document.getElementById('parent_post_id_field');
    const replyingToInfo = document.getElementById('replyingToInfo');
    
    const quotedMessagePreview = document.getElementById('quotedMessagePreview');
    const quotedAuthorSpan = document.getElementById('quotedAuthor');
    const quotedTextBloquote = document.getElementById('quotedText');
    const cancelReplyLink = document.getElementById('cancelReply');
    const actualCommentForm = document.getElementById('commentFormActual'); // Formun kendisi

    let currentRawQuoteString = ''; // Alıntı metnini saklamak için

    if (!replyLinks.length || !commentTextarea || !parentPostIdField || !quotedMessagePreview || !quotedAuthorSpan || !quotedTextBloquote || !cancelReplyLink || !replyingToInfo || !actualCommentForm) {
        console.warn("Tartışma/yorum alıntılama sistemi için gerekli HTML elementlerinden bazıları eksik. ID'leri kontrol edin.");
        return;
    }

    // Ham içeriği temizleme fonksiyonu - alıntı etiketlerini kaldırır
    function extractRawContentFromQuotes(text) {
        // >[ALINTI="Yazar"]İçerik[/ALINTI] formatındaki alıntıları temizle
        const pattern = />\[ALINTI="[^"]*"\]\s*([\s\S]*?)\s*\[\/ALINTI\]/gi;
        
        let cleanedText = text.replace(pattern, function(match, quotedContent) {
            // İç içe alıntılar varsa onları da temizle (recursive)
            return extractRawContentFromQuotes(quotedContent.trim());
        });

        return cleanedText.trim();
    }

    // HTML'den ham metni çıkarma fonksiyonu
    function extractRawTextFromHtml(htmlContent) {
        // Önce blockquote'ları kaldır (işlenmiş alıntılar)
        let tempContent = htmlContent.replace(/<blockquote[^>]*>[\s\S]*?<\/blockquote>/gi, '');
        
        // P etiketlerini satır sonuna çevir
        tempContent = tempContent.replace(/<p[^>]*>/gi, '').replace(/<\/p>/gi, '\n');
        // BR etiketlerini satır sonuna çevir
        tempContent = tempContent.replace(/<br\s*\/?>/gi, '\n');
        
        // HTML etiketlerini temizle
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = tempContent;
        let rawText = (tempDiv.textContent || tempDiv.innerText || "").trim();
        
        // Çoklu satır sonlarını tek satır sonuna çevir
        rawText = rawText.replace(/\n\s*\n/g, '\n').trim();
        
        return rawText;
    }

    replyLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const postIdToReply = this.dataset.postId;
            const postAuthorToReply = this.dataset.postAuthor;

            const postItemToQuote = document.getElementById('post-' + postIdToReply);
            let rawContentToQuote = ""; // Ham içeriği alacağız
            
            if (postItemToQuote) {
                const contentElement = postItemToQuote.querySelector('.post-content-dd');
                if (contentElement && contentElement.dataset.rawContent) {
                    // data-raw-content varsa onu kullan ve alıntı etiketlerini temizle
                    rawContentToQuote = extractRawContentFromQuotes(contentElement.dataset.rawContent.trim());
                } else if (contentElement) { 
                    // Fallback: HTML'den ham metni çıkar
                    rawContentToQuote = extractRawTextFromHtml(contentElement.innerHTML);
                    console.warn("data-raw-content bulunamadı, HTML'den içerik çekildi.");
                }
            }

            // Önizleme için kısalt
            let displayQuotedText = rawContentToQuote.substring(0, 150);
            if (rawContentToQuote.length > 150) {
                displayQuotedText += '...';
            }
            
            parentPostIdField.value = postIdToReply;
            
            // Alıntı metnini sakla, textarea'ya YAZMA
            currentRawQuoteString = `>[ALINTI="${postAuthorToReply}"]\n${rawContentToQuote}\n[/ALINTI]\n\n`;
            
            // Alıntı önizlemesini doldur ve göster
            if (quotedAuthorSpan) quotedAuthorSpan.textContent = postAuthorToReply + " kullanıcısından alıntı:";
            if (quotedTextBloquote) quotedTextBloquote.textContent = displayQuotedText;
            if (quotedMessagePreview) quotedMessagePreview.style.display = 'block';

            if (replyingToInfo) {
                replyingToInfo.innerHTML = ` (<a href="#post-${postIdToReply}" style="color: var(--turquase); text-decoration: none;">${postAuthorToReply}</a> kullanıcısına yanıt yazılıyor)`;
                replyingToInfo.style.display = 'inline';
            }
            if (cancelReplyLink) cancelReplyLink.style.display = 'inline';

            commentTextarea.focus();
            if(commentFormContainer) {
                 commentFormContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    });

    if (cancelReplyLink) {
        cancelReplyLink.addEventListener('click', function(e) {
            e.preventDefault();
            if(parentPostIdField) parentPostIdField.value = "";
            currentRawQuoteString = ''; // Saklanan alıntıyı temizle
            
            if(quotedMessagePreview) quotedMessagePreview.style.display = 'none';
            if(replyingToInfo) replyingToInfo.style.display = 'none';
            this.style.display = 'none';
        });
    }

    if (actualCommentForm) {
        actualCommentForm.addEventListener('submit', function(e) {
            // Eğer bir alıntı aktifse (parentPostIdField dolu ve currentRawQuoteString varsa)
            // ve saklanan alıntı varsa, onu textarea'nın başına ekle.
            if (currentRawQuoteString && parentPostIdField.value) {
                const currentUserText = commentTextarea.value.trim();
                if (currentUserText) {
                    commentTextarea.value = currentRawQuoteString + currentUserText;
                } else {
                    commentTextarea.value = currentRawQuoteString.trim();
                }
            }
            // Formun normal şekilde submit olmasına izin ver.
        });
    }
});