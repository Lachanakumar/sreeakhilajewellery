<?php
require_once __DIR__ . '/_bootstrap.php';

$q = trim($_GET['q'] ?? $_POST['q'] ?? '');
if (mb_strlen($q) < 2) {
    json_response(true, '', ['query' => $q, 'results' => []]);
}

$result = getProducts(['search' => $q, 'limit' => 8, 'sort' => 'latest']);
$results = [];
foreach ($result['items'] as $p) {
    $results[] = [
        'id'         => $p['id'],
        'name'       => $p['name'],
        'url'        => 'product-details.php?id=' . $p['id'],
        'image'      => $p['image'],
        'category'   => $p['category'],
        'price_html' => formatPrice($p['price']),
    ];
}

json_response(true, '', [
    'query'   => $q,
    'total'   => $result['total'],
    'results' => $results,
]);
