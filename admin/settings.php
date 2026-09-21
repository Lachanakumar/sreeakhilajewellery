<?php
require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/../includes/theme.php';
require_once __DIR__ . '/includes/upload-image.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/social.php';
requireAdmin();

$db = getDB();

/* setting_key => [group, label, type]  (type: text | textarea | color | select:fonts | file) */
$fields = [
    'site_name'                => ['Company', 'Company / Site Name', 'text'],
    'site_tagline'             => ['Company', 'Tagline', 'text'],
    'site_email'               => ['Company', 'Contact Email', 'text'],
    'site_phone'               => ['Company', 'Contact Phone', 'text'],
    'site_address'             => ['Company', 'Address', 'textarea'],
    'site_logo'                => ['Company', 'Logo', 'file'],
    'site_favicon'             => ['Company', 'Favicon', 'file'],

    'theme_primary'            => ['Appearance', 'Primary / Accent colour', 'color'],
    'theme_secondary'          => ['Appearance', 'Secondary / Button colour', 'color'],
    'theme_font_heading'       => ['Appearance', 'Heading font', 'select:fonts'],
    'theme_font_body'          => ['Appearance', 'Body font', 'select:fonts'],

    /* Social rows are generated from social_networks() further down. */

    'currency_symbol'          => ['Store', 'Currency symbol (HTML entity ok)', 'text'],
    'shipping_flat_rate'       => ['Store', 'Flat shipping rate', 'text'],
    'free_shipping_threshold'  => ['Store', 'Free shipping threshold', 'text'],
    'tax_percent'              => ['Store', 'Tax / GST percent', 'text'],

    'quick_contact_enabled'    => ['Contact & App', 'Show floating quick-contact button', 'toggle'],
    'whatsapp_number'          => ['Contact & App', 'WhatsApp number (country code, digits only)', 'text'],
    'whatsapp_message'         => ['Contact & App', 'WhatsApp pre-filled message', 'text'],
    'call_number'              => ['Contact & App', 'Call button number', 'text'],
    'instagram_url'            => ['Contact & App', 'Instagram profile URL', 'text'],
    'appointment_times'        => ['Contact & App', 'Appointment time slots (comma separated)', 'textarea'],
    'show_metal_rates'         => ['Contact & App', 'Show gold/silver rate ticker in header', 'toggle'],
    'digigold_enabled'         => ['Contact & App', 'Show Digi Gold app section in footer', 'toggle'],
    'digigold_blurb'           => ['Contact & App', 'Digi Gold description', 'textarea'],
    'app_android_url'          => ['Contact & App', 'Android app (Play Store) URL', 'text'],
    'app_ios_url'              => ['Contact & App', 'iOS app (App Store) URL', 'text'],
];

/* Per-form notification recipients. Built from mail_modules() so adding a
   module there is all it takes to get a field here. Any of them may be left
   blank, in which case that form falls back to the Contact Email above. */
/* One field per social network, built from the same registry the footer
   renders from (includes/social.php). Adding a network there gives you the
   settings field and the footer icon together, so they cannot drift. */
foreach (social_networks() as $socKey => $socMeta) {
    $fields['social_' . $socKey] = ['Social', $socMeta['field'], 'text'];
}

/* Mail transport. 'mail' hands off to the local relay (nothing listening on a
   stock XAMPP box); 'smtp' talks to a real server via includes/smtp.php. */
$fields['mail_transport']   = ['Mail Server', 'How to send mail', 'select:transport'];
$fields['smtp_host']        = ['Mail Server', 'SMTP host', 'text'];
$fields['smtp_port']        = ['Mail Server', 'SMTP port', 'text'];
$fields['smtp_encryption']  = ['Mail Server', 'Encryption', 'select:encryption'];
$fields['smtp_auth']        = ['Mail Server', 'Server requires a login', 'toggle'];
$fields['smtp_username']    = ['Mail Server', 'SMTP username', 'text'];
$fields['smtp_password']    = ['Mail Server', 'SMTP password', 'password'];
$fields['notify_from_name'] = ['Mail Server', 'Send FROM name (blank = site name)', 'text'];

$fields['notify_enabled']    = ['Notifications', 'Send email notifications', 'toggle'];
$fields['notify_email_from'] = ['Notifications', 'Send notifications FROM (blank = Contact Email)', 'text'];
foreach (mail_modules() as $modKey => $modLabel) {
    $fields['notify_email_' . $modKey] = [
        'Notifications',
        $modLabel,
        'text',
    ];
}

if (admin_post_ok('settings.php') && isset($_POST['send_test'])) {
    $testTo = trim((string) ($_POST['test_to'] ?? ''));
    $testTo = $testTo !== '' ? $testTo : (mail_recipients('contact')[0] ?? '');

    if (!filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
        flash_set('admin_err', 'Enter a valid address to send the test to.');
        redirect('settings.php');
    }

    $body = "This is a test message from your website's admin panel.

"
          . 'Transport: ' . mail_transport() . "
"
          . 'From: ' . mail_from_address() . "
"
          . 'Sent: ' . date('d M Y \a	 g:i A') . "

"
          . "If you are reading this, notification email is working.
";

    [$ok, $err] = mail_deliver([$testTo], 'Test email from ' . (SITE_NAME !== '' ? SITE_NAME : 'your website'), $body, '', 20);
    mail_log('test', [$testTo], 'Test email', $body, ($ok ? 'sent' : 'failed') . ' via ' . mail_transport() . ($err !== '' ? ' — ' . $err : ''));

    if ($ok) {
        flash_set('admin_ok', 'Test email sent to ' . $testTo . '. Check the inbox (and the spam folder).');
    } else {
        flash_set('admin_err', 'Test email failed: ' . $err);
    }
    redirect('settings.php');
}

/* Only a genuine submission of the settings form may write.
   Toggles are the reason this matters: an unchecked checkbox posts nothing,
   so the loop below has to treat "absent" as "off". That is right for the
   real form, but it means ANY other POST landing here — a partial request, a
   second form on the page, a tool hitting the URL — silently switches off
   every toggle on the page. The marker input makes the difference explicit. */
if (admin_post_ok('settings.php') && !empty($_POST['settings_form'])) {
    $set = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');

    foreach ($fields as $key => [$group, $label, $type]) {
        if ($type === 'file') {
            [$ok, $path] = admin_upload_image($key, 'branding');
            if (!$ok) {
                flash_set('admin_err', $label . ': ' . $path);
                redirect('settings.php');
            }
            if ($path) {
                $old = cfg($key);
                if ($old && strpos($old, 'uploads/') === 0) {
                    admin_delete_upload($old);
                }
                $set->execute([$key, $path]);
            } elseif (!empty($_POST['clear_' . $key])) {
                $set->execute([$key, '']);
            }
            continue;
        }
        if ($type === 'toggle') {
            $set->execute([$key, empty($_POST[$key]) ? '0' : '1']);
            continue;
        }
        if ($type === 'password') {
            // The field renders empty on purpose (the stored secret is never
            // echoed into HTML), so an empty post means "leave it alone".
            $posted = mail_normalise_password($_POST[$key] ?? '');
            if ($posted !== '') {
                $set->execute([$key, $posted]);
            } elseif (!empty($_POST['clear_' . $key])) {
                $set->execute([$key, '']);
            }
            continue;
        }
        if (array_key_exists($key, $_POST)) {
            $set->execute([$key, trim((string) $_POST[$key])]);
        }
    }
    flash_set('admin_ok', 'Settings saved. (Storefront picks the new theme up on next page load.)');
    redirect('settings.php');
}

// reload fresh values
$current = [];
foreach ($db->query('SELECT setting_key, setting_value FROM settings') as $r) {
    $current[$r['setting_key']] = $r['setting_value'];
}
$val = fn($k, $d = '') => $current[$k] ?? $d;
$fontOptions = theme_fonts();

// group the fields
$grouped = [];
foreach ($fields as $key => $meta) {
    $grouped[$meta[0]][$key] = $meta;
}

$adminPageTitle = 'Settings';
$adminNav = 'settings';
require __DIR__ . '/includes/layout.php';
admin_layout_start();
admin_page_head('Settings', 'Company details, theme, contact and store options.', '<a class="btn btn--ghost" href="../index.php" target="_blank" rel="noopener">View storefront &#8599;</a>');

/* The storefront holds no hardcoded company details, so an empty field here
   shows up as a blank on the live site. Say so rather than letting it pass. */
$identityGaps = site_identity_gaps();
if ($identityGaps) {
    admin_warn('Not yet filled in, and blank on the storefront: '
        . implode(', ', $identityGaps) . '.');
}

// one-line explainer under each group heading, matching the product form
$groupNotes = [
    'Company'      => 'Your store name, logo and the details shown to customers.',
    'Appearance'   => 'These colours and fonts drive the storefront theme and its Bootstrap components.',
    'Social'       => 'Links used in the footer and the floating contact widget.',
    'Store'        => 'Currency, shipping and tax values used across the cart and checkout.',
    'Mail Server'  => 'How outgoing mail actually leaves the server. Choose SMTP and fill these in, then use Send test email to confirm before relying on it. For Gmail the password is a 16-character App Password from myaccount.google.com/apppasswords (2-Step Verification must be on first) - your normal Gmail password will not work. Spaces in it are fine.',
    'Notifications' => 'Where each form on the site sends its alert. Leave one blank to use the Contact Email. Several addresses can be separated by commas.',
    'Contact & App' => 'WhatsApp, calls, appointment slots and the Digi Gold app links.',
];
$groupNum = 0;
?>
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
    <input type="hidden" name="settings_form" value="1">

    <?php foreach ($grouped as $groupName => $groupFields): $groupNum++; ?>
        <div class="admin__card fsec">
            <div class="fsec__head">
                <span class="fsec__num"><?php echo $groupNum; ?></span>
                <div><h3><?php echo e($groupName); ?></h3>
                    <?php if (isset($groupNotes[$groupName])): ?><p><?php echo e($groupNotes[$groupName]); ?></p><?php endif; ?>
                </div>
            </div>

            <div class="row2">
            <?php foreach ($groupFields as $key => [$g, $label, $type]): ?>
                <div class="field">
                    <label><?php echo e($label); ?></label>
                    <?php if ($type === 'textarea'): ?>
                        <textarea name="<?php echo $key; ?>"><?php echo e($val($key)); ?></textarea>

                    <?php elseif ($type === 'color'): ?>
                        <span style="display:flex;gap:10px;align-items:center">
                            <input type="color" name="<?php echo $key; ?>" value="<?php echo e($val($key) ?: ($key === 'theme_primary' ? '#b98f3e' : '#1f1b16')); ?>" style="width:56px;height:40px;padding:2px">
                            <input type="text" value="<?php echo e($val($key)); ?>" oninput="this.previousElementSibling.value=this.value" placeholder="#rrggbb" style="flex:1">
                        </span>

                    <?php elseif ($type === 'select:fonts'): ?>
                        <select name="<?php echo $key; ?>">
                            <?php $sel = $val($key) ?: ($key === 'theme_font_heading' ? 'Cormorant Garamond' : 'Jost'); ?>
                            <?php foreach ($fontOptions as $fk => $fmeta): ?>
                                <option value="<?php echo e($fk); ?>" <?php echo $sel === $fk ? 'selected' : ''; ?> style="font-family:<?php echo e($fmeta[1]); ?>"><?php echo e($fmeta[0]); ?></option>
                            <?php endforeach; ?>
                        </select>

                    <?php elseif ($type === 'toggle'): ?>
                        <label style="font-weight:400;display:flex;align-items:center;gap:8px">
                            <input type="checkbox" name="<?php echo $key; ?>" value="1" <?php echo ($val($key, '1') === '1') ? 'checked' : ''; ?>> Enabled
                        </label>

                    <?php elseif ($type === 'file'): ?>
                        <?php if ($val($key)): ?>
                            <div style="margin-bottom:8px"><img src="../<?php echo e($val($key)); ?>" alt="" style="max-height:56px;background:#222;padding:6px;border-radius:6px"></div>
                            <label style="font-weight:400"><input type="checkbox" name="clear_<?php echo $key; ?>" value="1"> remove current</label>
                        <?php endif; ?>
                        <input type="file" name="<?php echo $key; ?>" accept="image/png,image/jpeg,image/webp,image/x-icon">

                    <?php elseif ($type === 'select:transport'): ?>
                        <?php $selT = $val($key, 'mail'); ?>
                        <select name="<?php echo $key; ?>">
                            <option value="mail" <?php echo $selT !== 'smtp' ? 'selected' : ''; ?>>PHP mail() &mdash; local relay</option>
                            <option value="smtp" <?php echo $selT === 'smtp' ? 'selected' : ''; ?>>SMTP server</option>
                        </select>

                    <?php elseif ($type === 'select:encryption'): ?>
                        <?php $selE = $val($key, 'tls'); ?>
                        <select name="<?php echo $key; ?>">
                            <option value="tls" <?php echo $selE === 'tls' ? 'selected' : ''; ?>>STARTTLS (port 587)</option>
                            <option value="ssl" <?php echo $selE === 'ssl' ? 'selected' : ''; ?>>SSL / TLS (port 465)</option>
                            <option value="none" <?php echo $selE === 'none' ? 'selected' : ''; ?>>None (port 25)</option>
                        </select>

                    <?php elseif ($type === 'password'): ?>
                        <input type="password" name="<?php echo $key; ?>" value="" autocomplete="new-password"
                               placeholder="<?php echo $val($key) !== '' ? 'saved &mdash; leave blank to keep' : 'not set'; ?>">
                        <?php if ($val($key) !== ''): ?>
                            <label style="font-weight:400"><input type="checkbox" name="clear_<?php echo $key; ?>" value="1"> clear it</label>
                        <?php endif; ?>

                    <?php else: ?>
                        <input type="text" name="<?php echo $key; ?>" value="<?php echo e($val($key)); ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="admin__card">
        <button class="btn" type="submit">Save All Settings</button>
        <a class="btn btn--ghost" href="../index.php" target="_blank">View storefront &#8599;</a>
    </div>
</form>

<!-- Its own <form>: nesting it inside the settings form would be invalid
     HTML and browsers drop the inner one. -->
<form method="post" class="admin__card">
    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
    <h3 style="margin-top:0">Test the mail settings</h3>
    <p style="margin-bottom:12px">Save your changes first, then send a test. The result below is the mail
       server's own answer &mdash; unlike the storefront forms, which log a failure quietly rather than
       showing customers an error.</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input type="email" name="test_to" placeholder="<?php echo e(mail_recipients('contact')[0] ?? 'you@example.com'); ?>" style="flex:1;min-width:240px">
        <button class="btn" type="submit" name="send_test" value="1">Send test email</button>
    </div>
</form>
<?php admin_layout_end();
