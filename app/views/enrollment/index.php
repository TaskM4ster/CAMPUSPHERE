<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php ui_heading('Enrollment', 'Applications for the current school year',
    '<button class="btn btn-primary" type="button" data-open="new-enrollment">' . icon('plus', 14) . ' New enrollment</button>'); ?>

<?php if ($temp): ?>
  <div class="callout key" role="alert">
    <strong><?= icon('lock', 15) ?> Student account created for <?= e($temp['name']) ?></strong>
    <p class="small muted">Share these sign-in details with the learner now. The temporary password is shown only once and must be changed at first sign-in.</p>
    <code>Username: <?= e($temp['username']) ?></code><code>Temporary password: <?= e($temp['password']) ?></code>
  </div>
<?php endif; ?>

<div class="metric-grid">
  <?= ui_metric('Enrolled learners', 'users', num($counts['total'])) ?>
  <?= ui_metric('Re-enrolled', 'user-check', num($counts['returning']), 'Approved returning learners') ?>
  <?= ui_metric('New enrollees', 'plus', num($counts['new']), 'Approved new learners') ?>
  <?= ui_metric('Pending review', 'clock', num($counts['pending']), 'Awaiting a decision', 'yellow') ?>
</div>

<?php ui_filters_open('enrollment'); ?>
  <label class="search-field"><?= icon('search', 15) ?><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search applicant or code" aria-label="Search enrollment"></label>
  <select name="status" aria-label="Status" data-autosubmit><?= ui_options(['Pending' => 'Pending', 'Approved' => 'Approved', 'Rejected' => 'Rejected'], $f['status'], 'All statuses') ?></select>
  <button class="btn" type="submit">Search</button>
</form>

<section class="panel">
  <?php if (!$rows): ?><?= ui_empty('No enrollment records match.', 'clipboard-list') ?><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Applicant</th><th>Code</th><th>Type</th><th>Section</th><th>Documents</th><th>Status</th><th class="right">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= ui_student($r['applicant_name'], $r['student_no'] ?: 'New applicant', $r['initials']) ?></td>
        <td class="num"><?= e($r['code']) ?></td>
        <td><?= e($r['type']) ?></td>
        <td><?= e(grade_section($r['grade'], $r['section_name'])) ?></td>
        <td><?= badge($r['docs_status'], status_tone($r['docs_status'])) ?></td>
        <td><?= badge($r['status'], status_tone($r['status'])) ?></td>
        <td class="right nowrap">
          <?php if ($r['status'] === 'Pending'): ?>
            <?php if ($r['docs_status'] !== 'Verified'): ?>
              <?php post_form('enrollment/' . $r['id'] . '/verify', 'class="inline"'); ?><button class="btn btn-sm" type="submit"><?= icon('file-check', 13) ?> Verify docs</button></form>
            <?php else: ?>
              <?php post_form('enrollment/' . $r['id'] . '/approve', 'class="inline" data-confirm="Approve ' . e($r['applicant_name']) . '?"'); ?><button class="btn btn-sm btn-primary" type="submit"><?= icon('check', 13) ?> Approve</button></form>
            <?php endif; ?>
            <button class="btn btn-sm btn-danger" type="button" data-open="reject-<?= (int) $r['id'] ?>">Reject</button>
          <?php else: ?>
            <span class="muted small"><?= $r['remarks'] ? e($r['remarks']) : '—' ?></span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= pager($total, $page, $per) ?>
  <?php endif; ?>
</section>

<?php foreach ($rows as $r): if ($r['status'] !== 'Pending') { continue; } ?>
<dialog class="modal" id="reject-<?= (int) $r['id'] ?>">
  <?php post_form('enrollment/' . $r['id'] . '/reject'); ?>
    <div class="modal-header"><h2>Reject <?= e($r['code']) ?></h2><button class="icon-btn" type="button" data-close aria-label="Close"><?= icon('x', 18) ?></button></div>
    <div class="modal-body"><p class="muted small"><?= e($r['applicant_name']) ?> will be marked Rejected. Give a short reason for the record.</p>
      <div class="form-group"><label class="form-label" for="reason-<?= (int) $r['id'] ?>">Reason</label>
      <input class="field" id="reason-<?= (int) $r['id'] ?>" name="reason" maxlength="255" required></div></div>
    <div class="modal-footer"><button class="btn" type="button" data-close>Cancel</button><button class="btn btn-danger" type="submit">Reject application</button></div>
  </form>
</dialog>
<?php endforeach; ?>

<dialog class="modal wide" id="new-enrollment" <?= old('type') !== '' ? 'data-autoopen' : '' ?>>
  <?php post_form('enrollment'); ?>
    <div class="modal-header"><h2>New enrollment</h2><button class="icon-btn" type="button" data-close aria-label="Close"><?= icon('x', 18) ?></button></div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="form-group"><label class="form-label" for="e-type">Type</label>
          <select class="field" id="e-type" name="type" required><?= ui_options(['New enrollment' => 'New enrollment', 'Re-enrollment' => 'Re-enrollment'], old('type'), 'Choose a type') ?></select></div>
        <div class="form-group"><label class="form-label" for="e-section">Section</label>
          <select class="field" id="e-section" name="section_id" required><?= ui_sections_options($sections, old('section_id'), 'Choose a section') ?></select></div>
        <div class="form-group"><label class="form-label" for="e-student">Returning learner <span class="muted">(re-enrollment only)</span></label>
          <select class="field" id="e-student" name="student_id"><option value="">—</option>
            <?php foreach ($returning as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($s['id'], old('student_id')) ?>><?= e($s['full_name'] . ' · ' . $s['student_no']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label class="form-label" for="e-name">Applicant name <span class="muted">(new enrollment)</span></label>
          <input class="field" id="e-name" name="name" maxlength="120" value="<?= e(old('name')) ?>"></div>
        <div class="form-group"><label class="form-label" for="e-email">Email</label><input class="field" id="e-email" name="email" type="email" maxlength="150" value="<?= e(old('email')) ?>"></div>
        <div class="form-group"><label class="form-label" for="e-birth">Date of birth</label><input class="field" id="e-birth" name="birth_date" type="date" max="<?= e(today()) ?>" value="<?= e(old('birth_date')) ?>"></div>
        <div class="form-group"><label class="form-label" for="e-guardian">Guardian</label><input class="field" id="e-guardian" name="guardian_name" maxlength="120" value="<?= e(old('guardian_name')) ?>"></div>
        <div class="form-group"><label class="form-label" for="e-gphone">Guardian phone</label><input class="field" id="e-gphone" name="guardian_phone" maxlength="30" value="<?= e(old('guardian_phone')) ?>"></div>
      </div>
      <p class="field-hint">Approval requires verified documents and room in the section (capacity <?= (int) config_item('section_capacity') ?>). A new learner receives a student account with a one-time temporary password.</p>
    </div>
    <div class="modal-footer"><button class="btn" type="button" data-close>Cancel</button><button class="btn btn-primary" type="submit">Save as pending</button></div>
  </form>
</dialog>
