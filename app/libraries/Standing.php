<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Standing - transparent, rule-based student support indicator.
 *
 * It never changes a record and never labels a learner "failing"; it only
 * suggests where a person (adviser / guidance counselor) should look first.
 * Each result carries the human readable reasons so reviewers can see why.
 */
class Standing
{
    const GOOD    = 'Good Standing';
    const REVIEW  = 'Needs Review';
    const SUPPORT = 'Recommended Support';

    /**
     * @param float|null $average     general average (null when no grades yet)
     * @param float|null $attendance  attendance rate in percent (null when unknown)
     * @param int        $missing     overdue activities without a submission
     * @param array      $rules       config 'standing_rules'
     * @param array      $signals     optional: declining (subjects with falling quarters), low_subjects (final < 80),
     *                                absent_streak (school days since the last gate time-in)
     * @return array{label:string,level:int,reasons:array}
     */
    public static function evaluate($average, $attendance, $missing, array $rules, array $signals = [])
    {
        $reasons = [];
        $level   = 0;

        foreach (['review' => 1, 'support' => 2] as $key => $lvl) {
            $r = $rules[$key];
            $hit = [];
            if ($average !== null && $average < $r['average_below']) {
                $hit[] = sprintf('General average %.1f is below %s', $average, $r['average_below']);
            }
            if ($attendance !== null && $attendance < $r['attendance_below']) {
                $hit[] = sprintf('Attendance %.1f%% is below %s%%', $attendance, $r['attendance_below']);
            }
            if ($missing >= $r['missing_at_least']) {
                $hit[] = sprintf('%d missing requirement%s (threshold %d)', $missing, $missing === 1 ? '' : 's', $r['missing_at_least']);
            }
            if ($hit) {
                $level   = $lvl;
                $reasons = $hit;
            }
        }
        // Multivariate indicators: academic trend, weak subjects, consecutive absences.
        $indicators = [];
        $declining = isset($signals['declining']) ? (int) $signals['declining'] : 0;
        $low       = isset($signals['low_subjects']) ? (int) $signals['low_subjects'] : 0;
        $streak    = isset($signals['absent_streak']) ? (int) $signals['absent_streak'] : 0;
        if ($declining > 0) { $indicators[] = sprintf('%d subject%s with declining quarter grades', $declining, $declining === 1 ? '' : 's'); }
        if ($low > 0)       { $indicators[] = sprintf('%d subject%s below 80', $low, $low === 1 ? '' : 's'); }
        if ($streak >= 3)   { $indicators[] = sprintf('%d consecutive school days without a gate time-in', $streak); }
        if ($indicators) {
            $level = max($level, $streak >= 5 ? 2 : 1);
            $reasons = array_values(array_unique(array_merge($level === 0 ? [] : $reasons, $indicators)));
        }
        $label = [self::GOOD, self::REVIEW, self::SUPPORT][$level];
        if ($level === 0) {
            $reasons = ['Recent attendance, grades and activity completion are consistent.'];
        }
        return ['label' => $label, 'level' => $level, 'reasons' => $reasons, 'indicators' => $indicators,
                'disclaimer' => 'AI-assisted decision support only: these indicators do not assign an official status or replace educator judgment.'];
    }
}
