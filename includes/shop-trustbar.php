<?php
/**
 * Reassurance strip shown under the shop grid.
 */
$shopTrust = [
    [
        'title' => 'Free Shipping',
        'desc'  => 'On orders above &#8377;50,000',
        'icon'  => '<path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
    ],
    [
        'title' => 'Certified Jewellery',
        'desc'  => '100% Hallmarked',
        'icon'  => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
    ],
    [
        'title' => 'Easy Returns',
        'desc'  => '7 Days Return Policy',
        'icon'  => '<path d="M3 12a9 9 0 0 1 15.5-6.2L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15.5 6.2L3 16"/><path d="M3 21v-5h5"/>',
    ],
    [
        'title' => 'Secure Payments',
        'desc'  => '100% Safe &amp; Secure',
        'icon'  => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    ],
];
?>
<section class="shop__trust">
    <div class="container">
        <ul class="shop__trust--list">
            <?php foreach ($shopTrust as $t): ?>
                <li class="shop__trust--item">
                    <span class="shop__trust--icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><?php echo $t['icon']; ?></svg>
                    </span>
                    <span class="shop__trust--text">
                        <strong><?php echo $t['title']; ?></strong>
                        <small><?php echo $t['desc']; ?></small>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
