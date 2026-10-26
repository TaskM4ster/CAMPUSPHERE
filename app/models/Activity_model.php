<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'models/Campus_model.php';

/** Activity_model - teacher activities, student submissions, scores and feedback. */
class Activity_model extends Campus_model
{
    private function base()
    {
        return "SELECT a.*, sub.name AS subject_name, t.full_name AS teacher_name, sec.grade, sec.name AS section_name,
                       (SELECT COUNT(*) FROM students s WHERE s.section_id = a.section_id AND s.enrollment_status = 'Enrolled') AS class_size,
                       (SELECT COUNT(*) FROM submissions sb WHERE sb.activity_id = a.id) AS submitted_count,
                       (SELECT COUNT(*) FROM submissions sb WHERE sb.activity_id = a.id AND sb.status = 'Submitted') AS to_check
                  FROM activities a
                  JOIN subjects sub ON sub.id = a.subject_id
                  JOIN teachers t ON t.id = a.teacher_id
                  JOIN sections sec ON sec.id = a.section_id";
    }

    /** Activities visible to a user, newest deadline first. */
    public function list_for(array $user)
    {
        switch ($user['role']) {
            case 'Administrator':
                return $this->rows($this->base() . ' ORDER BY a.deadline DESC, a.id DESC');
            case 'Teacher':
                return $this->rows(
                    $this->base() . ' WHERE a.teacher_id = ? OR sec.adviser_id = ? ORDER BY a.deadline DESC, a.id DESC',
                    [(int) $user['teacher_id'], (int) $user['teacher_id']]
                );
            case 'Student':
                return $this->rows(
                    $this->base() . ' WHERE a.section_id = (SELECT section_id FROM students WHERE id = ?) ORDER BY a.deadline DESC, a.id DESC',
                    [(int) $user['student_id']]
                );
        }
        return [];
    }

    public function find($id)
    {
        return $this->row($this->base() . ' WHERE a.id = ?', [(int) $id]);
    }

    public function can_view(array $user, array $a)
    {
        switch ($user['role']) {
            case 'Administrator': return true;
            case 'Teacher':
                return (int) $a['teacher_id'] === (int) $user['teacher_id']
                    || (bool) $this->val('SELECT 1 FROM sections WHERE id = ? AND adviser_id = ?', [$a['section_id'], (int) $user['teacher_id']]);
            case 'Student':
                return (bool) $this->val('SELECT 1 FROM students WHERE id = ? AND section_id = ?', [(int) $user['student_id'], $a['section_id']]);
        }
        return false;
    }

    /** Only the owning teacher records scores. */
    public function can_grade(array $user, array $a)
    {
        return $user['role'] === 'Teacher' && (int) $a['teacher_id'] === (int) $user['teacher_id'];
    }

    public function submission($activity_id, $student_id)
    {
        return $this->row('SELECT * FROM submissions WHERE activity_id = ? AND student_id = ?', [(int) $activity_id, (int) $student_id]);
    }

    /** View model for one student/activity pair (mirrors the prototype's submissionView). */
    public function student_view(array $a, $student_id)
    {
        $s = $this->submission($a['id'], $student_id);
        return [
            'status'       => $s ? $s['status'] : 'Not submitted',
            'score'        => $s && $s['score'] !== null ? rtrim(rtrim(number_format((float) $s['score'], 2), '0'), '.') . ' / ' . $a['max_score'] : '—',
            'feedback'     => $s && $s['feedback'] ? $s['feedback'] : 'Feedback will be available after review.',
            'submitted_at' => $s ? $s['submitted_at'] : null,
            'file'         => $s ? $s['file_name'] : null,
            'late'         => $s ? (substr($s['submitted_at'], 0, 10) > $a['deadline']) : false,
            'row'          => $s,
        ];
    }

    /** Every student of the section with their submission (or none). */
    public function roster($activity_id)
    {
        return $this->rows(
            "SELECT s.id AS student_id, s.student_no, s.full_name, sb.status, sb.file_name, sb.submitted_at, sb.score, sb.feedback
               FROM activities a
               JOIN students s ON s.section_id = a.section_id AND s.enrollment_status = 'Enrolled'
          LEFT JOIN submissions sb ON sb.activity_id = a.id AND sb.student_id = s.id
              WHERE a.id = ? ORDER BY (sb.id IS NULL), sb.status, s.full_name",
            [(int) $activity_id]
        );
    }

    public function pending_for_student($student_id)
    {
        return (int) $this->val(
            'SELECT COUNT(*) FROM activities a JOIN students s ON s.section_id = a.section_id
              WHERE s.id = ? AND NOT EXISTS (SELECT 1 FROM submissions sb WHERE sb.activity_id = a.id AND sb.student_id = s.id)',
            [(int) $student_id]
        );
    }

    public function to_check_for_teacher($teacher_id)
    {
        return (int) $this->val(
            "SELECT COUNT(*) FROM submissions sb JOIN activities a ON a.id = sb.activity_id WHERE a.teacher_id = ? AND sb.status = 'Submitted'",
            [(int) $teacher_id]
        );
    }

    public function create(array $user, array $in)
    {
        if ($user['role'] !== 'Teacher') { return $this->fail('Only teachers can publish activities.'); }
        $title = trim($in['title'] ?? '');
        if ($title === '' || mb_strlen($title) > 160) { return $this->fail('Activity title is required (max 160 characters).'); }
        $instructions = trim($in['instructions'] ?? '');
        if ($instructions === '') { return $this->fail('Instructions are required.'); }
        $deadline = $in['deadline'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline) || !strtotime($deadline)) { return $this->fail('Choose a valid deadline.'); }
        if ($deadline < today()) { return $this->fail('The deadline cannot be in the past.'); }
        $max = (int) ($in['max_score'] ?? 25);
        if ($max < 1 || $max > 100) { return $this->fail('Maximum score must be from 1 to 100.'); }
        $section_id = (int) ($in['section_id'] ?? 0); $subject_id = (int) ($in['subject_id'] ?? 0);
        if (!$this->val('SELECT 1 FROM class_assignments WHERE teacher_id = ? AND section_id = ? AND subject_id = ?', [(int) $user['teacher_id'], $section_id, $subject_id])) {
            return $this->fail('You can only publish activities for the classes you teach.');
        }
        $id = $this->insert_row('activities', [
            'title' => $title, 'subject_id' => $subject_id, 'teacher_id' => (int) $user['teacher_id'], 'section_id' => $section_id,
            'deadline' => $deadline, 'instructions' => $instructions, 'max_score' => $max, 'created_at' => now(),
        ]);
        return $this->ok('Activity published.', ['id' => $id, 'activity' => $this->find($id)]);
    }

    /**
     * Record a student's submission.
     * @param array $file ['name'=>original, 'path'=>stored file name]
     */
    public function submit(array $user, $activity_id, array $file)
    {
        if ($user['role'] !== 'Student' || !$user['student_id']) { return $this->fail('Only students can submit their own work.'); }
        $a = $this->find($activity_id);
        if (!$a) { return $this->fail('Activity not found.'); }
        if (!$this->can_view($user, $a)) { return $this->fail('This activity is not assigned to your class.'); }
        if (empty($file['name']) || empty($file['path'])) { return $this->fail('Choose a file before submitting your work.'); }
        $prev = $this->submission($a['id'], $user['student_id']);
        if ($prev && $prev['status'] === 'Graded') { return $this->fail('This work has already been graded.'); }
        if ($prev) {
            $this->update_row('submissions', $prev['id'], ['file_name' => $file['name'], 'file_path' => $file['path'], 'submitted_at' => now()]);
        } else {
            $this->insert_row('submissions', [
                'activity_id' => $a['id'], 'student_id' => (int) $user['student_id'], 'status' => 'Submitted',
                'file_name' => $file['name'], 'file_path' => $file['path'], 'submitted_at' => now(),
            ]);
        }
        return $this->ok("{$file['name']} submitted for {$a['title']}.", ['activity' => $a, 'previous_file' => $prev ? $prev['file_path'] : null]);
    }

    public function grade(array $user, $activity_id, $student_id, $score, $feedback)
    {
        $a = $this->find($activity_id);
        if (!$a) { return $this->fail('Activity not found.'); }
        if (!$this->can_grade($user, $a)) { return $this->fail('Only the class teacher can record scores.'); }
        $sub = $this->submission($a['id'], $student_id);
        if (!$sub) { return $this->fail('This student has not submitted work yet.'); }
        if (!is_numeric($score) || $score < 0 || $score > $a['max_score']) { return $this->fail("Score must be from 0 to {$a['max_score']}."); }
        $feedback = trim((string) $feedback);
        if ($feedback === '') { return $this->fail('Feedback is required.'); }
        $this->update_row('submissions', $sub['id'], [
            'status' => 'Graded', 'score' => round((float) $score, 2), 'feedback' => $feedback,
            'graded_by' => $user['id'], 'graded_at' => now(),
        ]);
        return $this->ok('Score and feedback saved.', ['activity' => $a]);
    }
}
