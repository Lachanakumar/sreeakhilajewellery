<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/charts.php';
requireAdmin();

$db = getDB();

if (admin_post_ok('customers.php')) {
    $do = $_POST['do'] ?? '';
    if ($do === 'toggle') {
        $cid  = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('UPDATE users SET status = IF(status = "active", "blocked", "active") WHERE id = ?');
        $stmt->execute([$cid]);
        if ($stmt->rowCount() === 0) {
            admin_warn('That customer no longer exists — nothing was changed.');
        } else {
            $now = (string) $db->query('SELECT status FROM users WHERE id = ' . $cid)->fetchColumn();
            admin_ok($now === 'blocked'
                ? 'Customer blocked — they can no longer sign in.'
                : 'Customer unblocked — they can sign in again.');
        }
    }
    if ($do === 'bulk') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));
        $act = $_POST['bulk_action'] ?? '';

        /* The two failures were reported as one ("select a customer AND an
           action"), so whichever you had actually forgotten, the message named
           both. They are separate checks now. */
        if (!$ids) {
            admin_err('No customers were selected — tick the rows you want to act on.');
        } elseif (!in_array($act, ['active', 'blocked'], true)) {
            admin_err('Choose an action to apply.');
        } else {
            $in   = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("UPDATE users SET status = ? WHERE id IN ($in)");
            $stmt->execute(array_merge([$act], $ids));

            /* rowCount(), not count($ids): MySQL does not count a row whose
               status already matched, so re-blocking someone who was already
               blocked no longer reports itself as a change that happened. */
            $changed = $stmt->rowCount();
            $verb    = $act === 'active' ? 'unblocked' : 'blocked';

            if ($changed > 0) {
                admin_ok($changed . ' customer(s) ' . $verb . '.');
            }
            if ($changed < count($ids)) {
                admin_warn((count($ids) - $changed) . ' of the selected customer(s) were already '
                    . $verb . ', so nothing changed for them.');
            }
        }
    }
    redirect('customers.php' . admin_qs());
}

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT u.*,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count,
        (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.user_id = u.id AND o.order_status NOT IN ("cancelled","refunded")) AS total_spent
        FROM users u';
$params = [];
if ($q !== '') { $sql .= ' WHERE u.name LIKE ? OR u.email LIKE ?'; $params[] = "%$q%"; $params[] = "%$q%"; }
$sql .= ' ORDER BY u.created_at DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$customers = admin_list($customers, [
    'name'       => 'name',
    'membership' => 'membership_no',
    'email'      => 'email',
    'phone'      => 'phone',
    'orders'     => fn($c) => (int) $c['order_count'],
    'spent'      => fn($c) => (float) $c['total_spent'],
    'status'     => 'status',
    'joined'     => 'created_at',
], 'joined:desc', 20);
$total = admin_list_total();

$cc = function ($sql) use ($db) {
    try { return $db->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; }
};
$memAll     = (int) $cc('SELECT COUNT(*) FROM users');
$memActive  = (int) $cc("SELECT COUNT(*) FROM users WHERE status = 'active'");
$memBlocked = (int) $cc("SELECT COUNT(*) FROM users WHERE status <> 'active'");
$memNew30   = (int) $cc('SELECT COUNT(*) FROM users WHERE created_at >= (NOW() - INTERVAL 30 DAY)');

$adminPageTitle = 'Customers';
$adminNav = 'customers';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Customers', 'Members, their orders and lifetime spend.');

echo admin_stat_cards([
    ['label' => 'Total Members', 'value' => number_format($memAll), 'icon' => 'users', 'tone' => 'brand'],
    ['label' => 'Active', 'value' => number_format($memActive), 'icon' => 'check', 'tone' => 'green'],
    ['label' => 'New This Month', 'value' => number_format($memNew30), 'icon' => 'star', 'tone' => 'blue', 'hint' => 'Joined in the last 30 days'],
    ['label' => 'Blocked', 'value' => number_format($memBlocked), 'icon' => 'draft', 'tone' => 'red'],
]);

echo admin_filter_open();
echo admin_filter_search('q', $q, 'Name or email…');
echo admin_filter_close('customers.php');
?>
<div class="admin__card">
    <?php echo admin_list_head('Customers', $total, admin_bulk_bar(['active' => 'Unblock', 'blocked' => 'Block'])); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_check_all(); ?>
            <?php echo admin_th('name', 'Customer'); ?>
            <?php echo admin_th('membership', 'Membership'); ?>
            <?php echo admin_th('phone', 'Phone'); ?>
            <?php echo admin_th('orders', 'Orders'); ?>
            <?php echo admin_th('spent', 'Spent'); ?>
            <?php echo admin_th('status', 'Status'); ?>
            <th class="col-act">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($customers as $c): $cid = (int) $c['id']; $blocked = $c['status'] !== 'active'; ?>
            <tr>
                <?php echo admin_check_row($cid); ?>
                <td>
                    <div class="cell__title"><strong><?php echo e($c['name']); ?></strong>
                        <span class="cell__sub"><?php echo e($c['email'] ?: '—'); ?></span></div>
                </td>
                <td>
                    <div class="cell__title"><strong><?php echo e($c['membership_no'] ?? '—'); ?></strong>
                        <span class="cell__sub"><?php echo e(ucfirst($c['membership_tier'] ?? 'classic')); ?></span></div>
                </td>
                <td><?php echo e($c['phone']); ?></td>
                <td><?php echo (int) $c['order_count']; ?></td>
                <td><span class="price__now"><?php echo formatPrice($c['total_spent'], 0); ?></span></td>
                <td><?php echo status_pill($c['status']); ?></td>
                <?php echo admin_acts_open(); ?>
                    <?php echo admin_act_link('customer-view.php?id=' . $cid, 'edit', 'View customer'); ?>
                    <?php echo admin_act_menu([
                        ['label' => 'Open profile', 'href' => 'customer-view.php?id=' . $cid, 'icon' => 'users'],
                        ['label' => 'Their orders', 'href' => 'orders.php?q=' . urlencode($c['email'] ?: $c['name']), 'icon' => 'cart'],
                        ['sep' => true],
                        ['label' => $blocked ? 'Unblock customer' : 'Block customer', 'do' => 'toggle', 'id' => $cid,
                         'icon' => $blocked ? 'check' : 'draft', 'danger' => !$blocked],
                    ]); ?>
                <?php echo admin_acts_close(); ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$customers): ?><tr><td colspan="8">No customers found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<?php admin_layout_end();
