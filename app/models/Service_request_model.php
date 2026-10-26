<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'models/Campus_model.php';

/**
 * Service_request_model - document requests handled by the registrar.
 * Status flow: Pending -> Processing -> Ready -> Completed (one step at a time).
 */
class Service_request_model extends Campus_model
{
    private function base()
    {
        return 'SELECT r.*, s.full_name AS student_name, s.student_no, sec.grade, sec.name AS section_name
                  FROM service_requests r
                  JOIN students s ON s.id = r.student_id
             LEFT JOIN sections sec ON sec.id = s.section_id';
    }

    public function search(array $f = [], $page = 1, $per = 20)
    {
        $where = ['1=1']; $p = [];
        if (!empty($f['status']))     { $where[] = 'r.status = ?';     $p[] = $f['status']; }
        if (!empty($f['student_id'])) { $where[] = 'r.student_id = ?'; $p[] = (int) $f['student_id']; }
        if (!empty($f['q'])) {
            $where[] = '(r.code LIKE ? OR s.full_name LIKE ? OR r.type LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($p, $like, $like, $like);
        }
        $w = ' WHERE ' . implode(' AND ', $where);
        $total = (int) $this->val('SELECT COUNT(*) FROM service_requests r JOIN students s ON s.id = r.student_id' . $w, $p);
        list($page, $per, $off) = $this->page($page, $per);
        return ['rows' => $this->rows($this->base() . $w . " ORDER BY r.created_at DESC, r.id DESC LIMIT $per OFFSET $off", $p), 'total' => $total];
    }

    public function for_student($student_id)
    {
        return $this->rows($this->base() . ' WHERE r.student_id = ? ORDER BY r.created_at DESC, r.id DESC', [(int) $student_id]);
    }

    public function find($id)
    {
        return $this->row($this->base() . ' WHERE r.id = ?', [(int) $id]);
    }

    public function find_by_code($code)
    {
        return $this->row($this->base() . ' WHERE r.code = ?', [$code]);
    }

    public function status_counts()
    {
        $out = array_fill_keys(config_item('request_statuses'), 0);
        foreach ($this->rows('SELECT status, COUNT(*) AS n FROM service_requests GROUP BY status') as $r) {
            $out[$r['status']] = (int) $r['n'];
        }
        return $out;
    }

    public function new_today()
    {
        return (int) $this->val('SELECT COUNT(*) FROM service_requests WHERE created_at >= ?', [today() . ' 00:00:00']);
    }

    public function timeline($id)
    {
        $rows = $this->rows('SELECT status, created_at FROM request_status_log WHERE request_id = ? ORDER BY id', [(int) $id]);
        return array_column($rows, 'created_at', 'status');
    }

    public function create(array $user, $student_id, $type, $purpose)
    {
        $student_id = (int) $student_id;
        if ($user['role'] === 'Student' && (int) $user['student_id'] !== $student_id) {
            return $this->fail('You can only submit requests for yourself.');
        }
        if (!in_array($user['role'], ['Student', 'Administrator'], true)) {
            return $this->fail('You are not allowed to submit requests.');
        }
        $s = $this->row('SELECT id, full_name FROM students WHERE id = ?', [$student_id]);
        if (!$s) { return $this->fail('Unknown student.'); }
        if (!in_array($type, config_item('request_types'), true)) { return $this->fail('Choose a request type.'); }
        $purpose = trim((string) $purpose);
        if ($purpose === '') { return $this->fail('Purpose is required.'); }
        if (mb_strlen($purpose) > 255) { return $this->fail('Purpose is too long (max 255 characters).'); }
        if ($this->val("SELECT 1 FROM service_requests WHERE student_id = ? AND type = ? AND status IN ('Pending','Processing')", [$student_id, $type])) {
            return $this->fail("An open {$type} request already exists for {$s['full_name']}.");
        }
        $this->db->transaction();
        try {
            $id = $this->insert_row('service_requests', [
                'student_id' => $student_id, 'type' => $type, 'purpose' => $purpose, 'status' => 'Pending',
                'created_by' => $user['id'], 'created_at' => now(),
            ]);
            $code = 'REQ-' . date('Y') . '-' . str_pad($id, 3, '0', STR_PAD_LEFT);
            $this->update_row('service_requests', $id, ['code' => $code]);
            $this->insert_row('request_status_log', ['request_id' => $id, 'status' => 'Pending', 'changed_by' => $user['id'], 'created_at' => now()]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->roll_back();
            throw $e;
        }
        return $this->ok("{$code} submitted and pending registrar review.", ['id' => $id, 'code' => $code, 'student' => $s, 'type' => $type]);
    }

    public function advance(array $user, $id)
    {
        if ($user['role'] !== 'Administrator') { return $this->fail('Only registrar staff can update request status.'); }
        $r = $this->find($id);
        if (!$r) { return $this->fail('Request not found.'); }
        $flow = config_item('request_statuses');
        $next = $flow[array_search($r['status'], $flow, true) + 1] ?? null;
        if (!$next) { return $this->fail('This request is already completed.'); }
        $this->db->transaction();
        try {
            $this->update_row('service_requests', $r['id'], ['status' => $next, 'handled_by' => $user['id'], 'updated_at' => now()]);
            $this->insert_row('request_status_log', ['request_id' => $r['id'], 'status' => $next, 'changed_by' => $user['id'], 'created_at' => now()]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->roll_back();
            throw $e;
        }
        return $this->ok("{$r['code']} is now {$next}.", ['request' => $r, 'next' => $next]);
    }
}
