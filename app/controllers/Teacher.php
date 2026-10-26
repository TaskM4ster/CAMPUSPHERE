<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
require_once APP_DIR . 'controllers/Base_controller.php';

/** Teacher - class overview for the signed-in teacher. */
class Teacher extends Base_controller
{
    public function index()
    {
        $this->require_role('Teacher');
        $tid   = (int) $this->user['teacher_id'];
        $scope = $this->students->scope($this->user);
        $date  = $this->gate->latest_date() ?: today();

        $this->render('dashboard/teacher', [
            'date'          => $date,
            'teacher'       => $this->teachers->find($tid),
            'sections'      => $this->teachers->sections($tid),
            'assignments'   => $this->teachers->assignments($tid),
            'summary'       => $this->attendance->day_summary($date, $scope),
            'disc_count'    => $this->attendance->discrepancy_count($date, $scope),
            'discrepancies' => $this->attendance->discrepancies($date, $scope, 6),
            'to_check'      => $this->activities->to_check_for_teacher($tid),
            'activities'    => array_slice($this->activities->list_for($this->user), 0, 5),
            'review'        => array_slice($this->students->for_review($this->user), 0, 6),
            'notices'       => $this->notices->recent($this->user, 3),
        ], 'My Classes');
    }
}
