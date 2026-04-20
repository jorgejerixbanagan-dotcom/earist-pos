<?php
// ============================================================
// public/menu.php  —  Public-facing menu (no login required)
// Shows all available products grouped by category.
// Browse-only — students order from their dashboard.
// ============================================================
require_once __DIR__ . '/../config/init.php';

$db = Database::getInstance();

// Use the existing database View (product_rating_summary) to avoid Cartesian explosions
// and explicitly SELECT c.parent_id to fix the undefined array key error.
$products = $db->query(
  "SELECT p.*, c.name AS cat_name, c.id AS cat_id, c.parent_id,
          COALESCE(prs.avg_rating, 0) AS avg_rating,
          COALESCE(prs.total_ratings, 0) AS rating_count,
          COALESCE(prs.total_sold, 0) AS total_sold
   FROM products p
   JOIN categories c ON p.category_id = c.id
   LEFT JOIN product_rating_summary prs ON prs.product_id = p.id
   WHERE p.is_available = 1
   ORDER BY c.sort_order, p.name"
)->fetchAll();

// Mark top-5 best sellers by total_sold
$soldCounts = array_column($products, 'total_sold');
rsort($soldCounts);
$bestSellerThreshold = $soldCounts[min(4, count($soldCounts) - 1)] ?? 0;
$categories = $db->query("SELECT * FROM categories WHERE name != 'Add-ons' ORDER BY sort_order")->fetchAll();

// Group products by category for the section layout
$grouped = [];
foreach ($products as $p) {
  $grouped[$p['cat_id']]['name']       = $p['cat_name'];
  $grouped[$p['cat_id']]['parent_id']  = $p['parent_id'];
  $grouped[$p['cat_id']]['products'][] = $p;
}

// Separate Main/Group Categories from Sub-Categories for the Pills
$catsByParent = [];
foreach ($categories as $cat) {
  if ($cat['parent_id']) $catsByParent[$cat['parent_id']][] = $cat;
}
$catGroups     = array_filter($categories, fn($c) => $c['parent_id'] === null && !empty($catsByParent[$c['id']]));
$catStandalone = array_filter($categories, fn($c) => $c['parent_id'] === null && empty($catsByParent[$c['id']]));

$imgBase = APP_URL . '/../uploads/products/';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Our Menu — EARIST Coffee Shop</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,300&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/../assets/css/variables.css">
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      --land-bg: #faf8f5;
      --land-surface: #f2ede8;
      --land-card: #ffffff;
      --land-border: rgba(107, 62, 38, 0.12);
      --land-text: #1a1008;
      --land-muted: rgba(26, 16, 8, 0.50);
      --land-dim: rgba(26, 16, 8, 0.28);
    }

    html {
      font-size: 16px;
      scroll-behavior: smooth;
    }

    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      background: var(--land-bg);
      color: var(--land-text);
      min-height: 100vh;
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
    }

    a {
      color: inherit;
      text-decoration: none;
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.035'/%3E%3C/svg%3E");
      background-size: 200px 200px;
      pointer-events: none;
      z-index: 1000;
      opacity: 0.6;
    }

    .nav {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 100;
      padding: 18px 48px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      transition: background 0.3s, padding 0.3s;
    }

    .nav.scrolled {
      background: rgba(250, 248, 245, 0.94);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      padding: 12px 48px;
      border-bottom: 1px solid rgba(107, 62, 38, 0.12);
    }

    .nav-logo {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .nav-logo-icon {
      width: 36px;
      height: 36px;
      background: var(--primary-color);
      border-radius: var(--radius-full);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 14px rgba(192, 57, 43, 0.45);
      flex-shrink: 0;
      overflow: hidden;
    }

    .nav-logo-icon img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .nav-logo-text {
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--land-text);
      letter-spacing: -0.01em;
      line-height: 1.2;
    }

    .nav-logo-sub {
      font-size: 0.62rem;
      color: var(--land-muted);
    }

    .nav-actions {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .nav-link {
      font-size: 0.80rem;
      font-weight: 500;
      color: var(--land-muted);
      padding: 6px 12px;
      border-radius: 6px;
      transition: color 0.15s, background 0.15s;
      display: inline-flex;
      align-items: center;
      gap: 7px;
    }

    .nav-link:hover {
      color: var(--land-text);
      background: rgba(255, 255, 255, 0.06);
    }

    .nav-link.active {
      color: var(--land-text);
    }

    .btn-nav {
      height: 34px;
      padding: 0 16px;
      border-radius: 7px;
      font-size: 0.80rem;
      font-weight: 700;
      font-family: inherit;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 7px;
      border: none;
    }

    .btn-nav-ghost {
      background: transparent;
      color: var(--land-text);
      border: 1.5px solid rgba(255, 255, 255, 0.13);
      transition: all 0.15s;
    }

    .btn-nav-ghost:hover {
      background: rgba(107, 62, 38, 0.06);
      border-color: rgba(255, 255, 255, 0.25);
    }

    .btn-nav-primary {
      background: var(--primary-color);
      color: #fff;
      box-shadow: 0 4px 12px rgba(192, 57, 43, 0.38);
      transition: all 0.15s;
    }

    .btn-nav-primary:hover {
      background: var(--primary-dark);
      transform: translateY(-1px);
    }

    .page-header {
      padding: 120px 48px 56px;
      text-align: center;
      position: relative;
      overflow: hidden;
      background: linear-gradient(180deg, rgba(192, 57, 43, 0.04) 0%, transparent 100%);
    }

    .page-header::after {
      content: '';
      position: absolute;
      top: 60%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 600px;
      height: 400px;
      background: radial-gradient(ellipse, rgba(192, 57, 43, 0.05) 0%, transparent 70%);
      pointer-events: none;
    }

    .page-header-inner {
      position: relative;
      z-index: 2;
    }

    .page-eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      background: rgba(192, 57, 43, 0.07);
      border: 1px solid rgba(192, 57, 43, 0.18);
      border-radius: 99px;
      padding: 5px 14px;
      font-size: 0.68rem;
      font-weight: 700;
      color: var(--primary-color);
      letter-spacing: 0.08em;
      text-transform: uppercase;
      margin-bottom: 20px;
      animation: fadeUp 0.5s 0.05s both;
    }

    .page-eyebrow i {
      font-size: 9px;
    }

    .page-title {
      font-family: 'Instrument Serif', serif;
      font-size: clamp(2.2rem, 5vw, 4rem);
      font-weight: 400;
      line-height: 1.1;
      letter-spacing: -0.02em;
      color: var(--land-text);
      margin-bottom: 16px;
      animation: fadeUp 0.6s 0.15s both;
    }

    .page-title em {
      font-style: italic;
      color: var(--primary-color);
    }

    .page-sub {
      font-size: 0.95rem;
      color: var(--land-muted);
      line-height: 1.7;
      font-weight: 300;
      max-width: 440px;
      margin: 0 auto;
      animation: fadeUp 0.6s 0.25s both;
    }

    .filter-bar-wrap {
      position: sticky;
      top: 60px;
      z-index: 50;
      background: rgba(250, 248, 245, 0.94);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-bottom: 1px solid rgba(107, 62, 38, 0.12);
    }

    .filter-bar {
      padding: 14px 48px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }

    .filter-sub {
      padding: 10px 48px 14px;
      background: rgba(107, 62, 38, 0.02);
      border-top: 1px solid rgba(107, 62, 38, 0.08);
      display: none;
    }

    .filter-sub.open {
      display: flex;
    }

    .sub-pill {
      height: 28px;
      padding: 0 14px;
      font-size: 0.72rem;
      background: var(--land-surface);
      border-color: transparent;
    }

    .cat-pills {
      display: flex;
      align-items: center;
      gap: 6px;
      overflow-x: auto;
      scrollbar-width: none;
      flex-wrap: nowrap;
    }

    .cat-pills::-webkit-scrollbar {
      display: none;
    }

    .cat-pill {
      height: 32px;
      padding: 0 16px;
      border-radius: 99px;
      font-size: 0.78rem;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      border: 1.5px solid rgba(107, 62, 38, 0.14);
      background: transparent;
      color: var(--land-muted);
      white-space: nowrap;
      transition: all 0.15s;
      display: inline-flex;
      align-items: center;
      gap: 7px;
    }

    .cat-pill:hover {
      border-color: rgba(107, 62, 38, 0.30);
      color: var(--land-text);
    }

    .cat-pill.active {
      background: var(--primary-color);
      color: #fff;
      border-color: var(--primary-color);
      box-shadow: 0 3px 10px rgba(192, 57, 43, 0.35);
    }

    .cat-pill-count {
      background: rgba(255, 255, 255, 0.15);
      border-radius: 99px;
      padding: 1px 7px;
      font-size: 0.65rem;
      font-weight: 700;
    }

    .cat-pill.active .cat-pill-count {
      background: rgba(255, 255, 255, 0.25);
    }

    .search-wrap {
      position: relative;
      flex-shrink: 0;
    }

    .search-input {
      height: 34px;
      padding: 0 14px 0 36px;
      background: rgba(107, 62, 38, 0.04);
      border: 1.5px solid rgba(107, 62, 38, 0.14);
      border-radius: 8px;
      font-size: 0.80rem;
      font-family: inherit;
      color: var(--land-text);
      width: 220px;
      outline: none;
      transition: border-color 0.15s, background 0.15s;
    }

    .search-input::placeholder {
      color: var(--land-dim);
    }

    .search-input:focus {
      border-color: rgba(192, 57, 43, 0.50);
      background: rgba(107, 62, 38, 0.06);
    }

    .search-icon {
      position: absolute;
      left: 11px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 11px;
      color: var(--land-dim);
      pointer-events: none;
    }

    .content {
      padding: 48px;
      max-width: 1400px;
      margin: 0 auto;
    }

    .results-bar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 32px;
      font-size: 0.78rem;
      color: var(--land-muted);
    }

    .results-count strong {
      color: var(--land-text);
    }

    .empty-search {
      text-align: center;
      padding: 80px 20px;
      display: none;
    }

    .empty-search i {
      font-size: 40px;
      color: var(--land-dim);
      margin-bottom: 16px;
      display: block;
    }

    .empty-search h3 {
      font-size: 1.1rem;
      font-weight: 600;
      color: var(--land-text);
      margin-bottom: 8px;
    }

    .empty-search p {
      font-size: 0.84rem;
      color: var(--land-muted);
    }

    .cat-section {
      margin-bottom: 56px;
    }

    .cat-section-header {
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 24px;
    }

    .cat-section-line {
      flex: 1;
      height: 1px;
      background: rgba(107, 62, 38, 0.10);
    }

    .cat-section-name {
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      color: var(--land-muted);
      white-space: nowrap;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .cat-section-name i {
      color: var(--primary-color);
      font-size: 10px;
    }

    .product-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
      gap: 16px;
    }

    .menu-card {
      background: var(--land-card);
      border: 1px solid rgba(107, 62, 38, 0.10);
      border-radius: 14px;
      overflow: hidden;
      transition: border-color 0.2s, transform 0.2s, box-shadow 0.2s;
      position: relative;
      display: flex;
      flex-direction: column;
    }

    .menu-card:hover {
      border-color: rgba(192, 57, 43, 0.30);
      transform: translateY(-3px);
      box-shadow: 0 10px 30px rgba(107, 62, 38, 0.14);
    }

    .menu-card.unavailable {
      opacity: 0.45;
      filter: grayscale(0.5);
    }

    .menu-card-img {
      height: 140px;
      background: #f5ece4;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      border-bottom: 1px solid rgba(107, 62, 38, 0.12);
      position: relative;
    }

    .menu-card-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.35s ease;
    }

    .menu-card:hover .menu-card-img img {
      transform: scale(1.06);
    }

    .menu-card-img-icon {
      font-size: 36px;
      color: rgba(107, 62, 38, 0.18);
    }

    .unavail-ribbon {
      position: absolute;
      top: 10px;
      right: -1px;
      background: rgba(239, 68, 68, 0.85);
      color: #fff;
      font-size: 0.62rem;
      font-weight: 700;
      padding: 3px 10px 3px 8px;
      border-radius: 4px 0 0 4px;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .menu-card-cat {
      position: absolute;
      top: 10px;
      left: 10px;
      background: rgba(255, 255, 255, 0.82);
      backdrop-filter: blur(6px);
      color: rgba(26, 16, 8, 0.65);
      font-size: 0.60rem;
      font-weight: 700;
      padding: 3px 9px;
      border-radius: 99px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    .best-seller-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      background: linear-gradient(135deg, #f59e0b, #d97706);
      color: #fff;
      font-size: 0.60rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      padding: 2px 8px;
      border-radius: 99px;
      margin-bottom: 4px;
    }

    .card-rating-badge {
      font-size: 0.70rem;
      font-weight: 700;
      color: var(--accent-dark);
      background: rgba(240, 180, 41, 0.15);
      border-radius: 99px;
      padding: 2px 7px;
      white-space: nowrap;
      flex-shrink: 0;
    }

    .menu-card-body {
      padding: 16px;
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .menu-card-name {
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--land-text);
      line-height: 1.35;
      margin-bottom: 5px;
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
    }

    .menu-card-desc {
      font-size: 0.74rem;
      color: var(--land-muted);
      line-height: 1.55;
      font-weight: 300;
      margin-bottom: 12px;
      flex: 1;
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
    }

    .menu-card-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      margin-top: auto;
    }

    .menu-card-price {
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--accent-color);
      letter-spacing: -0.01em;
    }

    .cta-banner {
      margin: 0 48px 56px;
      background: linear-gradient(135deg, rgba(192, 57, 43, 0.06), rgba(107, 62, 38, 0.04));
      border: 1px solid rgba(192, 57, 43, 0.22);
      border-radius: 16px;
      padding: 32px 40px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      flex-wrap: wrap;
    }

    .cta-banner-text h3 {
      font-family: 'Instrument Serif', serif;
      font-size: 1.4rem;
      font-weight: 400;
      color: var(--land-text);
      margin-bottom: 6px;
    }

    .cta-banner-text h3 em {
      font-style: italic;
      color: var(--primary-color);
    }

    .cta-banner-text p {
      font-size: 0.84rem;
      color: var(--land-muted);
      font-weight: 300;
    }

    .cta-banner-actions {
      display: flex;
      gap: 10px;
      flex-shrink: 0;
      flex-wrap: wrap;
    }

    .btn-cta-primary {
      height: 40px;
      padding: 0 22px;
      border-radius: 9px;
      border: none;
      background: var(--primary-color);
      color: #fff;
      font-size: 0.84rem;
      font-weight: 700;
      font-family: inherit;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 14px rgba(192, 57, 43, 0.40);
      transition: all 0.15s;
      text-decoration: none;
    }

    .btn-cta-primary:hover {
      background: var(--primary-dark);
      transform: translateY(-1px);
    }

    .btn-cta-ghost {
      height: 40px;
      padding: 0 20px;
      border-radius: 9px;
      border: 1.5px solid rgba(107, 62, 38, 0.20);
      background: transparent;
      color: var(--land-text);
      font-size: 0.84rem;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.15s;
      text-decoration: none;
    }

    .btn-cta-ghost:hover {
      background: rgba(107, 62, 38, 0.07);
    }

    .footer {
      border-top: 1px solid rgba(107, 62, 38, 0.12);
      padding: 32px 48px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }

    .footer-brand {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .footer-brand-icon {
      width: 28px;
      height: 28px;
      background: var(--primary-color);
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      color: #fff;
    }

    .footer-brand-name {
      font-size: 0.80rem;
      font-weight: 600;
      color: var(--land-text);
    }

    .footer-copy {
      font-size: 0.72rem;
      color: var(--land-dim);
    }

    .footer-links {
      display: flex;
      gap: 18px;
    }

    .footer-links a {
      font-size: 0.76rem;
      color: var(--land-muted);
      transition: color 0.15s;
    }

    .footer-links a:hover {
      color: var(--land-text);
    }

    @keyframes fadeUp {
      from {
        opacity: 0;
        transform: translateY(18px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .reveal {
      opacity: 0;
      transform: translateY(20px);
      transition: opacity 0.5s ease, transform 0.5s ease;
    }

    .reveal.visible {
      opacity: 1;
      transform: translateY(0);
    }

    @media (max-width: 900px) {

      .nav,
      .nav.scrolled {
        padding: 14px 20px;
      }

      .filter-bar {
        padding: 12px 20px;
      }

      .content {
        padding: 32px 20px;
      }

      .cta-banner {
        margin: 0 20px 40px;
        padding: 24px;
      }

      .page-header {
        padding: 100px 24px 40px;
      }

      .footer {
        padding: 24px 20px;
        flex-direction: column;
        align-items: flex-start;
      }
    }

    @media (max-width: 540px) {
      .search-input {
        width: 160px;
      }

      .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
      }
    }
  </style>
</head>

<body>

  <nav class="nav" id="navbar">
    <a href="<?= APP_URL ?>/landing.php" class="nav-logo">
      <div class="nav-logo-icon"><img src="../assets/images/logo.png" alt="Logo"></div>
      <div>
        <div class="nav-logo-text"><?= APP_NAME ?></div>
      </div>
    </a>
    <div class="nav-actions">
      <a href="<?= APP_URL ?>/landing.php" class="nav-link">
        <i class="fa-solid fa-house"></i> Home
      </a>
      <a href="<?= APP_URL ?>/menu.php" class="nav-link active">
        <i class="fa-solid fa-list"></i> Menu
      </a>
      <?php if (isLoggedIn()): ?>
        <a href="<?= APP_URL ?>/student/dashboard.php" class="btn-nav btn-nav-primary">
          <i class="fa-solid fa-gauge"></i> Dashboard
        </a>
      <?php else: ?>
        <a href="<?= APP_URL ?>/register.php" class="btn-nav btn-nav-ghost">
          <i class="fa-solid fa-user-plus"></i> Register
        </a>
        <a href="<?= APP_URL ?>/login.php" class="btn-nav btn-nav-primary">
          <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In
        </a>
      <?php endif; ?>
    </div>
  </nav>

  <div class="page-header">
    <div class="page-header-inner">
      <div class="page-eyebrow"><i class="fa-solid fa-mug-hot"></i> What We Serve</div>
      <h1 class="page-title">Our <em>full menu</em></h1>
      <p class="page-sub">
        Hot drinks, cold brews, fresh snacks, and pastries — made fresh every day on campus.
      </p>
    </div>
  </div>

  <div class="filter-bar-wrap" id="filter-bar-wrap">
    <div class="filter-bar">
      <div class="cat-pills" id="tier-1">
        <button class="cat-pill active" data-type="all" data-id="all">
          All <span class="cat-pill-count" id="count-all"><?= count($products) ?></span>
        </button>
        <?php foreach ($catGroups as $group): ?>
          <button class="cat-pill" data-type="group" data-id="<?= $group['id'] ?>">
            <?= e($group['name']) ?> <i class="fa-solid fa-chevron-down" style="font-size:10px;opacity:0.6;margin-left:2px"></i>
          </button>
        <?php endforeach; ?>
        <?php foreach ($catStandalone as $cat):
          $catCount = count(array_filter($products, fn($p) => $p['cat_id'] == $cat['id']));
        ?>
          <button class="cat-pill" data-type="cat" data-id="<?= $cat['id'] ?>">
            <?= e($cat['name']) ?> <span class="cat-pill-count"><?= $catCount ?></span>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="search-wrap">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
        <input type="text" class="search-input" id="search-input" placeholder="Search menu…" autocomplete="off">
      </div>
    </div>

    <div class="filter-sub cat-pills" id="tier-2">
      <?php foreach ($catsByParent as $parentId => $children): ?>
        <?php foreach ($children as $subCat):
          $subCount = count(array_filter($products, fn($p) => $p['cat_id'] == $subCat['id']));
        ?>
          <button class="cat-pill sub-pill" data-parent="<?= $parentId ?>" data-id="<?= $subCat['id'] ?>" style="display:none;">
            <?= e($subCat['name']) ?> <span class="cat-pill-count"><?= $subCount ?></span>
          </button>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <main class="content">
    <div class="results-bar" id="results-bar">
      <span>Showing <strong id="results-count"><?= count($products) ?></strong> items</span>
    </div>

    <div class="empty-search" id="empty-search">
      <i class="fa-solid fa-magnifying-glass"></i>
      <h3>No items found</h3>
      <p>Try a different search term or browse a category.</p>
    </div>

    <div id="all-sections">
      <?php foreach ($grouped as $catId => $group): ?>
        <div class="cat-section reveal" data-section-cat="<?= $catId ?>" data-cat="<?= $catId ?>" data-parent="<?= $group['parent_id'] ?? '' ?>">
          <div class="cat-section-header">
            <div class="cat-section-line"></div>
            <div class="cat-section-name">
              <i class="fa-solid fa-circle-dot"></i>
              <?= e($group['name']) ?>
            </div>
            <div class="cat-section-line"></div>
          </div>

          <div class="product-grid">
            <?php foreach ($group['products'] as $p): ?>
              <div class="menu-card <?= !$p['is_available'] ? 'unavailable' : '' ?>"
                data-cat="<?= $catId ?>"
                data-parent="<?= $group['parent_id'] ?? '' ?>"
                data-name="<?= strtolower(e($p['name'])) ?>"
                data-desc="<?= strtolower(e($p['description'] ?? '')) ?>">

                <div class="menu-card-img">
                  <?php if (!empty($p['image_path']) && file_exists(UPLOAD_DIR . $p['image_path'])): ?>
                    <img src="<?= $imgBase . e($p['image_path']) ?>" alt="<?= e($p['name']) ?>">
                  <?php else: ?>
                    <span class="menu-card-img-icon"><i class="fa-solid fa-mug-hot"></i></span>
                  <?php endif; ?>

                  <span class="menu-card-cat"><?= e($group['name']) ?></span>

                  <?php if (!$p['is_available']): ?>
                    <span class="unavail-ribbon">Unavailable</span>
                  <?php endif; ?>
                </div>

                <div class="menu-card-body">
                  <?php if ($p['total_sold'] >= $bestSellerThreshold && $p['total_sold'] > 0): ?>
                    <div class="best-seller-badge"><i class="fa-solid fa-fire"></i> Best Seller</div>
                  <?php endif; ?>
                  <div class="menu-card-name"><?= e($p['name']) ?></div>
                  <?php if (!empty($p['description'])): ?>
                    <div class="menu-card-desc"><?= e($p['description']) ?></div>
                  <?php else: ?>
                    <div class="menu-card-desc" style="opacity:0.3;font-style:italic">No description</div>
                  <?php endif; ?>

                  <div class="menu-card-footer">
                    <span class="menu-card-price">₱<?= number_format($p['price'], 2) ?></span>
                    <?php if ($p['avg_rating'] > 0): ?>
                      <span class="card-rating-badge">★ <?= $p['avg_rating'] ?></span>
                    <?php endif; ?>
                  </div>
                </div>

              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <?php if (!isLoggedIn()): ?>
    <div class="cta-banner">
      <div class="cta-banner-text">
        <h3>Ready to skip the queue?<br><em>Order ahead.</em></h3>
        <p>Create a free student account and start pre-ordering in under a minute.</p>
      </div>
      <div class="cta-banner-actions">
        <a href="<?= APP_URL ?>/register.php" class="btn-cta-primary">
          <i class="fa-solid fa-user-plus"></i> Create Account
        </a>
        <a href="<?= APP_URL ?>/login.php" class="btn-cta-ghost">
          Already registered? Sign in
        </a>
      </div>
    </div>
  <?php endif; ?>

  <footer class="footer">
    <div class="footer-brand">
      <div class="footer-brand-icon"><i class="fa-solid fa-mug-hot"></i></div>
      <div>
        <div class="footer-brand-name">EARIST Coffee Shop</div>
        <div style="font-size:.66rem;color:var(--land-dim)">Cavite Campus</div>
      </div>
    </div>
    <div class="footer-copy">&copy; <?= date('Y') ?> EARIST Cavite Campus. All rights reserved.</div>
    <div class="footer-links">
      <a href="<?= APP_URL ?>/landing.php">Home</a>
      <a href="<?= APP_URL ?>/login.php">Sign In</a>
      <a href="<?= APP_URL ?>/register.php">Register</a>
    </div>
  </footer>

  <script>
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
      navbar.classList.toggle('scrolled', window.scrollY > 40);
    }, {
      passive: true
    });

    const revealEls = document.querySelectorAll('.reveal');
    const revealObs = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.classList.add('visible');
          revealObs.unobserve(e.target);
        }
      });
    }, {
      threshold: 0.08
    });
    revealEls.forEach(el => revealObs.observe(el));

    const allCards = Array.from(document.querySelectorAll('.menu-card'));
    const allSections = Array.from(document.querySelectorAll('.cat-section'));
    const subContainer = document.getElementById('tier-2');
    const subPills = document.querySelectorAll('.sub-pill');

    let activeType = 'all';
    let activeId = 'all';
    let searchQuery = '';

    document.getElementById('tier-1').addEventListener('click', e => {
      const pill = e.target.closest('.cat-pill');
      if (!pill) return;
      document.querySelectorAll('#tier-1 .cat-pill').forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      activeType = pill.dataset.type;
      activeId = pill.dataset.id;
      subPills.forEach(sp => sp.classList.remove('active'));
      if (activeType === 'group') {
        let hasChildren = false;
        subPills.forEach(sp => {
          if (sp.dataset.parent === activeId) {
            sp.style.display = 'inline-flex';
            hasChildren = true;
          } else sp.style.display = 'none';
        });
        if (hasChildren) subContainer.classList.add('open');
      } else {
        subContainer.classList.remove('open');
        subPills.forEach(sp => sp.style.display = 'none');
      }
      applyFilter();
    });

    subContainer.addEventListener('click', e => {
      const pill = e.target.closest('.sub-pill');
      if (!pill) return;
      subPills.forEach(p => p.classList.remove('active'));
      if (activeId === pill.dataset.id && activeType === 'subcat') {
        activeType = 'group';
        activeId = pill.dataset.parent;
      } else {
        pill.classList.add('active');
        activeType = 'subcat';
        activeId = pill.dataset.id;
      }
      applyFilter();
    });

    document.getElementById('search-input').addEventListener('input', e => {
      searchQuery = e.target.value.toLowerCase().trim();
      applyFilter();
    });

    function applyFilter() {
      let visibleCount = 0;
      allCards.forEach(card => {
        let matchCat = false;
        if (activeType === 'all') matchCat = true;
        else if (activeType === 'group') matchCat = card.dataset.parent === activeId;
        else if (activeType === 'cat' || activeType === 'subcat') matchCat = card.dataset.cat === activeId;
        const matchText = !searchQuery || card.dataset.name.includes(searchQuery) || card.dataset.desc.includes(searchQuery);
        const show = matchCat && matchText;
        card.style.display = show ? '' : 'none';
        if (show) visibleCount++;
      });
      allSections.forEach(section => {
        const hasVisible = Array.from(section.querySelectorAll('.menu-card')).some(c => c.style.display !== 'none');
        section.style.display = hasVisible ? '' : 'none';
      });
      document.getElementById('results-count').textContent = visibleCount;
      document.getElementById('empty-search').style.display = visibleCount === 0 ? 'block' : 'none';
    }
  </script>

</body>

</html>