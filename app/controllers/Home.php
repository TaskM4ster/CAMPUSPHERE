<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/** Home - role-aware landing redirect. */
class Home extends Base_controller
{
    public function index()
    {
        go($this->user ? ltrim($this->auth->home(), '/') : 'login');
    }
}
