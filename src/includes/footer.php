</div> <?php // .site-container kapanışı ?>
<?php
// src/includes/footer.php - Site Footer

// Mevcut yılı al
$current_year = date('Y');

// Site bilgileri (isteğe bağlı olarak database'den de alınabilir)
$site_name = "Ilgarion Turanis";
$discord_link = "https://discord.gg/aBsfF5e7EV"; // Discord sunucu linki
$youtube_link = "https://www.youtube.com/@melkan5/videos"; // YouTube kanalı
$twitter_link = "#"; // Twitter/X hesabı
?>

<footer class="main-footer">
    <div class="footer-container">
        <!-- Sol taraf - Site bilgileri -->
        <div class="footer-left">
            <div class="footer-logo">
                <img src="/assets/logo.png" alt="<?= htmlspecialchars($site_name) ?> Logo" class="footer-logo-img">
                <span class="footer-site-name"><?= htmlspecialchars($site_name) ?></span>
            </div>
            <p class="footer-description">
                Star Citizen Türk Topluluğu - Evrenin En İyi Pilotları
            </p>
        </div>

        <!-- Orta - Hızlı Linkler -->
        <div class="footer-center">
            <div class="footer-links">
                <a href="/forum/" class="footer-link">
                    <i class="fas fa-comments"></i> Forum
                </a>
                <a href="/gallery/" class="footer-link">
                    <i class="fas fa-images"></i> Galeri
                </a>
                <a href="/events/" class="footer-link">
                    <i class="fas fa-calendar"></i> Etkinlikler
                </a>
            </div>
        </div>

        <!-- Sağ taraf - Sosyal medya ve telif hakkı -->
        <div class="footer-right">
            <div class="footer-social">
                <a href="<?= htmlspecialchars($discord_link) ?>" class="social-link discord" title="Discord" target="_blank">
                    <i class="fab fa-discord"></i>
                </a>
                <a href="<?= htmlspecialchars($youtube_link) ?>" class="social-link youtube" title="YouTube" target="_blank">
                    <i class="fab fa-youtube"></i>
                </a>
                <a href="<?= htmlspecialchars($twitter_link) ?>" class="social-link twitter" title="Twitter" target="_blank">
                    <i class="fab fa-twitter"></i>
                </a>
            </div>
            <div class="footer-copyright">
                <p>&copy; <?= $current_year ?> <?= htmlspecialchars($site_name) ?></p>
                <p class="footer-subtitle">Made with <i class="fas fa-heart"></i> for Citizens</p>
            </div>
        </div>
    </div>
</footer>

<style>
/* Footer Stilleri */
.main-footer {
    background-color: var(--tranparent-gold);
    height: 100px;
    color: var(--gold);
    border-top: 1px solid var(--border-1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: auto;
    position: relative;
    overflow: hidden;
}

.main-footer::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, 
        transparent 0%, 
        var(--gold) 20%, 
        var(--light-gold) 50%, 
        var(--gold) 80%, 
        transparent 100%
    );
}

.footer-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    max-width: 1600px;
    gap: 2rem;
}

/* Sol Taraf - Logo ve Site Bilgileri */
.footer-left {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex: 1;
}

.footer-logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.25rem;
}

.footer-logo-img {
    width: 35px;
    height: 35px;
    object-fit: contain;
}

.footer-site-name {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--gold);
}

.footer-description {
    font-size: 0.8rem;
    color: var(--light-grey);
    margin: 0;
    line-height: 1.3;
}

/* Orta - Hızlı Linkler */
.footer-center {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 1;
}

.footer-links {
    display: flex;
    gap: 1.5rem;
    align-items: center;
}

.footer-link {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--gold);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    padding: 0.4rem 0.8rem;
    border-radius: 15px;
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.footer-link:hover {
    color: var(--turquase);
    text-decoration: none;
    background: var(--transparent-turquase);
    border-color: var(--turquase);
    transform: translateY(-1px);
}

.footer-link i {
    font-size: 0.9rem;
}

/* Sağ Taraf - Sosyal Medya ve Telif Hakkı */
.footer-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
    flex: 1;
}

.footer-social {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    margin-bottom: 0.25rem;
}

.social-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    color: var(--light-grey);
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid var(--border-1);
    background: var(--card-bg);
}

.social-link:hover {
    transform: translateY(-2px) scale(1.1);
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.social-link.discord:hover {
    background: #5865F2;
    color: var(--white);
    border-color: #5865F2;
}

.social-link.youtube:hover {
    background: #FF0000;
    color: var(--white);
    border-color: #FF0000;
}

.social-link.twitter:hover {
    background: #1DA1F2;
    color: var(--white);
    border-color: #1DA1F2;
}

.footer-copyright {
    text-align: right;
}

.footer-copyright p {
    margin: 0;
    font-size: 0.75rem;
    color: var(--light-grey);
    line-height: 1.2;
}

.footer-subtitle {
    color: var(--gold) !important;
    font-weight: 500;
}

.footer-subtitle i {
    color: var(--red);
}

/* Responsive Design */
@media (max-width: 968px) {
    .footer-container {
        flex-direction: column;
        gap: 1rem;
        padding: 1rem;
        text-align: center;
    }
    
    .main-footer {
        height: auto;
        min-height: 100px;
        padding: 1rem 0;
    }
    
    .footer-left,
    .footer-right {
        align-items: center;
        text-align: center;
    }
    
    .footer-links {
        flex-wrap: wrap;
        gap: 1rem;
        justify-content: center;
    }
    
    .footer-social {
        justify-content: center;
    }
    
    .footer-copyright {
        text-align: center;
    }
}

@media (max-width: 768px) {
    .footer-container {
        padding: 0.75rem;
    }
    
    .footer-links {
        gap: 0.75rem;
    }
    
    .footer-link {
        font-size: 0.8rem;
        padding: 0.3rem 0.6rem;
    }
    
    .footer-logo-img {
        width: 30px;
        height: 30px;
    }
    
    .footer-site-name {
        font-size: 1.1rem;
    }
    
    .social-link {
        width: 28px;
        height: 28px;
    }
}

@media (max-width: 480px) {
    .footer-links {
        grid-template-columns: repeat(2, 1fr);
        display: grid;
        gap: 0.5rem;
        width: 100%;
    }
    
    .footer-link {
        justify-content: center;
    }
}

/* Animasyonlar */
@keyframes footerGlow {
    0% { opacity: 0.5; }
    50% { opacity: 1; }
    100% { opacity: 0.5; }
}

.main-footer::before {
    animation: footerGlow 3s ease-in-out infinite;
}

/* Dark Theme Enhancements */
@media (prefers-color-scheme: dark) {
    .main-footer {
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
    }
    
    .social-link {
        backdrop-filter: blur(10px);
    }
}

/* Scroll-to-top button (opsiyonel) */
.scroll-to-top {
    position: fixed;
    bottom: 120px;
    right: 2rem;
    width: 45px;
    height: 45px;
    background: var(--gold);
    color: var(--charcoal);
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 100;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.scroll-to-top.visible {
    opacity: 1;
    visibility: visible;
}

.scroll-to-top:hover {
    background: var(--light-gold);
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
}

@media (max-width: 768px) {
    .scroll-to-top {
        bottom: 140px;
        right: 1rem;
        width: 40px;
        height: 40px;
    }
}
</style>

<!-- Scroll to Top Button (Opsiyonel) -->
<button class="scroll-to-top" id="scrollToTop" onclick="scrollToTop()" title="Yukarı Çık">
    <i class="fas fa-chevron-up"></i>
</button>

<script>
// Scroll to Top Functionality
window.addEventListener('scroll', function() {
    const scrollToTopBtn = document.getElementById('scrollToTop');
    if (window.pageYOffset > 300) {
        scrollToTopBtn.classList.add('visible');
    } else {
        scrollToTopBtn.classList.remove('visible');
    }
});

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Footer link hover effects
document.querySelectorAll('.footer-link').forEach(link => {
    link.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-1px) scale(1.05)';
    });
    
    link.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(-1px)';
    });
});
</script>

</body>
</html>