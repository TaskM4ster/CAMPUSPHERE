<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$unread = count(array_filter($feed, function ($n) { return !(int) $n['is_read']; }));
$actions = '';
if ($unread) { ob_start(); post_form('notifications/read-all', 'class="inline"'); echo '<button class="btn" type="submit">' . icon('check', 14) . ' Mark all read</button></form>'; $actions .= ob_get_clean(); }
if ($can_post) { $actions .= '<button class="btn btn-primary" type="button" data-open="announce">' . icon('megaphone', 14) . ' Announce</button>'; }
ui_heading('Notifications', $unread ? $unread . ' unread' : 'You are all caught up', $actions); ?>

<div class="chart-tabs" style="margin-bottom:18px;display:inline-flex;flex-wrap:wrap">
  <a class="btn btn-sm <?= $category === '' ? 'selected' : '' ?>" href="<?= e(site_url('notifications')) ?>">All</a>
  <?php foreach ($categories as $c): ?><a class="btn btn-sm <?= $category === $c ? 'selected' : '' ?>" href="<?= e(site_url('notifications?category=' . $c)) ?>"><?= e($c) ?></a><?php endforeach; ?>
</div>

<section class="panel">
  <?php if (!$feed): ?><?= ui_empty('No notifications in this category.', 'bell') ?><?php else: foreach ($feed as $n): $un = !(int) $n['is_read']; ?>
    <article class="notification-row <?= $un ? 'unread' : '' ?>">
      <div style="flex:1;min-width:0">
        <div class="row"><?= badge($n['category'], $n['category'] === 'Attendance' ? 'warning' : '') ?> <?php if ($is_admin || $can_post): ?><span class="muted small">To: <?= e($n['audience']) ?></span><?php endif; ?></div>
        <h3><?= e($n['title']) ?></h3><p><?= nl2br(e($n['body'])) ?></p>
        <small class="muted"><?= e(fmt_datetime($n['created_at'])) ?></small>
      </div>
      <?php if ($un): ?><?php post_form('notifications/' . $n['id'] . '/read', 'class="inline"'); ?><input type="hidden" name="category" value="<?= e($category) ?>"><button class="btn btn-sm" type="submit">Mark read</button></form><?php endif; ?>
    </article>
  <?php endforeach; endif; ?>
</section>

<?php if ($can_post): ?>
<dialog class="modal" id="announce" <?= old('title') !== '' ? 'data-autoopen' : '' ?>>
  <?php post_form('notifications/announce'); ?>
    <div class="modal-header"><h2>New announcement</h2><button class="icon-btn" type="button" data-close aria-label="Close"><?= icon('x', 18) ?></button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label" for="n-title">Title</label><input class="field" id="n-title" name="title" maxlength="160" required value="<?= e(old('title')) ?>"></div>
      <div class="form-group"><label class="form-label" for="n-body">Message</label><textarea class="field" id="n-body" name="body" maxlength="2000" required><?= e(old('body')) ?></textarea></div>
      <div class="form-grid">
        <div class="form-group"><label class="form-label" for="n-cat">Category</label><select class="field" id="n-cat" name="category"><?= ui_options(array_combine($categories, $categories), old('category', 'Announcement')) ?></select></div>
        <div class="form-group"><label class="form-label" for="n-aud">Audience</label>
          <select class="field" id="n-aud" name="audience">
            <?php if ($is_admin): ?><option value="all">Everyone</option><?php foreach (['Teacher', 'Student', 'Guidance', 'Administrator'] as $r): ?><option value="role:<?= e($r) ?>"<?= selected('role:' . $r, old('audience')) ?>>All <?= e(strtolower($r)) ?>s</option><?php endforeach; endif; ?>
            <?php foreach ($sections as $s): ?><option value="section:<?= (int) $s['id'] ?>"<?= selected('section:' . $s['id'], old('audience')) ?>><?= e($s['label']) ?></option><?php endforeach; ?>
          </select></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn" type="button" data-close>Cancel</button><button class="btn btn-primary" type="submit">Publish</button></div>
  </form>
</dialog>
<?php endif; ?>
