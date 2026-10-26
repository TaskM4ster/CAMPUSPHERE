<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/** Dashboard - administrator overview, derived entirely from live records. */
class Dashboard extends Base_controller
{
    public function index()
    {
        $this->require_role('Administrator');
        $all  = ['1=1', []];
        $date = $this->gate->latest_date() ?: today();

        $this->render('dashboard/index', [
            'date'         => $date,
            'is_today'     => $date === today(),
            'summary'      => $this->attendance->day_summary($date, $all),
            'week'         => $this->attendance->week_chart($date, $all),
            'disc_count'   => $this->attendance->discrepancy_count($date, $all),
            'discrepancies' => $this->attendance->discrepancies($date, $all, 5),
            'counts'       => $this->students->counts(),
            'average'      => $this->students->school_average(),
            'distribution' => $this->students->standing_distribution(),
            'review'       => array_slice($this->students->for_review($this->user), 0, 5),
            'enrollment'   => $this->enrollments->counts(),
            'req_counts'   => $this->requests->status_counts(),
            'req_new'      => $this->requests->new_today(),
            'notices'      => $this->notices->recent($this->user, 4),
            'grade_counts' => $this->school->grade_counts(),
        ], 'Dashboard');
    }
}
