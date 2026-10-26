<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/*
| -------------------------------------------------------------------
|  CAMPUSPHERE application configuration
| -------------------------------------------------------------------
|  Loaded through $autoload['configs'] in config/autoload.php.
|  Business rules live here so that they can be tuned without touching code.
*/

// ---- Identity -------------------------------------------------------
$config['school_name']    = getenv('SCHOOL_NAME')  ?: 'Aurora National High School';
$config['school_short']   = 'ANHS';
$config['system_name']    = 'CAMPUSPHERE';
$config['school_tagline'] = 'One campus. Connected possibilities.';

// ---- Academic rules ---------------------------------------------------
$config['passing_grade']        = 75;    // below => "Did not meet expectations"
$config['support_grade_below']  = 82;    // below => "Support suggested"
$config['late_cutoff']          = '07:30:00';   // gate time-in after this is "Late"
$config['section_capacity']     = 40;    // used by the forecast to estimate sections

// ---- Student standing / support-indicator rules ---------------------
// Indicators are decision support for people; they never change records.
$config['standing_rules'] = [
    'support' => ['average_below' => 83, 'attendance_below' => 90, 'missing_at_least' => 4],
    'review'  => ['average_below' => 88, 'attendance_below' => 93, 'missing_at_least' => 1],
];

// ---- Security -----------------------------------------------------------
$config['password_min_length']  = 8;
$config['login_max_attempts']   = 5;     // per username+IP ...
$config['login_window_minutes'] = 15;    // ... within this window => temporary lock
$config['show_demo_accounts']   = (getenv('SHOW_DEMO_ACCOUNTS') !== false)
    ? filter_var(getenv('SHOW_DEMO_ACCOUNTS'), FILTER_VALIDATE_BOOLEAN)
    : (strtolower((string) getenv('APP_ENV')) !== 'production');

// ---- Uploads --------------------------------------------------------------
$config['upload_max_bytes']  = 5 * 1024 * 1024;
$config['upload_extensions'] = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
$config['upload_mimes']      = [
    'application/pdf', 'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/jpeg', 'image/png', 'application/octet-stream',
];
$config['upload_dir'] = ROOT_DIR . 'runtime/uploads/submissions/';

// ---- Student services ---------------------------------------------------------
$config['request_types'] = [
    'Certificate of Enrollment',
    'Form 137 / Academic Record',
    'Certificate of Good Moral',
    'School ID Replacement',
    'School-related Request',
];
$config['request_statuses'] = ['Pending', 'Processing', 'Ready', 'Completed'];

// ---- Role based access (path prefix map) ----------------------------------
// A role may open a path when it equals an entry, or starts with "entry/".
// "/" is matched exactly. Row-level rules (my students, my sections ...) are
// enforced again inside the controllers/models.
$config['access_map'] = [
    'Administrator' => ['/dashboard', '/students', '/enrollment', '/attendance', '/grades', '/activities', '/services',
                        '/insights', '/guidance', '/forecast', '/reports', '/notifications', '/users', '/audit', '/account', '/logout'],
    'Teacher'       => ['/teacher', '/students', '/attendance', '/grades', '/activities', '/insights', '/guidance',
                        '/notifications', '/account', '/logout'],
    'Student'       => ['/student', '/activities', '/notifications', '/account', '/logout'],
    'Guidance'      => ['/guidance', '/students', '/insights', '/notifications', '/account', '/logout'],
];
$config['role_home'] = [
    'Administrator' => '/dashboard',
    'Teacher'       => '/teacher',
    'Student'       => '/student',
    'Guidance'      => '/guidance',
];

// ---- Navigation (label, path, icon) ----------------------------------------
$config['nav'] = [
    'Administrator' => [
        ['Dashboard',              '/dashboard',   'layout-dashboard'],
        ['Students',               '/students',    'users'],
        ['Enrollment',             '/enrollment',  'clipboard-list'],
        ['Attendance',             '/attendance',  'scan-line'],
        ['Academic Records',       '/grades',      'book-open'],
        ['Activities',             '/activities',  'calendar-days'],
        ['Student Services',       '/services',    'files'],
        ['AI Insights',            '/insights',    'sparkles'],
        ['Student Standing',       '/guidance',    'shield-check'],
        ['Re-enrollment Forecast', '/forecast',    'trending-up'],
        ['Reports',                '/reports',     'chart-no-axes-combined'],
        ['User Accounts',          '/users',       'user-cog'],
        ['Audit Log',              '/audit',       'history'],
    ],
    'Teacher' => [
        ['Teacher Dashboard', '/teacher',    'layout-dashboard'],
        ['My Students',       '/students',   'users'],
        ['Attendance',        '/attendance', 'scan-line'],
        ['Grades',            '/grades',     'book-open'],
        ['Activities',        '/activities', 'calendar-days'],
        ['AI Insights',       '/insights',   'sparkles'],
        ['Student Standing',  '/guidance',   'shield-check'],
    ],
    'Student' => [
        ['My Dashboard', '/student',          'layout-dashboard'],
        ['My Grades',    '/student/grades',   'book-open'],
        ['My Activities','/activities',       'calendar-days'],
        ['My Requests',  '/student/requests', 'files'],
    ],
    'Guidance' => [
        ['Student Standing', '/guidance', 'shield-check'],
        ['Students',         '/students', 'users'],
        ['AI Insights',      '/insights', 'sparkles'],
    ],
];

// ---- Demo accounts (listed on the login page only when show_demo_accounts is on) ----
$config['demo_password'] = 'Campus@2026';
$config['demo_accounts'] = [
    ['Administrator', 'admin',    'School administrator'],
    ['Teacher',       'teacher',  'Adviser / Mathematics'],
    ['Student',       'student',  'Grade 10 learner'],
    ['Guidance',      'guidance', 'Guidance counselor'],
];

// Forecast: how many section-subject classes one teacher carries (used to estimate teacher allocation)
$config['teacher_class_load'] = 6;
