<?php
require_once 'includes/config.php';
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';
require_once 'includes/auth.php';
require_once 'includes/engagement.php';

$user = currentUser();
$csrf = csrf_token();

$pageMetaTitle = SITE_NAME . ' - Customer Feedback';
$pageMetaDesc  = 'Tell us about your experience at ' . SITE_NAME . '.';
$pageTitle     = 'Customer Feedback';
$breadcrumbs   = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Feedback', 'url' => '']];
$heroEyebrow   = 'Your Experience';
$heroSub       = 'The good and the not-so-good &mdash; both help us serve you better.';
$heroImage     = 'assets/img/slider/slider2.png';
$pageStyles    = ['assets/css/pages.css'];

include 'includes/header.php';
?>
<main class="main__content_wrapper">
    <?php include 'includes/page-hero.php'; ?>

    <section class="pg__section">
        <div class="container">
            <div class="pg__grid pg__grid--form">

                <div class="pg__card pg__form">
                    <h2 class="pg__form--title">We'd love to hear from you</h2>
                    <p class="pg__form--sub">Tell us about your visit, a piece you bought, or anything we could do better.</p>
                    <div class="pg__alert is-ok js-form-success" hidden></div>

                    <form class="js-ajax-form" action="ajax/feedback.php" method="post" data-validate>
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>">
                        <div class="row">
                            <div class="col-md-6 mb-15"><label class="checkout__input--label">Name</label><input class="checkout__input--field w-100" name="name" data-label="Name" minlength="2" value="<?php echo e($user['name'] ?? ''); ?>" required></div>
                            <div class="col-md-6 mb-15"><label class="checkout__input--label">Mobile <span style="font-weight:400">(optional)</span></label><input class="checkout__input--field w-100" type="tel" name="phone" data-label="Mobile" value="<?php echo e($user['phone'] ?? ''); ?>"></div>
                            <div class="col-md-6 mb-15"><label class="checkout__input--label">Email <span style="font-weight:400">(optional)</span></label><input class="checkout__input--field w-100" type="email" name="email" data-label="Email" value="<?php echo e($user['email'] ?? ''); ?>"></div>
                            <div class="col-md-6 mb-15"><label class="checkout__input--label">Topic</label>
                                <select class="w-100" name="category">
                                    <?php foreach (FEEDBACK_CATEGORIES as $k => $lbl): ?><option value="<?php echo e($k); ?>"><?php echo e($lbl); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-15">
                            <label class="checkout__input--label">Overall rating</label>
                            <div>
                                <input type="hidden" name="rating" value="0">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="js-star" data-value="<?php echo $i; ?>" style="cursor:pointer;display:inline-block"><svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 10.105 9.732"><path d="M9.837,3.5,6.73,3.039,5.338.179a.335.335,0,0,0-.571,0L3.375,3.039.268,3.5a.3.3,0,0,0-.178.514L2.347,6.242,1.813,9.4a.314.314,0,0,0,.464.316L5.052,8.232,7.827,9.712A.314.314,0,0,0,8.292,9.4L7.758,6.242l2.257-2.231A.3.3,0,0,0,9.837,3.5Z" transform="translate(0 -0.018)" fill="currentColor"></path></svg></span>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="mb-15"><label class="checkout__input--label">Your feedback</label><textarea class="checkout__input--field w-100" name="message" data-label="Your feedback" rows="5" minlength="10" placeholder="What went well? What could be better?" required></textarea></div>
                        <button class="btn btn-primary" type="submit">Send Feedback</button>
                    </form>
                </div>

                <aside>
                    <div class="pg__card mb-30">
                        <h3 class="pg__aside--title">What happens next</h3>
                        <ul class="pg__ticks" style="margin:0">
                            <li>Every message is read by the store team</li>
                            <li>If you leave a number, we call back within a working day</li>
                            <li>Issues with an order are escalated the same day</li>
                        </ul>
                    </div>
                    <div class="pg__card">
                        <h3 class="pg__aside--title">Need an answer now?</h3>
                        <p style="font-size:1.4rem;line-height:2.4rem">For anything urgent &mdash; an order, a repair, a delivery &mdash; calling the store is fastest.</p>
                        <p class="pg__info--value" style="margin-bottom:1.6rem"><?php echo site_phone_links(); ?></p>
                        <a class="btn btn-outline-primary" href="contact.php">Contact Page</a>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>
<?php include 'includes/footer.php'; ?>
