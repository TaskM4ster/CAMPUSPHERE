<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Secure routes:
 *   1. a user must be signed in           -> else redirect to /login (remembering the target)
 *   2. the role must own the URL prefix   -> else 403 page
 *   3. forced password change             -> else redirect to /account/password
 */
class SecureMiddleware
{
    public function handle(Closure $next)
    {
        $lava = lava_instance();
        $auth = $lava->auth;

        $path = self::request_path();

        if (!$auth->check()) {
            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                $lava->session->set_userdata('intended', $path);
            }
            $lava->session->set_flashdata('flash', ['type' => 'info', 'msg' => 'Please sign in to continue.']);
            redirect('login');
            exit;
        }

        $user = $auth->user();
        if ((int) $user['must_change_password'] === 1 && strpos($path, '/account/password') !== 0) {
            redirect('account/password');
            exit;
        }

        if (!$auth->can_access($path)) {
            http_response_code(403);
            $lava->call->view('errors/forbidden', [
                'user' => $user, 'home' => site_url($auth->home()),
                'school' => config_item('school_name'),
            ]);
            exit;
        }
        return $next();
    }

    /** Path relative to the application base, e.g. "/students/ANHS-2026-001". */
    public static function request_path()
    {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = substr($path, strlen($base));
        return '/' . trim((string) $path, '/');
    }
}
