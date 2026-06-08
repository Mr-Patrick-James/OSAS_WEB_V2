<?php
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_path', '/');
session_start();

// Check if user wants to see landing page (bypass auto-redirect)
$forceLanding = isset($_GET['force_landing']) && $_GET['force_landing'] === 'true';

// Helper: detect project prefix ('' on AWS root, '/OSAS_WEB' on local subfolder)
function getAppPrefix(): string {
    $appDirs = ['app','api','includes','assets','public','index.php'];
    $parts   = explode('/', trim($_SERVER['SCRIPT_NAME'] ?? '', '/'));
    return (!empty($parts[0]) && !in_array($parts[0], $appDirs)) ? '/' . $parts[0] : '';
}
$prefix = getAppPrefix();

// Restore session from cookies if session is empty
if (!isset($_SESSION['user_id']) && isset($_COOKIE['user_id']) && isset($_COOKIE['role'])) {
    $_SESSION['user_id'] = $_COOKIE['user_id'];
    $_SESSION['username'] = $_COOKIE['username'] ?? '';
    $_SESSION['role']    = $_COOKIE['role'];
}

if (!$forceLanding && isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if (in_array($_SESSION['role'], ['admin', 'OSAS Staff', 'CSC Officer', 'Officer', 'Faculty Member'])) {
        header('Location: ' . $prefix . '/includes/dashboard.php');
        exit;
    } elseif ($_SESSION['role'] === 'user') {
        header('Location: ' . $prefix . '/includes/user_dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-OSAS — Student Affairs Portal | Colegio de Naujan</title>
    <meta name="description" content="The official digital student affairs management system of Colegio de Naujan. One platform for every student, zero paperwork.">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#D4AF37">
    <link rel="icon" type="image/png" sizes="32x32" href="app/assets/img/default.png">
    <link rel="icon" type="image/png" sizes="192x192" href="app/assets/img/default.png">
    <link rel="shortcut icon" href="app/assets/img/default.png">
    <link rel="apple-touch-icon" href="app/assets/img/default.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --gold: #D4AF37;
            --gold-light: #F0C040;
            --gold-dark: #A07820;
            --bg: #080808;
            --surface: #111111;
            --card: #141414;
            --border: rgba(255,255,255,0.08);
            --text: #F5F5F5;
            --text-muted: rgba(255,255,255,0.45);
            --green: #22c55e;
            --red: #ef4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        html { scroll-behavior: smooth; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        body.light {
            --bg: #ffffff;
            --surface: #f8f9fa;
            --card: #ffffff;
            --border: rgba(0,0,0,0.08);
            --text: #0a0a0a;
            --text-muted: rgba(0,0,0,0.5);
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            letter-spacing: -0.03em;
        }

        /* ===== NAVBAR ===== */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding: 1.25rem 3rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .navbar.scrolled {
            background: rgba(8, 8, 8, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
        }

        body.light .navbar.scrolled {
            background: rgba(255, 255, 255, 0.85);
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .nav-logo {
            width: 42px;
            height: 42px;
            background: rgba(212, 175, 55, 0.1);
            border: 1.5px solid var(--gold);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .nav-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .nav-brand-text {
            display: flex;
            flex-direction: column;
        }

        .nav-brand-name {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
            letter-spacing: -0.02em;
        }

        .nav-brand-sub {
            font-size: 0.7rem;
            color: var(--text-muted);
            font-weight: 500;
            letter-spacing: 0.02em;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 2.5rem;
            list-style: none;
        }

        .nav-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: var(--gold);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-theme {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .btn-theme:hover {
            background: var(--surface);
            color: var(--gold);
            border-color: var(--gold);
        }

        .btn-signin {
            padding: 0.6rem 1.5rem;
            background: transparent;
            border: 1.5px solid var(--gold);
            color: var(--gold);
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            cursor: pointer;
        }

        .btn-signin:hover {
            background: var(--gold);
            color: #000;
        }

        .btn-primary {
            padding: 0.9rem 2rem;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: #000;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            box-shadow: 0 4px 20px rgba(212, 175, 55, 0.3);
            cursor: pointer;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(212, 175, 55, 0.5);
        }

        /* ===== HERO ===== */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 8rem 3rem 4rem;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('app/assets/img/background.jpg') center/cover;
            opacity: 0.15;
            pointer-events: none;
        }

        #particles {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }

        .hero-container {
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .hero-content {
            max-width: 100%;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            background: rgba(212, 175, 55, 0.08);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--gold-light);
            margin-bottom: 2rem;
            letter-spacing: 0.02em;
        }

        .hero-title {
            font-size: clamp(2rem, 5vw, 3.8rem);
            font-weight: 800;
            line-height: 1.05;
            margin-bottom: 1.5rem;
        }

        .hero-title .line {
            display: block;
        }

        .hero-title .gold-gradient {
            background: linear-gradient(135deg, var(--gold-light), var(--gold), var(--gold-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 0.95rem;
            line-height: 1.7;
            color: var(--text-muted);
            margin-bottom: 2.5rem;
            max-width: 680px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero-buttons {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .btn-ghost {
            padding: 0.9rem 2rem;
            background: transparent;
            color: var(--text);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }

        .btn-ghost:hover {
            border-color: var(--gold);
            color: var(--gold);
        }

        .hero-trust {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .trust-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .trust-divider {
            width: 1px;
            height: 16px;
            background: var(--border);
        }

        /* ===== MARQUEE ===== */
        .marquee {
            background: var(--surface);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 1.5rem 0;
            overflow: hidden;
        }

        .marquee-content {
            display: flex;
            animation: scroll 30s linear infinite;
            white-space: nowrap;
        }

        @keyframes scroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        .marquee-item {
            display: inline-flex;
            align-items: center;
            gap: 2rem;
            padding: 0 2rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gold);
            letter-spacing: 0.05em;
        }

        .marquee-divider {
            color: var(--gold);
            opacity: 0.3;
        }

        /* ===== FEATURES BENTO ===== */
        .features {
            padding: 8rem 3rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-tag {
            display: inline-block;
            padding: 0.35rem 1rem;
            background: rgba(212, 175, 55, 0.08);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--gold);
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }

        .section-title {
            font-size: clamp(1.5rem, 3vw, 2.2rem);
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .bento-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }

        .bento-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .bento-card:hover {
            border-color: var(--gold);
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(212, 175, 55, 0.15);
        }

        .bento-card.large {
            grid-column: span 2;
        }

        .bento-card.wide {
            grid-column: span 2;
        }

        .bento-icon {
            width: 48px;
            height: 48px;
            background: rgba(212, 175, 55, 0.1);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--gold);
            margin-bottom: 1.25rem;
        }

        .bento-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: var(--text);
        }

        .bento-desc {
            font-size: 0.9rem;
            line-height: 1.6;
            color: var(--text-muted);
        }

        .violation-list {
            margin-top: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .violation-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--surface);
            border-radius: 8px;
            font-size: 0.85rem;
        }

        .violation-icon {
            width: 32px;
            height: 32px;
            background: rgba(212, 175, 55, 0.1);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: var(--gold);
            flex-shrink: 0;
        }

        .violation-name {
            color: var(--text);
            font-weight: 500;
        }

        .mini-chart {
            margin-top: 1.5rem;
            display: flex;
            align-items: flex-end;
            gap: 0.5rem;
            height: 80px;
        }

        .mini-bar {
            flex: 1;
            background: linear-gradient(to top, var(--gold-dark), var(--gold));
            border-radius: 4px 4px 0 0;
        }

        /* ===== STATS STRIP ===== */
        .stats {
            padding: 6rem 3rem;
            background: var(--surface);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .stats-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 3rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.25rem;
        }

        .stat-desc {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* ===== TESTIMONIAL ===== */
        .testimonial {
            padding: 8rem 3rem;
            text-align: center;
        }

        .testimonial-inner {
            max-width: 900px;
            margin: 0 auto;
        }

        .quote-mark {
            font-size: 4rem;
            line-height: 0.8;
            color: var(--gold);
            opacity: 0.2;
            font-family: Georgia, serif;
            margin-bottom: 1rem;
        }

        .quote-text {
            font-size: 1.3rem;
            font-weight: 600;
            line-height: 1.5;
            color: var(--text);
            margin-bottom: 2rem;
            font-family: 'Space Grotesk', sans-serif;
        }

        .quote-author {
            font-size: 1rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
        }

        .avatars {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--gold);
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            border: 2px solid var(--bg);
        }

        /* ===== CTA ===== */
        .cta {
            padding: 8rem 3rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at center, rgba(212, 175, 55, 0.08), transparent 70%);
            pointer-events: none;
        }

        .cta-inner {
            max-width: 700px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .cta-title {
            font-size: clamp(1.8rem, 3.5vw, 2.8rem);
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .cta-subtitle {
            font-size: 0.95rem;
            color: var(--text-muted);
            margin-bottom: 2.5rem;
        }

        .cta-note {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 1.5rem;
        }

        /* ===== FOOTER ===== */
        .footer {
            background: #050505;
            border-top: 3px solid;
            border-image: linear-gradient(90deg, transparent, var(--gold), transparent) 1;
            padding: 4rem 3rem 2rem;
        }

        body.light .footer {
            background: #f8f9fa;
        }

        .footer-grid {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .footer-brand-name {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 0.75rem;
        }

        .footer-brand-desc {
            font-size: 0.9rem;
            line-height: 1.6;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        .footer-social {
            display: flex;
            gap: 0.75rem;
        }

        .social-link {
            width: 38px;
            height: 38px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.2s;
        }

        body.light .social-link {
            background: rgba(0, 0, 0, 0.03);
        }

        .social-link:hover {
            background: rgba(212, 175, 55, 0.1);
            border-color: var(--gold);
            color: var(--gold);
        }

        .footer-col h4 {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 1.25rem;
        }

        .footer-col ul {
            list-style: none;
        }

        .footer-col ul li {
            margin-bottom: 0.75rem;
        }

        .footer-col ul li a {
            font-size: 0.9rem;
            color: var(--text-muted);
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-col ul li a:hover {
            color: var(--gold);
        }

        .footer-bottom {
            max-width: 1400px;
            margin: 0 auto;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-copy {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .footer-love {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .footer-love .heart {
            color: #ef4444;
        }

        .footer-love .school {
            color: var(--gold);
            font-weight: 600;
        }

        /* ===== ANIMATIONS ===== */
        .fade-up {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .fade-up.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .bento-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .navbar {
                padding: 1rem 1.5rem;
            }

            .nav-links {
                display: none;
            }

            .hero {
                padding: 6rem 1.5rem 3rem;
            }

            .features {
                padding: 4rem 1.5rem;
            }

            .bento-grid {
                grid-template-columns: 1fr;
            }

            .bento-card.large,
            .bento-card.wide {
                grid-column: span 1;
            }

            .stats {
                padding: 4rem 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .testimonial {
                padding: 4rem 1.5rem;
            }

            .cta {
                padding: 4rem 1.5rem;
            }

            .footer {
                padding: 3rem 1.5rem 1.5rem;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }
    </style>
</head>
<body data-eosas-push="guest">


<!-- NAVBAR -->
<nav class="navbar" id="navbar">
    <a href="#" class="nav-brand">
        <div class="nav-logo">
            <img src="./app/assets/img/default.png" alt="E-OSAS Logo">
        </div>
        <div class="nav-brand-text">
            <div class="nav-brand-name">E-OSAS</div>
            <div class="nav-brand-sub">Colegio de Naujan</div>
        </div>
    </a>

    <ul class="nav-links">
        <li><a href="#features">Features</a></li>
        <li><a href="#about">About</a></li>
        <li><a href="#contact">Contact</a></li>
    </ul>

    <div class="nav-actions">
        <button class="btn-theme" id="themeToggle" aria-label="Toggle theme">
            <i class="fas fa-sun" id="themeIcon"></i>
        </button>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?php echo $_SESSION['role'] === 'admin' ? 'includes/dashboard.php' : 'includes/user_dashboard.php'; ?>" class="btn-signin">
                Dashboard <i class="fas fa-arrow-right"></i>
            </a>
        <?php else: ?>
            <button class="btn-signin" id="openLoginModal">
                Sign In <i class="fas fa-arrow-right"></i>
            </button>
        <?php endif; ?>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <canvas id="particles"></canvas>
    
    <div class="hero-container">
        <div class="hero-content fade-up">
            <div class="hero-badge">
                ✦ Official Student Portal — Colegio de Naujan
            </div>

            <h1 class="hero-title">
                <span class="line">E-OSAS.</span>
                <span class="line">Office of Student.</span>
                <span class="line gold-gradient">Affairs and Services.</span>
            </h1>

            <p class="hero-subtitle">
                The official digital platform of the Office of Student Affairs and Services. 
                Streamlining student records, violations, announcements, and welfare management 
                in one secure, modern system.
            </p>

            <div class="hero-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo $_SESSION['role'] === 'admin' ? 'includes/dashboard.php' : 'includes/user_dashboard.php'; ?>" class="btn-primary">
                        Go to Dashboard <i class="fas fa-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <button class="btn-primary" id="openLoginModalHero">
                        Access Portal <i class="fas fa-arrow-right"></i>
                    </button>
                <?php endif; ?>
                <a href="#features" class="btn-ghost">
                    See Features <i class="fas fa-chevron-down"></i>
                </a>
            </div>


        </div>
    </div>
</section>

<!-- MARQUEE -->
<div class="marquee">
    <div class="marquee-content">
        <span class="marquee-item">Student Records <span class="marquee-divider">✦</span></span>
        <span class="marquee-item">Violation Tracking <span class="marquee-divider">✦</span></span>
        <span class="marquee-item">Announcements <span class="marquee-divider">✦</span></span>
        <span class="marquee-item">Reports & Statistics <span class="marquee-divider">✦</span></span>
        <span class="marquee-item">Department Management <span class="marquee-divider">✦</span></span>
        <span class="marquee-item">AI Assistant <span class="marquee-divider">✦</span></span>
        <span class="marquee-item">Role-Based Access <span class="marquee-divider">✦</span></span>
        <span class="marquee-item">Digital Clearance <span class="marquee-divider">✦</span></span>
        <!-- Duplicate for seamless loop -->
        <span class="marquee-item">Student Records <span class="marquee-divider">✦</span></span>
        <span class="marquee-item">Violation Tracking <span class="marquee-divider">✦</span></span>
        <span class="marquee-item">Announcements <span class="marquee-divider">✦</span></span>
        <span class="marquee-item">Reports & Analytics <span class="marquee-divider">✦</span></span>
        <span class="marquee-item">Department Management <span class="marquee-divider">✦</span></span>
        <span class="marquee-item">AI Assistant <span class="marquee-divider">✦</span></span>
        <span class="marquee-item">Role-Based Access <span class="marquee-divider">✦</span></span>
        <span class="marquee-item">Digital Clearance <span class="marquee-divider">✦</span></span>
    </div>
</div>

<!-- FEATURES BENTO -->
<section class="features" id="features">
    <div class="section-header fade-up">
        <span class="section-tag">Capabilities</span>
        <h2 class="section-title">Built for Modern Student Affairs</h2>
    </div>

    <div class="bento-grid">
        <!-- Large Card: Violation Tracking -->
        <div class="bento-card large fade-up">
            <div class="bento-icon">
                <i class="fas fa-triangle-exclamation"></i>
            </div>
            <h3 class="bento-title">Violation Tracking</h3>
            <p class="bento-desc">
                Comprehensive violation management with status tracking, evidence uploads, 
                and automated notifications.
            </p>
            <div class="violation-list">
                <div class="violation-item">
                    <div class="violation-icon">
                        <i class="fas fa-shoe-prints"></i>
                    </div>
                    <span class="violation-name">Improper Footwear</span>
                </div>
                <div class="violation-item">
                    <div class="violation-icon">
                        <i class="fas fa-shirt"></i>
                    </div>
                    <span class="violation-name">Uniform Violation</span>
                </div>
                <div class="violation-item">
                    <div class="violation-icon">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <span class="violation-name">ID Not Worn</span>
                </div>
            </div>
        </div>

        <!-- Medium Card: Student Records -->
        <div class="bento-card fade-up">
            <div class="bento-icon">
                <i class="fas fa-users"></i>
            </div>
            <h3 class="bento-title">Student Records</h3>
            <p class="bento-desc">
                Centralized student profiles with complete academic history, photos, 
                and contact information.
            </p>
        </div>

        <!-- Medium Card: Announcements -->
        <div class="bento-card fade-up">
            <div class="bento-icon">
                <i class="fas fa-bullhorn"></i>
            </div>
            <h3 class="bento-title">Announcements</h3>
            <p class="bento-desc">
                Broadcast important notices and updates directly to students 
                through the portal in real-time.
            </p>
        </div>

        <!-- Card: Reports & Analytics -->
        <div class="bento-card wide fade-up">
            <div class="bento-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
            <h3 class="bento-title">Reports & Statistics</h3>
            <p class="bento-desc">
                Generate detailed reports on violations, student behavior trends, 
                and departmental statistics with export capabilities.
            </p>
        </div>

        <!-- Small Card: AI Chatbot -->
        <div class="bento-card fade-up">
            <div class="bento-icon">
                <i class="fas fa-robot"></i>
            </div>
            <h3 class="bento-title">Addons AI Chatbot</h3>
            <p class="bento-desc">
                Intelligent assistant to help students and staff navigate 
                the system instantly.
            </p>
        </div>

        <!-- Small Card: Role-Based Access -->
        <div class="bento-card fade-up">
            <div class="bento-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <h3 class="bento-title">Role-Based Access</h3>
            <p class="bento-desc">
                Secure, permission-based access control for admins, 
                staff, and students.
            </p>
        </div>

        <!-- Small Card: Offline/Online Mode -->
        <div class="bento-card wide fade-up">
            <div class="bento-icon">
                <i class="fas fa-wifi"></i>
            </div>
            <h3 class="bento-title">Offline / Online Mode</h3>
            <p class="bento-desc">
                Works even without internet. Data syncs automatically 
                when connectivity is restored.
            </p>
        </div>

        <!-- Small Card: Push Notifications -->
        <div class="bento-card wide fade-up">
            <div class="bento-icon">
                <i class="fas fa-bell"></i>
            </div>
            <h3 class="bento-title">Push Notifications</h3>
            <p class="bento-desc">
                Real-time alerts for violations, announcements, and 
                important updates delivered instantly.
            </p>
        </div>
    </div>
</section>

<!-- STATS -->
<section class="stats">
    <div class="stats-grid">
        <div class="stat-item fade-up">
            <div class="stat-number" data-target="500">0</div>
            <div class="stat-label">Students</div>
            <div class="stat-desc">Enrolled across all departments</div>
        </div>
        <div class="stat-item fade-up">
            <div class="stat-number" data-target="6">0</div>
            <div class="stat-label">Departments</div>
            <div class="stat-desc">Fully integrated system-wide</div>
        </div>
        <div class="stat-item fade-up">
            <div class="stat-number" data-target="100">0</div>
            <div class="stat-label">Paperless</div>
            <div class="stat-desc">100% digital record management</div>
        </div>
        <div class="stat-item fade-up">
            <div class="stat-number" data-target="24">0</div>
            <div class="stat-label">24/7 Access</div>
            <div class="stat-desc">Available anytime, anywhere</div>
        </div>
    </div>
</section>

<!-- TESTIMONIAL -->
<section class="testimonial" id="about">
    <div class="testimonial-inner fade-up">
        <div class="quote-mark">"</div>
        <p class="quote-text">
            E-OSAS has transformed how we manage student affairs — from paper-based 
            chaos to a fully digital, organized system.
        </p>
        <p class="quote-author">
            — Office of Student Affairs, Colegio de Naujan
        </p>
        <div class="avatars">
            <div class="avatar">MR</div>
            <div class="avatar">JD</div>
            <div class="avatar">AS</div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta">
    <div class="cta-inner fade-up">
        <h2 class="cta-title">Ready to get started?</h2>
        <p class="cta-subtitle">
            Sign in to access the student affairs portal and manage everything in one place.
        </p>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?php echo $_SESSION['role'] === 'admin' ? 'includes/dashboard.php' : 'includes/user_dashboard.php'; ?>" class="btn-primary">
                Go to Dashboard <i class="fas fa-arrow-right"></i>
            </a>
        <?php else: ?>
            <button class="btn-primary" id="openLoginModalCTA">
                Sign In to E-OSAS <i class="fas fa-arrow-right"></i>
            </button>
        <?php endif; ?>
        <p class="cta-note">
            Authorized personnel and enrolled students only.
        </p>
    </div>
</section>

<!-- FOOTER -->
<footer class="footer" id="contact">
    <div class="footer-grid">
        <div class="footer-brand">
            <div class="footer-brand-name">E-OSAS</div>
            <p class="footer-brand-desc">
                The official digital platform of the Office of Student Affairs and Services 
                at Colegio de Naujan, Santiago, Oriental Mindoro. Empowering students through technology.
            </p>
            <div class="footer-social">
                <a href="#" class="social-link" aria-label="Facebook">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="#" class="social-link" aria-label="Email">
                    <i class="fas fa-envelope"></i>
                </a>
            </div>
        </div>

        <div class="footer-col">
            <h4>System</h4>
            <ul>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="<?php echo $_SESSION['role'] === 'admin' ? 'includes/dashboard.php' : 'includes/user_dashboard.php'; ?>">Dashboard</a></li>
                    <li><a href="app/views/auth/logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="#" id="openLoginModalFooter">Sign In</a></li>
                <?php endif; ?>
                <li><a href="#features">Features</a></li>
                <li><a href="#about">About</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Institution</h4>
            <ul>
                <li><a href="#">Colegio de Naujan</a></li>
                <li><a href="#">Santiago, Oriental Mindoro</a></li>
                <li><a href="#">Student Handbook</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Contact</h4>
            <ul>
                <li><a href="mailto:osas@colegiodenaujan.edu.ph">osas@colegiodenaujan.edu.ph</a></li>
                <li><a href="tel:+639989134594">+63 998 913 4594</a></li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <p class="footer-copy">
            &copy; <?php echo date('Y'); ?> E-OSAS. All rights reserved.
        </p>
    </div>
</footer>

<!-- Login Modal -->
<div id="loginModal" class="login-modal-overlay">
    <div class="login-modal-content">
        <button class="close-login-modal" id="closeLoginModal">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="login-modal-header">
            <div class="login-modal-logo">
                <img src="./app/assets/img/default.png" alt="E-OSAS Logo">
            </div>
            <h2>Welcome Back</h2>
            <p>Please enter your credentials to login</p>
        </div>

        <form id="loginForm" class="login-modal-form">
            <!-- Inline alert banner -->
            <div id="loginAlert" class="login-alert" style="display:none;"></div>

            <div class="form-group">
                <label for="username">Username or Email</label>
                <input id="username" name="username" type="text" placeholder="Enter your username or email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-input-wrapper">
                    <input id="password" name="password" type="password" placeholder="Enter your password" required>
                    <button type="button" class="toggle-password" id="passwordToggle">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" id="rememberMe">
                    <span class="checkmark"></span>
                    Remember me
                </label>
                <a href="#" class="forgot-password" id="forgotPasswordBtn">Forgot password?</a>
            </div>

            <button type="submit" class="login-button" id="loginButton">
                <span>Login</span>
            </button>
        </form>

        <div class="login-modal-footer">
            <p>Use the email provided by the admin and the default password to login.</p>
        </div>
    </div>
</div>

<!-- Forgot Password Modal -->
<div id="forgotPasswordModal" class="forgot-modal-overlay">
    <div class="forgot-modal-content">
        <div class="forgot-modal-header">
            <div class="forgot-modal-icon">
                <i class="fas fa-key"></i>
            </div>
            <h2>Reset Password</h2>
            <button class="close-forgot-modal" id="closeForgotModal">&times;</button>
        </div>
        <div class="forgot-modal-body">
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <p>You need to go to <strong>admin</strong> and request to reset the password.</p>
            </div>
        </div>
        <div class="forgot-modal-footer">
            <button class="modal-btn-primary" id="gotItBtn">Got it</button>
        </div>
    </div>
</div>

<style>
    /* Login Modal Styles */
    .login-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.75);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 10000;
        opacity: 0;
        padding: 20px;
        transition: opacity 0.35s ease;
    }

    .login-modal-overlay.show {
        display: flex;
        opacity: 1;
    }

    .login-modal-content {
        background: var(--card);
        width: 100%;
        max-width: 380px;
        padding: 28px 32px;
        border-radius: 20px;
        border: 1px solid var(--border);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        transform: scale(0.7) translateY(30px);
        opacity: 0;
        transition: none;
        position: relative;
        max-height: 90vh;
        overflow-y: auto;
    }

    .login-modal-overlay.show .login-modal-content {
        animation: loginModalPop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    }

    @keyframes loginModalPop {
        0% {
            opacity: 0;
            transform: scale(0.7) translateY(30px);
        }
        50% {
            opacity: 1;
        }
        100% {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .close-login-modal {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: transparent;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1rem;
        transition: all 0.2s;
    }

    .close-login-modal:hover {
        background: var(--surface);
        color: var(--gold);
        border-color: var(--gold);
    }

    .login-modal-header {
        text-align: center;
        margin-bottom: 20px;
    }

    .login-modal-logo {
        width: 52px;
        height: 52px;
        background: rgba(212, 175, 55, 0.1);
        border: 2px solid var(--gold);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 14px;
        overflow: hidden;
    }

    .login-modal-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .login-modal-header h2 {
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--text);
        margin-bottom: 6px;
    }

    .login-modal-header p {
        font-size: 0.82rem;
        color: var(--text-muted);
    }

    .login-modal-form .form-group {
        margin-bottom: 14px;
    }

    .login-modal-form label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 8px;
    }

    .login-modal-form input[type="text"],
    .login-modal-form input[type="password"] {
        width: 100%;
        padding: 10px 14px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 10px;
        font-size: 0.95rem;
        color: var(--text);
        transition: all 0.2s;
    }

    .login-modal-form input:focus {
        outline: none;
        border-color: var(--gold);
        box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
    }

    .password-input-wrapper {
        position: relative;
    }

    .toggle-password {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        padding: 8px;
        transition: color 0.2s;
    }

    .toggle-password:hover {
        color: var(--gold);
    }

    .form-options {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        font-size: 0.875rem;
    }

    .remember-me {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        color: var(--text);
    }

    .remember-me input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--gold);
    }

    .forgot-password {
        color: var(--gold);
        text-decoration: none;
        font-weight: 500;
        transition: opacity 0.2s;
    }

    .forgot-password:hover {
        opacity: 0.8;
    }

    .login-button {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, var(--gold), var(--gold-dark));
        color: #000;
        border: none;
        border-radius: 10px;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 4px 20px rgba(212, 175, 55, 0.3);
    }

    .login-button:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 30px rgba(212, 175, 55, 0.5);
    }

    .login-button:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }

    .login-button .spinner {
        border: 3px solid rgba(0, 0, 0, 0.3);
        border-top: 3px solid #000;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .login-modal-footer {
        text-align: center;
        margin-top: 20px;
    }

    .login-modal-footer p {
        font-size: 0.72rem;
        color: var(--text-muted);
        line-height: 1.5;
    }

    /* ── Login modal font scale-down ── */
    .login-modal-content { font-size: 13px !important; scrollbar-width: none !important; -ms-overflow-style: none !important; }
    .login-modal-content::-webkit-scrollbar { display: none !important; }
    .login-modal-overlay { overflow: hidden !important; }
    .login-modal-header h2 { font-size: 1.1rem !important; }
    .login-modal-header p  { font-size: 0.72rem !important; }
    .login-modal-form label { font-size: 0.72rem !important; }
    .login-modal-form input[type="text"],
    .login-modal-form input[type="password"],
    .login-modal-form input[type="email"] { font-size: 0.8rem !important; padding: 9px 12px !important; }
    .login-button { font-size: 0.82rem !important; padding: 10px !important; }
    .login-modal-logo { width: 46px !important; height: 46px !important; }

    /* Inline login alert banner */
    .login-alert {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 0.875rem;
        font-weight: 500;
        margin-bottom: 18px;
        animation: fadeInDown 0.25s ease;
    }

    .login-alert.alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.35);
        color: #ef4444;
    }

    .login-alert.alert-success {
        background: rgba(34, 197, 94, 0.1);
        border: 1px solid rgba(34, 197, 94, 0.35);
        color: #22c55e;
    }

    .login-alert i {
        font-size: 1rem;
        flex-shrink: 0;
    }

    /* Forgot Password Modal Styles */
    .forgot-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(5px);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 10001;
        opacity: 0;
        transition: opacity 0.3s ease;
        padding: 20px;
    }

    .forgot-modal-overlay.show {
        display: flex;
        opacity: 1;
    }

    .forgot-modal-content {
        background: var(--card);
        width: 100%;
        max-width: 400px;
        padding: 30px;
        border-radius: 20px;
        border: 1px solid var(--border);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        transform: translateY(20px);
        transition: transform 0.3s ease;
        text-align: center;
        position: relative;
    }

    .forgot-modal-overlay.show .forgot-modal-content {
        transform: translateY(0);
    }

    .forgot-modal-header {
        margin-bottom: 20px;
    }

    .forgot-modal-icon {
        width: 60px;
        height: 60px;
        background: rgba(212, 175, 55, 0.1);
        color: var(--gold);
        font-size: 24px;
        border-radius: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 0 auto 15px;
    }

    .forgot-modal-header h2 {
        font-size: 1.5rem;
        color: var(--text);
        margin: 0;
    }

    .close-forgot-modal {
        position: absolute;
        top: 15px;
        right: 20px;
        background: none;
        border: none;
        font-size: 24px;
        color: var(--text-muted);
        cursor: pointer;
        transition: color 0.2s;
    }

    .close-forgot-modal:hover {
        color: var(--text);
    }

    .forgot-modal-body {
        margin-bottom: 25px;
    }

    .info-box {
        background: rgba(212, 175, 55, 0.05);
        border-left: 4px solid var(--gold);
        padding: 15px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 12px;
        text-align: left;
    }

    .info-box i {
        color: var(--gold);
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .info-box p {
        margin: 0;
        color: var(--text);
        font-size: 0.95rem;
        line-height: 1.5;
    }

    .modal-btn-primary {
        background: var(--gold);
        color: #000;
        border: none;
        padding: 12px 30px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
        font-size: 0.95rem;
    }

    .modal-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
    }

    /* Toast Notification */
    .toast {
        position: fixed;
        top: 24px;
        right: 24px;
        background: #ffffff;
        color: #1a1a1a;
        padding: 0;
        border-radius: 14px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.14), 0 2px 8px rgba(0,0,0,0.08);
        display: flex;
        align-items: stretch;
        opacity: 0;
        transform: translateX(120%) scale(0.95);
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        z-index: 99999;
        min-width: 300px;
        max-width: 400px;
        overflow: hidden;
        font-family: 'Inter', sans-serif;
    }

    .toast.show {
        opacity: 1;
        transform: translateX(0) scale(1);
    }

    /* Colored left accent bar */
    .toast-accent {
        width: 5px;
        flex-shrink: 0;
        border-radius: 14px 0 0 14px;
    }

    .toast.success .toast-accent { background: #22c55e; }
    .toast.error   .toast-accent { background: #ef4444; }
    .toast.warning .toast-accent { background: #f59e0b; }
    .toast.info    .toast-accent { background: #3b82f6; }

    .toast-inner {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px 14px 14px;
        flex: 1;
    }

    /* Icon circle */
    .toast-icon-wrap {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1rem;
    }

    .toast.success .toast-icon-wrap { background: rgba(34,197,94,0.12);  color: #22c55e; }
    .toast.error   .toast-icon-wrap { background: rgba(239,68,68,0.12);  color: #ef4444; }
    .toast.warning .toast-icon-wrap { background: rgba(245,158,11,0.12); color: #f59e0b; }
    .toast.info    .toast-icon-wrap { background: rgba(59,130,246,0.12); color: #3b82f6; }

    .toast-body {
        flex: 1;
        min-width: 0;
    }

    .toast-title-text {
        display: block;
        font-size: 0.85rem;
        font-weight: 700;
        color: #111;
        margin-bottom: 2px;
        letter-spacing: -0.01em;
    }

    .toast-msg-text {
        display: block;
        font-size: 0.78rem;
        color: #666;
        line-height: 1.4;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .toast-close {
        background: none;
        border: none;
        color: #bbb;
        cursor: pointer;
        font-size: 0.75rem;
        padding: 4px;
        transition: color 0.2s;
        align-self: flex-start;
        margin-top: 2px;
        flex-shrink: 0;
    }

    .toast-close:hover { color: #555; }

    /* Progress bar at bottom */
    .toast-progress {
        position: absolute;
        bottom: 0;
        left: 5px;
        right: 0;
        height: 3px;
        background: rgba(0,0,0,0.06);
        border-radius: 0 0 14px 0;
        overflow: hidden;
    }

    .toast-progress-bar {
        height: 100%;
        width: 100%;
        transform-origin: left;
        border-radius: inherit;
    }

    .toast.success .toast-progress-bar { background: #22c55e; }
    .toast.error   .toast-progress-bar { background: #ef4444; }
    .toast.warning .toast-progress-bar { background: #f59e0b; }
    .toast.info    .toast-progress-bar { background: #3b82f6; }

    /* Responsive */
    @media (max-width: 768px) {
        .login-modal-content {
            padding: 30px 24px;
        }

        .toast {
            right: 10px;
            left: 10px;
            min-width: auto;
        }
    }
</style>

<script src="service-worker.js"></script>
<script src="app/assets/js/pwa.js"></script>
<script src="app/assets/js/push-notifications.js?v=6"></script>
<script src="app/assets/js/guestAnnouncements.js?v=4"></script>
<script src="app/assets/js/session.js"></script>
<script src="app/assets/js/login.js"></script>
<script>
    // Login Modal Management (extends login.js functionality)
    document.addEventListener('DOMContentLoaded', function() {
        const loginModal = document.getElementById('loginModal');
        const forgotModal = document.getElementById('forgotPasswordModal');

        // Open login modal buttons
        const openLoginBtns = [
            document.getElementById('openLoginModal'),
            document.getElementById('openLoginModalHero'),
            document.getElementById('openLoginModalCTA'),
            document.getElementById('openLoginModalFooter')
        ];

        openLoginBtns.forEach(btn => {
            if (btn) {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    loginModal.classList.add('show');
                    document.body.style.overflow = 'hidden';
                });
            }
        });

        // Close login modal
        const closeLoginBtn = document.getElementById('closeLoginModal');
        if (closeLoginBtn) {
            closeLoginBtn.addEventListener('click', () => {
                loginModal.classList.remove('show');
                document.body.style.overflow = '';
            });
        }

        // Close on overlay click
        if (loginModal) {
            loginModal.addEventListener('click', (e) => {
                if (e.target === loginModal) {
                    loginModal.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });
        }

        // Close modals on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (loginModal && loginModal.classList.contains('show')) {
                    loginModal.classList.remove('show');
                    document.body.style.overflow = '';
                }
                if (forgotModal && forgotModal.classList.contains('show')) {
                    forgotModal.classList.remove('show');
                }
            }
        });
    });
</script>

<!-- PWA Install Button -->
<button id="installPWA" class="pwa-install-btn" style="display: none;">
    <i class="fas fa-download"></i> Install App
</button>

<style>
    .pwa-install-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        padding: 14px 24px;
        background: linear-gradient(135deg, var(--gold), var(--gold-dark));
        color: #000;
        border: none;
        border-radius: 50px;
        font-size: 0.95rem;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 8px 24px rgba(212, 175, 55, 0.4);
        z-index: 9999;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        animation: pulse 2s infinite;
    }

    .pwa-install-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 32px rgba(212, 175, 55, 0.6);
    }

    .pwa-install-btn i {
        font-size: 1.1rem;
    }

    @keyframes pulse {
        0%, 100% {
            box-shadow: 0 8px 24px rgba(212, 175, 55, 0.4);
        }
        50% {
            box-shadow: 0 8px 32px rgba(212, 175, 55, 0.7);
        }
    }

    @media (max-width: 768px) {
        .pwa-install-btn {
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            font-size: 0.875rem;
        }
    }
</style>

<script>
    // Theme Toggle
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const body = document.body;

    function setTheme(isDark) {
        if (isDark) {
            body.classList.remove('light');
            themeIcon.className = 'fas fa-sun';
        } else {
            body.classList.add('light');
            themeIcon.className = 'fas fa-moon';
        }
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    }

    // Initialize theme — default is light, respect saved preference
    const savedTheme = localStorage.getItem('theme');
    if (!savedTheme) {
        // First visit: force light and save it
        localStorage.setItem('theme', 'light');
        setTheme(false);
    } else {
        setTheme(savedTheme === 'dark');
    }

    themeToggle.addEventListener('click', () => {
        setTheme(body.classList.contains('light'));
    });

    // Navbar scroll effect
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        navbar.classList.toggle('scrolled', window.scrollY > 50);
    }, { passive: true });

    // Intersection Observer for fade-up animations
    const fadeElements = document.querySelectorAll('.fade-up');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.1 });

    fadeElements.forEach(el => observer.observe(el));

    // Counter animation
    function animateCounter(element) {
        const target = parseInt(element.dataset.target);
        const duration = 2000;
        const start = performance.now();

        function update(currentTime) {
            const elapsed = currentTime - start;
            const progress = Math.min(elapsed / duration, 1);
            const value = Math.floor(progress * target);
            element.textContent = value;

            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                element.textContent = target + (target === 500 ? '+' : target === 100 ? '%' : target === 24 ? '/7' : '');
            }
        }

        requestAnimationFrame(update);
    }

    const statNumbers = document.querySelectorAll('.stat-number[data-target]');
    const statsObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCounter(entry.target);
                statsObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    statNumbers.forEach(el => statsObserver.observe(el));

    // Particle canvas
    const canvas = document.getElementById('particles');
    const ctx = canvas.getContext('2d');
    let particles = [];

    function resizeCanvas() {
        canvas.width = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
    }

    function createParticles() {
        particles = [];
        const count = Math.floor((canvas.width * canvas.height) / 15000);
        for (let i = 0; i < count; i++) {
            particles.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                radius: Math.random() * 2 + 0.5,
                vx: (Math.random() - 0.5) * 0.3,
                vy: (Math.random() - 0.5) * 0.3,
                alpha: Math.random() * 0.5 + 0.2
            });
        }
    }

    function drawParticles() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach(p => {
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(212, 175, 55, ${p.alpha})`;
            ctx.fill();

            p.x += p.vx;
            p.y += p.vy;

            if (p.x < 0 || p.x > canvas.width) p.vx *= -1;
            if (p.y < 0 || p.y > canvas.height) p.vy *= -1;
        });
        requestAnimationFrame(drawParticles);
    }

    resizeCanvas();
    createParticles();
    drawParticles();

    window.addEventListener('resize', () => {
        resizeCanvas();
        createParticles();
    }, { passive: true });
</script>

</body>
</html>