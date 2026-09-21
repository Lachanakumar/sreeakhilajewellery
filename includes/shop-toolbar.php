<?php
/**
 * Shop results toolbar. Set $listing before include. Optional $toolbarSorts.
 */
$sorts = $toolbarSorts ?? [
    'latest'       => 'Newest',
    'best_selling' => 'Best Selling',
    'price_low'    => 'Price: Low to High',
    'price_high'   => 'Price: High to Low',
    'name_asc'     => 'Name: A - Z',
];
$currentSort = $_GET['sort'] ?? 'latest';

$total = (int) $listing['total'];
$page  = (int) ($listing['pagination']['current_page'] ?? 1);
$from  = $total ? (($page - 1) * SHOP_PER_PAGE) + 1 : 0;
$to    = min($page * SHOP_PER_PAGE, $total);
?>
<div class="shop__toolbar">
    <button type="button" class="shop__toolbar--filters" data-filter-toggle>
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
        Filters
    </button>

    <p class="product__showing--count" data-shop-count>
        Showing <?php echo $from; ?>&ndash;<?php echo $to; ?> of <?php echo $total; ?> products
    </p>

    <div class="shop__toolbar--right">
        <label class="shop__toolbar--sort">
            <span>Sort by</span>
            <select data-sort-proxy>
                <?php foreach ($sorts as $val => $label): ?>
                    <option value="<?php echo e($val); ?>" <?php echo $currentSort === $val ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="shop__view" role="group" aria-label="Layout">
            <button type="button" class="shop__view--btn is-active" data-shop-view="grid" aria-label="Grid view">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><rect x="3" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5"/></svg>
            </button>
            <button type="button" class="shop__view--btn" data-shop-view="list" aria-label="List view">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>
</div>
