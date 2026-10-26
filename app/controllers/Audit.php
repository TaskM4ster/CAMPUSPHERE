<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/** Audit - read-only view of the activity trail (administrators). */
class Audit extends Base_controller
{
    public function before_action()
    {
        parent::before_action();
        if ($this->user && $this->user['role'] !== 'Administrator') { $this->forbidden(); }
    }

    public function index()
    {
        $f = ['q' => mb_substr($this->query('q'), 0, 80), 'entity' => $this->query('entity')];
        $res = $this->audit->search($f, $this->page(), 25);
        $this->render('admin/audit', [
            'rows' => $res['rows'], 'total' => $res['total'], 'page' => $this->page(), 'per' => 25, 'f' => $f,
            'entities' => $this->audit->entities(),
        ], 'Audit Trail');
    }
}
