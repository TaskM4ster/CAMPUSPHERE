<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$fmt = function ($v) { return $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.'); };
$st = $s['standing'];
$pass = (int) config_item('passing_grade');
?>
<?php ui_heading($s['full_name'], $s['student_no'] . ' · ' . $s['section_label'], '<a class="btn" href="' . e(site_url('students')) . '">' . icon('arrow-right', 14, 'flip') . ' Back to list</a>'); ?>

<div class="section-grid">
  <section class="panel">
    <div class="profile-header">
      <div class="profile-avatar"><?= e($s['initials']) ?></div>
      <div><h2><?= e($s['full_name']) ?></h2><p><?= e($s['section_label']) ?> · <?= e($s['enrollment_status']) ?></p>
        <div class="pill-row" style="margin-top:8px"><?= badge($st['label'], standing_tone($st['label'])) ?></div></div>
    </div>
    <div class="info-grid">
      <div class="info-item"><span>General average</span><strong><?= $s['average'] === null ? '—' : e($s['average']) ?></strong></div>
      <div class="info-item"><span>Attendance</span><strong><?= $s['attendance'] === null ? '—' : e($s['attendance']) . '%' ?></strong></div>
      <div class="info-item"><span>Missing work</span><strong><?= (int) $s['missing'] ?></strong></div>
      <div class="info-item"><span>Guardian</span><strong><?= e($s['guardian_name'] ?: '—') ?></strong></div>
      <div class="info-item"><span>Guardian phone</span><strong><?= e($s['guardian_phone'] ?: '—') ?></strong></div>
      <div class="info-item"><span>Email</span><strong><?= e($s['email'] ?: '—') ?></strong></div>
      <div class="info-item"><span>Phone</span><strong><?= e($s['phone'] ?: '—') ?></strong></div>
      <div class="info-item"><span>Birth date</span><strong><?= $s['birth_date'] ? e(fmt_date($s['birth_date'])) : '—' ?></strong></div>
      <div class="info-item"><span>Address</span><strong><?= e($s['address'] ?: '—') ?></strong></div>
    </div>
    <div class="panel-body"><div class="ai-notice"><?= icon('sparkles', 18) ?><div><h3>Why this standing?</h3>
      <ul class="reason-list"><?php foreach ($st['reasons'] as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul>
      <small>Rule-based decision support — a person decides what, if anything, to do.</small></div></div></div>
  </section>

  <section class="panel">
    <div class="panel-header"><div><h2>Gate &amp; classes</h2><p><?= e(fmt_date($date, 'l, M j')) ?></p></div></div>
    <div class="panel-body">
      <div class="row" style="margin-bottom:12px">
        <?php if ($gate): foreach ($gate as $g): ?><?= badge($g['event'] . ' · ' . fmt_time($g['log_time']), $g['event'] === 'Time-in' ? '' : 'neutral') ?><?php endforeach;
        else: ?><span class="muted small">No gate scan on this day.</span><?php endif; ?>
      </div>
      <?php foreach ($classes as $c): ?>
        <div class="activity-row"><div class="activity-info"><strong><?= e($c['subject_name']) ?></strong><p><?= e(fmt_time($c['period_start'])) ?> · <?= e($c['teacher_name']) ?></p></div>
          <?= $c['status'] ? badge($c['status'], status_tone($c['status'])) : '<span class="badge neutral">Not recorded</span>' ?></div>
      <?php endforeach; ?>
      <p style="margin-top:14px"><a class="text-link" href="<?= e(site_url('attendance?date=' . $date . '&detail=' . $s['id'])) ?>">Attendance details <?= icon('arrow-right', 12) ?></a></p>
    </div>
  </section>
</div>

<div class="dashboard-grid">
  <?php ui_panel('Academic record', 'School year ' . $sy['label']); ?>
    <?php if (!$card): ?><?= ui_empty('No grades have been encoded yet.', 'book-open') ?><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Subject</th><th class="right">Q1</th><th class="right">Q2</th><th class="right">Q3</th><th class="right">Q4</th><th class="right">Final</th><th>Remarks</th></tr></thead>
      <tbody>
      <?php foreach ($card as $r): ?>
        <tr><td><strong><?= e($r['subject']) ?></strong></td>
          <?php foreach (['q1','q2','q3','q4'] as $q): ?><td class="right num"><?= e($fmt($r[$q])) ?></td><?php endforeach; ?>
          <td class="right num"><strong><?= e($fmt($r['final_grade'])) ?></strong></td>
          <td><?= $r['remarks'] ? badge($r['remarks'], $r['final_grade'] !== null && $r['final_grade'] < $pass ? 'danger' : '') : '' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  <?php ui_panel_end(); ?>

  <?php ui_panel('Quarterly trend'); ?>
    <div class="panel-body chart">
      <?= chart_lines(['Q1', 'Q2', 'Q3', 'Q4'], [
          ['name' => 'Average', 'color' => 'var(--chart-green)', 'values' => array_map(function ($t) { return $t['average']; }, $trend)],
          ['name' => 'Mathematics', 'color' => 'var(--yellow)', 'values' => array_map(function ($t) { return $t['focus']; }, $trend)],
      ], ['label' => 'Quarterly trend', 'min' => 70, 'max' => 100]) ?>
      <?= chart_legend([['name' => 'Average', 'color' => 'var(--chart-green)'], ['name' => 'Mathematics', 'color' => 'var(--yellow)']]) ?>
    </div>
  <?php ui_panel_end(); ?>
</div>

<div class="dashboard-grid">
  <?php ui_panel('Activities', 'Latest in this section'); ?>
    <div class="panel-body">
      <?php if (!$activities): ?><?= ui_empty('No activities for this section yet.', 'calendar-days') ?>
      <?php else: foreach ($activities as $x): $a = $x['activity']; $v = $x['view']; ?>
        <div class="activity-row"><span class="activity-symbol"><?= icon('calendar-days', 15) ?></span>
          <div class="activity-info"><strong><a href="<?= e(site_url('activities/' . $a['id'])) ?>"><?= e($a['title']) ?></a></strong>
            <p><?= e($a['subject_name']) ?> · due <?= e(fmt_date($a['deadline'])) ?> · score <?= e($v['score']) ?></p></div>
          <?= badge($v['status'], status_tone($v['status'])) ?></div>
      <?php endforeach; endif; ?>
    </div>
  <?php ui_panel_end(); ?>

  <?php if ($can_notes): ?>
  <?php ui_panel('Support notes & plans', '', '<a class="text-link" href="' . e(site_url('guidance/' . $s['id'])) . '">Open guidance</a>'); ?>
    <div class="panel-body">
      <?php foreach ($plans as $p): ?>
        <div class="activity-row"><span class="activity-symbol <?= $p['is_done'] ? '' : 'yellow' ?>"><?= icon($p['is_done'] ? 'check' : 'clock', 15) ?></span>
          <div class="activity-info"><strong><?= e($p['title']) ?></strong><p>Due <?= e(fmt_date($p['due_date'])) ?><?= $p['detail'] ? ' · ' . e($p['detail']) : '' ?></p></div></div>
      <?php endforeach; foreach ($notes as $n): ?>
        <div class="activity-row"><span class="activity-symbol"><?= icon('shield-check', 15) ?></span>
          <div class="activity-info"><strong><?= e($n['author_name']) ?></strong><p><?= e($n['note']) ?></p></div><span class="activity-time"><?= e(time_ago($n['created_at'])) ?></span></div>
      <?php endforeach; if (!$plans && !$notes): ?><?= ui_empty('No notes or plans yet.', 'shield-check') ?><?php endif; ?>
    </div>
  <?php ui_panel_end(); ?>
  <?php endif; ?>
</div>

<?php if ($can_edit): ?>
<?php ui_panel('Edit contact details', 'Visible to administrators only'); ?>
  <div class="panel-body">
    <?php post_form('students/' . $s['id'] . '/update'); ?>
      <div class="form-grid three">
        <div class="form-group"><label class="form-label" for="email">Email</label><input class="field" id="email" name="email" type="email" maxlength="150" value="<?= e(old('email', $s['email'])) ?>"></div>
        <div class="form-group"><label class="form-label" for="phone">Phone</label><input class="field" id="phone" name="phone" maxlength="30" value="<?= e(old('phone', $s['phone'])) ?>"></div>
        <div class="form-group"><label class="form-label" for="birth_date">Birth date</label><input class="field" id="birth_date" name="birth_date" type="date" max="<?= e(today()) ?>" value="<?= e($s['birth_date']) ?>"></div>
        <div class="form-group"><label class="form-label" for="guardian_name">Guardian</label><input class="field" id="guardian_name" name="guardian_name" maxlength="120" value="<?= e(old('guardian_name', $s['guardian_name'])) ?>"></div>
        <div class="form-group"><label class="form-label" for="guardian_phone">Guardian phone</label><input class="field" id="guardian_phone" name="guardian_phone" maxlength="30" value="<?= e(old('guardian_phone', $s['guardian_phone'])) ?>"></div>
        <div class="form-group"><label class="form-label" for="address">Address</label><input class="field" id="address" name="address" maxlength="200" value="<?= e(old('address', $s['address'])) ?>"></div>
      </div>
      <button class="btn btn-primary" type="submit">Save changes</button>
    </form>
  </div>
<?php ui_panel_end(); endif; ?>
