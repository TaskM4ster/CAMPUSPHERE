<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/** Services - registrar queue for document requests (administrators). */
class Services extends Base_controller
{
    public function before_action()
    {
        parent::before_action();
        if ($this->user && $this->user['role'] !== 'Administrator') { $this->forbidden(); }
    }

    public function index()
    {
        $f = ['q' => mb_substr($this->query('q'), 0, 80), 'status' => $this->query('status')];
        $res = $this->requests->search($f, $this->page(), 15);
        $detail = null;
        if ($d = (int) $this->query('detail')) {
            $r = $this->requests->find($d);
            if ($r) { $detail = ['request' => $r, 'log' => $this->requests->timeline($d)]; }
        }
        $this->render('services/index', [
            'rows' => $res['rows'], 'total' => $res['total'], 'page' => $this->page(), 'per' => 15, 'f' => $f,
            'counts' => $this->requests->status_counts(), 'detail' => $detail,
            'types' => config_item('request_types'), 'statuses' => config_item('request_statuses'),
            'students' => $this->students->all($this->user),
        ], 'Student Services');
    }

    public function create()
    {
        $res = $this->requests->create($this->user, (int) $this->post('student_id'), $this->post('type'), $this->post('purpose'));
        if (!empty($res['ok'])) { $this->audit('request.create', 'service_request', $res['id'], $res['type']); }
        $this->respond($res, 'services', ['student_id', 'type', 'purpose']);
    }

    public function advance($id)
    {
        $res = $this->requests->advance($this->user, (int) $id);
        if (!empty($res['ok'])) {
            $r = $res['request'];
            $this->audit('request.advance', 'service_request', (int) $id, $r['code'] . ' → ' . $res['next']);
            $this->notices->publish(
                $r['type'] . ' — ' . $res['next'], "Your request {$r['code']} is now {$res['next']}." . ($res['next'] === 'Ready' ? ' You may claim it at the registrar\'s office.' : ''),
                'Services', ['roles' => ['Student'], 'student_id' => (int) $r['student_id']], $this->user['id']
            );
        }
        $this->respond($res, 'services?detail=' . (int) $id);
    }
}
