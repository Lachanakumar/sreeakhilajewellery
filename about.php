<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';

$pageMetaTitle = SITE_NAME . ' - About Us';
$pageMetaDesc  = 'Discover the story behind ' . SITE_NAME . ', where tradition meets modern elegance in jewelry crafting.';
$pageTitle     = 'About Us';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'About Us', 'url' => '']];
$pageStyles    = ['assets/css/about.css'];

$designCount = getProducts(['limit' => 1])['total'];

$values = [
    [
        'title' => 'Uncompromising Quality',
        'desc'  => 'Every diamond and gemstone is hand-selected and verified for its exceptional brilliance and quality.',
        'icon'  => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
    ],
    [
        'title' => 'Ethical Craftsmanship',
        'desc'  => 'We take pride in ethically sourced materials and sustainable practices that honour both people and planet.',
        'icon'  => '<path d="M20.42 4.58a5.4 5.4 0 0 0-7.65 0l-.77.78-.77-.78a5.4 5.4 0 0 0-7.65 0C1.46 6.7 1.33 10.28 4 13l8 8 8-8c2.67-2.72 2.54-6.3.42-8.42z"/>',
    ],
    [
        'title' => 'Trusted Legacy',
        'desc'  => 'With over 25 years of experience, we have built a legacy of trust and satisfaction with our customers.',
        'icon'  => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    ],
];

$steps = [
    ['Design',   'Every piece begins as a sketch, shaped around the occasion it is meant for.'],
    ['Source',   'Gold, diamonds and silver are sourced from certified suppliers and assayed on arrival.'],
    ['Craft',    'Our artisans set, solder and finish each piece by hand using traditional techniques.'],
    ['Hallmark', 'Nothing leaves the workshop until it is BIS hallmarked and inspected a final time.'],
];

include 'includes/header.php';
?>

<main class="main__content_wrapper">

    <!-- Hero -->
    <section class="about__hero">
        <div class="container">
            <div class="about__hero--inner">
                <span class="about__hero--eyebrow">About <?php echo e(SITE_NAME); ?></span>
                <h1 class="about__hero--title">Where tradition meets modern elegance</h1>
                <p class="about__hero--desc">A quarter-century of craftsmanship, and a simple belief: jewellery should be worth passing on.</p>
                <nav class="about__hero--crumbs" aria-label="Breadcrumb">
                    <a href="index.php">Home</a>
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"/></svg>
                    <span aria-current="page">About Us</span>
                </nav>
            </div>
        </div>
    </section>

    <!-- Story -->
    <section class="about__section">
        <div class="container">
            <div class="about__story">
                <div class="about__story--media">
                    <img src="assets/img/other/aboutus.jpg" alt="<?php echo e(SITE_NAME); ?> showroom" loading="lazy">
                </div>
                <div>
                    <span class="about__story--eyebrow">Our Story</span>
                    <h2 class="about__story--title">Craftsmanship you can hold,<br>heritage you can wear.</h2>
                    <p>At <?php echo e(SITE_NAME); ?>, we celebrate the beauty of tradition blended with modern elegance. Our journey is driven by a deep passion for craftsmanship and a commitment to creating jewellery that tells a story.</p>
                    <p>Each piece we design reflects fine artistry, precision and timeless appeal. From exquisite gold ornaments to intricately crafted bridal collections, we ensure every creation meets the highest standards of quality and authenticity &mdash; using ethically sourced 24K gold, certified diamonds and premium silver.</p>
                    <p>Our skilled artisans combine traditional techniques with contemporary designs to create pieces that are both unique and enduring. Whether it is a wedding, a celebration or a quiet moment worth marking, we are here to make it special.</p>
                    <a class="btn btn-primary" href="products.php">Browse the Collection</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats -->
    <section class="about__section about__section--ivory">
        <div class="container">
            <div class="about__stats">
                <div class="about__stat"><strong>25+</strong><span>Years of Trust</span></div>
                <div class="about__stat"><strong><?php echo (int) $designCount; ?>+</strong><span>Designs</span></div>
                <div class="about__stat"><strong>100%</strong><span>BIS Hallmarked</span></div>
                <div class="about__stat"><strong>24K</strong><span>Certified Gold</span></div>
            </div>
        </div>
    </section>

    <!-- Values -->
    <section class="about__section">
        <div class="container">
            <div class="section__heading text-center mb-50">
                <h2 class="section__heading--maintitle">Our Core Values</h2>
                <p class="section__heading--desc">The three things we will not trade away, whatever the piece or the price.</p>
            </div>
            <div class="about__values">
                <?php foreach ($values as $v): ?>
                    <div class="about__value">
                        <span class="about__value--icon">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><?php echo $v['icon']; ?></svg>
                        </span>
                        <h3 class="about__value--title"><?php echo e($v['title']); ?></h3>
                        <p><?php echo e($v['desc']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- How a piece is made -->
    <section class="about__section about__section--ivory">
        <div class="container">
            <div class="section__heading text-center mb-50">
                <h2 class="section__heading--maintitle">How a Piece Is Made</h2>
                <p class="section__heading--desc">From first sketch to final hallmark, every ornament passes through four hands-on stages.</p>
            </div>
            <div class="about__steps">
                <?php foreach ($steps as $i => $s): ?>
                    <div class="about__step">
                        <span class="about__step--num">Step <?php echo $i + 1; ?></span>
                        <h3 class="about__step--title"><?php echo e($s[0]); ?></h3>
                        <p><?php echo e($s[1]); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="about__section">
        <div class="container">
            <div class="about__cta">
                <h2 class="about__cta--title">Come see it in person</h2>
                <p>Photographs only go so far. Book a visit and try on the pieces you have been saving.</p>
                <div class="about__cta--actions">
                    <a class="btn btn-primary" href="appointment.php">Book an Appointment</a>
                    <a class="btn about__cta--ghost" href="contact.php">Contact Us</a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
