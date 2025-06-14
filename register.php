<?php
// public/auth.php - Entegre Login/Register Sayfası

require_once 'src/config/database.php';
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
.site-container {
    width: 100vw !important;
    margin: 0 !important;
}
/* Modern Auth Page Styles - Ultra Modern & Professional */
.auth-page-wrapper {
    min-height: calc(100vh - 170px);
    width: 100vw !important;
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
    overflow: auto;
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
/* ==========================================================================
   DETAYLI RESPONSIVE MEDIA QUERIES - AUTH PAGE
   ========================================================================== */

/* Ultra Wide Screens (3840px+) - 4K ve üzeri */
@media screen and (min-width: 3840px) {
    .auth-page-wrapper {
        font-size: 1.1rem;
    }
    
    .auth-content {
        max-width: 2400px;
        margin: 0 auto;
        grid-template-columns: 1.2fr 600px;
        gap: 6rem;
    }
    
    .auth-welcome {
        font-size: 4rem;
        margin-bottom: 2rem;
    }
    
    .auth-subtitle {
        font-size: 1.4rem;
        margin-bottom: 3rem;
    }
    
    .auth-card {
        padding: 3.5rem;
        max-width: 600px;
    }
    
    .form-input {
        padding: 1.2rem 1.5rem;
        font-size: 1.1rem;
    }
    
    .btn-auth {
        padding: 1.4rem 2.5rem;
        font-size: 1.1rem;
    }
}

/* Wide Desktop Screens (2560px - 3839px) - 2K/1440p */
@media screen and (min-width: 2560px) and (max-width: 3839px) {
    .auth-container {
        max-width: 2200px;
        padding: 0 3rem;
    }
    
    .auth-content {
        grid-template-columns: 1.1fr 550px;
        gap: 5rem;
        min-width: auto;
    }
    
    .auth-welcome {
        font-size: 3.5rem;
    }
    
    .auth-subtitle {
        font-size: 1.3rem;
        margin-bottom: 3rem;
    }
    
    .auth-card {
        padding: 3rem;
        max-width: 550px;
    }
    
    .form-input {
        padding: 1.1rem 1.4rem;
        font-size: 1rem;
    }
    
    .btn-auth {
        padding: 1.3rem 2.2rem;
        font-size: 1.05rem;
    }
}

/* Large Desktop Screens (1920px - 2559px) - Full HD */
@media screen and (min-width: 1920px) and (max-width: 2559px) {
    .auth-container {
        max-width: 1800px;
        padding: 0 2.5rem;
    }
    
    .auth-content {
        grid-template-columns: 1fr 500px;
        gap: 4rem;
        min-width: auto;
    }
    
    .auth-welcome {
        font-size: 3.2rem;
    }
    
    .auth-subtitle {
        font-size: 1.25rem;
    }
    
    .auth-card {
        padding: 2.8rem;
        max-width: 500px;
    }
}

/* Standard Desktop Screens (1600px - 1919px) */
@media screen and (min-width: 1600px) and (max-width: 1919px) {
    .auth-container {
        max-width: 1500px;
        padding: 0 2rem;
    }
    
    .auth-content {
        grid-template-columns: 1fr 480px;
        gap: 3.5rem;
        min-width: auto;
    }
    
    .auth-welcome {
        font-size: 3rem;
    }
    
    .auth-card {
        padding: 2.5rem;
        max-width: 480px;
    }
}

/* Medium Desktop Screens (1440px - 1599px) */
@media screen and (min-width: 1440px) and (max-width: 1599px) {
    .auth-container {
        max-width: 1300px;
        padding: 0 2rem;
    }
    
    .auth-content {
        grid-template-columns: 1fr 450px;
        gap: 3rem;
        min-width: auto;
    }
    
    .auth-welcome {
        font-size: 2.8rem;
    }
    
    .auth-subtitle {
        font-size: 1.15rem;
    }
    
    .auth-card {
        padding: 2.3rem;
        max-width: 450px;
        min-height: auto;
        max-height: none;
    }
}

/* Small Desktop Screens (1200px - 1439px) */
@media screen and (min-width: 1200px) and (max-width: 1439px) {
    .auth-container {
        max-width: 1100px;
        padding: 0 1.5rem;
    }
    
    .auth-content {
        grid-template-columns: 1fr 420px;
        gap: 2.5rem;
        min-width: auto;
    }
    
    .auth-welcome {
        font-size: 2.5rem;
    }
    
    .auth-subtitle {
        font-size: 1.1rem;
    }
    
    .auth-card {
        padding: 2rem;
        max-width: 420px;
        min-height: auto;
        max-height: none;
    }
    
    .form-input {
        padding: 0.9rem 1.1rem;
        font-size: 0.9rem;
    }
    
    .btn-auth {
        padding: 1.1rem 1.8rem;
        font-size: 0.95rem;
    }
}

/* Large Tablet/Small Desktop (1024px - 1199px) */
@media screen and (min-width: 1024px) and (max-width: 1199px) {
    .auth-page-wrapper {
        min-height: calc(100vh - 140px);
    }
    
    .auth-container {
        max-width: 950px;
        padding: 0 1.5rem;
    }
    
    .auth-content {
        grid-template-columns: 1fr;
        gap: 2rem;
        text-align: center;
        min-width: auto;
    }
    
    .auth-info {
        order: 2;
        max-width: 600px;
        margin: 0 auto;
    }
    
    .auth-card {
        order: 1;
        max-width: 480px;
        margin: 0 auto;
        padding: 2rem;
        min-height: auto;
        max-height: none;
    }
    
    .auth-welcome {
        font-size: 2.3rem;
        margin-bottom: 1rem;
    }
    
    .auth-subtitle {
        font-size: 1rem;
        margin-bottom: 2rem;
    }
    
    .auth-features {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem 2rem;
        max-width: 500px;
        margin: 0 auto;
    }
    
    .auth-features li {
        font-size: 0.9rem;
        text-align: left;
    }
}

/* Standard Tablet Portrait (768px - 1023px) */
@media screen and (min-width: 768px) and (max-width: 1023px) {
    .auth-page-wrapper {
        min-height: calc(100vh - 120px);
        padding: 1.5rem 0;
    }
    
    .auth-container {
        max-width: 700px;
        padding: 0 1rem;
    }
    
    .auth-content {
        grid-template-columns: 1fr;
        gap: 1.5rem;
        text-align: center;
        min-width: auto;
    }
    
    .auth-info {
        order: 2;
    }
    
    .auth-card {
        order: 1;
        max-width: 450px;
        margin: 0 auto;
        padding: 1.8rem;
        min-height: auto;
        max-height: none;
    }
    
    .auth-welcome {
        font-size: 2rem;
        margin-bottom: 1rem;
    }
    
    .auth-subtitle {
        font-size: 0.95rem;
        margin-bottom: 1.5rem;
    }
    
    .auth-features {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.4rem 1.5rem;
        max-width: 450px;
        margin: 0 auto;
    }
    
    .auth-features li {
        font-size: 0.85rem;
        text-align: left;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 1.2rem;
    }
    
    .form-input {
        padding: 0.85rem 1rem;
        font-size: 0.9rem;
    }
    
    .btn-auth {
        padding: 1rem 1.5rem;
        font-size: 0.9rem;
    }
    
    .nav-btn {
        padding: 0.65rem 1.2rem;
        font-size: 0.85rem;
    }
}

/* Large Mobile Landscape/Small Tablet (640px - 767px) */
@media screen and (min-width: 640px) and (max-width: 767px) {
    .auth-page-wrapper {
        min-height: calc(100vh - 100px);
        padding: 1rem 0;
    }
    
    .auth-container {
        max-width: 600px;
        padding: 0 1rem;
    }
    
    .auth-content {
        grid-template-columns: 1fr;
        gap: 1.2rem;
        text-align: center;
        min-width: auto;
    }
    
    .auth-info {
        order: 2;
    }
    
    .auth-card {
        order: 1;
        max-width: 400px;
        margin: 0 auto;
        padding: 1.5rem;
        border-radius: 12px;
        min-height: auto;
        max-height: none;
    }
    
    .auth-welcome {
        font-size: 1.8rem;
        margin-bottom: 0.8rem;
        line-height: 1.1;
    }
    
    .auth-subtitle {
        font-size: 0.9rem;
        margin-bottom: 1.2rem;
    }
    
    .auth-features {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.3rem;
        max-width: 350px;
        margin: 0 auto;
    }
    
    .auth-features li {
        font-size: 0.8rem;
        text-align: center;
    }
    
    .form-group {
        margin-bottom: 1.2rem;
    }
    
    .form-input {
        padding: 0.8rem 0.9rem;
        font-size: 0.85rem;
    }
    
    .btn-auth {
        padding: 0.9rem 1.3rem;
        font-size: 0.85rem;
    }
    
    .nav-btn {
        padding: 0.6rem 1rem;
        font-size: 0.8rem;
    }
    
    .password-requirements {
        padding: 0.8rem;
        font-size: 0.75rem;
    }
}

/* Standard Mobile (480px - 639px) */
@media screen and (min-width: 480px) and (max-width: 639px) {
    .auth-page-wrapper {
        min-height: calc(100vh - 80px);
        padding: 0.8rem 0;
    }
    
    .auth-container {
        max-width: 460px;
        padding: 0 0.8rem;
    }
    
    .auth-content {
        grid-template-columns: 1fr;
        gap: 1rem;
        text-align: center;
        min-width: auto;
    }
    
    .auth-info {
        order: 2;
    }
    
    .auth-card {
        order: 1;
        max-width: 360px;
        margin: 0 auto;
        padding: 1.3rem;
        border-radius: 10px;
        min-height: auto;
        max-height: none;
    }
    
    .auth-logo {
        width: 50px;
        height: 50px;
        margin-bottom: 0.8rem;
    }
    
    .auth-title {
        font-size: 1.4rem;
    }
    
    .auth-welcome {
        font-size: 1.6rem;
        margin-bottom: 0.6rem;
    }
    
    .auth-subtitle {
        font-size: 0.85rem;
        margin-bottom: 1rem;
    }
    
    .auth-features {
        display: none; /* Mobilde gizle, yer kazanmak için */
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-input {
        padding: 0.75rem 0.85rem;
        font-size: 0.8rem;
    }
    
    .btn-auth {
        padding: 0.85rem 1.2rem;
        font-size: 0.8rem;
        margin: 1.2rem 0;
    }
    
    .nav-btn {
        padding: 0.55rem 0.9rem;
        font-size: 0.75rem;
    }
    
    .password-requirements {
        padding: 0.7rem;
        font-size: 0.7rem;
    }
    
    .alert {
        padding: 0.8rem;
        font-size: 0.8rem;
        margin-bottom: 1rem;
    }
}

/* Small Mobile (320px - 479px) */
@media screen and (min-width: 320px) and (max-width: 479px) {
    .auth-page-wrapper {
        min-height: calc(100vh - 60px);
        padding: 0.5rem 0;
    }
    
    .auth-container {
        max-width: 300px;
        padding: 0 0.5rem;
    }
    
    .auth-content {
        grid-template-columns: 1fr;
        gap: 0.8rem;
        text-align: center;
        min-width: auto;
    }
    
    .auth-info {
        order: 2;
    }
    
    .auth-card {
        order: 1;
        max-width: 290px;
        margin: 0 auto;
        padding: 1rem;
        border-radius: 8px;
        min-height: auto;
        max-height: none;
    }
    
    .auth-logo {
        width: 40px;
        height: 40px;
        margin-bottom: 0.6rem;
    }
    
    .auth-title {
        font-size: 1.2rem;
        margin-bottom: 0.3rem;
    }
    
    .auth-card-subtitle {
        font-size: 0.75rem;
    }
    
    .auth-welcome {
        font-size: 1.4rem;
        margin-bottom: 0.5rem;
    }
    
    .auth-subtitle {
        font-size: 0.8rem;
        margin-bottom: 0.8rem;
    }
    
    .auth-features {
        display: none;
    }
    
    .form-navigation {
        margin-bottom: 1.5rem;
        padding: 0.2rem;
    }
    
    .form-group {
        margin-bottom: 0.9rem;
    }
    
    .form-label {
        font-size: 0.8rem;
        margin-bottom: 0.4rem;
    }
    
    .form-input {
        padding: 0.7rem 0.8rem;
        font-size: 0.75rem;
        border-radius: 6px;
    }
    
    .btn-auth {
        padding: 0.8rem 1rem;
        font-size: 0.75rem;
        margin: 1rem 0;
        border-radius: 6px;
    }
    
    .nav-btn {
        padding: 0.5rem 0.7rem;
        font-size: 0.7rem;
        border-radius: 4px;
    }
    
    .password-requirements {
        padding: 0.6rem;
        font-size: 0.65rem;
        margin-top: 0.8rem;
    }
    
    .password-requirements ul {
        padding-left: 1rem;
    }
    
    .alert {
        padding: 0.7rem;
        font-size: 0.75rem;
        margin-bottom: 0.8rem;
        flex-direction: column;
        gap: 0.4rem;
    }
    
    .alert i.fas {
        align-self: center;
        margin-top: 0;
    }
    
    .form-note {
        font-size: 0.7rem;
        margin-top: 0.3rem;
    }
    
    .checkbox-group {
        margin: 0.8rem 0;
    }
    
    .checkbox-label {
        font-size: 0.8rem;
    }
    
    .auth-links {
        margin-top: 0.5rem;
    }
    
    .auth-links p {
        font-size: 0.75rem;
    }
}

/* Very Small Mobile (300px ve altı) */
@media screen and (max-width: 319px) {
    .auth-page-wrapper {
        min-height: calc(100vh - 40px);
        padding: 0.3rem 0;
    }
    
    .auth-container {
        max-width: 280px;
        padding: 0 0.3rem;
    }
    
    .auth-card {
        max-width: 270px;
        padding: 0.8rem;
        border-radius: 6px;
    }
    
    .auth-logo {
        width: 35px;
        height: 35px;
    }
    
    .auth-title {
        font-size: 1.1rem;
    }
    
    .auth-welcome {
        font-size: 1.2rem;
    }
    
    .auth-subtitle {
        font-size: 0.75rem;
    }
    
    .form-input {
        padding: 0.6rem 0.7rem;
        font-size: 0.7rem;
    }
    
    .btn-auth {
        padding: 0.7rem 0.9rem;
        font-size: 0.7rem;
    }
    
    .nav-btn {
        padding: 0.4rem 0.6rem;
        font-size: 0.65rem;
    }
    
    .password-requirements {
        padding: 0.5rem;
        font-size: 0.6rem;
    }
    
    .alert {
        padding: 0.6rem;
        font-size: 0.7rem;
    }
}

/* Landscape Orientation Adjustments */
@media screen and (max-width: 1023px) and (orientation: landscape) {
    .auth-page-wrapper {
        min-height: calc(100vh - 100px);
        padding: 0.5rem 0;
    }
    
    .auth-content {
        gap: 1rem;
    }
    
    .auth-info {
        display: none; /* Landscape'de bilgi kısmını gizle */
    }
    
    .auth-card {
        max-width: 400px;
        padding: 1.2rem;
        margin: 0 auto;
    }
    
    .auth-welcome {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }
    
    .password-requirements {
        display: none; /* Landscape'de şifre gereksinimlerini gizle */
    }
}

/* High DPI / Retina Display Adjustments */
@media screen and (-webkit-min-device-pixel-ratio: 2),
       screen and (min-resolution: 192dpi),
       screen and (min-resolution: 2dppx) {
    .auth-logo {
        background-size: cover;
    }
    
    .floating-element {
        border: 0.5px solid rgba(189, 145, 42, 0.1);
    }
}

/* Dark Mode Adjustments (if device prefers dark) */
@media (prefers-color-scheme: dark) {
    .auth-page-wrapper::before {
        opacity: 0.8;
    }
    
    .auth-card {
        box-shadow: 
            0 25px 50px rgba(0, 0, 0, 0.5),
            0 0 0 1px rgba(189, 145, 42, 0.15),
            inset 0 1px 0 rgba(255, 255, 255, 0.03);
    }
}

/* Reduced Motion Preferences */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
    
    .floating-elements {
        display: none;
    }
    
    .auth-page-wrapper::before {
        animation: none;
    }
}

/* Touch Device Optimizations */
@media (hover: none) and (pointer: coarse) {
    .form-input {
        padding: 1rem 1.2rem; /* Daha büyük dokunma alanı */
    }
    
    .btn-auth {
        padding: 1.2rem 2rem;
        min-height: 48px; /* iOS dokunma standardı */
    }
    
    .nav-btn {
        padding: 0.8rem 1.2rem;
        min-height: 44px;
    }
    
    .checkbox-input {
        width: 20px;
        height: 20px;
    }
    
    .auth-link {
        padding: 0.3rem;
        min-height: 44px;
        display: inline-flex;
        align-items: center;
    }
}

/* Print Styles (if someone tries to print) */
@media print {
    .auth-page-wrapper {
        background: white !important;
        color: black !important;
    }
    
    .floating-elements,
    .auth-info,
    .btn-auth {
        display: none !important;
    }
    
    .auth-card {
        box-shadow: none !important;
        border: 2px solid #000 !important;
        background: white !important;
    }
}

/* Special handling for very wide but short screens */
@media screen and (min-width: 1400px) and (max-height: 800px) {
    .auth-content {
        min-height: auto;
        align-items: center;
    }
    
    .auth-card {
        min-height: auto;
        max-height: 700px;
        overflow-y: auto;
    }
    
    .auth-info {
        max-height: 600px;
    }
    
    .auth-features {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 0.5rem 1rem;
    }
    
    .auth-features li {
        font-size: 0.9rem;
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
    const emailInput = document.getElementById('email');
    const ingameNameInput = document.getElementById('ingame_name');
    
    // Current mode
    let currentMode = '<?php echo $auth_mode; ?>';
    
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
            targetForm.style.transform = 'translateX(100%)';
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
                
                // Re-bind auth link event
                const newAuthLink = authLinkText.querySelector('[data-switch]');
                if (newAuthLink) {
                    newAuthLink.addEventListener('click', function(e) {
                        e.preventDefault();
                        switchForm(this.dataset.switch);
                    });
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
    
    // Event Listeners
    navBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            switchForm(this.dataset.form);
        });
    });
    
    authLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            switchForm(this.dataset.switch);
        });
    });
    
    // DÜZELTME: Enhanced form validation - handle_register.php regex kurallarına uygun
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
            
            // Specific validations based on PHP regex patterns
            switch(input.name) {
                case 'username':
                    if (form.id === 'registerForm') {
                        // Username: 3-50 karakter, sadece harf, rakam ve alt çizgi
                        const usernameRegex = /^[a-zA-Z0-9_]{3,50}$/;
                        if (!usernameRegex.test(value)) {
                            input.classList.add('error');
                            isValid = false;
                            showFieldError(input, 'Kullanıcı adı 3-50 karakter olmalı ve sadece harf, rakam, alt çizgi içermelidir');
                        } else {
                            input.classList.add('success');
                            hideFieldError(input);
                        }
                    } else {
                        // Login form - username veya email olabilir
                        input.classList.add('success');
                    }
                    break;
                    
                case 'email':
                    // Email validation - PHP filter_var(FILTER_VALIDATE_EMAIL) standardına uygun
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        input.classList.add('error');
                        isValid = false;
                        showFieldError(input, 'Geçerli bir e-posta adresi girin');
                    } else {
                        input.classList.add('success');
                        hideFieldError(input);
                    }
                    break;
                    
                case 'ingame_name':
                    // Ingame name: 1-100 karakter, boş olamaz
                    if (value.length < 1 || value.length > 100) {
                        input.classList.add('error');
                        isValid = false;
                        showFieldError(input, 'Oyun içi isim 1-100 karakter arasında olmalıdır');
                    } else {
                        input.classList.add('success');
                        hideFieldError(input);
                    }
                    break;
                    
                case 'password':
                    // Password: En az 6 karakter + büyük harf, küçük harf kontrolü
                    if (value.length < 6) {
                        input.classList.add('error');
                        isValid = false;
                        showFieldError(input, 'Şifre en az 6 karakter olmalıdır');
                    } else {
                        // Şifre gücü kontrolü ve önerileri
                        const hasLowerCase = /[a-z]/.test(value);
                        const hasUpperCase = /[A-Z]/.test(value);
                        const hasNumbers = /\d/.test(value);
                        const hasSpecialChar = /[^A-Za-z0-9]/.test(value);
                        
                        const strengthChecks = [hasLowerCase, hasUpperCase, hasNumbers, hasSpecialChar];
                        const strengthCount = strengthChecks.filter(Boolean).length;
                        
                        if (strengthCount >= 3) {
                            input.classList.add('success');
                            hideFieldError(input);
                        } else {
                            input.classList.add('error');
                            isValid = false;
                            let suggestions = [];
                            if (!hasLowerCase) suggestions.push('küçük harf');
                            if (!hasUpperCase) suggestions.push('büyük harf');
                            if (!hasNumbers) suggestions.push('rakam');
                            if (!hasSpecialChar) suggestions.push('özel karakter');
                            
                            showFieldError(input, `Güçlü şifre için ekleyin: ${suggestions.slice(0, 2).join(', ')}`);
                        }
                    }
                    break;
                    
                case 'confirm_password':
                    // Password confirmation
                    const passwordField = form.querySelector('[name="password"]');
                    if (!passwordField || value !== passwordField.value) {
                        input.classList.add('error');
                        isValid = false;
                        showFieldError(input, 'Şifreler uyuşmuyor');
                    } else if (value.length >= 6) {
                        input.classList.add('success');
                        hideFieldError(input);
                    }
                    break;
                    
                default:
                    // Default validation for other fields
                    if (value.trim()) {
                        input.classList.add('success');
                        hideFieldError(input);
                    }
            }
        });
        
        return isValid;
    }
    
    // DÜZELTME: Field error messaging functions
    function showFieldError(input, message) {
        // Remove existing error message
        hideFieldError(input);
        
        // Create error message element
        const errorElement = document.createElement('div');
        errorElement.className = 'field-error-message';
        errorElement.style.cssText = `
            color: #e74c3c;
            font-size: 0.75rem;
            margin-top: 0.25rem;
            padding-left: 0.25rem;
            animation: slideInDown 0.3s ease;
        `;
        errorElement.textContent = message;
        
        // Insert after input wrapper
        const inputWrapper = input.closest('.form-input-wrapper');
        if (inputWrapper) {
            inputWrapper.parentNode.insertBefore(errorElement, inputWrapper.nextSibling);
        }
    }
    
    function hideFieldError(input) {
        const inputWrapper = input.closest('.form-input-wrapper');
        if (inputWrapper && inputWrapper.parentNode) {
            const errorElement = inputWrapper.parentNode.querySelector('.field-error-message');
            if (errorElement) {
                errorElement.remove();
            }
        }
    }
    
    // DÜZELTME: Password strength indicator - doğru element ile
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
                    } else {
                        // Clear validation states when empty
                        this.classList.remove('error', 'success');
                        hideFieldError(this);
                    }
                }, 300);
            });
            
            input.addEventListener('blur', function() {
                validateForm(form);
            });
            
            // Enhanced focus effects
            input.addEventListener('focus', function() {
                const label = this.closest('.form-group').querySelector('.form-label');
                if (label) {
                    label.style.color = 'var(--gold)';
                }
                // Clear error on focus
                hideFieldError(this);
            });
            
            input.addEventListener('blur', function() {
                const label = this.closest('.form-group').querySelector('.form-label');
                if (label) {
                    label.style.color = 'var(--lighter-grey)';
                }
            });
        });
    });
    
    // DÜZELTME: Password strength checking - doğru element + aZ görsel geri bildirimi
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            checkPasswordStrength(password);
            
            // Şifre gücü ve aZ kontrolleri için görsel geri bildirim
            if (password.length >= 6) {
                const hasLowerCase = /[a-z]/.test(password);
                const hasUpperCase = /[A-Z]/.test(password);
                const hasNumbers = /\d/.test(password);
                const hasSpecialChar = /[^A-Za-z0-9]/.test(password);
                
                // Label'ı güncelle - şifre gücü göstergesi
                const label = this.closest('.form-group').querySelector('.form-label');
                if (label) {
                    const checks = [];
                    if (hasLowerCase) checks.push('<span style="color: #2abd8a;">a</span>');
                    else checks.push('<span style="color: #e74c3c;">a</span>');
                    
                    if (hasUpperCase) checks.push('<span style="color: #2abd8a;">A</span>');
                    else checks.push('<span style="color: #e74c3c;">A</span>');
                    
                    if (hasNumbers) checks.push('<span style="color: #2abd8a;">9</span>');
                    else checks.push('<span style="color: #e74c3c;">9</span>');
                    
                    if (hasSpecialChar) checks.push('<span style="color: #2abd8a;">@</span>');
                    else checks.push('<span style="color: #e74c3c;">@</span>');
                    
                    label.innerHTML = `Şifre * [${checks.join('')}]`;
                }
                
                // Güçlü şifre kontrolü
                const strengthCount = [hasLowerCase, hasUpperCase, hasNumbers, hasSpecialChar].filter(Boolean).length;
                
                if (strengthCount >= 3) {
                    this.classList.remove('error');
                    this.classList.add('success');
                    hideFieldError(this);
                } else {
                    this.classList.remove('success');
                    this.classList.add('error');
                }
            } else {
                this.classList.remove('success');
                if (password.length > 0) {
                    this.classList.add('error');
                }
                // Label'ı sıfırla
                const label = this.closest('.form-group').querySelector('.form-label');
                if (label) {
                    label.textContent = 'Şifre *';
                }
            }
            
            // Trigger validation for this field specifically
            setTimeout(() => {
                validateForm(registerForm);
            }, 100);
            
            // Also validate confirm password if it has a value
            if (confirmPasswordInput && confirmPasswordInput.value) {
                setTimeout(() => {
                    validateForm(registerForm);
                }, 100);
            }
        });
        
        // Password blur event - label'ı sıfırla
        passwordInput.addEventListener('blur', function() {
            const label = this.closest('.form-group').querySelector('.form-label');
            if (label && this.value.length === 0) {
                label.textContent = 'Şifre *';
            }
        });
    }
    
    // DÜZELTME: Username input filter - doğru element
    if (usernameInput) {
        usernameInput.addEventListener('input', function() {
            const original = this.value;
            // Remove invalid characters in real-time
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
            
            if (original !== this.value) {
                this.style.borderColor = '#f39c12';
                showFieldError(this, 'Sadece harf, rakam ve alt çizgi (_) kullanabilirsiniz');
                setTimeout(() => {
                    this.style.borderColor = '';
                    hideFieldError(this);
                }, 2000);
            }
        });
        
        // Character counter for username
        usernameInput.addEventListener('input', function() {
            const label = this.closest('.form-group').querySelector('.form-label');
            const length = this.value.length;
            if (label && length > 0) {
                label.textContent = `Kullanıcı Adı * (${length}/50)`;
            } else if (label) {
                label.textContent = 'Kullanıcı Adı *';
            }
        });
    }
    
    // DÜZELTME: Email validation enhancement
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            // Remove spaces automatically
            this.value = this.value.replace(/\s/g, '');
        });
    }
    
    // DÜZELTME: Ingame name character counter
    if (ingameNameInput) {
        ingameNameInput.addEventListener('input', function() {
            const label = this.closest('.form-group').querySelector('.form-label');
            const length = this.value.length;
            if (label && length > 0) {
                label.textContent = `Oyun İçi İsim * (${length}/100)`;
            } else if (label) {
                label.textContent = 'Oyun İçi İsim *';
            }
        });
    }
    
    // DÜZELTME: Form submissions - doğru element ID'leri
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'registerBtn') {
            e.preventDefault();
            
            const form = document.getElementById('registerForm');
            
            // Get form data with correct IDs
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const ingame_name = document.getElementById('ingame_name').value.trim();
            const password = document.getElementById('password').value;
            const confirm_password = document.getElementById('confirm_password').value;
            const csrf_token = form.querySelector('input[name="csrf_token"]').value;
            
            // Validate form before submission
            if (!validateForm(form)) {
                // Show general error
                showFormAlert('Lütfen tüm alanları doğru şekilde doldurun!', 'error');
                return;
            }
            
            // Additional validation checks
            if (!username || !email || !ingame_name || !password || !confirm_password) {
                showFormAlert('Lütfen tüm zorunlu alanları doldurun!', 'error');
                return;
            }
            
            if (password !== confirm_password) {
                showFormAlert('Şifreler uyuşmuyor!', 'error');
                return;
            }
            
            if (password.length < 6) {
                showFormAlert('Şifre en az 6 karakter olmalıdır!', 'error');
                return;
            }
            
            // Show loading state
            registerBtn.classList.add('loading');
            registerBtn.disabled = true;
            registerBtn.textContent = 'Kayıt Oluşturuluyor...';
            
            // Create and submit form manually
            const submitForm = document.createElement('form');
            submitForm.method = 'POST';
            submitForm.action = '../src/actions/handle_register.php';
            submitForm.style.display = 'none';
            
            // Add all fields
            const fields = {
                'csrf_token': csrf_token,
                'username': username,
                'email': email,
                'ingame_name': ingame_name,
                'password': password,
                'confirm_password': confirm_password
            };
            
            for (const [name, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                submitForm.appendChild(input);
            }
            
            document.body.appendChild(submitForm);
            submitForm.submit();
        }
        
        if (e.target && e.target.id === 'loginBtn') {
            e.preventDefault();
            
            const form = document.getElementById('loginForm');
            const username = document.getElementById('login_username').value.trim();
            const password = document.getElementById('login_password').value;
            const csrf_token = form.querySelector('input[name="csrf_token"]').value;
            const remember_me = document.getElementById('remember_me').checked ? '1' : '';
            
            if (!username || !password) {
                showFormAlert('Kullanıcı adı ve şifre gerekli!', 'error');
                return;
            }
            
            // Show loading state
            loginBtn.classList.add('loading');
            loginBtn.disabled = true;
            loginBtn.textContent = 'Giriş Yapılıyor...';
            
            const submitForm = document.createElement('form');
            submitForm.method = 'POST';
            submitForm.action = '../src/actions/handle_login.php';
            submitForm.style.display = 'none';
            
            const fields = {
                'csrf_token': csrf_token,
                'username': username,
                'password': password,
                'remember_me': remember_me
            };
            
            for (const [name, value] of Object.entries(fields)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                submitForm.appendChild(input);
            }
            
            document.body.appendChild(submitForm);
            submitForm.submit();
        }
    });
    
    // DÜZELTME: Form alert function
    function showFormAlert(message, type = 'error') {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.dynamic-alert');
        existingAlerts.forEach(alert => alert.remove());
        
        // Create alert element
        const alert = document.createElement('div');
        alert.className = `alert alert-${type === 'error' ? 'danger' : type} dynamic-alert`;
        alert.textContent = message;
        alert.style.marginBottom = '1.5rem';
        alert.style.animation = 'slideInDown 0.4s ease';
        
        // Insert at top of forms container
        const formsContainer = document.querySelector('.auth-forms-container');
        if (formsContainer) {
            formsContainer.insertBefore(alert, formsContainer.firstChild);
        }
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.animation = 'slideOutUp 0.4s ease';
                setTimeout(() => alert.remove(), 400);
            }
        }, 5000);
    }
    
    // Form event listeners - prevent default submission
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
        });
    }
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault(); 
        });
    }
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(e) {
        const mode = e.state?.mode || 'login';
        if (mode !== currentMode) {
            switchForm(mode);
        }
    });
    
    // Auto-focus first empty field
    setTimeout(() => {
        const activeForm = document.querySelector('.auth-form.active');
        if (activeForm) {
            const firstEmptyField = activeForm.querySelector('.form-input[required]:not([value])');
            if (firstEmptyField && !firstEmptyField.value) {
                firstEmptyField.focus();
            }
        }
    }, 500);
    
    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
        window.history.replaceState({mode: currentMode}, '', `?mode=${currentMode}`);
    }
    
    // Enhanced accessibility
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            const activeForm = document.querySelector('.auth-form.active');
            if (activeForm) {
                const submitBtn = activeForm.querySelector('.btn-auth');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.click();
                }
            }
        }
        
        // Switch forms with Ctrl+Tab
        if (e.ctrlKey && e.key === 'Tab') {
            e.preventDefault();
            switchForm(currentMode === 'login' ? 'register' : 'login');
        }
    });
    
    // Real-time password confirmation check
    if (confirmPasswordInput && passwordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            if (this.value && passwordInput.value) {
                if (this.value === passwordInput.value) {
                    this.classList.remove('error');
                    this.classList.add('success');
                    hideFieldError(this);
                } else {
                    this.classList.remove('success');
                    this.classList.add('error');
                    showFieldError(this, 'Şifreler uyuşmuyor');
                }
            }
        });
    }
});
</script>
<?php include BASE_PATH . '/src/includes/footer.php'; ?>
