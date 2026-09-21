<?php
require_once 'includes/products.php';
require_once 'includes/cart-functions.php';

$compareItems = getCompareItems();
require_once 'includes/config.php';
$pageMetaTitle = SITE_NAME . ' - Compare';
$pageTitle = 'Compare Products';
$breadcrumbs = [['name'=>'Home','url'=>'index.php'],['name'=>'Compare','url'=>'']];
include 'includes/header.php';
?>

<main class="main__content_wrapper">
    <?php include 'includes/page-title.php'; ?>

    <section class="compare__section section--padding">
        <div class="container-fluid">
            <?php if (empty($compareItems)): ?>
                <div class="text-center py-5">
                    <h3>No products to compare</h3>
                    <p class="mt-3">Add products to compare from the product listing.</p>
                    <a href="products.php" class="primary__btn mt-3">Browse Products</a>
                </div>
            <?php else: ?>
            <h2 class="cart__title mb-30">Compare Products</h2>
            <div class="compare__table" style="overflow-x:auto;">
                <table class="cart__table--inner" style="width:100%;">
                    <thead class="cart__table--header">
                        <tr class="cart__table--header__items">
                            <th class="cart__table--header__list" style="min-width:150px;">Feature</th>
                            <?php foreach ($compareItems as $item): ?>
                            <th class="cart__table--header__list text-center" style="min-width:200px;"><?php echo htmlspecialchars($item['name']); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="cart__table--body">
                        <tr class="cart__table--body__items">
                            <td class="cart__table--body__list"><strong>Image</strong></td>
                            <?php foreach ($compareItems as $item): ?>
                            <td class="cart__table--body__list text-center"><a href="product-details.php?id=<?php echo $item['id']; ?>"><img src="<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="max-width:120px;"></a></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="cart__table--body__items">
                            <td class="cart__table--body__list"><strong>Price</strong></td>
                            <?php foreach ($compareItems as $item): ?>
                            <td class="cart__table--body__list text-center"><span class="current__price"><?php echo formatPrice($item['price']); ?></span> <del class="old__price"><?php echo formatPrice($item['old_price']); ?></del></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="cart__table--body__items">
                            <td class="cart__table--body__list"><strong>Category</strong></td>
                            <?php foreach ($compareItems as $item): ?>
                            <td class="cart__table--body__list text-center"><?php echo htmlspecialchars($item['category']); ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="cart__table--body__items">
                            <td class="cart__table--body__list"><strong>Rating</strong></td>
                            <?php foreach ($compareItems as $item): ?>
                            <td class="cart__table--body__list text-center"><?php echo $item['rating']; ?>/5 Stars</td>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="cart__table--body__items">
                            <td class="cart__table--body__list"><strong>Description</strong></td>
                            <?php foreach ($compareItems as $item): ?>
                            <td class="cart__table--body__list text-center" style="font-size:13px;"><?php echo htmlspecialchars($item['description']); ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="cart__table--body__items">
                            <td class="cart__table--body__list"><strong>Actions</strong></td>
                            <?php foreach ($compareItems as $item): ?>
                            <td class="cart__table--body__list text-center">
                                <div class="d-flex justify-content-center" style="gap:8px; flex-wrap:wrap;">
                                    <a class="primary__btn" href="cart-actions.php?action=add_to_cart&product_id=<?php echo $item['id']; ?>&redirect=compare.php" style="padding:6px 12px; font-size:12px;">Add to Cart</a>
                                    <a class="cart__remove--btn" href="cart-actions.php?action=remove_from_compare&product_id=<?php echo $item['id']; ?>&redirect=compare.php" style="padding:6px 12px; font-size:12px; color:red;">Remove</a>
                                </div>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
