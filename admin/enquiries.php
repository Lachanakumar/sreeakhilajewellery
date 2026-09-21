<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/engagement.php';
require_once __DIR__ . '/includes/charts.php';
requireAdmin();

if (admin_post_ok('enquiries.php')) {
    $id = (int) ($_POST['id'] ?? 0);
    /* '' is the dropdown's "keep as is" option. Anything else must be a legal
       next step, which updateEnquiry() checks. The old line coerced anything
       unrecognised to 'new', which would now walk a closed enquiry backwards. */
    $status = (string) ($_POST['status'] ?? '');
    if (updateEnquiry($id, $status, trim($_POST['admin_note'] ?? '') ?: null)) {
        admin_ok($status === '' ? 'Note saved.' : 'Enquiry moved to ' . workflow_label($status) . '.');
    } else {
        admin_err('That is not a valid next step for this enquiry — nothing was changed.');
    }
    redirect('enquiries.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}

$filter = $_GET['status'] ?? 'all';
$rows = admin_list(getEnquiries($filter), [
    'when'     => 'created_at',
    'customer' => 'name',
    'about'    => fn($r) => $r['product_name'] ?: ($r['scheme_name'] ?: $r['subject']),
    'status'   => 'status',
], 'when:desc', 20);

$adminPageTitle = 'Enquiries';
$adminNav = 'enquiries';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Enquiries', 'Product and scheme questions from customers.');

$ec = function ($sql) {
    try { return (int) getDB()->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; }
};
echo admin_stat_cards([
    ['label' => 'All Enquiries', 'value' => number_format($ec('SELECT COUNT(*) FROM enquiries')), 'icon' => 'chat', 'tone' => 'brand'],
    ['label' => 'Closed', 'value' => number_format($ec("SELECT COUNT(*) FROM enquiries WHERE status='closed'")), 'icon' => 'check', 'tone' => 'green'],
    ['label' => 'New', 'value' => number_format($ec("SELECT COUNT(*) FROM enquiries WHERE status='new'")), 'icon' => 'alert', 'tone' => 'amber', 'hint' => 'Not yet picked up'],
    ['label' => 'In Progress', 'value' => number_format($ec("SELECT COUNT(*) FROM enquiries WHERE status='in_progress'")), 'icon' => 'clock', 'tone' => 'blue'],
]);

echo admin_chip_filter('enquiries.php', [
    'all' => 'All', 'new' => 'New', 'in_progress' => 'In progress', 'responded' => 'Responded', 'closed' => 'Closed',
], $filter);
?>
<div class="admin__card">
    <?php echo admin_list_head('Enquiries', admin_list_total()); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_th('when', 'When'); ?>
            <?php echo admin_th('customer', 'Customer'); ?>
            <?php echo admin_th('about', 'About'); ?>
            <th>Message</th>
            <?php echo admin_th('status', 'Status'); ?>
            <th>Update</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($r['created_at'])); ?><br><span class="cell__sub"><?php echo date('H:i', strtotime($r['created_at'])); ?></span></td>
                <td>
                    <div class="cell__title"><strong><?php echo e($r['name']); ?></strong>
                        <span class="cell__sub"><?php echo e($r['phone']); ?><?php echo $r['email'] ? ' · ' . e($r['email']) : ''; ?></span></div>
                </td>
                <td>
                    <?php if ($r['product_name']): ?><a href="../product-details.php?id=<?php echo (int) $r['product_id']; ?>" target="_blank" rel="noopener"><?php echo e($r['product_name']); ?></a>
                    <?php elseif ($r['scheme_name']): ?>Scheme: <?php echo e($r['scheme_name']); ?>
                    <?php else: ?><?php echo e($r['subject'] ?: 'General'); ?><?php endif; ?>
                </td>
                <td class="cell__long"><?php echo nl2br(e($r['message'])); ?><?php if ($r['admin_note']): ?><br><em class="cell__sub">Note: <?php echo e($r['admin_note']); ?></em><?php endif; ?></td>
                <td><?php echo status_pill($r['status']); ?></td>
                <td>
                    <form method="post" class="rowform">
                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                        <?php /* only the steps that may follow this one — see includes/workflow.php */ ?>
                        <?php echo workflow_select('enquiry', $r['status']); ?>
                        <input type="text" name="admin_note" placeholder="Note" value="<?php echo e($r['admin_note']); ?>">
                        <button class="btn btn--sm">Save</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6">No enquiries in this view.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<?php admin_layout_end();
