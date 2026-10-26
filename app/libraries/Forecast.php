<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Forecast - cohort-survival enrollment projection (decision support).
 *
 *  - Grades 8-12 next year  = this year's previous grade  x  historical retention ratio
 *  - Grade 7 intake         = linear trend of past Grade 7 headcounts
 *  - Sections needed        = ceil(projected / section capacity)
 *
 * Retention ratio for grade g in year y  =  H[y][g] / H[y-1][g-1]; the forecast uses the
 * mean over all consecutive years that have data (including the live current year).
 */
class Forecast
{
    /**
     * @param array $current  [grade => headcount] live headcount this school year
     * @param array $history  [yearStart => [grade => headcount]] past years
     * @param int   $year     current year start (e.g. 2026)
     * @param int   $capacity students per section
     * @param array $sections [grade => existing section count]
     */
    public static function project(array $current, array $history, $year, $capacity = 40, array $sections = [])
    {
        $series = $history;
        $series[$year] = $current;
        ksort($series);

        // --- retention ratios ------------------------------------------------
        $ratios = [];
        foreach (range(8, 12) as $g) {
            $list = [];
            foreach ($series as $y => $row) {
                if (isset($series[$y - 1][$g - 1], $row[$g]) && $series[$y - 1][$g - 1] > 0) {
                    $list[] = $row[$g] / $series[$y - 1][$g - 1];
                }
            }
            $ratios[$g] = $list ? [
                'mean' => array_sum($list) / count($list),
                'min'  => min($list),
                'max'  => max($list),
                'n'    => count($list),
            ] : ['mean' => 0.95, 'min' => 0.92, 'max' => 0.98, 'n' => 0];
        }

        // --- grade 7 intake (least squares trend) ----------------------------
        $pts = [];
        foreach ($series as $y => $row) {
            if (isset($row[7])) {
                $pts[$y] = $row[7];
            }
        }
        $g7 = self::trend($pts, $year + 1, isset($current[7]) ? $current[7] : 0);

        // --- projection ---------------------------------------------------------
        $out = [];
        $tot = ['current' => 0, 'projected' => 0, 'low' => 0, 'high' => 0, 'sections' => 0];
        $returning = 0;
        foreach (range(7, 12) as $g) {
            $cur = isset($current[$g]) ? (int) $current[$g] : 0;
            if ($g === 7) {
                $p = (int) round($g7);
                $lo = (int) round($g7 * 0.95);
                $hi = (int) round($g7 * 1.05);
            } else {
                $prev = isset($current[$g - 1]) ? (int) $current[$g - 1] : 0;
                $p  = (int) round($prev * $ratios[$g]['mean']);
                $lo = (int) round($prev * $ratios[$g]['min']);
                $hi = (int) round($prev * $ratios[$g]['max']);
                $returning += $p;
            }
            $need = $p > 0 ? (int) ceil($p / max(1, $capacity)) : 0;
            $have = isset($sections[$g]) ? (int) $sections[$g] : 0;
            if ($need > $have) {
                $plan = 'Review additional section';
            } elseif ($have > 0 && $p > 0.9 * $have * $capacity) {
                $plan = 'Near section capacity';
            } else {
                $plan = 'Review current capacity';
            }
            $out[$g] = ['grade' => $g, 'current' => $cur, 'projected' => $p, 'low' => $lo, 'high' => $hi,
                        'sections_have' => $have, 'sections_need' => $need, 'plan' => $plan];
            $tot['current']   += $cur;
            $tot['projected'] += $p;
            $tot['low']       += $lo;
            $tot['high']      += $hi;
            $tot['sections']  += $need;
        }
        $continuing = 0;
        foreach (range(7, 11) as $g) {
            $continuing += isset($current[$g]) ? (int) $current[$g] : 0;
        }
        return [
            'grades'      => $out,
            'totals'      => $tot,
            'returning'   => $returning,
            'new_intake'  => $out[7]['projected'],
            'retention'   => $continuing > 0 ? round(100 * $returning / $continuing, 1) : null,
            'ratios'      => $ratios,
            'year'        => $year + 1,
        ];
    }

    /** Least squares line through [x => y]; predicts at $at. Falls back to $fallback. */
    public static function trend(array $pts, $at, $fallback = 0)
    {
        $n = count($pts);
        if ($n < 2) {
            return $n === 1 ? (float) reset($pts) : (float) $fallback;
        }
        $sx = array_sum(array_keys($pts));
        $sy = array_sum($pts);
        $sxx = 0; $sxy = 0;
        foreach ($pts as $x => $y) {
            $sxx += $x * $x;
            $sxy += $x * $y;
        }
        $den = $n * $sxx - $sx * $sx;
        if ($den == 0) {
            return (float) ($sy / $n);
        }
        $b = ($n * $sxy - $sx * $sy) / $den;
        $a = ($sy - $b * $sx) / $n;
        return max(0.0, $a + $b * $at);
    }
}
