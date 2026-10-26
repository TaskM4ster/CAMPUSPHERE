<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Gate_api - machine endpoint for gate scanners.
 *
 *   POST /api/gate/scan
 *   Authorization: Bearer <device token>        (or header X-Device-Token)
 *   Body (JSON or form):  {"student_no":"ANHS-2026-001","event":"Time-in"}   event optional
 *
 * The server clock is authoritative (device clocks drift). Requests are authenticated by
 * a per-device token whose SHA-256 hash is stored; no cookies/CSRF are involved.
 */
class Gate_api extends Controller
{
    private function out($status, array $body)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function bearer()
    {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($h === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) { if (strtolower($k) === 'authorization') { $h = $v; } }
        }
        if (stripos($h, 'Bearer ') === 0) { return trim(substr($h, 7)); }
        return trim($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '');
    }

    public function scan()
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { $this->out(405, ['ok' => false, 'error' => 'POST required.']); }
        $this->db = shared_db();
        $this->call->model('Gate_model', 'gate');
        $this->call->model('Student_model', 'students');

        $device = $this->gate->device_by_token($this->bearer());
        if (!$device) { $this->out(401, ['ok' => false, 'error' => 'Invalid or inactive device token.']); }

        $in = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($in)) { $in = $_POST; }
        $no = trim((string) ($in['student_no'] ?? ''));
        if ($no === '' || strlen($no) > 30) { $this->out(422, ['ok' => false, 'error' => 'student_no is required.']); }
        $student = $this->students->find_by_no($no);
        if (!$student || $student['enrollment_status'] !== 'Enrolled') { $this->out(404, ['ok' => false, 'error' => 'Unknown or inactive student.']); }

        $event = $in['event'] ?? '';
        if ($event === '' || $event === null) {
            $has_in = count(array_filter($this->gate->logs_for($student['id'], today()), function ($l) { return $l['event'] === 'Time-in'; })) > 0;
            $event = $has_in ? 'Time-out' : 'Time-in';
        }
        if (!in_array($event, ['Time-in', 'Time-out'], true)) { $this->out(422, ['ok' => false, 'error' => 'event must be Time-in or Time-out.']); }

        $res = $this->gate->record($student['id'], $event, today(), date('H:i:s'), 'Scanner', $device['id'], null);
        $this->gate->touch_device($device['id']);
        if (empty($res['ok'])) { $this->out(422, ['ok' => false, 'error' => $res['error']]); }
        $this->out(200, [
            'ok' => true, 'duplicate' => !empty($res['duplicate']), 'event' => $event, 'time' => date('H:i:s'),
            'student' => ['student_no' => $student['student_no'], 'name' => $student['full_name'], 'section' => $student['section_label']],
            'message' => $res['message'],
        ]);
    }
}
