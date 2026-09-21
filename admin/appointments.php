<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/engagement.php';
require_once __DIR__ . '/includes/charts.php';
requireAdmin();

if (admin_post_ok('appointments.php')) {
    $id = (int) ($_POST['id'] ?? 0);
    // '' is the dropdown's "keep as is" option; updateAppointment() checks the rest
    $status = (string) ($_POST['status'] ?? '');
    $note = trim($_POST['admin_note'] ?? '');

    // The note is shown to the customer under My Appointments, so a
    // cancellation without one leaves them with no explanation.
    if ($status === 'cancelled' && $note === '') {
        flash_set('admin_err', 'Please add a short note explaining the cancellation — the customer sees it on their account.');
        redirect('appointments.php' . admin_qs());
    }

    if (updateAppointment($id, $status, $note ?: null)) {
        admin_ok($status === 'cancelled'
            ? 'Appointment cancelled — your note is now visible to the customer.'
            : ($status === '' ? 'Note saved.' : 'Appointment moved to ' . workflow_label($status) . '.'));
    } else {
        admin_err('That is not a valid next step for this appointment — nothing was changed.');
    }
    redirect('appointments.php' . admin_qs());
}

$statusTabs = ['all' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
$filter = isset($_GET['status'], $statusTabs[$_GET['status']]) ? $_GET['status'] : 'all';
$rows = admin_list(getAppointments($filter), [
    'when'     => fn($r) => $r['appointment_date'] . ' ' . $r['appointment_time'],
    'customer' => 'name',
    'purpose'  => 'purpose',
    'items'    => fn($r) => count($r['items_json'] ? (json_decode($r['items_json'], true) ?: []) : []),
    'status'   => 'status',
], 'when:desc', 20);
$view = !empty($_GET['view']) ? getAppointment((int) $_GET['view']) : null;

$adminPageTitle = 'Appointments';
$adminNav = 'appointments';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Appointments', 'Store visit requests from customers.');

$ac = function ($sql) {
    try { return (int) getDB()->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; }
};
echo admin_stat_cards([
    ['label' => 'All Appointments', 'value' => number_format($ac('SELECT COUNT(*) FROM appointments')), 'icon' => 'calendar', 'tone' => 'brand'],
    ['label' => 'Confirmed', 'value' => number_format($ac("SELECT COUNT(*) FROM appointments WHERE status='confirmed'")), 'icon' => 'check', 'tone' => 'green'],
    ['label' => 'Pending', 'value' => number_format($ac("SELECT COUNT(*) FROM appointments WHERE status='pending'")), 'icon' => 'clock', 'tone' => 'amber', 'hint' => 'Awaiting your confirmation'],
    ['label' => 'Upcoming', 'value' => number_format($ac("SELECT COUNT(*) FROM appointments WHERE appointment_date >= CURDATE() AND status IN ('pending','confirmed')")), 'icon' => 'star', 'tone' => 'blue', 'hint' => 'Today onwards'],
]);
?>
<?php echo admin_chip_filter('appointments.php', $statusTabs, $filter, 'status', ['view']); ?>
<?php if ($view): ?>
    <div class="admin__card">
        <h3>Appointment #<?php echo (int) $view['id']; ?> <?php echo status_pill($view['status']); ?></h3>
        <div class="row2">
            <div>
                <p><strong><?php echo e($view['name']); ?></strong> &middot; <?php echo e($view['phone']); ?><?php echo $view['email'] ? ' &middot; ' . e($view['email']) : ''; ?>
                    <?php if ($view['membership_no']): ?><br>Member <?php echo e($view['membership_no']); ?><?php endif; ?></p>
                <p><strong>When:</strong> <?php echo date('l, d M Y', strtotime($view['appointment_date'])); ?> at <?php echo e($view['appointment_time']); ?></p>
                <p><strong>Purpose:</strong> <?php echo e($view['purpose'] ?: 'General visit'); ?></p>
                <?php if ($view['message']): ?><p><strong>Message:</strong> <?php echo nl2br(e($view['message'])); ?></p><?php endif; ?>
            </div>
            <div>
                <h4>Wishlist / pieces to keep ready</h4>
                <?php if (!empty($view['items'])): ?>
                    <ul><?php foreach ($view['items'] as $it): ?><li><?php echo e($it['name']); ?> <small>(<?php echo e($it['sku'] ?? ''); ?>)</small></li><?php endforeach; ?></ul>
                <?php else: ?><p style="color:var(--a-text-soft)">None attached.</p><?php endif; ?>
            </div>
        </div>
        <a class="btn btn--ghost" href="appointments.php<?php echo e(admin_qs(['view' => null])); ?>">Back to <?php echo e($statusTabs[$filter]); ?> list</a>
    </div>
<?php endif; ?>
<div class="admin__card">
    <?php echo admin_list_head('Appointments', admin_list_total()); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_th('when', 'Date / Time'); ?>
            <?php echo admin_th('customer', 'Customer'); ?>
            <?php echo admin_th('purpose', 'Purpose'); ?>
            <?php echo admin_th('items', 'Items'); ?>
            <?php echo admin_th('status', 'Status'); ?>
            <th>Update</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $items = $r['items_json'] ? (json_decode($r['items_json'], true) ?: []) : []; ?>
            <tr>
                <td><strong><?php echo date('d M Y', strtotime($r['appointment_date'])); ?></strong><br><span class="cell__sub"><?php echo e($r['appointment_time']); ?></span></td>
                <td>
                    <div class="cell__title"><strong><?php echo e($r['name']); ?></strong>
                        <span class="cell__sub"><?php echo e($r['phone']); ?><?php echo $r['membership_no'] ? ' · ' . e($r['membership_no']) : ''; ?></span></div>
                </td>
                <td><?php echo e($r['purpose'] ?: 'General visit'); ?></td>
                <td><?php echo $items ? '<a href="appointments.php' . e(admin_qs(['view' => (int) $r['id']])) . '">' . count($items) . ' item(s)</a>' : '—'; ?></td>
                <td><?php echo status_pill($r['status']); ?></td>
                <td>
                    <form method="post" class="rowform">
                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                        <?php /* only the steps that may follow this one — see includes/workflow.php */ ?>
                        <?php echo workflow_select('appointment', $r['status']); ?>
                        <input type="text" name="admin_note" class="js-appt-note" placeholder="Note to the customer" title="Shown to the customer under My Appointments" value="<?php echo e($r['admin_note']); ?>">
                        <button class="btn btn--sm">Save</button>
                        <a class="act" href="appointments.php<?php echo e(admin_qs(['view' => (int) $r['id']])); ?>" title="View details" aria-label="View details"><?php echo admin_ui_icon('external', 15); ?></a>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6">No appointments in this view.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<?php admin_layout_end();
