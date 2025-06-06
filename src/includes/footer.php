<?php // src/includes/footer.php ?>
        </div> <?php // .site-container kapanışı ?>
        <footer class="main-footer">
            <p>ILGARION TURANIS. Tüm hakları saklıdır. <a href="https://doganaylab.com" target="_blank" rel="noopener noreferrer">© <?php echo date("Y"); ?> doganaylab.com</a></p>
        </footer>
        <?php // Diğer script yüklemeleri... ?>
        <script src="js/navbar.js?v=<?php echo defined('BASE_PATH') && file_exists(BASE_PATH . 'js/navbar.js') ? filemtime(BASE_PATH . 'js/navbar.js') : time(); ?>"></script>
        <script src="js/dropdown.js?v=<?php echo defined('BASE_PATH') && file_exists(BASE_PATH . 'js/dropdown.js') ? filemtime(BASE_PATH . 'js/dropdown.js') : time(); ?>"></script>
        
        <?php $currentPageJs = basename($_SERVER['PHP_SELF'], '.php'); ?>
        <?php if ($currentPageJs === 'edit_hangar' || $currentPageJs === 'edit_loadout_items'): ?>
            <?php if (file_exists(BASE_PATH . 'js/hangar_edit.js')): ?>
                <script src="js/hangar_edit.js?v=<?php echo filemtime(BASE_PATH . 'js/hangar_edit.js'); ?>"></script>
            <?php endif; ?>
             <?php if ($currentPageJs === 'edit_loadout_items' && file_exists(BASE_PATH . 'js/loadout_editor.js')): ?>
                <script src="js/loadout_editor.js?v=<?php echo filemtime(BASE_PATH . 'js/loadout_editor.js'); ?>"></script>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (($currentPageJs === 'discussion_detail' || $currentPageJs === 'discussions') && file_exists(BASE_PATH . 'js/discussion.js')): ?>
            <script src="js/discussion.js?v=<?php echo filemtime(BASE_PATH . 'js/discussion.js'); ?>"></script>
        <?php endif; ?>
        <?php if (($currentPageJs === 'guide_detail' || $currentPageJs === 'guides' || $currentPageJs === 'new_guide') && file_exists(BASE_PATH . 'js/guides.js')): ?>
            <script src="js/guides.js?v=<?php echo filemtime(BASE_PATH . 'js/guides.js'); ?>"></script>
        <?php endif; ?>
        <?php if (file_exists(BASE_PATH . 'js/notifications_ajax.js')): ?>
             <script src="js/notifications_ajax.js?v=<?php echo filemtime(BASE_PATH . 'js/notifications_ajax.js'); ?>"></script>
        <?php endif; ?>
      </body>
</html>