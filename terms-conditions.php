<?php
require_once 'includes/config.php';
$pageMetaTitle = SITE_NAME . ' - Terms & Conditions';
$pageMetaDesc = 'Terms and Conditions for shopping with ' . SITE_NAME . '.';
$pageTitle = 'Terms & Conditions';
$breadcrumbs = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Terms & Conditions', 'url' => '']];
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
                            <p class="terms__content--desc">Welcome to <strong><?php echo SITE_NAME; ?></strong>. By shopping with us—whether in our store, on our website, or through our official social media pages—you agree to the terms below.</p>
                        </div>

                        <div class="terms__list--wrapper">
                            <!-- Section 1 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">1. About These Terms</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>These terms apply to all purchases of gold, silver jewellery from us.</li>
                                    <li>By placing an order, you agree to follow these terms.</li>
                                    <li>We may update these terms from time to time.</li>
                                </ul>
                            </div>

                            <!-- Section 2 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">2. Who Can Purchase</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>You must be 18 years or older to buy jewellery from us.</li>
                                    <li>Please ensure that the details you provide are correct.</li>
                                </ul>
                            </div>

                            <!-- Section 3 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">3. Jewellery & Pricing</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>All jewellery is subject to availability.</li>
                                    <li>Prices depend on current gold/silver/diamond rates, making charges, and applicable taxes.</li>
                                    <li>Prices may change without prior notice due to market fluctuations.</li>
                                    <li>Jewellery images are for reference; slight variations in design or weight may occur.</li>
                                    <li>If a pricing or listing mistake happens, we reserve the right to cancel the order and refund the amount paid.</li>
                                </ul>
                            </div>

                            <!-- Section 4 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">4. Orders & Payments</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Orders can be placed in-store, on our website, or via our verified Instagram and Facebook pages.</li>
                                    <li>Full payment is required at the time of ordering.</li>
                                    <li>We accept UPI, debit cards, credit cards, and net banking.</li>
                                    <li>Payment-related delays caused by banks or payment providers are beyond our control.</li>
                                </ul>
                            </div>

                            <!-- Section 5 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">5. Order Confirmation & Cancellation</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Orders are confirmed once payment is received.</li>
                                    <li>Customized, engraved, or made-to-order jewellery cannot be canceled or changed after confirmation.</li>
                                    <li>We may cancel an order if there is a stock issue or pricing error, and a full refund will be provided.</li>
                                </ul>
                            </div>

                            <!-- Section 6 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">6. Delivery</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Delivery timelines are estimates and may vary due to courier delays or other unforeseen reasons.</li>
                                    <li>Please refer to our Shipping Policy for more details.</li>
                                </ul>
                            </div>

                            <!-- Section 7 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">7. Returns & Refunds</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Returns and refunds are handled as per our Return & Refund Policy.</li>
                                    <li>Customized and personalized jewellery may not be eligible for return or refund.</li>
                                </ul>
                            </div>

                            <!-- Section 8 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">8. Our Responsibility</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>We are not responsible for damages caused by improper use, wear and tear, or third-party alterations.</li>
                                    <li>Our responsibility, if any, is limited to the value of the jewellery purchased.</li>
                                </ul>
                            </div>

                            <!-- Section 9 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">9. Content Ownership</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>All images, designs, logos, and text used by us belong to <strong><?php echo SITE_NAME; ?></strong>.</li>
                                    <li>Using them without permission is not allowed.</li>
                                </ul>
                            </div>

                            <!-- Section 10 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">10. Your Privacy</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Your personal information is handled safely as explained in our <a href="privacy-policy.php" style="color: var(--secondary-color); text-decoration: underline;">Privacy Policy</a>.</li>
                                </ul>
                            </div>

                            <!-- Section 11 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">11. Legal Information</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>These terms follow the laws of India.</li>
                                    <li>Any disputes will be handled by courts in Krishnagiri, Tamil Nadu.</li>
                                </ul>
                            </div>

                            <!-- Section 12 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">12. Unforeseen Events</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>We are not responsible for delays or failures caused by events beyond our control, such as natural disasters, government rules, or transport issues.</li>
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