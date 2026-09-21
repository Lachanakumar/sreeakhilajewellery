<?php
require_once 'includes/config.php';
$pageMetaTitle = SITE_NAME . ' - 404 Page Not Found';
$pageMetaDesc = 'The page you are looking for does not exist.';
$pageTitle = '404 Error';
$breadcrumbs = [['name'=>'Home','url'=>'index.php'],['name'=>'404 Error','url'=>'']];
include 'includes/header.php';
?>

<main class="main__content_wrapper">
    <!-- Start error section -->
    <section class="error__section section--padding" style="background: var(--light-color); padding: 100px 0; overflow: hidden;">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <div class="error__content" style="position: relative; padding: 40px 0;">
                        <h1 class="error__title" style="font-size: clamp(8rem, 20vw, 15rem); font-weight: 900; color: rgba(65, 19, 17, 0.05); line-height: 1; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); z-index: 1; pointer-events: none; white-space: nowrap;">404</h1>
                        
                        <div class="error__inner" style="position: relative; z-index: 2;">
                            <div class="error__img--wrapper mb-40">
                                <img class="error__content--img" src="assets/img/other/404-thumb.png" alt="error-img" style="max-width: 280px; height: auto;">
                            </div>
                            <h2 class="error__content--title mb-20" style="font-size: clamp(2.4rem, 5vw, 3.6rem);">Oops! Page Not Found</h2>
                            <p class="error__content--desc mb-40" style="font-size: 1.8rem; color: var(--text-gray-color); max-width: 500px; margin: 0 auto 40px;">The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
                            <div class="error__button">
                                <a class="error__content--btn primary__btn" href="index.php" style="padding: 18px 50px; border-radius: 5px; height: auto; line-height: 1; font-size: 1.8rem;">Back To Homepage</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- End error section -->
</main>

<?php include 'includes/footer.php'; ?>


