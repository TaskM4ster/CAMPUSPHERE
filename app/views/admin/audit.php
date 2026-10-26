<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php ui_heading('Audit log', 'Who did what, and when. Entries cannot be edited from the application.'); ?>
<?php ui_filters_open('audit'); ?>
  <label class="search-field"><?= icon('search', 15) ?><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search user, action or details" aria-label="Search audit log"></label>
  <select name="entity" aria-label="Entity" data-autosubmit><?= ui_options(array_combine($entities, $entities), $f['entity'], 'All entities') ?></select>
  <button class="btn" type="submit">Search</button>
</form>
<section class="panel">
  <?php if (!$rows): ?><?= ui_empty('No audit entries.', 'history') ?><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td class="nowrap num"><?= e(fmt_datetime($r['created_at'])) ?></td><td><?= e($r['username'] ?: 'system') ?></td>
        <td><?= badge($r['action'], strpos($r['action'], 'failed') !== false ? 'danger' : 'neutral') ?></td>
        <td><?= e($r['entity']) ?><?= $r['entity_id'] !== null ? ' <span class="muted">#' . e($r['entity_id']) . '</span>' : '' ?></td>
        <td class="wrap small"><?= e($r['details']) ?></td><td class="num small muted"><?= e($r['ip_address']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= pager($total, $page, $per) ?>
  <?php endif; ?>
</section>
