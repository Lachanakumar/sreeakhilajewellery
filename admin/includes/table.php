<?php
/**
 * Shared admin list-table helpers: whitelisted column sorting, pagination and
 * the pager chrome.
 *
 * These operate on a plain PHP row array so the same code wraps both direct
 * SQL result sets and the getX() helper functions in includes/. Row counts in
 * this admin are small (hundreds, not millions); if a table ever outgrows that,
 * move the sort/limit into its SQL and keep admin_th()/admin_pager() for the UI.
 *
 * Usage in a page:
 *     $rows = admin_list($rows, ['name' => 'name', 'price' => 'sale_price'], 'created_at:desc');
 *     ... <thead><tr><?php echo admin_th('name', 'Name'); ?> ... </tr></thead>
 *     ... </table><?php echo admin_pager();
 *
 * Several tables on one page: give each a distinct $id (the 5th argument) and
 * pass that same id to admin_th()/admin_pager().
 */

const ADMIN_PER_PAGE = [10, 20, 50, 100];

/** Query-string parameter name for a table, namespaced when $id is given. */
function admin_table_param($id, $name) {
    return ($id !== '' ? $id . '_' : '') . $name;
}

/** Current query string with overrides applied; a null/'' value drops the key. */
function admin_qs(array $over = []) {
    $q = $_GET;
    foreach ($over as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    return '?' . http_build_query($q);
}

/**
 * Sort (only when the admin asked for it, so each page keeps its own natural
 * default order) and slice a row array.
 *
 * @param array  $sortable  map of sort key => row key or callable(row)
 * @param string $default   'key' or 'key:desc' — marks the header shown as active
 */
function admin_list(array $rows, array $sortable = [], $default = '', $perPage = 20, $id = '') {
    $pSort = admin_table_param($id, 'sort');
    $pDir  = admin_table_param($id, 'dir');
    $pPage = admin_table_param($id, 'page');
    $pPp   = admin_table_param($id, 'pp');

    [$defKey, $defDir] = array_pad(explode(':', (string) $default), 2, 'asc');

    $asked  = isset($_GET[$pSort]) && isset($sortable[$_GET[$pSort]]) ? $_GET[$pSort] : '';
    $active = $asked !== '' ? $asked : $defKey;
    $dir    = strtolower($_GET[$pDir] ?? ($asked !== '' ? 'asc' : $defDir)) === 'desc' ? 'desc' : 'asc';

    if ($asked !== '') {
        $get = $sortable[$asked];
        $pick = is_callable($get) ? $get : function ($row) use ($get) { return $row[$get] ?? null; };
        usort($rows, function ($a, $b) use ($pick, $dir) {
            $x = $pick($a);
            $y = $pick($b);
            // blanks always sort last, whichever direction
            $xEmpty = ($x === null || $x === '');
            $yEmpty = ($y === null || $y === '');
            if ($xEmpty || $yEmpty) {
                return $xEmpty && $yEmpty ? 0 : ($xEmpty ? 1 : -1);
            }
            if (is_numeric($x) && is_numeric($y)) {
                $c = (float) $x <=> (float) $y;
            } else {
                // ISO dates compare correctly as strings, so one branch covers both
                $c = strnatcasecmp((string) $x, (string) $y);
            }
            return $dir === 'desc' ? -$c : $c;
        });
    }

    $pp = (int) ($_GET[$pPp] ?? $perPage);
    if (!in_array($pp, ADMIN_PER_PAGE, true)) {
        $pp = in_array((int) $perPage, ADMIN_PER_PAGE, true) ? (int) $perPage : 20;
    }

    $total  = count($rows);
    $pages  = max(1, (int) ceil($total / $pp));
    $page   = max(1, min($pages, (int) ($_GET[$pPage] ?? 1)));
    $offset = ($page - 1) * $pp;
    $slice  = array_slice($rows, $offset, $pp);

    $GLOBALS['__admin_tables'][$id] = [
        'id' => $id, 'total' => $total, 'pages' => $pages, 'page' => $page,
        'pp' => $pp, 'offset' => $offset, 'shown' => count($slice),
        'active' => $active, 'dir' => $dir, 'sortable' => $sortable,
    ];
    $GLOBALS['__admin_table_last'] = $id;

    return $slice;
}

function admin_table_meta($id = null) {
    if ($id === null) {
        $id = $GLOBALS['__admin_table_last'] ?? '';
    }
    return $GLOBALS['__admin_tables'][$id] ?? null;
}

/** Total row count for the last (or named) table — for page subtitles. */
function admin_list_total($id = null) {
    $m = admin_table_meta($id);
    return $m ? $m['total'] : 0;
}

/**
 * Rows actually rendered on THIS page of the last (or named) table.
 *
 * Not the same as admin_list_total(): page 3 of a 50-row list shows 10, and an
 * empty filter result shows 0 while the table still exists. The bulk-action
 * controls key off this, so they match what is on screen.
 */
function admin_list_shown($id = null) {
    $m = admin_table_meta($id);
    return $m ? (int) $m['shown'] : 0;
}

/**
 * A table header cell. Renders a sort link when $key is in the table's
 * whitelist, otherwise a plain <th> — so every header can go through here.
 *
 * $class lands on the <th> either way (e.g. 'th--num' to right-align a figure
 * column), so a header keeps the same alignment whether or not it can sort.
 */
function admin_th($key, $label = null, $id = null, $class = '') {
    if ($label === null) {
        $label = $key;
        $key = '';
    }
    $m = admin_table_meta($id);
    if (!$m || $key === '' || !isset($m['sortable'][$key])) {
        return '<th' . ($class ? ' class="' . e($class) . '"' : '') . '>' . e($label) . '</th>';
    }
    $isActive = $m['active'] === $key;
    $next = ($isActive && $m['dir'] === 'asc') ? 'desc' : 'asc';
    $href = admin_qs([
        admin_table_param($m['id'], 'sort') => $key,
        admin_table_param($m['id'], 'dir')  => $next,
        admin_table_param($m['id'], 'page') => null,
    ]);
    $cls = 'th__sort' . ($isActive ? ' is-active is-' . $m['dir'] : '');
    $tip = $isActive
        ? 'Sorted ' . ($m['dir'] === 'asc' ? 'A→Z' : 'Z→A') . ' — click to reverse'
        : 'Sort by ' . $label;
    return '<th class="th--sortable' . ($class ? ' ' . e($class) : '') . '"><a class="' . $cls . '" href="' . e($href) . '"'
        . ' data-tip="' . e($tip) . '" data-tip-pos="bottom">' . e($label)
        . '<svg class="th__ic" width="11" height="14" viewBox="0 0 11 14" aria-hidden="true">'
        . '<path class="th__up" d="M5.5 1.5 9 5.5H2z"/><path class="th__dn" d="M5.5 12.5 2 8.5h7z"/>'
        . '</svg></a></th>';
}

/** "Showing 1–20 of 132" + page links + rows-per-page. Renders after </table>. */
function admin_pager($id = null) {
    $m = admin_table_meta($id);
    if (!$m || $m['total'] === 0) {
        return '';
    }
    $from = $m['offset'] + 1;
    $to   = $m['offset'] + $m['shown'];
    $pParam = admin_table_param($m['id'], 'page');

    $link = function ($page, $label, $cls = '', $aria = '') use ($pParam) {
        $href = admin_qs([$pParam => $page > 1 ? $page : null]);
        return '<a class="pager__btn ' . $cls . '" href="' . e($href) . '"'
            . ($aria ? ' aria-label="' . e($aria) . '"' : '') . '>' . $label . '</a>';
    };

    $out = '<div class="pager">';
    $out .= '<div class="pager__info">Showing <b>' . $from . '&ndash;' . $to . '</b> of <b>'
          . number_format($m['total']) . '</b></div>';

    if ($m['pages'] > 1) {
        $out .= '<nav class="pager__pages" aria-label="Pagination">';
        $out .= $m['page'] > 1
            ? $link($m['page'] - 1, '&lsaquo;', '', 'Previous page')
            : '<span class="pager__btn is-off">&lsaquo;</span>';

        // first / last always visible, a window of ±1 around the current page
        $win = [];
        for ($i = 1; $i <= $m['pages']; $i++) {
            if ($i === 1 || $i === $m['pages'] || abs($i - $m['page']) <= 1) {
                $win[] = $i;
            }
        }
        $prev = 0;
        foreach ($win as $i) {
            if ($prev && $i - $prev > 1) {
                $out .= '<span class="pager__gap">&hellip;</span>';
            }
            $out .= $i === $m['page']
                ? '<span class="pager__btn is-active" aria-current="page">' . $i . '</span>'
                : $link($i, (string) $i);
            $prev = $i;
        }

        $out .= $m['page'] < $m['pages']
            ? $link($m['page'] + 1, '&rsaquo;', '', 'Next page')
            : '<span class="pager__btn is-off">&rsaquo;</span>';
        $out .= '</nav>';
    }

    if ($m['total'] > ADMIN_PER_PAGE[0]) {
        $ppParam = admin_table_param($m['id'], 'pp');
        $out .= '<label class="pager__pp">Rows <select data-nav-select>';
        foreach (ADMIN_PER_PAGE as $n) {
            $url = admin_qs([$ppParam => $n, $pParam => null]);
            $out .= '<option value="' . e($url) . '"' . ($n === $m['pp'] ? ' selected' : '') . '>' . $n . '</option>';
        }
        $out .= '</select></label>';
    }

    return $out . '</div>';
}
