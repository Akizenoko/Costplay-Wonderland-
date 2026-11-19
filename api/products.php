<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$like = '%' . $q . '%';
$sql = "
    SELECT p.product_id, p.product_name, p.character_name, p.main_image,
           p.listing_type, p.sale_price, p.rental_price_per_day,
           m.title AS franchise, s.store_name
    FROM products p
    JOIN stores s ON s.store_id = p.store_id
    LEFT JOIN media_titles m ON m.media_id = p.media_id
    WHERE p.is_active = 1
      AND (p.product_name LIKE ? OR p.character_name LIKE ? OR m.title LIKE ?)
    ORDER BY p.view_count DESC, p.created_at DESC
    LIMIT 8
";

$results = db_query($conn, $sql, 'sss', [$like, $like, $like]);

echo json_encode([
    'results' => array_map(function($row) {
        return [
            'id'             => (int)$row['product_id'],
            'name'           => $row['product_name'],
            'character'      => $row['character_name'],
            'image'          => $row['main_image'] ?: 'https://picsum.photos/60/60?random=' . $row['product_id'],
            'franchise'      => $row['franchise'],
            'store'          => $row['store_name'],
            'listing_type'   => $row['listing_type'],
            'sale_price'     => $row['sale_price'] ? (float)$row['sale_price'] : null,
            'rental_rate'    => $row['rental_price_per_day'] ? (float)$row['rental_price_per_day'] : null,
            'url'            => '/product.php?id=' . $row['product_id']
        ];
    }, $results)
]);
