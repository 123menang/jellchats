<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
// Mendapatkan protokol (http atau https)

// Penggunaan:
?>

<!DOCTYPE html>
<html lang="en-US" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    
    <title>LiveChat® - The Best AI Live Chat Software for Business | LiveChat.com</title>
     <link rel="icon" href="/assets/images/icon-jellchat.png">
    <meta name="description" content="LiveChat® is the best AI live chat software for business, perfect for ecommerce and B2B. It powers real-time website support, boosting sales and customer satisfaction.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
    

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] ?>">
    <meta property="og:title" content="LiveChat® - The Best AI Live Chat Software for Business">
    <meta property="og:description" content="Boost sales and customer satisfaction with the best AI live chat software for business.">
    <meta property="og:image" content="<?= (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] ?>/assets/img/og-image.jpg">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] ?>">
    <meta property="twitter:title" content="LiveChat® - The Best AI Live Chat Software for Business">
    <meta property="twitter:description" content="Boost sales and customer satisfaction with the best AI live chat software for business.">
    <meta property="twitter:image" content="<?= (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] ?>/assets/img/og-image.jpg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] ?>/assets/css/jellchats.css?id=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/jellchats.css') ?>">
    <style>
        /* ========================================
           ROOT VARIABLES
        ======================================== */
        :root {
            --blue: #1e62ff;
            --blue-dark: #1348cc;
            --blue-light: #eff6ff;
            --red: #ff3b30;
            --green: #10b981;
            --amber: #f59e0b;
            --dark: #0a0f1e;
            --dark2: #1a2035;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --bg: #fafafa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 80px;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            color: var(--text);
            background: #fff;
            overflow-x: hidden;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--blue);
            border-radius: 5px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--blue-dark);
        }

        /* Banner */
        .banner {
            background: #0a0f1e;
            color: #fff;
            text-align: center;
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 500;
        }
        .banner a {
            color: #fff;
            font-weight: 700;
            text-decoration: underline;
            margin-left: 6px;
        }
        .banner a:hover {
            text-decoration: none;
        }

        /* ========================================
           NAVIGATION STYLES
        ======================================== */
        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 5%;
            height: 70px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        nav.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            height: 62px;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            flex-shrink: 0;
            transition: transform 0.2s ease;
        }

        .logo:hover {
            transform: scale(1.02);
        }

        .logo-img {
            height: 60px;
            width: auto;
            object-fit: contain;
            display: block;
            transition: height 0.3s ease;
        }

        nav.scrolled .logo-img {
            height: 42px;
        }

        .nav-center {
            display: flex;
            align-items: center;
            gap: 4px;
            list-style: none;
        }

        .nav-center li a {
            display: inline-flex;
            align-items: center;
            padding: 8px 14px;
            color: var(--muted);
            font-weight: 500;
            font-size: 14px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-center li a:hover {
            color: var(--text);
            background: #f3f4f6;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .btn-ghost {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            background: transparent;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            color: var(--text);
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-ghost:hover {
            border-color: var(--blue);
            color: var(--blue);
            transform: translateY(-2px);
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: #ff0000;
            border: none;
            border-radius: 9px;
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-primary i {
            font-size: 14px;
            transition: transform 0.2s ease;
        }

        .btn-primary:hover {
            background: var(--blue-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(30, 98, 255, 0.35);
        }

        .btn-primary:hover i {
            transform: rotate(-15deg) scale(1.1);
        }

        /* Hero Section */
        .hero {
            min-height: 88vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 80px 20px;
            position: relative;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 60% at 50% -10%, rgba(30, 98, 255, 0.1), transparent 70%);
            pointer-events: none;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: #eff6ff;
            color: var(--blue);
            border: 1px solid #bfdbfe;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        .hero h1 {
            font-size: clamp(36px, 6vw, 68px);
            font-weight: 800;
            line-height: 1.08;
            margin-bottom: 22px;
            letter-spacing: -1.5px;
            max-width: 900px;
        }

        .hero h1 span {
            background: linear-gradient(135deg, var(--blue), #8b5cf6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .hero p {
            font-size: clamp(16px, 2vw, 20px);
            color: var(--muted);
            max-width: 600px;
            line-height: 1.65;
            margin-bottom: 40px;
        }

        .hero-actions {
            display: flex;
            gap: 14px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 56px;
        }

        .btn-lg {
            padding: 15px 32px;
            font-size: 16px;
            border-radius: 12px;
        }

        .btn-outline-lg {
            padding: 14px 28px;
            border: 1.5px solid #e5e7eb;
            border-radius: 12px;
            background: transparent;
            color: var(--text);
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-outline-lg:hover {
            border-color: var(--blue);
            color: var(--blue);
            transform: translateY(-2px);
        }

        .hero-trust {
            display: flex;
            align-items: center;
            gap: 16px;
            color: var(--muted);
            font-size: 13px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .hero-trust span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .hero-trust i {
            color: var(--green);
        }

        /* Dashboard Preview */
        .preview-wrap {
            max-width: 1100px;
            margin: 0 auto 80px;
            padding: 0 20px;
            position: relative;
        }

        .preview-glow {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 60%;
            background: radial-gradient(ellipse, rgba(30, 98, 255, 0.12), transparent);
            filter: blur(40px);
            pointer-events: none;
            z-index: -1;
        }

        .fake-dash {
            display: flex;
            height: 480px;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.14);
        }

        .fd-sidebar {
            width: 56px;
            background: #0a0f1e;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 16px 0;
            gap: 8px;
            flex-shrink: 0;
        }

        .fd-ico {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.45);
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .fd-ico.act {
            background: rgba(30, 98, 255, 0.25);
            color: var(--blue);
        }

        .fd-ico:hover {
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.9);
        }

        .fd-sp {
            flex: 1;
        }

        .fd-list {
            width: 280px;
            background: #fff;
            border-right: 1px solid #f0f0f0;
            display: flex;
            flex-direction: column;
        }

        .fd-list-header {
            padding: 16px 18px;
            font-weight: 700;
            font-size: 15px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .fd-filter {
            font-size: 12px;
            color: var(--blue);
            font-weight: 600;
        }

        .fd-section {
            padding: 8px 16px;
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-top: 4px;
        }

        .fd-chat-item {
            padding: 12px 16px;
            cursor: pointer;
            transition: background 0.15s;
            border-radius: 10px;
            margin: 2px 8px;
            position: relative;
        }

        .fd-chat-item:hover,
        .fd-chat-item.act {
            background: #f0f5ff;
        }

        .fd-chat-item.act {
            background: var(--blue-light);
        }

        .fd-ci-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .fd-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue), #8b5cf6);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            flex-shrink: 0;
            position: relative;
        }

        .fd-avatar .online {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 10px;
            height: 10px;
            background: #10b981;
            border-radius: 50%;
            border: 2px solid #fff;
        }

        .fd-ci-info {
            flex: 1;
            min-width: 0;
        }

        .fd-ci-name {
            font-size: 13px;
            font-weight: 600;
            color: #111;
        }

        .fd-ci-msg {
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .fd-ci-meta {
            font-size: 11px;
            color: var(--muted);
            white-space: nowrap;
        }

        .fd-unread {
            width: 18px;
            height: 18px;
            background: var(--red);
            color: #fff;
            border-radius: 9px;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: 10px;
            right: 14px;
        }

        .fd-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f9fafb;
        }

        .fd-main-header {
            padding: 14px 20px;
            background: #fff;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .fd-main-title {
            font-size: 15px;
            font-weight: 700;
        }

        .fd-main-actions {
            display: flex;
            gap: 6px;
        }

        .fd-icon-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #f3f4f6;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            transition: all 0.2s;
        }

        .fd-icon-btn:hover {
            background: #e5e7eb;
        }

        .fd-msgs {
            flex: 1;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            overflow-y: auto;
        }

        .fd-msg {
            max-width: 68%;
            padding: 10px 14px;
            border-radius: 16px;
            font-size: 13px;
            line-height: 1.5;
        }

        .fd-msg-v {
            background: var(--blue);
            color: #fff;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }

        .fd-msg-a {
            background: #fff;
            color: #111;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
        }

        .fd-input {
            padding: 12px 16px;
            background: #fff;
            border-top: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .fd-input-box {
            flex: 1;
            background: #f3f4f6;
            border-radius: 20px;
            padding: 9px 16px;
            font-size: 13px;
            color: #374151;
        }

        .fd-send {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--blue);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .fd-send:hover {
            background: var(--blue-dark);
        }

        .fd-right {
            width: 220px;
            background: #fff;
            border-left: 1px solid #f0f0f0;
            padding: 16px;
            overflow-y: auto;
        }

        .fd-right-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            margin: 0 auto 8px;
        }

        .fd-right-name {
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 2px;
        }

        .fd-right-email {
            font-size: 11px;
            color: var(--muted);
            text-align: center;
            margin-bottom: 12px;
        }

        .fd-map {
            height: 80px;
            background: linear-gradient(135deg, #e8f4e8, #d4e8d4);
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #10b981;
            font-size: 22px;
        }

        .fd-info-row {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            padding: 4px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .fd-info-label {
            color: var(--muted);
        }

        .fd-info-val {
            font-weight: 600;
            color: #111;
        }

        /* Stats */
        .stats {
            padding: 0 6% 50px;
        }
        .stats-grid {
            max-width: 1000px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            text-align: center;
            background: var(--blue);
            padding: 50px 40px;
            border-radius: 48px;
            color: #fff;
        }
        .stat-num {
            font-size: 42px;
            font-weight: 800;
            letter-spacing: -1px;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 4px;
        }

        /* Features */
        .features {
            padding: 100px 6%;
            max-width: 1200px;
            margin: 0 auto;
        }
        .section-eyebrow {
            font-size: 13px;
            font-weight: 700;
            color: var(--blue);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        .section-title {
            font-size: clamp(28px, 4vw, 44px);
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 16px;
        }
        .section-sub {
            font-size: 17px;
            color: var(--muted);
            line-height: 1.65;
            max-width: 580px;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 60px;
        }
        .feat-card {
            padding: 28px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: #fff;
            transition: all 0.4s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            transform: translateY(0);
        }
        .feat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.1);
            border-color: transparent;
        }
        .feat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 16px;
        }
        .feat-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .feat-desc {
            font-size: 14px;
            color: var(--muted);
            line-height: 1.6;
        }

        /* How It Works */
        .how {
            padding: 100px 6%;
            background: var(--bg);
        }
        .how-inner {
            max-width: 1000px;
            margin: 0 auto;
        }
        .how-steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 40px;
            margin-top: 60px;
        }
        .how-step {
            text-align: center;
        }
        .how-num {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--blue);
            color: #fff;
            font-size: 20px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            transition: all 0.3s;
        }
        .how-step:hover .how-num {
            transform: scale(1.1);
        }
        .how-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .how-desc {
            font-size: 14px;
            color: var(--muted);
            line-height: 1.6;
        }

        /* Pricing */
        .pricing {
            padding: 100px 6%;
        }
        .pricing-inner {
            max-width: 1100px;
            margin: 0 auto;
            text-align: center;
        }
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 60px;
        }
        .p-card {
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 36px 32px;
            text-align: left;
            position: relative;
            transition: all 0.4s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            background: #fff;
            transform: translateY(0);
        }
        .p-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.12);
        }
        .p-card.featured {
            border: 2px solid #011970;
            box-shadow: 0 20px 50px rgba(30, 98, 255, 0.15);
            overflow: hidden;
        }
        .p-badge {
            position: absolute;
            top: 4px;
            left: 50%;
            transform: translateX(-50%);
            background: #011970;
            color: #fff;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .p-tier {
            font-size: 14px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 12px;
        }
        .p-price {
            font-size: 38px;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 4px;
        }
        .p-price span {
            font-size: 16px;
            font-weight: 500;
            color: var(--muted);
        }
        .p-desc {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }
        .p-features {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 11px;
            margin-bottom: 32px;
        }
        .p-features li {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .p-features li i {
            color: var(--green);
            width: 16px;
            flex-shrink: 0;
        }
        .p-btn {
            width: 100%;
            padding: 13px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }
        .p-btn-outline {
            background: transparent;
            border: 1.5px solid var(--border);
            color: var(--text);
        }
        .p-btn-outline:hover {
            border-color: var(--blue);
            color: var(--blue);
            transform: translateY(-2px);
        }
        .p-btn-fill {
            background: #ff0000;
            color: #fff;
        }
        .p-btn-fill:hover {
            background: var(--blue-dark);
            transform: translateY(-2px);
        }

        /* CTA */
        .cta-section {
            padding: 100px 6%;
            background: linear-gradient(135deg, var(--dark), var(--dark2));
            text-align: center;
            color: #fff;
        }
        .cta-section h2 {
            font-size: clamp(28px, 4vw, 48px);
            font-weight: 800;
            margin-bottom: 16px;
        }
        .cta-section p {
            font-size: 18px;
            opacity: 0.75;
            max-width: 500px;
            margin: 0 auto 40px;
        }
        .cta-form {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .cta-email {
            padding: 14px 20px;
            border-radius: 10px;
            border: none;
            font-size: 15px;
            width: 320px;
            outline: none;
            transition: all 0.3s;
        }
        .cta-email:focus {
            transform: scale(1.02);
            box-shadow: 0 0 0 3px rgba(30, 98, 255, 0.3);
        }
        .cta-btn {
            padding: 14px 28px;
            background: var(--blue);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        .cta-btn:hover {
            background: var(--blue-dark);
            transform: translateY(-3px);
        }

        /* Footer */
        footer {
            background: #0a0f1e;
            color: rgba(255, 255, 255, 0.7);
            padding: 60px 6% 40px;
        }
        .footer-inner {
            max-width: 1200px;
            margin: 0 auto;
        }
        .footer-top {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 50px;
            margin-bottom: 50px;
        }
        .footer-brand p {
            font-size: 14px;
            line-height: 1.7;
            margin-top: 12px;
            max-width: 260px;
        }
        .footer-col h4 {
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 16px;
        }
        .footer-col ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 9px;
        }
        .footer-col ul li a {
            text-decoration: none;
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            transition: color 0.2s;
        }
        .footer-col ul li a:hover {
            color: #fff;
        }
        .footer-bot {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
        }
        .footer-socials {
            display: flex;
            gap: 12px;
        }
        .footer-socials a {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.6);
            transition: all 0.3s;
            text-decoration: none;
        }
        .footer-socials a:hover {
            background: var(--blue);
            color: #fff;
            transform: translateY(-3px);
        }

        /* Scroll Reveal */
        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: all 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
        .reveal-left {
            opacity: 0;
            transform: translateX(-40px);
            transition: all 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        .reveal-left.active {
            opacity: 1;
            transform: translateX(0);
        }
        .reveal-right {
            opacity: 0;
            transform: translateX(40px);
            transition: all 0.7s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        .reveal-right.active {
            opacity: 1;
            transform: translateX(0);
        }
        .reveal-scale {
            opacity: 0;
            transform: scale(0.95);
            transition: all 0.6s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        }
        .reveal-scale.active {
            opacity: 1;
            transform: scale(1);
        }
        .delay-1 { transition-delay: 0.1s; }
        .delay-2 { transition-delay: 0.2s; }
        .delay-3 { transition-delay: 0.3s; }
        .delay-4 { transition-delay: 0.4s; }
        .delay-5 { transition-delay: 0.5s; }

        /* Animations */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        .float-animation {
            animation: float 4s ease-in-out infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(30, 98, 255, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(30, 98, 255, 0); }
            100% { box-shadow: 0 0 0 0 rgba(30, 98, 255, 0); }
        }
        .pulse-animation {
            animation: pulse 2s infinite;
        }

        /* Scroll Progress */
        .scroll-progress {
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 3px;
            background: linear-gradient(90deg, var(--blue), #8b5cf6);
            z-index: 10000;
            transition: width 0.1s ease-out;
        }

        /* Back to Top */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 45px;
            height: 45px;
            background: var(--blue);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 999;
            border: none;
            font-size: 20px;
        }
        .back-to-top.show {
            opacity: 1;
            visibility: visible;
        }
        .back-to-top:hover {
            background: var(--blue-dark);
            transform: translateY(-3px) scale(1.05);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-center {
                display: none;
            }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            .features-grid,
            .how-steps,
            .pricing-grid {
                grid-template-columns: 1fr;
            }
            .footer-top {
                grid-template-columns: 1fr 1fr;
            }
            .fake-dash {
                height: auto;
                flex-direction: column;
            }
            .fd-right,
            .fd-list {
                display: none;
            }
            .fd-sidebar {
                flex-direction: row;
                width: 100%;
                height: 56px;
                padding: 0 16px;
            }
            .fd-sp {
                display: none;
            }
            .hero {
                padding: 50px 20px;
            }
            .back-to-top {
                bottom: 20px;
                right: 20px;
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>

<div class="banner">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" style="display: inline-block; vertical-align: middle;">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="currentColor"/>
    </svg>
    Baru: Integrasi AI Chatbot dengan Gemini & Claude sudah tersedia! 
    <a href="login.php?signup=1">Coba gratis sekarang →</a>
</div>

<nav>
    <a href="/" class="logo">
        <img src="/assets/images/logo-dark.png" alt="JellChat Pro Logo" class="logo-img">
    </a>
    <ul class="nav-center">
        <li><a href="#fitur">Fitur</a></li>
        <li><a href="#cara-kerja">Cara Kerja</a></li>
        <li><a href="#harga">Harga</a></li>
        <li><a href="#">Dokumentasi</a></li>
    </ul>
    <div class="nav-right">
        <a href="login.php" class="btn-ghost">Masuk</a>
        <a href="signup" class="btn-primary"><i class="fa-solid fa-rocket"></i> Daftar Gratis</a>
    </div>
</nav>

<div class="scroll-progress" id="scrollProgress"></div>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-badge float-animation">
        <i class="fa-solid fa-bolt"></i> Platform Live Chat #1 untuk Bisnis Indonesia
    </div>
    <h1>The live chat <span>software</span><br>that gets the job done</h1>
    <p>Pasang widget chat di website Anda dalam 5 menit. Didukung AI Claude, Gemini, dan ChatGPT — balas pelanggan 24/7 secara otomatis.</p>
    <div class="hero-actions">
        <a href="login.php?signup=1" class="btn-primary btn-lg"><i class="fa-solid fa-rocket"></i> Coba 14 Hari Gratis</a>
        <a href="#cara-kerja" class="btn-outline-lg"><i class="fa-solid fa-play-circle"></i> Lihat Demo</a>
    </div>
    <div class="hero-trust">
        <span><i class="fa-solid fa-check"></i> Tanpa kartu kredit</span>
        <span><i class="fa-solid fa-check"></i> Setup 5 menit</span>
        <span><i class="fa-solid fa-check"></i> Batalkan kapan saja</span>
        <span><i class="fa-solid fa-check"></i> Data di server Indonesia</span>
    </div>
</section>

<!-- DASHBOARD PREVIEW -->
<div class="preview-wrap">
  <div class="preview-glow"></div>
  <div class="fake-dash">
    <div class="fd-sidebar">
      <div class="fd-ico act"><i class="fa-solid fa-comments"></i></div>
      <div class="fd-ico"><i class="fa-solid fa-robot"></i></div>
      <div class="fd-ico"><i class="fa-solid fa-users"></i></div>
      <div class="fd-ico"><i class="fa-solid fa-chart-line"></i></div>
      <div class="fd-ico"><i class="fa-solid fa-code"></i></div>
      <div class="fd-sp"></div>
      <div class="fd-ico"><i class="fa-solid fa-gear"></i></div>
      <div class="fd-ico"><i class="fa-solid fa-bell"></i></div>
    </div>
    <div class="fd-list">
      <div class="fd-list-header">
        Pesanan <span class="fd-filter">Semua ▼</span>
      </div>
      <div class="fd-section">Perlu Balasan (3)</div>
      <div class="fd-chat-item act">
        <div class="fd-ci-row">
          <div class="fd-avatar">R<span class="online"></span></div>
          <div class="fd-ci-info">
            <div class="fd-ci-name">Rina Permata</div>
            <div class="fd-ci-msg">Barang ready stock kak?</div>
          </div>
          <div class="fd-ci-meta">12:32</div>
        </div>
        <div class="fd-unread">3</div>
      </div>
      <div class="fd-chat-item">
        <div class="fd-ci-row">
          <div class="fd-avatar" style="background:linear-gradient(135deg,#f59e0b,#ef4444)">D</div>
          <div class="fd-ci-info">
            <div class="fd-ci-name">Dedi Kurnia</div>
            <div class="fd-ci-msg">Kapan pesanan dikirim?</div>
          </div>
          <div class="fd-ci-meta">11:18</div>
        </div>
      </div>
      <div class="fd-chat-item">
        <div class="fd-ci-row">
          <div class="fd-avatar" style="background:linear-gradient(135deg,#10b981,#059669)">L</div>
          <div class="fd-ci-info">
            <div class="fd-ci-name">Linda Sari</div>
            <div class="fd-ci-msg">Bisa tukar ukuran gak?</div>
          </div>
          <div class="fd-ci-meta">10:05</div>
        </div>
      </div>
    </div>
    <div class="fd-main">
      <div class="fd-main-header">
        <div class="fd-main-title">Rina Permata — Info Produk</div>
        <div class="fd-main-actions">
          <button class="fd-icon-btn"><i class="fa-solid fa-robot" style="color:var(--blue)"></i></button>
          <button class="fd-icon-btn"><i class="fa-solid fa-ellipsis"></i></button>
          <button class="fd-icon-btn"><i class="fa-solid fa-xmark"></i></button>
        </div>
      </div>
      <div class="fd-msgs">
        <div class="fd-msg fd-msg-a">Halo Kak Rina! 👋 Ada yang bisa kami bantu mengenai produk kami?</div>
        <div class="fd-msg fd-msg-v">Halo, saya mau tanya untuk model ini apakah masih ada stok warna navy size L?</div>
        <div class="fd-msg fd-msg-a">
          <svg class="u-block b--u-mr-1" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none">
            <circle cx="12" cy="12" r="12" fill="#7EDD92"></circle>
            <path stroke="#fff" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4" d="m7.7 12.8 3.3 2.6L17 8.6"></path>
          </svg>
          Stok <b>Warna Navy (L)</b> tersedia kak! Silakan langsung dipesan sebelum kehabisan ya.
        </div>
        <div class="fd-msg fd-msg-v">Oke kak, kalau pesan sekarang apakah bisa kirim hari ini juga?</div>
        <div class="fd-msg fd-msg-a">Tentu kak! Batas order untuk pengiriman hari ini adalah pukul 16:00 WIB. Kami tunggu pesanannya 🙏</div>
      </div>
      <div class="fd-input">
        <div class="fd-input-box">Tulis balasan...</div>
        <div class="fd-send"><i class="fa-solid fa-paper-plane"></i></div>
      </div>
    </div>
    <div class="fd-right">
      <div class="fd-right-avatar">R</div>
      <div class="fd-right-name">Rina Permata</div>
      <div class="fd-right-email">rina.p@email.com</div>
      <div class="fd-map"><i class="fa-solid fa-location-dot"></i></div>
      <div class="fd-info-row"><span class="fd-info-label">Status</span><span class="fd-info-val">Calon Pembeli</span></div>
      <div class="fd-info-row"><span class="fd-info-label">Produk</span><span class="fd-info-val">Kemeja Flanel</span></div>
      <div class="fd-info-row"><span class="fd-info-label">Lokasi</span><span class="fd-info-val">Jakarta Pusat</span></div>
      <div class="fd-info-row"><span class="fd-info-label">Platform</span><span class="fd-info-val" style="color:var(--blue)">Mobile App</span></div>
    </div>
  </div>
</div>
<!-- Stats Section -->
<section class="stats">
    <div class="stats-grid">
        <div><div class="stat-num">50K+</div><div class="stat-label">Chat Diproses/Bulan</div></div>
        <div><div class="stat-num">98%</div><div class="stat-label">Response Rate</div></div>
        <div><div class="stat-num">2s</div><div class="stat-label">Waktu Respons AI</div></div>
        <div><div class="stat-num">3</div><div class="stat-label">Provider AI Tersedia</div></div>
    </div>
</section>

<!-- Features Section -->
<section class="features" id="fitur">
    <div><div class="section-eyebrow">Fitur Lengkap</div></div>
    <div><h2 class="section-title">Semua yang Anda butuhkan, sudah ada di sini</h2></div>
    <div><p class="section-sub">Dari live chat manual hingga AI bot canggih — satu platform untuk semua kebutuhan customer support bisnis Anda.</p></div>
    <div class="features-grid">
        <div class="feat-card">
            <div class="feat-icon" style="background:#eff6ff"><i class="fa-solid fa-robot" style="color:var(--blue)"></i></div>
            <h3 class="feat-title">AI Chatbot Multi-Provider</h3>
            <p class="feat-desc">Dukung Claude (Anthropic), Gemini (Google), dan OpenAI. Pilih yang terbaik sesuai budget Anda.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon" style="background:#fef3c7"><i class="fa-solid fa-bolt" style="color:#f59e0b"></i></div>
            <h3 class="feat-title">Mode Hybrid Bot+AI</h3>
            <p class="feat-desc">Keyword modules menjawab pertanyaan umum, AI menangani pertanyaan kompleks — efisien dan cerdas.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon" style="background:#d1fae5"><i class="fa-solid fa-puzzle-piece" style="color:#10b981"></i></div>
            <h3 class="feat-title">Module Keyword Fleksibel</h3>
            <p class="feat-desc">Buat aturan auto-reply berdasarkan keyword, exact match, starts with, atau regex — tanpa coding.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon" style="background:#fce7f3"><i class="fa-solid fa-location-dot" style="color:#ec4899"></i></div>
            <h3 class="feat-title">Pelacak Visitor Lengkap</h3>
            <p class="feat-desc">IP, lokasi, user agent, riwayat chat — semua informasi visitor tersedia di satu panel.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon" style="background:#ede9fe"><i class="fa-solid fa-code" style="color:#8b5cf6"></i></div>
            <h3 class="feat-title">Widget Dapat Dikustomisasi</h3>
            <p class="feat-desc">Warna, posisi, pesan sambutan, form pre-chat — sesuaikan dengan brand Anda dalam hitungan detik.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon" style="background:#fee2e2"><i class="fa-solid fa-chart-line" style="color:#ef4444"></i></div>
            <h3 class="feat-title">Analitik Real-Time</h3>
            <p class="feat-desc">Pantau jumlah chat, response rate, module paling aktif, dan performa agen — semua dalam dashboard.</p>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="how" id="cara-kerja">
    <div class="how-inner">
        <div><div class="section-eyebrow">Cara Kerja</div></div>
        <div><h2 class="section-title">Mulai dalam 3 langkah mudah</h2></div>
        <div><p class="section-sub">Tidak perlu keahlian teknis. Widget Anda siap dalam 5 menit.</p></div>
        <div class="how-steps">
            <div class="how-step">
                <div class="how-num">1</div>
                <h3 class="how-title">Daftar & Buat Akun</h3>
                <p class="how-desc">Buat akun gratis, konfigurasi profil agent dan tim Anda. Tidak perlu kartu kredit.</p>
            </div>
            <div class="how-step">
                <div class="how-num">2</div>
                <h3 class="how-title">Pasang Widget</h3>
                <p class="how-desc">Salin 1 baris kode JavaScript dan tempelkan ke website Anda. Selesai! Widget langsung aktif.</p>
            </div>
            <div class="how-step">
                <div class="how-num">3</div>
                <h3 class="how-title">Aktifkan AI & Module</h3>
                <p class="how-desc">Tambahkan API key AI favorit Anda, buat keyword modules — chatbot siap menjawab 24/7.</p>
            </div>
        </div>
    </div>
</section>

<!-- Pricing Section -->
<section class="pricing" id="harga">
    <div class="pricing-inner">
        <div><div class="section-eyebrow">Harga Transparan</div></div>
        <div><h2 class="section-title">Paket yang tumbuh bersama bisnis Anda</h2></div>
        <div><p class="section-sub" style="margin:0 auto;">Semua paket sudah termasuk 14 hari uji coba gratis.</p></div>
        <div class="pricing-grid">
            <div class="p-card">
                <div class="p-tier">Starter</div>
                <div class="p-price">Rp150.000<span>/bln</span></div>
                <p class="p-desc">Cocok untuk bisnis baru dan toko online kecil.</p>
                <ul class="p-features">
                    <li><i class="fa-solid fa-check"></i> 1 Tim, 1 Agent</li>
                    <li><i class="fa-solid fa-check"></i> 1.000 chat/bulan</li>
                    <li><i class="fa-solid fa-check"></i> 10 Keyword Modules</li>
                    <li><i class="fa-solid fa-check"></i> Widget kustomisasi</li>
                    <li><i class="fa-solid fa-check"></i> Analitik dasar</li>
                </ul>
                <button class="p-btn p-btn-outline" onclick="location='login.php?signup=1'">Coba Gratis</button>
            </div>
            <div class="p-card featured">
                <div class="p-badge">⭐ PALING POPULER</div>
                <div class="p-tier">Team</div>
                <div class="p-price">Rp450.000<span>/bln</span></div>
                <p class="p-desc">Ideal untuk tim customer service yang aktif.</p>
                <ul class="p-features">
                    <li><i class="fa-solid fa-check"></i> 3 Tim, 5 Agent</li>
                    <li><i class="fa-solid fa-check"></i> 10.000 chat/bulan</li>
                    <li><i class="fa-solid fa-check"></i> 50 Keyword Modules</li>
                    <li><i class="fa-solid fa-check"></i> <strong>AI Bot (Claude/Gemini/OpenAI)</strong></li>
                    <li><i class="fa-solid fa-check"></i> Mode Hybrid Bot+AI</li>
                    <li><i class="fa-solid fa-check"></i> Analitik lanjutan</li>
                </ul>
                <button class="p-btn p-btn-fill" onclick="location='login.php?signup=1'">Coba Gratis 14 Hari</button>
            </div>
            <div class="p-card">
                <div class="p-tier">Business</div>
                <div class="p-price">Rp1.200.000<span>/bln</span></div>
                <p class="p-desc">Solusi skala enterprise dengan semua fitur premium.</p>
                <ul class="p-features">
                    <li><i class="fa-solid fa-check"></i> 10 Tim, 20 Agent</li>
                    <li><i class="fa-solid fa-check"></i> 50.000 chat/bulan</li>
                    <li><i class="fa-solid fa-check"></i> 200 Keyword Modules</li>
                    <li><i class="fa-solid fa-check"></i> Semua fitur AI</li>
                    <li><i class="fa-solid fa-check"></i> Custom branding</li>
                    <li><i class="fa-solid fa-check"></i> API access & priority support</li>
                </ul>
                <button class="p-btn p-btn-outline" onclick="location='login.php?signup=1'">Coba Gratis</button>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <h2>Siap meningkatkan CS Anda?</h2>
    <p>Mulai gratis hari ini. Tidak perlu kartu kredit. Setup dalam 5 menit.</p>
    <div class="cta-form">
        <input class="cta-email" type="email" placeholder="Email bisnis Anda..." id="ctaEmail">
        <button class="cta-btn pulse-animation" onclick="if(document.getElementById('ctaEmail').value) location='login.php?signup=1&email='+encodeURIComponent(document.getElementById('ctaEmail').value)">
            <i class="fa-solid fa-rocket"></i> Daftar Gratis Sekarang
        </button>
    </div>
</section>

<!-- Footer -->
<footer>
    <div class="footer-inner">
        <div class="footer-top">
            <div class="footer-brand">
                <div class="logo" style="color:#fff;text-decoration:none;display:flex;align-items:center;gap:10px;">
                    <img src="/assets/images/logo-white.png" alt="JellChat Pro Logo" class="logo-img">
                </div>
                <p>Platform live chat dan AI chatbot terpercaya untuk bisnis Indonesia. Hubungkan pelanggan Anda dengan agen dan AI 24/7.</p>
            </div>
            <div class="footer-col">
                <h4>Produk</h4>
                <ul>
                    <li><a href="#">Live Chat</a></li>
                    <li><a href="#">AI Chatbot</a></li>
                    <li><a href="#">Keyword Modules</a></li>
                    <li><a href="#">Analytics</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Perusahaan</h4>
                <ul>
                    <li><a href="#">Tentang Kami</a></li>
                    <li><a href="#">Blog</a></li>
                    <li><a href="#">Karir</a></li>
                    <li><a href="#">Kontak</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Dukungan</h4>
                <ul>
                    <li><a href="#">Dokumentasi</a></li>
                    <li><a href="#">Status</a></li>
                    <li><a href="#">Kebijakan Privasi</a></li>
                    <li><a href="#">Syarat & Ketentuan</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bot">
            <span>© 2025 JellChat Pro. Hak cipta dilindungi.</span>
            <div class="footer-socials">
                <a href="#"><i class="fa-brands fa-instagram"></i></a>
                <a href="#"><i class="fa-brands fa-whatsapp"></i></a>
                <a href="#"><i class="fa-brands fa-telegram"></i></a>
                <a href="#"><i class="fa-brands fa-github"></i></a>
            </div>
        </div>
    </div>
</footer>

<button class="back-to-top" id="backToTop">
    <i class="fa-solid fa-arrow-up"></i>
</button>
<!-- LiveChat Pro Widget -->
<script 
    src="/widget/widget.js?v=<?= time() ?>" 
    license="19679492" 
    async>
</script>
<script>
(function() {
    'use strict';

    function isInViewport(element) {
        const rect = element.getBoundingClientRect();
        const windowHeight = window.innerHeight || document.documentElement.clientHeight;
        return rect.top <= windowHeight - 100 && rect.bottom >= 0;
    }

    function addRevealClasses() {
        document.querySelector('.hero')?.classList.add('reveal');
        document.querySelectorAll('.stats-grid > div').forEach((el, i) => {
            el.classList.add('reveal-scale', `delay-${(i % 5) + 1}`);
        });
        document.querySelectorAll('.feat-card').forEach((el, i) => {
            el.classList.add('reveal', `delay-${(i % 5) + 1}`);
        });
        document.querySelectorAll('.how-step').forEach((el, i) => {
            el.classList.add('reveal', `delay-${(i % 5) + 1}`);
        });
        document.querySelectorAll('.p-card').forEach((el, i) => {
            el.classList.add('reveal-scale', `delay-${(i % 5) + 1}`);
        });
        document.querySelector('.cta-section')?.classList.add('reveal');
        document.querySelectorAll('.footer-top > div').forEach((el, i) => {
            el.classList.add('reveal-right', `delay-${(i % 5) + 1}`);
        });
        document.querySelector('.fake-dash')?.classList.add('reveal-scale', 'delay-2');
    }

    let ticking = false;
    function revealOnScroll() {
        if (!ticking) {
            requestAnimationFrame(() => {
                document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale').forEach(el => {
                    if (isInViewport(el) && !el.classList.contains('active')) {
                        el.classList.add('active');
                    }
                });
                ticking = false;
            });
            ticking = true;
        }
    }

    function updateScrollProgress() {
        const winScroll = document.documentElement.scrollTop;
        const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const scrolled = (winScroll / height) * 100;
        const progressBar = document.getElementById('scrollProgress');
        if (progressBar) progressBar.style.width = scrolled + '%';
    }

    function handleNavbarScroll() {
        const nav = document.querySelector('nav');
        if (nav) {
            if (window.scrollY > 50) nav.classList.add('scrolled');
            else nav.classList.remove('scrolled');
        }
    }

    function handleBackToTop() {
        const backBtn = document.getElementById('backToTop');
        if (backBtn) {
            if (window.scrollY > 300) backBtn.classList.add('show');
            else backBtn.classList.remove('show');
        }
    }

    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href && href !== '#' && href !== '#0') {
                    const target = document.querySelector(href);
                    if (target) {
                        e.preventDefault();
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        });
    }

    function init() {
        addRevealClasses();
        revealOnScroll();
        initSmoothScroll();
        
        window.addEventListener('scroll', () => {
            revealOnScroll();
            updateScrollProgress();
            handleNavbarScroll();
            handleBackToTop();
        });
        
        document.getElementById('backToTop')?.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        updateScrollProgress();
        handleNavbarScroll();
        handleBackToTop();
        window.addEventListener('resize', () => revealOnScroll());
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

</body>
</html>