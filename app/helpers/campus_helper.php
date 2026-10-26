<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * CAMPUSPHERE view/controller helpers (autoloaded).
 */

if (!function_exists('shared_db')) {
    /** One PDO connection per request, shared by auth, middleware, controllers and models. */
    function shared_db()
    {
        static $db = null;
        if ($db === null) {
            $db = lava_instance()->call->database();
        }
        return $db;
    }
}

if (!function_exists('e')) {
    /** Escape for HTML output. */
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('go')) {
    /** Redirect and stop. (LavaLust's redirect() does not halt execution.) */
    function go($uri = '')
    {
        redirect($uri);
        exit;
    }
}

if (!function_exists('now')) {
    function now()   { return date('Y-m-d H:i:s'); }
}
if (!function_exists('today')) {
    function today() { return date('Y-m-d'); }
}

if (!function_exists('fmt_date')) {
    function fmt_date($d, $fmt = 'M j, Y')
    {
        if (!$d) { return '—'; }
        $t = strtotime($d);
        return $t ? date($fmt, $t) : '—';
    }
}
if (!function_exists('fmt_datetime')) {
    function fmt_datetime($d) { return fmt_date($d, 'M j, Y · g:i A'); }
}
if (!function_exists('fmt_time')) {
    function fmt_time($t)
    {
        if (!$t) { return '—'; }
        $x = strtotime('1970-01-01 ' . $t);
        return $x !== false ? date('g:i A', $x) : '—';   // 08:00 in UTC+8 is epoch 0, so test strictly
    }
}
if (!function_exists('time_ago')) {
    function time_ago($datetime)
    {
        $t = strtotime($datetime);
        if (!$t) { return ''; }
        $d = time() - $t;
        if ($d < 60)      { return 'Just now'; }
        if ($d < 3600)    { $m = (int) floor($d / 60);    return $m . ' minute' . ($m == 1 ? '' : 's') . ' ago'; }
        if ($d < 86400)   { $h = (int) floor($d / 3600);  return $h . ' hour' . ($h == 1 ? '' : 's') . ' ago'; }
        if ($d < 172800)  { return 'Yesterday'; }
        if ($d < 2592000) { return (int) floor($d / 86400) . ' days ago'; }
        return fmt_date($datetime);
    }
}
if (!function_exists('num')) {
    function num($n, $dec = 0) { return $n === null ? '—' : number_format((float) $n, $dec); }
}
if (!function_exists('grade_section')) {
    function grade_section($grade, $section) { return 'Grade ' . (int) $grade . ' – ' . $section; }
}
if (!function_exists('initials')) {
    function initials($name)
    {
        $parts = preg_split('/[\s,]+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        $out = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        return $out ?: '?';
    }
}

if (!function_exists('badge')) {
    /** Small status pill. $tone: '' (green) | warning | neutral | danger */
    function badge($text, $tone = '')
    {
        return '<span class="badge ' . e($tone) . '">' . e($text) . '</span>';
    }
}
if (!function_exists('standing_tone')) {
    function standing_tone($label) { return $label === 'Good Standing' ? '' : 'warning'; }
}
if (!function_exists('status_tone')) {
    function status_tone($status)
    {
        switch ($status) {
            case 'Pending': case 'Processing': case 'Not submitted': case 'Missing record': case 'Absent': case 'Late':
            case 'Awaiting verification': case 'Submitted':
                return 'warning';
            case 'Excused': case 'Rejected':
                return 'neutral';
            default:
                return '';
        }
    }
}

if (!function_exists('qs')) {
    /** Current query string with $merge applied; null removes a key. */
    function qs(array $merge = [], $path = '')
    {
        $q = $_GET;
        foreach ($merge as $k => $v) {
            if ($v === null || $v === '') { unset($q[$k]); } else { $q[$k] = $v; }
        }
        $s = http_build_query($q);
        return $path . ($s ? '?' . $s : '');
    }
}
if (!function_exists('old')) {
    /** Re-populate a form field after a failed validation (values are flashed by Base_controller). */
    function old($key, $default = '')
    {
        global $__old;
        return isset($__old[$key]) ? $__old[$key] : $default;
    }
}
if (!function_exists('selected')) {
    function selected($a, $b) { return (string) $a === (string) $b ? ' selected' : ''; }
}
if (!function_exists('checked')) {
    function checked($a, $b) { return (string) $a === (string) $b ? ' checked' : ''; }
}

if (!function_exists('csv_download')) {
    /** Stream rows as an Excel-friendly UTF-8 CSV file and stop. */
    function csv_download($name, array $rows)
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="campusphere-' . $name . '.csv"');
        header('X-Content-Type-Options: nosniff');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            // neutralise spreadsheet formula injection
            $row = array_map(function ($v) {
                $v = (string) $v;
                return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false && !is_numeric($v)) ? "'" . $v : $v;
            }, $row);
            fputcsv($out, $row, ',', '"', '');
        }
        fclose($out);
        exit;
    }
}

if (!function_exists('pager')) {
    /** Render prev/next pagination for ?page=N. */
    function pager($total, $page, $per, $param = 'page')
    {
        $pages = (int) max(1, ceil($total / max(1, $per)));
        if ($pages <= 1) { return ''; }
        $page = max(1, min($pages, (int) $page));
        $from = ($page - 1) * $per + 1;
        $to   = min($total, $page * $per);
        $link = function ($p, $label, $disabled = false) use ($param) {
            return $disabled
                ? '<span class="btn btn-sm is-disabled" aria-disabled="true">' . $label . '</span>'
                : '<a class="btn btn-sm" href="' . e(qs([$param => $p > 1 ? $p : null])) . '">' . $label . '</a>';
        };
        return '<nav class="pager" aria-label="Pagination"><span class="muted">Showing ' . $from . '–' . $to . ' of ' . $total . '</span><span class="pager-links">'
            . $link($page - 1, 'Previous', $page <= 1) . '<span class="pager-page">Page ' . $page . ' / ' . $pages . '</span>' . $link($page + 1, 'Next', $page >= $pages)
            . '</span></nav>';
    }
}
if (!function_exists('valid_date')) {
    /** Return $d when it is a real Y-m-d date, otherwise $fallback. */
    function valid_date($d, $fallback = null)
    {
        $fallback = $fallback ?: today();
        if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) { return $fallback; }
        $t = DateTime::createFromFormat('Y-m-d', $d);
        return ($t && $t->format('Y-m-d') === $d) ? $d : $fallback;
    }
}

if (!function_exists('post_form')) {
    /** Open a POST form with the CSRF token. Close with </form>. */
    function post_form($path, $attrs = '')
    {
        echo '<form method="post" action="' . e(site_url($path)) . '" ' . $attrs . '>' . "\n";
        csrf_field();
    }
}
if (!function_exists('attendance_tone')) {
    function attendance_tone($rate)
    {
        if ($rate === null) { return 'neutral'; }
        return $rate >= 93 ? '' : ($rate >= 90 ? 'warning' : 'danger');
    }
}
if (!function_exists('meter')) {
    /** Horizontal progress bar. */
    function meter($pct, $warn_below = 90, $bad_below = 80)
    {
        $pct = max(0, min(100, (float) $pct));
        $c = $pct < $bad_below ? 'bad' : ($pct < $warn_below ? 'warn' : '');
        return '<div class="meter ' . $c . '" role="img" aria-label="' . round($pct) . ' percent"><i style="width:' . round($pct) . '%"></i></div>';
    }
}
