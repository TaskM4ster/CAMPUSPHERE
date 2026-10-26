<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/** Activities - teacher-published tasks, student file submissions, scoring and feedback. */
class Activities extends Base_controller
{
    public function index()
    {
        $role = $this->user['role'];
        $list = $this->activities->list_for($this->user);
        $rows = [];
        foreach ($list as $a) {
            $rows[] = ['a' => $a, 'view' => $role === 'Student' ? $this->activities->student_view($a, $this->user['student_id']) : null];
        }
        $this->render('activities/index', [
            'rows'   => $rows,
            'is_teacher' => $role === 'Teacher',
            'classes' => $role === 'Teacher' ? $this->teachers->assignments($this->user['teacher_id']) : [],
            'today'  => today(),
            'to_check' => $role === 'Teacher' ? $this->activities->to_check_for_teacher($this->user['teacher_id']) : null,
        ], 'Activities');
    }

    public function create()
    {
        if (!$this->user['teacher_id']) { $this->forbidden(); }
        $in = [
            'title' => $this->post('title'), 'instructions' => $this->post('instructions'), 'deadline' => $this->post('deadline'),
            'max_score' => $this->post('max_score'), 'section_id' => (int) $this->post('section_id'), 'subject_id' => (int) $this->post('subject_id'),
        ];
        // the class picker posts "sectionId:subjectId"
        if (strpos($this->post('class'), ':') !== false) {
            list($in['section_id'], $in['subject_id']) = array_map('intval', explode(':', $this->post('class'), 2));
        }
        $res = $this->activities->create($this->user, $in);
        if (!empty($res['ok'])) {
            $a = $res['activity'];
            $this->audit('activity.create', 'activity', $res['id'], $a['title']);
            $this->notices->publish('New activity: ' . $a['title'], $a['subject_name'] . ' — due ' . fmt_date($a['deadline']) . '. ' . $a['instructions'],
                'Academic', ['roles' => ['Student'], 'section_id' => (int) $a['section_id']], $this->user['id']);
        }
        $this->respond($res, 'activities', ['title', 'instructions', 'deadline', 'max_score', 'class']);
    }

    public function show($id)
    {
        $a = $this->activities->find((int) $id);
        if (!$a || !$this->activities->can_view($this->user, $a)) { $this->not_found('activity'); }
        $role = $this->user['role'];
        $this->render('activities/show', [
            'a'       => $a,
            'view'    => $role === 'Student' ? $this->activities->student_view($a, $this->user['student_id']) : null,
            'roster'  => $role !== 'Student' ? $this->activities->roster($a['id']) : [],
            'can_grade' => $this->activities->can_grade($this->user, $a),
            'today'   => today(),
            'max_mb'  => round(config_item('upload_max_bytes') / 1048576),
            'ext'     => config_item('upload_extensions'),
        ], $a['title'], '/activities');
    }

    // ------------------------------------------------------------------ student upload
    public function submit($id)
    {
        $id = (int) $id;
        $back = 'activities/' . $id;
        $f = $_FILES['work'] ?? null;
        if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $this->respond(['ok' => false, 'error' => 'Choose a file before submitting your work.'], $back);
        }
        $stored = $this->store_upload($f, $err);
        if ($stored === null) { $this->respond(['ok' => false, 'error' => $err], $back); }

        $res = $this->activities->submit($this->user, $id, ['name' => $stored['name'], 'path' => $stored['path']]);
        if (empty($res['ok'])) {
            @unlink(config_item('upload_dir') . $stored['path']);
        } else {
            if (!empty($res['previous_file'])) { @unlink(config_item('upload_dir') . basename($res['previous_file'])); }
            $this->audit('submission.submit', 'activity', $id, $stored['name']);
        }
        $this->respond($res, $back);
    }

    /**
     * Validate and move an uploaded file. Returns ['name' => original, 'path' => stored] or null (+ $err).
     * Extension allow-list, size cap and magic-byte check; stored under a random name outside the web root.
     */
    private function store_upload(array $f, &$err)
    {
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $err = $f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE ? 'The file is too large.' : 'The upload failed. Please try again.';
            return null;
        }
        if (!is_uploaded_file($f['tmp_name'])) { $err = 'Invalid upload.'; return null; }
        if ($f['size'] > config_item('upload_max_bytes')) { $err = 'The file is larger than ' . round(config_item('upload_max_bytes') / 1048576) . ' MB.'; return null; }
        $orig = preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', basename((string) $f['name']));
        $orig = mb_substr($orig, -120);
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, config_item('upload_extensions'), true)) {
            $err = 'Allowed file types: ' . strtoupper(implode(', ', config_item('upload_extensions'))) . '.';
            return null;
        }
        $head = file_get_contents($f['tmp_name'], false, null, 0, 8);
        $ok = [
            'pdf' => strncmp($head, '%PDF', 4) === 0, 'png' => strncmp($head, "\x89PNG", 4) === 0,
            'jpg' => strncmp($head, "\xFF\xD8\xFF", 3) === 0, 'jpeg' => strncmp($head, "\xFF\xD8\xFF", 3) === 0,
            'docx' => strncmp($head, 'PK', 2) === 0, 'doc' => strncmp($head, "\xD0\xCF\x11\xE0", 4) === 0,
        ];
        if (empty($ok[$ext])) { $err = 'The file content does not match its type.'; return null; }
        $dir = config_item('upload_dir');
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) { $err = 'The upload folder is not writable.'; return null; }
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dir . $stored)) { $err = 'The file could not be saved.'; return null; }
        @chmod($dir . $stored, 0640);
        return ['name' => $orig ?: ('work.' . $ext), 'path' => $stored];
    }

    // ------------------------------------------------------------------ teacher scoring
    public function grade($id)
    {
        $id = (int) $id;
        $res = $this->activities->grade($this->user, $id, (int) $this->post('student_id'), $this->post('score'), $this->post('feedback'));
        if (!empty($res['ok'])) {
            $a = $res['activity'];
            $this->audit('submission.grade', 'activity', $id, 'student ' . (int) $this->post('student_id') . ' score ' . $this->post('score'));
            $this->notices->publish('Score posted: ' . $a['title'], 'Your teacher recorded a score and feedback for ' . $a['title'] . '.',
                'Academic', ['roles' => ['Student'], 'student_id' => (int) $this->post('student_id')], $this->user['id']);
        }
        $this->respond($res, 'activities/' . $id);
    }

    // ------------------------------------------------------------------ download (authorised, never a public URL)
    public function file($id, $student_id)
    {
        $a = $this->activities->find((int) $id);
        if (!$a || !$this->activities->can_view($this->user, $a)) { $this->not_found('file'); }
        $student_id = (int) $student_id;
        if ($this->user['role'] === 'Student' && (int) $this->user['student_id'] !== $student_id) { $this->forbidden(); }
        $s = $this->activities->submission($a['id'], $student_id);
        $path = $s ? config_item('upload_dir') . basename($s['file_path']) : null;
        if (!$s || !is_file($path)) { $this->not_found('file'); }
        $this->audit('submission.download', 'activity', $a['id'], 'student ' . $student_id);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $s['file_name']) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
