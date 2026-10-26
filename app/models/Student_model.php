<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'models/Campus_model.php';
require_once APP_DIR . 'libraries/Standing.php';

/**
 * Student_model - student records, row-level visibility and the derived
 * academic indicators (average, attendance rate, missing requirements, standing).
 */
class Student_model extends Campus_model
{
    /** number of school days with gate data this year (cached) */
    private $school_days = null;

    // ------------------------------------------------------------------ visibility
    /**
     * Row-level rule: which students may this user see?
     * @return array [sql fragment, params]
     */
    public function scope(array $user, $a = 's')
    {
        switch ($user['role']) {
            case 'Administrator':
            case 'Guidance':
                return ['1=1', []];
            case 'Teacher':
                $t = (int) $user['teacher_id'];
                return ["{$a}.section_id IN (SELECT section_id FROM class_assignments WHERE teacher_id = ?
                         UNION SELECT id FROM sections WHERE adviser_id = ?)", [$t, $t]];
            case 'Student':
                return ["{$a}.id = ?", [(int) $user['student_id']]];
        }
        return ['1=0', []];
    }

    public function can_view(array $user, $student_id)
    {
        list($w, $p) = $this->scope($user);
        return (bool) $this->val("SELECT 1 FROM students s WHERE s.id = ? AND ($w)", array_merge([(int) $student_id], $p));
    }

    // ------------------------------------------------------------------ derived metrics
    public function school_days()
    {
        if ($this->school_days === null) {
            $sy = $this->sy();
            $this->school_days = (int) $this->val(
                'SELECT COUNT(DISTINCT log_date) FROM gate_logs WHERE log_date BETWEEN ? AND ?',
                [$sy['start_date'], today()]
            );
        }
        return $this->school_days;
    }

    private function select_sql()
    {
        return "SELECT s.*, sec.grade, sec.name AS section_name,
                  (SELECT AVG(g.final_grade) FROM grades g WHERE g.student_id = s.id AND g.school_year_id = ?) AS average,
                  (SELECT COUNT(DISTINCT gl.log_date) FROM gate_logs gl
                    WHERE gl.student_id = s.id AND gl.event = 'Time-in' AND gl.log_date BETWEEN ? AND ?) AS days_present,
                  (SELECT COUNT(*) FROM activities a
                    WHERE a.section_id = s.section_id AND a.deadline < ?
                      AND NOT EXISTS (SELECT 1 FROM submissions sb WHERE sb.activity_id = a.id AND sb.student_id = s.id)) AS missing,
                  (SELECT COUNT(*) FROM grades g WHERE g.student_id = s.id AND g.school_year_id = ?
                      AND ((g.q1 > g.q2 AND g.q2 > g.q3) OR (g.q2 > g.q3 AND g.q3 > g.q4))) AS declining,
                  (SELECT COUNT(*) FROM grades g WHERE g.student_id = s.id AND g.school_year_id = ? AND g.final_grade < 80) AS low_subjects,
                  (SELECT COUNT(DISTINCT d.log_date) FROM gate_logs d WHERE d.event = 'Time-in'
                      AND d.log_date > COALESCE((SELECT MAX(x.log_date) FROM gate_logs x WHERE x.student_id = s.id AND x.event = 'Time-in'), '1970-01-01')) AS absent_streak
                FROM students s LEFT JOIN sections sec ON sec.id = s.section_id";
    }

    private function select_params()
    {
        $sy = $this->sy();
        return [$sy['id'], $sy['start_date'], today(), today(), $sy['id'], $sy['id']];
    }

    /** Add average / attendance / missing / standing / initials to a raw row. */
    public function enrich(array $r)
    {
        $days = $this->school_days();
        $r['average']    = $r['average'] === null ? null : round((float) $r['average'], 1);
        $r['attendance'] = $days > 0 ? round(min(100, 100 * (int) $r['days_present'] / $days), 1) : null;
        $r['missing']    = (int) $r['missing'];
        $r['standing']   = Standing::evaluate($r['average'], $r['attendance'], $r['missing'], config_item('standing_rules'),
            ['declining' => (int) $r['declining'], 'low_subjects' => (int) $r['low_subjects'], 'absent_streak' => (int) $r['absent_streak']]);
        $r['initials']   = initials($r['full_name']);
        $r['section_label'] = $r['section_name'] ? grade_section($r['grade'], $r['section_name']) : 'Unassigned';
        $r['section_key']   = $r['section_name'] ? $r['grade'] . '-' . $r['section_name'] : null;
        return $r;
    }

    public function find($id)
    {
        $r = $this->row($this->select_sql() . ' WHERE s.id = ?', array_merge($this->select_params(), [(int) $id]));
        return $r ? $this->enrich($r) : null;
    }

    public function find_by_no($no)
    {
        $r = $this->row($this->select_sql() . ' WHERE s.student_no = ?', array_merge($this->select_params(), [$no]));
        return $r ? $this->enrich($r) : null;
    }

    // ------------------------------------------------------------------ lists
    /**
     * @param array $f  q, grade, section_id, standing(level), status
     * @return array ['rows'=>[], 'total'=>int]
     */
    public function search(array $user, array $f = [], $page = 1, $per = 15)
    {
        list($scope, $sp) = $this->scope($user);
        $where = ["($scope)"];
        $p     = $sp;
        if (!empty($f['q'])) {
            $where[] = '(s.full_name LIKE ? OR s.student_no LIKE ? OR sec.name LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($p, $like, $like, $like);
        }
        if (!empty($f['grade'])) {
            $where[] = 'sec.grade = ?';
            $p[] = (int) $f['grade'];
        }
        if (!empty($f['section_id'])) {
            $where[] = 's.section_id = ?';
            $p[] = (int) $f['section_id'];
        }
        if (empty($f['all_status'])) {
            $where[] = "s.enrollment_status = 'Enrolled'";
        }
        $w = ' WHERE ' . implode(' AND ', $where);

        $total = (int) $this->val(
            'SELECT COUNT(*) FROM students s LEFT JOIN sections sec ON sec.id = s.section_id' . $w, $p
        );
        list($page, $per, $off) = $this->page($page, $per, 5000);
        $rows = $this->rows(
            $this->select_sql() . $w . " ORDER BY sec.grade, sec.name, s.full_name LIMIT $per OFFSET $off",
            array_merge($this->select_params(), $p)
        );
        return ['rows' => array_map([$this, 'enrich'], $rows), 'total' => $total];
    }

    /** All visible students (no paging) - used for dropdowns, exports and reports. */
    public function all(array $user, array $f = [])
    {
        return $this->search($user, $f, 1, 5000)['rows'];
    }

    /** Students whose indicators call for a human look (insights page). */
    public function for_review(array $user)
    {
        $out = [];
        foreach ($this->all($user) as $s) {
            if ($s['standing']['level'] > 0 || $s['missing'] > 0) {
                $out[] = $s;
            }
        }
        usort($out, function ($a, $b) {
            return [$b['standing']['level'], $b['missing']] <=> [$a['standing']['level'], $a['missing']];
        });
        return $out;
    }

    // ------------------------------------------------------------------ school-wide figures
    public function counts()
    {
        $sy = $this->sy()['id'];
        $r = $this->row(
            "SELECT COUNT(*) AS total,
                    SUM(sec.grade BETWEEN 7 AND 10) AS jhs,
                    SUM(sec.grade BETWEEN 11 AND 12) AS shs,
                    SUM(s.section_id IS NULL OR s.guardian_name IS NULL OR s.guardian_name = '' OR s.email IS NULL OR s.email = '') AS incomplete
               FROM students s LEFT JOIN sections sec ON sec.id = s.section_id
              WHERE s.enrollment_status = 'Enrolled' AND (sec.school_year_id = ? OR s.section_id IS NULL)",
            [$sy]
        );
        return ['total' => (int) $r['total'], 'jhs' => (int) $r['jhs'], 'shs' => (int) $r['shs'], 'incomplete' => (int) $r['incomplete']];
    }

    /** Standing distribution for the dashboard donut: [label => n]. */
    public function standing_distribution()
    {
        $d = [Standing::GOOD => 0, Standing::REVIEW => 0, Standing::SUPPORT => 0];
        $pending = 0;
        foreach ($this->all(['role' => 'Administrator']) as $s) {
            if ($s['average'] === null) { $pending++; continue; }
            $d[$s['standing']['label']]++;
        }
        $d['Pending records'] = $pending;
        return $d;
    }

    public function school_average()
    {
        $v = $this->val('SELECT AVG(final_grade) FROM grades WHERE school_year_id = ?', [$this->sy()['id']]);
        return $v === null ? null : round((float) $v, 1);
    }

    // ------------------------------------------------------------------ writes
    public function update_contact($id, array $d)
    {
        $allowed = array_intersect_key($d, array_flip(['email', 'phone', 'address', 'guardian_name', 'guardian_phone', 'birth_date']));
        $allowed['updated_at'] = now();
        $this->update_row('students', (int) $id, $allowed);
        return $this->ok('Student record updated.');
    }

    /** Next student number for the current school year: ANHS-2026-007. */
    public function next_student_no()
    {
        $year = substr($this->sy()['label'], 0, 4);
        $prefix = config_item('school_short') . '-' . $year . '-';
        $max = (int) $this->val('SELECT MAX(CAST(SUBSTRING(student_no, ?) AS UNSIGNED)) FROM students WHERE student_no LIKE ?',
            [strlen($prefix) + 1, $prefix . '%']);
        return $prefix . str_pad($max + 1, 3, '0', STR_PAD_LEFT);
    }
}
