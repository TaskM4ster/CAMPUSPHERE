<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$st = $s['standing'];
?>
<?php ui_heading($s['full_name'], $s['student_no'] . ' · ' . $s['section_label'], '<a class="btn" href="' . e(site_url('guidance')) . '">Back</a><a class="btn" href="' . e(site_url('students/' . $s['id'])) . '">Full profile</a>'); ?>

<div class="metric-grid">
  <?= ui_metric('Standing', 'shield-check', $st['label'], '', $st['level'] ? ($st['level'] === 2 ? 'danger' : 'yellow') : '') ?>
  <?= ui_metric('General average', 'book-open', $s['average'] === null ? '—' : $s['average']) ?>
  <?= ui_metric('Attendance', 'user-check', $s['attendance'] === null ? '—' : $s['attendance'] . '%') ?>
  <?= ui_metric('Missing work', 'calendar-days', num($s['missing'])) ?>
</div>

<div class="dashboard-grid">
  <div>
    <?php ui_panel('Why this indicator?'); ?>
      <div class="panel-body"><ul class="reason-list" style="font-size:13px"><?php foreach ($st['reasons'] as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul>
        <?php if ($weak): ?><p class="small" style="margin-top:12px"><strong>Subjects below <?= (int) config_item('support_grade_below') ?>:</strong> <?= e(implode(', ', array_map(function ($w) { return $w['subject'] . ' (' . rtrim(rtrim(number_format((float) $w['final_grade'], 1), '0'), '.') . ')'; }, $weak))) ?></p><?php endif; ?>
        <div class="subtle-note" style="margin-top:14px"><?= icon('info', 13) ?> <?= e($st['disclaimer']) ?></div></div>
    <?php ui_panel_end(); ?>

    <?php ui_panel('Quarterly trend'); ?>
      <div class="panel-body chart"><?= chart_lines(['Q1', 'Q2', 'Q3', 'Q4'], [
        ['name' => 'Average', 'color' => 'var(--chart-green)', 'values' => array_map(function ($t) { return $t['average']; }, $trend)],
        ['name' => 'Mathematics', 'color' => 'var(--yellow)', 'values' => array_map(function ($t) { return $t['focus']; }, $trend)]], ['min' => 70, 'max' => 100, 'label' => 'Quarterly trend']) ?>
        <?= chart_legend([['name' => 'Average', 'color' => 'var(--chart-green)'], ['name' => 'Mathematics', 'color' => 'var(--yellow)']]) ?></div>
    <?php ui_panel_end(); ?>
  </div>

  <div>
    <?php ui_panel('Support plan', count($plans) . ' item' . (count($plans) === 1 ? '' : 's')); ?>
      <div class="panel-body">
        <?php foreach ($plans as $p): ?>
          <div class="activity-row"><span class="activity-symbol <?= $p['is_done'] ? '' : 'yellow' ?>"><?= icon($p['is_done'] ? 'check' : 'clock', 15) ?></span>
            <div class="activity-info"><strong><?= e($p['title']) ?></strong><p>Due <?= e(fmt_date($p['due_date'])) ?><?= $p['detail'] ? ' · ' . e($p['detail']) : '' ?></p></div>
            <?php if ($can_write): ?><?php post_form('guidance/' . $s['id'] . '/plan/' . $p['id'] . '/toggle', 'class="inline"'); ?><button class="btn btn-sm" type="submit"><?= $p['is_done'] ? 'Reopen' : 'Mark done' ?></button></form><?php endif; ?></div>
        <?php endforeach; if (!$plans): ?><?= ui_empty('No interventions yet.', 'clipboard-check') ?><?php endif; ?>
        <?php if ($can_write): ?>
          <?php post_form('guidance/' . $s['id'] . '/intervention', 'style="margin-top:16px"'); ?>
            <div class="form-grid">
              <div class="form-group"><label class="form-label" for="i-title">Intervention</label><input class="field" id="i-title" name="title" maxlength="160" required value="<?= e(old('title')) ?>" placeholder="Tutoring in Mathematics"></div>
              <div class="form-group"><label class="form-label" for="i-due">Due</label><input class="field" id="i-due" name="due_date" type="date" required value="<?= e(old('due_date')) ?>"></div>
              <div class="form-group full"><label class="form-label" for="i-detail">Details</label><input class="field" id="i-detail" name="detail" maxlength="255" value="<?= e(old('detail')) ?>"></div>
            </div>
            <button class="btn btn-primary" type="submit"><?= icon('plus', 14) ?> Add intervention</button>
          </form>
        <?php endif; ?>
      </div>
    <?php ui_panel_end(); ?>

    <?php ui_panel('Standing record', 'Notes, commendations, consultations and support cases'); ?>
      <div class="panel-body">
        <?php foreach ($notes as $n): ?>
          <div class="activity-row"><span class="activity-symbol <?= $n['kind'] === 'Commendation' ? '' : ($n['kind'] === 'Note' ? '' : 'yellow') ?>"><?= icon($n['kind'] === 'Commendation' ? 'sparkles' : ($n['kind'] === 'Parent consultation' ? 'users' : 'shield-check'), 15) ?></span>
            <div class="activity-info"><strong><?= e($n['author_name']) ?></strong> <?= $n['kind'] !== 'Note' ? badge($n['kind'], $n['kind'] === 'Commendation' ? '' : 'neutral') : '' ?><p style="white-space:pre-wrap"><?= e($n['note']) ?></p></div><span class="activity-time"><?= e(time_ago($n['created_at'])) ?></span></div>
        <?php endforeach; if (!$notes): ?><?= ui_empty('Nothing recorded yet.', 'shield-check') ?><?php endif; ?>
        <?php if ($can_write): ?>
          <?php post_form('guidance/' . $s['id'] . '/note', 'style="margin-top:16px"'); ?>
            <div class="form-group"><label class="form-label" for="g-kind">Type</label><select class="field" id="g-kind" name="kind"><?= ui_options(array_combine(Guidance_model::KINDS, Guidance_model::KINDS), old('kind', 'Note')) ?></select></div>
            <div class="form-group"><label class="form-label" for="g-note">Details</label><textarea class="field" id="g-note" name="note" maxlength="2000" required><?= e(old('note')) ?></textarea></div>
            <button class="btn btn-primary" type="submit">Save record</button>
          </form>
        <?php endif; ?>
      </div>
    <?php ui_panel_end(); ?>
  </div>
</div>
