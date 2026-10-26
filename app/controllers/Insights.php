<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/**
 * Insights - "AI Insights": transparent, rule-based decision support. It surfaces which
 * learners and subjects need a human look first and always shows why; it never changes records.
 */
class Insights extends Base_controller
{
    public function index()
    {
        $scope = $this->students->scope($this->user);
        $sy    = $this->school->sy();
        $date  = $this->gate->latest_date();
        $review = $this->students->for_review($this->user);
        $top = [];
        foreach (array_slice($review, 0, 8) as $s) {
            $top[] = ['s' => $s, 'weak' => array_slice($this->grades->weak_subjects($s['id'], $sy['id']), 0, 3)];
        }
        $levels = [0 => 0, 1 => 0, 2 => 0];
        foreach ($this->students->all($this->user) as $s) { $levels[$s['standing']['level']]++; }

        $this->render('insights/index', [
            'date'     => $date,
            'top'      => $top,
            'review_total' => count($review),
            'levels'   => $levels,
            'subjects' => $this->grades->subject_averages($scope, $sy['id']),
            'summary'  => $this->attendance->day_summary($date, $scope),
            'disc_count' => $this->attendance->discrepancy_count($date, $scope),
            'rules'    => config_item('standing_rules'),
        ], 'AI Insights');
    }
}
