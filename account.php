<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';
require_once 'includes/orders.php';
require_once 'includes/engagement.php';

requireLogin();
$user = currentUser();
$db = getDB();
$tab = $_GET['tab'] ?? 'profile';
$notice = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $do = $_POST['do'] ?? '';

    if ($do === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        // Email is optional at sign-up, so this is often where a customer first
        // adds one — it has to be editable here and unique across accounts.
        $email = trim($_POST['email'] ?? '');

        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address (or leave it blank).';
        }
        if (!$errors && $email !== '') {
            $taken = $db->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $taken->execute([$email, $user['id']]);
            if ($taken->fetch()) {
                $errors[] = 'Another account is already using that email address.';
            }
        }

        if (!$errors) {
            $db->prepare('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?')
               ->execute([$name, $email ?: null, $phone ?: null, $user['id']]);
            $notice = 'Profile updated.';
            $user = currentUser(true);
        }
        $tab = 'profile';
    }

    if ($do === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['new_password_confirm'] ?? '';
        $row = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $row->execute([$user['id']]);
        $hash = $row->fetchColumn();
        $weak = password_problem($new);
        if (!password_verify($current, $hash)) {
            $errors[] = 'Current password is incorrect.';
        } elseif ($weak !== '') {
            // same wording the inline check shows, so the two never disagree
            $errors[] = $weak;
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $notice = 'Password changed.';
        }
        $tab = 'profile';
    }

    if ($do === 'address_save') {
        $req = ['full_name', 'phone', 'address_line1', 'city', 'state', 'postal_code'];
        $data = [];
        foreach ($req as $f) {
            $data[$f] = trim($_POST[$f] ?? '');
            if ($data[$f] === '') $errors[] = ucwords(str_replace('_', ' ', $f)) . ' is required.';
        }
        $data['address_line2'] = trim($_POST['address_line2'] ?? '');
        $data['country'] = trim($_POST['country'] ?? 'India');
        $data['is_default'] = !empty($_POST['is_default']);
        if (!$errors) {
            saveAddress($user['id'], $data, !empty($_POST['address_id']) ? (int) $_POST['address_id'] : null);
            $notice = 'Address saved.';
        }
        $tab = 'addresses';
    }

    if ($do === 'address_delete') {
        deleteAddress((int) ($_POST['address_id'] ?? 0), $user['id']);
        $notice = 'Address removed.';
        $tab = 'addresses';
    }
}

$addresses = getUserAddresses($user['id']);
$allOrders = getUserOrders($user['id']);

// ?show=unpaid opts back in to the never-completed checkouts
$showUnpaid = ($_GET['show'] ?? '') === 'unpaid';
$orders = [];
$unpaidAttempts = 0;
foreach ($allOrders as $o) {
    if (order_is_abandoned_payment($o)) {
        $unpaidAttempts++;
        if (!$showUnpaid) {
            continue;
        }
    }
    $orders[] = $o;
}
$editAddress = null;
if ($tab === 'addresses' && !empty($_GET['edit'])) {
    $editAddress = getAddress((int) $_GET['edit'], $user['id']);
}
$csrf = csrf_token();

$pageMetaTitle = SITE_NAME . ' - My Account';
$pageTitle     = 'My Account';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'My Account', 'url' => '']];
$heroEyebrow   = 'Your Space';
$heroSub       = 'Orders, addresses, appointments and membership &mdash; all in one place.';
$heroImage     = 'assets/img/banner/haram.jpg';
$pageStyles    = ['assets/css/pages.css'];

/** Map an order/appointment status onto a pill colour. */
function account_pill_class($status) {
    $s = strtolower((string) $status);
    if (in_array($s, ['delivered', 'completed', 'confirmed', 'paid'], true)) return ' is-ok';
    if (in_array($s, ['cancelled', 'failed', 'refunded'], true)) return ' is-bad';
    if (in_array($s, ['pending', 'processing', 'shipped'], true)) return ' is-warn';
    return '';
}

$accountTabs = [
    'profile'      => ['Profile',      '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>'],
    'addresses'    => ['Addresses',    '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>'],
    'orders'       => ['My Orders',    '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>'],
    'appointments' => ['Appointments', '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>'],
];

include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-hero.php'; ?>
    <section class="pg__section">
        <div class="container">
            <?php if ($notice): ?><div class="pg__alert is-ok"><?php echo e($notice); ?></div><?php endif; ?>
            <?php if ($errors): ?><div class="pg__alert is-error"><?php echo implode('<br>', array_map('e', $errors)); ?></div><?php endif; ?>

            <div class="pg__member">
                <span class="pg__member--label">Membership Number</span>
                <div class="pg__member--no"><?php echo e($user['membership_no'] ?: membership_number_for($user['id'])); ?></div>
                <div class="pg__member--who">
                    <?php echo e($user['name']); ?>
                    <span class="pg__member--tier"><?php echo e($user['membership_tier'] ?? 'classic'); ?> member</span>
                </div>
            </div>

            <div class="pg__grid pg__grid--aside">
                <nav>
                    <ul class="pg__nav">
                        <?php foreach ($accountTabs as $key => $t): ?>
                            <li><a class="<?php echo $tab === $key ? 'is-active' : ''; ?>" href="account.php?tab=<?php echo e($key); ?>">
                                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><?php echo $t[1]; ?></svg>
                                <?php echo e($t[0]); ?>
                            </a></li>
                        <?php endforeach; ?>
                        <li><a class="is-danger" href="logout.php">
                            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/></svg>
                            Logout
                        </a></li>
                    </ul>
                </nav>
                <div>
                    <?php if ($tab === 'profile'): ?>
                        <div class="pg__card pg__form mb-30">
                            <h2 class="pg__panel--title" style="margin-bottom:2rem">Profile Details</h2>
                            <form method="post" data-validate>
                                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                                <input type="hidden" name="do" value="profile">
                                <div class="mb-15"><label class="checkout__input--label">Name</label><input class="checkout__input--field border-radius-5 w-100" name="name" data-label="Name" minlength="2" required value="<?php echo e($user['name']); ?>"></div>
                                <div class="mb-15"><label class="checkout__input--label">Email <span style="font-weight:400">(optional)</span></label><input class="checkout__input--field border-radius-5 w-100" type="email" name="email" data-label="Email" placeholder="you@example.com" value="<?php echo e($user['email']); ?>"></div>
                                <div class="mb-15"><label class="checkout__input--label">Phone</label><input class="checkout__input--field border-radius-5 w-100" name="phone" type="tel" data-label="Phone" data-rule="phone" required value="<?php echo e($user['phone']); ?>"></div>
                                <button class="btn btn-primary" type="submit">Save Changes</button>
                            </form>
                        </div>
                        <div class="pg__card pg__form">
                            <h2 class="pg__panel--title" style="margin-bottom:2rem">Change Password</h2>
                            <form method="post" data-validate>
                                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                                <input type="hidden" name="do" value="password">
                                <div class="mb-15"><label class="checkout__input--label">Current Password</label><input class="checkout__input--field border-radius-5 w-100" type="password" name="current_password" data-label="Current password" required></div>
                                <div class="mb-15"><label class="checkout__input--label">New Password</label><input class="checkout__input--field border-radius-5 w-100" type="password" name="new_password" id="acctNewPassword" data-label="New password" data-rule="password" required></div>
                                <div class="mb-15"><label class="checkout__input--label">Confirm New Password</label><input class="checkout__input--field border-radius-5 w-100" type="password" name="new_password_confirm" data-label="Confirm new password" data-match="#acctNewPassword" data-match-message="Passwords do not match." required></div>
                                <button class="btn btn-primary" type="submit">Update Password</button>
                            </form>
                        </div>

                    <?php elseif ($tab === 'addresses'): ?>
                        <?php if ($addresses): ?>
                            <div class="pg__addrs">
                                <?php foreach ($addresses as $addr): ?>
                                    <div class="pg__addr<?php echo $addr['is_default'] ? ' is-default' : ''; ?>">
                                        <div class="pg__addr--head">
                                            <strong><?php echo e($addr['full_name']); ?></strong>
                                            <?php if ($addr['is_default']): ?><span class="pg__pill is-ok">Default</span><?php endif; ?>
                                        </div>
                                        <?php echo e($addr['phone']); ?><br>
                                        <?php echo nl2br(e(formatAddressText($addr))); ?>
                                        <div class="pg__addr--acts">
                                            <a href="account.php?tab=addresses&edit=<?php echo (int) $addr['id']; ?>">Edit</a>
                                            <form method="post" onsubmit="return confirm('Delete this address?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                                                <input type="hidden" name="do" value="address_delete">
                                                <input type="hidden" name="address_id" value="<?php echo (int) $addr['id']; ?>">
                                                <button type="submit">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="pg__card pg__form">
                            <h2 class="pg__panel--title" style="margin-bottom:2rem"><?php echo $editAddress ? 'Edit Address' : 'Add New Address'; ?></h2>
                            <form method="post" data-validate>
                                <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                                <input type="hidden" name="do" value="address_save">
                                <?php if ($editAddress): ?><input type="hidden" name="address_id" value="<?php echo (int) $editAddress['id']; ?>"><?php endif; ?>
                                <div class="row">
                                    <div class="col-md-6 mb-15"><label class="checkout__input--label">Full Name</label><input class="checkout__input--field border-radius-5 w-100" name="full_name" data-label="Full name" minlength="2" required value="<?php echo e($editAddress['full_name'] ?? $user['name']); ?>"></div>
                                    <div class="col-md-6 mb-15"><label class="checkout__input--label">Phone</label><input class="checkout__input--field border-radius-5 w-100" name="phone" type="tel" data-label="Phone" data-rule="phone" required value="<?php echo e($editAddress['phone'] ?? $user['phone']); ?>"></div>
                                    <div class="col-12 mb-15"><label class="checkout__input--label">Address line 1</label><input class="checkout__input--field border-radius-5 w-100" name="address_line1" data-label="Address line 1" required value="<?php echo e($editAddress['address_line1'] ?? ''); ?>"></div>
                                    <div class="col-12 mb-15"><label class="checkout__input--label">Address line 2</label><input class="checkout__input--field border-radius-5 w-100" name="address_line2" value="<?php echo e($editAddress['address_line2'] ?? ''); ?>"></div>
                                    <div class="col-md-4 mb-15"><label class="checkout__input--label">City</label><input class="checkout__input--field border-radius-5 w-100" name="city" data-label="City" required value="<?php echo e($editAddress['city'] ?? ''); ?>"></div>
                                    <div class="col-md-4 mb-15"><label class="checkout__input--label">State</label><input class="checkout__input--field border-radius-5 w-100" name="state" data-label="State" required value="<?php echo e($editAddress['state'] ?? ''); ?>"></div>
                                    <div class="col-md-4 mb-15"><label class="checkout__input--label">Postcode</label><input class="checkout__input--field border-radius-5 w-100" name="postal_code" data-label="Postcode" pattern="[1-9][0-9]{5}" data-message="Enter a valid 6-digit postcode." required value="<?php echo e($editAddress['postal_code'] ?? ''); ?>"></div>
                                    <div class="col-md-4 mb-15"><label class="checkout__input--label">Country</label><input class="checkout__input--field border-radius-5 w-100" name="country" value="<?php echo e($editAddress['country'] ?? 'India'); ?>"></div>
                                    <div class="col-12 mb-15"><label><input type="checkbox" name="is_default" value="1" <?php echo !empty($editAddress['is_default']) ? 'checked' : ''; ?>> Set as default</label></div>
                                </div>
                                <button class="btn btn-primary" type="submit">Save Address</button>
                            </form>
                        </div>

                    <?php elseif ($tab === 'appointments'): ?>
                        <?php $myAppointments = getUserAppointments($user['id']); ?>
                        <div class="pg__panel--head">
                            <h2 class="pg__panel--title">My Appointments</h2>
                            <a class="btn btn-primary" href="appointment.php">Book new appointment</a>
                        </div>
                        <?php if (empty($myAppointments)): ?>
                            <div class="pg__empty">
                                <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                                <p>No appointments yet.</p>
                                <span>Book a slot and we will have your shortlist ready when you arrive.</span>
                                <a class="btn btn-primary" href="appointment.php">Book an Appointment</a>
                            </div>
                        <?php else: ?>
                            <div class="pg__table--wrap">
                                <table class="pg__table">
                                    <thead><tr><th>Date</th><th>Time</th><th>Purpose</th><th>Status</th><th>Notes from the store</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($myAppointments as $ap): $apNote = trim((string) ($ap['admin_note'] ?? '')); ?>
                                            <tr>
                                                <td><?php echo date('d M Y', strtotime($ap['appointment_date'])); ?></td>
                                                <td><?php echo e($ap['appointment_time']); ?></td>
                                                <td><?php echo e($ap['purpose'] ?: 'General visit'); ?></td>
                                                <td><span class="pg__pill<?php echo account_pill_class($ap['status']); ?>"><?php echo e(ucfirst($ap['status'])); ?></span></td>
                                                <td class="pg__table--note">
                                                    <?php if ($apNote !== ''): ?>
                                                        <?php echo nl2br(e($apNote)); ?>
                                                    <?php elseif ($ap['status'] === 'cancelled'): ?>
                                                        <span class="pg__muted">Cancelled by the store &mdash; please call us and we will rebook you.</span>
                                                    <?php else: ?>
                                                        <span class="pg__muted">&mdash;</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                    <?php else: /* orders */ ?>
                        <div class="pg__panel--head">
                            <h2 class="pg__panel--title">My Orders</h2>
                            <a class="btn btn-outline-primary" href="products.php">Continue shopping</a>
                        </div>
                        <?php if (empty($orders)): ?>
                            <div class="pg__empty">
                                <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/></svg>
                                <p>No orders yet.</p>
                                <span>Once you place an order it will show up here with its status.</span>
                                <a class="btn btn-primary" href="products.php">Start Shopping</a>
                                <?php if ($unpaidAttempts): ?>
                                    <p style="margin-top:1.4rem;font-size:1.3rem">
                                        <?php echo (int) $unpaidAttempts; ?> checkout<?php echo $unpaidAttempts === 1 ? "" : "s"; ?>
                                        did not complete payment.
                                        <a href="account.php?tab=orders&amp;show=unpaid">Show <?php echo $unpaidAttempts === 1 ? "it" : "them"; ?></a>
                                        to finish paying.
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="pg__table--wrap">
                                <table class="pg__table">
                                    <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th>Total</th><th></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($orders as $o): $pay = payment_status_label($o); ?>
                                            <tr>
                                                <td><strong><?php echo e($o['order_number']); ?></strong></td>
                                                <td><?php echo date('d M Y', strtotime($o['created_at'])); ?></td>
                                                <td><span class="pg__pill<?php echo account_pill_class($o['order_status']); ?>"><?php echo e(ucfirst($o['order_status'])); ?></span></td>
                                                <td><span class="pg__pill<?php echo $pay['tone'] === 'ok' ? ' is-ok' : ($pay['tone'] === 'bad' ? ' is-bad' : ' is-warn'); ?>"><?php echo e($pay['label']); ?></span></td>
                                                <td><?php echo formatPrice($o['total_amount']); ?></td>
                                                <td><a href="order-details.php?order=<?php echo urlencode($o['order_number']); ?>">View</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php if ($unpaidAttempts): ?>
                                    <p class="pg__help" style="margin-top:1.6rem;font-size:1.3rem">
                                        <?php echo (int) $unpaidAttempts; ?> checkout<?php echo $unpaidAttempts === 1 ? '' : 's'; ?>
                                        did not complete payment and <?php echo $unpaidAttempts === 1 ? 'is' : 'are'; ?> not listed here.
                                        <a href="account.php?tab=orders&show=unpaid">Show <?php echo $unpaidAttempts === 1 ? 'it' : 'them'; ?></a>
                                        if you would like to finish paying.
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>
<?php include 'includes/footer.php'; ?>
