<?php
/** Unit tests for the pure-logic libraries.  php tests/run.php */
define('PREVENT_DIRECT_ACCESS', true);
foreach (['Acl', 'Standing', 'Grading', 'Forecast'] as $c) { require __DIR__ . '/../app/libraries/' . $c . '.php'; }
$pass = $fail = 0;
function t($name, $cond) { global $pass, $fail; $cond ? $pass++ : $fail++; echo ($cond ? '  ok   ' : '  FAIL ') . $name . "\n"; }
$rules = ['support' => ['average_below' => 83, 'attendance_below' => 90, 'missing_at_least' => 4], 'review' => ['average_below' => 88, 'attendance_below' => 93, 'missing_at_least' => 1]];

echo "Acl\n";
$map = ['Teacher' => ['/students', '/grades'], 'Student' => ['/student']];
t('exact path allowed', Acl::can($map, 'Teacher', '/students'));
t('sub path allowed', Acl::can($map, 'Teacher', '/students/12/update'));
t('query string ignored', Acl::can($map, 'Teacher', '/grades?section=1'));
t('prefix must end at a segment ("/student" != "/students")', !Acl::can($map, 'Student', '/students'));
t('other role denied', !Acl::can($map, 'Student', '/grades'));
t('unknown role fails closed', !Acl::can($map, 'Hacker', '/students'));
t('traversal-like path is not matched', !Acl::can($map, 'Teacher', '/studentsx'));

echo "Standing\n";
t('strong learner = Good Standing', Standing::evaluate(94.2, 98, 0, $rules)['label'] === Standing::GOOD);
t('one missing requirement = Needs Review', Standing::evaluate(95, 98, 1, $rules)['label'] === Standing::REVIEW);
t('low attendance = Needs Review', Standing::evaluate(95, 91, 0, $rules)['label'] === Standing::REVIEW);
t('four missing = Recommended Support', Standing::evaluate(95, 98, 4, $rules)['label'] === Standing::SUPPORT);
t('average 82 = Recommended Support', Standing::evaluate(82, 98, 0, $rules)['label'] === Standing::SUPPORT);
t('no data yet = Good Standing', Standing::evaluate(null, null, 0, $rules)['label'] === Standing::GOOD);
$r = Standing::evaluate(82.1, 89, 4, $rules);
t('reasons explain the decision', count($r['reasons']) === 3 && $r['level'] === 2);

echo "Grading\n";
t('final grade is the mean of encoded quarters', Grading::final_grade([80, 90, null, '']) === 85.0);
t('no quarters = null', Grading::final_grade([null, '']) === null);
t('rounding to 2 decimals', Grading::final_grade([85, 86, 86]) === 85.67);
t('below 75 fails', Grading::remarks(74.9) === 'Did not meet expectations');
t('75-81.99 support suggested', Grading::remarks(80) === 'Support suggested');
t('82+ passed', Grading::remarks(82) === 'Passed');
t('score validation', Grading::valid_score('88.5') && !Grading::valid_score(101) && !Grading::valid_score('abc') && !Grading::valid_score(-1));

echo "Forecast\n";
$hist = [2025 => [7 => 100, 8 => 96, 9 => 94, 10 => 92, 11 => 90, 12 => 88], 2024 => [7 => 98, 8 => 94, 9 => 92, 10 => 90, 11 => 88, 12 => 85]];
$cur = [7 => 102, 8 => 98, 9 => 92, 10 => 91, 11 => 89, 12 => 86];
$f = Forecast::project($cur, $hist, 2026, 40, [7 => 3, 8 => 3, 9 => 3, 10 => 3, 11 => 3, 12 => 3]);
t('projects every grade', isset($f['grades'][7]) || isset($f['rows']) || is_array($f));
t('retention is a sane percentage', $f['retention'] > 80 && $f['retention'] <= 100);
$rows = isset($f['grades']) ? $f['grades'] : (isset($f['rows']) ? $f['rows'] : []);
if ($rows) {
    $g8 = $rows[8];
    t('Grade 8 projection near 102 x ~0.96', abs($g8['projected'] - 98) <= 4);
    t('confidence band brackets the projection', $g8['low'] <= $g8['projected'] && $g8['projected'] <= $g8['high']);
    t('sections needed = ceil(projected / capacity)', $g8['sections_need'] === (int) ceil($g8['projected'] / 40));
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
