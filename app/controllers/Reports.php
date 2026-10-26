<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/** Reports - filtered summaries with on-screen preview and one-click CSV export (administrators). */
class Reports extends Base_controller
{
    public function before_action()
    {
        parent::before_action();
        if ($this->user && $this->user['role'] !== 'Administrator') { $this->forbidden(); }
    }

    public function index()
    {
        $this->render('reports/index', ['catalog' => Report_model::catalog()], 'Reports');
    }

    private function filters($key)
    {
        $f = ['grade' => 0, 'section_id' => 0, 'status' => '', 'from' => '', 'to' => ''];
        $use = Report_model::filters_for($key);
        if (in_array('grade', $use, true))      { $g = (int) $this->query('grade'); $f['grade'] = ($g >= 7 && $g <= 12) ? $g : 0; }
        if (in_array('section_id', $use, true)) { $f['section_id'] = (int) $this->query('section'); }
        if (in_array('status', $use, true))     { $f['status'] = mb_substr($this->query('status'), 0, 30); }
        foreach (['from', 'to'] as $d) {
            if (in_array($d, $use, true) && $this->query($d) !== '') { $f[$d] = valid_date($this->query($d), '') ?: ''; }
        }
        return $f;
    }

    public function download($key)
    {
        $cat = Report_model::catalog();
        if (!isset($cat[$key]) || !in_array($this->user['role'], $cat[$key][2], true)) { $this->not_found('report'); }
        $f = $this->filters($key);
        $data = $this->reports->build($key, $this->user, $this->students, $f);
        if (!$data) { $this->not_found('report'); }
        if ($this->query('format') === 'csv') {
            $this->audit('export', 'report', $key, count($data['rows']) . ' rows');
            csv_download($data['title'] . '-' . date('Ymd'), array_merge([$data['headers']], $data['rows']));
        }
        $this->render('reports/preview', [
            'key' => $key, 'title' => $cat[$key][0], 'desc' => $cat[$key][1], 'report' => $data, 'f' => $f,
            'uses' => Report_model::filters_for($key), 'sections' => $this->section_options(),
            'statuses' => $key === 'enrollment' ? ['Pending', 'Approved', 'Rejected'] : ['Pending', 'Processing', 'Ready', 'Completed'],
        ], 'Reports', '/reports');
    }
}
