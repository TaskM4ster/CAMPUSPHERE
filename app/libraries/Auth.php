<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'libraries/Acl.php';

/**
 * Auth - authentication, session identity and access checks.
 *
 *  - passwords are verified with password_verify() (bcrypt) and transparently re-hashed
 *  - the session id is regenerated on login (session fixation protection)
 *  - failed logins are throttled per username+IP and every attempt is stored
 *  - the user row is re-read on every request, so deactivating an account or
 *    changing a role takes effect immediately
 */
class Auth
{
    /** valid bcrypt hash used to keep response time similar for unknown users */
    const DUMMY_HASH = '$2y$10$VEN.h.w..hdjBj/WRu9P6.YGG/TnDJkjSLUcX37SURa5hrWZ29uti';

    private $user   = null;
    private $loaded = false;

    /** Always resolve the *current* controller: LavaLust creates a new one per routed request. */
    private function lava()  { return lava_instance(); }
    private function db()    { return shared_db(); }

    // ------------------------------------------------------------------ identity
    /** @return array|null current user (with student_id / teacher_id) */
    public function user()
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;
        $uid = $this->lava()->session->userdata('uid');
        if (!$uid) {
            return null;
        }
        $row = $this->db()->raw(
            'SELECT u.id, u.username, u.email, u.role, u.full_name, u.initials, u.is_active, u.must_change_password,
                    s.id AS student_id, s.student_no, t.id AS teacher_id
               FROM users u
          LEFT JOIN students s ON s.user_id = u.id
          LEFT JOIN teachers t ON t.user_id = u.id
              WHERE u.id = ?',
            [(int) $uid]
        )->fetch();
        if (!$row || !(int) $row['is_active']) {
            $this->logout();
            return null;
        }
        return $this->user = $row;
    }

    public function check()      { return $this->user() !== null; }
    public function id()         { $u = $this->user(); return $u ? (int) $u['id'] : null; }
    public function role()       { $u = $this->user(); return $u ? $u['role'] : null; }
    public function is($role)    { return $this->role() === $role; }
    public function in(array $roles) { return in_array($this->role(), $roles, true); }

    // ------------------------------------------------------------------ access
    public function can_access($path, $role = null)
    {
        $role = $role ?: $this->role();
        return Acl::can(config_item('access_map'), $role, $path);
    }

    public function home($role = null)
    {
        $role = $role ?: $this->role();
        $map  = config_item('role_home');
        return isset($map[$role]) ? $map[$role] : '/login';
    }

    // ------------------------------------------------------------------ login
    /**
     * @return array ['ok'=>bool, 'error'=>string|null, 'user'=>array|null]
     */
    public function attempt($username, $password, $ip)
    {
        $username = trim((string) $username);
        if ($username === '' || (string) $password === '') {
            return ['ok' => false, 'error' => 'Enter your username and password.'];
        }
        $wait = $this->locked_minutes($username, $ip);
        if ($wait > 0) {
            return ['ok' => false, 'error' => "Too many failed sign-in attempts. Try again in about {$wait} minute" . ($wait === 1 ? '' : 's') . '.'];
        }

        $row = $this->db()->raw(
            'SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1',
            [$username, $username]
        )->fetch();

        $hash = $row ? $row['password_hash'] : self::DUMMY_HASH;
        $ok   = password_verify((string) $password, $hash) && $row && (int) $row['is_active'] === 1;

        $this->log_attempt($username, $ip, $ok);
        if (!$ok) {
            return ['ok' => false, 'error' => 'Invalid username or password.'];
        }
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            $this->db()->raw('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        }
        $this->login($row);
        return ['ok' => true, 'error' => null, 'user' => $row];
    }

    public function login(array $row)
    {
        $this->lava()->session->regenerate_on_login(true);
        $this->lava()->session->set_userdata('uid', (int) $row['id']);
        $this->db()->raw('UPDATE users SET last_login_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $row['id']]);
        $this->loaded = false;
        $this->user   = null;
    }

    public function logout()
    {
        $this->lava()->session->unset_userdata('uid');
        $this->user   = null;
        $this->loaded = true;
    }

    // ------------------------------------------------------------------ throttling
    private function window_start()
    {
        return date('Y-m-d H:i:s', time() - 60 * (int) config_item('login_window_minutes'));
    }

    /** minutes left on a lock, 0 when not locked */
    public function locked_minutes($username, $ip)
    {
        $max = (int) config_item('login_max_attempts');
        $row = $this->db()->raw(
            'SELECT COUNT(*) AS n, MIN(created_at) AS first_at FROM login_attempts
              WHERE success = 0 AND username = ? AND ip_address = ? AND created_at >= ?',
            [strtolower($username), $ip, $this->window_start()]
        )->fetch();
        if ((int) $row['n'] < $max) {
            return 0;
        }
        $unlock = strtotime($row['first_at']) + 60 * (int) config_item('login_window_minutes');
        return max(1, (int) ceil(($unlock - time()) / 60));
    }

    private function log_attempt($username, $ip, $ok)
    {
        $db = $this->db();
        $db->raw('INSERT INTO login_attempts (username, ip_address, success, created_at) VALUES (?,?,?,?)',
            [strtolower($username), $ip, $ok ? 1 : 0, date('Y-m-d H:i:s')]);
        if ($ok) {
            $db->raw('DELETE FROM login_attempts WHERE success = 0 AND username = ? AND ip_address = ?', [strtolower($username), $ip]);
        }
        // housekeeping: keep the table small
        if (mt_rand(1, 50) === 1) {
            $db->raw('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 7 * 86400)]);
        }
    }
}
