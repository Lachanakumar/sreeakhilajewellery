<?php
/**
 * Tiny dependency-free SVG chart helpers for the admin dashboard.
 */

/** Sparkline / area line. $values = numeric array. */
function chart_spark(array $values, $w = 240, $h = 56, $color = '#c19a4b') {
    $values = array_values(array_map('floatval', $values));
    $n = count($values);
    if ($n < 2) {
        $values = array_pad($values, 2, $n ? $values[0] : 0);
        $n = 2;
    }
    $min = min($values);
    $max = max($values);
    $range = ($max - $min) ?: 1;
    $stepX = $w / ($n - 1);
    $pts = [];
    foreach ($values as $i => $v) {
        $x = round($i * $stepX, 2);
        $y = round($h - 4 - (($v - $min) / $range) * ($h - 8), 2);
        $pts[] = "$x,$y";
    }
    $line = implode(' ', $pts);
    $area = "0,$h " . $line . "," . $w . ",$h";
    $id = 'sg' . substr(md5($line), 0, 6);
    return '<svg class="chart-spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none">'
        . '<defs><linearGradient id="' . $id . '" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0" stop-color="' . $color . '" stop-opacity=".28"/>'
        . '<stop offset="1" stop-color="' . $color . '" stop-opacity="0"/></linearGradient></defs>'
        . '<polygon points="' . $area . '" fill="url(#' . $id . ')"/>'
        . '<polyline points="' . $line . '" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';
}

/** Vertical bar chart. $data = [ ['label'=>..,'value'=>..], ... ] */
function chart_bars(array $data, $color = '#c19a4b', $height = 180) {
    if (!$data) {
        return '<p class="admin__muted">No data for this range.</p>';
    }
    $max = max(1, max(array_map(fn($d) => (float) $d['value'], $data)));
    $out = '<div class="chart-bars" style="--chart-h:' . (int) $height . 'px">';
    foreach ($data as $d) {
        $pct = round(((float) $d['value'] / $max) * 100, 1);
        $out .= '<div class="chart-bars__col" title="' . e($d['label'] . ': ' . $d['value']) . '">'
            . '<div class="chart-bars__track"><div class="chart-bars__fill" style="height:' . $pct . '%;background:' . $color . '"></div></div>'
            . '<span class="chart-bars__label">' . e($d['label']) . '</span></div>';
    }
    return $out . '</div>';
}

/** Donut chart. $segments = [ ['label'=>..,'value'=>..,'color'=>..], ... ] */
function chart_donut(array $segments, $size = 168) {
    $segments = array_values(array_filter($segments, fn($s) => (float) $s['value'] > 0));
    $total = array_sum(array_map(fn($s) => (float) $s['value'], $segments));
    $r = 60;
    $c = 2 * M_PI * $r;
    $offset = 0;
    $circles = '';
    if ($total <= 0) {
        $circles = '<circle cx="80" cy="80" r="' . $r . '" fill="none" stroke="#e6e8ec" stroke-width="22"/>';
    } else {
        foreach ($segments as $s) {
            $frac = (float) $s['value'] / $total;
            $len = $frac * $c;
            $circles .= '<circle cx="80" cy="80" r="' . $r . '" fill="none" stroke="' . e($s['color']) . '" stroke-width="22"'
                . ' stroke-dasharray="' . round($len, 2) . ' ' . round($c - $len, 2) . '"'
                . ' stroke-dashoffset="' . round(-$offset, 2) . '" transform="rotate(-90 80 80)"/>';
            $offset += $len;
        }
    }
    $legend = '<ul class="chart-donut__legend">';
    foreach ($segments as $s) {
        $legend .= '<li><span style="background:' . e($s['color']) . '"></span>' . e($s['label']) . ' <b>' . (int) $s['value'] . '</b></li>';
    }
    $legend .= '</ul>';
    return '<div class="chart-donut"><svg viewBox="0 0 160 160" width="' . (int) $size . '" height="' . (int) $size . '">'
        . $circles
        . '<text x="80" y="76" text-anchor="middle" class="chart-donut__num">' . (int) $total . '</text>'
        . '<text x="80" y="94" text-anchor="middle" class="chart-donut__cap">total</text>'
        . '</svg>' . $legend . '</div>';
}

/** Coloured status pill. */
function status_pill($status) {
    $map = [
        'pending' => 'amber', 'new' => 'amber', 'draft' => 'grey',
        'confirmed' => 'blue', 'processing' => 'blue', 'in_progress' => 'blue', 'reviewed' => 'blue',
        'shipped' => 'violet',
        'delivered' => 'green', 'completed' => 'green', 'responded' => 'green', 'active' => 'green', 'paid' => 'green', 'approved' => 'green',
        'cancelled' => 'red', 'refunded' => 'red', 'closed' => 'grey', 'rejected' => 'red', 'inactive' => 'grey', 'blocked' => 'red', 'archived' => 'grey', 'failed' => 'red',
        'partially_refunded' => 'amber',
    ];
    $tone = $map[strtolower($status)] ?? 'grey';
    return '<span class="pill pill--' . $tone . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}
