<?php
/**
 * Inline breadcrumb row for the shop pages (replaces the full-width title band).
 * Expects $breadcrumbs (as set for includes/page-title.php).
 */
$crumbs = $breadcrumbs ?? [['name' => 'Home', 'url' => 'index.php']];
?>
<nav class="shop__crumbs" aria-label="Breadcrumb">
    <?php foreach ($crumbs as $i => $c): ?>
        <?php if ($i > 0): ?>
            <svg class="shop__crumbs--sep" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"/></svg>
        <?php endif; ?>
        <?php if ($i < count($crumbs) - 1 && !empty($c['url'])): ?>
            <a href="<?php echo e($c['url']); ?>"><?php echo e($c['name']); ?></a>
        <?php else: ?>
            <span aria-current="page"><?php echo e($c['name']); ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
