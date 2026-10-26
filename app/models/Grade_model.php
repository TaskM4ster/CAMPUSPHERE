<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'models/Campus_model.php';
require_once APP_DIR . 'libraries/Grading.php';

/** Grade_model - quarterly grades, report cards and trends. */
class Grade_model extends Campus_model
{
    /** Report card rows (all subjects, even ones without grades yet). */
    public function report_card($student_id, $sy_id)
    {
        return $this->rows(
            'SELECT sub.id AS subject_id, sub.name AS subject, g.q1, g.q2, g.q3, g.q4, g.final_grade, g.remarks
               FROM subjects sub
               JOIN grades g ON g.subject_id = sub.id AND g.student_id = ? AND g.school_year_id = ?
              ORDER BY sub.id',
            [(int) $student_id, (int) $sy_id]
        );
    }

    /** Average of quarter N across subjects, plus one subject (Mathematics) - for the trend chart. */
    public function trend($student_id, $sy_id, $focus_subject = 'Mathematics')
    {
        $out = [];
        foreach ([1, 2, 3, 4] as $q) {
            $avg = $this->val("SELECT AVG(q{$q}) FROM grades WHERE student_id = ? AND school_year_id = ?", [(int) $student_id, (int) $sy_id]);
            $foc = $this->val(
                "SELECT g.q{$q} FROM grades g JOIN subjects s ON s.id = g.subject_id
                  WHERE g.student_id = ? AND g.school_year_id = ? AND s.name = ?",
                [(int) $student_id, (int) $sy_id, $focus_subject]
            );
            $out[] = ['q' => $q, 'average' => $avg === null ? null : round((float) $avg, 1), 'focus' => $foc === null ? null : (float) $foc];
        }
        return $out;
    }

    /** Students of one section with their grades for one subject (grade encoding sheet). */
    public function class_sheet($section_id, $subject_id, $sy_id)
    {
        return $this->rows(
            "SELECT s.id AS student_id, s.student_no, s.full_name, g.q1, g.q2, g.q3, g.q4, g.final_grade, g.remarks
               FROM students s
          LEFT JOIN grades g ON g.student_id = s.id AND g.subject_id = ? AND g.school_year_id = ?
              WHERE s.section_id = ? AND s.enrollment_status = 'Enrolled'
              ORDER BY s.full_name",
            [(int) $subject_id, (int) $sy_id, (int) $section_id]
        );
    }

    /**
     * Save one student's quarter grades for a subject. Final grade and remarks are
     * always computed server-side from the quarters.
     */
    public function save($student_id, $subject_id, $sy_id, array $q, $by_user)
    {
        foreach ($q as $k => $v) {
            if ($v === '' || $v === null) { $q[$k] = null; continue; }
            if (!Grading::valid_score($v)) {
                return $this->fail('Grades must be numbers from 0 to 100.');
            }
            $q[$k] = round((float) $v, 2);
        }
        $final   = Grading::final_grade($q);
        $remarks = Grading::remarks($final, config_item('passing_grade'), config_item('support_grade_below'));
        $existing = $this->val('SELECT id FROM grades WHERE student_id = ? AND subject_id = ? AND school_year_id = ?',
            [(int) $student_id, (int) $subject_id, (int) $sy_id]);
        $data = ['q1' => $q['q1'], 'q2' => $q['q2'], 'q3' => $q['q3'], 'q4' => $q['q4'],
                 'final_grade' => $final, 'remarks' => $remarks, 'encoded_by' => $by_user, 'updated_at' => now()];
        if ($existing) {
            $this->update_row('grades', $existing, $data);
        } else {
            $this->insert_row('grades', $data + ['student_id' => (int) $student_id, 'subject_id' => (int) $subject_id, 'school_year_id' => (int) $sy_id]);
        }
        return $this->ok('Grades saved.', ['final' => $final]);
    }

    /** Subjects below the "support suggested" threshold - for the "needs attention" panel. */
    public function weak_subjects($student_id, $sy_id)
    {
        return $this->rows(
            'SELECT sub.name AS subject, g.final_grade, g.q1, g.q2, g.q3, g.q4
               FROM grades g JOIN subjects sub ON sub.id = g.subject_id
              WHERE g.student_id = ? AND g.school_year_id = ? AND g.final_grade < ?
              ORDER BY g.final_grade',
            [(int) $student_id, (int) $sy_id, config_item('support_grade_below')]
        );
    }

    /** Average final grade per subject for the students a user may see (insights page). */
    public function subject_averages(array $scope, $sy_id)
    {
        list($sw, $sp) = $scope;
        return $this->rows(
            "SELECT sub.name AS subject, ROUND(AVG(g.final_grade), 1) AS average, COUNT(*) AS n,
                    SUM(g.final_grade < ?) AS below_support
               FROM grades g JOIN subjects sub ON sub.id = g.subject_id JOIN students s ON s.id = g.student_id
              WHERE g.school_year_id = ? AND g.final_grade IS NOT NULL AND ($sw)
              GROUP BY sub.id, sub.name ORDER BY average",
            array_merge([config_item('support_grade_below'), (int) $sy_id], $sp)
        );
    }
}
