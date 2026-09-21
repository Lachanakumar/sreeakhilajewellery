<?php
/**
 * Slim breadcrumb bar (alternate to the full-bleed image hero).
 *
 * Set before including:
 *   $pageTitle    string  heading text
 *   $breadcrumbs  array   [['name'=>..,'url'=>..], ...]  last item = current
 *   $pageHeading  bool    false to render the trail only — use it when the page
 *                         already prints its own <h1> (product / order detail),
 *                         so there is never a second <h1> on the page.
 */
if (!isset($pageTitle)) { $pageTitle = 'Page'; }
if (!isset($breadcrumbs)) {
    $breadcrumbs = [['name' => 'Home', 'url' => 'index.php'], ['name' => $pageTitle, 'url' => '']];
}
$showHeading = !isset($pageHeading) || $pageHeading !== false;
$lastCrumb   = count($breadcrumbs) - 1;
?>
<!-- Start breadcrumb bar -->
<section class="pgcrumb<?php echo $showHeading ? '' : ' pgcrumb--bare'; ?>">
    <div class="container">
        <nav class="pgcrumb__nav" aria-label="Breadcrumb">
            <ol class="pgcrumb__list">
                <?php foreach ($breadcrumbs as $i => $crumb): ?>
                    <li class="pgcrumb__item">
                        <?php if ($i < $lastCrumb && !empty($crumb['url'])): ?>
                            <a href="<?php echo htmlspecialchars($crumb['url']); ?>"><?php echo htmlspecialchars($crumb['name']); ?></a>
                            <svg class="pgcrumb__sep" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                        <?php else: ?>
                            <span aria-current="page"><?php echo htmlspecialchars($crumb['name']); ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
        <?php if ($showHeading): ?>
            <h1 class="pgcrumb__title"><?php echo htmlspecialchars($pageTitle); ?></h1>
        <?php endif; ?>
    </div>
</section>
<!-- End breadcrumb bar -->
