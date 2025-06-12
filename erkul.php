<?php
// erkul.php - Erkul Games Calculator Iframe Page

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Proje ana dizinini belirle
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

require_once 'src/config/database.php';
require_once 'src/functions/auth_functions.php';
require_once 'src/functions/role_functions.php';

$page_title = "Erkul Games Calculator";

require_once 'src/includes/header.php';
require_once 'src/includes/navbar.php';

?>

<style>
    .erkul-container {
        position: relative;
        width: 100%;
        padding-top: .5rem;
    }
    .erkul-iframe-wrapper {
        width: 100%;
        height: calc(100vh - var(--navbar-height) - 120px); /* Navbar ve alt/üst boşluk payı */
        border: 1px solid var(--border-1);
        border-radius: 12px;
        overflow: hidden;
        background: var(--card-bg);
    }
    .erkul-iframe {
        width: 100%;
        height: 100%;
        border: none;
    }
    .page-title-h1 {
        position: absolute;
        top: 2.55%;
        left: 44%;
        transform: translateX(-50%);
        margin: 0 0 1.5rem 0;
        font-size: 1.5rem;
        color:  #FFA537;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding-bottom: 1rem;
    }
    .page-title-h1 a {
        color: #FFA537;
        text-decoration: none;
    }
</style>

<div class="site-container">
    <div class="erkul-container">
        <h1 class="page-title-h1"><i class="fas fa-calculator"></i><a href="https://www.erkul.games/live/calculator" target="_blank">Erkul.games</a></h1>
        <div class="erkul-iframe-wrapper">
            <iframe src="https://www.erkul.games/live/calculator" 
                    class="erkul-iframe"
                    title="Erkul Games DPS Calculator">
            </iframe>
        </div>
    </div>
</div>

<?php
require_once 'src/includes/footer.php';
?> 