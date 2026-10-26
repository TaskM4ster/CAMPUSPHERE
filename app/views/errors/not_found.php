<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Not found · CAMPUSPHERE</title><link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>"></head>
<body><main class="page" style="max-width:560px;padding-top:12vh;text-align:center">
  <div class="metric-icon" style="margin:0 auto 18px"><?= icon('circle-help', 22) ?></div>
  <h1 style="font-size:24px;margin:0 0 8px">We couldn't find that <?= e($what ?? 'page') ?></h1>
  <p class="muted">It may have been moved, or you may not have access to it.</p>
  <p><a class="btn btn-primary" href="<?= e($home) ?>">Back to my dashboard</a></p>
</main></body></html>
