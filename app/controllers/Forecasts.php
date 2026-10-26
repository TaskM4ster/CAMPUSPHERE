<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';
require_once APP_DIR . 'libraries/Forecast.php';

/** Forecast - re-enrollment / section planning projection (administrators). */
class Forecasts extends Base_controller
{
    private function projection()
    {
        $sy = $this->school->sy();
        $year = (int) substr($sy['label'], 0, 4);
        return Forecast::project(
            $this->school->grade_counts($sy['id']), $this->school->history(), $year,
            (int) config_item('section_capacity'), $this->school->section_counts_by_grade($sy['id'])
        );
    }

    /** Teacher allocation estimate: needed section-subject classes ÷ classes one teacher carries. */
    private function staffing(array $f)
    {
        $subjects = count($this->school->subjects());
        $load = max(1, (int) config_item('teacher_class_load'));
        $classes = (int) $f['totals']['sections'] * $subjects;
        $now = (int) $this->teachers->count_all();
        $need = (int) ceil($classes / $load);
        return ['subjects' => $subjects, 'classes' => $classes, 'load' => $load, 'needed' => $need, 'have' => $now, 'gap' => $need - $now];
    }

    public function index()
    {
        $this->require_role('Administrator');
        $sy = $this->school->sy();
        $f = $this->projection();
        $this->render('forecast/index', [
            'staff' => $this->staffing($f),
            'f' => $f, 'sy' => $sy, 'history' => $this->school->history(),
            'capacity' => (int) config_item('section_capacity'),
            'enroll' => $this->enrollments->counts(),
        ], 'Re-enrollment Forecast');
    }

    public function export()
    {
        $this->require_role('Administrator');
        $f = $this->projection();
        $rows = [['Grade', 'Current', 'Projected next year', 'Low', 'High', 'Sections now', 'Sections needed', 'Planning note']];
        foreach ($f['grades'] as $g) {
            $rows[] = ['Grade ' . $g['grade'], $g['current'], $g['projected'], $g['low'], $g['high'], $g['sections_have'], $g['sections_need'], $g['plan']];
        }
        $st = $this->staffing($f);
        $rows[] = [];
        $rows[] = ['Estimated teachers needed', $st['needed'], 'Current teachers', $st['have'], 'Classes per teacher', $st['load']];
        $this->audit('export', 'forecast');
        csv_download('forecast', $rows);
    }
}
