<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$sum = $summary;
$donut = [];
$colors = ['Good Standing' => 'var(--chart-green)', 'Needs Review' => 'var(--yellow)', 'Recommended Support' => 'var(--destructive)', 'Pending records' => 'var(--border)'];
foreach ($distribution as $label => $n) { $donut[] = ['label' => $label, 'value' => $n, 'color' => $colors[$label]]; }
$graded = array_sum(array_diff_key($distribution, ['Pending records' => 1]));
?>
<div class="campus-banner">
  <img src="<?= e(base_url('assets/img/campus.jpg')) ?>" alt="">
  <div class="banner-copy">
    <div class="banner-eyebrow"><?= e(config_item('school_short')) ?> · Administrator</div>
    <h2>Good day, <?= e(explode(' ', $user['full_name'])[0]) ?>.</h2>
    <p><?= e(config_item('school_tagline')) ?></p>
    <span class="banner-pill"><?= icon('calendar-days', 14) ?> <?= e(fmt_date($date, 'l, F j, Y')) ?><?= $is_today ? '' : ' · latest gate data' ?></span>
  </div>
</div>

<div class="metric-grid">
  <?= ui_metric('Enrolled learners', 'users', num($counts['total']), '<em>' . (int) $counts['jhs'] . '</em> JHS · <em>' . (int) $counts['shs'] . '</em> SHS') ?>
  <?= ui_metric('Present today', 'user-check', num($sum['present_total']),
      ($sum['rate'] === null ? 'No scans yet' : '<em>' . e($sum['rate']) . '%</em> of ' . (int) $sum['enrolled'] . ' enrolled') . ($sum['late'] ? ' · ' . (int) $sum['late'] . ' late' : '')) ?>
  <?= ui_metric('Absent today', 'user-x', num($sum['absent_total']), (int) $sum['excused'] . ' excused', 'yellow') ?>
  <?= ui_metric('Pending enrollment', 'clipboard-list', num($enrollment['pending']), '<a class="text-link" href="' . e(site_url('enrollment?status=Pending')) . '">Review applications</a>', 'yellow') ?>
</div>

<div class="dashboard-grid">
  <?php ui_panel('Attendance this week', 'Gate time-ins for ' . fmt_date($week['from'], 'M j') . ' – ' . fmt_date($week['to'], 'M j'),
      '<a class="text-link" href="' . e(site_url('attendance?date=' . $date)) . '">Open attendance ' . icon('arrow-right', 12) . '</a>'); ?>
    <div class="panel-body chart">
      <?= chart_bars($week['labels'], [
          ['name' => 'Present', 'color' => 'var(--chart-green)', 'values' => $week['present']],
          ['name' => 'Absent',  'color' => 'var(--yellow)',      'values' => $week['absent']],
      ], ['label' => 'Present and absent learners per school day this week']) ?>
      <?= chart_legend([['name' => 'Present', 'color' => 'var(--chart-green)'], ['name' => 'Absent', 'color' => 'var(--yellow)']]) ?>
    </div>
  <?php ui_panel_end(); ?>

  <?php ui_panel('Student standing', 'Rule-based indicators across all grades'); ?>
    <div class="panel-body">
      <div class="performance-content">
        <div class="donut"><?= chart_donut($donut, num($graded), 'with grades') ?></div>
        <div class="performance-legend">
          <?php foreach ($distribution as $label => $n): ?>
            <div class="performance-row"><span><i class="legend-dot" style="background:<?= e($colors[$label]) ?>"></i> <?= e($label) ?></span><strong><?= (int) $n ?></strong></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="subtle-note"><?= icon('info', 13) ?> Indicators support people's decisions; they never change records.</div>
    </div>
  <?php ui_panel_end(); ?>
</div>

<div class="dashboard-grid">
  <?php ui_panel('Gate vs. classroom attendance', $disc_count ? $disc_count . ' class record' . ($disc_count === 1 ? '' : 's') . ' missing for learners who entered the gate' : 'Everything reconciles',
      $disc_count ? '<a class="text-link" href="' . e(site_url('attendance?date=' . $date . '#discrepancies')) . '">Resolve</a>' : ''); ?>
    <div class="panel-body">
      <?php if (!$discrepancies): ?>
        <?= ui_empty('Every learner who entered the gate has a classroom record for classes that already started.', 'circle-check') ?>
      <?php else: foreach ($discrepancies as $d): ?>
        <div class="activity-row">
          <span class="activity-symbol yellow"><?= icon('triangle-alert', 15) ?></span>
          <div class="activity-info"><strong><?= e($d['full_name']) ?></strong>
            <p><?= e($d['subject_name']) ?> · <?= e(grade_section($d['grade'], $d['section_name'])) ?> · gate time-in <?= e(fmt_time($d['first_in'])) ?></p></div>
          <span class="activity-time"><?= e(fmt_time($d['period_start'])) ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  <?php ui_panel_end(); ?>

  <?php ui_panel('Needs attention', 'Learners flagged by the standing rules', '<a class="text-link" href="' . e(site_url('guidance')) . '">All learners</a>'); ?>
    <div class="panel-body">
      <?php if (!$review): ?>
        <?= ui_empty('No learner currently needs review.', 'circle-check') ?>
      <?php else: foreach ($review as $s): ?>
        <div class="activity-row">
          <span class="user-avatar"><?= e($s['initials']) ?></span>
          <div class="activity-info"><strong><a href="<?= e(site_url('guidance/' . $s['id'])) ?>"><?= e($s['full_name']) ?></a></strong>
            <p><?= e($s['section_label']) ?> · <?= e($s['standing']['reasons'][0]) ?></p></div>
          <?= badge($s['standing']['label'], standing_tone($s['standing']['label'])) ?>
        </div>
      <?php endforeach; endif; ?>
    </div>
  <?php ui_panel_end(); ?>
</div>

<div class="dashboard-grid">
  <?php ui_panel('Latest notifications', '', '<a class="text-link" href="' . e(site_url('notifications')) . '">View all</a>'); ?>
    <div class="panel-body">
      <?php if (!$notices): ?><?= ui_empty('No notifications yet.', 'bell') ?>
      <?php else: foreach ($notices as $n): ?>
        <div class="activity-row">
          <span class="activity-symbol"><?= icon('bell', 15) ?></span>
          <div class="activity-info"><strong><?= e($n['title']) ?></strong><p><?= e(mb_strimwidth($n['body'], 0, 140, '…')) ?></p></div>
          <span class="activity-time"><?= e(time_ago($n['created_at'])) ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  <?php ui_panel_end(); ?>

  <?php ui_panel('Quick actions'); ?>
    <div class="panel-body">
      <div class="quick-grid">
        <a class="quick-action" href="<?= e(site_url('enrollment')) ?>"><?= icon('clipboard-list', 19) ?> New enrollment</a>
        <a class="quick-action" href="<?= e(site_url('attendance/devices')) ?>"><?= icon('scan-line', 19) ?> Gate devices</a>
        <a class="quick-action" href="<?= e(site_url('services')) ?>"><?= icon('files', 19) ?> Requests (<?= (int) ($req_counts['Pending'] + $req_counts['Processing']) ?> open)</a>
        <a class="quick-action" href="<?= e(site_url('reports')) ?>"><?= icon('chart-no-axes-combined', 19) ?> Reports</a>
      </div>
      <div class="support-strip">
        <?= icon('info', 18) ?>
        <div><strong>School average: <?= $average === null ? '—' : e($average) ?></strong>
          <?= (int) $counts['incomplete'] ?> learner record<?= $counts['incomplete'] === 1 ? '' : 's' ?> with incomplete contact details · <?= (int) $req_new ?> new request<?= $req_new === 1 ? '' : 's' ?> today</div>
        <a href="<?= e(site_url('students')) ?>">Students</a>
      </div>
    </div>
  <?php ui_panel_end(); ?>
</div>
