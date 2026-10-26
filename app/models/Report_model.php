<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'models/Campus_model.php';

/**
 * Report_model - tabular datasets for the CSV exports.
 * Every report returns ['title' => string, 'headers' => [...], 'rows' => [[...], ...]].
 */
class Report_model extends Campus_model
{
    /** key => [title, description, roles allowed] */
    public static function catalog()
    {
        return [
            'students'   => ['Student master list',        'Enrolled learners with section, contact, average, attendance and standing.', ['Administrator', 'Teacher', 'Guidance']],
            'attendance' => ['Daily attendance summary',    'Present, late, excused and absent counts per school day.',                 ['Administrator', 'Teacher', 'Guidance']],
            'grades'     => ['Grade summary',               'Quarterly and final grades per learner and subject.',                      ['Administrator', 'Teacher']],
            'enrollment' => ['Enrollment applications',     'All applications with status and document verification.',                  ['Administrator']],
            'services'   => ['Student service requests',    'Certificates and records requests with current status.',                   ['Administrator']],
            'support'    => ['Learners needing support',    'Learners whose standing is Needs Review or Recommended Support, with reasons.', ['Administrator', 'Teacher', 'Guidance']],
        ];
    }

    /** Which filters each report understands. */
    public static function filters_for($key)
    {
        $map = [
            'students'   => ['grade', 'section_id'],
            'attendance' => ['grade', 'section_id', 'from', 'to'],
            'grades'     => ['grade', 'section_id'],
            'enrollment' => ['grade', 'status', 'from', 'to'],
            'services'   => ['status', 'from', 'to'],
            'support'    => ['grade', 'section_id'],
        ];
        return isset($map[$key]) ? $map[$key] : [];
    }

    public function build($key, array $user, Student_model $students, array $f = [])
    {
        $f += ['grade' => 0, 'section_id' => 0, 'status' => '', 'from' => '', 'to' => ''];
        switch ($key) {
            case 'students':   return $this->students($user, $students, $f);
            case 'attendance': return $this->attendance($user, $students, $f);
            case 'grades':     return $this->grades($user, $students, $f);
            case 'enrollment': return $this->enrollment($f);
            case 'services':   return $this->services($f);
            case 'support':    return $this->support($user, $students, $f);
        }
        return null;
    }

    /** keep only learners matching the grade / section filters */
    private function keep(array $list, array $f)
    {
        return array_values(array_filter($list, function ($s) use ($f) {
            if ($f['grade'] && (int) $s['grade'] !== (int) $f['grade']) { return false; }
            if ($f['section_id'] && (int) $s['section_id'] !== (int) $f['section_id']) { return false; }
            return true;
        }));
    }

    private function students(array $user, Student_model $m, array $f)
    {
        $rows = [];
        foreach ($this->keep($m->all($user), $f) as $s) {
            $rows[] = [$s['student_no'], $s['full_name'], $s['section_label'], $s['email'], $s['phone'], $s['guardian_name'], $s['guardian_phone'],
                       $s['average'] === null ? '' : $s['average'], $s['attendance'] === null ? '' : $s['attendance'], $s['missing'], $s['standing']['label']];
        }
        return ['title' => 'student-master-list', 'headers' => ['Student no.', 'Name', 'Section', 'Email', 'Phone', 'Guardian', 'Guardian phone', 'Average', 'Attendance %', 'Missing work', 'Standing'], 'rows' => $rows];
    }

    private function attendance(array $user, Student_model $m, array $f)
    {
        list($sw, $sp) = $m->scope($user);
        if ($f['grade']) { $sw = "($sw) AND s.section_id IN (SELECT id FROM sections WHERE grade = ?)"; $sp[] = (int) $f['grade']; }
        if ($f['section_id']) { $sw = "($sw) AND s.section_id = ?"; $sp[] = (int) $f['section_id']; }
        $att = lava_instance()->attendance;
        $sy = $this->sy();
        $from = $f['from'] && $f['from'] > $sy['start_date'] ? $f['from'] : $sy['start_date'];
        $to   = $f['to'] && $f['to'] < today() ? $f['to'] : today();
        $dates = $this->rows('SELECT DISTINCT log_date FROM gate_logs WHERE log_date BETWEEN ? AND ? ORDER BY log_date', [$from, $to]);
        $rows = [];
        foreach ($dates as $d) {
            $s = $att->day_summary($d['log_date'], [$sw, $sp]);
            $rows[] = [$d['log_date'], $s['enrolled'], $s['present'], $s['late'], $s['excused'], $s['absent'], $s['rate'] === null ? '' : $s['rate']];
        }
        return ['title' => 'daily-attendance', 'headers' => ['Date', 'Enrolled', 'Present', 'Late', 'Excused', 'Absent', 'Attendance rate %'], 'rows' => $rows];
    }

    private function grades(array $user, Student_model $m, array $f)
    {
        list($sw, $sp) = $m->scope($user);
        if ($f['grade']) { $sw = "($sw) AND sec.grade = ?"; $sp[] = (int) $f['grade']; }
        if ($f['section_id']) { $sw = "($sw) AND sec.id = ?"; $sp[] = (int) $f['section_id']; }
        $rows = $this->rows(
            "SELECT s.student_no, s.full_name, sec.grade, sec.name AS sec, sub.name AS subject, g.q1, g.q2, g.q3, g.q4, g.final_grade, g.remarks
               FROM grades g JOIN students s ON s.id = g.student_id JOIN subjects sub ON sub.id = g.subject_id
               LEFT JOIN sections sec ON sec.id = s.section_id
              WHERE g.school_year_id = ? AND ($sw) ORDER BY sec.grade, sec.name, s.full_name, sub.name",
            array_merge([$this->sy()['id']], $sp)
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = [$r['student_no'], $r['full_name'], $r['grade'] ? grade_section($r['grade'], $r['sec']) : '', $r['subject'], $r['q1'], $r['q2'], $r['q3'], $r['q4'], $r['final_grade'], $r['remarks']];
        }
        return ['title' => 'grade-summary', 'headers' => ['Student no.', 'Name', 'Section', 'Subject', 'Q1', 'Q2', 'Q3', 'Q4', 'Final', 'Remarks'], 'rows' => $out];
    }

    private function enrollment(array $f)
    {
        $w = ['1=1']; $p = [];
        if ($f['grade'])  { $w[] = 'e.grade = ?'; $p[] = (int) $f['grade']; }
        if ($f['status']) { $w[] = 'e.status = ?'; $p[] = $f['status']; }
        if ($f['from'])   { $w[] = 'DATE(e.created_at) >= ?'; $p[] = $f['from']; }
        if ($f['to'])     { $w[] = 'DATE(e.created_at) <= ?'; $p[] = $f['to']; }
        $rows = $this->rows(
            "SELECT e.code, e.applicant_name, e.type, e.grade, sec.name AS sec, e.status, e.docs_status, e.guardian_name, e.guardian_phone, e.email, e.created_at
               FROM enrollments e JOIN sections sec ON sec.id = e.section_id WHERE " . implode(' AND ', $w) . " ORDER BY e.id DESC", $p
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = [$r['code'], $r['applicant_name'], $r['type'], grade_section($r['grade'], $r['sec']), $r['status'], $r['docs_status'],
                      $r['guardian_name'], $r['guardian_phone'], $r['email'], $r['created_at']];
        }
        return ['title' => 'enrollment-applications', 'headers' => ['Code', 'Applicant', 'Type', 'Section', 'Status', 'Documents', 'Guardian', 'Guardian phone', 'Email', 'Submitted'], 'rows' => $out];
    }

    private function services(array $f)
    {
        $w = ['1=1']; $p = [];
        if ($f['status']) { $w[] = 'r.status = ?'; $p[] = $f['status']; }
        if ($f['from'])   { $w[] = 'DATE(r.created_at) >= ?'; $p[] = $f['from']; }
        if ($f['to'])     { $w[] = 'DATE(r.created_at) <= ?'; $p[] = $f['to']; }
        $rows = $this->rows(
            "SELECT r.code, s.student_no, s.full_name, r.type, r.status, r.purpose, r.created_at, r.updated_at
               FROM service_requests r JOIN students s ON s.id = r.student_id WHERE " . implode(' AND ', $w) . " ORDER BY r.id DESC", $p
        );
        $out = [];
        foreach ($rows as $r) { $out[] = array_values($r); }
        return ['title' => 'service-requests', 'headers' => ['Code', 'Student no.', 'Student', 'Type', 'Status', 'Purpose', 'Requested', 'Updated'], 'rows' => $out];
    }

    private function support(array $user, Student_model $m, array $f)
    {
        $rows = [];
        foreach ($this->keep($m->for_review($user), $f) as $s) {
            $rows[] = [$s['student_no'], $s['full_name'], $s['section_label'], $s['average'] === null ? '' : $s['average'],
                       $s['attendance'] === null ? '' : $s['attendance'], $s['missing'], $s['standing']['label'], implode('; ', $s['standing']['reasons'])];
        }
        return ['title' => 'learners-needing-support', 'headers' => ['Student no.', 'Name', 'Section', 'Average', 'Attendance %', 'Missing work', 'Standing', 'Reasons'], 'rows' => $rows];
    }
}
