<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/** @var string $content $title $active $school $system */
// highlight the nav item with the longest matching path prefix
$best = null; $best_len = -1;
foreach ($nav as $item) {
    $p = $item[1];
    if ($active === $p || strpos($active . '/', $p . '/') === 0) {
        if (strlen($p) > $best_len) { $best = $p; $best_len = strlen($p); }
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="color-scheme" content="light">
<title><?= e($title ? $title . ' · ' : '') . e($system) ?></title>
<link rel="icon" href="<?= e(base_url('assets/img/school-logo.jpg')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=1">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<div class="app-shell">
  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="brand">
      <img class="brand-mark" src="<?= e(base_url('assets/img/school-logo.jpg')) ?>" alt="">
      <div>
        <div class="brand-name"><?= e($system) ?></div>
        <div class="brand-tagline">for <strong><?= e(config_item('school_short')) ?></strong></div>
      </div>
    </div>
    <div class="school-label"><?= icon('school', 16) ?> <span><?= e($school) ?></span></div>
    <div class="nav-label">Menu</div>
    <nav class="sidebar-nav">
      <?php foreach ($nav as $item): $on = $item[1] === $best; ?>
        <a class="nav-item<?= $on ? ' active' : '' ?>" href="<?= e(site_url($item[1])) ?>"<?= $on ? ' aria-current="page"' : '' ?>>
          <?= icon($item[2], 18) ?><span><?= e($item[0]) ?></span>
        </a>
      <?php endforeach; ?>
      <a class="nav-item<?= strpos($active, '/notifications') === 0 ? ' active' : '' ?>" href="<?= e(site_url('notifications')) ?>">
        <?= icon('bell', 18) ?><span>Notifications</span><?php if ($unread > 0): ?><span class="nav-count"><?= (int) $unread ?></span><?php endif; ?>
      </a>
    </nav>
    <div class="sidebar-bottom">
      <?php if ($sy): ?>
        <div class="year-box">School year<strong><?= e($sy['label']) ?></strong></div>
      <?php endif; ?>
      <div class="side-footer">
        <a class="user-avatar" href="<?= e(site_url('account')) ?>" title="My account"><?= e($user['initials']) ?></a>
        <div>
          <strong style="font-size:12px;display:block"><?= e($user['full_name']) ?></strong>
          <small class="muted"><?= e($user['role']) ?></small>
        </div>
      </div>
    </div>
  </aside>

  <div class="workspace">
    <header class="topbar">
      <div class="row">
        <button type="button" class="icon-btn mobile-menu" id="menu-toggle" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false"><?= icon('menu', 20) ?></button>
        <div class="breadcrumb"><?= e($system) ?> <?= icon('chevron-right', 12) ?> <strong><?= e($title) ?></strong></div>
      </div>
      <div class="header-tools">
        <a class="icon-btn" href="<?= e(site_url('notifications')) ?>" aria-label="Notifications<?= $unread > 0 ? ', ' . (int) $unread . ' unread' : '' ?>">
          <?= icon('bell', 19) ?><?php if ($unread > 0): ?><span class="notification-dot"></span><?php endif; ?>
        </a>
        <div class="header-profile">
          <div class="user-avatar"><?= e($user['initials']) ?></div>
          <div><strong><a href="<?= e(site_url('account')) ?>"><?= e($user['full_name']) ?></a></strong><small><?= e($user['role']) ?></small></div>
          <?php post_form('logout', 'class="logout-form inline"'); ?>
            <button type="submit" aria-label="Sign out" title="Sign out"><?= icon('log-out', 17) ?></button>
          </form>
        </div>
      </div>
    </header>

    <main class="page" id="main">
      <?php if ($flash): ?>
        <div class="flash <?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>">
          <?= icon($flash['type'] === 'error' ? 'triangle-alert' : ($flash['type'] === 'success' ? 'circle-check' : 'info'), 16) ?>
          <div class="grow"><?= e($flash['msg']) ?></div>
        </div>
      <?php endif; ?>
      <?= $content ?>
      <footer class="footer">
        <span class="school-status"><?= e($school) ?> · <?= e(config_item('school_tagline')) ?></span>
        <span class="muted"><?= e($system) ?> · LavaLust MVC</span>
      </footer>
    </main>
  </div>
</div>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=1" defer></script>
</body>
</html>
