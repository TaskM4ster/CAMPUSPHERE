<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/**
 * Attendance - gate-driven daily attendance, classroom attendance, discrepancy review
 * and gate device management.
 */
class Attendance extends Base_controller
{
    private function date()
    {
        return valid_date($this->query('date'), $this->gate->latest_date());
    }

    public function index()
    {
        $date  = $this->date();
        $scope = $this->students->scope($this->user);
        $f = ['q' => mb_substr($this->query('q'), 0, 80), 'grade' => (int) $this->query('grade'), 'section_id' => (int) $this->query('section')];
        $roster = $this->attendance->roster($date, $scope, $f, $this->page(), 15);

        $detail = null;
        if ($sid = (int) $this->query('detail')) {
            if ($this->students->can_view($this->user, $sid)) {
                $st = $this->students->find($sid);
                if ($st && $st['section_id']) {
                    $classes = $this->attendance->student_day($sid, $st['section_id'], $date);
                    foreach ($classes as &$c) {
                        $c['can_record'] = $this->attendance->may_record($this->user, $st['section_id'], $c['subject_id']);
                        $c['history']    = $c['attendance_id'] ? $this->attendance->corrections_for($c['attendance_id']) : [];
                    }
                    unset($c);
                    $detail = ['student' => $st, 'gate' => $this->gate->logs_for($sid, $date), 'classes' => $classes];
                }
            }
        }

        $sections = $this->section_options();
        if ($this->user['role'] === 'Teacher') {
            $ids = $this->teachers->section_ids($this->user['teacher_id']);
            $sections = array_values(array_filter($sections, function ($s) use ($ids) { return in_array((int) $s['id'], $ids, true); }));
        }

        $this->render('attendance/index', [
            'date'     => $date,
            'is_admin' => $this->user['role'] === 'Administrator',
            'summary'  => $this->attendance->day_summary($date, $scope),
            'week'     => $this->attendance->week_chart($date, $scope),
            'month'    => $this->attendance->month_chart($date, $scope),
            'roster'   => $roster['rows'], 'total' => $roster['total'], 'page' => $this->page(), 'per' => 15,
            'f'        => $f,
            'sections' => $sections,
            'disc'     => $this->attendance->discrepancies($date, $scope, 25),
            'disc_total' => $this->attendance->discrepancy_count($date, $scope),
            'detail'   => $detail,
            'statuses' => Attendance_model::STATUSES,
            'my_classes' => $this->user['role'] === 'Teacher' ? $this->teachers->assignments($this->user['teacher_id']) : [],
        ], 'Attendance');
    }

    // ------------------------------------------------------------------ classroom attendance
    public function class_sheet()
    {
        $section_id = (int) $this->query('section');
        $subject_id = (int) $this->query('subject');
        $date = valid_date($this->query('date'), today());
        if (!$this->attendance->may_record($this->user, $section_id, $subject_id)) { $this->forbidden(); }
        $section = $this->school->section($section_id);
        $subject = $this->school->subject($subject_id);
        if (!$section || !$subject) { $this->not_found('class'); }
        $this->render('attendance/class', [
            'section' => $section, 'subject' => $subject, 'date' => $date,
            'sheet'   => $this->attendance->class_sheet($section_id, $subject_id, $date),
            'statuses' => Attendance_model::STATUSES,
        ], 'Class Attendance', '/attendance');
    }

    public function save_class()
    {
        $section_id = (int) $this->post('section_id');
        $subject_id = (int) $this->post('subject_id');
        $date = valid_date($this->post('date'), today());
        $raw  = ($_POST['status'] ?? null);
        $statuses = is_array($raw) ? $raw : [];
        $res = $this->attendance->record_class($this->user, $section_id, $subject_id, $date, $statuses, $this->post('reason'));
        if (!empty($res['ok'])) { $this->audit('class_attendance', 'section', $section_id, "subject {$subject_id} {$date}: " . $res['message']); }
        $this->respond($res, 'attendance/class?' . http_build_query(['section' => $section_id, 'subject' => $subject_id, 'date' => $date]));
    }

    public function record()
    {
        $sid  = (int) $this->post('student_id');
        $date = valid_date($this->post('date'), today());
        if (!$this->students->can_view($this->user, $sid)) { $this->forbidden(); }
        $res = $this->attendance->record($this->user, $sid, (int) $this->post('subject_id'), $date, $this->post('status'), $this->post('reason'));
        if (!empty($res['ok'])) {
            $this->audit(!empty($res['corrected']) ? 'attendance.correct' : 'attendance.record', 'student', $sid, ($res['subject'] ?? '') . " {$date} " . $this->post('status') . ($this->post('reason') ? ' — ' . $this->post('reason') : ''));
        }
        $this->respond($res, 'attendance?' . http_build_query(['date' => $date, 'detail' => $sid]));
    }

    public function verify()
    {
        $sid  = (int) $this->post('student_id');
        $date = valid_date($this->post('date'), today());
        if (!$this->students->can_view($this->user, $sid)) { $this->forbidden(); }
        $res = $this->attendance->verify_present($this->user, $sid, (int) $this->post('subject_id'), $date);
        if (!empty($res['ok'])) { $this->audit('attendance.verify', 'student', $sid, "subject " . (int) $this->post('subject_id') . " {$date}"); }
        $this->respond($res, 'attendance?' . http_build_query(['date' => $date]) . '#discrepancies');
    }

    /** Tell the section adviser(s) about gate-vs-class discrepancies for a date. */
    public function notify()
    {
        $this->require_role('Administrator');
        $date  = valid_date($this->post('date'), today());
        $scope = ['1=1', []];
        $rows  = $this->attendance->discrepancies($date, $scope, 500);
        if (!$rows) { $this->respond(['ok' => false, 'error' => 'There are no discrepancies to report for this date.'], 'attendance?date=' . $date); }
        $by = [];
        foreach ($rows as $r) { $by[$r['section_id']][] = $r; }
        foreach ($by as $section_id => $list) {
            $names = array_slice(array_unique(array_column($list, 'full_name')), 0, 5);
            $this->notices->publish(
                'Attendance discrepancies for ' . fmt_date($date),
                count($list) . ' class record(s) are missing although the learner(s) entered the gate: ' . implode(', ', $names) . (count($list) > 5 ? ' and others' : '') . '. Please record or verify attendance.',
                'Attendance', ['roles' => ['Teacher'], 'section_id' => (int) $section_id], $this->user['id']
            );
        }
        $this->audit('attendance.notify', 'attendance', null, count($by) . ' section(s), ' . $date);
        $this->respond(['ok' => true, 'message' => 'Advisers of ' . count($by) . ' section(s) were notified.'], 'attendance?date=' . $date);
    }

    // ------------------------------------------------------------------ gate (administrators)
    public function gate_entry()
    {
        $this->require_role('Administrator');
        $res = $this->gate->record(
            (int) $this->post('student_id'), $this->post('event'), valid_date($this->post('date'), today()),
            $this->post('time') ?: date('H:i:s'), 'Manual', null, $this->user['id']
        );
        if (!empty($res['ok']) && empty($res['duplicate'])) { $this->audit('gate.manual', 'student', $this->post('student_id'), $this->post('event') . ' ' . $this->post('time')); }
        $this->respond($res, 'attendance?date=' . valid_date($this->post('date'), today()));
    }

    public function devices()
    {
        $this->require_role('Administrator');
        $this->render('attendance/devices', [
            'devices' => $this->gate->devices(),
            'token'   => $this->session->flashdata('device_token'),
            'endpoint' => site_url('api/gate/scan'),
        ], 'Gate Devices', '/attendance');
    }

    public function create_device()
    {
        $this->require_role('Administrator');
        $res = $this->gate->create_device($this->post('name'), $this->post('location'));
        if (!empty($res['ok'])) {
            $this->audit('device.create', 'gate_device', $res['id'], $this->post('name'));
            $this->session->set_flashdata('device_token', ['name' => $this->post('name'), 'token' => $res['token']]);
        }
        $this->respond($res, 'attendance/devices', ['name', 'location']);
    }

    public function toggle_device($id)
    {
        $this->require_role('Administrator');
        $this->gate->toggle_device((int) $id);
        $this->audit('device.toggle', 'gate_device', (int) $id);
        $this->flash('success', 'Device status updated.');
        go('attendance/devices');
    }
}
