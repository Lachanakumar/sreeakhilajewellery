<?php
/**
 * Admin UI kit — the pieces every list page is built from, so a change here
 * lands on all of them at once:
 *
 *   admin_crumbs()      breadcrumb above the page title
 *   admin_stat_cards()  the tinted KPI row
 *   admin_filter_*()    the filter bar shell + its two buttons
 *   admin_list_head()   "Products (13)" bar with optional bulk actions
 *   admin_bulk_bar()    the bulk-action select + Apply (posts do=bulk)
 *   admin_acts_*()      the round icon actions at the end of a row
 *   admin_thumb()       44px rounded row thumbnail with a fallback
 *
 * Pair with admin/includes/table.php (admin_list/admin_th/admin_pager).
 */

// status_pill() / the chart helpers are used by nearly every list page now
require_once __DIR__ . '/charts.php';

/* =========================================================================
   Notifications — every admin action reports back
   -------------------------------------------------------------------------
   Four types: ok (done), err (failed), warn (nothing done / needs attention),
   info (neutral). Queued in the session, drained once by admin_layout_start()
   and shown as toasts. Several can be queued for one request.
   ========================================================================= */

const ADMIN_NOTIFY_TYPES = ['ok', 'err', 'warn', 'info'];

function admin_notify($type, $message) {
    $message = trim((string) $message);
    if ($message === '') {
        return;
    }
    if (!in_array($type, ADMIN_NOTIFY_TYPES, true)) {
        $type = 'info';
    }
    $_SESSION['admin_notices'][] = ['type' => $type, 'msg' => $message];
}

function admin_ok($message)   { admin_notify('ok', $message); }
function admin_err($message)  { admin_notify('err', $message); }
function admin_warn($message) { admin_notify('warn', $message); }
function admin_info($message) { admin_notify('info', $message); }

/**
 * Drain the queue. Also picks up the older flash_set('admin_ok'|'admin_err')
 * calls still scattered through the pages, so both styles work.
 */
function admin_notify_take() {
    $out = $_SESSION['admin_notices'] ?? [];
    unset($_SESSION['admin_notices']);

    if ($m = flash_get('admin_ok'))  { $out[] = ['type' => 'ok',  'msg' => $m]; }
    if ($m = flash_get('admin_err')) { $out[] = ['type' => 'err', 'msg' => $m]; }
    if ($m = flash_get('admin_warn')) { $out[] = ['type' => 'warn', 'msg' => $m]; }
    if ($m = flash_get('admin_info')) { $out[] = ['type' => 'info', 'msg' => $m]; }

    return $out;
}

/**
 * Guard a state-changing POST. On a bad/expired token it warns and bounces
 * back instead of failing silently, which is what every page used to do.
 *
 * @return bool true when the request is a verified POST worth handling
 */
function admin_post_ok($redirectTo = null) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return false;
    }
    if (csrf_verify()) {
        return true;
    }
    admin_warn('Your session expired before that could be saved. Please try again.');
    redirect($redirectTo ?: basename($_SERVER['SCRIPT_NAME']));
    return false; // not reached
}

/** Small inline icons used by the chrome (nav icons live in layout.php). */
function admin_ui_icon($name, $size = 16) {
    $p = [
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'filter'   => '<path d="M3 5h18l-7 8v5l-4 2v-7z"/>',
        'reset'    => '<path d="M3 12a9 9 0 1 0 2.6-6.4"/><path d="M3 4v5h5"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'edit'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'copy'     => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'trash'    => '<path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/>',
        'kebab'    => '<circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/>',
        'external' => '<path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M18 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5"/>',
        'bell'     => '<path d="M18 9a6 6 0 1 0-12 0c0 6-2 7-2 7h16s-2-1-2-7z"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'chevron'  => '<path d="m6 9 6 6 6-6"/>',
        'box'      => '<path d="M20 7 12 3 4 7v10l8 4 8-4z"/><path d="m4 7 8 4 8-4M12 11v10"/>',
        'check'    => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
        'alert'    => '<path d="M10.3 3.6 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
        'draft'    => '<circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/>',
        'star'     => '<path d="m12 3 2.6 5.3 5.9.9-4.3 4.1 1 5.9L12 16.5 6.8 19.2l1-5.9L3.5 9.2l5.9-.9z"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 3-6 6.5-6s6.5 2.4 6.5 6"/>',
        'cart'     => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>',
        'rupee'    => '<path d="M6 4h12M6 9h12M16 4c0 5-4 5-7 5l7 10"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'chat'     => '<path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.5 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7A8.4 8.4 0 0 1 4 11.5 8.4 8.4 0 0 1 12.5 3 8.4 8.4 0 0 1 21 11.5z"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'moon'     => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'tag'      => '<path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"/>',
        'image'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m21 16-5-5-6 6"/>',
        'upload'   => '<path d="M12 16V4"/><path d="m7.5 8.5 4.5-4.5 4.5 4.5"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
        'send'     => '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/>',
    ];
    return '<svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none"'
        . ' stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}

/** Breadcrumb strip that sits above the page title. */
function admin_crumbs(array $trail) {
    $out = '<nav class="crumbs" aria-label="Breadcrumb">';
    $last = count($trail) - 1;
    $i = 0;
    foreach ($trail as $label => $href) {
        if ($i) {
            $out .= '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg>';
        }
        $out .= ($i === $last || $href === '')
            ? '<b>' . e($label) . '</b>'
            : '<a href="' . e($href) . '">' . e($label) . '</a>';
        $i++;
    }
    return $out . '</nav>';
}

/**
 * KPI row. Each card: ['label','value','icon','tone','trend','trend_dir','hint'].
 * tone: brand|green|amber|red|blue|violet. The wave at the foot is decorative —
 * deliberately not a chart, so it can never be misread as data.
 */
function admin_stat_cards(array $cards) {
    $out = '<div class="statgrid">';
    foreach ($cards as $c) {
        $tone = $c['tone'] ?? 'brand';
        $out .= '<div class="statcard statcard--' . e($tone) . '">'
            . '<div class="statcard__top">'
            . '<span class="statcard__ic">' . admin_ui_icon($c['icon'] ?? 'box', 19) . '</span>'
            . '<div class="statcard__meta"><b class="statcard__val">' . e((string) $c['value']) . '</b>'
            . '<span class="statcard__lbl">' . e($c['label']) . '</span></div>';
        if (!empty($c['trend'])) {
            $dir = $c['trend_dir'] ?? 'flat';
            $arrow = $dir === 'up' ? '&uarr;' : ($dir === 'down' ? '&darr;' : '');
            $out .= '<span class="statcard__trend is-' . e($dir) . '">' . $arrow . ' ' . e($c['trend']) . '</span>';
        }
        $out .= '</div>';
        if (!empty($c['hint'])) {
            $out .= '<span class="statcard__hint">' . e($c['hint']) . '</span>';
        }
        $out .= '<svg class="statcard__wave" viewBox="0 0 100 40" preserveAspectRatio="none" aria-hidden="true">'
            . '<path d="M0,28 C12,18 20,34 32,26 C44,18 52,30 64,22 C76,14 86,26 100,18 L100,40 L0,40 Z"/></svg>'
            . '</div>';
    }
    return $out . '</div>';
}

/** Filter bar shell — wrap the .field controls of a GET form. */
function admin_filter_open($extraClass = '') {
    return '<form method="get" class="filterbar ' . e($extraClass) . '"><div class="filterbar__fields">';
}
function admin_filter_close($resetUrl) {
    return '</div><div class="filterbar__actions">'
        . '<button class="btn" type="submit">' . admin_ui_icon('filter', 15) . ' Filter</button>'
        . '<a class="btn btn--ghost" href="' . e($resetUrl) . '">' . admin_ui_icon('reset', 15) . ' Reset</a>'
        . '</div></form>';
}

/** A search field with the magnifier tucked inside. */
function admin_filter_search($name, $value, $placeholder = 'Search…', $label = 'Search') {
    return '<div class="field field--icon"><label>' . e($label) . '</label>'
        . '<div class="field__wrap">' . admin_ui_icon('search', 15)
        . '<input type="text" name="' . e($name) . '" value="' . e((string) $value) . '" placeholder="' . e($placeholder) . '">'
        . '</div></div>';
}

/**
 * The right-hand "Publish" rail shared by every admin form, matching the
 * product form. Pass $statusName = '' to omit the status select entirely.
 *
 * @param string $saveLabel  primary button text
 * @param string $status     currently selected status
 * @param string $cancelUrl  shows a Cancel link when non-empty
 * @param array  $statuses   value => label
 * @param string $extraHtml  appended inside the card (toggles, hints, …)
 */
function admin_form_side($saveLabel, $status = 'active', $cancelUrl = '',
                         array $statuses = ['active' => 'Active', 'inactive' => 'Inactive'],
                         $extraHtml = '', $statusName = 'status') {
    $out = '<aside class="pform__side"><div class="admin__card fsec">'
        . '<div class="fsec__head"><span class="fsec__ic">' . admin_ui_icon('send', 18) . '</span>'
        . '<div><h3>Publish</h3></div></div>';

    if ($statusName !== '' && $statuses) {
        $out .= '<div class="field"><label>Status</label>'
             . '<select name="' . e($statusName) . '" data-tip="Inactive hides this from the storefront" data-tip-pos="left">';
        foreach ($statuses as $val => $label) {
            $out .= '<option value="' . e($val) . '"' . ((string) $status === (string) $val ? ' selected' : '') . '>' . e($label) . '</option>';
        }
        $out .= '</select></div>';
    }

    $out .= $extraHtml;
    $out .= '<button class="btn btn--block" type="submit">' . admin_ui_icon('check', 16) . ' ' . e($saveLabel) . '</button>';
    if ($cancelUrl !== '') {
        $out .= '<a class="btn btn--ghost btn--block" href="' . e($cancelUrl) . '">Cancel</a>';
    }
    return $out . '</div></aside>';
}

/**
 * Segmented status filter for the pages that tab by status instead of using a
 * form. $options is value => label; $param is the query key it drives.
 * $drop lists query keys to clear when switching (e.g. an open detail view).
 */
function admin_chip_filter($page, array $options, $current, $param = 'status', array $drop = []) {
    $out = '<div class="chipbar" role="group" aria-label="Filter by status">';
    foreach ($options as $val => $label) {
        $on = (string) $current === (string) $val;
        $qs = admin_qs([$param => $val, 'page' => null] + array_fill_keys($drop, null));
        $out .= '<a class="chip' . ($on ? ' is-on' : '') . '" href="' . e($page . $qs) . '"'
            . ($on ? ' aria-current="true"' : '') . '>' . e($label) . '</a>';
    }
    return $out . '</div>';
}

/**
 * Header bar of a list card: title + row count, with anything else on the right
 * (usually admin_bulk_bar()).
 */
function admin_list_head($title, $count = null, $rightHtml = '') {
    return '<div class="tbl__head"><h3>' . e($title)
        . ($count === null ? '' : ' <span>(' . number_format((int) $count) . ')</span>') . '</h3>'
        . ($rightHtml ? '<div class="tbl__head--right">' . $rightHtml . '</div>' : '')
        . '</div>';
}

/**
 * Bulk action select + Apply. Renders a standalone <form id="bulkForm">; the row
 * checkboxes join it with form="bulkForm" so nothing has to nest inside it.
 * $options: value => label.
 */
/**
 * Is there anything on screen for a bulk action to act on?
 *
 * Driven by the table metadata the rows themselves came from, so an empty list
 * disables its own bulk controls without every page having to remember a flag.
 * A page that renders its own markup registers no table, and nothing is assumed
 * about it — the controls stay live there.
 */
function admin_bulk_has_rows() {
    return admin_table_meta() === null || admin_list_shown() > 0;
}

function admin_bulk_bar(array $options, $formId = 'bulkForm', $formAttrs = '', $extraFields = '') {
    // nothing listed means nothing to act on, so the whole bar goes inert
    $live = admin_bulk_has_rows();
    $off  = $live ? '' : ' disabled';
    $tip  = $live ? 'Choose an action, tick the rows, then Apply' : 'Nothing to act on — the list is empty';
    $out = '<form method="post" id="' . e($formId) . '" class="bulkbar' . ($live ? '' : ' bulkbar--off') . '" data-bulk-form'
        . ($formAttrs !== '' ? ' ' . $formAttrs : '') . '>'
        . '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'
        . '<input type="hidden" name="do" value="bulk">'
        . $extraFields
        . '<select name="bulk_action" aria-label="Bulk action"' . $off . ' data-tip="' . e($tip) . '" data-tip-pos="bottom"><option value="">Bulk Actions</option>';
    foreach ($options as $v => $l) {
        $out .= '<option value="' . e($v) . '">' . e($l) . '</option>';
    }
    return $out . '</select><button class="btn" type="submit"' . $off . ' data-tip="'
        . ($live ? 'Run the chosen action on every ticked row' : e($tip))
        . '" data-tip-pos="bottom">Apply</button></form>';
}

/** Header + body cells for the bulk-select column. */
function admin_check_all($formId = 'bulkForm') {
    // no data-tip here: the checkbox paints its tick in ::after, and the tooltip
    // bubble is also an ::after — one element cannot have both
    // an empty list leaves this the only checkbox on the page; ticking it can
    // do nothing, so it is disabled rather than left looking operable
    $off = admin_bulk_has_rows() ? '' : ' disabled';
    return '<th class="col-chk"><input type="checkbox" class="chk" data-check-all form="' . e($formId) . '"' . $off . ' aria-label="Select all"></th>';
}
function admin_check_row($id, $formId = 'bulkForm') {
    return '<td class="col-chk"><input type="checkbox" class="chk" name="ids[]" value="' . (int) $id . '" form="' . e($formId) . '" aria-label="Select row"></td>';
}

/** 44px rounded row thumbnail; $src is web-relative to the site root. */
function admin_thumb($src, $alt = '') {
    $src = trim((string) $src);
    if ($src === '' || !is_file(__DIR__ . '/../../' . $src)) {
        return '<span class="thumb thumb--empty">' . admin_ui_icon('box', 16) . '</span>';
    }
    return '<img class="thumb" src="../' . e($src) . '" alt="' . e($alt) . '" loading="lazy">';
}

/* ------------------------------------------------------------------ actions */

function admin_acts_open() { return '<td class="col-act"><div class="acts">'; }
function admin_acts_close() { return '</div></td>'; }

/**
 * Icon link (edit, view, …).
 * data-tip (not title) drives the styled tooltip in admin.css — emitting it
 * here avoids the native tooltip flashing before ui.js converts it.
 */
function admin_act_link($href, $icon, $title, $class = '', $blank = false) {
    return '<a class="act ' . e($class) . '" href="' . e($href) . '" data-tip="' . e($title) . '" data-tip-pos="left"'
        . ' aria-label="' . e($title) . '"' . ($blank ? ' target="_blank" rel="noopener"' : '') . '>'
        . admin_ui_icon($icon, 15) . '</a>';
}

/**
 * An icon action that must collect a short reason before it runs.
 *
 * Same tiny self-contained form as admin_act_post(), plus a hidden `reason`
 * field that assets/js/admin/ui.js fills from a prompt on submit and refuses to
 * leave empty. Without JS the field posts blank and the server rejects it with
 * the same message, so the rule holds either way.
 */
function admin_act_reason($do, $id, $icon, $title, $promptText, $class = '') {
    return '<form method="post" data-reason-prompt="' . e($promptText) . '">'
        . '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'
        . '<input type="hidden" name="do" value="' . e($do) . '">'
        . '<input type="hidden" name="id" value="' . (int) $id . '">'
        . '<input type="hidden" name="reason" value="">'
        . '<button class="act ' . e($class) . '" type="submit" data-tip="' . e($title) . '" data-tip-pos="left" aria-label="' . e($title) . '">'
        . admin_ui_icon($icon, 15) . '</button></form>';
}

/**
 * "View on site" for a storefront row.
 *
 * Every one of these used to point at plain ../index.php, so whichever slide or
 * banner you clicked you landed on the same homepage with no way to tell which
 * one you had asked for. $href is the deep link to that specific item.
 *
 * $hiddenWhy, when set, is the reason the item is not on the page right now
 * (inactive, not yet started, expired). The link still works — the homepage is
 * still worth seeing — but it says so up front rather than sending someone to
 * hunt for something that was never rendered.
 */
function admin_act_view_site($href, $hiddenWhy = '') {
    return admin_act_link(
        $href,
        'external',
        $hiddenWhy !== '' ? 'View on site — ' . $hiddenWhy : 'View on site',
        $hiddenWhy !== '' ? 'act--muted' : '',
        true
    );
}

/** Icon button that posts do/id in its own tiny form (no nesting, works without JS). */
function admin_act_post($do, $id, $icon, $title, $class = '', $confirm = '') {
    return '<form method="post"' . ($confirm ? ' onsubmit="return confirm(' . e(json_encode($confirm)) . ')"' : '') . '>'
        . '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'
        . '<input type="hidden" name="do" value="' . e($do) . '">'
        . '<input type="hidden" name="id" value="' . (int) $id . '">'
        . '<button class="act ' . e($class) . '" type="submit" data-tip="' . e($title) . '" data-tip-pos="left" aria-label="' . e($title) . '">'
        . admin_ui_icon($icon, 15) . '</button></form>';
}

/**
 * The "⋮" overflow menu. $items is a list of:
 *   ['label'=>.., 'href'=>..,  'icon'=>.., 'blank'=>bool]         a link
 *   ['label'=>.., 'do'=>..,    'id'=>.., 'fields'=>[k=>v], 'icon'=>.., 'confirm'=>..,'danger'=>bool]  a POST
 *   ['sep'=>true]                                                 a divider
 */
function admin_act_menu(array $items) {
    $out = '<div class="act__menu"><button class="act" type="button" data-menu-toggle data-tip="More actions" data-tip-pos="left" aria-label="More actions">'
        . admin_ui_icon('kebab', 15) . '</button><div class="act__pop" hidden>';
    foreach ($items as $it) {
        if (!empty($it['sep'])) {
            $out .= '<div class="act__sep"></div>';
            continue;
        }
        $icon = isset($it['icon']) ? admin_ui_icon($it['icon'], 15) : '';
        $cls  = !empty($it['danger']) ? ' is-danger' : '';
        if (isset($it['href'])) {
            $out .= '<a class="act__item' . $cls . '" href="' . e($it['href']) . '"'
                . (!empty($it['blank']) ? ' target="_blank" rel="noopener"' : '') . '>'
                . $icon . '<span>' . e($it['label']) . '</span></a>';
        } else {
            $out .= '<form method="post"' . (!empty($it['confirm']) ? ' onsubmit="return confirm(' . e(json_encode($it['confirm'])) . ')"' : '') . '>'
                . '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'
                . '<input type="hidden" name="do" value="' . e($it['do']) . '">'
                . '<input type="hidden" name="id" value="' . (int) ($it['id'] ?? 0) . '">';
            foreach (($it['fields'] ?? []) as $k => $v) {
                $out .= '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">';
            }
            $out .= '<button class="act__item' . $cls . '" type="submit">' . $icon . '<span>' . e($it['label']) . '</span></button></form>';
        }
    }
    return $out . '</div></div>';
}
