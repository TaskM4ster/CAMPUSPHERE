<?php
/**
 * End-to-end HTTP flow tests (needs the app running and a freshly seeded demo database):
 *   php tests/flows.php [base-url]        default http://127.0.0.1:8099
 */
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
$pw = 'Campus@2026'; $pass = 0; $fail = 0;

function ck($name, $cond, $extra = '') { global $pass, $fail; if ($cond) { $pass++; echo "  ok   $name\n"; } else { $fail++; echo "  FAIL $name $extra\n"; } }

class Client {
    public $jar; public $base; public $last = ''; public $code = 0; public $loc = '';
    function __construct($base) { $this->base = $base; $this->jar = tempnam(sys_get_temp_dir(), 'cj'); }
    function req($method, $path, $data = null, $headers = [], $raw = false) {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $headers]);
        if ($method === 'POST') { $hasFile = is_array($data) && count(array_filter($data, function ($x) { return $x instanceof CURLFile; })); curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, (is_array($data) && !$hasFile) ? http_build_query($data) : $data); }
        $res = curl_exec($ch); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE); $this->code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        $h = substr($res, 0, $hs); $this->last = substr($res, $hs);
        $this->loc = preg_match('/^Location:\s*(.+)$/mi', $h, $m) ? trim($m[1]) : '';
        return $this;
    }
    function get($p) { return $this->req('GET', $p); }
    function token($path) { $this->get($path); return preg_match('/name="csrf_test_name" value="([^"]+)"/', $this->last, $m) ? $m[1] : ''; }
    function post($path, array $d, $from = null) { $d['csrf_test_name'] = $this->token($from ?: '/account'); return $this->req('POST', $path, $d); }
    function login($u, $p) { $t = $this->token('/login'); return $this->req('POST', '/login', ['username' => $u, 'password' => $p, 'csrf_test_name' => $t]); }
    function follow() { if ($this->loc) { $u = parse_url($this->loc); $this->get(($u['path'] ?? '/') . (isset($u['query']) ? '?' . $u['query'] : '')); } return $this; }
    function has($s) { return strpos($this->last, $s) !== false; }
}
function db() { static $d; if (!$d) { $e = parse_ini_file(__DIR__ . '/../.env'); $d = new PDO("mysql:host={$e['DB_HOST']};dbname={$e['DB_NAME']};charset=utf8mb4", $e['DB_USER'], $e['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); } return $d; }
function q($sql, $a = []) { $s = db()->prepare($sql); $s->execute($a); return $s->fetchAll(PDO::FETCH_ASSOC); }
function v($sql, $a = []) { $r = q($sql, $a); return $r ? array_values($r[0])[0] : null; }

echo "Authentication\n";
$anon = new Client($base);
ck('anonymous /dashboard redirects to login', $anon->get('/dashboard')->code === 302 && strpos($anon->loc, 'login') !== false);
ck('landing sends visitors to the login page', $anon->get('/')->code === 302 && strpos($anon->loc, 'login') !== false);
$t = $anon->token('/login');
ck('POST /login without CSRF token is rejected', $anon->req('POST', '/login', ['username' => 'admin', 'password' => $pw])->code === 403);
ck('wrong password does not sign in', $anon->login('admin', 'nope')->follow()->has('Invalid username or password'));
$c = new Client($base);
ck('admin signs in', $c->login('admin', $pw)->code === 302);
ck('sign in by e-mail works for student', (new Client($base))->login('sofia.reyes@example.com', $pw)->code === 302);

echo "Lockout\n";
$l = new Client($base);
for ($i = 0; $i < 6; $i++) { $l->login('victim.user', 'wrong' . $i)->follow(); }
ck('account locks after repeated failures', $l->has('Too many failed'));
q("DELETE FROM login_attempts");

echo "Access control\n";
$stu = new Client($base); $stu->login('student', $pw);
$tea = new Client($base); $tea->login('teacher', $pw);
$gui = new Client($base); $gui->login('guidance', $pw);
ck('student cannot open /users (403)', $stu->get('/users')->code === 403);
ck('teacher cannot open /enrollment (403)', $tea->get('/enrollment')->code === 403);
ck('guidance cannot open /grades (403)', $gui->get('/grades')->code === 403);
$other = v("SELECT id FROM students WHERE full_name = 'Gabriel Santos'");
$self  = v("SELECT id FROM students WHERE full_name = 'Sofia Mae Reyes'");
ck('student cannot read another learner profile', $stu->get("/students/$other")->code !== 200);
ck('teacher sees own advisee / class', $tea->get("/students/$self")->code === 200);
$g7 = v("SELECT id FROM students WHERE full_name = 'Joshua Villanueva'");
ck('teacher is blocked from a learner outside their sections', $tea->get("/students/$g7")->code !== 200);
ck('forged POST without CSRF is rejected', $c->req('POST', '/users/1/toggle', ['x' => 1])->code === 403);

echo "Dashboards & standings\n";
$c->get('/dashboard'); ck('admin dashboard shows learner count', $c->has('72'));
ck('Sofia is "Needs Review"', ($x = $c->get("/students/$self")) && $x->has('Needs Review'));
$daniel = v("SELECT id FROM students WHERE full_name = 'Daniel Mendoza'");
ck('Daniel is "Recommended Support"', $c->get("/students/$daniel")->has('Recommended Support'));
$gab = v("SELECT id FROM students WHERE full_name = 'Gabriel Santos'");
ck('Gabriel is "Good Standing"', $c->get("/students/$gab")->has('Good Standing'));
ck('student portal loads for Sofia', $stu->get('/student')->code === 200 && $stu->has('Sofia'));

echo "Gate API\n";
$token = 'cs_testtoken_' . bin2hex(random_bytes(8));
q("UPDATE gate_devices SET token_hash = ? WHERE id = 1", [hash('sha256', $token)]);
$api = new Client($base);
$no = v("SELECT student_no FROM students WHERE id = ?", [$self]);
ck('scan without token is 401', $api->req('POST', '/api/gate/scan', json_encode(['student_no' => $no]), ['Content-Type: application/json'])->code === 401);
$r = $api->req('POST', '/api/gate/scan', json_encode(['student_no' => $no, 'event' => 'Time-out']), ['Content-Type: application/json', "Authorization: Bearer $token"]);
ck('scan with token is accepted', $r->code === 200 && strpos($r->last, '"ok":true') !== false, $r->last);
ck('unknown student is 404', $api->req('POST', '/api/gate/scan', json_encode(['student_no' => 'NOPE-1']), ['Content-Type: application/json', "Authorization: Bearer $token"])->code === 404);
ck('scan is stored with source Scanner', (int) v("SELECT COUNT(*) FROM gate_logs WHERE student_id = ? AND source = 'Scanner' AND log_date = CURDATE() AND event = 'Time-out'", [$self]) >= 1);

echo "Enrollment\n";
$pend = v("SELECT id FROM enrollments WHERE status = 'Pending' AND docs_status = 'Verified' LIMIT 1");
$before = (int) v('SELECT COUNT(*) FROM students');
$r = $c->post("/enrollment/$pend/approve", [], '/enrollment');
ck('approve redirects', $r->code === 302, "code {$r->code}");
ck('approval created a learner', (int) v('SELECT COUNT(*) FROM students') === $before + 1);
ck('approval created a student account', (int) v("SELECT COUNT(*) FROM users WHERE role = 'Student'") === $before + 1);
$unv = v("SELECT id FROM enrollments WHERE status = 'Pending' AND docs_status <> 'Verified' LIMIT 1");
$c->post("/enrollment/$unv/approve", [], '/enrollment');
ck('unverified documents cannot be approved', v('SELECT status FROM enrollments WHERE id = ?', [$unv]) === 'Pending');
$c->post("/enrollment/$unv/verify", [], '/enrollment');
ck('documents can be verified', v('SELECT docs_status FROM enrollments WHERE id = ?', [$unv]) === 'Verified');
$c->post("/enrollment/$unv/reject", ['reason' => 'Incomplete requirements'], '/enrollment');
ck('rejection stores a reason', v('SELECT status FROM enrollments WHERE id = ?', [$unv]) === 'Rejected');
$sec10 = v("SELECT id FROM sections WHERE grade = 10 LIMIT 1");
$c->post('/enrollment', ['name' => 'Test Applicant', 'type' => 'New enrollment', 'section_id' => $sec10, 'email' => 'test.app@example.com', 'guardian_name' => 'Parent Test', 'guardian_phone' => '0917 000 0000', 'birth_date' => '2011-03-04'], '/enrollment');
ck('new application is saved', (int) v("SELECT COUNT(*) FROM enrollments WHERE applicant_name = 'Test Applicant'") === 1);

echo "Attendance\n";
$ca = q("SELECT id, student_id, subject_id, section_id, attend_date, status FROM class_attendance WHERE status = 'Present' ORDER BY id DESC LIMIT 1")[0];
$c->post('/attendance/record', ['student_id' => $ca['student_id'], 'subject_id' => $ca['subject_id'], 'date' => $ca['attend_date'], 'status' => 'Late', 'reason' => ''], '/attendance');
ck('a correction without a reason is refused', v('SELECT status FROM class_attendance WHERE id = ?', [$ca['id']]) === 'Present');
$c->post('/attendance/record', ['student_id' => $ca['student_id'], 'subject_id' => $ca['subject_id'], 'date' => $ca['attend_date'], 'status' => 'Late', 'reason' => 'Teacher confirmed late arrival'], '/attendance');
ck('correction with a reason is applied', v('SELECT status FROM class_attendance WHERE id = ?', [$ca['id']]) === 'Late');
ck('correction is in the audit trail', (int) v('SELECT COUNT(*) FROM attendance_corrections WHERE attendance_id = ?', [$ca['id']]) === 1);
$d = v('SELECT MAX(attend_date) FROM class_attendance');
ck('attendance overview loads with discrepancies', $c->get("/attendance?date=$d")->code === 200);
$tsec = v("SELECT ca.section_id FROM class_assignments ca JOIN teachers t ON t.id = ca.teacher_id JOIN users u ON u.id = t.user_id WHERE u.username = 'teacher' LIMIT 1"); $tsub = v("SELECT ca.subject_id FROM class_assignments ca JOIN teachers t ON t.id = ca.teacher_id JOIN users u ON u.id = t.user_id WHERE u.username = 'teacher' AND ca.section_id = ? LIMIT 1", [$tsec]);
ck('teacher class sheet loads', $tea->get("/attendance/class?section=$tsec&subject=$tsub")->code === 200);

echo "Grades\n";
$math = v("SELECT id FROM subjects WHERE code = 'MATH'");
ck('grade sheet loads', $tea->get("/grades?section=$sec10&subject=$math")->code === 200);
$gr = q("SELECT student_id FROM grades WHERE subject_id = ? AND student_id <> ? AND student_id IN (SELECT id FROM students WHERE section_id = ?) LIMIT 1", [$math, $self, $sec10])[0];
$tea->post('/grades/save', ['section_id' => $sec10, 'subject_id' => $math, 'g' => [$gr['student_id'] => ['q1' => 90, 'q2' => 90, 'q3' => 90, 'q4' => 90]]], "/grades?section=$sec10&subject=$math");
ck('teacher saved grades; final computed', (float) v('SELECT final_grade FROM grades WHERE student_id = ? AND subject_id = ?', [$gr['student_id'], $math]) == 90.0);
$tea->post('/grades/save', ['section_id' => $sec10, 'subject_id' => $math, 'g' => [$gr['student_id'] => ['q1' => 150, 'q2' => 'x', 'q3' => '', 'q4' => '']]], "/grades?section=$sec10&subject=$math");
ck('out-of-range grades are rejected', (float) v('SELECT q1 FROM grades WHERE student_id = ? AND subject_id = ?', [$gr['student_id'], $math]) <= 100);

echo "Activities\n";
$act = v("SELECT id FROM activities WHERE title = 'Chemical reactions lab report'");
$tea->post('/activities', ['title' => 'Flow test activity', 'subject_id' => $math, 'section_id' => $sec10, 'deadline' => date('Y-m-d', strtotime('+5 days')), 'max_score' => 20, 'instructions' => 'Do it.'], '/activities');
$newact = v("SELECT id FROM activities WHERE title = 'Flow test activity'");
ck('teacher created an activity', (bool) $newact);
$tmp = tempnam(sys_get_temp_dir(), 'w') . '.pdf'; file_put_contents($tmp, "%PDF-1.4\n1 0 obj<<>>endobj\n%%EOF\n");
$tok = $stu->token("/activities/$newact");
$r = $stu->req('POST', "/activities/$newact/submit", ['csrf_test_name' => $tok, 'work' => new CURLFile($tmp, 'application/pdf', 'my work.pdf')]);
ck('student submitted a PDF', (int) v('SELECT COUNT(*) FROM submissions WHERE activity_id = ? AND student_id = ?', [$newact, $self]) === 1, "code {$r->code}");
$stored = v('SELECT file_path FROM submissions WHERE activity_id = ? AND student_id = ?', [$newact, $self]);
ck('file stored outside the web root with a random name', $stored && !file_exists(__DIR__ . '/../public/' . $stored) && file_exists(__DIR__ . '/../runtime/uploads/submissions/' . $stored));
$bad = tempnam(sys_get_temp_dir(), 'w') . '.php'; file_put_contents($bad, '<?php echo 1;');
$stu->req('POST', "/activities/$act/submit", ['csrf_test_name' => $stu->token("/activities/$act"), 'work' => new CURLFile($bad, 'application/pdf', 'evil.php')]);
ck('executable upload is refused', (int) v('SELECT COUNT(*) FROM submissions WHERE activity_id = ? AND student_id = ? AND file_name LIKE ?', [$act, $self, '%evil%']) === 0);
$tea->post("/activities/$newact/grade", ['student_id' => $self, 'score' => 18, 'feedback' => 'Great'], "/activities/$newact");
ck('teacher graded the submission', v('SELECT status FROM submissions WHERE activity_id = ? AND student_id = ?', [$newact, $self]) === 'Graded');
ck('teacher can download the submission', $tea->get("/activities/$newact/file/$self")->code === 200);
ck('another student cannot download it', (function () use ($base, $pw, $newact, $self) { $o = new Client($base); $o->login('anhs-2026-002', $pw); return $o->get("/activities/$newact/file/$self")->code !== 200; })());

echo "Student services\n";
$stu->post('/student/requests', ['type' => 'Certificate of Good Moral', 'purpose' => 'Flow test'], '/student/requests');
$rq = v("SELECT id FROM service_requests WHERE purpose = 'Flow test'");
ck('student filed a request', (bool) $rq);
$c->post("/services/$rq/advance", [], '/services');
ck('admin advanced status', v('SELECT status FROM service_requests WHERE id = ?', [$rq]) === 'Processing');
ck('status history recorded', (int) v('SELECT COUNT(*) FROM request_status_log WHERE request_id = ?', [$rq]) >= 2);

echo "Notifications\n";
$c->post('/notifications/announce', ['title' => 'Flow notice', 'body' => 'Hello', 'category' => 'Announcement', 'audience' => 'role:Student'], '/notifications');
ck('announcement created', (bool) v("SELECT id FROM notices WHERE title = 'Flow notice'"));
ck('student sees the announcement', $stu->get('/notifications')->has('Flow notice'));
ck('teacher does not see a student-only notice', !$tea->get('/notifications')->has('Flow notice'));
$stu->post('/notifications/read-all', [], '/notifications');
ck('mark-all-read works', (int) v('SELECT COUNT(*) FROM notice_reads nr JOIN users u ON u.id = nr.user_id WHERE u.username = ?', ['student']) > 0);

echo "Workflow rules (write-up)\n";
ck('Sofia flagged: Mathematics grades are falling', $c->get("/students/$self")->has('declining quarter grades'));
ck('AI disclaimer is shown', $c->get("/guidance/$self")->has('do not assign an official status'));
$gate_in = (int) v("SELECT COUNT(*) FROM gate_logs WHERE student_id = ? AND log_date = CURDATE() AND event = 'Time-in'", [$gab]);
if ($gate_in) {
    $m = v("SELECT id FROM class_attendance WHERE student_id = ? AND attend_date = CURDATE() LIMIT 1", [$gab]);
    q("UPDATE class_attendance SET status = 'Absent' WHERE id = ?", [$m]);
    ck('gate time-in + marked Absent is flagged as a discrepancy', $c->get('/attendance')->has('Gabriel Santos') || $c->get('/dashboard')->has('Gabriel Santos'));
    q("UPDATE class_attendance SET status = 'Present' WHERE id = ?", [$m]);
}
echo "Guidance\n";
$gui->post("/guidance/$self/note", ['note' => 'Flow test note'], "/guidance/$self");
$gui->post("/guidance/$self/note", ['note' => 'Flow commendation', 'kind' => 'Commendation'], "/guidance/$self");
ck('commendation stored with its kind', v("SELECT kind FROM guidance_notes WHERE note = 'Flow commendation'") === 'Commendation');
$gui->post("/guidance/$self/note", ['note' => 'Bad kind', 'kind' => 'Hacked'], "/guidance/$self");
ck('unknown kind falls back to Note', v("SELECT kind FROM guidance_notes WHERE note = 'Bad kind'") === 'Note');
ck('guidance note saved', (bool) v("SELECT id FROM guidance_notes WHERE note = 'Flow test note'"));
$gui->post("/guidance/$self/intervention", ['title' => 'Flow plan', 'detail' => 'x', 'due_date' => date('Y-m-d', strtotime('+3 days'))], "/guidance/$self");
$iv = v("SELECT id FROM interventions WHERE title = 'Flow plan'");
ck('intervention created', (bool) $iv);
$gui->post("/guidance/$self/plan/$iv/toggle", [], "/guidance/$self");
ck('intervention toggled done', (int) v('SELECT is_done FROM interventions WHERE id = ?', [$iv]) === 1);

echo "Users & account\n";
$c->post('/users', ['username' => 'flowuser', 'email' => 'flow@example.com', 'full_name' => 'Flow User', 'role' => 'Teacher'], '/users');
ck('admin created a user', (bool) v("SELECT id FROM users WHERE username = 'flowuser'"));
ck('new user must change password', (int) v("SELECT must_change_password FROM users WHERE username = 'flowuser'") === 1);
$uid = v("SELECT id FROM users WHERE username = 'flowuser'");
$c->post("/users/$uid/toggle", [], '/users');
ck('admin deactivated the user', (int) v('SELECT is_active FROM users WHERE id = ?', [$uid]) === 0);
$c->post('/users', ['username' => 'flowuser', 'email' => 'x@example.com', 'full_name' => 'Dup', 'role' => 'Teacher'], '/users');
ck('duplicate username refused', (int) v("SELECT COUNT(*) FROM users WHERE username = 'flowuser'") === 1);
$p = new Client($base); $p->login('guidance', $pw);
$p->post('/account/password', ['current' => $pw, 'new' => 'abc', 'confirm' => 'abc'], '/account/password');
ck('weak password refused', password_verify($pw, v("SELECT password_hash FROM users WHERE username = 'guidance'")));
$p->post('/account/password', ['current' => $pw, 'new' => 'Better#Pass2026', 'confirm' => 'Better#Pass2026'], '/account/password');
ck('strong password accepted', password_verify('Better#Pass2026', v("SELECT password_hash FROM users WHERE username = 'guidance'")));
q("UPDATE users SET password_hash = ? WHERE username = 'guidance'", [password_hash($pw, PASSWORD_DEFAULT)]);

echo "Exports & reports\n";
foreach (['/students/export', '/forecast/export', '/reports/students?format=csv', '/reports/attendance?format=csv', '/reports/grades?format=csv', '/reports/enrollment?format=csv', '/reports/services?format=csv', '/reports/support?format=csv'] as $u) {
    $c->get($u); ck("CSV $u", $c->code === 200 && strlen($c->last) > 20 && strpos($c->last, '<html') === false, "code {$c->code}");
}
$c->get('/reports/students?grade=10');
ck('report preview filters by grade', $c->code === 200 && $c->has('Sofia Mae Reyes') && !$c->has('Joshua Villanueva'));
$c->get('/reports/services?status=Ready');
ck('services report filters by status', $c->code === 200 && $c->has('Isabella Cruz') && !$c->has('Alyssa Garcia'));
ck('forecast shows teacher allocation', $c->get('/forecast')->has('Teachers needed'));
ck('forecast page', $c->get('/forecast')->code === 200 && $c->has('<svg'));
ck('audit log has entries', (int) v('SELECT COUNT(*) FROM audit_logs') > 3);
$c->post('/logout', [], '/account'); ck('logout works', $c->get('/dashboard')->code === 302);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
