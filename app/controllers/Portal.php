<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/** Portal - the student's own dashboard, grades and service requests. */
class Portal extends Base_controller
{
    private $me = null;

    public function before_action()
    {
        parent::before_action();
        if ($this->user && $this->user['role'] === 'Student') {
            $this->me = $this->students->find($this->user['student_id']);
        }
    }

    private function me()
    {
        if (!$this->me) { $this->forbidden(); }
        return $this->me;
    }

    public function index()
    {
        $me = $this->me();
        $date = $this->gate->latest_date() ?: today();
        $this->render('portal/index', [
            'me'        => $me,
            'date'      => $date,
            'gate'      => $this->gate->logs_for($me['id'], $date),
            'classes'   => $me['section_id'] ? $this->attendance->student_day($me['id'], $me['section_id'], $date) : [],
            'pending'   => $this->activities->pending_for_student($me['id']),
            'activities' => array_slice($this->activities->list_for($this->user), 0, 4),
            'weak'      => $this->grades->weak_subjects($me['id'], $this->school->sy()['id']),
            'notices'   => $this->notices->recent($this->user, 4),
            'requests'  => array_slice($this->requests->for_student($me['id']), 0, 3),
        ], 'My Dashboard');
    }

    public function grades()
    {
        $me = $this->me();
        $sy = $this->school->sy();
        $this->render('portal/grades', [
            'me'     => $me,
            'sy'     => $sy,
            'card'   => $this->grades->report_card($me['id'], $sy['id']),
            'trend'  => $this->grades->trend($me['id'], $sy['id']),
            'weak'   => $this->grades->weak_subjects($me['id'], $sy['id']),
        ], 'My Grades');
    }

    public function requests()
    {
        $me = $this->me();
        $this->render('portal/requests', [
            'me'       => $me,
            'requests' => $this->requests->for_student($me['id']),
            'types'    => config_item('request_types'),
            'timeline' => ($id = (int) $this->query('detail')) ? $this->timeline_for($me['id'], $id) : null,
        ], 'My Requests');
    }

    private function timeline_for($student_id, $id)
    {
        $r = $this->requests->find($id);
        if (!$r || (int) $r['student_id'] !== (int) $student_id) { return null; }
        return ['request' => $r, 'log' => $this->requests->timeline($id)];
    }

    public function create_request()
    {
        $me = $this->me();
        $res = $this->requests->create($this->user, $me['id'], $this->post('type'), $this->post('purpose'));
        if (!empty($res['ok'])) { $this->audit('request.create', 'service_request', $res['id'] ?? null, $this->post('type')); }
        $this->respond($res, 'student/requests', ['type', 'purpose']);
    }
}
