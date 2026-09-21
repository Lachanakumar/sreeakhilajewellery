<?php
/**
 * Shop filter sidebar body. Set before include:
 *   $filterCategoryTree  array|null  category tree, or null to hide the category picker
 *   $filterBrands        array       brands list (may be [])
 *   $filterPriceRange    array       ['min'=>float,'max'=>float]
 *   $filterQuery         array       usually $_GET
 *   $filterSearchLabel   string      heading for the search box (optional)
 */
require_once __DIR__ . '/shop-listing.php';

$q         = $filterQuery ?? [];
$activeCat = $q['category'] ?? '';
$hasFilters = !empty($q['search']) || $activeCat !== '' || !empty($q['min_price']) || !empty($q['max_price'])
    || !empty($q['brand']) || !empty($q['on_sale']) || !empty($q['in_stock']) || !empty($q['featured']) || !empty($q['is_new']);
$searchLabel = $filterSearchLabel ?? 'Search';

$catCounts   = shop_category_counts();
$facetCounts = shop_facet_counts();

// Slider bounds, rounded out to whole thousands so the handles land on neat numbers.
$rangeMin = (int) floor(($filterPriceRange['min'] ?? 0) / 1000) * 1000;
$rangeMax = (int) ceil(($filterPriceRange['max'] ?? 0) / 1000) * 1000;
if ($rangeMax <= $rangeMin) {
    $rangeMax = $rangeMin + 1000;
}
$curMin = ($q['min_price'] ?? '') !== '' ? (int) $q['min_price'] : $rangeMin;
$curMax = ($q['max_price'] ?? '') !== '' ? (int) $q['max_price'] : $rangeMax;

/** Short money label for the slider readouts: 45000 -> "₹45,000". */
function shop_filter_money($n) {
    return '&#8377;' . inr_number_format((int) $n, 0);
}

// Preset price bands, filtered to the ones that actually fall inside the range.
$pricePresets = [
    ['label' => 'Below 50K', 'min' => '',       'max' => 50000],
    ['label' => '50K - 1L',  'min' => 50000,    'max' => 100000],
    ['label' => '1L - 3L',   'min' => 100000,   'max' => 300000],
    ['label' => 'Above 3L',  'min' => 300000,   'max' => ''],
];

$availability = [
    ['name' => 'in_stock', 'label' => 'In Stock',     'count' => $facetCounts['in_stock']],
    ['name' => 'on_sale',  'label' => 'On Sale',      'count' => $facetCounts['on_sale']],
    ['name' => 'featured', 'label' => 'Featured',     'count' => $facetCounts['featured']],
    ['name' => 'is_new',   'label' => 'New Arrivals', 'count' => $facetCounts['is_new']],
];

$clearHref = basename($_SERVER['SCRIPT_NAME']) . (isset($q['slug']) ? '?slug=' . urlencode($q['slug']) : '');
?>
<div class="filter__head">
    <h2 class="filter__head--title">Filters</h2>
    <a class="filter__clear<?php echo $hasFilters ? '' : ' is-muted'; ?>" href="<?php echo e($clearHref); ?>">Clear All</a>
</div>

<div class="filter__group">
    <span class="filter__group--label"><?php echo e($searchLabel); ?></span>
    <div class="filter__search">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" name="search" value="<?php echo e($q['search'] ?? ''); ?>" placeholder="Search jewellery...">
    </div>
</div>

<?php if (!empty($filterCategoryTree)): ?>
<div class="filter__group filter__group--collapsible is-open" data-filter-collapse>
    <button type="button" class="filter__group--label filter__group--toggle" data-collapse-toggle>
        Category
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <div class="filter__group--body">
        <label class="filter__cat filter__cat--all<?php echo $activeCat === '' ? ' is-active' : ''; ?>">
            <input type="radio" name="category" value="" <?php echo $activeCat === '' ? 'checked' : ''; ?>>
            <span>All Jewellery</span>
            <em>(<?php echo (int) $facetCounts['all']; ?>)</em>
        </label>

        <ul class="filter__cats">
            <?php foreach ($filterCategoryTree as $mainIndex => $main): ?>
                <?php
                // Open the group when it (or a child) is the active filter; with no
                // category chosen, the first group stays open so counts are visible.
                $childSlugs = array_column($main['children'] ?? [], 'slug');
                $groupOpen  = $activeCat === $main['slug']
                    || in_array($activeCat, $childSlugs, true)
                    || ($activeCat === '' && $mainIndex === array_key_first($filterCategoryTree));
                ?>
                <li class="filter__cats--group<?php echo $groupOpen ? ' is-open' : ''; ?>" data-filter-collapse>
                    <div class="filter__cats--row">
                        <label class="filter__cat filter__cat--main<?php echo $activeCat === $main['slug'] ? ' is-active' : ''; ?>">
                            <input type="radio" name="category" value="<?php echo e($main['slug']); ?>" <?php echo $activeCat === $main['slug'] ? 'checked' : ''; ?>>
                            <span><?php echo e($main['name']); ?></span>
                        </label>
                        <?php if (!empty($main['children'])): ?>
                            <button type="button" class="filter__cats--expand" data-collapse-toggle aria-label="Toggle <?php echo e($main['name']); ?>">
                                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"/></svg>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($main['children'])): ?>
                        <ul class="filter__cats--sub filter__group--body">
                            <?php foreach ($main['children'] as $sub): ?>
                                <li>
                                    <label class="filter__cat<?php echo $activeCat === $sub['slug'] ? ' is-active' : ''; ?>">
                                        <input type="radio" name="category" value="<?php echo e($sub['slug']); ?>" <?php echo $activeCat === $sub['slug'] ? 'checked' : ''; ?>>
                                        <span><?php echo e($sub['name']); ?></span>
                                        <em>(<?php echo (int) ($catCounts[(int) $sub['id']] ?? 0); ?>)</em>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<div class="filter__group">
    <span class="filter__group--label">Price Range</span>
    <div class="filter__range" data-price-range
         data-min="<?php echo $rangeMin; ?>" data-max="<?php echo $rangeMax; ?>">
        <div class="filter__range--slider">
            <div class="filter__range--track"></div>
            <div class="filter__range--fill" data-range-fill></div>
            <input type="range" data-range-lo min="<?php echo $rangeMin; ?>" max="<?php echo $rangeMax; ?>" step="1000" value="<?php echo $curMin; ?>" aria-label="Minimum price">
            <input type="range" data-range-hi min="<?php echo $rangeMin; ?>" max="<?php echo $rangeMax; ?>" step="1000" value="<?php echo $curMax; ?>" aria-label="Maximum price">
        </div>
        <div class="filter__range--values">
            <span data-range-out-lo><?php echo shop_filter_money($curMin); ?></span>
            <span data-range-out-hi><?php echo shop_filter_money($curMax); ?></span>
        </div>
        <input type="hidden" name="min_price" value="<?php echo e($q['min_price'] ?? ''); ?>" data-range-field-lo>
        <input type="hidden" name="max_price" value="<?php echo e($q['max_price'] ?? ''); ?>" data-range-field-hi>
    </div>
    <div class="filter__chips">
        <?php foreach ($pricePresets as $p): ?>
            <?php $on = (string) ($q['min_price'] ?? '') === (string) $p['min'] && (string) ($q['max_price'] ?? '') === (string) $p['max']; ?>
            <button type="button" class="filter__chip<?php echo $on ? ' is-active' : ''; ?>"
                    data-price-preset data-preset-min="<?php echo e((string) $p['min']); ?>" data-preset-max="<?php echo e((string) $p['max']); ?>">
                <?php echo e($p['label']); ?>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!empty($filterBrands)): ?>
<div class="filter__group">
    <span class="filter__group--label">Brand</span>
    <select name="brand" class="form-select">
        <option value="">All Brands</option>
        <?php foreach ($filterBrands as $b): ?>
            <option value="<?php echo (int) $b['id']; ?>" <?php echo (($q['brand'] ?? '') == $b['id']) ? 'selected' : ''; ?>><?php echo e($b['name']); ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>

<div class="filter__group filter__group--last">
    <span class="filter__group--label">Availability</span>
    <?php foreach ($availability as $a): ?>
        <label class="filter__check">
            <input type="checkbox" name="<?php echo e($a['name']); ?>" value="1" <?php echo !empty($q[$a['name']]) ? 'checked' : ''; ?>>
            <span class="filter__check--box"></span>
            <span class="filter__check--text"><?php echo e($a['label']); ?></span>
            <em>(<?php echo (int) $a['count']; ?>)</em>
        </label>
    <?php endforeach; ?>
</div>

<noscript><button type="submit" class="btn btn-outline-primary filter__apply">Apply</button></noscript>
