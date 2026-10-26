<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$pass = (int) config_item('passing_grade'); $sup = (int) config_item('support_grade_below');
$labels = ['Q1', 'Q2', 'Q3', 'Q4'];
$avg = array_map(function ($t) { return $t['average']; }, $trend);
$foc = array_map(function ($t) { return $t['focus']; }, $trend);
$fmt = function ($v) { return $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.'); };
?>
<?php ui_heading('My grades', 'School year ' . $sy['label'] . ' · ' . $me['section_label'],
    '<button class="btn" onclick="window.print()">' . icon('download', 14) . ' Print</button>'); ?>

<div class="dashboard-grid">
  <?php ui_panel('Report card', 'Final grade is the average of the quarters graded so far'); ?>
    <?php if (!$card): ?><?= ui_empty('No grades have been encoded yet.', 'book-open') ?><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Subject</th><th class="right">Q1</th><th class="right">Q2</th><th class="right">Q3</th><th class="right">Q4</th><th class="right">Final</th><th>Remarks</th></tr></thead>
      <tbody>
      <?php foreach ($card as $r): ?>
        <tr><td><strong><?= e($r['subject']) ?></strong></td>
          <?php foreach (['q1','q2','q3','q4'] as $q): ?><td class="right num"><?= e($fmt($r[$q])) ?></td><?php endforeach; ?>
          <td class="right num"><strong><?= e($fmt($r['final_grade'])) ?></strong></td>
          <td><?= $r['remarks'] ? badge($r['remarks'], $r['final_grade'] !== null && $r['final_grade'] < $pass ? 'danger' : ($r['final_grade'] < $sup ? 'warning' : '')) : '' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td><strong>General average</strong></td><td colspan="4"></td><td class="right num"><strong><?= $me['average'] === null ? '—' : e($me['average']) ?></strong></td><td></td></tr></tfoot>
    </table></div>
    <?php endif; ?>
  <?php ui_panel_end(); ?>

  <?php ui_panel('Quarterly trend', 'All-subject average and Mathematics'); ?>
    <div class="panel-body chart">
      <?= chart_lines($labels, [
          ['name' => 'Average', 'color' => 'var(--chart-green)', 'values' => $avg],
          ['name' => 'Mathematics', 'color' => 'var(--yellow)', 'values' => $foc],
      ], ['label' => 'Quarterly grade trend', 'min' => 70, 'max' => 100]) ?>
      <?= chart_legend([['name' => 'Average', 'color' => 'var(--chart-green)'], ['name' => 'Mathematics', 'color' => 'var(--yellow)']]) ?>
      <?php if ($weak): ?>
        <div class="support-strip"><?= icon('triangle-alert', 18) ?><div><strong>Support suggested</strong>
          <?= e(implode(', ', array_column($weak, 'subject'))) ?> — talk to your teacher or adviser.</div></div>
      <?php endif; ?>
    </div>
  <?php ui_panel_end(); ?>
</div>
