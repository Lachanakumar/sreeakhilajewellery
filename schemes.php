<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';
require_once 'includes/engagement.php';

$cat = null;
if (!empty($_GET['category'])) {
    $cat = getSchemeCategoryBySlug($_GET['category']);
}
$categories = getSchemeCategories();
$schemes = getSchemes($cat ? ['category_id' => $cat['id']] : []);

$pageMetaTitle = SITE_NAME . ' - ' . ($cat ? $cat['name'] : 'Savings Schemes');
$pageMetaDesc  = 'Save monthly and own the jewellery you have been planning for, with schemes from ' . SITE_NAME . '.';
$pageTitle     = $cat ? $cat['name'] : 'Savings Schemes';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Schemes', 'url' => 'schemes.php']];
if ($cat) {
    $breadcrumbs[] = ['name' => $cat['name'], 'url' => ''];
}
$heroEyebrow = 'Plan Ahead';
$heroSub     = 'Set aside a little each month and let it grow into the piece you have been saving for.';
$heroImage   = 'assets/img/slider/slider1.png';
$pageStyles  = ['assets/css/pages.css'];

$steps = [
    ['Choose a plan',  'Pick the scheme and monthly amount that fits your budget.'],
    ['Pay monthly',    'Pay in store or online on any day of the month.'],
    ['We add ours',    'Complete the term and the store contribution is added on top.'],
    ['Redeem',         'Use the matured value against any jewellery in the showroom.'],
];

include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-hero.php'; ?>

    <section class="pg__section">
        <div class="container">
            <?php if ($categories): ?>
                <div class="pg__chips mb-35">
                    <a class="pg__chip<?php echo $cat ? '' : ' is-active'; ?>" href="schemes.php">All Schemes</a>
                    <?php foreach ($categories as $c): ?>
                        <a class="pg__chip<?php echo ($cat && $cat['id'] == $c['id']) ? ' is-active' : ''; ?>" href="schemes.php?category=<?php echo e($c['slug']); ?>"><?php echo e($c['name']); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($cat && $cat['description']): ?>
                <p class="pg__lead mb-35"><?php echo nl2br(e($cat['description'])); ?></p>
            <?php endif; ?>

            <?php if (!$schemes): ?>
                <div class="pg__empty">
                    <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="3" y="6" width="18" height="14" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                    <p>No schemes are open right now.</p>
                    <span>New plans are announced each season &mdash; we can let you know when the next one opens.</span>
                    <a class="btn btn-primary" href="contact.php">Contact Us</a>
                </div>
            <?php else: ?>
                <div class="pg__schemes">
                    <?php foreach ($schemes as $s): ?>
                        <article class="pg__scheme">
                            <?php if ($s['image']): ?>
                                <a class="pg__scheme--media" href="scheme-details.php?slug=<?php echo e($s['slug']); ?>">
                                    <img src="<?php echo e($s['image']); ?>" alt="<?php echo e($s['name']); ?>" loading="lazy">
                                </a>
                            <?php endif; ?>
                            <div class="pg__scheme--body">
                                <?php if ($s['category_name']): ?><span class="pg__scheme--cat"><?php echo e($s['category_name']); ?></span><?php endif; ?>
                                <h2 class="pg__scheme--title"><a href="scheme-details.php?slug=<?php echo e($s['slug']); ?>"><?php echo e($s['name']); ?></a></h2>
                                <p class="pg__scheme--desc"><?php echo e($s['short_description']); ?></p>
                                <?php if ($s['duration_months'] || $s['monthly_amount']): ?>
                                    <div class="pg__scheme--meta">
                                        <?php if ($s['duration_months']): ?>
                                            <div><strong><?php echo (int) $s['duration_months']; ?></strong><span>Months</span></div>
                                        <?php endif; ?>
                                        <?php if ($s['monthly_amount']): ?>
                                            <div><strong><?php echo formatPrice($s['monthly_amount'], 0); ?></strong><span>Per month</span></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <a class="btn btn-primary" href="scheme-details.php?slug=<?php echo e($s['slug']); ?>">View Details</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- How it works -->
    <section class="pg__section pg__section--ivory">
        <div class="container">
            <div class="section__heading text-center mb-50">
                <h2 class="section__heading--maintitle">How a Scheme Works</h2>
                <p class="section__heading--desc">Four simple steps from your first instalment to the piece you take home.</p>
            </div>
            <div class="pg__steps">
                <?php foreach ($steps as $i => $s): ?>
                    <div class="pg__step">
                        <span class="pg__step--num"><?php echo $i + 1; ?></span>
                        <h3 class="pg__step--title"><?php echo e($s[0]); ?></h3>
                        <p><?php echo e($s[1]); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="pg__section">
        <div class="container">
            <div class="pg__cta" style="--pg-cta-image:url('<?php echo e(asset_url('assets/img/banner/traditional.jpg')); ?>')">
                <h2 class="pg__cta--title">Not sure which plan suits you?</h2>
                <p>Our scheme desk will walk you through the options and the maturity value, with no obligation.</p>
                <div class="pg__cta--actions">
                    <a class="btn btn-primary" href="appointment.php">Book an Appointment</a>
                    <a class="btn pg__cta--ghost" href="contact.php">Ask a Question</a>
                </div>
            </div>
        </div>
    </section>
</main>
<?php include 'includes/footer.php'; ?>
