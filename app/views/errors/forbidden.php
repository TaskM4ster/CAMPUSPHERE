<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Access denied · CAMPUSPHERE</title><link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>"></head>
<body><main class="page" style="max-width:560px;padding-top:12vh;text-align:center">
  <div class="metric-icon danger" style="margin:0 auto 18px"><?= icon('lock', 22) ?></div>
  <h1 style="font-size:24px;margin:0 0 8px">Access denied</h1>
  <p class="muted">Your account<?= !empty($user['role']) ? ' (' . e($user['role']) . ')' : '' ?> does not have permission to open this page. If you think this is a mistake, contact the school administrator.</p>
  <p><a class="btn btn-primary" href="<?= e($home) ?>">Back to my dashboard</a></p>
</main></body></html>
