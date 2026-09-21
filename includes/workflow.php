<?php
/**
 * Status workflows for orders, enquiries, appointments and feedback.
 *
 * Every one of these used to render its full status list on every row, with
 * the current value merely pre-selected. That let an order go from Delivered
 * back to Pending, or an Appointment be "confirmed" after it was cancelled —
 * the dropdown offered it, so sooner or later someone picked it.
 *
 * The maps below are the single definition of what may follow what. A status
 * that is not listed as a successor cannot be reached, which covers going
 * backwards, skipping a step, and re-applying the status a record already has.
 * A status with an empty list is final.
 *
 * The admin pages read these to build the dropdown, and the update functions
 * read them again before writing, so a hand-made POST is refused the same way
 * a mis-click is.
 */

/** Forward-only transitions, keyed by module. */
function workflow_map($module) {
    $maps = [
        /* An order moves through fulfilment. Cancelling is allowed while there
           is still something to stop; once it has shipped, the way out is a
           refund rather than a cancellation.
         *
         * 'refunded' follows nearly every state, because money is captured the
         * moment the customer pays — long before the order ships. It used to
         * follow only 'delivered' and 'cancelled', so refunding a paid order
         * that was still being packed was silently refused: the gateway sent
         * the money back and pay_record_refund() marked the payment refunded,
         * but updateOrderStatus() rejected the transition, so the order stayed
         * 'Confirmed' and its stock was never returned. */
        'order' => [
            'pending'    => ['confirmed', 'cancelled', 'refunded'],
            'confirmed'  => ['processing', 'cancelled', 'refunded'],
            'processing' => ['shipped', 'cancelled', 'refunded'],
            'shipped'    => ['delivered', 'refunded'],
            'delivered'  => ['refunded'],
            'cancelled'  => ['refunded'],   // money already taken can still go back
            'refunded'   => [],
        ],
        'enquiry' => [
            'new'         => ['in_progress', 'closed'],
            'in_progress' => ['responded', 'closed'],
            'responded'   => ['closed'],
            'closed'      => [],
        ],
        'appointment' => [
            'pending'   => ['confirmed', 'cancelled'],
            'confirmed' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ],
        'feedback' => [
            'new'      => ['reviewed', 'archived'],
            'reviewed' => ['archived'],
            'archived' => [],
        ],
    ];
    return $maps[$module] ?? [];
}

/** Statuses that may follow $current. Empty means the record is finished. */
function workflow_next($module, $current) {
    $map = workflow_map($module);
    $current = (string) $current;
    // an unrecognised current value (legacy row, hand-edited data) would other-
    // wise strand the record with no way forward, so fall back to the entry point
    if (!array_key_exists($current, $map)) {
        $first = array_key_first($map);
        return $first === null ? [] : $map[$first];
    }
    return $map[$current];
}

/** May $current become $next? */
function workflow_allows($module, $current, $next) {
    return in_array((string) $next, workflow_next($module, $current), true);
}

/** True when nothing can follow — the row is in a final state. */
function workflow_is_final($module, $current) {
    return workflow_next($module, $current) === [];
}

/** "in_progress" -> "In Progress" */
function workflow_label($status) {
    return ucwords(str_replace('_', ' ', (string) $status));
}

/**
 * The <select> for a status form.
 *
 * The first option is an empty placeholder naming where the record is now, so
 * the box does not open already pointing at a change nobody asked for — with
 * only the successors listed, a stray click would otherwise submit one. The
 * update handlers treat an empty value as "leave it alone".
 *
 * When there is nowhere left to go the control is disabled and says so, rather
 * than presenting an empty menu.
 */
function workflow_select($module, $current, $name = 'status', $attrs = '') {
    $next = workflow_next($module, $current);
    $now  = workflow_label($current);

    if (!$next) {
        return '<select name="' . e($name) . '" disabled aria-label="Status"'
             . '><option>' . e($now) . ' — no further changes</option></select>';
    }

    $out = '<select name="' . e($name) . '" aria-label="Status" ' . $attrs . '>'
         . '<option value="">' . e($now) . ' — keep as is</option>';
    foreach ($next as $s) {
        $out .= '<option value="' . e($s) . '">Move to ' . e(workflow_label($s)) . '</option>';
    }
    return $out . '</select>';
}
