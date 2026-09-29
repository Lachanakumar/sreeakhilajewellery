<!-- Start footer section -->
<footer class="footer__section bg__black color-scheme-2">
    <div class="container">
        <div class="main__footer row justify-content-center">
            <div class="footer__widget footer__widget--width col-md-4 mb-3">
                <h2 class="footer__widget--title text-ofwhite h3">About Us
                    <button class="footer__widget--button" aria-label="footer widget button">
                        <svg class="footer__widget--title__arrowdown--icon" xmlns="http://www.w3.org/2000/svg" width="12.355" height="8.394" viewBox="0 0 10.355 6.394">
                            <path d="M15.138,8.59l-3.961,3.952L7.217,8.59,6,9.807l5.178,5.178,5.178-5.178Z" transform="translate(-6 -8.59)" fill="currentColor"></path>
                        </svg>
                    </button>
                </h2>
                <div class="footer__widget--inner">
                    <p class="footer__widget--desc text-ofwhite mb-20">Welcome to <?php echo SITE_NAME; ?>. We offer exquisite gold, silver, and diamond jewelry at the best prices. Your satisfaction is our priority.</p>
                    <?php $footDigi = digigold_config();
                    if ($footDigi['enabled'] && ($footDigi['ios'] || $footDigi['android'])): ?>
                        <div class="footer__apps mb-20">
                            <h3 class="social__title text-ofwhite h4 mb-15">Get the Digi Gold App</h3>
                            <div class="d-flex flex-wrap" style="gap:10px">
                                <?php if ($footDigi['android']): ?><a class="app__badge app__badge--sm" href="<?php echo htmlspecialchars($footDigi['android']); ?>" target="_blank" rel="noopener"><svg width="18" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M3 20.5V3.5c0-.6.3-1.1.8-1.4l10.9 9.9L3.8 21.9c-.5-.3-.8-.8-.8-1.4zM16.8 14.3l2.7 2.4c.8.5.8 1.7 0 2.2l-3.3 1.9-3.2-2.9 3.8-3.6zM5.3 2l10.1 5.8-2.9 2.7L5.3 2zm10.1 14.4L5.3 22l7.2-8.5 2.9 2.9z" />
                                        </svg><span><small>Get it on</small><br>Google Play</span></a><?php endif; ?>
                                <?php if ($footDigi['ios']): ?><a class="app__badge app__badge--sm" href="<?php echo htmlspecialchars($footDigi['ios']); ?>" target="_blank" rel="noopener"><svg width="18" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M16.4 12.9c0-2.6 2.1-3.8 2.2-3.9-1.2-1.8-3.1-2-3.7-2-1.6-.2-3.1.9-3.9.9s-2-.9-3.4-.9c-1.7 0-3.3 1-4.2 2.6-1.8 3.1-.5 7.7 1.3 10.2.9 1.2 1.9 2.6 3.3 2.5 1.3-.1 1.8-.9 3.4-.9s2 .9 3.4.8c1.4 0 2.3-1.2 3.2-2.5.6-.9 1-1.7 1.4-2.7-3.7-1.4-2.7-5.6-2.7-5.7zM13.9 4.2c.7-.9 1.2-2.1 1.1-3.3-1 0-2.3.7-3 1.5-.7.8-1.3 2-1.1 3.2 1.1.1 2.3-.6 3-1.4z" />
                                        </svg><span><small>Download on the</small><br>App Store</span></a><?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="footer__social">
                        <h3 class="social__title text-ofwhite h4 mb-15">Follow Us</h3>
                        <ul class="social__shear d-flex">
                            <?php
                            /* Icons come from includes/social.php, the same list that builds the
                               fields in Admin -> Settings -> Social. Keeping one registry is what
                               stops the two drifting — the admin used to offer a YouTube URL that
                               no template rendered. A blank setting simply hides its icon. */
                            foreach (social_links() as $soc): ?>
                                <li class="social__shear--list">
                                    <a class="social__shear--list__icon" href="<?php echo e($soc['url']); ?>"
                                        target="_blank" rel="noopener noreferrer"
                                        aria-label="<?php echo e($soc['label']); ?>" title="<?php echo e($soc['label']); ?>"><?php echo $soc['svg']; ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-1"></div>
            <div class="footer__widget--menu__wrapper footer__widget--width col-md-3 mb-3">
                <div class="footer__widget">
                    <h2 class="footer__widget--title text-ofwhite h3">Quick Links
                        <button class="footer__widget--button" aria-label="footer widget button"><svg class="footer__widget--title__arrowdown--icon" xmlns="http://www.w3.org/2000/svg" width="12.355" height="8.394" viewBox="0 0 10.355 6.394">
                                <path d="M15.138,8.59l-3.961,3.952L7.217,8.59,6,9.807l5.178,5.178,5.178-5.178Z" transform="translate(-6 -8.59)" fill="currentColor"></path>
                            </svg></button>
                    </h2>
                    <ul class="footer__widget--menu footer__widget--inner">
                        <li class="footer__widget--menu__list"><a class="footer__widget--menu__text" href="index.php">Home</a></li>
                        <li class="footer__widget--menu__list"><a class="footer__widget--menu__text" href="products.php">Products</a></li>
                        <li class="footer__widget--menu__list"><a class="footer__widget--menu__text" href="schemes.php">Savings Schemes</a></li>
                        <li class="footer__widget--menu__list"><a class="footer__widget--menu__text" href="digigold.php">Digi Gold</a></li>
                        <li class="footer__widget--menu__list"><a class="footer__widget--menu__text" href="appointment.php">Book an Appointment</a></li>
                        <li class="footer__widget--menu__list"><a class="footer__widget--menu__text" href="feedback.php">Customer Feedback</a></li>
                        <li class="footer__widget--menu__list"><a class="footer__widget--menu__text" href="about.php">About Us</a></li>
                        <li class="footer__widget--menu__list"><a class="footer__widget--menu__text" href="contact.php">Contact Us</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer__widget col-md-3">
                <h2 class="footer__widget--title text-ofwhite h3">Information
                    <button class="footer__widget--button" aria-label="footer widget button"><svg class="footer__widget--title__arrowdown--icon" xmlns="http://www.w3.org/2000/svg" width="12.355" height="8.394" viewBox="0 0 10.355 6.394">
                            <path d="M15.138,8.59l-3.961,3.952L7.217,8.59,6,9.807l5.178,5.178,5.178-5.178Z" transform="translate(-6 -8.59)" fill="currentColor"></path>
                        </svg></button>
                </h2>
                <ul class="footer__widget--menu footer__widget--inner">
                    <li class="footer__widget--menu__list"><a class="footer__widget--menu__text" href="terms-conditions.php">Terms & Conditions</a></li>
                    <li class="footer__widget--menu__list"><a class="footer__widget--menu__text" href="privacy-policy.php">Privacy Policy</a></li>
                    <li class="footer__widget--menu__list"><a class="footer__widget--menu__text" href="return-refund-policy.php">Return & Refund Policy</a></li>
                    <li class="footer__widget--menu__list"><a class="footer__widget--menu__text" href="cancellation-policy.php">Cancellation Policy</a></li>
                    <li class="footer__widget--menu__list"><a class="footer__widget--menu__text" href="shipping-policy.php">Shipping Policy</a></li>

                </ul>
            </div>
        </div>
        <div class="footer__bottom d-flex justify-content-center align-items-center flex-wrap" style="gap:6px 18px">
            <p class="copyright__content text-ofwhite m-0">Copyright &copy; <?php echo date('Y'); ?> <a class="copyright__content--link" href="index.php" style='color: #fdec01'><?php echo SITE_NAME; ?></a>. All Rights Reserved.</p>
            <?php $vt = visit_totals(); ?>
            <span class="copyright__content text-ofwhite m-0" style="opacity:.7">&bull; Visitors: <?php echo number_format($vt['total']); ?> (<?php echo number_format($vt['today']); ?> today)</span>
        </div>
    </div>
</footer>
<!-- End footer section -->

<?php $qc = quick_contact();
if ($qc['enabled']): ?>
    <div class="quick__contact" aria-label="Quick contact">
        <button type="button" class="quick__contact--toggle" aria-label="Open contact options">
            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
        </button>
        <div class="quick__contact--menu">
            <?php if ($qc['whatsapp']): ?>
                <a class="quick__contact--btn qc-wa" href="<?php echo htmlspecialchars($qc['whatsapp']); ?>" target="_blank" rel="noopener" title="Chat on WhatsApp">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.46 1.32 4.96L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.9-4.45 9.9-9.91C21.95 6.45 17.5 2 12.04 2zm0 18.02h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.19 8.19 0 0 1-1.26-4.36c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.83 2.42a8.18 8.18 0 0 1 2.41 5.82c0 4.54-3.69 8.24-8.23 8.24zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.12-.16.25-.64.81-.79.98-.14.16-.29.18-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.13-.14.17-.25.25-.41.08-.16.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.43-.14 0-.31-.01-.47-.01-.16 0-.43.06-.66.31-.23.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.16 1.75 2.67 4.25 3.74.59.26 1.06.41 1.42.52.6.19 1.14.16 1.57.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.14-1.18-.06-.11-.22-.17-.47-.29z" />
                    </svg>
                    <span>WhatsApp</span>
                </a>
            <?php endif; ?>
            <?php if ($qc['call']): ?>
                <a class="quick__contact--btn qc-call" href="<?php echo htmlspecialchars($qc['call_href']); ?>" title="Call us">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    <span>Call <?php echo htmlspecialchars($qc['call']); ?></span>
                </a>
            <?php endif; ?>
            <?php if ($qc['instagram']): ?>
                <a class="quick__contact--btn qc-ig" href="<?php echo htmlspecialchars($qc['instagram']); ?>" target="_blank" rel="noopener" title="Instagram">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16zM12 0C8.74 0 8.33.01 7.05.07 5.78.13 4.9.33 4.14.63c-.79.3-1.46.72-2.12 1.38A5.87 5.87 0 0 0 .63 4.14C.33 4.9.13 5.78.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.27.26 2.15.56 2.91.3.79.72 1.46 1.38 2.12.66.66 1.33 1.08 2.12 1.38.76.3 1.64.5 2.91.56C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c1.27-.06 2.15-.26 2.91-.56a5.87 5.87 0 0 0 2.12-1.38 5.87 5.87 0 0 0 1.38-2.12c.3-.76.5-1.64.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.27-.26-2.15-.56-2.91a5.87 5.87 0 0 0-1.38-2.12A5.87 5.87 0 0 0 19.86.63c-.76-.3-1.64-.5-2.91-.56C15.67.01 15.26 0 12 0zm0 5.84A6.16 6.16 0 1 0 18.16 12 6.16 6.16 0 0 0 12 5.84zm0 10.16A4 4 0 1 1 16 12a4 4 0 0 1-4 4zm7.85-10.4a1.44 1.44 0 1 1-1.44-1.44 1.44 1.44 0 0 1 1.44 1.44z" />
                    </svg>
                    <span>Instagram</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Scroll top bar -->
<button class="color-scheme-2" id="scroll__top"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
        <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="48" d="M112 244l144-144 144 144M256 120v292" />
    </svg></button>

<!-- All Script JS Plugins here -->
<script src="assets/js/vendor/popper.js" defer="defer"></script>
<script src="assets/js/vendor/bootstrap.min.js" defer="defer"></script>
<script src="assets/js/plugins/swiper-bundle.min.js"></script>
<script src="assets/js/plugins/glightbox.min.js"></script>

<!-- Customscript js -->
<?php /* asset() appends the file's mtime — without it a deployed script.js
         change is invisible to anyone who has visited before */ ?>
<script src="<?php echo asset('assets/js/script.js'); ?>"></script>
<script src="<?php echo asset('assets/js/datepicker.js'); ?>"></script>
<!-- validate.js must precede shop.js: both bind submit on document, and an invalid
     form calls stopImmediatePropagation() so shop.js's AJAX handlers don't fire. -->
<script src="<?php echo asset('assets/js/validate.js'); ?>"></script>
<script src="<?php echo asset('assets/js/shop.js'); ?>"></script>
<?php foreach ((array) ($pageScripts ?? []) as $pageScriptSrc): ?>
    <script src="<?php echo (strpos($pageScriptSrc, '//') !== false) ? htmlspecialchars($pageScriptSrc) : asset($pageScriptSrc); ?>"></script>
<?php endforeach; ?>

</body>

</html>