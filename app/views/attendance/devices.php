<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php ui_heading('Gate devices', 'Scanners that post Time-in / Time-out events to ' . config_item('system_name'), '<a class="btn" href="' . e(site_url('attendance')) . '">Back to attendance</a>'); ?>

<?php if ($token): ?>
  <div class="callout key" role="alert">
    <strong><?= icon('lock', 15) ?> Token for “<?= e($token['name']) ?>”</strong>
    <p class="small muted">Copy it now — only a SHA-256 hash is stored, so it cannot be shown again. Store it in the scanner's secure configuration.</p>
    <code><?= e($token['token']) ?></code>
  </div>
<?php endif; ?>

<div class="dashboard-grid">
  <?php ui_panel('Registered devices'); ?>
    <?php if (!$devices): ?><?= ui_empty('No scanners registered yet.', 'scan-line') ?><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Device</th><th>Location</th><th>Last seen</th><th class="right">Scans today</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($devices as $d): ?>
        <tr><td><strong><?= e($d['name']) ?></strong></td><td><?= e($d['location'] ?: '—') ?></td><td><?= $d['last_seen_at'] ? e(time_ago($d['last_seen_at'])) : 'Never' ?></td>
          <td class="right num"><?= (int) $d['scans_today'] ?></td><td><?= $d['is_active'] ? badge('Active') : badge('Disabled', 'neutral') ?></td>
          <td class="right"><?php post_form('attendance/devices/' . $d['id'] . '/toggle', 'class="inline"'); ?><button class="btn btn-sm" type="submit"><?= $d['is_active'] ? 'Disable' : 'Enable' ?></button></form></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  <?php ui_panel_end(); ?>

  <div>
    <?php ui_panel('Register a device'); ?>
      <div class="panel-body">
        <?php post_form('attendance/devices'); ?>
          <div class="form-group"><label class="form-label" for="d-name">Name</label><input class="field" id="d-name" name="name" maxlength="80" required value="<?= e(old('name')) ?>" placeholder="Main gate reader"></div>
          <div class="form-group"><label class="form-label" for="d-loc">Location</label><input class="field" id="d-loc" name="location" maxlength="120" value="<?= e(old('location')) ?>" placeholder="Gate 1"></div>
          <button class="btn btn-primary" type="submit"><?= icon('plus', 14) ?> Register</button>
        </form>
      </div>
    <?php ui_panel_end(); ?>
    <?php ui_panel('Integration'); ?>
      <div class="panel-body small">
        <p class="muted">Scanners authenticate with their token. The server clock is authoritative.</p>
        <code class="token">POST <?= e($endpoint) ?></code>
        <code class="token">Authorization: Bearer &lt;token&gt;
Content-Type: application/json

{"student_no": "ANHS-2026-001", "event": "Time-in"}</code>
        <p class="muted">“event” is optional (first scan of the day is Time-in, the next is Time-out). Repeated scans are ignored. Responses: 200 ok · 401 bad token · 404 unknown learner · 422 invalid.</p>
      </div>
    <?php ui_panel_end(); ?>
  </div>
</div>
