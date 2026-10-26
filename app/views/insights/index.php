<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$total = array_sum($levels);
$lowest = $subjects ? $subjects[0] : null;
?>
<?php ui_heading('AI insights', 'Transparent, rule-based decision support. It highlights where a person should look first and always shows why.'); ?>

<div class="ai-notice mb"><?= icon('sparkles', 20) ?><div><h3>How these insights work</h3>
  <p>Each learner is checked against fixed thresholds — general average (review below <?= (int) $rules['review']['average_below'] ?>, support below <?= (int) $rules['support']['average_below'] ?>), attendance (below <?= (int) $rules['review']['attendance_below'] ?>% / <?= (int) $rules['support']['attendance_below'] ?>%) and overdue work (<?= (int) $rules['review']['missing_at_least'] ?>+ / <?= (int) $rules['support']['missing_at_least'] ?>+). It also looks for subjects with falling quarter grades, subjects below 80 and 3+ consecutive school days without a gate time-in. No model is trained on personal data; these are indicators, not an official status, they do not replace educator judgment, and nothing here changes a record.</p>
  <small>Thresholds are configurable in app/config/campus.php.</small></div></div>

<div class="metric-grid">
  <?= ui_metric('Learners reviewed', 'users', num($total)) ?>
  <?= ui_metric('Good standing', 'circle-check', num($levels[0])) ?>
  <?= ui_metric('Needs review', 'triangle-alert', num($levels[1]), '', 'yellow') ?>
  <?= ui_metric('Recommended support', 'shield-check', num($levels[2]), '', 'danger') ?>
</div>

<div class="dashboard-grid">
  <?php ui_panel('Learners to look at first', $review_total . ' flagged', '<a class="text-link" href="' . e(site_url('guidance')) . '">Student standing</a>'); ?>
    <div class="panel-body">
      <?php if (!$top): ?><?= ui_empty('No learner currently needs review.', 'circle-check') ?>
      <?php else: foreach ($top as $t): $s = $t['s']; ?>
        <div class="activity-row"><span class="user-avatar"><?= e($s['initials']) ?></span>
          <div class="activity-info"><strong><a href="<?= e(site_url('students/' . $s['id'])) ?>"><?= e($s['full_name']) ?></a> <span class="muted">· <?= e($s['section_label']) ?></span></strong>
            <ul class="reason-list"><?php foreach ($s['standing']['reasons'] as $r): ?><li><?= e($r) ?></li><?php endforeach; ?>
              <?php if ($t['weak']): ?><li>Subjects to support: <?= e(implode(', ', array_column($t['weak'], 'subject'))) ?></li><?php endif; ?></ul></div>
          <?= badge($s['standing']['label'], standing_tone($s['standing']['label'])) ?></div>
      <?php endforeach; endif; ?>
    </div>
  <?php ui_panel_end(); ?>

  <div>
    <?php ui_panel('Subject averages', 'Lowest first'); ?>
      <div class="panel-body">
        <?php if (!$subjects): ?><?= ui_empty('No grades yet.', 'book-open') ?>
        <?php else: foreach ($subjects as $s): ?>
          <div class="performance-row"><span><?= e($s['subject']) ?><?= (int) $s['below_support'] ? ' <span class="muted small">(' . (int) $s['below_support'] . ' below ' . (int) config_item('support_grade_below') . ')</span>' : '' ?></span><strong class="num"><?= e($s['average']) ?></strong></div>
        <?php endforeach; endif; ?>
      </div>
    <?php ui_panel_end(); ?>
    <?php ui_panel('Attendance signals', fmt_date($date)); ?>
      <div class="panel-body">
        <div class="performance-row"><span>Attendance rate</span><strong class="num"><?= $summary['rate'] === null ? '—' : e($summary['rate']) . '%' ?></strong></div>
        <div class="performance-row"><span>Late arrivals</span><strong class="num"><?= (int) $summary['late'] ?></strong></div>
        <div class="performance-row"><span>Gate-in without class record</span><strong class="num"><?= (int) $disc_count ?></strong></div>
        <?php if ($lowest): ?><div class="subtle-note"><?= icon('info', 13) ?> Weakest subject overall: <?= e($lowest['subject']) ?> (<?= e($lowest['average']) ?>).</div><?php endif; ?>
      </div>
    <?php ui_panel_end(); ?>
  </div>
</div>
