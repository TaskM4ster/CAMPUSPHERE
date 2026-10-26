<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/** Grading - final grade computation and remarks. Pure logic. */
class Grading
{
    /** Final grade = mean of the encoded quarters (null when nothing encoded). */
    public static function final_grade(array $quarters)
    {
        $vals = array_values(array_filter($quarters, function ($v) {
            return $v !== null && $v !== '';
        }));
        if (!$vals) {
            return null;
        }
        return round(array_sum($vals) / count($vals), 2);
    }

    public static function remarks($final, $passing = 75, $support_below = 82)
    {
        if ($final === null) {
            return null;
        }
        if ($final < $passing) {
            return 'Did not meet expectations';
        }
        if ($final < $support_below) {
            return 'Support suggested';
        }
        return 'Passed';
    }

    /** DepEd grades are whole numbers 60-100; we accept 0-100 with up to two decimals. */
    public static function valid_score($v)
    {
        return is_numeric($v) && $v >= 0 && $v <= 100;
    }
}
