<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Base_controller - shared plumbing for every authenticated controller.
 *
 *  - one shared PDO connection and lazily-created models (aliases below)
 *  - the signed-in user, flash messages, old-input repopulation
 *  - render(): wraps a page view in layouts/app with navigation, unread count, CSRF
 *  - small guards: require_role(), forbidden(), not_found(), json()
 *
 * Authentication and path-level authorisation happen in SecureMiddleware; controllers add
 * the row-level checks (own section, own record, owning teacher) through the models.
 */
class Base_controller extends Controller
{
    /** @var array|null signed-in user row */
    protected $user = null;

    /** alias => model class */
    private static $models = [
        'school'     => 'School_model',
        'teachers'   => 'Teacher_model',
        'students'   => 'Student_model',
        'grades'     => 'Grade_model',
        'attendance' => 'Attendance_model',
        'gate'       => 'Gate_model',
        'enrollments' => 'Enrollment_model',
        'requests'   => 'Service_request_model',
        'notices'    => 'Notice_model',
        'activities' => 'Activity_model',
        'guidance'   => 'Guidance_model',
        'accounts'   => 'User_model',
        'audit'      => 'Audit_model',
        'reports'    => 'Report_model',
    ];

    public function before_action()
    {
        $this->db = shared_db();
        foreach (self::$models as $alias => $class) {
            $this->call->model($class, $alias);
        }
        $this->user = $this->auth->user();
    }

    // ------------------------------------------------------------------ input
    protected function post($key, $default = '')
    {
        $v = isset($_POST[$key]) ? $_POST[$key] : null;   // Io::post() warns on missing keys
        return $v === null ? $default : (is_array($v) ? $v : trim((string) $v));
    }

    protected function query($key, $default = '')
    {
        $v = isset($_GET[$key]) ? $_GET[$key] : null;     // Io::get() warns on missing keys
        return $v === null || is_array($v) ? $default : trim((string) $v);
    }

    protected function is_post()
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    // ------------------------------------------------------------------ feedback
    protected function flash($type, $msg)
    {
        $this->session->set_flashdata('flash', ['type' => $type, 'msg' => $msg]);
    }

    /** Remember submitted values so a failed form can be re-populated through old(). */
    protected function remember_input(array $keys)
    {
        $old = [];
        foreach ($keys as $k) { $old[$k] = $this->post($k); }
        $this->session->set_flashdata('old', $old);
    }

    /** Flash the result of a model call and redirect. */
    protected function respond(array $result, $to, $keep = [])
    {
        if (!empty($result['ok'])) {
            $this->flash('success', $result['message'] ?? 'Saved.');
        } else {
            $this->flash('error', $result['error'] ?? 'The request could not be completed.');
            if ($keep) { $this->remember_input($keep); }
        }
        go($to);
    }

    protected function audit($action, $entity, $entity_id = null, $details = null)
    {
        $this->audit->log($this->user, $action, $entity, $entity_id, $details);
    }

    // ------------------------------------------------------------------ guards
    protected function require_role($roles)
    {
        if (!$this->user || !in_array($this->user['role'], (array) $roles, true)) {
            $this->forbidden();
        }
    }

    protected function forbidden()
    {
        http_response_code(403);
        $this->call->view('errors/forbidden', [
            'user' => $this->user, 'home' => site_url($this->user ? $this->auth->home() : 'login'),
            'school' => config_item('school_name'),
        ]);
        exit;
    }

    protected function not_found($what = 'page')
    {
        http_response_code(404);
        $this->call->view('errors/not_found', [
            'user' => $this->user, 'home' => site_url($this->user ? $this->auth->home() : 'login'),
            'school' => config_item('school_name'), 'what' => $what,
        ]);
        exit;
    }

    protected function json($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ------------------------------------------------------------------ rendering
    /**
     * Render a page inside the application shell.
     *
     * @param string $view   view path under app/views
     * @param array  $data   view data (views do not inherit controller state, pass everything)
     * @param string $title  page title (<title> and header)
     * @param string $active nav path to highlight (defaults to first URL segment)
     */
    protected function render($view, array $data = [], $title = '', $active = null)
    {
        $path = SecureMiddleware::request_path();
        $nav  = config_item('nav');
        $nav  = isset($nav[$this->user['role']]) ? $nav[$this->user['role']] : [];

        $GLOBALS['__old'] = $this->session->flashdata('old') ?: [];
        $flash = $this->session->flashdata('flash');

        $common = [
            'user'  => $this->user,
            'title' => $title,
        ];
        ob_start();
        $this->call->view($view, array_merge($common, $data));
        $content = ob_get_clean();

        $this->call->view('layouts/app', [
            'content' => $content,
            'title'   => $title,
            'user'    => $this->user,
            'nav'     => $nav,
            'active'  => $active ?: $path,
            'flash'   => is_array($flash) ? $flash : null,
            'unread'  => $this->notices->unread_count($this->user),
            'school'  => config_item('school_name'),
            'system'  => config_item('system_name'),
            'sy'      => $this->school->years() ? $this->students->sy() : null,
        ]);
    }

    /** Common section dropdown data for filters/forms. */
    protected function section_options()
    {
        $out = [];
        foreach ($this->school->sections() as $s) {
            $out[] = ['id' => $s['id'], 'grade' => $s['grade'], 'name' => $s['name'], 'label' => grade_section($s['grade'], $s['name'])];
        }
        return $out;
    }

    protected function page()
    {
        return max(1, (int) $this->query('page', 1));
    }
}
