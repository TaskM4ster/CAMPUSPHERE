<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'models/Campus_model.php';

/** Teacher_model - teachers and their class assignments (the basis of row-level access). */
class Teacher_model extends Campus_model
{
    public function count_all()
    {
        return (int) $this->val('SELECT COUNT(*) FROM teachers');
    }

    public function find($id)
    {
        return $this->row('SELECT * FROM teachers WHERE id = ?', [(int) $id]);
    }

    public function all()
    {
        return $this->rows('SELECT * FROM teachers ORDER BY full_name');
    }

    /** Sections a teacher teaches in OR advises (current school year). */
    public function section_ids($teacher_id)
    {
        $sy = $this->sy()['id'];
        $rows = $this->rows(
            'SELECT ca.section_id AS id FROM class_assignments ca JOIN sections s ON s.id = ca.section_id
              WHERE ca.teacher_id = ? AND s.school_year_id = ?
             UNION
             SELECT id FROM sections WHERE adviser_id = ? AND school_year_id = ?',
            [$teacher_id, $sy, $teacher_id, $sy]
        );
        return array_map('intval', array_column($rows, 'id'));
    }

    public function sections($teacher_id)
    {
        $ids = $this->section_ids($teacher_id);
        if (!$ids) { return []; }
        $in = implode(',', array_fill(0, count($ids), '?'));
        return $this->rows(
            "SELECT sec.*, (SELECT COUNT(*) FROM students s WHERE s.section_id = sec.id AND s.enrollment_status = 'Enrolled') AS headcount,
                    (sec.adviser_id = ?) AS is_adviser
               FROM sections sec WHERE sec.id IN ($in) ORDER BY sec.grade, sec.name",
            array_merge([$teacher_id], $ids)
        );
    }

    /** Subjects this teacher is assigned to, optionally within one section. */
    public function assignments($teacher_id, $section_id = null)
    {
        $sql = 'SELECT ca.*, sub.name AS subject_name, sec.grade, sec.name AS section_name
                  FROM class_assignments ca
                  JOIN subjects sub ON sub.id = ca.subject_id
                  JOIN sections sec ON sec.id = ca.section_id
                 WHERE ca.teacher_id = ? AND sec.school_year_id = ?';
        $p = [$teacher_id, $this->sy()['id']];
        if ($section_id) {
            $sql .= ' AND ca.section_id = ?';
            $p[] = (int) $section_id;
        }
        return $this->rows($sql . ' ORDER BY sec.grade, sec.name, ca.period_start', $p);
    }

    public function teaches($teacher_id, $section_id, $subject_id)
    {
        return (bool) $this->val(
            'SELECT 1 FROM class_assignments WHERE teacher_id = ? AND section_id = ? AND subject_id = ?',
            [(int) $teacher_id, (int) $section_id, (int) $subject_id]
        );
    }

    public function advises($teacher_id, $section_id)
    {
        return (bool) $this->val('SELECT 1 FROM sections WHERE id = ? AND adviser_id = ?', [(int) $section_id, (int) $teacher_id]);
    }

    /** May this teacher see anything of this section? */
    public function has_section($teacher_id, $section_id)
    {
        return in_array((int) $section_id, $this->section_ids($teacher_id), true);
    }
}
