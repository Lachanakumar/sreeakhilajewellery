<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';
require_once 'includes/engagement.php';

$scheme = !empty($_GET['slug']) ? getSchemeBySlug($_GET['slug']) : null;
if (!$scheme || $scheme['status'] !== 'active') {
    redirect('schemes.php');
}
$user = currentUser();
$csrf = csrf_token();

$benefits = [];
if ($scheme['benefits']) {
    foreach (preg_split('/[\n;]+/', $scheme['benefits']) as $b) {
        $b = trim($b);
        if ($b !== '') $benefits[] = $b;
    }
}

/* Indicative total the customer pays in over the term — the store contribution
   is deliberately not guessed here, it is described in the scheme terms. */
$totalPaid = ($scheme['duration_months'] && $scheme['monthly_amount'])
    ? (float) $scheme['duration_months'] * (float) $scheme['monthly_amount']
    : null;

$pageMetaTitle = SITE_NAME . ' - ' . $scheme['name'];
$pageMetaDesc  = $scheme['short_description'] ?: ('Join the ' . $scheme['name'] . ' savings scheme at ' . SITE_NAME);
$pageTitle     = $scheme['name'];
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Schemes', 'url' => 'schemes.php']];
if ($scheme['category_slug']) {
    $breadcrumbs[] = ['name' => $scheme['category_name'], 'url' => 'schemes.php?category=' . $scheme['category_slug']];
}
$breadcrumbs[] = ['name' => $scheme['name'], 'url' => ''];
$heroEyebrow = $scheme['category_name'] ?: 'Savings Scheme';
$heroSub     = $scheme['short_description'] ?: '';
$heroImage   = $scheme['image'] ?: 'assets/img/banner/traditional.jpg';
$pageStyles  = ['assets/css/pages.css'];

include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-hero.php'; ?>

    <section class="pg__section">
        <div class="container">
            <div class="pg__grid pg__grid--form">

                <div>
                    <?php if ($scheme['duration_months'] || $scheme['monthly_amount']): ?>
                        <div class="pg__card mb-30">
                            <div class="pg__scheme--meta" style="margin:0;padding:0;border:0">
                                <?php if ($scheme['duration_months']): ?>
                                    <div><strong><?php echo (int) $scheme['duration_months']; ?></strong><span>Months</span></div>
                                <?php endif; ?>
                                <?php if ($scheme['monthly_amount']): ?>
                                    <div><strong><?php echo formatPrice($scheme['monthly_amount'], 0); ?></strong><span>Per month</span></div>
                                <?php endif; ?>
                                <?php if ($totalPaid !== null): ?>
                                    <div><strong><?php echo formatPrice($totalPaid, 0); ?></strong><span>You pay in</span></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($scheme['description']): ?>
                        <h2 class="pg__title">About this scheme</h2>
                        <p class="pg__lead mb-35"><?php echo nl2br(e($scheme['description'])); ?></p>
                    <?php endif; ?>

                    <?php if ($benefits): ?>
                        <h2 class="pg__title">What you get</h2>
                        <ul class="pg__ticks mb-35">
                            <?php foreach ($benefits as $b): ?>
                                <li><?php echo e($b); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($scheme['terms']): ?>
                        <div class="pg__card pg__section--ivory" style="background:var(--ivory)">
                            <h3 class="pg__form--title">Terms &amp; Conditions</h3>
                            <p style="font-size:1.38rem;line-height:2.4rem;color:var(--text-gray-color);margin:0"><?php echo nl2br(e($scheme['terms'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="pg__card pg__card--sticky pg__form">
                    <h2 class="pg__form--title">Enrol or enquire</h2>
                    <p class="pg__form--sub">Leave your details and our scheme desk will call you back to complete the enrolment.</p>
                    <div class="pg__alert is-ok js-form-success" hidden></div>
                    <form class="js-ajax-form" action="ajax/enquiry.php" method="post" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                        <input type="hidden" name="scheme_id" value="<?php echo (int) $scheme['id']; ?>">
                        <input type="hidden" name="subject" value="Scheme enquiry: <?php echo e($scheme['name']); ?>">
                        <div class="mb-15"><label class="checkout__input--label">Name</label><input class="checkout__input--field w-100" name="name" data-label="Name" minlength="2" value="<?php echo e($user['name'] ?? ''); ?>" required></div>
                        <div class="mb-15"><label class="checkout__input--label">Mobile</label><input class="checkout__input--field w-100" name="phone" type="tel" data-label="Mobile" data-rule="phone" value="<?php echo e($user['phone'] ?? ''); ?>" required></div>
                        <div class="mb-15"><label class="checkout__input--label">Email <span style="font-weight:400">(optional)</span></label><input class="checkout__input--field w-100" name="email" type="email" data-label="Email" value="<?php echo e($user['email'] ?? ''); ?>"></div>
                        <div class="mb-15"><label class="checkout__input--label">Message <span style="font-weight:400">(optional)</span></label><textarea class="checkout__input--field w-100" name="message" rows="3"></textarea></div>
                        <button class="btn btn-primary w-100" type="submit">Submit Enquiry</button>
                    </form>
                    <p style="font-size:1.32rem;margin:1.6rem 0 0">Prefer to talk in person? <a href="appointment.php">Book a store visit</a>.</p>
                </aside>
            </div>
        </div>
    </section>
</main>
<?php include 'includes/footer.php'; ?>
