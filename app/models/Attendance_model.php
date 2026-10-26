<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'models/Campus_model.php';

/**
 * Attendance_model - daily attendance (derived from gate scans), classroom attendance,
 * gate-vs-class discrepancies and the correction audit trail.
 *
 * Daily status of a student (per date):
 *   Present  - gate Time-in at or before the late cutoff
 *   Late     - gate Time-in after the cutoff            (counted as present)
 *   Excused  - no Time-in but an Excused classroom record (counted as absent)
 *   Absent   - none of the above
 */
class Attendance_model extends Campus_model
{
    const STATUSES = ['Present', 'Absent', 'Late', 'Excused'];

    private function cutoff() { return config_item('late_cutoff'); }

    /** SQL: first gate time-in of the day per student. */
    private function first_in_sql() { return "(SELECT student_id, MIN(log_time) AS first_in FROM gate_logs WHERE log_date = ? AND event = 'Time-in' GROUP BY student_id)"; }

    // ------------------------------------------------------------------ summaries
    /** @return array enrolled / present / late / excused / absent for one date */
    public function day_summary($date, array $scope)
    {
        list($sw, $sp) = $scope;
        $r = $this->row(
            "SELECT COUNT(*) AS enrolled,
                    COALESCE(SUM(gi.first_in IS NOT NULL AND gi.first_in <= ?), 0) AS present,
                    COALESCE(SUM(gi.first_in IS NOT NULL AND gi.first_in > ?), 0)  AS late,
                    COALESCE(SUM(gi.first_in IS NULL AND ex.sid IS NOT NULL), 0)   AS excused
               FROM students s
          LEFT JOIN " . $this->first_in_sql() . " gi ON gi.student_id = s.id
          LEFT JOIN (SELECT DISTINCT student_id AS sid FROM class_attendance WHERE attend_date = ? AND status = 'Excused') ex ON ex.sid = s.id
              WHERE s.enrollment_status = 'Enrolled' AND ($sw)",
            array_merge([$this->cutoff(), $this->cutoff(), $date, $date], $sp)
        );
        $e = (int) $r['enrolled'];
        $present = (int) $r['present']; $late = (int) $r['late']; $excused = (int) $r['excused'];
        return [
            'enrolled' => $e, 'present' => $present, 'late' => $late, 'excused' => $excused,
            'absent' => max(0, $e - $present - $late - $excused),
            'present_total' => $present + $late,                     // late counts as present
            'absent_total'  => max(0, $e - $present - $late),        // excused counts as absent
            'rate' => $e > 0 ? round(100 * ($present + $late) / $e, 1) : null,
        ];
    }

    /** Per-day present/absent for a date range - feeds the bar charts. */
    public function range_series($from, $to, array $scope)
    {
        list($sw, $sp) = $scope;
        $enrolled = (int) $this->val("SELECT COUNT(*) FROM students s WHERE s.enrollment_status = 'Enrolled' AND ($sw)", $sp);
        $rows = $this->rows(
            "SELECT d.log_date, COUNT(*) AS present FROM
                (SELECT log_date, student_id FROM gate_logs WHERE event = 'Time-in' AND log_date BETWEEN ? AND ? GROUP BY log_date, student_id) d
             JOIN students s ON s.id = d.student_id
             WHERE s.enrollment_status = 'Enrolled' AND ($sw)
             GROUP BY d.log_date",
            array_merge([$from, $to], $sp)
        );
        $by = [];
        foreach ($rows as $r) { $by[$r['log_date']] = (int) $r['present']; }
        return ['enrolled' => $enrolled, 'present' => $by];
    }

    /** Monday-Friday of the week that contains $date, with present/absent counts. */
    public function week_chart($date, array $scope)
    {
        $monday = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $friday = date('Y-m-d', strtotime($monday . ' +4 days'));
        $s = $this->range_series($monday, $friday, $scope);
        $labels = []; $present = []; $absent = [];
        for ($i = 0; $i < 5; $i++) {
            $d = date('Y-m-d', strtotime($monday . " +$i days"));
            $labels[]  = date('D', strtotime($d));
            $p = isset($s['present'][$d]) ? $s['present'][$d] : 0;
            $present[] = $p;
            $absent[]  = $p > 0 ? max(0, $s['enrolled'] - $p) : 0;       // no scans yet => nothing to compare
        }
        return ['labels' => $labels, 'present' => $present, 'absent' => $absent, 'from' => $monday, 'to' => $friday];
    }

    /** Weekly averages for the month containing $date. */
    public function month_chart($date, array $scope)
    {
        $first = date('Y-m-01', strtotime($date));
        $last  = date('Y-m-t', strtotime($date));
        $s = $this->range_series($first, $last, $scope);
        $weeks = [];
        foreach ($s['present'] as $d => $p) {
            $w = (int) ceil((int) date('j', strtotime($d)) / 7);
            $weeks[$w][] = $p;
        }
        $labels = []; $present = []; $absent = [];
        ksort($weeks);
        foreach ($weeks as $w => $list) {
            $avg = (int) round(array_sum($list) / count($list));
            $labels[] = 'Week ' . $w; $present[] = $avg; $absent[] = max(0, $s['enrolled'] - $avg);
        }
        return ['labels' => $labels, 'present' => $present, 'absent' => $absent, 'from' => $first, 'to' => $last];
    }

    // ------------------------------------------------------------------ roster / discrepancy
    /** SQL for "class periods that should have a record by now". */
    private function due_cond($date)
    {
        // past dates: every period is due. Today: only periods that already started.
        return $date < today() ? ['1=1', []] : ['(cx.period_start IS NULL OR cx.period_start <= ?)', [date('H:i:s')]];
    }

    /**
     * Attendance roster for a date.
     * @param array $f q, grade, section_id
     */
    public function roster($date, array $scope, array $f = [], $page = 1, $per = 15)
    {
        list($sw, $sp) = $scope;
        list($dc, $dp) = $this->due_cond($date);
        $where = ["s.enrollment_status = 'Enrolled'", "($sw)"];
        $wp = $sp;
        if (!empty($f['q']))          { $where[] = 's.full_name LIKE ?'; $wp[] = '%' . addcslashes($f['q'], '%_\\') . '%'; }
        if (!empty($f['grade']))      { $where[] = 'sec.grade = ?';      $wp[] = (int) $f['grade']; }
        if (!empty($f['section_id'])) { $where[] = 's.section_id = ?';   $wp[] = (int) $f['section_id']; }
        $w = ' WHERE ' . implode(' AND ', $where);

        $from = " FROM students s
                  LEFT JOIN sections sec ON sec.id = s.section_id
                  LEFT JOIN " . $this->first_in_sql() . " gi ON gi.student_id = s.id
                  LEFT JOIN (SELECT student_id, MAX(log_time) AS last_out FROM gate_logs WHERE log_date = ? AND event = 'Time-out' GROUP BY student_id) go ON go.student_id = s.id ";
        $fp = [$date, $date];

        $total = (int) $this->val('SELECT COUNT(*)' . $from . $w, array_merge($fp, $wp));
        list($page, $per, $off) = $this->page($page, $per, 5000);

        $sql = "SELECT s.id, s.student_no, s.full_name, s.section_id, sec.grade, sec.name AS section_name, gi.first_in, go.last_out,
                  (SELECT COUNT(*) FROM class_attendance ca WHERE ca.student_id = s.id AND ca.attend_date = ?) AS class_recorded,
                  (SELECT COUNT(*) FROM class_attendance ca WHERE ca.student_id = s.id AND ca.attend_date = ? AND ca.status = 'Excused') AS excused_n,
                  (SELECT COUNT(*) FROM class_assignments cx
                     LEFT JOIN class_attendance ca ON ca.student_id = s.id AND ca.subject_id = cx.subject_id AND ca.attend_date = ?
                    WHERE cx.section_id = s.section_id AND ca.id IS NULL AND gi.first_in IS NOT NULL AND $dc) AS missing_n"
             . $from . $w . " ORDER BY sec.grade, sec.name, s.full_name LIMIT $per OFFSET $off";
        $params = array_merge([$date, $date, $date], $dp, $fp, $wp);
        $rows = $this->rows($sql, $params);

        $cut = $this->cutoff();
        foreach ($rows as &$r) {
            $r['initials'] = initials($r['full_name']);
            if ($r['first_in'] !== null) {
                $r['day_status'] = $r['first_in'] > $cut ? 'Late' : 'Present';
            } else {
                $r['day_status'] = (int) $r['excused_n'] > 0 ? 'Excused' : 'Absent';
            }
            if ($r['first_in'] === null) {
                $r['class_badge'] = $r['day_status'] === 'Excused' ? 'Excused' : 'Absent';
            } else {
                $r['class_badge'] = (int) $r['missing_n'] > 0 ? 'Missing record' : 'Recorded';
            }
        }
        return ['rows' => $rows, 'total' => $total];
    }

    /** Pairs of (student, subject) with a gate time-in but no classroom record. */
    public function discrepancies($date, array $scope, $limit = 50)
    {
        list($sw, $sp) = $scope;
        list($dc, $dp) = $this->due_cond($date);
        $limit = max(1, (int) $limit);
        return $this->rows(
            "SELECT s.id AS student_id, s.student_no, s.full_name, s.section_id, sec.grade, sec.name AS section_name,
                    cx.subject_id, sub.name AS subject_name, cx.period_start, gi.first_in, ca.status AS class_status
               FROM students s
               JOIN sections sec ON sec.id = s.section_id
               JOIN " . $this->first_in_sql() . " gi ON gi.student_id = s.id
               JOIN class_assignments cx ON cx.section_id = s.section_id
               JOIN subjects sub ON sub.id = cx.subject_id
          LEFT JOIN class_attendance ca ON ca.student_id = s.id AND ca.subject_id = cx.subject_id AND ca.attend_date = ?
              WHERE (ca.id IS NULL OR ca.status = 'Absent') AND s.enrollment_status = 'Enrolled' AND ($sw) AND $dc
              ORDER BY cx.period_start, s.full_name LIMIT $limit",
            array_merge([$date, $date], $sp, $dp)
        );
    }

    public function discrepancy_count($date, array $scope)
    {
        list($sw, $sp) = $scope;
        list($dc, $dp) = $this->due_cond($date);
        return (int) $this->val(
            "SELECT COUNT(*) FROM students s
               JOIN " . $this->first_in_sql() . " gi ON gi.student_id = s.id
               JOIN class_assignments cx ON cx.section_id = s.section_id
          LEFT JOIN class_attendance ca ON ca.student_id = s.id AND ca.subject_id = cx.subject_id AND ca.attend_date = ?
              WHERE (ca.id IS NULL OR ca.status = 'Absent') AND s.enrollment_status = 'Enrolled' AND ($sw) AND $dc",
            array_merge([$date, $date], $sp, $dp)
        );
    }

    /** Subjects of a student's section for one date with the recorded status (or null = missing). */
    public function student_day($student_id, $section_id, $date)
    {
        $rows = $this->rows(
            "SELECT cx.subject_id, sub.name AS subject_name, cx.period_start, t.full_name AS teacher_name,
                    ca.id AS attendance_id, ca.status, u.full_name AS recorded_by_name, ca.updated_at,
                    (SELECT COUNT(*) FROM attendance_corrections c WHERE c.attendance_id = ca.id) AS corrections
               FROM class_assignments cx
               JOIN subjects sub ON sub.id = cx.subject_id
               JOIN teachers t ON t.id = cx.teacher_id
          LEFT JOIN class_attendance ca ON ca.subject_id = cx.subject_id AND ca.student_id = ? AND ca.attend_date = ?
          LEFT JOIN users u ON u.id = ca.recorded_by
              WHERE cx.section_id = ? ORDER BY cx.period_start, sub.name",
            [(int) $student_id, $date, (int) $section_id]
        );
        return $rows;
    }

    public function corrections_for($attendance_id)
    {
        return $this->rows(
            'SELECT c.*, u.full_name AS by_name FROM attendance_corrections c LEFT JOIN users u ON u.id = c.changed_by
              WHERE c.attendance_id = ? ORDER BY c.created_at DESC',
            [(int) $attendance_id]
        );
    }

    // ------------------------------------------------------------------ writes
    /** May this user record attendance for this section + subject? */
    public function may_record(array $user, $section_id, $subject_id)
    {
        if ($user['role'] === 'Administrator') { return true; }
        if ($user['role'] !== 'Teacher') { return false; }
        return (bool) $this->val(
            'SELECT 1 FROM class_assignments WHERE teacher_id = ? AND section_id = ? AND subject_id = ?',
            [(int) $user['teacher_id'], (int) $section_id, (int) $subject_id]
        );
    }

    /**
     * Record or correct one student's classroom attendance.
     * An existing record can only be changed with a written reason, which is kept in the audit trail.
     */
    public function record(array $user, $student_id, $subject_id, $date, $status, $reason = '')
    {
        if (!in_array($status, self::STATUSES, true)) {
            return $this->fail('Choose a valid attendance status.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) || !strtotime($date)) {
            return $this->fail('Choose a valid date.');
        }
        if ($date > today()) {
            return $this->fail('Attendance cannot be recorded for a future date.');
        }
        $s = $this->row('SELECT id, full_name, section_id FROM students WHERE id = ?', [(int) $student_id]);
        if (!$s || !$s['section_id']) {
            return $this->fail('Unknown student.');
        }
        $sub = $this->row(
            'SELECT sub.name FROM class_assignments cx JOIN subjects sub ON sub.id = cx.subject_id WHERE cx.section_id = ? AND cx.subject_id = ?',
            [(int) $s['section_id'], (int) $subject_id]
        );
        if (!$sub) {
            return $this->fail('That subject is not taught in the student\'s section.');
        }
        if (!$this->may_record($user, $s['section_id'], $subject_id)) {
            return $this->fail('You can only record attendance for your own classes.');
        }
        $existing = $this->row('SELECT * FROM class_attendance WHERE student_id = ? AND subject_id = ? AND attend_date = ?',
            [(int) $student_id, (int) $subject_id, $date]);
        if ($existing) {
            $reason = trim((string) $reason);
            if ($reason === '') {
                return $this->fail('Attendance is already recorded for this class and date. Give a correction reason to change it.');
            }
            if ($existing['status'] === $status) {
                return $this->fail("Status is already {$status}.");
            }
            $this->db->transaction();
            try {
                $this->update_row('class_attendance', $existing['id'], ['status' => $status, 'recorded_by' => $user['id'], 'updated_at' => now()]);
                $this->insert_row('attendance_corrections', [
                    'attendance_id' => $existing['id'], 'old_status' => $existing['status'], 'new_status' => $status,
                    'reason' => mb_substr($reason, 0, 255), 'changed_by' => $user['id'], 'created_at' => now(),
                ]);
                $this->db->commit();
            } catch (Throwable $e) {
                $this->db->roll_back();
                throw $e;
            }
            return $this->ok("Correction saved for {$s['full_name']}: {$status}.", ['id' => $existing['id'], 'student' => $s, 'subject' => $sub['name'], 'corrected' => true]);
        }
        $id = $this->insert_row('class_attendance', [
            'student_id' => (int) $student_id, 'subject_id' => (int) $subject_id, 'section_id' => (int) $s['section_id'],
            'attend_date' => $date, 'status' => $status, 'recorded_by' => $user['id'], 'created_at' => now(),
        ]);
        return $this->ok("{$s['full_name']} marked {$status} for {$sub['name']}.", ['id' => $id, 'student' => $s, 'subject' => $sub['name']]);
    }

    /** Class attendance sheet: students of a section with today's status for one subject. */
    public function class_sheet($section_id, $subject_id, $date)
    {
        return $this->rows(
            "SELECT s.id AS student_id, s.student_no, s.full_name, ca.status, gi.first_in
               FROM students s
          LEFT JOIN class_attendance ca ON ca.student_id = s.id AND ca.subject_id = ? AND ca.attend_date = ?
          LEFT JOIN " . $this->first_in_sql() . " gi ON gi.student_id = s.id
              WHERE s.section_id = ? AND s.enrollment_status = 'Enrolled' ORDER BY s.full_name",
            [(int) $subject_id, $date, $date, (int) $section_id]
        );
    }

    /** Save a whole class at once. New rows are inserted; changed rows need a reason. */
    public function record_class(array $user, $section_id, $subject_id, $date, array $statuses, $reason = '')
    {
        if (!$this->may_record($user, $section_id, $subject_id)) {
            return $this->fail('You can only record attendance for your own classes.');
        }
        $students = array_column($this->class_sheet($section_id, $subject_id, $date), null, 'student_id');
        $new = 0; $changed = 0; $skipped = 0; $errors = [];
        foreach ($statuses as $sid => $status) {
            $sid = (int) $sid;
            if (!isset($students[$sid]) || !in_array($status, self::STATUSES, true)) { continue; }
            $cur = $students[$sid]['status'];
            if ($cur === $status) { $skipped++; continue; }
            $res = $this->record($user, $sid, $subject_id, $date, $status, $reason);
            if (!$res['ok']) { $errors[] = $res['error']; continue; }
            $cur === null ? $new++ : $changed++;
        }
        if ($errors && !$new && !$changed) {
            return $this->fail($errors[0]);
        }
        return $this->ok("Attendance saved: {$new} new, {$changed} corrected, {$skipped} unchanged.", ['new' => $new, 'changed' => $changed]);
    }

    /** Adviser/teacher/admin confirms a student was present for a class that only has a gate record. */
    public function verify_present(array $user, $student_id, $subject_id, $date)
    {
        $s = $this->row('SELECT id, full_name, section_id FROM students WHERE id = ?', [(int) $student_id]);
        if (!$s) { return $this->fail('Unknown student.'); }
        $advises = $user['role'] === 'Teacher' && $this->val('SELECT 1 FROM sections WHERE id = ? AND adviser_id = ?', [(int) $s['section_id'], (int) $user['teacher_id']]);
        if (!($user['role'] === 'Administrator' || $advises || $this->may_record($user, $s['section_id'], $subject_id))) {
            return $this->fail('Only the class adviser, the subject teacher or an administrator can verify attendance.');
        }
        if (!$this->val("SELECT 1 FROM gate_logs WHERE student_id = ? AND log_date = ? AND event = 'Time-in'", [(int) $student_id, $date])) {
            return $this->fail('There is no gate record to verify against.');
        }
        if ($this->val('SELECT 1 FROM class_attendance WHERE student_id = ? AND subject_id = ? AND attend_date = ?', [(int) $student_id, (int) $subject_id, $date])) {
            return $this->fail('This class already has an attendance record.');
        }
        $id = $this->insert_row('class_attendance', [
            'student_id' => (int) $student_id, 'subject_id' => (int) $subject_id, 'section_id' => (int) $s['section_id'],
            'attend_date' => $date, 'status' => 'Present', 'recorded_by' => $user['id'], 'created_at' => now(),
        ]);
        return $this->ok($s['full_name'] . ' verified as present.', ['id' => $id, 'student' => $s]);
    }
}
