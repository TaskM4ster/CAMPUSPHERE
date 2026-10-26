<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'models/Campus_model.php';

/** User_model - sign-in accounts (administration, password changes). */
class User_model extends Campus_model
{
    const ROLES = ['Administrator', 'Teacher', 'Student', 'Guidance'];

    public function find($id)
    {
        return $this->row(
            'SELECT u.*, s.id AS student_id, s.student_no, t.id AS teacher_id
               FROM users u LEFT JOIN students s ON s.user_id = u.id LEFT JOIN teachers t ON t.user_id = u.id WHERE u.id = ?',
            [(int) $id]
        );
    }

    public function search(array $f = [], $page = 1, $per = 20)
    {
        $where = ['1=1']; $p = [];
        if (!empty($f['role']) && in_array($f['role'], self::ROLES, true)) { $where[] = 'u.role = ?'; $p[] = $f['role']; }
        if (isset($f['active']) && $f['active'] !== '') { $where[] = 'u.is_active = ?'; $p[] = (int) $f['active']; }
        if (!empty($f['q'])) {
            $where[] = '(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($p, $like, $like, $like);
        }
        $w = ' WHERE ' . implode(' AND ', $where);
        $total = (int) $this->val('SELECT COUNT(*) FROM users u' . $w, $p);
        list($page, $per, $off) = $this->page($page, $per);
        $rows = $this->rows(
            "SELECT u.id, u.username, u.email, u.role, u.full_name, u.initials, u.is_active, u.must_change_password, u.last_login_at, u.created_at,
                    s.student_no FROM users u LEFT JOIN students s ON s.user_id = u.id $w ORDER BY u.role, u.full_name LIMIT $per OFFSET $off", $p
        );
        return ['rows' => $rows, 'total' => $total];
    }

    public function role_counts()
    {
        $out = array_fill_keys(self::ROLES, 0);
        foreach ($this->rows('SELECT role, COUNT(*) AS n FROM users WHERE is_active = 1 GROUP BY role') as $r) { $out[$r['role']] = (int) $r['n']; }
        return $out;
    }

    public function password_problem($password)
    {
        $min = (int) config_item('password_min_length');
        if (mb_strlen((string) $password) < $min) { return "Password must be at least {$min} characters."; }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) { return 'Password must contain letters and numbers.'; }
        return null;
    }

    /** Create a staff/guidance/admin account (students are created through enrollment). */
    public function create(array $in)
    {
        $username = strtolower(trim($in['username'] ?? ''));
        $role = $in['role'] ?? '';
        $name = trim($in['full_name'] ?? '');
        $email = trim($in['email'] ?? '');
        if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username)) { return $this->fail('Username must be 3-40 characters: letters, numbers, dot, dash or underscore.'); }
        if (!in_array($role, self::ROLES, true)) { return $this->fail('Choose a role.'); }
        if ($name === '' || mb_strlen($name) > 120) { return $this->fail('Full name is required.'); }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { return $this->fail('Enter a valid email address.'); }
        if ($this->val('SELECT 1 FROM users WHERE username = ?', [$username])) { return $this->fail('That username is already taken.'); }
        if ($email !== '' && $this->val('SELECT 1 FROM users WHERE email = ?', [$email])) { return $this->fail('That email address is already in use.'); }
        $temp = $in['password'] ?? '';
        if ($temp === '') { $temp = substr(strtr(base64_encode(random_bytes(9)), '+/=', 'xyz'), 0, 10) . '#7'; }
        elseif ($problem = $this->password_problem($temp)) { return $this->fail($problem); }
        $this->db->transaction();
        try {
            $uid = $this->insert_row('users', [
                'username' => $username, 'email' => $email ?: null, 'password_hash' => password_hash($temp, PASSWORD_DEFAULT), 'role' => $role,
                'full_name' => $name, 'initials' => initials($name), 'must_change_password' => 1, 'created_at' => now(),
            ]);
            if ($role === 'Teacher') {
                $n = (int) $this->val('SELECT COUNT(*) FROM teachers') + 1;
                $this->insert_row('teachers', ['user_id' => $uid, 'employee_no' => 'T-' . date('Y') . '-' . str_pad($n, 3, '0', STR_PAD_LEFT), 'full_name' => $name, 'created_at' => now()]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->roll_back();
            throw $e;
        }
        return $this->ok("Account {$username} created.", ['id' => $uid, 'temp_password' => $temp, 'username' => $username]);
    }

    public function set_active($id, $active, $acting_id)
    {
        if ((int) $id === (int) $acting_id && !$active) { return $this->fail('You cannot deactivate your own account.'); }
        $u = $this->find($id);
        if (!$u) { return $this->fail('Account not found.'); }
        if (!$active && $u['role'] === 'Administrator'
            && (int) $this->val("SELECT COUNT(*) FROM users WHERE role = 'Administrator' AND is_active = 1 AND id <> ?", [(int) $id]) === 0) {
            return $this->fail('At least one active administrator is required.');
        }
        $this->update_row('users', $id, ['is_active' => $active ? 1 : 0, 'updated_at' => now()]);
        return $this->ok($u['username'] . ($active ? ' activated.' : ' deactivated.'), ['user' => $u]);
    }

    public function reset_password($id)
    {
        $u = $this->find($id);
        if (!$u) { return $this->fail('Account not found.'); }
        $temp = substr(strtr(base64_encode(random_bytes(9)), '+/=', 'xyz'), 0, 10) . '#7';
        $this->update_row('users', $id, ['password_hash' => password_hash($temp, PASSWORD_DEFAULT), 'must_change_password' => 1, 'updated_at' => now()]);
        $this->run('DELETE FROM login_attempts WHERE username = ?', [strtolower($u['username'])]);
        return $this->ok("Temporary password issued for {$u['username']}.", ['temp_password' => $temp, 'user' => $u]);
    }

    public function change_password($id, $current, $new, $confirm)
    {
        $u = $this->row('SELECT * FROM users WHERE id = ?', [(int) $id]);
        if (!$u || !password_verify((string) $current, $u['password_hash'])) { return $this->fail('Your current password is incorrect.'); }
        if ($new !== $confirm) { return $this->fail('The new passwords do not match.'); }
        if (password_verify((string) $new, $u['password_hash'])) { return $this->fail('Choose a password different from the current one.'); }
        if ($problem = $this->password_problem($new)) { return $this->fail($problem); }
        $this->update_row('users', $id, ['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'must_change_password' => 0, 'updated_at' => now()]);
        return $this->ok('Your password was changed.');
    }
}
