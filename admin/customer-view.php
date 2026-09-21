<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/orders.php';
requireAdmin();

$db = getDB();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$customer = $stmt->fetch();
if (!$customer) {
    admin_warn('That customer could not be found — the account may have been removed.');
    redirect('customers.php');
}
$orders = getUserOrders($id);
$addresses = getUserAddresses($id);

$adminPageTitle = $customer['name'];
$adminNav = 'customers';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head($customer['name'], 'Customer profile', '<a class="btn btn--ghost" href="customers.php">&#8592; All customers</a>');
?>
<div class="admin__card">
    <h3><?php echo e($customer['name']); ?> <span class="badge <?php echo $customer['status'] === 'active' ? 'badge--green' : 'badge--red'; ?>"><?php echo e($customer['status']); ?></span></h3>
    <?php
    /* Email is nullable — customers can register with a mobile number alone.
       The line used to glue the fields together with a literal "&middot;"
       between each, so a missing email left the row starting with a stray
       separator dot. Built from the parts that actually have a value instead. */
    $parts = array_filter([
        trim((string) $customer['email']),
        trim((string) $customer['phone']),
    ], fn($v) => $v !== '');
    $parts[] = 'joined ' . date('d M Y', strtotime($customer['created_at']));
    ?>
    <p><?php echo implode(' &middot; ', array_map('e', $parts)); ?></p>
    <table class="admin__table admin__table--kv">
        <tbody>
            <tr><td>Email</td><td><?php echo $customer['email'] !== null && trim((string) $customer['email']) !== ''
                ? e($customer['email']) : '<span class="muted">Not provided</span>'; ?></td></tr>
            <tr><td>Phone</td><td><?php echo trim((string) $customer['phone']) !== ''
                ? e($customer['phone']) : '<span class="muted">Not provided</span>'; ?></td></tr>
            <tr><td>Membership</td><td><?php echo trim((string) ($customer['membership_no'] ?? '')) !== ''
                ? e($customer['membership_no']) . ' <span class="muted">(' . e(ucfirst($customer['membership_tier'] ?? 'classic')) . ')</span>'
                : '<span class="muted">Not provided</span>'; ?></td></tr>
        </tbody>
    </table>
</div>
<div class="admin__card">
    <h3>Addresses</h3>
    <?php if (!$addresses): ?><p>None.</p><?php endif; ?>
    <?php foreach ($addresses as $a): ?>
        <p><strong><?php echo e($a['full_name']); ?></strong> (<?php echo e($a['phone']); ?>)<br><?php echo nl2br(e(formatAddressText($a))); ?></p>
    <?php endforeach; ?>
</div>
<div class="admin__card">
    <h3>Orders (<?php echo count($orders); ?>)</h3>
    <table class="admin__table">
        <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Total</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr><td><?php echo e($o['order_number']); ?></td><td><?php echo date('d M Y', strtotime($o['created_at'])); ?></td><td><?php echo ucfirst($o['order_status']); ?></td><td><?php echo formatPrice($o['total_amount']); ?></td><td><a class="btn btn--sm btn--ghost" href="order-view.php?id=<?php echo (int) $o['id']; ?>">View</a></td></tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="5">No orders.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<a class="btn btn--ghost" href="customers.php">Back</a>
<?php admin_layout_end();
