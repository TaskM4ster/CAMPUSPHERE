<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php ui_heading('My account', 'Your sign-in details'); ?>
<div class="section-grid">
  <section class="panel">
    <div class="profile-header"><div class="profile-avatar"><?= e($user['initials']) ?></div>
      <div><h2><?= e($user['full_name']) ?></h2><p><?= e($user['role']) ?></p></div></div>
    <dl class="kv">
      <dt>Username</dt><dd><?= e($user['username']) ?></dd>
      <dt>Email</dt><dd><?= e($user['email'] ?: '—') ?></dd>
      <?php if (!empty($user['student_no'])): ?><dt>Student no.</dt><dd><?= e($user['student_no']) ?></dd><?php endif; ?>
      <?php if ($profile && isset($profile['section_label'])): ?><dt>Section</dt><dd><?= e($profile['section_label']) ?></dd><?php endif; ?>
      <?php if ($profile && isset($profile['employee_no'])): ?><dt>Employee no.</dt><dd><?= e($profile['employee_no']) ?></dd><?php endif; ?>
    </dl>
  </section>
  <section class="panel">
    <div class="panel-header"><div><h2>Security</h2><p>Use a long, unique password.</p></div></div>
    <div class="panel-body"><a class="btn btn-primary" href="<?= e(site_url('account/password')) ?>"><?= icon('lock', 14) ?> Change password</a></div>
  </section>
</div>
