<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * Dependency-free SVG charts (no JavaScript, no CDN, print friendly).
 * Colours come from CSS variables so the charts follow the design system.
 *
 * Series format: [ ['name' => 'Present', 'color' => 'var(--chart-green)', 'values' => [..]], ... ]
 */

if (!function_exists('chart_nice_max')) {
    function chart_nice_max($max)
    {
        if ($max <= 0) { return 4; }
        $exp = floor(log10($max));
        $f   = $max / pow(10, $exp);
        $nf  = $f <= 1 ? 1 : ($f <= 2 ? 2 : ($f <= 2.5 ? 2.5 : ($f <= 5 ? 5 : 10)));
        return $nf * pow(10, $exp);
    }
}

if (!function_exists('chart_axes')) {
    /** @return array [svgString, plot box] gridlines + y labels. */
    function chart_axes($W, $H, $m, $min, $max, $ticks = 4, $fmt = null)
    {
        $svg = '';
        $ph  = $H - $m['t'] - $m['b'];
        for ($i = 0; $i <= $ticks; $i++) {
            $v = $min + ($max - $min) * $i / $ticks;
            $y = $m['t'] + $ph - ($ph * $i / $ticks);
            $svg .= '<line class="grid" x1="' . $m['l'] . '" x2="' . ($W - $m['r']) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '"/>';
            $label = $fmt ? $fmt($v) : number_format($v, $v == floor($v) ? 0 : 1);
            $svg .= '<text class="tick" x="' . ($m['l'] - 6) . '" y="' . round($y + 3, 1) . '" text-anchor="end">' . e($label) . '</text>';
        }
        return $svg;
    }
}

if (!function_exists('chart_bars')) {
    function chart_bars(array $labels, array $series, array $o = [])
    {
        $W = 600; $H = isset($o['height']) ? $o['height'] : 230;
        $m = ['l' => 44, 'r' => 8, 't' => 12, 'b' => 28];
        $max = 0;
        foreach ($series as $s) { $max = max($max, $s['values'] ? max($s['values']) : 0); }
        $max = isset($o['max']) ? $o['max'] : chart_nice_max($max);
        $pw = $W - $m['l'] - $m['r']; $ph = $H - $m['t'] - $m['b'];
        $n  = max(1, count($labels));
        $gw = $pw / $n;
        $sc = max(1, count($series));
        $bw = min(26, ($gw * 0.7) / $sc);
        $svg = '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . e(isset($o['label']) ? $o['label'] : 'Bar chart') . '">';
        $svg .= chart_axes($W, $H, $m, 0, $max);
        foreach ($labels as $i => $label) {
            $gx = $m['l'] + $gw * $i + ($gw - $bw * $sc - 4 * ($sc - 1)) / 2;
            foreach ($series as $k => $s) {
                $v  = isset($s['values'][$i]) ? $s['values'][$i] : 0;
                $bh = $max > 0 ? $ph * $v / $max : 0;
                $x  = $gx + $k * ($bw + 4);
                $y  = $m['t'] + $ph - $bh;
                $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bw, 1) . '" height="' . round(max(0, $bh), 1)
                      . '" rx="3" style="fill:' . e($s['color']) . '"><title>' . e($label . ' · ' . $s['name'] . ': ' . number_format($v)) . '</title></rect>';
            }
            $svg .= '<text class="tick" x="' . round($m['l'] + $gw * $i + $gw / 2, 1) . '" y="' . ($H - 8) . '" text-anchor="middle">' . e($label) . '</text>';
        }
        return $svg . '</svg>';
    }
}

if (!function_exists('chart_lines')) {
    function chart_lines(array $labels, array $series, array $o = [])
    {
        $W = 600; $H = isset($o['height']) ? $o['height'] : 230;
        $m = ['l' => 44, 'r' => 14, 't' => 14, 'b' => 28];
        $all = [];
        foreach ($series as $s) { foreach ($s['values'] as $v) { if ($v !== null) { $all[] = $v; } } }
        $min = isset($o['min']) ? $o['min'] : ($all ? floor(min($all) / 5) * 5 - 5 : 0);
        $max = isset($o['max']) ? $o['max'] : ($all ? ceil(max($all) / 5) * 5 + 5 : 100);
        if ($max <= $min) { $max = $min + 10; }
        $pw = $W - $m['l'] - $m['r']; $ph = $H - $m['t'] - $m['b'];
        $n  = count($labels);
        $px = function ($i) use ($m, $pw, $n) { return $m['l'] + ($n > 1 ? $pw * $i / ($n - 1) : $pw / 2); };
        $py = function ($v) use ($m, $ph, $min, $max) { return $m['t'] + $ph - $ph * ($v - $min) / ($max - $min); };
        $svg = '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . e(isset($o['label']) ? $o['label'] : 'Line chart') . '">';
        $svg .= chart_axes($W, $H, $m, $min, $max);
        foreach ($labels as $i => $label) {
            $svg .= '<text class="tick" x="' . round($px($i), 1) . '" y="' . ($H - 8) . '" text-anchor="middle">' . e($label) . '</text>';
        }
        foreach ($series as $s) {
            $pts = []; $dots = '';
            foreach ($s['values'] as $i => $v) {
                if ($v === null) { continue; }
                $pts[] = round($px($i), 1) . ',' . round($py($v), 1);
                $dots .= '<circle cx="' . round($px($i), 1) . '" cy="' . round($py($v), 1) . '" r="3.5" style="fill:' . e($s['color'])
                       . '"><title>' . e($labels[$i] . ' · ' . $s['name'] . ': ' . number_format($v, 1)) . '</title></circle>';
            }
            if ($pts) {
                $svg .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" style="stroke:' . e($s['color']) . '"/>' . $dots;
            }
        }
        return $svg . '</svg>';
    }
}

if (!function_exists('chart_area')) {
    function chart_area(array $labels, array $values, array $o = [])
    {
        $W = 600; $H = isset($o['height']) ? $o['height'] : 230;
        $m = ['l' => 48, 'r' => 14, 't' => 16, 'b' => 28];
        $min = isset($o['min']) ? $o['min'] : max(0, floor(min($values) * 0.8 / 50) * 50);
        $max = isset($o['max']) ? $o['max'] : ceil(max($values) * 1.1 / 50) * 50;
        if ($max <= $min) { $max = $min + 50; }
        $pw = $W - $m['l'] - $m['r']; $ph = $H - $m['t'] - $m['b']; $n = count($labels);
        $px = function ($i) use ($m, $pw, $n) { return $m['l'] + ($n > 1 ? $pw * $i / ($n - 1) : $pw / 2); };
        $py = function ($v) use ($m, $ph, $min, $max) { return $m['t'] + $ph - $ph * ($v - $min) / ($max - $min); };
        $svg = '<svg class="chart" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . e(isset($o['label']) ? $o['label'] : 'Area chart') . '">';
        $svg .= chart_axes($W, $H, $m, $min, $max);
        $pts = []; $dots = '';
        foreach ($values as $i => $v) {
            $pts[] = round($px($i), 1) . ',' . round($py($v), 1);
            $proj = !empty($o['projected_last']) && $i === $n - 1;
            $dots .= '<circle cx="' . round($px($i), 1) . '" cy="' . round($py($v), 1) . '" r="4" style="fill:' . ($proj ? 'var(--yellow)' : 'var(--chart-green)') . '"><title>'
                   . e($labels[$i] . ': ' . number_format($v) . ($proj ? ' (projected)' : '')) . '</title></circle>';
            $svg .= '<text class="tick" x="' . round($px($i), 1) . '" y="' . ($H - 8) . '" text-anchor="middle">' . e($labels[$i]) . '</text>';
        }
        $base = $m['t'] + $ph;
        $svg .= '<polygon points="' . round($px(0), 1) . ',' . $base . ' ' . implode(' ', $pts) . ' ' . round($px($n - 1), 1) . ',' . $base . '" style="fill:var(--secondary)"/>';
        $svg .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke-width="3" stroke-linejoin="round" style="stroke:var(--chart-green)"/>' . $dots;
        return $svg . '</svg>';
    }
}

if (!function_exists('chart_donut')) {
    /** @param array $segments [ ['label'=>, 'value'=>, 'color'=>], ... ] */
    function chart_donut(array $segments, $center_value, $center_label)
    {
        $total = 0;
        foreach ($segments as $s) { $total += $s['value']; }
        $c = 2 * M_PI * 47;
        $svg = '<svg viewBox="0 0 120 120" role="img" aria-label="' . e($center_label) . '">'
             . '<circle cx="60" cy="60" r="47" fill="none" stroke-width="12" style="stroke:var(--border)"/>';
        $offset = 0;
        foreach ($segments as $s) {
            if ($total <= 0 || $s['value'] <= 0) { continue; }
            $len = $c * $s['value'] / $total;
            $svg .= '<circle cx="60" cy="60" r="47" fill="none" stroke-width="12" transform="rotate(-90 60 60)" stroke-dasharray="'
                  . round($len, 2) . ' ' . round($c - $len, 2) . '" stroke-dashoffset="' . round(-$offset, 2) . '" style="stroke:' . e($s['color'])
                  . '"><title>' . e($s['label'] . ': ' . number_format($s['value'])) . '</title></circle>';
            $offset += $len;
        }
        $svg .= '</svg><div class="donut-value"><strong>' . $center_value . '</strong><span>' . e($center_label) . '</span></div>';
        return $svg;
    }
}

if (!function_exists('chart_legend')) {
    function chart_legend(array $series)
    {
        $h = '<div class="legend">';
        foreach ($series as $s) {
            $h .= '<span><i class="legend-dot" style="background:' . e($s['color']) . '"></i>' . e($s['name']) . '</span>';
        }
        return $h . '</div>';
    }
}
