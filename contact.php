<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';

$pageMetaTitle = SITE_NAME . ' - Contact Us';
$pageMetaDesc  = 'Get in touch with ' . SITE_NAME . ' for any enquiries about our gold, silver and diamond collections.';
$pageTitle     = 'Contact Us';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Contact Us', 'url' => '']];
$heroEyebrow   = 'We Are Listening';
$heroSub       = 'Questions about a piece, a price or an order? Our team replies the same working day.';
$heroImage     = 'assets/img/banner/haram.jpg';
$pageStyles    = ['assets/css/pages.css'];

$infos = [
    [
        'label' => 'Our Location',
        'value' => SITE_ADDRESS,
        'href'  => null,
        'icon'  => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
    ],
    [
        'label' => 'Call Us',
        // rendered as one tel: link per number — a single href holding both
        // numbers put all of them on the dial pad at once
        'html'  => site_phone_links('<br>'),
        'value' => SITE_PHONE,
        'href'  => null,
        'icon'  => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
    ],
    [
        'label' => 'Email Support',
        'value' => SITE_EMAIL,
        'href'  => 'mailto:' . SITE_EMAIL,
        'icon'  => '<rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="22,6 12,13 2,6"/>',
    ],
];

// Admin -> Settings owns these; a blank one is skipped rather than rendered
// as an empty card with a lone icon.
$infos = array_values(array_filter($infos, function ($i) {
    return trim((string) ($i['html'] ?? $i['value'])) !== '';
}));

include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-hero.php'; ?>

    <section class="pg__section">
        <div class="container">
            <div class="pg__info mb-50">
                <?php foreach ($infos as $i): ?>
                    <div class="pg__info--item">
                        <span class="pg__info--icon">
                            <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><?php echo $i['icon']; ?></svg>
                        </span>
                        <div>
                            <span class="pg__info--label"><?php echo e($i['label']); ?></span>
                            <p class="pg__info--value">
                                <?php if (!empty($i['html'])): ?>
                                    <?php echo $i['html']; ?>
                                <?php elseif ($i['href']): ?>
                                    <a href="<?php echo e($i['href']); ?>"><?php echo e($i['value']); ?></a>
                                <?php else: ?>
                                    <?php echo e($i['value']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="pg__grid pg__grid--2">
                <div class="pg__card pg__form">
                    <h2 class="pg__form--title">Send us a message</h2>
                    <p class="pg__form--sub">Tell us what you are looking for and we will come back to you with options and pricing.</p>
                    <div id="contact-message"></div>

                    <form class="contact__form--inner" id="contact-form" method="POST" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                        <div class="row">
                            <div class="col-md-6 mb-15">
                                <div class="contact__form--list">
                                    <label class="checkout__input--label" for="firstname">First Name</label>
                                    <input class="checkout__input--field w-100" name="firstname" id="firstname" data-label="First name" placeholder="First name" type="text" minlength="2" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-15">
                                <div class="contact__form--list">
                                    <label class="checkout__input--label" for="lastname">Last Name</label>
                                    <input class="checkout__input--field w-100" name="lastname" id="lastname" data-label="Last name" placeholder="Last name" type="text" minlength="1" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-15">
                                <div class="contact__form--list">
                                    <label class="checkout__input--label" for="phone">Phone Number</label>
                                    <input class="checkout__input--field w-100" name="phone" id="phone" data-label="Phone number" placeholder="10-digit mobile number" type="tel" data-rule="phone" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-15">
                                <div class="contact__form--list">
                                    <label class="checkout__input--label" for="email">Email</label>
                                    <input class="checkout__input--field w-100" name="email" id="email" data-label="Email" placeholder="john.doe@example.com" type="email" required>
                                </div>
                            </div>
                            <div class="col-12 mb-15">
                                <div class="contact__form--list">
                                    <label class="checkout__input--label" for="message">Your Message</label>
                                    <textarea class="checkout__input--field w-100" name="message" id="message" data-label="Message" rows="5" placeholder="How can we help you today?" required></textarea>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-primary" type="submit" id="submit-btn">Send Message</button>
                    </form>
                </div>

                <div class="pg__map">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3913.3101521785!2d78.86656719999999!3d11.2385795!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3bab1b1837af058b%3A0x6ee35965a22dad2e!2sSRI%20ANAND%20JEWELLERS%2C%20NK%20COMPLEX!5e0!3m2!1sen!2sin!4v1788746057908!5m2!1sen!2sin" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
                </div>
            </div>
        </div>
    </section>

    <section class="pg__section pg__section--tight">
        <div class="container">
            <div class="pg__cta" style="--pg-cta-image:url('<?php echo e(asset_url('assets/img/banner/traditional.jpg')); ?>')">
                <h2 class="pg__cta--title">Rather see it in person?</h2>
                <p>Book a slot and we will have your shortlist ready when you walk in.</p>
                <div class="pg__cta--actions">
                    <a class="btn btn-primary" href="appointment.php">Book an Appointment</a>
                    <a class="btn pg__cta--ghost" href="feedback.php">Leave Feedback</a>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    document.getElementById('contact-form').addEventListener('submit', function(e) {
        e.preventDefault();

        var form = this;
        var submitBtn = document.getElementById('submit-btn');
        var messageDiv = document.getElementById('contact-message');

        /* This listener is bound to the form, so it runs before validate.js's
           document-level handler. Run the shared rules here or invalid data
           would post straight through. */
        if (window.formValidate && window.formValidate.form(form)) return;

        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';
        messageDiv.innerHTML = '';

        fetch('contact-process.php', {
                method: 'POST',
                body: new FormData(form)
            })
            .then(function(r) {
                return r.json();
            })
            .then(function(data) {
                messageDiv.innerHTML = '<div class="pg__alert ' + (data.success ? 'is-ok' : 'is-error') + '"></div>';
                messageDiv.firstChild.textContent = data.message;
                if (data.success) form.reset();
            })
            .catch(function() {
                messageDiv.innerHTML = '<div class="pg__alert is-error">Something went wrong. Please try again.</div>';
            })
            .finally(function() {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send Message';
            });
    });
</script>

<?php include 'includes/footer.php'; ?>