<?php
require_once 'includes/config.php';
$pageMetaTitle = SITE_NAME . ' - Return & Refund Policy';
$pageMetaDesc = 'Return and Refund Policy for ' . SITE_NAME . '. Learn about our returns and exchange terms.';
$pageTitle = 'Return & Refund Policy';
$breadcrumbs = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Return & Refund Policy', 'url' => '']];
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
                            <p class="terms__content--desc" style="font-size: 1.6rem; line-height: 1.6;">At <strong><?php echo SITE_NAME; ?></strong>, customer satisfaction is important to us. This policy explains the conditions under which returns, exchanges, and refunds are accepted.</p>
                        </div>

                        <div class="terms__list--wrapper">
                            <!-- Section 1 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">1. Eligibility for Returns</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Returns or exchanges may be considered only for eligible jewellery items and subject to inspection.</li>
                                    <li>Requests must be raised within 7 days from the date of delivery or purchase.</li>
                                    <li>The jewellery must be unused, unworn, undamaged, and returned in its original condition along with the original invoice and packaging.</li>
                                </ul>
                            </div>

                            <!-- Section 2 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">2. Items Not Eligible for Return or Refund</h3>
                                <p class="mb-15" style="font-size: 1.6rem; color: var(--text-gray-color);">The following items cannot be returned or refunded:</p>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Customized, engraved, made-to-order, or personalized jewellery</li>
                                    <li>Jewellery altered in size or design at the customer’s request</li>
                                    <li>Products damaged due to misuse, wear and tear, or improper storage</li>
                                    <li>Gift cards, promotional items, or discounted sale items (if applicable)</li>
                                </ul>
                            </div>

                            <!-- Section 3 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">3. Return Process</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>To request a return or exchange, customers must contact us through our official phone number, email, or verified social media channels.</li>
                                    <li>Returned items will undergo quality and purity inspection before approval.</li>
                                    <li><?php echo SITE_NAME; ?> reserves the right to accept or reject a return based on inspection results.</li>
                                </ul>
                            </div>

                            <!-- Section 4 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">4. Exchanges</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Eligible products may be exchanged for another jewellery item of equal or higher value.</li>
                                    <li>Any price difference must be paid by the customer.</li>
                                    <li>Exchanges are subject to current market rates and availability.</li>
                                </ul>
                            </div>

                            <!-- Section 5 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">5. Refunds</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Approved refunds will be processed through the original mode of payment.</li>
                                    <li>Refunds may exclude making charges, taxes, or transaction fees where applicable.</li>
                                    <li>Refund processing may take 7–10 working days, depending on the bank or payment provider.</li>
                                </ul>
                            </div>

                            <!-- Section 6 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">6. Gold & Diamond Rate Variation</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Refunds and exchanges are calculated based on prevailing market rates at the time of return, not the original purchase rate.</li>
                                    <li>Making charges, wastage, and certification charges are non-refundable, unless otherwise stated.</li>
                                </ul>
                            </div>

                            <!-- Section 7 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">7. Shipping & Handling for Returns</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Customers are responsible for securely returning the jewellery unless the return is due to an error on our part.</li>
                                    <li>We are not responsible for loss or damage during return transit.</li>
                                </ul>
                            </div>

                            <!-- Section 8 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">8. Store Purchases</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Return and exchange terms for in-store purchases may vary and will be communicated at the time of sale.</li>
                                    <li>Certain store purchases may qualify only for exchange, not refund.</li>
                                </ul>
                            </div>

                            <!-- Section 9 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">9. Policy Updates</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>This policy may be updated or modified at any time without prior notice.</li>
                                    <li>The latest policy will be available on our website or official platforms.</li>
                                </ul>
                            </div>

                            <!-- Section 10 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">10. Contact Us</h3>
                                <p class="mb-10" style="font-size: 1.6rem; color: var(--text-gray-color);">For return or refund requests, please contact:</p>
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
