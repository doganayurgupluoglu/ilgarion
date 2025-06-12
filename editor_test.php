<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define BASE_PATH if it's not already defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

// Basic includes from your project structure
require_once 'src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';
require_once BASE_PATH . '/src/includes/header.php';
// We don't include navbar here to have a cleaner test page.
?>

<main class="site-container">
    <div class="main-content">
        <h1 class="section-title">WYSIWYG Editor Test Page</h1>
        
        <div class="profile-section" style="width: 100%; max-width: 900px;">
            <p style="margin-bottom: 1rem; color: var(--lighter-grey);">
                This page demonstrates how to use the WYSIWYG editor. 
                The editor is included from <code>/editor/wysiwyg_editor.php</code>.
                The content you create will be submitted via a form, and the raw HTML will be displayed below.
            </p>
            
            <form action="editor_test.php" method="post">
                <div style="margin-bottom: 1rem;">
                    <label for="editor_content" class="section-title" style="border: none; padding-bottom: 0.5rem; font-size: 1.1rem;">Editor Content</label>
                    <?php 
                    // To use the editor, you just need to include the file.
                    // You can optionally pass variables to it.
                    // $textarea_name = 'my_custom_name'; // To change the textarea name attribute
                    // $initial_content = '<p>This is some <b>initial content</b>.</p>'; // To load existing HTML content
                    require 'editor/wysiwyg_editor.php'; 
                    ?>
                </div>
                
                <button type="submit" class="btn btn-primary">Submit Content</button>
            </form>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editor_content'])): ?>
            <div class="profile-section" style="width: 100%; max-width: 900px; margin-top: 2rem;">
                <h2 class="section-title">Submitted Content (Raw HTML)</h2>
                <pre style="background-color: var(--card-bg-3); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-1); white-space: pre-wrap; word-wrap: break-word; color: var(--lighter-grey);"><?php echo htmlspecialchars($_POST['editor_content']); ?></pre>

                <h2 class="section-title" style="margin-top: 2rem;">Rendered Content</h2>
                <div class="wysiwyg-content" style="border: 1px solid var(--border-1); padding: 1rem; border-radius: 8px;">
                    <?php echo $_POST['editor_content']; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
// We don't include footer here to have a cleaner test page.
// require_once BASE_PATH . '/src/includes/footer.php';
?> 