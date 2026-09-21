<?php
require_once 'includes/config.php';
$pageMetaTitle = SITE_NAME . ' - Cancellation Policy';
$pageMetaDesc = 'Cancellation Policy for ' . SITE_NAME . '. Learn about our order cancellation terms.';
$pageTitle = 'Cancellation Policy';
$breadcrumbs = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Cancellation Policy', 'url' => '']];
include 'includes/header.php';
?>
<style>
    .terms__section { font-size: 1.6rem; }
    .terms__content--desc { font-size: 1.6rem; line-height: 1.6; }
    .terms__list--title { font-size: 1.9rem; }
    .terms__list--subitem li {
        list-style: disc;
        line-height: normal;
        margin-bottom: 10px;
    }
</style>
<main class="main__content_wrapper">
    <?php include 'includes/page-title.php'; ?>

    <section class="terms__section section--padding">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="terms__content">
                        <div class="terms__content--header mb-40">
                            <p class="terms__content--desc" style="font-size: 1.6rem; line-height: 1.6;">At <strong><?php echo SITE_NAME; ?></strong>, we value transparency and customer satisfaction. This Cancellation Policy explains when and how an order may be canceled.</p>
                        </div>

                        <div class="terms__list--wrapper">
                            <!-- Section 1 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">1. Order Cancellation by Customer</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Orders may be canceled only before order confirmation or production begins, subject to approval.</li>
                                    <li>Customized, engraved, made-to-order, or size-altered jewellery cannot be canceled once the order is confirmed.</li>
                                    <li>To request a cancellation, customers must contact us immediately through our official phone number, email, or verified social media channels.</li>
                                </ul>
                            </div>

                            <!-- Section 2 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">2. Order Cancellation by <?php echo SITE_NAME; ?></h3>
                                <p class="mb-15" style="font-size: 1.6rem; color: var(--text-gray-color);">We reserve the right to cancel an order in the following situations:</p>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Pricing or product listing errors</li>
                                    <li>Stock unavailability or sourcing issues</li>
                                    <li>Payment failure or verification issues</li>
                                    <li>Suspected fraudulent activity</li>
                                    <li>Events beyond our control (force majeure)</li>
                                </ul>
                                <p class="mt-15" style="font-size: 1.6rem; color: var(--text-gray-color);">In such cases, any amount paid will be fully refunded.</p>
                            </div>

                            <!-- Section 3 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">3. Refund for Canceled Orders</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>If a cancellation is approved, the refund will be processed using the original payment method.</li>
                                    <li>Refund processing may take 7–10 working days, depending on your bank or payment provider.</li>
                                    <li>Transaction or gateway charges, if applicable, may be deducted where permitted by law.</li>
                                </ul>
                            </div>

                            <!-- Section 4 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">4. Partial Payments & Advances</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Any advance or booking amount paid for custom or special orders is non-refundable.</li>
                                    <li>For non-custom orders, advances may be refunded if cancellation is approved before order confirmation.</li>
                                </ul>
                            </div>

                            <!-- Section 5 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">5. No Cancellation After Dispatch</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Orders cannot be canceled once shipped or dispatched.</li>
                                    <li>In such cases, customers are requested to refer to our Return & Refund Policy, if applicable.</li>
                                </ul>
                            </div>

                            <!-- Section 6 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">6. Contact for Cancellation Requests</h3>
                                <p class="mb-10" style="font-size: 1.6rem; color: var(--text-gray-color);">All cancellation requests must be made through our official channels:</p>
                                <p style="font-size: 1.6rem; font-weight: 600; margin-bottom:2px"><?php echo SITE_NAME; ?></p>
                                <span style="font-size: 1.6rem;">Contact No : <?php echo site_phone_links(); ?></span><br/>
                                <a href="mailto:<?php echo SITE_EMAIL; ?>" style="font-size: 1.6rem;">Mail ID : <?php echo SITE_EMAIL; ?></a>
                            </div>

                            <!-- Section 7 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">7. Policy Updates</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>This Cancellation Policy may be updated at any time without prior notice.</li>
                                    <li>The latest version will always be available on our website or official platforms.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
