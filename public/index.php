<?php
require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$page_title = "Ana Sayfa - ILGARION TURANIS";

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<main class="main-content">
    <section class="hero-section">
        <div class="hero-left">
            <h1>ILGARION TURANIS</h1>
            <p class="hero-subtitle">" Gerçekçilik, Takım Ruhu ve Taktiksel Üstünlük. "</p>
            <div class="hero-links">
                <a href="<?php echo get_auth_base_url(); ?>/events.php" class="btn">Yaklaşan Etkinlikleri Gör</a>
                <?php if (!is_user_logged_in()): ?>
                    <a href="<?php echo get_auth_base_url(); ?>/register.php" class="btn btn-hero-action">Hemen Katıl!</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-right">
            <img class="scg-logo" src="/assets/scg-logo.png" alt="">
            <img class="scg-loga" style="" src="/assets/ilgarion-banner-logo.png" alt="">
        </div>
    </section>

    <section class="main-content-section">
        <h2>Son Duyurular ve Etkinlikler</h2>
        <p>Henüz bir duyuru bulunmamaktadır. Yakında burada önemli duyurular, güncel etkinlikler ve
            fraksiyonumuzla ilgili daha fazla bilgi yer alacaktır.</p>
    </section>

    <section class="about-section">
        <div class="about-section-text">
            <h3>Hakkımızda</h3>
            <p>
                Ilgarion Turanis: Gerçekliğin ve Taktiğin Buluşma Noktası<br><br>

                Star Citizen evreni, sonsuz fırsatlar ve tehlikelerle dolu bir uzay simülasyonundan çok daha
                fazlası.<br>
                Bu karmaşık sistemin karanlık yüzeylerinde, disiplinli ekip çalışması ve stratejik hareket kabiliyeti
                fark yaratır.<br>
                Tam da bu noktada Ilgarion Turanis sahneye çıkıyor.<br><br>

                Ilgarion Turanis; taktiksel düşünceyi, ekip koordinasyonunu ve gerçekçilik odaklı oynanışı merkezine
                alan bir SCG Fraksiyonudur.<br>
                Ana hedefimiz; yer görevleri, bunker operasyonları, keşif faaliyetleri ve taaruz temelli senaryolarda
                profesyonelce hareket eden, ne yaptığını bilen bir ekip yapısı oluşturmaktır.<br><br>

                Her operasyon bir planla başlar.<br>
                Her adım disiplinle atılır.<br>
                Her görev, yalnızca tamamlanmak için değil, mükemmel icra edilmek için yürütülür.<br>
                Bizimle birlikte görev alan her pilot, rastgele değil; bilinçli, hazırlıklı ve birbirine bağlı bir ekip
                yapısının parçası olur.<br><br>

                Eğer sen de:<br><br>

                Koordineli hareket etmeyi seviyor,<br>
                Gerçekçi ve planlı yer görevlerinden keyif alıyor,<br>
                Takım ruhunu bireyselliğin önüne koyuyorsan;<br><br>

                Ilgarion Turanis seni bekliyor.<br><br>

                <strong class="last-sentence">“Biz farkı şansa bırakmayız. Ilgarion’da her birey, görevle bütünleşmiş bir etki unsurudur. Bize katıl ve sen de farkını yarat!”</strong>
            </p>

        </div>
        <div class="main-card-container">
            <h3>Temas Noktaları</h3>
            <hr class="index-hr">
            <div class="card"><img src="/assets/rsi-logo.png" alt=""></div>
            <div class="card"><i class="fa-brands fa-youtube"></i></div>
            <div class="card"><i class="fa-brands fa-discord"></i></div>
            <div class="card"><i class="fa-brands fa-instagram"></i><div>
        </div>
        </section>
</main>

<?php
require_once BASE_PATH . '/src/includes/footer.php';
?>