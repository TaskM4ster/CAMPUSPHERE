<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php
$actions = $is_teacher ? '<button class="btn btn-primary" type="button" data-open="new-activity">' . icon('plus', 14) . ' New activity</button>' : '';
ui_heading($user['role'] === 'Student' ? 'My activities' : 'Activities', $user['role'] === 'Student' ? 'Submit your work before the deadline' : 'Publish tasks, collect submissions, record scores', $actions); ?>

<?php if ($to_check): ?><div class="flash warning"><?= icon('clipboard-check', 16) ?><div class="grow"><?= (int) $to_check ?> submission<?= $to_check === 1 ? '' : 's' ?> waiting for your score and feedback.</div></div><?php endif; ?>

<section class="panel">
  <?php if (!$rows): ?><?= ui_empty('No activities yet.', 'calendar-days') ?><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Activity</th><th>Subject</th><th>Section</th><th>Deadline</th>
      <?php if ($user['role'] === 'Student'): ?><th>My status</th><th>Score</th><?php else: ?><th class="right">Submitted</th><th class="right">To check</th><?php endif; ?><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $x): $a = $x['a']; $v = $x['view']; $late = $a['deadline'] < $today; ?>
      <tr><td class="wrap"><strong><a href="<?= e(site_url('activities/' . $a['id'])) ?>"><?= e($a['title']) ?></a></strong><br><small class="muted"><?= e($a['teacher_name']) ?></small></td>
        <td><?= e($a['subject_name']) ?></td><td><?= e(grade_section($a['grade'], $a['section_name'])) ?></td>
        <td class="nowrap"><?= e(fmt_date($a['deadline'])) ?> <?= $late ? badge('Past due', 'neutral') : '' ?></td>
        <?php if ($v): ?>
          <td><?= badge($v['status'], status_tone($v['status'])) ?><?= $v['late'] ? ' ' . badge('Late', 'warning') : '' ?></td><td class="num"><?= e($v['score']) ?></td>
        <?php else: ?>
          <td class="right num"><?= (int) $a['submitted_count'] ?>/<?= (int) $a['class_size'] ?></td><td class="right num"><?= (int) $a['to_check'] ?></td>
        <?php endif; ?>
        <td class="right"><a class="btn btn-sm" href="<?= e(site_url('activities/' . $a['id'])) ?>">Open</a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<?php if ($is_teacher): ?>
<dialog class="modal wide" id="new-activity" <?= old('title') !== '' ? 'data-autoopen' : '' ?>>
  <?php post_form('activities'); ?>
    <div class="modal-header"><h2>New activity</h2><button class="icon-btn" type="button" data-close aria-label="Close"><?= icon('x', 18) ?></button></div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="form-group full"><label class="form-label" for="a-title">Title</label><input class="field" id="a-title" name="title" maxlength="160" required value="<?= e(old('title')) ?>"></div>
        <div class="form-group"><label class="form-label" for="a-class">Class</label>
          <select class="field" id="a-class" name="class" required><option value="">Choose a class</option>
            <?php foreach ($classes as $c): $k = $c['section_id'] . ':' . $c['subject_id']; ?><option value="<?= e($k) ?>"<?= selected($k, old('class')) ?>><?= e($c['subject_name'] . ' · ' . grade_section($c['grade'], $c['section_name'])) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label class="form-label" for="a-deadline">Deadline</label><input class="field" id="a-deadline" name="deadline" type="date" min="<?= e($today) ?>" required value="<?= e(old('deadline')) ?>"></div>
        <div class="form-group"><label class="form-label" for="a-max">Maximum score</label><input class="field" id="a-max" name="max_score" type="number" min="1" max="100" value="<?= e(old('max_score', '25')) ?>" required></div>
        <div class="form-group full"><label class="form-label" for="a-inst">Instructions</label><textarea class="field" id="a-inst" name="instructions" required maxlength="2000"><?= e(old('instructions')) ?></textarea></div>
      </div>
      <p class="field-hint">Learners in the section are notified when you publish.</p>
    </div>
    <div class="modal-footer"><button class="btn" type="button" data-close>Cancel</button><button class="btn btn-primary" type="submit">Publish</button></div>
  </form>
</dialog>
<?php endif; ?>
