<?php
/**
 * Image hero + inline breadcrumbs for inner pages (replaces page-title.php).
 * Set before include:
 *   $pageTitle    string   required
 *   $breadcrumbs  array    [['name'=>..,'url'=>..], ...]
 *   $heroEyebrow  string   small uppercase kicker (optional)
 *   $heroSub      string   one-line intro (optional)
 *   $heroImage    string   background image path (optional)
 */
$heroCrumbs  = $breadcrumbs ?? [['name' => 'Home', 'url' => 'index.php']];
$heroImgPath = $heroImage ?? 'assets/img/banner/haram.jpg';
?>
<section class="pg__hero" style="--pg-hero-image:url('<?php echo e(asset_url($heroImgPath)); ?>')">
    <div class="container">
        <div class="pg__hero--inner">
            <?php if (!empty($heroEyebrow)): ?>
                <span class="pg__hero--eyebrow"><?php echo e($heroEyebrow); ?></span>
            <?php endif; ?>
            <h1 class="pg__hero--title"><?php echo e($pageTitle ?? 'Page'); ?></h1>
            <?php if (!empty($heroSub)): ?>
                <p class="pg__hero--sub"><?php echo e($heroSub); ?></p>
            <?php endif; ?>
            <nav class="pg__hero--crumbs" aria-label="Breadcrumb">
                <?php foreach ($heroCrumbs as $i => $c): ?>
                    <?php if ($i > 0): ?>
                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"/></svg>
                    <?php endif; ?>
                    <?php if ($i < count($heroCrumbs) - 1 && !empty($c['url'])): ?>
                        <a href="<?php echo e($c['url']); ?>"><?php echo e($c['name']); ?></a>
                    <?php else: ?>
                        <span aria-current="page"><?php echo e($c['name']); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>
</section>
<?php
// don't leak hero settings into the next include on the same request
unset($heroEyebrow, $heroSub, $heroImage);
