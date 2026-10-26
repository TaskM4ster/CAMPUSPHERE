<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'models/Campus_model.php';

/** Audit_model - append-only trail of who changed what (and failed security events). */
class Audit_model extends Campus_model
{
    public function log($user, $action, $entity, $entity_id = null, $details = null)
    {
        try {
            $this->insert_row('audit_logs', [
                'user_id' => $user['id'] ?? null, 'username' => $user['username'] ?? null,
                'action' => mb_substr($action, 0, 60), 'entity' => mb_substr($entity, 0, 60),
                'entity_id' => $entity_id === null ? null : mb_substr((string) $entity_id, 0, 40),
                'details' => $details === null ? null : mb_substr((string) $details, 0, 2000),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null, 'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            error_log('Audit log failed: ' . $e->getMessage());   // auditing must never break the request
        }
    }

    public function search(array $f = [], $page = 1, $per = 25)
    {
        $where = ['1=1']; $p = [];
        if (!empty($f['entity'])) { $where[] = 'entity = ?'; $p[] = $f['entity']; }
        if (!empty($f['q'])) {
            $where[] = '(username LIKE ? OR action LIKE ? OR details LIKE ? OR entity_id LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($p, $like, $like, $like, $like);
        }
        $w = ' WHERE ' . implode(' AND ', $where);
        $total = (int) $this->val('SELECT COUNT(*) FROM audit_logs' . $w, $p);
        list($page, $per, $off) = $this->page($page, $per);
        return ['rows' => $this->rows("SELECT * FROM audit_logs $w ORDER BY id DESC LIMIT $per OFFSET $off", $p), 'total' => $total];
    }

    public function entities()
    {
        return array_column($this->rows('SELECT DISTINCT entity FROM audit_logs ORDER BY entity'), 'entity');
    }
}
