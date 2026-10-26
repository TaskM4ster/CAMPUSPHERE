<?php
/**
 * Demo school for CAMPUSPHERE — realistic, internally consistent sample data.
 * Everything is derived relative to "today", so dashboards always show current-looking activity.
 * Deterministic (seeded RNG): the same run date gives the same data.
 *
 * The six learners of the original prototype are reproduced with the same profiles
 * (Sofia Mae Reyes ... Joshua Villanueva); 66 more learners fill out the six sections.
 */
require_once ROOT . 'app/libraries/Grading.php';

function seed_demo(PDO $pdo)
{
    mt_srand(2026);
    $now      = date('Y-m-d H:i:s');
    $today    = date('Y-m-d');
    $nowtime  = date('H:i:s');
    $demo_pw  = 'Campus@2026';
    $hash     = password_hash($demo_pw, PASSWORD_DEFAULT);
    $sy       = $pdo->query('SELECT * FROM school_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch();
    $sy_id    = (int) $sy['id'];
    $year     = (int) substr($sy['label'], 0, 4);

    $ins = function ($table, array $row) use ($pdo) {
        $cols = implode(',', array_map(function ($c) { return "`$c`"; }, array_keys($row)));
        $pdo->prepare("INSERT INTO `$table` ($cols) VALUES (" . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
        return (int) $pdo->lastInsertId();
    };
    $bulk = function ($table, array $cols, array $rows) use ($pdo) {
        $colsql = implode(',', array_map(function ($c) { return "`$c`"; }, $cols));
        foreach (array_chunk($rows, 800) as $chunk) {
            $ph = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
            $stmt = $pdo->prepare("INSERT INTO `$table` ($colsql) VALUES " . implode(',', array_fill(0, count($chunk), $ph)));
            $flat = [];
            foreach ($chunk as $r) { foreach ($r as $v) { $flat[] = $v; } }
            $stmt->execute($flat);
        }
    };
    $initials = function ($name) {
        $p = preg_split('/\s+/', trim($name));
        return strtoupper(mb_substr($p[0], 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
    };
    $clamp = function ($v, $lo, $hi) { return max($lo, min($hi, $v)); };
    $gauss = function ($mean, $sd) {
        $u = max(mt_rand() / mt_getrandmax(), 1e-9); $v = mt_rand() / mt_getrandmax();
        return $mean + $sd * sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
    };
    $chance = function ($p) { return mt_rand() / mt_getrandmax() < $p; };
    $ago = function ($days, $time = '09:00:00') use ($today) { return date('Y-m-d', strtotime("$today $days days")) . ' ' . $time; };

    $subjects = $pdo->query('SELECT id, code, name FROM subjects ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $sub_by_code = [];
    foreach ($subjects as $s) { $sub_by_code[$s['code']] = $s; }
    $order = ['FIL', 'ENG', 'MATH', 'SCI', 'AP', 'MAPEH', 'ESP', 'TLE'];

    $pdo->beginTransaction();

    // ------------------------------------------------------------------ staff accounts
    $staff = [
        'paolo'   => ['teacher',   'Paolo Dela Cruz',  'Mathematics',  'T-2018-001'],
        'camille' => ['ctorres',   'Camille Torres',   'English',      'T-2016-002'],
        'andrea'  => ['aflores',   'Andrea Flores',    'Science',      'T-2019-003'],
        'liza'    => ['lramos',    'Liza Ramos',       'Mathematics',  'T-2015-004'],
        'ramon'   => ['raquino',   'Ramon Aquino',     'Mathematics',  'T-2014-005'],
        'joy'     => ['jnavarro',  'Joy Navarro',      'Mathematics',  'T-2020-006'],
        'marco'   => ['mbautista', 'Marco Bautista',   'Filipino / AP', 'T-2017-007'],
        'elena'   => ['ecastillo', 'Elena Castillo',   'MAPEH / TLE',  'T-2013-008'],
    ];
    $teacher = []; $teacher_user = [];
    foreach ($staff as $key => $t) {
        $uid = $ins('users', ['username' => $t[0], 'email' => $t[0] . '@aurora.example', 'password_hash' => $hash, 'role' => 'Teacher',
            'full_name' => $t[1], 'initials' => $initials($t[1]), 'must_change_password' => 0, 'created_at' => $now]);
        $teacher[$key] = $ins('teachers', ['user_id' => $uid, 'employee_no' => $t[3], 'full_name' => $t[1], 'department' => $t[2], 'created_at' => $now]);
        $teacher_user[$key] = $uid;
    }
    $guidance_uid = $ins('users', ['username' => 'guidance', 'email' => 'guidance@aurora.example', 'password_hash' => $hash, 'role' => 'Guidance',
        'full_name' => 'Rosalie Andrada', 'initials' => 'RA', 'must_change_password' => 0, 'created_at' => $now]);
    $admin_uid = (int) $pdo->query("SELECT id FROM users WHERE role = 'Administrator' ORDER BY id LIMIT 1")->fetchColumn();

    // ------------------------------------------------------------------ sections & class assignments
    $section_defs = [[7, 'Jacinto', 'joy'], [8, 'Mabini', 'ramon'], [9, 'Bonifacio', 'liza'], [10, 'Rizal', 'paolo'], [11, 'STEM – A', 'camille'], [12, 'ABM – A', 'elena']];
    $math_by_grade = [7 => 'joy', 8 => 'ramon', 9 => 'liza', 10 => 'paolo', 11 => 'paolo', 12 => 'paolo'];
    $subject_teacher = ['ENG' => 'camille', 'SCI' => 'andrea', 'FIL' => 'marco', 'AP' => 'marco', 'MAPEH' => 'elena', 'ESP' => 'elena', 'TLE' => 'elena'];
    $periods = ['MATH' => '08:00:00', 'ENG' => '09:00:00', 'SCI' => '10:00:00', 'FIL' => '11:00:00', 'AP' => '13:00:00', 'MAPEH' => '14:00:00', 'ESP' => '15:00:00', 'TLE' => '15:30:00'];
    $section = [];       // grade => id
    $assign = [];        // grade => code => [teacher key, period]
    foreach ($section_defs as $d) {
        $sid = $ins('sections', ['school_year_id' => $sy_id, 'grade' => $d[0], 'name' => $d[1], 'adviser_id' => $teacher[$d[2]], 'capacity' => 40]);
        $section[$d[0]] = $sid;
        foreach ($order as $code) {
            $tk = $code === 'MATH' ? $math_by_grade[$d[0]] : $subject_teacher[$code];
            $ins('class_assignments', ['teacher_id' => $teacher[$tk], 'section_id' => $sid, 'subject_id' => $sub_by_code[$code]['id'], 'period_start' => $periods[$code]]);
            $assign[$d[0]][$code] = [$tk, $periods[$code]];
        }
    }

    // ------------------------------------------------------------------ learners
    $counts = [7 => 14, 8 => 13, 9 => 12, 10 => 12, 11 => 11, 12 => 10];
    $named = [
        ['Sofia Mae Reyes', 10, 'Maria Elena Reyes', '0917 555 0101', 'sofia.reyes@example.com', 92, 'student',
            [88, 87, 80, 83, 90, 89, 90, 85], 3, 'sofia'],
        ['Gabriel Santos', 10, 'Roberto Santos', '0917 555 0102', 'gabriel.santos@example.com', 98, null, [94, 95, 96, 93, 94, 93, 95, 93], 0, ''],
        ['Isabella Cruz', 9, 'Ana Cruz', '0917 555 0103', 'isabella.cruz@example.com', 96, null, [92, 91, 93, 90, 91, 92, 91, 90], 0, ''],
        ['Daniel Mendoza', 8, 'Luis Mendoza', '0917 555 0104', 'daniel.mendoza@example.com', 89, null, [84, 83, 76, 81, 85, 84, 84, 80], 4, ''],
        ['Alyssa Garcia', 11, 'Teresa Garcia', '0917 555 0105', 'alyssa.garcia@example.com', 97, null, [94, 93, 95, 94, 93, 94, 93, 93], 0, ''],
        ['Joshua Villanueva', 7, 'Carmen Villanueva', '0917 555 0106', 'joshua.v@example.com', 91, null, [86, 85, 82, 85, 87, 86, 86, 84], 2, ''],
    ];
    $first = ['Maria', 'Juan', 'Angela', 'Miguel', 'Kristine', 'Carlo', 'Bianca', 'Nathan', 'Patricia', 'Rafael', 'Jasmine', 'Lorenzo', 'Trisha', 'Ethan', 'Mikaela', 'Vincent', 'Denise',
              'Joaquin', 'Katrina', 'Lara', 'Marco', 'Elaine', 'Adrian', 'Hazel', 'Francis', 'Nicole', 'Jericho', 'Ritchelle', 'Dominic', 'Princess', 'Kenneth', 'Althea', 'Gian', 'Louise'];
    $last  = ['Bautista', 'Aquino', 'Navarro', 'Castillo', 'Ramos', 'Torres', 'Lopez', 'Fernandez', 'Domingo', 'Salazar', 'Mercado', 'Pascual', 'Tolentino', 'Manalo', 'Aguilar', 'Del Rosario',
              'Soriano', 'Valdez', 'Corpuz', 'Estrada', 'Ocampo', 'Padilla', 'Rivera', 'Gonzales', 'Hernandez', 'Lim', 'Tan', 'Dizon', 'Macaraeg', 'Sarmiento', 'Magno', 'Alcantara', 'Evangelista', 'Pineda'];

    $learners = [];   // each: no, name, grade, guardian, phone, email, att, username, finals, missing_target, key
    foreach ($named as $i => $n) {
        $learners[] = ['no' => sprintf('ANHS-%d-%03d', $year, $i + 1), 'name' => $n[0], 'grade' => $n[1], 'guardian' => $n[2], 'phone' => $n[3], 'email' => $n[4],
            'att' => $n[5] / 100, 'username' => $n[6] ?: strtolower(sprintf('ANHS-%d-%03d', $year, $i + 1)), 'finals' => $n[7], 'missing' => $n[8], 'key' => $n[9]];
    }
    $have = array_count_values(array_column($learners, 'grade'));
    $idx = 0; $seq = count($learners); $used = array_column($learners, 'name');
    foreach ($counts as $g => $total) {
        for ($k = isset($have[$g]) ? $have[$g] : 0; $k < $total; $k++) {
            do { $name = $first[($idx * 5 + $g) % count($first)] . ' ' . $last[($idx * 7 + $k) % count($last)]; $idx++; } while (in_array($name, $used, true));
            $used[] = $name; $seq++;
            $base = $clamp((int) round($gauss(91.5, 3.8)), 80, 98);
            $finals = [];
            foreach ($order as $o) { $finals[] = $clamp($base + mt_rand(-3, 3), 72, 99); }
            $sur = explode(' ', $name); $sur = end($sur);
            $learners[] = ['no' => sprintf('ANHS-%d-%03d', $year, $seq), 'name' => $name, 'grade' => $g, 'guardian' => $first[mt_rand(0, count($first) - 1)] . ' ' . $sur,
                'phone' => sprintf('0917 555 %04d', 100 + $seq), 'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com',
                'att' => $clamp($gauss(0.968, 0.018), 0.9, 0.995), 'username' => strtolower(sprintf('ANHS-%d-%03d', $year, $seq)),
                'finals' => $finals, 'missing' => null, 'key' => ''];
        }
    }
    foreach ($learners as $i => &$L) {
        $uid = $ins('users', ['username' => $L['username'], 'email' => $L['email'], 'password_hash' => $hash, 'role' => 'Student', 'full_name' => $L['name'],
            'initials' => $initials($L['name']), 'must_change_password' => 0, 'created_at' => $now]);
        $age = 12 + ($L['grade'] - 7);
        $born = date('Y-m-d', strtotime("-$age years -" . mt_rand(30, 330) . ' days', strtotime($today)));
        $L['id'] = $ins('students', ['student_no' => $L['no'], 'user_id' => $uid, 'full_name' => $L['name'], 'email' => $L['email'], 'phone' => $L['phone'],
            'birth_date' => $born, 'address' => 'Brgy. ' . ['Poblacion', 'San Isidro', 'Bagumbayan', 'Santo Niño', 'Mabuhay'][$i % 5] . ', Aurora',
            'guardian_name' => $L['guardian'], 'guardian_phone' => $L['phone'], 'section_id' => $section[$L['grade']], 'enrollment_status' => 'Enrolled', 'created_at' => $now]);
    }
    unset($L);

    // ------------------------------------------------------------------ grades
    $grade_rows = [];
    foreach ($learners as $L) {
        foreach ($order as $si => $code) {
            $f = $L['finals'][$si];
            $q = [$f - 1.5, $f - 0.5, $f + 0.5, $f + 1.5];
            if ($L['key'] === 'sofia' && $code === 'MATH') { $q = [84, 81, 78, 77]; }          // the downward Mathematics trend of the prototype
            $q = array_map(function ($v) { return min(100, $v); }, $q);
            $final = Grading::final_grade($q);
            $grade_rows[] = [$L['id'], $sub_by_code[$code]['id'], $sy_id, $q[0], $q[1], $q[2], $q[3], $final, Grading::remarks($final, 75, 82),
                $teacher_user[$assign[$L['grade']][$code][0]], $now];
        }
    }
    $bulk('grades', ['student_id', 'subject_id', 'school_year_id', 'q1', 'q2', 'q3', 'q4', 'final_grade', 'remarks', 'encoded_by', 'updated_at'], $grade_rows);

    // ------------------------------------------------------------------ gate scanner, gate logs and classroom attendance
    $token = 'cs_' . bin2hex(random_bytes(24));
    $device_id = $ins('gate_devices', ['name' => 'Main gate reader', 'location' => 'Gate 1', 'token_hash' => hash('sha256', $token), 'last_seen_at' => $now, 'created_at' => $now]);

    $holidays = [$year . '-08-21', $year . '-08-31', $year . '-11-30'];
    $days = [];
    for ($t = strtotime($sy['start_date']); $t <= strtotime($today); $t += 86400) {
        $d = date('Y-m-d', $t);
        if (date('N', $t) >= 6 || in_array($d, $holidays, true)) { continue; }
        if ($d === $today && $nowtime < '08:05:00') { continue; }     // no scans yet this morning
        $days[] = $d;
    }
    $latest = end($days);
    $gate_rows = []; $class_rows = [];
    $skip_latest = [];                                                  // (student, subject) deliberately left without a classroom record
    foreach ($learners as $L) { if ($L['key'] === 'sofia') { $skip_latest[$L['id'] . ':MATH'] = true; } }
    foreach ($learners as $L) { if ($L['name'] === 'Joshua Villanueva') { $skip_latest[$L['id'] . ':ENG'] = true; } }
    $extra = 0;
    foreach ($learners as $L) { if ($L['key'] === '' && $L['grade'] >= 8 && $extra < 3 && $L['id'] % 7 === 0) { $skip_latest[$L['id'] . ':MATH'] = true; $extra++; } }

    foreach ($learners as $L) {
        $n = count($days);
        $absent_n = (int) round($n * (1 - $L['att']));
        $keys = array_keys($days); shuffle($keys);
        $absent_days = array_flip(array_slice($keys, 0, $absent_n));
        $late_prone = $chance(0.12);
        foreach ($days as $di => $d) {
            $teachers_of = $assign[$L['grade']];
            if (isset($absent_days[$di])) {
                $excused = $chance(0.3);
                foreach ($order as $code) {
                    $start = $teachers_of[$code][1];
                    if ($d === $today && $start > $nowtime) { continue; }
                    $class_rows[] = [$L['id'], $sub_by_code[$code]['id'], $section[$L['grade']], $d, $excused ? 'Excused' : 'Absent', $teacher_user[$teachers_of[$code][0]], $d . ' ' . $start];
                }
                continue;
            }
            $mins = (int) round($gauss($late_prone ? 7 * 60 + 28 : 7 * 60 + 8, $late_prone ? 14 : 11));
            $mins = $clamp($mins, 6 * 60 + 40, 7 * 60 + 58);
            $tin = sprintf('%02d:%02d:%02d', intdiv($mins, 60), $mins % 60, mt_rand(0, 59));
            $gate_rows[] = [$L['id'], $d, $tin, 'Time-in', 'Scanner', $device_id, $d . ' ' . $tin];
            $out = sprintf('16:%02d:%02d', mt_rand(0, 44), mt_rand(0, 59));
            if ($d < $today || $nowtime >= $out) { $gate_rows[] = [$L['id'], $d, $out, 'Time-out', 'Scanner', $device_id, $d . ' ' . $out]; }
            foreach ($order as $code) {
                $start = $teachers_of[$code][1];
                if ($d === $today && $start > $nowtime) { continue; }
                if ($d === $latest && isset($skip_latest[$L['id'] . ':' . $code])) { continue; }
                $status = $chance(0.02) ? 'Late' : 'Present';
                $class_rows[] = [$L['id'], $sub_by_code[$code]['id'], $section[$L['grade']], $d, $status, $teacher_user[$teachers_of[$code][0]], $d . ' ' . $start];
            }
        }
    }
    $bulk('gate_logs', ['student_id', 'log_date', 'log_time', 'event', 'source', 'device_id', 'created_at'], $gate_rows);
    $bulk('class_attendance', ['student_id', 'subject_id', 'section_id', 'attend_date', 'status', 'recorded_by', 'created_at'], $class_rows);

    // one documented correction in the audit trail
    $ca = $pdo->query("SELECT id, status FROM class_attendance WHERE status = 'Absent' ORDER BY id DESC LIMIT 1")->fetch();
    if ($ca) {
        $pdo->prepare("UPDATE class_attendance SET status = 'Excused', updated_at = ? WHERE id = ?")->execute([$now, $ca['id']]);
        $ins('attendance_corrections', ['attendance_id' => $ca['id'], 'old_status' => 'Absent', 'new_status' => 'Excused', 'reason' => 'Medical certificate presented by guardian',
            'changed_by' => $admin_uid, 'created_at' => $now]);
    }

    // ------------------------------------------------------------------ activities & submissions
    $upload_dir = ROOT . 'runtime/uploads/submissions/';
    if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0750, true); }
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 100]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    $act_defs = [   // grade => [[code, title, day offset, max, instructions]]
        10 => [['MATH', 'Quadratic equations: practice set', -14, 25, 'Solve exercises 1–10. Show complete solutions and upload a PDF or a clear photo of your work.'],
               ['SCI', 'Our ecosystem field journal', -11, 20, 'Observe a local ecosystem. Submit a two-page journal with your observations and three original photos.'],
               ['ENG', 'A letter to my future self', -16, 25, 'Write a 300-word letter using future tense and reflective language.'],
               ['FIL', 'Pagbasa at repleksyon', -8, 20, 'Basahin ang maikling kuwento at sumulat ng repleksyon na hindi bababa sa 200 salita.'],
               ['AP', 'Timeline ng kasaysayan ng Aurora', -5, 20, 'Gumawa ng timeline ng limang mahahalagang pangyayari sa kasaysayan ng lalawigan.'],
               ['SCI', 'Chemical reactions lab report', 4, 30, 'Write a lab report with hypothesis, procedure, data and conclusion.']],
        9  => [['MATH', 'Linear functions worksheet', -10, 20, 'Complete the worksheet on slope and intercepts.'],
               ['ENG', 'Narrative essay draft', -6, 25, 'Submit the first draft of your narrative essay (500 words).'],
               ['SCI', 'Newton’s laws demonstration', -3, 20, 'Record a short demonstration of one of Newton’s laws and explain it.'],
               ['FIL', 'Sanaysay: Kabataan at teknolohiya', 5, 20, 'Sumulat ng sanaysay tungkol sa epekto ng teknolohiya sa kabataan.']],
    ];
    $generic = [['MATH', 'Problem set', -18, 20], ['ENG', 'Reading response', -12, 20], ['SCI', 'Science investigatory outline', -7, 25], ['FIL', 'Maikling pagsusulit', -4, 15],
                ['AP', 'Map activity', -2, 20], ['MATH', 'Quiz review worksheet', 6, 20]];
    $activities = [];       // id => row
    $sec_acts = [];         // grade => [activity ids]
    foreach ($counts as $g => $_) {
        $list = isset($act_defs[$g]) ? $act_defs[$g] : array_map(function ($a) {
            return [$a[0], $a[1], $a[2], $a[3], 'Complete the task and upload a PDF or a clear photo of your work.'];
        }, $generic);
        foreach ($list as $a) {
            $tk = $assign[$g][$a[0]][0];
            $deadline = date('Y-m-d', strtotime("$today {$a[2]} days"));
            $id = $ins('activities', ['title' => $a[1], 'subject_id' => $sub_by_code[$a[0]]['id'], 'teacher_id' => $teacher[$tk], 'section_id' => $section[$g],
                'deadline' => $deadline, 'instructions' => $a[4], 'max_score' => $a[3], 'created_at' => date('Y-m-d H:i:s', strtotime($deadline . ' -10 days 08:00'))]);
            $activities[$id] = ['id' => $id, 'grade' => $g, 'title' => $a[1], 'deadline' => $deadline, 'max' => $a[3], 'teacher' => $tk];
            $sec_acts[$g][] = $id;
        }
    }
    $feedback = ['Thoughtful work. Add a bit more detail next time.', 'Good effort — check your computations in the last items.', 'Excellent structure and clear explanations.',
                 'Well done! Keep practicing the format.', 'Solid output. Review the rubric for full marks.'];
    $sub_rows = [];
    $slug = function ($s) { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-'); };
    foreach ($learners as $L) {
        $past = []; $future = [];
        foreach ($sec_acts[$L['grade']] as $aid) { if ($activities[$aid]['deadline'] < $today) { $past[] = $aid; } else { $future[] = $aid; } }
        $must_miss = $L['missing'];                           // exact count for the prototype learners, random for the rest
        $miss_ids = [];
        if ($must_miss !== null) {
            $order_ids = $past;
            if ($L['key'] === 'sofia') {                      // Sofia: graded Science + English, missing Mathematics + Filipino + AP
                $miss_ids = array_values(array_filter($past, function ($aid) use ($activities) { return !in_array($activities[$aid]['title'], ['Our ecosystem field journal', 'A letter to my future self'], true); }));
            } else {
                shuffle($order_ids); $miss_ids = array_slice($order_ids, 0, $must_miss);
            }
        } else {
            foreach ($past as $aid) { if ($chance(0.025)) { $miss_ids[] = $aid; } }
        }
        foreach ($past as $aid) {
            if (in_array($aid, $miss_ids, true)) { continue; }
            $a = $activities[$aid];
            $stamp = date('Y-m-d H:i:s', strtotime($a['deadline'] . ' -' . mt_rand(0, 3) . ' days ' . sprintf('%02d:%02d:00', mt_rand(15, 21), mt_rand(0, 59))));
            if ($chance(0.05)) { $stamp = date('Y-m-d H:i:s', strtotime($a['deadline'] . ' +1 day 09:30:00')); }   // a few late submissions
            $graded = $chance(0.72) || ($L['key'] === 'sofia');
            $ext = $chance(0.7) ? 'pdf' : 'docx';
            $stored = bin2hex(random_bytes(16)) . '.pdf';
            file_put_contents($upload_dir . $stored, $pdf);
            $last = explode(' ', $L['name']); $last = end($last);
            $score = $graded ? round($clamp($a['max'] * ($clamp(($L['finals'][2] + mt_rand(-4, 6)) / 100, 0.6, 1)), 0, $a['max']), 1) : null;
            $sub_rows[] = [$aid, $L['id'], $graded ? 'Graded' : 'Submitted', $slug($last) . '-' . $slug($a['title']) . '.' . $ext, $stored, $stamp,
                $score, $graded ? $feedback[mt_rand(0, count($feedback) - 1)] : null, $graded ? $teacher_user[$a['teacher']] : null, $graded ? date('Y-m-d H:i:s', strtotime($stamp . ' +1 day')) : null];
        }
        foreach ($future as $aid) {
            if ($L['key'] !== '' || !$chance(0.2)) { continue; }
            $stored = bin2hex(random_bytes(16)) . '.pdf';
            file_put_contents($upload_dir . $stored, $pdf);
            $last = explode(' ', $L['name']); $last = end($last);
            $sub_rows[] = [$aid, $L['id'], 'Submitted', $slug($last) . '-' . $slug($activities[$aid]['title']) . '.pdf', $stored, $ago(-1, '18:20:00'), null, null, null, null];
        }
    }
    $bulk('submissions', ['activity_id', 'student_id', 'status', 'file_name', 'file_path', 'submitted_at', 'score', 'feedback', 'graded_by', 'graded_at'], $sub_rows);

    // ------------------------------------------------------------------ enrollment
    $n = 0;
    foreach ($learners as $L) {
        $n++;
        $id = $ins('enrollments', ['student_id' => $L['id'], 'applicant_name' => $L['name'], 'grade' => $L['grade'], 'section_id' => $section[$L['grade']], 'school_year_id' => $sy_id,
            'type' => $L['grade'] == 7 ? 'New enrollment' : 'Re-enrollment', 'docs_status' => 'Verified', 'status' => 'Approved', 'email' => $L['email'], 'guardian_name' => $L['guardian'],
            'guardian_phone' => $L['phone'], 'created_by' => $admin_uid, 'decided_by' => $admin_uid, 'decided_at' => $ago(-120 + ($n % 40)), 'created_at' => $ago(-125 + ($n % 40))]);
        $pdo->prepare('UPDATE enrollments SET code = ? WHERE id = ?')->execute([sprintf('ENR-%d-%03d', $year, $id), $id]);
    }
    $pending = [['Mikhail Reyes', 7, 'New enrollment', 'Awaiting verification', 'mikhail.reyes@example.com', 'Dolores Reyes'],
                ['Hannah Villanueva', 7, 'New enrollment', 'Verified', 'hannah.v@example.com', 'Carmen Villanueva'],
                ['Jerome Salazar', 11, 'New enrollment', 'Awaiting verification', 'jerome.salazar@example.com', 'Teodoro Salazar']];
    foreach ($pending as $p) {
        $id = $ins('enrollments', ['applicant_name' => $p[0], 'grade' => $p[1], 'section_id' => $section[$p[1]], 'school_year_id' => $sy_id, 'type' => $p[2], 'docs_status' => $p[3],
            'status' => 'Pending', 'email' => $p[4], 'guardian_name' => $p[5], 'guardian_phone' => '0917 555 09' . sprintf('%02d', mt_rand(0, 99)), 'created_by' => $admin_uid, 'created_at' => $ago(-2)]);
        $pdo->prepare('UPDATE enrollments SET code = ? WHERE id = ?')->execute([sprintf('ENR-%d-%03d', $year, $id), $id]);
    }

    // enrollment history (past years) so that the forecast has a trend to work with
    $cur = $counts; $hist = [];
    $prev_year_counts = $cur;
    for ($k = 1; $k <= 4; $k++) {
        $y = $year - $k; $row = [];
        foreach (range(7, 12) as $g) {
            $row[$g] = $g < 12 ? (int) round($prev_year_counts[$g + 1] / (0.93 + mt_rand(0, 60) / 1000)) : (int) round($prev_year_counts[12] * (0.9 + mt_rand(0, 25) / 100));
        }
        $row[7] = max(10, $row[7] + mt_rand(-1, 1));
        $hist[$y] = $row; $prev_year_counts = $row;
    }
    foreach ($hist as $y => $row) { foreach ($row as $g => $hc) { $ins('enrollment_history', ['year_start' => $y, 'grade' => $g, 'headcount' => $hc]); } }

    // ------------------------------------------------------------------ student services
    $by_key = []; foreach ($learners as $L) { $by_key[$L['name']] = $L; }
    $reqs = [[ 'Sofia Mae Reyes', 'Certificate of Enrollment', 'Scholarship application', 'Pending', -1],
             ['Gabriel Santos', 'Form 137 / Academic Record', 'Academic record verification', 'Processing', -2],
             ['Isabella Cruz', 'Certificate of Good Moral', 'Youth leadership program', 'Ready', -3],
             ['Alyssa Garcia', 'School ID Replacement', 'Lost school identification card', 'Completed', -4]];
    $flow = ['Pending', 'Processing', 'Ready', 'Completed'];
    foreach ($reqs as $r) {
        $L = $by_key[$r[0]];
        $id = $ins('service_requests', ['student_id' => $L['id'], 'type' => $r[1], 'purpose' => $r[2], 'status' => $r[3], 'created_by' => $admin_uid, 'created_at' => $ago($r[4], '10:00:00'), 'updated_at' => $ago($r[4] + 1, '10:00:00')]);
        $pdo->prepare('UPDATE service_requests SET code = ? WHERE id = ?')->execute([sprintf('REQ-%d-%03d', date('Y'), $id), $id]);
        foreach (array_slice($flow, 0, array_search($r[3], $flow, true) + 1) as $si => $st) {
            $ins('request_status_log', ['request_id' => $id, 'status' => $st, 'changed_by' => $admin_uid, 'created_at' => $ago($r[4] + $si * 0.5, '10:' . sprintf('%02d', $si * 7) . ':00')]);
        }
    }

    // ------------------------------------------------------------------ notifications
    $sofia = $by_key['Sofia Mae Reyes']; $isabella = $by_key['Isabella Cruz'];
    $notices = [
        ['Attendance record needs verification', 'Gate attendance recorded, but classroom attendance is missing for Sofia Mae Reyes • Grade 10 – Rizal • Mathematics, 8:00 AM. Adviser verification requested.', 'Attendance', 'Administrator,Teacher', null, $section[10], 0],
        ['Academic support review recommended', 'Sofia Mae Reyes has 3 missing requirements and a downward Mathematics trend. Review the connected academic records before planning support.', 'Academic', 'Administrator,Teacher,Guidance', null, null, 0],
        ['Your certificate is ready', 'Isabella Cruz’s Certificate of Good Moral is ready for collection at the Registrar’s Office.', 'Services', 'Administrator,Student', $isabella['id'], null, 0],
        ['World Teachers’ Day celebration', 'Thank you to our Aurora National High School teachers. Student Council activities will be held at the covered court.', 'Announcement', null, null, null, 0],
        ['Grade 10 – Rizal: Mathematics quiz moved', 'The Grade 10 – Rizal Mathematics quiz is moved to next week.', 'Announcement', null, null, $section[10], 0],
        ['Grade 9 – Bonifacio field trip forms', 'Grade 9 – Bonifacio students: submit signed field trip forms by Friday.', 'Announcement', null, null, $section[9], 0],
        ['Guardian contact update logged', 'A verified attendance summary for Sofia Mae Reyes was queued for the registered guardian contact.', 'Guardian', 'Administrator,Teacher,Guidance', null, null, 1],
    ];
    foreach ($notices as $i => $nt) {
        $nid = $ins('notices', ['title' => $nt[0], 'body' => $nt[1], 'category' => $nt[2], 'aud_roles' => $nt[3], 'aud_student_id' => $nt[4], 'aud_section_id' => $nt[5],
            'created_by' => $admin_uid, 'created_at' => date('Y-m-d H:i:s', strtotime("$now -" . (10 + $i * 25) . ' minutes'))]);
        if ($nt[6]) { $ins('notice_reads', ['notice_id' => $nid, 'user_id' => $admin_uid, 'read_at' => $now]); }
    }

    // ------------------------------------------------------------------ guidance
    $daniel = $by_key['Daniel Mendoza'];
    $ins('guidance_notes', ['student_id' => $sofia['id'], 'author_id' => $guidance_uid, 'author_name' => 'Rosalie Andrada', 'kind' => 'Parent consultation', 'note' => 'Brief check-in held. Sofia is keeping up in English and Science but struggling with quadratic equations. Agreed on weekly peer tutoring.', 'created_at' => $ago(-6, '14:10:00')]);
    $ins('guidance_notes', ['student_id' => $daniel['id'], 'author_id' => $guidance_uid, 'author_name' => 'Rosalie Andrada', 'note' => 'Four requirements outstanding and attendance below 90%. Guardian to be contacted to discuss support options.', 'created_at' => $ago(-4, '11:30:00')]);
    $ins('guidance_notes', ['student_id' => $by_key['Gabriel Santos']['id'], 'author_id' => $guidance_uid, 'author_name' => 'Rosalie Andrada', 'kind' => 'Commendation', 'note' => 'Placed first in the division quiz bee and mentors classmates in Mathematics.', 'created_at' => $ago(-9, '09:15:00')]);
    $ins('guidance_notes', ['student_id' => $daniel['id'], 'author_id' => $guidance_uid, 'author_name' => 'Rosalie Andrada', 'kind' => 'Support case', 'note' => 'Opened a support case: attendance and requirements to be monitored for four weeks.', 'created_at' => $ago(-3, '10:00:00')]);
    $ins('interventions', ['student_id' => $sofia['id'], 'title' => 'Peer tutoring — Mathematics', 'detail' => 'Weekly, Wednesdays after class', 'due_date' => $ago(7, '00:00:00') ? substr($ago(7), 0, 10) : $today, 'is_done' => 0, 'created_by' => $guidance_uid, 'created_at' => $ago(-6)]);
    $ins('interventions', ['student_id' => $daniel['id'], 'title' => 'Guardian conference', 'detail' => 'Discuss attendance and missing requirements', 'due_date' => substr($ago(3), 0, 10), 'is_done' => 0, 'created_by' => $guidance_uid, 'created_at' => $ago(-4)]);
    $ins('interventions', ['student_id' => $daniel['id'], 'title' => 'Catch-up plan for missing work', 'detail' => null, 'due_date' => substr($ago(-1), 0, 10), 'is_done' => 1, 'created_by' => $guidance_uid, 'created_at' => $ago(-8)]);

    $ins('audit_logs', ['user_id' => $admin_uid, 'username' => 'admin', 'action' => 'system.install', 'entity' => 'system', 'details' => 'Demo data loaded', 'ip_address' => '127.0.0.1', 'created_at' => $now]);

    $pdo->commit();

    echo "  Demo school: " . count($learners) . " learners, " . count($staff) . " teachers, " . count($days) . " school days, " . count($gate_rows) . " gate scans, " . count($class_rows) . " class records.\n";
    echo "  Demo gate device token (for the scan API): $token\n";
}
