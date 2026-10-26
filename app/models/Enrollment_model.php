<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'models/Campus_model.php';

/**
 * Enrollment_model - admission / re-enrollment register.
 *
 * Workflow:  Pending (documents awaiting verification)
 *            -> documents Verified -> Approved  (student record created/updated)
 *            -> or Rejected
 */
class Enrollment_model extends Campus_model
{
    public function search(array $f = [], $page = 1, $per = 20)
    {
        $sy = !empty($f['sy_id']) ? (int) $f['sy_id'] : $this->sy()['id'];
        $where = ['e.school_year_id = ?']; $p = [$sy];
        if (!empty($f['status'])) { $where[] = 'e.status = ?'; $p[] = $f['status']; }
        if (!empty($f['q'])) {
            $where[] = '(e.applicant_name LIKE ? OR e.code LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($p, $like, $like);
        }
        $w = ' WHERE ' . implode(' AND ', $where);
        $total = (int) $this->val('SELECT COUNT(*) FROM enrollments e' . $w, $p);
        list($page, $per, $off) = $this->page($page, $per);
        $rows = $this->rows(
            "SELECT e.*, sec.name AS section_name, s.student_no, u.full_name AS created_by_name
               FROM enrollments e
               JOIN sections sec ON sec.id = e.section_id
          LEFT JOIN students s ON s.id = e.student_id
          LEFT JOIN users u ON u.id = e.created_by
             $w ORDER BY FIELD(e.status,'Pending','Approved','Rejected'), e.id DESC LIMIT $per OFFSET $off", $p
        );
        foreach ($rows as &$r) { $r['initials'] = initials($r['applicant_name']); }
        return ['rows' => $rows, 'total' => $total];
    }

    public function find($id)
    {
        return $this->row(
            'SELECT e.*, sec.name AS section_name, sec.capacity FROM enrollments e JOIN sections sec ON sec.id = e.section_id WHERE e.id = ?',
            [(int) $id]
        );
    }

    public function counts()
    {
        $sy = $this->sy()['id'];
        $r = $this->row(
            "SELECT SUM(type = 'Re-enrollment' AND status = 'Approved') AS returning_n,
                    SUM(type = 'New enrollment'  AND status = 'Approved') AS new_n,
                    SUM(status = 'Pending') AS pending_n
               FROM enrollments WHERE school_year_id = ?", [$sy]
        );
        $total = (int) $this->val(
            "SELECT COUNT(*) FROM students s JOIN sections sec ON sec.id = s.section_id
              WHERE s.enrollment_status = 'Enrolled' AND sec.school_year_id = ?", [$sy]
        );
        return ['total' => $total, 'returning' => (int) $r['returning_n'], 'new' => (int) $r['new_n'], 'pending' => (int) $r['pending_n']];
    }

    public function create(array $user, array $in)
    {
        if ($user['role'] !== 'Administrator') {
            return $this->fail('Only administrators can create enrollment records.');
        }
        $type    = $in['type'] ?? '';
        $name    = trim($in['name'] ?? '');
        $student = !empty($in['student_id']) ? $this->row('SELECT * FROM students WHERE id = ?', [(int) $in['student_id']]) : null;
        if (!in_array($type, ['New enrollment', 'Re-enrollment'], true)) {
            return $this->fail('Choose an enrollment type.');
        }
        if ($type === 'Re-enrollment') {
            if (!$student) { return $this->fail('Select the returning student for a re-enrollment.'); }
            $name = $student['full_name'];
        }
        if ($name === '' || mb_strlen($name) > 120) {
            return $this->fail('Name is required (max 120 characters).');
        }
        $sec = $this->row('SELECT * FROM sections WHERE id = ?', [(int) ($in['section_id'] ?? 0)]);
        if (!$sec) { return $this->fail('Select a section.'); }
        $grade = (int) $sec['grade'];
        if ($grade < 7 || $grade > 12) { return $this->fail('Grade level must be from 7 to 12.'); }
        if (!empty($in['email']) && !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->fail('Enter a valid email address.');
        }
        if (!empty($in['birth_date']) && (!strtotime($in['birth_date']) || $in['birth_date'] > today())) {
            return $this->fail('Enter a valid date of birth.');
        }
        $sy = (int) $sec['school_year_id'];
        $dup = $student
            ? $this->val("SELECT code FROM enrollments WHERE student_id = ? AND school_year_id = ? AND type = ? AND status <> 'Rejected'", [$student['id'], $sy, $type])
            : $this->val("SELECT code FROM enrollments WHERE LOWER(applicant_name) = LOWER(?) AND school_year_id = ? AND type = ? AND status <> 'Rejected'", [$name, $sy, $type]);
        if ($dup) {
            $label = $this->val('SELECT label FROM school_years WHERE id = ?', [$sy]);
            return $this->fail("{$name} already has a " . strtolower($type) . " record for {$label} ({$dup}).");
        }
        $id = $this->insert_row('enrollments', [
            'student_id' => $student ? $student['id'] : null, 'applicant_name' => $name, 'grade' => $grade,
            'section_id' => $sec['id'], 'school_year_id' => $sy, 'type' => $type,
            'email' => $in['email'] ?? null ?: null, 'birth_date' => ($in['birth_date'] ?? '') ?: null,
            'guardian_name' => ($in['guardian_name'] ?? '') ?: null, 'guardian_phone' => ($in['guardian_phone'] ?? '') ?: null,
            'created_by' => $user['id'], 'created_at' => now(),
        ]);
        $code = 'ENR-' . substr($this->val('SELECT label FROM school_years WHERE id = ?', [$sy]), 0, 4) . '-' . str_pad($id, 3, '0', STR_PAD_LEFT);
        $this->update_row('enrollments', $id, ['code' => $code]);
        return $this->ok("{$code} saved as Pending for {$name}.", ['id' => $id, 'code' => $code, 'name' => $name]);
    }

    public function verify_documents(array $user, $id)
    {
        if ($user['role'] !== 'Administrator') { return $this->fail('Only administrators can verify documents.'); }
        $e = $this->find($id);
        if (!$e) { return $this->fail('Enrollment record not found.'); }
        if ($e['status'] !== 'Pending') { return $this->fail('Only pending enrollments can be verified.'); }
        if ($e['docs_status'] === 'Verified') { return $this->fail('Documents are already verified.'); }
        $this->update_row('enrollments', $e['id'], ['docs_status' => 'Verified']);
        return $this->ok("Documents verified for {$e['applicant_name']}.", ['enrollment' => $e]);
    }

    public function reject(array $user, $id, $reason)
    {
        if ($user['role'] !== 'Administrator') { return $this->fail('Only administrators can reject enrollment.'); }
        $e = $this->find($id);
        if (!$e) { return $this->fail('Enrollment record not found.'); }
        if ($e['status'] !== 'Pending') { return $this->fail('Only pending enrollments can be rejected.'); }
        $this->update_row('enrollments', $e['id'], [
            'status' => 'Rejected', 'remarks' => mb_substr(trim($reason), 0, 255) ?: null, 'decided_by' => $user['id'], 'decided_at' => now(),
        ]);
        return $this->ok("{$e['code']} rejected.", ['enrollment' => $e]);
    }

    /**
     * Approve: updates the existing student (re-enrollment) or creates the new student
     * record plus a student sign-in account with a one-time temporary password.
     */
    public function approve(array $user, $id)
    {
        if ($user['role'] !== 'Administrator') { return $this->fail('Only administrators can approve enrollment.'); }
        $e = $this->find($id);
        if (!$e) { return $this->fail('Enrollment record not found.'); }
        if ($e['status'] === 'Approved') { return $this->fail('This enrollment is already approved.'); }
        if ($e['status'] !== 'Pending') { return $this->fail('Only pending enrollments can be approved.'); }
        if ($e['docs_status'] !== 'Verified') { return $this->fail('Verify the required documents before approving.'); }
        $already_in = $e['student_id'] && $this->val("SELECT 1 FROM students WHERE id = ? AND section_id = ? AND enrollment_status = 'Enrolled'", [$e['student_id'], $e['section_id']]);
        $filled = (int) $this->val("SELECT COUNT(*) FROM students WHERE section_id = ? AND enrollment_status = 'Enrolled'", [$e['section_id']]);
        if (!$already_in && $filled >= (int) $e['capacity']) {
            return $this->fail("Section {$e['grade']}-{$e['section_name']} is full ({$e['capacity']} students). Choose another section.");
        }
        $temp = null;
        $this->db->transaction();
        try {
            $student_id = $e['student_id'];
            if ($student_id) {
                if (!$this->val('SELECT 1 FROM students WHERE id = ?', [$student_id])) {
                    $this->db->roll_back();
                    return $this->fail('Linked student record not found.');
                }
                $this->update_row('students', $student_id, ['section_id' => $e['section_id'], 'enrollment_status' => 'Enrolled', 'updated_at' => now()]);
            } else {
                $this->call->model('Student_model');
                $no = $this->Student_model->next_student_no();
                $uid = null;
                $temp = substr(strtr(base64_encode(random_bytes(9)), '+/=', 'xyz'), 0, 10) . '#7';
                $uid = $this->insert_row('users', [
                    'username' => strtolower($no), 'email' => $e['email'] ?: null, 'password_hash' => password_hash($temp, PASSWORD_DEFAULT),
                    'role' => 'Student', 'full_name' => $e['applicant_name'], 'initials' => initials($e['applicant_name']),
                    'must_change_password' => 1, 'created_at' => now(),
                ]);
                $student_id = $this->insert_row('students', [
                    'student_no' => $no, 'user_id' => $uid, 'full_name' => $e['applicant_name'], 'email' => $e['email'] ?: null,
                    'birth_date' => $e['birth_date'], 'guardian_name' => $e['guardian_name'] ?: 'To be completed', 'guardian_phone' => $e['guardian_phone'],
                    'section_id' => $e['section_id'], 'enrollment_status' => 'Enrolled', 'created_at' => now(),
                ]);
            }
            $this->update_row('enrollments', $e['id'], ['status' => 'Approved', 'student_id' => $student_id, 'decided_by' => $user['id'], 'decided_at' => now()]);
            $this->db->commit();
        } catch (Throwable $ex) {
            $this->db->roll_back();
            throw $ex;
        }
        $student = $this->row('SELECT student_no FROM students WHERE id = ?', [$student_id]);
        return $this->ok("{$e['applicant_name']} is enrolled in Grade {$e['grade']} – {$e['section_name']}.", [
            'student_id' => $student_id, 'student_no' => $student['student_no'], 'temp_password' => $temp, 'enrollment' => $e,
        ]);
    }
}
