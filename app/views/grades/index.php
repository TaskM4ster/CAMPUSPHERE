<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$fmt = function ($v) { return $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.'); };
?>
<?php ui_heading('Academic records', 'School year ' . $sy['label'] . ' · passing grade ' . $pass . ' · support suggested below ' . $support); ?>

<?php if ($standings): ?>
<div class="metric-grid">
  <?= ui_metric('School average', 'book-open', $average === null ? '—' : $average) ?>
  <?= ui_metric('Good standing', 'circle-check', num($standings['Good Standing'])) ?>
  <?= ui_metric('Needs review', 'triangle-alert', num($standings['Needs Review']), '', 'yellow') ?>
  <?= ui_metric('Recommended support', 'shield-check', num($standings['Recommended Support']), '', 'danger') ?>
</div>
<?php endif; ?>

<?php if ($sheet): $sec = $sheet['section']; $sub = $sheet['subject']; ?>
<section class="panel mb">
  <?php if ($sheet['can_encode']) { post_form('grades/save'); } ?>
  <input type="hidden" name="section_id" value="<?= (int) $sec['id'] ?>"><input type="hidden" name="subject_id" value="<?= (int) $sub['id'] ?>">
  <div class="panel-header"><div><h2><?= e($sub['name']) ?> · <?= e(grade_section($sec['grade'], $sec['name'])) ?></h2>
      <p><?= $sheet['can_encode'] ? 'Enter quarter grades from 0 to 100. Final grade and remarks are computed automatically.' : 'Read-only view.' ?></p></div>
    <div class="panel-actions"><a class="btn btn-sm" href="<?= e(site_url('grades')) ?>">Close</a></div></div>
  <?php if (!$sheet['rows']): ?><?= ui_empty('No learners enrolled in this section.', 'users') ?><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Learner</th><th class="right">Q1</th><th class="right">Q2</th><th class="right">Q3</th><th class="right">Q4</th><th class="right">Final</th><th>Remarks</th></tr></thead>
    <tbody>
    <?php foreach ($sheet['rows'] as $r): ?>
      <tr><td><?= ui_student($r['full_name'], $r['student_no']) ?></td>
        <?php foreach (['q1', 'q2', 'q3', 'q4'] as $q): ?>
          <td class="right"><?php if ($sheet['can_encode']): ?>
            <input class="field score-input num" type="number" min="0" max="100" step="0.01" inputmode="decimal" name="g[<?= (int) $r['student_id'] ?>][<?= $q ?>]" value="<?= e($fmt($r[$q])) ?>" aria-label="<?= e(strtoupper($q) . ' for ' . $r['full_name']) ?>">
            <?php else: ?><span class="num"><?= $r[$q] === null ? '—' : e($fmt($r[$q])) ?></span><?php endif; ?></td>
        <?php endforeach; ?>
        <td class="right num"><strong><?= $r['final_grade'] === null ? '—' : e($fmt($r['final_grade'])) ?></strong></td>
        <td><?= $r['remarks'] ? badge($r['remarks'], $r['final_grade'] < $pass ? 'danger' : ($r['final_grade'] < $support ? 'warning' : '')) : '' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($sheet['can_encode']): ?><div class="panel-body row between"><span class="muted small">Leave a quarter empty if it has not been graded yet.</span><button class="btn btn-primary" type="submit"><?= icon('check', 14) ?> Save grades</button></div><?php endif; ?>
  <?php endif; ?>
  <?php if ($sheet['can_encode']) { echo '</form>'; } ?>
</section>
<?php endif; ?>

<?php ui_panel($user['role'] === 'Teacher' ? 'My classes' : 'All classes', 'Open a class to view' . ($user['role'] === 'Teacher' ? ' and encode' : '') . ' grades'); ?>
  <?php if (!$classes): ?><?= ui_empty('No classes found.', 'book-open') ?><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Section</th><th>Subject</th><th>Teacher</th><th class="right"></th></tr></thead>
    <tbody>
    <?php foreach ($classes as $c): ?>
      <tr><td><?= e(grade_section($c['grade'], $c['section_name'])) ?></td><td><strong><?= e($c['subject_name']) ?></strong></td>
        <td><?= e($c['teacher_name'] ?? $user['full_name']) ?></td>
        <td class="right"><a class="btn btn-sm" href="<?= e(site_url('grades?section=' . $c['section_id'] . '&subject=' . $c['subject_id'])) ?>">Open</a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
<?php ui_panel_end(); ?>
