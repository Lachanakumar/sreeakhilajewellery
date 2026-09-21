<?php
require_once 'includes/config.php';
$pageMetaTitle = SITE_NAME . ' - FAQ';
$pageMetaDesc = 'Frequently Asked Questions about ' . SITE_NAME . ' products, shipping, and more.';
$pageTitle = 'Frequently Asked Questions';
$breadcrumbs = [['name'=>'Home','url'=>'index.php'],['name'=>'FAQ','url'=>'']];
include 'includes/header.php';
?>

<main class="main__content_wrapper">
    <?php include 'includes/page-title.php'; ?>

    <section class="faq__section section--padding">
        <div class="container">
            <div class="row row-cols-1">
                <div class="col">
                    <div class="faq__content">
                        <div class="faq__content--inner">
                            <h2 class="faq__content--title">General Questions</h2>
                            <div class="faq__accordion">
                                <div class="faq__accordion--items">
                                    <h3 class="faq__accordion--title">Do you offer authentic gold and diamonds?</h3>
                                    <div class="faq__accordion--content">
                                        <p>Yes, all our gold jewelry is hallmark certified, and our diamonds are GIA or IGI certified. We guarantee the authenticity of every piece we sell.</p>
                                    </div>
                                </div>
                                <div class="faq__accordion--items">
                                    <h3 class="faq__accordion--title">Can I customize my jewelry?</h3>
                                    <div class="faq__accordion--content">
                                        <p>Absolutely! We offer customization services for engagement rings, pendants, and other jewelry. Contact us via our contact form or visit our store for more details.</p>
                                    </div>
                                </div>
                                <div class="faq__accordion--items">
                                    <h3 class="faq__accordion--title">What is your return policy?</h3>
                                    <div class="faq__accordion--content">
                                        <p>We offer a 14-day return policy for unused items in their original packaging. Please note that customized items are not eligible for return.</p>
                                    </div>
                                </div>
                                <div class="faq__accordion--items">
                                    <h3 class="faq__accordion--title">Do you provide international shipping?</h3>
                                    <div class="faq__accordion--content">
                                        <p>Yes, we ship our jewelry worldwide. Shipping costs and delivery times vary by location.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
