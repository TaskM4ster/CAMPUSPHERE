<?php defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed'); ?>
<?php ui_heading('Change password', $forced ? 'You must choose a new password before continuing.' : 'Choose a new password for your account.'); ?>
<?php if ($forced): ?><div class="flash warning"><?= icon('triangle-alert', 16) ?><div class="grow">Your account uses a temporary password. Set your own to continue.</div></div><?php endif; ?>
<section class="panel" style="max-width:520px">
  <div class="panel-body">
    <?php post_form('account/password'); ?>
      <div class="form-group"><label class="form-label" for="current">Current password</label><input class="field" id="current" name="current" type="password" required autocomplete="current-password"></div>
      <div class="form-group"><label class="form-label" for="new">New password</label><input class="field" id="new" name="new" type="password" required minlength="<?= (int) $min ?>" autocomplete="new-password">
        <div class="field-hint">At least <?= (int) $min ?> characters, with letters and numbers.</div></div>
      <div class="form-group"><label class="form-label" for="confirm">Confirm new password</label><input class="field" id="confirm" name="confirm" type="password" required minlength="<?= (int) $min ?>" autocomplete="new-password"></div>
      <button class="btn btn-primary" type="submit">Update password</button>
    </form>
  </div>
</section>
