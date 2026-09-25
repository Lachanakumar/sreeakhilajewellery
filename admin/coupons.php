<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/functions.php';
// coupon_state() — the same validity test checkout applies
require_once __DIR__ . '/../includes/cart-functions.php';
require_once __DIR__ . '/includes/charts.php';
requireAdmin();

$db = getDB();

if (admin_post_ok('coupons.php')) {
    $do = $_POST['do'] ?? '';
    if ($do === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $type = ($_POST['type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
        $value = (float) ($_POST['value'] ?? 0);
        // blank means "no minimum", which is 0 — the field is no longer
        // pre-filled with a literal 0 for the admin to type in front of
        $min = trim((string) ($_POST['min_order_amount'] ?? '')) === ''
            ? 0.0 : max(0, (float) $_POST['min_order_amount']);
        $max = $_POST['max_discount'] !== '' ? (float) $_POST['max_discount'] : null;
        $limit = $_POST['usage_limit'] !== '' ? (int) $_POST['usage_limit'] : null;
        $starts = $_POST['starts_at'] ?: null;
        $expires = $_POST['expires_at'] ?: null;
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        /* Both are DATE columns, so a plain string compare is the right test —
           and it lets start and end be the same day, which means "valid for
           that one day" rather than an error. */
        $errors = [];
        if ($code === '' || $value <= 0) {
            $errors[] = 'A coupon code and a value greater than zero are required.';
        }
        foreach (['starts_at' => $starts, 'expires_at' => $expires] as $field => $val) {
            if ($val !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                $errors[] = 'The ' . ($field === 'starts_at' ? 'start' : 'expiry') . ' date is not a valid date.';
            }
        }
        if (!$errors && $starts && $expires && $starts > $expires) {
            $errors[] = 'The start date cannot be later than the expiry date.';
        }

        if ($errors) {
            admin_err(implode(' ', $errors) . ' Nothing was saved.');
        } else {
            try {
                if ($id) {
                    $db->prepare('UPDATE coupons SET code=?, type=?, value=?, min_order_amount=?, max_discount=?, usage_limit=?, starts_at=?, expires_at=?, status=? WHERE id=?')
                       ->execute([$code, $type, $value, $min, $max, $limit, $starts, $expires, $status, $id]);
                } else {
                    $db->prepare('INSERT INTO coupons (code, type, value, min_order_amount, max_discount, usage_limit, starts_at, expires_at, status) VALUES (?,?,?,?,?,?,?,?,?)')
                       ->execute([$code, $type, $value, $min, $max, $limit, $starts, $expires, $status]);
                }
                admin_ok($id ? 'Coupon updated.' : 'Coupon created.');
            } catch (Throwable $e) {
                admin_err('Could not save the coupon — that code is already in use.');
            }
        }
    }
    if ($do === 'delete') {
        $db->prepare('DELETE FROM coupons WHERE id = ?')->execute([(int) $_POST['id']]);
        admin_ok('Coupon deleted.');
    }
    redirect('coupons.php');
}

$coupons = admin_list(
    $db->query('SELECT * FROM coupons ORDER BY created_at DESC')->fetchAll(),
    [
        'code'    => 'code',
        'type'    => 'type',
        'value'   => fn($c) => (float) $c['value'],
        'min'     => fn($c) => (float) $c['min_order_amount'],
        'used'    => fn($c) => (int) $c['used_count'],
        'expires' => 'expires_at',
        'status'  => 'status',
        'created' => 'created_at',
    ],
    'created:desc', 20
);
$edit = null;
if (!empty($_GET['edit'])) {
    $s = $db->prepare('SELECT * FROM coupons WHERE id = ?');
    $s->execute([(int) $_GET['edit']]);
    $edit = $s->fetch();
}

$adminPageTitle = 'Coupons';
$adminNav = 'coupons';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Coupons', 'Discount codes customers can apply at checkout.');
?>
<form method="post" data-validate>
    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
    <input type="hidden" name="do" value="save">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo (int) $edit['id']; ?>"><?php endif; ?>
    <div class="pform">
        <div class="pform__main">
            <div class="admin__card fsec">
                <div class="fsec__head">
                    <span class="fsec__num">1</span>
                    <div><h3><?php echo $edit ? 'Edit Coupon' : 'Coupon Details'; ?></h3>
                        <p>The code customers type at checkout and what it takes off.</p></div>
                </div>
                <div class="row3">
                    <div class="field"><label>Code <b class="req">*</b></label>
                        <input type="text" name="code" value="<?php echo e($edit['code'] ?? ''); ?>" placeholder="e.g. WELCOME10"
                               pattern="[A-Za-z0-9_-]{3,40}" data-message="Use 3–40 letters, numbers, hyphen or underscore." required></div>
                    <div class="field"><label>Type</label>
                        <select name="type">
                            <option value="percent" <?php echo (($edit['type'] ?? '') === 'percent') ? 'selected' : ''; ?>>Percent %</option>
                            <option value="fixed" <?php echo (($edit['type'] ?? '') === 'fixed') ? 'selected' : ''; ?>>Fixed amount</option>
                        </select>
                    </div>
                    <div class="field"><label>Value <b class="req">*</b></label>
                        <input type="number" step="0.01" min="0.01" name="value" value="<?php echo e($edit['value'] ?? ''); ?>"
                               data-label="Value" data-min-message="Value must be greater than zero." placeholder="0.00" required></div>
                </div>
            </div>

            <div class="admin__card fsec">
                <div class="fsec__head">
                    <span class="fsec__num">2</span>
                    <div><h3>Limits <span class="fsec__opt">(Optional)</span></h3>
                        <p>Restrict when and how often the coupon can be used.</p></div>
                </div>
                <div class="row3">
                    <?php /* Left blank rather than pre-filled with 0: the old default put a
                             literal "0" in the box, so typing 1 after it produced "01".
                             Blank still saves as 0 — see the handler above. A stored value
                             loses its trailing ".00" so 500.00 edits as 500. */ ?>
                    <?php
                    $minVal = isset($edit['min_order_amount']) && (float) $edit['min_order_amount'] > 0
                        ? rtrim(rtrim(number_format((float) $edit['min_order_amount'], 2, '.', ''), '0'), '.')
                        : '';
                    ?>
                    <div class="field"><label>Min order amount</label>
                        <div class="money"><span><?php echo CURRENCY_SYMBOL; ?></span>
                            <input type="number" step="0.01" min="0" name="min_order_amount" value="<?php echo e($minVal); ?>" placeholder="0"></div>
                        <span class="hint">Leave blank for no minimum.</span></div>
                    <div class="field"><label>Max discount (percent type)</label>
                        <div class="money"><span><?php echo CURRENCY_SYMBOL; ?></span>
                            <input type="number" step="0.01" min="0" name="max_discount" value="<?php echo e($edit['max_discount'] ?? ''); ?>" placeholder="No cap"></div></div>
                    <div class="field"><label>Usage limit</label>
                        <input type="number" min="1" name="usage_limit" value="<?php echo e($edit['usage_limit'] ?? ''); ?>" placeholder="Unlimited"></div>
                </div>
                <div class="row2">
                    <?php /* Tied to each other so the expiry can never be set before the
                             start; same day is allowed and means "valid that day".
                             See assets/js/datepicker.js.

                             data-range-strict keeps both dates exactly as they were
                             entered. Without it, choosing a start later than an expiry
                             already in the box silently moved that expiry to match, so
                             the coupon was saved with an end date nobody chose and the
                             admin was never told the range was wrong. Now the pair
                             stands and data-date-not-after reports it on the field;
                             admin/coupons.php checks the same rule again on save. */ ?>
                    <div class="field"><label>Starts</label>
                        <input type="text" data-datepicker name="starts_at" data-max-input="expires_at"
                               data-range-strict data-date-not-after="[name=expires_at]"
                               data-date-message="The start date cannot be later than the expiry date."
                               data-label="Start date"
                               value="<?php echo e($edit['starts_at'] ?? ''); ?>" placeholder="Leave blank to start today">
                        <span class="hint">Valid from the start of this day.</span></div>
                    <?php /* The placeholder names what the box takes; what a blank box
                             means moves into the hint, where the rest of the rule for
                             this field already is. */ ?>
                    <div class="field"><label>Expires</label>
                        <input type="text" data-datepicker name="expires_at" data-min-input="starts_at"
                               data-range-strict data-label="Expiry date"
                               value="<?php echo e($edit['expires_at'] ?? ''); ?>" placeholder="Select expiry date">
                        <span class="hint">Valid until the end of this day. Leave blank for no expiry.</span></div>
                </div>
            </div>
        </div>
        <?php echo admin_form_side($edit ? 'Update Coupon' : 'Add Coupon', $edit['status'] ?? 'active', $edit ? 'coupons.php' : ''); ?>
    </div>
</form>
<div class="admin__card">
    <?php echo admin_list_head('All Coupons', admin_list_total()); ?>
    <table class="admin__table">
        <thead><tr>
            <?php echo admin_th('code', 'Code'); ?>
            <?php echo admin_th('type', 'Type'); ?>
            <?php echo admin_th('value', 'Value'); ?>
            <?php echo admin_th('min', 'Min'); ?>
            <?php echo admin_th('used', 'Used'); ?>
            <?php echo admin_th('expires', 'Expires'); ?>
            <?php echo admin_th('status', 'Status'); ?>
            <th class="col-act">Actions</th>
        </tr></thead>
        <tbody>
        <?php /* coupon_state() is the same test checkout applies, so the badge here
                 can no longer contradict whether the code actually works. */ ?>
        <?php foreach ($coupons as $c): $cid = (int) $c['id']; $state = coupon_state($c); ?>
            <tr>
                <td><strong><?php echo e($c['code']); ?></strong></td>
                <td><span class="badge"><?php echo e($c['type']); ?></span></td>
                <td><span class="price__now"><?php echo $c['type'] === 'percent' ? (float) $c['value'] . '%' : formatPrice($c['value'], 0); ?></span></td>
                <td><?php echo formatPrice($c['min_order_amount'], 0); ?></td>
                <td><?php echo (int) $c['used_count']; ?><?php echo $c['usage_limit'] !== null ? ' / ' . (int) $c['usage_limit'] : ''; ?></td>
                <td><?php echo $c['expires_at'] ? date('d M Y', strtotime($c['expires_at'])) : '&mdash;'; ?>
                    <?php if ($c['starts_at']): ?><br><span class="cell__sub">from <?php echo date('d M Y', strtotime($c['starts_at'])); ?></span><?php endif; ?></td>
                <td><?php echo status_pill($c['status']); ?>
                    <?php if ($state !== 'active' && $state !== 'inactive'): ?>
                        <br><span class="cell__sub"><?php echo e(coupon_state_label($state)); ?></span>
                    <?php endif; ?></td>
                <?php echo admin_acts_open(); ?>
                    <?php echo admin_act_link('coupons.php?edit=' . $cid, 'edit', 'Edit'); ?>
                    <?php echo admin_act_post('delete', $cid, 'trash', 'Delete', 'act--danger', 'Delete coupon ' . $c['code'] . '?'); ?>
                <?php echo admin_acts_close(); ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$coupons): ?><tr><td colspan="8">No coupons yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?php echo admin_pager(); ?>
</div>
<?php admin_layout_end();
