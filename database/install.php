<?php
date_default_timezone_set('Asia/Manila');   // must match app/config/config.php so seeded dates line up
/**
 * CAMPUSPHERE installer (CLI only)
 *
 *   php database/install.php                       create database + tables + reference data + administrator
 *   php database/install.php --demo                ... plus a complete demo school (learners, grades, attendance...)
 *   php database/install.php --fresh --yes         DROP every table first (destroys data!)
 *   php database/install.php --admin-user=admin --admin-password='S0me#Strong'
 *
 * Connection settings come from .env (DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME, DB_CHARSET)
 * or real environment variables. Safe to re-run: existing tables and rows are left alone unless --fresh.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('PREVENT_DIRECT_ACCESS', true);   // the app libraries/config expect this constant

// ------------------------------------------------------------------ arguments & env
$opts = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) { $opts[$m[1]] = isset($m[2]) ? $m[2] : true; }
    else { fwrite(STDERR, "Unknown argument: $a\n"); exit(1); }
}
if (isset($opts['help']) || isset($opts['h'])) { echo substr(file_get_contents(__FILE__), 0, strpos(file_get_contents(__FILE__), '*/') + 2), "\n"; exit(0); }

$env = [];
if (is_file(ROOT . '.env')) {
    foreach (file(ROOT . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) { continue; }
        list($k, $v) = explode('=', $line, 2);
        $v = trim(preg_replace('/\s+#.*$/', '', $v));
        $env[trim($k)] = trim($v, "\"'");
    }
}
$cfg = function ($k, $d = '') use ($env) { $v = getenv($k); return $v !== false && $v !== '' ? $v : (isset($env[$k]) && $env[$k] !== '' ? $env[$k] : $d); };
$host = $cfg('DB_HOST', '127.0.0.1'); $port = $cfg('DB_PORT', '3306'); $user = $cfg('DB_USER', 'root');
$pass = $cfg('DB_PASSWORD', ''); $name = $cfg('DB_NAME', 'campusphere'); $charset = $cfg('DB_CHARSET', 'utf8mb4');
if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) { fwrite(STDERR, "DB_NAME may only contain letters, digits and underscore.\n"); exit(1); }

function say($m) { echo $m, PHP_EOL; }
function fail($m) { fwrite(STDERR, "ERROR: $m\n"); exit(1); }

// ------------------------------------------------------------------ connect / create database
try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$name`");
} catch (PDOException $e) {
    fail('Cannot connect to MySQL at ' . $host . ':' . $port . ' as "' . $user . '": ' . $e->getMessage());
}
say("Connected to $host:$port, database `$name`.");

// ------------------------------------------------------------------ --fresh
if (isset($opts['fresh'])) {
    if (!isset($opts['yes'])) {
        fwrite(STDOUT, "--fresh will DROP every table in `$name`. Type the database name to continue: ");
        if (trim((string) fgets(STDIN)) !== $name) { fail('Aborted.'); }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) { $pdo->exec("DROP TABLE IF EXISTS `$t`"); }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    say('Dropped all tables.');
}

// ------------------------------------------------------------------ schema
$sql = file_get_contents(__DIR__ . '/schema.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql);                   // full-line comments
$count = 0;
foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '') { continue; }
    try { $pdo->exec($stmt); $count++; }
    catch (PDOException $e) { fail("Schema statement failed: " . $e->getMessage() . "\n--- " . substr($stmt, 0, 200)); }
}
say("Schema applied ($count statements).");

// ------------------------------------------------------------------ reference data
$now = date('Y-m-d H:i:s');
$has_sy = (int) $pdo->query('SELECT COUNT(*) FROM school_years')->fetchColumn();
$year = (int) date('n') >= 6 ? (int) date('Y') : (int) date('Y') - 1;       // school year starts in June
if (!$has_sy) {
    $st = $pdo->prepare('INSERT INTO school_years (label, start_date, end_date, term_label, is_active, created_at) VALUES (?,?,?,?,1,?)');
    $st->execute([$year . '–' . ($year + 1), "$year-06-08", ($year + 1) . '-03-31', 'First Semester', $now]);
    say("School year {$year}–" . ($year + 1) . ' created.');
}
$subjects = [['FIL', 'Filipino'], ['ENG', 'English'], ['MATH', 'Mathematics'], ['SCI', 'Science'], ['AP', 'Araling Panlipunan'],
             ['MAPEH', 'MAPEH'], ['ESP', 'Edukasyon sa Pagpapakatao'], ['TLE', 'Technology & Livelihood Education']];
$st = $pdo->prepare('INSERT IGNORE INTO subjects (code, name) VALUES (?,?)');
foreach ($subjects as $s) { $st->execute($s); }
$st = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
$st->execute(['schema_version', '1']);
$st->execute(['installed_at', $now]);

// ------------------------------------------------------------------ administrator
$admin_user = isset($opts['admin-user']) && $opts['admin-user'] !== true ? strtolower($opts['admin-user']) : 'admin';
if (!preg_match('/^[a-z0-9._-]{3,40}$/', $admin_user)) { fail('Invalid --admin-user.'); }
$exists = $pdo->prepare('SELECT id FROM users WHERE username = ?'); $exists->execute([$admin_user]);
$admin_password = null;
if (!$exists->fetchColumn()) {
    $demo = isset($opts['demo']);
    $admin_password = isset($opts['admin-password']) && $opts['admin-password'] !== true ? $opts['admin-password']
        : ($demo ? 'Campus@2026' : substr(strtr(base64_encode(random_bytes(12)), '+/=', 'xyz'), 0, 12) . '#7');
    if (strlen($admin_password) < 8) { fail('Administrator password must be at least 8 characters.'); }
    $pdo->prepare('INSERT INTO users (username, email, password_hash, role, full_name, initials, must_change_password, created_at) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$admin_user, null, password_hash($admin_password, PASSWORD_DEFAULT), 'Administrator', 'Admin Office', 'AO', $demo ? 0 : 1, $now]);
    say("Administrator account `$admin_user` created.");
} else {
    say("Administrator `$admin_user` already exists — left unchanged.");
}

// ------------------------------------------------------------------ demo school
if (isset($opts['demo'])) {
    $has_students = (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
    if ($has_students) {
        say('Demo data skipped: the students table is not empty (use --fresh to start over).');
    } else {
        require __DIR__ . '/seed_demo.php';
        $t0 = microtime(true);
        seed_demo($pdo);
        say(sprintf('Demo data created in %.1fs.', microtime(true) - $t0));
    }
}

// ------------------------------------------------------------------ writable folders
foreach (['runtime/session', 'runtime/uploads/submissions', 'runtime/logs', 'runtime/cache'] as $d) {
    if (!is_dir(ROOT . $d)) { @mkdir(ROOT . $d, 0750, true); }
    if (!is_writable(ROOT . $d)) { say("WARNING: $d is not writable by this user — the web server user needs write access."); }
}

say('');
say('Done.');
if ($admin_password !== null) {
    say("  Sign in as:  $admin_user");
    say("  Password:    $admin_password" . (isset($opts['demo']) ? '' : '   (you will be asked to change it at first sign-in)'));
}
if (isset($opts['demo'])) { say('  Demo accounts (admin, teacher, student, guidance) use the password: Campus@2026'); }
