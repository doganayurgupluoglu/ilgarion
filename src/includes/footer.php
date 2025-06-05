<?php // src/includes/footer.php ?>
        </div> <?php // .site-container kapanışı ?>
        <footer class="main-footer">
            <p>ILGARION TURANIS. Tüm hakları saklıdır. <a href="https://doganaylab.com" target="_blank" rel="noopener noreferrer">© <?php echo date("Y"); ?> doganaylab.com</a></p>
        </footer>

        <?php // YENİ POPOVER HTML YAPISI (Bu zaten güncellenmiş olmalı) ?>
        <div id="userInfoPopover" class="user-info-popover-v2" style="display:none;">
            <div class="popover-arrow"></div>
            <div class="popover-header-v2">
                <a href="#" id="popoverAvatarLinkV2" class="popover-avatar-link-v2">
                    <img id="popoverAvatarV2" src="" alt="Avatar" style="display:none;">
                    <div id="popoverAvatarPlaceholderV2" class="avatar-placeholder-popover-v2" style="display:flex;">U</div>
                </a>
                <div class="popover-user-titles-v2">
                    <a href="#" id="popoverUsernameV2" class="popover-username-link-v2">Kullanıcı Adı</a>
                    <div id="popoverUserRolesV2" class="popover-user-roles-display">
                        {/* Roller buraya JS ile eklenecek */}
                    </div>
                </div>
            </div>
            <div class="popover-body-v2">
                <div class="popover-info-section">
                    <p><i class="fas fa-shield-alt fa-fw popover-icon"></i>Oyun İçi Ad: <strong id="popoverIngameNameV2">N/A</strong></p>
                    <p><i class="fab fa-discord fa-fw popover-icon"></i>Discord: <strong id="popoverDiscordUsernameV2">N/A</strong></p>
                </div>
                <hr class="popover-divider-v2">
                <div class="popover-stats-section">
                    <p><i class="fas fa-calendar-check fa-fw popover-icon"></i>Oluşturduğu Etkinlik: <strong id="popoverEventCountV2">0</strong></p>
                    <p><i class="fas fa-images fa-fw popover-icon"></i>Yüklediği Galeri F.: <strong id="popoverGalleryCountV2">0</strong></p>
                </div>
            </div>
            <div class="popover-footer-v2">
                <a href="#" id="popoverProfileLinkV2" class="btn btn-sm btn-popover-action">Profili Görüntüle</a>
            </div>
        </div>
        <?php // YENİ POPOVER HTML YAPISI SONU ?>


        <?php // GÜNCELLENMİŞ GALERİ MODAL HTML YAPISI ?>
        <div id="galleryModal" class="modal">
            <span class="close-modal" id="closeGalleryModalSpan">&times;</span>
            <a class="prev-modal" id="prevGalleryModalButton">&#10094;</a>
            <a class="next-modal" id="nextGalleryModalButton">&#10095;</a>
            <div class="modal-content-wrapper">
                <img class="modal-content" id="galleryModalImage" alt="Galeri Fotoğrafı">
                <div id="galleryCaptionTextV2" class="caption-v2">
                    <div class="caption-v2-header">
                        <?php // YENİ TETİKLEYİCİ YAPI (MODAL İÇİN) ?>
                        <span class="caption-v2-uploader-trigger-wrapper user-info-trigger" 
                             id="modalUploaderInfoTrigger" <?php // ID'yi değiştirdim karışmasın diye, ya da sadece class'ı kullanırız ?>
                             data-user-id="" data-username="" data-avatar="" 
                             data-ingame="" data-discord="" 
                             data-event-count="" data-gallery-count="" data-roles=""
                             style="display: inline-flex; align-items: center; gap: 10px; cursor:default; flex-grow: 1; min-width: 0;">
                            
                            <img id="modalUploaderAvatar" src="" alt="Yükleyen Avatar" class="caption-v2-avatar" style="display:none;">
                            <div id="modalUploaderAvatarPlaceholder" class="caption-v2-avatar-placeholder" style="display:flex;">U</div>
                            <a href="#" id="modalUploaderUsername" class="caption-v2-username">Yükleyen</a>
                        </span>
                        <?php // YENİ TETİKLEYİCİ YAPI SONU (MODAL İÇİN) ?>

                        <div class="caption-v2-actions">
                            <?php if (is_user_approved()): ?>
                            <button class="like-button-modal" id="modalLikeButton" data-photo-id="">
                                <i class="fas fa-heart like-icon-modal"></i> 
                                <span class="like-count-modal" id="modalLikeCount">0</span>
                            </button>
                            <?php else: ?>
                            <span class="like-display-modal">
                                <i class="fas fa-heart" style="color:var(--grey);"></i> 
                                <span id="modalLikeCountStatic">0</span>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p id="modalPhotoDescription" class="caption-v2-description">Açıklama buraya gelecek...</p>
                    <small id="modalPhotoDate" class="caption-v2-date">Tarih buraya gelecek...</small>
                </div>
            </div>
        </div>
        <?php // GÜNCELLENMİŞ GALERİ MODAL HTML YAPISI SONU ?>


        <?php // Diğer script yüklemeleri... ?>
        <script src="/public/js/navbar.js?v=<?php echo defined('BASE_PATH') && file_exists(BASE_PATH . '/public/js/navbar.js') ? filemtime(BASE_PATH . '/public/js/navbar.js') : time(); ?>"></script>
        <script src="/public/js/dropdown.js?v=<?php echo defined('BASE_PATH') && file_exists(BASE_PATH . '/public/js/dropdown.js') ? filemtime(BASE_PATH . '/public/js/dropdown.js') : time(); ?>"></script>
        
        <?php $currentPageJs = basename($_SERVER['PHP_SELF'], '.php'); ?>
        <?php if ($currentPageJs === 'edit_hangar' || $currentPageJs === 'edit_loadout_items'): ?>
            <?php if (file_exists(BASE_PATH . '/public/js/hangar_edit.js')): ?>
                <script src="/public/js/hangar_edit.js?v=<?php echo filemtime(BASE_PATH . '/public/js/hangar_edit.js'); ?>"></script>
            <?php endif; ?>
             <?php if ($currentPageJs === 'edit_loadout_items' && file_exists(BASE_PATH . '/public/js/loadout_editor.js')): ?>
                <script src="/public/js/loadout_editor.js?v=<?php echo filemtime(BASE_PATH . '/public/js/loadout_editor.js'); ?>"></script>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (($currentPageJs === 'discussion_detail' || $currentPageJs === 'discussions') && file_exists(BASE_PATH . '/public/js/discussion.js')): ?>
            <script src="/public/js/discussion.js?v=<?php echo filemtime(BASE_PATH . '/public/js/discussion.js'); ?>"></script>
        <?php endif; ?>
        <?php if (($currentPageJs === 'guide_detail' || $currentPageJs === 'guides' || $currentPageJs === 'new_guide') && file_exists(BASE_PATH . '/public/js/guides.js')): ?>
            <script src="/public/js/guides.js?v=<?php echo filemtime(BASE_PATH . '/public/js/guides.js'); ?>"></script>
        <?php endif; ?>
        <?php if (file_exists(BASE_PATH . '/public/js/notifications_ajax.js')): ?>
             <script src="/public/js/notifications_ajax.js?v=<?php echo filemtime(BASE_PATH . '/public/js/notifications_ajax.js'); ?>"></script>
        <?php endif; ?>
      </body>
</html>