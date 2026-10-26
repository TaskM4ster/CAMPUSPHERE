<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php ui_heading('User accounts', 'Who can sign in to ' . config_item('system_name'),
    '<button class="btn btn-primary" type="button" data-open="new-user">' . icon('plus', 14) . ' New account</button>'); ?>

<?php if ($temp): ?>
  <div class="callout key" role="alert">
    <strong><?= icon('lock', 15) ?> Temporary credentials</strong>
    <p class="small muted">Shown once. The user must choose a new password at first sign-in.</p>
    <code>Username: <?= e($temp['username']) ?></code><code>Temporary password: <?= e($temp['password']) ?></code>
  </div>
<?php endif; ?>

<div class="metric-grid">
  <?php $ic = ['Administrator' => 'user-cog', 'Teacher' => 'book-open', 'Student' => 'users', 'Guidance' => 'shield-check']; foreach ($counts as $role => $n): ?>
    <?= ui_metric($role . 's', $ic[$role], num($n), 'Active accounts') ?>
  <?php endforeach; ?>
</div>

<?php ui_filters_open('users'); ?>
  <label class="search-field"><?= icon('search', 15) ?><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search username, name or email" aria-label="Search accounts"></label>
  <select name="role" aria-label="Role" data-autosubmit><?= ui_options(array_combine($roles, $roles), $f['role'], 'All roles') ?></select>
  <select name="active" aria-label="Status" data-autosubmit><?= ui_options(['1' => 'Active', '0' => 'Deactivated'], $f['active'], 'Any status') ?></select>
  <button class="btn" type="submit">Search</button>
</form>

<section class="panel">
  <?php if (!$rows): ?><?= ui_empty('No accounts match.', 'users') ?><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Account</th><th>Role</th><th>Status</th><th>Last sign-in</th><th class="right">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= ui_student($r['full_name'], $r['username'] . ($r['email'] ? ' · ' . $r['email'] : ''), $r['initials']) ?></td>
        <td><?= badge($r['role'], 'neutral') ?></td>
        <td><?= $r['is_active'] ? badge('Active') : badge('Deactivated', 'danger') ?><?= $r['must_change_password'] ? ' ' . badge('Must change password', 'warning') : '' ?></td>
        <td><?= $r['last_login_at'] ? e(time_ago($r['last_login_at'])) : 'Never' ?></td>
        <td class="right nowrap">
          <?php post_form('users/' . $r['id'] . '/reset', 'class="inline" data-confirm="Issue a new temporary password for ' . e($r['username']) . '?"'); ?><button class="btn btn-sm" type="submit">Reset password</button></form>
          <?php if ((int) $r['id'] !== (int) $user['id']): ?>
            <?php post_form('users/' . $r['id'] . '/toggle', 'class="inline" data-confirm="' . ($r['is_active'] ? 'Deactivate' : 'Activate') . ' ' . e($r['username']) . '?"'); ?><button class="btn btn-sm <?= $r['is_active'] ? 'btn-danger' : '' ?>" type="submit"><?= $r['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
          <?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= pager($total, $page, $per) ?>
  <?php endif; ?>
</section>

<dialog class="modal" id="new-user" <?= old('username') !== '' ? 'data-autoopen' : '' ?>>
  <?php post_form('users'); ?>
    <div class="modal-header"><h2>New account</h2><button class="icon-btn" type="button" data-close aria-label="Close"><?= icon('x', 18) ?></button></div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="form-group"><label class="form-label" for="u-name">Full name</label><input class="field" id="u-name" name="full_name" maxlength="120" required value="<?= e(old('full_name')) ?>"></div>
        <div class="form-group"><label class="form-label" for="u-user">Username</label><input class="field" id="u-user" name="username" maxlength="40" required pattern="[A-Za-z0-9._-]{3,40}" autocapitalize="none" value="<?= e(old('username')) ?>"></div>
        <div class="form-group"><label class="form-label" for="u-email">Email</label><input class="field" id="u-email" name="email" type="email" maxlength="150" value="<?= e(old('email')) ?>"></div>
        <div class="form-group"><label class="form-label" for="u-role">Role</label>
          <select class="field" id="u-role" name="role" required><?= ui_options(array_combine(['Administrator', 'Teacher', 'Guidance'], ['Administrator', 'Teacher', 'Guidance']), old('role'), 'Choose a role') ?></select></div>
      </div>
      <p class="field-hint">Student accounts are created automatically when an enrollment is approved. A one-time temporary password is generated and shown after saving.</p>
    </div>
    <div class="modal-footer"><button class="btn" type="button" data-close>Cancel</button><button class="btn btn-primary" type="submit">Create account</button></div>
  </form>
</dialog>
