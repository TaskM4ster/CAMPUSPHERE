<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$flow = config_item('request_statuses');
?>
<?php ui_heading('My requests', 'Request certificates and records from the registrar'); ?>

<div class="dashboard-grid">
  <?php ui_panel('Request history'); ?>
    <?php if (!$requests): ?><?= ui_empty('You have not requested anything yet.', 'files') ?><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Code</th><th>Document</th><th>Purpose</th><th>Status</th><th>Requested</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($requests as $r): ?>
        <tr><td class="num"><?= e($r['code']) ?></td><td><strong><?= e($r['type']) ?></strong></td><td class="wrap"><?= e($r['purpose']) ?></td>
          <td><?= badge($r['status'], status_tone($r['status'])) ?></td><td><?= e(fmt_date($r['created_at'])) ?></td>
          <td class="right"><a class="btn btn-sm" href="<?= e(site_url('student/requests?detail=' . $r['id'])) ?>">Track</a></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  <?php ui_panel_end(); ?>

  <div>
    <?php if ($timeline): $cur = array_search($timeline['request']['status'], $flow, true); ?>
    <?php ui_panel($timeline['request']['type'], $timeline['request']['code'], '<a class="btn btn-sm" href="' . e(site_url('student/requests')) . '">Close</a>', 'detail'); ?>
      <div class="panel-body">
        <div class="timeline">
          <?php foreach ($flow as $i => $s): $done = $i <= $cur; ?>
            <div class="timeline-step <?= $done ? 'done' : '' ?>"><strong><?= e($s) ?></strong>
              <small><?= isset($timeline['log'][$s]) ? e(fmt_datetime($timeline['log'][$s])) : 'Waiting' ?></small></div>
          <?php endforeach; ?>
        </div>
        <?php if ($timeline['request']['status'] === 'Ready'): ?><p class="flash success"><?= icon('circle-check', 16) ?> Ready — claim it at the registrar's office.</p><?php endif; ?>
      </div>
    <?php ui_panel_end(); ?>
    <?php endif; ?>

    <?php ui_panel('New request', 'Processing time depends on the document'); ?>
      <div class="panel-body">
        <?php post_form('student/requests'); ?>
          <div class="form-group"><label class="form-label" for="type">Document</label>
            <select class="field" id="type" name="type" required><?= ui_options(array_combine($types, $types), old('type'), 'Choose a document') ?></select></div>
          <div class="form-group"><label class="form-label" for="purpose">Purpose</label>
            <textarea class="field" id="purpose" name="purpose" maxlength="255" required placeholder="e.g. Scholarship application"><?= e(old('purpose')) ?></textarea></div>
          <button class="btn btn-primary" type="submit"><?= icon('plus', 14) ?> Submit request</button>
        </form>
      </div>
    <?php ui_panel_end(); ?>
  </div>
</div>
