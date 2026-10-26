<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/** Account - own profile summary and password change. */
class Account extends Base_controller
{
    public function index()
    {
        $profile = null;
        if ($this->user['role'] === 'Student') { $profile = $this->students->find($this->user['student_id']); }
        elseif ($this->user['role'] === 'Teacher') { $profile = $this->teachers->find($this->user['teacher_id']); }
        $this->render('admin/account', ['profile' => $profile, 'forced' => false], 'My Account');
    }

    public function password()
    {
        if ($this->is_post()) {
            $res = $this->accounts->change_password(
                $this->user['id'], (string) ($_POST['current'] ?? ''), (string) ($_POST['new'] ?? ''), (string) ($_POST['confirm'] ?? '')
            );
            if ($res['ok']) {
                $this->audit('password.change', 'user', $this->user['id']);
                $this->session->regenerate_on_login(true);
                $this->flash('success', $res['message']);
                go(ltrim($this->auth->home(), '/'));
            }
            $this->flash('error', $res['error']);
            go('account/password');
        }
        $this->render('admin/password', [
            'forced' => (int) $this->user['must_change_password'] === 1,
            'min'    => (int) config_item('password_min_length'),
        ], 'Change Password');
    }
}
