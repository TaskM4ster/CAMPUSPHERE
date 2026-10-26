<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$years = array_keys($history); sort($years);
$labels = []; $vals = [];
foreach ($years as $y) { $labels[] = $y . '–' . substr($y + 1, 2); $vals[] = array_sum($history[$y]); }
$cur_start = (int) substr($sy['label'], 0, 4);
$labels[] = $sy['label']; $vals[] = $f['totals']['current'];
$labels[] = $f['year'] . '–' . substr($f['year'] + 1, 2); $vals[] = $f['totals']['projected'];
?>
<?php ui_heading('Re-enrollment forecast', 'Projection for ' . $f['year'] . '–' . substr($f['year'] + 1, 2) . ' from enrollment history (decision support)',
    '<a class="btn" href="' . e(site_url('forecast/export')) . '">' . icon('download', 14) . ' Export CSV</a>'); ?>

<div class="metric-grid">
  <?= ui_metric('Enrolled now', 'users', num($f['totals']['current']), 'School year ' . e($sy['label'])) ?>
  <?= ui_metric('Projected next year', 'trending-up', num($f['totals']['projected']), 'Range ' . num($f['totals']['low']) . '–' . num($f['totals']['high'])) ?>
  <?= ui_metric('Expected returning', 'user-check', num($f['returning']), $f['retention'] === null ? '' : '<em>' . e($f['retention']) . '%</em> retention') ?>
  <?= ui_metric('Sections needed', 'school', num($f['totals']['sections']), 'At ' . (int) $capacity . ' learners per section') ?>
</div>

<div class="metric-grid">
  <?= ui_metric('Teachers needed (est.)', 'graduation-cap', num($staff['needed']), $staff['classes'] . ' classes ÷ ' . (int) $staff['load'] . ' per teacher') ?>
  <?= ui_metric('Teachers now', 'users', num($staff['have'])) ?>
  <?= ui_metric($staff['gap'] > 0 ? 'Additional teachers' : 'Staffing surplus', 'user-plus', num(abs($staff['gap'])), $staff['gap'] > 0 ? 'Plan hiring or load sharing' : 'Current staff can cover the plan', $staff['gap'] > 0 ? 'yellow' : '') ?>
</div>

<div class="dashboard-grid">
  <?php ui_panel('Headcount trend', 'Actual and projected'); ?>
    <div class="panel-body chart"><?= chart_area($labels, $vals, ['projected_last' => true, 'label' => 'Enrollment headcount by school year']) ?>
      <div class="legend"><span><i class="legend-dot"></i>Actual</span><span><i class="legend-dot yellow"></i>Projected</span></div></div>
  <?php ui_panel_end(); ?>
  <?php ui_panel('How it is calculated'); ?>
    <div class="panel-body small">
      <ul class="reason-list" style="font-size:12px">
        <li>Grades 8–12: this year's previous grade × the average historical retention ratio.</li>
        <li>Grade 7 intake: linear trend of past Grade 7 headcounts.</li>
        <li>Sections = projected learners ÷ <?= (int) $capacity ?>, rounded up.</li>
        <li>Teachers = sections needed × <?= (int) $staff['subjects'] ?> subjects ÷ <?= (int) $staff['load'] ?> classes per teacher (config <code>teacher_class_load</code>).</li>
        <li><?= (int) $enroll['pending'] ?> pending enrollment record(s) are not counted until approved.</li>
      </ul>
      <div class="subtle-note"><?= icon('info', 13) ?> A planning aid — transfers, migration and policy changes can move the real numbers.</div>
    </div>
  <?php ui_panel_end(); ?>
</div>

<?php ui_panel('Grade-level projection'); ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Grade</th><th class="right">Now</th><th class="right">Projected</th><th class="right">Low – High</th><th class="right">Sections now</th><th class="right">Needed</th><th>Planning note</th></tr></thead>
    <tbody>
    <?php foreach ($f['grades'] as $g): ?>
      <tr><td><strong>Grade <?= (int) $g['grade'] ?></strong></td><td class="right num"><?= (int) $g['current'] ?></td><td class="right num"><strong><?= (int) $g['projected'] ?></strong></td>
        <td class="right num"><?= (int) $g['low'] ?> – <?= (int) $g['high'] ?></td><td class="right num"><?= (int) $g['sections_have'] ?></td><td class="right num"><?= (int) $g['sections_need'] ?></td>
        <td><?= badge($g['plan'], $g['plan'] === 'Review current capacity' ? 'neutral' : 'warning') ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php ui_panel_end(); ?>
