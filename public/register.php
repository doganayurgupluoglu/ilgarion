<?php
// public/auth.php - Entegre Login/Register Sayfası

require_once '../src/config/database.php';
require_once BASE_PATH . '/src/functions/auth_functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Zaten giriş yapmış kullanıcıları ana sayfaya yönlendir
if (is_user_logged_in()) {
    header('Location: ' . get_auth_base_url() . '/index.php');
    exit;
}

// URL parametresinden hangi form gösterileceğini belirle
$auth_mode = $_GET['mode'] ?? 'login'; // 'login' veya 'register'

if (!in_array($auth_mode, ['login', 'register'])) {
    $auth_mode = 'login';
}

$page_title = $auth_mode === 'login' ? "Giriş Yap - Ilgarion Turanis" : "Kayıt Ol - Ilgarion Turanis";

// Form verilerini session'dan al (hata durumunda)
$form_data = $_SESSION['form_input'] ?? [];
unset($_SESSION['form_input']); // Temizle

// CSRF token oluştur
$csrf_token = generate_csrf_token();

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<style>
/* Modern Auth Page Styles - Ultra Modern & Professional */
.auth-page-wrapper {
    min-height: calc(100vh - 170px);
    width: 100%;
    background: linear-gradient(135deg, var(--charcoal) 0%, #1a1a1a 25%, var(--grey) 50%, #1a1a1a 75%, var(--charcoal) 100%);
    position: relative;
    overflow: hidden;
}

.auth-page-wrapper::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(circle at 20% 20%, rgba(189, 145, 42, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(42, 189, 168, 0.08) 0%, transparent 50%),
        radial-gradient(circle at 40% 40%, rgba(189, 145, 42, 0.05) 0%, transparent 50%);
    animation: backgroundShift 20s ease-in-out infinite;
}

@keyframes backgroundShift {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.auth-container {
    display:flex;
    align-items: center;
    justify-content: center;
    background: none;
    border: none;
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
    position: relative;
    z-index: 2;
}

.auth-content {
    display: grid;
    min-height: calc(100vh - 170px);
    min-width: 1400px;

    grid-template-columns: 1fr 480px;
    gap: 4rem;
    align-items: center;
}

.auth-info {
    color: var(--lighter-grey);
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.auth-welcome {
    font-size: 3rem;
    font-weight: 200;
    color: var(--gold);
    margin-bottom: 1.5rem;
    line-height: 1.2;
    letter-spacing: -1px;
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.auth-welcome .highlight {
    background: linear-gradient(135deg, var(--gold), var(--light-gold));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 300;
}

.auth-subtitle {
    font-size: 1.2rem;
    color: var(--light-grey);
    margin-bottom: 2.5rem;
    line-height: 1.6;
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.auth-features {
    list-style: none;
    padding: 0;
    margin: 0;
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.auth-features li {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
    font-size: 1rem;
    color: var(--lighter-grey);
    opacity: 0;
    transform: translateX(-20px);
    animation: slideInLeft 0.6s ease-out forwards;
}

.auth-features li:nth-child(1) { animation-delay: 0.1s; }
.auth-features li:nth-child(2) { animation-delay: 0.2s; }
.auth-features li:nth-child(3) { animation-delay: 0.3s; }
.auth-features li:nth-child(4) { animation-delay: 0.4s; }
.auth-features li:nth-child(5) { animation-delay: 0.5s; }
.auth-features li:nth-child(6) { animation-delay: 0.6s; }

.auth-features li::before {
    content: '✦';
    color: var(--gold);
    font-size: 1.2rem;
    margin-right: 1rem;
    width: 20px;
    text-align: center;
}

@keyframes slideInLeft {
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.auth-card {
    min-height: calc(100vh - 200px);
    max-height: calc(100vh - 200px);
        background: linear-gradient(135deg, var(--charcoal), var(--darker-gold-2));

    border: 1px solid var(--darker-gold-1);
    border-radius: 16px;
    padding: 2.5rem;
    backdrop-filter: blur(20px);
    box-shadow: 
        0 20px 40px rgba(0, 0, 0, 0.3),
        0 0 0 1px rgba(189, 145, 42, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 0.05);
    position: relative;
    overflow: hidden;
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.auth-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
    opacity: 0.6;
}

.auth-card-header {
    text-align: center;
    margin-bottom: 2rem;
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.auth-logo {
    width: 60px;
    height: 60px;
    margin: 0 auto 1rem;
    background-image: url('../assets/logo.png');
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    border-radius: 50%;
    border: 2px solid var(--gold);
    box-shadow: 0 0 20px rgba(189, 145, 42, 0.3);
    transition: all 0.3s ease;
}

.auth-logo:hover {
    transform: scale(1.05);
    box-shadow: 0 0 30px rgba(189, 145, 42, 0.5);
}

.auth-title {
    color: var(--gold);
    font-size: 1.6rem;
    font-weight: 300;
    margin: 0 0 0.5rem 0;
    letter-spacing: -0.5px;
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.auth-card-subtitle {
    color: var(--light-grey);
    font-size: 0.9rem;
    margin: 0;
    opacity: 0.8;
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Form Container with Animation */
.auth-forms-container {
    position: relative;
    overflow: hidden;
    min-height: 400px;
}

.auth-form {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    opacity: 0;
    transform: translateX(100%);
    pointer-events: none;
}

.auth-form.active {
    opacity: 1;
    transform: translateX(0);
    pointer-events: all;
    position: relative;
}

.auth-form.exit-left {
    transform: translateX(-100%);
    opacity: 0;
}

.auth-form.enter-right {
    transform: translateX(100%);
    opacity: 0;
}

/* Form Navigation */
.form-navigation {
    display: flex;
    justify-content: center;
    margin-bottom: 2rem;
    background: rgba(66, 66, 66, 0.3);
    border-radius: 8px;
    padding: 0.25rem;
    position: relative;
}

.nav-btn {
    flex: 1;
    padding: 0.75rem 1.5rem;
    background: transparent;
    border: none;
    color: var(--light-grey);
    cursor: pointer;
    transition: all 0.3s ease;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    position: relative;
    z-index: 2;
}

.nav-btn.active {
    color: var(--charcoal);
    background: var(--gold);
    box-shadow: 0 2px 8px rgba(189, 145, 42, 0.3);
}

.nav-btn:hover:not(.active) {
    color: var(--lighter-grey);
    background: rgba(66, 66, 66, 0.5);
}

.form-group {
    margin-bottom: 1.5rem;
    position: relative;
    opacity: 0;
    transform: translateY(20px);
    animation: slideInUp 0.6s ease-out forwards;
}

.form-group:nth-child(1) { animation-delay: 0.1s; }
.form-group:nth-child(2) { animation-delay: 0.2s; }
.form-group:nth-child(3) { animation-delay: 0.3s; }
.form-group:nth-child(4) { animation-delay: 0.4s; }
.form-group:nth-child(5) { animation-delay: 0.5s; }

@keyframes slideInUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-label {
    display: block;
    color: var(--lighter-grey);
    font-size: 0.9rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
    transition: color 0.2s ease;
}

.form-input-wrapper {
    position: relative;
}

.form-input {
    width: 100%;
    padding: 1rem 1.25rem;
    background: rgba(66, 66, 66, 0.6);
    border: 2px solid transparent;
    border-radius: 8px;
    color: var(--lighter-grey);
    font-size: 0.95rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-sizing: border-box;
    backdrop-filter: blur(10px);
}

.form-input:focus {
    outline: none;
    background: rgba(34, 34, 34, 0.9);
    border-color: var(--gold);
    box-shadow: 
        0 0 0 3px rgba(189, 145, 42, 0.1),
        0 8px 25px rgba(189, 145, 42, 0.15);
    transform: translateY(-1px);
}

.form-input::placeholder {
    color: var(--light-grey);
    opacity: 0.7;
}

.form-input.error {
    border-color: #e74c3c;
    background: rgba(231, 76, 60, 0.05);
    box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
}

.form-input.success {
    border-color: var(--turquase);
    background: rgba(42, 189, 168, 0.05);
    box-shadow: 0 0 0 3px rgba(42, 189, 168, 0.1);
}

.form-input-icon {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--light-grey);
    opacity: 0;
    transition: all 0.3s ease;
    pointer-events: none;
}

.form-input.success ~ .form-input-icon.success-icon {
    opacity: 1;
    color: var(--turquase);
}

.form-input.error ~ .form-input-icon.error-icon {
    opacity: 1;
    color: #e74c3c;
}

.form-note {
    font-size: 0.8rem;
    color: var(--light-grey);
    margin-top: 0.4rem;
    line-height: 1.4;
    opacity: 0.8;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.password-strength-meter {
    margin-top: 0.5rem;
    height: 4px;
    background: rgba(66, 66, 66, 0.6);
    border-radius: 2px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.password-strength-fill {
    height: 100%;
    width: 0%;
    transition: all 0.3s ease;
    border-radius: 2px;
}

.password-strength-weak .password-strength-fill {
    width: 33%;
    background: #e74c3c;
}

.password-strength-medium .password-strength-fill {
    width: 66%;
    background: #f39c12;
}

.password-strength-strong .password-strength-fill {
    width: 100%;
    background: var(--turquase);
}

.password-requirements {
    margin-top: 1rem;
    padding: 1rem;
    background: rgba(42, 189, 168, 0.05);
    border-radius: 8px;
    border-left: 3px solid var(--turquase);
    font-size: 0.8rem;
    color: var(--light-grey);
}

.password-requirements-title {
    color: var(--turquase);
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: block;
}

.password-requirements ul {
    margin: 0;
    padding-left: 1.2rem;
    list-style-type: none;
}

.password-requirements li {
    margin-bottom: 0.3rem;
    position: relative;
    padding-left: 1rem;
}

.password-requirements li::before {
    content: '•';
    color: var(--turquase);
    position: absolute;
    left: 0;
}

.btn-auth {
    width: 100%;
    padding: 1.2rem 2rem;
    background: linear-gradient(135deg, var(--gold) 0%, var(--light-gold) 100%);
    color: var(--charcoal);
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    margin: 1.5rem 0;
    position: relative;
    overflow: hidden;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-auth::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.btn-auth:hover {
    transform: translateY(-2px);
    box-shadow: 
        0 12px 30px rgba(189, 145, 42, 0.4),
        0 0 0 1px rgba(189, 145, 42, 0.2);
    background: linear-gradient(135deg, var(--light-gold) 0%, var(--gold) 100%);
}

.btn-auth:hover::before {
    left: 100%;
}

.btn-auth:active {
    transform: translateY(0);
}

.btn-auth:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.btn-auth.loading {
    pointer-events: none;
}

.btn-auth.loading::after {
    content: "";
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top: 2px solid var(--charcoal);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: translateY(-50%) rotate(0deg); }
    100% { transform: translateY(-50%) rotate(360deg); }
}

.auth-links {
    text-align: center;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(189, 145, 42, 0.2);
}

.auth-link {
    color: var(--turquase);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.2s ease;
    position: relative;
    cursor: pointer;
}

.auth-link::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 1px;
    background: var(--turquase);
    transition: width 0.3s ease;
}

.auth-link:hover {
    color: var(--light-gold);
    transform: translateY(-1px);
}

.auth-link:hover::after {
    width: 100%;
    background: var(--light-gold);
}

.alert {
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    line-height: 1.5;
    border: 1px solid;
    position: relative;
    animation: slideInDown 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.alert::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    border-radius: 2px 0 0 2px;
}

.alert-danger {
    background: rgba(231, 76, 60, 0.1);
    border-color: rgba(231, 76, 60, 0.3);
    color: #e74c3c;
}

.alert-danger::before {
    background: #e74c3c;
}

.alert-success {
    background: rgba(42, 189, 168, 0.1);
    border-color: rgba(42, 189, 168, 0.3);
    color: var(--turquase);
}

.alert-success::before {
    background: var(--turquase);
}


.alert-info::before {
    background: #3498db;
}

.floating-elements {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    overflow: hidden;
}

.floating-element {
    position: absolute;
    background: rgba(189, 145, 42, 0.1);
    border-radius: 50%;
    animation: float 20s infinite ease-in-out;
}

.floating-element:nth-child(1) {
    width: 60px;
    height: 60px;
    top: 20%;
    left: 10%;
    animation-delay: 0s;
}

.floating-element:nth-child(2) {
    width: 40px;
    height: 40px;
    top: 60%;
    right: 15%;
    animation-delay: 7s;
}

.floating-element:nth-child(3) {
    width: 80px;
    height: 80px;
    bottom: 20%;
    left: 20%;
    animation-delay: 14s;
}

@keyframes float {
    0%, 100% {
        transform: translateY(0) rotate(0deg);
        opacity: 0.3;
    }
    33% {
        transform: translateY(-20px) rotate(120deg);
        opacity: 0.6;
    }
    66% {
        transform: translateY(20px) rotate(240deg);
        opacity: 0.4;
    }
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Remember Me Checkbox */
.checkbox-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 1rem 0;
}

.checkbox-input {
    width: 18px;
    height: 18px;
    accent-color: var(--gold);
    cursor: pointer;
}

.checkbox-label {
    font-size: 0.9rem;
    color: var(--light-grey);
    cursor: pointer;
}

.forgot-password {
    text-align: right;
    margin-top: 0.5rem;
}

.forgot-password .auth-link {
    font-size: 0.85rem;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .auth-content {
        grid-template-columns: 1fr;
        gap: 2rem;
        text-align: center;
    }
    
    .auth-info {
        order: 2;
    }
    
    .auth-card {
        order: 1;
        max-width: 500px;
        margin: 0 auto;
    }
}

@media (max-width: 768px) {
    .auth-page-wrapper {
        padding: 2rem 0;
    }
    
    .auth-container {
        padding: 0 1rem;
    }
    
    .auth-welcome {
        font-size: 2.2rem;
    }
    
    .auth-card {
        padding: 2rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
}

@media (max-width: 480px) {
    .auth-welcome {
        font-size: 1.8rem;
    }
    
    .auth-card {
        padding: 1.5rem;
        border-radius: 12px;
    }
    
    .form-input {
        padding: 0.875rem 1rem;
    }
    
    .btn-auth {
        padding: 1rem 1.5rem;
    }
    
    .nav-btn {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
    }
}
/* Gelişmiş Alert Stilleri - Register.php için */
.alert {
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    line-height: 1.5;
    border: 1px solid;
    position: relative;
    animation: slideInDown 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.alert::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    border-radius: 2px 0 0 2px;
}

.alert i.fas {
    font-size: 1.1em;
    margin-top: 0.1rem;
    flex-shrink: 0;
}

.alert-content {
    flex: 1;
}

/* Danger Alert - Kırmızı */
.alert-danger {
    background: rgba(231, 76, 60, 0.1);
    border-color: rgba(231, 76, 60, 0.3);
    color: #e74c3c;
}

.alert-danger::before {
    background: #e74c3c;
}

.alert-danger i.fas {
    color: #e74c3c;
}

/* Success Alert - Yeşil */
.alert-success {
    background: rgba(42, 189, 168, 0.1);
    border-color: rgba(42, 189, 168, 0.3);
    color: var(--turquase);
}

.alert-success::before {
    background: var(--turquase);
}

.alert-success i.fas {
    color: var(--turquase);
}

/* Info Alert - Mavi */
.alert-info {
    background: rgba(52, 152, 219, 0.1);
    border-color: rgba(52, 152, 219, 0.3);
    color: #3498db;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.alert-info::before {
    background: #3498db;
}

.alert-info i.fas {
    color: #3498db;
}

/* Warning Alert - Turuncu */
.alert-warning {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-direction: column;
    background: rgba(243, 156, 18, 0.1);
    border-color: rgba(243, 156, 18, 0.3);
    color: #f39c12;
}

.alert-warning::before {
    background: #f39c12;
}

.alert-warning i.fas {
    color: #f39c12;
}

/* Alert içindeki linkler */
.alert .auth-link {
    color: inherit;
    text-decoration: underline;
    font-weight: 500;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.alert .auth-link:hover {
    opacity: 0.8;
    transform: translateY(-1px);
}

.alert .auth-link i.fas {
    font-size: 0.9em;
}

/* Alert başlıkları */
.alert strong {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    font-size: 1rem;
}

/* Alert küçük metinler */
.alert small {
    opacity: 0.8;
    font-size: 0.8rem;
    margin-top: 0.5rem;
    display: block;
}

/* Animasyon */
@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Alert action butonları */
.alert-actions {
    margin-top: 1rem;
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
}

.alert-actions .auth-link {
    padding: 0.4rem 0.8rem;
    border-radius: 4px;
    border: 1px solid currentColor;
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 0.85rem;
}

.alert-actions .auth-link:hover {
    background: currentColor;
    color: var(--charcoal);
    transform: none;
}

/* Responsive */
@media (max-width: 768px) {
    .alert {
        padding: 0.875rem 1rem;
        font-size: 0.85rem;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .alert i.fas {
        align-self: flex-start;
        margin-top: 0;
    }
    
    .alert-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
    
    .alert-actions .auth-link {
        text-align: center;
        justify-content: center;
    }
}

/* Özel durum alert'leri için ek stiller */
.alert-account-status {
    border-width: 2px;
    font-weight: 500;
}

.alert-account-status strong {
    font-size: 1.1rem;
    color: inherit;
}

/* Divider için stil */
.alert-divider {
    color: rgba(255, 255, 255, 0.3);
    margin: 0 0.5rem;
}
</style>

<main class="main-content">
    <div class="auth-page-wrapper">
        <!-- Floating Background Elements -->
        <div class="floating-elements">
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
        </div>

        <div class="auth-container">
            <div class="auth-content">
                <!-- Welcome Section -->
                <div class="auth-info">
                    <h1 class="auth-welcome">
                        <span class="highlight">Ilgarion Turanis</span><br>
                        <span id="welcomeText"><?php echo $auth_mode === 'login' ? 'Hoş Geldiniz' : 'Topluluğuna Katılın'; ?></span>
                    </h1>
                    <p class="auth-subtitle" id="subtitleText">
                        <?php echo $auth_mode === 'login' 
                            ? 'Hesabınıza giriş yapın ve galaksideki arkadaşlarınızla buluşun.' 
                            : 'Star Citizen evreninde büyük maceralar yaşayın. Deneyimli pilotlarla birlikte galaksiyi keşfedin.'; ?>
                    </p>
                    
                    <ul class="auth-features">
                        <li>Profesyonel ve organize topluluk</li>
                        <li>Düzenli etkinlikler ve operasyonlar</li>
                        <li>Kapsamlı rehberler ve kaynaklar</li>
                        <li>Deneyimli pilotlardan mentorluk</li>
                        <li>Aktif Discord topluluğu</li>
                        <li>PvE ve PvP içerikleri</li>
                    </ul>
                </div>

                <!-- Auth Card -->
                <div class="auth-card">
                    <div class="auth-card-header">
                        <div class="auth-logo"></div>
                        <h2 class="auth-title" id="cardTitle"><?php echo $auth_mode === 'login' ? 'Giriş Yap' : 'Kayıt Ol'; ?></h2>
                        <p class="auth-card-subtitle" id="cardSubtitle"><?php echo $auth_mode === 'login' ? 'Hesabınıza erişin' : 'Yeni hesap oluşturun'; ?></p>
                    </div>

                    <!-- Form Navigation -->
                    <div class="form-navigation">
                        <button class="nav-btn <?php echo $auth_mode === 'login' ? 'active' : ''; ?>" data-form="login">
                            Giriş Yap
                        </button>
                        <button class="nav-btn <?php echo $auth_mode === 'register' ? 'active' : ''; ?>" data-form="register">
                            Kayıt Ol
                        </button>
                    </div>

                     <!-- Hata ve Başarı Mesajları - GENİŞLETİLMİŞ -->
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['info_message'])): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <?php echo $_SESSION['info_message']; unset($_SESSION['info_message']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['warning_message'])): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $_SESSION['warning_message']; unset($_SESSION['warning_message']); ?>
                        </div>
                    <?php endif; ?>

                    <!-- URL Status Parametrelerine Göre Özel Mesajlar -->
                    <?php if (isset($_GET['status'])): ?>
                        <?php 
                        $status = $_GET['status'];
                        $mode = $_GET['mode'] ?? 'login';
                        ?>
                        
                        <?php if ($status === 'pending_approval'): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-clock"></i>
                                <strong>Hesap Onay Bekliyor</strong><br>
                                <?php if ($mode === 'login'): ?>
                                    <p>Hesabınız henüz admin tarafından onaylanmamış. Onaylandıktan sonra giriş yapabilirsiniz.<br></p>
                                <?php else: ?>
                                    Kayıt başarılı! Hesabınız oluşturuldu ve admin onayı bekliyor. Onaylandıktan sonra giriş yapabilirsiniz.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($status === 'account_rejected'): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-times-circle"></i>
                                <strong>Hesap Reddedildi</strong><br>
                                Hesabınız yönetici tarafından reddedilmiştir. Giriş yapamazsınız.<br>
                                <div style="margin-top: 1rem;">
                                    <a href="?mode=register" class="auth-link">
                                        <i class="fas fa-user-plus"></i> Yeni Hesap Oluştur
                                    </a>
                                    <span style="margin: 0 1rem;">|</span>
                                    <a href="mailto:admin@ilgarionturanis.com" class="auth-link">
                                        <i class="fas fa-envelope"></i> Yönetici ile İletişime Geç
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($status === 'account_suspended'): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-ban"></i>
                                <strong>Hesap Askıya Alındı</strong><br>
                                Hesabınız geçici olarak askıya alınmıştır. Giriş yapamazsınız.<br>
                                <div style="margin-top: 1rem;">
                                    <a href="mailto:admin@ilgarionturanis.com" class="auth-link">
                                        <i class="fas fa-envelope"></i> Yönetici ile İletişime Geçin
                                    </a>
                                    <a href="?mode=register" class="auth-link" style="margin-left: .3rem;">
                                        <i class="fas fa-user-plus"></i> Yeni Hesap Oluştur
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($status === 'logged_out'): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-sign-out-alt"></i>
                                <strong>Çıkış Yapıldı</strong><br>
                                Güvenlik nedeniyle oturumunuz sonlandırıldı. Tekrar giriş yapabilirsiniz.
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($status === 'login_required'): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-lock"></i>
                                <strong>Giriş Gerekli</strong><br>
                                Bu işlemi yapmak için giriş yapmanız gerekmektedir.
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($status === 'session_expired'): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-clock"></i>
                                <strong>Oturum Süresi Doldu</strong><br>
                                Güvenlik nedeniyle oturumunuz sonlandırıldı. Lütfen tekrar giriş yapın.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="auth-forms-container">
                        <!-- Login Form -->
                        <form action="../src/actions/handle_login.php" 
                              method="POST" 
                              id="loginForm" 
                              class="auth-form <?php echo $auth_mode === 'login' ? 'active' : ''; ?>" 
                              novalidate>
                            
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                            <div class="form-group">
                                <label for="login_username" class="form-label">Kullanıcı Adı veya E-posta *</label>
                                <div class="form-input-wrapper">
                                    <input type="text" 
                                           id="login_username" 
                                           name="username" 
                                           class="form-input" 
                                           placeholder="Kullanıcı adı veya e-posta adresiniz"
                                           required
                                           autocomplete="username">
                                    <i class="fas fa-check form-input-icon success-icon"></i>
                                    <i class="fas fa-times form-input-icon error-icon"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="login_password" class="form-label">Şifre *</label>
                                <div class="form-input-wrapper">
                                    <input type="password" 
                                           id="login_password" 
                                           name="password" 
                                           class="form-input" 
                                           placeholder="Şifreniz"
                                           required
                                           autocomplete="current-password">
                                    <i class="fas fa-check form-input-icon success-icon"></i>
                                    <i class="fas fa-times form-input-icon error-icon"></i>
                                </div>
                                <div class="forgot-password">
                                    <a href="#" class="auth-link">Şifremi Unuttum</a>
                                </div>
                            </div>

                            <div class="checkbox-group">
                                <input type="checkbox" id="remember_me" name="remember_me" class="checkbox-input">
                                <label for="remember_me" class="checkbox-label">Beni Hatırla</label>
                            </div>

                            <button type="submit" class="btn-auth" id="loginBtn">
                                Giriş Yap
                            </button>
                        </form>

                        <!-- Register Form -->
                       <!-- Register Form - DÜZELTİLMİŞ -->
<form action="../src/actions/handle_register.php" 
      method="POST" 
      id="registerForm" 
      class="auth-form <?php echo $auth_mode === 'register' ? 'active' : ''; ?>" 
      novalidate>
    
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    
    <!-- Honeypot field - Bot koruması -->
    <input type="text" name="honeypot" style="display:none !important;" tabindex="-1" autocomplete="off">

    <div class="form-group">
        <label for="username" class="form-label">Kullanıcı Adı *</label>
        <div class="form-input-wrapper">
            <input type="text" 
                   id="username" 
                   name="username" 
                   class="form-input" 
                   value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>"
                   placeholder="Benzersiz kullanıcı adınız"
                   required
                   minlength="3"
                   maxlength="50"
                   pattern="[a-zA-Z0-9_]+"
                   autocomplete="username">
            <i class="fas fa-check form-input-icon success-icon"></i>
            <i class="fas fa-times form-input-icon error-icon"></i>
        </div>
        <div class="form-note">3-50 karakter, sadece harf, rakam ve alt çizgi (_) kullanabilirsiniz.</div>
    </div>

    <div class="form-group">
        <label for="email" class="form-label">E-posta Adresi *</label>
        <div class="form-input-wrapper">
            <input type="email" 
                   id="email" 
                   name="email" 
                   class="form-input" 
                   value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                   placeholder="ornek@email.com"
                   required
                   autocomplete="email">
            <i class="fas fa-check form-input-icon success-icon"></i>
            <i class="fas fa-times form-input-icon error-icon"></i>
        </div>
        <div class="form-note">Geçerli bir e-posta adresi girin. Geçici e-posta servisleri kabul edilmez.</div>
    </div>

    <div class="form-group">
        <label for="ingame_name" class="form-label">Oyun İçi İsim *</label>
        <div class="form-input-wrapper">
            <input type="text" 
                   id="ingame_name" 
                   name="ingame_name" 
                   class="form-input" 
                   value="<?php echo htmlspecialchars($form_data['ingame_name'] ?? ''); ?>"
                   placeholder="Star Citizen karakterinizin adı"
                   required
                   minlength="2"
                   maxlength="100">
            <i class="fas fa-check form-input-icon success-icon"></i>
            <i class="fas fa-times form-input-icon error-icon"></i>
        </div>
        <div class="form-note">Star Citizen oyunundaki karakter isminizi girin.</div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label for="password" class="form-label">Şifre *</label>
            <div class="form-input-wrapper">
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="form-input" 
                       placeholder="Güçlü bir şifre"
                       required
                       minlength="8"
                       autocomplete="new-password">
                <i class="fas fa-check form-input-icon success-icon"></i>
                <i class="fas fa-times form-input-icon error-icon"></i>
            </div>
            <div class="password-strength-meter" id="passwordStrength">
                <div class="password-strength-fill"></div>
            </div>
        </div>

        <div class="form-group">
            <label for="confirm_password" class="form-label">Şifre Tekrar *</label>
            <div class="form-input-wrapper">
                <input type="password" 
                       id="confirm_password" 
                       name="confirm_password" 
                       class="form-input" 
                       placeholder="Şifrenizi tekrar girin"
                       required
                       minlength="8"
                       autocomplete="new-password">
                <i class="fas fa-check form-input-icon success-icon"></i>
                <i class="fas fa-times form-input-icon error-icon"></i>
            </div>
        </div>
    </div>

    <div class="password-requirements">
        <span class="password-requirements-title">Güçlü Şifre Gereksinimleri:</span>
        <ul>
            <li>En az 8 karakter uzunluğunda olmalıdır</li>
            <li>En az bir büyük harf (A-Z) içermelidir</li>
            <li>En az bir küçük harf (a-z) içermelidir</li>
            <li>En az bir rakam (0-9) içermelidir</li>
            <li>Yaygın kullanılan şifreler kabul edilmez</li>
        </ul>
    </div>

    <button type="submit" class="btn-auth" id="registerBtn">
        Hesap Oluştur
    </button>
</form>
                    </div>

                    <div class="auth-links">
                        <p id="authLinkText">
                            <?php if ($auth_mode === 'login'): ?>
                                Hesabınız yok mu? 
                                <a href="#" class="auth-link" data-switch="register">Kayıt Olun</a>
                            <?php else: ?>
                                Zaten hesabınız var mı? 
                                <a href="#" class="auth-link" data-switch="login">Giriş Yapın</a>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form Elements
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const navBtns = document.querySelectorAll('.nav-btn');
    const authLinks = document.querySelectorAll('.auth-link[data-switch]');
    
    // Content Elements
    const welcomeText = document.getElementById('welcomeText');
    const subtitleText = document.getElementById('subtitleText');
    const cardTitle = document.getElementById('cardTitle');
    const cardSubtitle = document.getElementById('cardSubtitle');
    const authLinkText = document.getElementById('authLinkText');
    
    // Button Elements
    const loginBtn = document.getElementById('loginBtn');
    const registerBtn = document.getElementById('registerBtn');
    
    // Password Strength Elements - DÜZELTME: Doğru ID'ler
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordStrengthMeter = document.getElementById('passwordStrength');
    const usernameInput = document.getElementById('username');
    
    // Current mode
    let currentMode = document.querySelector('.auth-form.active').id === 'loginForm' ? 'login' : 'register';
    
    // Content data
    const contentData = {
        login: {
            welcome: 'Hoş Geldiniz',
            subtitle: 'Hesabınıza giriş yapın ve galaksideki arkadaşlarınızla buluşun.',
            title: 'Giriş Yap',
            cardSubtitle: 'Hesabınıza erişin',
            linkText: 'Hesabınız yok mu? <a href="#" class="auth-link" data-switch="register">Kayıt Olun</a>'
        },
        register: {
            welcome: 'Topluluğuna Katılın',
            subtitle: 'Star Citizen evreninde büyük maceralar yaşayın. Deneyimli pilotlarla birlikte galaksiyi keşfedin.',
            title: 'Kayıt Ol',
            cardSubtitle: 'Yeni hesap oluşturun',
            linkText: 'Zaten hesabınız var mı? <a href="#" class="auth-link" data-switch="login">Giriş Yapın</a>'
        }
    };
    
    // Switch Forms Function
    function switchForm(targetMode) {
        if (currentMode === targetMode) return;
        
        const currentForm = currentMode === 'login' ? loginForm : registerForm;
        const targetForm = targetMode === 'login' ? loginForm : registerForm;
        const data = contentData[targetMode];
        
        // Update navigation
        navBtns.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.form === targetMode);
        });
        
        // Animate form transition
        currentForm.style.transform = 'translateX(-100%)';
        currentForm.style.opacity = '0';
        
        setTimeout(() => {
            currentForm.classList.remove('active');
            targetForm.classList.add('active');
            targetForm.style.transform = 'translateX(-100%)';
            targetForm.style.opacity = '0';
            
            setTimeout(() => {
                targetForm.style.transform = 'translateX(0)';
                targetForm.style.opacity = '1';
            }, 50);
        }, 300);
        
        // Update content with smooth transitions
        setTimeout(() => {
            welcomeText.style.opacity = '0';
            subtitleText.style.opacity = '0';
            cardTitle.style.opacity = '0';
            cardSubtitle.style.opacity = '0';
            
            setTimeout(() => {
                welcomeText.textContent = data.welcome;
                subtitleText.textContent = data.subtitle;
                cardTitle.textContent = data.title;
                cardSubtitle.textContent = data.cardSubtitle;
                authLinkText.innerHTML = data.linkText;
                
                // Re-bind auth link event - DÜZELTME: Event delegation kullandığımız için gerek yok
                const newAuthLink = authLinkText.querySelector('[data-switch]');
                if (newAuthLink) {
                    console.log('New auth link found:', newAuthLink.dataset.switch);
                    // Event delegation sayesinde otomatik olarak çalışacak
                }
                
                welcomeText.style.opacity = '1';
                subtitleText.style.opacity = '1';
                cardTitle.style.opacity = '1';
                cardSubtitle.style.opacity = '1';
            }, 200);
        }, 150);
        
        // Update current mode and URL
        currentMode = targetMode;
        window.history.pushState({mode: targetMode}, '', `?mode=${targetMode}`);
    }
    
    // Event Listeners - DÜZELTME: Event delegation kullan
    document.addEventListener('click', function(e) {
        // Navigation butonları için
        if (e.target.classList.contains('nav-btn')) {
            e.preventDefault();
            const targetForm = e.target.dataset.form;
            if (targetForm) {
                console.log('Nav button clicked:', targetForm);
                switchForm(targetForm);
            }
        }
        
        // Auth link'ler için
        if (e.target.classList.contains('auth-link') && e.target.dataset.switch) {
            e.preventDefault();
            const targetForm = e.target.dataset.switch;
            console.log('Auth link clicked:', targetForm);
            switchForm(targetForm);
        }
    });
    
    // Fallback - direkt event listener'lar da ekle
    navBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Direct nav button clicked:', this.dataset.form);
            switchForm(this.dataset.form);
        });
    });
    
    // İlk yüklemede mevcut auth link'ler için
    authLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Direct auth link clicked:', this.dataset.switch);
            switchForm(this.dataset.switch);
        });
    });
    
    // Enhanced form validation
    function validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('.form-input[required]');
        
        inputs.forEach(input => {
            const value = input.value.trim();
            
            // Reset previous states
            input.classList.remove('error', 'success');
            
            if (!value) {
                input.classList.add('error');
                isValid = false;
                return;
            }
            
            // Specific validations
            switch(input.type) {
                case 'email':
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        input.classList.add('error');
                        isValid = false;
                    } else {
                        input.classList.add('success');
                    }
                    break;
                    
                case 'password':
                    if (value.length < 6) {
                        input.classList.add('error');
                        isValid = false;
                    } else {
                        input.classList.add('success');
                    }
                    break;
                    
                default:
                    if (input.name === 'username' && form.id === 'registerForm') {
                        const usernameRegex = /^[a-zA-Z0-9_]{3,50}$/;
                        if (!usernameRegex.test(value)) {
                            input.classList.add('error');
                            isValid = false;
                        } else {
                            input.classList.add('success');
                        }
                    } else {
                        input.classList.add('success');
                    }
            }
        });
        
        // Password confirmation check (only for register form)
        if (form.id === 'registerForm') {
            const password = form.querySelector('#password');
            const confirmPassword = form.querySelector('#confirm_password');
            
            if (password && confirmPassword && password.value && confirmPassword.value) {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.classList.remove('success');
                    confirmPassword.classList.add('error');
                    isValid = false;
                } else if (password.value.length >= 6) {
                    confirmPassword.classList.remove('error');
                    confirmPassword.classList.add('success');
                }
            }
        }
        
        return isValid;
    }
    
    // Password strength indicator
    function checkPasswordStrength(password) {
        if (!passwordStrengthMeter) return;
        
        let strength = 0;
        const checks = {
            length: password.length >= 8,
            lowercase: /[a-z]/.test(password),
            uppercase: /[A-Z]/.test(password),
            numbers: /\d/.test(password),
            symbols: /[^A-Za-z0-9]/.test(password)
        };
        
        strength = Object.values(checks).filter(Boolean).length;
        
        // Update strength meter
        passwordStrengthMeter.className = 'password-strength-meter';
        if (password.length >= 6) {
            if (strength <= 2) {
                passwordStrengthMeter.classList.add('password-strength-weak');
            } else if (strength <= 4) {
                passwordStrengthMeter.classList.add('password-strength-medium');
            } else {
                passwordStrengthMeter.classList.add('password-strength-strong');
            }
        }
    }
    
    // Real-time validation for both forms
    [loginForm, registerForm].forEach(form => {
        const inputs = form.querySelectorAll('.form-input');
        inputs.forEach(input => {
            let validationTimeout;
            
            input.addEventListener('input', function() {
                clearTimeout(validationTimeout);
                validationTimeout = setTimeout(() => {
                    if (this.value.trim()) {
                        validateForm(form);
                    }
                }, 300);
            });
            
            input.addEventListener('blur', function() {
                if (this.value.trim()) {
                    validateForm(form);
                }
            });
            
            // Enhanced focus effects
            input.addEventListener('focus', function() {
                this.closest('.form-group').querySelector('.form-label').style.color = 'var(--gold)';
            });
            
            input.addEventListener('blur', function() {
                this.closest('.form-group').querySelector('.form-label').style.color = 'var(--lighter-grey)';
            });
        });
    });
    
    // Password strength checking
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            checkPasswordStrength(password);
            
            if (password.length >= 6) {
                this.classList.remove('error');
                this.classList.add('success');
            } else {
                this.classList.remove('success');
                if (password.length > 0) {
                    this.classList.add('error');
                }
            }
            
            // Also validate confirm password if it has a value
            if (confirmPasswordInput && confirmPasswordInput.value) {
                validateForm(registerForm);
            }
        });
    }
    
    // Username input filter
    if (usernameInput) {
        usernameInput.addEventListener('input', function() {
            const original = this.value;
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
            
            if (original !== this.value) {
                this.style.borderColor = '#f39c12';
                setTimeout(() => {
                    this.style.borderColor = '';
                }, 1000);
            }
        });
    }
    
    // DÜZELTME: Form Submit Event Listeners - TEK VE BASIT YAKLAŞIM
    
    // Register Form Submit Handler
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            console.log('Register form submitted!');
            
            // Form verilerini al
            const formData = new FormData(this);
            const username = formData.get('username')?.trim();
            const email = formData.get('email')?.trim();
            const ingame_name = formData.get('ingame_name')?.trim();
            const password = formData.get('password');
            const confirm_password = formData.get('confirm_password');
            const csrf_token = formData.get('csrf_token');
            
            console.log('Form data:', {username, email, ingame_name, password, confirm_password, csrf_token});
            
            // Basit validasyon
            if (!username || !email || !ingame_name || !password || !confirm_password) {
                alert('Lütfen tüm alanları doldurun!');
                return;
            }
            
            if (password !== confirm_password) {
                alert('Şifreler uyuşmuyor!');
                return;
            }
            
            if (password.length < 6) {
                alert('Şifre en az 6 karakter olmalıdır!');
                return;
            }
            
            // Email validasyonu
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Geçerli bir e-posta adresi girin!');
                return;
            }
            
            // Username validasyonu
            const usernameRegex = /^[a-zA-Z0-9_]{3,50}$/;
            if (!usernameRegex.test(username)) {
                alert('Kullanıcı adı 3-50 karakter olmalı ve sadece harf, rakam, alt çizgi içermeli!');
                return;
            }
            
            // Submit butonunu disable et
            registerBtn.disabled = true;
            registerBtn.textContent = 'Gönderiliyor...';
            
            // Form'u gerçekten submit et
            console.log('Submitting to:', this.action);
            this.submit();
        });
    }
    
    // Login Form Submit Handler
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            console.log('Login form submitted!');
            
            const formData = new FormData(this);
            const username = formData.get('username')?.trim();
            const password = formData.get('password');
            
            if (!username || !password) {
                alert('Kullanıcı adı ve şifre gerekli!');
                return;
            }
            
            // Submit butonunu disable et
            loginBtn.disabled = true;
            loginBtn.textContent = 'Giriş yapılıyor...';
            
            // Form'u gerçekten submit et
            this.submit();
        });
    }
    
    // DÜZELTME: Enter tuşu desteği
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const activeForm = document.querySelector('.auth-form.active');
            if (activeForm) {
                const focusedElement = document.activeElement;
                
                // Eğer form içindeki bir input'ta enter'a basıldıysa
                if (activeForm.contains(focusedElement) && focusedElement.tagName === 'INPUT') {
                    e.preventDefault();
                    
                    // Form submit event'ini tetikle
                    const submitEvent = new Event('submit', {
                        bubbles: true,
                        cancelable: true
                    });
                    activeForm.dispatchEvent(submitEvent);
                }
            }
        }
        
        // Ctrl+Enter ile hızlı submit
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            const activeForm = document.querySelector('.auth-form.active');
            if (activeForm) {
                const submitEvent = new Event('submit', {
                    bubbles: true,
                    cancelable: true
                });
                activeForm.dispatchEvent(submitEvent);
            }
        }
        
        // Switch forms with Ctrl+Tab
        if (e.ctrlKey && e.key === 'Tab') {
            e.preventDefault();
            switchForm(currentMode === 'login' ? 'register' : 'login');
        }
    });
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(e) {
        const mode = e.state?.mode || 'login';
        if (mode !== currentMode) {
            switchForm(mode);
        }
    });
    
    // Auto-focus first empty field
    const activeForm = document.querySelector('.auth-form.active');
    if (activeForm) {
        const firstEmptyField = activeForm.querySelector('.form-input[required]:not([value])');
        if (firstEmptyField && !firstEmptyField.value) {
            setTimeout(() => firstEmptyField.focus(), 500);
        }
    }
    
    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
        window.history.replaceState({mode: currentMode}, '', `?mode=${currentMode}`);
    }
    
    console.log('Form handlers initialized successfully!');
    
    // DEBUG: Element'leri kontrol et
    console.log('Navigation buttons found:', navBtns.length);
    console.log('Auth links found:', authLinks.length);
    console.log('Current mode:', currentMode);
    
    // DEBUG: Butonlara click event test et
    navBtns.forEach((btn, index) => {
        console.log(`Nav button ${index}:`, btn.dataset.form, btn.classList.contains('active'));
    });
    
    // DEBUG: Manual test fonksiyonu
    window.testFormSwitch = function(mode) {
        console.log('Manual test - switching to:', mode);
        switchForm(mode);
    };
    
    // DEBUG: Butonlara manual click test
    window.testNavClick = function() {
        const registerBtn = document.querySelector('[data-form="register"]');
        if (registerBtn) {
            console.log('Testing register button click');
            registerBtn.click();
        }
    };
}); </script>