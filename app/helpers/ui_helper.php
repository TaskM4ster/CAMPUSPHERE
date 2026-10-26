<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * Small presentation helpers shared by the views (markup only, no business logic).
 * Every dynamic value is escaped here, so callers pass plain text unless a parameter says "html".
 */

if (!function_exists('ui_heading')) {
    /** Page heading. $actions_html is trusted markup built by the view. */
    function ui_heading($title, $subtitle = '', $actions_html = '')
    {
        echo '<div class="page-heading"><div><h1>' . e($title) . '</h1>' . ($subtitle !== '' ? '<p>' . e($subtitle) . '</p>' : '') . '</div>'
           . ($actions_html !== '' ? '<div class="panel-actions">' . $actions_html . '</div>' : '') . '</div>';
    }
}
if (!function_exists('ui_metric')) {
    /** KPI tile. $foot_html is trusted markup. $tone: '' | yellow | danger */
    function ui_metric($label, $icon, $value, $foot_html = '', $tone = '')
    {
        return '<div class="metric"><div class="metric-top"><span>' . e($label) . '</span><span class="metric-icon ' . e($tone) . '">' . icon($icon, 17) . '</span></div>'
             . '<div class="metric-number num">' . e($value) . '</div>' . ($foot_html !== '' ? '<div class="metric-foot">' . $foot_html . '</div>' : '') . '</div>';
    }
}
if (!function_exists('ui_panel')) {
    /** Open a panel; close with ui_panel_end(). */
    function ui_panel($title, $subtitle = '', $actions_html = '', $class = '')
    {
        echo '<section class="panel ' . e($class) . '"><div class="panel-header"><div><h2>' . e($title) . '</h2>' . ($subtitle !== '' ? '<p>' . e($subtitle) . '</p>' : '') . '</div>'
           . ($actions_html !== '' ? '<div class="panel-actions">' . $actions_html . '</div>' : '') . '</div>';
    }
}
if (!function_exists('ui_panel_end')) {
    function ui_panel_end() { echo '</section>'; }
}
if (!function_exists('ui_student')) {
    /** Avatar + name + secondary line, linked to the profile when $link is given. */
    function ui_student($name, $sub = '', $initials = null, $link = null)
    {
        $n = $link ? '<a href="' . e($link) . '"><strong>' . e($name) . '</strong></a>' : '<strong>' . e($name) . '</strong>';
        return '<div class="student-cell"><span class="user-avatar">' . e($initials ?: initials($name)) . '</span><div>' . $n
             . ($sub !== '' ? '<small>' . e($sub) . '</small>' : '') . '</div></div>';
    }
}
if (!function_exists('ui_empty')) {
    function ui_empty($text, $icon = 'info') { return '<div class="empty-note">' . icon($icon, 20) . '<p>' . e($text) . '</p></div>'; }
}
if (!function_exists('ui_filters_form')) {
    function ui_filters_open($action) { echo '<form class="filters" method="get" action="' . e(site_url($action)) . '">'; }
}
if (!function_exists('ui_options')) {
    /** <option> list from [value => label]; $current is the selected value. */
    function ui_options(array $opts, $current = '', $blank = null)
    {
        $h = $blank !== null ? '<option value="">' . e($blank) . '</option>' : '';
        foreach ($opts as $v => $l) { $h .= '<option value="' . e($v) . '"' . selected($v, $current) . '>' . e($l) . '</option>'; }
        return $h;
    }
}
if (!function_exists('ui_sections_options')) {
    function ui_sections_options(array $sections, $current = '', $blank = 'All sections')
    {
        $o = [];
        foreach ($sections as $s) { $o[$s['id']] = $s['label']; }
        return ui_options($o, $current, $blank);
    }
}
