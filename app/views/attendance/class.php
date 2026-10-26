<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php ui_heading('Class attendance', $subject['name'] . ' · ' . grade_section($section['grade'], $section['name']) . ' · ' . fmt_date($date, 'l, M j'),
    '<a class="btn" href="' . e(site_url('attendance')) . '">Back to attendance</a>'); ?>

<form class="filters" method="get" action="<?= e(site_url('attendance/class')) ?>">
  <input type="hidden" name="section" value="<?= (int) $section['id'] ?>"><input type="hidden" name="subject" value="<?= (int) $subject['id'] ?>">
  <input class="field" type="date" name="date" max="<?= e(today()) ?>" value="<?= e($date) ?>" aria-label="Date" onchange="this.form.submit()">
</form>

<section class="panel">
  <?php post_form('attendance/class'); ?>
  <input type="hidden" name="section_id" value="<?= (int) $section['id'] ?>"><input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>"><input type="hidden" name="date" value="<?= e($date) ?>">
  <div class="panel-header"><div><h2>Roster</h2><p>Changing an existing record requires a reason, which is kept in the audit trail.</p></div>
    <div class="panel-actions"><button class="btn btn-sm" type="button" data-set-all="Present">Set all Present</button></div></div>
  <?php if (!$sheet): ?><?= ui_empty('No learners are enrolled in this section.', 'users') ?><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Learner</th><th>Gate in</th><th>Recorded</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($sheet as $r): ?>
      <tr><td><?= ui_student($r['full_name'], $r['student_no']) ?></td>
        <td class="num"><?= $r['first_in'] ? e(fmt_time($r['first_in'])) : '<span class="muted">No scan</span>' ?></td>
        <td><?= $r['status'] ? badge($r['status'], status_tone($r['status'])) : '<span class="badge neutral">Not yet</span>' ?></td>
        <td><select class="status-select" name="status[<?= (int) $r['student_id'] ?>]" aria-label="Status for <?= e($r['full_name']) ?>">
          <?= ui_options(array_combine($statuses, $statuses), $r['status'] ?: ($r['first_in'] ? 'Present' : 'Absent')) ?></select></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="panel-body row between">
    <input class="field" style="max-width:360px" name="reason" maxlength="255" placeholder="Reason (required only when correcting an existing record)" aria-label="Correction reason">
    <button class="btn btn-primary" type="submit"><?= icon('check', 14) ?> Save attendance</button>
  </div>
  <?php endif; ?>
  </form>
</section>
