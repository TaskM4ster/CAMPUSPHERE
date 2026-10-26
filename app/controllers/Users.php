<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/** Users - sign-in account administration (administrators). */
class Users extends Base_controller
{
    public function before_action()
    {
        parent::before_action();
        if ($this->user && $this->user['role'] !== 'Administrator') { $this->forbidden(); }
    }

    public function index()
    {
        $f = ['q' => mb_substr($this->query('q'), 0, 80), 'role' => $this->query('role'), 'active' => $this->query('active')];
        $res = $this->accounts->search($f, $this->page(), 15);
        $this->render('admin/users', [
            'rows' => $res['rows'], 'total' => $res['total'], 'page' => $this->page(), 'per' => 15, 'f' => $f,
            'roles' => User_model::ROLES, 'counts' => $this->accounts->role_counts(),
            'temp' => $this->session->flashdata('temp_credentials'),
        ], 'Users');
    }

    public function create()
    {
        $res = $this->accounts->create([
            'username' => $this->post('username'), 'full_name' => $this->post('full_name'), 'email' => $this->post('email'),
            'role' => $this->post('role'),
        ]);
        if (!empty($res['ok'])) {
            $this->audit('user.create', 'user', $res['id'], $res['username']);
            $this->session->set_flashdata('temp_credentials', ['username' => $res['username'], 'password' => $res['temp_password']]);
        }
        $this->respond($res, 'users', ['username', 'full_name', 'email', 'role']);
    }

    public function toggle($id)
    {
        $user = $this->accounts->find((int) $id);
        if (!$user) { $this->not_found('account'); }
        $res = $this->accounts->set_active((int) $id, !(int) $user['is_active'], $this->user['id']);
        if (!empty($res['ok'])) { $this->audit($user['is_active'] ? 'user.deactivate' : 'user.activate', 'user', (int) $id, $user['username']); }
        $this->respond($res, 'users');
    }

    public function reset($id)
    {
        $res = $this->accounts->reset_password((int) $id);
        if (!empty($res['ok'])) {
            $this->audit('user.reset_password', 'user', (int) $id, $res['user']['username']);
            $this->session->set_flashdata('temp_credentials', ['username' => $res['user']['username'], 'password' => $res['temp_password']]);
        }
        $this->respond($res, 'users');
    }
}
