<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'models/Campus_model.php';

/**
 * Notice_model - notifications with audience targeting and per-user read state.
 *
 * A notice may be limited by role(s), by one student and/or by one section.
 * Administrators see everything; see can_see() for the exact rules.
 */
class Notice_model extends Campus_model
{
    const CATEGORIES = ['Attendance', 'Academic', 'Announcement', 'Services', 'Guardian', 'Enrollment'];
    private $ctx_cache = [];

    /**
     * Publish a notice.
     * @param array $aud ['roles'=>[..], 'student_id'=>int, 'section_id'=>int]
     */
    public function publish($title, $body, $category, array $aud = [], $by = null)
    {
        if (!in_array($category, self::CATEGORIES, true)) { $category = 'Announcement'; }
        return $this->insert_row('notices', [
            'title' => mb_substr(trim($title), 0, 160), 'body' => trim($body), 'category' => $category,
            'aud_roles' => !empty($aud['roles']) ? implode(',', $aud['roles']) : null,
            'aud_student_id' => !empty($aud['student_id']) ? (int) $aud['student_id'] : null,
            'aud_section_id' => !empty($aud['section_id']) ? (int) $aud['section_id'] : null,
            'created_by' => $by, 'created_at' => now(),
        ]);
    }

    /** Extra facts needed to evaluate audiences for one user. */
    private function context(array $user)
    {
        $k = $user['id'];
        if (!isset($this->ctx_cache[$k])) {
            $ctx = ['student_section' => null, 'teacher_sections' => []];
            if ($user['role'] === 'Student' && $user['student_id']) {
                $ctx['student_section'] = $this->val('SELECT section_id FROM students WHERE id = ?', [(int) $user['student_id']]);
            }
            if ($user['role'] === 'Teacher' && $user['teacher_id']) {
                $this->call->model('Teacher_model');
                $ctx['teacher_sections'] = $this->Teacher_model->section_ids($user['teacher_id']);
            }
            $this->ctx_cache[$k] = $ctx;
        }
        return $this->ctx_cache[$k];
    }

    public function can_see(array $n, array $user)
    {
        if ($n['aud_roles'] === null && $n['aud_student_id'] === null && $n['aud_section_id'] === null) { return true; }
        if ($user['role'] === 'Administrator') { return true; }
        if ($n['aud_roles'] !== null && !in_array($user['role'], explode(',', $n['aud_roles']), true)) { return false; }
        $ctx = $this->context($user);
        if ($user['role'] === 'Student') {
            if ($n['aud_student_id'] !== null && (int) $n['aud_student_id'] !== (int) $user['student_id']) { return false; }
            if ($n['aud_section_id'] !== null && (int) $n['aud_section_id'] !== (int) $ctx['student_section']) { return false; }
            return true;
        }
        if ($user['role'] === 'Teacher') {
            if ($n['aud_section_id'] !== null && !in_array((int) $n['aud_section_id'], $ctx['teacher_sections'], true)) { return false; }
            if ($n['aud_student_id'] !== null) {
                return $n['aud_student_section'] !== null && in_array((int) $n['aud_student_section'], $ctx['teacher_sections'], true);
            }
        }
        return true;
    }

    /** Newest-first notices visible to the user, with `is_read` flag. */
    public function feed(array $user, $category = null, $limit = 300)
    {
        $sql = 'SELECT n.*, st.section_id AS aud_student_section, (nr.user_id IS NOT NULL) AS is_read,
                       sec.grade AS aud_grade, sec.name AS aud_section_name
                  FROM notices n
             LEFT JOIN students st ON st.id = n.aud_student_id
             LEFT JOIN sections sec ON sec.id = n.aud_section_id
             LEFT JOIN notice_reads nr ON nr.notice_id = n.id AND nr.user_id = ?';
        $p = [(int) $user['id']];
        if ($category) { $sql .= ' WHERE n.category = ?'; $p[] = $category; }
        $sql .= ' ORDER BY n.created_at DESC, n.id DESC LIMIT ' . max(1, (int) $limit);
        $out = [];
        foreach ($this->rows($sql, $p) as $n) {
            if ($this->can_see($n, $user)) { $out[] = $n; }
        }
        return $out;
    }

    public function unread_count(array $user)
    {
        static $cache = [];
        if (!isset($cache[$user['id']])) {
            $n = 0;
            foreach ($this->feed($user, null, 150) as $x) { if (!(int) $x['is_read']) { $n++; } }
            $cache[$user['id']] = $n;
        }
        return $cache[$user['id']];
    }

    public function recent(array $user, $n = 4)
    {
        return array_slice($this->feed($user, null, 60), 0, $n);
    }

    public function mark_read(array $user, $notice_id)
    {
        $n = $this->row('SELECT n.*, st.section_id AS aud_student_section FROM notices n LEFT JOIN students st ON st.id = n.aud_student_id WHERE n.id = ?', [(int) $notice_id]);
        if (!$n || !$this->can_see($n, $user)) { return false; }
        $this->run('INSERT IGNORE INTO notice_reads (notice_id, user_id, read_at) VALUES (?,?,?)', [(int) $notice_id, (int) $user['id'], now()]);
        return true;
    }

    public function mark_all_read(array $user)
    {
        $c = 0;
        foreach ($this->feed($user, null, 500) as $n) {
            if (!(int) $n['is_read']) {
                $this->run('INSERT IGNORE INTO notice_reads (notice_id, user_id, read_at) VALUES (?,?,?)', [(int) $n['id'], (int) $user['id'], now()]);
                $c++;
            }
        }
        return $c;
    }

    /** Human readable audience label, e.g. "Grade 10 – Rizal" or "Teacher, Guidance". */
    public static function audience_label(array $n)
    {
        $bits = [];
        if ($n['aud_section_name']) { $bits[] = grade_section($n['aud_grade'], $n['aud_section_name']); }
        if ($n['aud_student_id'])   { $bits[] = 'One student'; }
        if ($n['aud_roles'])        { $bits[] = str_replace(',', ', ', $n['aud_roles']); }
        return $bits ? implode(' · ', $bits) : 'Everyone';
    }
}
