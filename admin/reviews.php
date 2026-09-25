<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/charts.php';
requireAdmin();

$db = getDB();

/** Longest reason the rejection_reason column can hold. */
const REVIEW_REASON_MAX = 255;

/**
 * Moderate one review.
 *
 * "WHERE status = 'pending'" is the whole point: approve and reject used to be
 * unconditional UPDATEs, so a second click — a double submit, the back button,
 * a hand-made POST — silently flipped an already-decided review. The UI now
 * hides the buttons once a decision is made, but the guard lives here because
 * hiding a button is not a rule.
 *
 * @return bool true when this call is the one that decided it
 */
function review_moderate(PDO $db, int $id, string $status, ?string $reason = null): bool {
    /* rejection_reason is VARCHAR(255) and the reason arrives as free text, so
       a moderator writing two sentences overruns it. On a server with MySQL's
       default strict sql_mode that is not a truncation but an error — 1406
       "Data too long" — which surfaced as a crash page after the reason had
       been typed. The box now caps the input, and this is the backstop for a
       post that did not come from it. */
    if ($reason !== null) {
        $reason = mb_substr($reason, 0, REVIEW_REASON_MAX);
    }
    $stmt = $db->prepare("UPDATE reviews
                             SET status = ?, rejection_reason = ?, moderated_at = NOW()
                           WHERE id = ? AND status = 'pending'");
    $stmt->execute([$status, $status === 'rejected' ? $reason : null, $id]);
    return $stmt->rowCount() > 0;
}

if (admin_post_ok('reviews.php')) {
    $id = (int) ($_POST['id'] ?? 0);
    $do = $_POST['do'] ?? '';
    $reason = trim((string) ($_POST['reason'] ?? ''));

    /* Every path below writes rejection_reason and moderated_at. A database that
       predates those columns — one set up before review-moderation.sql existed
       and never migrated — throws on the first write, and an uncaught PDO error
       replaced the whole page with a PHP stack trace for both Approve and
       Reject, single and bulk alike. A moderator cannot act on that; naming the
       missing migration is something they can hand to whoever deploys. */
    try {
    if ($do === 'bulk') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));
        $act = $_POST['bulk_action'] ?? '';

        if (!$ids) {
            admin_err('No reviews were selected — tick the rows you want to act on.');
        } elseif (!in_array($act, ['approved', 'rejected', 'delete'], true)) {
            admin_err('Choose an action to apply.');
        } elseif ($act === 'rejected' && $reason === '') {
            // same rule as a single reject: a rejection always carries a reason
            admin_err('Enter a reason before rejecting — it is stored with the review.');
        } elseif ($act === 'delete') {
            $in   = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("DELETE FROM reviews WHERE id IN ($in)");
            $stmt->execute($ids);
            $n = $stmt->rowCount();
            $n > 0 ? admin_ok($n . ' review(s) deleted.')
                   : admin_warn('Those reviews no longer exist — nothing was deleted.');
        } else {
            // one at a time, so each goes through the same pending-only guard
            $done = 0;
            foreach ($ids as $rid) {
                if (review_moderate($db, $rid, $act, $reason)) { $done++; }
            }
            $verb = $act === 'approved' ? 'approved' : 'rejected';
            if ($done > 0) {
                admin_ok($done . ' review(s) ' . $verb . '.');
            }
            if ($done < count($ids)) {
                admin_warn((count($ids) - $done) . ' of the selected review(s) had already been '
                    . 'moderated, so their status was left as it is.');
            }
        }
    } elseif ($do === 'approve' || $do === 'reject') {
        $status = $do === 'approve' ? 'approved' : 'rejected';
        if ($status === 'rejected' && $reason === '') {
            admin_err('Enter a reason before rejecting — it is stored with the review.');
        } elseif (review_moderate($db, $id, $status, $reason)) {
            admin_ok($status === 'approved'
                ? 'Review approved and published.'
                : 'Review rejected — the reason has been saved.');
        } else {
            admin_warn('That review has already been moderated, so its status was left as it is.');
        }
    } elseif ($do === 'delete') {
        $stmt = $db->prepare('DELETE FROM reviews WHERE id = ?');
        $stmt->execute([$id]);
        $stmt->rowCount() > 0
            ? admin_ok('Review deleted.')
            : admin_warn('That review no longer exists — nothing was deleted.');
    }
    } catch (Throwable $e) {
        $missingColumn = $e instanceof PDOException && ($e->getCode() === '42S22');
        admin_err($missingColumn
            ? 'This database is missing the review moderation columns, so nothing was changed. '
            . 'Run database/review-moderation.sql against it (it is also part of database/deploy.sql).'
            : 'That could not be saved — the database refused the change, so nothing was moderated.');
        error_log('reviews.php moderation failed: ' . $e->getMessage());
    }
    redirect('reviews.php' . admin_qs());
}

$filter = $_GET['status'] ?? 'pending';
$where = in_array($filter, ['pending', 'approved', 'rejected'], true) ? 'WHERE r.status = ' . $db->quote($filter) : '';
$reviews = $db->query("SELECT r.*, u.name AS user_name, p.name AS product_name
    FROM reviews r JOIN users u ON u.id = r.user_id JOIN products p ON p.id = r.product_id
    $where ORDER BY r.created_at DESC")->fetchAll();

$reviews = admin_list($reviews, [
    'product'  => 'product_name',
    'customer' => 'user_name',
    'rating'   => fn($r) => (int) $r['rating'],
    'date'     => 'created_at',
    'status'   => 'status',
], 'date:desc', 20);

/* Approve and Reject are offered only for reviews still awaiting a decision,
   so a view holding none of those gets a Delete-only menu rather than two
   actions that would be refused. */
$anyPending = false;
foreach ($reviews as $r) {
    if ($r['status'] === 'pending') { $anyPending = true; break; }
}
$bulkActions = $anyPending
    ? ['approved' => 'Approve', 'rejected' => 'Reject', 'delete' => 'Delete']
    : ['delete' => 'Delete'];

$adminPageTitle = 'Reviews';
$adminNav = 'reviews';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Reviews', 'Moderate what customers say about your pieces.');

$rc = function ($sql) use ($db) {
    try { return (int) $db->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; }
};
echo admin_stat_cards([
    ['label' => 'All Reviews', 'value' => number_format($rc('SELECT COUNT(*) FROM reviews')), 'icon' => 'star', 'tone' => 'brand'],
    ['label' => 'Approved', 'value' => number_format($rc("SELECT COUNT(*) FROM reviews WHERE status='approved'")), 'icon' => 'check', 'tone' => 'green'],
    ['label' => 'Awaiting Moderation', 'value' => number_format($rc("SELECT COUNT(*) FROM reviews WHERE status='pending'")), 'icon' => 'clock', 'tone' => 'amber'],
    ['label' => 'Rejected', 'value' => number_format($rc("SELECT COUNT(*) FROM reviews WHERE status='rejected'")), 'icon' => 'draft', 'tone' => 'red'],
]);

echo admin_chip_filter('reviews.php', [
    'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All',
], in_array($filter, ['pending', 'approved', 'rejected'], true) ? $filter : 'all');
?>
<div class="admin__card">
    <?php /* data-reason-when limits the reason prompt to the Reject action, so
             choosing Approve or Delete from the same menu is not interrogated. */ ?>
    <?php echo admin_list_head('Reviews', admin_list_total(), admin_bulk_bar(
        $bulkActions,
        'bulkForm',
        'data-reason-prompt="Why are these reviews being rejected?" data-reason-when="rejected"',
        '<input type="hidden" name="reason" value="">'
    )); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_check_all(); ?>
            <?php echo admin_th('product', 'Product'); ?>
            <?php echo admin_th('customer', 'Customer'); ?>
            <?php echo admin_th('rating', 'Rating'); ?>
            <th>Review</th>
            <?php echo admin_th('date', 'Date'); ?>
            <?php echo admin_th('status', 'Status'); ?>
            <th class="col-act">Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($reviews as $r): $rid = (int) $r['id']; ?>
            <tr>
                <?php echo admin_check_row($rid); ?>
                <td><?php echo e($r['product_name']); ?></td>
                <td><?php echo e($r['user_name']); ?></td>
                <td><span class="stars"><?php echo str_repeat('&#9733;', (int) $r['rating']) . str_repeat('&#9734;', 5 - (int) $r['rating']); ?></span></td>
                <td class="cell__long"><?php if ($r['title']): ?><strong><?php echo e($r['title']); ?></strong><br><?php endif; ?><?php echo nl2br(e($r['comment'])); ?></td>
                <td><?php echo date('d M Y', strtotime($r['created_at'])); ?></td>
                <td><?php echo status_pill($r['status']); ?>
                    <?php if ($r['status'] !== 'pending' && !empty($r['moderated_at'])): ?>
                        <br><span class="cell__sub"><?php echo date('d M Y', strtotime($r['moderated_at'])); ?></span>
                    <?php endif; ?>
                    <?php if ($r['status'] === 'rejected' && !empty($r['rejection_reason'])): ?>
                        <br><span class="cell__sub" title="<?php echo e($r['rejection_reason']); ?>"><?php echo e(mb_strimwidth($r['rejection_reason'], 0, 40, '…')); ?></span>
                    <?php endif; ?>
                </td>
                <?php echo admin_acts_open(); ?>
                    <?php /* Approve and Reject only while the review is still pending —
                             once it is decided the buttons would only re-post a status it
                             already has. review_moderate() enforces the same rule. */ ?>
                    <?php if ($r['status'] === 'pending'): ?>
                        <?php echo admin_act_post('approve', $rid, 'check', 'Approve'); ?>
                        <?php echo admin_act_reason('reject', $rid, 'draft', 'Reject', 'Why is this review being rejected?'); ?>
                    <?php endif; ?>
                    <?php echo admin_act_post('delete', $rid, 'trash', 'Delete', 'act--danger', 'Delete this review?'); ?>
                    <?php echo admin_act_menu([
                        ['label' => 'View product', 'href' => '../product-details.php?id=' . (int) $r['product_id'], 'icon' => 'external', 'blank' => true],
                    ]); ?>
                <?php echo admin_acts_close(); ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$reviews): ?><tr><td colspan="8">No reviews in this view.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<?php admin_layout_end();
