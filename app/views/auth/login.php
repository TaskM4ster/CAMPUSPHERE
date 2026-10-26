<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sign in · <?= e($system) ?></title>
<link rel="icon" href="<?= e(base_url('assets/img/school-logo.jpg')) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>?v=1">
</head>
<body>
<div class="login-page">
  <section class="login-photo">
    <img src="<?= e(base_url('assets/img/campus.jpg')) ?>" alt="">
    <div class="login-photo-copy">
      <h1><?= e($school) ?></h1>
      <p><?= e($tagline) ?></p>
    </div>
  </section>
  <section class="login-form-area">
    <form class="login-form" method="post" action="<?= e(site_url('login')) ?>" autocomplete="on">
      <?php csrf_field(); ?>
      <div class="brand" style="padding:0;margin-bottom:26px">
        <img class="brand-mark" src="<?= e(base_url('assets/img/school-logo.jpg')) ?>" alt="">
        <div><div class="brand-name"><?= e($system) ?></div><div class="brand-tagline">School management platform</div></div>
      </div>
      <h2>Welcome back</h2>
      <p>Sign in with your school account to continue.</p>
      <?php if ($flash): ?><div class="<?= $flash['type'] === 'error' ? 'login-error' : 'flash ' . e($flash['type']) ?>" role="alert"><?= e($flash['msg']) ?></div><?php endif; ?>
      <div class="form-group">
        <label class="form-label" for="username">Username or email</label>
        <input class="field" id="username" name="username" type="text" value="<?= e(old('username')) ?>" required autofocus autocapitalize="none" autocomplete="username" maxlength="150">
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="password-field">
          <input class="field" id="password" name="password" type="password" required autocomplete="current-password" maxlength="200">
          <button type="button" id="pw-toggle" aria-label="Show password"><?= icon('eye', 16) ?></button>
        </div>
      </div>
      <button class="btn btn-primary" style="width:100%;height:42px" type="submit">Sign in <?= icon('arrow-right', 16) ?></button>
      <?php if ($demo): ?>
        <div class="demo-box">
          <strong>Demo accounts</strong> (password <code style="display:inline;padding:1px 5px"><?= e($demo_password) ?></code>)
          <div>
            <?php foreach ($demo as $d): ?>
              <button type="button" data-fill="<?= e($d[1]) ?>" data-pass="<?= e($demo_password) ?>"><?= e($d[0]) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </form>
  </section>
</div>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=1" defer></script>
</body>
</html>
