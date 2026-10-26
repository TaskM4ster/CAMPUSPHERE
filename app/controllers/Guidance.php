<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/** Guidance - student standing, support notes and intervention plans. */
class Guidance extends Base_controller
{
    private function may_write(array $s)
    {
        $role = $this->user['role'];
        if (in_array($role, ['Administrator', 'Guidance'], true)) { return true; }
        return $role === 'Teacher' && $s['section_id'] && $this->teachers->advises($this->user['teacher_id'], $s['section_id']);
    }

    public function index()
    {
        $level = $this->query('level');
        $q = mb_strtolower($this->query('q'));
        $rows = [];
        foreach ($this->students->all($this->user) as $s) {
            if ($level !== '' && (string) $s['standing']['level'] !== $level) { continue; }
            if ($q !== '' && mb_stripos($s['full_name'] . ' ' . $s['student_no'], $q) === false) { continue; }
            $rows[] = $s;
        }
        usort($rows, function ($a, $b) { return [$b['standing']['level'], $b['missing']] <=> [$a['standing']['level'], $a['missing']]; });
        $dist = [0 => 0, 1 => 0, 2 => 0];
        foreach ($this->students->all($this->user) as $s) { $dist[$s['standing']['level']]++; }
        $total = count($rows);
        $page = $this->page(); $per = 12;
        $this->render('guidance/index', [
            'rows' => array_slice($rows, ($page - 1) * $per, $per), 'total' => $total, 'page' => $page, 'per' => $per,
            'level' => $level, 'q' => $this->query('q'), 'dist' => $dist,
            'open_plans' => $this->guidance->open_count(),
            'rules' => config_item('standing_rules'),
        ], 'Student Standing');
    }

    public function show($id)
    {
        $id = (int) $id;
        if (!$this->students->can_view($this->user, $id)) { $this->not_found('student'); }
        $s = $this->students->find($id);
        $sy = $this->school->sy();
        $may = $this->may_write($s);
        $this->render('guidance/show', [
            's' => $s, 'weak' => $this->grades->weak_subjects($id, $sy['id']),
            'trend' => $this->grades->trend($id, $sy['id']),
            'notes' => $may || $this->user['role'] === 'Teacher' ? $this->guidance->notes($id, 30) : [],
            'plans' => $this->guidance->interventions($id),
            'can_write' => $may,
        ], 'Standing — ' . $s['full_name'], '/guidance');
    }

    private function load_writable($id)
    {
        $id = (int) $id;
        if (!$this->students->can_view($this->user, $id)) { $this->not_found('student'); }
        $s = $this->students->find($id);
        if (!$this->may_write($s)) { $this->forbidden(); }
        return $s;
    }

    public function note($id)
    {
        $s = $this->load_writable($id);
        $res = $this->guidance->add_note($this->user, $s['id'], $this->post('note'), $this->post('kind', 'Note'));
        if (!empty($res['ok'])) { $this->audit('guidance.note', 'student', $s['id'], $this->post('kind', 'Note')); }
        $this->respond($res, 'guidance/' . $s['id'], ['note', 'kind']);
    }

    public function intervention($id)
    {
        $s = $this->load_writable($id);
        $res = $this->guidance->add_intervention($this->user, $s['id'], $this->post('title'), $this->post('detail'), $this->post('due_date'));
        if (!empty($res['ok'])) { $this->audit('guidance.intervention', 'student', $s['id'], $this->post('title')); }
        $this->respond($res, 'guidance/' . $s['id'], ['title', 'detail', 'due_date']);
    }

    public function toggle($id, $plan_id)
    {
        $s = $this->load_writable($id);
        $plans = array_column($this->guidance->interventions($s['id']), null, 'id');
        if (!isset($plans[(int) $plan_id])) { $this->not_found('plan'); }
        $this->guidance->set_done((int) $plan_id, !(int) $plans[(int) $plan_id]['is_done']);
        $this->audit('guidance.toggle', 'intervention', (int) $plan_id);
        go('guidance/' . $s['id']);
    }
}
