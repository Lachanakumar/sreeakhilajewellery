<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';
require_once 'includes/engagement.php';

$user       = currentUser();
$wishlist   = getWishlistItems();
$slots      = appointment_time_slots();
$csrf       = csrf_token();
$prefAttach = isset($_GET['wishlist']);

$pageMetaTitle = SITE_NAME . ' - Book an Appointment';
$pageMetaDesc  = 'Book a personal jewellery appointment at ' . SITE_NAME . ' and we will have your shortlist ready.';
$pageTitle     = 'Book an Appointment';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Book an Appointment', 'url' => '']];
$heroEyebrow   = 'Personal Visit';
$heroSub       = 'Pick a time that suits you and we will have your pieces laid out and ready to try on.';
$heroImage     = 'assets/img/banner/haram.jpg';
$pageStyles    = ['assets/css/pages.css'];

$perks = [
    'A private counter with no queue',
    'Your wishlist pulled and ready to try on',
    'Advice on making charges and exchange value',
    'No obligation to buy on the day',
];

include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-hero.php'; ?>

    <section class="pg__section">
        <div class="container">
            <div class="pg__grid pg__grid--form">

                <div class="pg__card pg__form">
                    <h2 class="pg__form--title">Schedule your visit</h2>
                    <p class="pg__form--sub">Pick a date and time and our team will keep your selection ready. We will confirm your slot by phone.</p>
                    <div class="pg__alert is-ok js-form-success" hidden></div>

                    <form class="js-ajax-form" action="ajax/appointment.php" method="post" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                        <div class="row">
                            <div class="col-md-6 mb-15"><label class="checkout__input--label">Name <span class="req" aria-hidden="true">*</span></label><input class="checkout__input--field w-100" name="name" data-label="Name" minlength="2" value="<?php echo e($user['name'] ?? ''); ?>" required></div>
                            <div class="col-md-6 mb-15"><label class="checkout__input--label">Mobile <span class="req" aria-hidden="true">*</span></label><input class="checkout__input--field w-100" type="tel" name="phone" data-label="Mobile" data-rule="phone" value="<?php echo e($user['phone'] ?? ''); ?>" required></div>
                            <div class="col-md-6 mb-15"><label class="checkout__input--label">Email <span style="font-weight:400">(optional)</span></label><input class="checkout__input--field w-100" type="email" name="email" data-label="Email" value="<?php echo e($user['email'] ?? ''); ?>"></div>
                            <div class="col-md-6 mb-15" data-error-anchor><label class="checkout__input--label">Preferred date <span class="req" aria-hidden="true">*</span></label><input class="checkout__input--field w-100" type="text" data-datepicker name="appointment_date" data-label="Preferred date" data-min="<?php echo date('Y-m-d'); ?>" placeholder="Pick a date" required aria-required="true"></div>
                            <div class="col-md-6 mb-15"><label class="checkout__input--label">Preferred time <span class="req" aria-hidden="true">*</span></label>
                                <?php if ($slots): ?>
                                    <select class="w-100" name="appointment_time" data-label="Preferred time" required>
                                        <option value="">Select a slot</option>
                                        <?php foreach ($slots as $slot): ?><option value="<?php echo e($slot); ?>"><?php echo e($slot); ?></option><?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input class="checkout__input--field w-100" type="time" name="appointment_time" data-label="Preferred time" required>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-15"><label class="checkout__input--label">Purpose <span style="font-weight:400">(optional)</span></label>
                                <select class="w-100" name="purpose">
                                    <option value="">General visit</option>
                                    <option>View wishlist pieces</option>
                                    <option>Bridal jewellery consultation</option>
                                    <option>Gold / diamond scheme</option>
                                    <option>Old gold exchange / valuation</option>
                                    <option>Repair or resizing</option>
                                </select>
                            </div>
                            <div class="col-12 mb-15"><label class="checkout__input--label">Message <span style="font-weight:400">(optional)</span></label><textarea class="checkout__input--field w-100" name="message" rows="3" placeholder="Anything we should have ready for you?"></textarea></div>
                            <?php if ($wishlist): ?>
                                <div class="col-12 mb-15">
                                    <label class="pg__check">
                                        <input type="checkbox" name="attach_wishlist" value="1" <?php echo $prefAttach ? 'checked' : ''; ?>>
                                        <span>Attach my wishlist (<?php echo count($wishlist); ?> item<?php echo count($wishlist) === 1 ? '' : 's'; ?>) so the store keeps them ready</span>
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn-primary" type="submit">Request Appointment</button>
                    </form>
                </div>

                <aside>
                    <div class="pg__card mb-30">
                        <h3 class="pg__aside--title">What to expect</h3>
                        <ul class="pg__ticks" style="margin:0">
                            <?php foreach ($perks as $p): ?><li><?php echo e($p); ?></li><?php endforeach; ?>
                        </ul>
                    </div>

                    <?php if ($wishlist): ?>
                        <div class="pg__card mb-30">
                            <h3 class="pg__aside--title">Your wishlist</h3>
                            <?php foreach ($wishlist as $p): ?>
                                <div class="pg__mini">
                                    <img src="<?php echo e($p['image']); ?>" alt="<?php echo e($p['name']); ?>">
                                    <div>
                                        <a href="product-details.php?id=<?php echo (int) $p['id']; ?>"><?php echo e($p['name']); ?></a>
                                        <small><?php echo e($p['sku']); ?> &middot; <?php echo formatPrice($p['price'], 0); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="pg__card mb-30">
                            <h3 class="pg__aside--title">Add pieces to your wishlist</h3>
                            <p style="font-size:1.4rem;line-height:2.4rem">Save the jewellery you would like to see, then attach the list to your appointment so it is ready when you arrive.</p>
                            <a class="btn btn-outline-primary" href="products.php">Browse Jewellery</a>
                        </div>
                    <?php endif; ?>

                    <div class="pg__card">
                        <h3 class="pg__aside--title">Visit us</h3>
                        <p class="pg__info--value" style="margin-bottom:1.2rem"><?php echo SITE_ADDRESS; ?></p>
                        <p class="pg__info--value" style="margin-bottom:.6rem"><?php echo site_phone_links(); ?></p>
                        <p class="pg__info--value" style="margin:0"><a href="mailto:<?php echo SITE_EMAIL; ?>"><?php echo SITE_EMAIL; ?></a></p>
                        <?php if ($user): ?>
                            <p style="font-size:1.34rem;margin:1.8rem 0 0"><a href="account.php?tab=appointments">See my appointments</a></p>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>
<?php include 'includes/footer.php'; ?>
