<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/engagement.php';
require_once __DIR__ . '/includes/charts.php';
requireAdmin();

if (admin_post_ok('feedback.php')) {
    $id = (int) ($_POST['id'] ?? 0);
    // '' is the dropdown's "keep as is" option; updateFeedbackStatus() checks the rest
    $status = (string) ($_POST['status'] ?? '');
    if (!updateFeedbackStatus($id, $status)) {
        admin_err('That is not a valid next step for this feedback — nothing was changed.');
    } elseif ($status !== '') {
        admin_ok('Feedback moved to ' . workflow_label($status) . '.');
    }
    redirect('feedback.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
}

$filter = $_GET['status'] ?? 'all';
$rows = admin_list(getFeedback($filter), [
    'when'     => 'created_at',
    'customer' => 'name',
    'topic'    => 'category',
    'rating'   => fn($r) => (int) $r['rating'],
    'status'   => 'status',
], 'when:desc', 20);

$adminPageTitle = 'Customer Feedback';
$adminNav = 'feedback';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Customer Feedback', 'What customers are telling you about the store.');

$fc = function ($sql) {
    try { return getDB()->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; }
};
$avgRating = (float) $fc('SELECT COALESCE(AVG(rating),0) FROM feedback WHERE rating > 0');
echo admin_stat_cards([
    ['label' => 'All Feedback', 'value' => number_format((int) $fc('SELECT COUNT(*) FROM feedback')), 'icon' => 'chat', 'tone' => 'brand'],
    ['label' => 'Average Rating', 'value' => $avgRating ? number_format($avgRating, 1) . ' / 5' : '—', 'icon' => 'star', 'tone' => 'green'],
    ['label' => 'New', 'value' => number_format((int) $fc("SELECT COUNT(*) FROM feedback WHERE status='new'")), 'icon' => 'alert', 'tone' => 'amber', 'hint' => 'Not yet reviewed'],
    ['label' => 'Archived', 'value' => number_format((int) $fc("SELECT COUNT(*) FROM feedback WHERE status='archived'")), 'icon' => 'draft', 'tone' => 'red'],
]);

echo admin_chip_filter('feedback.php', [
    'all' => 'All', 'new' => 'New', 'reviewed' => 'Reviewed', 'archived' => 'Archived',
], $filter);
?>
<div class="admin__card">
    <?php echo admin_list_head('Feedback', admin_list_total()); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_th('when', 'When'); ?>
            <?php echo admin_th('customer', 'Customer'); ?>
            <?php echo admin_th('topic', 'Topic'); ?>
            <?php echo admin_th('rating', 'Rating'); ?>
            <th>Feedback</th>
            <?php echo admin_th('status', 'Status'); ?>
            <th>Update</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?php echo date('d M Y', strtotime($r['created_at'])); ?></td>
                <td>
                    <div class="cell__title"><strong><?php echo e($r['name']); ?></strong>
                        <span class="cell__sub"><?php echo e($r['phone'] ?: $r['email']); ?></span></div>
                </td>
                <td><?php echo e(FEEDBACK_CATEGORIES[$r['category']] ?? $r['category']); ?></td>
                <td><?php echo $r['rating'] ? '<span class="stars">' . str_repeat('&#9733;', (int) $r['rating']) . str_repeat('&#9734;', 5 - (int) $r['rating']) . '</span>' : '—'; ?></td>
                <td class="cell__long"><?php echo nl2br(e($r['message'])); ?></td>
                <td><?php echo status_pill($r['status']); ?></td>
                <td>
                    <form method="post" class="rowform">
                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                        <?php /* only the steps that may follow this one — see includes/workflow.php */ ?>
                        <?php echo workflow_select('feedback', $r['status'], 'status', 'onchange="if(this.value)this.form.submit()"'); ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7">No feedback in this view.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<?php admin_layout_end();
