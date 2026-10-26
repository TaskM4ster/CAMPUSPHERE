<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'models/Campus_model.php';

/** School_model - school years, settings, subjects and sections. */
class School_model extends Campus_model
{
    public function years()
    {
        return $this->rows('SELECT * FROM school_years ORDER BY start_date DESC');
    }

    public function year($id)
    {
        return $this->row('SELECT * FROM school_years WHERE id = ?', [(int) $id]);
    }

    public function year_by_label($label)
    {
        return $this->row('SELECT * FROM school_years WHERE label = ?', [$label]);
    }

    public function setting($key, $default = null)
    {
        $v = $this->val('SELECT setting_value FROM settings WHERE setting_key = ?', [$key]);
        return $v === null ? $default : $v;
    }

    public function subjects()
    {
        return $this->rows('SELECT * FROM subjects ORDER BY id');
    }

    public function subject($id)
    {
        return $this->row('SELECT * FROM subjects WHERE id = ?', [(int) $id]);
    }

    /** Sections of the active year with adviser name and enrolled head count. */
    public function sections($sy_id = null, $grade = null)
    {
        $sy_id = $sy_id ?: $this->sy()['id'];
        $sql = 'SELECT sec.*, t.full_name AS adviser_name,
                       (SELECT COUNT(*) FROM students s WHERE s.section_id = sec.id AND s.enrollment_status = \'Enrolled\') AS headcount
                  FROM sections sec LEFT JOIN teachers t ON t.id = sec.adviser_id
                 WHERE sec.school_year_id = ?';
        $p = [$sy_id];
        if ($grade) {
            $sql .= ' AND sec.grade = ?';
            $p[] = (int) $grade;
        }
        return $this->rows($sql . ' ORDER BY sec.grade, sec.name', $p);
    }

    public function section($id)
    {
        return $this->row(
            'SELECT sec.*, t.full_name AS adviser_name FROM sections sec LEFT JOIN teachers t ON t.id = sec.adviser_id WHERE sec.id = ?',
            [(int) $id]
        );
    }

    /** "10-Rizal" style key used in the original prototype. */
    public static function key(array $section)
    {
        return $section['grade'] . '-' . $section['name'];
    }

    /** Subjects taught in a section with the assigned teacher and period start. */
    public function section_subjects($section_id)
    {
        return $this->rows(
            'SELECT ca.*, sub.name AS subject_name, t.full_name AS teacher_name
               FROM class_assignments ca
               JOIN subjects sub ON sub.id = ca.subject_id
               JOIN teachers t ON t.id = ca.teacher_id
              WHERE ca.section_id = ? ORDER BY ca.period_start, sub.name',
            [(int) $section_id]
        );
    }

    /** Enrolled head count per grade for the active year: [grade => n]. */
    public function grade_counts($sy_id = null)
    {
        $sy_id = $sy_id ?: $this->sy()['id'];
        $out = array_fill_keys(range(7, 12), 0);
        foreach ($this->rows(
            "SELECT sec.grade, COUNT(*) AS n FROM students s JOIN sections sec ON sec.id = s.section_id
              WHERE s.enrollment_status = 'Enrolled' AND sec.school_year_id = ? GROUP BY sec.grade", [$sy_id]) as $r) {
            $out[(int) $r['grade']] = (int) $r['n'];
        }
        return $out;
    }

    public function section_counts_by_grade($sy_id = null)
    {
        $sy_id = $sy_id ?: $this->sy()['id'];
        $out = array_fill_keys(range(7, 12), 0);
        foreach ($this->rows('SELECT grade, COUNT(*) AS n FROM sections WHERE school_year_id = ? GROUP BY grade', [$sy_id]) as $r) {
            $out[(int) $r['grade']] = (int) $r['n'];
        }
        return $out;
    }

    public function history()
    {
        $out = [];
        foreach ($this->rows('SELECT year_start, grade, headcount FROM enrollment_history ORDER BY year_start, grade') as $r) {
            $out[(int) $r['year_start']][(int) $r['grade']] = (int) $r['headcount'];
        }
        return $out;
    }
}
