<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/** Grades - academic records overview and grade encoding for the teacher of a class. */
class Grades extends Base_controller
{
    private function may_encode($section_id, $subject_id)
    {
        if ($this->user['role'] === 'Administrator') { return true; }
        return $this->user['role'] === 'Teacher' && $this->teachers->teaches($this->user['teacher_id'], $section_id, $subject_id);
    }

    private function classes()
    {
        if ($this->user['role'] === 'Teacher') {
            return $this->teachers->assignments($this->user['teacher_id']);
        }
        $out = [];
        foreach ($this->school->sections() as $sec) {
            foreach ($this->school->section_subjects($sec['id']) as $ca) {
                $out[] = $ca + ['grade' => $sec['grade'], 'section_name' => $sec['name']];
            }
        }
        return $out;
    }

    public function index()
    {
        $sy = $this->school->sy();
        $section_id = (int) $this->query('section');
        $subject_id = (int) $this->query('subject');
        $sheet = null;

        if ($section_id && $subject_id) {
            $assigned = $this->val_assigned($section_id, $subject_id);
            if (!$assigned) { $this->not_found('class'); }
            if ($this->user['role'] === 'Teacher' && !$this->teachers->has_section($this->user['teacher_id'], $section_id)) { $this->forbidden(); }
            $sheet = [
                'section' => $this->school->section($section_id),
                'subject' => $this->school->subject($subject_id),
                'rows'    => $this->grades->class_sheet($section_id, $subject_id, $sy['id']),
                'can_encode' => $this->may_encode($section_id, $subject_id),
            ];
        }

        $this->render('grades/index', [
            'sy'      => $sy,
            'classes' => $this->classes(),
            'sheet'   => $sheet,
            'pass'    => (int) config_item('passing_grade'),
            'support' => (int) config_item('support_grade_below'),
            'standings' => $this->user['role'] === 'Administrator' ? $this->students->standing_distribution() : null,
            'average'   => $this->students->school_average(),
        ], 'Academic Records');
    }

    private function val_assigned($section_id, $subject_id)
    {
        foreach ($this->school->section_subjects($section_id) as $ca) {
            if ((int) $ca['subject_id'] === (int) $subject_id) { return true; }
        }
        return false;
    }

    public function save()
    {
        $section_id = (int) $this->post('section_id');
        $subject_id = (int) $this->post('subject_id');
        $back = 'grades?' . http_build_query(['section' => $section_id, 'subject' => $subject_id]);
        if (!$this->val_assigned($section_id, $subject_id) || !$this->may_encode($section_id, $subject_id)) { $this->forbidden(); }

        $sy = $this->school->sy();
        $input = ($_POST['g'] ?? null);
        if (!is_array($input)) { $this->respond(['ok' => false, 'error' => 'No grades were submitted.'], $back); }

        $roster = array_column($this->grades->class_sheet($section_id, $subject_id, $sy['id']), null, 'student_id');
        $saved = 0;
        $this->db->transaction();
        try {
            foreach ($input as $sid => $q) {
                $sid = (int) $sid;
                if (!isset($roster[$sid]) || !is_array($q)) { continue; }
                $res = $this->grades->save($sid, $subject_id, $sy['id'], [
                    'q1' => $q['q1'] ?? null, 'q2' => $q['q2'] ?? null, 'q3' => $q['q3'] ?? null, 'q4' => $q['q4'] ?? null,
                ], $this->user['id']);
                if (!$res['ok']) {
                    $this->db->roll_back();
                    $this->respond(['ok' => false, 'error' => $roster[$sid]['full_name'] . ': ' . $res['error']], $back);
                }
                $saved++;
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->roll_back();
            throw $e;
        }
        $this->audit('grades.save', 'section', $section_id, "subject {$subject_id}: {$saved} learner(s)");
        $this->respond(['ok' => true, 'message' => "Grades saved for {$saved} learner" . ($saved === 1 ? '' : 's') . '.'], $back);
    }
}
