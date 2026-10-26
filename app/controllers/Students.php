<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/** Students - directory, profile and contact updates (row-level scoped per role). */
class Students extends Base_controller
{
    private function filters()
    {
        return [
            'q'          => mb_substr($this->query('q'), 0, 80),
            'grade'      => (int) $this->query('grade'),
            'section_id' => (int) $this->query('section'),
        ];
    }

    public function index()
    {
        $f = $this->filters();
        $res = $this->students->search($this->user, $f, $this->page(), 15);
        $standing = $this->query('standing');
        $this->render('students/index', [
            'rows'     => $res['rows'],
            'total'    => $res['total'],
            'page'     => $this->page(),
            'per'      => 15,
            'f'        => $f,
            'sections' => $this->visible_sections(),
            'can_export' => in_array($this->user['role'], ['Administrator', 'Teacher', 'Guidance'], true),
        ], 'Students');
    }

    private function visible_sections()
    {
        $all = $this->section_options();
        if ($this->user['role'] !== 'Teacher') { return $all; }
        $ids = $this->teachers->section_ids($this->user['teacher_id']);
        return array_values(array_filter($all, function ($s) use ($ids) { return in_array((int) $s['id'], $ids, true); }));
    }

    public function export()
    {
        $rows = [['Student no.', 'Name', 'Section', 'Email', 'Phone', 'Guardian', 'Average', 'Attendance %', 'Missing work', 'Standing']];
        foreach ($this->students->all($this->user, $this->filters()) as $s) {
            $rows[] = [$s['student_no'], $s['full_name'], $s['section_label'], $s['email'], $s['phone'], $s['guardian_name'],
                       $s['average'], $s['attendance'], $s['missing'], $s['standing']['label']];
        }
        $this->audit('export', 'students', null, count($rows) - 1 . ' rows');
        csv_download('students', $rows);
    }

    public function show($id)
    {
        $id = (int) $id;
        if (!$this->students->can_view($this->user, $id)) { $this->not_found('student'); }
        $s  = $this->students->find($id);
        if (!$s) { $this->not_found('student'); }
        $sy = $this->school->sy();
        $date = $this->gate->latest_date() ?: today();
        $role = $this->user['role'];
        $advises = $role === 'Teacher' && $s['section_id'] && $this->teachers->advises($this->user['teacher_id'], $s['section_id']);
        $can_notes = in_array($role, ['Administrator', 'Guidance'], true) || $advises;

        $this->render('students/show', [
            's'          => $s,
            'sy'         => $sy,
            'date'       => $date,
            'card'       => $this->grades->report_card($id, $sy['id']),
            'trend'      => $this->grades->trend($id, $sy['id']),
            'gate'       => $this->gate->logs_for($id, $date),
            'classes'    => $s['section_id'] ? $this->attendance->student_day($id, $s['section_id'], $date) : [],
            'activities' => $this->student_activities($s),
            'notes'      => $can_notes ? $this->guidance->notes($id, 10) : [],
            'plans'      => $can_notes ? $this->guidance->interventions($id) : [],
            'can_notes'  => $can_notes,
            'can_edit'   => $role === 'Administrator',
            'weak'       => $this->grades->weak_subjects($id, $sy['id']),
        ], $s['full_name'], '/students');
    }

    private function student_activities(array $s)
    {
        if (!$s['section_id']) { return []; }
        $out = [];
        foreach ($this->activities->list_for(['role' => 'Administrator']) as $a) {
            if ((int) $a['section_id'] !== (int) $s['section_id']) { continue; }
            $out[] = ['activity' => $a, 'view' => $this->activities->student_view($a, $s['id'])];
        }
        return array_slice($out, 0, 6);
    }

    public function update($id)
    {
        $this->require_role('Administrator');
        $id = (int) $id;
        if (!$this->students->find($id)) { $this->not_found('student'); }
        $email = $this->post('email');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond(['ok' => false, 'error' => 'Enter a valid email address.'], 'students/' . $id, ['email', 'phone', 'address', 'guardian_name', 'guardian_phone']);
        }
        $birth = $this->post('birth_date');
        if ($birth !== '' && valid_date($birth, '') === '') {
            $this->respond(['ok' => false, 'error' => 'Enter a valid date of birth.'], 'students/' . $id);
        }
        $res = $this->students->update_contact($id, [
            'email' => $email ?: null, 'phone' => mb_substr($this->post('phone'), 0, 30) ?: null,
            'address' => mb_substr($this->post('address'), 0, 200) ?: null,
            'guardian_name' => mb_substr($this->post('guardian_name'), 0, 120) ?: null,
            'guardian_phone' => mb_substr($this->post('guardian_phone'), 0, 30) ?: null,
            'birth_date' => $birth ?: null,
        ]);
        $this->audit('update', 'student', $id, 'contact details');
        $this->respond($res, 'students/' . $id);
    }
}
