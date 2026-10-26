<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$sum = $summary;
$prev = date('Y-m-d', strtotime($date . ' -1 day')); $next = date('Y-m-d', strtotime($date . ' +1 day'));
?>
<?php
$actions = '<a class="btn btn-sm" href="' . e(site_url('attendance?date=' . $prev)) . '" aria-label="Previous day">' . icon('chevron-right', 14, 'flip') . '</a>'
  . '<form class="filters" style="margin:0" method="get" action="' . e(site_url('attendance')) . '"><input class="field" type="date" name="date" max="' . e(today()) . '" value="' . e($date) . '" aria-label="Date" onchange="this.form.submit()"></form>'
  . ($next <= today() ? '<a class="btn btn-sm" href="' . e(site_url('attendance?date=' . $next)) . '" aria-label="Next day">' . icon('chevron-right', 14) . '</a>' : '')
  . ($is_admin ? '<a class="btn" href="' . e(site_url('attendance/devices')) . '">' . icon('scan-line', 14) . ' Gate devices</a>' : '');
ui_heading('Attendance', 'Gate scans set the daily status; classroom records confirm each period', $actions); ?>

<div class="metric-grid">
  <?= ui_metric('Enrolled', 'users', num($sum['enrolled']), e(fmt_date($date, 'D, M j'))) ?>
  <?= ui_metric('Present', 'user-check', num($sum['present_total']), $sum['rate'] === null ? '—' : '<em>' . e($sum['rate']) . '%</em> attendance rate' . ($sum['late'] ? ' · ' . (int) $sum['late'] . ' late' : '')) ?>
  <?= ui_metric('Absent', 'user-x', num($sum['absent_total']), (int) $sum['excused'] . ' excused', 'yellow') ?>
  <?= ui_metric('Missing class records', 'triangle-alert', num($disc_total), 'Gate in, no classroom record', $disc_total ? 'danger' : '') ?>
</div>

<?php if ($my_classes): ?>
<section class="panel mb">
  <div class="panel-header"><div><h2>Record classroom attendance</h2><p>Choose one of your classes</p></div></div>
  <div class="panel-body row">
    <?php foreach ($my_classes as $c): ?>
      <a class="btn" href="<?= e(site_url('attendance/class?section=' . $c['section_id'] . '&subject=' . $c['subject_id'] . '&date=' . today())) ?>"><?= icon('user-check', 14) ?> <?= e($c['subject_name']) ?> · <?= e(grade_section($c['grade'], $c['section_name'])) ?> · <?= e(fmt_time($c['period_start'])) ?></a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<div class="grid-2">
  <?php ui_panel('This week', fmt_date($week['from'], 'M j') . ' – ' . fmt_date($week['to'], 'M j')); ?>
    <div class="panel-body chart"><?= chart_bars($week['labels'], [
        ['name' => 'Present', 'color' => 'var(--chart-green)', 'values' => $week['present']],
        ['name' => 'Absent', 'color' => 'var(--yellow)', 'values' => $week['absent']]], ['label' => 'Attendance this week']) ?>
      <?= chart_legend([['name' => 'Present', 'color' => 'var(--chart-green)'], ['name' => 'Absent', 'color' => 'var(--yellow)']]) ?></div>
  <?php ui_panel_end(); ?>
  <?php ui_panel('This month', 'Weekly average of ' . fmt_date($date, 'F Y')); ?>
    <div class="panel-body chart"><?php if ($month['labels']): ?><?= chart_bars($month['labels'], [
        ['name' => 'Present', 'color' => 'var(--chart-green)', 'values' => $month['present']],
        ['name' => 'Absent', 'color' => 'var(--yellow)', 'values' => $month['absent']]], ['label' => 'Average attendance per week']) ?>
      <?= chart_legend([['name' => 'Present', 'color' => 'var(--chart-green)'], ['name' => 'Absent', 'color' => 'var(--yellow)']]) ?>
      <?php else: ?><?= ui_empty('No gate scans this month.', 'scan-line') ?><?php endif; ?></div>
  <?php ui_panel_end(); ?>
</div>

<?php if ($detail): $st = $detail['student']; ?>
<?php ui_panel($st['full_name'], $st['student_no'] . ' · ' . $st['section_label'] . ' · ' . fmt_date($date, 'l, M j'), '<a class="btn btn-sm" href="' . e(site_url('attendance?date=' . $date)) . '">Close</a>', 'detail mb'); ?>
  <div class="panel-body">
    <div class="row" style="margin-bottom:14px">
      <?php if ($detail['gate']): foreach ($detail['gate'] as $g): ?><?= badge($g['event'] . ' · ' . fmt_time($g['log_time']) . ' · ' . $g['source'], $g['event'] === 'Time-in' ? '' : 'neutral') ?><?php endforeach;
      else: ?><span class="muted small">No gate scan recorded.</span><?php endif; ?>
    </div>
    <div class="table-wrap"><table>
      <thead><tr><th>Period</th><th>Subject</th><th>Teacher</th><th>Status</th><th>Record / correct</th></tr></thead>
      <tbody>
      <?php foreach ($detail['classes'] as $c): ?>
        <tr>
          <td class="num"><?= e(fmt_time($c['period_start'])) ?></td><td><strong><?= e($c['subject_name']) ?></strong></td><td><?= e($c['teacher_name']) ?></td>
          <td><?= $c['status'] ? badge($c['status'], status_tone($c['status'])) : badge('Missing record', 'warning') ?>
            <?php if ($c['history']): ?><div class="small muted"><?php foreach ($c['history'] as $h): ?><?= e($h['old_status']) ?> → <?= e($h['new_status']) ?> (<?= e($h['reason']) ?>, <?= e($h['by_name']) ?>)<br><?php endforeach; ?></div><?php endif; ?></td>
          <td>
            <?php if ($c['can_record']): ?>
              <?php post_form('attendance/record', 'class="row"'); ?>
                <input type="hidden" name="student_id" value="<?= (int) $st['id'] ?>"><input type="hidden" name="subject_id" value="<?= (int) $c['subject_id'] ?>"><input type="hidden" name="date" value="<?= e($date) ?>">
                <select name="status" class="status-select" aria-label="Status for <?= e($c['subject_name']) ?>"><?= ui_options(array_combine($statuses, $statuses), $c['status'] ?: 'Present') ?></select>
                <?php if ($c['status']): ?><input class="field" style="width:200px" name="reason" placeholder="Reason for correction" maxlength="255" required aria-label="Correction reason"><?php endif; ?>
                <button class="btn btn-sm btn-primary" type="submit"><?= $c['status'] ? 'Correct' : 'Record' ?></button>
              </form>
            <?php else: ?><span class="muted small">Teacher / administrator only</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
<?php ui_panel_end(); endif; ?>

<section class="panel mb" id="discrepancies">
  <div class="panel-header"><div><h2>Gate vs. classroom discrepancies</h2><p>Learners who passed the gate but have no record for a class that already started</p></div>
    <?php if ($is_admin && $disc): ?><?php post_form('attendance/notify', 'class="panel-actions"'); ?><input type="hidden" name="date" value="<?= e($date) ?>"><button class="btn btn-sm" type="submit"><?= icon('bell', 13) ?> Notify advisers</button></form><?php endif; ?></div>
  <?php if (!$disc): ?><?= ui_empty('No discrepancies for this date.', 'circle-check') ?><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Learner</th><th>Subject</th><th>Period</th><th>Gate in</th><th class="right">Action</th></tr></thead>
    <tbody>
    <?php foreach ($disc as $d): ?>
      <tr><td><?= ui_student($d['full_name'], grade_section($d['grade'], $d['section_name']), null, site_url('attendance?date=' . $date . '&detail=' . $d['student_id'])) ?></td>
        <td><?= e($d['subject_name']) ?></td><td class="num"><?= e(fmt_time($d['period_start'])) ?></td><td class="num"><?= e(fmt_time($d['first_in'])) ?></td>
        <td class="right nowrap">
          <?php post_form('attendance/verify', 'class="inline"'); ?><input type="hidden" name="student_id" value="<?= (int) $d['student_id'] ?>"><input type="hidden" name="subject_id" value="<?= (int) $d['subject_id'] ?>"><input type="hidden" name="date" value="<?= e($date) ?>">
            <button class="btn btn-sm" type="submit"><?= icon('check', 13) ?> Verify present</button></form>
          <a class="btn btn-sm" href="<?= e(site_url('attendance?date=' . $date . '&detail=' . $d['student_id'])) ?>">Review</a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($disc_total > count($disc)): ?><div class="table-footer"><span>Showing <?= count($disc) ?> of <?= (int) $disc_total ?></span></div><?php endif; ?>
  <?php endif; ?>
</section>

<?php ui_filters_open('attendance'); ?>
  <input type="hidden" name="date" value="<?= e($date) ?>">
  <label class="search-field"><?= icon('search', 15) ?><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search learner" aria-label="Search learner"></label>
  <select name="section" aria-label="Section" data-autosubmit><?= ui_sections_options($sections, $f['section_id']) ?></select>
  <button class="btn" type="submit">Search</button>
</form>

<section class="panel mb">
  <div class="panel-header"><div><h2>Daily roster</h2><p>Late after <?= e(fmt_time(config_item('late_cutoff'))) ?></p></div></div>
  <?php if (!$roster): ?><?= ui_empty('No learners match.', 'users') ?><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Learner</th><th>Section</th><th>Gate in</th><th>Gate out</th><th>Day status</th><th>Classroom</th></tr></thead>
    <tbody>
    <?php foreach ($roster as $r): ?>
      <tr><td><?= ui_student($r['full_name'], $r['student_no'], $r['initials'], site_url('attendance?date=' . $date . '&detail=' . $r['id'])) ?></td>
        <td><?= e(grade_section($r['grade'], $r['section_name'])) ?></td>
        <td class="num"><?= $r['first_in'] ? e(fmt_time($r['first_in'])) : '—' ?></td><td class="num"><?= $r['last_out'] ? e(fmt_time($r['last_out'])) : '—' ?></td>
        <td><?= badge($r['day_status'], status_tone($r['day_status'])) ?></td>
        <td><?= badge($r['class_badge'], status_tone($r['class_badge'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= pager($total, $page, $per) ?>
  <?php endif; ?>
</section>

<?php if ($is_admin): ?>
<?php ui_panel('Manual gate entry', 'For lost cards or a scanner outage — recorded and audited as a manual source'); ?>
  <div class="panel-body">
    <?php post_form('attendance/gate', 'class="filters"'); ?>
      <input type="hidden" name="date" value="<?= e($date) ?>">
      <select class="field" name="student_id" required aria-label="Learner"><option value="">Choose learner</option>
        <?php foreach ($roster as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['full_name']) ?></option><?php endforeach; ?></select>
      <select class="field" name="event" aria-label="Event"><option>Time-in</option><option>Time-out</option></select>
      <input class="field" type="time" name="time" value="<?= e(date('H:i')) ?>" aria-label="Time" required>
      <button class="btn btn-primary" type="submit">Record</button>
    </form>
    <p class="field-hint">The learner list shows the learners on the current page of the roster; use the search above to find others.</p>
  </div>
<?php ui_panel_end(); endif; ?>
