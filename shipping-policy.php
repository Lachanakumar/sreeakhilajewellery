<?php
require_once 'includes/config.php';
$pageMetaTitle = SITE_NAME . ' - Shipping Policy';
$pageMetaDesc = 'Shipping Policy for ' . SITE_NAME . '. Learn about our delivery terms and coverage.';
$pageTitle = 'Shipping Policy';
$breadcrumbs = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Shipping Policy', 'url' => '']];
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
                            <p class="terms__content--desc" style="font-size: 1.6rem; line-height: 1.6;">At <strong><?php echo SITE_NAME; ?></strong>, we ensure safe, secure, and timely delivery of your jewellery. Please review our shipping policy below.</p>
                        </div>

                        <div class="terms__list--wrapper">
                            <!-- Section 1 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">1. Shipping Coverage</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>We currently ship orders within India only.</li>
                                    <li>Delivery is available to locations serviceable by our logistics partners.</li>
                                </ul>
                            </div>

                            <!-- Section 2 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">2. Order Processing Time</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Ready-stock jewellery is typically processed within 2-3 business days after order confirmation.</li>
                                    <li>Customized or made-to-order jewellery may require additional processing time, which will be communicated at the time of order.</li>
                                    <li>Orders are processed only after successful payment confirmation.</li>
                                </ul>
                            </div>

                            <!-- Section 3 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">3. Shipping Method</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>All jewellery is shipped through trusted and insured courier partners.</li>
                                    <li>Orders are securely packed to ensure protection during transit.</li>
                                </ul>
                            </div>

                            <!-- Section 4 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">4. Delivery Timeline</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Estimated delivery timelines vary based on location and courier availability.</li>
                                    <li>Delivery dates provided are estimates and may change due to weather conditions, courier delays, regulatory checks, or other unforeseen circumstances.</li>
                                </ul>
                            </div>

                            <!-- Section 5 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">5. Shipping Charges</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Shipping charges, if applicable, will be clearly communicated at the time of checkout or order confirmation.</li>
                                    <li>Any additional charges due to incorrect address details will be borne by the customer.</li>
                                </ul>
                            </div>

                            <!-- Section 6 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">6. Order Tracking</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Tracking details will be shared once the order is dispatched, where available.</li>
                                    <li>Customers are advised to track shipments and be available to receive the delivery.</li>
                                </ul>
                            </div>

                            <!-- Section 7 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">7. Delivery Conditions</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Jewellery will be delivered to the address provided at the time of order.</li>
                                    <li>A valid government-issued ID may be required at the time of delivery.</li>
                                    <li>Risk of loss passes to the customer upon successful delivery.</li>
                                </ul>
                            </div>

                            <!-- Section 8 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">8. Delays & Force Majeure</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>We are not responsible for delays caused by courier partners, natural calamities, government restrictions, or events beyond our control.</li>
                                </ul>
                            </div>

                            <!-- Section 9 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">9. Damaged or Missing Packages</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Any visible damage or missing items must be reported to us within 24 hours of delivery.</li>
                                    <li>Claims raised after this period may not be accepted.</li>
                                </ul>
                            </div>

                            <!-- Section 10 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">10. Contact Us</h3>
                                <p class="mb-10" style="font-size: 1.6rem; color: var(--text-gray-color);">For shipping-related queries, please contact:</p>
                                <p style="font-size: 1.6rem; font-weight: 600; margin-bottom:2px"><?php echo SITE_NAME; ?></p>
                                <span style="font-size: 1.6rem;">Contact No : <?php echo site_phone_links(); ?></span><br/>
                                <a href="mailto:<?php echo SITE_EMAIL; ?>" style="font-size: 1.6rem;">Mail ID : <?php echo SITE_EMAIL; ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
