<?php
/**
 * Shared shell for the account pages (login, register, forgot/reset password).
 * Renders a split panel: a brand/benefits aside beside the form card.
 * Usage: auth_page_open('Title', 'optional sub'); ... form ... auth_page_close();
 */

/** Benefit lines shown in the aside. Kept here so all four pages stay in sync. */
function auth_page_benefits() {
    return [
        'Track orders and savings-scheme instalments',
        'Keep your wishlist across every device',
        'Member-only offers and birthday rewards',
    ];
}

function auth_page_open($heading, $sub = '') {
    ?>
    <section class="auth__section">
        <div class="container">
            <div class="auth__panel">

                <aside class="auth__aside">
                    <div class="auth__aside--inner">
                        <span class="auth__aside--eyebrow"><?php echo e(SITE_NAME); ?></span>
                        <h2 class="auth__aside--title">Your jewellery box,<br>now online.</h2>
                        <ul class="auth__aside--list">
                            <?php foreach (auth_page_benefits() as $line): ?>
                                <li><?php echo e($line); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="auth__aside--foot">BIS hallmarked &middot; Secure checkout &middot; Since 1985</p>
                    </div>
                </aside>

                <div class="auth__form">
                    <div class="auth__form--head">
                        <h1 class="auth__form--title"><?php echo e($heading); ?></h1>
                        <?php if ($sub): ?><p class="auth__form--sub"><?php echo $sub; ?></p><?php endif; ?>
                    </div>
    <?php
}

function auth_page_close() {
    ?>
                </div><!-- /.auth__form -->
            </div><!-- /.auth__panel -->
        </div>
    </section>
    <script>
    /* Show/hide toggle on every password field in the auth card. Added here rather
       than in the markup so all four auth pages get it without touching their forms. */
    (function () {
        var fields = document.querySelectorAll('.auth__form input[type="password"]');
        Array.prototype.forEach.call(fields, function (input) {
            var group = input.parentNode;

            var wrap = document.createElement('div');
            wrap.className = 'auth__pw';
            group.insertBefore(wrap, input);
            wrap.appendChild(input);

            /* The toggle is centred on .auth__pw. Without this, validate.js
               appends its message inside that wrapper (the input's new parent),
               the wrapper grows by the height of the message, and "Show" slides
               down off the middle of the box. Naming the outer group as the
               error anchor keeps the message below the field and the wrapper
               exactly as tall as the input. */
            if (group && group.setAttribute) {
                group.setAttribute('data-error-anchor', '');
            }

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'auth__pw--toggle';
            btn.textContent = 'Show';
            btn.setAttribute('aria-label', 'Show password');
            btn.addEventListener('click', function () {
                var hidden = input.type === 'password';
                input.type = hidden ? 'text' : 'password';
                btn.textContent = hidden ? 'Hide' : 'Show';
                btn.setAttribute('aria-label', (hidden ? 'Hide' : 'Show') + ' password');
            });
            wrap.appendChild(btn);
        });
    })();
    </script>
    <?php
}

function auth_error_box(array $errors) {
    if (!$errors) return;
    echo '<div class="auth__alert is-error">' . implode('<br>', array_map('e', $errors)) . '</div>';
}
