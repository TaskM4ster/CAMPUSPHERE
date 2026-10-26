<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/** Enrollment - applications, document verification, approval (administrators only). */
class Enrollment extends Base_controller
{
    public function before_action()
    {
        parent::before_action();
        if ($this->user && $this->user['role'] !== 'Administrator') { $this->forbidden(); }
    }

    public function index()
    {
        $f = ['q' => mb_substr($this->query('q'), 0, 80), 'status' => $this->query('status')];
        $res = $this->enrollments->search($f, $this->page(), 15);
        $this->render('enrollment/index', [
            'rows' => $res['rows'], 'total' => $res['total'], 'page' => $this->page(), 'per' => 15, 'f' => $f,
            'counts'   => $this->enrollments->counts(),
            'sections' => $this->section_options(),
            'returning' => $this->students->all($this->user),
            'detail'   => ($d = (int) $this->query('detail')) ? $this->enrollments->find($d) : null,
            'temp'     => $this->session->flashdata('temp_credentials'),
        ], 'Enrollment');
    }

    public function create()
    {
        $in = [
            'type' => $this->post('type'), 'name' => $this->post('name'), 'student_id' => (int) $this->post('student_id'),
            'section_id' => (int) $this->post('section_id'), 'email' => $this->post('email'), 'birth_date' => $this->post('birth_date'),
            'guardian_name' => $this->post('guardian_name'), 'guardian_phone' => $this->post('guardian_phone'),
        ];
        $res = $this->enrollments->create($this->user, $in);
        if (!empty($res['ok'])) { $this->audit('create', 'enrollment', $res['id'], $res['code']); }
        $this->respond($res, 'enrollment', ['type', 'name', 'student_id', 'section_id', 'email', 'birth_date', 'guardian_name', 'guardian_phone']);
    }

    public function verify($id)
    {
        $res = $this->enrollments->verify_documents($this->user, (int) $id);
        if (!empty($res['ok'])) { $this->audit('verify_documents', 'enrollment', $id); }
        $this->respond($res, 'enrollment');
    }

    public function reject($id)
    {
        $res = $this->enrollments->reject($this->user, (int) $id, $this->post('reason'));
        if (!empty($res['ok'])) { $this->audit('reject', 'enrollment', $id, $this->post('reason')); }
        $this->respond($res, 'enrollment');
    }

    public function approve($id)
    {
        $res = $this->enrollments->approve($this->user, (int) $id);
        if (!empty($res['ok'])) {
            $this->audit('approve', 'enrollment', $id, $res['message'] ?? null);
            if (!empty($res['temp_password'])) {
                $this->session->set_flashdata('temp_credentials', [
                    'username' => strtolower($res['student_no'] ?? ''), 'password' => $res['temp_password'], 'name' => $res['enrollment']['applicant_name'] ?? '',
                ]);
            }
        }
        $this->respond($res, 'enrollment');
    }
}
