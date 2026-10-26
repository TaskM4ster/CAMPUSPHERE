<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$labels = [0 => 'Good Standing', 1 => 'Needs Review', 2 => 'Recommended Support'];
?>
<?php ui_heading('Student standing', 'Rule-based indicators for guidance and advisers — support, not sanctions'); ?>

<div class="metric-grid">
  <?= ui_metric('Good standing', 'circle-check', num($dist[0])) ?>
  <?= ui_metric('Needs review', 'triangle-alert', num($dist[1]), '', 'yellow') ?>
  <?= ui_metric('Recommended support', 'shield-check', num($dist[2]), '', 'danger') ?>
  <?= ui_metric('Open support plans', 'clipboard-check', num($open_plans), 'Interventions not yet done') ?>
</div>

<?php ui_filters_open('guidance'); ?>
  <label class="search-field"><?= icon('search', 15) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search learner" aria-label="Search learner"></label>
  <select name="level" aria-label="Standing" data-autosubmit><?= ui_options([0 => 'Good Standing', 1 => 'Needs Review', 2 => 'Recommended Support'], $level, 'All standings') ?></select>
  <button class="btn" type="submit">Search</button>
</form>

<section class="panel">
  <?php if (!$rows): ?><?= ui_empty('No learners match.', 'users') ?><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Learner</th><th>Section</th><th class="right">Average</th><th class="right">Attendance</th><th class="right">Missing</th><th>Standing</th><th>Why</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $s): ?>
      <tr><td><?= ui_student($s['full_name'], $s['student_no'], $s['initials']) ?></td><td><?= e($s['section_label']) ?></td>
        <td class="right num"><?= $s['average'] === null ? '—' : e($s['average']) ?></td>
        <td class="right num"><?= $s['attendance'] === null ? '—' : e($s['attendance']) . '%' ?></td>
        <td class="right num"><?= (int) $s['missing'] ?></td>
        <td><?= badge($s['standing']['label'], standing_tone($s['standing']['label'])) ?></td>
        <td class="wrap small muted"><?= e($s['standing']['reasons'][0]) ?></td>
        <td class="right"><a class="btn btn-sm" href="<?= e(site_url('guidance/' . $s['id'])) ?>">Open</a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= pager($total, $page, $per) ?>
  <?php endif; ?>
</section>
