<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php ui_heading('Student services', 'Registrar queue for certificates and records',
    '<button class="btn btn-primary" type="button" data-open="new-request">' . icon('plus', 14) . ' Log a request</button>'); ?>

<div class="metric-grid">
  <?php $icons = ['Pending' => 'clock', 'Processing' => 'files', 'Ready' => 'circle-check', 'Completed' => 'check']; foreach ($statuses as $s): ?>
    <?= ui_metric($s, $icons[$s], num($counts[$s]), '<a class="text-link" href="' . e(site_url('services?status=' . $s)) . '">Show</a>', $s === 'Pending' ? 'yellow' : '') ?>
  <?php endforeach; ?>
</div>

<?php ui_filters_open('services'); ?>
  <label class="search-field"><?= icon('search', 15) ?><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search code, learner or document" aria-label="Search requests"></label>
  <select name="status" aria-label="Status" data-autosubmit><?= ui_options(array_combine($statuses, $statuses), $f['status'], 'All statuses') ?></select>
  <button class="btn" type="submit">Search</button>
</form>

<?php if ($detail): $r = $detail['request']; $cur = array_search($r['status'], $statuses, true); ?>
<?php ui_panel($r['type'] . ' · ' . $r['code'], $r['student_name'] . ' · ' . $r['student_no'], '<a class="btn btn-sm" href="' . e(site_url('services')) . '">Close</a>', 'detail mb'); ?>
  <div class="panel-body">
    <p><strong>Purpose:</strong> <?= e($r['purpose']) ?></p>
    <div class="timeline">
      <?php foreach ($statuses as $i => $s): ?>
        <div class="timeline-step <?= $i <= $cur ? 'done' : '' ?>"><strong><?= e($s) ?></strong><small><?= isset($detail['log'][$s]) ? e(fmt_datetime($detail['log'][$s])) : 'Waiting' ?></small></div>
      <?php endforeach; ?>
    </div>
    <?php if ($cur < count($statuses) - 1): ?>
      <?php post_form('services/' . $r['id'] . '/advance'); ?><button class="btn btn-primary" type="submit">Move to “<?= e($statuses[$cur + 1]) ?>” <?= icon('arrow-right', 14) ?></button></form>
    <?php else: ?><p class="muted small">This request is complete.</p><?php endif; ?>
  </div>
<?php ui_panel_end(); endif; ?>

<section class="panel">
  <?php if (!$rows): ?><?= ui_empty('No requests match.', 'files') ?><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Code</th><th>Learner</th><th>Document</th><th>Purpose</th><th>Status</th><th>Requested</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td class="num"><?= e($r['code']) ?></td><td><?= ui_student($r['student_name'], $r['student_no'], null, site_url('students/' . $r['student_id'])) ?></td>
        <td><strong><?= e($r['type']) ?></strong></td><td class="wrap"><?= e($r['purpose']) ?></td>
        <td><?= badge($r['status'], status_tone($r['status'])) ?></td><td><?= e(fmt_date($r['created_at'])) ?></td>
        <td class="right"><a class="btn btn-sm" href="<?= e(site_url('services?detail=' . $r['id'])) ?>">Open</a></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= pager($total, $page, $per) ?>
  <?php endif; ?>
</section>

<dialog class="modal" id="new-request" <?= old('type') !== '' ? 'data-autoopen' : '' ?>>
  <?php post_form('services'); ?>
    <div class="modal-header"><h2>Log a request</h2><button class="icon-btn" type="button" data-close aria-label="Close"><?= icon('x', 18) ?></button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label" for="r-student">Learner</label>
        <select class="field" id="r-student" name="student_id" required><option value="">Choose a learner</option>
          <?php foreach ($students as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($s['id'], old('student_id')) ?>><?= e($s['full_name'] . ' · ' . $s['student_no']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label class="form-label" for="r-type">Document</label>
        <select class="field" id="r-type" name="type" required><?= ui_options(array_combine($types, $types), old('type'), 'Choose a document') ?></select></div>
      <div class="form-group"><label class="form-label" for="r-purpose">Purpose</label><input class="field" id="r-purpose" name="purpose" maxlength="255" required value="<?= e(old('purpose')) ?>"></div>
    </div>
    <div class="modal-footer"><button class="btn" type="button" data-close>Cancel</button><button class="btn btn-primary" type="submit">Submit</button></div>
  </form>
</dialog>
