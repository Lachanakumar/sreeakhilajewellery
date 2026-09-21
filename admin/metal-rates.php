<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/engagement.php';
requireAdmin();

$metals = [
    'gold_24k' => '24K Gold',
    'gold_22k' => '22K Gold',
    'gold_18k' => '18K Gold',
    'silver'   => 'Silver',
];

if (admin_post_ok('metal-rates.php')) {
    $date = $_POST['effective_date'] ?: date('Y-m-d');
    foreach ($metals as $key => $label) {
        if (isset($_POST['rate'][$key]) && $_POST['rate'][$key] !== '') {
            saveRate($key, $label, (float) $_POST['rate'][$key], $date, $_POST['unit'][$key] ?? 'gram');
        }
    }
    flash_set('admin_ok', 'Rates saved for ' . $date . '.');
    redirect('metal-rates.php');
}

$latest = [];
foreach (getLatestRates() as $r) {
    $latest[$r['metal']] = $r;
}
$history = admin_list(getRateHistory(500), [
    'date'  => 'effective_date',
    'metal' => 'label',
    'rate'  => fn($h) => (float) $h['rate_per_gram'],
    'unit'  => 'unit',
], 'date:desc', 20, 'h');

$adminPageTitle = 'Gold / Silver Rates';
$adminNav = 'metal_rates';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Gold / Silver Rates', 'Drives the header ticker and Digi Gold page');
?>
<div class="admin__card fsec">
    <div class="fsec__head">
        <span class="fsec__num">1</span>
        <div><h3>Update today's rates</h3>
            <p>Shown in the header ticker and on the Digi Gold page. Set the effective date to backdate or schedule.</p></div>
    </div>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
        <div class="field" style="max-width:240px"><label>Effective date</label><input type="text" data-datepicker name="effective_date" value="<?php echo date('Y-m-d'); ?>"></div>
        <table class="admin__table" style="max-width:560px">
            <thead><tr><th>Metal</th><th>Rate</th><th>Per</th><th>Current</th></tr></thead>
            <tbody>
            <?php foreach ($metals as $key => $label): ?>
                <tr>
                    <td><?php echo e($label); ?></td>
                    <td><input type="number" step="0.01" name="rate[<?php echo $key; ?>]" value="<?php echo e($latest[$key]['rate_per_gram'] ?? ''); ?>" style="width:130px"></td>
                    <td>
                        <select name="unit[<?php echo $key; ?>]">
                            <option value="gram" <?php echo (($latest[$key]['unit'] ?? 'gram') === 'gram') ? 'selected' : ''; ?>>gram</option>
                            <option value="8 gram" <?php echo (($latest[$key]['unit'] ?? '') === '8 gram') ? 'selected' : ''; ?>>8 gram</option>
                            <option value="10 gram" <?php echo (($latest[$key]['unit'] ?? '') === '10 gram') ? 'selected' : ''; ?>>10 gram</option>
                        </select>
                    </td>
                    <td><?php echo isset($latest[$key]) ? formatPrice($latest[$key]['rate_per_gram'], 2) . ' (' . date('d M', strtotime($latest[$key]['effective_date'])) . ')' : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button class="btn" type="submit">Save Rates</button>
    </form>
</div>
<div class="admin__card">
    <?php echo admin_list_head('Rate history', admin_list_total('h')); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_th('date', 'Date', 'h'); ?>
            <?php echo admin_th('metal', 'Metal', 'h'); ?>
            <?php echo admin_th('rate', 'Rate', 'h'); ?>
            <?php echo admin_th('unit', 'Per', 'h'); ?>
        </tr></thead>
        <tbody>
        <?php foreach ($history as $h): ?>
            <tr><td><?php echo e($h['effective_date']); ?></td><td><?php echo e($h['label']); ?></td><td><?php echo formatPrice($h['rate_per_gram'], 2); ?></td><td><?php echo e($h['unit']); ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$history): ?><tr><td colspan="4">No rates recorded yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager('h'); ?>
</div>
<?php admin_layout_end();
