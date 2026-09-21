<?php
require_once 'includes/config.php';
$pageMetaTitle = SITE_NAME . ' - Privacy Policy';
$pageMetaDesc = 'Privacy Policy for ' . SITE_NAME . '. Your privacy is important to us.';
$pageTitle = 'Privacy Policy';
$breadcrumbs = [['name' => 'Home', 'url' => 'index.php'], ['name' => 'Privacy Policy', 'url' => '']];
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
                            <p class="terms__content--desc" style="font-size: 1.6rem; line-height: 1.6;">At <strong><?php echo SITE_NAME; ?></strong>, we respect your privacy and are committed to protecting your personal information. This Privacy Policy explains how we collect, use, store, and safeguard your data when you interact with us.</p>
                        </div>

                        <div class="terms__list--wrapper">
                            <!-- Section 1 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">1. Information We Collect</h3>
                                <p class="mb-15" style="font-size: 1.6rem; color: var(--text-gray-color);">We may collect the following information when you visit our store, website, or contact us through official channels:</p>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Name, phone number, email address, and billing details</li>
                                    <li>Delivery address for orders</li>
                                    <li>Payment-related information (processed securely via third-party gateways)</li>
                                    <li>Purchase history and order details</li>
                                    <li>Communication records (calls, messages, emails, social media DMs)</li>
                                    <li>Website usage data such as IP address, browser type, and pages visited</li>
                                </ul>
                            </div>

                            <!-- Section 2 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">2. How We Use Your Information</h3>
                                <p class="mb-15" style="font-size: 1.6rem; color: var(--text-gray-color);">Your information is used only for legitimate business purposes, including:</p>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Processing and delivering your jewellery orders</li>
                                    <li>Confirming payments and issuing invoices</li>
                                    <li>Responding to your queries and customer support requests</li>
                                    <li>Informing you about order status, offers, or promotions (if opted in)</li>
                                    <li>Improving our products, services, and website experience</li>
                                    <li>Complying with legal, tax, and regulatory requirements</li>
                                </ul>
                            </div>

                            <!-- Section 3 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">3. Payment Security</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>All payments are processed through secure and trusted third-party payment gateways.</li>
                                    <li>We do not store your card, UPI, or bank details on our servers.</li>
                                </ul>
                            </div>

                            <!-- Section 4 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">4. Sharing of Information</h3>
                                <p class="mb-15" style="font-size: 1.6rem; color: var(--text-gray-color);">We do not sell, rent, or trade your personal information. Your data may be shared only with:</p>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Courier and logistics partners for delivery</li>
                                    <li>Payment service providers for transaction processing</li>
                                    <li>Government or legal authorities when required by law</li>
                                    <li>All such parties are required to maintain confidentiality.</li>
                                </ul>
                            </div>

                            <!-- Section 5 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">5. Data Protection & Security</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>We take reasonable technical and organizational measures to protect your personal data from unauthorized access, misuse, or loss.</li>
                                    <li>Access to customer data is limited to authorized personnel only.</li>
                                </ul>
                            </div>

                            <!-- Section 6 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">6. Cookies & Website Tracking</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Our website may use cookies to enhance user experience and analyze site traffic.</li>
                                    <li>You may choose to disable cookies through your browser settings, though some features may not function properly.</li>
                                </ul>
                            </div>

                            <!-- Section 7 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">7. Your Rights</h3>
                                <p class="mb-15" style="font-size: 1.6rem; color: var(--text-gray-color);">You have the right to:</p>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Request access to your personal data</li>
                                    <li>Ask for correction of incorrect or outdated information</li>
                                    <li>Request deletion of your data, subject to legal and business requirements</li>
                                </ul>
                                <p class="mt-15" style="font-size: 1.6rem; color: var(--text-gray-color);">Requests can be made by contacting us using the details below.</p>
                            </div>

                            <!-- Section 8 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">8. Third-Party Links</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>Our website or social media pages may contain links to third-party platforms.</li>
                                    <li>We are not responsible for the privacy practices or content of those external sites.</li>
                                </ul>
                            </div>

                            <!-- Section 9 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">9. Policy Updates</h3>
                                <ul class="terms__list--subitem" style="list-style: disc; padding-left: 30px; line-height: 1.8; font-size: 1.6rem; color: var(--text-gray-color);">
                                    <li>This Privacy Policy may be updated periodically.</li>
                                    <li>Any changes will be effective immediately upon posting on our website or official platforms.</li>
                                </ul>
                            </div>

                            <!-- Section 10 -->
                            <div class="terms__list--item mb-40">
                                <h3 class="terms__list--title mb-15" style="font-size: 1.9rem; font-weight: 600;">10. Contact Us</h3>
                                <p class="mb-10" style="font-size: 1.6rem; color: var(--text-gray-color);">If you have any questions or concerns about this Privacy Policy or how your information is handled, please contact us:</p>
                                <p style="font-size: 1.6rem; font-weight: 600; margin-bottom:2px"><?php echo SITE_NAME; ?></p>
                                <span style="font-size: 1.6rem;">Contact No : <?php echo site_phone_links(); ?></span><br />
                                <a style="font-size: 1.6rem;" href=" mailto:<?php echo SITE_EMAIL; ?>">Mail ID : <?php echo SITE_EMAIL; ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>