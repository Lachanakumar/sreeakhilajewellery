<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';
require_once 'includes/engagement.php';

$dg    = digigold_config();
$rates = getLatestRates();

$pageMetaTitle = SITE_NAME . ' - Digi Gold';
$pageMetaDesc  = 'Buy 24K digital gold from your phone, track live rates and redeem for jewellery at ' . SITE_NAME . '.';
$pageTitle     = 'Digi Gold';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Digi Gold', 'url' => '']];
$heroEyebrow   = 'Save in Gold';
$heroSub       = 'Own 24K gold from ₹1, track the live rate, and redeem for jewellery whenever you are ready.';
$heroImage     = 'assets/img/slider/slider2.png';
$pageStyles    = ['assets/css/pages.css'];

$points = [
    'Start from as little as &#8377;1',
    'Live 24K rate, no hidden charges',
    'Insured &amp; stored securely in your name',
    'Redeem for jewellery, coins or cash any time',
    'Auto-save daily, weekly or monthly',
];

$steps = [
    ['Open your account', 'Sign up in the app with your mobile number and KYC.'],
    ['Buy any amount',    'Buy by rupee value or by grams at the live 24K rate.'],
    ['We store it',       'Your gold is insured and held in secure vaults in your name.'],
    ['Redeem any time',   'Convert to jewellery or coins in store, or sell it back.'],
];

include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-hero.php'; ?>

    <section class="pg__section">
        <div class="container">
            <div class="pg__grid pg__grid--form">

                <div>
                    <span class="pg__eyebrow">Why Digi Gold</span>
                    <h2 class="pg__title">Own gold, one rupee at a time</h2>
                    <p class="pg__lead mb-35"><?php echo e($dg['blurb'] ?: 'Buy 24K digital gold from your phone, track live rates, and redeem for jewellery or coins whenever you like.'); ?></p>

                    <ul class="pg__ticks mb-35">
                        <?php foreach ($points as $p): ?><li><?php echo $p; ?></li><?php endforeach; ?>
                    </ul>

                    <div class="pg__badges">
                        <?php if ($dg['android']): ?>
                            <a class="app__badge" href="<?php echo e($dg['android']); ?>" target="_blank" rel="noopener">
                                <svg width="20" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M3 20.5V3.5c0-.6.3-1.1.8-1.4l10.9 9.9L3.8 21.9c-.5-.3-.8-.8-.8-1.4zM16.8 14.3l2.7 2.4c.8.5.8 1.7 0 2.2l-3.3 1.9-3.2-2.9 3.8-3.6zM5.3 2l10.1 5.8-2.9 2.7L5.3 2zm10.1 14.4L5.3 22l7.2-8.5 2.9 2.9z"/></svg>
                                <span><small>Get it on</small><br>Google Play</span>
                            </a>
                        <?php endif; ?>
                        <?php if ($dg['ios']): ?>
                            <a class="app__badge" href="<?php echo e($dg['ios']); ?>" target="_blank" rel="noopener">
                                <svg width="20" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M16.4 12.9c0-2.6 2.1-3.8 2.2-3.9-1.2-1.8-3.1-2-3.7-2-1.6-.2-3.1.9-3.9.9s-2-.9-3.4-.9c-1.7 0-3.3 1-4.2 2.6-1.8 3.1-.5 7.7 1.3 10.2.9 1.2 1.9 2.6 3.3 2.5 1.3-.1 1.8-.9 3.4-.9s2 .9 3.4.8c1.4 0 2.3-1.2 3.2-2.5.6-.9 1-1.7 1.4-2.7-3.7-1.4-2.7-5.6-2.7-5.7zM13.9 4.2c.7-.9 1.2-2.1 1.1-3.3-1 0-2.3.7-3 1.5-.7.8-1.3 2-1.1 3.2 1.1.1 2.3-.6 3-1.4z"/></svg>
                                <span><small>Download on the</small><br>App Store</span>
                            </a>
                        <?php endif; ?>
                        <?php if (!$dg['android'] && !$dg['ios']): ?>
                            <div class="pg__alert is-ok" style="margin:0">
                                App links are coming soon &mdash; <a href="contact.php">contact us</a> and we will set you up at the store.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <aside class="pg__card pg__card--sticky">
                    <div class="pg__rate--head">
                        <h3 class="pg__form--title" style="margin:0">Today's rates</h3>
                        <span class="pg__rate--live"><i class="pg__rate--dot"></i><?php echo date('d M Y'); ?></span>
                    </div>
                    <?php if ($rates): ?>
                        <table class="pg__rates">
                            <?php foreach ($rates as $r): ?>
                                <tr>
                                    <td><?php echo e($r['label']); ?></td>
                                    <td><strong><?php echo formatPrice($r['rate_per_gram'], 2); ?></strong> <span style="color:var(--text-gray-color)">/ <?php echo e($r['unit']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php else: ?>
                        <p style="font-size:1.4rem;color:var(--text-gray-color)">Rates are updated daily in store. Call us for today's price.</p>
                    <?php endif; ?>
                    <p style="font-size:1.24rem;color:var(--text-gray-color);margin:1.6rem 0 0">Indicative rates. The actual purchase rate is confirmed at the time of the transaction.</p>
                    <a class="btn btn-outline-primary w-100" style="margin-top:1.8rem" href="contact.php">Talk to Us</a>
                </aside>
            </div>
        </div>
    </section>

    <section class="pg__section pg__section--ivory">
        <div class="container">
            <div class="section__heading text-center mb-50">
                <h2 class="section__heading--maintitle">How It Works</h2>
                <p class="section__heading--desc">Four steps from your first rupee to jewellery in hand.</p>
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
            <div class="pg__cta" style="--pg-cta-image:url('<?php echo e(asset_url('assets/img/banner/haram.jpg')); ?>')">
                <h2 class="pg__cta--title">Ready to turn savings into gold?</h2>
                <p>Start in the app, or drop into the store and we will open your account with you.</p>
                <div class="pg__cta--actions">
                    <a class="btn btn-primary" href="appointment.php">Book an Appointment</a>
                    <a class="btn pg__cta--ghost" href="schemes.php">See Savings Schemes</a>
                </div>
            </div>
        </div>
    </section>
</main>
<?php include 'includes/footer.php'; ?>
