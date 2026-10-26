<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'models/Campus_model.php';

/**
 * Gate_model - gate time-in / time-out logs and the scanner devices that post them.
 * Devices authenticate with a random token; only its SHA-256 hash is stored.
 */
class Gate_model extends Campus_model
{
    public function latest_date()
    {
        $d = $this->val('SELECT MAX(log_date) FROM gate_logs WHERE log_date <= ?', [today()]);
        return $d ?: today();
    }

    public function logs_for($student_id, $date)
    {
        return $this->rows(
            'SELECT gl.*, gd.name AS device_name FROM gate_logs gl LEFT JOIN gate_devices gd ON gd.id = gl.device_id
              WHERE gl.student_id = ? AND gl.log_date = ? ORDER BY gl.log_time',
            [(int) $student_id, $date]
        );
    }

    /**
     * Record a gate event. Idempotent: scanning twice the same day keeps the first
     * Time-in / Time-out (scanners often double-read a card).
     */
    public function record($student_id, $event, $date, $time, $source = 'Manual', $device_id = null, $by = null)
    {
        if (!in_array($event, ['Time-in', 'Time-out'], true)) {
            return $this->fail('Unknown gate event.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            return $this->fail('Invalid date.');
        }
        if ($date > today()) {
            return $this->fail('Gate events cannot be recorded for a future date.');
        }
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
            return $this->fail('Invalid time.');
        }
        if (strlen($time) === 5) { $time .= ':00'; }
        $student = $this->row('SELECT id, full_name, student_no FROM students WHERE id = ?', [(int) $student_id]);
        if (!$student) {
            return $this->fail('Unknown student.');
        }
        $dup = $this->row('SELECT id, log_time FROM gate_logs WHERE student_id = ? AND log_date = ? AND event = ?', [(int) $student_id, $date, $event]);
        if ($dup) {
            return $this->ok($student['full_name'] . ' already has a ' . $event . ' at ' . fmt_time($dup['log_time']) . '.', ['duplicate' => true, 'student' => $student]);
        }
        if ($event === 'Time-out') {
            $in = $this->val("SELECT MIN(log_time) FROM gate_logs WHERE student_id = ? AND log_date = ? AND event = 'Time-in'", [(int) $student_id, $date]);
            if ($in && $time < $in) {
                return $this->fail('Time-out cannot be earlier than the Time-in (' . fmt_time($in) . ').');
            }
        }
        $id = $this->insert_row('gate_logs', [
            'student_id' => (int) $student_id, 'log_date' => $date, 'log_time' => $time, 'event' => $event,
            'source' => $source, 'device_id' => $device_id, 'recorded_by' => $by, 'created_at' => now(),
        ]);
        return $this->ok($student['full_name'] . ' ' . strtolower($event) . ' recorded at ' . fmt_time($time) . '.', ['id' => $id, 'student' => $student]);
    }

    // ------------------------------------------------------------------ devices
    public function devices()
    {
        return $this->rows(
            "SELECT gd.*, (SELECT COUNT(*) FROM gate_logs gl WHERE gl.device_id = gd.id AND gl.log_date = ?) AS scans_today
               FROM gate_devices gd ORDER BY gd.name",
            [today()]
        );
    }

    /** @return array ['ok'=>true,'token'=>plaintext shown once] */
    public function create_device($name, $location)
    {
        $name = trim($name);
        if ($name === '') {
            return $this->fail('Device name is required.');
        }
        $token = 'cs_' . bin2hex(random_bytes(24));
        $id = $this->insert_row('gate_devices', [
            'name' => mb_substr($name, 0, 80), 'location' => mb_substr(trim($location), 0, 120),
            'token_hash' => hash('sha256', $token), 'created_at' => now(),
        ]);
        return $this->ok('Device registered.', ['id' => $id, 'token' => $token]);
    }

    public function device_by_token($token)
    {
        if (!is_string($token) || $token === '') { return null; }
        return $this->row('SELECT * FROM gate_devices WHERE token_hash = ? AND is_active = 1', [hash('sha256', $token)]);
    }

    public function touch_device($id)
    {
        $this->run('UPDATE gate_devices SET last_seen_at = ? WHERE id = ?', [now(), (int) $id]);
    }

    public function toggle_device($id)
    {
        $this->run('UPDATE gate_devices SET is_active = 1 - is_active WHERE id = ?', [(int) $id]);
        return $this->ok('Device updated.');
    }
}
