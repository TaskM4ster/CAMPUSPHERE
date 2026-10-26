<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$st = $me['standing'];
?>
<?php ui_heading('Hello, ' . explode(' ', $me['full_name'])[0], $me['section_label'] . ' · ' . $me['student_no'],
    '<span class="date-chip">' . icon('calendar-days', 14) . ' ' . e(fmt_date($date, 'D, M j')) . '</span>'); ?>

<div class="metric-grid">
  <?= ui_metric('General average', 'book-open', $me['average'] === null ? '—' : $me['average'], '<a class="text-link" href="' . e(site_url('student/grades')) . '">View grades</a>') ?>
  <?= ui_metric('Attendance', 'user-check', $me['attendance'] === null ? '—' : $me['attendance'] . '%', (int) $me['days_present'] . ' day(s) present') ?>
  <?= ui_metric('Activities to submit', 'calendar-days', num($pending), '<a class="text-link" href="' . e(site_url('activities')) . '">Open activities</a>', $pending ? 'yellow' : '') ?>
  <?= ui_metric('Standing', 'shield-check', $st['label'], e($st['reasons'][0]), $st['level'] ? 'yellow' : '') ?>
</div>

<div class="dashboard-grid">
  <?php ui_panel('Today at school', fmt_date($date, 'l, F j')); ?>
    <div class="panel-body">
      <?php if ($gate): ?>
        <div class="row" style="margin-bottom:14px">
          <?php foreach ($gate as $g): ?><?= badge($g['event'] . ' · ' . fmt_time($g['log_time']), $g['event'] === 'Time-in' ? '' : 'neutral') ?><?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted small">No gate scan recorded for this day.</p>
      <?php endif; ?>
      <?php if ($classes): ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Period</th><th>Subject</th><th>Teacher</th><th>Class attendance</th></tr></thead>
        <tbody>
        <?php foreach ($classes as $c): ?>
          <tr><td class="num"><?= e(fmt_time($c['period_start'])) ?></td><td><strong><?= e($c['subject_name']) ?></strong></td><td><?= e($c['teacher_name']) ?></td>
            <td><?= $c['status'] ? badge($c['status'], status_tone($c['status'])) : '<span class="muted small">Not yet recorded</span>' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>
  <?php ui_panel_end(); ?>

  <?php ui_panel('Needs attention', 'Subjects below ' . (int) config_item('support_grade_below')); ?>
    <div class="panel-body">
      <?php if (!$weak): ?><?= ui_empty('No subject is below the support threshold. Keep it up!', 'circle-check') ?>
      <?php else: foreach ($weak as $w): ?>
        <div class="activity-row"><span class="activity-symbol yellow"><?= icon('triangle-alert', 15) ?></span>
          <div class="activity-info"><strong><?= e($w['subject']) ?></strong><p>Final grade <?= e(rtrim(rtrim(number_format((float) $w['final_grade'], 2), '0'), '.')) ?>. Talk to your teacher about extra support.</p></div></div>
      <?php endforeach; endif; ?>
    </div>
  <?php ui_panel_end(); ?>
</div>

<div class="dashboard-grid">
  <?php ui_panel('Upcoming activities', '', '<a class="text-link" href="' . e(site_url('activities')) . '">All activities</a>'); ?>
    <div class="panel-body">
      <?php if (!$activities): ?><?= ui_empty('No activities assigned yet.', 'calendar-days') ?>
      <?php else: foreach ($activities as $a): ?>
        <div class="activity-row"><span class="activity-symbol"><?= icon('calendar-days', 15) ?></span>
          <div class="activity-info"><strong><a href="<?= e(site_url('activities/' . $a['id'])) ?>"><?= e($a['title']) ?></a></strong><p><?= e($a['subject_name']) ?> · due <?= e(fmt_date($a['deadline'])) ?></p></div></div>
      <?php endforeach; endif; ?>
    </div>
  <?php ui_panel_end(); ?>

  <?php ui_panel('Notifications', '', '<a class="text-link" href="' . e(site_url('notifications')) . '">View all</a>'); ?>
    <div class="panel-body">
      <?php if (!$notices): ?><?= ui_empty('No notifications.', 'bell') ?>
      <?php else: foreach ($notices as $n): ?>
        <div class="activity-row"><span class="activity-symbol <?= (int) $n['is_read'] ? '' : 'yellow' ?>"><?= icon('bell', 15) ?></span>
          <div class="activity-info"><strong><?= e($n['title']) ?></strong><p><?= e(mb_strimwidth($n['body'], 0, 120, '…')) ?></p></div>
          <span class="activity-time"><?= e(time_ago($n['created_at'])) ?></span></div>
      <?php endforeach; endif; ?>
      <?php if ($requests): ?>
        <div class="support-strip"><?= icon('files', 18) ?><div><strong>Latest request</strong><?= e($requests[0]['type']) ?> · <?= e($requests[0]['status']) ?></div>
          <a href="<?= e(site_url('student/requests')) ?>">Track</a></div>
      <?php endif; ?>
    </div>
  <?php ui_panel_end(); ?>
</div>
