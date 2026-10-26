<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$closed = $a['deadline'] < $today;
?>
<?php ui_heading($a['title'], $a['subject_name'] . ' · ' . grade_section($a['grade'], $a['section_name']) . ' · ' . $a['teacher_name'],
    '<a class="btn" href="' . e(site_url('activities')) . '">Back</a>'); ?>

<div class="section-grid">
  <section class="panel">
    <div class="panel-header"><div><h2>Instructions</h2><p>Due <?= e(fmt_date($a['deadline'], 'l, F j, Y')) ?> · maximum score <?= (int) $a['max_score'] ?><?= $closed ? ' · deadline passed' : '' ?></p></div></div>
    <div class="panel-body"><p style="white-space:pre-wrap"><?= e($a['instructions']) ?></p></div>
  </section>

  <?php if ($view): $sub = $view['row']; ?>
  <section class="panel">
    <div class="panel-header"><div><h2>My submission</h2><p><?= badge($view['status'], status_tone($view['status'])) ?> <?= $view['late'] ? badge('Submitted late', 'warning') : '' ?></p></div></div>
    <div class="panel-body">
      <?php if ($sub): ?>
        <p><?= icon('files', 15) ?> <a href="<?= e(site_url('activities/' . $a['id'] . '/file/' . $user['student_id'])) ?>"><?= e($sub['file_name']) ?></a><br>
          <small class="muted">Submitted <?= e(fmt_datetime($sub['submitted_at'])) ?></small></p>
        <p><strong>Score:</strong> <?= e($view['score']) ?></p>
        <p><strong>Feedback:</strong> <?= e($view['feedback']) ?></p>
      <?php else: ?><p class="muted">You have not submitted this activity yet.</p><?php endif; ?>
      <?php if (!$sub || $sub['status'] !== 'Graded'): ?>
        <form method="post" enctype="multipart/form-data" action="<?= e(site_url('activities/' . $a['id'] . '/submit')) ?>" style="margin-top:14px">
          <?php csrf_field(); ?>
          <div class="form-group"><label class="form-label" for="work"><?= $sub ? 'Replace your file' : 'Upload your work' ?></label>
            <input class="field" id="work" type="file" name="work" required accept=".<?= e(implode(',.', $ext)) ?>">
            <div class="field-hint"><?= e(strtoupper(implode(', ', $ext))) ?> · up to <?= (int) $max_mb ?> MB<?= $closed ? ' · after the deadline, your submission is marked late' : '' ?></div></div>
          <button class="btn btn-primary" type="submit"><?= icon('upload', 14) ?> Submit</button>
        </form>
      <?php endif; ?>
    </div>
  </section>
  <?php else: ?>
  <section class="panel">
    <div class="panel-header"><div><h2>Progress</h2></div></div>
    <div class="panel-body"><p class="num"><strong><?= (int) $a['submitted_count'] ?></strong> of <?= (int) $a['class_size'] ?> submitted · <strong><?= (int) $a['to_check'] ?></strong> to check</p>
      <?= meter($a['class_size'] ? 100 * $a['submitted_count'] / $a['class_size'] : 0, 50, 25) ?></div>
  </section>
  <?php endif; ?>
</div>

<?php if (!$view): ?>
<section class="panel">
  <div class="panel-header"><div><h2>Submissions</h2><p><?= $can_grade ? 'Record a score and feedback for each submitted work.' : 'Only the class teacher can record scores.' ?></p></div></div>
  <div class="table-wrap"><table>
    <thead><tr><th>Learner</th><th>Status</th><th>File</th><th>Score</th><?php if ($can_grade): ?><th>Score &amp; feedback</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($roster as $r): $st = $r['status'] ?: 'Not submitted'; ?>
      <tr><td><?= ui_student($r['full_name'], $r['student_no']) ?></td>
        <td><?= badge($st, status_tone($st)) ?></td>
        <td class="wrap"><?= $r['file_name'] ? '<a href="' . e(site_url('activities/' . $a['id'] . '/file/' . $r['student_id'])) . '">' . e($r['file_name']) . '</a><br><small class="muted">' . e(fmt_datetime($r['submitted_at'])) . '</small>' : '<span class="muted">—</span>' ?></td>
        <td class="num"><?= $r['score'] === null ? '—' : e(rtrim(rtrim(number_format((float) $r['score'], 2), '0'), '.')) . ' / ' . (int) $a['max_score'] ?></td>
        <?php if ($can_grade): ?><td>
          <?php if ($r['status']): ?>
            <?php post_form('activities/' . $a['id'] . '/grade', 'class="row"'); ?>
              <input type="hidden" name="student_id" value="<?= (int) $r['student_id'] ?>">
              <input class="field score-input num" type="number" name="score" min="0" max="<?= (int) $a['max_score'] ?>" step="0.01" value="<?= $r['score'] === null ? '' : e($r['score']) ?>" required aria-label="Score for <?= e($r['full_name']) ?>">
              <input class="field" style="width:220px" name="feedback" maxlength="500" value="<?= e($r['feedback']) ?>" placeholder="Feedback" required aria-label="Feedback for <?= e($r['full_name']) ?>">
              <button class="btn btn-sm btn-primary" type="submit"><?= $r['status'] === 'Graded' ? 'Update' : 'Save' ?></button>
            </form>
          <?php else: ?><span class="muted small">Waiting for submission</span><?php endif; ?></td><?php endif; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>
