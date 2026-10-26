<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php ui_heading('Teacher dashboard', 'Welcome, ' . $user['full_name'] . ' · ' . count($sections) . ' class' . (count($sections) === 1 ? '' : 'es') . ' this school year',
    '<span class="date-chip">' . icon('calendar-days', 14) . ' ' . e(fmt_date($date, 'D, M j')) . '</span>'); ?>

<div class="metric-grid">
  <?= ui_metric('My learners', 'users', num(array_sum(array_column($sections, 'headcount'))), count($sections) . ' section' . (count($sections) === 1 ? '' : 's')) ?>
  <?= ui_metric('Present (my classes)', 'user-check', num($summary['present_total']), $summary['rate'] === null ? 'No scans yet' : '<em>' . e($summary['rate']) . '%</em> of ' . (int) $summary['enrolled']) ?>
  <?= ui_metric('Missing class records', 'triangle-alert', num($disc_count), 'Gate in, no classroom record', $disc_count ? 'yellow' : '') ?>
  <?= ui_metric('Submissions to check', 'clipboard-check', num($to_check), '<a class="text-link" href="' . e(site_url('activities')) . '">Open activities</a>', $to_check ? 'yellow' : '') ?>
</div>

<div class="dashboard-grid">
  <?php ui_panel('My classes', 'Open a class to record attendance or encode grades'); ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Subject</th><th>Section</th><th>Period</th><th class="right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($assignments as $a): ?>
        <tr>
          <td><strong><?= e($a['subject_name']) ?></strong></td>
          <td><?= e(grade_section($a['grade'], $a['section_name'])) ?></td>
          <td class="num"><?= e(fmt_time($a['period_start'])) ?></td>
          <td class="right nowrap">
            <a class="btn btn-sm" href="<?= e(site_url('attendance/class?section=' . $a['section_id'] . '&subject=' . $a['subject_id'] . '&date=' . today())) ?>"><?= icon('user-check', 13) ?> Attendance</a>
            <a class="btn btn-sm" href="<?= e(site_url('grades?section=' . $a['section_id'] . '&subject=' . $a['subject_id'])) ?>"><?= icon('book-open', 13) ?> Grades</a>
          </td>
        </tr>
      <?php endforeach; if (!$assignments): ?><tr><td colspan="4"><?= ui_empty('No classes are assigned to you yet.') ?></td></tr><?php endif; ?>
      </tbody>
    </table></div>
  <?php ui_panel_end(); ?>

  <?php ui_panel('Gate vs. classroom', $disc_count ? $disc_count . ' to resolve' : 'All reconciled', '<a class="text-link" href="' . e(site_url('attendance?date=' . $date . '#discrepancies')) . '">Open</a>'); ?>
    <div class="panel-body">
      <?php if (!$discrepancies): ?><?= ui_empty('No missing classroom records.', 'circle-check') ?>
      <?php else: foreach ($discrepancies as $d): ?>
        <div class="activity-row"><span class="activity-symbol yellow"><?= icon('triangle-alert', 15) ?></span>
          <div class="activity-info"><strong><?= e($d['full_name']) ?></strong><p><?= e($d['subject_name']) ?> · in at <?= e(fmt_time($d['first_in'])) ?></p></div>
          <span class="activity-time"><?= e(fmt_time($d['period_start'])) ?></span></div>
      <?php endforeach; endif; ?>
    </div>
  <?php ui_panel_end(); ?>
</div>

<div class="dashboard-grid">
  <?php ui_panel('Learners to look at', 'From the standing rules', '<a class="text-link" href="' . e(site_url('guidance')) . '">Student standing</a>'); ?>
    <div class="panel-body">
      <?php if (!$review): ?><?= ui_empty('No learner in your classes currently needs review.', 'circle-check') ?>
      <?php else: foreach ($review as $s): ?>
        <div class="activity-row"><span class="user-avatar"><?= e($s['initials']) ?></span>
          <div class="activity-info"><strong><a href="<?= e(site_url('students/' . $s['id'])) ?>"><?= e($s['full_name']) ?></a></strong><p><?= e($s['section_label']) ?> · <?= e($s['standing']['reasons'][0]) ?></p></div>
          <?= badge($s['standing']['label'], standing_tone($s['standing']['label'])) ?></div>
      <?php endforeach; endif; ?>
    </div>
  <?php ui_panel_end(); ?>

  <?php ui_panel('Recent activities', '', '<a class="text-link" href="' . e(site_url('activities')) . '">All</a>'); ?>
    <div class="panel-body">
      <?php if (!$activities): ?><?= ui_empty('No activities yet. Publish one from the Activities page.', 'calendar-days') ?>
      <?php else: foreach ($activities as $a): ?>
        <div class="activity-row"><span class="activity-symbol"><?= icon('calendar-days', 15) ?></span>
          <div class="activity-info"><strong><a href="<?= e(site_url('activities/' . $a['id'])) ?>"><?= e($a['title']) ?></a></strong>
            <p><?= e($a['subject_name']) ?> · <?= e(grade_section($a['grade'], $a['section_name'])) ?> · due <?= e(fmt_date($a['deadline'])) ?></p></div>
          <span class="activity-time"><?= (int) $a['submitted_count'] ?>/<?= (int) $a['class_size'] ?></span></div>
      <?php endforeach; endif; ?>
    </div>
  <?php ui_panel_end(); ?>
</div>

<?php if ($notices): ui_panel('Notifications', '', '<a class="text-link" href="' . e(site_url('notifications')) . '">View all</a>'); ?>
  <div class="panel-body">
    <?php foreach ($notices as $n): ?>
      <div class="activity-row"><span class="activity-symbol"><?= icon('bell', 15) ?></span>
        <div class="activity-info"><strong><?= e($n['title']) ?></strong><p><?= e(mb_strimwidth($n['body'], 0, 160, '…')) ?></p></div>
        <span class="activity-time"><?= e(time_ago($n['created_at'])) ?></span></div>
    <?php endforeach; ?>
  </div>
<?php ui_panel_end(); endif; ?>
