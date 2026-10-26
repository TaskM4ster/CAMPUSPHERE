<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/** Login - sign in / sign out. */
class Login extends Base_controller
{
    public function login()
    {
        if ($this->is_post()) {
            $res = $this->auth->attempt($this->post('username'), (string) ($_POST['password'] ?? ''), $_SERVER['REMOTE_ADDR'] ?? '');
            if (!$res['ok']) {
                $this->audit->log(['id' => null, 'username' => mb_substr($this->post('username'), 0, 60)], 'login.failed', 'auth', null, $res['error']);
                $this->flash('error', $res['error']);
                $this->remember_input(['username']);
                go('login');
            }
            $user = $this->auth->user();
            $this->audit->log($user, 'login', 'auth', $user['id']);
            $intended = (string) $this->session->userdata('intended');
            $this->session->unset_userdata('intended');
            if ($intended !== '' && $intended[0] === '/' && strpos($intended, '//') !== 0 && $this->auth->can_access($intended)) {
                go(ltrim($intended, '/'));
            }
            go(ltrim($this->auth->home(), '/'));
        }

        $GLOBALS['__old'] = $this->session->flashdata('old') ?: [];
        $flash = $this->session->flashdata('flash');
        $this->call->view('auth/login', [
            'school'  => config_item('school_name'),
            'system'  => config_item('system_name'),
            'tagline' => config_item('school_tagline'),
            'flash'   => is_array($flash) ? $flash : null,
            'demo'    => config_item('show_demo_accounts') ? config_item('demo_accounts') : [],
            'demo_password' => config_item('demo_password'),
        ]);
    }

    public function logout()
    {
        if ($this->user) { $this->audit->log($this->user, 'logout', 'auth', $this->user['id']); }
        $this->auth->logout();
        $this->flash('info', 'You have been signed out.');
        go('login');
    }
}
